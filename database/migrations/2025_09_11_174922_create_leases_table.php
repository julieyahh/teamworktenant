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
        // 1️⃣ Create leases table
        Schema::create('leases', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // tenant id
            $table->foreignId('unit_id')->constrained('units')->onDelete('cascade'); // unit id

            // Lease details
            $table->date('lea_start_date');        // start of lease
            $table->date('lea_end_date');          // end of lease
            $table->string('lea_status')->default('active'); // active / ended
            $table->string('lea_terms')->nullable();        // optional lease terms
            $table->string('room_no')->nullable();          // optional room number

            // Renewal request
            $table->boolean('renewal_requested')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};
