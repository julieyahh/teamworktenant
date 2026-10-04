<?php

namespace Tests\Unit;

use App\Services\BillingService;
use Tests\TestCase;

/**
 * The proration arithmetic, with no database in the way.
 *
 * BillingService::splitAmount() is deliberately pure — it takes a total and a
 * set of weights and returns shares. That makes the awkward cases (amounts that
 * do not divide evenly, all-zero weights, a single participant) cheap to pin
 * down here rather than discovering them on a real bill.
 */
class BillSplitTest extends TestCase
{
    private BillingService $billing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->billing = new BillingService();
    }

    /** The clean case: three ways, divides exactly. */
    public function test_it_splits_an_evenly_divisible_amount(): void
    {
        $result = $this->billing->splitAmount(900.00, [1 => 1.0, 2 => 1.0, 3 => 1.0], 'landlord');

        $this->assertEquals([1 => 300.00, 2 => 300.00, 3 => 300.00], $result['shares']);
        $this->assertEquals(0.00, $result['remainder']);
    }

    /**
     * The case the whole rounding rule exists for.
     *
     * 1000 / 3 = 333.333... Shares round DOWN to 333.33, which leaves one
     * centavo unassigned. Under the landlord policy nobody is charged it.
     */
    public function test_it_rounds_down_and_reports_the_remainder(): void
    {
        $result = $this->billing->splitAmount(1000.00, [1 => 1.0, 2 => 1.0, 3 => 1.0], 'landlord');

        $this->assertEquals([1 => 333.33, 2 => 333.33, 3 => 333.33], $result['shares']);
        $this->assertEquals(0.01, $result['remainder']);
        $this->assertNull($result['designated_lease_id']);

        // The key property: tenants are never charged MORE than the total.
        $this->assertLessThanOrEqual(1000.00, array_sum($result['shares']));
    }

    /** Under the designated policy the remainder is added to one share and the total balances. */
    public function test_designated_tenant_absorbs_the_remainder(): void
    {
        $result = $this->billing->splitAmount(1000.00, [1 => 1.0, 2 => 1.0, 3 => 1.0], 'designated_tenant');

        $this->assertEquals(1000.00, round(array_sum($result['shares']), 2));
        $this->assertNotNull($result['designated_lease_id']);
        $this->assertEquals(333.34, $result['shares'][$result['designated_lease_id']]);
    }

    /**
     * Ties must break the same way every time. If this ever goes random, two
     * managers splitting the same bill get different answers and there is no
     * defensible reason why one tenant paid the extra centavo.
     */
    public function test_designation_is_deterministic_on_a_tie(): void
    {
        $first = $this->billing->splitAmount(1000.00, [7 => 1.0, 3 => 1.0, 9 => 1.0], 'designated_tenant');

        // Equal shares, so the tie-break is lowest lease id.
        $this->assertEquals(3, $first['designated_lease_id']);

        // Same input, same answer, however many times you run it.
        for ($i = 0; $i < 5; $i++) {
            $again = $this->billing->splitAmount(1000.00, [7 => 1.0, 3 => 1.0, 9 => 1.0], 'designated_tenant');
            $this->assertEquals($first['shares'], $again['shares']);
        }
    }

    /** Weighted split: 2 occupants vs 1 pays twice as much. */
    public function test_it_splits_by_weight(): void
    {
        $result = $this->billing->splitAmount(900.00, [1 => 2.0, 2 => 1.0], 'landlord');

        $this->assertEquals(600.00, $result['shares'][1]);
        $this->assertEquals(300.00, $result['shares'][2]);
    }

    /**
     * Every weight zero — e.g. a headcount split where no lease records
     * occupants. Falls back to an equal split rather than dividing by zero,
     * because a bill that cannot be issued is worse than one split evenly.
     */
    public function test_all_zero_weights_fall_back_to_an_equal_split(): void
    {
        $result = $this->billing->splitAmount(300.00, [1 => 0.0, 2 => 0.0, 3 => 0.0], 'landlord');

        $this->assertEquals([1 => 100.00, 2 => 100.00, 3 => 100.00], $result['shares']);
    }

    /** Zero-amount bill: everyone owes nothing, no remainder, no crash. */
    public function test_a_zero_amount_splits_to_zero(): void
    {
        $result = $this->billing->splitAmount(0.00, [1 => 1.0, 2 => 1.0], 'landlord');

        $this->assertEquals([1 => 0.00, 2 => 0.00], $result['shares']);
        $this->assertEquals(0.00, $result['remainder']);
    }

    /** One participant takes the whole thing, remainder or not. */
    public function test_a_single_participant_takes_the_full_amount(): void
    {
        $result = $this->billing->splitAmount(1234.57, [5 => 1.0], 'landlord');

        $this->assertEquals(1234.57, $result['shares'][5]);
        $this->assertEquals(0.00, $result['remainder']);
    }

    public function test_it_rejects_an_empty_participant_list(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->billing->splitAmount(100.00, [], 'landlord');
    }
}
