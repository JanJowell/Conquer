<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('email');
            $table->json('interests')->nullable()->after('medical_conditions');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('interest_type')->nullable()->after('organized_by');
        });

        Schema::table('community_posts', function (Blueprint $table) {
            $table->string('title')->nullable()->after('event_id');
            $table->string('image_path')->nullable()->after('content');
            $table->string('video_path')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            $table->dropColumn(['title', 'image_path', 'video_path']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('interest_type');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'interests']);
        });
    }
};
