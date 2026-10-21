<?php

namespace Tests\Support;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

abstract class FunctionalDatabaseTestCase extends TestCase
{
    private bool $postgresTransaction = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', app()->environment());
        $this->assertEmpty(DB::connection()->getConfig('url'));
        if (DB::connection()->getDriverName() === 'pgsql') {
            $database = getenv('IT_INFRASTRUCTURE_TEST_DATABASE');
            $this->assertIsString($database);
            $this->assertMatchesRegularExpression('/^ik_it_test_[a-f0-9]{16}$/', $database);
            $this->assertSame($database, DB::connection()->getDatabaseName());
            $this->assertSame($database, DB::selectOne('SELECT current_database() AS name')->name);
            DB::beginTransaction();
            $this->postgresTransaction = true;
        } else {
            $this->assertSame('sqlite', DB::connection()->getDriverName());
            $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    protected function tearDown(): void
    {
        try {
            if ($this->postgresTransaction) {
                while (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            }
        } finally {
            parent::tearDown();
        }
    }
}
