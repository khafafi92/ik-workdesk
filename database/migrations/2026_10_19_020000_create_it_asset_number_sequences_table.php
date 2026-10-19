<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_asset_number_sequences', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('last_number');
        });
        $last = 0;
        $observe = function (?string $number) use (&$last): void {
            if ($number && preg_match('~\A([0-9]{1,5})/[A-Z][A-Z0-9]*//IT/[0-9]{4}\z~i', trim($number), $parts)) {
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
        DB::table('it_asset_number_sequences')->insert(['id' => 1, 'last_number' => $last]);
    }

    public function down(): void
    {
        Schema::dropIfExists('it_asset_number_sequences');
    }
};
