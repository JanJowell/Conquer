<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('race_results', function (Blueprint $table) {
            if (! Schema::hasIndex('race_results', ['registration_id'], 'unique')) {
                $table->unique('registration_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('race_results', function (Blueprint $table) {
            if (Schema::hasIndex('race_results', ['registration_id'], 'unique')) {
                $table->dropUnique(['registration_id']);
            }
        });
    }
};
