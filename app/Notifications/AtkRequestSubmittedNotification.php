<?php

namespace App\Notifications;

use App\Filament\Resources\AtkRequests\AtkRequestResource;
use App\Models\AtkRequest;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AtkRequestSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly AtkRequest $request,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $request = $this->request->loadMissing([
            'department',
            'requester',
            'items.item',
        ]);

        $items = $request->items
            ->map(fn ($item): string => sprintf(
                '%s %s %s',
                $item->item?->name ?? 'Barang ATK',
                number_format((float) $item->qty_requested, 0, ',', '.'),
                $item->unit,
            ))
            ->implode(', ');

        return FilamentNotification::make()
            ->title('Permintaan ATK baru')
            ->body(sprintf(
                '%s dari %s (%s): %s.',
                $request->request_number,
                $request->requester?->name ?? 'Peminta',
                $request->department?->name ?? 'Departemen',
                $items,
            ))
            ->icon('heroicon-o-clipboard-document-list')
            ->info()
            ->actions([
                Action::make('open')
                    ->label('Lihat permintaan')
                    ->button()
                    ->url(AtkRequestResource::getUrl('index'))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
