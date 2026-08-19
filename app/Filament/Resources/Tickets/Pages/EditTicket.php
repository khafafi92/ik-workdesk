<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\WorkTask;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    protected bool $isLegalResubmission = false;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->isLegalResubmission = TicketResource::canReviseRejectedLegalRequest(
            $this->record
        );

        if (! $this->isLegalResubmission) {
            return $data;
        }

        return collect($data)->only([
            'subject',
            'description',
            'legal_background',
            'legal_objective',
            'legal_desired_scheme',
            'legal_document_types',
            'attachments',
            'priority',
            'due_at',
            'permit_company_id',
            'permit_kbli_id',
            'permit_kbli_unavailable',
        ])->all();
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $this->isLegalResubmission) {
            return parent::handleRecordUpdate($record, $data);
        }

        return DB::transaction(function () use ($record, $data): Model {
            $record->update($data);

            $record->workTasks()
                ->where('approval_status', 'rejected')
                ->each(
                    fn (WorkTask $task) => $task->resubmitLegalTask(auth()->user())
                );

            return $record;
        });
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return $this->isLegalResubmission
            ? 'Permintaan Legal diperbaiki dan diajukan ulang'
            : parent::getSavedNotificationTitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(
                    fn (): bool => TicketResource::canDelete($this->record)
                )
                ->before(function (): void {
                    abort_unless(
                        TicketResource::canDelete($this->record),
                        403,
                        'Service request ini tidak dapat dihapus.'
                    );
                }),
        ];
    }
}
