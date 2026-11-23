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
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('events')->whereNotNull('cancelled_at')->exists()) {
            throw new RuntimeException('Cancellation history must be preserved. Do not roll back a populated cancellation migration.');
        }
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['cancelled_at']);
            $table->dropConstrainedForeignId('cancelled_by_user_id');
            $table->dropColumn(['cancelled_at', 'cancellation_reason']);
        });
    }
};
