<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('participation_mode', 20)->default('individual')->after('qualification_notes');
            $table->unsignedSmallInteger('group_min_members')->nullable()->after('participation_mode');
            $table->unsignedSmallInteger('group_max_members')->nullable()->after('group_min_members');
        });

        Schema::create('registration_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leader_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->char('join_code_hash', 64)->unique();
            $table->string('status', 20)->default('forming');
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'status']);
            $table->index(['leader_user_id', 'category_id']);
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('registration_group_id')
                ->nullable()
                ->after('category_id')
                ->constrained('registration_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registration_group_id');
        });

        Schema::dropIfExists('registration_groups');

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['participation_mode', 'group_min_members', 'group_max_members']);
        });
    }
};
