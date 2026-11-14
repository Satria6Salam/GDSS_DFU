<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/** Tiga akun decision maker (Bab 4.8, D16). Password dibaca dari .env, tidak ditulis di kode. */
class UserSeeder extends Seeder
{
    public const ACCOUNTS = [
        ['username' => 'dokter', 'name' => 'Dokter Demo', 'role' => Role::Dokter, 'is_leader' => true],
        ['username' => 'perawat', 'name' => 'Perawat Luka Demo', 'role' => Role::Perawat, 'is_leader' => false],
        ['username' => 'apoteker', 'name' => 'Apoteker Klinis Demo', 'role' => Role::Apoteker, 'is_leader' => false],
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as $account) {
            $password = config('gdss.demo_passwords.'.$account['username']);

            if (blank($password)) {
                throw new RuntimeException(
                    'Password akun demo belum diisi. Isi DEMO_PASSWORD_'.strtoupper($account['username']).' di file .env.'
                );
            }

            User::updateOrCreate(
                ['username' => $account['username']],
                [...$account, 'password' => $password],
            );
        }
    }
}
