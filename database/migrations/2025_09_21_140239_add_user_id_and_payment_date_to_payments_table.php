<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Add user_id if it doesn't exist
            if (!Schema::hasColumn('payments', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            }

            // Add payment_date (if user_id exists, place after it, otherwise just add it)
            if (!Schema::hasColumn('payments', 'pay_date')) {
                if (Schema::hasColumn('payments', 'user_id')) {
                    $table->date('pay_date')->after('user_id');
                } else {
                    $table->date('pay_date');
                }
            }

            // Add amount if missing
            if (!Schema::hasColumn('payments', 'amount')) {
                $table->decimal('amount', 10, 2);
            }

            // Add type if missing
            if (!Schema::hasColumn('payments', 'type')) {
                $table->string('type')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('payments', 'pay_date')) {
                $table->dropColumn('pay_date');
            }
            if (Schema::hasColumn('payments', 'amount')) {
                $table->dropColumn('amount');
            }
            if (Schema::hasColumn('payments', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
