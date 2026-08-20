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
            $table->date('event_end_date')->nullable()->after('event_date');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->date('scheduled_start_date')->nullable()->after('status');
            $table->date('scheduled_end_date')->nullable()->after('scheduled_start_time');
        });

        DB::table('events')
            ->whereNull('event_end_date')
            ->update(['event_end_date' => DB::raw('event_date')]);

        DB::table('categories')
            ->orderBy('id')
            ->chunkById(200, function ($categories) {
                $eventDates = DB::table('events')
                    ->whereIn('id', $categories->pluck('event_id')->filter()->unique())
                    ->get(['id', 'event_date', 'event_end_date'])
                    ->keyBy('id');

                foreach ($categories as $category) {
                    $event = $eventDates->get($category->event_id);

                    if (! $event) {
                        continue;
                    }

                    DB::table('categories')
                        ->where('id', $category->id)
                        ->update([
                            'scheduled_start_date' => $category->scheduled_start_date ?? $event->event_date,
                            'scheduled_end_date' => $category->scheduled_end_date ?? $event->event_date,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['scheduled_start_date', 'scheduled_end_date']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('event_end_date');
        });
    }
};
