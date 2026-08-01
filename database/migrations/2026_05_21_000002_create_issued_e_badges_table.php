<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issued_e_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('e_badge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['e_badge_id', 'registration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issued_e_badges');
    }
};
