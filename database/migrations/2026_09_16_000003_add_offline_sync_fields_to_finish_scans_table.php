<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finish_scans', function (Blueprint $table) {
            $table->uuid('client_scan_id')->nullable()->unique()->after('scanned_by_user_id');
            $table->boolean('captured_offline')->default(false)->after('client_scan_id');
        });
    }

    public function down(): void
    {
        Schema::table('finish_scans', function (Blueprint $table) {
            $table->dropUnique(['client_scan_id']);
            $table->dropColumn(['client_scan_id', 'captured_offline']);
        });
    }
};
