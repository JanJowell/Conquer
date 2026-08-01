<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
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
                'name' => 'Super Admin',
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

        User::firstOrCreate(
            ['email' => 'executive@eventmanager.com'],
            [
                'name' => 'Executive User',
                'password' => Hash::make('password'),
                'role' => 'executive',
                'email_verified_at' => now(),
                'phone' => '+1234567892',
                'address' => 'Executive Office',
                'emergency_contact' => 'Emergency Contact: +0987654323',
            ]
        );

        User::firstOrCreate(
            ['email' => 'moderator@eventmanager.com'],
            [
                'name' => 'Content Moderator',
                'password' => Hash::make('password'),
                'role' => 'content_moderator',
                'email_verified_at' => now(),
                'phone' => '+1234567893',
                'address' => 'Content Desk',
                'emergency_contact' => 'Emergency Contact: +0987654324',
            ]
        );

        $this->command->info('Admin users created successfully!');
        $this->command->info('Super Admin: admin@eventmanager.com / password');
        $this->command->info('Event Manager: manager@eventmanager.com / password');
        $this->command->info('Executive: executive@eventmanager.com / password');
        $this->command->info('Content Moderator: moderator@eventmanager.com / password');
    }
}
