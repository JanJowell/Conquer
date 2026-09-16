<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE push_notifications MODIFY type ENUM('payment', 'reminder', 'announcement', 'emergency', 'community', 'achievement') NOT NULL");
        }
    }

    public function down(): void
    {
        DB::table('push_notifications')
            ->where('type', 'achievement')
            ->update(['type' => 'announcement']);

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE push_notifications MODIFY type ENUM('payment', 'reminder', 'announcement', 'emergency', 'community') NOT NULL");
        }
    }
};
