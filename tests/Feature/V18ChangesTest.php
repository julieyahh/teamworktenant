<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Payment;
use App\Models\RentCharge;
use App\Models\Unit;
use App\Models\User;
use App\Models\UtilityBillingProof;
use App\Services\DueDateMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * v18: login spinner, ID camera + data privacy on registration, the manager
 * dashboard due-date monitor, and the payments page as lists.
 */
class V18ChangesTest extends TestCase
{
    use RefreshDatabase;

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
            'room_price' => 5000, 'status' => 'occupied', 'no_of_occupants' => 1,
        ]);

        return Lease::create(array_merge([
            'user_id' => $tenant->id, 'unit_id' => $unit->id,
            'lea_start_date' => now()->subMonths(3)->toDateString(),
            'lea_end_date'   => now()->addMonths(9)->toDateString(),
            'lea_status' => 'active', 'room_no' => $unit->room_no,
            'rent_balance' => 0, 'utility_balance' => 0, 'deposit_balance' => 0,
            'penalty_fee' => 0, 'monthly_rent' => 5000,
            'due_date' => now()->addDays(20)->toDateString(),
        ], $o));
    }

    /* ---------------- Due date monitor ---------------- */

    public function test_monitor_buckets_rent_bills_and_lease_ends(): void
    {
        $late   = $this->lease($this->tenant('Late'),   ['rent_balance' => 5000, 'penalty_fee' => 250, 'due_date' => now()->subDays(4)->toDateString()]);
        $today  = $this->lease($this->tenant('Today'),  ['rent_balance' => 5000, 'due_date' => now()->toDateString()]);
        $soon   = $this->lease($this->tenant('Soon'),   ['rent_balance' => 5000, 'due_date' => now()->addDays(3)->toDateString()]);
        $paid   = $this->lease($this->tenant('Paid'),   ['rent_balance' => 0, 'due_date' => now()->addDays(12)->toDateString()]);
        $stale  = $this->lease($this->tenant('Stale'),  ['rent_balance' => 0, 'due_date' => now()->subDays(10)->toDateString()]);
        $far    = $this->lease($this->tenant('Far'),    ['rent_balance' => 5000, 'due_date' => now()->addDays(60)->toDateString()]);
        $ending = $this->lease($this->tenant('Ending'), ['lea_end_date' => now()->addDays(5)->toDateString(), 'due_date' => now()->addDays(60)->toDateString()]);
        $gone   = $this->lease($this->tenant('Gone'),   ['lea_status' => 'terminated', 'rent_balance' => 9000, 'due_date' => now()->subDays(40)->toDateString()]);

        UtilityBillingProof::create([
            'tenant_id' => $soon->user_id, 'lease_id' => $soon->id, 'billing_type' => 'Electricity',
            'billing_month' => 'August 2026', 'amount' => 1200, 'amount_paid' => 500,
            'status' => 'partial', 'due_date' => now()->subDays(2)->toDateString(),
        ]);

        $out   = app(DueDateMonitor::class)->build();
        $items = $out['items'];

        $find = fn (Lease $l, string $kind) => $items->first(fn ($i) => $i['lease_id'] === $l->id && $i['kind'] === $kind);

        $this->assertSame('overdue', $find($late, 'rent')['bucket']);
        $this->assertEquals(5250.0, $find($late, 'rent')['amount']);
        $this->assertSame(-4, $find($late, 'rent')['days']);
        $this->assertSame('today', $find($today, 'rent')['bucket']);
        $this->assertSame('week',  $find($soon, 'rent')['bucket']);
        $this->assertTrue($find($paid, 'rent')['paid_up']);
        $this->assertNull($find($stale, 'rent'), 'a paid-up lease with a stale past date is not overdue');
        $this->assertNull($find($far, 'rent'), 'beyond the 30-day window');
        $this->assertSame('week', $find($ending, 'lease_end')['bucket']);
        $this->assertNull($find($gone, 'rent'), 'terminated leases are not monitored');

        $bill = $find($soon, 'utility');
        $this->assertSame('overdue', $bill['bucket']);
        $this->assertEquals(700.0, $bill['amount'], 'shows what is left, not the bill total');

        // Overdue first.
        $this->assertSame('overdue', $items->first()['bucket']);

        $this->assertSame(2, $out['summary']['overdue_count']);
        $this->assertEquals(5950.0, $out['summary']['overdue_amount']);
        $this->assertSame(1, $out['summary']['lease_end_count']);
    }

    public function test_manager_dashboard_renders_the_monitor(): void
    {
        $this->lease($this->tenant('Overdue'), ['rent_balance' => 5000, 'due_date' => now()->subDays(2)->toDateString()]);

        $this->actingAs($this->manager())
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertSee('Due Date Monitor')
            ->assertSee('Overdue')
            ->assertSee('2 days overdue')
            ->assertSee('₱5,000.00', false);
    }

    public function test_manager_dashboard_renders_with_nothing_due(): void
    {
        $this->actingAs($this->manager())
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertSee('Nothing overdue and nothing due');
    }

    /* ---------------- Payments page ---------------- */

    public function test_tenant_payments_page_is_lists_not_tables(): void
    {
        $tenant = $this->tenant();
        $lease  = $this->lease($tenant, ['rent_balance' => 5000]);

        RentCharge::create([
            'lease_id' => $lease->id, 'tenant_id' => $tenant->id, 'room_no' => $lease->room_no,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end'   => now()->endOfMonth()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(), 'amount' => 5000,
        ]);
        UtilityBillingProof::create([
            'tenant_id' => $tenant->id, 'lease_id' => $lease->id, 'billing_type' => 'Water',
            'billing_month' => 'September 2026', 'amount' => 300, 'amount_paid' => 0,
            'status' => 'unpaid', 'due_date' => now()->addDays(5)->toDateString(),
        ]);
        Payment::create([
            'tenant_id' => $tenant->id, 'lease_id' => $lease->id, 'pay_date' => now(),
            'payment_for' => 'Utilities', 'utility_type' => 'Electricity', 'pay_amount' => 450,
            'pay_method' => 'gcash', 'account_no' => '09171234567', 'pay_status' => 'Pending',
        ]);

        $html = $this->actingAs($tenant)->get(route('tenant.payments'))
            ->assertOk()
            ->assertSee('Payment History')
            ->assertSee('pl-list', false)
            ->assertSee('09171234567')
            ->assertSee('Electricity')
            ->assertSee('Water')
            ->getContent();

        $this->assertStringNotContainsString('<table', $html);
    }

    /* ---------------- Login ---------------- */

    public function test_login_page_has_the_please_wait_spinner(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('id="authLoading"', false)
            ->assertSee('Please wait');
    }

    /* ---------------- Registration ---------------- */

    private function registrationPayload(Unit $unit): array
    {
        return [
            'first_name' => 'Maria', 'last_name' => 'Santos',
            'email' => 'maria' . uniqid() . '@example.test', 'contact' => '09171234567',
            'password' => 'Secret#123', 'password_confirmation' => 'Secret#123',
            'current_address' => 'Davao City', 'birthdate' => '1995-01-01',
            'unit_type' => '1-Bedroom', 'unit_id' => $unit->id,
            'move_in_date' => now()->addDay()->toDateString(), 'reason' => 'Work',
            'employment_status' => 'Employed', 'employer_school' => 'ACME',
            'source_of_income' => 'Salary', 'emergency_name' => 'Jose Santos',
            'emergency_number' => '09181234567', 'emergency_relationship' => 'Father',
            'valid_id'   => UploadedFile::fake()->image('valid_id_1727000000.jpg', 1600, 1000),
            'id_picture' => UploadedFile::fake()->image('me.jpg', 300, 300),
            'terms' => 'on',
        ];
    }

    private function vacantUnit(): Unit
    {
        return Unit::create([
            'type' => '1-Bedroom', 'room_no' => 'V' . random_int(1000, 9999),
            'room_price' => 5000, 'status' => 'vacant', 'no_of_occupants' => 0,
        ]);
    }

    public function test_registration_requires_data_privacy_consent(): void
    {
        Storage::fake('public');
        $payload = $this->registrationPayload($this->vacantUnit());

        $this->from('/')->post(route('register.submit'), $payload)
            ->assertSessionHasErrors('data_privacy');

        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
    }

    public function test_registration_requires_terms_server_side(): void
    {
        Storage::fake('public');
        $payload = $this->registrationPayload($this->vacantUnit());
        unset($payload['terms']);
        $payload['data_privacy'] = '1';

        $this->from('/')->post(route('register.submit'), $payload)
            ->assertSessionHasErrors('terms');
    }

    public function test_registration_records_consent_and_stores_the_captured_id(): void
    {
        Storage::fake('public');
        $payload = $this->registrationPayload($this->vacantUnit());
        $payload['data_privacy'] = '1';

        $this->post(route('register.submit'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        $user = User::where('email', $payload['email'])->firstOrFail();
        $this->assertNotNull($user->privacy_consent_at);
        $this->assertSame(config('privacy.version'), $user->privacy_consent_version);

        $app = $user->tenantApplication()->first();
        $this->assertNotNull($app);
        Storage::disk('public')->assertExists($app->valid_id_path);
    }

    public function test_landing_form_has_camera_and_privacy_consent(): void
    {
        $this->vacantUnit();

        $this->get('/')
            ->assertOk()
            ->assertSee('data-id-camera', false)
            ->assertSee('name="data_privacy"', false)
            ->assertSee('id="privacyNotice"', false)
            ->assertSee('RA 10173');
    }

    public function test_register_page_has_camera_and_privacy_consent(): void
    {
        $this->vacantUnit();

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('data-id-camera', false)
            ->assertSee('name="data_privacy"', false);
    }
}
