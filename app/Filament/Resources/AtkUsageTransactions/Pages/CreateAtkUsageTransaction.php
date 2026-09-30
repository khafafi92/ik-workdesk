<?php

namespace App\Filament\Resources\AtkUsageTransactions\Pages;

use App\Filament\Resources\AtkUsageTransactions\AtkUsageTransactionResource;
use App\Services\AtkDepartmentStockService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateAtkUsageTransaction extends CreateRecord
{
    protected static string $resource = AtkUsageTransactionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        $user?->loadMissing('employee');
        $departmentId = $user?->employee?->department_id;

        if (! $user || ! $departmentId) {
            throw ValidationException::withMessages([
                'atk_item_id' => 'Akun Anda belum terhubung ke departemen employee.',
            ]);
        }

        return app(AtkDepartmentStockService::class)->use(
            $user->employee->department,
            (int) $data['atk_item_id'],
            (float) $data['qty_used'],
            $user->id,
            $data['purpose'],
            null,
            $data['usage_date'],
        );
    }
}
