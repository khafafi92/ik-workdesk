<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AtkItem extends Model
{
    protected $fillable = ['code', 'name', 'size', 'category', 'atk_category_id', 'unit', 'atk_unit_id', 'current_stock', 'actual_stock', 'minimum_stock', 'is_active'];

    protected function casts(): array
    {
        return ['current_stock' => 'decimal:2', 'actual_stock' => 'decimal:2', 'minimum_stock' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function requestItems(): HasMany
    {
        return $this->hasMany(AtkRequestItem::class);
    }

    public function categoryMaster(): BelongsTo
    {
        return $this->belongsTo(AtkCategory::class, 'atk_category_id');
    }

    public function unitMaster(): BelongsTo
    {
        return $this->belongsTo(AtkUnit::class, 'atk_unit_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(AtkStockMovement::class);
    }

    public function departmentBalances(): HasMany
    {
        return $this->hasMany(AtkDepartmentBalance::class);
    }

    public function departmentMovements(): HasMany
    {
        return $this->hasMany(AtkDepartmentStockMovement::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(AtkUsageTransaction::class);
    }
}
