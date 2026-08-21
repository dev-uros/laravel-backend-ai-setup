<?php

use App\Enums\UserRole;
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
             * A plain string rather than a native Postgres enum: the values are
             * validated by the UserRole cast on the model, and adding a role to
             * a database enum type is a migration all of its own.
             *
             * The default is the harmless role, so a row created without one
             * can only read.
             */
            $table->string('role')
                ->default(UserRole::User->value)
                ->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
