<?php

namespace Tests\Postgres;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Scm\ApprovalRoute;
use App\Models\Scm\Audit;
use App\Models\Scm\Category;
use App\Models\Scm\Cbe;
use App\Models\Scm\Procurement;
use App\Models\Scm\Quotation;
use App\Models\User;
use App\Services\Scm\Approvals;
use App\Services\Scm\Signatures;
use App\Services\Scm\Workflow;
use Database\Seeders\ScmSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ScmConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ScmSeeder::class);
    }

    public function runDatabaseMigrations(): void
    {
        $name = getenv('SCM_TEST_DATABASE');
        if (! app()->environment('testing') || ! preg_match('/^ik_scm_test_[a-f0-9]{16}$/', (string) $name)
            || DB::connection()->getDriverName() !== 'pgsql' || DB::connection()->getDatabaseName() !== $name) {
            throw new \RuntimeException('Hanya database uji SCM terpisah yang diizinkan.');
        }
        $this->artisan('migrate:fresh', ['--force' => true]);
    }

    private function workers(array $job): array
    {
        $directory = sys_get_temp_dir().'/ik-scm-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $workers = [];
        $files = [];
        $results = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $payload = [...$job, 'ready' => $directory.'/ready-'.$i, 'gate' => $directory.'/gate'];
                $path = $directory.'/job-'.$i.'.json';
                file_put_contents($path, json_encode($payload));
                $files[] = $path;
                $files[] = $payload['ready'];
                $worker = new Process([PHP_BINARY, base_path('tests/Support/scm-concurrent-worker.php'), $path], base_path(), [
                    'APP_CONFIG_CACHE' => base_path('bootstrap/cache/scm-testing-config-not-used.php'), 'APP_ROUTES_CACHE' => base_path('bootstrap/cache/scm-testing-routes-not-used.php'),
                ]);
                $worker->setTimeout(35);
                $worker->start();
                $workers[] = $worker;
            }
            $started = microtime(true);
            while (! is_file($directory.'/ready-0') || ! is_file($directory.'/ready-1')) {
                if (microtime(true) - $started > 20) {
                    $this->fail('Worker tidak dapat dimulai: '.implode('', array_map(fn ($w) => $w->getErrorOutput(), $workers)));
                }
                usleep(10000);
            }
            touch($directory.'/gate');
            $files[] = $directory.'/gate';
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $results[] = json_decode(trim($worker->getOutput()), true, flags: JSON_THROW_ON_ERROR);
            }
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($directory);
        }

        return $results;
    }

    public function test_simultaneous_request_numbers_and_cbe_finalization(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $actor->directPermissions()->attach(Permission::whereIn('code', ['scm.request', 'scm.process'])->pluck('id'));
        $department = Department::create(['name' => 'SCM test', 'code' => 'SCM-T', 'is_active' => true]);
        $category = Category::create(['name' => 'Kategori uji bersamaan']);
        $data = ['kind' => 'PR', 'date' => today()->toDateString(), 'department_id' => $department->id, 'estimated_total' => '5000000',
            'title' => 'PR uji bersamaan', 'reason' => 'Data uji fiktif', 'needed_at' => '', 'items' => [['name' => 'Barang uji', 'specification' => 'Spesifikasi uji', 'quantity' => '1', 'unit' => 'unit']]];
        $created = $this->workers(['mode' => 'create', 'actor_id' => $actor->id, 'data' => $data]);
        $this->assertCount(2, array_unique(array_column($created, 'number')));
        $numbers = array_column($created, 'number');
        sort($numbers);
        $this->assertStringStartsWith('001/APCA-HO/PR/', $numbers[0]);
        $this->assertStringStartsWith('002/APCA-HO/PR/', $numbers[1]);
        $kpmog = $this->workers(['mode' => 'create', 'actor_id' => $actor->id, 'data' => [...$data, 'company' => 'KPMOG']]);
        $kpmogNumbers = array_column($kpmog, 'number');
        sort($kpmogNumbers);
        $this->assertStringStartsWith('001/KPMOG-OFC/PR/', $kpmogNumbers[0]);
        $this->assertStringStartsWith('002/KPMOG-OFC/PR/', $kpmogNumbers[1]);
        $flow = app(Workflow::class);
        $r = Procurement::firstOrFail();
        $this->assertNull($r->getRawOriginal('needed_at'));
        $signatures = app(Signatures::class);
        $signatures->upload($actor, new UploadedFile(database_path('fixtures/scm-demo-signature.png'), 'DEMO-BUKAN-TTD.png', 'image/png', null, true));
        $r = $signatures->sign($actor, $r->id, $r->version);
        ApprovalRoute::create(['company' => 'APCA', 'department_id' => $department->id,
            'acknowledger_id' => $actor->id, 'cbo_id' => $actor->id, 'cbo3_id' => $actor->id]);
        $r = $flow->submit($actor, $r->id, $r->version);
        $crosscheck = $r->approvals()->where('role', 'crosschecked')->sole();
        $this->assertSame('skipped', $crosscheck->status);
        $this->assertSame('approved', app(Approvals::class)->next($r)->role);
        $crosscheck->update(['status' => 'pending', 'acted_at' => null, 'notes' => null]);
        $beforeRepairVersion = $r->version;
        $repairs = $this->workers(['mode' => 'skip_manager_crosscheck', 'actor_id' => $actor->id, 'request_id' => $r->id]);
        $this->assertEqualsCanonicalizing(['adjusted', 'unchanged'], array_column($repairs, 'result'));
        $r->refresh();
        $this->assertSame($beforeRepairVersion + 1, $r->version);
        $this->assertSame(1, Audit::where('action', 'penyesuaian_crosscheck_manager')->count());
        $this->assertSame('approved', app(Approvals::class)->next($r)->role);
        while ($step = app(Approvals::class)->next($r)) {
            $r = app(Approvals::class)->decide($actor, $r->id, $r->version, $step->id, $step->is_required ? 'sign' : 'skip');
        }
        $this->assertNull($r->category_id);
        $number = $r->number;
        $r = $flow->receive($actor, $r->id, $r->version);
        $this->assertNull($r->category_id);
        $this->assertSame($number, $r->number);
        for ($i = 0; $i < 3; $i++) {
            $vendor = $flow->saveVendor($actor, ['code' => 'V'.$i, 'name' => 'Vendor uji '.$i, 'provider_type' => 'barang', 'is_active' => true]);
            $r = $flow->addCandidate($actor, $r->id, $r->version, ['vendor_id' => $vendor->id, 'followup_status' => 'diminta', 'requested_at' => '']);
        }
        foreach ($r->candidates as $candidate) {
            $r = $flow->saveQuotation($actor, $r->id, $r->version, ['candidate_id' => $candidate->id, 'number' => 'Q'.$candidate->id, 'date' => today()->toDateString(),
                'currency' => 'IDR', 'lead_time' => '7 hari', 'payment_terms' => 'Tunai', 'valid_until' => '', 'extra' => '0', 'lines' => [['request_item_id' => $r->items->first()->id, 'description' => 'Sesuai', 'quantity' => '1', 'unit' => 'unit', 'price' => '100', 'discount' => '0', 'tax' => '0']]]);
        }
        $data = ['date' => today()->toDateString(), 'quotation_ids' => Quotation::pluck('id')->all(), 'selected_vendor_id' => $r->candidates->first()->vendor_id, 'selection_reason' => 'Harga sesuai dan spesifikasi memenuhi kebutuhan.'];
        $results = $this->workers(['mode' => 'finalize', 'actor_id' => $actor->id, 'request_id' => $r->id, 'version' => $r->version, 'data' => $data]);
        $this->assertEqualsCanonicalizing(['finalized', 'conflict'], array_column($results, 'result'));
        $this->assertSame(1, Cbe::where('status', 'final')->count());
        $this->assertSame(1, Audit::where('action', 'finalisasi_cbe')->count());
    }
}
