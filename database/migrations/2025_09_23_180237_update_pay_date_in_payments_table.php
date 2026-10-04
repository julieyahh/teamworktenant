<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Make sure pay_date has a default
            $table->timestamp('pay_date')->nullable()->default(now())->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Rollback changes
            $table->dropColumn(['payment_for', 'account_no']);
            $table->timestamp('pay_date')->nullable(false)->change();
        });
    }
};
