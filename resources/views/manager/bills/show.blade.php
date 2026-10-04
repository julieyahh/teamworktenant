{{--
    One bill, its corrections, and every payment that touched it.

    Rendered by: App\Http\Controllers\Manager\UtilityBillController@show
    Route:       GET /manager/bills/{bill}   (manager.bills.show)
    Receives:    $bill  (with tenant, lease.unit, batch.bills.lease,
                         adjustments.createdBy, allocations.payment)
    Optional:    $occupants  (live occupant count for the room; falls back to
                             lease.unit.no_of_occupants)

    v20: setting the actual amount REWRITES the bill's amount (and its kWh
    reading for metered electricity). The estimate the tenant first saw is
    kept in estimated_amount / estimated_kwh and shown here, and the change is
    listed under Corrections as an "Estimate → actual" history line (it does
    not add to the total a second time). Manual corrections still stack on top.

    v3: an estimated ELECTRICITY bill is finalised by the actual METER READING
    (kWh), exactly like issuing it. The form also posts a hidden `actual_amount`
    (kWh x rate / occupants) filled in by the script below for the preview;
    the server recomputes it from the reading (UtilityBillController@reconcile)
    and ignores the hidden value. Water / Other / Repair bills, and shared-meter (batch) bills, are
    still finalised by a typed peso amount.
--}}
@extends('layouts.managerdashboardlayout')

@section('title', 'Pinikitan Rental | Bill #' . $bill->id)
@section('page-title', 'Bill #' . $bill->id)

@push('styles')
    @include('partials.manager-ui')
@endpush

