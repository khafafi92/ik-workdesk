<?php

namespace App\Filament\Resources\WorkTasks\Pages;

use App\Filament\Resources\WorkTasks\WorkTaskResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkTask extends EditRecord
{
    protected static string $resource = WorkTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
