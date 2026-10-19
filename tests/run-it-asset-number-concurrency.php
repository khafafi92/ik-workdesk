<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__);
putenv('APP_CONFIG_CACHE='.$root.'/bootstrap/cache/it-testing-config-not-used.php');
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = getenv('IT_INFRASTRUCTURE_TEST_DATABASE');
if (! $database || ! preg_match('/^ik_it_test_[a-f0-9]{16}$/', $database) || DB::connection()->getDriverName() !== 'pgsql' || DB::connection()->getDatabaseName() !== $database) {
    throw new RuntimeException('Only the isolated PostgreSQL IT test database is allowed.');
}
$workers = [];
try {
    Schema::create('it_asset_number_sequences', function (Blueprint $table): void {
        $table->unsignedInteger('id')->primary();
        $table->unsignedInteger('last_number');
    });
    DB::table('it_asset_number_sequences')->insert(['id' => 1, 'last_number' => 34]);
    foreach (['APCA', 'KPMOG'] as $company) {
        $worker = new Process([PHP_BINARY, $root.'/tests/Support/allocate-it-asset-numbers.php', $company], $root);
        $worker->setTimeout(30);
        $worker->start();
        $workers[] = $worker;
    }
    $numbers = [];
    foreach ($workers as $worker) {
        $worker->wait();
        if (! $worker->isSuccessful()) {
            throw new RuntimeException('Concurrent allocation worker failed: '.$worker->getErrorOutput());
        }
        $numbers = [...$numbers, ...json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR)];
    }
    $sequences = array_map(fn ($number): int => (int) explode('/', $number)[0], $numbers);
    sort($sequences);
    if (count(array_unique($numbers)) !== 40 || $sequences !== range(35, 74) || (int) DB::table('it_asset_number_sequences')->value('last_number') !== 74) {
        throw new RuntimeException('Concurrent allocation produced a duplicate, gap or incorrect counter.');
    }
    echo 'PASS: two concurrent workers allocated 40 distinct asset sequences (00035 through 00074).'.PHP_EOL;
} finally {
    foreach ($workers as $worker) {
        if ($worker->isRunning()) {
            $worker->stop();
        }
    }
    Schema::dropIfExists('it_asset_number_sequences');
}
