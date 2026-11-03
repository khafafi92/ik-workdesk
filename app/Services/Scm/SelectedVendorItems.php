<?php

namespace App\Services\Scm;

use App\Models\Scm\RequestItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SelectedVendorItems
{
    public static function query(User $user, string $pr = ''): Builder
    {
        return RequestItem::query()->whereHas('request', function (Builder $query) use ($user, $pr): void {
            Access::scope($query, $user)->where('kind', 'PR')
                ->whereHas('cbe', fn (Builder $cbe) => $cbe->where('status', 'final')->whereNotNull('selected_vendor_id'))
                ->when($pr !== '', fn (Builder $request) => $request->where('number', 'like', '%'.$pr.'%'));
        })->with('request.cbe')->orderByDesc('request_id')->orderBy('position')->orderBy('id');
    }

    public static function row(RequestItem $item): array
    {
        $cbe = $item->request->cbe;
        $snapshot = $cbe->snapshot;
        $frozenItem = collect($snapshot['items'] ?? [])->firstWhere('id', $item->id) ?? [];
        $quote = collect($snapshot['quotes'] ?? [])->first(fn (array $quote): bool => (int) ($quote['vendor']['id'] ?? 0) === (int) $cbe->selected_vendor_id);
        $line = collect($quote['lines'] ?? [])->firstWhere('request_item_id', $item->id);

        return ['pr' => $snapshot['request']['number'] ?? $item->request->number,
            'vendor' => $quote['vendor']['name'] ?? '', 'size' => $frozenItem['size'] ?? '',
            'model_part_number' => $frozenItem['model_part_number'] ?? '', 'material' => $frozenItem['name'] ?? '',
            'description' => $frozenItem['specification'] ?? '',
            'price' => isset($line['price_minor'], $quote['currency']) ? Decimal::money($line['price_minor'], $quote['currency']) : ''];
    }
}
