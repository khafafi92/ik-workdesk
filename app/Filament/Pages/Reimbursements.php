<?php

namespace App\Filament\Pages;

class Reimbursements extends FinanceRequests
{
    protected static ?string $company = null;

    protected static string $kind = 'reimbursement';

    protected static ?string $slug = 'finance/reimbursements';

    protected static ?string $title = 'Reimbursement';

    protected static ?int $navigationSort = 11;
}