@section('content')

    @foreach(['success', 'error', 'info'] as $key)
        @if(session($key))
            <div class="pr-alert pr-alert--{{ $key === 'error' ? 'error' : ($key === 'info' ? 'info' : 'success') }}">
                <i class="bi bi-info-circle-fill pr-alert__icon"></i>
                <div>{{ session($key) }}</div>
            </div>
        @endif
    @endforeach

    @if($errors->any())
        <div class="pr-alert pr-alert--error">
            <i class="bi bi-exclamation-octagon-fill pr-alert__icon"></i>
            <div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
        </div>
    @endif

    @if($bill->isVoid())
        <div class="pr-alert pr-alert--warning">
            <i class="bi bi-slash-circle pr-alert__icon"></i>
            <div>
                <strong>This bill was voided</strong>
                on {{ $bill->voided_at?->format('M d, Y') }}.
                Reason: {{ $bill->void_reason ?: 'not recorded' }}.
                It is kept visible on purpose — a tenant who remembers the charge
                should be able to see what happened to it.
            </div>
        </div>
    @endif

    {{-- Summary --}}
    <div class="pr-card">
        <div class="pr-card__header">
            <h3 class="pr-card__title">
                {{ $bill->billing_type }} — {{ $bill->billing_month }}
            </h3>
            <span class="pr-badge pr-badge--{{ $bill->status === 'paid' ? 'paid' : ($bill->status === 'partial' ? 'partial' : ($bill->isVoid() ? 'void' : 'unpaid')) }}">
                {{ $bill->statusLabel() }}
            </span>
        </div>
        <div class="pr-card__body">
            <div class="pr-dl">
                <div>
                    <div class="pr-dl__label">Tenant</div>
                    <div class="pr-dl__value">
                        {{ trim(($bill->tenant->first_name ?? '') . ' ' . ($bill->tenant->last_name ?? '')) ?: '—' }}
                    </div>
                </div>
                <div>
                    <div class="pr-dl__label">Room</div>
                    <div class="pr-dl__value">{{ $bill->lease->room_no ?? ($bill->lease->unit->room_no ?? 'N/A') }}</div>
                </div>
                <div>
                    <div class="pr-dl__label">Issued</div>
                    <div class="pr-dl__value">{{ $bill->created_at?->format('M d, Y') ?? '—' }}</div>
                </div>
                <div>
                    <div class="pr-dl__label">Due</div>
                    <div class="pr-dl__value">
                        {{ $bill->due_date?->format('M d, Y') ?? '—' }}
                        @if($bill->isOverdue())
                            <span class="pr-badge pr-badge--overdue">{{ $bill->daysOverdue() }} days late</span>
                        @endif
                    </div>
                </div>
                @if($bill->kwh_reading)
                    <div>
                        <div class="pr-dl__label">Meter reading{{ $bill->wasReconciled() && $bill->estimated_kwh ? ' (actual)' : '' }}</div>
                        <div class="pr-dl__value">
                            {{ number_format((float) $bill->kwh_reading, 2) }} kWh
                            <span style="font-weight:500;color:var(--pr-gray-500);">
                                @ PHP {{ number_format((float) $bill->kwh_rate, 2) }}
                            </span>
                            @if($bill->wasReconciled() && $bill->estimated_kwh && abs((float) $bill->estimated_kwh - (float) $bill->kwh_reading) >= 0.01)
                                <div style="font-size:.78rem;font-weight:500;color:var(--pr-gray-500);">
                                    estimated {{ number_format((float) $bill->estimated_kwh, 2) }} kWh
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <hr style="margin:20px 0;border:0;border-top:1px solid rgba(12,26,58,.07);">

            <div class="pr-dl">
                <div>
                    <div class="pr-dl__label">{{ $bill->wasReconciled() ? 'Bill amount (actual)' : ($bill->isUnreconciledEstimate() ? 'Bill amount (estimate)' : 'Bill amount') }}</div>
                    <div class="pr-dl__value">PHP {{ number_format((float) $bill->amount, 2) }}</div>
                    @if($bill->wasReconciled() && abs((float) $bill->estimated_amount - (float) $bill->amount) >= 0.01)
                        <div style="font-size:.78rem;color:var(--pr-gray-500);">
                            estimate was PHP {{ number_format((float) $bill->estimated_amount, 2) }}
                        </div>
                    @endif
                </div>
                <div>
                    <div class="pr-dl__label">Corrections</div>
                    <div class="pr-dl__value">
                        {{ $bill->adjustmentTotal() >= 0 ? '+' : '−' }}
                        PHP {{ number_format(abs($bill->adjustmentTotal()), 2) }}
                    </div>
                </div>
                <div>
                    <div class="pr-dl__label">Total due</div>
                    <div class="pr-dl__value" style="font-weight:700;">PHP {{ number_format($bill->totalDue(), 2) }}</div>
                </div>
                <div>
                    <div class="pr-dl__label">Paid</div>
                    <div class="pr-dl__value">PHP {{ number_format((float) $bill->amount_paid, 2) }}</div>
                </div>
                <div>
                    <div class="pr-dl__label">Outstanding</div>
                    <div class="pr-dl__value" style="font-weight:700;color:{{ $bill->outstanding() > 0 ? '#b91c1c' : '#166534' }};">
                        PHP {{ number_format($bill->outstanding(), 2) }}
                    </div>
                </div>
            </div>

            @if($bill->notes)
                <div style="margin-top:18px;">
                    <div class="pr-dl__label">Notes</div>
                    <div class="pr-dl__value" style="white-space:pre-line;">{{ $bill->notes }}</div>
                </div>
            @endif

            @if($bill->file_path)
                <div style="margin-top:18px;">
                    <div class="pr-dl__label">Provider bill</div>
                    <img src="{{ asset('storage/' . $bill->file_path) }}" alt="Provider bill"
                         style="max-width:100%;border-radius:12px;border:1px solid #e5e7eb;margin-top:8px;">
                </div>
            @endif
        </div>
    </div>

    {{-- Shared split explanation --}}
    @if($bill->batch)
        <div class="pr-card">
            <div class="pr-card__header">
                <h3 class="pr-card__title"><i class="bi bi-diagram-3 me-2" style="color:var(--pr-gold);"></i>How this share was worked out</h3>
            </div>
            <div class="pr-card__body">
                <p style="margin-top:0;">{{ $bill->batch->splitExplanation() }}</p>

                <div class="pr-table-wrap">
                    <table class="pr-table">
                        <thead>
                            <tr>
                                <th>Room</th>
                                <th class="num">Basis</th>
                                <th class="num">Share</th>
                                <th class="num">Rounding</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bill->batch->bills as $sibling)
                                <tr style="{{ $sibling->id === $bill->id ? 'background:rgba(201,168,76,.08);' : '' }}">
                                    <td>
                                        {{ $sibling->lease->room_no ?? 'N/A' }}
                                        @if($sibling->id === $bill->id)<strong>(this bill)</strong>@endif
                                    </td>
                                    <td class="num">{{ rtrim(rtrim((string) $sibling->share_basis_value, '0'), '.') ?: '—' }}</td>
                                    <td class="num">{{ number_format((float) $sibling->amount, 2) }}</td>
                                    <td class="num">
                                        {{ (float) $sibling->rounding_adjustment > 0
                                            ? '+' . number_format((float) $sibling->rounding_adjustment, 2)
                                            : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                            <tr style="font-weight:700;">
                                <td>Provider total</td>
                                <td class="num">—</td>
                                <td class="num">{{ number_format((float) $bill->batch->total_amount, 2) }}</td>
                                <td class="num">
                                    @if($bill->batch->remainder_policy === 'landlord' && (float) $bill->batch->rounding_absorbed > 0)
                                        {{ number_format((float) $bill->batch->rounding_absorbed, 2) }} absorbed
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Reconcile an estimate --}}
    @if($bill->isUnreconciledEstimate() && !$bill->isVoid())
        @php
            // Electricity bills that were issued from a meter reading are finalised by
            // the actual reading. Shared-meter (batch) bills hold a peso share of the
            // provider total, so a per-kWh figure doesn't apply to them.
            $isKwhBill   = $bill->billing_type === 'Electricity'
                           && !$bill->batch
                           && $bill->kwh_reading !== null;
            $kwhRate     = (float) ($bill->kwh_rate ?: config('billing.electricity.rate_per_kwh', 18));
            $isBedSpacer = ($bill->lease->unit->type ?? '') === 'Bed-Spacer';
            $divisor     = $isBedSpacer
                ? max(1, (int) ($occupants ?? ($bill->lease->unit->no_of_occupants ?? 1)))
                : 1;
        @endphp

        <div class="pr-card">
            <div class="pr-card__header">
                <h3 class="pr-card__title"><i class="bi bi-arrow-left-right me-2" style="color:var(--pr-gold);"></i>Set the final {{ $isKwhBill ? 'meter reading' : 'amount' }}</h3>
                <span class="pr-badge pr-badge--est">Estimated</span>
            </div>
            <div class="pr-card__body">
                <div class="pr-alert pr-alert--info">
                    <i class="bi bi-info-circle-fill pr-alert__icon"></i>
                    <div>
                        <strong>The tenant cannot pay this bill yet.</strong> It is an estimate, so it shows
                        on their payments page as "awaiting final amount" with no way to pay it.
                        @if($isKwhBill)
                            Enter the <strong>actual meter reading in kWh</strong>. The peso amount is worked out
                            from the rate and the room's occupants, and the bill opens for payment straight away
                            (the tenant is notified). The bill's amount <strong>and</strong> kWh are rewritten to
                            the actual figures; the estimate of PHP {{ number_format((float) $bill->amount, 2) }}
                            ({{ number_format((float) $bill->kwh_reading, 2) }} kWh) stays on file as
                            "estimate was". If the estimate was right, enter the same reading.
                        @else
                            Enter what the provider actually charged and it opens for payment straight away
                            (the tenant is notified). The bill's amount is rewritten to the actual figure; the
                            estimate of PHP {{ number_format((float) $bill->amount, 2) }} stays on file as
                            "estimate was". If the estimate was right, enter the same figure.
                        @endif
                    </div>
                </div>

                <form method="POST" action="{{ route('manager.bills.reconcile', $bill->id) }}">
                    @csrf
                    <div class="pr-grid-2">
                        @if($isKwhBill)
                            <div class="pr-field">
                                <label>Actual meter reading (kWh)</label>
                                <input type="number" name="actual_kwh" id="final-kwh" step="0.01" min="0.01"
                                       value="{{ old('actual_kwh') }}" required>
                                {{-- Calculated from kWh x rate / occupants by the script below. Never typed. --}}
                                <input type="hidden" name="actual_amount" id="final-amount" value="{{ old('actual_amount') }}">
                                <div class="pr-field__hint" id="final-kwh-hint">
                                    PHP {{ number_format($kwhRate, 2) }} per kWh.
                                    @if($isBedSpacer)
                                        This room is Bed-Spacer with {{ $divisor }} tenant(s); the total is split between them.
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="pr-field">
                                <label>Actual amount on the provider bill (PHP)</label>
                                <input type="number" name="actual_amount" step="0.01" min="0"
                                       value="{{ old('actual_amount') }}" required>
                            </div>
                        @endif
                        <div class="pr-field">
                            <label>Reason (optional)</label>
                            <input type="text" name="reason" value="{{ old('reason') }}"
                                   placeholder="{{ $isKwhBill ? 'e.g. Actual meter reading taken' : 'e.g. Actual Meralco bill received' }}">
                        </div>
                    </div>
                    <button type="submit" class="pr-btn pr-btn--navy"><i class="bi bi-check2"></i> Set final {{ $isKwhBill ? 'reading' : 'amount' }} &amp; open for payment</button>
                </form>
            </div>
        </div>
    @endif

    {{-- Adjustment history --}}
    <div class="pr-card">
        <div class="pr-card__header">
            <h3 class="pr-card__title"><i class="bi bi-pencil-square me-2" style="color:var(--pr-gold);"></i>Corrections</h3>
            <span class="pr-card__count">{{ $bill->adjustments->count() }}</span>
        </div>

        @if($bill->adjustments->isEmpty())
            <div class="pr-empty">No corrections. The bill stands as issued.</div>
        @else
            <div class="pr-table-wrap">
                <table class="pr-table">
                    <thead>
                        <tr><th>When</th><th>Type</th><th class="num">Amount</th><th>Reason</th><th>By</th></tr>
                    </thead>
                    <tbody>
                        @foreach($bill->adjustments->sortBy('created_at') as $adj)
                            <tr>
                                <td>{{ $adj->created_at?->format('M d, Y') }}</td>
                                <td>
                                    <span class="pr-badge pr-badge--{{ $adj->isReconciliation() ? 'est' : ($adj->isCredit() ? 'paid' : 'unpaid') }}">
                                        {{ $adj->label() }}
                                    </span>
                                </td>
                                <td class="num">
                                    {{ $adj->isCredit() ? '−' : '+' }} {{ number_format(abs((float) $adj->amount), 2) }}
                                    @if($adj->isReconciliation())
                                        <div style="font-size:.7rem;color:var(--pr-gray-500);">already in bill amount</div>
                                    @endif
                                </td>
                                <td style="white-space:normal;">{{ $adj->reason }}</td>
                                <td>
                                    {{ trim(($adj->createdBy->first_name ?? '') . ' ' . ($adj->createdBy->last_name ?? '')) ?: 'System' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Payments against this bill --}}
    <div class="pr-card">
        <div class="pr-card__header">
            <h3 class="pr-card__title"><i class="bi bi-cash-stack me-2" style="color:var(--pr-gold);"></i>Payments applied</h3>
            <span class="pr-card__count">{{ $bill->allocations->whereNull('reversed_at')->count() }} live</span>
        </div>

        @if($bill->allocations->isEmpty())
            <div class="pr-empty">Nothing has been paid against this bill yet.</div>
        @else
            <div class="pr-table-wrap">
                <table class="pr-table">
                    <thead>
                        <tr><th>Date</th><th>Reference</th><th>Method</th><th class="num">Applied</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach($bill->allocations->sortBy('created_at') as $alloc)
                            <tr style="{{ $alloc->reversed_at ? 'opacity:.55;' : '' }}">
                                <td>{{ $alloc->payment?->pay_date?->format('M d, Y') ?? $alloc->created_at?->format('M d, Y') }}</td>
                                <td>{{ $alloc->payment->reference_number ?? '—' }}</td>
                                <td>{{ $alloc->payment->pay_method ?? '—' }}</td>
                                <td class="num">{{ number_format((float) $alloc->amount, 2) }}</td>
                                <td>
                                    @if($alloc->reversed_at)
                                        <span class="pr-badge pr-badge--void">Reversed</span>
                                        <div style="font-size:.72rem;color:var(--pr-gray-500);">{{ $alloc->reversal_reason }}</div>
                                    @else
                                        <span class="pr-badge pr-badge--paid">Applied</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Manual actions --}}
    @if(!$bill->isVoid())
        <div class="pr-card">
            <div class="pr-card__header">
                <h3 class="pr-card__title"><i class="bi bi-tools me-2" style="color:var(--pr-gold);"></i>Corrections and void</h3>
            </div>
            <div class="pr-card__body">
                <form method="POST" action="{{ route('manager.bills.adjust', $bill->id) }}" style="margin-bottom:26px;">
                    @csrf
                    <div class="pr-grid-2">
                        <div class="pr-field">
                            <label>Amount (PHP)</label>
                            <input type="number" name="amount" step="0.01" required placeholder="Negative for a credit">
                            <div class="pr-field__hint">Positive adds a charge; negative credits the tenant.</div>
                        </div>
                        <div class="pr-field">
                            <label>Type</label>
                            <select name="type" required>
                                <option value="correction">Correction</option>
                                <option value="goodwill">Goodwill</option>
                                <option value="rounding">Rounding</option>
                            </select>
                        </div>
                    </div>
                    <div class="pr-field">
                        <label>Reason (shown to the tenant)</label>
                        <textarea name="reason" rows="2" required></textarea>
                    </div>
                    <button type="submit" class="pr-btn pr-btn--gold">Record correction</button>
                </form>

                @php
                    $openSiblings = $bill->batch
                        ? $bill->batch->bills->where('id', '!=', $bill->id)->reject(fn ($b) => $b->isVoid())->count()
                        : 0;
                    $linkedRepair = $bill->billing_type === 'Repair'
                        ? ($bill->maintenance_request_id
                            ?: (preg_match('/Maintenance request #(\d+)/', (string) $bill->notes, $mm) ? (int) $mm[1] : null))
                        : null;
                @endphp
                <form method="POST" action="{{ route('manager.bills.void', $bill->id) }}"
                      onsubmit="return confirm('Void this bill? It stays visible to the tenant, marked void, and everything tied to it is updated.');">
                    @csrf
                    <div class="pr-field">
                        <label>Void this bill — reason</label>
                        <input type="text" name="void_reason" required placeholder="e.g. Issued to the wrong room">
                        <div class="pr-field__hint">
                            Only possible while nothing has been paid against it. If money has already
                            landed, post a credit correction instead. Voiding also: re-syncs the lease's
                            utility balance, notifies the tenant the charge is cancelled, and notes it on
                            any open demand notice for this lease.
                            @if($linkedRepair)
                                <br><strong>Repair bill:</strong> maintenance request #{{ $linkedRepair }} goes back to
                                "not yet billed" — its costs unlock and it can be charged again.
                            @endif
                        </div>
                    </div>
                    @if($openSiblings > 0)
                        <label style="display:flex;gap:8px;align-items:flex-start;font-size:.85rem;margin-bottom:14px;cursor:pointer;">
                            <input type="hidden" name="void_batch" value="0">
                            <input type="checkbox" name="void_batch" value="1" checked style="margin-top:3px;">
                            <span>
                                Also void the other {{ $openSiblings }} share(s) of this shared bill.
                                Untick if only this tenant's share was wrong. Shares with money on them are skipped.
                            </span>
                        </label>
                    @endif
                    <button type="submit" class="pr-btn pr-btn--danger"
                            {{ (float) $bill->amount_paid > 0 ? 'disabled' : '' }}>
                        <i class="bi bi-slash-circle"></i> Void bill
                    </button>
                </form>
            </div>
        </div>
    @endif

    <a href="{{ route('manager.bills.index') }}" class="pr-btn pr-btn--outline">← Back to bills</a>

