<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('height_cm', 5, 2)->nullable()->after('medical_conditions');
            $table->decimal('weight_kg', 6, 2)->nullable()->after('height_cm');
            $table->date('bmi_measured_at')->nullable()->after('weight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['height_cm', 'weight_kg', 'bmi_measured_at']);
        });
    }
};
