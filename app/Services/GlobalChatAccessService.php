<?php

namespace App\Services;

use App\Models\PermitCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class GlobalChatAccessService
{
    public function companiesFor(User $user): Builder
    {
        $user->loadMissing('employee.permitCompanies');

        $companyIds = $user->employee?->permitCompanies
            ->modelKeys();
        $companyIds ??= [];
        $hasGlobalAccess = $user->is_admin || $user->hasRole('system-admin');

        return PermitCompany::query()
            ->where('is_active', true)
            ->when(! $hasGlobalAccess, fn (Builder $query): Builder => $query->whereKey($companyIds))
            ->orderBy('name');
    }

    public function canAccessCompany(User $user, PermitCompany|int $company): bool
    {
        $companyId = $company instanceof PermitCompany
            ? (int) $company->getKey()
            : $company;

        return $this->companiesFor($user)->whereKey($companyId)->exists();
    }
}
