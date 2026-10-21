<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CheckSecurityReadiness extends Command
{
    protected $signature = 'security:check {--production : Periksa persyaratan deployment produksi}';

    protected $description = 'Memeriksa konfigurasi keamanan dan hak koneksi database tanpa mengubah data';

    public function handle(): int
    {
        $production = $this->option('production') || config('app.env') === 'production';
        $checks = [
            'Debug dinonaktifkan' => config('app.debug') === false,
            'APP_KEY tersedia' => filled(config('app.key')),
            'Cookie sesi HttpOnly' => config('session.http_only') === true,
            'Cookie sesi SameSite dibatasi' => in_array(config('session.same_site'), ['lax', 'strict'], true),
            'Disk upload utama privat' => config('filesystems.default') === 'local'
                && config('filesystems.disks.local.root') !== config('filesystems.disks.public.root'),
        ];

        if ($production) {
            $checks += [
                'Environment produksi' => config('app.env') === 'production',
                'APP_URL menggunakan HTTPS' => parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https',
                'Cookie sesi Secure' => config('session.secure') === true,
                'Vite development server tidak aktif' => ! is_file(public_path('hot')),
                'Kredensial admin database tidak ada di deployment' => ! is_file(base_path('.env.database-admin')),
                'Kredensial bootstrap admin dikosongkan' => blank(config('workdesk.bootstrap_admin.password')),
            ];
        }

        try {
            if (DB::connection()->getDriverName() === 'pgsql') {
                $role = DB::selectOne('SELECT rolsuper, rolcreatedb, rolcreaterole, rolreplication, rolbypassrls FROM pg_roles WHERE rolname = current_user');
                $checks['Akun database tanpa hak administrator'] = $role
                    && ! $role->rolsuper && ! $role->rolcreatedb && ! $role->rolcreaterole
                    && ! $role->rolreplication && ! $role->rolbypassrls;
                $checks['Akun database tidak dapat membuat objek pada schema public'] = ! DB::selectOne("SELECT has_schema_privilege('public', 'CREATE') AS allowed")->allowed;
                $checks['Akun database tidak memiliki keanggotaan role tambahan'] = (int) DB::selectOne('SELECT count(*) AS memberships FROM pg_auth_members WHERE member = (SELECT oid FROM pg_roles WHERE rolname = current_user)')->memberships === 0;
            } elseif ($production) {
                $checks['Hak runtime database sudah diperiksa untuk PostgreSQL'] = false;
            }
        } catch (Throwable) {
            $checks['Koneksi dan hak database dapat diperiksa'] = false;
        }

        try {
            $sensitiveFiles = collect(['attendance-imports', 'service-request-attachments', 'ticket-comments', 'work-task-findings', 'work-task-finding-responses', 'work-task-permit-results', 'global-chat'])
                ->sum(fn (string $directory) => count(Storage::disk('public')->allFiles($directory)));
            $checks['Lampiran sensitif tidak berada di disk public'] = $sensitiveFiles === 0;
        } catch (Throwable) {
            $checks['Lokasi lampiran sensitif dapat diperiksa'] = false;
        }

        foreach ($checks as $label => $passed) {
            $this->line(($passed ? 'PASS' : 'FAIL').': '.$label);
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
