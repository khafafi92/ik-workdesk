<?php

require dirname(__DIR__).'/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__);
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$source = DB::connection();
$config = $source->getConfig();
if (! $app->environment('local') || $config['driver'] !== 'pgsql' || ! in_array($config['host'], ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Runner requires local PostgreSQL.');
}
$database = 'ik_travel_test_'.bin2hex(random_bytes(8));
$sourceName = $source->selectOne('SELECT current_database() AS name')->name;
$snapshot = function () use ($source): string {
    $snapshot = ['schema' => $source->select("SELECT table_name,column_name,udt_name,is_nullable,column_default FROM information_schema.columns WHERE table_schema='public' ORDER BY table_name,ordinal_position")];
    foreach ($source->select("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename") as $table) {
        $name = '"'.str_replace('"', '""', $table->tablename).'"';
        $snapshot[$table->tablename] = $source->selectOne('SELECT md5(COALESCE(string_agg(record::text, chr(10) ORDER BY record::text), \'\')) AS hash FROM (SELECT to_jsonb(t) AS record FROM public.'.$name.' t) records')->hash;
    }

    return hash('sha256', serialize($snapshot));
};
$before = $snapshot();
$identity = null;
$exitCode = 1;
try {
    $source->statement('CREATE DATABASE "'.$database.'" TEMPLATE template0 ENCODING \'UTF8\'');
    $identity = $source->selectOne('SELECT oid,datdba FROM pg_database WHERE datname=?', [$database]);
    echo 'PostgreSQL: testing SPD/LPJ in a separate temporary database.'.PHP_EOL;
    $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_HOST' => (string) $config['host'],
        'DB_PORT' => (string) $config['port'], 'DB_DATABASE' => $database, 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'],
        'TRAVEL_TEST_DATABASE' => $database, 'APP_CONFIG_CACHE' => $root.'/bootstrap/cache/travel-testing-config-not-used.php',
        'APP_ROUTES_CACHE' => $root.'/bootstrap/cache/travel-testing-routes-not-used.php', 'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'BROADCAST_CONNECTION' => 'null'];
    $process = new Process([PHP_BINARY, $root.'/vendor/phpunit/phpunit/phpunit', '-c', $root.'/phpunit.travel-postgres.xml', '--do-not-cache-result', ...array_slice($argv, 1)], $root, $environment);
    $process->setTimeout(180);
    $exitCode = $process->run(fn ($type, $output) => print ($output));
    if ($exitCode === 0) {
        $concurrency = new Process([PHP_BINARY, $root.'/tests/run-travel-number-concurrency.php'], $root, $environment);
        $concurrency->setTimeout(60)->mustRun();
        echo $concurrency->getOutput();
    }
} finally {
    if ($identity) {
        $current = $source->selectOne('SELECT oid,datdba FROM pg_database WHERE datname=?', [$database]);
        if (! $current || $current->oid !== $identity->oid || $current->datdba !== $identity->datdba || $sourceName === $database || ! preg_match('/^ik_travel_test_[a-f0-9]{16}$/', $database)) {
            throw new RuntimeException('Temporary database identity changed.');
        }
        $source->statement('DROP DATABASE "'.$database.'"');
        echo 'PASS: temporary travel database removed.'.PHP_EOL;
    }
    if ($snapshot() !== $before) {
        throw new RuntimeException('Source database schema or data changed during tests.');
    }
    echo 'PASS: original database schema and existing rows unchanged.'.PHP_EOL;
}
exit($exitCode);
