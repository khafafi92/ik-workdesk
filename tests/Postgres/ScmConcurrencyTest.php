<?php

namespace Tests\Postgres;

use App\Models\Department;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryReceipt;
use App\Models\Permission;
use App\Models\Scm\ApprovalRoute;
use App\Models\Scm\Audit;
use App\Models\Scm\Category;
use App\Models\Scm\Cbe;
use App\Models\Scm\CbeApprovalPolicy;
use App\Models\Scm\Procurement;
use App\Models\Scm\ProjectNumbering;
use App\Models\Scm\PurchaseOrder;
use App\Models\Scm\Quotation;
use App\Models\Scm\ServiceReceiptLine;
use App\Models\Scm\Vendor;
use App\Models\User;
use App\Services\Inventory\InventoryStock;
use App\Services\Inventory\Warehouses;
use App\Services\Scm\Approvals;
use App\Services\Scm\CbeApprovals;
use App\Services\Scm\Signatures;
use App\Services\Scm\VendorPublicPortal;
use App\Services\Scm\Workflow;
use Database\Seeders\ScmSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
                if ($job['different_tokens'] ?? false) {
                    $payload['data']['token'] = (string) Str::uuid();
                }
                if ($job['mode'] === 'save_cbe_policy') {
                    $payload['policy_ready'] = $directory.'/policy-'.$i;
                    $payload['policy_peer'] = $directory.'/policy-'.(1 - $i);
                    $files[] = $payload['policy_ready'];
                }
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

    public function test_simultaneous_project_numbers_use_one_counter_without_consuming_ho_numbers(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $actor->directPermissions()->attach(Permission::where('code', 'scm.request')->sole()->id);
        $department = Department::create(['code' => 'PROJECT-NUM', 'name' => 'Project numbering test']);
        $master = ProjectNumbering::where('code', 'COMP-19E001')->sole();
        $created = $this->workers(['mode' => 'create', 'actor_id' => $actor->id, 'data' => [
            'company' => 'KPMOG', 'kind' => 'PR', 'need_type' => 'PROJECT', 'project_numbering_id' => $master->id,
            'date' => today()->toDateString(), 'department_id' => $department->id,
            'items' => [['name' => 'Concurrent valve', 'specification' => 'Test', 'quantity' => '1', 'unit' => 'unit']],
        ]]);
        $numbers = array_column($created, 'number');
        sort($numbers);
        $this->assertCount(2, array_unique($numbers));
        $this->assertStringStartsWith('001/COMP-19E001/PR/', $numbers[0]);
        $this->assertStringStartsWith('002/COMP-19E001/PR/', $numbers[1]);
        $this->assertSame(2, DB::table('scm_project_number_sequences')->value('last_number'));
        $this->assertSame(0, DB::table('scm_number_sequences')->count());
    }

    public function test_inventory_receipts_serialize_quantities_and_duplicate_tokens(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $department = Department::create(['code' => 'INV-PG', 'name' => 'Inventori PostgreSQL QA']);
        $item = InventoryItem::create(['code' => 'INV-PG', 'name' => 'Barang QA', 'kind' => 'material', 'unit' => 'PCS', 'is_active' => true]);
        $request = Procurement::create(['number' => 'INV/PG/PR', 'kind' => 'PR', 'company' => 'KPMOG', 'need_type' => 'HO', 'date' => today(),
            'department_id' => $department->id, 'requester_id' => $actor->id, 'title' => 'Uji penerimaan', 'reason' => 'QA', 'status' => 'selected']);
        $line = $request->items()->create(['inventory_item_id' => $item->id, 'name' => 'Barang QA', 'specification' => 'QA', 'quantity_milli' => 5000, 'unit' => 'PCS', 'position' => 1]);
        $vendor = Vendor::create(['code' => 'INV-PG', 'name' => 'Vendor QA', 'provider_type' => 'barang', 'is_active' => true, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $cbe = Cbe::create(['request_id' => $request->id, 'number' => 'INV/PG/CBE', 'date' => today(), 'status' => 'final', 'prepared_by' => $actor->id, 'selected_vendor_id' => $vendor->id]);
        PurchaseOrder::create(['request_id' => $request->id, 'cbe_id' => $cbe->id, 'company' => 'KPMOG',
            'number' => 'INV/PG/PO', 'number_key' => 'inv/pg/po', 'issued_at' => today(), 'delivery_terms' => 'Kantor HO',
            'delivery_time' => '7 hari', 'payment_terms' => 'Setelah diterima', 'created_by' => $actor->id, 'updated_by' => $actor->id,
            'snapshot' => ['lines' => [['name' => $line->name, 'quantity_milli' => $line->quantity_milli, 'unit' => $line->unit]]]]);
        $warehouse = app(Warehouses::class)->forContext('KPMOG', 'HO', null);
        $data = ['token' => (string) Str::uuid(), 'warehouse_id' => $warehouse->id, 'received_on' => today()->toDateString(), 'reference' => 'SJ-QA',
            'lines' => [['request_item_id' => $line->id, 'inventory_item_id' => $item->id, 'quantity' => '4']]];
        $results = $this->workers(['mode' => 'inventory_receipt', 'actor_id' => $actor->id, 'request_id' => $request->id, 'data' => $data, 'different_tokens' => true]);
        $this->assertEqualsCanonicalizing(['received', 'conflict'], array_column($results, 'result'));
        $this->assertSame(4000, (int) InventoryBalance::sum('quantity_milli'));
        $this->assertSame(1, InventoryReceipt::count());
        $data['lines'][0]['quantity'] = '1';
        $results = $this->workers(['mode' => 'inventory_receipt', 'actor_id' => $actor->id, 'request_id' => $request->id, 'data' => $data]);
        $this->assertSame(['received', 'received'], array_column($results, 'result'));
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame(5000, (int) InventoryBalance::sum('quantity_milli'));
        $this->assertSame(2, InventoryReceipt::count());
    }

    public function test_service_receipts_serialize_partial_quantities_and_duplicate_submissions(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $department = Department::create(['code' => 'SR-PG', 'name' => 'Penerimaan jasa PostgreSQL QA']);
        $request = Procurement::create(['number' => 'SR-PG-01', 'kind' => 'SR', 'company' => 'KPMOG', 'need_type' => 'HO', 'date' => today(),
            'department_id' => $department->id, 'requester_id' => $actor->id, 'title' => 'Servis AC', 'reason' => 'QA', 'status' => 'completed']);
        $vendor = Vendor::create(['code' => 'SR-PG', 'name' => 'Vendor QA', 'provider_type' => 'jasa', 'created_by' => $actor->id, 'updated_by' => $actor->id]);
        $cbe = Cbe::create(['request_id' => $request->id, 'number' => 'CBE-SR-PG', 'date' => today(), 'status' => 'final',
            'prepared_by' => $actor->id, 'selected_vendor_id' => $vendor->id]);
        $order = PurchaseOrder::create(['request_id' => $request->id, 'cbe_id' => $cbe->id, 'company' => 'KPMOG',
            'number' => 'PO-SR-PG', 'number_key' => 'po-sr-pg', 'issued_at' => today(), 'delivery_terms' => 'Kantor HO',
            'delivery_time' => '7 hari', 'payment_terms' => 'Setelah diterima', 'created_by' => $actor->id, 'updated_by' => $actor->id,
            'snapshot' => ['lines' => [['name' => 'Servis AC', 'quantity_milli' => 5000, 'unit' => 'unit']]]]);
        $job = ['mode' => 'service_receipt', 'actor_id' => $actor->id, 'order_id' => $order->id, 'data' => [
            'token' => (string) Str::uuid(), 'received_on' => today()->toDateString(), 'reference' => 'BAST-PG', 'quantities' => [1 => '4'],
        ]];

        $first = $this->workers([...$job, 'different_tokens' => true]);
        $this->assertEqualsCanonicalizing(['received', 'conflict'], array_column($first, 'result'));
        $this->assertSame(4000, (int) ServiceReceiptLine::sum('quantity_milli'));

        $job['data']['quantities'][1] = '1';
        $replayed = $this->workers($job);
        $this->assertSame(['received', 'received'], array_column($replayed, 'result'));
        $this->assertSame($replayed[0]['id'], $replayed[1]['id']);
        $this->assertSame(5000, (int) ServiceReceiptLine::sum('quantity_milli'));
        $this->assertSame(2, $order->serviceReceipts()->count());
        $this->assertSame(0, InventoryReceipt::count());
    }

    public function test_inventory_stock_cannot_be_overdrawn_by_simultaneous_issues(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $item = InventoryItem::create(['code' => 'STOCK-PG', 'name' => 'Stok QA', 'kind' => 'material', 'unit' => 'PCS', 'is_active' => true]);
        $warehouse = app(Warehouses::class)->forContext('KPMOG', 'HO', null);
        $balance = DB::transaction(function () use ($actor, $item, $warehouse) {
            $stock = app(InventoryStock::class);
            $balance = $stock->balance($warehouse, $item, null);
            $stock->move($balance, 5000, 'incoming', 'opening-pg-stock', $actor, 'Saldo pengujian.');

            return $balance;
        });
        $results = $this->workers(['mode' => 'inventory_stock', 'actor_id' => $actor->id, 'different_tokens' => true,
            'data' => ['token' => (string) Str::uuid(), 'balance_id' => $balance->id, 'quantity' => '4', 'notes' => 'Pengeluaran serentak QA']]);
        $this->assertEqualsCanonicalizing(['stock_moved', 'conflict'], array_column($results, 'result'));
        $this->assertSame(1000, $balance->fresh()->quantity_milli);
    }

    public function test_simultaneous_first_cbe_master_save_returns_a_recoverable_conflict(): void
    {
        $actor = User::factory()->create();
        $actor->directPermissions()->attach(Permission::where('code', 'scm.configure')->sole()->id);
        $users = User::factory()->count(3)->create();
        $data = ['company' => 'KPMOG', 'acknowledger_id' => $users[0]->id, 'crosschecker_id' => $users[1]->id,
            'approver_id' => $users[2]->id, 'version' => 0];

        $results = $this->workers(['mode' => 'save_cbe_policy', 'actor_id' => $actor->id, 'data' => $data]);

        $this->assertEqualsCanonicalizing(['saved', 'conflict'], array_column($results, 'result'));
        $this->assertSame(1, CbeApprovalPolicy::where('company', 'KPMOG')->count());
        $this->assertSame(1, Audit::where('action', 'master_approval_cbe')->count());
    }

    public function test_vendor_number_opens_one_update_draft_when_resumed_by_two_sessions(): void
    {
        $actor = User::factory()->create();
        $actor->directPermissions()->attach(Permission::where('code', 'scm.process')->sole()->id);
        [$registration, $token, $code] = app(VendorPublicPortal::class)->register(['company' => 'KPMOG', 'provider_type' => 'barang', 'name' => 'Vendor pengujian konkurensi']);
        $vendor = app(Workflow::class)->saveVendor($actor, ['code' => 'DEMO-PUBLIC', 'name' => 'Vendor pengujian konkurensi', 'provider_type' => 'barang', 'is_active' => true]);
        $registration->update(['status' => 'accepted', 'vendor_id' => $vendor->id, 'vendor_version' => $vendor->version, 'submitted_snapshot' => ['fixture' => 'DEMO pengujian']]);
        $registration->portalAccess->update(['vendor_id' => $vendor->id]);
        $results = $this->workers(['mode' => 'resume_public_vendor', 'actor_id' => $actor->id, 'access_code' => $code]);
        $this->assertSame(['resumed', 'resumed'], array_column($results, 'result'));
        $this->assertCount(1, array_unique(array_column($results, 'registration_id')));
        $this->assertSame(2, $registration->portalAccess->registrations()->count());
        $this->assertSame('accepted', $registration->fresh()->status);
        $this->assertSame(['fixture' => 'DEMO pengujian'], $registration->fresh()->submitted_snapshot);
        $this->assertSame(1, $vendor->fresh()->version);
    }

    public function test_simultaneous_request_numbers_and_cbe_finalization(): void
    {
        $actor = User::factory()->create(['is_admin' => true]);
        $actor->directPermissions()->attach(Permission::whereIn('code', ['scm.request', 'scm.process'])->pluck('id'));
        $acknowledger = User::factory()->create();
        CbeApprovalPolicy::create(['company' => 'APCA', 'acknowledger_id' => $acknowledger->id, 'crosschecker_id' => User::factory()->create()->id, 'approver_id' => User::factory()->create()->id]);
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
        $cbe = Cbe::sole();
        $this->assertSame(1, app(CbeApprovals::class)->pendingCount($acknowledger));
        app(Signatures::class)->upload($acknowledger, new UploadedFile(database_path('fixtures/scm-demo-signature.png'), 'DEMO-BUKAN-TTD.png', 'image/png', null, true));
        $results = $this->workers(['mode' => 'sign_cbe', 'actor_id' => $acknowledger->id, 'cbe_id' => $cbe->id, 'version' => $cbe->request->version]);
        $this->assertEqualsCanonicalizing(['signed', 'conflict'], array_column($results, 'result'));
        $this->assertSame(2, $cbe->approvals()->where('status', 'signed')->count());
        $this->assertSame(1, Audit::where('action', 'tanda_tangan_cbe')->count());
        $this->assertSame(0, app(CbeApprovals::class)->pendingCount($acknowledger));
        $this->assertSame(1, app(CbeApprovals::class)->pendingCount(CbeApprovalPolicy::where('company', 'APCA')->sole()->crosschecker));
    }
}
