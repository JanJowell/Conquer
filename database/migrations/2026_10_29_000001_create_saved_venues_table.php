<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('fingerprint', 64);
            $table->string('name', 100);
            $table->string('address', 150);
            $table->string('area_code', 10);
            $table->string('area_name', 150);
            $table->string('city_code', 10);
            $table->string('city_name', 150);
            $table->string('barangay_code', 10);
            $table->string('barangay_name', 150);
            $table->string('reference_version', 40);
            $table->string('label');
            $table->timestamps();
            $table->unique(['created_by', 'fingerprint']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venue_id');
        });
        Schema::dropIfExists('venues');
    }
};
