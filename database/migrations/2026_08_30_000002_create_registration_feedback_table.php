<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('overall_rating');
            $table->unsignedTinyInteger('organization_rating')->nullable();
            $table->unsignedTinyInteger('route_rating')->nullable();
            $table->unsignedTinyInteger('safety_rating')->nullable();
            $table->unsignedTinyInteger('experience_rating')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['event_id', 'submitted_at']);
            $table->index(['category_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_feedback');
    }
};
