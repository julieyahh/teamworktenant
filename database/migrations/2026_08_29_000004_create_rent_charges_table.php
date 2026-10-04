<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per month of rent charged.
 *
 * WHY A TABLE AND NOT JUST A RUNNING BALANCE
 *   `leases.rent_balance` can say "you owe PHP 15,000". It cannot say WHICH
 *   MONTHS, at what rate, or when each fell due — so a tenant who disputes the
 *   figure, or a manager trying to explain it, has nothing to point at. This
 *   is the same reasoning that put `lease_renewals` in the schema: a charge is
 *   a thing that HAPPENED, not a property of the lease's current state.
 *
 * WHY THE UNIQUE INDEX IS THE REAL SAFETY NET
 *   `leases.next_rent_charge_on` tells the command what to raise next, but a
 *   crash between writing the charge and stepping the date, two cron runs
 *   overlapping, or a manager clicking a manual charge twice would all
 *   double-bill a tenant. The unique index on (lease_id, period_start) makes
 *   that impossible at the database level rather than merely unlikely at the
 *   application level. Money is worth a constraint.
 *
 * Written only by App\Services\BillingService::chargeDueRent().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rent_charges', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lease_id')->constrained('leases')->onDelete('cascade');
            $table->foreignId('tenant_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('room_no')->nullable();

            // The month this charge covers: [period_start, period_end).
            $table->date('period_start');
            $table->date('period_end');

            // When the tenant has to have paid it by.
            $table->date('due_date');

            // The rent at the time it was charged. Stored as a number, not
            // recomputed from the lease: after a renewal the lease's
            // monthly_rent is the NEW rate, and history has to keep showing
            // what was actually charged at the time.
            $table->decimal('amount', 12, 2)->default(0);

            // How much of it was met immediately out of advance rent already
            // on the lease, so a prepaid tenant's history shows the month
            // being covered rather than a charge appearing from nowhere.
            $table->decimal('advance_applied', 12, 2)->default(0);

            // 'scheduled' — the rent:charge command raised it
            // 'manual'    — a manager raised it by hand
            // 'approval'  — the first month, written when the lease was created
            $table->string('source', 20)->default('scheduled');

            $table->foreignId('charged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();

            // The idempotency guarantee. One charge per lease per month, at
            // the database level.
            $table->unique(['lease_id', 'period_start']);
            $table->index(['tenant_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_charges');
    }
};
