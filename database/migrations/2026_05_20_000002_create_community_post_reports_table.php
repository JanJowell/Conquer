<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_post_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_post_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->timestamps();

            $table->unique(['user_id', 'community_post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_post_reports');
    }
};
