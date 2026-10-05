<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $roles = [
            ['name' => 'super_admin', 'label' => 'Super Admin',       'description' => 'Full access, including user and system management.'],
            ['name' => 'admin',       'label' => 'Admin / Pengelola', 'description' => 'Manages master data, households, transactions, and reports.'],
            ['name' => 'officer',     'label' => 'Petugas',           'description' => 'Field officer: household surveys and deposit/sale input.'],
            ['name' => 'viewer',      'label' => 'Viewer / Pimpinan', 'description' => 'Read-only access to dashboard and reports.'],
        ];

        $rows = array_map(fn ($r) => $r + ['created_at' => $now, 'updated_at' => $now], $roles);

        // Safe to run repeatedly: matches on the unique `name` column.
        DB::table('roles')->upsert($rows, ['name'], ['label', 'description', 'updated_at']);
    }
}
