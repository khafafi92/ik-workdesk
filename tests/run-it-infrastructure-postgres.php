<?php

require dirname(__DIR__).'/vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__);
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$source = DB::connection();

if ($source->getDriverName() !== 'pgsql') {
    throw new RuntimeException('Koneksi aplikasi harus PostgreSQL untuk runner ini.');
}

$snapshot = function (Connection $connection): string {
    $columns = $connection->select("SELECT table_schema, table_name, column_name, udt_name, is_nullable, column_default FROM information_schema.columns WHERE table_schema = 'public' ORDER BY table_name, ordinal_position");
    $migrations = $connection->table('migrations')->orderBy('id')->get()->toArray();
    $counts = [];
    foreach (['users', 'employees', 'departments', 'tickets', 'work_tasks', 'ltro_assets', 'ltro_mttr_records', 'atk_items', 'atk_requests', 'outgoing_letters'] as $table) {
        $counts[$table] = $connection->table($table)->count();
    }

    return hash('sha256', serialize([$columns, $migrations, $counts]));
};

$before = $snapshot($source);
$sourceDatabase = $source->selectOne('SELECT current_database() AS name')->name;
$version = $source->selectOne("SELECT current_setting('server_version') AS version")->version;
$database = 'ik_it_test_'.bin2hex(random_bytes(8));
if ($database === $sourceDatabase || ! preg_match('/^ik_it_test_[a-f0-9]{16}$/', $database)) {
    throw new RuntimeException('Nama database pengujian tidak aman.');
}

$created = false;
$identity = null;
$exitCode = 1;

try {
    $source->statement('CREATE DATABASE "'.$database.'" TEMPLATE template0 ENCODING \'UTF8\'');
    $created = true;
    $identity = $source->selectOne('SELECT oid, datdba FROM pg_database WHERE datname = ?', [$database]);
    echo 'PostgreSQL '.$version.': database uji terpisah dibuat.'.PHP_EOL;

    $connectionConfig = $source->getConfig();
    $processEnvironment = array_merge(getenv(), [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_URL' => '',
        'DB_HOST' => (string) $connectionConfig['host'],
        'DB_PORT' => (string) $connectionConfig['port'],
        'DB_DATABASE' => $database,
        'DB_USERNAME' => (string) $connectionConfig['username'],
        'DB_PASSWORD' => (string) $connectionConfig['password'],
        'DB_SSLMODE' => (string) ($connectionConfig['sslmode'] ?? 'prefer'),
        'IT_INFRASTRUCTURE_TEST_DATABASE' => $database,
    ]);

    $process = proc_open([
        PHP_BINARY,
        $root.'/vendor/phpunit/phpunit/phpunit',
        '-c', $root.'/phpunit.it-infrastructure-postgres.xml',
        '--do-not-cache-result',
    ], [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $root, $processEnvironment, ['bypass_shell' => true]);

    if (! is_resource($process)) {
        throw new RuntimeException('PHPUnit PostgreSQL tidak dapat dijalankan.');
    }
    $exitCode = proc_close($process);
} finally {
    if ($created) {
        $current = $source->selectOne('SELECT oid, datdba FROM pg_database WHERE datname = ?', [$database]);
        if (! $identity || ! $current || $current->oid !== $identity->oid || $current->datdba !== $identity->datdba || $source->selectOne('SELECT current_database() AS name')->name !== $sourceDatabase) {
            throw new RuntimeException('Identitas database sementara berubah; pembersihan dihentikan.');
        }
        $source->statement('DROP DATABASE "'.$database.'"');
        echo 'Database uji sementara sudah dibersihkan.'.PHP_EOL;
    }

    if ($before !== $snapshot($source)) {
        throw new RuntimeException('Metadata atau jumlah record database utama berubah selama pengujian; perlu ditinjau.');
    }
    echo 'PASS: schema public, daftar migration, dan jumlah record tabel utama tetap sama.'.PHP_EOL;
}

exit($exitCode);
