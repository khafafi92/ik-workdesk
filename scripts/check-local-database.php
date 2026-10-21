<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$configuration = DB::connection()->getConfig();
if (! $app->environment('local') || ($configuration['driver'] ?? null) !== 'pgsql'
    || ! in_array($configuration['host'] ?? null, ['127.0.0.1', 'localhost', '::1'], true)) {
    fwrite(STDERR, "This check requires the local PostgreSQL database.\n");
    exit(1);
}

$quote = fn (string $identifier): string => '"'.str_replace('"', '""', $identifier).'"';
DB::beginTransaction();
try {
    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
    $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE' ORDER BY table_name");
    $counts = [];
    foreach ($tables as $table) {
        $counts[$table->table_name] = (int) DB::selectOne('SELECT count(*) AS total FROM "public".'.$quote($table->table_name))->total;
    }

    $foreignKeys = DB::select(<<<'SQL'
        SELECT c.conname, c.convalidated, s.relname AS source_table, t.relname AS target_table,
            tn.nspname AS target_schema,
            array_to_json(ARRAY(SELECT a.attname FROM unnest(c.conkey) WITH ORDINALITY AS k(attnum, ord)
                JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = k.attnum ORDER BY k.ord)) AS source_columns,
            array_to_json(ARRAY(SELECT a.attname FROM unnest(c.confkey) WITH ORDINALITY AS k(attnum, ord)
                JOIN pg_attribute a ON a.attrelid = c.confrelid AND a.attnum = k.attnum ORDER BY k.ord)) AS target_columns
        FROM pg_constraint c
        JOIN pg_class s ON s.oid = c.conrelid
        JOIN pg_namespace sn ON sn.oid = s.relnamespace
        JOIN pg_class t ON t.oid = c.confrelid
        JOIN pg_namespace tn ON tn.oid = t.relnamespace
        WHERE c.contype = 'f' AND sn.nspname = 'public'
        ORDER BY s.relname, c.conname
        SQL);
    $checks = [];
    foreach ($foreignKeys as $foreignKey) {
        $sources = json_decode($foreignKey->source_columns, true, flags: JSON_THROW_ON_ERROR);
        $targets = json_decode($foreignKey->target_columns, true, flags: JSON_THROW_ON_ERROR);
        $notNull = [];
        $matches = [];
        foreach ($sources as $index => $column) {
            $notNull[] = 's.'.$quote($column).' IS NOT NULL';
            $matches[] = 't.'.$quote($targets[$index]).' = s.'.$quote($column);
        }
        $sql = 'SELECT count(*) AS total FROM "public".'.$quote($foreignKey->source_table).' s WHERE '.implode(' AND ', $notNull)
            .' AND NOT EXISTS (SELECT 1 FROM '.$quote($foreignKey->target_schema).'.'.$quote($foreignKey->target_table).' t WHERE '.implode(' AND ', $matches).')';
        $checks[] = [
            'constraint' => $foreignKey->conname,
            'source' => $foreignKey->source_table,
            'target' => $foreignKey->target_table,
            'validated' => $foreignKey->convalidated,
            'orphans' => (int) DB::selectOne($sql)->total,
        ];
    }

    $unvalidated = DB::select("SELECT c.conname, c.contype, t.relname AS table_name FROM pg_constraint c JOIN pg_class t ON t.oid = c.conrelid JOIN pg_namespace n ON n.oid = t.relnamespace WHERE n.nspname = 'public' AND NOT c.convalidated ORDER BY t.relname, c.conname");
    $pendingJobs = DB::select(<<<'SQL'
        SELECT queue, count(*) AS total, max(attempts) AS max_attempts,
            count(*) FILTER (WHERE reserved_at IS NOT NULL) AS reserved,
            to_timestamp(min(created_at)) AT TIME ZONE 'Asia/Jakarta' AS oldest_created_wib,
            to_timestamp(min(available_at)) AT TIME ZONE 'Asia/Jakarta' AS oldest_available_wib,
            payload::jsonb ->> 'displayName' AS job_class
        FROM jobs GROUP BY queue, payload::jsonb ->> 'displayName' ORDER BY queue, job_class
        SQL);
    $failedJobs = DB::select(<<<'SQL'
        SELECT queue, count(*) AS total, min(failed_at) AS oldest_failed_at,
            max(failed_at) AS newest_failed_at, split_part(exception, ':', 1) AS exception_class,
            payload::jsonb ->> 'displayName' AS job_class
        FROM failed_jobs GROUP BY queue, split_part(exception, ':', 1), payload::jsonb ->> 'displayName'
        ORDER BY queue, exception_class, job_class
        SQL);
    echo json_encode([
        'table_count' => count($counts),
        'table_counts' => $counts,
        'foreign_key_count' => count($checks),
        'foreign_keys' => $checks,
        'orphan_count' => array_sum(array_column($checks, 'orphans')),
        'unvalidated_constraints' => $unvalidated,
        'failed_jobs' => $counts['failed_jobs'] ?? null,
        'pending_jobs' => $counts['jobs'] ?? null,
        'pending_job_groups' => $pendingJobs,
        'failed_job_groups' => $failedJobs,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
} finally {
    DB::rollBack();
}
