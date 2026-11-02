<?php

namespace App\Services\Scm;

use App\Models\Scm\Procurement;
use App\Models\Scm\ProjectNumbering;
use App\Models\Scm\RequestNumber;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestNumbering
{
    public function reserve(User $actor, string $kind, string $company = 'APCA', string $needType = 'HO', ?int $projectNumberingId = null): RequestNumber
    {
        abort_unless(Access::has($actor, 'scm.request') && in_array($kind, ['PR', 'SR'], true) && array_key_exists($company, config('scm.companies')), 403);
        abort_unless(in_array($needType, ['HO', 'PROJECT'], true), 422);

        if ($needType === 'PROJECT') {
            return $this->reserveProject($actor, $kind, $company, $projectNumberingId);
        }

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
                'company' => $company, 'kind' => $kind, 'need_type' => 'HO', 'requester_id' => $actor->id]);
        }, 3);
    }

    private function reserveProject(User $actor, string $kind, string $company, ?int $id): RequestNumber
    {
        return DB::transaction(function () use ($actor, $kind, $company, $id): RequestNumber {
            $master = ProjectNumbering::query()->lockForUpdate()->find($id);
            if (! $master || ! $master->is_active) {
                throw ValidationException::withMessages(['project_numbering_id' => 'Pilih master nomor Project yang aktif.']);
            }
            $date = today();
            $initial = 0;
            $pattern = '~^(\d+)/'.preg_quote($master->code, '~').'/'.$kind.'/[IVX]+/'.$date->year.'$~';
            foreach (Procurement::where('kind', $kind)->where('need_type', 'PROJECT')->where('number', 'like', '%/'.$master->code.'/'.$kind.'/%/'.$date->year)->pluck('number') as $number) {
                if (preg_match($pattern, $number, $match)) {
                    $initial = max($initial, (int) $match[1]);
                }
            }
            $scope = ['project_numbering_id' => $master->id, 'kind' => $kind, 'year' => $date->year];
            DB::table('scm_project_number_sequences')->insertOrIgnore([...$scope, 'last_number' => $initial]);
            $sequence = DB::table('scm_project_number_sequences')->where($scope)->lockForUpdate()->firstOrFail();
            $next = max((int) $sequence->last_number, $initial) + 1;
            DB::table('scm_project_number_sequences')->where('id', $sequence->id)->update(['last_number' => $next]);
            $month = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$date->month];

            return RequestNumber::create(['number' => str_pad((string) $next, 3, '0', STR_PAD_LEFT).'/'.$master->code.'/'.$kind.'/'.$month.'/'.$date->year,
                'company' => $company, 'kind' => $kind, 'need_type' => 'PROJECT', 'project_numbering_id' => $master->id, 'requester_id' => $actor->id]);
        }, 3);
    }
}
