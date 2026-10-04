{{--
    The utility bill ledger.

    Rendered by: App\Http\Controllers\Manager\UtilityBillController@index
    Route:       GET /manager/bills   (manager.bills.index)
    Receives:    $bills (paginated), $leases (billable), $summary

    This is the bill-level view. /manager/utilities shows one rolled-up number
    per room; this shows the individual bills behind it, which is where partial
    payments, arrears and unreconciled estimates actually become visible.
--}}
@extends('layouts.managerdashboardlayout')

@section('title', 'Pinikitan Rental | Utility Bills')
@section('page-title', 'Utility Bills')

@push('styles')
    @include('partials.manager-ui')
@endpush

@section('content')

    @foreach(['success' => 'check-circle-fill', 'error' => 'exclamation-octagon-fill', 'info' => 'info-circle-fill'] as $key => $icon)
        @if(session($key))
            <div class="pr-alert pr-alert--{{ $key === 'error' ? 'error' : ($key === 'info' ? 'info' : 'success') }}">
                <i class="bi bi-{{ $icon }} pr-alert__icon"></i>
                <div>
                    {{ session($key) }}
                    @if($key === 'error' && session('duplicate_bill_id'))
                        <a href="{{ route('manager.bills.show', session('duplicate_bill_id')) }}" style="font-weight:700;margin-left:6px;">Open that bill &rarr;</a>
                    @endif
                </div>
            </div>
        @endif
    @endforeach

    @if($errors->any())
        <div class="pr-alert pr-alert--error">
            <i class="bi bi-exclamation-octagon-fill pr-alert__icon"></i>
            <div>
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="pr-dash-header">
        <div class="pr-dash-header__eyebrow">Manager</div>
        <h2 class="pr-dash-header__title">Utility Bills</h2>
        <p class="pr-dash-header__sub">
            Every bill, what has been paid against it, and what is still carried forward.
        </p>
    </div>

    {{-- Totals --}}
    <div class="pr-stats">
        <div class="pr-stat">
            <div class="pr-stat__label">Outstanding</div>
            <div class="pr-stat__value">PHP {{ number_format((float) $summary['open'], 2) }}</div>
            <div class="pr-stat__note">Across all open bills</div>
        </div>
        <div class="pr-stat">
            <div class="pr-stat__label">Of that, past due</div>
            <div class="pr-stat__value {{ $summary['arrears'] > 0 ? 'pr-stat__value--danger' : '' }}">
                PHP {{ number_format((float) $summary['arrears'], 2) }}
            </div>
            <div class="pr-stat__note">Carried forward from an earlier cycle</div>
        </div>
        <div class="pr-stat">
            <div class="pr-stat__label">Estimates awaiting final amount</div>
            <div class="pr-stat__value {{ $summary['estimates'] > 0 ? 'pr-stat__value--warn' : '' }}">
                {{ $summary['estimates'] }}
            </div>
            <div class="pr-stat__note">
                {{ $summary['estimates'] > 0
                    ? 'Tenants cannot pay these until you set the final amount'
                    : 'Nothing waiting on a provider bill' }}
            </div>
        </div>
    </div>

    {{-- Issue a bill --}}
    <div class="pr-card">
        <div class="pr-card__header">
            <h3 class="pr-card__title"><i class="bi bi-receipt me-2" style="color:var(--pr-gold);"></i>Issue a bill</h3>
            <div style="display:flex;gap:8px;">
                <button type="button" class="pr-btn pr-btn--outline pr-btn--sm" onclick="prShowBillForm('single')">One room</button>
                <button type="button" class="pr-btn pr-btn--outline pr-btn--sm" onclick="prShowBillForm('shared')">Shared meter</button>
            </div>
        </div>

        {{-- Single-lease bill --}}
        <div class="pr-card__body" id="pr-bill-single">
            <form method="POST" action="{{ route('manager.bills.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="pr-grid-2">
                    <div class="pr-field">
                        <label>Room</label>
                        <select name="lease_id" id="bill-lease" required>
                            <option value="">Select a room…</option>
                            @foreach($leases as $lease)
                                <option value="{{ $lease->id }}"
                                        data-unit-type="{{ $lease->unit->type ?? '' }}"
                                        data-occupants="{{ $occupantsByUnit[$lease->unit_id] ?? 1 }}">
                                    {{ $lease->room_no ?? ($lease->unit->room_no ?? 'Room') }}
                                    — {{ trim(($lease->tenant->first_name ?? '') . ' ' . ($lease->tenant->last_name ?? '')) ?: 'Unassigned' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pr-field">
                        <label>Type</label>
                        <select name="billing_type" id="bill-type" required>
                            <option value="Electricity">Electricity</option>
                            <option value="Water">Water</option>
                            <option value="Repair">Repair</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="pr-field">
                        <label>Billing month</label>
                        <input type="month"
                            name="billing_month"
                            value="{{ old('billing_month', now()->format('Y-m')) }}"
                            min="{{ now()->format('Y-m') }}"
                            max="{{ now()->format('Y-m') }}"
                            required>
                        <div class="pr-field__hint">
                            One {{ strtolower(implode(' / ', config('billing.one_bill_per_month_types', []))) }} bill per tenant, room and month.
                            To correct an estimate, open it and set the final amount.
                        </div>
                    </div>

                    {{-- Electricity: billed by meter reading, not typed by hand.
                         Rate and Bed-Spacer division both happen server-side
                         (config('billing.electricity.rate_per_kwh') and the
                         room's current occupant count) — this preview is a
                         convenience so the manager sees the number before
                         submitting, never the value that gets saved. --}}
                    <div class="pr-field" id="bill-kwh-field">
                        <label>Meter Reading (kWh)</label>
                        <input type="number" name="kwh_reading" id="bill-kwh" step="0.01" min="0.01">
                        <div class="pr-field__hint" id="bill-kwh-hint">
                            PHP {{ number_format((float) config('billing.electricity.rate_per_kwh', 18), 2) }} per kWh.
                        </div>
                    </div>
                    <div class="pr-field" id="bill-amount-field" style="display:none;">
                        <label>Amount (PHP)</label>
                        <input type="number" name="amount" id="bill-amount" step="0.01" min="0">
                    </div>

                    <div class="pr-field">
                        <label>Due date <span style="font-weight:500;text-transform:none;letter-spacing:0;">(optional)</span></label>
                        <input type="date" name="due_date" value="{{ old('due_date') }}">
                        <div class="pr-field__hint">
                            Leave blank to use the payment day: the {{ \App\Support\RentCalendar::ordinal(\App\Support\RentCalendar::dueOnDay()) }}
                            of the month after the billing month (e.g. a {{ now()->format('F') }} bill is due
                            {{ \App\Support\RentCalendar::billDueDate(now()->format('F Y'))->format('M j') }}).
                        </div>
                    </div>
                    <div class="pr-field">
                        <label>Provider bill photo</label>
                        <input type="file" name="file" accept="image/*">
                    </div>
                </div>

                <div class="pr-field">
                    <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;font-size:.86rem;">
                        <input type="checkbox" name="is_estimated" value="1" style="width:auto;">
                        This is an estimate — the provider bill has not arrived yet
                    </label>
                    <div class="pr-field__hint">
                        The tenant sees an estimate but <strong>cannot pay it</strong> until you open the bill
                        and set the final amount. Setting it writes an adjustment entry; the amount the
                        tenant first saw is never rewritten.
                    </div>
                </div>

                <div class="pr-field">
                    <label>Notes</label>
                    <textarea name="notes" rows="2" placeholder="Meter reading, period covered, anything the tenant should see"></textarea>
                </div>

                <button type="submit" class="pr-btn pr-btn--navy"><i class="bi bi-send"></i> Issue bill</button>
            </form>
        </div>

        {{-- Shared / prorated bill --}}
        <div class="pr-card__body" id="pr-bill-shared" style="display:none;border-top:1px solid rgba(12,26,58,.06);">
            <div class="pr-alert pr-alert--info">
                <i class="bi bi-info-circle-fill pr-alert__icon"></i>
                <div>
                    One provider bill split across several rooms. The rule and the rounding
                    policy are saved with the split, so months later you can still show a tenant
                    exactly how their share was worked out.
                </div>
            </div>

            <form method="POST" action="{{ route('manager.bills.storeShared') }}" enctype="multipart/form-data">
                @csrf

                <div class="pr-field">
                    <label>Rooms sharing this bill (pick at least two)</label>
                    <div style="max-height:190px;overflow-y:auto;border:1px solid #cbd5e1;border-radius:10px;padding:10px;">
                        @foreach($leases as $lease)
                            <label style="display:flex;align-items:center;gap:8px;padding:4px 0;text-transform:none;letter-spacing:0;font-size:.86rem;font-weight:500;color:var(--pr-gray-800);">
                                <input type="checkbox" name="lease_ids[]" value="{{ $lease->id }}" style="width:auto;">
                                {{ $lease->room_no ?? ($lease->unit->room_no ?? 'Room') }}
                                — {{ trim(($lease->tenant->first_name ?? '') . ' ' . ($lease->tenant->last_name ?? '')) ?: 'Unassigned' }}
                                <span style="color:var(--pr-gray-500);">
                                    ({{ $lease->unit->no_of_occupants ?? 1 }} occupant(s))
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="pr-grid-2">
                    <div class="pr-field">
                        <label>Type</label>
                        <select name="billing_type" required>
                            <option value="Electricity">Electricity</option>
                            <option value="Water">Water</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="pr-field">
                        <label>Billing month</label>
                        <input type="month" name="billing_month" value="{{ old('billing_month', now()->format('Y-m')) }}" required>
                        <div class="pr-field__hint">
                            One {{ strtolower(implode(' / ', config('billing.one_bill_per_month_types', []))) }} bill per tenant, room and month.
                            To correct an estimate, open it and set the final amount.
                        </div>
                    </div>
                    <div class="pr-field">
                        <label>Total on the provider bill (PHP)</label>
                        <input type="number" name="total_amount" step="0.01" min="0.01" required>
                    </div>
                    <div class="pr-field">
                        <label>Due date <span style="font-weight:500;text-transform:none;letter-spacing:0;">(optional)</span></label>
                        <input type="date" name="due_date" value="{{ old('due_date') }}">
                        <div class="pr-field__hint">
                            Leave blank to use the payment day: the {{ \App\Support\RentCalendar::ordinal(\App\Support\RentCalendar::dueOnDay()) }}
                            of the month after the billing month (e.g. a {{ now()->format('F') }} bill is due
                            {{ \App\Support\RentCalendar::billDueDate(now()->format('F Y'))->format('M j') }}).
                        </div>
                    </div>
                    <div class="pr-field">
                        <label>Split rule</label>
                        <select name="split_rule" required>
                            <option value="equal" @selected(config('billing.default_split_rule') === 'equal')>Equal — same for every room</option>
                            <option value="headcount" @selected(config('billing.default_split_rule') === 'headcount')>By headcount — per occupant</option>
                            <option value="unit_size" @selected(config('billing.default_split_rule') === 'unit_size')>By unit size — per room rate</option>
                        </select>
                    </div>
                    <div class="pr-field">
                        <label>Leftover centavo</label>
                        <select name="remainder_policy" required>
                            <option value="landlord" @selected(config('billing.rounding_remainder') === 'landlord')>Landlord absorbs it</option>
                            <option value="designated_tenant" @selected(config('billing.rounding_remainder') === 'designated_tenant')>Add to the largest share</option>
                        </select>
                        <div class="pr-field__hint">
                            Shares always round down, so no tenant is ever billed a centavo more
                            than their computed share unless you pick the second option.
                        </div>
                    </div>
                </div>

                <div class="pr-field">
                    <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;font-size:.86rem;">
                        <input type="checkbox" name="is_estimated" value="1" style="width:auto;">
                        Estimate — tenants cannot pay it until you set the final amount
                    </label>
                </div>

                <div class="pr-grid-2">
                    <div class="pr-field">
                        <label>Provider bill photo</label>
                        <input type="file" name="file" accept="image/*">
                    </div>
                    <div class="pr-field">
                        <label>Notes</label>
                        <input type="text" name="notes" placeholder="Meter reading, period covered">
                    </div>
                </div>

                <button type="submit" class="pr-btn pr-btn--navy"><i class="bi bi-diagram-3"></i> Split and issue</button>
            </form>
        </div>
    </div>

    {{-- Filters --}}
    <div class="pr-card">
        <div class="pr-card__body" style="padding-bottom:6px;">
            <form method="GET" action="{{ route('manager.bills.index') }}" class="pr-grid-2">
                <div class="pr-field">
                    <label>Status</label>
                    <select name="status" onchange="this.form.submit()">
                        <option value="">All</option>
                        <option value="arrears" @selected(request('status') === 'arrears')>Past due only</option>
                        <option value="unpaid"  @selected(request('status') === 'unpaid')>Unpaid</option>
                        <option value="partial" @selected(request('status') === 'partial')>Partially paid</option>
                        <option value="paid"    @selected(request('status') === 'paid')>Paid</option>
                        <option value="void"    @selected(request('status') === 'void')>Void</option>
                    </select>
                </div>
                <div class="pr-field">
                    <label>Type</label>
                    <select name="type" onchange="this.form.submit()">
                        <option value="">All</option>
                        @foreach(['Electricity', 'Water', 'Repair', 'Other'] as $t)
                            <option value="{{ $t }}" @selected(request('type') === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="pr-field">
                    <label>Tenant</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Name or email">
                </div>
                <div class="pr-field" style="display:flex;align-items:flex-end;gap:8px;">
                    <button type="submit" class="pr-btn pr-btn--navy">Filter</button>
                    <a href="{{ route('manager.bills.index') }}" class="pr-btn pr-btn--outline">Clear</a>
                </div>
            </form>
        </div>
    </div>

    {{-- The ledger --}}
    <div class="pr-card">
        <div class="pr-card__header">
            <h3 class="pr-card__title"><i class="bi bi-list-columns me-2" style="color:var(--pr-gold);"></i>Bills</h3>
            <span class="pr-card__count">{{ $bills->total() }} bill{{ $bills->total() !== 1 ? 's' : '' }}</span>
        </div>

        <div class="pr-table-wrap">
            <table class="pr-table">
                <thead>
                    <tr>
                        <th>Tenant / Room</th>
                        <th>Type</th>
                        <th>Month</th>
                        <th>Due</th>
                        <th class="num">Billed</th>
                        <th class="num">Paid</th>
                        <th class="num">Outstanding</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bills as $bill)
                        <tr>
                            <td>
                                <div style="font-weight:600;">
                                    {{ trim(($bill->tenant->first_name ?? '') . ' ' . ($bill->tenant->last_name ?? '')) ?: '—' }}
                                </div>
                                <div style="font-size:.76rem;color:var(--pr-gray-500);">
                                    Room {{ $bill->lease->room_no ?? ($bill->lease->unit->room_no ?? 'N/A') }}
                                    @if($bill->batch_id)
                                        · <span title="Part of a shared bill">shared</span>
                                    @endif
                                </div>
                            </td>
                            <td>{{ $bill->billing_type ?? '—' }}</td>
                            <td>{{ $bill->billing_month ?? '—' }}</td>
                            <td>
                                {{ $bill->due_date ? $bill->due_date->format('M d, Y') : '—' }}
                                @if($bill->isOverdue())
                                    <div><span class="pr-badge pr-badge--overdue">{{ $bill->daysOverdue() }}d late</span></div>
                                @endif
                            </td>
                            <td class="num">
                                {{ number_format($bill->totalDue(), 2) }}
                                @if($bill->kwh_reading)
                                    <div style="font-size:.72rem;color:var(--pr-gray-500);">
                                        {{ number_format((float) $bill->kwh_reading, 2) }} kWh
                                        @ PHP {{ number_format((float) $bill->kwh_rate, 2) }}
                                    </div>
                                @endif
                                @if($bill->wasReconciled() && abs((float) $bill->estimated_amount - (float) $bill->amount) >= 0.01)
                                    <div style="font-size:.72rem;color:var(--pr-gray-500);">
                                        est. {{ number_format((float) $bill->estimated_amount, 2) }}{{ $bill->estimated_kwh ? ' / ' . number_format((float) $bill->estimated_kwh, 2) . ' kWh' : '' }}
                                    </div>
                                @endif
                                @if(abs($bill->adjustmentTotal()) >= 0.01)
                                    <div style="font-size:.72rem;color:var(--pr-gray-500);">
                                        ({{ number_format((float) $bill->amount, 2) }}
                                        {{ $bill->adjustmentTotal() > 0 ? '+' : '−' }}
                                        {{ number_format(abs($bill->adjustmentTotal()), 2) }} adj)
                                    </div>
                                @endif
                            </td>
                            <td class="num">{{ number_format((float) $bill->amount_paid, 2) }}</td>
                            <td class="num" style="font-weight:700;">{{ number_format($bill->outstanding(), 2) }}</td>
                            <td>
                                <span class="pr-badge pr-badge--{{ $bill->status === 'paid' ? 'paid' : ($bill->status === 'partial' ? 'partial' : ($bill->isVoid() ? 'void' : 'unpaid')) }}">
                                    {{ $bill->statusLabel() }}
                                </span>
                                @if($bill->isUnreconciledEstimate())
                                    <div style="margin-top:3px;">
                                        <span class="pr-badge pr-badge--est">
                                            {{ $bill->isStaleEstimate() ? 'Estimate — final amount overdue' : 'Estimate — not payable yet' }}
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('manager.bills.show', $bill->id) }}" class="pr-btn pr-btn--outline pr-btn--sm">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="pr-empty">No bills match that filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($bills->hasPages())
            <div style="padding:14px 22px;">{{ $bills->links() }}</div>
        @endif
    </div>

@endsection

@push('scripts')
{{-- Included ONCE per page, not per form: the partial installs a single
     document-level submit listener, so a second include would double-fire it. --}}
<script>
    function prShowBillForm(which) {
        document.getElementById('pr-bill-single').style.display = which === 'single' ? '' : 'none';
        document.getElementById('pr-bill-shared').style.display = which === 'shared' ? '' : 'none';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const RATE = {{ (float) config('billing.electricity.rate_per_kwh', 18) }};

        const typeSelect   = document.getElementById('bill-type');
        const leaseSelect  = document.getElementById('bill-lease');
        const kwhField     = document.getElementById('bill-kwh-field');
        const kwhInput     = document.getElementById('bill-kwh');
        const kwhHint      = document.getElementById('bill-kwh-hint');
        const amountField  = document.getElementById('bill-amount-field');
        const amountInput  = document.getElementById('bill-amount');

        if (!typeSelect || !leaseSelect) return;

        function fmt(n) {
            return n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // Purely a preview for the manager — the amount actually saved is
        // always computed server-side from kwh_reading, the rate in
        // config('billing.electricity.rate_per_kwh'), and the room's live
        // occupant count, so this can never be the value that gets billed.
        function updatePreview() {
            const isElectricity = typeSelect.value === 'Electricity';

            kwhField.style.display    = isElectricity ? '' : 'none';
            amountField.style.display = isElectricity ? 'none' : '';
            kwhInput.required    = isElectricity;
            amountInput.required = !isElectricity;

            if (!isElectricity) {
                kwhHint.textContent = 'PHP ' + fmt(RATE) + ' per kWh.';
                return;
            }

            const opt        = leaseSelect.selectedOptions[0];
            const isBedSpacer = opt && opt.dataset.unitType === 'Bed-Spacer';
            const occupants   = opt ? (parseInt(opt.dataset.occupants, 10) || 1) : 1;
            const kwh         = parseFloat(kwhInput.value || 0);
            const total       = kwh * RATE;

            let hint = 'PHP ' + fmt(RATE) + ' per kWh.';

            if (kwh > 0) {
                hint += ' ' + fmt(kwh) + ' kWh = PHP ' + fmt(total) + '.';

                if (isBedSpacer) {
                    hint += ' This room is Bed-Spacer with ' + occupants
                        + ' tenant(s) — this lease will be billed PHP '
                        + fmt(total / occupants) + '.';
                }
            } else if (isBedSpacer) {
                hint += ' This room is Bed-Spacer with ' + occupants
                    + ' tenant(s) — the total will be split evenly between them.';
            }

            kwhHint.textContent = hint;
        }

        typeSelect.addEventListener('change', updatePreview);
        leaseSelect.addEventListener('change', updatePreview);
        kwhInput.addEventListener('input', updatePreview);

        updatePreview();
    });
</script>
@endpush
