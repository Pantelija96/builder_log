<?php

namespace App\Console\Commands;

use App\Services\Database\ClientDatabaseRebuildService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class RebuildClientDatabase extends Command
{
    protected $signature = 'client:rebuild {--client=} {--source=} {--backup=} {--export=} {--rehearsal=}
        {--dry-run} {--confirm-database=} {--writes-stopped}';

    protected $description = 'Verify or explicitly rebuild a client database in place';

    public function handle(ClientDatabaseRebuildService $service): int
    {
        $stage = 'initialization';
        try {
            $report = $service->run($this->options(), function (string $current) use (&$stage): void {
                $stage = $current;
            });
        } catch (Throwable $exception) {
            $message = $exception::class === RuntimeException::class
                ? $exception->getMessage()
                : 'Database or file operation failed ('.get_class($exception).'/'.$exception->getCode().').';
            $this->error("Failure at $stage. ".$message);
            if (! $this->option('dry-run')) {
                $this->warn('Keep application writes stopped. Inspect the target and the verified SQL backup before taking any recovery action.');
            }

            return self::FAILURE;
        }

        $this->info($report['dry_run'] ? 'Rebuild dry-run passed. The target database was read only.' : 'In-place rebuild and validation completed.');
        $this->line(json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
