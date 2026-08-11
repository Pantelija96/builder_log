<?php

namespace Database\Seeders;

use App\Models\ConstructionSite;
use App\Models\Worker;
use Illuminate\Database\Seeder;

class ConstructionSiteManagerSeeder extends Seeder
{
    public function run(): void
    {
        $marko = Worker::query()
            ->where('username', 'marko')
            ->firstOrFail();

        $nikola = Worker::query()
            ->where('username', 'nikola')
            ->firstOrFail();

        $site1 = ConstructionSite::query()->findOrFail(1);
        $site2 = ConstructionSite::query()->findOrFail(2);
        $site3 = ConstructionSite::query()->findOrFail(3);

        /*
        |--------------------------------------------------------------------------
        | Marko
        |--------------------------------------------------------------------------
        */

        $marko->constructionSites()->syncWithoutDetaching([
            $site1->id,
            $site3->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Nikola
        |--------------------------------------------------------------------------
        */

        $nikola->constructionSites()->syncWithoutDetaching([
            $site2->id,
            $site3->id,
        ]);
    }
}
