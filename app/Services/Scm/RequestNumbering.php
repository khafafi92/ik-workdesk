<?php

namespace App\Services\Scm;

use App\Models\Scm\Procurement;
use App\Models\Scm\RequestNumber;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RequestNumbering
{
    public function reserve(User $actor, string $kind): RequestNumber
    {
        abort_unless(Access::has($actor, 'scm.request') && in_array($kind, ['PR', 'SR'], true), 403);

        return DB::transaction(function () use ($actor, $kind): RequestNumber {
            $date = today();
            $year = $date->year;
            $initial = 0;
            foreach (Procurement::where('kind', $kind)->where('number', 'like', '%/APCA-HO/'.$kind.'/%/'.$year)->pluck('number') as $number) {
                if (preg_match('~^(\d+)/APCA-HO/'.$kind.'/[IVX]+/'.$year.'$~', $number, $match)) {
                    $initial = max($initial, (int) $match[1]);
                }
            }
            DB::table('scm_number_sequences')->insertOrIgnore(['kind' => $kind, 'year' => $year, 'last_number' => $initial]);
            $sequence = DB::table('scm_number_sequences')->where('kind', $kind)->where('year', $year)->lockForUpdate()->firstOrFail();
            $next = max((int) $sequence->last_number, $initial) + 1;
            DB::table('scm_number_sequences')->where('id', $sequence->id)->update(['last_number' => $next]);
            $month = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$date->month];

            return RequestNumber::create(['number' => str_pad((string) $next, 3, '0', STR_PAD_LEFT).'/APCA-HO/'.$kind.'/'.$month.'/'.$year,
                'kind' => $kind, 'requester_id' => $actor->id]);
        }, 3);
    }
}
