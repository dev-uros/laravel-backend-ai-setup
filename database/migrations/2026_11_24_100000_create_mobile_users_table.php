<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The accounts of the external mobile app -- customers, citizens, members --
 * kept apart from `users`, the people who run the system.
 *
 * A second population with its own tokens: Sanctum resolves a bearer token to
 * whichever model minted it, so every route group states which of the two it
 * serves (`EnsureInternalUser`, `EnsureMobileUser`).
 *
 * There is no password. An account is proved by a code mailed to its address,
 * or by Google or Apple vouching for it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mobile_users', function (Blueprint $table) {
            $table->id();

            /**
             * The handle other people see. Always stored lower-cased, so the
             * plain unique index is also case-insensitive. Null only once the
             * account is deleted -- Postgres lets any number of rows hold NULL
             * under a unique index, so the handle is free again at once.
             */
            $table->string('username')->nullable()->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();

            /** Show "first last" instead of the handle, where the person has given a name. */
            $table->boolean('display_personal_name')->default(false);

            /** Lower-cased and trimmed by every way in. Null once the account is deleted. */
            $table->string('email')->nullable()->unique();

            /** The `sub` of the Google and Apple identities linked to the account. */
            $table->string('google_id')->nullable()->unique();
            $table->string('apple_id')->nullable()->unique();

            /**
             * Encrypted (cast on the model): as good as the person's Apple
             * authorisation until it is revoked, which deleting the account
             * does.
             */
            $table->text('apple_refresh_token')->nullable();

            /**
             * The bundle ID the refresh token was issued to. Each app variant
             * has its own, and Apple refuses a revocation sent under another.
             */
            $table->string('apple_client_id')->nullable();

            /** What mail and pushes are written in: `sr` or `en`. */
            $table->string('locale', 5)->default('sr');
            $table->string('avatar_path')->nullable();

            /**
             * A store reviewer's account (`mobile:demo-accounts`): signs in with
             * the fixed `MOBILE_DEMO_CODE` and is never mailed.
             */
            $table->boolean('is_demo')->default(false);

            /** Set by an administrator; every token is deleted with it. */
            $table->timestamp('blocked_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mobile_users');
    }
};
