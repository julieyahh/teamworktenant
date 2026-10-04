<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\RentCharge;
use App\Models\Unit;
use App\Models\User;
use App\Models\UtilityBillingProof;
use App\Services\BillingService;
use App\Services\DuplicateBillException;
use App\Support\RentCalendar;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * v19:
 *   1. Payment days — rent raised on the 1st, due on the 10th.
 *   2. One bill per tenant, room, type and month.
 *   3. Estimated bills are shown to the tenant but cannot be paid.
 *   4. Renewal starts with the tenant; the manager's Renew is locked until then.
 *   5. Invoice for accepted payments.
 *   6. Compact tenant dashboard.
 */
class V19ChangesTest extends TestCase
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

    private function tenant(string $first = 'Juan'): User
    {
        return User::create([
            'first_name' => $first,
            'last_name'  => 'Tenant' . uniqid(),
            'email'      => strtolower($first) . uniqid() . '@example.test',
            'password'   => 'Secret#123',
            'role'       => 'tenant',
            'status'     => 'approved',
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

    private function lease(User $tenant, array $o = []): Lease
    {
        $unit = Unit::create([
            'type' => '1-Bedroom', 'room_no' => 'R' . random_int(1000, 9999),
            'room_price' => 6000, 'status' => 'occupied', 'no_of_occupants' => 1,
        ]);

        return Lease::create(array_merge([
            'user_id' => $tenant->id, 'unit_id' => $unit->id,
            'lea_start_date' => now()->subMonths(3)->toDateString(),
            'lea_end_date'   => now()->addMonths(9)->toDateString(),
            'lea_status' => 'active', 'room_no' => $unit->room_no,
            'rent_balance' => 0, 'utility_balance' => 0, 'deposit_balance' => 0,
            'penalty_fee' => 0, 'monthly_rent' => 6000,
        ], $o));
    }

    private function bill(Lease $lease, array $o = []): UtilityBillingProof
    {
        return $this->billing->issueBill($lease, array_merge([
            'billing_type'  => 'Electricity',
            'billing_month' => 'September 2026',
            'amount'        => 1500.00,
        ], $o));
    }

    /* ==================================================================
     |  1. PAYMENT DAYS
     * ================================================================== */

    public function test_calendar_periods_and_due_dates(): void
    {
        // Periods end on the next 1st.
        $this->assertSame('2026-11-01', RentCalendar::periodEnd(Carbon::parse('2026-10-01'))->toDateString());
        $this->assertSame('2026-11-01', RentCalendar::periodEnd(Carbon::parse('2026-10-17'))->toDateString());
        $this->assertSame('2027-01-01', RentCalendar::periodEnd(Carbon::parse('2026-12-31'))->toDateString());

        // Rent due: the 10th, with at least 5 days' notice.
        $this->assertSame('2026-10-10', RentCalendar::rentDueDate(Carbon::parse('2026-10-01'))->toDateString());
        $this->assertSame('2026-10-10', RentCalendar::rentDueDate(Carbon::parse('2026-10-05'))->toDateString());
        $this->assertSame('2026-11-10', RentCalendar::rentDueDate(Carbon::parse('2026-10-08'))->toDateString());
        $this->assertSame('2026-11-10', RentCalendar::rentDueDate(Carbon::parse('2026-10-17'))->toDateString());

        // Proration: 15 of 31 days.
        $this->assertEquals(2903.23, RentCalendar::amountFor(Carbon::parse('2026-10-17'), 6000));
        $this->assertEquals(6000.00, RentCalendar::amountFor(Carbon::parse('2026-10-01'), 6000));

        // Bills: the 10th of the following month.
        $this->assertSame('2026-10-10', RentCalendar::billDueDate('September 2026', Carbon::parse('2026-09-28'))->toDateString());
        $this->assertSame('2026-10-10', RentCalendar::billDueDate('2026-09', Carbon::parse('2026-10-01'))->toDateString());
        // Issued too late for that: next payment day with notice.
        $this->assertSame('2026-11-10', RentCalendar::billDueDate('September 2026', Carbon::parse('2026-10-08'))->toDateString());

        $this->assertSame('1st–10th', RentCalendar::label());
    }

    public function test_billing_month_spellings_normalise(): void
    {
        foreach (['2026-09', 'September 2026', 'Sep 2026', 'september 2026', '09/2026'] as $v) {
            $this->assertSame('September 2026', RentCalendar::normaliseBillingMonth($v), $v);
        }
    }

    public function test_move_in_mid_month_is_prorated_then_joins_the_1st(): void
    {
        Carbon::setTestNow('2026-10-17 09:00:00');

        $lease = $this->lease($this->tenant(), ['lea_start_date' => '2026-10-17']);

        $first = $this->billing->openRentSchedule($lease, Carbon::parse('2026-10-17'));
        $lease->refresh();

        $this->assertSame('2026-10-17', $first->period_start->toDateString());
        $this->assertSame('2026-11-01', $first->period_end->toDateString());
        $this->assertSame('2026-11-10', $first->due_date->toDateString());
        $this->assertEquals(2903.23, (float) $first->amount);
        $this->assertTrue($first->isPartMonth());
        $this->assertSame('Oct 17–31, 2026', $first->periodLabel());
        $this->assertSame('2026-11-01', $lease->next_rent_charge_on->toDateString());

        // Nothing more until the 1st.
        $this->assertSame(0, $this->billing->chargeDueRent($lease, Carbon::parse('2026-10-31'))['charged']);

        // On the 1st: a whole month, due the 10th.
        $result = $this->billing->chargeDueRent($lease->fresh(), Carbon::parse('2026-11-01'));
        $this->assertSame(1, $result['charged']);

        $nov = $result['charges'][0];
        $this->assertSame('2026-11-01', $nov->period_start->toDateString());
        $this->assertSame('2026-11-10', $nov->due_date->toDateString());
        $this->assertEquals(6000.00, (float) $nov->amount);
        $this->assertSame('November 2026', $nov->periodLabel());

        $this->assertEquals(8903.23, (float) $lease->fresh()->rent_balance);
    }

    public function test_a_pre_v19_lease_joins_the_calendar_without_gap_or_overlap(): void
    {
        // Old schedule: the 17th of every month.
        $lease = $this->lease($this->tenant(), ['next_rent_charge_on' => '2026-10-17']);

        $result = $this->billing->chargeDueRent($lease, Carbon::parse('2026-11-02'));

        // Both the part-month and November were due by Nov 2.
        $this->assertSame(2, $result['charged']);
        $this->assertSame('2026-10-17', $result['charges'][0]->period_start->toDateString());
        $this->assertSame('2026-11-01', $result['charges'][0]->period_end->toDateString());
        $this->assertSame('2026-11-01', $result['charges'][1]->period_start->toDateString());
        $this->assertSame('2026-12-01', $lease->fresh()->next_rent_charge_on->toDateString());
    }

    public function test_penalty_starts_the_day_after_the_10th(): void
    {
        Carbon::setTestNow('2026-10-01 09:00:00');

        $lease = $this->lease($this->tenant());
        $this->billing->openRentSchedule($lease, Carbon::parse('2026-10-01'));
        $lease->refresh();

        $this->assertSame('2026-10-10', $lease->due_date->toDateString());
        $this->assertEquals(0.0, $this->billing->assessLatePenalty($lease, Carbon::parse('2026-10-10')));
        $this->assertEquals(600.0, $this->billing->assessLatePenalty($lease->fresh(), Carbon::parse('2026-10-11')));
    }

    public function test_bills_default_to_the_10th_of_the_next_month(): void
    {
        Carbon::setTestNow('2026-09-28 09:00:00');

        $bill = $this->bill($this->lease($this->tenant()), ['billing_month' => '2026-09']);

        $this->assertSame('September 2026', $bill->billing_month);
        $this->assertSame('2026-10-10', $bill->due_date->toDateString());
    }

    /* ==================================================================
     |  2. ONE BILL PER TENANT / ROOM / TYPE / MONTH
     * ================================================================== */

    public function test_a_second_bill_for_the_same_month_is_refused_even_when_the_first_is_an_estimate(): void
    {
        $lease = $this->lease($this->tenant());

        $this->bill($lease, ['is_estimated' => true]);

        try {
            $this->bill($lease, ['billing_month' => '2026-09', 'amount' => 1700]);
            $this->fail('A duplicate bill was issued.');
        } catch (DuplicateBillException $e) {
            $this->assertStringContainsString('set its final amount', $e->getMessage());
        }

        $this->assertSame(1, UtilityBillingProof::count());
    }

    public function test_other_type_month_room_void_and_repair_are_allowed(): void
    {
        $tenant = $this->tenant();
        $lease  = $this->lease($tenant);

        $first = $this->bill($lease);
        $this->bill($lease, ['billing_type' => 'Water']);                 // other type
        $this->bill($lease, ['billing_month' => 'October 2026']);         // other month
        $this->bill($this->lease($tenant), []);                           // other room, same tenant
        $this->bill($this->lease($this->tenant('Ana')), []);              // other tenant
        $this->bill($lease, ['billing_type' => 'Repair', 'amount' => 300]);
        $this->bill($lease, ['billing_type' => 'Repair', 'amount' => 450]); // two repairs: fine

        // Voiding the original frees the slot for a corrected bill.
        $first->update(['status' => UtilityBillingProof::STATUS_VOID, 'voided_at' => now(), 'void_reason' => 'wrong']);
        $this->bill($lease, ['amount' => 1450]);

        $this->assertSame(8, UtilityBillingProof::count());
    }

    public function test_the_bills_screen_refuses_a_duplicate_with_a_link_to_the_existing_bill(): void
    {
        $lease    = $this->lease($this->tenant());
        $existing = $this->bill($lease, ['billing_type' => 'Water', 'is_estimated' => true]);

        $this->actingAs($this->manager())
            ->from(route('manager.bills.index'))
            ->post(route('manager.bills.store'), [
                'lease_id'      => $lease->id,
                'billing_type'  => 'Water',
                'billing_month' => '2026-09',
                'amount'        => 800,
            ])
            ->assertRedirect(route('manager.bills.index'))
            ->assertSessionHas('error')
            ->assertSessionHas('duplicate_bill_id', $existing->id);

        $this->assertSame(1, UtilityBillingProof::count());
    }

    public function test_a_shared_bill_is_refused_whole_if_anyone_already_has_one(): void
    {
        $a = $this->lease($this->tenant('A'));
        $b = $this->lease($this->tenant('B'));
        $this->bill($b, ['billing_type' => 'Water']);

        $this->expectException(DuplicateBillException::class);

        try {
            $this->billing->issueSharedBill(collect([$a, $b]), [
                'billing_type' => 'Water', 'billing_month' => 'September 2026',
                'total_amount' => 2000, 'split_rule' => 'equal', 'remainder_policy' => 'landlord',
            ]);
        } finally {
            $this->assertSame(1, UtilityBillingProof::count(), 'nobody was billed');
        }
    }

    /* ==================================================================
     |  3. ESTIMATES CANNOT BE PAID
     * ================================================================== */

    public function test_tenant_cannot_pay_an_estimate_until_the_final_amount_is_set(): void
    {
        Storage::fake('public');

        $tenant = $this->tenant();
        $lease  = $this->lease($tenant);
        $bill   = $this->bill($lease, ['is_estimated' => true]);

        $post = fn () => $this->actingAs($tenant)
            ->from(route('tenant.payments'))
            ->post(route('tenant.payments.store'), [
                'lease_id'     => $lease->id,
                'pay_method'   => 'GCash',
                'payment_for'  => 'Utilities',
                'billing_type' => 'Electricity',
                'pay_amount'   => 1500,
                'proof'        => UploadedFile::fake()->image('proof.jpg'),
            ]);

        $post()->assertRedirect(route('tenant.payments'))->assertSessionHas('error');
        $this->assertSame(0, Payment::count());

        // Shown on the page, labelled, and not in the "unpaid" total.
        $this->actingAs($tenant)->get(route('tenant.payments'))
            ->assertOk()
            ->assertSee('Not payable yet')
            ->assertSee('estimated — payable once finalised');

        // Manager sets the final amount → payable.
        $this->actingAs($this->manager())
            ->post(route('manager.bills.reconcile', $bill->id), ['actual_amount' => 1620])
            ->assertSessionHas('success');

        $this->assertTrue($bill->fresh()->isPayable());
        $this->assertTrue(Notification::where('user_id', $tenant->id)->where('message', 'like', '%can now pay%')->exists());

        $post()->assertSessionHas('success');
        $this->assertSame(1, Payment::count());
    }

    public function test_an_estimate_with_an_unchanged_final_amount_still_opens_for_payment(): void
    {
        $tenant = $this->tenant();
        $bill   = $this->bill($this->lease($tenant), ['is_estimated' => true]);

        $this->actingAs($this->manager())
            ->post(route('manager.bills.reconcile', $bill->id), ['actual_amount' => 1500])
            ->assertSessionHas('success');

        $this->assertTrue($bill->fresh()->isPayable());
        $this->assertTrue(Notification::where('user_id', $tenant->id)->where('title', 'Utility Bill Finalised')->exists());
    }

    public function test_an_estimate_is_never_overdue_or_on_the_manager_due_monitor(): void
    {
        $lease = $this->lease($this->tenant());
        $bill  = $this->bill($lease, ['is_estimated' => true, 'due_date' => now()->subDays(20)->toDateString()]);

        $this->assertFalse($bill->isOverdue());
        $this->assertEquals(0.0, $this->billing->arrearsFor($lease)['utilities']);
        $this->assertEquals(0.0, (float) $lease->fresh()->utility_balance);

        $items = app(\App\Services\DueDateMonitor::class)->build()['items'];
        $this->assertNull($items->first(fn ($i) => $i['kind'] === 'utility'));
    }

    public function test_move_out_waits_for_estimates_to_be_finalised(): void
    {
        $tenant = $this->tenant();
        $lease  = $this->lease($tenant, ['move_out_requested' => true]);
        $this->bill($lease, ['is_estimated' => true]);

        $this->actingAs($this->manager())
            ->post(route('manager.leases.approveMoveOut', $lease->id), [
                'deposit_amount' => 0, 'damage_cost' => 0, 'refund_amount' => 0, 'damages_json' => '[]',
            ])
            ->assertSessionHas('error');

        $this->assertSame('active', $lease->fresh()->lea_status);
    }

    /* ==================================================================
     |  4. RENEWAL STARTS WITH THE TENANT
     * ================================================================== */

    public function test_manager_cannot_renew_until_the_tenant_requests_it(): void
    {
        $tenant = $this->tenant();
        $lease  = $this->lease($tenant, ['lea_end_date' => now()->addDays(20)->toDateString()]);
        $mgr    = $this->manager();

        // Locked on Tenant Management.
        $this->actingAs($mgr)->get(route('manager.tenants'))
            ->assertOk()
            ->assertSee('not requested by tenant')
            ->assertDontSee('renewLeaseModal' . $tenant->id, false);

        // And on the server.
        $this->actingAs($mgr)->post(route('manager.leases.renew', $lease->id))->assertSessionHas('error');
        $this->assertSame(0, (int) $lease->fresh()->renewal_count);

        // Tenant asks.
        $this->actingAs($tenant)->post(route('tenant.leases.requestRenewal', $lease->id))
            ->assertRedirect(route('tenant.leases'))
            ->assertSessionHas('success');

        $lease->refresh();
        $this->assertTrue($lease->renewal_requested);
        $this->assertNotNull($lease->renewal_requested_at);
        $this->assertTrue(Notification::where('user_id', $mgr->id)->where('title', 'Renewal Request')->exists());

        // Unlocked.
        $this->actingAs($mgr)->get(route('manager.tenants'))->assertSee('renewLeaseModal' . $tenant->id, false);

        $this->actingAs($mgr)->post(route('manager.leases.renew', $lease->id))->assertSessionHas('success');

        $lease->refresh();
        $this->assertSame(1, (int) $lease->renewal_count);
        $this->assertFalse($lease->renewal_requested, 'the request is used up by the renewal');
        $this->assertEquals(6300.00, $lease->currentRent());
    }

    public function test_tenant_can_only_request_inside_the_window_and_can_withdraw(): void
    {
        $tenant = $this->tenant();
        $far    = $this->lease($tenant, ['lea_end_date' => now()->addMonths(8)->toDateString()]);
        $near   = $this->lease($tenant, ['lea_end_date' => now()->addDays(30)->toDateString()]);

        $this->actingAs($tenant)->post(route('tenant.leases.requestRenewal', $far->id))->assertSessionHas('error');
        $this->assertFalse($far->fresh()->renewal_requested);

        $this->actingAs($tenant)->post(route('tenant.leases.requestRenewal', $near->id))->assertSessionHas('success');
        $this->actingAs($tenant)->patch(route('tenant.leases.cancelRenewal', $near->id))->assertSessionHas('success');
        $this->assertFalse($near->fresh()->renewal_requested);

        // Someone else's lease.
        $this->actingAs($this->tenant('Other'))->post(route('tenant.leases.requestRenewal', $near->id))->assertSessionHas('error');
        $this->assertFalse($near->fresh()->renewal_requested);
    }

    public function test_the_lease_page_shows_the_request_button(): void
    {
        $tenant = $this->tenant();
        $this->lease($tenant, ['lea_end_date' => now()->addDays(30)->toDateString()]);

        $this->actingAs($tenant)->get(route('tenant.leases'))->assertOk()->assertSee('Request Renewal');
    }

    public function test_the_nightly_renewal_only_renews_requested_leases(): void
    {
        $asked  = $this->lease($this->tenant('Asked'),  ['lea_end_date' => now()->subDay()->toDateString(), 'renewal_requested' => true]);
        $silent = $this->lease($this->tenant('Silent'), ['lea_end_date' => now()->subDay()->toDateString()]);

        $this->artisan('leases:renew')->assertSuccessful();

        $this->assertSame(1, (int) $asked->fresh()->renewal_count);
        $this->assertSame(0, (int) $silent->fresh()->renewal_count);
        $this->assertSame('active', $silent->fresh()->lea_status);
    }

    /* ==================================================================
     |  5. INVOICE
     * ================================================================== */

    public function test_tenant_downloads_an_invoice_for_an_accepted_payment_only(): void
    {
        $tenant = $this->tenant();
        $lease  = $this->lease($tenant);
        $bill   = $this->bill($lease);

        $payment = Payment::create([
            'tenant_id' => $tenant->id, 'lease_id' => $lease->id, 'pay_date' => now(),
            'payment_for' => 'Utilities', 'utility_type' => 'Electricity', 'pay_amount' => 1500,
            'pay_method' => 'GCash', 'pay_status' => 'Pending', 'reference_number' => 'PAY-TEST-1',
        ]);

        // Pending: no invoice.
        $this->actingAs($tenant)->get(route('tenant.payments.invoice', $payment->id))
            ->assertRedirect(route('tenant.payments'));

        // Manager accepts it the normal way.
        $this->actingAs($this->manager())
            ->patch(route('manager.payments.updateStatus', $payment->id), ['pay_status' => 'Accepted'])
            ->assertSessionHas('success');

        $payment->refresh();
        $this->assertNotNull($payment->accepted_at);
        $this->assertSame('INV-' . now()->format('Y') . '-' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT), $payment->invoiceNumber());

        $lines = app(\App\Services\InvoiceService::class)->lines($payment);
        $this->assertSame('Electricity bill — September 2026', $lines['lines'][0]['description']);
        $this->assertEquals(1500.00, $lines['total']);

        $res = $this->actingAs($tenant)->get(route('tenant.payments.invoice', $payment->id));
        $res->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $res->headers->get('content-type'));
        $this->assertStringContainsString($payment->invoiceNumber() . '.pdf', (string) $res->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $res->getContent());

        // Not someone else's.
        $this->actingAs($this->tenant('Snoop'))->get(route('tenant.payments.invoice', $payment->id))->assertNotFound();

        // The office can download the same document.
        $this->actingAs($this->manager())->get(route('manager.payments.invoice', $payment->id))->assertOk();

        // Shown on the payments page.
        $this->actingAs($tenant)->get(route('tenant.payments'))->assertSee(route('tenant.payments.invoice', $payment->id), false);
    }

    public function test_un_accepting_a_payment_withdraws_its_invoice(): void
    {
        $tenant  = $this->tenant();
        $lease   = $this->lease($tenant, ['rent_balance' => 6000]);
        $payment = Payment::create([
            'tenant_id' => $tenant->id, 'lease_id' => $lease->id, 'pay_date' => now(),
            'payment_for' => 'Rent', 'pay_amount' => 6000, 'pay_method' => 'GCash',
            'pay_status' => 'Pending', 'reference_number' => 'PAY-TEST-2',
        ]);

        $mgr = $this->manager();
        $this->actingAs($mgr)->patch(route('manager.payments.updateStatus', $payment->id), ['pay_status' => 'Accepted']);
        $this->actingAs($mgr)->patch(route('manager.payments.updateStatus', $payment->id), ['pay_status' => 'Rejected']);

        $payment->refresh();
        $this->assertNull($payment->accepted_at);
        $this->assertFalse($payment->hasInvoice());
    }

    /* ==================================================================
     |  6. COMPACT TENANT DASHBOARD
     * ================================================================== */

    public function test_tenant_dashboard_is_compact_and_shows_whats_due(): void
    {
        Carbon::setTestNow('2026-10-04 09:00:00');

        $tenant = $this->tenant('Maria');
        $lease  = $this->lease($tenant, ['lea_start_date' => '2026-10-01']);
        $this->billing->openRentSchedule($lease, Carbon::parse('2026-10-01'));
        $this->bill($lease->fresh(), ['billing_type' => 'Water', 'amount' => 400, 'is_estimated' => true]);

        $this->actingAs($tenant)->get(route('tenant.home'))
            ->assertOk()
            ->assertSee('td-kpis', false)
            ->assertSee('td-main', false)
            ->assertSee("What's Due", false)
            ->assertSee('Payment window open')
            ->assertSee('Rent · October 2026')
            ->assertSee('Not payable yet')
            ->assertSee('Welcome back, Maria');
    }

    public function test_tenant_dashboard_renders_with_no_lease(): void
    {
        $this->actingAs($this->tenant())->get(route('tenant.home'))
            ->assertOk()
            ->assertSee('No active lease');
    }
}
