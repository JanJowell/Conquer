<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('medical_certificate_path')->nullable()->after('medical_conditions');
            $table->timestamp('medical_certificate_submitted_at')->nullable()->after('medical_certificate_path');
            $table->boolean('first_aid_kit_confirmed')->default(false)->after('medical_certificate_submitted_at');
            $table->boolean('waiver_accepted')->default(false)->after('first_aid_kit_confirmed');
            $table->timestamp('waiver_accepted_at')->nullable()->after('waiver_accepted');
            $table->string('waiver_name')->nullable()->after('waiver_accepted_at');
            $table->string('waiver_ip', 45)->nullable()->after('waiver_name');
            $table->string('waiver_user_agent', 512)->nullable()->after('waiver_ip');
            $table->timestamp('kit_waiver_signed_at')->nullable()->after('waiver_user_agent');
            $table->timestamp('kit_released_at')->nullable()->after('kit_waiver_signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn([
                'medical_certificate_path',
                'medical_certificate_submitted_at',
                'first_aid_kit_confirmed',
                'waiver_accepted',
                'waiver_accepted_at',
                'waiver_name',
                'waiver_ip',
                'waiver_user_agent',
                'kit_waiver_signed_at',
                'kit_released_at',
            ]);
        });
    }
};
