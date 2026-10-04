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
            $table->decimal('rent_balance', 10, 2)->nullable()->after('utility_balance');
            $table->decimal('deposit_balance', 10, 2)->nullable()->after('utility_balance');
            $table->timestamp('paid_date')->nullable()->after('move_out_reason');
            $table->decimal('penalty_fee', 10, 2)->nullable()->before('bed_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
