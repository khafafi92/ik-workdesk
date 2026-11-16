<?php

namespace App\Services\Travel;

use App\Models\TravelDocument;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TravelDocumentNumbering
{
    public function resolve(string $company, string $issuedOn, string $input, ?TravelDocument $document = null, string $kind = 'spd'): string
    {
        if (! in_array($kind, ['spd', 'lpj'], true)) {
            throw new \InvalidArgumentException('Jenis dokumen tidak dikenal.');
        }
        $label = strtoupper($kind);
        $pattern = '~^(?!0+/)([0-9]{3,18})/'.$label.'/(0[1-9]|1[0-2])-([1-9][0-9]{3})$~D';
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('SPD numbering must share the document transaction.');
        }
        $date = CarbonImmutable::parse($issuedOn);
        if ($date->year < 1000 || $date->year > 9999) {
            throw ValidationException::withMessages(['issued_on' => 'Tahun Tgl Issue harus terdiri dari empat digit.']);
        }
        if ($document && trim($input) === $document->number && ! preg_match($pattern, strtoupper($document->number))) {
            return $document->number;
        }
        $number = strtoupper(trim($input));
        if ($document && $number === '') {
            $number = $document->number;
        }
        $newPeriod = $document && $number === $document->number && preg_match($pattern, $number)
            && ($company !== $document->company || $date->format('Y-m') !== $document->issued_on->format('Y-m'));
        if ($number === '' || $newPeriod) {
            $next = $this->sequence($company, $date->year, null, $kind, $pattern);

            return str_pad((string) $next, 3, '0', STR_PAD_LEFT).'/'.$label.'/'.$date->format('m-Y');
        }
        if (! preg_match($pattern, $number, $parts)) {
            if ($document && $number === $document->number) {
                return $number;
            }
            throw ValidationException::withMessages(['number' => 'Gunakan format 001/'.$label.'/01-2026: nomor urut/'.$label.'/bulan-tahun.']);
        }
        $this->sequence($company, (int) $parts[3], (int) $parts[1], $kind, $pattern);

        return $number;
    }

    private function sequence(string $company, int $year, ?int $manual, string $kind, string $pattern): int
    {
        $table = $kind === 'spd' ? 'travel_spd_number_sequences' : 'travel_lpj_number_sequences';
        $scope = ['company' => $company, 'year' => $year];
        DB::table($table)->insertOrIgnore([...$scope, 'last_number' => 0]);
        $sequence = DB::table($table)->where($scope)->lockForUpdate()->firstOrFail();
        $largest = (int) $sequence->last_number;
        foreach (TravelDocument::where('kind', $kind)->where('company', $company)->whereRaw('LOWER(number) LIKE ?', ['%/'.$kind.'/%-'.$year])->pluck('number') as $number) {
            if (preg_match($pattern, strtoupper(trim($number)), $parts)) {
                $largest = max($largest, (int) $parts[1]);
            }
        }
        $next = $manual === null ? $largest + 1 : max($largest, $manual);
        if ($next > 999999999999999999) {
            throw ValidationException::withMessages(['number' => 'Urutan nomor SPD melampaui batas angka yang didukung.']);
        }
        DB::table($table)->where('id', $sequence->id)->update(['last_number' => $next]);

        return $next;
    }
}
