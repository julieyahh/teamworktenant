@extends('layouts.tenantdashboardlayout')

@section('title', 'My Payments')
@section('page-title', 'Payments')

@push('styles')
<style>
    /* ===================== GCASH QR ===================== */
    .pr-gcash-qr { display:flex; justify-content:center; padding:16px; background:#f8f9fb; border:1.5px dashed var(--pr-gray-200); border-radius:12px; }
    .pr-gcash-qr img { max-width:220px; width:100%; height:auto; border-radius:8px; background:#fff; }

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

    /* ===================== SUMMARY CARDS ===================== */
    .pr-summary-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }
    .pr-summary-card {
        border-radius:18px; padding:26px 24px;
        display:flex; align-items:center; gap:18px;
        cursor:pointer; transition:transform .25s ease, box-shadow .25s ease;
        position:relative; overflow:hidden;
    }
    .pr-summary-card:hover { transform:translateY(-4px); box-shadow:0 14px 36px rgba(12,26,58,.18); }
    .pr-summary-card--rent    { background:linear-gradient(135deg,var(--pr-navy),var(--pr-navy-light)); }
    .pr-summary-card--utility { background:linear-gradient(135deg,#0d9488,#2dd4bf); }
    .pr-summary-card::before {
        content:''; position:absolute; inset:0;
        background-image:
            linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
        background-size:24px 24px; pointer-events:none;
    }
    .pr-summary-card__icon { width:56px; height:56px; border-radius:16px; background:rgba(255,255,255,.15); display:flex; align-items:center; justify-content:center; font-size:1.5rem; color:#fff; flex-shrink:0; position:relative; z-index:1; }
    .pr-summary-card__body { position:relative; z-index:1; }
    .pr-summary-card__label { font-size:.75rem; font-weight:600; color:rgba(255,255,255,.7); text-transform:uppercase; letter-spacing:.1em; margin-bottom:6px; }
    .pr-summary-card__value { font-family:var(--pr-font-display); font-size:1.9rem; font-weight:700; color:#fff; line-height:1; margin-bottom:4px; }
    .pr-summary-card__sub { font-size:.75rem; color:rgba(255,255,255,.6); }
    .pr-summary-card__arrow { margin-left:auto; position:relative; z-index:1; width:32px; height:32px; border-radius:8px; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.2); display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,.8); transition:all .2s; flex-shrink:0; }
    .pr-summary-card:hover .pr-summary-card__arrow { background:rgba(255,255,255,.22); color:#fff; }

    /* ===================== TABLE CARD ===================== */
    .pr-table-card { background:var(--pr-white); border:1px solid rgba(12,26,58,.07); border-radius:16px; box-shadow:0 2px 12px rgba(12,26,58,.06); overflow:hidden; }
    .pr-table-card__header { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:16px 22px; border-bottom:1px solid rgba(12,26,58,.06); }
    .pr-table-card__title { font-family:var(--pr-font-display); font-size:.98rem; font-weight:700; color:var(--pr-navy); margin:0; }
    .pr-table-wrap { overflow-x:auto; }
    .pr-table { width:100%; border-collapse:collapse; font-size:.85rem; }
    .pr-table thead th { font-size:.68rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--pr-gray-500); padding:12px 16px; border-bottom:1px solid rgba(12,26,58,.07); background:rgba(12,26,58,.02); white-space:nowrap; text-align:left; }
    .pr-table tbody td { padding:13px 16px; color:var(--pr-gray-800); border-bottom:1px solid rgba(12,26,58,.04); vertical-align:middle; }
    .pr-table tbody tr:last-child td { border-bottom:0; }
    .pr-table tbody tr:hover td { background:rgba(12,26,58,.015); }

    /* ===================== STATUS PILLS ===================== */
    .pr-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:999px; font-size:.71rem; font-weight:600; }
    .pr-pill::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; }
    .pr-pill--green { background:#f0fdf4; color:#16a34a; }
    .pr-pill--amber { background:#fffbeb; color:#d97706; }
    .pr-pill--red   { background:#fef2f2; color:#dc2626; }
    .pr-pill--gray  { background:#f3f4f6; color:#6b7280; }
    .pr-amount { font-weight:700; color:#16a34a; }
    .pr-room-no { display:inline-flex; align-items:center; gap:4px; padding:2px 9px; border-radius:999px; font-size:.75rem; font-weight:700; background:rgba(12,26,58,.07); color:var(--pr-navy); border:1px solid rgba(12,26,58,.1); }

    /* ===================== EMPTY STATE ===================== */
    .pr-empty { text-align:center; padding:48px 24px; color:var(--pr-gray-500); }
    .pr-empty__icon { font-size:2.5rem; margin-bottom:10px; opacity:.3; }
    .pr-empty__text { font-size:.88rem; }

    /* ===================== BUTTONS ===================== */
    .pr-btn { display:inline-flex; align-items:center; gap:7px; padding:10px 18px; border-radius:10px; font-family:var(--pr-font-body); font-size:.85rem; font-weight:600; cursor:pointer; border:none; text-decoration:none; white-space:nowrap; transition:all .25s ease; }
    .pr-btn--navy { background:linear-gradient(135deg,var(--pr-navy),var(--pr-navy-mid)); color:var(--pr-white); }
    .pr-btn--navy:hover { color:var(--pr-white); transform:translateY(-2px); box-shadow:0 6px 18px rgba(12,26,58,.22); }
    .pr-btn--outline { background:transparent; border:1.5px solid rgba(12,26,58,.15); color:var(--pr-navy); }
    .pr-btn--outline:hover { background:rgba(12,26,58,.05); color:var(--pr-navy); }
    .pr-btn--green { background:#16a34a; color:#fff; }
    .pr-btn--green:hover { background:#15803d; color:#fff; }
    .pr-btn--sm { padding:6px 12px; font-size:.78rem; }
    .pr-btn:disabled,[disabled] { opacity:.38; cursor:not-allowed; pointer-events:none; }

    /* ===================== MODAL ===================== */
    .pr-modal-content { border:0 !important; border-radius:20px !important; box-shadow:0 32px 80px rgba(12,26,58,.28) !important; overflow:hidden; }
    .pr-modal__header { display:flex; align-items:center; justify-content:space-between; padding:20px 26px; background:linear-gradient(135deg,var(--pr-navy),var(--pr-navy-mid)); border-bottom:1px solid rgba(201,168,76,.15); }
    .pr-modal__header--teal { background:linear-gradient(135deg,#0d9488,#2dd4bf); }
    .pr-modal__eyebrow { font-size:.68rem; font-weight:600; letter-spacing:.14em; text-transform:uppercase; color:rgba(255,255,255,.65); margin-bottom:3px; }
    .pr-modal__title { font-family:var(--pr-font-display); font-size:1.1rem; font-weight:700; color:var(--pr-white); margin:0; }
    .pr-modal__close { width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,.12); border:none; display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,.8); cursor:pointer; transition:all .2s; }
    .pr-modal__close:hover { background:rgba(255,255,255,.22); color:#fff; }
    .pr-modal__body { padding:24px 26px; }
    .pr-modal__footer { display:flex; justify-content:flex-end; gap:10px; padding:16px 26px; border-top:1px solid rgba(12,26,58,.07); background:rgba(12,26,58,.02); }

    .pr-form-group { margin-bottom:16px; }
    .pr-form-group:last-child { margin-bottom:0; }
    .pr-form-label { display:block; font-size:.78rem; font-weight:600; color:var(--pr-navy); margin-bottom:6px; }
    .pr-form-control { width:100%; padding:10px 14px; border:1.5px solid var(--pr-gray-200); border-radius:10px; font-family:var(--pr-font-body); font-size:.88rem; color:var(--pr-gray-800); background:var(--pr-white); outline:none; transition:border-color .2s; box-sizing:border-box; }
    .pr-form-control:focus { border-color:var(--pr-navy); box-shadow:0 0 0 3px rgba(12,26,58,.07); }
    .pr-form-control:disabled { background:var(--pr-gray-100); opacity:.7; }
    .pr-form-hint { font-size:.72rem; color:var(--pr-gray-500); margin-top:4px; }
    .pr-section-label { font-size:.72rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--pr-gold); margin-bottom:12px; display:flex; align-items:center; gap:8px; }
    .pr-section-label::after { content:''; flex:1; height:1px; background:linear-gradient(to right,rgba(201,168,76,.3),transparent); }
    .pr-notice { display:flex; align-items:flex-start; gap:10px; padding:12px 14px; border-radius:10px; font-size:.85rem; margin-bottom:0; }
    .pr-notice--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }

    .pr-modal-table { width:100%; border-collapse:collapse; font-size:.84rem; }
    .pr-modal-table thead th { font-size:.67rem; font-weight:700; letter-spacing:.09em; text-transform:uppercase; color:var(--pr-gray-500); padding:10px 14px; border-bottom:1px solid rgba(12,26,58,.07); background:rgba(12,26,58,.02); white-space:nowrap; }
    .pr-modal-table tbody td { padding:12px 14px; color:var(--pr-gray-800); border-bottom:1px solid rgba(12,26,58,.04); vertical-align:middle; }
    .pr-modal-table tbody tr:last-child td { border-bottom:0; }
    .pr-modal-table tbody tr:hover td { background:rgba(12,26,58,.015); }

    /* Billing type indicator */
    .pr-billing-dot { display:inline-block; width:7px; height:7px; border-radius:50%; margin-right:5px; }
    .pr-billing-dot--electricity { background:#d97706; }
    .pr-billing-dot--water       { background:#2563eb; }


    /* ===================== LISTS (v18 — replaces the tables) ===================== */
    .pl-list { list-style:none; margin:0; padding:0; }
    .pl-item {
        display:grid; grid-template-columns:42px minmax(0,1fr) auto; gap:4px 14px; align-items:center;
        padding:14px 22px; border-bottom:1px solid rgba(12,26,58,.05); transition:background .15s;
    }
    .pl-list > li:last-child .pl-item, .pl-list > .pl-item:last-child { border-bottom:0; }
    .pl-item:hover { background:rgba(12,26,58,.015); }
    .pl-icon {
        width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center;
        font-size:1.05rem; background:rgba(12,26,58,.06); color:var(--pr-navy); grid-row:span 2; align-self:start;
    }
    .pl-icon--rent     { background:rgba(12,26,58,.07);  color:var(--pr-navy); }
    .pl-icon--deposit  { background:rgba(201,168,76,.14); color:#a8842f; }
    .pl-icon--electric { background:rgba(217,119,6,.1);  color:#d97706; }
    .pl-icon--water    { background:rgba(37,99,235,.1);  color:#2563eb; }
    .pl-icon--utility  { background:rgba(13,148,136,.1); color:#0f766e; }
    .pl-icon--advance  { background:rgba(22,163,74,.1);  color:#16a34a; }
    .pl-icon--late     { background:#fef2f2; color:#dc2626; }
    .pl-main { min-width:0; }
    .pl-title { font-weight:600; color:var(--pr-navy); font-size:.9rem; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .pl-meta  { font-size:.76rem; color:var(--pr-gray-500); display:flex; align-items:center; gap:6px 12px; flex-wrap:wrap; margin-top:3px; }
    .pl-meta i { font-size:.72rem; margin-right:3px; }
    .pl-note  { font-size:.72rem; margin-top:4px; }
    .pl-side  { text-align:right; display:flex; flex-direction:column; align-items:flex-end; gap:5px; }
    .pl-amount { font-family:var(--pr-font-display); font-weight:700; font-size:1.02rem; color:var(--pr-navy); white-space:nowrap; }
    .pl-amount--green { color:#16a34a; }
    .pl-amount--amber { color:#b45309; }
    .pl-amount--red   { color:#b91c1c; }
    .pl-sub { font-size:.72rem; color:var(--pr-gray-500); white-space:nowrap; }
    .pl-foot { grid-column:2 / -1; display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-top:6px; }
    .pl-bar { flex:1; min-width:120px; max-width:260px; height:5px; border-radius:999px; background:rgba(12,26,58,.07); overflow:hidden; }
    .pl-bar span { display:block; height:100%; border-radius:999px; background:#16a34a; }
    .pl-tag { display:inline-flex; align-items:center; gap:4px; font-size:.68rem; font-weight:700; padding:2px 8px; border-radius:6px; }
    .pl-tag--late  { background:#fee2e2; color:#991b1b; }
    .pl-tag--est   { background:#ede9fe; color:#5b21b6; }
    .pl-tag--adv   { background:#dcfce7; color:#166534; }
    .pl-group { padding:9px 22px; font-size:.68rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--pr-gray-500); background:rgba(12,26,58,.025); border-bottom:1px solid rgba(12,26,58,.05); }
    .pl-kv { display:grid; grid-template-columns:repeat(auto-fit, minmax(120px, 1fr)); gap:10px; grid-column:2 / -1; margin-top:8px; }
    .pl-kv div { background:rgba(12,26,58,.03); border-radius:10px; padding:8px 11px; }
    .pl-kv dt { font-size:.64rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--pr-gray-500); margin:0; }
    .pl-kv dd { margin:2px 0 0; font-weight:700; font-size:.88rem; color:var(--pr-navy); }
    @media (max-width:576px) {
        .pl-item { grid-template-columns:36px minmax(0,1fr); padding:13px 16px; }
        .pl-icon { width:36px; height:36px; font-size:.95rem; }
        .pl-side { grid-column:2; flex-direction:row; align-items:center; justify-content:space-between; text-align:left; flex-wrap:wrap; }
        .pl-group { padding:9px 16px; }
    }

    @media (max-width:768px) {
        .pr-summary-grid { grid-template-columns:1fr; }
        .pr-summary-card__value { font-size:1.5rem; }
    }
</style>
@endpush

@section('content')

    @if(session('success'))
        <x-alert type="success">{{ session('success') }}</x-alert>
    @endif
    @if(session('error'))
        <x-alert type="error">{{ session('error') }}</x-alert>
    @endif
    @if(session('info'))
        <x-alert type="info">{{ session('info') }}</x-alert>
    @endif

    {{-- Page header --}}
    <div class="pr-dash-header">
        <div class="pr-dash-header__eyebrow">Tenant Portal</div>
        <h2 class="pr-dash-header__title">My Payments</h2>
        <p class="pr-dash-header__sub">Track your payment history and outstanding balances.</p>
    </div>

    {{-- Summary cards --}}
    @php
        $user       = auth()->user();
        $hasDeposit = $depositBalance > 0;

        /* Make Payment used to be disabled whenever ANY payment was pending,
           which meant a tenant who had submitted rent on Monday could not pay
           their electricity on Tuesday until the office got round to
           confirming the first one. Pending only means "not yet reviewed";
           it is not a reason to refuse the next payment.

           The one exception is the deposit. It gates rent and utility
           payments anyway (see Tenant\PaymentController@store), so a second
           deposit submission while the first is unreviewed is just a
           duplicate waiting to happen. */
        $hasPendingDeposit = $payments->contains(
            fn($p) => $p->pay_status === 'Pending' && $p->payment_for === 'Deposit'
        );
    @endphp

    <div class="pr-summary-grid">

        {{-- Rent / Deposit --}}
        <div class="pr-summary-card pr-summary-card--rent" onclick="viewRentBalance()" role="button" tabindex="0">
            <div class="pr-summary-card__icon"><i class="bi bi-cash-coin"></i></div>
            <div class="pr-summary-card__body">
                <div class="pr-summary-card__label">{{ $hasDeposit ? 'Pending Deposit' : 'Unpaid Rent' }}</div>
                <div class="pr-summary-card__value">₱{{ number_format($hasDeposit ? $depositBalance : $unpaidRent, 2) }}</div>
                <div class="pr-summary-card__sub">Click to view lease details</div>
            </div>
            <div class="pr-summary-card__arrow"><i class="bi bi-arrow-right" style="font-size:.85rem;"></i></div>
        </div>

        {{-- Utilities --}}
        <div class="pr-summary-card pr-summary-card--utility" onclick="viewUtilityProof()" role="button" tabindex="0">
            <div class="pr-summary-card__icon"><i class="bi bi-lightning-charge-fill"></i></div>
            <div class="pr-summary-card__body">
                <div class="pr-summary-card__label">Unpaid Utilities · {{ $unpaidUtilitiesMonth }}</div>
                <div class="pr-summary-card__value">₱{{ number_format($unpaidUtilities, 2) }}</div>
                @if(($estimatedUtilities ?? 0) > 0)
                    <div class="pr-summary-card__sub" style="color:#6d28d9;">
                        + ₱{{ number_format($estimatedUtilities, 2) }} estimated — payable once finalised
                    </div>
                @else
                    <div class="pr-summary-card__sub">Click to view billing history</div>
                @endif
            </div>
            <div class="pr-summary-card__arrow"><i class="bi bi-arrow-right" style="font-size:.85rem;"></i></div>
        </div>

    </div>

    {{-- ==========================================================
         Advance rent.

         Shown only when there is some, and kept separate from the two
         balance cards above on purpose: those say what you OWE, this says
         what you have already covered. Folding a credit into a card headed
         "Unpaid Rent" is how a tenant ends up thinking they are behind when
         they are months ahead.
         ========================================================== --}}
    @if(!empty($advanceTotal) && $advanceTotal > 0)
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:16px;padding:18px 22px;margin-bottom:24px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <div style="width:44px;height:44px;border-radius:12px;background:rgba(22,163,74,.12);display:flex;align-items:center;justify-content:center;color:#16a34a;font-size:1.2rem;flex-shrink:0;">
                <i class="bi bi-calendar2-check-fill"></i>
            </div>
            <div style="flex:1;min-width:220px;">
                <div style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#16a34a;margin-bottom:4px;">
                    Rent Paid In Advance
                </div>
                <div style="font-family:var(--pr-font-display);font-size:1.35rem;font-weight:700;color:#166534;line-height:1;">
                    &#8369;{{ number_format($advanceTotal, 2) }} on account
                </div>
                @if($advancePaidThrough)
                    <div style="font-size:.8rem;color:#166534;opacity:.8;margin-top:5px;">
                        Covered through {{ \Carbon\Carbon::parse($advancePaidThrough)->format('F d, Y') }}.
                    </div>
                @endif
            </div>
            <div style="font-size:.78rem;color:#166534;opacity:.85;max-width:340px;">
                This money is held against your rent only &mdash; it is never used to pay a
                utility bill. If you move out before it runs out, the unused part is
                returned to you.
            </div>
        </div>

        @if($advanceLeases->count() > 1)
            <div class="pr-table-card" style="margin-bottom:24px;">
                <div class="pr-table-card__header">
                    <h3 class="pr-table-card__title">
                        <i class="bi bi-list-check me-2" style="color:var(--pr-gold);font-size:.9rem;"></i>
                        Advance Rent By Room
                    </h3>
                </div>
                <ul class="pl-list">
                    @foreach($advanceLeases as $al)
                        <li class="pl-item">
                            <div class="pl-icon pl-icon--advance"><i class="bi bi-calendar2-check"></i></div>
                            <div class="pl-main">
                                <div class="pl-title">Room {{ $al->room_no ?? '—' }}</div>
                                <div class="pl-meta">
                                    <span><i class="bi bi-calendar3"></i>{{ $al->advanceMonthsCovered() }} month{{ $al->advanceMonthsCovered() === 1 ? '' : 's' }} covered</span>
                                    <span><i class="bi bi-hourglass-split"></i>Paid through {{ $al->rent_paid_through ? \Carbon\Carbon::parse($al->rent_paid_through)->format('M d, Y') : '—' }}</span>
                                </div>
                            </div>
                            <div class="pl-side">
                                <div class="pl-amount pl-amount--green">&#8369;{{ number_format($al->advanceRentBalance(), 2) }}</div>
                                <div class="pl-sub">on account</div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif

    {{-- ==========================================================
         Rent by month.

         The "Unpaid Rent" card above is a single total. This is what it is
         made of. Without it, a balance of PHP 15,000 is a number the tenant
         has to take on trust — and the first question anyone asks about a
         rent balance is which months it covers.
         ========================================================== --}}
    @if(!empty($rentLedger))
        <div class="pr-table-card" style="margin-bottom:24px;">
            <div class="pr-table-card__header">
                <h3 class="pr-table-card__title">
                    <i class="bi bi-calendar3 me-2" style="color:var(--pr-gold);font-size:.9rem;"></i>
                    Rent By Month
                </h3>
            </div>
            <ul class="pl-list">
                @foreach($rentLedger as $line)
                    @php
                        $rc   = $line['charge'];
                        $out  = $line['outstanding'];
                        $late = $out >= 0.01 && $rc->due_date && $rc->due_date->endOfDay()->isPast();
                        $amt  = (float) $rc->amount;
                        $pctPaid = $amt > 0 ? min(100, round($line['settled'] / $amt * 100)) : 100;
                    @endphp
                    <li class="pl-item">
                        <div class="pl-icon {{ $late ? 'pl-icon--late' : 'pl-icon--rent' }}"><i class="bi bi-calendar3"></i></div>
                        <div class="pl-main">
                            <div class="pl-title">
                                {{ $rc->periodLabel() }}
                                @if($late)<span class="pl-tag pl-tag--late"><i class="bi bi-exclamation-circle"></i> Overdue</span>@endif
                            </div>
                            <div class="pl-meta">
                                <span><i class="bi bi-door-open"></i>Room {{ $rc->room_no ?? '—' }}</span>
                                <span><i class="bi bi-calendar-event"></i>Due {{ $rc->due_date?->format('M d, Y') ?? '—' }}</span>
                                <span>Charged &#8369;{{ number_format($amt, 2) }}</span>
                            </div>
                            @if((float) $rc->advance_applied > 0)
                                <div class="pl-note" style="color:#16a34a;">
                                    &#8369;{{ number_format((float) $rc->advance_applied, 2) }} from advance rent
                                </div>
                            @endif
                        </div>
                        <div class="pl-side">
                            @if($out >= 0.01)
                                <div class="pl-amount {{ $late ? 'pl-amount--red' : 'pl-amount--amber' }}">&#8369;{{ number_format($out, 2) }}</div>
                                <div class="pl-sub">still owed @if($line['settled'] > 0)· &#8369;{{ number_format($line['settled'], 2) }} paid @endif</div>
                            @else
                                <span class="pr-pill pr-pill--green">Paid</span>
                                <div class="pl-sub">&#8369;{{ number_format($line['settled'], 2) }}</div>
                            @endif
                        </div>
                        @if($out >= 0.01 && $line['settled'] > 0)
                            <div class="pl-foot"><div class="pl-bar" title="{{ $pctPaid }}% paid"><span style="width:{{ $pctPaid }}%"></span></div></div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ==========================================================
         Bill ledger.

         The two summary cards above are single totals. This is what those
         totals are made of — one row per bill, showing what was billed, what
         has landed against it, and what is still owed. A bill that has been
         partly paid shows the remainder rather than disappearing or looking
         untouched.
         ========================================================== --}}
    @if(!empty($openBills) && $openBills->isNotEmpty())
        <div class="pr-table-card" style="margin-bottom:24px;">
            <div class="pr-table-card__header">
                <h3 class="pr-table-card__title">
                    <i class="bi bi-receipt me-2" style="color:var(--pr-gold);font-size:.9rem;"></i>
                    What You Owe
                </h3>
                @if(!empty($utilityArrears) && $utilityArrears > 0)
                    <span style="font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:999px;background:#fee2e2;color:#991b1b;">
                        ₱{{ number_format($utilityArrears, 2) }} past due
                    </span>
                @endif
            </div>

            @if(!empty($utilityArrears) && $utilityArrears > 0)
                <div style="padding:14px 22px;background:#fffbeb;border-bottom:1px solid rgba(12,26,58,.06);font-size:.84rem;color:#92400e;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    ₱{{ number_format($utilityArrears, 2) }} of this is carried over from an earlier
                    billing period. Payments are applied to the oldest bill first.
                    If you cannot clear it all at once, a part payment is fine — please talk to the
                    office so we can note it.
                </div>
            @endif

            <ul class="pl-list">
                @foreach($openBills as $bill)
                    @php
                        $bType   = strtolower((string) $bill->billing_type);
                        $bIcon   = $bType === 'electricity' ? 'bi-lightning-charge-fill' : ($bType === 'water' ? 'bi-droplet-fill' : 'bi-receipt');
                        $bTone   = $bill->isOverdue() ? 'pl-icon--late' : ($bType === 'electricity' ? 'pl-icon--electric' : ($bType === 'water' ? 'pl-icon--water' : 'pl-icon--utility'));
                        $isEst   = $bill->isUnreconciledEstimate();
                        $billed  = $bill->totalDue();
                        $paidAmt = (float) $bill->amount_paid;
                        $pctPaid = $billed > 0 ? min(100, round($paidAmt / $billed * 100)) : 0;
                        $bg = $isEst ? '#ede9fe' : ($bill->status === 'partial' ? '#dbeafe' : '#fef3c7');
                        $fg = $isEst ? '#5b21b6' : ($bill->status === 'partial' ? '#1e40af' : '#92400e');
                    @endphp
                    <li class="pl-item {{ $isEst ? 'pl-item--estimate' : '' }}">
                        <div class="pl-icon {{ $bTone }}"><i class="bi {{ $bIcon }}"></i></div>
                        <div class="pl-main">
                            <div class="pl-title">
                                {{ $bill->billing_type }}
                                <span style="display:inline-block;padding:2px 9px;border-radius:999px;font-size:.68rem;font-weight:700;background:{{ $bg }};color:{{ $fg }};">{{ $bill->statusLabel() }}</span>
                                @if($bill->isOverdue())<span class="pl-tag pl-tag--late">{{ $bill->daysOverdue() }} days late</span>@endif
                                @if($isEst)
                                    <span class="pl-tag pl-tag--est" title="The office will set the final amount from the provider bill. You can pay it then.">Not payable yet</span>
                                @endif
                            </div>
                            <div class="pl-meta">
                                <span><i class="bi bi-calendar3"></i>{{ $bill->billing_month }}</span>
                                @if($bill->lease)<span><i class="bi bi-door-open"></i>Room {{ $bill->lease->room_no ?? 'N/A' }}</span>@endif
                                @unless($isEst)
                                    <span><i class="bi bi-calendar-event"></i>Due {{ $bill->due_date?->format('M d, Y') ?? '—' }}</span>
                                @endunless
                            </div>
                            @if($isEst)
                                <div class="pl-note" style="font-size:.76rem;color:#5b21b6;">
                                    <i class="bi bi-info-circle"></i>
                                    This is an estimate so you know it is coming. You will be notified when the office
                                    sets the final amount from the provider bill — you can pay it then.
                                </div>
                            @endif
                        </div>
                        <div class="pl-side">
                            @if($isEst)
                                <div class="pl-amount" style="color:#7c3aed;">&#8776; &#8369;{{ number_format($bill->outstanding(), 2) }}</div>
                                <div class="pl-sub">estimate</div>
                            @else
                                <div class="pl-amount {{ $bill->isOverdue() ? 'pl-amount--red' : 'pl-amount--amber' }}">&#8369;{{ number_format($bill->outstanding(), 2) }}</div>
                                <div class="pl-sub">still owed</div>
                            @endif
                        </div>
                        <div class="pl-foot">
                            @if($paidAmt > 0)
                                <div class="pl-bar" title="{{ $pctPaid }}% paid"><span style="width:{{ $pctPaid }}%"></span></div>
                            @endif
                            <span class="pl-sub">&#8369;{{ number_format($paidAmt, 2) }} paid of &#8369;{{ number_format($billed, 2) }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Payment history (v18: list, not table) --}}
    <div class="pr-table-card">
        <div class="pr-table-card__header">
            <h3 class="pr-table-card__title">
                <i class="bi bi-clock-history me-2" style="color:var(--pr-gold);font-size:.9rem;"></i>
                Payment History
            </h3>
            <button class="pr-btn pr-btn--navy pr-btn--sm"
                    data-bs-toggle="modal" data-bs-target="#makePaymentModal"
                    title="{{ $hasPendingDeposit ? 'Your deposit payment is still being reviewed.' : '' }}"
                    {{ $leasesForSelection->isEmpty() || $hasPendingDeposit ? 'disabled' : '' }}>
                <i class="bi bi-plus-lg"></i> Make Payment
            </button>
        </div>

        @if($payments->isEmpty())
            <div class="pr-empty">
                <div class="pr-empty__icon"><i class="bi bi-receipt"></i></div>
                <div class="pr-empty__text">No payments recorded yet.</div>
            </div>
        @else
            <ul class="pl-list">
                @foreach($payments as $payment)
                    @php
                        $for   = $payment->payment_for ?? '—';
                        $uType = strtolower((string) $payment->utility_type);
                        [$pIcon, $pTone] = match (true) {
                            $for === 'Deposit'                               => ['bi-safe2-fill', 'pl-icon--deposit'],
                            $for === 'Utilities' && $uType === 'electricity' => ['bi-lightning-charge-fill', 'pl-icon--electric'],
                            $for === 'Utilities' && $uType === 'water'       => ['bi-droplet-fill', 'pl-icon--water'],
                            $for === 'Utilities'                             => ['bi-receipt', 'pl-icon--utility'],
                            default                                          => ['bi-house-fill', 'pl-icon--rent'],
                        };
                        $sPill = match($payment->pay_status) {
                            'Accepted' => 'pr-pill--green',
                            'Rejected' => 'pr-pill--red',
                            default    => 'pr-pill--amber',
                        };
                    @endphp
                    <li class="pl-item">
                        <div class="pl-icon {{ $pTone }}"><i class="bi {{ $pIcon }}"></i></div>
                        <div class="pl-main">
                            <div class="pl-title">
                                {{ $for }}
                                {{-- Column on payments is utility_type, NOT billing_type. --}}
                                @if($for === 'Utilities' && $payment->utility_type)
                                    <span style="font-size:.74rem;font-weight:600;color:{{ $payment->utility_type === 'Electricity' ? '#d97706' : '#2563eb' }};">
                                        <span class="pr-billing-dot pr-billing-dot--{{ $uType }}"></span>{{ $payment->utility_type }}
                                    </span>
                                @endif
                            </div>
                            <div class="pl-meta">
                                <span><i class="bi bi-calendar3"></i>{{ \Carbon\Carbon::parse($payment->pay_date ?? $payment->created_at)->format('M d, Y') }}</span>
                                @if($payment->lease)
                                    <span><i class="bi bi-door-open"></i>Room {{ $payment->lease->room_no ?? '—' }} · {{ $payment->lease->unit->type ?? 'Unit' }}@if($payment->lease->bed_number) · Bed {{ $payment->lease->bed_number }}@endif</span>
                                @endif
                                <span><i class="bi bi-wallet2"></i>{{ ucfirst($payment->pay_method) }}</span>
                                @if($payment->account_no)<span><i class="bi bi-hash"></i>{{ $payment->account_no }}</span>@endif
                            </div>
                            @if($payment->advanceSummary())
                                <div class="pl-note"><span class="pl-tag pl-tag--adv"><i class="bi bi-calendar2-check"></i> {{ $payment->advanceSummary() }}</span></div>
                            @endif
                        </div>
                        <div class="pl-side">
                            <div class="pl-amount pl-amount--green">₱{{ number_format($payment->pay_amount, 2) }}</div>
                            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;justify-content:flex-end;">
                                <span class="pr-pill {{ $sPill }}">{{ $payment->pay_status }}</span>
                                @if($payment->hasInvoice())
                                    <a href="{{ route('tenant.payments.invoice', $payment->id) }}"
                                       class="pr-btn pr-btn--navy pr-btn--sm" title="Download invoice {{ $payment->invoiceNumber() }}">
                                        <i class="bi bi-file-earmark-arrow-down"></i> Invoice
                                    </a>
                                @endif
                                @if($payment->proof)
                                    <a href="{{ asset('storage/'.$payment->proof) }}" target="_blank" rel="noopener"
                                       class="pr-btn pr-btn--outline pr-btn--sm"><i class="bi bi-eye"></i> Proof</a>
                                @else
                                    <span style="font-size:.72rem;color:var(--pr-gray-500);font-style:italic;">No proof</span>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

@endsection

@section('modals')

{{-- ============================================================ --}}
{{-- MAKE PAYMENT MODAL --}}
{{-- ============================================================ --}}
<div class="modal fade" id="makePaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content pr-modal-content">
            <form method="POST" action="{{ route('tenant.payments.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="pr-modal__header">
                    <div>
                        <div class="pr-modal__eyebrow">Tenant Portal</div>
                        <h5 class="pr-modal__title"><i class="bi bi-cash-coin me-2"></i>Make a Payment</h5>
                    </div>
                    <button type="button" class="pr-modal__close" data-bs-dismiss="modal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="pr-modal__body">

                    <div class="pr-section-label">Select Unit</div>

                    <div class="pr-form-group">
                        <label class="pr-form-label">Room / Unit</label>
                        <select name="lease_id" id="lease_id" class="pr-form-control"
                                {{ $leasesForSelection->isEmpty() ? 'disabled' : '' }} required>
                            <option value="">Select Room / Unit…</option>
                            
                            @foreach($leasesForSelection as $leaseOption)
                                <option value="{{ $leaseOption->id }}"
                                        data-deposit="{{ $leaseOption->deposit_balance ?? 0 }}"
                                        data-rent="{{ ($leaseOption->rent_balance ?? 0) + ($leaseOption->penalty_fee ?? 0) }}"
                                        data-electricity-amount="{{ $leaseOption->electricity_amount ?? 0 }}"
                                        data-water-amount="{{ $leaseOption->water_amount ?? 0 }}"
                                        data-estimates="{{ ($leaseOption->electricity_estimate ?? 0) + ($leaseOption->water_estimate ?? 0) }}"
                                        data-monthly-rent="{{ $leaseOption->monthly_rent_amount ?? 0 }}"
                                        data-advance-balance="{{ $leaseOption->advance_on_account ?? 0 }}"
                                        data-advance-months-left="{{ $leaseOption->advance_months_left ?? 0 }}"
                                        {{ old('lease_id') == $leaseOption->id ? 'selected' : '' }}>
                                    {{ $leaseOption->room_no ?? 'Room' }} · {{ $leaseOption->unit->type ?? 'Unit' }}
                                    @if($leaseOption->bed_number) (Bed {{ $leaseOption->bed_number }}) @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if($leasesForSelection->isEmpty())
                        <div class="pr-notice pr-notice--warning" style="margin-bottom:16px;">
                            <i class="bi bi-exclamation-triangle-fill" style="flex-shrink:0;"></i>
                            <div>No active leases linked to your account. Please contact your property manager.</div>
                        </div>
                    @endif

                    <div class="pr-section-label">Payment Details</div>

                    <div class="pr-form-group">
                        <label class="pr-form-label">Payment For</label>
                        <select name="payment_for" id="payment_for" class="pr-form-control" required disabled>
                            <option value="">Select Payment Type…</option>
                            <option value="Deposit">Deposit (Select unit first)</option>
                            <option value="Rent">Rent (Select unit first)</option>
                            <option value="Advance Rent">Advance Rent (Select unit first)</option>
                            <option value="Utilities">Utilities (Select unit first)</option>
                        </select>
                    </div>

                    {{-- Months ahead — only shown for Advance Rent.

                         The tenant picks months and the amount is filled in
                         for them, rather than typing a peso figure and hoping
                         it divides evenly. The server still derives the months
                         it actually grants from the money received, so this
                         field is a convenience, not a promise. --}}
                    <div class="pr-form-group d-none" id="advanceMonthsField">
                        <label class="pr-form-label">
                            Months To Pay Ahead <span style="color:#dc2626;">*</span>
                        </label>
                        <select name="advance_months" id="advance_months" class="pr-form-control" disabled>
                            <option value="">Select…</option>
                        </select>
                        <div class="pr-form-hint" id="advanceMonthsHint">
                            Anything you currently owe is settled first, then the rest is held
                            against your future rent.
                        </div>
                    </div>

                    {{-- ✅ Billing type — only shown when Utilities is selected --}}
                    <div class="pr-form-group d-none" id="billingTypeField">
                        <label class="pr-form-label">
                            Utility Type <span style="color:#dc2626;">*</span>
                        </label>
                        <select name="billing_type" id="billing_type" class="pr-form-control" disabled>
                            <option value="">Select Utility Type…</option>
                            <option value="Electricity">⚡ Electricity</option>
                            <option value="Water">💧 Water</option>
                        </select>
                        <div class="pr-form-hint">Select which utility bill you are paying for.</div>
                        <div class="pr-form-hint d-none" id="estimateHint" style="color:#6d28d9;">
                            <i class="bi bi-info-circle"></i>
                            Estimated bills are not listed here. You can pay them once the office sets the final amount.
                        </div>
                    </div>

                    <div class="pr-form-group">
                        <label class="pr-form-label">Amount</label>
                        <input type="number" name="pay_amount" id="pay_amount" class="pr-form-control"
                               required disabled placeholder="0.00" step="0.01" min="0">
                    </div>

                    <div class="pr-form-group">
                        <label class="pr-form-label">Payment Method</label>
                        <select name="pay_method" id="payment_method" class="pr-form-control" required disabled>
                            <option value="">Select Method…</option>
                            <option value="GCash">GCash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>

                    {{-- GCash QR — shown only when GCash is the selected method.
                         Scan-to-pay, then upload the screenshot below as proof. --}}
                    <div class="pr-form-group d-none" id="gcashQrField">
                        <label class="pr-form-label">Scan to Pay</label>
                        <div class="pr-gcash-qr">
                            <img src="{{ asset('images/payment/gcash-qr.png') }}" alt="GCash QR code">
                        </div>
                        <div class="pr-form-hint">
                            Scan this with your GCash app, complete the payment, then upload your
                            payment screenshot as proof below.
                        </div>
                    </div>

                    <div class="pr-form-group d-none" id="accountNumberField">
                        <label class="pr-form-label">Account / GCash Number</label>
                        <input type="text" name="account_no" class="pr-form-control" disabled placeholder="09XXXXXXXXX">
                    </div>

                    <div class="pr-form-group d-none" id="proofOfPaymentField">
                        <label class="pr-form-label">Proof of Payment</label>
                        <input type="file" name="proof" id="proof" class="pr-form-control" accept="image/*" disabled>
                        <div class="pr-form-hint">Upload a screenshot or receipt (JPG, PNG).</div>
                    </div>

                </div>
                <div class="pr-modal__footer">
                    <button type="button" class="pr-btn pr-btn--outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="pr-btn pr-btn--navy"
                            {{ $leasesForSelection->isEmpty() ? 'disabled' : '' }}>
                        <i class="bi bi-check-circle"></i> Submit Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- UTILITY PROOF MODAL --}}
{{-- ============================================================ --}}
<div class="modal fade" id="viewUtilityProofModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content pr-modal-content">
            <div class="pr-modal__header pr-modal__header--teal">
                <div>
                    <div class="pr-modal__eyebrow">Billing Records</div>
                    <h5 class="pr-modal__title"><i class="bi bi-receipt me-2"></i>Proof of Utility Billing</h5>
                </div>
                <button type="button" class="pr-modal__close" data-bs-dismiss="modal">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="pr-modal__body" style="padding:0;">
                @if($utilityProofs->isEmpty())
                    <div class="pr-empty" style="padding:48px 24px;">
                        <div class="pr-empty__icon"><i class="bi bi-receipt"></i></div>
                        <div class="pr-empty__text">No proof of billing records have been uploaded yet.</div>
                    </div>
                @else
                    <ul class="pl-list">
                        @foreach($utilityProofs as $proof)
                            @php
                                $uT = strtolower((string) $proof->billing_type);
                                $uIcon = $uT === 'electricity' ? 'bi-lightning-charge-fill' : ($uT === 'water' ? 'bi-droplet-fill' : 'bi-receipt');
                                $uTone = $uT === 'electricity' ? 'pl-icon--electric' : ($uT === 'water' ? 'pl-icon--water' : 'pl-icon--utility');
                            @endphp
                            <li class="pl-item">
                                <div class="pl-icon {{ $uTone }}"><i class="bi {{ $uIcon }}"></i></div>
                                <div class="pl-main">
                                    <div class="pl-title">
                                        {{ $proof->billing_type ?? '—' }} · {{ $proof->billing_month ?? '—' }}
                                        @if($proof->status === 'paid')
                                            <span class="pr-pill pr-pill--green">Paid</span>
                                        @else
                                            <span class="pr-pill pr-pill--red">Unpaid</span>
                                        @endif
                                    </div>
                                    <div class="pl-meta">
                                        <span><i class="bi bi-door-open"></i>Room {{ $proof->lease->room_no ?? '—' }}</span>
                                        <span><i class="bi bi-cloud-arrow-up"></i>Uploaded {{ $proof->created_at?->format('M d, Y h:i A') }}</span>
                                    </div>
                                </div>
                                <div class="pl-side">
                                    <div class="pl-amount">₱{{ number_format($proof->amount ?? 0, 2) }}</div>
                                    @if($proof->file_path)
                                        <a href="{{ asset('storage/'.$proof->file_path) }}" target="_blank" rel="noopener"
                                           class="pr-btn pr-btn--outline pr-btn--sm"><i class="bi bi-eye"></i> View</a>
                                    @else
                                        <span style="font-size:.72rem;color:var(--pr-gray-500);font-style:italic;">No file</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="pr-modal__footer">
                <button type="button" class="pr-btn pr-btn--outline" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- RENT BALANCE MODAL --}}
{{-- ============================================================ --}}
<div class="modal fade" id="viewRentBalanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content pr-modal-content">
            <div class="pr-modal__header">
                <div>
                    <div class="pr-modal__eyebrow">Lease Records</div>
                    <h5 class="pr-modal__title"><i class="bi bi-house-fill me-2"></i>Active Lease Details</h5>
                </div>
                <button type="button" class="pr-modal__close" data-bs-dismiss="modal">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="pr-modal__body" style="padding:0;">
                @if($tenantLeases->isEmpty())
                    <div class="pr-empty" style="padding:48px 24px;">
                        <div class="pr-empty__icon"><i class="bi bi-house-slash"></i></div>
                        <div class="pr-empty__text">No lease records found.</div>
                    </div>
                @else
                    <ul class="pl-list">
                        @foreach($tenantLeases as $lease)
                            <li class="pl-item">
                                <div class="pl-icon pl-icon--rent"><i class="bi bi-house-fill"></i></div>
                                <div class="pl-main">
                                    <div class="pl-title">Room {{ $lease->room_no ?? '—' }}</div>
                                    <div class="pl-meta">
                                        <span><i class="bi bi-building"></i>{{ $lease->unit->type ?? '—' }}</span>
                                        <span><i class="bi bi-calendar-event"></i>Due {{ $lease->due_date?->format('M d, Y') ?? '—' }}</span>
                                    </div>
                                </div>
                                <div class="pl-side">
                                    <div class="pl-amount {{ ($lease->rent_balance ?? 0) > 0 ? 'pl-amount--amber' : '' }}">₱{{ number_format($lease->rent_balance ?? 0, 2) }}</div>
                                    <div class="pl-sub">rent balance</div>
                                </div>
                                <dl class="pl-kv">
                                    <div><dt>Deposit balance</dt><dd style="{{ ($lease->deposit_balance ?? 0) > 0 ? 'color:#b45309' : 'color:var(--pr-gray-500)' }}">₱{{ number_format($lease->deposit_balance ?? 0, 2) }}</dd></div>
                                    <div><dt>Penalty fee</dt><dd style="{{ ($lease->penalty_fee ?? 0) > 0 ? 'color:#dc2626' : 'color:var(--pr-gray-500)' }}">₱{{ number_format($lease->penalty_fee ?? 0, 2) }}</dd></div>
                                    <div><dt>Rent balance</dt><dd>₱{{ number_format($lease->rent_balance ?? 0, 2) }}</dd></div>
                                </dl>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="pr-modal__footer">
                <button type="button" class="pr-btn pr-btn--outline" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ── Element refs ─────────────────────────────────────────── */
    const leaseSelect      = document.getElementById('lease_id');
    const paymentFor       = document.getElementById('payment_for');
    const payAmount        = document.getElementById('pay_amount');
    const payMethod        = document.getElementById('payment_method');
    const accountField     = document.getElementById('accountNumberField');
    const accountInput     = accountField?.querySelector('input[name="account_no"]');
    const proofField       = document.getElementById('proofOfPaymentField');
    const proofInput       = document.getElementById('proof');
    const billingTypeField  = document.getElementById('billingTypeField');
    const billingTypeSelect = document.getElementById('billing_type');
    const advanceField      = document.getElementById('advanceMonthsField');
    const advanceSelect     = document.getElementById('advance_months');
    const advanceHint       = document.getElementById('advanceMonthsHint');

    /* ── Lease data from PHP ──────────────────────────────────── */
    /*
     * We read electricity_amount and water_amount from the
     * data attributes set on each <option> in the lease select.
     * These come from UtilityBillingProof (unpaid records only).
     */
    let selectedLease = null;

    /* ── Helpers ──────────────────────────────────────────────── */
    function fmt(n) {
        return parseFloat(n || 0).toFixed(2);
    }

    function resetBillingType() {
        billingTypeField.classList.add('d-none');
        billingTypeSelect.disabled = true;
        billingTypeSelect.required = false;
        billingTypeSelect.value    = '';
    }

    function resetAmount() {
        payAmount.value    = '';
        payAmount.disabled = true;
        payAmount.readOnly = false;
    }

    function resetAdvance() {
        if (!advanceField) return;
        advanceField.classList.add('d-none');
        advanceSelect.disabled = true;
        advanceSelect.required = false;
        advanceSelect.innerHTML = '<option value="">Select…</option>';
    }

    /* Fill the months dropdown, capped at what the server will accept.
       Each option carries the total the tenant would pay for that many
       months: arrears first, then N x monthly rent. */
    function buildAdvanceOptions() {
        advanceSelect.innerHTML = '<option value="">Select…</option>';

        const rent    = selectedLease.monthlyRent;
        const arrears = selectedLease.rent;
        const cap     = selectedLease.advanceMonthsLeft;

        for (let m = 1; m <= cap; m++) {
            const total = arrears + (rent * m);
            const opt   = document.createElement('option');
            opt.value       = m;
            opt.dataset.total = total.toFixed(2);
            opt.textContent = `${m} month${m > 1 ? 's' : ''} — ₱${fmt(total)}`;
            advanceSelect.appendChild(opt);
        }

        if (advanceHint) {
            const bits = [];
            if (arrears > 0) {
                bits.push(`₱${fmt(arrears)} currently due is settled first.`);
            }
            if (selectedLease.advanceBalance > 0) {
                bits.push(`You already have ₱${fmt(selectedLease.advanceBalance)} on account.`);
            }
            bits.push(`One month is ₱${fmt(rent)}. You can go up to ${cap} month${cap > 1 ? 's' : ''} ahead.`);
            advanceHint.textContent = bits.join(' ');
        }
    }

    function showMethodAndProof() {
        payMethod.disabled = false;
        payMethod.value    = '';
    }

    /* ── Step 1: Lease selected ───────────────────────────────── */
    leaseSelect.addEventListener('change', function () {
        // Reset downstream fields
        paymentFor.value    = '';
        paymentFor.disabled = true;
        resetBillingType();
        resetAdvance();
        resetAmount();
        payMethod.disabled  = true;
        accountField.classList.add('d-none');
        proofField.classList.add('d-none');
        if (accountInput) accountInput.disabled = true;
        if (proofInput)   proofInput.disabled   = true;

        if (!this.value) {
            selectedLease = null;
            return;
        }

        const opt = this.options[this.selectedIndex];

        // Store amounts from data attributes
        selectedLease = {
            deposit:     parseFloat(opt.dataset.deposit             || 0),
            rent:        parseFloat(opt.dataset.rent                || 0),
            electricity: parseFloat(opt.dataset.electricityAmount   || 0),
            water:       parseFloat(opt.dataset.waterAmount         || 0),
            estimates:         parseFloat(opt.dataset.estimates         || 0),
            monthlyRent:       parseFloat(opt.dataset.monthlyRent       || 0),
            advanceBalance:    parseFloat(opt.dataset.advanceBalance    || 0),
            advanceMonthsLeft: parseInt(opt.dataset.advanceMonthsLeft   || 0, 10),
        };

        // Update option labels with real amounts
        const depositOpt     = paymentFor.querySelector('option[value="Deposit"]');
        const rentOpt        = paymentFor.querySelector('option[value="Rent"]');
        const advanceOpt     = paymentFor.querySelector('option[value="Advance Rent"]');
        const utilitiesOpt   = paymentFor.querySelector('option[value="Utilities"]');

        /* Advance rent is offered only once the deposit is settled and there
           is actually room to go further forward. Offering it to someone with
           an unpaid deposit would just produce an error on submit — the
           deposit gate in the controller rejects everything else first. */
        if (advanceOpt) {
            const canAdvance = selectedLease.deposit <= 0
                            && selectedLease.monthlyRent > 0
                            && selectedLease.advanceMonthsLeft > 0;

            if (canAdvance) {
                advanceOpt.textContent   = `Advance Rent — ₱${fmt(selectedLease.monthlyRent)} / month`;
                advanceOpt.style.display = '';
            } else {
                advanceOpt.style.display = 'none';
            }
        }

        if (depositOpt) {
            if (selectedLease.deposit > 0) {
                depositOpt.textContent    = `Deposit — ₱${fmt(selectedLease.deposit)}`;
                depositOpt.style.display  = '';
            } else {
                depositOpt.style.display  = 'none';
            }
        }
        if (rentOpt) {
            if (selectedLease.rent > 0) {
                rentOpt.textContent    = `Rent — ₱${fmt(selectedLease.rent)}`;
                rentOpt.style.display  = '';
            } else {
                rentOpt.style.display  = 'none';
            }
        }
        if (utilitiesOpt) {
            // Show utilities option only if at least one type has a balance
            const hasUtility = selectedLease.electricity > 0 || selectedLease.water > 0;
            if (hasUtility) {
                utilitiesOpt.textContent   = `Utilities`;
                utilitiesOpt.style.display = '';
            } else {
                utilitiesOpt.style.display = 'none';
            }
        }

        paymentFor.disabled = false;
    });

    /* ── Step 2: Payment type selected ───────────────────────── */
    paymentFor.addEventListener('change', function () {
        resetBillingType();
        resetAdvance();
        resetAmount();

        if (!this.value || !selectedLease) return;

        if (this.value === 'Advance Rent') {
            advanceField.classList.remove('d-none');
            advanceSelect.disabled = false;
            advanceSelect.required = true;
            buildAdvanceOptions();
            return;   // amount fills once the months are picked
        }

        if (this.value === 'Utilities') {
            // Show billing type selector — amount will fill after step 3
            billingTypeField.classList.remove('d-none');
            billingTypeSelect.disabled = false;
            billingTypeSelect.required = true;
            billingTypeSelect.value    = '';

            const estHint = document.getElementById('estimateHint');
            if (estHint) estHint.classList.toggle('d-none', !(selectedLease.estimates > 0));

            // Update electricity/water labels with actual amounts
            const elecOpt  = billingTypeSelect.querySelector('option[value="Electricity"]');
            const waterOpt = billingTypeSelect.querySelector('option[value="Water"]');

            if (elecOpt) {
                if (selectedLease.electricity > 0) {
                    elecOpt.textContent   = `⚡ Electricity — ₱${fmt(selectedLease.electricity)}`;
                    elecOpt.style.display = '';
                } else {
                    elecOpt.style.display = 'none'; // already paid / not billed
                }
            }
            if (waterOpt) {
                if (selectedLease.water > 0) {
                    waterOpt.textContent   = `💧 Water — ₱${fmt(selectedLease.water)}`;
                    waterOpt.style.display = '';
                } else {
                    waterOpt.style.display = 'none';
                }
            }
        } else {
            // Non-utility: fill amount directly
            const amountMap = {
                Deposit: selectedLease.deposit,
                Rent:    selectedLease.rent,
            };
            payAmount.value    = fmt(amountMap[this.value] || 0);
            payAmount.disabled = false;
            showMethodAndProof();
        }
    });

    /* ── Advance rent: months selected → fill the amount ─────── */
    if (advanceSelect) {
        advanceSelect.addEventListener('change', function () {
            if (!this.value || !selectedLease) {
                resetAmount();
                return;
            }

            const opt = this.options[this.selectedIndex];
            payAmount.value    = opt.dataset.total;
            payAmount.disabled = false;
            // Read-only rather than disabled: a disabled input is not posted,
            // and the amount is what the server actually acts on.
            payAmount.readOnly = true;
            showMethodAndProof();
        });
    }

    /* ── Step 3: Billing type selected ───────────────────────── */
    billingTypeSelect.addEventListener('change', function () {
        if (!this.value || !selectedLease) return;

        const amountMap = {
            Electricity: selectedLease.electricity,
            Water:       selectedLease.water,
        };

        payAmount.value    = fmt(amountMap[this.value] || 0);
        payAmount.disabled = false;
        showMethodAndProof();
    });

    /* ── Payment method: show account + proof fields (+ GCash QR) ── */
    const gcashQrField = document.getElementById('gcashQrField');

    payMethod.addEventListener('change', function () {
        const hasMethod = this.value !== '';
        if (hasMethod) {
            accountField.classList.remove('d-none');
            proofField.classList.remove('d-none');
            if (accountInput) accountInput.disabled = false;
            if (proofInput)   proofInput.disabled   = false;
        } else {
            accountField.classList.add('d-none');
            proofField.classList.add('d-none');
            if (accountInput) accountInput.disabled = true;
            if (proofInput)   proofInput.disabled   = true;
        }

        if (gcashQrField) {
            gcashQrField.classList.toggle('d-none', this.value !== 'GCash');
        }
    });

    /* ── Modal openers ────────────────────────────────────────── */
    window.viewUtilityProof = () => new bootstrap.Modal(document.getElementById('viewUtilityProofModal')).show();
    window.viewRentBalance  = () => new bootstrap.Modal(document.getElementById('viewRentBalanceModal')).show();
});
</script>
@endpush