<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles a committee confers on its members.
     *
     * The pivot points at Spatie's roles table rather than at permissions
     * directly: "the events committee are event admins" is the sentence being
     * modelled, and it reuses the role vocabulary the user admin already shows.
     */
    public function up(): void
    {
        Schema::create('committee_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('committee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['committee_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_role');
    }
};
