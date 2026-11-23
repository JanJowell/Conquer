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
            $table->timestamp('auto_archive_exempt_at')->nullable()->index();
        });
        Schema::create('event_auto_archive_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('events')->restrictOnDelete();
            $table->string('event_title');
            // Explicit values only. Legacy MariaDB TIMESTAMP defaults can
            // introduce zero dates or ON UPDATE behavior under strict mode.
            $table->dateTime('event_ended_at');
            $table->dateTime('cutoff_at');
            $table->dateTime('archived_at');
            $table->timestamps();
        });

        // Preserve earlier Admin restorations with surviving successful audit
        // evidence, including a restore performed before this migration exists.
        DB::table('admin_activity_logs')->where('action', 'like', 'event.cleanup.restore event=%')
            ->lazyByIdDesc(100)->each(function ($log) {
                if (preg_match('/\Aevent\.cleanup\.restore event=([1-9][0-9]*)\z/', $log->action, $match) !== 1) {
                    return;
                }
                DB::table('events')->where('id', $match[1])->whereNull('revision_of_id')
                    ->whereNull('archived_at')->whereNull('auto_archive_exempt_at')
                    ->update(['auto_archive_exempt_at' => $log->created_at ?? now()]);
            });
    }

    public function down(): void
    {
        if (DB::table('events')->whereNotNull('auto_archive_exempt_at')->exists()
            || DB::table('event_auto_archive_logs')->exists()) {
            throw new RuntimeException('Automatic archive history or restore exemptions exist. Preserve and review them before removing this migration.');
        }
        Schema::drop('event_auto_archive_logs');
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['auto_archive_exempt_at']);
            $table->dropColumn('auto_archive_exempt_at');
        });
    }
};
