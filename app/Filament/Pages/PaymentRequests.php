<?php

namespace App\Filament\Pages;

class PaymentRequests extends FinanceRequests
{
    protected static ?string $company = 'APCA';

    protected static ?string $slug = 'finance/payment-requests';

    protected static ?string $title = 'Payment Request';

    protected static ?int $navigationSort = 9;
}
