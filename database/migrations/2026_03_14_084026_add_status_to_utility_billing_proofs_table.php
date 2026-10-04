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
        Schema::table('utility_billing_proofs', function (Blueprint $table) {
            $table->enum('status', ['unpaid', 'paid'])->default('unpaid')->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('utility_billing_proofs', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
