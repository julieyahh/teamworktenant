<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\RenovationRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renovation requests are blocked while a deposit is outstanding.
 *
 * The gate is enforced in three places (dropdown, submission, approval) because
 * the form is rendered client-side — filtering the dropdown alone would leave a
 * stale tab able to post a blocked lease id.
 */
class RenovationDepositGateTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithLease(float $depositBalance): array
    {
        $tenant = User::create([
            'first_name' => 'Test', 'last_name' => 'Tenant' . uniqid(),
            'email' => 'tenant' . uniqid() . '@example.test',
            'password' => 'secret', 'role' => 'tenant', 'status' => 'approved',
        ]);

        $unit = Unit::create([
            'type' => 'Studio', 'room_no' => 'R' . random_int(100, 999),
            'room_price' => 8000, 'status' => 'occupied', 'no_of_occupants' => 1,
        ]);

        $lease = Lease::create([
            'user_id' => $tenant->id, 'unit_id' => $unit->id,
            // lea_start_date / lea_end_date are NOT NULL in the schema, so
            // every lease fixture has to set them. Leaving them out is what
            // made the whole suite fail on the first insert.
            'lea_start_date' => now()->subMonths(2)->toDateString(),
            'lea_end_date'   => now()->addMonths(10)->toDateString(),
            'lea_status' => 'active', 'room_no' => $unit->room_no,
            'rent_balance' => 0, 'utility_balance' => 0,
            'deposit_balance' => $depositBalance,
        ]);

        return [$tenant, $lease];
    }

    public function test_the_lease_model_reports_an_unpaid_deposit(): void
    {
        [, $blocked] = $this->makeTenantWithLease(5000.00);
        [, $clear]   = $this->makeTenantWithLease(0.00);

        $this->assertTrue($blocked->hasUnpaidDeposit());
        $this->assertFalse($clear->hasUnpaidDeposit());
    }

    public function test_a_blocked_lease_is_kept_out_of_the_dropdown(): void
    {
        [$tenant, $blocked] = $this->makeTenantWithLease(5000.00);

        $response = $this->actingAs($tenant)->get(route('tenant.renovations'));

        $response->assertOk();
        $response->assertViewHas('activeLeases', fn ($leases) => $leases->isEmpty());
        $response->assertViewHas('blockedLeases', fn ($leases) => $leases->contains('id', $blocked->id));
    }

    /** The important one: a stale form post must still be refused. */
    public function test_submitting_against_a_blocked_lease_is_refused(): void
    {
        [$tenant, $blocked] = $this->makeTenantWithLease(5000.00);

        $response = $this->actingAs($tenant)->post(route('tenant.renovations.store'), [
            'lease_id'        => $blocked->id,
            'title'           => 'Repaint the walls',
            'description'     => 'Neutral colour throughout',
            'renovation_type' => 'Painting',
            'preferred_date'  => now()->addWeek()->toDateString(),
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(0, RenovationRequest::where('lease_id', $blocked->id)->count());
    }

    public function test_a_lease_with_the_deposit_settled_can_submit(): void
    {
        [$tenant, $clear] = $this->makeTenantWithLease(0.00);

        $this->actingAs($tenant)->post(route('tenant.renovations.store'), [
            'lease_id'        => $clear->id,
            'title'           => 'Repaint the walls',
            'description'     => 'Neutral colour throughout',
            'renovation_type' => 'Painting',
            'preferred_date'  => now()->addWeek()->toDateString(),
        ]);

        $this->assertEquals(1, RenovationRequest::where('lease_id', $clear->id)->count());
    }

    /**
     * A request can sit in the queue while the deposit position changes, so
     * approval is checked against the balance as it stands now.
     */
    public function test_approval_is_blocked_if_the_deposit_lapses_after_submission(): void
    {
        [$tenant, $lease] = $this->makeTenantWithLease(0.00);

        $renovation = RenovationRequest::create([
            'tenant_id' => $tenant->id, 'lease_id' => $lease->id, 'unit_id' => $lease->unit_id,
            'room_no' => $lease->room_no, 'title' => 'Repaint',
            'description' => 'Neutral colour', 'renovation_type' => 'Painting',
            'preferred_date' => now()->addWeek()->toDateString(),
            'status' => RenovationRequest::STATUS_PENDING,
        ]);

        // Deposit falls back into arrears after submission.
        $lease->update(['deposit_balance' => 3000.00]);

        $manager = User::create([
            'first_name' => 'Test', 'last_name' => 'Manager' . uniqid(),
            'email' => 'mgr' . uniqid() . '@example.test',
            'password' => 'secret', 'role' => 'manager', 'status' => 'approved',
        ]);

        $this->actingAs($manager)->post(route('manager.renovations.decide', $renovation->id), [
            'decision' => 'approve',
        ]);

        $this->assertEquals(RenovationRequest::STATUS_PENDING, $renovation->fresh()->status);
    }

    /** Denying is always allowed — the gate is on approval only. */
    public function test_denying_is_allowed_even_with_an_unpaid_deposit(): void
    {
        [$tenant, $lease] = $this->makeTenantWithLease(3000.00);

        $renovation = RenovationRequest::create([
            'tenant_id' => $tenant->id, 'lease_id' => $lease->id, 'unit_id' => $lease->unit_id,
            'room_no' => $lease->room_no, 'title' => 'Repaint',
            'description' => 'Neutral colour', 'renovation_type' => 'Painting',
            'preferred_date' => now()->addWeek()->toDateString(),
            'status' => RenovationRequest::STATUS_PENDING,
        ]);

        $manager = User::create([
            'first_name' => 'Test', 'last_name' => 'Manager' . uniqid(),
            'email' => 'mgr' . uniqid() . '@example.test',
            'password' => 'secret', 'role' => 'manager', 'status' => 'approved',
        ]);

        $this->actingAs($manager)->post(route('manager.renovations.decide', $renovation->id), [
            'decision' => 'deny', 'manager_remarks' => 'Not while the deposit is outstanding',
        ]);

        $this->assertEquals(RenovationRequest::STATUS_DENIED, $renovation->fresh()->status);
    }
}
