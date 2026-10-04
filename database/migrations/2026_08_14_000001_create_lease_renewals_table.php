<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per renewal event.
     *
     * `leases.renewal_count` and `leases.last_renewed_at` only say "this lease
     * has been renewed N times, most recently on X". They cannot answer "which
     * tenants renewed, when, and what did the rent go from and to" — which is
     * the question the renewal report has to answer. That needs a row per
     * event, because a renewal is a thing that HAPPENED, not a property of the
     * lease's current state.
     *
     * previous_rent / new_rent are stored as numbers rather than recomputed
     * from RENT_INCREASE_RATE on the fly: if the rate is ever changed, history
     * must keep showing what was actually charged at the time.
     */
    public function up(): void
    {
        Schema::create('lease_renewals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lease_id')->constrained('leases')->onDelete('cascade');
            $table->foreignId('tenant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();

            $table->string('room_no')->nullable();

            // 1 for the first renewal, 2 for the second, and so on. Mirrors
            // leases.renewal_count at the moment the renewal happened.
            $table->unsignedInteger('renewal_no')->default(1);

            $table->decimal('previous_rent', 10, 2)->default(0);
            $table->decimal('new_rent', 10, 2)->default(0);
            $table->decimal('increase_amount', 10, 2)->default(0);
            // Stored as a fraction: 0.0500 = 5%.
            $table->decimal('increase_rate', 6, 4)->default(0);

            $table->date('previous_end_date')->nullable();
            $table->date('new_start_date')->nullable();
            $table->date('new_end_date')->nullable();

            // 'manual'    — a manager clicked Renew Lease
            // 'scheduled' — the leases:renew command rolled it over automatically
            $table->string('source')->default('manual');
            $table->foreignId('renewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['lease_id', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
        });

        // Backfill: leases that were already renewed before this table existed
        // have a renewal_count but no rows. Without this, a tenant who renewed
        // last month would look like they never had. We only know the CURRENT
        // rent and the count, so one summary row per lease is the honest
        // reconstruction — flagged in notes so nobody reads it as a real event
        // log. Renewal_no is set to the count so it sorts as the latest.
        $legacy = DB::table('leases')
            ->where('renewal_count', '>', 0)
            ->get(['id', 'user_id', 'unit_id', 'room_no', 'renewal_count', 'last_renewed_at', 'monthly_rent', 'lea_start_date', 'lea_end_date']);

        $now = now();

        foreach ($legacy as $lease) {
            $newRent = (float) ($lease->monthly_rent ?? 0);
            // Undo one 5% step to get the rent before the most recent renewal.
            $previousRent = $newRent > 0 ? round($newRent / 1.05, 2) : 0;

            DB::table('lease_renewals')->insert([
                'lease_id'          => $lease->id,
                'tenant_id'         => $lease->user_id,
                'unit_id'           => $lease->unit_id,
                'room_no'           => $lease->room_no,
                'renewal_no'        => (int) $lease->renewal_count,
                'previous_rent'     => $previousRent,
                'new_rent'          => $newRent,
                'increase_amount'   => round($newRent - $previousRent, 2),
                'increase_rate'     => 0.0500,
                'previous_end_date' => null,
                'new_start_date'    => $lease->lea_start_date,
                'new_end_date'      => $lease->lea_end_date,
                'source'            => 'legacy',
                'renewed_by'        => null,
                'notes'             => 'Reconstructed when the renewal history table was added. '
                                     . 'Rent figures are derived from the current rate, not recorded at the time.',
                'created_at'        => $lease->last_renewed_at ?? $now,
                'updated_at'        => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_renewals');
    }
};
