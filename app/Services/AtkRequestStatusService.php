<?php

namespace App\Services;

use App\Models\AtkRequest;
use App\Models\AtkRequestHistory;

class AtkRequestStatusService
{
    public function recalculate(AtkRequest $request): AtkRequest
    {
        $request->loadMissing('items');

        if ($request->status === 'cancelled') {
            return $request;
        }

        $items = $request->items->where('status', '!=', 'cancelled');
        $status = 'submitted';

        if ($items->isNotEmpty() && $items->every(fn ($item): bool => (float) $item->qty_received >= (float) $item->qty_requested)) {
            $status = 'completed';
        } elseif ($items->contains(fn ($item): bool => (float) $item->qty_issued > 0 || (float) $item->qty_received > 0)) {
            $status = 'partially_fulfilled';
        } elseif ($items->contains(fn ($item): bool => $item->status !== 'pending')) {
            $status = 'processing';
        }

        $changes = ['status' => $status];

        if ($status === 'processing' && ! $request->processing_at) {
            $changes['processing_at'] = now();
        }

        if ($status === 'completed' && ! $request->completed_at) {
            $changes['completed_at'] = now();
        }

        if ($request->status !== $status) {
            $request->update($changes);
            AtkRequestHistory::create([
                'atk_request_id' => $request->id,
                'action' => 'request_status_changed',
                'description' => "Request status changed to {$status}.",
                'meta' => ['status' => $status],
                'performed_by' => auth()->id(),
            ]);
        }

        return $request->fresh('items');
    }
}
