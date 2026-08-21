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
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();

            /**
             * Its own table rather than more rows in `user_logs`, for two
             * reasons that both bite later:
             *
             * - an attempt on an address nobody uses has no user to point at,
             *   and making `user_logs.user_id` nullable to fit it would weaken
             *   the one column that trail is built on;
             * - logins outnumber data changes by orders of magnitude, and they
             *   are pruned on a different schedule. Mixed together, "who edited
             *   this account" drowns in "who signed in".
             */
            $table->foreignId('user_id')->nullable()->constrained();

            /**
             * What was typed. Kept even when it matches nobody -- that is how a
             * sweep across invented addresses becomes visible. The login request
             * validates the field as an email before this is reached, so a
             * password typed into the wrong box is rejected as 422 and never
             * lands here.
             */
            $table->string('email');

            $table->string('outcome');

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at');

            /** Who signed in, and when. */
            $table->index(['user_id', 'created_at']);

            /** Attempts on one address, including addresses that do not exist. */
            $table->index(['email', 'created_at']);

            /** Everything one address on the network tried, for spotting sweeps. */
            $table->index(['ip_address', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
