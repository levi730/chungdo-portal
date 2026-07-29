<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guardianship is a many-to-many self-relation on users: who may act/pay
     * on behalf of whom. It replaces the single `responsible_user_id` FK, which
     * could only express one guardian per dependent. `responsible_user_id` is
     * left in place for now and retired once all reads move onto this table.
     */
    public function up(): void
    {
        Schema::create('guardianships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('dependent_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['guardian_user_id', 'dependent_user_id']);
        });

        // Backfill: every existing responsible_user_id becomes a primary guardianship.
        DB::statement('
            INSERT INTO guardianships (guardian_user_id, dependent_user_id, is_primary, created_at, updated_at)
            SELECT responsible_user_id, id, 1, NOW(), NOW()
            FROM users
            WHERE responsible_user_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('guardianships');
    }
};
