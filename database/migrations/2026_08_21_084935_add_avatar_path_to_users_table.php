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
             * Where the avatar lives on the `public` disk, e.g.
             * `avatars/01K3B2....webp`. Null means the account has none and the
             * frontend draws its own placeholder.
             *
             * The path is stored, never the URL: the disk's base URL changes
             * with the environment (and would change again behind a CDN), and a
             * stored URL would freeze whichever one was current when the file
             * was uploaded.
             */
            $table->string('avatar_path')
                ->nullable()
                ->after('deactivated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
