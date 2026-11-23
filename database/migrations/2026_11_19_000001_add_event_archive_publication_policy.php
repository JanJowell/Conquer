<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Do not reconstruct publication from created_at, updated_at or the
            // latest approval: those would enroll already-published older events.
            $table->dateTime('first_published_at')->nullable()->index();
            $table->dateTime('auto_archive_restored_at')->nullable()->index();
        });
        Schema::table('event_auto_archive_logs', function (Blueprint $table) {
            // MySQL needs a replacement FK-supporting index BEFORE the unique
            // index is removed. Keep every previous audit row and its FK intact.
            $table->index('event_id');
            $table->dropUnique(['event_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('events')->whereNotNull('first_published_at')->orWhereNotNull('auto_archive_restored_at')->exists()
            || DB::table('event_auto_archive_logs')->select('event_id')->groupBy('event_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Publication, restoration or repeated archive evidence exists. Preserve and review it before removing this migration.');
        }
        Schema::table('event_auto_archive_logs', function (Blueprint $table) {
            $table->unique('event_id');
            $table->dropIndex(['event_id']);
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['first_published_at']);
            $table->dropIndex(['auto_archive_restored_at']);
            $table->dropColumn(['first_published_at', 'auto_archive_restored_at']);
        });
    }
};
