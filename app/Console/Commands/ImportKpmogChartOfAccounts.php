<?php

namespace App\Console\Commands;

use App\Models\FinanceAccount;
use App\Models\User;
use App\Services\Scm\Access;
use App\Services\Scm\Workflow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportKpmogChartOfAccounts extends Command
{
    protected $signature = 'finance:import-kpmog-coa-2026 {--actor= : ID pengguna yang menjalankan impor}';

    protected $description = 'Impor master COA KPMOG dari daftar per 31 Agustus 2026';

    public function handle(Workflow $workflow): int
    {
        $actor = User::find($this->option('actor'));
        if (! $actor || ! Access::has($actor, 'finance.coa.manage')) {
            $this->error('Pilih ID pengguna yang memiliki akses kelola master COA.');

            return self::FAILURE;
        }

        $rows = json_decode(file_get_contents(database_path('seeders/data/coa-kpmog-2026.json')), true, flags: JSON_THROW_ON_ERROR);

        try {
            DB::transaction(function () use ($rows, $actor, $workflow): void {
                if (! DB::table('finance_account_hierarchy_locks')->where('company', 'KPMOG')->lockForUpdate()->exists()) {
                    throw new RuntimeException('Kunci hierarki KPMOG tidak tersedia.');
                }
                if (FinanceAccount::where('company', 'KPMOG')->exists()) {
                    throw new RuntimeException('Master COA KPMOG sudah berisi data. Impor dibatalkan untuk menghindari perubahan data yang ada.');
                }
                $ids = [];
                foreach ($rows as $row) {
                    if (isset($ids[$row['code']]) || ($row['parent_code'] !== null && ! isset($ids[$row['parent_code']]))) {
                        throw new RuntimeException('Kode duplikat atau induk tidak ditemukan: '.$row['code']);
                    }
                    $account = FinanceAccount::create([
                        'company' => 'KPMOG', 'code' => $row['code'], 'name' => $row['name'],
                        'account_type' => $row['account_type'], 'parent_id' => $row['parent_code'] === null ? null : $ids[$row['parent_code']],
                        'is_active' => true, 'created_by' => $actor->id, 'updated_by' => $actor->id,
                    ]);
                    $ids[$row['code']] = $account->id;
                    $workflow->audit($actor, $account, 'impor_coa', ['after' => $account->getAttributes(), 'source' => 'COA 2026.xlsx, per 31 Aug 2026']);
                }
            });
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(count($rows).' akun COA KPMOG berhasil diimpor.');

        return self::SUCCESS;
    }
}
