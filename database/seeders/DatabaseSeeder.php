<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $roles = Role::pluck('id', 'name');

        $users = [
            'admin@importachina.com' => ['name' => 'Administrador', 'role' => 'Administrador'],
            'vendedor@importachina.com' => ['name' => 'Vendedor Test', 'role' => 'Vendedor'],
            'cliente@importachina.com' => ['name' => 'Cliente Test', 'role' => 'Cliente'],
        ];

        foreach ($users as $email => $data) {
            User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $data['name'],
                    'role_id' => $roles[$data['role']] ?? null,
                    'password' => bcrypt('password'),
                ]
            );
        }
    }
}
