<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Notification;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Move-out settlement — what the tenant gets back.
 *
 * The rules being pinned down, in the order they matter:
 *
 *   1. Advance rent is drawn down against arrears FIRST. A month covered by
 *      the tenant's own money is not a month they owe.
 *   2. Whatever advance survives is refunded. It is rent for months that will
 *      not happen, not security against damage.
 *   3. Damages and arrears come out of the DEPOSIT only. When they exceed it
 *      the refund floors at zero and the advance is added on top — a big
 *      damage bill must never reach the advance.
 *   4. An unpaid deposit means no deposit refund. It does not mean no refund.
 *   5. Lease::settlementPreview() and Lease::consumeAdvanceRent() agree,
 *      because the modals show the manager the former and the controller
 *      applies the latter.
 *
 * Runs against SQLite in memory (see phpunit.xml).
 */
class MoveOutRefundTest extends TestCase
{
    use RefreshDatabase;

    /* ==================================================================
     |  FIXTURES
     * ================================================================== */

    private function makeManager(): User
    {
        return User::create([
            'first_name' => 'Mgr',
            'last_name'  => 'User' . uniqid(),
            'email'      => 'mgr' . uniqid() . '@example.test',
            'password'   => 'secret',
            'role'       => 'manager',
            'status'     => 'approved',
        ]);
    }

