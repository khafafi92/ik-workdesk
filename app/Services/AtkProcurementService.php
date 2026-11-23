<?php

namespace App\Services;

use App\Models\AtkItem;
use App\Models\AtkRequest;
use App\Models\AtkRequestHistory;
use App\Models\Scm\Procurement;
use App\Models\User;
use App\Services\Inventory\InventoryStock;
use App\Services\Inventory\Warehouses;
use App\Services\Scm\Access;
use App\Services\Scm\Workflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AtkProcurementService
{
    public static function canCreate(?User $actor): bool
    {
        return $actor?->isActiveForAccess() && $actor->hasPermission('atk.manage') && Access::has($actor, 'scm.request');
    }

    public function createDraft(AtkRequest $source, User $actor): Procurement
    {
        abort_unless(self::canCreate($actor), 403);

        return DB::transaction(function () use ($source, $actor): Procurement {
            $source = AtkRequest::query()->lockForUpdate()->findOrFail($source->id);
            if ($existing = $source->procurement()->first()) {
                return $existing;
            }
            if (! in_array($source->status, ['submitted', 'processing', 'partially_fulfilled'], true)) {
                throw ValidationException::withMessages(['procurement' => 'Only active office supplies requests can be used to create purchase requests.']);
            }
            $source->load(['company', 'requester', 'projectNumbering']);
            $company = $source->company?->code;
            if (! $source->company?->is_active || ! array_key_exists($company ?? '', config('scm.companies'))) {
                throw ValidationException::withMessages(['procurement' => 'Request entity must be KPMOG or APCA active before creating a purchase request.']);
            }
            $actor->loadMissing('employee');
            $departmentId = $actor->canAccessDepartment($source->department_id)
                ? $source->department_id
                : $actor->employee?->department_id;
            if (! $departmentId) {
                throw ValidationException::withMessages(['procurement' => 'Connect the PR admissions account to the department first.']);
            }
            $requestItems = $source->items()->where('status', '!=', 'cancelled')->orderBy('atk_item_id')->lockForUpdate()->get();
            $stock = AtkItem::query()->whereIn('id', $requestItems->pluck('atk_item_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $items = [];
            $quantities = [];
            $warehouse = app(InventoryStock::class)->atkWarehouse($source);
            foreach ($requestItems as $line) {
                $item = $stock->get($line->atk_item_id);
                $shortage = round(max(0, $line->outstandingRequested() - app(InventoryStock::class)->availableAtk($item, $warehouse->id)), 2);
                if ($shortage <= 0) {
                    continue;
                }
                if (app(InventoryStock::class)->availableAtk($item, app(Warehouses::class)->legacyAtk()->id) > 0) {
                    throw ValidationException::withMessages(['procurement' => 'Map old office supplies balance for '.$item->name.' to the correct entity before calculating purchase shortages.']);
                }
                if (strcasecmp(trim($line->unit), trim($item->unit)) !== 0) {
                    throw ValidationException::withMessages(['procurement' => 'Request unit '.$item->name.' different from the stock unit, match the unit before creating a purchase request.']);
                }
                $items[] = ['inventory_item_id' => $item->inventory_item_id, 'name' => $item->name, 'specification' => $item->name.(filled($item->size) ? ', '.$item->size : ''),
                    'quantity' => number_format($shortage, 2, '.', ''), 'unit' => $line->unit,
                    'notes' => $line->requester_note];
                $quantities[$line->id] = $shortage;
            }
            if ($items === []) {
                throw ValidationException::withMessages(['procurement' => 'There\'s no shortage of supplies, hand over the supplies from the main warehouse.']);
            }
            $draft = app(Workflow::class)->saveDraft($actor, [
                'company' => $company, 'kind' => 'PR', 'need_type' => $source->need_type,
                'project_numbering_id' => $source->project_numbering_id, 'department_id' => $departmentId,
                'date' => today()->toDateString(), 'title' => 'Procuring '.$source->request_number,
                'reason' => $source->purpose,
                'notes' => 'Office supplies Source: '.$source->request_number.'. Peminta: '.$source->requester->name.'. '.$source->purpose,
                'items' => $items,
            ]);
            $draft->update(['source_atk_request_id' => $source->id, 'source_atk_quantities' => $quantities]);
            foreach ($requestItems as $line) {
                if (isset($quantities[$line->id])) {
                    $line->update(['status' => 'waiting_procurement', 'waiting_procurement_at' => now()]);
                }
            }
            AtkRequestHistory::create(['atk_request_id' => $source->id, 'action' => 'pr_draft_created',
                'description' => 'Draft PR '.$draft->number.' made for lack of stock.',
                'meta' => ['procurement_id' => $draft->id, 'quantities' => $quantities], 'performed_by' => $actor->id]);
            app(Workflow::class)->audit($actor, $draft, 'draft_dari_atk', ['atk_request_id' => $source->id, 'quantities' => $quantities], $draft->id);
            app(AtkRequestStatusService::class)->recalculate($source->fresh('items'));

            return $draft->fresh();
        }, 3);
    }
}
