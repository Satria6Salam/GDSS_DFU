<?php

/*
| Konfigurasi khusus GDSS DFU (dokumen spesifikasi Bab 9.5 dan 11.7).
*/

return [

    // Versi rubrik kriteria dan dataset yang dimuat seeder.
    'criteria_version' => 'v1',
    'dataset_version' => 'dataset_v1',

    // Isi draf penilaian tiga DM dari dataset untuk demo cepat (DemoAssessmentSeeder).
    'seed_demo_assessments' => (bool) env('SEED_DEMO_ASSESSMENTS', false),

    // Password akun demo; diisi di .env lokal dan tidak pernah di-commit (D16).
    'demo_passwords' => [
        'dokter' => env('DEMO_PASSWORD_DOKTER'),
        'perawat' => env('DEMO_PASSWORD_PERAWAT'),
        'apoteker' => env('DEMO_PASSWORD_APOTEKER'),
    ],

    // Batas CR (D06) dan tabel RI ditetapkan di App\Services\AhpService agar hanya ada satu sumber.
];
