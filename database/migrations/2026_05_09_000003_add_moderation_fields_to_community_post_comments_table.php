<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_post_comments', function (Blueprint $table) {
            $table->boolean('is_flagged')->default(false)->after('content');
            $table->text('moderation_note')->nullable()->after('is_flagged');
            $table->foreignId('moderated_by')->nullable()->after('moderation_note')->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable()->after('moderated_by');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('community_post_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropColumn(['is_flagged', 'moderation_note', 'moderated_at', 'deleted_at']);
        });
    }
};
