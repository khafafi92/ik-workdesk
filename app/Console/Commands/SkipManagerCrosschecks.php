<?php

namespace App\Console\Commands;

use App\Models\Scm\Procurement;
use App\Models\User;
use App\Services\Scm\Access;
use App\Services\Scm\Approvals;
use Illuminate\Console\Command;

class SkipManagerCrosschecks extends Command
{
    protected $signature = 'scm:skip-manager-crosschecks {--actor= : ID administrator pelaksana} {--request=* : ID permintaan yang diperiksa} {--apply : Terapkan penyesuaian; tanpa opsi ini hanya pemeriksaan}';

    protected $description = 'Lewati Crosschecked by opsional yang masih tertunda pada pengajuan atasan sendiri';

    public function handle(Approvals $approvals): int
    {
        $actor = User::find((int) $this->option('actor'));
        $ids = $this->option('request');
        if (! $actor || ! $actor->isActiveForAccess() || (! $actor->hasRole('system-admin') && ! Access::has($actor, 'scm.configure'))) {
            $this->error('Actor harus administrator aktif atau memiliki izin konfigurasi SCM.');

            return self::FAILURE;
        }
        if (! $ids || collect($ids)->contains(fn ($id) => ! ctype_digit((string) $id) || (int) $id < 1)) {
            $this->error('Tentukan ID permintaan dengan --request=ID.');

            return self::FAILURE;
        }
        $requests = Procurement::whereIn('id', $ids)->orderBy('id')->get();
        if ($requests->count() !== count(array_unique($ids))) {
            $this->error('Ada ID permintaan yang tidak ditemukan.');

            return self::FAILURE;
        }
        $count = 0;
        foreach ($requests as $request) {
            if ($approvals->skipManagerCrosscheck($actor, $request->id, (bool) $this->option('apply'))) {
                $count++;
                $this->line($request->id.' | '.$request->number);
            }
        }
        $this->info(($this->option('apply') ? 'Disesuaikan: ' : 'Memenuhi aturan: ').$count);

        return self::SUCCESS;
    }
}
