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
        Schema::table('users', function (Blueprint $table) {
            /**
             * Null means the account works. A timestamp rather than a boolean
             * flag, in the same shape as `email_verified_at`, because the first
             * question support asks about a locked-out user is when it happened.
             *
             * This is not a soft delete: the row stays visible everywhere,
             * including the users list, so an administrator can see who is
             * switched off and switch them back on.
             */
            $table->timestamp('deactivated_at')
                ->nullable()
                ->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deactivated_at');
        });
    }
};
