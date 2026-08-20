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
            $table->boolean('payment_setup_needs_review')->default(false)->after('type_details');
        });

        Schema::create('event_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['event_id', 'provider'], 'event_payment_methods_event_provider_unique');
        });

        DB::table('events')->orderBy('id')->chunkById(100, function ($events) {
            foreach ($events as $event) {
                $legacyMethods = DB::table('categories')
                    ->where('event_id', $event->id)
                    ->whereNotNull('payment_provider')
                    ->where('payment_provider', '!=', '')
                    ->orderBy('id')
                    ->get(['payment_provider', 'payment_account_name', 'payment_account_number', 'payment_instructions'])
                    ->map(function ($category) {
                        $provider = match (strtolower(trim((string) $category->payment_provider))) {
                            'gcash', 'g-cash' => 'GCash',
                            'maya', 'paymaya' => 'Maya',
                            'bank', 'bank transfer' => 'Bank',
                            default => trim((string) $category->payment_provider),
                        };

                        return [
                            'provider' => $provider,
                            'account_name' => $category->payment_account_name,
                            'account_number' => $category->payment_account_number,
                            'instructions' => $category->payment_instructions,
                        ];
                    })
                    ->filter(fn (array $method) => in_array($method['provider'], ['GCash', 'Maya', 'Bank'], true));

                $hasConflicts = $legacyMethods
                    ->groupBy('provider')
                    ->contains(fn ($methods) => $methods->unique(fn ($method) => json_encode($method))->count() > 1);

                foreach ($legacyMethods->unique('provider')->values() as $index => $method) {
                    DB::table('event_payment_methods')->insert([
                        'event_id' => $event->id,
                        ...$method,
                        'is_enabled' => true,
                        'sort_order' => $index,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                if ($hasConflicts) {
                    DB::table('events')->where('id', $event->id)->update([
                        'payment_setup_needs_review' => true,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_payment_methods');

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('payment_setup_needs_review');
        });
    }
};
