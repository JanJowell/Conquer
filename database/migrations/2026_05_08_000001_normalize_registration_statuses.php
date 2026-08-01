<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('registrations')
            ->where('status', 'registered')
            ->update(['status' => 'pending']);
    }

    public function down(): void
    {
        DB::table('registrations')
            ->where('status', 'pending')
            ->update(['status' => 'registered']);
    }
};
