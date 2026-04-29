<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('runner')->after('email');
            $table->string('phone')->nullable()->after('role');
            $table->string('gender')->nullable()->after('phone');
            $table->date('birthdate')->nullable()->after('gender');
            $table->string('address')->nullable()->after('birthdate');
            $table->string('emergency_contact_name')->nullable()->after('address');
            $table->string('emergency_contact_number')->nullable()->after('emergency_contact_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'phone',
                'gender',
                'birthdate',
                'address',
                'emergency_contact_name',
                'emergency_contact_number',
            ]);
        });
    }
};