<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('requires_medical_certificate')->default(false)->after('qualification_notes');
        });

        // Preserve the previous distance-based behavior for existing categories.
        DB::table('categories')
            ->where('distance_km', '>=', 50)
            ->update(['requires_medical_certificate' => true]);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('requires_medical_certificate');
        });
    }
};
