<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Urutan seeder Bab 9.5: akun → kriteria → sesi demo → (opsional) draf penilaian. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CriteriaSeeder::class,
            DemoSessionSeeder::class,
        ]);

        if (config('gdss.seed_demo_assessments')) {
            $this->call(DemoAssessmentSeeder::class);
        }
    }
}
