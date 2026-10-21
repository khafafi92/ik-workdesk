<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$runtime = DB::connection()->getConfig();
if (PHP_SAPI !== 'cli' || ! $app->environment('local') || $runtime['driver'] !== 'pgsql'
    || ! in_array($runtime['host'], ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('This benchmark requires a local CLI and loopback PostgreSQL.');
}
$options = getopt('', ['output:', 'pg-bin:']);
$output = $options['output'] ?? $root.'/output/concurrency-review-20261021';
$pgBin = $options['pg-bin'] ?? 'C:/Program Files/PostgreSQL/18/bin';
if (! is_dir($output)) {
    mkdir($output, 0777, true);
}
$output = realpath($output);
$token = bin2hex(random_bytes(8));
$database = 'ik_load_test_'.$token;
$temporary = $root.'/storage/framework/loadtest-'.$token;
mkdir($temporary, 0700);
file_put_contents($temporary.'/.gitignore', "*\n");
foreach (['sessions', 'views', 'cache'] as $folder) {
    mkdir($temporary.'/'.$folder, 0700);
}
$adminConfig = $runtime;
if (is_file($root.'/.env.database-admin')) {
    $admin = Dotenv\Dotenv::parse(file_get_contents($root.'/.env.database-admin'));
    if (($admin['DB_DATABASE'] ?? null) !== $runtime['database'] || ($admin['DB_HOST'] ?? null) !== $runtime['host']) {
        throw new RuntimeException('Database administrator target differs from the local application.');
    }
    $adminConfig['username'] = $admin['DB_USERNAME'];
    $adminConfig['password'] = $admin['DB_PASSWORD'];
}
$adminConfig['url'] = null;
config(['database.connections.load_admin' => $adminConfig]);
$source = DB::connection('load_admin');
$sourceName = $source->selectOne('SELECT current_database() AS name')->name;
if ($sourceName !== $runtime['database'] || ! preg_match('/^ik_load_test_[a-f0-9]{16}$/', $database)) {
    throw new RuntimeException('Unexpected source or temporary database identity.');
}
$snapshot = function () use ($source): array {
    $tables = $source->select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
    $counts = [];
    foreach ($tables as $table) {
        $name = str_replace('"', '""', $table->tablename);
        $counts[$table->tablename] = (int) $source->selectOne('SELECT count(*) AS n FROM public."'.$name.'"')->n;
    }
    $columns = $source->select("SELECT table_name, column_name, udt_name, column_default FROM information_schema.columns WHERE table_schema = 'public' ORDER BY table_name, ordinal_position");

    return ['counts' => $counts, 'schema_hash' => hash('sha256', serialize($columns))];
};
$before = $snapshot();
$identity = null;
$exit = 1;
$pgEnvironment = ['PGPASSWORD' => (string) $adminConfig['password']];
$pgArguments = ['-h', $runtime['host'], '-p', (string) $runtime['port'], '-U', $adminConfig['username']];
$run = function (array $command, array $environment = []) use ($root): Process {
    $process = new Process($command, $root, $environment);
    $process->setTimeout(1200);
    $process->mustRun();

    return $process;
};

try {
    $source->statement('CREATE DATABASE "'.$database.'" TEMPLATE template0 ENCODING \'UTF8\'');
    $identity = $source->selectOne('SELECT oid, datdba FROM pg_database WHERE datname = ?', [$database]);
    echo "Copying local PostgreSQL data into an isolated database.\n";
    $run([$pgBin.'/pg_dump.exe', ...$pgArguments, '-d', $sourceName, '--no-owner', '--no-acl', '-Fc', '-f', $temporary.'/snapshot.dump'], $pgEnvironment);
    $run([$pgBin.'/pg_restore.exe', ...$pgArguments, '-d', $database, '--no-owner', '--no-acl', '--exit-on-error', $temporary.'/snapshot.dump'], $pgEnvironment);
    unlink($temporary.'/snapshot.dump');

    $testConnection = [...$adminConfig, 'database' => $database];
    config(['database.connections.load_target' => $testConnection]);
    $target = DB::connection('load_target');
    if ($target->selectOne('SELECT current_database() AS name')->name !== $database) {
        throw new RuntimeException('Fixture connection is not isolated.');
    }
    $password = bin2hex(random_bytes(18));
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    for ($i = 1; $i <= 40; $i++) {
        $target->table('users')->insert([
            'name' => 'Concurrent test '.$i, 'email' => 'load-'.$token.'-'.$i.'@example.test',
            'password' => $hash, 'is_admin' => true, 'email_verified_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    if (! password_verify($password, $target->table('users')->where('email', 'load-'.$token.'-1@example.test')->value('password'))) {
        throw new RuntimeException('Test credentials failed database verification.');
    }
    $configuration = config()->all();
    $configuration['app']['env'] = 'local';
    $configuration['app']['debug'] = false;
    $configuration['database']['default'] = 'pgsql';
    $configuration['database']['connections'] = ['pgsql' => $testConnection];
    $configuration['session'] = [...$configuration['session'], 'driver' => 'file', 'files' => $temporary.'/sessions',
        'cookie' => 'loadtest_'.$token, 'domain' => null, 'secure' => false, 'connection' => null, 'store' => null];
    $configuration['cache']['default'] = 'file';
    $configuration['cache']['stores']['file']['path'] = $temporary.'/cache';
    $configuration['cache']['stores']['file']['lock_path'] = $temporary.'/cache';
    $configuration['view']['compiled'] = $temporary.'/views';
    $configuration['queue']['default'] = 'null';
    $configuration['broadcasting']['default'] = 'null';
    $configuration['mail']['default'] = 'array';
    foreach ($configuration['mail']['mailers'] as &$mailer) {
        $mailer = ['transport' => 'array'];
    }
    unset($mailer);
    $configuration['logging']['default'] = 'single';
    $configuration['logging']['channels']['single']['path'] = $temporary.'/laravel.log';
    $configuration['hashing']['bcrypt']['rounds'] = 12;
    file_put_contents($temporary.'/config.php', '<?php return '.var_export($configuration, true).';');
    $router = '<?php define("LARAVEL_START", microtime(true)); require '.var_export($root.'/vendor/autoload.php', true).';'
        .' $app = require '.var_export($root.'/bootstrap/app.php', true).';'
        .' $app->make(\\Illuminate\\Contracts\\Http\\Kernel::class)->bootstrap();'
        .' if (! $app->configurationIsCached() || config("database.connections.pgsql.database") !== '.var_export($database, true)
        .' || config("database.connections.pgsql.url") !== null || config("session.files") !== '.var_export($temporary.'/sessions', true).')'
        .' { throw new \\RuntimeException("Load-test isolation check failed"); }'
        .' $app->handleRequest(\\Illuminate\\Http\\Request::capture());';
    file_put_contents($temporary.'/router.php', $router);
    file_put_contents($temporary.'/credentials.json', json_encode(['token' => $token, 'password' => $password]));
    file_put_contents($output.'/environment.json', json_encode([
        'php' => PHP_VERSION, 'postgres' => $source->selectOne('SHOW server_version')->server_version,
        'database_isolated' => true, 'accounts' => 40, 'account_role' => 'is_admin=true',
        'bcrypt_cost' => 12, 'session' => 'file', 'cache' => 'file', 'source_table_counts' => $before['counts'],
        'postgres_max_connections' => $source->selectOne('SHOW max_connections')->max_connections,
        'source_database_bytes' => $source->selectOne('SELECT pg_database_size(current_database()) AS bytes')->bytes,
        'limits' => ['No static assets/browser rendering', 'No production server', 'No external notifications',
            'No uploads/imports/AI/realtime', 'Synthetic administrator accounts, current local data volume'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    DB::disconnect('load_target');
    $process = new Process(['python', $root.'/scripts/test-concurrent-users.py', '--root', $root,
        '--runtime', $temporary, '--output', $output, '--php', PHP_BINARY], $root, ['APP_CONFIG_CACHE' => 'storage/framework/loadtest-'.$token.'/config.php']);
    $process->setTimeout(1800);
    $exit = $process->run(function ($type, $buffer): void {
        echo $buffer;
    });
} finally {
    if ($identity !== null) {
        $current = $source->selectOne('SELECT oid, datdba FROM pg_database WHERE datname = ?', [$database]);
        if (! $current || $current->oid !== $identity->oid || $current->datdba !== $identity->datdba
            || $source->selectOne('SELECT current_database() AS name')->name !== $sourceName) {
            throw new RuntimeException('Temporary database identity changed; cleanup stopped.');
        }
        DB::disconnect('load_target');
        $source->statement('DROP DATABASE "'.$database.'"');
    }
    $after = $snapshot();
    file_put_contents($output.'/isolation.json', json_encode([
        'source_schema_and_counts_unchanged' => $before === $after,
        'temporary_database_removed' => $identity !== null,
        'note' => 'Schema and row counts comparison does not prove every source row value is unchanged.',
    ], JSON_PRETTY_PRINT));
    $resolved = realpath($temporary);
    if ($resolved && dirname($resolved) === realpath($root.'/storage/framework')
        && preg_match('/^loadtest-[a-f0-9]{16}$/', basename($resolved))) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($resolved);
    }
    if ($before !== $after) {
        throw new RuntimeException('Source database schema or row counts changed during the benchmark.');
    }
    echo "Isolation verified; temporary database and private runtime removed.\n";
}

exit($exit);
