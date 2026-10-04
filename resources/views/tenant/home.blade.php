{{-- resources/views/tenant/home.blade.php --}}
@extends('layouts.tenantdashboardlayout')

@section('title', 'Tenant Dashboard')
@section('page-title', 'Dashboard')

{{--
    Tenant dashboard — v19, compact like the manager's (manager/dashboard.blade.php).

    Fits one screen on a PC (1200px+ wide, 700px+ tall): header and KPI strip
    are fixed height, and the panels below take what is left and scroll INSIDE
    themselves. Smaller screens fall back to a normal scrolling page.

    Data: Tenant\TenantController@dashboard (read-only).
--}}

@push('styles')
<style>
    /* ===================== HEADER ===================== */
    .td-head { display:flex; align-items:flex-end; justify-content:space-between; gap:14px; flex-wrap:wrap; margin-bottom:14px; }
    .td-head__eyebrow { font-size:.68rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:var(--pr-gold); margin-bottom:2px; }
    .td-head__title { font-family:var(--pr-font-display); font-size:1.4rem; font-weight:700; color:var(--pr-navy); margin:0; line-height:1.15; }
    .td-head__right { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .td-head__date { font-size:.8rem; color:var(--pr-gray-500); display:flex; align-items:center; gap:6px; }
    .td-window { display:inline-flex; align-items:center; gap:7px; padding:5px 12px; border-radius:999px; font-size:.76rem; font-weight:600; border:1px solid; }
    .td-window--open   { background:#f0fdf4; color:#166534; border-color:#bbf7d0; }
    .td-window--closed { background:#f8fafc; color:#475569; border-color:#e2e8f0; }
    .td-window__dot { width:7px; height:7px; border-radius:50%; background:currentColor; }
    .td-window--open .td-window__dot { animation:tdPulse 1.6s ease-in-out infinite; }
    @keyframes tdPulse { 0%,100%{opacity:1} 50%{opacity:.3} }

    /* ===================== KPI STRIP ===================== */
    .td-kpis { display:grid; grid-template-columns:repeat(6, minmax(0,1fr)); gap:12px; margin-bottom:14px; }
    .td-kpi {
        background:var(--pr-white); border-radius:14px; padding:12px 14px;
        border:1px solid rgba(12,26,58,.06); box-shadow:0 2px 10px rgba(12,26,58,.05);
        display:flex; align-items:center; gap:11px; min-width:0; position:relative; overflow:hidden; color:inherit;
        transition:transform .2s ease, box-shadow .2s ease;
    }
    a.td-kpi:hover { transform:translateY(-2px); box-shadow:0 8px 22px rgba(12,26,58,.1); color:inherit; }
    .td-kpi::before { content:''; position:absolute; left:0; top:0; bottom:0; width:3px; background:var(--k-ink, var(--pr-navy)); }
    .td-kpi__icon { width:36px; height:36px; border-radius:10px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:1rem; background:var(--k-soft, rgba(12,26,58,.07)); color:var(--k-ink, var(--pr-navy)); }
    .td-kpi__body { min-width:0; }
    .td-kpi__value { font-family:var(--pr-font-display); font-size:1.12rem; font-weight:700; color:var(--pr-navy); line-height:1.1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .td-kpi__label { font-size:.66rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:var(--pr-gray-500); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .td-kpi__sub { font-size:.68rem; color:var(--pr-gray-500); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .td-kpi--navy   { --k-ink:var(--pr-navy); --k-soft:rgba(12,26,58,.07); }
    .td-kpi--teal   { --k-ink:#0f766e; --k-soft:rgba(13,148,136,.1); }
    .td-kpi--red    { --k-ink:#dc2626; --k-soft:rgba(239,68,68,.1); }
    .td-kpi--green  { --k-ink:#16a34a; --k-soft:rgba(34,197,94,.1); }
    .td-kpi--amber  { --k-ink:#d97706; --k-soft:rgba(245,158,11,.12); }
    .td-kpi--blue   { --k-ink:#2563eb; --k-soft:rgba(37,99,235,.1); }
    .td-kpi--gold   { --k-ink:#b8923a; --k-soft:rgba(201,168,76,.14); }

    /* ===================== MAIN GRID ===================== */
    .td-main { display:grid; grid-template-columns:minmax(0,1.5fr) minmax(0,1fr); gap:14px; }
    .td-side { display:flex; flex-direction:column; gap:14px; min-height:0; }
    .td-card { background:var(--pr-white); border-radius:16px; border:1px solid rgba(12,26,58,.06); box-shadow:0 2px 12px rgba(12,26,58,.06); display:flex; flex-direction:column; min-height:0; overflow:hidden; }
    .td-card__head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px 16px; border-bottom:1px solid rgba(12,26,58,.06); flex-shrink:0; }
    .td-card__title { display:flex; align-items:center; gap:9px; margin:0; font-family:var(--pr-font-display); font-size:.95rem; font-weight:700; color:var(--pr-navy); }
    .td-card__title > i { width:28px; height:28px; border-radius:8px; background:rgba(12,26,58,.06); display:inline-flex; align-items:center; justify-content:center; font-size:.84rem; }
    .td-card__hint { display:block; font-family:var(--pr-font-body); font-weight:400; font-size:.7rem; color:var(--pr-gray-500); margin-top:1px; }
    .td-card__link { font-size:.74rem; font-weight:600; color:var(--pr-navy); white-space:nowrap; }
    .td-card__link:hover { color:var(--pr-gold); }

    .td-scroll { overflow-y:auto; flex:1; min-height:0; }
    .td-scroll::-webkit-scrollbar { width:6px; }
    .td-scroll::-webkit-scrollbar-thumb { background:rgba(12,26,58,.15); border-radius:6px; }

    /* ===================== DUE LIST ===================== */
    .td-due { list-style:none; margin:0; padding:0; }
    .td-due__row { display:grid; grid-template-columns:48px minmax(0,1fr) auto auto; align-items:center; gap:12px; padding:9px 16px; border-bottom:1px solid rgba(12,26,58,.045); }
    .td-due li:last-child .td-due__row { border-bottom:0; }
    .td-date { width:48px; border-radius:10px; text-align:center; padding:4px 0 5px; line-height:1; background:var(--d-soft); color:var(--d-ink); border:1px solid var(--d-line); }
    .td-date__m { font-size:.58rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
    .td-date__d { font-family:var(--pr-font-display); font-size:1.1rem; font-weight:700; margin-top:2px; }
    .td-t-overdue  { --d-soft:#fef2f2; --d-ink:#b91c1c; --d-line:#fecaca; }
    .td-t-soon     { --d-soft:#fffbeb; --d-ink:#b45309; --d-line:#fde68a; }
    .td-t-later    { --d-soft:#eff6ff; --d-ink:#1d4ed8; --d-line:#bfdbfe; }
    .td-t-upcoming { --d-soft:#f0fdf4; --d-ink:#15803d; --d-line:#bbf7d0; }
    .td-t-estimate { --d-soft:#f5f3ff; --d-ink:#6d28d9; --d-line:#ddd6fe; }
    .td-what__name { font-weight:600; color:var(--pr-navy); font-size:.85rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .td-what__meta { font-size:.72rem; color:var(--pr-gray-500); display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-top:2px; }
    .td-kind { display:inline-flex; align-items:center; gap:4px; font-size:.64rem; font-weight:700; padding:1px 7px; border-radius:6px; text-transform:uppercase; letter-spacing:.04em; background:rgba(12,26,58,.07); color:var(--pr-navy); }
    .td-kind--utility  { background:rgba(13,148,136,.1); color:#0f766e; }
    .td-kind--estimate { background:#ede9fe; color:#5b21b6; }
    .td-kind--penalty  { background:#fee2e2; color:#b91c1c; }
    .td-kind--deposit  { background:rgba(201,168,76,.16); color:#8a6a1f; }
    .td-kind--upcoming { background:#dcfce7; color:#166534; }
    .td-chip { font-size:.68rem; font-weight:700; padding:3px 9px; border-radius:999px; white-space:nowrap; background:var(--d-soft); color:var(--d-ink); }
    .td-amt { text-align:right; min-width:88px; }
    .td-amt__v { font-weight:700; font-size:.86rem; color:var(--pr-navy); white-space:nowrap; }
    .td-amt__s { font-size:.66rem; color:var(--pr-gray-500); white-space:nowrap; }

    .td-foot { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 16px; border-top:1px solid rgba(12,26,58,.06); background:rgba(12,26,58,.015); flex-shrink:0; font-size:.76rem; color:var(--pr-gray-500); }
    .td-pay-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:10px; font-size:.78rem; font-weight:600; background:linear-gradient(135deg,var(--pr-navy),var(--pr-navy-mid)); color:#fff; white-space:nowrap; }
    .td-pay-btn:hover { color:#fff; box-shadow:0 6px 16px rgba(12,26,58,.22); }

    .td-empty { text-align:center; padding:30px 18px; color:var(--pr-gray-500); font-size:.84rem; }
    .td-empty i { font-size:1.7rem; opacity:.35; display:block; margin-bottom:6px; }

    /* ===================== LEASE CARD ===================== */
    .td-lease { padding:12px 16px; border-bottom:1px solid rgba(12,26,58,.045); }
    .td-lease:last-child { border-bottom:0; }
    .td-lease__top { display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .td-lease__room { font-weight:700; color:var(--pr-navy); font-size:.88rem; }
    .td-lease__type { font-size:.72rem; color:var(--pr-gray-500); }
    .td-lease__grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; margin-top:8px; }
    .td-lease__k { font-size:.62rem; font-weight:700; letter-spacing:.07em; text-transform:uppercase; color:var(--pr-gray-500); }
    .td-lease__v { font-size:.8rem; font-weight:600; color:var(--pr-navy); white-space:nowrap; }
    .td-lease__note { margin-top:8px; font-size:.72rem; padding:6px 9px; border-radius:8px; display:flex; align-items:center; gap:6px; }
    .td-lease__note--renew  { background:#f0fdf4; color:#166534; }
    .td-lease__note--wait   { background:#eff6ff; color:#1e40af; }
    .td-lease__note--move   { background:#fffbeb; color:#92400e; }
    .td-lease__note a { font-weight:700; color:inherit; text-decoration:underline; }
    .td-pill { display:inline-flex; align-items:center; gap:5px; padding:2px 9px; border-radius:999px; font-size:.66rem; font-weight:700; }
    .td-pill--green { background:#f0fdf4; color:#16a34a; }
    .td-pill--amber { background:#fffbeb; color:#d97706; }
    .td-pill--red   { background:#fef2f2; color:#dc2626; }

    /* ===================== ACTIVITY (tabs) ===================== */
    .td-tabs { display:flex; gap:6px; }
    .td-tab { border:1px solid rgba(12,26,58,.1); background:var(--pr-white); color:var(--pr-gray-800); border-radius:999px; padding:3px 10px; font-size:.72rem; font-weight:600; cursor:pointer; font-family:var(--pr-font-body); display:inline-flex; align-items:center; gap:5px; }
    .td-tab__n { background:rgba(12,26,58,.07); border-radius:999px; padding:0 6px; font-size:.66rem; }
    .td-tab.is-active { background:var(--pr-navy); color:#fff; border-color:var(--pr-navy); }
    .td-tab.is-active .td-tab__n { background:rgba(255,255,255,.18); }
    [data-pane][hidden] { display:none !important; }

    .td-act { list-style:none; margin:0; padding:0; }
    .td-act__row { display:flex; align-items:center; gap:10px; padding:8px 16px; border-bottom:1px solid rgba(12,26,58,.045); }
    .td-act li:last-child .td-act__row { border-bottom:0; }
    .td-act__icon { width:28px; height:28px; border-radius:8px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:.8rem; background:rgba(12,26,58,.06); color:var(--pr-navy); }
    .td-act__main { flex:1; min-width:0; }
    .td-act__title { font-size:.8rem; font-weight:600; color:var(--pr-navy); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .td-act__meta { font-size:.68rem; color:var(--pr-gray-500); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .td-act__side { text-align:right; display:flex; flex-direction:column; align-items:flex-end; gap:3px; }
    .td-act__amt { font-size:.8rem; font-weight:700; color:var(--pr-navy); white-space:nowrap; }
    .td-inv { font-size:.68rem; font-weight:700; color:var(--pr-navy); display:inline-flex; align-items:center; gap:3px; white-space:nowrap; }
    .td-inv:hover { color:var(--pr-gold); }

    /* ===================== FIT-TO-SCREEN (PC) =====================
       Same budget as the manager dashboard: topbar 66 + content padding 56 +
       header ~46 + KPI strip ~76 + gaps. */
    @media (min-width:1200px) and (min-height:700px) {
        .td-main { height:calc(100vh - 66px - 56px - 46px - 92px); min-height:360px; }
        .td-main > .td-card, .td-side { height:100%; }
        .td-side > .td-card--grow { flex:1; }
        .td-side > .td-card--lease { flex:0 1 auto; max-height:46%; }
    }

    /* ===================== RESPONSIVE ===================== */
    @media (max-width:1599px) {
        .td-kpi__icon { display:none; }
        .td-kpi { padding:11px 13px; }
    }
    @media (max-width:1199px) {
        .td-kpis { grid-template-columns:repeat(3,minmax(0,1fr)); }
        .td-main { grid-template-columns:1fr; }
        .td-card--due .td-scroll { max-height:480px; }
        .td-side .td-scroll { max-height:340px; }
    }
    @media (max-width:640px) {
        .td-kpis { grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
        .td-due__row { grid-template-columns:44px minmax(0,1fr) auto; }
        .td-due__row .td-chip-cell { display:none; }
        .td-lease__grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .td-head__right { width:100%; }
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

    @php
        $user    = Auth::user();
        $peso    = fn ($v) => '₱' . number_format((float) $v, 2);
        $active  = $active  ?? collect();
        $leases  = $leases  ?? collect();
        $dueItems = $dueItems ?? collect();
        $payments = $payments ?? collect();
        $requests = $requests ?? collect();
        $next     = $summary['next_due'] ?? null;
        $label    = \App\Support\RentCalendar::label();

        $kindIcon = [
            'rent' => 'bi-house', 'penalty' => 'bi-exclamation-triangle', 'utility' => 'bi-lightning-charge',
            'estimate' => 'bi-hourglass-split', 'deposit' => 'bi-safe2', 'upcoming' => 'bi-calendar-check',
        ];
        $kindText = [
            'rent' => 'Rent', 'penalty' => 'Penalty', 'utility' => 'Utility',
            'estimate' => 'Estimate', 'deposit' => 'Deposit', 'upcoming' => 'Upcoming',
        ];
        $recentPayments = $payments->take(15);
        $recentRequests = $requests->take(15);
    @endphp

    {{-- ── Header ─────────────────────────────────────────────── --}}
    <div class="td-head">
        <div>
            <div class="td-head__eyebrow">Tenant Portal</div>
            <h2 class="td-head__title">Welcome back, {{ $user->first_name ?? $user->name }}</h2>
        </div>
        <div class="td-head__right">
            @if($window['open'])
                <span class="td-window td-window--open" title="Rent is raised on the {{ \App\Support\RentCalendar::ordinal(\App\Support\RentCalendar::opensOnDay()) }} and due by the {{ \App\Support\RentCalendar::ordinal(\App\Support\RentCalendar::dueOnDay()) }}. A late penalty applies after that.">
                    <span class="td-window__dot"></span>
                    Payment window open · pay by {{ $window['closes']->format('M j') }}
                    @if($window['days_left'] > 0) ({{ $window['days_left'] }} day{{ $window['days_left'] === 1 ? '' : 's' }} left) @else (today) @endif
                </span>
            @else
                <span class="td-window td-window--closed" title="Payments can be made any time; this is when the next month falls due.">
                    <span class="td-window__dot"></span>
                    Next payment window: {{ $window['opens']->format('M j') }}–{{ $window['closes']->format('j') }}
                </span>
            @endif
            <span class="td-head__date"><i class="bi bi-calendar3"></i> {{ now()->format('l, F j, Y') }}</span>
        </div>
    </div>

    {{-- ── KPI strip ──────────────────────────────────────────── --}}
    <div class="td-kpis">
        @if($summary['deposit_due'] > 0)
            <a href="{{ route('tenant.payments') }}" class="td-kpi td-kpi--gold">
                <div class="td-kpi__icon"><i class="bi bi-safe2-fill"></i></div>
                <div class="td-kpi__body">
                    <div class="td-kpi__value">{{ $peso($summary['deposit_due']) }}</div>
                    <div class="td-kpi__label">Deposit due</div>
                    <div class="td-kpi__sub">Pay this first</div>
                </div>
            </a>
        @endif
        <a href="{{ route('tenant.payments') }}" class="td-kpi td-kpi--navy">
            <div class="td-kpi__icon"><i class="bi bi-house-fill"></i></div>
            <div class="td-kpi__body">
                <div class="td-kpi__value">{{ $peso($summary['rent_owed']) }}</div>
                <div class="td-kpi__label">Rent due</div>
                <div class="td-kpi__sub">{{ $summary['rent_owed'] > 0 ? 'incl. any penalty' : 'All paid' }}</div>
            </div>
        </a>
        <a href="{{ route('tenant.payments') }}" class="td-kpi td-kpi--teal">
            <div class="td-kpi__icon"><i class="bi bi-lightning-charge-fill"></i></div>
            <div class="td-kpi__body">
                <div class="td-kpi__value">{{ $peso($summary['util_owed']) }}</div>
                <div class="td-kpi__label">Utilities due</div>
                <div class="td-kpi__sub">
                    @if($summary['estimated'] > 0) + {{ $peso($summary['estimated']) }} estimate @else Electricity &amp; water @endif
                </div>
            </div>
        </a>
        <div class="td-kpi {{ $summary['overdue_count'] > 0 ? 'td-kpi--red' : 'td-kpi--blue' }}">
            <div class="td-kpi__icon"><i class="bi {{ $summary['overdue_count'] > 0 ? 'bi-exclamation-octagon-fill' : 'bi-calendar-event-fill' }}"></i></div>
            <div class="td-kpi__body">
                @if($summary['overdue_count'] > 0)
                    <div class="td-kpi__value" style="color:#b91c1c;">{{ $peso($summary['overdue_amount']) }}</div>
                    <div class="td-kpi__label">Overdue</div>
                    <div class="td-kpi__sub">{{ $summary['overdue_count'] }} item{{ $summary['overdue_count'] === 1 ? '' : 's' }} past due</div>
                @elseif($next)
                    <div class="td-kpi__value">{{ $next['date']->format('M j') }}</div>
                    <div class="td-kpi__label">Next due date</div>
                    <div class="td-kpi__sub">{{ $peso($next['amount']) }} · {{ $kindText[$next['kind']] ?? '' }}</div>
                @else
                    <div class="td-kpi__value">{{ $label }}</div>
                    <div class="td-kpi__label">Payment days</div>
                    <div class="td-kpi__sub">Nothing due right now</div>
                @endif
            </div>
        </div>
        @if($summary['deposit_due'] <= 0)
            <div class="td-kpi td-kpi--green">
                <div class="td-kpi__icon"><i class="bi bi-calendar2-check-fill"></i></div>
                <div class="td-kpi__body">
                    <div class="td-kpi__value">{{ $peso($summary['advance']) }}</div>
                    <div class="td-kpi__label">Paid in advance</div>
                    <div class="td-kpi__sub">Held for future rent</div>
                </div>
            </div>
        @endif
        <a href="{{ route('tenant.payments') }}" class="td-kpi td-kpi--amber">
            <div class="td-kpi__icon"><i class="bi bi-hourglass-split"></i></div>
            <div class="td-kpi__body">
                <div class="td-kpi__value">{{ $summary['pending_payments'] }}</div>
                <div class="td-kpi__label">Pending payments</div>
                <div class="td-kpi__sub">Awaiting the office</div>
            </div>
        </a>
        <a href="{{ route('tenant.requests') }}" class="td-kpi td-kpi--blue">
            <div class="td-kpi__icon"><i class="bi bi-tools"></i></div>
            <div class="td-kpi__body">
                <div class="td-kpi__value">{{ $summary['open_requests'] }}</div>
                <div class="td-kpi__label">Open requests</div>
                <div class="td-kpi__sub">{{ $requests->count() }} total</div>
            </div>
        </a>
    </div>

    {{-- ── Main ───────────────────────────────────────────────── --}}
    <div class="td-main">

        {{-- What I owe --}}
        <section class="td-card td-card--due">
            <div class="td-card__head">
                <h3 class="td-card__title">
                    <i class="bi bi-receipt-cutoff"></i>
                    <span>
                        What's Due
                        <span class="td-card__hint">Rent is raised on the {{ \App\Support\RentCalendar::ordinal(\App\Support\RentCalendar::opensOnDay()) }} and due by the {{ \App\Support\RentCalendar::ordinal(\App\Support\RentCalendar::dueOnDay()) }} — late penalty after that</span>
                    </span>
                </h3>
                <a href="{{ route('tenant.payments') }}" class="td-card__link">Payments <i class="bi bi-arrow-right"></i></a>
            </div>

            <div class="td-scroll">
                @if($dueItems->isEmpty())
                    <div class="td-empty">
                        <i class="bi bi-check2-circle"></i>
                        @if($active->isEmpty())
                            No active lease yet. Your charges will show here once your lease starts.
                        @else
                            You're all paid up. Nothing is due right now.
                        @endif
                    </div>
                @else
                    <ul class="td-due">
                        @foreach($dueItems as $item)
                            @php
                                $d = $item['days'];
                                $tone = match (true) {
                                    $item['kind'] === 'estimate' => 'td-t-estimate',
                                    $item['kind'] === 'upcoming' => 'td-t-upcoming',
                                    $item['overdue']             => 'td-t-overdue',
                                    $d <= 7                      => 'td-t-soon',
                                    default                      => 'td-t-later',
                                };
                                $chip = match (true) {
                                    $item['kind'] === 'estimate' => 'Not payable yet',
                                    $item['kind'] === 'upcoming' => 'Upcoming',
                                    $d < 0                       => abs($d) . ' day' . (abs($d) === 1 ? '' : 's') . ' late',
                                    $d === 0                     => 'Due today',
                                    $d === 1                     => 'Due tomorrow',
                                    default                      => 'Due in ' . $d . ' days',
                                };
                            @endphp
                            <li>
                                <div class="td-due__row {{ $tone }}">
                                    <div class="td-date">
                                        <div class="td-date__m">{{ $item['date']->format('M') }}</div>
                                        <div class="td-date__d">{{ $item['date']->format('d') }}</div>
                                    </div>
                                    <div class="td-what">
                                        <div class="td-what__name">{{ $item['label'] }}</div>
                                        <div class="td-what__meta">
                                            <span class="td-kind td-kind--{{ $item['kind'] }}"><i class="bi {{ $kindIcon[$item['kind']] ?? 'bi-dot' }}"></i> {{ $kindText[$item['kind']] ?? '' }}</span>
                                            <span>Room {{ $item['room'] }}</span>
                                            @if($item['note'])<span>· {{ $item['note'] }}</span>@endif
                                        </div>
                                    </div>
                                    <div class="td-chip-cell"><span class="td-chip">{{ $chip }}</span></div>
                                    <div class="td-amt">
                                        <div class="td-amt__v" style="{{ $item['overdue'] ? 'color:#b91c1c;' : ($item['kind'] === 'estimate' ? 'color:#7c3aed;' : '') }}">
                                            {{ $item['kind'] === 'estimate' ? '≈ ' : '' }}{{ $peso($item['amount']) }}
                                        </div>
                                        <div class="td-amt__s">{{ $item['kind'] === 'upcoming' ? 'next charge' : ($item['kind'] === 'estimate' ? 'estimate' : 'owed') }}</div>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @if($dueItems->where('payable', true)->isNotEmpty())
                <div class="td-foot">
                    <span>Total payable now: <strong style="color:var(--pr-navy);">{{ $peso($dueItems->where('payable', true)->sum('amount')) }}</strong></span>
                    <a href="{{ route('tenant.payments') }}" class="td-pay-btn"><i class="bi bi-cash-coin"></i> Make a payment</a>
                </div>
            @endif
        </section>

        <div class="td-side">

            {{-- My lease --}}
            <section class="td-card td-card--lease">
                <div class="td-card__head">
                    <h3 class="td-card__title"><i class="bi bi-door-open-fill"></i> My Lease{{ $leases->count() > 1 ? 's' : '' }}</h3>
                    <a href="{{ route('tenant.leases') }}" class="td-card__link">Manage <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="td-scroll">
                    @forelse($leases as $l)
                        @php
                            $status = strtolower((string) $l->lea_status);
                            $opens  = $l->renewalRequestOpensOn();
                        @endphp
                        <div class="td-lease">
                            <div class="td-lease__top">
                                <div>
                                    <div class="td-lease__room">Room {{ $l->room_no ?? 'N/A' }}{{ $l->bed_number ? ' · Bed ' . $l->bed_number : '' }}</div>
                                    <div class="td-lease__type">{{ $l->unit->type ?? 'Unit' }}</div>
                                </div>
                                <span class="td-pill {{ $status === 'active' ? 'td-pill--green' : 'td-pill--amber' }}">{{ ucfirst($status) }}</span>
                            </div>
                            <div class="td-lease__grid">
                                <div><div class="td-lease__k">Rent</div><div class="td-lease__v">{{ $peso($l->currentRent()) }}</div></div>
                                <div><div class="td-lease__k">Start</div><div class="td-lease__v">{{ $l->lea_start_date?->format('M d, Y') ?? '—' }}</div></div>
                                <div><div class="td-lease__k">End</div><div class="td-lease__v">{{ $l->lea_end_date?->format('M d, Y') ?? '—' }}</div></div>
                            </div>
                            @if($l->move_out_requested)
                                <div class="td-lease__note td-lease__note--move"><i class="bi bi-box-arrow-right"></i> Move out requested — awaiting the office.</div>
                            @elseif($l->renewal_requested)
                                <div class="td-lease__note td-lease__note--wait"><i class="bi bi-hourglass-split"></i> Renewal requested — awaiting the office.</div>
                            @elseif($l->canRequestRenewal())
                                <div class="td-lease__note td-lease__note--renew">
                                    <i class="bi bi-arrow-repeat"></i>
                                    Ends soon. <a href="{{ route('tenant.leases') }}">Request a renewal</a> to stay.
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="td-empty"><i class="bi bi-house-slash"></i> No active lease.</div>
                    @endforelse
                </div>
            </section>

            {{-- Recent activity --}}
            <section class="td-card td-card--grow" id="tdActivity">
                <div class="td-card__head">
                    <h3 class="td-card__title"><i class="bi bi-clock-history"></i> Recent</h3>
                    <div class="td-tabs" role="tablist">
                        <button type="button" class="td-tab is-active" data-tab="payments" aria-selected="true">Payments <span class="td-tab__n">{{ $payments->count() }}</span></button>
                        <button type="button" class="td-tab" data-tab="requests" aria-selected="false">Requests <span class="td-tab__n">{{ $requests->count() }}</span></button>
                    </div>
                </div>
                <div class="td-scroll">
                    <div data-pane="payments">
                        @if($recentPayments->isEmpty())
                            <div class="td-empty"><i class="bi bi-receipt"></i> No payments yet.</div>
                        @else
                            <ul class="td-act">
                                @foreach($recentPayments as $p)
                                    @php
                                        $pill = match ($p->pay_status) { 'Accepted' => 'td-pill--green', 'Rejected' => 'td-pill--red', default => 'td-pill--amber' };
                                        $pIcon = match (true) {
                                            $p->payment_for === 'Deposit'   => 'bi-safe2',
                                            $p->payment_for === 'Utilities' => 'bi-lightning-charge',
                                            default                         => 'bi-house',
                                        };
                                    @endphp
                                    <li>
                                        <div class="td-act__row">
                                            <div class="td-act__icon"><i class="bi {{ $pIcon }}"></i></div>
                                            <div class="td-act__main">
                                                <div class="td-act__title">{{ $p->payment_for }}{{ $p->utility_type ? ' · ' . $p->utility_type : '' }}</div>
                                                <div class="td-act__meta">{{ \Carbon\Carbon::parse($p->pay_date ?? $p->created_at)->format('M d, Y') }} · {{ $p->pay_method }}{{ $p->lease ? ' · Room ' . $p->lease->room_no : '' }}</div>
                                            </div>
                                            <div class="td-act__side">
                                                <div class="td-act__amt">{{ $peso($p->pay_amount) }}</div>
                                                @if($p->hasInvoice())
                                                    <a href="{{ route('tenant.payments.invoice', $p->id) }}" class="td-inv" title="Download invoice {{ $p->invoiceNumber() }}">
                                                        <i class="bi bi-file-earmark-arrow-down"></i> Invoice
                                                    </a>
                                                @else
                                                    <span class="td-pill {{ $pill }}">{{ $p->pay_status }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div data-pane="requests" hidden>
                        @if($recentRequests->isEmpty())
                            <div class="td-empty"><i class="bi bi-tools"></i> No maintenance requests.</div>
                        @else
                            <ul class="td-act">
                                @foreach($recentRequests as $r)
                                    @php
                                        $rs = strtolower((string) $r->status);
                                        $rPill = match ($rs) { 'completed' => 'td-pill--green', 'cancelled' => 'td-pill--red', default => 'td-pill--amber' };
                                    @endphp
                                    <li>
                                        <a href="{{ route('tenant.requests.show', $r->id) }}" class="td-act__row" style="color:inherit;">
                                            <div class="td-act__icon"><i class="bi bi-wrench-adjustable"></i></div>
                                            <div class="td-act__main">
                                                <div class="td-act__title">{{ \Illuminate\Support\Str::limit($r->description, 60) }}</div>
                                                <div class="td-act__meta">{{ $r->created_at?->format('M d, Y') }}{{ $r->room_no ? ' · Room ' . $r->room_no : '' }}{{ $r->urgency ? ' · ' . ucfirst($r->urgency) : '' }}</div>
                                            </div>
                                            <div class="td-act__side"><span class="td-pill {{ $rPill }}">{{ $r->status }}</span></div>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const card = document.getElementById('tdActivity');
    if (!card) return;
    card.querySelectorAll('.td-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            card.querySelectorAll('.td-tab').forEach(function (b) {
                const on = b === btn;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            card.querySelectorAll('[data-pane]').forEach(function (pane) {
                pane.hidden = pane.dataset.pane !== btn.dataset.tab;
            });
        });
    });
});
</script>
@endpush
