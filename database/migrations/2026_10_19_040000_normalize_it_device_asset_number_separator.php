<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('it_devices')->orderBy('id')->lockForUpdate()->chunkById(500, function ($devices): void {
                foreach ($devices as $device) {
                    if (! preg_match('~\A([0-9]{5}/[A-Z][A-Z0-9]*)//IT/([0-9]{4})\z~', $device->asset_number ?? '', $parts)) {
                        continue;
                    }
                    $number = $parts[1].'/IT/'.$parts[2];
                    if (DB::table('it_devices')->where('asset_number', $number)->where('id', '!=', $device->id)->exists()) {
                        throw new RuntimeException('Koreksi separator dibatalkan: nomor aset perangkat '.$device->id.' sudah dipakai perangkat lain.');
                    }
                    DB::table('it_devices')->where('id', $device->id)->update(['asset_number' => $number]);
                }
            });
        });
    }

    public function down(): void {}
};
