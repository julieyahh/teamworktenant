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
        Schema::table('leases', function (Blueprint $table) {
            // Add utility_balance column if it doesn't exist
            if (!Schema::hasColumn('leases', 'utility_balance')) {
                $table->decimal('utility_balance', 10, 2)->default(0)->after('room_no');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            if (Schema::hasColumn('leases', 'utility_balance')) {
                $table->dropColumn('utility_balance');
            }
        });
    }
};