@endsection

@push('scripts')
@if($bill->isUnreconciledEstimate() && !$bill->isVoid() && ($isKwhBill ?? false))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const RATE        = {{ $kwhRate }};
        const DIVISOR     = {{ $divisor }};
        const IS_BEDSPACE = {{ $isBedSpacer ? 'true' : 'false' }};
        const ORIGINAL    = {{ (float) $bill->amount }};

        const kwhInput    = document.getElementById('final-kwh');
        const amountInput = document.getElementById('final-amount');   // hidden, calculated
        const hint        = document.getElementById('final-kwh-hint');
        if (!kwhInput || !hint) return;

        function fmt(n) {
            return n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // The hidden actual_amount is informational only — the server recomputes the
        // billed amount from the reading, the rate and the room's occupants, so a
        // tampered value here can never be what gets billed.
        function update() {
            const kwh   = parseFloat(kwhInput.value || 0);
            let text    = 'PHP ' + fmt(RATE) + ' per kWh.';

            if (kwh > 0) {
                const total = kwh * RATE;
                const share = total / DIVISOR;
                const diff  = share - ORIGINAL;

                if (amountInput) amountInput.value = (Math.round(share * 100) / 100).toFixed(2);

                text += ' ' + fmt(kwh) + ' kWh = PHP ' + fmt(total) + '.';
                if (IS_BEDSPACE) {
                    text += ' Bed-Spacer with ' + DIVISOR + ' tenant(s) — this lease is billed PHP ' + fmt(share) + '.';
                }
                text += ' Estimate was PHP ' + fmt(ORIGINAL) + ' → '
                     + (Math.abs(diff) < 0.005
                          ? 'no change.'
                          : (diff > 0 ? '+' : '−') + 'PHP ' + fmt(Math.abs(diff)) + ' correction.');
            } else {
                if (amountInput) amountInput.value = '';
                if (IS_BEDSPACE) {
                    text += ' This room is Bed-Spacer with ' + DIVISOR + ' tenant(s); the total is split between them.';
                }
            }

            hint.textContent = text;
        }

        kwhInput.addEventListener('input', update);
        update();
    });
</script>
@endif
@endpush