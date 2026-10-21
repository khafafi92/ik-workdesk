<?php

require dirname(__DIR__).'/vendor/autoload.php';

use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__);
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('local') || $app->configurationIsCached()) {
    throw new RuntimeException('Provisioning ini khusus workspace lokal tanpa config cache.');
}

$config = DB::connection()->getConfig();
if ($config['driver'] !== 'pgsql' || ! in_array($config['host'], ['127.0.0.1', 'localhost', '::1'], true) || filled($config['url'] ?? null)) {
    throw new RuntimeException('Diperlukan koneksi PostgreSQL lokal tanpa DB_URL.');
}

$envPath = $root.'/.env';
$adminPath = $root.'/.env.database-admin';
$originalEnv = file_get_contents($envPath);
$admin = is_file($adminPath) ? Dotenv::parse(file_get_contents($adminPath)) : null;
if ($admin && (($admin['DB_DATABASE'] ?? null) !== $config['database'] || ($admin['DB_HOST'] ?? null) !== $config['host'])) {
    throw new RuntimeException('Target kredensial admin berbeda dari database aplikasi.');
}
$pdo = new PDO('pgsql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'].';sslmode='.($config['sslmode'] ?? 'prefer'), $admin['DB_USERNAME'] ?? $config['username'], $admin['DB_PASSWORD'] ?? $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$adminRole = $pdo->query('SELECT rolsuper FROM pg_roles WHERE rolname = current_user')->fetchColumn();
if (! $adminRole) {
    throw new RuntimeException('Provisioning memerlukan koneksi administrator terpisah.');
}

$role = 'ik_workdesk_runtime_'.substr(hash('sha256', $config['database']), 0, 8);
$existsQuery = $pdo->prepare('SELECT 1 FROM pg_roles WHERE rolname = ?');
$existsQuery->execute([$role]);
$exists = (bool) $existsQuery->fetchColumn();
if ($exists && (! $admin || $config['username'] !== $role)) {
    throw new RuntimeException('Role target sudah ada tetapi tidak dikelola oleh workspace ini.');
}
$password = $exists ? $config['password'] : bin2hex(random_bytes(32));
$identifier = fn (string $value): string => '"'.str_replace('"', '""', $value).'"';
$dotenvValue = fn (string $value): string => '"'.str_replace(['\\', '"', '$', "\r", "\n"], ['\\\\', '\\"', '\\$', '\\r', '\\n'], $value).'"';
$writePrivate = function (string $path, string $contents) use ($root): void {
    $temporary = $path.'.security-tmp-'.bin2hex(random_bytes(8));
    $handle = fopen($temporary, 'x');
    if (! $handle) {
        throw new RuntimeException('File konfigurasi privat tidak dapat dibuat.');
    }
    fclose($handle);
    try {
        if (PHP_OS_FAMILY === 'Windows') {
            (new Process(['powershell.exe', '-NoProfile', '-NonInteractive', '-File', $root.'/scripts/protect-environment.ps1', '-Path', $temporary]))->mustRun();
        } elseif (! chmod($temporary, 0600)) {
            throw new RuntimeException('Izin file konfigurasi privat tidak dapat dibatasi.');
        }
        if (file_put_contents($temporary, $contents, LOCK_EX) === false || ! rename($temporary, $path)) {
            throw new RuntimeException('Konfigurasi privat tidak dapat diaktifkan.');
        }
    } catch (Throwable $exception) {
        unlink($temporary);
        throw $exception;
    }
};

if (! $admin) {
    $adminValues = ['APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '',
        'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'], 'DB_DATABASE' => $config['database'],
        'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password'], 'DB_SSLMODE' => $config['sslmode'] ?? 'prefer'];
    $writePrivate($adminPath, implode(PHP_EOL, array_map(fn ($key, $value) => $key.'='.$dotenvValue($value), array_keys($adminValues), array_values($adminValues))).PHP_EOL);
}

try {
    $pdo->beginTransaction();
    if (! $exists) {
        $pdo->exec('CREATE ROLE '.$identifier($role).' LOGIN PASSWORD '.$pdo->quote($password).' NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS NOINHERIT');
    }
    $pdo->exec('GRANT CONNECT ON DATABASE '.$identifier($config['database']).' TO '.$identifier($role));
    $pdo->exec('GRANT USAGE ON SCHEMA public TO '.$identifier($role));
    $pdo->exec('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO '.$identifier($role));
    $pdo->exec('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO '.$identifier($role));
    $owners = $pdo->query("SELECT DISTINCT pg_get_userbyid(relowner) FROM pg_class JOIN pg_namespace ON pg_namespace.oid = relnamespace WHERE nspname = 'public' AND relkind IN ('r', 'p', 'S')")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($owners as $owner) {
        $pdo->exec('ALTER DEFAULT PRIVILEGES FOR ROLE '.$identifier($owner).' IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO '.$identifier($role));
        $pdo->exec('ALTER DEFAULT PRIVILEGES FOR ROLE '.$identifier($owner).' IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO '.$identifier($role));
    }
    $pdo->exec('SET LOCAL ROLE '.$identifier($role));
    if ($pdo->query("SELECT has_schema_privilege('public', 'CREATE')")->fetchColumn()) {
        throw new RuntimeException('Role runtime masih memiliki CREATE pada schema public; periksa grant PUBLIC terlebih dahulu.');
    }
    $pdo->query('SELECT count(*) FROM migrations')->fetchColumn();
    $pdo->exec('RESET ROLE');
    $pdo->commit();

    $runtime = new PDO('pgsql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'].';sslmode='.($config['sslmode'] ?? 'prefer'), $role, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $runtime->query('SELECT count(*) FROM users')->fetchColumn();
    $updates = ['DB_USERNAME' => $role, 'DB_PASSWORD' => $password, 'DB_URL' => '', 'APP_DEBUG' => 'false', 'SESSION_HTTP_ONLY' => 'true', 'SESSION_SAME_SITE' => 'lax'];
    $newEnv = $originalEnv;
    foreach ($updates as $key => $value) {
        $line = $key.'='.$dotenvValue($value);
        $newEnv = preg_match('/^'.preg_quote($key, '/').'=/m', $newEnv)
            ? preg_replace_callback('/^'.preg_quote($key, '/').'=.*$/m', fn () => $line, $newEnv)
            : rtrim($newEnv).PHP_EOL.$line.PHP_EOL;
    }
    $writePrivate($envPath, $newEnv);
    echo 'PASS: akun runtime terbatas aktif; debug dinonaktifkan. Kredensial admin tersimpan terpisah di .env.database-admin (jangan deploy file ini).'.PHP_EOL;
} catch (Throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Provisioning gagal; konfigurasi koneksi aplikasi belum dialihkan. Tidak ada kredensial yang dicetak.'.PHP_EOL);
    exit(1);
}
