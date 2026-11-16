<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__);
putenv('APP_CONFIG_CACHE='.$root.'/bootstrap/cache/travel-testing-config-not-used.php');
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = getenv('TRAVEL_TEST_DATABASE');
if (! $database || ! preg_match('/^ik_travel_test_[a-f0-9]{16}$/', $database)
    || DB::connection()->getDriverName() !== 'pgsql'
    || DB::connection()->getDatabaseName() !== $database) {
    throw new RuntimeException('Only the isolated travel test database is allowed.');
}
$workers = [];
try {
    if (DB::table('travel_spd_number_sequences')->where('company', 'KPMOG')->where('year', 2098)->exists()) {
        throw new RuntimeException('Concurrency fixture year must be unused.');
    }
    foreach (['01', '02'] as $month) {
        $worker = new Process([PHP_BINARY, $root.'/tests/Support/allocate-travel-spd-numbers.php', $month], $root);
        $worker->setTimeout(45);
        $worker->start();
        $workers[] = $worker;
    }
    $numbers = [];
    foreach ($workers as $worker) {
        $worker->wait();
        if (! $worker->isSuccessful()) {
            throw new RuntimeException('SPD allocation worker failed: '.$worker->getErrorOutput());
        }
        $numbers = [...$numbers, ...json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR)];
    }
    $sequences = array_map(fn ($number) => (int) explode('/', $number)[0], $numbers);
    sort($sequences);
    if (count(array_unique($numbers)) !== 40 || $sequences !== range(1, 40)
        || (int) DB::table('travel_spd_number_sequences')->where('company', 'KPMOG')->where('year', 2098)->value('last_number') !== 40) {
        throw new RuntimeException('SPD allocation produced duplicates or an incorrect annual sequence.');
    }
    echo 'PASS: two concurrent workers allocated 40 unique SPD numbers across January and February.'.PHP_EOL;
} finally {
    foreach ($workers as $worker) {
        if ($worker->isRunning()) {
            $worker->stop();
        }
    }
}
