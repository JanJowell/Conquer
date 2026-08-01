<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE push_notifications MODIFY type ENUM('payment', 'reminder', 'announcement', 'emergency', 'community') NOT NULL");
        }

        Schema::table('push_notifications', function (Blueprint $table) {
            $table->foreignId('target_user_id')
                ->nullable()
                ->after('target_audience')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('push_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('target_user_id');
        });

        DB::table('push_notifications')
            ->where('type', 'community')
            ->delete();

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE push_notifications MODIFY type ENUM('payment', 'reminder', 'announcement', 'emergency') NOT NULL");
        }
    }
};
