<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name'     => 'Admin Principal',
                'email'    => 'admin@servicehub.com',
                'password' => Hash::make('password123'),
                'role'     => 'admin',
            ],
           /*  [
                'name'     => 'María González',
                'email'    => 'cliente@servicehub.com',
                'password' => Hash::make('password123'),
                'role'     => 'client',
            ],
            [
                'name'     => 'Carlos Mendoza',
                'email'    => 'profesional@servicehub.com',
                'password' => Hash::make('password123'),
                'role'     => 'professional',
            ], */
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }

        $this->command->info('✅ Usuarios creados correctamente');
    }
}