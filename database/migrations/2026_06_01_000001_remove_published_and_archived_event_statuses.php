<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('events')->where('status', 'published')->update(['status' => 'upcoming']);
        DB::table('events')->where('status', 'archived')->update(['status' => 'completed']);
    }

    public function down(): void
    {
        DB::table('events')->where('status', 'upcoming')->update(['status' => 'published']);
    }
};
