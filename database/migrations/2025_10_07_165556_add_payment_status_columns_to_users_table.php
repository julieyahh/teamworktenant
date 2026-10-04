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
            $table->enum('rental_payment_status', ['pending', 'overdue', 'settled'])
                ->default('pending')
                ->after('status');

            $table->enum('utility_payment_status', ['pending', 'overdue', 'settled'])
                ->default('pending')
                ->after('rental_payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rental_payment_status', 'utility_payment_status']);
        });
    }
};
