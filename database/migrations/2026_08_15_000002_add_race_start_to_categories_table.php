<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('status');
            $table->foreignId('started_by_user_id')->nullable()->after('started_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('started_by_user_id');
            $table->dropColumn('started_at');
        });
    }
};
