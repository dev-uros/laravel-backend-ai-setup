<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_logs', function (Blueprint $table) {
            $table->id();

            /**
             * Two references, never one: over user accounts both sides are
             * users, and a single `user_id` stops being readable within months.
             * This one is whose record changed.
             *
             * Neither foreign key cascades. Deleting a user should fail loudly
             * rather than quietly erase the trail that says what happened to
             * them -- there is no delete endpoint, so this only ever fires for
             * somebody doing it by hand.
             */
            $table->foreignId('user_id')->constrained();

            /**
             * Who made the change. Null means the system -- a seeder, a console
             * command, the scheduler. That is a statement, not a gap.
             */
            $table->foreignId('actor_id')->nullable()->constrained('users');

            $table->string('event');

            /**
             * Only the fields that changed, not a snapshot of the row. As jsonb
             * rather than real columns: otherwise every new field on `users`
             * would need a migration here too, forever.
             */
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            /**
             * Written once and never touched again, so there is no updated_at.
             */
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
            $table->index('event');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_logs');
    }
};
