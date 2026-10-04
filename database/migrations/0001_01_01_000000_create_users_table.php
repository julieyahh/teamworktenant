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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('role')->default('tenant'); // tenant or manager
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->string('contact_number')->nullable();
            
            // Tenant financial info
            $table->decimal('rent_amount', 10, 2)->default(0);      // Monthly rent
            $table->decimal('utility_amount', 10, 2)->default(0);   // Monthly utilities
            $table->decimal('deposit_amount', 10, 2)->default(0);   // Total deposit required
            $table->decimal('rent_balance', 10, 2)->default(0);     // Unpaid rent balance
            $table->decimal('utility_balance', 10, 2)->default(0);  // Unpaid utility balance

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->index(); // index instead of primary
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop child tables first
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
