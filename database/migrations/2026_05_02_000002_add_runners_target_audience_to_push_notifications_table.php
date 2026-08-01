<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE push_notifications MODIFY target_audience ENUM('all', 'runners', 'participants', 'admins') NOT NULL");
    }

    public function down(): void
    {
        DB::table('push_notifications')
            ->where('target_audience', 'runners')
            ->update(['target_audience' => 'all']);

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE push_notifications MODIFY target_audience ENUM('all', 'participants', 'admins') NOT NULL");
    }
};
