<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The six-digit codes the external app signs in and registers with.
 *
 * One live code per address (unique), stored as a bcrypt hash -- the plain
 * code exists only in the e-mail. Keyed on the address rather than an account,
 * because a registration code is sent before there is one.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mobile_otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('code_hash');

            /** Wrong guesses so far; raised atomically, the code dies at the limit. */
            $table->unsignedSmallInteger('attempts')->default(0);

            /** When it was mailed -- what the 60-second resend cooldown counts from. */
            $table->timestamp('sent_at');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mobile_otp_codes');
    }
};
