<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_revisions', function (Blueprint $table) {
            $table->string('purpose')->default('revision');
            $table->json('cancellation_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('event_revisions')->where('purpose', 'reactivation')->exists()) {
            throw new RuntimeException('Reactivation and cancellation history must be preserved.');
        }
        Schema::table('event_revisions', fn (Blueprint $table) => $table->dropColumn(['purpose', 'cancellation_snapshot']));
    }
};
