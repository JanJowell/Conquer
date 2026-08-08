<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $table) {
            $table->text('content')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('community_posts')
            ->whereNull('content')
            ->update(['content' => '']);

        Schema::table('community_posts', function (Blueprint $table) {
            $table->text('content')->nullable(false)->change();
        });
    }
};
