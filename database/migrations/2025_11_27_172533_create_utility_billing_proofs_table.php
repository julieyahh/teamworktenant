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
        Schema::create('utility_billing_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('lease_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('file_path');
            $table->string('billing_month')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('utility_billing_proofs');
    }
};
