<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_payment_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_shared')->default(false)->index();
            $table->string('label', 100);
            $table->string('provider', 50);
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Saved payment accounts require an explicitly reviewed recovery plan before removal.');
    }
};
