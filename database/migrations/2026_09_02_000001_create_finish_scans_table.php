<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finish_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('bib_number');
            $table->timestamp('scanned_at');
            $table->unsignedBigInteger('elapsed_seconds');
            $table->string('elapsed_time');
            $table->foreignId('scanned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('provisional');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'category_id', 'scanned_at']);
            $table->index(['scanned_by_user_id', 'scanned_at']);
            $table->index(['status', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finish_scans');
    }
};
