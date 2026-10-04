<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Payment;
use App\Models\Unit;
use App\Models\User;
use App\Models\UtilityBillingProof;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The QA list, as tests.
 *
 * Covers: exact payment, underpayment, overpayment, zero-amount edge case,
 * a bill arriving after its due date, and multi-tenant proration with a
 * rounding remainder.
 *
 * These run against SQLite in memory (see phpunit.xml). One thing to watch:
 * the report filters use raw DATE_FORMAT(), which is MySQL-only and will throw
 * here — that is a pre-existing issue noted in docs/PROJECT-MAP.md §3 and is
 * why these tests exercise the service directly rather than through the report
 * screens.
 */
class BillingTest extends TestCase
{
    use RefreshDatabase;

    private BillingService $billing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->billing = app(BillingService::class);
    }

    /* ==================================================================
     |  FIXTURES
     * ================================================================== */

    private function makeLease(array $overrides = []): Lease
    {
        $tenant = User::create([
            'first_name' => 'Test',
            'last_name'  => 'Tenant' . uniqid(),
            'email'      => 'tenant' . uniqid() . '@example.test',
            'password'   => 'secret',
            'role'       => 'tenant',
            'status'     => 'approved',
        ]);

        $unit = Unit::create([
            'type'            => 'Studio',
            'room_no'         => 'R' . random_int(100, 999),
            'room_price'      => 8000,
            'status'          => 'occupied',
            'no_of_occupants' => 1,
        ]);

        return Lease::create(array_merge([
            'user_id'         => $tenant->id,
            'unit_id'         => $unit->id,
            // lea_start_date / lea_end_date are NOT NULL in the schema, so
            // every lease fixture has to set them. Leaving them out is what
            // made the whole suite fail on the first insert.
            'lea_start_date'  => now()->subMonths(2)->toDateString(),
            'lea_end_date'    => now()->addMonths(10)->toDateString(),
            'lea_status'      => 'active',
            'room_no'         => $unit->room_no,
            'rent_balance'    => 0,
            'utility_balance' => 0,
            'deposit_balance' => 0,
            'monthly_rent'    => 8000,
        ], $overrides));
    }

    private function payment(Lease $lease, string $for, float $amount, ?string $type = null): Payment
    {
        return Payment::create([
            'tenant_id'        => $lease->user_id,
            'lease_id'         => $lease->id,
            'pay_date'         => now(),
            'payment_for'      => $for,
            'utility_type'     => $type,
            'pay_amount'       => $amount,
            'pay_method'       => 'Cash',
            'pay_status'       => 'Accepted',
            'reference_number' => 'PAY-TEST-' . uniqid(),
        ]);
    }

    /* ==================================================================
     |  1. EXACT PAYMENT
     * ================================================================== */

    public function test_an_exact_payment_settles_the_bill(): void
    {
        $lease = $this->makeLease();

        $bill = $this->billing->issueBill($lease, [
            'billing_type'  => 'Electricity',
            'billing_month' => 'July 2026',
            'amount'        => 1500.00,
        ]);

        $this->billing->applyAcceptedPayment(
            $this->payment($lease, 'Utilities', 1500.00, 'Electricity')
        );

        $bill->refresh();

        $this->assertEquals(UtilityBillingProof::STATUS_PAID, $bill->status);
        $this->assertEquals(0.00, $bill->outstanding());
        $this->assertEquals(0.00, round((float) $lease->fresh()->utility_balance, 2));
    }

    /* ==================================================================
     |  2. UNDERPAYMENT — the main point of the whole change
     * ================================================================== */

    public function test_an_underpayment_leaves_the_bill_partial_not_unpaid(): void
    {
        $lease = $this->makeLease();

        $bill = $this->billing->issueBill($lease, [
            'billing_type'  => 'Water',
            'billing_month' => 'July 2026',
            'amount'        => 1000.00,
        ]);

        $this->billing->applyAcceptedPayment(
            $this->payment($lease, 'Utilities', 400.00, 'Water')
        );

        $bill->refresh();

        $this->assertEquals(UtilityBillingProof::STATUS_PARTIAL, $bill->status);
        $this->assertEquals(400.00, (float) $bill->amount_paid);
        $this->assertEquals(600.00, $bill->outstanding());

        // And the remainder is carried, not forgotten.
        $this->assertEquals(600.00, round($lease->fresh()->openUtilityTotal(), 2));
    }

    public function test_rent_can_be_paid_partially_without_advancing_the_due_date(): void
    {
        $lease = $this->makeLease([
            'rent_balance' => 8000.00,
            'due_date'     => now()->addDays(5)->toDateString(),
        ]);

        $originalDue = $lease->due_date->toDateString();

        $this->billing->applyAcceptedPayment($this->payment($lease, 'Rent', 3000.00));

        $lease->refresh();

        $this->assertEquals(5000.00, round((float) $lease->rent_balance, 2));

        // A partial payment buys goodwill, not a month.
        $this->assertEquals($originalDue, $lease->due_date->toDateString());
    }

    public function test_clearing_the_rent_balance_advances_the_due_date(): void
    {
        $lease = $this->makeLease([
            'rent_balance' => 8000.00,
            'due_date'     => now()->addDays(5)->toDateString(),
        ]);

        $originalDue = $lease->due_date->copy();

        $this->billing->applyAcceptedPayment($this->payment($lease, 'Rent', 8000.00));

        $lease->refresh();

        $this->assertEquals(0.00, round((float) $lease->rent_balance, 2));
        $this->assertEquals($originalDue->addMonth()->toDateString(), $lease->due_date->toDateString());
    }

    /* ==================================================================
     |  3. OVERPAYMENT — must never vanish
     * ================================================================== */

    public function test_an_overpayment_becomes_credit(): void
    {
        config(['billing.overpayment' => 'credit']);

        $lease  = $this->makeLease();
        $tenant = $lease->tenant;

        $this->billing->issueBill($lease, [
            'billing_type'  => 'Electricity',
            'billing_month' => 'July 2026',
            'amount'        => 987.50,
        ]);

        $result = $this->billing->applyAcceptedPayment(
            $this->payment($lease, 'Utilities', 1000.00, 'Electricity')
        );

        $this->assertEquals(987.50, $result['allocated']);
        $this->assertEquals(12.50, $result['credited']);
        $this->assertEquals(12.50, round((float) $tenant->fresh()->user_credit, 2));
    }

    public function test_an_overpayment_can_be_flagged_for_refund_instead(): void
    {
        config(['billing.overpayment' => 'refund']);

        $lease = $this->makeLease();

        $this->billing->issueBill($lease, [
            'billing_type'  => 'Water',
            'billing_month' => 'July 2026',
            'amount'        => 500.00,
        ]);

        $result = $this->billing->applyAcceptedPayment(
            $this->payment($lease, 'Utilities', 800.00, 'Water')
        );

        $this->assertEquals(300.00, $result['refundable']);
        $this->assertEquals(0.00, $result['credited']);
    }

    /* ==================================================================
     |  4. ZERO-AMOUNT EDGE CASE
     * ================================================================== */

    public function test_a_zero_amount_bill_is_settled_on_creation(): void
    {
        $lease = $this->makeLease();

        $bill = $this->billing->issueBill($lease, [
            'billing_type'  => 'Water',
            'billing_month' => 'July 2026',
            'amount'        => 0.00,
        ]);

        // Not left sitting as a permanently "unpaid" 0.00 nobody can clear.
        $this->assertEquals(UtilityBillingProof::STATUS_PAID, $bill->status);
        $this->assertEquals(0.00, $bill->outstanding());
        $this->assertEquals(0.00, round($lease->utilityArrearsTotal(), 2));
    }

    public function test_a_zero_amount_payment_does_nothing_and_does_not_crash(): void
    {
        $lease = $this->makeLease();

        $this->billing->issueBill($lease, [
            'billing_type'  => 'Electricity',
            'billing_month' => 'July 2026',
            'amount'        => 500.00,
        ]);

        $result = $this->billing->applyAcceptedPayment(
            $this->payment($lease, 'Utilities', 0.00, 'Electricity')
        );

        $this->assertEquals(0.00, $result['allocated']);
        $this->assertEquals(500.00, round($lease->fresh()->openUtilityTotal(), 2));
    }

    public function test_a_negative_bill_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->billing->issueBill($this->makeLease(), [
            'billing_type'  => 'Water',
            'billing_month' => 'July 2026',
            'amount'        => -100.00,
        ]);
    }

    /* ==================================================================
     |  5. BILL ARRIVING AFTER THE DUE DATE (estimates & reconciliation)
     * ================================================================== */

    public function test_an_underestimated_bill_reconciles_with_an_extra_charge(): void
    {
        $lease = $this->makeLease();

        $bill = $this->billing->issueBill($lease, [
            'billing_type'  => 'Electricity',
            'billing_month' => 'July 2026',
            'amount'        => 1200.00,
            'is_estimated'  => true,
        ]);

        $this->assertTrue($bill->isUnreconciledEstimate());

        // v19: an estimate cannot be paid, so nothing is owed on it yet.
        $this->assertFalse($bill->isPayable());
        $this->assertEquals(0.00, $lease->fresh()->openUtilityTotal());
        $this->assertEquals(1200.00, $lease->fresh()->estimatedUtilityTotal());

        $adjustment = $this->billing->reconcileEstimate($bill->fresh(), 1340.00, 'Actual provider bill');

        $bill->refresh();

        $this->assertNotNull($adjustment);
        $this->assertEquals(140.00, (float) $adjustment->amount);

        // v20: the bill itself now carries the actual figure; the estimate the
        // tenant first saw is kept beside it, and the history row is not
        // counted a second time.
        $this->assertEquals(1340.00, (float) $bill->amount);
        $this->assertEquals(1200.00, (float) $bill->estimated_amount);
        $this->assertEquals(0.00, $bill->adjustmentTotal());
        $this->assertEquals(1340.00, $bill->totalDue());
        $this->assertEquals(1340.00, $bill->outstanding());
        $this->assertFalse($bill->isUnreconciledEstimate());

        // Final amount set: now it can be paid.
        $this->assertTrue($bill->isPayable());
        $this->assertEquals(1340.00, $lease->fresh()->openUtilityTotal());
    }

    public function test_an_overestimated_bill_reconciles_down_before_anything_is_paid(): void
    {
        $lease = $this->makeLease();

        $bill = $this->billing->issueBill($lease, [
            'billing_type'  => 'Electricity',
            'billing_month' => 'July 2026',
            'amount'        => 1500.00,
            'is_estimated'  => true,
        ]);

        // Real bill was lower than the estimate.
        $this->billing->reconcileEstimate($bill->fresh(), 1100.00);

        $bill->refresh();

        $this->assertEquals(1100.00, $bill->totalDue());
        $this->assertEquals(1100.00, $bill->outstanding());
        $this->assertTrue($bill->isPayable());
    }

    public function test_money_never_lands_on_an_unfinalised_estimate(): void
    {
        config(['billing.overpayment' => 'credit']);

        $lease  = $this->makeLease();
        $tenant = $lease->tenant;

        $bill = $this->billing->issueBill($lease, [
            'billing_type'  => 'Electricity',
            'billing_month' => 'July 2026',
            'amount'        => 1500.00,
            'is_estimated'  => true,
        ]);

        // e.g. the office records cash before setting the final amount.
        $this->billing->applyAcceptedPayment(
            $this->payment($lease, 'Utilities', 1500.00, 'Electricity')
        );

        $bill->refresh();

        $this->assertEquals(0.00, (float) $bill->amount_paid);
        $this->assertEquals(1500.00, round((float) $tenant->fresh()->user_credit, 2));
        $this->assertFalse($bill->isOverdue());
    }

    public function test_a_bill_issued_after_its_due_date_registers_as_arrears_immediately(): void
    {
        $lease = $this->makeLease();

        $this->billing->issueBill($lease, [
            'billing_type'  => 'Water',
            'billing_month' => 'June 2026',
            'amount'        => 750.00,
            'due_date'      => now()->subDays(10)->toDateString(),
        ]);

        $arrears = $this->billing->arrearsFor($lease->fresh());

        $this->assertEquals(750.00, $arrears['utilities']);
        $this->assertEquals(750.00, $arrears['total']);
        $this->assertNotNull($arrears['oldest_due']);
    }

    /* ==================================================================
     |  6. ALLOCATION ORDER & ARREARS CARRY-FORWARD
     * ================================================================== */

    public function test_a_payment_clears_the_oldest_bill_first(): void
    {
        $lease = $this->makeLease();

        $old = $this->billing->issueBill($lease, [
            'billing_type'  => 'Electricity',
            'billing_month' => 'May 2026',
            'amount'        => 600.00,
            'due_date'      => now()->subDays(60)->toDateString(),
        ]);

        $new = $this->billing->issueBill($lease, [
            'billing_type'  => 'Electricity',
            'billing_month' => 'July 2026',
            'amount'        => 800.00,
            'due_date'      => now()->addDays(10)->toDateString(),
        ]);

        // Enough for the old bill and part of the new one.
        $this->billing->applyAcceptedPayment(
            $this->payment($lease, 'Utilities', 900.00, 'Electricity')
        );

        $this->assertEquals(UtilityBillingProof::STATUS_PAID, $old->fresh()->status);
        $this->assertEquals(UtilityBillingProof::STATUS_PARTIAL, $new->fresh()->status);
        $this->assertEquals(500.00, $new->fresh()->outstanding());
    }

    public function test_a_water_payment_does_not_get_eaten_by_an_electricity_arrear(): void
    {
        $lease = $this->makeLease();

        $elec = $this->billing->issueBill($lease, [
            'billing_type'  => 'Electricity',
            'billing_month' => 'May 2026',
            'amount'        => 900.00,
            'due_date'      => now()->subDays(60)->toDateString(),
        ]);

        $water = $this->billing->issueBill($lease, [
            'billing_type'  => 'Water',
            'billing_month' => 'July 2026',
            'amount'        => 300.00,
        ]);

        $this->billing->applyAcceptedPayment(
            $this->payment($lease, 'Utilities', 300.00, 'Water')
        );

        // The tenant paid their water bill; that is what got paid.
        $this->assertEquals(UtilityBillingProof::STATUS_PAID, $water->fresh()->status);
        $this->assertEquals(900.00, $elec->fresh()->outstanding());
    }

    /* ==================================================================
     |  7. MULTI-TENANT PRORATION WITH ROUNDING REMAINDER
     * ================================================================== */

    public function test_a_shared_bill_splits_with_the_landlord_absorbing_the_remainder(): void
    {
        $leases = collect([$this->makeLease(), $this->makeLease(), $this->makeLease()]);

        $batch = $this->billing->issueSharedBill($leases, [
            'billing_type'     => 'Water',
            'billing_month'    => 'July 2026',
            'total_amount'     => 1000.00,
            'split_rule'       => 'equal',
            'remainder_policy' => 'landlord',
        ]);

        $this->assertCount(3, $batch->bills);
        $this->assertEquals(0.01, (float) $batch->rounding_absorbed);
        $this->assertNull($batch->designated_lease_id);

        foreach ($batch->bills as $bill) {
            $this->assertEquals(333.33, (float) $bill->amount);
        }

        // Tenants are billed 999.99 between them; the landlord eats the centavo.
        $this->assertEquals(999.99, round($batch->bills->sum(fn ($b) => (float) $b->amount), 2));
    }

    public function test_a_shared_bill_can_put_the_remainder_on_one_tenant(): void
    {
        $leases = collect([$this->makeLease(), $this->makeLease(), $this->makeLease()]);

        $batch = $this->billing->issueSharedBill($leases, [
            'billing_type'     => 'Electricity',
            'billing_month'    => 'July 2026',
            'total_amount'     => 1000.00,
            'split_rule'       => 'equal',
            'remainder_policy' => 'designated_tenant',
        ]);

        $this->assertNotNull($batch->designated_lease_id);
        $this->assertEquals(1000.00, round($batch->bills->sum(fn ($b) => (float) $b->amount), 2));

        $designated = $batch->bills->firstWhere('lease_id', $batch->designated_lease_id);
        $this->assertEquals(333.34, (float) $designated->amount);
        $this->assertEquals(0.01, (float) $designated->rounding_adjustment);
    }

    public function test_a_headcount_split_charges_by_occupants(): void
    {
        $a = $this->makeLease();
        $b = $this->makeLease();

        $a->unit->update(['no_of_occupants' => 3]);
        $b->unit->update(['no_of_occupants' => 1]);

        $batch = $this->billing->issueSharedBill(collect([$a->fresh(), $b->fresh()]), [
            'billing_type'     => 'Water',
            'billing_month'    => 'July 2026',
            'total_amount'     => 800.00,
            'split_rule'       => 'headcount',
            'remainder_policy' => 'landlord',
        ]);

        $this->assertEquals(600.00, (float) $batch->bills->firstWhere('lease_id', $a->id)->amount);
        $this->assertEquals(200.00, (float) $batch->bills->firstWhere('lease_id', $b->id)->amount);
    }

    public function test_a_shared_bill_needs_more_than_one_participant(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->billing->issueSharedBill(collect([]), [
            'billing_type'  => 'Water',
            'billing_month' => 'July 2026',
            'total_amount'  => 100.00,
        ]);
    }

    /* ==================================================================
     |  8. REVERSAL — a mis-clicked Accept must be undoable
     * ================================================================== */

    public function test_reversing_a_payment_puts_the_balance_back(): void
    {
        $lease = $this->makeLease();

        $bill = $this->billing->issueBill($lease, [
            'billing_type'  => 'Electricity',
            'billing_month' => 'July 2026',
            'amount'        => 1000.00,
        ]);

        $payment = $this->payment($lease, 'Utilities', 1000.00, 'Electricity');
        $this->billing->applyAcceptedPayment($payment);

        $this->assertEquals(UtilityBillingProof::STATUS_PAID, $bill->fresh()->status);

        $this->billing->reverseAcceptedPayment($payment, 'Recorded in error');

        $bill->refresh();

        $this->assertEquals(UtilityBillingProof::STATUS_UNPAID, $bill->status);
        $this->assertEquals(1000.00, $bill->outstanding());

        // The allocation is kept, marked reversed — the money going in and back
        // out both stay on the record.
        $this->assertEquals(1, $payment->allocations()->count());
        $this->assertNotNull($payment->allocations()->first()->reversed_at);
    }

    public function test_applying_the_same_payment_twice_does_nothing_the_second_time(): void
    {
        $lease = $this->makeLease();

        $bill = $this->billing->issueBill($lease, [
            'billing_type'  => 'Water',
            'billing_month' => 'July 2026',
            'amount'        => 500.00,
        ]);

        $payment = $this->payment($lease, 'Utilities', 500.00, 'Water');

        $this->billing->applyAcceptedPayment($payment);
        $second = $this->billing->applyAcceptedPayment($payment->fresh());

        $this->assertEquals(0.00, $second['allocated']);
        $this->assertEquals(500.00, (float) $bill->fresh()->amount_paid);
    }

    /* ==================================================================
     |  9. PER-CHARGE STATUS — the "rent paid, utilities partial" case
     * ================================================================== */

    public function test_charge_statuses_report_each_type_separately(): void
    {
        $lease = $this->makeLease(['rent_balance' => 8000.00]);

        $this->billing->issueBill($lease, [
            'billing_type'  => 'Electricity',
            'billing_month' => 'July 2026',
            'amount'        => 1000.00,
        ]);

        $this->billing->applyAcceptedPayment($this->payment($lease, 'Rent', 8000.00));
        $this->billing->applyAcceptedPayment($this->payment($lease, 'Utilities', 400.00, 'Electricity'));

        $statuses = $lease->fresh()->chargeStatuses();

        $this->assertEquals('Paid', $statuses['rent']);
        $this->assertEquals('Partial', $statuses['utilities']);
    }

    /* ==================================================================
     |  10. MOVE-OUT SETTLEMENT
     * ================================================================== */

    public function test_move_out_settlement_closes_open_bills_as_paid(): void
    {
        $lease = $this->makeLease();

        $unpaid = $this->billing->issueBill($lease, [
            'billing_type'  => 'Electricity',
            'billing_month' => 'July 2026',
            'amount'        => 750.00,
        ]);

        $partial = $this->billing->issueBill($lease, [
            'billing_type'  => 'Water',
            'billing_month' => 'July 2026',
            'amount'        => 300.00,
        ]);

        $this->billing->applyAcceptedPayment(
            $this->payment($lease, 'Utilities', 100.00, 'Water')
        );

        $result = $this->billing->settleOpenBillsAtMoveOut($lease);

        $this->assertEquals(950.00, $result['settled_total']); // 750 + (300 - 100)
        $this->assertEquals(2, $result['bills_closed']);

        $this->assertEquals(UtilityBillingProof::STATUS_PAID, $unpaid->fresh()->status);
        $this->assertEquals(0.00, $unpaid->fresh()->outstanding());

        $this->assertEquals(UtilityBillingProof::STATUS_PAID, $partial->fresh()->status);
        $this->assertEquals(0.00, $partial->fresh()->outstanding());

        $this->assertEquals(0.00, round((float) $lease->fresh()->utility_balance, 2));
    }

    public function test_move_out_settlement_ignores_a_paid_or_void_bill(): void
    {
        $lease = $this->makeLease();

        $paid = $this->billing->issueBill($lease, [
            'billing_type'  => 'Electricity',
            'billing_month' => 'July 2026',
            'amount'        => 500.00,
        ]);
        $this->billing->applyAcceptedPayment(
            $this->payment($lease, 'Utilities', 500.00, 'Electricity')
        );

        $result = $this->billing->settleOpenBillsAtMoveOut($lease);

        $this->assertEquals(0.00, $result['settled_total']);
        $this->assertEquals(0, $result['bills_closed']);
        $this->assertEquals(UtilityBillingProof::STATUS_PAID, $paid->fresh()->status);
    }

    public function test_move_out_settlement_on_a_lease_with_nothing_open_does_nothing(): void
    {
        $lease = $this->makeLease();

        $result = $this->billing->settleOpenBillsAtMoveOut($lease);

        $this->assertEquals(0.00, $result['settled_total']);
        $this->assertEquals(0, $result['bills_closed']);
    }
}
