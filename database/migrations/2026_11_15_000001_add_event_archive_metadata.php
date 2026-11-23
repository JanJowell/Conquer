<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Management-list metadata only: no lifecycle changes or backfill.
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('events')->whereNotNull('archived_at')->orWhereNotNull('archived_by_user_id')->exists()) {
            throw new RuntimeException('Archived events exist. Restore them through the reviewed cleanup process before removing archive metadata.');
        }

        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('archived_by_user_id');
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
