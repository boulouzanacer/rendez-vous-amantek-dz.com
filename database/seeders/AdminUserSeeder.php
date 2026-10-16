<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Crée le compte administrateur principal à partir des variables d'environnement.
     */
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@amantek-dz.com');

        User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => (string) env('ADMIN_NAME', 'Administrateur'),
                'password' => Hash::make((string) env('ADMIN_PASSWORD', 'password')),
                'role' => UserRole::Admin,
                'phone' => env('COMPANY_PHONE'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
