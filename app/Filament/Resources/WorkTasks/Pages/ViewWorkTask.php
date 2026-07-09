<?php

namespace App\Filament\Resources\WorkTasks\Pages;

use App\Filament\Resources\WorkTasks\WorkTaskResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkTask extends ViewRecord
{
    protected static string $resource = WorkTaskResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        if (WorkTaskResource::canEdit($this->record)) {
            $this->redirect(
                WorkTaskResource::getUrl(
                    'edit',
                    ['record' => $this->record]
                )
            );
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(
                    fn (): bool =>
                        WorkTaskResource::canEdit($this->record)
                ),

            DeleteAction::make()
                ->visible(
                    fn (): bool =>
                        WorkTaskResource::canDelete($this->record)
                )
                ->before(function (): void {
                    abort_unless(
                        WorkTaskResource::canDelete($this->record),
                        403,
                        'Work log ini tidak dapat dihapus.'
                    );
                }),
        ];
    }
}
