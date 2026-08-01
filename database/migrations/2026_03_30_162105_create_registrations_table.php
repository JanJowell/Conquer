<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('bib_number')->nullable();
            $table->string('shirt_size')->nullable();
            $table->text('medical_conditions')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'bib_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
