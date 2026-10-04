@extends('layouts.tenantdashboardlayout')

@section('title', 'Lease Management')
@section('page-title', 'Lease Management')

@push('styles')
<style>
    /* ===================== ALERTS ===================== */
    .pr-alert { display:flex; align-items:flex-start; gap:12px; padding:14px 18px; border-radius:12px; font-size:.88rem; margin-bottom:20px; animation:slideDown .3s ease; }
    @keyframes slideDown { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
    .pr-alert--success { background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; }
    .pr-alert--error   { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
    .pr-alert__icon  { font-size:1.1rem; flex-shrink:0; margin-top:1px; }
    .pr-alert__close { margin-left:auto; background:none; border:none; cursor:pointer; opacity:.5; font-size:.9rem; padding:0; transition:opacity .2s; }
    .pr-alert__close:hover { opacity:1; }

    /* ===================== PAGE HEADER ===================== */
    .pr-dash-header { margin-bottom:24px; }
    .pr-dash-header__eyebrow { font-size:.72rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:var(--pr-gold); margin-bottom:6px; }
    .pr-dash-header__title { font-family:var(--pr-font-display); font-size:1.5rem; font-weight:700; color:var(--pr-navy); margin:0 0 4px; line-height:1.2; }
    .pr-dash-header__sub { font-size:.85rem; color:var(--pr-gray-500); margin:0; }

    /* ===================== TOOLBAR ===================== */
    .pr-toolbar { display:flex; align-items:center; justify-content:flex-end; margin-bottom:20px; }

    /* ===================== BUTTONS ===================== */
    .pr-btn { display:inline-flex; align-items:center; gap:7px; padding:10px 18px; border-radius:10px; font-family:var(--pr-font-body); font-size:.85rem; font-weight:600; cursor:pointer; border:none; text-decoration:none; white-space:nowrap; transition:all .25s ease; }
    .pr-btn--navy { background:linear-gradient(135deg,var(--pr-navy),var(--pr-navy-mid)); color:var(--pr-white); }
    .pr-btn--navy:hover { color:var(--pr-white); transform:translateY(-2px); box-shadow:0 6px 18px rgba(12,26,58,.22); }
    .pr-btn--gold { background:linear-gradient(135deg,var(--pr-gold),var(--pr-gold-lt)); color:var(--pr-navy); }
    .pr-btn--gold:hover { color:var(--pr-navy); transform:translateY(-2px); box-shadow:0 6px 18px rgba(201,168,76,.3); }
    .pr-btn--outline { background:transparent; border:1.5px solid rgba(12,26,58,.15); color:var(--pr-navy); }
    .pr-btn--outline:hover { background:rgba(12,26,58,.05); color:var(--pr-navy); }
    .pr-btn--danger-outline { background:transparent; border:1.5px solid rgba(239,68,68,.3); color:#dc2626; }
    .pr-btn--danger-outline:hover { background:rgba(239,68,68,.06); color:#dc2626; }
    .pr-btn--amber-outline { background:transparent; border:1.5px solid rgba(217,119,6,.3); color:#d97706; }
    .pr-btn--amber-outline:hover { background:rgba(217,119,6,.06); color:#d97706; }
    .pr-btn--sm { padding:7px 12px; font-size:.78rem; }

    /* ===================== LEASE CARDS GRID ===================== */
    .pr-leases-grid {
        display:grid;
        grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));
        gap:20px;
    }

    /* ===================== LEASE CARD ===================== */
    .pr-lease-card {
        background:var(--pr-white); border:1px solid rgba(12,26,58,.07);
        border-radius:18px; box-shadow:0 2px 12px rgba(12,26,58,.06);
        overflow:hidden; transition:transform .25s ease, box-shadow .25s ease;
        position:relative;
    }
    .pr-lease-card:hover { transform:translateY(-4px); box-shadow:0 12px 32px rgba(12,26,58,.12); }

    /* Top accent bar — colour by status */
    .pr-lease-card::before {
        content:''; position:absolute; top:0; left:0; right:0; height:3px; border-radius:18px 18px 0 0;
    }
    .pr-lease-card--active::before   { background:linear-gradient(90deg,#16a34a,#4ade80); }
    .pr-lease-card--pending::before  { background:linear-gradient(90deg,#d97706,#fbbf24); }
    .pr-lease-card--other::before    { background:linear-gradient(90deg,var(--pr-navy),#1a2d55); }

    .pr-lease-card__header {
        display:flex; align-items:flex-start; justify-content:space-between; gap:12px;
        padding:20px 20px 14px;
    }
    .pr-lease-card__header-left { display:flex; align-items:center; gap:12px; }
    .pr-lease-card__icon {
        width:44px; height:44px; border-radius:12px;
        background:linear-gradient(135deg,var(--pr-navy),var(--pr-navy-mid));
        display:flex; align-items:center; justify-content:center;
        color:var(--pr-gold-lt); font-size:1.1rem; flex-shrink:0;
    }
    .pr-lease-card__unit { font-family:var(--pr-font-display); font-size:1rem; font-weight:700; color:var(--pr-navy); margin:0 0 2px; }
    .pr-lease-card__room { font-size:.78rem; color:var(--pr-gray-500); }
    .pr-lease-card__room span { font-weight:700; color:var(--pr-navy); }

    .pr-lease-card__body { padding:0 20px 16px; }

    .pr-lease-card__detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px; }
    .pr-lease-card__detail { background:rgba(12,26,58,.03); border:1px solid rgba(12,26,58,.06); border-radius:9px; padding:10px 12px; }
    .pr-lease-card__detail-label { font-size:.65rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--pr-gray-500); margin-bottom:3px; }
    .pr-lease-card__detail-val { font-size:.85rem; font-weight:600; color:var(--pr-navy); }

    .pr-lease-card__footer {
        padding:14px 20px;
        border-top:1px solid rgba(12,26,58,.05);
        display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;
    }

    /* Status pill */
    .pr-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:999px; font-size:.71rem; font-weight:600; }
    .pr-pill::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; }
    .pr-pill--green  { background:#f0fdf4; color:#16a34a; }
    .pr-pill--amber  { background:#fffbeb; color:#d97706; }
    .pr-pill--red    { background:#fef2f2; color:#dc2626; }
    .pr-pill--gray   { background:#f3f4f6; color:#6b7280; }
    .pr-pill--blue   { background:#eff6ff; color:#2563eb; }

    /* Move out requested notice */
    .pr-moveout-notice {
        display:flex; align-items:flex-start; gap:9px;
        background:rgba(37,99,235,.07); border:1px solid rgba(37,99,235,.18);
        border-radius:9px; padding:10px 12px; font-size:.8rem; color:#1e40af;
        margin-bottom:10px;
    }
    .pr-moveout-notice i { flex-shrink:0; margin-top:1px; }

    /* Renewal notice */
    .pr-renewal-notice {
        display:flex; align-items:flex-start; gap:9px;
        background:rgba(22,163,74,.06); border:1px solid rgba(22,163,74,.18);
        border-radius:9px; padding:10px 12px; font-size:.78rem; color:#15803d;
        margin-bottom:12px;
    }
    .pr-renewal-notice i { flex-shrink:0; margin-top:1px; }
    .pr-renewal-notice__body { flex:1; min-width:0; }
    .pr-renewal-notice__actions { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-top:8px; }
    .pr-renewal-notice__actions form { margin:0; }
    .pr-renewal-notice--requested { background:rgba(37,99,235,.06); border-color:rgba(37,99,235,.2); color:#1e40af; }
    .pr-btn--renew { background:#16a34a; color:#fff; border:none; }
    .pr-btn--renew:hover { background:#15803d; color:#fff; }

    /* Empty state */
    .pr-empty-card {
        background:var(--pr-white); border:1px solid rgba(12,26,58,.07);
        border-radius:18px; box-shadow:0 2px 12px rgba(12,26,58,.06);
        text-align:center; padding:64px 32px; color:var(--pr-gray-500);
    }
    .pr-empty-card__icon-wrap { width:72px; height:72px; border-radius:20px; background:rgba(12,26,58,.05); display:inline-flex; align-items:center; justify-content:center; font-size:1.8rem; margin-bottom:16px; opacity:.5; }
    .pr-empty-card__title { font-family:var(--pr-font-display); font-size:1.05rem; font-weight:700; color:var(--pr-navy); margin-bottom:6px; }
    .pr-empty-card__sub { font-size:.85rem; }

    /* ===================== MODAL ===================== */
    .pr-modal-content { border:0 !important; border-radius:20px !important; box-shadow:0 32px 80px rgba(12,26,58,.28) !important; overflow:hidden; }
    .pr-modal__header { display:flex; align-items:center; justify-content:space-between; padding:20px 26px; background:linear-gradient(135deg,var(--pr-navy),var(--pr-navy-mid)); border-bottom:1px solid rgba(201,168,76,.15); }
    .pr-modal__header--danger { background:linear-gradient(135deg,#dc2626,#ef4444); }
    .pr-modal__header--amber  { background:linear-gradient(135deg,#d97706,#f59e0b); }
    .pr-modal__eyebrow { font-size:.68rem; font-weight:600; letter-spacing:.14em; text-transform:uppercase; color:rgba(255,255,255,.65); margin-bottom:3px; }
    .pr-modal__title { font-family:var(--pr-font-display); font-size:1.1rem; font-weight:700; color:var(--pr-white); margin:0; }
    .pr-modal__close { width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,.12); border:none; display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,.8); cursor:pointer; transition:all .2s; }
    .pr-modal__close:hover { background:rgba(255,255,255,.22); color:#fff; }
    .pr-modal__body { padding:24px 26px; }
    .pr-modal__footer { display:flex; justify-content:flex-end; gap:10px; padding:16px 26px; border-top:1px solid rgba(12,26,58,.07); background:rgba(12,26,58,.02); }

    .pr-form-group { margin-bottom:16px; }
    .pr-form-group:last-child { margin-bottom:0; }
    .pr-form-label { display:block; font-size:.78rem; font-weight:600; color:var(--pr-navy); margin-bottom:6px; }
    .pr-required { color:#dc2626; }
    .pr-form-control { width:100%; padding:10px 14px; border:1.5px solid var(--pr-gray-200); border-radius:10px; font-family:var(--pr-font-body); font-size:.88rem; color:var(--pr-gray-800); background:var(--pr-white); outline:none; transition:border-color .2s; box-sizing:border-box; }
    .pr-form-control:focus { border-color:var(--pr-navy); box-shadow:0 0 0 3px rgba(12,26,58,.07); }
    .pr-form-control.is-invalid { border-color:#dc2626; }
    .pr-form-hint { font-size:.72rem; color:var(--pr-gray-500); margin-top:4px; }
    .pr-invalid-feedback { font-size:.78rem; color:#dc2626; margin-top:4px; }
    .pr-section-label { font-size:.72rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--pr-gold); margin-bottom:12px; display:flex; align-items:center; gap:8px; }
    .pr-section-label::after { content:''; flex:1; height:1px; background:linear-gradient(to right,rgba(201,168,76,.3),transparent); }
    .pr-notice { display:flex; align-items:flex-start; gap:10px; padding:12px 14px; border-radius:10px; font-size:.85rem; margin-bottom:16px; }
    .pr-notice--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }

    .pr-confirm-body { text-align:center; padding:8px 0; }
    .pr-confirm-icon { font-size:2.4rem; margin-bottom:14px; }

    @media (max-width:768px) {
        .pr-leases-grid { grid-template-columns:1fr; }
        .pr-lease-card__detail-grid { grid-template-columns:1fr; }
    }
</style>
@endpush

@section('content')

    {{-- Alerts --}}
    {{-- @if(session('success'))
        <div class="pr-alert pr-alert--success">
            <span class="pr-alert__icon"><i class="bi bi-check-circle-fill"></i></span>
            <span>{{ session('success') }}</span>
            <button class="pr-alert__close" onclick="this.closest('.pr-alert').remove()">✕</button>
        </div>
    @endif
    @if(session('error'))
        <div class="pr-alert pr-alert--error">
            <span class="pr-alert__icon"><i class="bi bi-exclamation-circle-fill"></i></span>
            <span>{{ session('error') }}</span>
            <button class="pr-alert__close" onclick="this.closest('.pr-alert').remove()">✕</button>
        </div>
    @endif --}}

    @if(session('success'))
        <x-alert type="success">{{ session('success') }}</x-alert>
    @endif

    @if(session('info'))
        <x-alert type="info">{{ session('info') }}</x-alert>
    @endif

    @if(session('error'))
        <x-alert type="error">{{ session('error') }}</x-alert>
    @endif

    {{-- Page header --}}
    <div class="pr-dash-header">
        <div class="pr-dash-header__eyebrow">Tenant Portal</div>
        <h2 class="pr-dash-header__title">Room Management</h2>
        <p class="pr-dash-header__sub">View and manage your active lease agreements.</p>
    </div>

    {{-- Toolbar --}}
    <div class="pr-toolbar">
        {{-- <button type="button" class="pr-btn pr-btn--navy"
                data-bs-toggle="modal" data-bs-target="#applyLeaseModal">
            <i class="bi bi-plus-circle"></i> Apply for New Unit
        </button> --}}
        @if(($unpaidDeposit ?? 0) > 0)
            {{-- Unpaid deposit blocks new applications (enforced server-side too). --}}
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;justify-content:flex-end;">
                <span class="pr-pill pr-pill--red">
                    Unpaid deposit ₱{{ number_format($unpaidDeposit, 2) }}
                </span>
                <button type="button" class="pr-btn pr-btn--navy" disabled
                        title="Settle your deposit before applying for another unit"
                        style="opacity:.55;cursor:not-allowed;">
                    <i class="bi bi-lock-fill"></i> Apply for New Unit
                </button>
                <a href="{{ route('tenant.payments') }}" class="pr-btn pr-btn--gold">Pay Deposit</a>
            </div>
        @elseif($hasPendingApplication ?? false)
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;justify-content:flex-end;">
                <span class="pr-pill pr-pill--amber">Application pending review</span>
                <button type="button" class="pr-btn pr-btn--navy" disabled
                        title="You already have an application awaiting approval"
                        style="opacity:.55;cursor:not-allowed;">
                    <i class="bi bi-hourglass-split"></i> Apply for New Unit
                </button>
            </div>
        @else
            <a href="{{ url('/') }}#units" target="_blank" class="pr-btn pr-btn--navy">Apply for New Unit</a>
        @endif
    </div>

    @if(($unpaidDeposit ?? 0) > 0)
        <div class="pr-notice pr-notice--warning" style="margin-bottom:20px;">
            <i class="bi bi-exclamation-triangle-fill" style="flex-shrink:0;"></i>
            <div>
                You have an outstanding deposit of <strong>₱{{ number_format($unpaidDeposit, 2) }}</strong>.
                New unit or room applications are unavailable until the deposit is settled.
            </div>
        </div>
    @endif

    {{-- Lease cards --}}
    @if($leases->isEmpty())
        <div class="pr-empty-card">
            <div class="pr-empty-card__icon-wrap"><i class="bi bi-house-slash"></i></div>
            <div class="pr-empty-card__title">No active leases</div>
            <div class="pr-empty-card__sub">You currently have no active leases. Click <strong>Apply for New Unit</strong> to get started.</div>
        </div>
    @else
        <div class="pr-leases-grid">
            @foreach($leases as $lease)
                @php
                    $displayStatus = ($lease->lea_status === 'active' && ($lease->deposit_balance ?? 0) > 0)
                        ? 'Pending'
                        : ucfirst($lease->lea_status);

                    $statusPill = match(strtolower($displayStatus)) {
                        'active'      => 'pr-pill--green',
                        'pending'     => 'pr-pill--amber',
                        'terminated'  => 'pr-pill--red',
                        default       => 'pr-pill--gray',
                    };

                    $cardClass = match(strtolower($displayStatus)) {
                        'active'  => 'pr-lease-card--active',
                        'pending' => 'pr-lease-card--pending',
                        default   => 'pr-lease-card--other',
                    };

                    $canAct = in_array($lease->lea_status, ['active','pending']);
                @endphp

                <div class="pr-lease-card {{ $cardClass }}">

                    {{-- Card header --}}
                    <div class="pr-lease-card__header">
                        <div class="pr-lease-card__header-left">
                            <div class="pr-lease-card__icon">
                                <i class="bi bi-house-fill"></i>
                            </div>
                            <div>
                                <h4 class="pr-lease-card__unit">{{ $lease->unit->unit_type ?? $lease->unit->type ?? 'Unit' }}</h4>
                                <div class="pr-lease-card__room">
                                    Room <span>{{ $lease->unit->room_no ?? 'N/A' }}</span>
                                    @if($lease->bed_number)
                                        · Bed <span>{{ $lease->bed_number }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <span class="pr-pill {{ $statusPill }}">{{ $displayStatus }}</span>
                    </div>

                    {{-- Card body --}}
                    <div class="pr-lease-card__body">

                        {{-- Move out notice --}}
                        @if($lease->move_out_requested)
                            <div class="pr-moveout-notice">
                                <i class="bi bi-info-circle-fill"></i>
                                Move out request submitted — pending manager approval.
                            </div>
                        @endif

                        {{-- Renewal (v19): the tenant asks; the office can only renew once they have. --}}
                        @if($lease->lea_status === 'active' && $lease->lea_end_date && !$lease->move_out_requested)
                            @php $opensOn = $lease->renewalRequestOpensOn(); @endphp
                            <div class="pr-renewal-notice {{ $lease->renewal_requested ? 'pr-renewal-notice--requested' : '' }}">
                                <i class="bi {{ $lease->renewal_requested ? 'bi-hourglass-split' : 'bi-arrow-repeat' }}"></i>
                                <div class="pr-renewal-notice__body">
                                    @if($lease->renewal_requested)
                                        <strong>Renewal requested</strong>
                                        @if($lease->renewal_requested_at) on {{ $lease->renewal_requested_at->format('M d, Y') }}@endif
                                        — waiting for the office. On renewal your rent becomes
                                        <strong>₱{{ number_format($lease->nextRenewalRent(), 2) }}</strong>/month (+5%).
                                        <div class="pr-renewal-notice__actions">
                                            <form method="POST" action="{{ route('tenant.leases.cancelRenewal', $lease->id) }}"
                                                  onsubmit="return confirm('Withdraw your renewal request?');">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="pr-btn pr-btn--outline pr-btn--sm">
                                                    <i class="bi bi-x-circle"></i> Withdraw request
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        Lease ends <strong>{{ \Carbon\Carbon::parse($lease->lea_end_date)->format('M d, Y') }}</strong>.
                                        If you renew, rent increases 5% to
                                        <strong>₱{{ number_format($lease->nextRenewalRent(), 2) }}</strong>/month.
                                        <div class="pr-renewal-notice__actions">
                                            @if($lease->canRequestRenewal())
                                                <form method="POST" action="{{ route('tenant.leases.requestRenewal', $lease->id) }}"
                                                      onsubmit="return confirm('Ask the office to renew this lease for another year at ₱{{ number_format($lease->nextRenewalRent(), 2) }}/month?');">
                                                    @csrf
                                                    <button type="submit" class="pr-btn pr-btn--renew pr-btn--sm">
                                                        <i class="bi bi-arrow-repeat"></i> Request Renewal
                                                    </button>
                                                </form>
                                            @elseif($opensOn && $opensOn->isFuture())
                                                <span style="font-size:.74rem;opacity:.85;">
                                                    <i class="bi bi-calendar-event"></i>
                                                    You can request a renewal from {{ $opensOn->format('M d, Y') }}.
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- Details grid --}}
                        <div class="pr-lease-card__detail-grid">
                            <div class="pr-lease-card__detail">
                                <div class="pr-lease-card__detail-label">Monthly Rent</div>
                                <div class="pr-lease-card__detail-val" style="color:#16a34a;">
                                    ₱{{ number_format($lease->currentRent(), 2) }}
                                </div>
                                @if(($lease->renewal_count ?? 0) > 0)
                                    <div style="font-size:.68rem;color:var(--pr-gray-500);margin-top:2px;">
                                        After {{ $lease->renewal_count }} renewal{{ $lease->renewal_count > 1 ? 's' : '' }}
                                    </div>
                                @endif
                            </div>
                            <div class="pr-lease-card__detail">
                                <div class="pr-lease-card__detail-label">Lease Start</div>
                                <div class="pr-lease-card__detail-val">
                                    {{ $lease->lea_start_date ? \Carbon\Carbon::parse($lease->lea_start_date)->format('M d, Y') : 'N/A' }}
                                </div>
                            </div>
                            <div class="pr-lease-card__detail">
                                <div class="pr-lease-card__detail-label">Lease End</div>
                                <div class="pr-lease-card__detail-val">
                                    {{ $lease->lea_end_date ? \Carbon\Carbon::parse($lease->lea_end_date)->format('M d, Y') : 'N/A' }}
                                </div>
                            </div>
                            <div class="pr-lease-card__detail">
                                <div class="pr-lease-card__detail-label">Deposit Balance</div>
                                <div class="pr-lease-card__detail-val" style="{{ ($lease->deposit_balance ?? 0) > 0 ? 'color:#dc2626;' : 'color:#16a34a;' }}">
                                    ₱{{ number_format($lease->deposit_balance ?? 0, 2) }}
                                </div>
                            </div>
                        </div>

                    </div>

                    {{-- Card footer --}}
                    @if($canAct)
                        <div class="pr-lease-card__footer">
                            @if($lease->move_out_requested)
                                <span style="font-size:.78rem;color:var(--pr-gray-500);">Move out pending review</span>
                                <button type="button"
                                        class="pr-btn pr-btn--amber-outline pr-btn--sm cancel-moveout-btn"
                                        data-bs-toggle="modal" data-bs-target="#cancelMoveOutModal"
                                        data-lease-id="{{ $lease->id }}">
                                    <i class="bi bi-x-circle"></i> Cancel Move Out
                                </button>
                            @elseif($lease->lea_status === 'pending')
                                <span style="font-size:.78rem;color:var(--pr-gray-500);">Awaiting manager approval</span>
                                <button type="button"
                                        class="pr-btn pr-btn--danger-outline pr-btn--sm cancel-addUnit-btn"
                                        data-bs-toggle="modal" data-bs-target="#cancelAddUnitModal"
                                        data-lease-id="{{ $lease->id }}">
                                    <i class="bi bi-x-circle"></i> Cancel Application
                                </button>
                            @else
                                <span style="font-size:.78rem;color:var(--pr-gray-500);">Lease is active</span>
                                <button type="button"
                                        class="pr-btn pr-btn--danger-outline pr-btn--sm request-moveout-btn"
                                        data-bs-toggle="modal" data-bs-target="#moveOutModal"
                                        data-lease-id="{{ $lease->id }}">
                                    <i class="bi bi-door-open"></i> Request Move Out
                                </button>
                            @endif
                        </div>
                    @endif

                </div>
            @endforeach
        </div>
    @endif

@endsection

@section('modals')

{{-- ============================================================ --}}
{{-- APPLY FOR NEW UNIT MODAL --}}
{{-- ============================================================ --}}
<div class="modal fade" id="applyLeaseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content pr-modal-content">
            <form action="{{ route('tenant.leases.store') }}" method="POST">
                @csrf
                <div class="pr-modal__header">
                    <div>
                        <div class="pr-modal__eyebrow">Lease Management</div>
                        <h5 class="pr-modal__title"><i class="bi bi-plus-circle me-2"></i>Apply for New Unit</h5>
                    </div>
                    <button type="button" class="pr-modal__close" data-bs-dismiss="modal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="pr-modal__body">
                    <div class="pr-section-label">Room Selection</div>

                    <div class="pr-form-group">
                        <label class="pr-form-label">Select Room <span class="pr-required">*</span></label>
                        <select name="unit_id" id="unit_id"
                                class="pr-form-control @error('unit_id') is-invalid @enderror" required>
                            <option value="">Select a room…</option>
                            @foreach($availableUnits as $unit)
                                @php
                                    $takenBeds = ($unit->taken_beds ?? collect())->values();
                                    if ($unit->type === 'Bed-Spacer') {
                                        $displayCount = max($unit->no_of_occupants ?? 0, $takenBeds->count());
                                        $occupancyInfo = " ({$displayCount}/{$unit->capacity})";
                                        $isFullyBooked = $takenBeds->count() >= $unit->capacity;
                                    } else {
                                        $occupancyInfo = "";
                                        $isFullyBooked = $unit->status === 'occupied';
                                    }
                                @endphp
                                @if($unit->status === 'vacant' || ($unit->type === 'Bed-Spacer' && !$isFullyBooked))
                                    <option value="{{ $unit->id }}"
                                            data-type="{{ $unit->type }}"
                                            data-capacity="{{ $unit->capacity }}"
                                            data-taken-beds='@json($takenBeds)'
                                            {{ old('unit_id') == $unit->id ? 'selected' : '' }}>
                                        {{ $unit->room_no }} ({{ $unit->type }}{{ $occupancyInfo }})
                                        @if($isFullyBooked) — Occupied @endif
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        @error('unit_id')
                            <div class="pr-invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Bed selection (Bed-Spacer only) --}}
                    <div class="pr-form-group d-none" id="bedSelectionGroup">
                        <label class="pr-form-label">Select Bed <span class="pr-required">*</span></label>
                        <select name="bed_number" id="bed_number"
                                class="pr-form-control @error('bed_number') is-invalid @enderror">
                            <option value="">Select a bed…</option>
                        </select>
                        @error('bed_number')
                            <div class="pr-invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="pr-section-label">Lease Details</div>

                    <div class="pr-form-group">
                        <label class="pr-form-label">Lease Start Date <span class="pr-required">*</span></label>
                        <input type="date" name="start_date"
                               class="pr-form-control @error('start_date') is-invalid @enderror"
                               required
                               min="{{ now()->toDateString() }}"
                               max="{{ now()->addWeeks(2)->toDateString() }}">
                        @error('start_date')
                            <div class="pr-invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="pr-form-hint">You can select a date within the next 2 weeks.</div>
                    </div>
                </div>
                <div class="pr-modal__footer">
                    <button type="button" class="pr-btn pr-btn--outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="pr-btn pr-btn--navy">
                        <i class="bi bi-send-fill"></i> Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- CANCEL ADD UNIT MODAL --}}
{{-- ============================================================ --}}
<div class="modal fade" id="cancelAddUnitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content pr-modal-content">
            <form method="POST" id="cancelAddUnitForm">
                @csrf @method('PATCH')
                <div class="pr-modal__header pr-modal__header--danger">
                    <div>
                        <div class="pr-modal__eyebrow">Lease Management</div>
                        <h5 class="pr-modal__title"><i class="bi bi-x-circle me-2"></i>Cancel Application</h5>
                    </div>
                    <button type="button" class="pr-modal__close" data-bs-dismiss="modal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="pr-modal__body pr-confirm-body">
                    <div class="pr-confirm-icon">⚠️</div>
                    <p style="font-size:.9rem;color:var(--pr-gray-800);margin-bottom:6px;">
                        Are you sure you want to cancel this unit application?
                    </p>
                    <p style="font-size:.78rem;color:var(--pr-gray-500);margin:0;">
                        This will remove your pending application.
                    </p>
                </div>
                <div class="pr-modal__footer" style="justify-content:center;">
                    <button type="button" class="pr-btn pr-btn--outline" data-bs-dismiss="modal">No, Keep It</button>
                    <button type="submit" class="pr-btn pr-btn--navy">
                        <i class="bi bi-x-circle-fill"></i> Yes, Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- CANCEL MOVE OUT MODAL --}}
{{-- ============================================================ --}}
<div class="modal fade" id="cancelMoveOutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content pr-modal-content">
            <form method="POST" id="cancelMoveOutForm">
                @csrf @method('PATCH')
                <div class="pr-modal__header pr-modal__header--amber">
                    <div>
                        <div class="pr-modal__eyebrow">Lease Management</div>
                        <h5 class="pr-modal__title"><i class="bi bi-x-circle me-2"></i>Cancel Move Out</h5>
                    </div>
                    <button type="button" class="pr-modal__close" data-bs-dismiss="modal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="pr-modal__body pr-confirm-body">
                    <div class="pr-confirm-icon">🏠</div>
                    <p style="font-size:.9rem;color:var(--pr-gray-800);margin-bottom:6px;">
                        Cancel your move out request?
                    </p>
                    <p style="font-size:.78rem;color:var(--pr-gray-500);margin:0;">
                        Your lease will remain active.
                    </p>
                </div>
                <div class="pr-modal__footer" style="justify-content:center;">
                    <button type="button" class="pr-btn pr-btn--outline" data-bs-dismiss="modal">No, Keep It</button>
                    <button type="submit" class="pr-btn pr-btn--navy">
                        <i class="bi bi-check-circle-fill"></i> Yes, Cancel Move Out
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- REQUEST MOVE OUT MODAL --}}
{{-- ============================================================ --}}
<div class="modal fade" id="moveOutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content pr-modal-content">
            <form id="moveOutForm" method="POST" action="">
                @csrf
                <div class="pr-modal__header pr-modal__header--danger">
                    <div>
                        <div class="pr-modal__eyebrow">Lease Management</div>
                        <h5 class="pr-modal__title"><i class="bi bi-door-open me-2"></i>Request Move Out</h5>
                    </div>
                    <button type="button" class="pr-modal__close" data-bs-dismiss="modal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="pr-modal__body">
                    <div class="pr-notice pr-notice--warning" style="margin-bottom:16px;">
                        <i class="bi bi-exclamation-triangle-fill" style="flex-shrink:0;"></i>
                        <div>Your move out request will be reviewed by the property manager before processing.</div>
                    </div>
                    <div class="pr-form-group">
                        <label class="pr-form-label">Reason for Moving Out <span class="pr-required">*</span></label>
                        <textarea name="reason" class="pr-form-control" rows="4"
                                  placeholder="Please describe your reason for moving out…" required></textarea>
                    </div>
                </div>
                <div class="pr-modal__footer">
                    <button type="button" class="pr-btn pr-btn--outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="pr-btn pr-btn--navy">
                        <i class="bi bi-send-fill"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ── Cancel Add Unit ── */
    document.getElementById('cancelAddUnitModal')?.addEventListener('show.bs.modal', function (e) {
        const leaseId = e.relatedTarget?.getAttribute('data-lease-id');
        if (leaseId) {
            document.getElementById('cancelAddUnitForm').action =
                "{{ route('tenant.cancelAddUnit', ['leaseId' => ':leaseId']) }}".replace(':leaseId', leaseId);
        }
    });

    /* ── Cancel Move Out ── */
    document.getElementById('cancelMoveOutModal')?.addEventListener('show.bs.modal', function (e) {
        const leaseId = e.relatedTarget?.getAttribute('data-lease-id');
        if (leaseId) {
            document.getElementById('cancelMoveOutForm').action =
                "{{ route('tenant.cancelMoveOut', ['leaseId' => ':leaseId']) }}".replace(':leaseId', leaseId);
        }
    });

    /* ── Request Move Out ── */
    document.getElementById('moveOutModal')?.addEventListener('show.bs.modal', function (e) {
        const leaseId = e.relatedTarget?.getAttribute('data-lease-id');
        if (leaseId) {
            document.getElementById('moveOutForm').action = `/tenant/leases/${leaseId}/request-move-out`;
        }
    });

    /* ── Bed selection (Bed-Spacer) ── */
    const unitSelect       = document.getElementById('unit_id');
    const bedSelectionGroup = document.getElementById('bedSelectionGroup');
    const bedSelect        = document.getElementById('bed_number');

    function populateBeds() {
        const opt = unitSelect?.options[unitSelect.selectedIndex];
        if (!opt || opt.dataset.type !== 'Bed-Spacer') {
            bedSelectionGroup?.classList.add('d-none');
            if (bedSelect) { bedSelect.required = false; bedSelect.innerHTML = '<option value="">Select a bed…</option>'; }
            return;
        }
        bedSelectionGroup?.classList.remove('d-none');
        if (bedSelect) bedSelect.required = true;

        const capacity   = parseInt(opt.dataset.capacity || '0', 10);
        const takenBeds  = JSON.parse(opt.dataset.takenBeds || '[]').map(Number);
        const upperCount = Math.floor(capacity / 2);

        let html = '<option value="">Select a bed…</option>';
        for (let i = 1; i <= capacity; i++) {
            if (!takenBeds.includes(i)) {
                html += `<option value="${i}">Bed ${i} — ${i <= upperCount ? 'Upper' : 'Lower'}</option>`;
            }
        }
        if (bedSelect) {
            bedSelect.innerHTML = html;
            const old = bedSelect.getAttribute('data-old-value');
            if (old && !takenBeds.includes(Number(old))) bedSelect.value = old;
            bedSelect.removeAttribute('data-old-value');
        }
    }

    if (unitSelect) {
        unitSelect.addEventListener('change', populateBeds);
        if (unitSelect.value) {
            if (bedSelect) bedSelect.setAttribute('data-old-value', "{{ old('bed_number') }}");
            populateBeds();
        }
    }
});
</script>
@endpush