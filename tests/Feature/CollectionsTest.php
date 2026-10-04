<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\PaymentDemand;
use App\Models\Unit;
use App\Models\User;
use App\Services\BillingService;
use App\Services\CollectionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The collections workflow.
 *
 * The tests that matter most here are the negative ones: that an undelivered
 * notice never starts the clock, that a rounding centavo never puts anyone into
 * collections, and that turning on self-help remedies throws rather than
 * enabling anything.
 */
class CollectionsTest extends TestCase
{
    use RefreshDatabase;

    private CollectionsService $collections;
    private BillingService $billing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collections = app(CollectionsService::class);
        $this->billing     = app(BillingService::class);
    }

    private function makeLease(): Lease
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
            'type' => 'Studio', 'room_no' => 'R' . random_int(100, 999),
            'room_price' => 8000, 'status' => 'occupied', 'no_of_occupants' => 1,
        ]);

        return Lease::create([
            'user_id' => $tenant->id, 'unit_id' => $unit->id,
            // lea_start_date / lea_end_date are NOT NULL in the schema, so
            // every lease fixture has to set them. Leaving them out is what
            // made the whole suite fail on the first insert.
            'lea_start_date' => now()->subMonths(2)->toDateString(),
            'lea_end_date'   => now()->addMonths(10)->toDateString(),
            'lea_status' => 'active', 'room_no' => $unit->room_no,
            'rent_balance' => 0, 'utility_balance' => 0, 'deposit_balance' => 0,
        ]);
    }

    private function manager(): User
    {
        return User::create([
            'first_name' => 'Test', 'last_name' => 'Manager' . uniqid(),
            'email' => 'mgr' . uniqid() . '@example.test',
            'password' => 'secret', 'role' => 'manager', 'status' => 'approved',
        ]);
    }

    public function test_a_lease_with_nothing_owed_is_current(): void
    {
        $assessment = $this->collections->assess($this->makeLease());

        $this->assertEquals('current', $assessment['stage']);
        $this->assertFalse($assessment['actionable']);
    }

    /**
     * A stray centavo from a rounding remainder must never put a tenant into
     * collections. This is the guard that makes the landlord-absorbs policy
     * safe to run alongside the escalation logic.
     */
    public function test_a_trivial_balance_stays_below_the_actionable_floor(): void
    {
        config(['collections.minimum_actionable_arrears' => 100.00]);

        $lease = $this->makeLease();

        $this->billing->issueBill($lease, [
            'billing_type'  => 'Water',
            'billing_month' => 'June 2026',
            'amount'        => 0.01,
            'due_date'      => now()->subDays(40)->toDateString(),
        ]);

        $this->assertEquals('current', $this->collections->assess($lease->fresh())['stage']);
    }

    public function test_a_recently_overdue_bill_is_merely_overdue(): void
    {
        $lease = $this->makeLease();

        $this->billing->issueBill($lease, [
            'billing_type'  => 'Electricity',
            'billing_month' => 'July 2026',
            'amount'        => 2000.00,
            'due_date'      => now()->subDays(3)->toDateString(),
        ]);

        $assessment = $this->collections->assess($lease->fresh());

        $this->assertEquals('overdue', $assessment['stage']);
        $this->assertEquals(2000.00, $assessment['arrears']['total']);
    }

    public function test_a_long_overdue_bill_suggests_a_formal_demand(): void
    {
        $lease = $this->makeLease();

        $this->billing->issueBill($lease, [
            'billing_type'  => 'Electricity',
            'billing_month' => 'June 2026',
            'amount'        => 3000.00,
            'due_date'      => now()->subDays(30)->toDateString(),
        ]);

        $this->assertEquals('demand_due', $this->collections->assess($lease->fresh())['stage']);
    }

    /**
     * The single most important test in this file.
     *
     * A demand that was drafted but never served proves nothing. If the system
     * ever counts its grace period as expired, it would push a tenant into a
     * later stage on the strength of a document they may never have received.
     */
    public function test_an_undelivered_demand_never_expires(): void
    {
        $lease = $this->makeLease();

        $this->billing->issueBill($lease, [
            'billing_type'  => 'Water',
            'billing_month' => 'May 2026',
            'amount'        => 5000.00,
            'due_date'      => now()->subDays(90)->toDateString(),
        ]);

        $demand = $this->collections->issueDemand($lease, 'formal_demand', [], $this->manager());

        // Backdate it well past any grace period, but never record service.
        $demand->update([
            'issued_at' => now()->subDays(60),
            'comply_by' => now()->subDays(45)->toDateString(),
        ]);

        $this->assertFalse($demand->fresh()->graceHasExpired());

        $assessment = $this->collections->assess($lease->fresh());

        $this->assertNotEquals('grace_expired', $assessment['stage']);
        $this->assertNotEquals('eligible_for_formal_action', $assessment['stage']);
        $this->assertStringContainsString('not been recorded as delivered', $assessment['next_step']);
    }

    public function test_recording_service_starts_the_clock_from_the_delivery_date(): void
    {
        $lease  = $this->makeLease();
        $demand = $this->collections->issueDemand($lease, 'formal_demand', [], $this->manager());

        $deliveredAt = now()->subDays(2);

        $this->collections->recordDelivery($demand, [
            'delivery_channel'   => 'registered_mail',
            'delivery_reference' => 'RR-12345',
            'delivered_at'       => $deliveredAt,
        ]);

        $demand->refresh();

        $grace = (int) config('collections.grace_periods.formal_demand');

        // The period runs from service, not from when it was drafted.
        $this->assertEquals(
            $deliveredAt->copy()->addDays($grace)->toDateString(),
            $demand->comply_by->toDateString()
        );
        $this->assertTrue($demand->isDelivered());
    }

    public function test_a_served_demand_past_its_period_reaches_grace_expired(): void
    {
        $lease = $this->makeLease();

        $this->billing->issueBill($lease, [
            'billing_type'  => 'Electricity',
            'billing_month' => 'May 2026',
            'amount'        => 4000.00,
            'due_date'      => now()->subDays(60)->toDateString(),
        ]);

        $demand = $this->collections->issueDemand($lease, 'formal_demand', [], $this->manager());

        $demand->update([
            'delivered_at' => now()->subDays(40),
            'comply_by'    => now()->subDays(25)->toDateString(),
        ]);

        $this->assertEquals('grace_expired', $this->collections->assess($lease->fresh())['stage']);
    }

    /** The furthest the system goes is "refer to counsel". There is no next stage. */
    public function test_an_expired_notice_to_vacate_stops_at_refer_to_counsel(): void
    {
        $lease = $this->makeLease();

        $this->billing->issueBill($lease, [
            'billing_type'  => 'Electricity',
            'billing_month' => 'April 2026',
            'amount'        => 9000.00,
            'due_date'      => now()->subDays(120)->toDateString(),
        ]);

        $demand = $this->collections->issueDemand($lease, 'notice_to_vacate', [], $this->manager());

        $demand->update([
            'delivered_at' => now()->subDays(40),
            'comply_by'    => now()->subDays(20)->toDateString(),
        ]);

        $assessment = $this->collections->assess($lease->fresh());

        $this->assertEquals('eligible_for_formal_action', $assessment['stage']);
        $this->assertStringContainsString('counsel', $assessment['next_step']);

        // The lease is untouched — nothing was terminated automatically.
        $this->assertEquals('active', $lease->fresh()->lea_status);
    }

    public function test_amounts_are_frozen_on_the_demand_at_issue(): void
    {
        $lease = $this->makeLease();

        $bill = $this->billing->issueBill($lease, [
            'billing_type'  => 'Water',
            'billing_month' => 'June 2026',
            'amount'        => 2500.00,
            'due_date'      => now()->subDays(30)->toDateString(),
        ]);

        $demand = $this->collections->issueDemand($lease, 'formal_demand', [], $this->manager());

        $this->assertEquals(2500.00, (float) $demand->amount_claimed);

        // The tenant pays part of it. The demand must still read 2,500.
        $bill->update(['amount_paid' => 1000.00, 'status' => 'partial']);

        $this->assertEquals(2500.00, (float) $demand->fresh()->amount_claimed);
    }

    public function test_issuing_a_new_demand_supersedes_the_previous_open_one(): void
    {
        $lease   = $this->makeLease();
        $manager = $this->manager();

        $first = $this->collections->issueDemand($lease, 'reminder', [], $manager);
        $this->collections->issueDemand($lease, 'formal_demand', [], $manager);

        $this->assertEquals(PaymentDemand::OUTCOME_SUPERSEDED, $first->fresh()->outcome);
        $this->assertEquals(1, PaymentDemand::where('lease_id', $lease->id)->open()->count());
    }

    /** An unreconciled estimate should stop a manager demanding payment on it. */
    public function test_unreconciled_estimates_are_flagged_as_a_blocker(): void
    {
        $lease = $this->makeLease();

        // A real overdue bill puts the lease into collections...
        $this->billing->issueBill($lease, [
            'billing_type'  => 'Water',
            'billing_month' => 'May 2026',
            'amount'        => 500.00,
            'due_date'      => now()->subDays(30)->toDateString(),
        ]);

        // ...and an estimate on the same lease is flagged, never claimed.
        $this->billing->issueBill($lease, [
            'billing_type'  => 'Electricity',
            'billing_month' => 'June 2026',
            'amount'        => 3000.00,
            'due_date'      => now()->subDays(30)->toDateString(),
            'is_estimated'  => true,
        ]);

        $assessment = $this->collections->assess($lease->fresh());

        $this->assertEquals(500.00, $assessment['arrears']['utilities']);
        $this->assertNotEmpty($assessment['blockers']);
        $this->assertStringContainsString('estimates', implode(' ', $assessment['blockers']));
    }

    public function test_an_estimate_alone_is_never_arrears(): void
    {
        $lease = $this->makeLease();

        $this->billing->issueBill($lease, [
            'billing_type'  => 'Electricity',
            'billing_month' => 'June 2026',
            'amount'        => 3000.00,
            'due_date'      => now()->subDays(30)->toDateString(),
            'is_estimated'  => true,
        ]);

        $assessment = $this->collections->assess($lease->fresh());

        $this->assertEquals('current', $assessment['stage']);
        $this->assertEquals(0.00, $assessment['arrears']['total']);
    }

    /**
     * The hard stop. This config flag exists precisely so that nobody can
     * quietly switch on lockouts or utility cut-offs — there is no code path
     * that honours it, and the guard throws first.
     */
    public function test_enabling_self_help_remedies_throws(): void
    {
        config(['collections.allow_self_help_remedies' => true]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/self-help/i');

        $this->collections->assess($this->makeLease());
    }
}
