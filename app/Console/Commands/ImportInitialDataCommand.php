<?php

namespace App\Console\Commands;

use App\Services\InitialImport\InitialDataImporter;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImportInitialDataCommand extends Command
{
    protected $signature = 'initial-data:import
        {directory : Direktorijum sa opcionim CSV fajlovima}
        {--key= : Jedinstvena oznaka početnog uvoza, npr. tosa ili paki}
        {--dry-run : Provera fajlova i konflikata bez upisa u bazu}
        {--company-id= : Postojeća firma; bez opcije koristi se jedina firma u bazi}';

    protected $description = 'Proverava i uvozi početne CSV podatke za novu ili postojeću firmu, bez demo seedera.';

    public function handle(InitialDataImporter $importer): int
    {
        try {
            $result = $importer->run((string) $this->argument('directory'), (string) $this->option('key'), (bool) $this->option('dry-run'), $this->option('company-id'));
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $location => $messages) {
                foreach ($messages as $message) {
                    $this->error($location.': '.$message);
                }
            }

            return self::FAILURE;
        } catch (QueryException $exception) {
            // Do not print SQL/bindings, which can contain personal data.
            $this->error('Uvoz nije uspeo zbog greške baze (SQLSTATE '.$exception->getCode().'). Ništa iz ovog uvoza nije sačuvano.');

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Uvoz nije uspeo ('.class_basename($exception).'). Ništa iz ovog uvoza nije sačuvano.');

            return self::FAILURE;
        }

        $this->info(match ($result['status']) {
            'empty' => 'Nema CSV podataka za uvoz. Ništa nije upisano.',
            'validated' => 'Provera uspešna. Ništa nije upisano u bazu.',
            'skipped' => 'Ovaj uvoz je već završen. Nema novih upisa ni izmena.',
            'imported' => 'Uvoz uspešan. ID firme: '.$result['company_id'],
        });
        $this->table(['Fajl', 'Broj zapisa'], collect($result['counts'])->map(fn ($count, $file) => [$file.'.csv', $count])->values()->all());

        return self::SUCCESS;
    }
}
