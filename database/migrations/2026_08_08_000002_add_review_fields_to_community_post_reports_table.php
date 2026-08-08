<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_post_reports', function (Blueprint $table) {
            $table->timestamp('reviewed_at')->nullable()->after('reason');
            $table->foreignId('reviewed_by')
                ->nullable()
                ->after('reviewed_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('community_posts')
            ->whereNull('deleted_at')
            ->whereNull('moderated_by')
            ->where('is_flagged', true)
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('community_post_reports')
                    ->whereColumn('community_post_reports.community_post_id', 'community_posts.id');
            })
            ->whereRaw('(select count(*) from community_post_reports inner join users on users.id = community_post_reports.user_id where community_post_reports.community_post_id = community_posts.id and users.email_verified_at is not null) < 3')
            ->update([
                'is_flagged' => false,
                'moderation_note' => 'Reported by verified users; awaiting moderator review.',
                'moderated_at' => null,
            ]);
    }

    public function down(): void
    {
        Schema::table('community_post_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn('reviewed_at');
        });
    }
};
