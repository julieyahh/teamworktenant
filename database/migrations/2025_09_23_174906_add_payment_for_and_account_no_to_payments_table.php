<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'payment_for')) {
                $table->string('payment_for')->nullable()->after('pay_status');
            }
            if (!Schema::hasColumn('payments', 'account_no')) {
                $table->string('account_no')->nullable()->after('payment_for');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'payment_for')) {
                $table->dropColumn('payment_for');
            }
            if (Schema::hasColumn('payments', 'account_no')) {
                $table->dropColumn('account_no');
            }
        });
    }
};
