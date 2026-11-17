<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ConstructionSite;
use App\Models\Machine;
use App\Models\Supplier;
use App\Models\Truck;
use App\Models\Worker;
use App\Services\InitialImport\InitialDataImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class InitialDataImportTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function beforeRefreshingDatabase(): void
    {
        // Never use the project's configured/live database, even with cached configuration.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
        ]);
        DB::purge('sqlite');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/builderlog-csv-'.bin2hex(random_bytes(8));
        File::copyDirectory(base_path('tests/initial-import/primeri'), $this->directory);
    }

    protected function tearDown(): void
    {
        if (isset($this->directory)) {
            File::deleteDirectory($this->directory);
        }
        parent::tearDown();
    }

    private function runImport(bool $dryRun = false, string $key = 'tosa'): array
    {
        return app(InitialDataImporter::class)->run($this->directory, $key, $dryRun);
    }

    private function rows(string $file): array
    {
        $handle = fopen($this->directory.'/'.$file.'.csv', 'rb');
        $header = fgetcsv($handle, null, ';', '"', '');
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        $rows = [];
        while (($row = fgetcsv($handle, null, ';', '"', '')) !== false) {
            if ($row !== [null]) {
                $rows[] = array_combine($header, $row);
            }
        }
        fclose($handle);

        return [$header, $rows];
    }

    private function writeRows(string $file, array $header, array $rows): void
    {
        $handle = fopen($this->directory.'/'.$file.'.csv', 'wb');
        fputcsv($handle, $header, ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($column) => $row[$column], $header), ';', '"', '');
        }
        fclose($handle);
    }

    private function change(string $file, int $row, array $changes): void
    {
        [$header, $rows] = $this->rows($file);
        $rows[$row] = array_replace($rows[$row], $changes);
        $this->writeRows($file, $header, $rows);
    }

    private function assertEmptyImport(): void
    {
        foreach (['companies', 'workers', 'construction_sites', 'suppliers', 'subcontractors', 'machines', 'excavators', 'trucks', 'construction_site_worker', 'initial_data_imports'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_dry_run_validates_all_files_without_writing(): void
    {
        $this->artisan('initial-data:import', ['directory' => $this->directory, '--key' => 'tosa', '--dry-run' => true])
            ->expectsOutput('Provera uspešna. Ništa nije upisano u bazu.')
            ->assertSuccessful();
        $this->assertEmptyImport();
    }

    public function test_imports_all_entities_hashes_passwords_and_resolves_codes(): void
    {
        $this->change('dobavljaci', 0, ['phone' => '+38106000000', 'pib' => '001234567', 'is_active' => '-']);
        $this->change('masine', 0, ['initial_work_hours' => '0', 'status' => '-']);
        $this->assertSame('imported', $this->runImport()['status']);
        foreach (['companies' => 1, 'workers' => 5, 'construction_sites' => 2, 'suppliers' => 1, 'subcontractors' => 1, 'machines' => 3, 'excavators' => 2, 'trucks' => 1, 'construction_site_worker' => 2, 'initial_data_imports' => 1] as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $admin = Worker::where('username', 'primer.admin')->firstOrFail();
        $this->assertTrue(Hash::check('Primer123!', $admin->password));
        $this->assertTrue($admin->is_available);
        $this->assertNull($admin->hourly_rate);
        $this->assertNull($admin->phone);
        $this->assertSame('950.50', Worker::where('username', 'primer.bagerista')->firstOrFail()->hourly_rate);
        $this->assertSame('800.00', Worker::where('username', 'primer.vozac')->firstOrFail()->hourly_rate);
        $supplier = Supplier::firstOrFail();
        $this->assertSame('001234567', $supplier->pib);
        $this->assertSame('+38106000000', $supplier->phone);
        $this->assertTrue($supplier->is_active);
        $machine = Machine::where('name', 'CAT 320')->firstOrFail();
        $this->assertTrue($machine->owner->is(Company::firstOrFail()));
        $this->assertSame('0.00', $machine->excavator->initial_work_hours);
        $this->assertEquals(0, $machine->excavator->total_work_hours);
        $this->assertSame('150000.00', Truck::firstOrFail()->initial_mileage);
        $this->assertSame(2, Worker::where('username', 'primer.sef')->firstOrFail()->constructionSites()->count());
        foreach (['workers', 'construction_sites', 'suppliers', 'subcontractors', 'machines'] as $table) {
            $this->assertSame(0, DB::table($table)->where('company_id', '!=', $admin->company_id)->count());
        }
    }

    public function test_all_four_machine_owner_types_and_same_named_sites_remain_distinct(): void
    {
        [$header, $rows] = $this->rows('masine');
        $rows[] = array_replace($rows[0], ['sifra' => 'M4', 'owner_type' => 'supplier', 'owner_sifra' => 'D1']);
        $rows[] = array_replace($rows[0], ['sifra' => 'M5', 'owner_type' => 'worker', 'owner_sifra' => 'R4']);
        $this->writeRows('masine', $header, $rows);
        $this->change('gradilista', 1, ['name' => 'Primer gradilište A']);
        $this->runImport();

        foreach (Machine::all() as $machine) {
            $this->assertNotNull($machine->owner);
            $this->assertSame($machine->company_id, $machine->owner_type->value === 'company' ? $machine->owner->id : $machine->owner->company_id);
        }
        $this->assertSame(2, ConstructionSite::where('name', 'Primer gradilište A')->count());
        $this->assertSame(2, Worker::where('username', 'primer.sef')->firstOrFail()->constructionSites()->count());
    }

    public function test_repeat_skips_without_resetting_passwords_deleted_records_or_removed_links(): void
    {
        $this->runImport();
        $admin = Worker::where('username', 'primer.admin')->firstOrFail();
        $admin->update(['first_name' => 'Ručno izmenjeno', 'password' => 'NovaLozinka123!']);
        $password = $admin->fresh()->password;
        Worker::where('username', 'primer.radnik')->firstOrFail()->delete();
        Worker::where('username', 'primer.sef')->firstOrFail()->constructionSites()->detach();
        Supplier::firstOrFail()->delete();

        $this->assertSame('skipped', $this->runImport()['status']);
        $this->assertSame('Ručno izmenjeno', $admin->fresh()->first_name);
        $this->assertSame($password, $admin->fresh()->password);
        $this->assertDatabaseCount('workers', 5);
        $this->assertSame(4, Worker::count());
        $this->assertSame(0, Supplier::count());
        $this->assertDatabaseCount('suppliers', 1);
        $this->assertDatabaseCount('construction_site_worker', 0);
        $this->assertDatabaseCount('initial_data_imports', 1);
    }

    public function test_modified_completed_bundle_is_rejected_without_partial_updates(): void
    {
        $this->runImport();
        $this->change('gradilista', 0, ['name' => 'Novi naziv']);
        try {
            $this->runImport();
            $this->fail('Changed bundle must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('key', $exception->errors());
        }
        $this->assertDatabaseMissing('construction_sites', ['name' => 'Novi naziv']);
        $this->assertDatabaseCount('companies', 1);
    }

    public function test_same_bundle_cannot_be_imported_under_another_key(): void
    {
        $this->runImport();
        $this->expectException(ValidationException::class);
        $this->runImport(key: 'paki');
    }

    public function test_separate_clients_import_into_their_own_companies(): void
    {
        $first = $this->runImport();
        [$header, $rows] = $this->rows('radnici');
        foreach ($rows as &$row) {
            $row['username'] = 'paki.'.$row['username'];
        }
        unset($row);
        $this->writeRows('radnici', $header, $rows);
        $this->change('firma', 0, ['name' => 'Druga firma']);
        $this->change('masine', 1, ['license_plate' => 'TEST-002']);
        $second = $this->runImport(key: 'paki');
        $this->assertNotSame($first['company_id'], $second['company_id']);
        $this->assertDatabaseCount('companies', 2);
        $this->assertDatabaseCount('workers', 10);
        $this->assertSame(3, Machine::where('company_id', $second['company_id'])->count());
    }

    public function test_database_conflicts_include_soft_deleted_workers(): void
    {
        $this->runImport();
        Worker::where('username', 'primer.admin')->firstOrFail()->delete();
        $this->change('firma', 0, ['name' => 'Druga firma']);
        try {
            $this->runImport(dryRun: true, key: 'paki');
            $this->fail('Existing usernames/plates must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('radnici.csv, zapis 2 / username', $exception->errors());
            $this->assertArrayHasKey('masine.csv, zapis 3 / license_plate', $exception->errors());
        }
        $this->assertDatabaseCount('companies', 1);
    }

    public function test_header_only_optional_files_and_quoted_multiline_cells_work(): void
    {
        foreach (['gradilista', 'dobavljaci', 'podizvodjaci', 'masine', 'sefovi_gradilista'] as $file) {
            [$header] = $this->rows($file);
            $this->writeRows($file, $header, []);
        }
        $name = "Firma; \"Navodnici\"\nDrugi red";
        $this->change('firma', 0, ['name' => $name]);
        $this->runImport();
        $this->assertSame($name, Company::firstOrFail()->name);
        $this->assertDatabaseCount('machines', 0);
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_values_fail_before_any_write(string $file, int $index, array $changes): void
    {
        $this->change($file, $index, $changes);
        try {
            $this->runImport();
            $this->fail('Invalid CSV must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($file.'.csv', implode(' ', array_keys($exception->errors())));
        }
        $this->assertEmptyImport();
    }

    public static function invalidValues(): array
    {
        return [
            'missing name' => ['firma', 0, ['name' => '-']],
            'no active admin' => ['radnici', 0, ['is_active' => '0']],
            'missing password' => ['radnici', 0, ['password' => '-']],
            'short password' => ['radnici', 0, ['password' => 'short']],
            'unknown role' => ['radnici', 2, ['role' => 'boss']],
            'negative money' => ['radnici', 2, ['hourly_rate' => '-1']],
            'grouped money' => ['radnici', 2, ['hourly_rate' => '1.000,00']],
            'too much money precision' => ['radnici', 2, ['hourly_rate' => '100.001']],
            'money overflow' => ['radnici', 2, ['hourly_rate' => '100000000']],
            'invalid boolean' => ['dobavljaci', 0, ['is_active' => 'yes']],
            'invalid email' => ['dobavljaci', 0, ['email' => 'invalid']],
            'duplicate code' => ['radnici', 2, ['sifra' => 'R1']],
            'duplicate username case insensitive' => ['radnici', 2, ['username' => 'PRIMER.ADMIN']],
            'unknown owner' => ['masine', 0, ['owner_sifra' => 'NONE']],
            'wrong owner namespace' => ['masine', 0, ['owner_sifra' => 'R1']],
            'unknown type' => ['masine', 0, ['type' => 'tractor']],
            'missing excavator hours' => ['masine', 0, ['initial_work_hours' => '-']],
            'irrelevant truck hours' => ['masine', 1, ['initial_work_hours' => '20']],
            'mileage overflow' => ['masine', 1, ['initial_mileage' => '10000000000']],
            'missing site status' => ['gradilista', 0, ['status' => '-']],
            'invalid latitude' => ['gradilista', 0, ['latitude' => '91']],
            'too long site name for DB' => ['gradilista', 0, ['name' => str_repeat('x', 151)]],
            'unknown site reference' => ['sefovi_gradilista', 0, ['gradiliste_sifra' => 'NONE']],
            'non manager reference' => ['sefovi_gradilista', 0, ['sef_sifra' => 'R3']],
            'duplicate manager assignment' => ['sefovi_gradilista', 1, ['gradiliste_sifra' => 'G1']],
        ];
    }

    public function test_missing_file_bad_header_bad_column_count_and_encoding_are_reported(): void
    {
        unlink($this->directory.'/firma.csv');
        file_put_contents($this->directory.'/radnici.csv', "wrong;header\n");
        file_put_contents($this->directory.'/dobavljaci.csv', "incomplete\n", FILE_APPEND);
        file_put_contents($this->directory.'/gradilista.csv', "G3;Invalid \xFF;active;-;-;-;-\n", FILE_APPEND);
        try {
            $this->runImport();
            $this->fail('Malformed files must be rejected.');
        } catch (ValidationException $exception) {
            $errors = implode(' ', array_keys($exception->errors()));
            foreach (['firma.csv', 'radnici.csv', 'dobavljaci.csv', 'gradilista.csv'] as $file) {
                $this->assertStringContainsString($file, $errors);
            }
        }
        $this->assertEmptyImport();
    }

    public function test_late_failure_rolls_back_every_entity_and_hides_sensitive_exception_text(): void
    {
        Truck::creating(function () {
            throw new RuntimeException('Sensitive password or SQL must never be printed.');
        });
        try {
            $exit = Artisan::call('initial-data:import', ['directory' => $this->directory, '--key' => 'tosa']);
            $this->assertSame(1, $exit);
            $this->assertStringContainsString('Ništa iz ovog uvoza nije sačuvano.', Artisan::output());
            $this->assertStringNotContainsString('Sensitive password', Artisan::output());
        } finally {
            Truck::flushEventListeners();
        }
        $this->assertEmptyImport();
    }
}
