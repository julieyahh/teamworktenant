<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the fields needed for the yearly 5% rent escalation.
     *
     * monthly_rent  – the rent this lease is actually charged. Starts as the
     *                 unit's room_price and grows 5% on every renewal, so the
     *                 unit price stays the "list price" for new tenants.
     */
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            if (!Schema::hasColumn('leases', 'monthly_rent')) {
                $table->decimal('monthly_rent', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('leases', 'renewal_count')) {
                $table->unsignedInteger('renewal_count')->default(0);
            }
            if (!Schema::hasColumn('leases', 'last_renewed_at')) {
                $table->date('last_renewed_at')->nullable();
            }
        });

        // Backfill existing leases so nothing shows a blank rent.
        DB::table('leases')
            ->whereNull('monthly_rent')
            ->update([
                'monthly_rent' => DB::raw('COALESCE((SELECT room_price FROM units WHERE units.id = leases.unit_id), 0)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            foreach (['monthly_rent', 'renewal_count', 'last_renewed_at'] as $column) {
                if (Schema::hasColumn('leases', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
