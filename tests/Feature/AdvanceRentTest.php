<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\LeaseRenewal;
use App\Models\Payment;
use App\Models\Unit;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Advance rent — rent paid before the month it covers.
 *
 * The rules being pinned down here, in the order they matter:
 *
 *   1. Arrears and penalties are settled before any of the money buys a
 *      future month.
 *   2. What is left is held on the LEASE (advance_rent_balance), not in
 *      users.user_credit, so a utility bill can never spend it.
 *   3. Only WHOLE months move the due date. A part month sits on the balance.
 *   4. Reversing an advance undoes both halves in the proportions recorded at
 *      the time, and rolls the dates back by the months actually granted.
 *   5. A renewal draws the advance down at the NEW rent, not the old one.
 *
 * Runs against SQLite in memory (see phpunit.xml).
 */
class AdvanceRentTest extends TestCase
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

    /* ==================================================================
     |  FIXTURES
     * ================================================================== */

    private function makeLease(array $overrides = []): Lease
    {
        $tenant = User::create([
            'first_name' => 'Adv',
            'last_name'  => 'Tenant' . uniqid(),
            'email'      => 'adv' . uniqid() . '@example.test',
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
            'lea_start_date'  => now()->subMonths(2)->toDateString(),
            'lea_end_date'    => now()->addMonths(10)->toDateString(),
            'lea_status'      => 'active',
            'room_no'         => $unit->room_no,
            'rent_balance'    => 0,
            'utility_balance' => 0,
            'deposit_balance' => 0,
            'monthly_rent'    => 5000,
            'due_date'        => now()->addDays(10)->toDateString(),
        ], $overrides));
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

    /* ==================================================================
     |  1. HOLDING THE MONEY
     * ================================================================== */

    public function test_a_clean_advance_is_held_on_the_lease_and_buys_months(): void
    {
        $lease = $this->makeLease();
        $dueBefore = $lease->due_date->copy();

        $this->billing->applyAcceptedPayment($this->advancePayment($lease, 15000.00));

        $lease->refresh();

        $this->assertEquals(15000.00, $lease->advanceRentBalance());
        $this->assertEquals(3, $lease->advanceMonthsCovered());
        $this->assertEquals(0.00, (float) $lease->rent_balance);

        // Three whole months bought, so the due date moves three months.
        $this->assertEquals(
            $dueBefore->copy()->addMonths(3)->toDateString(),
            $lease->due_date->toDateString()
        );

        $this->assertNotNull($lease->rent_paid_through);
    }

    /**
     * The whole reason this lives on the lease instead of users.user_credit.
     */
    public function test_an_advance_never_becomes_general_tenant_credit(): void
    {
        $lease = $this->makeLease();

        $this->billing->applyAcceptedPayment($this->advancePayment($lease, 10000.00));

        $this->assertEquals(0.00, round((float) $lease->tenant->fresh()->user_credit, 2));
        $this->assertEquals(10000.00, $lease->fresh()->advanceRentBalance());
    }

    /* ==================================================================
     |  2. ARREARS FIRST
     * ================================================================== */

    public function test_arrears_and_penalty_are_settled_before_any_month_is_bought(): void
    {
        $lease = $this->makeLease([
            'rent_balance' => 5000,
            'penalty_fee'  => 500,
        ]);

        // 5,500 clears the arrears, the remaining 10,000 buys two months.
        $payment = $this->advancePayment($lease, 15500.00);
        $this->billing->applyAcceptedPayment($payment);

        $lease->refresh();
        $payment->refresh();

        $this->assertEquals(0.00, (float) $lease->rent_balance);
        $this->assertEquals(0.00, (float) $lease->penalty_fee);
        $this->assertEquals(10000.00, $lease->advanceRentBalance());
        $this->assertEquals(2, $payment->advance_months);
        $this->assertEquals(10000.00, (float) $payment->advance_amount);
    }

    /* ==================================================================
     |  3. PART MONTHS BUY NO TIME
     * ================================================================== */

    public function test_a_part_month_sits_on_the_balance_without_moving_the_due_date(): void
    {
        $lease = $this->makeLease();
        $dueBefore = $lease->due_date->copy();

        $this->billing->applyAcceptedPayment($this->advancePayment($lease, 7500.00));

        $lease->refresh();

        $this->assertEquals(7500.00, $lease->advanceRentBalance());
        $this->assertEquals(1, $lease->advanceMonthsCovered());
        $this->assertEquals(
            $dueBefore->copy()->addMonth()->toDateString(),
            $lease->due_date->toDateString()
        );
    }

    /**
     * Two half-months should buy one month between them, not zero. Months are
     * granted on the rounded-down TOTAL, not on each payment in isolation.
     */
    public function test_two_part_payments_combine_into_a_whole_month(): void
    {
        $lease = $this->makeLease();

        $first = $this->advancePayment($lease, 3000.00);
        $this->billing->applyAcceptedPayment($first);
        $lease->refresh();

        $this->assertEquals(0, $first->fresh()->advance_months);
        $this->assertEquals(0, $lease->advanceMonthsCovered());

        $second = $this->advancePayment($lease, 3000.00);
        $this->billing->applyAcceptedPayment($second);
        $lease->refresh();

        $this->assertEquals(1, $second->fresh()->advance_months);
        $this->assertEquals(6000.00, $lease->advanceRentBalance());
        $this->assertEquals(1, $lease->advanceMonthsCovered());
    }

    /* ==================================================================
     |  4. REVERSAL
     * ================================================================== */

    public function test_reversing_an_advance_undoes_both_halves_and_the_dates(): void
    {
        $lease = $this->makeLease(['rent_balance' => 5000]);
        $dueBefore = $lease->due_date->copy();

        $payment = $this->advancePayment($lease, 15000.00);  // 5,000 arrears + 2 months
        $this->billing->applyAcceptedPayment($payment);

        $lease->refresh();
        $this->assertEquals(10000.00, $lease->advanceRentBalance());
        $this->assertEquals(2, $payment->fresh()->advance_months);

        $this->billing->reverseAcceptedPayment($payment->fresh(), 'Accept was a mis-click');

        $lease->refresh();

        // The arrears come back, the held money goes away, the dates roll back.
        $this->assertEquals(5000.00, (float) $lease->rent_balance);
        $this->assertEquals(0.00, $lease->advanceRentBalance());
        $this->assertEquals($dueBefore->toDateString(), $lease->due_date->toDateString());
    }

    /* ==================================================================
     |  5. RENEWAL DRAWS IT DOWN AT THE NEW RENT
     * ================================================================== */

    public function test_a_renewal_changes_the_rate_without_billing_a_month(): void
    {
        $lease = $this->makeLease();

        $this->billing->applyAcceptedPayment($this->advancePayment($lease, 10000.00));
        $lease->refresh();

        $this->assertEquals(10000.00, $lease->advanceRentBalance());

        $summary = $lease->renewForAnotherYear(null, LeaseRenewal::SOURCE_MANUAL);

        $this->assertNotNull($summary);
        $this->assertEquals(5250.00, $summary['new_rent']);

        $lease->refresh();

        // A renewal is a CONTRACT event. It used to add a month of rent to the
        // balance because it was the only recurring charge in the system;
        // now that rent:charge raises every month, doing it here as well would
        // bill the renewal month twice.
        $this->assertEquals(0.00, (float) $lease->rent_balance);
        $this->assertEquals(10000.00, $lease->advanceRentBalance());
    }

    public function test_the_next_charge_after_a_renewal_uses_the_new_rate(): void
    {
        $lease = $this->makeLease();
        $this->billing->openRentSchedule($lease, now()->startOfDay());

        // 10,000 against a lease that already owes its first month: 5,000
        // settles that, 5,000 is held forward.
        $this->billing->applyAcceptedPayment($this->advancePayment($lease, 10000.00));
        $lease->refresh();

        $this->assertEquals(0.00, (float) $lease->rent_balance);
        $this->assertEquals(5000.00, $lease->advanceRentBalance());

        $lease->renewForAnotherYear(null, LeaseRenewal::SOURCE_MANUAL);
        $lease->refresh();

        // One month on, the scheduler raises rent at the renewed rate — not
        // the rate that applied when the advance was paid.
        $result = $this->billing->chargeDueRent($lease, now()->addMonthNoOverflow()->addDay());

        $this->assertEquals(1, $result['charged']);
        $this->assertEquals(5250.00, $result['total']);

        $lease->refresh();

        // The held 5,000 bought a month at the old rate. At 5,250 it no longer
        // covers one, so 250 falls due — the increase actually applied, which
        // is the whole point of drawing the advance down at the new rate.
        $this->assertEquals(5000.00, $result['advance_used']);
        $this->assertEquals(250.00, (float) $lease->rent_balance);
        $this->assertEquals(0.00, $lease->advanceRentBalance());
    }

    /* ==================================================================
     |  6. MOVE-OUT RETURNS THE UNUSED PART
     * ================================================================== */

    public function test_consuming_the_advance_clears_rent_and_penalty_first(): void
    {
        $lease = $this->makeLease();

        $this->billing->applyAcceptedPayment($this->advancePayment($lease, 10000.00));

        $lease->refresh();
        $lease->rent_balance = 5000;
        $lease->penalty_fee  = 500;

        $consumed = $lease->consumeAdvanceRent();
        $lease->save();

        $this->assertEquals(5500.00, $consumed);
        $this->assertEquals(0.00, (float) $lease->fresh()->rent_balance);
        $this->assertEquals(0.00, (float) $lease->fresh()->penalty_fee);
        $this->assertEquals(4500.00, $lease->fresh()->advanceRentBalance());
    }

    /* ==================================================================
     |  7. STATUS REPORTING
     * ================================================================== */

    public function test_charge_statuses_distinguish_paid_from_paid_ahead(): void
    {
        $lease = $this->makeLease();

        $this->assertEquals('Paid', $lease->chargeStatuses()['rent']);

        $this->billing->applyAcceptedPayment($this->advancePayment($lease, 5000.00));

        $this->assertEquals('Advance', $lease->fresh()->chargeStatuses()['rent']);
    }

    public function test_a_lease_with_no_rent_set_cannot_buy_months(): void
    {
        // Guards the division in advanceMonthsCovered(): a legacy or
        // misconfigured lease with no rent must not blow up or hand out
        // infinite months.
        $lease = $this->makeLease(['monthly_rent' => 0]);
        $lease->unit->update(['room_price' => 0]);
        $lease->refresh();

        $this->billing->applyAcceptedPayment($this->advancePayment($lease, 5000.00));

        $lease->refresh();

        $this->assertEquals(5000.00, $lease->advanceRentBalance());
        $this->assertEquals(0, $lease->advanceMonthsCovered());
    }
}
