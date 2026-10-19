<?php

namespace App\Models;

use App\Enums\ItDeviceCategory;
use App\Enums\ItDeviceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ItChecklistTemplate extends Model
{
    protected $fillable = ['code', 'version', 'name', 'category', 'device_type', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['category' => ItDeviceCategory::class, 'device_type' => ItDeviceType::class, 'version' => 'integer', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $template): void {
            if (! $template->device_type || $template->category !== $template->device_type->category() || $template->version < 1 || blank($template->code) || blank($template->name)) {
                throw ValidationException::withMessages(['template' => 'Kategori, jenis, kode, nama, dan versi template harus sesuai.']);
            }
            if ($template->exists && $template->isDirty(['code', 'version', 'name', 'category', 'device_type']) && $template->inspections()->exists()) {
                throw ValidationException::withMessages(['template' => 'Buat versi baru untuk mengubah template yang sudah digunakan.']);
            }
        });
        static::deleting(function (self $template): void {
            if ($template->inspections()->exists() || $template->items()->exists()) {
                throw ValidationException::withMessages(['template' => 'Template yang memiliki item atau pemeriksaan tidak dapat dihapus.']);
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItChecklistItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(ItInspection::class);
    }
}
