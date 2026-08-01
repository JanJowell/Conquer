<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('e_badges', function (Blueprint $table) {
            $table->string('auto_issue_rule')->default('manual')->after('criteria');
        });
    }

    public function down(): void
    {
        Schema::table('e_badges', function (Blueprint $table) {
            $table->dropColumn('auto_issue_rule');
        });
    }
};
