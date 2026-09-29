<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ltro_activity_logs';

    protected $guarded = ['id'];

    public static function write(string $action, string $module, string $description, ?User $user = null): self
    {
        $user ??= auth()->user();

        return self::query()->create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
