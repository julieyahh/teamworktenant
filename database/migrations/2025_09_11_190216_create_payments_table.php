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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('tenant_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->foreignId('lease_id')
                ->constrained()
                ->onDelete('cascade');

            // Payment details
            $table->date('pay_date');
            $table->string('payment_for'); // Rent, Utilities, Deposit, Other
            $table->decimal('pay_amount', 10, 2);
            $table->string('pay_method'); // Cash, GCash, Bank Transfer, etc.
            $table->string('account_no')->nullable(); // for online payments
            $table->string('pay_status')->default('Pending'); // Paid, Pending, Overdue
            $table->string('proof')->nullable(); // Screenshot or receipt

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
