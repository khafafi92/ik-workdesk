<?php

use App\Models\User;
use App\Services\Scm\Workflow;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = getenv('SCM_TEST_DATABASE');
if (! $app->environment('testing') || ! preg_match('/^ik_scm_test_[a-f0-9]{16}$/', (string) $database)
    || DB::connection()->getDatabaseName() !== $database) {
    throw new RuntimeException('Database uji SCM diperlukan.');
}
$job = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
touch($job['ready']);
$started = microtime(true);
while (! is_file($job['gate'])) {
    if (microtime(true) - $started > 15) {
        throw new RuntimeException('Barrier timeout.');
    }
    usleep(10000);
}
$actor = User::findOrFail($job['actor_id']);
try {
    if ($job['mode'] === 'finalize') {
        $r = app(Workflow::class)->saveCbe($actor, $job['request_id'], $job['version'], $job['data'], true);
        echo json_encode(['result' => 'finalized', 'id' => $r->cbe->id]), PHP_EOL;
    } else {
        $r = app(Workflow::class)->saveDraft($actor, $job['data']);
        echo json_encode(['result' => 'created', 'number' => $r->number]), PHP_EOL;
    }
} catch (ValidationException $e) {
    echo json_encode(['result' => 'conflict']), PHP_EOL;
}
