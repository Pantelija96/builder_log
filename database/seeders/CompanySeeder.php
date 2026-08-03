<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::create([
            'name' => 'BuilderLog Demo Construction',
            'pib' => '100000001',
            'email' => 'office@builderlog.local',
            'phone' => '+381601000001',
            'address' => 'Belgrade, Serbia',
        ]);
    }
}
