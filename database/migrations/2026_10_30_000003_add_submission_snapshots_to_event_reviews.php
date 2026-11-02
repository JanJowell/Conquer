<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_reviews', function (Blueprint $table) {
            // Older review records deliberately remain NULL; never reconstruct from an editable draft.
            $table->json('submission_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Submission history must be preserved. Use an explicitly reviewed recovery plan.');
    }
};
