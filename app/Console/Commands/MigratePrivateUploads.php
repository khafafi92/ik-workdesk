<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MigratePrivateUploads extends Command
{
    protected $signature = 'storage:migrate-private-uploads {--dry-run}';

    protected $description = 'Move sensitive legacy uploads from public to private storage';

    private const DIRECTORIES = [
        'attendance-imports',
        'service-request-attachments',
        'ticket-comments',
        'work-task-findings',
        'work-task-finding-responses',
    ];

    public function handle(): int
    {
        $publicDisk = Storage::disk('public');
        $privateDisk = Storage::disk('local');
        $files = collect(self::DIRECTORIES)
            ->flatMap(fn (string $directory) => $publicDisk->allFiles($directory))
            ->unique()
            ->values();

        if ($files->isEmpty()) {
            $this->info('No legacy public uploads need migration.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $files->each(fn (string $file) => $this->line($file));
            $this->info("{$files->count()} file(s) would be migrated.");

            return self::SUCCESS;
        }

        $migrated = 0;

        foreach ($files as $file) {
            $sourcePath = $publicDisk->path($file);
            $destinationPath = $privateDisk->path($file);

            if (! $privateDisk->exists($file)) {
                $stream = $publicDisk->readStream($file);

                if ($stream === false) {
                    throw new RuntimeException("Unable to read {$file}.");
                }

                try {
                    $privateDisk->writeStream($file, $stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            }

            if (
                ! is_file($destinationPath)
                || hash_file('sha256', $sourcePath) !==
                    hash_file('sha256', $destinationPath)
            ) {
                throw new RuntimeException(
                    "Checksum verification failed for {$file}."
                );
            }

            $publicDisk->delete($file);
            $migrated++;
        }

        $this->info("Migrated and verified {$migrated} file(s).");

        return self::SUCCESS;
    }
}
