<?php

namespace App\Console\Commands;

use App\Services\Database\ClientDatabaseRestoreService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class RestoreClientDatabase extends Command
{
    protected $signature = 'client:restore {--client=} {--export=} {--destination=} {--dry-run}';

    protected $description = 'Preflight or restore a verified client snapshot into an explicitly mapped empty database';

    public function handle(ClientDatabaseRestoreService $service): int
    {
        $stage = 'initialization';
        try {
            $report = $service->restore(
                (string) $this->option('client'),
                (string) $this->option('export'),
                (string) $this->option('destination'),
                (bool) $this->option('dry-run'),
                function (string $current) use (&$stage): void {
                    $stage = $current;
                },
            );
        } catch (Throwable $exception) {
            $message = $exception::class === RuntimeException::class ? $exception->getMessage() : 'Database, package or file operation failed ('.get_class($exception).'/'.$exception->getCode().').';
            $this->error("Failure at $stage. ".$message);
            if ($stage !== 'preflight' && $stage !== 'initialization') {
                $this->warn('Destination may contain migration schema or committed data. It was left in place for inspection.');
            }

            return self::FAILURE;
        }

        $this->info($report['dry_run'] ? 'Preflight passed. No database changes made.' : 'Restore and validation completed.');
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
