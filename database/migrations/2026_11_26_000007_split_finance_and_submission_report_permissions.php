<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FINANCE = [
        'finance.pv.create' => 'Buat Payment Voucher KPMOG',
        'finance.payreq.create' => 'Buat Payment Request APCA',
        'finance.er.create' => 'Buat Expense Report KPMOG',
        'finance.reimbursement.create' => 'Buat Form Reimbursement APCA',
    ];

    private const REPORTS = [
        'report.submissions' => 'Lihat Laporan Pengajuan',
        'report.submissions.export' => 'Unduh CSV Laporan Pengajuan',
    ];

    public function up(): void
    {
        $now = now();
        foreach (['Finance' => self::FINANCE, 'Reports' => self::REPORTS] as $module => $permissions) {
            foreach ($permissions as $code => $name) {
                DB::table('permissions')->insertOrIgnore(compact('code', 'name', 'module') + [
                    'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        $legacy = DB::table('permissions')->where('code', 'finance.requests.create')->where('is_active', true)->value('id');
        $financeIds = DB::table('permissions')->whereIn('code', array_keys(self::FINANCE))->pluck('id');
        $reportIds = DB::table('permissions')->whereIn('code', array_keys(self::REPORTS))->pluck('id');
        $reportSources = DB::table('permissions')->where('is_active', true)->whereIn('code', [
            'finance.requests.create', ...array_keys(self::FINANCE), 'scm.request', 'scm.process', 'scm.process-apca', 'scm.process-kpmog', 'ga.process',
            'travel.spd.create', 'travel.spd.view', 'travel.spd.manage', 'travel.lpj.create', 'travel.lpj.view', 'travel.lpj.manage', 'travel.approve',
        ])->pluck('id');

        foreach (['permission_role' => 'role_id', 'permission_user' => 'user_id'] as $pivot => $key) {
            if ($legacy) {
                $this->grant($pivot, $key, DB::table($pivot)->where('permission_id', $legacy)->pluck($key), $financeIds);
            }
            $this->grant($pivot, $key, DB::table($pivot)->whereIn('permission_id', $reportSources)->distinct()->pluck($key), $reportIds);
        }

        $assigned = DB::table('finance_request_approvals')->distinct()->pluck('user_id')
            ->merge(DB::table('travel_approvals')->distinct()->pluck('user_id'))
            ->merge(DB::table('scm_request_approvals')->where('role', '!=', 'requested')->distinct()->pluck('user_id'))
            ->unique();
        $this->grant('permission_user', 'user_id', $assigned, $reportIds);
    }

    private function grant(string $table, string $key, Collection $owners, Collection $permissions): void
    {
        $now = now();
        foreach ($owners->chunk(100) as $chunk) {
            $rows = $chunk->crossJoin($permissions)->map(fn (array $pair) => [
                $key => $pair[0], 'permission_id' => $pair[1], 'created_at' => $now, 'updated_at' => $now,
            ])->all();
            if ($rows) {
                DB::table($table)->insertOrIgnore($rows);
            }
        }
    }

    public function down(): void {}
};
