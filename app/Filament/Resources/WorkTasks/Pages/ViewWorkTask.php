<?php

namespace App\Filament\Resources\WorkTasks\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\WorkTasks\WorkTaskResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkTask extends ViewRecord
{
    protected static string $resource = WorkTaskResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (
            WorkTaskResource::canEdit($this->record)
            && ! $this->record->canBeApprovedBy(auth()->user())
        ) {
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
            Action::make('approveLegalTask')
                ->label('Approve for Legal')
                ->icon('heroicon-o-shield-check')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Approve task?')
                ->modalDescription('After it\'s done, the task can be done by the purpose department.')
                ->visible(fn (): bool => $this->record->canBeApprovedBy(auth()->user()))
                ->action(function (): void {
                    $this->record->approveLegalTask(auth()->user());

                    Notification::make()
                        ->title('Task successfully proofed')
                        ->body('Task can now be done by the purpose department.')
                        ->success()
                        ->send();

                    $this->redirect(WorkTaskResource::getUrl('index'));
                }),

            Action::make('rejectLegalTask')
                ->label('Reject to Legal')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Reject task?')
                ->modalDescription('Task is unworkable. The reason for rejection will be seen by the register and department of destinations.')
                ->schema([
                    Textarea::make('rejection_reason')
                        ->label('Reason for Rejection')
                        ->required()
                        ->rows(4)
                        ->maxLength(2000),
                ])
                ->visible(fn (): bool => $this->record->canBeApprovedBy(auth()->user()))
                ->action(function (array $data): void {
                    $this->record->rejectLegalTask(
                        auth()->user(),
                        $data['rejection_reason']
                    );

                    Notification::make()
                        ->title('Task has been rejected')
                        ->body('Decisions and grounds for rejection are noted.')
                        ->danger()
                        ->send();

                    $this->redirect(WorkTaskResource::getUrl('index'));
                }),

            Action::make('openCollaborationRoom')
                ->label('Open Collaboration Room')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('info')
                ->url(
                    fn (): string => TicketResource::getUrl(
                        'view',
                        ['record' => $this->record->ticket_id]
                    )
                )
                ->visible(fn (): bool => filled($this->record->ticket_id)),

            Action::make('claimTask')
                ->label('Get the Task')
                ->icon('heroicon-o-hand-raised')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Take this Work Log?')
                ->modalDescription(
                    'You will be assigned as PIC and can update status, progress, start time, and notes.'
                )
                ->visible(
                    fn (): bool => $this->record->canBeClaimedBy(auth()->user())
                )
                ->action(function (): void {
                    abort_unless(
                        $this->record->fresh()->canBeClaimedBy(auth()->user()),
                        403,
                        'Log Work already has PIC or can\'t you take.'
                    );

                    $this->record->update([
                        'employee_id' => auth()->user()->employee->id,
                    ]);

                    Notification::make()
                        ->title('Task successfully retrieved')
                        ->body('You are now the PIC Work Log of this.')
                        ->success()
                        ->send();

                    $this->redirect(
                        WorkTaskResource::getUrl(
                            'edit',
                            ['record' => $this->record]
                        )
                    );
                }),

            Action::make('markAsDone')
                ->label('Mark as Done')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Finish Log Work')
                ->modalDescription(
                    'Log work will be marked complete and Service Desk status will be updated. Make sure PIC/examination is specified. For the collaboratetive job, confirm Done by the register will complete all related Log work.'
                )
                ->visible(
                    fn (): bool => $this->record->status !== 'done'
                        && $this->record->canBeCompletedBy(auth()->user())
                )
                ->action(function (): void {
                    abort_unless(
                        $this->record->canBeCompletedBy(auth()->user()),
                        403,
                        'For a collaboratetive job, only a requester as a lead or System Administrator who can complete the Log Work.'
                    );

                    if (blank($this->record->employee_id)) {
                        Notification::make()
                            ->title('PIC/execution not specified')
                            ->body('Have the department manager set the PIC before Work Log is completed.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $this->record->update(['status' => 'done']);

                    Notification::make()
                        ->title('Log Work complete')
                        ->body('Service Desk status has been updated.')
                        ->success()
                        ->send();

                    $this->refreshFormData([
                        'status',
                        'progress_percent',
                        'completed_at',
                        'completed_by_user_id',
                    ]);
                }),

            EditAction::make()
                ->visible(
                    fn (): bool => WorkTaskResource::canEdit($this->record)
                ),

            DeleteAction::make()
                ->visible(
                    fn (): bool => WorkTaskResource::canDelete($this->record)
                )
                ->before(function (): void {
                    abort_unless(
                        WorkTaskResource::canDelete($this->record),
                        403,
                        'This log work could not be deleted.'
                    );
                }),
        ];
    }
}
