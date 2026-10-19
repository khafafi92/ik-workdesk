<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $sequence = DB::table('it_asset_number_sequences')->where('id', 1)->lockForUpdate()->firstOrFail();
            $last = (int) $sequence->last_number;
            $observe = function (?string $number) use (&$last): void {
                if ($number && preg_match('~\A([0-9]{1,5})/[A-Z][A-Z0-9]*/{1,2}IT/[0-9]{4}\z~i', trim($number), $parts)) {
                    $last = max($last, (int) $parts[1]);
                }
            };
            DB::table('it_devices')->orderBy('id')->chunkById(500, function ($devices) use ($observe): void {
                foreach ($devices as $device) {
                    $observe($device->asset_number);
                }
            });
            DB::table('it_inspections')->orderBy('id')->chunkById(500, function ($inspections) use ($observe): void {
                foreach ($inspections as $inspection) {
                    $observe(json_decode($inspection->device_snapshot, true)['asset_number'] ?? null);
                }
            });
            DB::table('it_asset_number_sequences')->where('id', 1)->update(['last_number' => $last]);
        });
    }

    public function down(): void {}
};
