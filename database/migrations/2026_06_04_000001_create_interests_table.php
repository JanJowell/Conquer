<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interests', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $rows = collect(config('conquer.interests', []))
            ->unique(fn (string $name) => Str::lower(trim($name)))
            ->values()
            ->map(fn (string $name, int $index) => [
                'name' => trim($name),
                'slug' => Str::slug($name),
                'is_active' => true,
                'sort_order' => ($index + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows !== []) {
            DB::table('interests')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('interests');
    }
};
