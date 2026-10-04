<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records the meter reading behind an Electricity bill, when one was used.
 *
 * `amount` remains the source of truth for what is owed (see the MONEY MODEL
 * note on App\Models\UtilityBillingProof) — these two columns are kept purely
 * so a bill can still show its own math later ("120 kWh x PHP 18.00") instead
 * of only the resulting total. Null on bills entered as a flat peso amount
 * (Water, Repair, Other, and any Electricity bill issued before this existed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utility_billing_proofs', function (Blueprint $table) {
            $table->decimal('kwh_reading', 10, 2)->nullable()->after('amount');
            $table->decimal('kwh_rate', 8, 2)->nullable()->after('kwh_reading');
        });
    }

    public function down(): void
    {
        Schema::table('utility_billing_proofs', function (Blueprint $table) {
            $table->dropColumn(['kwh_reading', 'kwh_rate']);
        });
    }
};
