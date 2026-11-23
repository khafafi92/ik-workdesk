<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ItInspectionEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['it_inspection_id', 'actor_id', 'action', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw ValidationException::withMessages(['event' => 'The check log could not be changed.']));
        static::deleting(fn () => throw ValidationException::withMessages(['event' => 'The check log could not be deleted.']));
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(ItInspection::class, 'it_inspection_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
