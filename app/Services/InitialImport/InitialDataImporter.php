<?php

namespace App\Services\InitialImport;

use App\Models\Company;
use App\Models\ConstructionSite;
use App\Models\Excavator;
use App\Models\Machine;
use App\Models\Subcontractor;
use App\Models\Supplier;
use App\Models\Truck;
use App\Models\Worker;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class InitialDataImporter
{
    public function __construct(private InitialDataCsv $csv) {}

    public function run(string $directory, string $key, bool $dryRun = false, int|string|null $companyId = null): array
    {
        if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $key)) {
            throw ValidationException::withMessages(['key' => 'Navedite --key (do 64 mala slova, cifre, - ili _), npr. --key=tosa.']);
        }
        if ($companyId !== null) {
            $companyId = filter_var($companyId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($companyId === false) {
                throw ValidationException::withMessages(['company-id' => 'ID firme mora biti pozitivan ceo broj.']);
            }
        }
        $data = $this->csv->read($directory);
        if (array_sum(array_map('count', $data)) === 0) {
            return ['status' => 'empty', 'company_id' => null, 'counts' => array_map('count', $data)];
        }
        if (! Schema::hasTable('initial_data_imports')) {
            throw ValidationException::withMessages(['baza' => 'Nedostaje tabela evidencije uvoza. Najpre pokrenite php artisan migrate.']);
        }

        return DB::transaction(function () use ($data, $key, $dryRun, $companyId) {
            $previous = DB::table('initial_data_imports')->where('import_key', $key)->first();
            $company = null;
            if ($data['firma']) {
                if ($companyId !== null) {
                    throw ValidationException::withMessages(['company-id' => 'Koristite ili firma.csv za novu firmu ili --company-id za postojeću, ne oba.']);
                }
            } else {
                $company = $this->existingCompany($companyId ?? $previous?->company_id);
            }
            // Keep old fingerprints for new-company imports; scope partial imports to their company.
            $contents = array_map('array_values', $data);
            $fingerprint = hash('sha256', json_encode(
                $company ? ['company_id' => $company->id, 'files' => $contents] : $contents,
                JSON_THROW_ON_ERROR,
            ));
            if ($previous) {
                if ($previous->fingerprint !== $fingerprint) {
                    throw ValidationException::withMessages(['key' => 'Ovaj uvoz je već završen sa drugim sadržajem. Početni uvoz ne ažurira postojeće podatke.']);
                }

                return ['status' => 'skipped', 'company_id' => $previous->company_id, 'counts' => array_map('count', $data)];
            }
            if (DB::table('initial_data_imports')->where('fingerprint', $fingerprint)->exists()) {
                throw ValidationException::withMessages(['key' => 'Ovi podaci su već uvezeni pod drugim ključem.']);
            }
            $this->validateDatabaseConflicts($data, $company);

            if ($dryRun) {
                return ['status' => 'validated', 'company_id' => $company?->id, 'counts' => array_map('count', $data)];
            }

            $models = [];
            if (! $company) {
                $companyRow = reset($data['firma']);
                $company = Company::create(Arr::except($companyRow, ['sifra']));
                $models['firma'][$companyRow['sifra']] = $company;
            }

            foreach ([
                'radnici' => Worker::class,
                'gradilista' => ConstructionSite::class,
                'dobavljaci' => Supplier::class,
                'podizvodjaci' => Subcontractor::class,
            ] as $file => $class) {
                foreach ($data[$file] as $row) {
                    $attributes = Arr::except($row, ['sifra']);
                    $attributes['company_id'] = $company->id;
                    if ($file === 'radnici') {
                        // The existing database column is NOT NULL; an unknown surname is empty.
                        $attributes['last_name'] ??= '';
                        if ($attributes['password'] !== null) {
                            // CSV passwords are always plaintext, including strings resembling hashes.
                            $attributes['password'] = Hash::make($attributes['password']);
                        }
                    }
                    $models[$file][$row['sifra']] = $class::create($attributes);
                }
            }
            foreach ($data['masine'] as $row) {
                $attributes = Arr::except($row, ['sifra', 'owner_sifra', 'initial_work_hours', 'initial_mileage']);
                $attributes['company_id'] = $company->id;
                $attributes['owner_id'] = $row['owner_type'] === 'company'
                    ? $company->id
                    : $models[InitialDataCsv::OWNER_FILES[$row['owner_type']]][$row['owner_sifra']]->id;
                $machine = Machine::create($attributes);
                if ($row['type'] === 'excavator') {
                    Excavator::create([
                        'machine_id' => $machine->id,
                        'initial_work_hours' => $row['initial_work_hours'],
                        'total_work_hours' => $row['initial_work_hours'],
                    ]);
                } else {
                    Truck::create(['machine_id' => $machine->id, 'initial_mileage' => $row['initial_mileage']]);
                }
            }
            foreach ($data['sefovi_gradilista'] as $row) {
                $models['radnici'][$row['sef_sifra']]->constructionSites()->attach($models['gradilista'][$row['gradiliste_sifra']]->id);
            }

            // Unique key/fingerprint also prevent concurrent double imports; all writes roll back on conflict.
            DB::table('initial_data_imports')->insert([
                'import_key' => $key,
                'fingerprint' => $fingerprint,
                'company_id' => $company->id,
                'created_at' => now(),
            ]);

            return ['status' => 'imported', 'company_id' => $company->id, 'counts' => array_map('count', $data)];
        });
    }

    private function validateDatabaseConflicts(array $data, ?Company $company): void
    {
        $errors = [];
        foreach (['radnici' => ['workers', 'username'], 'masine' => ['machines', 'license_plate']] as $file => [$table, $field]) {
            foreach ($data[$file] as $record => $row) {
                // Include soft-deleted records: the database still reserves these unique identifiers.
                if ($row[$field] !== null && DB::table($table)->where($field, $row[$field])->exists()) {
                    $errors[$file.'.csv, zapis '.$record.' / '.$field][] = 'Vrednost već postoji u ciljnoj bazi.';
                }
            }
        }
        $companyRow = reset($data['firma']);
        if ($companyRow && $companyRow['pib'] !== null && DB::table('companies')->where('pib', $companyRow['pib'])->exists()) {
            $errors['firma.csv / pib'][] = 'Firma sa ovim PIB-om već postoji. Ovo je uvoz nove firme, ne dopuna postojeće.';
        }
        if ($company) {
            foreach (['dobavljaci' => 'suppliers', 'podizvodjaci' => 'subcontractors'] as $file => $table) {
                foreach ($data[$file] as $record => $row) {
                    if ($row['pib'] !== null && DB::table($table)->where('company_id', $company->id)->where('pib', $row['pib'])->exists()) {
                        $errors[$file.'.csv, zapis '.$record.' / pib'][] = 'PIB već postoji u izabranoj firmi.';
                    }
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function existingCompany(?int $companyId): Company
    {
        if ($companyId !== null) {
            $company = Company::find($companyId);
            if (! $company) {
                throw ValidationException::withMessages(['company-id' => 'Izabrana firma ne postoji ili je obrisana.']);
            }

            return $company;
        }
        $companies = Company::limit(2)->get();
        if ($companies->isEmpty()) {
            throw ValidationException::withMessages(['firma' => 'U bazi nema firme. Napravite firmu ili dostavite firma.csv.']);
        }
        if ($companies->count() > 1) {
            throw ValidationException::withMessages(['company-id' => 'U bazi postoji više firmi. Izaberite ciljnu pomoću --company-id=ID.']);
        }

        return $companies->first();
    }
}
