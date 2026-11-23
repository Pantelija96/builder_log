<?php

namespace App\Services\InitialImport;

use App\Enums\ConstructionSiteStatus;
use App\Enums\MachineStatus;
use App\Enums\MachineType;
use App\Enums\OwnerType;
use App\Enums\WorkerRole;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InitialDataCsv
{
    public const HEADERS = [
        'firma' => 'sifra;name;pib;email;phone;address',
        'radnici' => 'sifra;first_name;last_name;role;username;password;phone;email;hourly_rate;is_active',
        'gradilista' => 'sifra;name;status;description;address;latitude;longitude',
        'dobavljaci' => 'sifra;name;phone;email;pib;address;contact_first_name;contact_last_name;contact_email;contact_phone;is_active',
        'podizvodjaci' => 'sifra;name;phone;email;description;pib;address;contact_first_name;contact_last_name;contact_email;contact_phone;is_active',
        'masine' => 'sifra;name;type;owner_type;owner_sifra;status;license_plate;initial_work_hours;initial_mileage;image_path',
        'sefovi_gradilista' => 'gradiliste_sifra;sef_sifra',
    ];

    public const OWNER_FILES = [
        'company' => 'firma',
        'worker' => 'radnici',
        'supplier' => 'dobavljaci',
        'subcontractor' => 'podizvodjaci',
    ];

    public function read(string $directory): array
    {
        if (! is_dir($directory) || ! is_readable($directory)) {
            throw ValidationException::withMessages(['directory' => 'CSV direktorijum ne postoji ili nije čitljiv.']);
        }
        $data = [];
        $errors = [];

        foreach (self::HEADERS as $file => $header) {
            $path = rtrim($directory, '/').'/'.$file.'.csv';
            $data[$file] = [];
            if (! file_exists($path)) {
                continue;
            }
            if (! is_file($path) || ! is_readable($path)) {
                $errors[$file.'.csv'][] = 'CSV putanja nije čitljiv fajl.';

                continue;
            }

            $handle = fopen($path, 'rb');
            try {
                // Detect the delimiter from the known header, not from decimal values in rows.
                foreach ([',', ';'] as $delimiter) {
                    rewind($handle);
                    $columns = fgetcsv($handle, null, $delimiter, '"', '');
                    if (is_array($columns)) {
                        $columns[0] = preg_replace('/^\xEF\xBB\xBF/', '', $columns[0] ?? '');
                    }
                    if ($columns === explode(';', $header)) {
                        break;
                    }
                }
                if ($columns !== explode(';', $header)) {
                    $errors[$file.'.csv'][] = 'Zaglavlje mora sadržati kolone: '.str_replace(';', ',', $header).' (separator , ili ;).';

                    continue;
                }

                $data[$file] = [];
                $record = 1;
                while (($values = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
                    $record++;
                    if ($values === [null]) {
                        continue;
                    }
                    $location = $file.'.csv, zapis '.$record;
                    if (count($values) !== count($columns)) {
                        $errors[$location][] = 'Pogrešan broj kolona; koristite isti separator kao u zaglavlju.';

                        continue;
                    }
                    if (! mb_check_encoding(implode('', $values), 'UTF-8')) {
                        $errors[$location][] = 'Fajl mora biti u UTF-8 formatu.';

                        continue;
                    }
                    $row = array_combine($columns, $values);
                    foreach ($row as $field => $value) {
                        $text = trim($value ?? '');
                        $row[$field] = ($text === '' || $text === '-') ? null : ($field === 'password' ? $value : $text);
                    }
                    foreach (['hourly_rate', 'initial_work_hours', 'initial_mileage', 'latitude', 'longitude'] as $field) {
                        if (isset($row[$field])) {
                            $row[$field] = str_replace(',', '.', $row[$field]);
                        }
                    }
                    if (array_key_exists('is_active', $row)) {
                        $row['is_active'] ??= '1';
                    }
                    if ($file === 'masine') {
                        $row['status'] ??= 'active';
                    }
                    $data[$file][$record] = $row;
                }
            } finally {
                fclose($handle);
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $this->validate($data);

        return $data;
    }

    private function validate(array $data): void
    {
        $errors = [];
        $indexes = [];
        foreach ($data as $file => $rows) {
            $seen = [];
            foreach ($rows as $record => $row) {
                $location = $file.'.csv, zapis '.$record;
                $validator = Validator::make($row, $this->rules($file));
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors[$location.' / '.$field] = $messages;
                }
                // External codes are case-sensitive. Business identifiers also reject case variants.
                $uniqueFields = match ($file) {
                    'radnici' => ['sifra', 'username'],
                    'masine' => ['sifra', 'license_plate'],
                    'dobavljaci', 'podizvodjaci' => ['sifra', 'pib'],
                    'sefovi_gradilista' => [],
                    default => ['sifra'],
                };
                foreach ($uniqueFields as $field) {
                    if ($row[$field] === null) {
                        continue;
                    }
                    $value = $field === 'sifra' ? $row[$field] : mb_strtolower($row[$field]);
                    if (isset($seen[$field][$value])) {
                        $errors[$location.' / '.$field][] = 'Vrednost se ponavlja u ovom fajlu.';
                    }
                    $seen[$field][$value] = true;
                }
                if (isset($row['sifra'])) {
                    $indexes[$file][$row['sifra']] = $row;
                }
            }
        }

        if (count($data['firma']) > 1) {
            $errors['firma.csv'][] = 'Dozvoljen je najviše jedan red za firmu.';
        }

        foreach ($data['masine'] as $record => $row) {
            // With no company CSV, company-owned machines belong to the selected existing company.
            if ($row['owner_type'] === 'company' && ! $data['firma']) {
                continue;
            }
            $ownerFile = self::OWNER_FILES[$row['owner_type']] ?? null;
            if (! $ownerFile || ! isset($indexes[$ownerFile][$row['owner_sifra']])) {
                $errors['masine.csv, zapis '.$record.' / owner_sifra'][] = 'Šifra vlasnika ne postoji u odgovarajućem fajlu.';
            }
        }
        $pairs = [];
        foreach ($data['sefovi_gradilista'] as $record => $row) {
            $location = 'sefovi_gradilista.csv, zapis '.$record;
            if (! isset($indexes['gradilista'][$row['gradiliste_sifra']])) {
                $errors[$location.' / gradiliste_sifra'][] = 'Gradilište sa ovom šifrom ne postoji.';
            }
            if (($indexes['radnici'][$row['sef_sifra']]['role'] ?? null) !== 'site_manager') {
                $errors[$location.' / sef_sifra'][] = 'Šifra mora pripadati zaposlenom sa ulogom site_manager.';
            }
            $pair = json_encode(array_values($row));
            if (isset($pairs[$pair])) {
                $errors[$location][] = 'Dodela šefa gradilištu se ponavlja.';
            }
            $pairs[$pair] = true;
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function rules(string $file): array
    {
        $code = ['required', 'string', 'max:100'];
        $contact = [
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'pib' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
        $business = [
            'sifra' => $code,
            'name' => ['required', 'string', 'max:150'],
            ...$contact,
            'contact_first_name' => ['nullable', 'string', 'max:100'],
            'contact_last_name' => ['nullable', 'string', 'max:100'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['required', Rule::in(['0', '1'])],
        ];
        $counter = ['numeric', 'min:0', 'regex:/^\d+(?:\.\d{1,2})?$/'];

        return match ($file) {
            'firma' => [
                'sifra' => $code,
                'name' => ['required', 'string', 'max:250'],
                ...$contact,
            ],
            'radnici' => [
                'sifra' => $code,
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['nullable', 'string', 'max:100'],
                'role' => ['required', Rule::enum(WorkerRole::class)],
                'username' => ['nullable', 'required_if:role,admin,site_manager,operator,driver', 'string', 'max:100'],
                'password' => ['nullable', 'required_if:role,admin,site_manager,operator,driver', 'string'],
                'phone' => $contact['phone'],
                'email' => $contact['email'],
                'hourly_rate' => ['nullable', ...$counter, 'max:99999999.99'],
                'is_active' => $business['is_active'],
            ],
            'gradilista' => [
                'sifra' => $code,
                'name' => ['required', 'string', 'max:150'],
                'status' => ['required', Rule::enum(ConstructionSiteStatus::class)],
                'description' => ['nullable', 'string'],
                'address' => $contact['address'],
                'latitude' => ['nullable', 'numeric', 'between:-90,90', 'regex:/^[+-]?\d+(?:\.\d{1,7})?$/'],
                'longitude' => ['nullable', 'numeric', 'between:-180,180', 'regex:/^[+-]?\d+(?:\.\d{1,7})?$/'],
            ],
            'dobavljaci' => $business,
            'podizvodjaci' => [...$business, 'description' => ['nullable', 'string']],
            'masine' => [
                'sifra' => $code,
                'name' => ['required', 'string', 'max:150'],
                'type' => ['required', Rule::enum(MachineType::class)],
                'owner_type' => ['required', Rule::enum(OwnerType::class)],
                'owner_sifra' => ['nullable', 'required_unless:owner_type,company', 'string', 'max:100'],
                'status' => ['required', Rule::enum(MachineStatus::class)],
                'license_plate' => ['nullable', 'string', 'max:30'],
                'image_path' => ['nullable', 'string', 'max:255'],
                'initial_work_hours' => ['nullable', 'prohibited_if:type,truck', ...$counter, 'max:99999999.99'],
                'initial_mileage' => ['nullable', 'required_if:type,truck', 'prohibited_if:type,excavator', ...$counter, 'max:9999999999.99'],
            ],
            'sefovi_gradilista' => ['gradiliste_sifra' => $code, 'sef_sifra' => $code],
        };
    }
}
