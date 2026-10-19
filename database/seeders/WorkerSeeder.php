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
        | Admins
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
            'is_available' => true,
            'hourly_rate' => null,
        ]);

        Worker::create([
            'company_id' => $company->id,
            'first_name' => 'Admin2',
            'last_name' => 'BuilderLog2',
            'phone' => '+38160112222',
            'role' => WorkerRole::ADMIN,
            'username' => 'admin2',
            'password' => 'password',
            'email' => 'admin2@builderlog.local',
            'is_active' => true,
            'is_available' => true,
            'hourly_rate' => null,
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
            'is_available' => true,
            'hourly_rate' => null,
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
            'is_available' => true,
            'hourly_rate' => null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Operators
        |--------------------------------------------------------------------------
        */

        $operators = [
            ['Milan', 'Ilić', 'operator01', 900],
            ['Dejan', 'Stanković', 'operator02', 900],
            ['Aleksandar', 'Nikolić', 'operator03', 950],
            ['Filip', 'Marković', 'operator04', 850],
            ['Uroš', 'Jovanović', 'operator05', 900],
            ['Vuk', 'Petrović', 'operator06', 1000],
            ['Nikola', 'Savić', 'operator07', 850],
            ['Lazar', 'Đorđević', 'operator08', 950],
            ['Marko', 'Pavlović', 'operator09', 900],
            ['Ognjen', 'Milošević', 'operator10', 1000],
        ];

        foreach (
            $operators as $index => [
            $firstName,
            $lastName,
            $username,
            $hourlyRate
        ]
        ) {
            Worker::create([
                'company_id' => $company->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => sprintf(
                    '+38160222%04d',
                    $index + 1
                ),
                'role' => WorkerRole::OPERATOR,
                'username' => $username,
                'password' => 'password',
                'email' => null,
                'is_active' => true,
                'is_available' => true,
                'hourly_rate' => $hourlyRate,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Drivers
        |--------------------------------------------------------------------------
        */

        $drivers = [
            ['Vladan', 'Jovanović', 'driver01', 750],
            ['Mladen', 'Simić', 'driver02', 750],
            ['Nenad', 'Stojanović', 'driver03', 800],
            ['Miroslav', 'Nikolić', 'driver04', 750],
            ['Dejan', 'Petrović', 'driver05', 800],
            ['Saša', 'Marković', 'driver06', 850],
            ['Branko', 'Jovanović', 'driver07', 750],
            ['Vladimir', 'Savić', 'driver08', 800],
            ['Bojan', 'Milošević', 'driver09', 850],
            ['Zoran', 'Đorđević', 'driver10', 800],
        ];

        foreach (
            $drivers as $index => [
            $firstName,
            $lastName,
            $username,
            $hourlyRate
        ]
        ) {
            Worker::create([
                'company_id' => $company->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => sprintf(
                    '+38160255%04d',
                    $index + 1
                ),
                'role' => WorkerRole::DRIVER,
                'username' => $username,
                'password' => 'password',
                'email' => null,
                'is_active' => true,
                'is_available' => true,
                'hourly_rate' => $hourlyRate,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Workers
        |--------------------------------------------------------------------------
        */

        $workers = [
            ['Petar', 'Marković', 600],
            ['Stefan', 'Đorđević', 650],
            ['Nemanja', 'Pavlović', 600],
            ['Luka', 'Milošević', 700],
            ['Miloš', 'Savić', 650],
            ['Ivan', 'Ristić', 600],
            ['Dušan', 'Lukić', 700],
            ['Bojan', 'Kostić', 650],
            ['Vladimir', 'Mladenović', 600],
            ['Zoran', 'Todorović', 750],
            ['Dragan', 'Popović', 650],
            ['Goran', 'Živković', 700],
            ['Nenad', 'Janković', 600],
            ['Slobodan', 'Mitrović', 650],
            ['Branislav', 'Radović', 700],
        ];

        foreach (
            $workers as $index => [
            $firstName,
            $lastName,
            $hourlyRate
        ]
        ) {
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
                'is_available' => true,
                'hourly_rate' => $hourlyRate,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Development / Testing Accounts
        |--------------------------------------------------------------------------
        |
        | Password for all accounts: password
        |
        */

        $testUsers = [
            [
                'first_name' => 'Pantelija',
                'last_name' => 'Stosic',
                'nickname' => 'panta',
                'phone_prefix' => '701',
            ],
            [
                'first_name' => 'Strahinja',
                'last_name' => 'Stanojevic',
                'nickname' => 'stana',
                'phone_prefix' => '702',
            ],
            [
                'first_name' => 'Dusan',
                'last_name' => 'Garabinovic',
                'nickname' => 'dule',
                'phone_prefix' => '703',
            ],
        ];

        $testRoles = [
            [
                'role' => WorkerRole::ADMIN,
                'suffix' => '',
                'phone_suffix' => '01',
                'hourly_rate' => null,
            ],
            [
                'role' => WorkerRole::SITE_MANAGER,
                'suffix' => '-mng',
                'phone_suffix' => '02',
                'hourly_rate' => null,
            ],
            [
                'role' => WorkerRole::OPERATOR,
                'suffix' => '-op',
                'phone_suffix' => '03',
                'hourly_rate' => 900,
            ],
            [
                'role' => WorkerRole::DRIVER,
                'suffix' => '-dr',
                'phone_suffix' => '04',
                'hourly_rate' => 800,
            ],
        ];

        foreach ($testUsers as $testUser) {
            foreach ($testRoles as $testRole) {
                $username =
                    $testUser['nickname']
                    . $testRole['suffix'];

                Worker::create([
                    'company_id' => $company->id,
                    'first_name' => $testUser['first_name'],
                    'last_name' => $testUser['last_name'],
                    'phone' => sprintf(
                        '+38160%s%s',
                        $testUser['phone_prefix'],
                        $testRole['phone_suffix'],
                    ),
                    'role' => $testRole['role'],
                    'username' => $username,
                    'password' => 'password',
                    'email' => $username . '@builderlog.local',
                    'is_active' => true,
                    'is_available' => true,
                    'hourly_rate' => $testRole['hourly_rate'],
                ]);
            }
        }
    }
}
