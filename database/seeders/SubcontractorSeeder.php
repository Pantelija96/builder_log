<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Subcontractor;
use Illuminate\Database\Seeder;

class SubcontractorSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->firstOrFail();

        $subcontractors = [
            [
                'name' => 'Geo Gradnja',
                'description' => 'Earthworks contractor.',
                'pib' => '200000101',
                'address' => 'Beograd',
                'phone' => '+381113330001',
                'email' => 'office@geogradnja.rs',
                'contact_first_name' => 'Marko',
                'contact_last_name' => 'Stojanović',
                'contact_email' => 'marko@geogradnja.rs',
                'contact_phone' => '+381602000001',
            ],
            [
                'name' => 'Elektro Tim',
                'description' => 'Electrical installations.',
                'pib' => '200000102',
                'address' => 'Beograd',
                'phone' => '+381113330002',
                'email' => 'office@elektrotim.rs',
                'contact_first_name' => 'Nikola',
                'contact_last_name' => 'Milenković',
                'contact_email' => 'nikola@elektrotim.rs',
                'contact_phone' => '+381602000002',
            ],
            [
                'name' => 'Beton Plus',
                'description' => 'Concrete works.',
                'pib' => '200000103',
                'address' => 'Pančevo',
                'phone' => '+381113330003',
                'email' => 'office@betonplus.rs',
                'contact_first_name' => 'Petar',
                'contact_last_name' => 'Janković',
                'contact_email' => 'petar@betonplus.rs',
                'contact_phone' => '+381602000003',
            ],
            [
                'name' => 'Armatura Invest',
                'description' => 'Reinforcement works.',
                'pib' => '200000104',
                'address' => 'Beograd',
                'phone' => '+381113330004',
                'email' => 'office@armaturainvest.rs',
                'contact_first_name' => 'Stefan',
                'contact_last_name' => 'Petrović',
                'contact_email' => 'stefan@armaturainvest.rs',
                'contact_phone' => '+381602000004',
            ],
        ];

        foreach ($subcontractors as $subcontractor) {
            Subcontractor::create([
                'company_id' => $company->id,
                'is_active' => true,
                ...$subcontractor,
            ]);
        }
    }
}
