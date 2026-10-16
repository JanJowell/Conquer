<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('health_profile_updated_at')->nullable()->after('bmi_measured_at');
        });

        Schema::create('participant_health_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_update_id');
            $table->timestamp('client_updated_at')->nullable();
            $table->timestamp('base_version')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'client_update_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_health_updates');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('health_profile_updated_at');
        });
    }
};
