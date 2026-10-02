<?php

namespace App\Notifications;

use App\Filament\Resources\AtkRequests\AtkRequestResource;
use App\Models\AtkRequestItem;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AtkRequestItemIssuedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly AtkRequestItem $requestItem,
        public readonly float $quantity,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $requestItem = $this->requestItem->loadMissing([
            'item',
            'request',
        ]);

        $quantity = rtrim(rtrim(number_format($this->quantity, 2, ',', '.'), '0'), ',');

        return FilamentNotification::make()
            ->title('Barang ATK telah diserahkan')
            ->body(sprintf(
                '%s: %s sejumlah %s %s telah diserahkan oleh GA. Silakan konfirmasi penerimaan.',
                $requestItem->request?->request_number ?? 'Permintaan ATK',
                $requestItem->item?->name ?? 'Barang ATK',
                $quantity,
                $requestItem->unit,
            ))
            ->icon('heroicon-o-truck')
            ->success()
            ->actions([
                Action::make('open')
                    ->label('Konfirmasi penerimaan')
                    ->button()
                    ->url(AtkRequestResource::getUrl('index'))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
