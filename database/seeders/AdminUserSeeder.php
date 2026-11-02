<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException(
                'AdminUserSeeder contains development-only accounts and cannot run in production.'
            );
        }

        User::firstOrCreate(
            ['email' => 'admin@eventmanager.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'email_verified_at' => now(),
                'phone' => '+1234567890',
                'address' => 'Admin Office',
                'emergency_contact' => 'Emergency Contact: +0987654321',
            ]
        );

        User::firstOrCreate(
            ['email' => 'manager@eventmanager.com'],
            [
                'name' => 'Event Manager',
                'password' => Hash::make('password'),
                'role' => 'event_manager',
                'email_verified_at' => now(),
                'phone' => '+1234567891',
                'address' => 'Event Manager Office',
                'emergency_contact' => 'Emergency Contact: +0987654322',
            ]
        );

        $this->command->info('Admin users created successfully!');
        $this->command->info('Admin: admin@eventmanager.com / password');
        $this->command->info('Event Manager: manager@eventmanager.com / password');
    }
}
