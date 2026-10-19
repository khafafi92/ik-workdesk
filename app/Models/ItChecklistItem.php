<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ItChecklistItem extends Model
{
    protected $fillable = ['it_checklist_template_id', 'code', 'section', 'label', 'instructions', 'sort_order', 'value_type', 'unit'];

    protected $attributes = ['value_type' => 'text'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $templateIds = array_filter([$item->it_checklist_template_id, $item->getRawOriginal('it_checklist_template_id')]);
            if (ItInspection::query()->whereIn('it_checklist_template_id', $templateIds)->exists()) {
                throw ValidationException::withMessages(['item' => 'Item template yang sudah digunakan tidak dapat diubah atau ditambah.']);
            }
            if (! in_array($item->value_type, ['text', 'number', 'datetime', 'rows'], true) || blank($item->code) || blank($item->label) || blank($item->section) || blank($item->instructions) || $item->sort_order < 1) {
                throw ValidationException::withMessages(['item' => 'Definisi butir checklist tidak lengkap.']);
            }
        });
        static::deleting(function (self $item): void {
            if ($item->results()->exists() || $item->template()->firstOrFail()->inspections()->exists()) {
                throw ValidationException::withMessages(['item' => 'Item template yang sudah digunakan tidak dapat dihapus.']);
            }
        });
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ItChecklistTemplate::class, 'it_checklist_template_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ItInspectionResult::class);
    }
}
