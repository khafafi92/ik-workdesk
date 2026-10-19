<?php

use App\Services\ItInfrastructure\ItAssetNumberGenerator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 2);
putenv('APP_CONFIG_CACHE='.$root.'/bootstrap/cache/it-testing-config-not-used.php');
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = getenv('IT_INFRASTRUCTURE_TEST_DATABASE');
if (! $database || ! preg_match('/^ik_it_test_[a-f0-9]{16}$/', $database) || DB::connection()->getDatabaseName() !== $database || ! in_array($argv[1] ?? '', ['APCA', 'KPMOG'], true)) {
    throw new RuntimeException('Only the isolated IT test database is allowed.');
}
$numbers = [];
foreach (range(1, 20) as $index) {
    $numbers[] = DB::transaction(function () use ($argv): string {
        $number = app(ItAssetNumberGenerator::class)->next($argv[1], 2025);
        usleep(10000);

        return $number;
    });
}
echo json_encode($numbers, JSON_THROW_ON_ERROR);
