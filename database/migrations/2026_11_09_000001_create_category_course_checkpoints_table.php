<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Derived identities only: do not rewrite routes, fabricate coordinates,
        // or backfill existing application data as a migration side effect.
        Schema::create('category_course_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->nullable();
            $table->string('label', 120);
            $table->decimal('latitude', 10, 6);
            $table->decimal('longitude', 11, 6);
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            // Retired rows have NULL positions and keep their IDs and details.
            $table->unique(['category_id', 'position']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('category_course_checkpoints') && DB::table('category_course_checkpoints')->exists()) {
            throw new RuntimeException('Checkpoint identities exist; rollback requires a reviewed recovery plan.');
        }
        Schema::dropIfExists('category_course_checkpoints');
    }
};
