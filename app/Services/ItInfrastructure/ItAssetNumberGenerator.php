<?php

namespace App\Services\ItInfrastructure;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ItAssetNumberGenerator
{
    public function next(string $company, int $year): string
    {
        return DB::transaction(function () use ($company, $year): string {
            $sequence = DB::table('it_asset_number_sequences')->where('id', 1)->lockForUpdate()->firstOrFail();
            if ($sequence->last_number >= 99999) {
                throw ValidationException::withMessages(['asset_number' => 'Nomor urut aset 5 digit sudah mencapai 99999.']);
            }
            $number = $sequence->last_number + 1;
            DB::table('it_asset_number_sequences')->where('id', 1)->update(['last_number' => $number]);

            return sprintf('%05d/%s/IT/%04d', $number, $company, $year);
        });
    }

    public function reserve(string $number): void
    {
        if (! preg_match('~\A([0-9]{5})/[A-Z][A-Z0-9]*/{1,2}IT/[0-9]{4}\z~', $number, $parts)) {
            return;
        }
        DB::transaction(function () use ($parts): void {
            $sequence = DB::table('it_asset_number_sequences')->where('id', 1)->lockForUpdate()->firstOrFail();
            if ((int) $parts[1] > $sequence->last_number) {
                DB::table('it_asset_number_sequences')->where('id', 1)->update(['last_number' => (int) $parts[1]]);
            }
        });
    }
}
