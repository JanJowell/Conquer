<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            if (Schema::hasIndex('registrations', ['bib_number'], 'unique')) {
                $table->dropUnique(['bib_number']);
            }

            if (! Schema::hasIndex('registrations', ['event_id', 'bib_number'], 'unique')) {
                $table->unique(['event_id', 'bib_number']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            if (Schema::hasIndex('registrations', ['event_id', 'bib_number'], 'unique')) {
                $table->dropUnique(['event_id', 'bib_number']);
            }

            if (! Schema::hasIndex('registrations', ['bib_number'], 'unique')) {
                $table->unique('bib_number');
            }
        });
    }
};
