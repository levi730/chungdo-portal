<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categorize events (Sparring / Forms / Combined tournament, Training, Picnic,
 * Social, Other) so we know which registration cards to print, and record an
 * optional host school. A null host reads as "Chung Do Association".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // One of the Event::TYPE_* keys. Null = legacy/unspecified.
            $table->string('type')->nullable()->after('name');
            $table->foreignId('host_school_id')->nullable()->after('location')
                ->constrained('schools')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('host_school_id');
            $table->dropColumn('type');
        });
    }
};
