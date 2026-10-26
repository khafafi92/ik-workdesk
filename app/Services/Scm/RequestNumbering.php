<?php

namespace App\Services\Scm;

use App\Models\Scm\Procurement;
use App\Models\Scm\RequestNumber;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RequestNumbering
{
    public function reserve(User $actor, string $kind, string $company = 'APCA'): RequestNumber
    {
        abort_unless(Access::has($actor, 'scm.request') && in_array($kind, ['PR', 'SR'], true) && array_key_exists($company, config('scm.companies')), 403);

        return DB::transaction(function () use ($actor, $kind, $company): RequestNumber {
            $date = today();
            $year = $date->year;
            $initial = 0;
            $prefix = config('scm.companies.'.$company.'.number_prefix');
            foreach (Procurement::where('company', $company)->where('kind', $kind)->where('number', 'like', '%/'.$prefix.'/'.$kind.'/%/'.$year)->pluck('number') as $number) {
                if (preg_match('~^(\d+)/'.preg_quote($prefix, '~').'/'.$kind.'/[IVX]+/'.$year.'$~', $number, $match)) {
                    $initial = max($initial, (int) $match[1]);
                }
            }
            DB::table('scm_number_sequences')->insertOrIgnore(['company' => $company, 'kind' => $kind, 'year' => $year, 'last_number' => $initial]);
            $sequence = DB::table('scm_number_sequences')->where('company', $company)->where('kind', $kind)->where('year', $year)->lockForUpdate()->firstOrFail();
            $next = max((int) $sequence->last_number, $initial) + 1;
            DB::table('scm_number_sequences')->where('id', $sequence->id)->update(['last_number' => $next]);
            $month = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$date->month];

            return RequestNumber::create(['number' => str_pad((string) $next, 3, '0', STR_PAD_LEFT).'/'.$prefix.'/'.$kind.'/'.$month.'/'.$year,
                'company' => $company, 'kind' => $kind, 'requester_id' => $actor->id]);
        }, 3);
    }
}
