<?php

namespace App\Services\Finance;

use App\Models\FinanceRequest;
use App\Models\Scm\Procurement;
use App\Models\User;
use App\Services\Scm\Access;
use App\Services\Scm\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ApcaPurchaseRequests
{
    public function sources(User $actor): Builder
    {
        return Access::scopeMine(Procurement::where(['company' => 'APCA', 'kind' => 'PR']), $actor)
            ->whereIn('department_id', app(Requests::class)->departments($actor)->select('id'));
    }

    public function options(User $actor, string $kind): Builder
    {
        $requests = app(Requests::class);

        return $this->sources($actor)
            ->whereDoesntHave('financeRequests', fn (Builder $query) => $query->where('kind', $kind))
            ->when($kind === 'reimbursement', fn (Builder $query) => $query
                ->whereHas('financeRequests', fn (Builder $payment) => $payment->where('kind', 'payment')->where(fn (Builder $allowed) => $allowed
                    ->where(fn (Builder $own) => $requests->requestedBy($own, $actor))
                    ->orWhereIn('id', $requests->visible($actor)->select('id')))));
    }

    public function payment(Procurement $source): ?FinanceRequest
    {
        return $source->financeRequests()->where('kind', 'payment')->first();
    }

    public function fields(Procurement $source, ?FinanceRequest $payment = null): array
    {
        $lines = $payment ? $payment->data['lines'] : $source->items->map(fn ($item) => [
            ...Requests::emptyLine(), 'source_item_id' => $item->id,
            'date' => $source->date->toDateString(),
            'description' => $item->name.' · '.Decimal::quantityInput((int) $item->quantity_milli).' '.$item->unit,
            'notes' => mb_substr(implode(' · ', array_filter([$item->specification, $item->notes])), 0, 500),
        ])->all();

        return ['procurement_id' => (string) $source->id, 'company' => 'APCA', 'department_id' => (string) $source->department_id,
            'description' => $source->title, 'lines' => $lines,
            ...($payment ? ['payment_voucher_id' => (string) $payment->id, 'exchange_rate' => $payment->data['exchange_rate']] : [])];
    }

    public function refresh(Procurement $source, array $previousLines, ?FinanceRequest $payment = null): array
    {
        $fields = $this->fields($source, $payment);
        $previousKeys = [];
        foreach ($previousLines as $key => $line) {
            if (isset($line['source_item_id']) && is_numeric($line['source_item_id'])) {
                $previousKeys[(int) $line['source_item_id']] = $key;
            }
        }
        $lines = [];
        foreach ($fields['lines'] as $line) {
            $key = $previousKeys[$line['source_item_id']] ?? 'pr-'.$line['source_item_id'];
            $lines[$key] = $payment ? $line : [...$line, ...collect($previousLines[$key] ?? [])
                ->only(['date', 'amount', 'category', 'notes', 'invoice_number', 'due_on'])->all()];
        }
        $fields['lines'] = $lines;

        return $fields;
    }

    public function prepare(User $actor, FinanceRequest $document, array $input): array
    {
        $kind = $input['kind'] ?? '';
        $apca = ($input['company'] ?? '') === 'APCA' && in_array($kind, ['payment', 'reimbursement'], true);
        $legacyPayment = $document->exists && $document->company === 'APCA' && $document->kind === 'payment' && $document->procurement_id === null;
        Validator::make(['input' => $input], [
            'input.procurement_id' => [$apca && $kind === 'payment' && ! $legacyPayment ? 'required' : 'nullable', 'integer'],
            'input.lines' => 'nullable|array', 'input.lines.*' => 'array',
        ],
            ['input.procurement_id.required' => 'Pilih PR APCA sebelum menyimpan Payment Request.'])->validate();
        $sourceId = filled($input['procurement_id'] ?? null) ? (int) $input['procurement_id'] : null;
        if (($document->procurement_id !== null || $document->round) && $document->procurement_id !== $sourceId) {
            throw ValidationException::withMessages(['input.procurement_id' => 'Referensi PR sudah tercatat pada pengajuan ini.']);
        }
        if ($sourceId === null) {
            return [$input, null];
        }
        if (! $apca || ! ($source = $this->sources($actor)->with('items')->lockForUpdate()->find($sourceId))) {
            throw ValidationException::withMessages(['input.procurement_id' => 'Pilih PR APCA yang dapat Anda akses.']);
        }
        if ($source->financeRequests()->where('kind', $kind)->when($document->exists, fn ($query) => $query->whereKeyNot($document->id))->exists()) {
            throw ValidationException::withMessages(['input.procurement_id' => 'Dokumen ini sudah dibuat dari PR tersebut. Buka dokumen yang ada dari halaman PR.']);
        }
        $payment = null;
        if ($kind === 'reimbursement') {
            $payment = $this->payment($source);
            if (! $payment || ! app(Requests::class)->canUsePaymentSource($actor, $payment)) {
                throw ValidationException::withMessages(['input.procurement_id' => 'Simpan Payment Request dari PR ini terlebih dahulu.']);
            }
        }
        $fields = $this->refresh($source, $input['lines'] ?? [], $payment);

        return [[...$input, ...$fields, 'travel_document_id' => null], $source];
    }

    public function ensureApproved(FinanceRequest $record): void
    {
        $source = $record->procurement()->with('items')->lockForUpdate()->first();
        if (! $source || ! $source->submitted_at || in_array($source->status, ['draft', 'approval'], true)) {
            throw ValidationException::withMessages(['request' => 'Tunggu PR sumber disetujui sebelum mengirim pengajuan ini.']);
        }
        if ($record->kind === 'reimbursement' && $record->paymentVoucher?->status !== 'approved') {
            throw ValidationException::withMessages(['request' => 'Tunggu Payment Request terkait disetujui sebelum mengirim Reimbursement.']);
        }
        $expected = $this->fields($source, $record->kind === 'reimbursement' ? $record->paymentVoucher : null);
        $keys = $record->kind === 'payment' ? ['source_item_id', 'description']
            : ['source_item_id', 'description', 'amount_minor', 'date', 'category', 'notes'];
        $items = fn (array $rows) => array_map(fn (array $row) => collect($row)->only($keys)->sortKeys()->all(), $rows);
        if ($record->data['description'] !== $expected['description'] || $items($record->data['lines']) !== $items($expected['lines'])
            || ($record->kind === 'reimbursement' && $record->data['exchange_rate'] !== $expected['exchange_rate'])) {
            throw ValidationException::withMessages(['request' => 'Buka ulang dan simpan draft untuk memuat rincian terbaru sebelum mengirim.']);
        }
    }
}
