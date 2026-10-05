<?php

use App\Models\PermitCompany;
use App\Services\GlobalChatAccessService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('global-chat.company.{companyId}', function ($user, int $companyId): bool {
    return app(GlobalChatAccessService::class)
        ->canAccessCompany($user, $companyId)
        && PermitCompany::query()
            ->whereKey($companyId)
            ->where('is_active', true)
            ->exists();
});
