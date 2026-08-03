<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->firstOrFail();

        $suppliers = [
            [
                'name' => 'NIS',
                'pib' => '100000101',
                'email' => 'office@nis.rs',
                'phone' => '+381112222001',
                'address' => 'Beograd',
                'contact_first_name' => 'Marko',
                'contact_last_name' => 'Marković',
                'contact_email' => 'marko@nis.rs',
                'contact_phone' => '+381601000001',
            ],
            [
                'name' => 'Lafarge Srbija',
                'pib' => '100000102',
                'email' => 'office@lafarge.rs',
                'phone' => '+381112222002',
                'address' => 'Beočin',
                'contact_first_name' => 'Nikola',
                'contact_last_name' => 'Jovanović',
                'contact_email' => 'nikola@lafarge.rs',
                'contact_phone' => '+381601000002',
            ],
            [
                'name' => 'Knauf',
                'pib' => '100000103',
                'email' => 'office@knauf.rs',
                'phone' => '+381112222003',
                'address' => 'Zemun',
                'contact_first_name' => 'Petar',
                'contact_last_name' => 'Petrović',
                'contact_email' => 'petar@knauf.rs',
                'contact_phone' => '+381601000003',
            ],
            [
                'name' => 'Wienerberger',
                'pib' => '100000104',
                'email' => 'office@wienerberger.rs',
                'phone' => '+381112222004',
                'address' => 'Kanjiža',
                'contact_first_name' => 'Milan',
                'contact_last_name' => 'Ilić',
                'contact_email' => 'milan@wienerberger.rs',
                'contact_phone' => '+381601000004',
            ],
            [
                'name' => 'BeoBeton',
                'pib' => '100000105',
                'email' => 'office@beobeton.rs',
                'phone' => '+381112222005',
                'address' => 'Beograd',
                'contact_first_name' => 'Aleksandar',
                'contact_last_name' => 'Nikolić',
                'contact_email' => 'aleksandar@beobeton.rs',
                'contact_phone' => '+381601000005',
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create([
                'company_id' => $company->id,
                'is_active' => true,
                ...$supplier,
            ]);
        }
    }
}
