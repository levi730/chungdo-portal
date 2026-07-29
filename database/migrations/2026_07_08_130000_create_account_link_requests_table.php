<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A consented request from one existing account holder to link households
     * with another. Accepting establishes mutual guardianship between the two
     * adults and co-guardianship of each household's children. Nothing is
     * merged; both accounts keep their ids and history.
     */
    public function up(): void
    {
        Schema::create('account_link_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending | accepted | declined | cancelled
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_user_id', 'status']);
            $table->index(['requester_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_link_requests');
    }
};
