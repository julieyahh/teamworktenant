@extends('layouts.managerdashboardlayout')

@section('title', 'Pinikitan Rental | Dashboard')
@section('page-title', 'Overview')

{{--
    Manager dashboard — v18 layout.

    Built to fit one screen on a PC (1200px+ wide, 700px+ tall): the header and
    KPI strip are fixed-height, and the two panels below take whatever height is
    left and scroll INSIDE themselves. On smaller screens it falls back to a
    normal scrolling page.

    The due-date data comes from App\Services\DueDateMonitor (read-only).
--}}

@push('styles')
<style>
    /* ===================== HEADER ===================== */
    .db-head {
        display: flex; align-items: flex-end; justify-content: space-between;
        gap: 16px; flex-wrap: wrap; margin-bottom: 16px;
    }
    .db-head__eyebrow { font-size: .68rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--pr-gold); margin-bottom: 2px; }
    .db-head__title   { font-family: var(--pr-font-display); font-size: 1.45rem; font-weight: 700; color: var(--pr-navy); margin: 0; line-height: 1.15; }
    .db-head__date    { font-size: .82rem; color: var(--pr-gray-500); display: flex; align-items: center; gap: 6px; }

    /* ===================== KPI STRIP ===================== */
    .db-kpis {
        display: grid; grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 14px; margin-bottom: 16px;
    }
    .db-kpi {
        background: var(--pr-white); border-radius: 14px; padding: 14px 16px;
        border: 1px solid rgba(12,26,58,.06); box-shadow: 0 2px 10px rgba(12,26,58,.05);
        display: flex; align-items: center; gap: 12px; min-width: 0;
        position: relative; overflow: hidden; color: inherit;
        transition: transform .2s ease, box-shadow .2s ease;
    }
    a.db-kpi:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(12,26,58,.1); color: inherit; }
    .db-kpi::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--kpi-accent, var(--pr-navy)); }
    .db-kpi__icon {
        width: 38px; height: 38px; border-radius: 11px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; font-size: 1.05rem;
        background: var(--kpi-soft, rgba(12,26,58,.07)); color: var(--kpi-accent, var(--pr-navy));
    }
    .db-kpi__body  { min-width: 0; }
    .db-kpi__value { font-family: var(--pr-font-display); font-size: 1.45rem; font-weight: 700; color: var(--pr-navy); line-height: 1.05; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .db-kpi__value--money { font-size: 1.15rem; }
    .db-kpi__label { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: var(--pr-gray-500); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .db-kpi__sub   { font-size: .7rem; color: var(--pr-gray-500); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .db-kpi--navy  { --kpi-accent: var(--pr-navy); --kpi-soft: rgba(12,26,58,.07); }
    .db-kpi--green { --kpi-accent: #16a34a; --kpi-soft: rgba(34,197,94,.1); }
    .db-kpi--gold  { --kpi-accent: #b8923a; --kpi-soft: rgba(201,168,76,.14); }
    .db-kpi--amber { --kpi-accent: #d97706; --kpi-soft: rgba(245,158,11,.12); }
    .db-kpi--red   { --kpi-accent: #dc2626; --kpi-soft: rgba(239,68,68,.1); }
    .db-kpi--blue  { --kpi-accent: #2563eb; --kpi-soft: rgba(37,99,235,.1); }

    /* ===================== MAIN GRID ===================== */
    .db-main {
        display: grid; grid-template-columns: minmax(0, 1.65fr) minmax(0, 1fr);
        gap: 16px;
    }
    .db-side { display: flex; flex-direction: column; gap: 16px; min-height: 0; }

    .db-card {
        background: var(--pr-white); border-radius: 16px;
        border: 1px solid rgba(12,26,58,.06); box-shadow: 0 2px 12px rgba(12,26,58,.06);
        display: flex; flex-direction: column; min-height: 0; overflow: hidden;
    }
    .db-card__head {
        display: flex; align-items: center; justify-content: space-between; gap: 10px;
        padding: 14px 18px; border-bottom: 1px solid rgba(12,26,58,.06); flex-shrink: 0;
    }
    .db-card__title { display: flex; align-items: center; gap: 9px; margin: 0; font-family: var(--pr-font-display); font-size: .98rem; font-weight: 700; color: var(--pr-navy); }
    .db-card__title i { width: 30px; height: 30px; border-radius: 8px; background: rgba(12,26,58,.06); display: inline-flex; align-items: center; justify-content: center; font-size: .88rem; }
    .db-card__hint { font-size: .72rem; color: var(--pr-gray-500); font-family: var(--pr-font-body); font-weight: 400; display: block; margin-top: 1px; }
    .db-card__tools { display: flex; align-items: center; gap: 12px; }
    .db-card__link { font-size: .75rem; font-weight: 600; color: var(--pr-navy); white-space: nowrap; }
    .db-card__link:hover { color: var(--pr-gold); }

    /* ===================== TABS / SEARCH ===================== */
    .db-toolbar {
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        padding: 10px 18px; border-bottom: 1px solid rgba(12,26,58,.06); flex-shrink: 0;
        background: rgba(12,26,58,.015);
    }
    .db-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
    .db-tab {
        border: 1px solid rgba(12,26,58,.1); background: var(--pr-white); color: var(--pr-gray-800);
        border-radius: 999px; padding: 4px 11px; font-size: .74rem; font-weight: 600; cursor: pointer;
        display: inline-flex; align-items: center; gap: 6px; transition: all .15s ease; font-family: var(--pr-font-body);
    }
    .db-tab:hover { border-color: var(--pr-navy); }
    .db-tab__n { background: rgba(12,26,58,.07); border-radius: 999px; padding: 0 7px; font-size: .68rem; line-height: 1.5; }
    .db-tab.is-active { background: var(--pr-navy); color: #fff; border-color: var(--pr-navy); }
    .db-tab.is-active .db-tab__n { background: rgba(255,255,255,.18); }
    .db-tab--red .db-tab__n { background: #fee2e2; color: #b91c1c; }
    .db-tab--red.is-active { background: #dc2626; border-color: #dc2626; }
    .db-tab--red.is-active .db-tab__n { background: rgba(255,255,255,.22); color: #fff; }

    .db-search { margin-left: auto; position: relative; }
    .db-search input {
        border: 1px solid rgba(12,26,58,.12); border-radius: 999px; padding: 5px 12px 5px 30px;
        font-size: .78rem; width: 180px; outline: none; font-family: var(--pr-font-body);
    }
    .db-search input:focus { border-color: var(--pr-navy); }
    .db-search i { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); font-size: .75rem; color: var(--pr-gray-500); }

    /* ===================== LISTS ===================== */
    .db-scroll { overflow-y: auto; flex: 1; min-height: 0; }
    .db-scroll::-webkit-scrollbar { width: 6px; }
    .db-scroll::-webkit-scrollbar-thumb { background: rgba(12,26,58,.15); border-radius: 6px; }

    .db-due { list-style: none; margin: 0; padding: 0; }
    .db-due__row {
        display: grid; grid-template-columns: 50px minmax(0, 1fr) auto auto 20px;
        align-items: center; gap: 12px; padding: 10px 18px;
        border-bottom: 1px solid rgba(12,26,58,.045); color: inherit; transition: background .15s;
    }
    .db-due__row:hover { background: rgba(12,26,58,.025); color: inherit; }
    .db-due li:last-child .db-due__row { border-bottom: 0; }

    .db-date {
        width: 50px; border-radius: 10px; text-align: center; padding: 4px 0 5px; line-height: 1;
        background: var(--d-soft); color: var(--d-ink); border: 1px solid var(--d-line);
    }
    .db-date__m { font-size: .6rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    .db-date__d { font-family: var(--pr-font-display); font-size: 1.15rem; font-weight: 700; margin-top: 2px; }

    .db-b-overdue { --d-soft: #fef2f2; --d-ink: #b91c1c; --d-line: #fecaca; }
    .db-b-today   { --d-soft: #fffbeb; --d-ink: #b45309; --d-line: #fde68a; }
    .db-b-week    { --d-soft: #eff6ff; --d-ink: #1d4ed8; --d-line: #bfdbfe; }
    .db-b-month   { --d-soft: #f8fafc; --d-ink: #475569; --d-line: #e2e8f0; }
    .db-b-paid    { --d-soft: #f0fdf4; --d-ink: #15803d; --d-line: #bbf7d0; }
    .db-b-end     { --d-soft: #f5f3ff; --d-ink: #6d28d9; --d-line: #ddd6fe; }

    .db-who__name { font-weight: 600; color: var(--pr-navy); font-size: .86rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .db-who__meta { font-size: .74rem; color: var(--pr-gray-500); display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-top: 2px; }
    .db-kind {
        display: inline-flex; align-items: center; gap: 4px; font-size: .66rem; font-weight: 700;
        padding: 1px 7px; border-radius: 6px; text-transform: uppercase; letter-spacing: .04em;
    }
    .db-kind--rent      { background: rgba(12,26,58,.07); color: var(--pr-navy); }
    .db-kind--utility   { background: rgba(13,148,136,.1); color: #0f766e; }
    .db-kind--lease_end { background: #f5f3ff; color: #6d28d9; }

    .db-chip {
        font-size: .7rem; font-weight: 700; padding: 3px 9px; border-radius: 999px; white-space: nowrap;
        background: var(--d-soft); color: var(--d-ink);
    }
    .db-amt { text-align: right; min-width: 92px; }
    .db-amt__v { font-weight: 700; font-size: .88rem; color: var(--pr-navy); white-space: nowrap; }
    .db-amt__s { font-size: .68rem; color: var(--pr-gray-500); white-space: nowrap; }
    .db-due__go { color: var(--pr-gray-500); font-size: .8rem; }

    .db-empty { text-align: center; padding: 34px 20px; color: var(--pr-gray-500); font-size: .85rem; }
    .db-empty i { font-size: 1.8rem; opacity: .35; display: block; margin-bottom: 6px; }

    /* ===================== STATUS CARD ===================== */
    .db-status { display: grid; grid-template-columns: 118px minmax(0,1fr); gap: 16px; align-items: center; padding: 14px 18px; }
    .db-donut { position: relative; width: 118px; height: 118px; }
    .db-donut__c { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; }
    .db-donut__v { font-family: var(--pr-font-display); font-size: 1.35rem; font-weight: 700; color: var(--pr-navy); line-height: 1; }
    .db-donut__l { font-size: .62rem; text-transform: uppercase; letter-spacing: .08em; color: var(--pr-gray-500); margin-top: 2px; }
    .db-legend { list-style: none; margin: 0; padding: 0; }
    .db-legend li { display: flex; align-items: center; gap: 8px; font-size: .8rem; padding: 3px 0; }
    .db-legend .dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .db-legend .lbl { flex: 1; color: var(--pr-gray-800); }
    .db-legend .val { font-weight: 700; color: var(--pr-navy); }
    .db-legend .pct { font-size: .7rem; color: var(--pr-gray-500); min-width: 32px; text-align: right; }
    .db-occ { margin-top: 8px; }
    .db-occ__track { height: 6px; background: var(--pr-gray-100, #f0f0f0); border-radius: 999px; overflow: hidden; }
    .db-occ__fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--pr-navy), var(--pr-navy-light, #1e3a8a)); }
    .db-occ__lbl { display: flex; justify-content: space-between; font-size: .72rem; color: var(--pr-gray-500); margin-top: 4px; }
    .db-occ__lbl strong { color: var(--pr-navy); }

    /* ===================== PEOPLE LIST ===================== */
    .db-people { list-style: none; margin: 0; padding: 0; }
    .db-person { display: flex; align-items: center; gap: 10px; padding: 9px 18px; border-bottom: 1px solid rgba(12,26,58,.045); }
    .db-people li:last-child .db-person { border-bottom: 0; }
    .db-avatar {
        width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .72rem;
    }
    .db-avatar--pending  { background: linear-gradient(135deg, var(--pr-navy), var(--pr-navy-light, #1e3a8a)); color: var(--pr-gold); }
    .db-avatar--approved { background: linear-gradient(135deg, #22c55e, #4ade80); color: #fff; }
    .db-person__main { min-width: 0; flex: 1; }
    .db-person__name { font-weight: 600; font-size: .84rem; color: var(--pr-navy); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .db-person__mail { font-size: .72rem; color: var(--pr-gray-500); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .db-person__date { font-size: .72rem; color: var(--pr-gray-500); white-space: nowrap; }

    [data-pane][hidden] { display: none !important; }

    /* ===================== FIT-TO-SCREEN (PC) =====================
       topbar 66px + content padding 56px + header ~52px + KPI strip ~84px.
       Only switched on where there is room for it; everything else scrolls
       normally. */
    @media (min-width: 1200px) and (min-height: 700px) {
        .db-main { height: calc(100vh - 66px - 56px - 52px - 100px); min-height: 380px; }
        .db-main > .db-card, .db-side { height: 100%; }
        .db-side > .db-card--grow { flex: 1; }
    }

    /* ===================== RESPONSIVE ===================== */
    @media (max-width: 1599px) {
        .db-kpi__value { font-size: 1.3rem; }
        .db-kpi__value--money { font-size: 1.05rem; }
        .db-kpi__icon { display: none; }            /* room for the labels on 1366–1600 screens */
        .db-kpi { padding: 12px 14px; }
        .db-search input { width: 140px; }
        .db-tab { padding: 4px 8px; }
        .db-tabs { gap: 5px; }
        .db-toolbar { padding: 10px 14px; }
    }
    @media (max-width: 1199px) {
        .db-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .db-main { grid-template-columns: 1fr; }
        .db-card--due .db-scroll { max-height: 520px; }
        .db-side .db-scroll { max-height: 360px; }
    }
    @media (max-width: 640px) {
        .db-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .db-due__row { grid-template-columns: 46px minmax(0, 1fr) auto; }
        .db-due__row .db-chip-cell, .db-due__row .db-due__go { display: none; }
        .db-card--due .db-card__head { flex-wrap: wrap; }
        .db-card__tools { width: 100%; }
        .db-search { margin-left: 0; flex: 1; }
        .db-search input { width: 100%; }
        .db-status { grid-template-columns: 1fr; justify-items: center; }
        .db-legend { width: 100%; }
    }
</style>
@endpush

@section('content')

    @if(session('success'))
        <x-alert type="success">{{ session('success') }}</x-alert>
    @endif

    @php
        $occPct = $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100) : 0;
        $peso   = fn ($v) => '₱' . number_format((float) $v, 2);

        $bucketCount = fn (string $b) => $dueItems->where('bucket', $b)->count();
        $tabs = [
            ['key' => 'all',       'label' => 'All',        'n' => $dueItems->count(),                          'cls' => ''],
            ['key' => 'overdue',   'label' => 'Overdue',    'n' => $bucketCount('overdue'),                     'cls' => 'db-tab--red'],
            ['key' => 'today',     'label' => 'Today',      'n' => $bucketCount('today'),                       'cls' => ''],
            ['key' => 'week',      'label' => '7 days','n' => $bucketCount('week'),                        'cls' => ''],
            ['key' => 'month',     'label' => '8–30 days',  'n' => $bucketCount('month'),                       'cls' => ''],
            ['key' => 'lease_end', 'label' => 'Lease ends', 'n' => $dueItems->where('kind', 'lease_end')->count(), 'cls' => ''],
        ];
        // Open on Overdue when there is something overdue — that is the thing to act on.
        $defaultTab = $dueSummary['overdue_count'] > 0 ? 'overdue' : 'all';

        $totalStatus = $approvedTenants + $pendingApplications + $rejectedTenants;
        $pct = fn ($n) => $totalStatus > 0 ? round($n / $totalStatus * 100) : 0;
    @endphp

    {{-- ── Header ─────────────────────────────────────────────── --}}
    <div class="db-head">
        <div>
            <div class="db-head__eyebrow">Manager Portal</div>
            <h2 class="db-head__title">Property Overview</h2>
        </div>
        <div class="db-head__date"><i class="bi bi-calendar3"></i> {{ now()->format('l, F j, Y') }}</div>
    </div>

    {{-- ── KPI strip ──────────────────────────────────────────── --}}
    <div class="db-kpis">
        <a href="{{ route('manager.units.index') }}" class="db-kpi db-kpi--navy">
            <div class="db-kpi__icon"><i class="bi bi-building-check"></i></div>
            <div class="db-kpi__body">
                <div class="db-kpi__value">{{ $totalUnits }}</div>
                <div class="db-kpi__label">Total Units</div>
            </div>
        </a>
        <div class="db-kpi db-kpi--green">
            <div class="db-kpi__icon"><i class="bi bi-house-door-fill"></i></div>
            <div class="db-kpi__body">
                <div class="db-kpi__value">{{ $occupiedUnits }}</div>
                <div class="db-kpi__label">Occupied · {{ $occPct }}%</div>
            </div>
        </div>
        <div class="db-kpi db-kpi--gold">
            <div class="db-kpi__icon"><i class="bi bi-door-closed-fill"></i></div>
            <div class="db-kpi__body">
                <div class="db-kpi__value">{{ $vacantUnits }}</div>
                <div class="db-kpi__label">Vacant Units</div>
            </div>
        </div>
        <a href="{{ route('manager.tenants') }}" class="db-kpi db-kpi--amber">
            <div class="db-kpi__icon"><i class="bi bi-clipboard-check-fill"></i></div>
            <div class="db-kpi__body">
                <div class="db-kpi__value">{{ $pendingApplications }}</div>
                <div class="db-kpi__label">Pending Apps</div>
            </div>
        </a>
        <a href="{{ route('manager.collections.index') }}" class="db-kpi db-kpi--red" title="Rent and utility bills past their due date">
            <div class="db-kpi__icon"><i class="bi bi-exclamation-octagon-fill"></i></div>
            <div class="db-kpi__body">
                <div class="db-kpi__value db-kpi__value--money">{{ $peso($dueSummary['overdue_amount']) }}</div>
                <div class="db-kpi__label">Overdue</div>
                <div class="db-kpi__sub">{{ $dueSummary['overdue_count'] }} item{{ $dueSummary['overdue_count'] === 1 ? '' : 's' }} · {{ $dueSummary['overdue_tenants'] }} lease{{ $dueSummary['overdue_tenants'] === 1 ? '' : 's' }}</div>
            </div>
        </a>
        <div class="db-kpi db-kpi--blue" title="Due today and in the next 7 days">
            <div class="db-kpi__icon"><i class="bi bi-calendar-event-fill"></i></div>
            <div class="db-kpi__body">
                <div class="db-kpi__value db-kpi__value--money">{{ $peso($dueSummary['today_amount'] + $dueSummary['week_amount']) }}</div>
                <div class="db-kpi__label">Due in 7 days</div>
                <div class="db-kpi__sub">{{ $dueSummary['today_count'] }} today · {{ $dueSummary['week_count'] }} this week</div>
            </div>
        </div>
    </div>

    {{-- ── Main ───────────────────────────────────────────────── --}}
    <div class="db-main">

        {{-- Due date monitor --}}
        <section class="db-card db-card--due" id="dueMonitor" data-default-tab="{{ $defaultTab }}">
            <div class="db-card__head">
                <h3 class="db-card__title">
                    <i class="bi bi-alarm-fill"></i>
                    <span>
                        Due Date Monitor
                        <span class="db-card__hint">Overdue, plus anything due in the next {{ \App\Services\DueDateMonitor::WINDOW_DAYS }} days</span>
                    </span>
                </h3>
                <div class="db-card__tools">
                    <label class="db-search" aria-label="Search tenant or room">
                        <i class="bi bi-search"></i>
                        <input type="search" id="dueSearch" placeholder="Search tenant/room" autocomplete="off">
                    </label>
                    <a href="{{ route('manager.collections.index') }}" class="db-card__link">Collections <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>

            <div class="db-toolbar">
                <div class="db-tabs" role="tablist">
                    @foreach($tabs as $t)
                        <button type="button" role="tab"
                                class="db-tab {{ $t['cls'] }} {{ $t['key'] === $defaultTab ? 'is-active' : '' }}"
                                data-tab="{{ $t['key'] }}"
                                aria-selected="{{ $t['key'] === $defaultTab ? 'true' : 'false' }}">
                            {{ $t['label'] }} <span class="db-tab__n">{{ $t['n'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="db-scroll">
                @if($dueItems->isEmpty())
                    <div class="db-empty">
                        <i class="bi bi-check2-circle"></i>
                        Nothing overdue and nothing due in the next {{ \App\Services\DueDateMonitor::WINDOW_DAYS }} days.
                    </div>
                @else
                    <ul class="db-due" id="dueList">
                        @foreach($dueItems as $item)
                            @php
                                $d = $item['days'];
                                if ($item['kind'] === 'lease_end') {
                                    $tone = 'db-b-end';
                                    $chip = $d < 0 ? 'Ended ' . abs($d) . 'd ago' : ($d === 0 ? 'Ends today' : 'Ends in ' . $d . 'd');
                                } elseif ($item['paid_up']) {
                                    $tone = 'db-b-paid';
                                    $chip = $d === 0 ? 'Paid up · due today' : 'Paid up · in ' . $d . 'd';
                                } else {
                                    $tone = match ($item['bucket']) {
                                        'overdue' => 'db-b-overdue',
                                        'today'   => 'db-b-today',
                                        'week'    => 'db-b-week',
                                        default   => 'db-b-month',
                                    };
                                    $chip = match (true) {
                                        $d < 0   => abs($d) . ' day' . (abs($d) === 1 ? '' : 's') . ' overdue',
                                        $d === 0 => 'Due today',
                                        $d === 1 => 'Due tomorrow',
                                        default  => 'Due in ' . $d . ' days',
                                    };
                                }
                                $kindIcon = ['rent' => 'bi-house', 'utility' => 'bi-lightning-charge', 'lease_end' => 'bi-file-earmark-text'][$item['kind']];
                                $kindText = $item['kind'] === 'rent' ? 'Rent' : ($item['kind'] === 'lease_end' ? 'Lease' : $item['label']);
                            @endphp
                            <li data-bucket="{{ $item['bucket'] }}"
                                data-kind="{{ $item['kind'] }}"
                                data-search="{{ strtolower($item['tenant'] . ' ' . $item['room'] . ' ' . $item['label']) }}">
                                <a href="{{ $item['link'] }}" class="db-due__row {{ $tone }}">
                                    <div class="db-date">
                                        <div class="db-date__m">{{ $item['date']->format('M') }}</div>
                                        <div class="db-date__d">{{ $item['date']->format('d') }}</div>
                                    </div>
                                    <div class="db-who">
                                        <div class="db-who__name">{{ $item['tenant'] }}</div>
                                        <div class="db-who__meta">
                                            <span class="db-kind db-kind--{{ $item['kind'] }}"><i class="bi {{ $kindIcon }}"></i> {{ $kindText }}</span>
                                            <span>Room {{ $item['room'] }}@if($item['bed']) · Bed {{ $item['bed'] }}@endif</span>
                                        </div>
                                    </div>
                                    <div class="db-chip-cell"><span class="db-chip">{{ $chip }}</span></div>
                                    <div class="db-amt">
                                        @if($item['amount'] === null)
                                            <div class="db-amt__v" style="color:var(--pr-gray-500);font-weight:600;">{{ $item['date']->format('M d, Y') }}</div>
                                            <div class="db-amt__s">contract end</div>
                                        @elseif($item['paid_up'])
                                            <div class="db-amt__v" style="color:#15803d;">{{ $peso($item['amount']) }}</div>
                                            <div class="db-amt__s">next month's rent</div>
                                        @else
                                            <div class="db-amt__v" style="{{ $item['bucket'] === 'overdue' ? 'color:#b91c1c;' : '' }}">{{ $peso($item['amount']) }}</div>
                                            <div class="db-amt__s">
                                                @if($item['penalty'] > 0) incl. {{ $peso($item['penalty']) }} penalty @else owed @endif
                                            </div>
                                        @endif
                                    </div>
                                    <i class="bi bi-chevron-right db-due__go"></i>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <div class="db-empty" id="dueEmpty" hidden>
                        <i class="bi bi-inbox"></i>
                        <span id="dueEmptyText">Nothing here.</span>
                    </div>
                @endif
            </div>
        </section>

        {{-- Right column --}}
        <div class="db-side">

            {{-- Tenant status --}}
            <section class="db-card">
                <div class="db-card__head">
                    <h3 class="db-card__title"><i class="bi bi-pie-chart-fill"></i> Tenant Status</h3>
                    <span class="db-card__hint">{{ now()->format('M d, Y') }}</span>
                </div>
                <div class="db-status">
                    <div class="db-donut">
                        <canvas id="tenantStatusChart" width="118" height="118"></canvas>
                        <div class="db-donut__c">
                            <div class="db-donut__v">{{ $totalStatus }}</div>
                            <div class="db-donut__l">Tenants</div>
                        </div>
                    </div>
                    <div>
                        <ul class="db-legend">
                            <li><span class="dot" style="background:#22c55e;"></span><span class="lbl">Approved</span><span class="val">{{ $approvedTenants }}</span><span class="pct">{{ $pct($approvedTenants) }}%</span></li>
                            <li><span class="dot" style="background:#f59e0b;"></span><span class="lbl">Pending</span><span class="val">{{ $pendingApplications }}</span><span class="pct">{{ $pct($pendingApplications) }}%</span></li>
                            <li><span class="dot" style="background:#ef4444;"></span><span class="lbl">Rejected</span><span class="val">{{ $rejectedTenants }}</span><span class="pct">{{ $pct($rejectedTenants) }}%</span></li>
                        </ul>
                        <div class="db-occ">
                            <div class="db-occ__track"><div class="db-occ__fill" style="width: {{ $occPct }}%;"></div></div>
                            <div class="db-occ__lbl"><span>Occupancy</span><strong>{{ $occPct }}%</strong></div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Applications / tenants --}}
            <section class="db-card db-card--grow" id="peopleCard">
                <div class="db-card__head">
                    <h3 class="db-card__title"><i class="bi bi-people-fill"></i> Tenants</h3>
                    <a href="{{ route('manager.tenants') }}" class="db-card__link">Manage <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="db-toolbar">
                    <div class="db-tabs" role="tablist">
                        <button type="button" class="db-tab is-active" data-people-tab="pending" aria-selected="true">
                            Pending <span class="db-tab__n">{{ $pendingApplications }}</span>
                        </button>
                        <button type="button" class="db-tab" data-people-tab="approved" aria-selected="false">
                            Recently approved <span class="db-tab__n">{{ count($approvedTenantList) }}</span>
                        </button>
                    </div>
                </div>
                <div class="db-scroll">
                    <div data-pane="pending">
                        @if(count($pendingTenants) > 0)
                            <ul class="db-people">
                                @foreach($pendingTenants as $tenant)
                                    <li class="db-person">
                                        <div class="db-avatar db-avatar--pending">{{ strtoupper(substr($tenant->first_name ?? $tenant->name ?? '?', 0, 1)) }}</div>
                                        <div class="db-person__main">
                                            <div class="db-person__name">{{ $tenant->first_name ?? '' }} {{ $tenant->last_name ?? $tenant->name ?? '' }}</div>
                                            <div class="db-person__mail">{{ $tenant->email }}</div>
                                        </div>
                                        <div class="db-person__date">{{ $tenant->created_at?->format('M d') }}</div>
                                    </li>
                                @endforeach
                            </ul>
                            @if($pendingApplications > count($pendingTenants))
                                <div class="db-empty" style="padding:10px;">
                                    <a href="{{ route('manager.tenants') }}" class="db-card__link">+{{ $pendingApplications - count($pendingTenants) }} more in Tenant Management</a>
                                </div>
                            @endif
                        @else
                            <div class="db-empty"><i class="bi bi-inbox"></i>No pending applications.</div>
                        @endif
                    </div>
                    <div data-pane="approved" hidden>
                        @if(count($approvedTenantList) > 0)
                            <ul class="db-people">
                                @foreach($approvedTenantList as $tenant)
                                    <li class="db-person">
                                        <div class="db-avatar db-avatar--approved">{{ strtoupper(substr($tenant->first_name ?? $tenant->name ?? '?', 0, 1)) }}</div>
                                        <div class="db-person__main">
                                            <div class="db-person__name">{{ $tenant->first_name ?? '' }} {{ $tenant->last_name ?? $tenant->name ?? '' }}</div>
                                            <div class="db-person__mail">{{ $tenant->email }}</div>
                                        </div>
                                        <div class="db-person__date">{{ $tenant->created_at?->format('M d') }}</div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="db-empty"><i class="bi bi-person-x"></i>No approved tenants yet.</div>
                        @endif
                    </div>
                </div>
            </section>

        </div>
    </div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    /* ---------- Donut ---------- */
    var canvas = document.getElementById('tenantStatusChart');
    if (canvas && window.Chart) {
        var data = [{{ (int) $approvedTenants }}, {{ (int) $pendingApplications }}, {{ (int) $rejectedTenants }}];
        var empty = data.every(function (v) { return v === 0; });
        new Chart(canvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Approved', 'Pending', 'Rejected'],
                datasets: [{
                    data: empty ? [1] : data,
                    backgroundColor: empty ? ['#e5e7eb'] : ['#22c55e', '#f59e0b', '#ef4444'],
                    borderColor: '#ffffff', borderWidth: 2, hoverOffset: 4
                }]
            },
            options: {
                responsive: false, cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: !empty,
                        backgroundColor: '#0c1a3a', titleColor: '#e2c580', bodyColor: '#fff',
                        padding: 10, cornerRadius: 8
                    }
                }
            }
        });
    }

    /* ---------- Due monitor: tabs + search ---------- */
    var monitor = document.getElementById('dueMonitor');
    var list    = document.getElementById('dueList');
    if (monitor && list) {
        var rows      = Array.prototype.slice.call(list.children);
        var tabs      = monitor.querySelectorAll('[data-tab]');
        var search    = document.getElementById('dueSearch');
        var empty     = document.getElementById('dueEmpty');
        var emptyText = document.getElementById('dueEmptyText');
        var active    = monitor.getAttribute('data-default-tab') || 'all';
        var labels    = { all: 'Nothing due.', overdue: 'Nothing overdue. 🎉', today: 'Nothing due today.',
                          week: 'Nothing due in the next 7 days.', month: 'Nothing due in 8–30 days.',
                          lease_end: 'No leases ending in the next 30 days.' };

        function apply() {
            var q = (search.value || '').trim().toLowerCase();
            var shown = 0;
            rows.forEach(function (li) {
                var inTab = active === 'all'
                    || (active === 'lease_end' ? li.dataset.kind === 'lease_end' : li.dataset.bucket === active);
                var inSearch = !q || li.dataset.search.indexOf(q) !== -1;
                var show = inTab && inSearch;
                li.hidden = !show;
                if (show) shown++;
            });
            empty.hidden = shown > 0;
            emptyText.textContent = q ? 'No matches for "' + q + '".' : (labels[active] || 'Nothing here.');
        }

        tabs.forEach(function (btn) {
            btn.addEventListener('click', function () {
                active = btn.dataset.tab;
                tabs.forEach(function (b) {
                    var on = b === btn;
                    b.classList.toggle('is-active', on);
                    b.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                apply();
            });
        });
        search.addEventListener('input', apply);
        apply();
    }

    /* ---------- People tabs ---------- */
    var people = document.getElementById('peopleCard');
    if (people) {
        var pTabs  = people.querySelectorAll('[data-people-tab]');
        var panes  = people.querySelectorAll('[data-pane]');
        pTabs.forEach(function (btn) {
            btn.addEventListener('click', function () {
                pTabs.forEach(function (b) {
                    var on = b === btn;
                    b.classList.toggle('is-active', on);
                    b.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                panes.forEach(function (p) { p.hidden = p.dataset.pane !== btn.dataset.peopleTab; });
            });
        });
    }
})();
</script>
@endpush