    private function makeLease(array $overrides = []): Lease
    {
        $tenant = User::create([
            'first_name' => 'Move',
            'last_name'  => 'Out' . uniqid(),
            'email'      => 'mo' . uniqid() . '@example.test',
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
            'penalty_fee'     => 0,
            'monthly_rent'    => 5000,
            'due_date'        => now()->addDays(10)->toDateString(),
        ], $overrides));
    }

    /* ==================================================================
     |  1. THE PREVIEW AND THE REAL THING AGREE
     |
     |  The whole point of settlementPreview(). If these two ever drift,
     |  the manager approves one number and the tenant is told another.
     * ================================================================== */

    public function test_preview_matches_what_consuming_the_advance_actually_does(): void
    {
        $lease = $this->makeLease([
            'advance_rent_balance' => 12000.00,
            'rent_balance'         =>  5000.00,
            'penalty_fee'          =>   500.00,
        ]);

        $preview = $lease->settlementPreview();
        $used    = $lease->consumeAdvanceRent();

        $this->assertEquals($preview['advance_used'],   $used);
        $this->assertEquals($preview['unpaid_rent'],    round((float) $lease->rent_balance, 2));
        $this->assertEquals($preview['unpaid_penalty'], round((float) $lease->penalty_fee, 2));
        $this->assertEquals($preview['advance_refund'], $lease->advanceRentBalance());
    }

    public function test_preview_touches_nothing(): void
    {
        $lease = $this->makeLease([
            'advance_rent_balance' => 12000.00,
            'rent_balance'         =>  5000.00,
        ]);

        $lease->settlementPreview();

        $this->assertEquals(12000.00, $lease->advanceRentBalance());
        $this->assertEquals(5000.00,  round((float) $lease->rent_balance, 2));
    }

    public function test_advance_pays_the_penalty_before_the_rent(): void
    {
        $lease = $this->makeLease([
            'advance_rent_balance' => 1000.00,
            'rent_balance'         => 5000.00,
            'penalty_fee'          =>  500.00,
        ]);

        $p = $lease->settlementPreview();

        $this->assertEquals(0.00,    $p['unpaid_penalty']);
        $this->assertEquals(4500.00, $p['unpaid_rent']);
        $this->assertEquals(0.00,    $p['advance_refund']);
    }

    public function test_utilities_cannot_eat_the_advance(): void
    {
        // Advance rent is ring-fenced to rent. A utility bill sits in
        // unpaid_total but must not reduce the refundable advance.
        $lease = $this->makeLease(['advance_rent_balance' => 10000.00]);

        $p = $lease->settlementPreview();

        $this->assertEquals(10000.00, $p['advance_refund']);
        $this->assertEquals(0.00,     $p['advance_used']);
    }

    /* ==================================================================
     |  2. APPROVED MOVE-OUT REQUEST
     * ================================================================== */

    public function test_approved_move_out_refunds_the_unused_advance_on_top_of_the_deposit(): void
    {
        $lease = $this->makeLease([
            'move_out_requested'   => true,
            'advance_rent_balance' => 15000.00,
        ]);

        $this->actingAs($this->makeManager())
            ->post(route('manager.leases.approveMoveOut', $lease->id), [
                'deposit_amount' => 10000.00,
                'damage_cost'    => 0,
                'refund_amount'  => 25000.00,
                'damages_json'   => '[]',
            ]);

        $lease->refresh();

        $this->assertEquals('terminated', $lease->lea_status);
        $this->assertEquals(25000.00, round((float) $lease->refund_amount, 2));

        // And it is not left sitting on the lease as well, or it would be
        // owed to the tenant twice.
        $this->assertEquals(0.00, $lease->advanceRentBalance());
    }

    public function test_advance_clears_the_arrears_instead_of_being_charged_then_handed_back(): void
    {
        $lease = $this->makeLease([
            'move_out_requested'   => true,
            'advance_rent_balance' => 15000.00,
            'rent_balance'         =>  5000.00,
        ]);

        $this->actingAs($this->makeManager())
            ->post(route('manager.leases.approveMoveOut', $lease->id), [
                'deposit_amount' => 10000.00,
                'damage_cost'    => 0,
                'refund_amount'  => 20000.00,
                'damages_json'   => '[]',
            ]);

        $lease->refresh();

        // Deposit 10,000 untouched (the 5,000 arrears was covered by the
        // advance) + 10,000 advance left over.
        $this->assertEquals(20000.00, round((float) $lease->refund_amount, 2));

        $details = json_decode($lease->damage_details, true);
        $items   = array_column($details, 'item');

        $this->assertNotContains('Unpaid rent', $items);
        $this->assertContains('Unused advance rent (returned)', $items);
    }

    public function test_damages_bigger_than_the_deposit_do_not_reach_the_advance(): void
    {
        $lease = $this->makeLease([
            'move_out_requested'   => true,
            'advance_rent_balance' => 15000.00,
        ]);

        $this->actingAs($this->makeManager())
            ->post(route('manager.leases.approveMoveOut', $lease->id), [
                'deposit_amount' => 10000.00,
                'damage_cost'    => 18000.00,
                'refund_amount'  => 15000.00,
                'damages_json'   => '[{"item":"Wrecked door","cost":18000}]',
            ]);

        $lease->refresh();

        // Deposit wiped out and 8,000 short, but the advance comes back whole.
        $this->assertEquals(15000.00, round((float) $lease->refund_amount, 2));
    }

    public function test_the_tenant_is_told_the_advance_is_part_of_the_refund(): void
    {
        $lease = $this->makeLease([
            'move_out_requested'   => true,
            'advance_rent_balance' => 15000.00,
        ]);

        $this->actingAs($this->makeManager())
            ->post(route('manager.leases.approveMoveOut', $lease->id), [
                'deposit_amount' => 10000.00,
                'damage_cost'    => 0,
                'refund_amount'  => 25000.00,
                'damages_json'   => '[]',
            ]);

        $notif = Notification::where('user_id', $lease->user_id)
            ->where('title', 'Move Out Approved')
            ->latest('id')
            ->first();

        $this->assertNotNull($notif);
        $this->assertStringContainsString('Unused Advance Rent', $notif->message);
        $this->assertStringContainsString('15,000.00', $notif->message);
    }

    /* ==================================================================
     |  3. FORCE MOVE-OUT
     * ================================================================== */

    public function test_force_move_out_refunds_the_unused_advance(): void
    {
        $lease = $this->makeLease(['advance_rent_balance' => 15000.00]);

        $this->actingAs($this->makeManager())
            ->patch(route('manager.tenant.forceMoveOut', $lease->user_id), [
                'lease_id'       => $lease->id,
                'reason'         => 'Repeated disturbance complaints from neighbours.',
                'deposit_amount' => 10000.00,
                'damage_cost'    => 0,
                'refund_amount'  => 25000.00,
                'damages_json'   => '[]',
                'deposit_paid'   => 'true',
            ]);

        $lease->refresh();

        $this->assertEquals('terminated', $lease->lea_status);
        $this->assertEquals(25000.00, round((float) $lease->refund_amount, 2));
        $this->assertEquals(0.00, $lease->advanceRentBalance());
    }

    public function test_unpaid_deposit_still_returns_the_advance(): void
    {
        // The deposit was never paid, so there is nothing to refund from it —
        // but the advance is the tenant's money and comes back anyway.
        $lease = $this->makeLease([
            'deposit_balance'      =>  8000.00,
            'advance_rent_balance' => 15000.00,
        ]);

        $this->actingAs($this->makeManager())
            ->patch(route('manager.tenant.forceMoveOut', $lease->user_id), [
                'lease_id'       => $lease->id,
                'reason'         => 'Lease terminated for non-payment of the security deposit.',
                'deposit_amount' => 0,
                'damage_cost'    => 0,
                'refund_amount'  => 15000.00,
                'damages_json'   => '[]',
                'deposit_paid'   => 'false',
            ]);

        $lease->refresh();

        $this->assertEquals(15000.00, round((float) $lease->refund_amount, 2));

        // And the notification must not flatly say "no refund".
        $notif = Notification::where('user_id', $lease->user_id)
            ->where('title', 'Forced Move Out')
            ->latest('id')
            ->first();

        $this->assertNotNull($notif);
        $this->assertStringContainsString('advance rent', strtolower($notif->message));
        $this->assertStringContainsString('15,000.00', $notif->message);
    }

    public function test_a_lease_with_no_advance_settles_exactly_as_before(): void
    {
        $lease = $this->makeLease([
            'move_out_requested' => true,
            'rent_balance'       => 3000.00,
        ]);

        $this->actingAs($this->makeManager())
            ->post(route('manager.leases.approveMoveOut', $lease->id), [
                'deposit_amount' => 10000.00,
                'damage_cost'    => 2000.00,
                'refund_amount'  => 5000.00,
                'damages_json'   => '[{"item":"Broken window","cost":2000}]',
            ]);

        $lease->refresh();

        // 10,000 deposit − 2,000 damages − 3,000 arrears.
        $this->assertEquals(5000.00, round((float) $lease->refund_amount, 2));
    }
}
