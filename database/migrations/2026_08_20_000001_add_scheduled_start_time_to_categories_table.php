<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->time('scheduled_start_time')->nullable()->after('status');
        });

        DB::table('categories')
            ->whereNull('scheduled_start_time')
            ->orderBy('id')
            ->chunkById(200, function ($categories) {
                $eventStartTimes = DB::table('events')
                    ->whereIn('id', $categories->pluck('event_id')->unique())
                    ->pluck('start_time', 'id');

                foreach ($categories as $category) {
                    $startTime = $eventStartTimes[$category->event_id] ?? null;

                    if ($startTime !== null) {
                        DB::table('categories')
                            ->where('id', $category->id)
                            ->update(['scheduled_start_time' => $startTime]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('scheduled_start_time');
        });
    }
};
