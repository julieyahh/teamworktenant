<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Payment;
use App\Models\RentCharge;
use App\Models\Unit;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Monthly rent accrual.
 *
 * The bug: rent was charged twice in a lease's whole life — once at approval
 * and again at each yearly renewal — so a tenant who paid their first month
 * owed nothing for the following eleven.
 *
 * What is pinned down here:
 *
 *   1. One charge per lease per month, and running the command twice does not
 *      bill anyone twice.
 *   2. Months that fall due while nobody is paying accumulate, up to a cap.
 *   3. Advance rent meets each month as it is raised.
 *   4. `due_date` points at the oldest month actually unpaid.
 *   5. The late penalty fires once per missed cycle, not once per night.
 *   6. Terminated leases and leases off the schedule are left alone.
 */
class RentAccrualTest extends TestCase
{
    use RefreshDatabase;

    private BillingService $billing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->billing = app(BillingService::class);

        // v19: these tests are about whole months. Rent is raised on the 1st,
        // so start every lease on a 1st. Part-months are covered in
        // tests/Feature/V19ChangesTest.php.
        \Carbon\Carbon::setTestNow('2026-10-01 09:00:00');
    }

    private function makeLease(array $overrides = []): Lease
    {
        $tenant = User::create([
            'first_name' => 'Rent',
            'last_name'  => 'Tenant' . uniqid(),
            'email'      => 'rent' . uniqid() . '@example.test',
            'password'   => 'secret',
            'role'       => 'tenant',
            'status'     => 'approved',
        ]);

        $unit = Unit::create([
            'type'            => 'Studio',
            'room_no'         => 'R' . random_int(100, 999),
            'room_price'      => 5000,
            'status'          => 'occupied',
            'no_of_occupants' => 1,
        ]);

        return Lease::create(array_merge([
            'user_id'         => $tenant->id,
            'unit_id'         => $unit->id,
            'lea_start_date'  => now()->toDateString(),
            'lea_end_date'    => now()->addYear()->toDateString(),
            'lea_status'      => 'active',
            'room_no'         => $unit->room_no,
            'rent_balance'    => 0,
            'utility_balance' => 0,
            'deposit_balance' => 0,
            'monthly_rent'    => 5000,
        ], $overrides));
    }

    /** A lease started today, already on the schedule with month one raised. */
    private function scheduledLease(array $overrides = []): Lease
    {
        $lease = $this->makeLease($overrides);
        $this->billing->openRentSchedule($lease, now()->startOfDay());

        return $lease->fresh();
    }

    /* ==================================================================
     |  1. THE CORE BUG
     * ================================================================== */

    public function test_rent_keeps_accruing_month_after_month(): void
    {
        $lease = $this->scheduledLease();

        // Month one is on the balance from approval.
        $this->assertEquals(5000.00, (float) $lease->rent_balance);
        $this->assertEquals(1, $lease->rentCharges()->count());

        // The tenant pays it. Under the old code this was the end of it — the
        // balance went to zero and nothing ever put month two on it.
        $this->billing->applyAcceptedPayment($this->rentPayment($lease, 5000.00));
        $this->assertEquals(0.00, (float) $lease->fresh()->rent_balance);

        // A month passes.
        $this->billing->chargeDueRent($lease->fresh(), now()->addMonthNoOverflow());

        $lease->refresh();

        $this->assertEquals(5000.00, (float) $lease->rent_balance);
        $this->assertEquals(2, $lease->rentCharges()->count());
    }

    public function test_running_the_charge_twice_does_not_bill_twice(): void
    {
        $lease = $this->scheduledLease();
        $asOf  = now()->addMonthNoOverflow();

        $this->billing->chargeDueRent($lease->fresh(), $asOf);
        $this->billing->chargeDueRent($lease->fresh(), $asOf);
        $this->billing->chargeDueRent($lease->fresh(), $asOf);

        $lease->refresh();

        $this->assertEquals(2, $lease->rentCharges()->count());
        $this->assertEquals(10000.00, (float) $lease->rent_balance);
    }

    public function test_one_charge_per_month_is_enforced_by_the_database(): void
    {
        $lease = $this->scheduledLease();
        $first = $lease->rentCharges()->first();

        // The date arithmetic is not the guarantee — the unique index is.
        $this->expectException(\Illuminate\Database\QueryException::class);

        RentCharge::create([
            'lease_id'     => $lease->id,
            'tenant_id'    => $lease->user_id,
            'period_start' => $first->period_start,
            'period_end'   => $first->period_end,
            'due_date'     => $first->due_date,
            'amount'       => 5000,
        ]);
    }

    /* ==================================================================
     |  2. CATCHING UP
     * ================================================================== */

    public function test_missed_months_accumulate_up_to_the_cap(): void
    {
        config(['billing.rent.max_catchup_months' => 3]);

        $lease = $this->scheduledLease();

        // Nobody has run the scheduler for half a year.
        $result = $this->billing->chargeDueRent($lease->fresh(), now()->addMonthsNoOverflow(6));

        $this->assertTrue($result['capped']);
        $this->assertEquals(3, $result['charged']);

        // A server whose cron has been dead for months must not silently bill
        // six months in one night. It stops at the cap and says so, and a
        // person decides what to do about the rest.
        // The approval month plus the three the cap allowed.
        $this->assertEquals(20000.00, (float) $lease->fresh()->rent_balance);
    }

    /* ==================================================================
     |  3. ADVANCE RENT MEETS EACH MONTH
     * ================================================================== */

    public function test_advance_rent_covers_months_as_they_are_raised(): void
    {
        $lease = $this->scheduledLease();

        // Clear month one, then pay two months forward.
        $this->billing->applyAcceptedPayment($this->rentPayment($lease, 5000.00));
        $this->billing->applyAcceptedPayment($this->advancePayment($lease, 10000.00));

        $lease->refresh();
        $this->assertEquals(10000.00, $lease->advanceRentBalance());

        $result = $this->billing->chargeDueRent($lease->fresh(), now()->addMonthNoOverflow());

        $this->assertEquals(5000.00, $result['advance_used']);

        $lease->refresh();

        // The whole point of paying ahead: the tenant does nothing when the
        // month comes round.
        $this->assertEquals(0.00, (float) $lease->rent_balance);
        $this->assertEquals(5000.00, $lease->advanceRentBalance());
        $this->assertEquals(5000.00, (float) $lease->rentCharges()->latest('id')->first()->advance_applied);
    }

    /* ==================================================================
     |  4. DUE DATE POINTS AT THE OLDEST UNPAID MONTH
     * ================================================================== */

    public function test_due_date_tracks_the_oldest_month_still_owed(): void
    {
        $lease = $this->scheduledLease();
        $firstDue = $lease->rentCharges()->first()->due_date->toDateString();

        $this->billing->chargeDueRent($lease->fresh(), now()->addMonthNoOverflow());
        $lease->refresh();

        // Two months owed; the due date is still the FIRST one's.
        $this->assertEquals(10000.00, (float) $lease->rent_balance);
        $this->assertEquals($firstDue, $lease->due_date->toDateString());

        // Clearing the older month moves it to the newer one, not blindly
        // forward by a month.
        $this->billing->applyAcceptedPayment($this->rentPayment($lease, 5000.00));
        $lease->refresh();

        $second = $lease->rentCharges()->orderBy('period_start')->get()->last();
        $this->assertEquals($second->due_date->toDateString(), $lease->due_date->toDateString());
    }

    public function test_the_ledger_says_which_months_are_settled(): void
    {
        $lease = $this->scheduledLease();
        $this->billing->chargeDueRent($lease->fresh(), now()->addMonthNoOverflow());

        // Pay one and a half months.
        $this->billing->applyAcceptedPayment($this->rentPayment($lease, 7500.00));

        $ledger = $lease->fresh()->rentChargeLedger();

        $this->assertCount(2, $ledger);
        $this->assertEquals(0.00, $ledger[0]['outstanding']);   // oldest cleared first
        $this->assertEquals(2500.00, $ledger[1]['outstanding']);
    }

    /* ==================================================================
     |  5. THE LATE PENALTY
     * ================================================================== */

    public function test_a_penalty_is_charged_once_per_missed_cycle(): void
    {
        $lease = $this->scheduledLease();

        $overdue = now()->addMonthsNoOverflow(2);

        $first = $this->billing->assessLatePenalty($lease->fresh(), $overdue);
        $this->assertEquals(500.00, $first);   // 10% of 5,000

        // The nightly run must not penalise the same overdue month again.
        $this->assertEquals(0.00, $this->billing->assessLatePenalty($lease->fresh(), $overdue->copy()->addDay()));
        $this->assertEquals(0.00, $this->billing->assessLatePenalty($lease->fresh(), $overdue->copy()->addDays(2)));

        $this->assertEquals(500.00, (float) $lease->fresh()->penalty_fee);
    }

    public function test_no_penalty_while_the_rent_is_paid(): void
    {
        $lease = $this->scheduledLease();

        $this->billing->applyAcceptedPayment($this->rentPayment($lease, 5000.00));

        $this->assertEquals(
            0.00,
            $this->billing->assessLatePenalty($lease->fresh(), now()->addMonthsNoOverflow(2))
        );
    }

    /* ==================================================================
     |  6. WHO IS LEFT ALONE
     * ================================================================== */

    public function test_a_terminated_lease_stops_accruing(): void
    {
        $lease = $this->scheduledLease();
        $lease->update(['lea_status' => 'terminated']);

        $result = $this->billing->chargeDueRent($lease->fresh(), now()->addMonthsNoOverflow(3));

        $this->assertEquals(0, $result['charged']);
        $this->assertEquals(1, $lease->fresh()->rentCharges()->count());
    }

    public function test_a_lease_not_on_the_schedule_is_never_charged(): void
    {
        // Exactly the state the migration leaves a lease it chose to skip.
        $lease = $this->makeLease();

        $this->assertFalse($lease->isOnRentSchedule());
        $this->assertEquals(0, $this->billing->chargeDueRent($lease, now()->addYear())['charged']);
    }

    public function test_a_lease_with_no_rent_set_raises_nothing(): void
    {
        $lease = $this->makeLease(['monthly_rent' => 0]);
        $lease->unit->update(['room_price' => 0]);
        $lease->refresh();

        // Must not produce an endless run of zero-peso charges every night.
        $this->assertNull($this->billing->openRentSchedule($lease, now()));
        $this->assertEquals(0, RentCharge::count());
    }

    /* ==================================================================
     |  7. THE COMMAND
     * ================================================================== */

    public function test_the_dry_run_writes_nothing(): void
    {
        $lease = $this->scheduledLease();

        $this->artisan('rent:charge', [
            '--dry-run' => true,
            '--date'    => now()->addMonthNoOverflow()->toDateString(),
        ])->assertSuccessful();

        $this->assertEquals(1, $lease->fresh()->rentCharges()->count());
        $this->assertEquals(5000.00, (float) $lease->fresh()->rent_balance);
    }

    public function test_the_command_charges_and_can_be_run_again_safely(): void
    {
        $lease = $this->scheduledLease();
        $date  = now()->addMonthNoOverflow()->toDateString();

        $this->artisan('rent:charge', ['--date' => $date])->assertSuccessful();
        $this->artisan('rent:charge', ['--date' => $date])->assertSuccessful();

        $lease->refresh();

        $this->assertEquals(2, $lease->rentCharges()->count());
        $this->assertEquals(10000.00, (float) $lease->rent_balance);
    }

    /* ==================================================================
     |  HELPERS
     * ================================================================== */

    private function rentPayment(Lease $lease, float $amount): Payment
    {
        return Payment::create([
            'tenant_id'        => $lease->user_id,
            'lease_id'         => $lease->id,
            'pay_date'         => now(),
            'payment_for'      => 'Rent',
            'pay_amount'       => $amount,
            'pay_method'       => 'Cash',
            'pay_status'       => 'Accepted',
            'reference_number' => 'RNT-' . uniqid(),
        ]);
    }

    private function advancePayment(Lease $lease, float $amount): Payment
    {
        return Payment::create([
            'tenant_id'        => $lease->user_id,
            'lease_id'         => $lease->id,
            'pay_date'         => now(),
            'payment_for'      => 'Advance Rent',
            'pay_amount'       => $amount,
            'pay_method'       => 'Cash',
            'pay_status'       => 'Accepted',
            'reference_number' => 'ADV-' . uniqid(),
        ]);
    }
}
