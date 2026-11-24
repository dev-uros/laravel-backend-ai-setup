<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The phones a mobile user has signed in on with the external app, and the
 * Expo token a push goes to.
 *
 * The same shape as `internal_user_devices`, on purpose a separate table: the
 * two populations must never share a push audience.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mobile_user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_user_id')->constrained()->cascadeOnDelete();

            /**
             * The session currently signed in on this phone, set to null by the
             * database when that token is deleted -- logout, blocking, deleting
             * the account. A device without a session receives no pushes.
             */
            $table->foreignId('personal_access_token_id')->nullable()->unique()->constrained()->nullOnDelete();

            $table->string('device_id');
            $table->string('name')->nullable();
            $table->string('platform');
            $table->string('operating_system')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('os_version')->nullable();

            /** `ExponentPushToken[...]`, or null until the person allows notifications. */
            $table->string('expo_push_notification_token')->nullable()->unique();

            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['mobile_user_id', 'device_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mobile_user_devices');
    }
};
