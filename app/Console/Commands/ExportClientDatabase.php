<?php

namespace App\Console\Commands;

use App\Services\Database\ClientDatabaseExportService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class ExportClientDatabase extends Command
{
    protected $signature = 'client:export {--client=} {--source=} {--operational= : Explicit policy for non-empty job tables (archive)}';

    protected $description = 'Export a consistent read-only snapshot of an explicitly mapped client database';

    public function handle(ClientDatabaseExportService $service): int
    {
        try {
            $manifest = $service->export((string) $this->option('client'), (string) $this->option('source'), $this->option('operational'));
        } catch (Throwable $exception) {
            $message = $exception::class === RuntimeException::class ? $exception->getMessage() : 'Database or file operation failed ('.get_class($exception).'/'.$exception->getCode().').';
            $this->error($message);

            return self::FAILURE;
        }

        $this->info('Completed export '.$manifest['id']);
        $this->table(['Table', 'Rows', 'Policy'], array_map(fn ($name, $row) => [$name, $row['count'], $row['policy']], array_keys($manifest['tables']), $manifest['tables']));

        return self::SUCCESS;
    }
}
