<?php

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$started = hrtime(true);
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$options = getopt('', ['path:', 'user::', 'inspection:', 'details', 'guest']);
$path = $options['path'] ?? '/panel';
$query = [];
if (isset($options['inspection'])) {
    if ($path !== '/panel/it-inspection-summary' || filter_var($options['inspection'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        fwrite(STDERR, "An inspection must be a positive integer on the inspection summary page.\n");
        exit(1);
    }
    $query['inspection'] = (int) $options['inspection'];
}
$request = Request::create('http://127.0.0.1:8000'.$path, 'GET', $query);
$app->instance('request', $request);
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$allowedPaths = json_decode(file_get_contents(__DIR__.'/profile-pages.json'), true, flags: JSON_THROW_ON_ERROR);
$databaseConnection = DB::connection();
if (! $app->environment('local') || ! in_array($path, $allowedPaths, true)
    || $databaseConnection->getDriverName() !== 'pgsql'
    || ! in_array($databaseConnection->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)) {
    fwrite(STDERR, "This profiler is restricted to the local database and its read-only page allowlist.\n");
    exit(1);
}

config(['session.driver' => 'array', 'cache.default' => 'array', 'queue.default' => 'null', 'mail.default' => 'array']);
$guest = array_key_exists('guest', $options);
if (! $guest) {
    $user = isset($options['user']) ? User::query()->findOrFail($options['user']) : User::query()->where('is_admin', true)->firstOrFail();
    Auth::guard('web')->setUser($user);
}
$queries = [];
DB::listen(function (QueryExecuted $query) use (&$queries): void {
    $fingerprint = preg_replace(["/'(?:''|[^'])*'/", '/\b\d+\b/'], '?', $query->sql);
    $queries[] = ['sql' => $fingerprint, 'ms' => $query->time];
});
$booted = hrtime(true);
DB::beginTransaction();
try {
    DB::statement('SET TRANSACTION READ ONLY');
    $response = $kernel->handle($request);
    $handled = hrtime(true);
    $kernel->terminate($app->make('request'), $response);
    $groups = collect($queries)->groupBy('sql')->map(fn ($group, $sql) => [
        'sql' => $sql, 'count' => $group->count(), 'ms' => round($group->sum('ms'), 2),
    ])->sortByDesc('count')->values()->take(12)->all();
    $result = [
        'path' => $path, 'status' => $response->getStatusCode(), 'actor' => $guest ? 'guest' : 'authenticated',
        'redirect' => $response->headers->get('Location'),
        'bootstrap_ms' => round(($booted - $started) / 1e6, 1),
        'render_ms' => round(($handled - $booted) / 1e6, 1),
        'total_ms' => round(($handled - $started) / 1e6, 1),
        'queries' => count($queries), 'sql_ms' => round(array_sum(array_column($queries, 'ms')), 2),
        'html_bytes' => strlen($response->getContent()), 'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
        'opcache_cli' => ini_get('opcache.enable_cli'), 'opcache_enabled' => ini_get('opcache.enable'),
        'top_queries' => $groups,
    ];
    if (array_key_exists('details', $options)) {
        $html = $response->getContent();
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            $document->loadHTML($html, LIBXML_NONET);
            $snapshotBytes = 0;
            $alpineBytes = 0;
            foreach ($document->getElementsByTagName('*') as $element) {
                $snapshotBytes += strlen($element->getAttribute('wire:snapshot'));
                $alpineBytes += strlen($element->getAttribute('x-data'));
            }
            $result['payload'] = [
                'gzip_html_bytes' => strlen(gzencode($html, 6)),
                'elements' => $document->getElementsByTagName('*')->length,
                'svg_elements' => $document->getElementsByTagName('svg')->length,
                'livewire_snapshot_bytes' => $snapshotBytes,
                'alpine_data_bytes' => $alpineBytes,
            ];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
    }
    echo json_encode($result, JSON_UNESCAPED_SLASHES).PHP_EOL;
} finally {
    DB::rollBack();
}
