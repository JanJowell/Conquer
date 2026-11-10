<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additive history only. No backfill, route changes, or real scan data.
        Schema::create('checkpoint_scan_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete()->unique();
            $table->char('fingerprint', 64);
            $table->timestamp('category_started_at');
            $table->json('snapshot');
            $table->timestamps();
        });
        Schema::create('checkpoint_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkpoint_scan_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('registration_id')->constrained()->restrictOnDelete();
            $table->foreignId('checkpoint_id')->constrained('category_course_checkpoints')->restrictOnDelete();
            // Staff deletion does not erase the visit; the name is snapshotted.
            $table->foreignId('scanned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scanned_by_name');
            $table->uuid('client_scan_id')->unique();
            $table->char('request_fingerprint', 64);
            $table->string('participant_name');
            $table->string('bib_number');
            $table->string('checkpoint_label', 120);
            $table->unsignedInteger('checkpoint_position');
            $table->timestamp('scanned_at');
            $table->unsignedBigInteger('elapsed_seconds');
            $table->boolean('after_cutoff')->default(false);
            $table->timestamps();
            $table->unique(['registration_id', 'checkpoint_id'], 'checkpoint_scans_registration_checkpoint_unique');
            $table->index(['event_id', 'category_id', 'scanned_at'], 'checkpoint_scans_category_time_index');
        });
    }

    public function down(): void
    {
        foreach (['checkpoint_scans', 'checkpoint_scan_plans'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Checkpoint history exists; rollback requires a reviewed retention/recovery plan.');
            }
        }
        Schema::dropIfExists('checkpoint_scans');
        Schema::dropIfExists('checkpoint_scan_plans');
    }
};
