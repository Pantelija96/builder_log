<?php

namespace Database\Seeders;

use App\Enums\WorkerRole;
use App\Models\Company;
use App\Models\Worker;
use Illuminate\Database\Seeder;

class WorkerSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Admin
        |--------------------------------------------------------------------------
        */

        Worker::create([
            'company_id' => $company->id,
            'first_name' => 'Admin',
            'last_name' => 'BuilderLog',
            'phone' => '+381601111111',
            'role' => WorkerRole::ADMIN,
            'username' => 'admin',
            'password' => 'password',
            'email' => 'admin@builderlog.local',
            'is_active' => true,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Site Managers
        |--------------------------------------------------------------------------
        */

        Worker::create([
            'company_id' => $company->id,
            'first_name' => 'Marko',
            'last_name' => 'Petrović',
            'phone' => '+381601111112',
            'role' => WorkerRole::SITE_MANAGER,
            'username' => 'marko',
            'password' => 'password',
            'email' => 'marko@builderlog.local',
            'is_active' => true,
        ]);

        Worker::create([
            'company_id' => $company->id,
            'first_name' => 'Nikola',
            'last_name' => 'Jovanović',
            'phone' => '+381601111113',
            'role' => WorkerRole::SITE_MANAGER,
            'username' => 'nikola',
            'password' => 'password',
            'email' => 'nikola@builderlog.local',
            'is_active' => true,
        ]);

        /*
         |--------------------------------------------------------------------------
         | Operators
         |--------------------------------------------------------------------------
         */

        $operators = [
            [
                'first_name' => 'Milan',
                'last_name' => 'Ilić',
                'username' => 'operator01',
            ],
            [
                'first_name' => 'Dejan',
                'last_name' => 'Stanković',
                'username' => 'operator02',
            ],
            [
                'first_name' => 'Aleksandar',
                'last_name' => 'Nikolić',
                'username' => 'operator03',
            ],
            [
                'first_name' => 'Filip',
                'last_name' => 'Marković',
                'username' => 'operator04',
            ],
            [
                'first_name' => 'Uroš',
                'last_name' => 'Jovanović',
                'username' => 'operator05',
            ],
            [
                'first_name' => 'Vuk',
                'last_name' => 'Petrović',
                'username' => 'operator06',
            ],
            [
                'first_name' => 'Nikola',
                'last_name' => 'Savić',
                'username' => 'operator07',
            ],
            [
                'first_name' => 'Lazar',
                'last_name' => 'Đorđević',
                'username' => 'operator08',
            ],
            [
                'first_name' => 'Marko',
                'last_name' => 'Pavlović',
                'username' => 'operator09',
            ],
            [
                'first_name' => 'Ognjen',
                'last_name' => 'Milošević',
                'username' => 'operator10',
            ],
        ];

        foreach ($operators as $index => $operator) {

            Worker::create([
                'company_id' => $company->id,
                'first_name' => $operator['first_name'],
                'last_name' => $operator['last_name'],
                'phone' => sprintf(
                    '+38160222%04d',
                    $index + 1
                ),
                'role' => WorkerRole::OPERATOR,
                'username' => $operator['username'],
                'password' => 'password',
                'email' => null,
                'is_active' => true,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Drivers
        |--------------------------------------------------------------------------
        */

        $drivers = [
            [
                'first_name' => 'Vladan',
                'last_name' => 'Jovanović',
                'username' => 'driver01',
            ],
            [
                'first_name' => 'Mladen',
                'last_name' => 'Simić',
                'username' => 'driver02',
            ],
            [
                'first_name' => 'Nenad',
                'last_name' => 'Stojanović',
                'username' => 'driver03',
            ],
            [
                'first_name' => 'Miroslav',
                'last_name' => 'Nikolić',
                'username' => 'driver04',
            ],
            [
                'first_name' => 'Dejan',
                'last_name' => 'Petrović',
                'username' => 'driver05',
            ],
            [
                'first_name' => 'Saša',
                'last_name' => 'Marković',
                'username' => 'driver06',
            ],
            [
                'first_name' => 'Branko',
                'last_name' => 'Jovanović',
                'username' => 'driver07',
            ],
            [
                'first_name' => 'Vladimir',
                'last_name' => 'Savić',
                'username' => 'driver08',
            ],
            [
                'first_name' => 'Bojan',
                'last_name' => 'Milošević',
                'username' => 'driver09',
            ],
            [
                'first_name' => 'Zoran',
                'last_name' => 'Đorđević',
                'username' => 'driver10',
            ],
        ];

        foreach ($drivers as $index => $driver) {

            Worker::create([
                'company_id' => $company->id,
                'first_name' => $driver['first_name'],
                'last_name' => $driver['last_name'],
                'phone' => sprintf(
                    '+38160255%04d',
                    $index + 1
                ),
                'role' => WorkerRole::DRIVER,
                'username' => $driver['username'],
                'password' => 'password',
                'email' => null,
                'is_active' => true,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Workers
        |--------------------------------------------------------------------------
        */

        $workers = [
            ['Petar', 'Marković'],
            ['Stefan', 'Đorđević'],
            ['Nemanja', 'Pavlović'],
            ['Luka', 'Milošević'],
            ['Miloš', 'Savić'],
            ['Ivan', 'Ristić'],
            ['Dušan', 'Lukić'],
            ['Bojan', 'Kostić'],
            ['Vladimir', 'Mladenović'],
            ['Zoran', 'Todorović'],
            ['Dragan', 'Popović'],
            ['Goran', 'Živković'],
            ['Nenad', 'Janković'],
            ['Slobodan', 'Mitrović'],
            ['Branislav', 'Radović'],
        ];

        foreach ($workers as $index => [$firstName, $lastName]) {

            $number = $index + 1;

            Worker::create([
                'company_id' => $company->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => sprintf(
                    '+38160333%04d',
                    $number
                ),
                'role' => WorkerRole::WORKER,
                'username' => sprintf(
                    'worker%02d',
                    $number
                ),
                'password' => 'password',
                'email' => null,
                'is_active' => true,
            ]);
        }
    }
}
