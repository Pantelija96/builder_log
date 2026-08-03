<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            WorkerSeeder::class,
            ConstructionSiteSeeder::class,
            SupplierSeeder::class,
            SubcontractorSeeder::class,
            MachineSeeder::class,
            ExcavatorSeeder::class,
            TruckSeeder::class,
        ]);
    }
}
