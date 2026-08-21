<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Both log tables are pruned by `created_at` alone, and neither could use an
     * index for it: their existing ones all lead with another column
     * (`user_id`, `email`, `ip_address`), which a plain `where created_at <= ?`
     * cannot enter.
     *
     * That matters more than it looks. MassPrunable deletes in chunks of a
     * thousand, so an unindexed prune is not one sequential scan, it is one per
     * chunk -- the cost climbs with the size of the table exactly as the table
     * gets big enough for pruning to matter.
     */
    public function up(): void
    {
        Schema::table('user_logs', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('login_attempts', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('login_attempts', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
