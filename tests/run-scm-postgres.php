<?php

require dirname(__DIR__).'/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__);
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = DB::connection()->getConfig();
if (! $app->environment('local') || $config['driver'] !== 'pgsql' || ! in_array($config['host'], ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Runner hanya boleh memakai PostgreSQL lokal.');
}
if (is_file($root.'/.env.database-admin')) {
    $admin = Dotenv\Dotenv::parse(file_get_contents($root.'/.env.database-admin'));
    if ($admin['DB_HOST'] !== $config['host'] || $admin['DB_DATABASE'] !== $config['database']) {
        throw new RuntimeException('Target admin berbeda.');
    }
    $config['username'] = $admin['DB_USERNAME'];
    $config['password'] = $admin['DB_PASSWORD'];
    $config['url'] = null;
}
config(['database.connections.scm_test_admin' => $config]);
$source = DB::connection('scm_test_admin');
$database = 'ik_scm_test_'.bin2hex(random_bytes(8));
$sourceName = $source->selectOne('SELECT current_database() AS name')->name;
$snapshot = function () use ($source): string {
    $tables = $source->select("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename");
    $counts = [];
    foreach ($tables as $table) {
        $counts[$table->tablename] = $source->table($table->tablename)->count();
    }

    return hash('sha256', serialize($counts));
};
$before = $snapshot();
$identity = null;
$exitCode = 1;
try {
    $source->statement('CREATE DATABASE "'.$database.'" TEMPLATE template0 ENCODING \'UTF8\'');
    $identity = $source->selectOne('SELECT oid, datdba FROM pg_database WHERE datname=?', [$database]);
    $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_HOST' => (string) $config['host'],
        'DB_PORT' => (string) $config['port'], 'DB_DATABASE' => $database, 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'],
        'SCM_TEST_DATABASE' => $database];
    $process = new Process([PHP_BINARY, $root.'/vendor/phpunit/phpunit/phpunit', '-c', $root.'/phpunit.scm-postgres.xml', '--do-not-cache-result', ...array_slice($argv, 1)], $root, $environment);
    $process->setTimeout(180);
    $exitCode = $process->run(fn ($type, $output) => print ($output));
} finally {
    if ($identity) {
        $current = $source->selectOne('SELECT oid, datdba FROM pg_database WHERE datname=?', [$database]);
        if (! $current || $current->oid !== $identity->oid || $current->datdba !== $identity->datdba || $sourceName === $database || ! preg_match('/^ik_scm_test_[a-f0-9]{16}$/', $database)) {
            throw new RuntimeException('Identitas database uji berubah.');
        }
        $source->statement('DROP DATABASE "'.$database.'"');
        echo 'PASS: database uji SCM terpisah telah dibersihkan.', PHP_EOL;
    }
    if ($snapshot() !== $before) {
        throw new RuntimeException('Jumlah record database utama berubah selama pengujian.');
    }
    echo 'PASS: jumlah record seluruh tabel utama tetap sama.', PHP_EOL;
}
exit($exitCode);
