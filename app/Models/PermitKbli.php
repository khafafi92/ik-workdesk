<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class PermitKbli extends Model
{
    protected $fillable = [
        'permit_company_id',
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (PermitKbli $kbli): void {
            $kbli->code = trim((string) $kbli->code);
            $kbli->name = trim((string) $kbli->name);

            if (! $kbli->permit_company_id || $kbli->code === '' || $kbli->name === '') {
                return;
            }

            $duplicateExists = static::query()
                ->where('permit_company_id', $kbli->permit_company_id)
                ->where('code', $kbli->code)
                ->where('name', $kbli->name)
                ->when($kbli->exists, fn ($query) => $query->whereKeyNot($kbli))
                ->exists();

            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'name' => 'Nomor dan nama KBLI yang sama sudah terdaftar pada Permit Company tersebut.',
                ]);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(PermitCompany::class, 'permit_company_id');
    }
}
