<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // NULL explicitly identifies existing events; do not fabricate approvals.
            $table->string('review_status')->nullable()->default('draft')->index();
            $table->unsignedInteger('review_version')->default(0);
            $table->text('review_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revision_of_id')->nullable()->constrained('events')->restrictOnDelete();
            $table->string('submitted_setup_hash', 64)->nullable();
        });
        // Existing rows retain their prior visibility without invented reviewers.
        // Later application AND direct SQL inserts default to a private draft.
        DB::table('events')->update(['review_status' => null]);
        Schema::create('event_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('draft_event_id')->unique()->constrained('events')->restrictOnDelete();
            $table->string('base_setup_hash', 64);
            $table->json('category_map');
            $table->json('payment_method_map');
            $table->json('checkpoint_map');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
        Schema::create('event_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->unsignedInteger('review_version');
            $table->text('reason')->nullable();
            $table->string('setup_hash', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Reviewed events/revisions must not become public by removing their gate.
        throw new RuntimeException('Event approval rollback requires a reviewed recovery plan.');
    }
};
