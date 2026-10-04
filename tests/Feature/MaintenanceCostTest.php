<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceWorkItem;
use App\Models\Unit;
use App\Models\User;
use App\Services\MaintenanceCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Repair costs and cost responsibility.
 *
 * The behaviour worth protecting: nothing defaults. A repair with costs on it
 * and no responsibility decision sits in 'undetermined' until a person chooses,
 * and charging a tenant requires a written reason. Both exist so a tenant never
 * gets silently billed for something that was never theirs.
 */
class MaintenanceCostTest extends TestCase
{
    use RefreshDatabase;

    private MaintenanceCostService $costs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->costs = app(MaintenanceCostService::class);
    }

    private function makeRequest(): MaintenanceRequest
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
            'rent_balance' => 0, 'utility_balance' => 0, 'deposit_balance' => 0,
        ]);

        return MaintenanceRequest::create([
            'tenant_id'     => $tenant->id,
            'lease_id'      => $lease->id,
            'unit_id'       => $unit->id,
            'unit_type'     => 'Studio',
            'room_no'       => $unit->room_no,
            'description'   => 'Leaking flush valve',
            'urgency'       => 'mid',
            'supposed_date' => now()->addDay()->toDateString(),
            'status'        => 'Pending',
        ]);
    }

    public function test_work_items_roll_up_into_labour_material_and_total(): void
    {
        $request = $this->makeRequest();

        $this->costs->addWorkItem($request, [
            'type' => 'labor', 'description' => 'Plumber', 'quantity' => 2, 'unit' => 'hrs', 'unit_cost' => 450,
        ]);

        $this->costs->addWorkItem($request, [
            'type' => 'material', 'description' => 'Flush valve', 'quantity' => 1, 'unit' => 'pcs', 'unit_cost' => 900,
        ]);

        $request->refresh();

        $this->assertEquals(900.00, (float) $request->labor_cost);
        $this->assertEquals(900.00, (float) $request->material_cost);
        $this->assertEquals(1800.00, (float) $request->total_cost);
    }

    public function test_line_total_is_computed_not_trusted(): void
    {
        $request = $this->makeRequest();

        // Try to set a line total that does not match quantity x unit cost.
        $item = MaintenanceWorkItem::create([
            'maintenance_request_id' => $request->id,
            'type' => 'labor', 'description' => 'Plumber',
            'quantity' => 2, 'unit_cost' => 450, 'line_total' => 99999,
        ]);

        $this->assertEquals(900.00, (float) $item->fresh()->line_total);
    }

    /** Nothing defaults: a costed repair waits for a human decision. */
    public function test_a_costed_repair_starts_undetermined_and_flags_for_a_decision(): void
    {
        $request = $this->makeRequest();

        $this->costs->addWorkItem($request, [
            'type' => 'labor', 'description' => 'Callout', 'quantity' => 1, 'unit_cost' => 800,
        ]);

        $request->refresh();

        $this->assertEquals('undetermined', $request->cost_responsibility);
        $this->assertTrue($request->needsResponsibilityDecision());
        $this->assertEquals(0.00, (float) $request->tenant_charge_amount);
    }

    public function test_landlord_responsibility_charges_the_tenant_nothing(): void
    {
        $request = $this->makeRequest();

        $this->costs->addWorkItem($request, [
            'type' => 'labor', 'description' => 'Callout', 'quantity' => 1, 'unit_cost' => 1000,
        ]);

        $this->costs->setResponsibility($request, 'landlord', null, 'Fair wear and tear');

        $request->refresh();

        $this->assertEquals(0.00, (float) $request->tenant_charge_amount);
        $this->assertEquals(1000.00, $request->landlordShare());
    }

    public function test_a_shared_cost_splits_by_percentage(): void
    {
        $request = $this->makeRequest();

        $this->costs->addWorkItem($request, [
            'type' => 'material', 'description' => 'Door', 'quantity' => 1, 'unit_cost' => 2000,
        ]);

        $this->costs->setResponsibility($request, 'shared', 40, 'Damage worsened by delayed reporting');

        $request->refresh();

        $this->assertEquals(800.00, (float) $request->tenant_charge_amount);
        $this->assertEquals(1200.00, $request->landlordShare());
    }

    /** "The manager ticked tenant" is not an answer to a disputed charge. */
    public function test_charging_a_tenant_requires_a_written_reason(): void
    {
        $request = $this->makeRequest();

        $this->costs->addWorkItem($request, [
            'type' => 'labor', 'description' => 'Callout', 'quantity' => 1, 'unit_cost' => 500,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/reason/i');

        $this->costs->setResponsibility($request, 'tenant', null, null);
    }

    public function test_a_shared_split_must_be_between_1_and_99_percent(): void
    {
        $request = $this->makeRequest();

        $this->expectException(\InvalidArgumentException::class);

        $this->costs->setResponsibility($request, 'shared', 100, 'Reason');
    }

    public function test_charging_creates_a_bill_the_tenant_can_pay_in_parts(): void
    {
        $request = $this->makeRequest();

        $this->costs->addWorkItem($request, [
            'type' => 'material', 'description' => 'Window pane', 'quantity' => 1, 'unit_cost' => 1500,
        ]);

        $this->costs->setResponsibility($request, 'tenant', null, 'Broken by the tenant, per their own report');

        $bill = $this->costs->chargeTenant($request->fresh());

        $request->refresh();

        $this->assertNotNull($bill);
        $this->assertEquals('Repair', $bill->billing_type);
        $this->assertEquals(1500.00, (float) $bill->amount);
        $this->assertTrue($request->isCharged());

        // It lands as a normal bill, so partial payment works like anything else.
        $this->assertEquals(1500.00, round($request->lease->fresh()->openUtilityTotal(), 2));
    }

    public function test_a_repair_cannot_be_charged_twice(): void
    {
        $request = $this->makeRequest();

        $this->costs->addWorkItem($request, [
            'type' => 'labor', 'description' => 'Callout', 'quantity' => 1, 'unit_cost' => 600,
        ]);
        $this->costs->setResponsibility($request, 'tenant', null, 'Tenant damage');
        $this->costs->chargeTenant($request->fresh());

        $this->expectException(\InvalidArgumentException::class);

        $this->costs->chargeTenant($request->fresh());
    }

    public function test_an_undetermined_repair_cannot_be_charged(): void
    {
        $request = $this->makeRequest();

        $this->costs->addWorkItem($request, [
            'type' => 'labor', 'description' => 'Callout', 'quantity' => 1, 'unit_cost' => 600,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/responsible/i');

        $this->costs->chargeTenant($request->fresh());
    }

    /** Costs are locked once billed — the tenant has seen that figure. */
    public function test_responsibility_cannot_change_after_charging(): void
    {
        $request = $this->makeRequest();

        $this->costs->addWorkItem($request, [
            'type' => 'labor', 'description' => 'Callout', 'quantity' => 1, 'unit_cost' => 600,
        ]);
        $this->costs->setResponsibility($request, 'tenant', null, 'Tenant damage');
        $this->costs->chargeTenant($request->fresh());

        $this->expectException(\InvalidArgumentException::class);

        $this->costs->setResponsibility($request->fresh(), 'landlord', null, 'Changed my mind');
    }

    public function test_status_changes_are_written_to_the_history(): void
    {
        $request = $this->makeRequest();

        $this->costs->changeStatus($request, 'In Progress', 'Plumber booked');
        $this->costs->changeStatus($request, 'Completed', 'Valve replaced');

        $request->refresh();

        $this->assertCount(2, $request->statusLogs);
        $this->assertEquals('Pending', $request->statusLogs->first()->from_status);
        $this->assertEquals('Completed', $request->statusLogs->last()->to_status);
        $this->assertNotNull($request->started_at);
        $this->assertNotNull($request->completed_at);
    }

    /* ==================================================================
     |  TENANT VISIBILITY  (added v16)
     |
     |  Every one of these fields was being written and shown to nobody but
     |  the manager. The tenant page at /tenant/requests/{id} is the other
     |  half of that record, so what it may and may not reveal is worth
     |  pinning down.
     * ================================================================== */

    public function test_a_tenant_can_only_open_their_own_request(): void
    {
        $mine     = $this->makeRequest();
        $somebody = $this->makeRequest();

        $this->actingAs($mine->tenant);

        $this->get(route('tenant.requests.show', $mine->id))->assertOk();

        // Not "filtered out of the view" — a 404. Guessing an id must not
        // return somebody else's repair history.
        $this->get(route('tenant.requests.show', $somebody->id))->assertNotFound();
    }

    public function test_the_tenant_sees_scheduling_diagnosis_and_resolution(): void
    {
        $request = $this->makeRequest();

        $request->update([
            'assigned_to'      => 'Boy Plumbing Services',
            'scheduled_date'   => now()->addDays(3)->toDateString(),
            'diagnosis'        => 'Corroded flush valve seat.',
            'resolution_notes' => 'Replaced the flush valve and reseated the tank.',
        ]);

        $this->actingAs($request->tenant)
            ->get(route('tenant.requests.show', $request->id))
            ->assertOk()
            ->assertSee('Boy Plumbing Services')
            ->assertSee('Corroded flush valve seat.')
            ->assertSee('Replaced the flush valve and reseated the tank.');
    }

    public function test_the_tenant_is_not_shown_costs_the_landlord_is_absorbing(): void
    {
        $request = $this->makeRequest();

        $this->costs->addWorkItem($request, [
            'type' => 'labor', 'description' => 'Plumber call-out',
            'quantity' => 2, 'unit_cost' => 450,
        ]);

        $this->costs->setResponsibility($request->fresh(), 'landlord', null, null);

        $response = $this->actingAs($request->tenant)
            ->get(route('tenant.requests.show', $request->id))
            ->assertOk();

        // What a repair cost the landlord is not the tenant's business, and
        // showing it invites an argument about a bill nobody is sending.
        $response->assertDontSee('Plumber call-out');
        $response->assertSee('no charge to you', false);
    }

    public function test_the_tenant_sees_the_breakdown_when_they_are_being_charged(): void
    {
        $request = $this->makeRequest();

        $this->costs->addWorkItem($request, [
            'type' => 'material', 'description' => 'Replacement flush valve',
            'quantity' => 1, 'unit_cost' => 900,
        ]);

        $this->costs->setResponsibility(
            $request->fresh(),
            'tenant',
            null,
            'Damage caused by a guest, per the note on the request.'
        );

        $this->actingAs($request->tenant)
            ->get(route('tenant.requests.show', $request->id))
            ->assertOk()
            // Itemised on purpose: "PHP 900" invites an argument,
            // "1 flush valve @ 900" does not.
            ->assertSee('Replacement flush valve')
            ->assertSee('Damage caused by a guest, per the note on the request.');
    }
}
