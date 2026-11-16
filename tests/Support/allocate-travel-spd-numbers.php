<?php

use App\Services\Travel\TravelDocumentNumbering;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 2);
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
$numbers = [];
for ($index = 0; $index < 20; $index++) {
    $numbers[] = DB::transaction(fn () => app(TravelDocumentNumbering::class)
        ->resolve('KPMOG', '2098-'.($argv[1] ?? '01').'-16', ''), 3);
}
echo json_encode($numbers, JSON_THROW_ON_ERROR);
