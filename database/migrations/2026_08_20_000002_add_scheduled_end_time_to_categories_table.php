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
            $table->time('scheduled_end_time')->nullable()->after('scheduled_start_time');
        });

        DB::table('categories')
            ->whereNull('scheduled_end_time')
            ->orderBy('id')
            ->chunkById(200, function ($categories) {
                $eventEndTimes = DB::table('events')
                    ->whereIn('id', $categories->pluck('event_id')->filter()->unique())
                    ->pluck('end_time', 'id');

                foreach ($categories as $category) {
                    $endTime = $eventEndTimes[$category->event_id] ?? null;

                    if ($endTime) {
                        DB::table('categories')
                            ->where('id', $category->id)
                            ->update(['scheduled_end_time' => $endTime]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('scheduled_end_time');
        });
    }
};
