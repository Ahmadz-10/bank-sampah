<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Demo accounts for LOCAL development only (password: "password").
     * Skipped in production: create the first Super Admin manually there.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('UserSeeder skipped in production.');

            return;
        }

        $now = now();
        $roleIds = DB::table('roles')->pluck('id', 'name');

        $users = [
            ['super_admin', 'Super Admin',     'superadmin@banksampah.test'],
            ['admin',       'Admin Pengelola', 'admin@banksampah.test'],
            ['officer',     'Petugas Lapangan', 'petugas@banksampah.test'],
            ['viewer',      'Pimpinan',        'pimpinan@banksampah.test'],
        ];

        foreach ($users as [$role, $name, $email]) {
            DB::table('users')->updateOrInsert(
                ['email' => $email],
                [
                    'role_id'           => $roleIds[$role],
                    'name'              => $name,
                    'password'          => Hash::make('password'),
                    'email_verified_at' => $now,
                    'is_active'         => true,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]
            );
        }
    }
}
