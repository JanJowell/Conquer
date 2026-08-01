<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedInteger('price_cents')->default(0)->after('slot_limit');
            $table->char('price_currency', 3)->default('PHP')->after('price_cents');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->boolean('payment_required')->default(false)->after('rejection_reason');
            $table->string('payment_status')->default('waived')->after('payment_required');
            $table->unsignedInteger('payment_amount_cents')->default(0)->after('payment_status');
            $table->char('payment_currency', 3)->default('PHP')->after('payment_amount_cents');
            $table->timestamp('paid_at')->nullable()->after('payment_currency');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('manual');
            $table->string('provider_reference')->nullable();
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('PHP');
            $table->string('status')->default('pending');
            $table->text('checkout_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['registration_id', 'status']);
            $table->index(['provider', 'provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn([
                'payment_required',
                'payment_status',
                'payment_amount_cents',
                'payment_currency',
                'paid_at',
            ]);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn([
                'price_cents',
                'price_currency',
            ]);
        });
    }
};
