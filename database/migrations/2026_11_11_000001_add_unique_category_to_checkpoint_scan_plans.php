<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix forward: the original, already-applied migration stays unchanged.
        if (config('checkpoint_scanning.enabled', false)) {
            throw new RuntimeException('Disable checkpoint recording before correcting plan uniqueness.');
        }

        if (! Schema::hasTable('checkpoint_scan_plans') || ! Schema::hasTable('checkpoint_scans')) {
            throw new RuntimeException('Checkpoint history schema is incomplete; review it before correcting plan uniqueness.');
        }

        // Do not choose, merge or delete historical plans, even without duplicates.
        if (DB::table('checkpoint_scan_plans')->exists() || DB::table('checkpoint_scans')->exists()) {
            throw new RuntimeException('Checkpoint history exists; plan uniqueness requires a reviewed data-preservation plan.');
        }

        if (Schema::hasIndex('checkpoint_scan_plans', 'checkpoint_scan_plans_category_id_unique')) {
            throw new RuntimeException('The checkpoint plan index already exists; review migration state before proceeding.');
        }

        Schema::table('checkpoint_scan_plans', function (Blueprint $table) {
            $table->unique('category_id', 'checkpoint_scan_plans_category_id_unique');
        });
    }

    public function down(): void
    {
        // Do not reintroduce duplicate plans or disturb a foreign-key support index.
        throw new RuntimeException('Checkpoint plan uniqueness is a history invariant; rollback requires a reviewed fix-forward plan.');
    }
};
