<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->boolean('is_auto_generated')->default(false)->after('is_published');
            $table->index(['event_id', 'is_auto_generated']);
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'is_auto_generated']);
            $table->dropColumn('is_auto_generated');
        });
    }
};
