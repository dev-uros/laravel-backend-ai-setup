<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The phones an internal user has signed in on with the mobile app, and the
 * Expo token a push goes to.
 *
 * Internal users only. An external (public) application, when a project has
 * one, gets its own accounts and its own devices table -- the two populations
 * sign in differently and must never share a push audience.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('internal_user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            /**
             * The session currently signed in on this phone. Set to null by the
             * database the moment that token is deleted -- logout, password
             * reset, deactivation, all of them -- and a device without a session
             * receives no pushes. That is how signing out stops notifications
             * without every one of those flows having to remember to.
             *
             * Unique: one token is one session, and a session is on one phone.
             */
            $table->foreignId('personal_access_token_id')->nullable()->unique()->constrained()->nullOnDelete();

            /**
             * The phone's own id (Android ID; on iOS the vendor id, which
             * changes when the app is reinstalled). What a login upserts on.
             */
            $table->string('device_id');
            $table->string('name')->nullable();
            $table->string('platform');
            $table->string('operating_system')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('os_version')->nullable();

            /**
             * `ExponentPushToken[...]`, or null until the person allows
             * notifications. Unique across the table: a token addresses one
             * physical phone, and a phone handed from one user to another must
             * not keep delivering the first user's notifications.
             */
            $table->string('expo_push_notification_token')->nullable()->unique();

            /** The last sign-in or app launch on this phone. */
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['user_id', 'device_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internal_user_devices');
    }
};
