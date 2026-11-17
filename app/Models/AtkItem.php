<?php

namespace App\Models;

use App\Models\Scm\RequestItem;
use App\Services\Inventory\InventoryCatalogue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class AtkItem extends Model
{
    protected $fillable = ['code', 'name', 'size', 'category', 'atk_category_id', 'unit', 'atk_unit_id', 'current_stock', 'actual_stock', 'minimum_stock', 'is_active', 'inventory_item_id'];

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            if ($item->exists && $item->inventory_item_id && $item->isDirty('unit')
                && (RequestItem::where('inventory_item_id', $item->inventory_item_id)->exists()
                    || InventoryMovement::whereIn('balance_id', InventoryBalance::where('inventory_item_id', $item->inventory_item_id)->select('id'))->exists()
                    || $item->requestItems()->exists())) {
                throw ValidationException::withMessages(['unit' => 'Satuan barang yang sudah dipakai transaksi tidak dapat diubah.']);
            }
        });
        static::saved(function (self $item): void {
            if (! $item->inventory_item_id || $item->wasChanged(['name', 'category', 'unit', 'size', 'is_active'])) {
                app(InventoryCatalogue::class)->syncAtk($item);
            }
        });
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

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
