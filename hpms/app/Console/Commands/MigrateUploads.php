<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Copies the only permanent uploads (logos and avatars) from a local disk to
 * the production uploads disk. Source files are never modified or deleted.
 */
class MigrateUploads extends Command
{
    protected $signature = 'uploads:migrate
        {--from=public : Source disk name}
        {--to=s3 : Destination disk name}
        {--dry-run : Report what would be copied without writing}
        {--overwrite : Replace objects that already exist at the destination}
        {--prefix=* : Limit to these top-level directories}';

    protected $description = 'Copy chamber logos and avatars to the production uploads disk (Cloudflare R2)';

    /** Directories the application treats as permanent uploads. */
    private const PREFIXES = ['avatars', 'branding'];

    public function handle(): int
    {
        $from = (string) $this->option('from');
        $to = (string) $this->option('to');

        if ($from === $to) {
            $this->error('Source and destination disks must be different.');

            return self::FAILURE;
        }

        $prefixes = array_values(array_filter((array) $this->option('prefix')));
        $prefixes = $prefixes === [] ? self::PREFIXES : $prefixes;

        foreach ($prefixes as $prefix) {
            if (! in_array($prefix, self::PREFIXES, true)) {
                $this->error("Unsupported prefix [{$prefix}]. Use avatars and/or branding.");

                return self::FAILURE;
            }
        }

        $source = Storage::disk($from);
        $destination = Storage::disk($to);
        $dryRun = (bool) $this->option('dry-run');
        $overwrite = (bool) $this->option('overwrite');

        $this->info(($dryRun ? 'DRY RUN: ' : '')."copying uploads from [{$from}] to [{$to}]");

        $copied = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($prefixes as $prefix) {
            foreach ($source->allFiles($prefix) as $path) {
                if (! $overwrite && $destination->exists($path)) {
                    $skipped++;
                    $this->line("skip    {$path} (already exists)");

                    continue;
                }

                $bytes = $source->size($path);

                if ($dryRun) {
                    $copied++;
                    $this->line("would   {$path} ({$bytes} bytes)");

                    continue;
                }

                try {
                    $this->copy($source, $destination, $path, $bytes);
                    $copied++;
                    $this->line("copied  {$path} ({$bytes} bytes)");
                } catch (Throwable $exception) {
                    $failed++;
                    $this->error("failed  {$path}: {$exception->getMessage()}");
                }
            }
        }

        $this->newLine();
        $this->table(['Copied', 'Skipped', 'Failed'], [[$copied, $skipped, $failed]]);

        if ($failed > 0) {
            $this->error('Some files were not copied. The source files were left untouched; fix the error and run the command again.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info('Dry run only. Re-run without --dry-run to copy the listed files.');
        }

        return self::SUCCESS;
    }

    private function copy(Filesystem $source, Filesystem $destination, string $path, int $expectedBytes): void
    {
        $stream = $source->readStream($path);

        if ($stream === null) {
            throw new \RuntimeException('source stream could not be opened');
        }

        try {
            if (! $destination->writeStream($path, $stream)) {
                throw new \RuntimeException('destination rejected the upload');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $destination->exists($path)) {
            throw new \RuntimeException('object missing after upload');
        }

        $actualBytes = $destination->size($path);

        if ($actualBytes !== $expectedBytes) {
            throw new \RuntimeException("size mismatch: expected {$expectedBytes} bytes, found {$actualBytes}");
        }
    }
}
