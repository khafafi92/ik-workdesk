<?php

namespace App\Filament\Pages;

class ExpenseReports extends FinanceRequests
{
    protected static string $kind = 'expense_report';

    protected static ?string $slug = 'finance/expense-reports';

    protected static ?string $title = 'Expense Report (ER)';

    protected static ?int $navigationSort = 12;
}
