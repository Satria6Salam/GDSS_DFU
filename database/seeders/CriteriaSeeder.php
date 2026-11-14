<?php

namespace Database\Seeders;

use App\Models\Criterion;
use Database\Seeders\Concerns\LoadsDataset;
use Illuminate\Database\Seeder;

/** Kriteria C1–C6 dan rubrik skor 1–5 (Bab 3), dari criteria.json. */
class CriteriaSeeder extends Seeder
{
    use LoadsDataset;

    public function run(): void
    {
        $data = $this->dataset('criteria.json');

        foreach ($data['criteria'] as $criterion) {
            Criterion::updateOrCreate(
                ['code' => $criterion['code'], 'version' => $criterion['version']],
                [
                    'name' => $criterion['name'],
                    'definition' => $criterion['definition'],
                    'type' => $criterion['type'],
                    'rubric' => $criterion['rubric'],
                    'data_reference' => $criterion['data_reference'],
                    'source' => $criterion['source'],
                    'sort_order' => $criterion['sort_order'],
                ],
            );
        }
    }
}
