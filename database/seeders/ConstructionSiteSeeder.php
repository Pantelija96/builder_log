<?php

namespace Database\Seeders;

use App\Enums\ConstructionSiteStatus;
use App\Models\Company;
use App\Models\ConstructionSite;
use Illuminate\Database\Seeder;

class ConstructionSiteSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->firstOrFail();

        ConstructionSite::create([
            'company_id' => $company->id,
            'name' => 'Blok A - Novi Beograd',
            'description' => 'Construction of a residential complex.',
            'address' => 'Novi Beograd, Beograd',
            'latitude' => 44.8125000,
            'longitude' => 20.4093000,
            'status' => ConstructionSiteStatus::ACTIVE,
        ]);

        ConstructionSite::create([
            'company_id' => $company->id,
            'name' => 'Poslovni centar Grocka',
            'description' => 'Business center construction.',
            'address' => 'Grocka, Beograd',
            'latitude' => 44.6719000,
            'longitude' => 20.7169000,
            'status' => ConstructionSiteStatus::ACTIVE,
        ]);

        ConstructionSite::create([
            'company_id' => $company->id,
            'name' => 'Stambeni objekat Zemun',
            'description' => 'Residential building project.',
            'address' => 'Zemun, Beograd',
            'latitude' => 44.8500000,
            'longitude' => 20.4010000,
            'status' => ConstructionSiteStatus::PAUSED,
        ]);
    }
}
