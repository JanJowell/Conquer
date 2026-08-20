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
            $table->json('type_details')->nullable()->after('distance_km');
        });

        $keysByEventType = [
            'Triathlon' => ['swim_distance_m', 'bike_distance_km', 'run_distance_km'],
            'Duathlon' => ['first_run_distance_km', 'bike_distance_km', 'second_run_distance_km'],
        ];

        DB::table('categories')
            ->whereNull('type_details')
            ->orderBy('id')
            ->chunkById(100, function ($categories) use ($keysByEventType) {
                $events = DB::table('events')
                    ->whereIn('id', $categories->pluck('event_id')->unique()->all())
                    ->get(['id', 'interest_type', 'type_details'])
                    ->keyBy('id');

                foreach ($categories as $category) {
                    $event = $events->get($category->event_id);
                    $keys = $keysByEventType[$event?->interest_type] ?? [];
                    $legacyDetails = is_string($event?->type_details)
                        ? json_decode($event->type_details, true)
                        : (array) ($event?->type_details ?? []);
                    $categoryDetails = collect($keys)
                        ->mapWithKeys(fn (string $key) => [$key => $legacyDetails[$key] ?? null])
                        ->filter(fn ($value) => filled($value))
                        ->all();

                    if (count($categoryDetails) === count($keys) && $keys !== []) {
                        DB::table('categories')
                            ->where('id', $category->id)
                            ->update(['type_details' => json_encode($categoryDetails)]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('type_details');
        });
    }
};
