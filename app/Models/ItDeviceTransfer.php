<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ItDeviceTransfer extends Model
{
    public $timestamps = false;

    protected $fillable = ['it_device_id', 'actor_id', 'from_snapshot', 'to_snapshot', 'actor_snapshot', 'transferred_on', 'notes'];

    protected function casts(): array
    {
        return ['from_snapshot' => 'array', 'to_snapshot' => 'array', 'actor_snapshot' => 'array', 'transferred_on' => 'date', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw ValidationException::withMessages(['transfer' => 'Riwayat perpindahan tidak dapat diubah.']));
        static::deleting(fn () => throw ValidationException::withMessages(['transfer' => 'Riwayat perpindahan tidak dapat dihapus.']));
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(ItDevice::class, 'it_device_id')->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
