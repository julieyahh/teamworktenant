<?php

namespace Tests\Feature;

use App\Models\BillAdjustment;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PaymentDemand;
use App\Models\Unit;
use App\Models\User;
use App\Models\UtilityBillingProof;
use App\Services\BillingService;
use App\Services\MaintenanceCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * v20:
 *   1. Setting the actual amount rewrites the bill (amount, and kWh for
 *      metered electricity); the estimate is kept, nothing is double-counted.
 *   2. Voiding a bill updates everything tied to it.
 *   3. Maintenance request page forms actually submit (double-submit guard
 *      was loaded twice and cancelled every save).
 */
class V20ChangesTest extends TestCase
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

    private function tenant(): User
    {
        return User::create([
            'first_name' => 'Juan', 'last_name' => 'Tenant' . uniqid(),
            'email' => 'juan' . uniqid() . '@example.test',
            'password' => 'Secret#123', 'role' => 'tenant', 'status' => 'approved',
        ]);
    }

    private function manager(): User
    {
        return User::create([
            'first_name' => 'Mgr', 'last_name' => 'One',
            'email' => 'mgr' . uniqid() . '@example.test',
            'password' => 'Secret#123', 'role' => 'manager', 'status' => 'approved',
        ]);
    }

    private function unit(string $type = '1-Bedroom'): Unit
    {
        return Unit::create([
            'type' => $type, 'room_no' => 'R' . random_int(1000, 9999),
            'room_price' => 6000, 'status' => 'occupied', 'no_of_occupants' => 1,
        ]);
    }

    private function lease(?User $tenant = null, ?Unit $unit = null): Lease
    {
        $tenant ??= $this->tenant();
        $unit   ??= $this->unit();

        return Lease::create([
            'user_id' => $tenant->id, 'unit_id' => $unit->id,
            'lea_start_date' => now()->subMonths(3)->toDateString(),
            'lea_end_date'   => now()->addMonths(9)->toDateString(),
            'lea_status' => 'active', 'room_no' => $unit->room_no,
            'rent_balance' => 0, 'utility_balance' => 0, 'deposit_balance' => 0,
            'penalty_fee' => 0, 'monthly_rent' => 6000,
        ]);
    }

    private function bill(Lease $lease, array $o = []): UtilityBillingProof
    {
        return $this->billing->issueBill($lease, array_merge([
            'billing_type'  => 'Water',
            'billing_month' => 'October 2026',
            'amount'        => 500.00,
        ], $o));
    }

    private function meteredEstimate(Lease $lease, float $kwh = 100, float $amount = 1800): UtilityBillingProof
    {
        return $this->bill($lease, [
            'billing_type' => 'Electricity',
            'amount'       => $amount,
            'kwh_reading'  => $kwh,
            'kwh_rate'     => 18,
            'is_estimated' => true,
            'notes'        => "Electricity: {$kwh} kWh x PHP 18.00 = PHP " . number_format($kwh * 18, 2) . '.',
        ]);
    }

    private function chargedRepair(): array
    {
        $lease = $this->lease();
        $costs = app(MaintenanceCostService::class);

        $request = MaintenanceRequest::create([
            'tenant_id' => $lease->user_id, 'lease_id' => $lease->id, 'unit_id' => $lease->unit_id,
            'unit_type' => '1-Bedroom', 'room_no' => $lease->room_no,
            'description' => 'Broken window', 'urgency' => 'mid',
            'supposed_date' => now()->addDay()->toDateString(), 'status' => 'Completed',
        ]);

        $costs->addWorkItem($request, ['type' => 'material', 'description' => 'Pane', 'quantity' => 1, 'unit_cost' => 1500]);
        $costs->setResponsibility($request->fresh(), 'tenant', null, 'Broken by a guest');
        $bill = $costs->chargeTenant($request->fresh());

        return [$request->fresh(), $bill->fresh(), $lease];
    }

    /* ==================================================================
     |  1. ACTUAL AMOUNT REWRITES THE BILL
     * ================================================================== */

    public function test_actual_amount_rewrites_the_bill_and_keeps_the_estimate(): void
    {
        $lease = $this->lease();
        $bill  = $this->bill($lease, ['amount' => 1200, 'is_estimated' => true]);

        $this->actingAs($this->manager())
            ->post(route('manager.bills.reconcile', $bill->id), ['actual_amount' => 1340])
            ->assertSessionHas('success');

        $bill->refresh();

        $this->assertEquals(1340.00, (float) $bill->amount);
        $this->assertEquals(1200.00, (float) $bill->estimated_amount);
        $this->assertEquals(1340.00, $bill->totalDue());     // not 1480
        $this->assertEquals(1340.00, $bill->outstanding());
        $this->assertTrue($bill->isPayable());
        $this->assertEquals(1340.00, (float) $lease->fresh()->utility_balance);

        // The change is on file as history.
        $row = $bill->adjustments()->first();
        $this->assertSame(BillAdjustment::TYPE_RECONCILIATION, $row->type);
        $this->assertEquals(140.00, (float) $row->amount);
    }

    public function test_manual_corrections_still_stack_on_the_actual_amount(): void
    {
        $bill = $this->bill($this->lease(), ['amount' => 1000, 'is_estimated' => true]);

        $this->billing->reconcileEstimate($bill->fresh(), 1100);

        $bill->refresh()->adjustments()->create(['type' => 'goodwill', 'amount' => -50, 'reason' => 'Late reading']);

        $this->assertEquals(1100.00, (float) $bill->fresh()->amount);
        $this->assertEquals(1050.00, $bill->fresh()->totalDue());
    }

    public function test_metered_electricity_rewrites_kwh_and_amount_computed_on_the_server(): void
    {
        $bill = $this->meteredEstimate($this->lease(), 100, 1800);

        // A tampered hidden amount must be ignored.
        $this->actingAs($this->manager())
            ->post(route('manager.bills.reconcile', $bill->id), ['actual_kwh' => 120, 'actual_amount' => 1])
            ->assertSessionHas('success');

        $bill->refresh();

        $this->assertEquals(120.00, (float) $bill->kwh_reading);
        $this->assertEquals(2160.00, (float) $bill->amount);          // 120 x 18
        $this->assertEquals(100.00, (float) $bill->estimated_kwh);
        $this->assertEquals(1800.00, (float) $bill->estimated_amount);
        $this->assertStringContainsString('Electricity (final): 120 kWh', $bill->notes);
        $this->assertStringNotContainsString('Electricity: 100', $bill->notes);
    }

    public function test_a_metered_bill_needs_a_reading_not_just_an_amount(): void
    {
        $bill = $this->meteredEstimate($this->lease());

        $this->actingAs($this->manager())
            ->post(route('manager.bills.reconcile', $bill->id), ['actual_amount' => 2000])
            ->assertSessionHasErrors('actual_kwh');

        $this->assertTrue($bill->fresh()->isUnreconciledEstimate());
    }

    public function test_bed_spacer_final_reading_is_split_the_same_way_as_the_estimate(): void
    {
        $unit = $this->unit('Bed-Spacer');
        $a    = $this->lease(null, $unit);
        $this->lease(null, $unit);

        // Estimate: 100 kWh x 18 = 1800 for the room, 2 ways = 900.
        $bill = $this->meteredEstimate($a, 100, 900);

        // Someone moves in before the real reading arrives.
        $this->lease(null, $unit);

        $this->billing->reconcileEstimate($bill->fresh(), $this->billing->electricityAmountFor($bill->fresh(), 120), null, null, 120);

        $this->assertEquals(1080.00, (float) $bill->fresh()->amount);   // 2160 / 2, not / 3
    }

    public function test_a_second_correction_keeps_the_first_estimate(): void
    {
        $bill = $this->bill($this->lease(), ['amount' => 1000, 'is_estimated' => true]);

        $this->billing->reconcileEstimate($bill->fresh(), 1100);
        $this->billing->reconcileEstimate($bill->fresh(), 1150);

        $bill->refresh();
        $this->assertEquals(1150.00, (float) $bill->amount);
        $this->assertEquals(1000.00, (float) $bill->estimated_amount);
        $this->assertEquals(1150.00, $bill->totalDue());
    }

    /* ==================================================================
     |  2. VOID CASCADE
     * ================================================================== */

    public function test_void_resyncs_the_lease_and_tells_the_tenant(): void
    {
        $lease = $this->lease();
        $bill  = $this->bill($lease);
        $this->billing->syncLeaseUtilityBalance($lease);
        $this->assertEquals(500.00, (float) $lease->fresh()->utility_balance);

        $this->actingAs($this->manager())
            ->post(route('manager.bills.void', $bill->id), ['void_reason' => 'Wrong room'])
            ->assertSessionHas('success');

        $this->assertTrue($bill->fresh()->isVoid());
        $this->assertEquals(0.00, (float) $lease->fresh()->utility_balance);
        $this->assertTrue(Notification::where('user_id', $lease->user_id)->where('title', 'Bill Cancelled')->exists());
    }

    public function test_void_is_refused_when_money_is_on_the_bill(): void
    {
        $lease = $this->lease();
        $bill  = $this->bill($lease);

        $payment = Payment::create([
            'tenant_id' => $lease->user_id, 'lease_id' => $lease->id, 'pay_date' => now(),
            'payment_for' => 'Utilities', 'utility_type' => 'Water', 'pay_amount' => 200,
            'pay_method' => 'Cash', 'pay_status' => 'Accepted', 'reference_number' => 'T-' . uniqid(),
        ]);
        $this->billing->applyAcceptedPayment($payment);

        $this->actingAs($this->manager())
            ->post(route('manager.bills.void', $bill->id), ['void_reason' => 'x'])
            ->assertSessionHas('error');

        $this->assertFalse($bill->fresh()->isVoid());
    }

    public function test_voiding_a_repair_bill_uncharges_the_maintenance_request(): void
    {
        [$request, $bill] = $this->chargedRepair();

        $this->assertSame($request->id, $bill->maintenance_request_id);
        $this->assertTrue($request->isCharged());

        $this->billing->voidBill($bill, 'Charged the wrong tenant', $this->manager());

        $request->refresh();
        $this->assertFalse($request->isCharged());
        $this->assertEquals(1500.00, (float) $request->tenant_charge_amount);   // still owed, not yet billed
        $this->assertTrue($request->isBillableToTenant());
        $this->assertTrue($request->statusLogs()->where('notes', 'like', '%reversed%')->exists());

        // And it can be charged again.
        $again = app(MaintenanceCostService::class)->chargeTenant($request);
        $this->assertNotNull($again);
    }

    public function test_a_pre_v20_repair_bill_is_found_by_its_note(): void
    {
        [$request, $bill] = $this->chargedRepair();
        $bill->update(['maintenance_request_id' => null]);   // as written before v20

        $this->billing->voidBill($bill->fresh(), 'Mistake');

        $this->assertFalse($request->fresh()->isCharged());
    }

    public function test_voiding_a_shared_bill_can_void_every_unpaid_share(): void
    {
        $leases = collect([$this->lease(), $this->lease(), $this->lease()]);

        $batch = $this->billing->issueSharedBill($leases, [
            'billing_type' => 'Water', 'billing_month' => 'October 2026',
            'total_amount' => 900, 'split_rule' => 'equal', 'remainder_policy' => 'landlord',
        ]);
        $bills = $batch->bills()->orderBy('id')->get();

        // One share already has money on it.
        $paidLease = $bills[2]->lease;
        $payment = Payment::create([
            'tenant_id' => $paidLease->user_id, 'lease_id' => $paidLease->id, 'pay_date' => now(),
            'payment_for' => 'Utilities', 'utility_type' => 'Water', 'pay_amount' => 100,
            'pay_method' => 'Cash', 'pay_status' => 'Accepted', 'reference_number' => 'T-' . uniqid(),
        ]);
        $this->billing->applyAcceptedPayment($payment);

        $result = $this->billing->voidBill($bills[0]->fresh(), 'Wrong provider total', null, true);

        $this->assertCount(2, $result['voided']);
        $this->assertSame([$bills[2]->id], $result['skipped_paid']);
        $this->assertTrue($bills[1]->fresh()->isVoid());
        $this->assertFalse($bills[2]->fresh()->isVoid());
    }

    public function test_voiding_one_share_only_leaves_the_others(): void
    {
        $batch = $this->billing->issueSharedBill(collect([$this->lease(), $this->lease()]), [
            'billing_type' => 'Water', 'billing_month' => 'October 2026',
            'total_amount' => 600, 'split_rule' => 'equal', 'remainder_policy' => 'landlord',
        ]);
        [$first, $second] = $batch->bills()->orderBy('id')->get();

        $this->billing->voidBill($first, 'Tenant had moved out');

        $this->assertTrue($first->fresh()->isVoid());
        $this->assertFalse($second->fresh()->isVoid());
    }

    public function test_open_demand_notices_are_flagged_when_a_bill_is_voided(): void
    {
        $lease = $this->lease();
        $bill  = $this->bill($lease);

        $demand = PaymentDemand::create([
            'tenant_id' => $lease->user_id, 'lease_id' => $lease->id, 'type' => 'reminder',
            'amount_claimed' => 500, 'utility_component' => 500,
            'issued_at' => now()->addMinute(), 'grace_period_days' => 5,
            'comply_by' => now()->addDays(5)->toDateString(), 'outcome' => PaymentDemand::OUTCOME_OPEN,
        ]);

        $this->billing->voidBill($bill, 'Duplicate');

        $this->assertStringContainsString('Bill #' . $bill->id, (string) $demand->fresh()->notes);
        $this->assertSame(PaymentDemand::OUTCOME_OPEN, $demand->fresh()->outcome);   // flagged, not closed
    }

    /* ==================================================================
     |  3. MAINTENANCE REQUEST PAGE
     * ================================================================== */

    public function test_the_request_page_loads_the_submit_guard_once(): void
    {
        [$request] = $this->chargedRepair();

        $html = $this->actingAs($this->manager())
            ->get(route('manager.requests.show', $request->id))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'window.__prSingleSubmit = true'));
    }

    public function test_all_four_request_forms_save(): void
    {
        $lease   = $this->lease();
        $manager = $this->manager();
        $request = MaintenanceRequest::create([
            'tenant_id' => $lease->user_id, 'lease_id' => $lease->id, 'unit_id' => $lease->unit_id,
            'description' => 'Leak', 'urgency' => 'low',
            'supposed_date' => now()->addDay()->toDateString(), 'status' => 'Pending',
        ]);

        $this->actingAs($manager)->patch(route('manager.requests.updateStatus', $request->id), ['status' => 'In Progress'])
            ->assertSessionHas('success');
        $this->actingAs($manager)->post(route('manager.requests.details', $request->id), [
            'assigned_to' => 'Mang Ben', 'scheduled_date' => now()->addDays(2)->toDateString(),
            'diagnosis' => 'Worn seal', 'resolution_notes' => '',
        ])->assertSessionHas('success');
        $this->actingAs($manager)->post(route('manager.requests.workItems.store', $request->id), [
            'type' => 'labor', 'description' => 'Replace seal', 'quantity' => 2, 'unit_cost' => 250,
        ])->assertSessionHas('success');
        $this->actingAs($manager)->post(route('manager.requests.responsibility', $request->id), [
            'cost_responsibility' => 'landlord',
        ])->assertSessionHas('success');

        $request->refresh();
        $this->assertSame('In Progress', $request->status);
        $this->assertSame('Mang Ben', $request->assigned_to);
        $this->assertSame('Worn seal', $request->diagnosis);
        $this->assertEquals(500.00, (float) $request->total_cost);
        $this->assertSame('landlord', $request->cost_responsibility);
    }
}
