<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException(
                'DatabaseSeeder contains development-only data and cannot run in production.'
            );
        }

        $this->call(AdminUserSeeder::class);

        $eventManager = User::updateOrCreate(
            ['email' => 'manager@eventmanager.com'],
            [
                'name' => 'Event Manager',
                'password' => Hash::make('password'),
                'role' => 'event_manager',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@racetech.com'],
            [
                'name' => 'RACETECH Admin',
                'password' => Hash::make('racetechadmin'),
                'role' => 'super_admin',
            ]
        );

        $event = Event::updateOrCreate(
            ['slug' => Str::slug('RACETECH Fun Run 2026')],
            [
            'title' => 'RACETECH Fun Run 2026',
            'slug' => Str::slug('RACETECH Fun Run 2026'),
            'description' => 'Annual running and recreational event for all participants.',
            'venue' => 'City Oval',
            'event_date' => now()->addMonth(),
            'start_time' => '05:00:00',
            'end_time' => '10:00:00',
            'registration_deadline' => now()->addWeeks(3),
            'status' => 'upcoming',
            'organized_by' => 'RACETECH',
            'manager_id' => $eventManager->id,
        ]);

        Category::updateOrCreate([
            'event_id' => $event->id,
            'name' => '3K Run',
        ], [
            'distance_km' => 3,
            'description' => 'Beginner category',
            'slot_limit' => 100,
            'status' => 'open',
        ]);

        Category::updateOrCreate([
            'event_id' => $event->id,
            'name' => '5K Run',
        ], [
            'distance_km' => 5,
            'description' => 'Intermediate category',
            'slot_limit' => 100,
            'status' => 'open',
        ]);

        Announcement::updateOrCreate([
            'event_id' => $event->id,
            'title' => 'Registration is now open',
        ], [
            'content' => 'Participants may now register for the RACETECH Fun Run 2026.',
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
}
