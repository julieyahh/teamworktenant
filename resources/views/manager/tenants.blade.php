@extends('layouts.managerdashboardlayout')

@section('title', 'Pinikitan Rental | Manage Tenants')
@section('page-title', 'Tenant Management')

@push('styles')
<style>
    /* ===================== ALERTS ===================== */
    .pr-alert {
        display:flex; align-items:flex-start; gap:12px;
        padding:14px 18px; border-radius:12px; font-size:.88rem;
        margin-bottom:20px; animation:slideDown .3s ease;
    }
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

    /* ===================== FILTER BAR ===================== */
    .pr-filter {
        background:var(--pr-white); border:1px solid rgba(12,26,58,.07);
        border-radius:16px; box-shadow:0 2px 12px rgba(12,26,58,.06);
        padding:18px 22px; margin-bottom:20px;
    }
    .pr-filter__row { display:flex; align-items:flex-end; gap:12px; flex-wrap:wrap; }
    .pr-filter__group { flex:1; min-width:160px; }
    .pr-filter__label { display:block; font-size:.78rem; font-weight:600; color:var(--pr-navy); margin-bottom:6px; }

    .pr-search-wrap {
        display:flex; align-items:center;
        border:1.5px solid var(--pr-gray-200); border-radius:10px;
        background:var(--pr-white); overflow:hidden; transition:border-color .2s;
    }
    .pr-search-wrap:focus-within { border-color:var(--pr-navy); box-shadow:0 0 0 3px rgba(12,26,58,.07); }
    .pr-search-wrap__icon { padding:0 12px; color:var(--pr-gray-500); font-size:.95rem; flex-shrink:0; }
    .pr-search-input { flex:1; padding:10px 0; border:none; outline:none; font-family:var(--pr-font-body); font-size:.88rem; color:var(--pr-gray-800); background:transparent; }
    .pr-search-btn { padding:10px 14px; background:var(--pr-navy); border:none; color:var(--pr-gold-lt); cursor:pointer; font-size:.9rem; transition:background .2s; flex-shrink:0; }
    .pr-search-btn:hover { background:var(--pr-navy-light); }

    .pr-select-filter {
        width:100%; padding:10px 14px; border:1.5px solid var(--pr-gray-200); border-radius:10px;
        font-family:var(--pr-font-body); font-size:.88rem; color:var(--pr-gray-800);
        background:var(--pr-white); outline:none; cursor:pointer; transition:border-color .2s;
    }
    .pr-select-filter:focus { border-color:var(--pr-navy); box-shadow:0 0 0 3px rgba(12,26,58,.07); }

    .pr-filter__actions { display:flex; align-items:center; gap:8px; flex-shrink:0; }

    /* ===================== BUTTONS ===================== */
    .pr-btn {
        display:inline-flex; align-items:center; gap:7px;
        padding:10px 18px; border-radius:10px;
        font-family:var(--pr-font-body); font-size:.85rem; font-weight:600;
        cursor:pointer; border:none; text-decoration:none; white-space:nowrap;
        transition:all .25s ease;
    }
    .pr-btn--navy { background:linear-gradient(135deg,var(--pr-navy),var(--pr-navy-mid)); color:var(--pr-white); }
    .pr-btn--navy:hover { color:var(--pr-white); transform:translateY(-2px); box-shadow:0 6px 18px rgba(12,26,58,.22); }
    .pr-btn--outline { background:transparent; border:1.5px solid rgba(12,26,58,.15); color:var(--pr-navy); }
    .pr-btn--outline:hover { background:rgba(12,26,58,.05); color:var(--pr-navy); }
    .pr-btn--danger-outline { background:transparent; border:1.5px solid rgba(239,68,68,.3); color:#dc2626; }
    .pr-btn--danger-outline:hover { background:rgba(239,68,68,.06); color:#dc2626; }
    .pr-btn--green { background:#16a34a; color:#fff; }
    .pr-btn--green:hover { background:#15803d; color:#fff; transform:translateY(-1px); }
    .pr-btn--amber { background:#d97706; color:#fff; }
    .pr-btn--amber:hover { background:#b45309; color:#fff; transform:translateY(-1px); }
    .pr-btn--sm { padding:7px 14px; font-size:.8rem; }

    /* ===================== TABLE CARD ===================== */
    .pr-table-card {
        background:var(--pr-white); border:1px solid rgba(12,26,58,.07);
        border-radius:16px; box-shadow:0 2px 12px rgba(12,26,58,.06); overflow:visible;
    }
    .pr-table-card__header {
        display:flex; align-items:center; justify-content:space-between;
        gap:12px; padding:16px 22px; border-bottom:1px solid rgba(12,26,58,.06);
    }
    .pr-table-card__title { font-family:var(--pr-font-display); font-size:.98rem; font-weight:700; color:var(--pr-navy); margin:0; }
    .pr-table-card__count { font-size:.72rem; font-weight:600; padding:3px 10px; border-radius:999px; background:rgba(201,168,76,.1); color:var(--pr-gold); border:1px solid rgba(201,168,76,.2); }

    /* ===================== TABLE ===================== */
    .pr-table-wrap { overflow-x:auto; overflow-y:visible; }
    .pr-table { width:100%; border-collapse:collapse; font-size:.85rem; }
    .pr-table thead th {
        font-size:.68rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase;
        color:var(--pr-gray-500); padding:12px 16px; border-bottom:1px solid rgba(12,26,58,.07);
        background:rgba(12,26,58,.02); text-align:left; white-space:nowrap;
    }
    .pr-table tbody td { padding:13px 16px; color:var(--pr-gray-800); border-bottom:1px solid rgba(12,26,58,.04); vertical-align:middle; overflow:visible; }
    .pr-table tbody tr:last-child td { border-bottom:0; }
    .pr-table tbody tr:hover td { background:rgba(12,26,58,.015); }
    .pr-table tbody { overflow:visible; }
    .pr-table tr { overflow:visible; }

    /* ===================== TENANT AVATAR ===================== */
    .pr-avatar {
        width:32px; height:32px; border-radius:50%;
        display:flex; align-items:center; justify-content:center;
        font-weight:700; font-size:.75rem; flex-shrink:0;
    }
    .pr-avatar--navy { background:linear-gradient(135deg,var(--pr-navy),var(--pr-navy-light)); color:var(--pr-gold-lt); }

    /* ===================== STATUS PILLS ===================== */
    .pr-pill {
        display:inline-flex; align-items:center; gap:5px;
        padding:3px 10px; border-radius:999px; font-size:.71rem; font-weight:600;
    }
    .pr-pill::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; }
    .pr-pill--green  { background:#f0fdf4; color:#16a34a; }
    .pr-pill--amber  { background:#fffbeb; color:#d97706; }
    .pr-pill--red    { background:#fef2f2; color:#dc2626; }
    .pr-pill--gray   { background:#f3f4f6; color:#6b7280; }
    .pr-pill--blue   { background:#eff6ff; color:#2563eb; }
    .pr-pill--navy   { background:rgba(12,26,58,.07); color:var(--pr-navy); }

    /* ===================== BALANCE CELLS ===================== */
    .pr-balance { font-weight:700; }
    .pr-balance--paid   { color:#16a34a; }
    .pr-balance--unpaid { color:#dc2626; }
    .pr-balance--warn   { color:#d97706; }
    .pr-balance__hint   { font-size:.72rem; display:flex; align-items:center; gap:4px; margin-top:2px; }

    /* ===================== ROOM BADGE ===================== */
    .pr-room-badge {
        display:inline-flex; align-items:center; gap:5px;
        padding:3px 10px; border-radius:999px; font-size:.72rem; font-weight:600;
        background:rgba(12,26,58,.07); color:var(--pr-navy);
        border:1px solid rgba(12,26,58,.1);
    }

    /* ===================== DROPDOWN ===================== */
    .pr-dropdown { position:relative !important; }
    .pr-dropdown-toggle {
        width:32px; height:32px; border-radius:8px;
        background:rgba(12,26,58,.06); border:1px solid rgba(12,26,58,.1);
        display:flex; align-items:center; justify-content:center;
        color:var(--pr-navy); cursor:pointer; transition:all .2s;
    }
    .pr-dropdown-toggle:hover { background:var(--pr-navy); color:var(--pr-gold-lt); border-color:var(--pr-navy); }

    .pr-dropdown-menu {
        position:absolute !important; right:0 !important; left:auto !important;
        top:100% !important; margin-top:4px; z-index:1050 !important;
        background:var(--pr-white); border:1px solid rgba(12,26,58,.1);
        border-radius:12px; box-shadow:0 8px 24px rgba(12,26,58,.15);
        min-width:200px; padding:6px; display:none; transform:none !important;
    }
    .pr-dropdown-menu.show { display:block; }

    .pr-dropdown-item {
        display:flex; align-items:center; gap:9px;
        padding:9px 12px; border-radius:8px; font-size:.83rem;
        color:var(--pr-gray-800); cursor:pointer; transition:background .15s;
        text-decoration:none; background:none; border:none; width:100%; text-align:left;
    }
    .pr-dropdown-item:hover { background:rgba(12,26,58,.05); color:var(--pr-navy); }
    .pr-dropdown-item--success { color:#16a34a; }
    .pr-dropdown-item--success:hover { background:#f0fdf4; color:#16a34a; }
    .pr-dropdown-item--danger  { color:#dc2626; }
    .pr-dropdown-item--danger:hover  { background:#fef2f2; color:#dc2626; }
    .pr-dropdown-item--warning { color:#d97706; }
    .pr-dropdown-item--warning:hover { background:#fffbeb; color:#d97706; }
    .pr-dropdown-divider { height:1px; background:rgba(12,26,58,.06); margin:4px 0; }
    .pr-dropdown-text { padding:9px 12px; font-size:.8rem; color:var(--pr-gray-500); }
    .pr-dropdown-text--locked { cursor:not-allowed; opacity:.75; }
    .pr-renew-flag { margin-left:auto; font-size:.62rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; padding:2px 7px; border-radius:999px; background:#dcfce7; color:#166534; }

    /* ===================== EMPTY STATE ===================== */
    .pr-empty { text-align:center; padding:48px 24px; color:var(--pr-gray-500); }
    .pr-empty__icon { font-size:2.5rem; margin-bottom:10px; opacity:.3; }
    .pr-empty__text { font-size:.88rem; }

    /* ===================== MODAL ===================== */
    .pr-modal-content {
        border:0; border-radius:20px !important;
        box-shadow:0 32px 80px rgba(12,26,58,.28); overflow:hidden;
    }
    .pr-modal__header {
        display:flex; align-items:center; justify-content:space-between;
        padding:20px 26px;
        background:linear-gradient(135deg,var(--pr-navy),var(--pr-navy-mid));
        border-bottom:1px solid rgba(201,168,76,.15);
    }
    .pr-modal__header--warning { background:linear-gradient(135deg,#d97706,#f59e0b); }
    .pr-modal__header--teal    { background:linear-gradient(135deg,#0d9488,#2dd4bf); }
    .pr-modal__header--green   { background:linear-gradient(135deg,#16a34a,#4ade80); }
    .pr-modal__eyebrow { font-size:.68rem; font-weight:600; letter-spacing:.14em; text-transform:uppercase; color:rgba(255,255,255,.7); margin-bottom:3px; }
    .pr-modal__title { font-family:var(--pr-font-display); font-size:1.1rem; font-weight:700; color:var(--pr-white); margin:0; }
    .pr-modal__close { width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,.12); border:none; display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,.8); cursor:pointer; transition:all .2s; }
    .pr-modal__close:hover { background:rgba(255,255,255,.22); color:#fff; }
    .pr-modal__body { padding:24px 26px; }
    .pr-modal__footer { display:flex; justify-content:flex-end; gap:10px; padding:16px 26px; border-top:1px solid rgba(12,26,58,.07); background:rgba(12,26,58,.02); }

    /* Form fields in modals */
    .pr-form-group { margin-bottom:16px; }
    .pr-form-label { display:block; font-size:.78rem; font-weight:600; color:var(--pr-navy); margin-bottom:6px; }
    .pr-form-control {
        width:100%; padding:10px 14px; border:1.5px solid var(--pr-gray-200); border-radius:10px;
        font-family:var(--pr-font-body); font-size:.88rem; color:var(--pr-gray-800);
        background:var(--pr-white); outline:none; transition:border-color .2s; box-sizing:border-box;
    }
    .pr-form-control:focus { border-color:var(--pr-navy); box-shadow:0 0 0 3px rgba(12,26,58,.07); }
    .pr-form-control:disabled, .pr-form-control[readonly] { background:var(--pr-gray-100); opacity:.7; }

    /* Info blocks inside modals */
    .pr-info-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:16px; }
    .pr-info-item { background:rgba(12,26,58,.03); border:1px solid rgba(12,26,58,.06); border-radius:10px; padding:12px 14px; }
    .pr-info-item__label { font-size:.68rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--pr-gray-500); margin-bottom:4px; }
    .pr-info-item__value { font-weight:700; color:var(--pr-navy); font-size:.92rem; }
    .pr-info-item__value--green { color:#16a34a; }
    .pr-info-item__value--red   { color:#dc2626; }
    .pr-info-item__value--amber { color:#d97706; }

    .pr-section-label { font-size:.72rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--pr-gold); margin-bottom:12px; display:flex; align-items:center; gap:8px; }
    .pr-section-label::after { content:''; flex:1; height:1px; background:linear-gradient(to right,rgba(201,168,76,.3),transparent); }

    .pr-notice { display:flex; align-items:flex-start; gap:12px; padding:12px 16px; border-radius:10px; font-size:.85rem; margin-bottom:16px; }
    .pr-notice--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .pr-notice--danger  { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
    .pr-notice--info    { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }

    /* Damage row input */
    .pr-damage-row { display:grid; grid-template-columns:1fr 160px 40px; gap:8px; margin-bottom:8px; align-items:center; }

    /* Status group: constrained on desktop, full on mobile */
    .pr-filter__group--status { max-width:200px; }

    /* ===================== RESPONSIVE ===================== */
    @media (max-width:768px) {
        /* Filter bar: 2-col grid on mobile */
        .pr-filter { padding:16px; }
        .pr-filter__row {
            display:grid;
            grid-template-columns:1fr 1fr;
            grid-template-areas:
                "status search"
                "actions actions";
            gap:10px;
            align-items:end;
        }
        .pr-filter__group--status {
            grid-area:status;
            max-width:none;  /* remove desktop cap */
            min-width:0;
        }
        .pr-filter__group:not(.pr-filter__group--status) { grid-area:search; min-width:0; }
        .pr-filter__actions {
            grid-area:actions;
            width:100%;
        }
        .pr-filter__actions .pr-btn {
            width:100%;
            justify-content:center;
        }
        /* Slightly smaller inputs on mobile */
        .pr-filter__label  { font-size:.72rem; }
        .pr-search-input   { font-size:.82rem; }
        .pr-select-filter  { font-size:.82rem; padding:9px 10px; }

        .pr-info-row { grid-template-columns:1fr; }
        .pr-damage-row { grid-template-columns:1fr 120px 36px; }
    }

    @media (max-width:400px) {
        /* Very small screens: full stack */
        .pr-filter__row {
            grid-template-columns:1fr;
            grid-template-areas:
                "status"
                "search"
                "actions";
        }
        .pr-filter__group:not(.pr-filter__group--status) { grid-area:search; }
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
    @if(session('error') || $errors->any())
        <div class="pr-alert pr-alert--error">
            <span class="pr-alert__icon"><i class="bi bi-exclamation-circle-fill"></i></span>
            <span>{{ session('error') ?: $errors->first() }}</span>
            <button class="pr-alert__close" onclick="this.closest('.pr-alert').remove()">✕</button>
        </div>
    @endif --}}

    @if(session('success'))
        <x-alert type="success">{{ session('success') }}</x-alert>
    @endif

    @if(session('error') || $errors->any())
        <x-alert type="error">{{ $errors->first() }}</x-alert>
    @endif

    {{-- Page header --}}
    <div class="pr-dash-header">
        <div class="pr-dash-header__eyebrow">Manager Portal</div>
        <h2 class="pr-dash-header__title">Tenant Management</h2>
        <p class="pr-dash-header__sub">Filter, search, and manage all tenants and their leases.</p>
    </div>

    {{-- Filter bar --}}
    <div class="pr-filter">
        <form method="GET" action="{{ route('manager.tenants') }}">
            <div class="pr-filter__row">

                <div class="pr-filter__group pr-filter__group--status">
                    <label class="pr-filter__label">
                        <i class="bi bi-funnel-fill me-1" style="color:var(--pr-gold);"></i> Status
                    </label>
                    <select name="filter" class="pr-select-filter" onchange="this.form.submit()">
                        <option value="all"      {{ $filter === 'all'      ? 'selected' : '' }}>All Tenants</option>
                        <option value="pending"  {{ $filter === 'pending'  ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ $filter === 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ $filter === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>

                <div class="pr-filter__group">
                    <label class="pr-filter__label">
                        <i class="bi bi-search me-1" style="color:var(--pr-gold);"></i> Search Tenant
                    </label>
                    <div class="pr-search-wrap">
                        <span class="pr-search-wrap__icon"><i class="bi bi-person"></i></span>
                        <input type="text" name="search" class="pr-search-input"
                               placeholder="Search by name…" value="{{ $search }}">
                        <button type="submit" class="pr-search-btn"><i class="bi bi-arrow-right"></i></button>
                    </div>
                </div>

                <div class="pr-filter__actions">
                    <a href="{{ route('manager.tenants.export', ['filter' => $filter, 'search' => $search]) }}"
                       target="_blank" class="pr-btn pr-btn--danger-outline">
                        <i class="bi bi-file-earmark-pdf-fill"></i> Export PDF
                    </a>
                </div>

            </div>
        </form>
    </div>

    {{-- Table card --}}
    <div class="pr-table-card">
        <div class="pr-table-card__header">
            <h3 class="pr-table-card__title">Tenants</h3>
            <span class="pr-table-card__count">{{ $filteredTenants->count() }} records</span>
        </div>

        <div class="pr-table-wrap">
            <table class="pr-table">
                <thead>
                    <tr>
                        <th>Tenant</th>
                        <th>Status</th>
                        <th>Email</th>
                        <th>Room / Lease</th>
                        <th>Deposit Balance</th>
                        <th>Rent Balance</th>
                        <th>Utility Balance</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($filteredTenants as $tenant)
                        @php
                            $activeLeases = $tenant->leases && $tenant->leases->isNotEmpty()
                                ? $tenant->leases->filter(fn($l) => !in_array(strtolower($l->lea_status ?? ''), ['terminated','ended','rejected','voided']))
                                : collect();
                            $leaseCount = max($activeLeases->count(), 1);
                        @endphp

                        @if($activeLeases->isNotEmpty())
                            @foreach($activeLeases as $leaseIndex => $lease)
                                @php
                                    $status     = strtolower($lease->lea_status ?? '');
                                    $today      = \Carbon\Carbon::today();
                                    $dueDate    = $lease->due_date ? \Carbon\Carbon::parse($lease->due_date) : null;
                                    $isOverdue  = $status === 'active' && $dueDate && $today->gt($dueDate);
                                    $isDueToday = $status === 'active' && $dueDate && $today->eq($dueDate);
                                    $isDueSoon  = $status === 'active' && $dueDate && $today->lt($dueDate) && $today->diffInDays($dueDate) <= 5;
                                @endphp
                                <tr>
                                    {{-- Tenant info — first row only --}}
                                    @if($leaseIndex === 0)
                                        <td rowspan="{{ $leaseCount }}">
                                            <div style="display:flex;align-items:center;gap:9px;">
                                                <div class="pr-avatar pr-avatar--navy">{{ strtoupper(substr($tenant->name,0,1)) }}</div>
                                                <span style="font-weight:600;color:var(--pr-navy);">{{ $tenant->name }}</span>
                                            </div>
                                        </td>
                                        <td rowspan="{{ $leaseCount }}">
                                            @php $sPill=['approved'=>'pr-pill--green','pending'=>'pr-pill--amber','rejected'=>'pr-pill--red']; @endphp
                                            <span class="pr-pill {{ $sPill[$tenant->status] ?? 'pr-pill--gray' }}">{{ ucfirst($tenant->status) }}</span>
                                        </td>
                                        <td rowspan="{{ $leaseCount }}" style="color:var(--pr-gray-500);">
                                            {{ $tenant->status !== 'rejected' ? $tenant->email : '—' }}
                                        </td>
                                    @endif

                                    {{-- Room --}}
                                    <td>
                                        <div style="display:flex;flex-wrap:wrap;gap:5px;align-items:center;">
                                            <span class="pr-room-badge">
                                                <i class="bi bi-door-open" style="font-size:.75rem;"></i>
                                                {{ $lease->room_no ?? 'Room' }} · {{ $lease->unit->type ?? 'Unit' }}
                                            </span>
                                            @php
                                                $lsPill=['active'=>'pr-pill--green','approved'=>'pr-pill--green','pending'=>'pr-pill--amber'];
                                            @endphp
                                            <span class="pr-pill {{ $lsPill[$status] ?? 'pr-pill--gray' }}" style="font-size:.65rem;">{{ ucfirst($lease->lea_status ?? 'N/A') }}</span>
                                            @if($isOverdue)
                                                <span class="pr-pill pr-pill--red" style="font-size:.65rem;">Overdue {{ $dueDate->diffInDays($today) }}d</span>
                                            @elseif($isDueToday)
                                                <span class="pr-pill pr-pill--red" style="font-size:.65rem;">Due Today</span>
                                            @elseif($isDueSoon)
                                                <span class="pr-pill pr-pill--amber" style="font-size:.65rem;">Due in {{ $today->diffInDays($dueDate) }}d</span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Deposit --}}
                                    {{-- Status is driven by the balance itself: > 0 is Unpaid, 0 is Paid.
                                         A lease still awaiting approval has no deposit set yet, so it shows
                                         "Awaiting approval" rather than a misleading Paid/₱0.00. --}}
                                    <td>
                                        @php
                                            $dep          = (float) ($lease->deposit_balance ?? 0);
                                            $depositSet   = !is_null($lease->deposit_balance);
                                            $awaitingDep  = $status === 'pending' && !$depositSet;
                                        @endphp
                                        @if($awaitingDep)
                                            <span class="pr-balance pr-balance--warn">—</span>
                                            <div class="pr-balance__hint pr-balance--warn">
                                                <i class="bi bi-hourglass-split"></i> Awaiting approval
                                            </div>
                                        @else
                                            <span class="pr-balance {{ $dep > 0 ? 'pr-balance--unpaid' : 'pr-balance--paid' }}">
                                                ₱{{ number_format($dep, 2) }}
                                            </span>
                                            <div class="pr-balance__hint {{ $dep > 0 ? 'pr-balance--unpaid' : 'pr-balance--paid' }}">
                                                <i class="bi bi-{{ $dep > 0 ? 'exclamation-circle-fill' : 'check-circle-fill' }}"></i>
                                                {{ $dep > 0 ? 'Unpaid' : 'Paid' }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Rent --}}
                                    {{-- Unpaid whenever there is a balance. The due date only decides how
                                         urgent that unpaid balance is; it can no longer make a balance of
                                         ₱5,000 display as "Paid" just because the due date is still ahead. --}}
                                    <td>
                                        @php
                                            $rentBalance = (float) ($lease->rent_balance ?? 0) + (float) ($lease->penalty_fee ?? 0);
                                            $rentUnpaid  = $rentBalance > 0;

                                            if (!$rentUnpaid) {
                                                $rentClass = 'pr-balance--paid';
                                            } elseif ($isOverdue || $isDueToday) {
                                                $rentClass = 'pr-balance--unpaid';
                                            } elseif ($isDueSoon) {
                                                $rentClass = 'pr-balance--warn';
                                            } else {
                                                $rentClass = 'pr-balance--unpaid';
                                            }
                                        @endphp
                                        <span class="pr-balance {{ $rentClass }}">₱{{ number_format($rentBalance, 2) }}</span>
                                        <div class="pr-balance__hint {{ $rentClass }}">
                                            @if(!$rentUnpaid)
                                                <i class="bi bi-check-circle-fill"></i> Paid
                                            @elseif($isOverdue)
                                                <i class="bi bi-exclamation-circle-fill"></i> Unpaid · Overdue {{ $dueDate->diffInDays($today) }}d
                                            @elseif($isDueToday)
                                                <i class="bi bi-exclamation-circle-fill"></i> Unpaid · Due Today
                                            @elseif($isDueSoon)
                                                <i class="bi bi-clock-fill"></i> Unpaid · Due in {{ $today->diffInDays($dueDate) }}d
                                            @else
                                                <i class="bi bi-exclamation-circle-fill"></i> Unpaid
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Utility --}}
                                    <td>
                                        @php $util = (float) ($lease->utility_balance ?? 0); @endphp
                                        <span class="pr-balance {{ $util > 0 ? 'pr-balance--unpaid' : 'pr-balance--paid' }}">
                                            ₱{{ number_format($util, 2) }}
                                        </span>
                                        <div class="pr-balance__hint {{ $util > 0 ? 'pr-balance--unpaid' : 'pr-balance--paid' }}">
                                            <i class="bi bi-{{ $util > 0 ? 'exclamation-circle-fill' : 'check-circle-fill' }}"></i>
                                            {{ $util > 0 ? 'Unpaid' : 'Paid' }}
                                        </div>
                                    </td>

                                    {{-- Actions — first row only --}}
                                    @if($leaseIndex === 0)
                                        <td style="text-align:center;" rowspan="{{ $leaseCount }}">
                                            <div class="pr-dropdown" id="dropdown-{{ $tenant->id }}">
                                                <button type="button" class="pr-dropdown-toggle" onclick="toggleDropdown('dropdown-{{ $tenant->id }}')">
                                                    <i class="bi bi-three-dots-vertical"></i>
                                                </button>
                                                <div class="pr-dropdown-menu" id="menu-{{ $tenant->id }}">
                                                    @if($tenant->status !== 'rejected')
                                                        @if($tenant->tenantApplication && $tenant->tenantApplication->valid_id_path && $tenant->tenantApplication->id_picture_path)
                                                            <a class="pr-dropdown-item" href="{{ route('manager.tenants.viewIds', $tenant->id) }}" target="_blank">
                                                                <i class="bi bi-card-image"></i> View ID
                                                            </a>
                                                        @else
                                                            <div class="pr-dropdown-text"><i class="bi bi-card-image me-1"></i> View ID <small>(Not Available)</small></div>
                                                        @endif
                                                        <a class="pr-dropdown-item" href="#"
                                                           data-bs-toggle="modal" data-bs-target="#utilityProofModal{{ $tenant->id }}">
                                                            <i class="bi bi-receipt"></i> Proof of Billing
                                                        </a>
                                                        {{-- Renovation requests moved to Reports > Renovation Requests,
                                                             where they can be searched, filtered and exported across all
                                                             tenants instead of one modal per tenant. This link filters
                                                             that report to this tenant. --}}
                                                        <a class="pr-dropdown-item"
                                                           href="{{ route('manager.reports.show', ['report' => 'renovation-requests', 'search' => $tenant->name]) }}">
                                                            <i class="bi bi-hammer"></i> Renovation Requests
                                                        </a>
                                                        @php
                                                            // v19: Renew opens only once the tenant has asked.
                                                            $liveLeases = $tenant->leases
                                                                ? $tenant->leases->filter(fn($l) => strtolower($l->lea_status ?? '') === 'active' && !$l->move_out_requested)
                                                                : collect();
                                                            $renewableLeases = $liveLeases->filter(fn($l) => $l->isAwaitingRenewal());
                                                        @endphp
                                                        @if($renewableLeases->isNotEmpty())
                                                            <a class="pr-dropdown-item" href="#"
                                                               data-bs-toggle="modal" data-bs-target="#renewLeaseModal{{ $tenant->id }}">
                                                                <i class="bi bi-arrow-repeat"></i> Renew Lease (+5%)
                                                                <span class="pr-renew-flag">Requested</span>
                                                            </a>
                                                        @elseif($liveLeases->isNotEmpty())
                                                            <div class="pr-dropdown-text pr-dropdown-text--locked"
                                                                 title="The tenant asks from their Lease Management page; Renew unlocks when they do.">
                                                                <i class="bi bi-lock-fill me-1"></i> Renew Lease
                                                                <small>(not requested by tenant)</small>
                                                            </div>
                                                        @endif
                                                        <a class="pr-dropdown-item pr-dropdown-item--warning" href="#"
                                                           data-bs-toggle="modal" data-bs-target="#forceMoveOutModal{{ $tenant->id }}">
                                                            <i class="bi bi-door-open"></i> Force Move Out
                                                        </a>
                                                        @if($tenant->status === 'pending')
                                                            <div class="pr-dropdown-divider"></div>
                                                            <form action="{{ route('manager.tenant.approve', $tenant->id) }}" method="POST">
                                                                @csrf
                                                                <button type="submit" class="pr-dropdown-item pr-dropdown-item--success"
                                                                        onclick="return confirm('Accept {{ $tenant->name }}?')">
                                                                    <i class="bi bi-check-circle-fill"></i> Accept
                                                                </button>
                                                            </form>
                                                            <a class="pr-dropdown-item pr-dropdown-item--danger" href="#"
                                                               data-bs-toggle="modal" data-bs-target="#rejectTenantModal{{ $tenant->id }}">
                                                                <i class="bi bi-x-circle-fill"></i> Reject
                                                            </a>
                                                        @endif
                                                        @php
                                                            $pendingMoveOuts = $tenant->leases
                                                                ? $tenant->leases->filter(fn($l) => in_array(strtolower($l->lea_status), ['active','pending']) && $l->move_out_requested)
                                                                : collect();
                                                        @endphp
                                                        @if($pendingMoveOuts->isNotEmpty())
                                                            <div class="pr-dropdown-divider"></div>
                                                            @foreach($pendingMoveOuts as $moLease)
                                                                <button type="button" class="pr-dropdown-item pr-dropdown-item--warning"
                                                                        data-bs-toggle="modal" data-bs-target="#moveOutReasonModal{{ $moLease->id }}">
                                                                    <i class="bi bi-exclamation-circle"></i> Move Out Requested
                                                                    <small>({{ $moLease->unit->room_no ?? $moLease->room_no }})</small>
                                                                </button>
                                                            @endforeach
                                                        @endif
                                                    @else
                                                        <div class="pr-dropdown-text"><i class="bi bi-info-circle me-1"></i> Reason: {{ $tenant->rejection_reason ?? 'N/A' }}</div>
                                                        <div class="pr-dropdown-divider"></div>
                                                        <a class="pr-dropdown-item" href="#"
                                                           data-bs-toggle="modal" data-bs-target="#tenantDetailsModal{{ $tenant->id }}">
                                                            <i class="bi bi-person-circle"></i> View Details
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach

                        @else
                            {{-- No active leases --}}
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:9px;">
                                        <div class="pr-avatar pr-avatar--navy">{{ strtoupper(substr($tenant->name,0,1)) }}</div>
                                        <span style="font-weight:600;color:var(--pr-navy);">{{ $tenant->name }}</span>
                                    </div>
                                </td>
                                <td>
                                    @php $sPill=['approved'=>'pr-pill--green','pending'=>'pr-pill--amber','rejected'=>'pr-pill--red']; @endphp
                                    <span class="pr-pill {{ $sPill[$tenant->status] ?? 'pr-pill--gray' }}">{{ ucfirst($tenant->status) }}</span>
                                </td>
                                <td style="color:var(--pr-gray-500);">{{ $tenant->status !== 'rejected' ? $tenant->email : '—' }}</td>
                                <td>
                                    @if($tenant->tenantApplication)
                                        @php
                                            $app = $tenant->tenantApplication;
                                            $roomNo   = $app->room_no ?? ($app->unit->room_no ?? 'N/A');
                                            $unitType = $app->unit_type ?? ($app->unit->type ?? 'N/A');
                                        @endphp
                                        <span class="pr-room-badge"><i class="bi bi-door-open" style="font-size:.75rem;"></i> {{ $roomNo }} · {{ $unitType }}</span>
                                    @else
                                        <span style="color:var(--pr-gray-500);font-size:.82rem;">No leases</span>
                                    @endif
                                </td>
                                <td><span style="color:var(--pr-gray-500);">—</span></td>
                                <td><span style="color:var(--pr-gray-500);">—</span></td>
                                <td><span style="color:var(--pr-gray-500);">—</span></td>
                                <td style="text-align:center;">
                                    <div class="pr-dropdown" id="dropdown-nl-{{ $tenant->id }}">
                                        <button type="button" class="pr-dropdown-toggle" onclick="toggleDropdown('dropdown-nl-{{ $tenant->id }}')">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <div class="pr-dropdown-menu" id="menu-nl-{{ $tenant->id }}">
                                            @if($tenant->status !== 'rejected')
                                                @if($tenant->tenantApplication && $tenant->tenantApplication->valid_id_path && $tenant->tenantApplication->id_picture_path)
                                                    <a class="pr-dropdown-item" href="{{ route('manager.tenants.viewIds', $tenant->id) }}" target="_blank">
                                                        <i class="bi bi-card-image"></i> View ID
                                                    </a>
                                                @else
                                                    <div class="pr-dropdown-text"><i class="bi bi-card-image me-1"></i> View ID <small>(Not Available)</small></div>
                                                @endif
                                                @if($tenant->status === 'pending')
                                                    <div class="pr-dropdown-divider"></div>
                                                    <form action="{{ route('manager.tenant.approve', $tenant->id) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="pr-dropdown-item pr-dropdown-item--success"
                                                                onclick="return confirm('Accept {{ $tenant->name }}?')">
                                                            <i class="bi bi-check-circle-fill"></i> Accept
                                                        </button>
                                                    </form>
                                                    <a class="pr-dropdown-item pr-dropdown-item--danger" href="#"
                                                       data-bs-toggle="modal" data-bs-target="#rejectTenantModal{{ $tenant->id }}">
                                                        <i class="bi bi-x-circle-fill"></i> Reject
                                                    </a>
                                                @endif
                                            @else
                                                <div class="pr-dropdown-text"><i class="bi bi-info-circle me-1"></i> Reason: {{ $tenant->rejection_reason ?? 'N/A' }}</div>
                                                <div class="pr-dropdown-divider"></div>
                                                <a class="pr-dropdown-item" href="#"
                                                   data-bs-toggle="modal" data-bs-target="#tenantDetailsModal{{ $tenant->id }}">
                                                    <i class="bi bi-person-circle"></i> View Details
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="pr-empty">
                                    <div class="pr-empty__icon"><i class="bi bi-people"></i></div>
                                    <div class="pr-empty__text">No tenants found for this filter.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{-- end table card --}}

@endsection

@section('modals')

{{-- ============================================================ --}}
{{-- MOVE OUT REASON MODALS --}}
{{-- ============================================================ --}}
@foreach($filteredTenants as $tenant)
    @if($tenant->leases && $tenant->leases->isNotEmpty())
        @foreach($tenant->leases as $lease)
            @if($lease->move_out_requested)
            @php
                $depositAmount = round((float) ($tenant->deposit_amount ?? 0), 2);

                /* Everything still owed on this lease AFTER the advance rent
                   is drawn down against it — same figures and the same
                   reasoning as the Force Move Out modal.

                   These used to be read straight off rent_balance and
                   penalty_fee, which is not what the server settles. It calls
                   consumeAdvanceRent() first, so a tenant paid two months
                   ahead with one month of arrears was shown as owing that
                   month and then refunded it anyway. Lease::settlementPreview()
                   is the exact arithmetic the controller applies, so what is
                   approved here is what is recorded. */
                $sp             = $lease->settlementPreview();
                $unpaidRentReq  = $sp['unpaid_rent'];
                $unpaidUtilReq  = $sp['unpaid_utilities'];
                $unpaidPenReq   = $sp['unpaid_penalty'];
                $unpaidTotalReq = $sp['unpaid_total'];

                /* Rent paid for months that will not happen. Returned on top
                   of the deposit refund — it is not security against damage. */
                $advanceUsedReq   = $sp['advance_used'];
                $advanceRefundReq = $sp['advance_refund'];

                $baseRefundReq = round(max(0, $depositAmount - $unpaidTotalReq) + $advanceRefundReq, 2);
            @endphp
            <div class="modal fade" id="moveOutReasonModal{{ $lease->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content pr-modal-content">
                        <div class="pr-modal__header pr-modal__header--teal">
                            <div>
                                <div class="pr-modal__eyebrow">Lease Management</div>
                                <h5 class="pr-modal__title"><i class="bi bi-door-closed me-2"></i>Move Out Request · Room {{ $lease->room_no }}</h5>
                            </div>
                            <button type="button" class="pr-modal__close" data-bs-dismiss="modal">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>
                        <div class="pr-modal__body">

                            <div class="pr-notice pr-notice--info">
                                <i class="bi bi-chat-left-text" style="flex-shrink:0;"></i>
                                <div><strong>Tenant's reason:</strong> {{ $lease->move_out_reason ?? 'No reason provided.' }}</div>
                            </div>

                            @if($unpaidTotalReq > 0)
                            <div class="pr-section-label">Outstanding Balance on This Lease</div>
                            <div class="pr-info-row" style="grid-template-columns:1fr 1fr 1fr 1fr; margin-bottom:8px;">
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">Unpaid Rent</div>
                                    <div class="pr-info-item__value" id="unpaidRent{{ $lease->id }}">₱{{ number_format($unpaidRentReq, 2) }}</div>
                                </div>
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">Unpaid Utilities</div>
                                    <div class="pr-info-item__value" id="unpaidUtil{{ $lease->id }}">₱{{ number_format($unpaidUtilReq, 2) }}</div>
                                </div>
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">Penalty</div>
                                    <div class="pr-info-item__value" id="unpaidPen{{ $lease->id }}">₱{{ number_format($unpaidPenReq, 2) }}</div>
                                </div>
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">Total Owed</div>
                                    <div class="pr-info-item__value pr-info-item__value--red" id="unpaidTotal{{ $lease->id }}">₱{{ number_format($unpaidTotalReq, 2) }}</div>
                                </div>
                            </div>
                            <div style="font-size:.72rem;color:var(--pr-gray-500);margin-bottom:20px;">
                                Deducted from the deposit refund automatically.
                                @if($advanceUsedReq > 0)
                                    Advance rent of ₱{{ number_format($advanceUsedReq, 2) }} has already been applied, so this is what is left owed.
                                @endif
                            </div>
                            @endif

                            @if($sp['advance_before'] > 0)
                            <div class="pr-section-label">Advance Rent on Account</div>
                            <div class="pr-info-row" style="grid-template-columns:1fr 1fr 1fr; margin-bottom:8px;">
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">Paid in Advance</div>
                                    <div class="pr-info-item__value">₱{{ number_format($sp['advance_before'], 2) }}</div>
                                </div>
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">Applied to Arrears</div>
                                    <div class="pr-info-item__value pr-info-item__value--red">₱{{ number_format($advanceUsedReq, 2) }}</div>
                                </div>
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">Unused — Refundable</div>
                                    <div class="pr-info-item__value pr-info-item__value--green">₱{{ number_format($advanceRefundReq, 2) }}</div>
                                </div>
                            </div>
                            <div style="font-size:.72rem;color:var(--pr-gray-500);margin-bottom:20px;">
                                Unused advance is rent for months the tenant will not be here. It is added to the
                                refund on top of the deposit and is not touched by damage deductions.
                            </div>
                            @endif

                            <div class="pr-section-label">Deposit & Refund</div>
                            <div class="pr-info-row" style="grid-template-columns:1fr 1fr 1fr 1fr;">
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">Original Deposit</div>
                                    <div class="pr-info-item__value pr-info-item__value--green" id="originalDeposit{{ $lease->id }}">₱{{ number_format($depositAmount, 2) }}</div>
                                </div>
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">Total Damages</div>
                                    <div class="pr-info-item__value pr-info-item__value--red" id="totalDamages{{ $lease->id }}">₱0.00</div>
                                </div>
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">+ Unused Advance</div>
                                    <div class="pr-info-item__value pr-info-item__value--green">₱{{ number_format($advanceRefundReq, 2) }}</div>
                                </div>
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">Total Refund</div>
                                    <div class="pr-info-item__value" id="refundAmount{{ $lease->id }}">₱{{ number_format($baseRefundReq, 2) }}</div>
                                </div>
                            </div>

                            <div id="shortfallNotice{{ $lease->id }}" style="display:{{ $unpaidTotalReq > $depositAmount ? 'block' : 'none' }};">
                                <div class="pr-notice pr-notice--danger" style="margin-bottom:20px;">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    <div><strong>The deposit does not cover the total.</strong>
                                        <span id="shortfallAmount{{ $lease->id }}">₱{{ number_format(max(0, $unpaidTotalReq - $depositAmount), 2) }}</span>
                                        will still be owed after this move-out.</div>
                                </div>
                            </div>

                            <div class="pr-section-label">Damage Assessment</div>
                            <div id="damagesContainer{{ $lease->id }}">
                                <div class="pr-damage-row damage-row" data-row="0">
                                    <input type="text" class="pr-form-control damage-item" name="damages[0][item]" placeholder="Item (e.g. Rice Cooker)">
                                    <input type="number" class="pr-form-control damage-cost" name="damages[0][cost]" placeholder="Cost" min="0" step="0.01" data-lease="{{ $lease->id }}">
                                    <button type="button" class="pr-btn pr-btn--danger-outline pr-btn--sm remove-damage" disabled style="padding:7px;justify-content:center;"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                            <button type="button" class="pr-btn pr-btn--outline pr-btn--sm mt-2" id="addDamageBtn{{ $lease->id }}">
                                <i class="bi bi-plus-circle"></i> Add Damage
                            </button>

                            <div class="pr-section-label mt-4">Reject Reason</div>
                            <form id="rejectMoveOutForm{{ $lease->id }}" method="POST" action="{{ route('manager.leases.rejectMoveOut', $lease->id) }}">
                                @csrf
                                <div class="pr-form-group">
                                    <label class="pr-form-label">Reason for rejecting move out</label>
                                    <textarea name="reason" class="pr-form-control" rows="3" placeholder="Enter reason for rejecting…"></textarea>
                                </div>
                            </form>

                            <form id="approveMoveOutForm{{ $lease->id }}" method="POST" action="{{ route('manager.leases.approveMoveOut', $lease->id) }}">
                                @csrf
                                <input type="hidden" name="deposit_amount"  id="depositAmount{{ $lease->id }}" value="{{ $depositAmount }}">
                                <input type="hidden" name="damage_cost"     id="damageCost{{ $lease->id }}" value="0">
                                <input type="hidden" name="refund_amount"   id="refundAmountInput{{ $lease->id }}" value="{{ $baseRefundReq }}">
                                <input type="hidden" name="damages_json"    id="damagesJson{{ $lease->id }}" value="[]">
                            </form>
                        </div>
                        <div class="pr-modal__footer">
                            <button type="button" class="pr-btn pr-btn--outline" data-bs-dismiss="modal">Close</button>
                            <button type="submit" form="rejectMoveOutForm{{ $lease->id }}" class="pr-btn pr-btn--danger-outline">
                                <i class="bi bi-x-circle-fill"></i> Reject
                            </button>
                            <button type="submit" form="approveMoveOutForm{{ $lease->id }}" class="pr-btn pr-btn--green">
                                <i class="bi bi-check-circle-fill"></i> Approve & Refund
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const leaseId = {{ $lease->id }};
                const originalDeposit = {{ $depositAmount }};
                // Arrears on this lease. Deducted alongside the damages.
                // Display only — ManagerController@moveOut recomputes this
                // from the lease, so a stale value here changes nothing.
                const unpaidBalance = {{ $unpaidTotalReq }};
                // Unused advance rent, already net of what the advance covers
                // of the arrears above. Added to the refund AFTER the deposit
                // is floored at zero — damages eat the deposit, never this.
                const advanceRefund = {{ $advanceRefundReq }};
                let damageCounter = 1;

                document.getElementById('addDamageBtn' + leaseId)?.addEventListener('click', function() {
                    const container = document.getElementById('damagesContainer' + leaseId);
                    const row = document.createElement('div');
                    row.className = 'pr-damage-row damage-row mb-2';
                    row.dataset.row = damageCounter;
                    row.innerHTML = `
                        <input type="text" class="pr-form-control damage-item" name="damages[${damageCounter}][item]" placeholder="Item">
                        <input type="number" class="pr-form-control damage-cost" name="damages[${damageCounter}][cost]" placeholder="Cost" min="0" step="0.01" data-lease="${leaseId}">
                        <button type="button" class="pr-btn pr-btn--danger-outline pr-btn--sm remove-damage" style="padding:7px;justify-content:center;"><i class="bi bi-trash"></i></button>
                    `;
                    container.appendChild(row);
                    damageCounter++;
                    attachListeners();
                });

                function calcDamages() {
                    let total = 0; const damages = [];
                    document.querySelectorAll('#damagesContainer' + leaseId + ' .damage-row').forEach(row => {
                        const item = row.querySelector('.damage-item').value.trim();
                        const cost = parseFloat(row.querySelector('.damage-cost').value) || 0;
                        if (item && cost > 0) { total += cost; damages.push({item,cost}); }
                    });

                    // Damages AND the outstanding balance both come out of the
                    // deposit. The unused advance is added on top of whatever
                    // survives — it is the tenant's rent for months that will
                    // not happen, not security the shop can spend on damage.
                    const deducted  = total + unpaidBalance;
                    const refund    = Math.max(0, originalDeposit - deducted) + advanceRefund;
                    const shortfall = Math.max(0, deducted - originalDeposit);

                    document.getElementById('totalDamages' + leaseId).textContent = '₱' + total.toLocaleString('en-US',{minimumFractionDigits:2});
                    const refEl = document.getElementById('refundAmount' + leaseId);
                    refEl.textContent = '₱' + refund.toLocaleString('en-US',{minimumFractionDigits:2});
                    refEl.className = 'pr-info-item__value ' + (refund>=originalDeposit+advanceRefund?'pr-info-item__value--green':refund>0?'':' pr-info-item__value--red');

                    const shortEl = document.getElementById('shortfallNotice' + leaseId);
                    if (shortEl) {
                        shortEl.style.display = shortfall > 0 ? 'block' : 'none';
                        const shortAmtEl = document.getElementById('shortfallAmount' + leaseId);
                        if (shortAmtEl) shortAmtEl.textContent = '₱' + shortfall.toLocaleString('en-US',{minimumFractionDigits:2});
                    }

                    // Only the manager-entered damages go in these fields. The
                    // server adds the outstanding balance itself, so posting it
                    // here too would deduct it twice.
                    document.getElementById('damageCost' + leaseId).value = total.toFixed(2);
                    document.getElementById('refundAmountInput' + leaseId).value = refund.toFixed(2);
                    document.getElementById('damagesJson' + leaseId).value = JSON.stringify(damages);
                }

                function attachListeners() {
                    document.querySelectorAll('#damagesContainer' + leaseId + ' .remove-damage').forEach(btn => {
                        btn.addEventListener('click', function() {
                            if (document.querySelectorAll('#damagesContainer' + leaseId + ' .damage-row').length > 1) {
                                this.closest('.damage-row, .pr-damage-row').remove(); calcDamages();
                            }
                        });
                    });
                    document.querySelectorAll('#damagesContainer' + leaseId + ' .damage-cost, #damagesContainer' + leaseId + ' .damage-item').forEach(inp => {
                        inp.removeEventListener('input', calcDamages);
                        inp.addEventListener('input', calcDamages);
                    });
                }
                attachListeners();
            });
            </script>
            @endif
        @endforeach
    @endif
@endforeach

{{-- ============================================================ --}}
{{-- TENANT DETAILS / REJECT / FORCE MOVE OUT / UTILITY MODALS --}}
{{-- ============================================================ --}}
@foreach($filteredTenants as $tenant)

    {{-- Tenant Details Modal --}}
    <div class="modal fade" id="tenantDetailsModal{{ $tenant->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content pr-modal-content">
                <div class="pr-modal__header">
                    <div>
                        <div class="pr-modal__eyebrow">Tenant Profile</div>
                        <h5 class="pr-modal__title"><i class="bi bi-person-circle me-2"></i>{{ $tenant->name }}</h5>
                    </div>
                    <button type="button" class="pr-modal__close" data-bs-dismiss="modal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="pr-modal__body">
                    <div class="pr-section-label">Personal Information</div>
                    <div class="pr-info-row">
                        <div class="pr-info-item"><div class="pr-info-item__label">Full Name</div><div class="pr-info-item__value">{{ $tenant->name }}</div></div>
                        <div class="pr-info-item"><div class="pr-info-item__label">Email</div><div class="pr-info-item__value" style="font-size:.82rem;">{{ $tenant->email }}</div></div>
                        <div class="pr-info-item"><div class="pr-info-item__label">Contact</div><div class="pr-info-item__value">{{ $tenant->contact_number ?? 'N/A' }}</div></div>
                        <div class="pr-info-item">
                            <div class="pr-info-item__label">Status</div>
                            <div class="pr-info-item__value">
                                @php $sPill=['approved'=>'pr-pill--green','pending'=>'pr-pill--amber','rejected'=>'pr-pill--red']; @endphp
                                <span class="pr-pill {{ $sPill[$tenant->status] ?? 'pr-pill--gray' }}">{{ ucfirst($tenant->status) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="pr-section-label">Financial Information</div>
                    <div class="pr-info-row">
                        <div class="pr-info-item"><div class="pr-info-item__label">Rent Balance</div><div class="pr-info-item__value pr-info-item__value--red">₱{{ number_format($tenant->rent_balance ?? 0, 2) }}</div></div>
                        <div class="pr-info-item"><div class="pr-info-item__label">Utility Balance</div><div class="pr-info-item__value pr-info-item__value--red">₱{{ number_format($tenant->total_utility_balance ?? 0, 2) }}</div></div>
                        <div class="pr-info-item"><div class="pr-info-item__label">Deposit Amount</div><div class="pr-info-item__value">₱{{ number_format($tenant->deposit_amount ?? 0, 2) }}</div></div>
                        <div class="pr-info-item"><div class="pr-info-item__label">User Credit</div><div class="pr-info-item__value pr-info-item__value--green">₱{{ number_format($tenant->user_credit ?? 0, 2) }}</div></div>
                    </div>
                    @if($tenant->tenantApplication)
                        <div class="pr-section-label">Application</div>
                        <div class="pr-info-row">
                            <div class="pr-info-item"><div class="pr-info-item__label">Unit Type</div><div class="pr-info-item__value">{{ $tenant->tenantApplication->unit_type ?? 'N/A' }}</div></div>
                            <div class="pr-info-item"><div class="pr-info-item__label">Applied</div><div class="pr-info-item__value">{{ $tenant->created_at->format('M d, Y') }}</div></div>
                        </div>
                        @if($tenant->rejection_reason)
                            <div class="pr-notice pr-notice--danger">
                                <i class="bi bi-x-circle-fill"></i>
                                <div><strong>Rejection Reason:</strong> {{ $tenant->rejection_reason }}</div>
                            </div>
                        @endif
                    @endif
                </div>
                <div class="pr-modal__footer">
                    <button type="button" class="pr-btn pr-btn--outline" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Reject Tenant Modal --}}
    @if($tenant->status === 'pending')
    <div class="modal fade" id="rejectTenantModal{{ $tenant->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content pr-modal-content">
                <form method="POST" action="{{ route('manager.tenant.reject') }}">
                    @csrf
                    <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                    <div class="pr-modal__header">
                        <div>
                            <div class="pr-modal__eyebrow">Tenant Action</div>
                            <h5 class="pr-modal__title"><i class="bi bi-x-circle me-2"></i>Reject Tenant</h5>
                        </div>
                        <button type="button" class="pr-modal__close" data-bs-dismiss="modal">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="pr-modal__body">
                        <div class="pr-notice pr-notice--warning">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div>You are about to reject <strong>{{ $tenant->name }}</strong>. Please select a reason.</div>
                        </div>
                        <div class="pr-form-group">
                            <label class="pr-form-label">Rejection Reason</label>
                            <select name="rejection_reason" class="pr-form-control" required>
                                <option value="">Select reason…</option>
                                <option value="Incomplete application">Incomplete application</option>
                                <option value="Failed background check">Failed background check</option>
                                <option value="Credit score too low">Credit score too low</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="pr-modal__footer">
                        <button type="button" class="pr-btn pr-btn--outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="pr-btn pr-btn--navy">
                            <i class="bi bi-x-circle-fill"></i> Confirm Reject
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Force Move Out Modal --}}
    <div class="modal fade" id="forceMoveOutModal{{ $tenant->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content pr-modal-content">
                <div class="pr-modal__header pr-modal__header--warning">
                    <div>
                        <div class="pr-modal__eyebrow">Lease Action</div>
                        <h5 class="pr-modal__title"><i class="bi bi-door-open me-2"></i>Force Move Out · {{ $tenant->name }}</h5>
                    </div>
                    <button type="button" class="pr-modal__close" data-bs-dismiss="modal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form id="forceMoveOutForm{{ $tenant->id }}" action="{{ route('manager.tenant.forceMoveOut', $tenant->id) }}" method="POST">
                    @csrf @method('PATCH')
                    <div class="pr-modal__body">
                        <div class="pr-notice pr-notice--danger">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div><strong>Warning:</strong> This will forcibly terminate the tenant's lease. This action cannot be undone.</div>
                        </div>

                        <div class="pr-form-group">
                            <label class="pr-form-label">Tenant</label>
                            <input type="text" class="pr-form-control" value="{{ $tenant->name }}" readonly>
                        </div>

                        <div class="pr-form-group">
                            <label class="pr-form-label">Select Room <span style="color:#dc2626;">*</span></label>
                            <select name="lease_id" id="lease_id{{ $tenant->id }}" class="pr-form-control" required>
                                <option value="" selected disabled>— Select a room —</option>
                                @if($tenant->leases && $tenant->leases->isNotEmpty())
                                    @foreach($tenant->leases as $lease)
                                        @php
                                            $ls = strtolower($lease->lea_status ?? '');
                                            $isAct = in_array($ls, ['active','approved','pending']);
                                            $depPaid = ($lease->deposit_balance ?? 0) == 0;

                                            /* The deposit held is two months of THIS lease's rent.
                                               This used to read rent_balance * 2, which is the
                                               outstanding rent, not the rent — so a tenant who was
                                               paid up showed a deposit of ₱0.00 and got no refund. */
                                            $depAmt  = $depPaid ? round($lease->currentRent() * 2, 2) : 0;

                                            /* Everything still owed on this lease AFTER the advance
                                               rent has been drawn down against it — the server settles
                                               the advance first, so reading rent_balance raw here would
                                               show arrears the tenant's own money already covers.
                                               Lease::settlementPreview() is that exact arithmetic.

                                               The deposit balance is not part of it — an unpaid deposit
                                               means there is no deposit refund. It does not mean there
                                               is no refund: unused advance comes back either way. */
                                            $spF         = $lease->settlementPreview();
                                            $unpaidRent  = $spF['unpaid_rent'];
                                            $unpaidUtil  = $spF['unpaid_utilities'];
                                            $unpaidPen   = $spF['unpaid_penalty'];
                                            $unpaidTotal = $spF['unpaid_total'];

                                            $advBefore   = $spF['advance_before'];
                                            $advUsed     = $spF['advance_used'];
                                            $advRefund   = $spF['advance_refund'];
                                        @endphp
                                        @if($isAct)
                                            <option value="{{ $lease->id }}"
                                                    data-deposit="{{ $depAmt }}"
                                                    data-deposit-paid="{{ $depPaid ? 'true' : 'false' }}"
                                                    data-unpaid-rent="{{ $unpaidRent }}"
                                                    data-unpaid-utilities="{{ $unpaidUtil }}"
                                                    data-unpaid-penalty="{{ $unpaidPen }}"
                                                    data-unpaid-total="{{ $unpaidTotal }}"
                                                    data-advance-before="{{ $advBefore }}"
                                                    data-advance-used="{{ $advUsed }}"
                                                    data-advance-refund="{{ $advRefund }}"
                                                    data-room="{{ $lease->room_no ?? 'Room' }}">
                                                {{ $lease->room_no ?? 'Room' }} · {{ $lease->unit->type ?? 'Unit' }}
                                                ({{ ucfirst($lease->lea_status) }})
                                                @if(!$depPaid) · ⚠ Deposit Unpaid @endif
                                                @if($unpaidTotal > 0) · ₱{{ number_format($unpaidTotal, 2) }} owed @endif
                                                @if($advRefund > 0) · ₱{{ number_format($advRefund, 2) }} advance to refund @endif
                                            </option>
                                        @endif
                                    @endforeach
                                @endif
                            </select>
                            <div style="font-size:.72rem;color:var(--pr-gray-500);margin-top:4px;">Only active/pending leases shown.</div>
                        </div>

                        <div id="unpaidDepositAlertForce{{ $tenant->id }}" style="display:none;">
                            <div class="pr-notice pr-notice--danger">
                                <i class="bi bi-exclamation-circle-fill"></i>
                                <div><strong>Deposit Unpaid.</strong> No deposit refund or damage assessment will be processed.
                                    Any unpaid balance stays owed — this move-out does not write it off.
                                    Unused advance rent, if any, is still returned; see below.</div>
                            </div>
                        </div>

                        {{-- Outstanding balance on the selected lease.

                             Shown whether or not the deposit was paid: if it was, this is
                             deducted from the refund; if it wasn't, the manager still needs
                             to see what is being left uncollected. Read-only — the server
                             recomputes these from the lease on submit, so editing anything
                             here would change nothing. --}}
                        <div id="unpaidBalanceForce{{ $tenant->id }}" style="display:none;">
                            <div class="pr-section-label">Outstanding Balance on This Lease</div>
                            <div class="pr-info-row" style="grid-template-columns:1fr 1fr 1fr 1fr; margin-bottom:8px;">
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">Unpaid Rent</div>
                                    <div class="pr-info-item__value" id="unpaidRentForce{{ $tenant->id }}">₱0.00</div>
                                </div>
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">Unpaid Utilities</div>
                                    <div class="pr-info-item__value" id="unpaidUtilForce{{ $tenant->id }}">₱0.00</div>
                                </div>
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">Penalty</div>
                                    <div class="pr-info-item__value" id="unpaidPenForce{{ $tenant->id }}">₱0.00</div>
                                </div>
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">Total Owed</div>
                                    <div class="pr-info-item__value pr-info-item__value--red" id="unpaidTotalForce{{ $tenant->id }}">₱0.00</div>
                                </div>
                            </div>
                            <div style="font-size:.72rem;color:var(--pr-gray-500);margin-bottom:20px;"
                                 id="unpaidNoteForce{{ $tenant->id }}">
                                Deducted from the deposit refund automatically.
                            </div>
                        </div>

                        {{-- Advance rent. Shown whether or not the deposit was paid, because
                             unused advance is refunded either way — it is rent for months the
                             tenant will not be here, not security against damage. --}}
                        <div id="advanceSectionForce{{ $tenant->id }}" style="display:none;">
                            <div class="pr-section-label">Advance Rent on Account</div>
                            <div class="pr-info-row" style="grid-template-columns:1fr 1fr 1fr; margin-bottom:8px;">
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">Paid in Advance</div>
                                    <div class="pr-info-item__value" id="advanceBeforeForce{{ $tenant->id }}">₱0.00</div>
                                </div>
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">Applied to Arrears</div>
                                    <div class="pr-info-item__value pr-info-item__value--red" id="advanceUsedForce{{ $tenant->id }}">₱0.00</div>
                                </div>
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">Unused — Refundable</div>
                                    <div class="pr-info-item__value pr-info-item__value--green" id="advanceRefundForce{{ $tenant->id }}">₱0.00</div>
                                </div>
                            </div>
                            <div style="font-size:.72rem;color:var(--pr-gray-500);margin-bottom:20px;">
                                Added to the refund on top of the deposit. Damage deductions cannot reach it.
                            </div>
                        </div>

                        {{-- Deposit unpaid but advance sitting on the lease: there is still a
                             refund to pay out, and the manager needs the figure. The deposit
                             grid below stays hidden because there is no deposit to itemise. --}}
                        <div id="advanceOnlyRefundForce{{ $tenant->id }}" style="display:none;">
                            <div class="pr-notice pr-notice--info" style="margin-bottom:20px;">
                                <i class="bi bi-cash-coin"></i>
                                <div><strong>Refund still due:</strong>
                                    <span id="advanceOnlyAmountForce{{ $tenant->id }}">₱0.00</span>
                                    of unused advance rent. The deposit was never paid, so nothing is
                                    refunded from it — but the advance is the tenant's money and is returned.</div>
                            </div>
                        </div>

                        <div id="depositSectionForce{{ $tenant->id }}" style="display:none;">
                            <div class="pr-section-label">Deposit & Refund</div>
                            <div class="pr-info-row" style="grid-template-columns:repeat(5,1fr); margin-bottom:20px;">
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">Original Deposit</div>
                                    <div class="pr-info-item__value pr-info-item__value--green" id="originalDepositForce{{ $tenant->id }}">₱0.00</div>
                                </div>
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">Total Damages</div>
                                    <div class="pr-info-item__value pr-info-item__value--red" id="totalDamagesForce{{ $tenant->id }}">₱0.00</div>
                                </div>
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">Unpaid Balance</div>
                                    <div class="pr-info-item__value pr-info-item__value--red" id="deductUnpaidForce{{ $tenant->id }}">₱0.00</div>
                                </div>
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">+ Unused Advance</div>
                                    <div class="pr-info-item__value pr-info-item__value--green" id="advanceAddForce{{ $tenant->id }}">₱0.00</div>
                                </div>
                                <div class="pr-info-item">
                                    <div class="pr-info-item__label">Total Refund</div>
                                    <div class="pr-info-item__value" id="refundAmountForce{{ $tenant->id }}">₱0.00</div>
                                </div>
                            </div>

                            <div id="shortfallNoticeForce{{ $tenant->id }}" style="display:none;">
                                <div class="pr-notice pr-notice--danger" style="margin-bottom:20px;">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    <div><strong>The deposit does not cover the total.</strong>
                                        <span id="shortfallAmountForce{{ $tenant->id }}">₱0.00</span>
                                        will still be owed after this move-out.</div>
                                </div>
                            </div>

                            <div class="pr-section-label">Damage Assessment</div>
                            <div id="damagesContainerForce{{ $tenant->id }}">
                                <div class="pr-damage-row damage-row" data-row="0">
                                    <input type="text" class="pr-form-control damage-item" name="damages[0][item]" placeholder="Item (e.g. Rice Cooker)">
                                    <input type="number" class="pr-form-control damage-cost" name="damages[0][cost]" placeholder="Cost" min="0" step="0.01" data-tenant="{{ $tenant->id }}">
                                    <button type="button" class="pr-btn pr-btn--danger-outline pr-btn--sm remove-damage" disabled style="padding:7px;justify-content:center;"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                            <button type="button" class="pr-btn pr-btn--outline pr-btn--sm mt-2" id="addDamageBtnForce{{ $tenant->id }}">
                                <i class="bi bi-plus-circle"></i> Add Damage
                            </button>
                        </div>

                        <div class="pr-form-group mt-3">
                            <label class="pr-form-label">Reason for Force Move Out <span style="color:#dc2626;">*</span></label>
                            <textarea name="reason" class="pr-form-control" rows="4" placeholder="Provide a detailed reason…" required></textarea>
                            <div style="font-size:.72rem;color:var(--pr-gray-500);margin-top:4px;">This will be recorded in the system.</div>
                        </div>

                        <input type="hidden" name="deposit_amount"  id="depositAmountForce{{ $tenant->id }}" value="0">
                        <input type="hidden" name="damage_cost"     id="damageCostForce{{ $tenant->id }}"    value="0">
                        <input type="hidden" name="refund_amount"   id="refundAmountInputForce{{ $tenant->id }}" value="0">
                        <input type="hidden" name="damages_json"    id="damagesJsonForce{{ $tenant->id }}"   value="[]">
                        <input type="hidden" name="deposit_paid"    id="depositPaidForce{{ $tenant->id }}"   value="false">
                    </div>
                    <div class="pr-modal__footer">
                        <button type="button" class="pr-btn pr-btn--outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="pr-btn pr-btn--amber"
                                onclick="return confirm('Force {{ $tenant->name }} to move out? This cannot be undone.')">
                            <i class="bi bi-door-open"></i> Confirm Force Move Out
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- The per-tenant Renovation Requests modal that used to sit here was
         removed in v14. The records — and the approve/deny controls — now live
         in Reports > Renovation Requests, so a manager can see every tenant's
         requests in one searchable, exportable table instead of opening one
         modal per tenant. The dropdown item above links straight to it. --}}

    {{-- ============================================================ --}}
    {{-- RENEW LEASE MODAL (applies the 5% yearly increase) --}}
    {{-- ============================================================ --}}
    @php
        // v19: only leases the tenant has asked to renew.
        $renewableLeases = $tenant->leases
            ? $tenant->leases->filter(fn($l) => $l->isAwaitingRenewal())
            : collect();
    @endphp
    @if($renewableLeases->isNotEmpty())
    <div class="modal fade" id="renewLeaseModal{{ $tenant->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content pr-modal-content">
                <div class="pr-modal__header pr-modal__header--green">
                    <div>
                        <div class="pr-modal__eyebrow">Lease Management</div>
                        <h5 class="pr-modal__title"><i class="bi bi-arrow-repeat me-2"></i>Renew Lease · {{ $tenant->name }}</h5>
                    </div>
                    <button type="button" class="pr-modal__close" data-bs-dismiss="modal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="pr-modal__body">
                    <div class="pr-notice pr-notice--warning">
                        <i class="bi bi-info-circle-fill"></i>
                        <div>The tenant has requested a renewal. Renewing extends the lease by one year and increases the monthly rent by <strong>5%</strong>, as per the contract terms.</div>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="pr-table">
                            <thead>
                                <tr>
                                    <th>Room</th>
                                    <th>Lease Ends</th>
                                    <th>Current Rent</th>
                                    <th>Rent After Renewal</th>
                                    <th>Renewals</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($renewableLeases as $rLease)
                                    @php
                                        $currentRent = $rLease->currentRent();
                                        $nextRent    = $rLease->nextRenewalRent();
                                        $dueRenewal  = $rLease->isDueForRenewal();
                                    @endphp
                                    <tr>
                                        <td>
                                            <span class="pr-room-badge">
                                                <i class="bi bi-door-open" style="font-size:.75rem;"></i>
                                                {{ $rLease->room_no ?? 'Room' }} · {{ $rLease->unit->type ?? 'Unit' }}
                                            </span>
                                        </td>
                                        <td style="color:var(--pr-gray-500);white-space:nowrap;">
                                            {{ $rLease->lea_end_date ? \Carbon\Carbon::parse($rLease->lea_end_date)->format('M d, Y') : '—' }}
                                            @if($dueRenewal)
                                                <span class="pr-pill pr-pill--red" style="font-size:.62rem;">Due</span>
                                            @endif
                                            @if($rLease->renewal_requested_at)
                                                <div style="font-size:.7rem;color:var(--pr-gray-500);">Requested {{ $rLease->renewal_requested_at->format('M d, Y') }}</div>
                                            @endif
                                        </td>
                                        <td class="pr-balance">₱{{ number_format($currentRent, 2) }}</td>
                                        <td class="pr-balance pr-balance--warn">
                                            ₱{{ number_format($nextRent, 2) }}
                                            <div style="font-size:.7rem;font-weight:500;">+₱{{ number_format($nextRent - $currentRent, 2) }}</div>
                                        </td>
                                        <td>{{ $rLease->renewal_count ?? 0 }}</td>
                                        <td class="text-center">
                                            <form method="POST" action="{{ route('manager.leases.renew', $rLease->id) }}" class="pr-single-submit">
                                                @csrf
                                                <button type="submit" class="pr-btn pr-btn--green pr-btn--sm"
                                                        onclick="return confirm('Renew this lease for another year and raise the rent to ₱{{ number_format($nextRent, 2) }}?')">
                                                    <i class="bi bi-arrow-repeat"></i> Renew
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="pr-modal__footer">
                    <button type="button" class="pr-btn pr-btn--outline" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Utility Proof Modal --}}
    <div class="modal fade" id="utilityProofModal{{ $tenant->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content pr-modal-content">
                <div class="pr-modal__header pr-modal__header--teal">
                    <div>
                        <div class="pr-modal__eyebrow">Billing Records</div>
                        <h5 class="pr-modal__title"><i class="bi bi-receipt me-2"></i>Proof of Billing · {{ $tenant->name }}</h5>
                    </div>
                    <button type="button" class="pr-modal__close" data-bs-dismiss="modal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="pr-modal__body">
                    @if($tenant->utilityProofs->isEmpty())
                        <div class="pr-empty">
                            <div class="pr-empty__icon"><i class="bi bi-receipt"></i></div>
                            <div class="pr-empty__text">No proof of billing records uploaded yet.</div>
                        </div>
                    @else
                        <div style="overflow-x:auto;">
                            <table class="pr-table">
                                <thead>
                                    <tr>
                                        <th>Billing Month</th>
                                        <th>Utility Balance</th>
                                        <th>Room No.</th>
                                        <th>Uploaded</th>
                                        <th>Proof</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tenant->utilityProofs as $proof)
                                        <tr>
                                            <td>{{ $proof->billing_month ?? '—' }}</td>
                                            <td><span class="pr-balance pr-balance--unpaid">₱{{ number_format($tenant->utility_balance, 2) }}</span></td>
                                            <td>{{ $proof->lease->room_no ?? '—' }}</td>
                                            <td style="color:var(--pr-gray-500);">{{ $proof->created_at->format('M d, Y h:i A') }}</td>
                                            <td>
                                                <a href="{{ asset('storage/'.$proof->file_path) }}" target="_blank" class="pr-btn pr-btn--outline pr-btn--sm">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <div class="pr-modal__footer">
                    <button type="button" class="pr-btn pr-btn--outline" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

@endforeach

@endsection

@push('scripts')
<script>
/* ── Custom dropdown toggle ── */
function toggleDropdown(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const menu = container.querySelector('.pr-dropdown-menu');
    if (!menu) return;
    const isOpen = menu.classList.contains('show');
    // Close all other open menus
    document.querySelectorAll('.pr-dropdown-menu.show').forEach(m => m.classList.remove('show'));
    if (!isOpen) menu.classList.add('show');
}

// Close on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.pr-dropdown')) {
        document.querySelectorAll('.pr-dropdown-menu.show').forEach(m => m.classList.remove('show'));
    }
});

/* ── Force Move Out JS ── */
document.addEventListener('DOMContentLoaded', function() {
    @foreach($filteredTenants as $tenant)
    (function() {
        const tenantId = {{ $tenant->id }};
        let originalDeposit = 0;
        // Arrears on the selected lease. Deducted alongside the damages.
        // Display only — ManagerController@forceMoveOut recomputes this from
        // the lease, so a tampered or stale value here changes nothing.
        let unpaidBalance = 0;
        // Unused advance rent on the selected lease, already net of whatever
        // the advance covers of the arrears above. Added to the refund; never
        // reduced by damages.
        let advanceRefund = 0;
        let damageCounter = 1;

        const leaseSelect   = document.getElementById('lease_id' + tenantId);
        const depositSec    = document.getElementById('depositSectionForce' + tenantId);
        const unpaidAlert   = document.getElementById('unpaidDepositAlertForce' + tenantId);
        const unpaidSec     = document.getElementById('unpaidBalanceForce' + tenantId);
        const advanceSec    = document.getElementById('advanceSectionForce' + tenantId);
        const advanceOnly   = document.getElementById('advanceOnlyRefundForce' + tenantId);

        if (!leaseSelect) return;

        leaseSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const isPaid = opt.getAttribute('data-deposit-paid') === 'true';
            const room   = opt.getAttribute('data-room') || '';

            document.getElementById('depositPaidForce' + tenantId).value = isPaid ? 'true' : 'false';

            // Arrears panel is filled in regardless of deposit status — an
            // unpaid deposit does not make the arrears stop existing.
            const uRent = parseFloat(opt.getAttribute('data-unpaid-rent'))      || 0;
            const uUtil = parseFloat(opt.getAttribute('data-unpaid-utilities')) || 0;
            const uPen  = parseFloat(opt.getAttribute('data-unpaid-penalty'))   || 0;
            unpaidBalance = parseFloat(opt.getAttribute('data-unpaid-total'))   || 0;

            setEl('unpaidRentForce'  + tenantId, '₱' + fmt(uRent));
            setEl('unpaidUtilForce'  + tenantId, '₱' + fmt(uUtil));
            setEl('unpaidPenForce'   + tenantId, '₱' + fmt(uPen));
            setEl('unpaidTotalForce' + tenantId, '₱' + fmt(unpaidBalance));
            if (unpaidSec) unpaidSec.style.display = unpaidBalance > 0 ? 'block' : 'none';

            // Advance panel, also independent of deposit status.
            const advBefore = parseFloat(opt.getAttribute('data-advance-before')) || 0;
            const advUsed   = parseFloat(opt.getAttribute('data-advance-used'))   || 0;
            advanceRefund   = parseFloat(opt.getAttribute('data-advance-refund')) || 0;

            setEl('advanceBeforeForce' + tenantId, '₱' + fmt(advBefore));
            setEl('advanceUsedForce'   + tenantId, '₱' + fmt(advUsed));
            setEl('advanceRefundForce' + tenantId, '₱' + fmt(advanceRefund));
            if (advanceSec) advanceSec.style.display = advBefore > 0 ? 'block' : 'none';

            setEl('unpaidNoteForce' + tenantId, advUsed > 0
                ? 'Deducted from the deposit refund automatically. Advance rent of ₱' + fmt(advUsed) +
                  ' has already been applied, so this is what is left owed.'
                : 'Deducted from the deposit refund automatically.');

            if (isPaid) {
                originalDeposit = parseFloat(opt.getAttribute('data-deposit')) || 0;
                if (unpaidAlert) unpaidAlert.style.display = 'none';
                if (depositSec)  depositSec.style.display  = 'block';
                if (advanceOnly) advanceOnly.style.display = 'none';

                setEl('originalDepositForce' + tenantId, '₱' + fmt(originalDeposit));
                setVal('depositAmountForce' + tenantId, originalDeposit.toFixed(2));
                resetForceDamages();   // recalculates the refund with arrears included
            } else {
                // No deposit means no deposit refund and no damage assessment —
                // but the unused advance is still paid back, so the refund
                // field cannot just be zeroed the way it used to be. The server
                // pays it out regardless; if this posted 0 the manager would be
                // approving a figure that does not match what leaves the till.
                originalDeposit = 0;
                if (unpaidAlert) unpaidAlert.style.display = 'block';
                if (depositSec)  depositSec.style.display  = 'none';

                if (advanceOnly) {
                    advanceOnly.style.display = advanceRefund > 0 ? 'block' : 'none';
                    setEl('advanceOnlyAmountForce' + tenantId, '₱' + fmt(advanceRefund));
                }

                ['depositAmountForce','damageCostForce'].forEach(id => setVal(id+tenantId,'0'));
                setVal('refundAmountInputForce'+tenantId, advanceRefund.toFixed(2));
                setVal('damagesJsonForce'+tenantId,'[]');
            }
        });

        function resetForceDamages() {
            const c = document.getElementById('damagesContainerForce' + tenantId);
            if (!c) return;
            c.querySelectorAll('.damage-row').forEach((r,i) => {
                if (i>0) r.remove();
                else { r.querySelector('.damage-item').value=''; r.querySelector('.damage-cost').value=''; }
            });
            damageCounter = 1;
            setEl('totalDamagesForce'+tenantId,'₱0.00');
            setVal('damageCostForce'+tenantId,'0');
            setVal('damagesJsonForce'+tenantId,'[]');
            attachForceListeners();
            // Recalculate rather than assume a full refund: damages may be
            // zero but the arrears still come off.
            calcForceDamages();
        }

        document.getElementById('addDamageBtnForce' + tenantId)?.addEventListener('click', function() {
            const c = document.getElementById('damagesContainerForce' + tenantId);
            const row = document.createElement('div');
            row.className = 'pr-damage-row damage-row mb-2';
            row.dataset.row = damageCounter;
            row.innerHTML = `
                <input type="text" class="pr-form-control damage-item" name="damages[${damageCounter}][item]" placeholder="Item">
                <input type="number" class="pr-form-control damage-cost" name="damages[${damageCounter}][cost]" placeholder="Cost" min="0" step="0.01" data-tenant="${tenantId}">
                <button type="button" class="pr-btn pr-btn--danger-outline pr-btn--sm remove-damage" style="padding:7px;justify-content:center;"><i class="bi bi-trash"></i></button>
            `;
            c.appendChild(row);
            damageCounter++;
            attachForceListeners();
        });

        function calcForceDamages() {
            let total = 0; const damages = [];
            const c = document.getElementById('damagesContainerForce' + tenantId);
            if (!c) return;
            c.querySelectorAll('.damage-row').forEach(r => {
                const item = r.querySelector('.damage-item').value.trim();
                const cost = parseFloat(r.querySelector('.damage-cost').value) || 0;
                if (item && cost > 0) { total += cost; damages.push({item,cost}); }
            });

            // Damages AND arrears both come out of the deposit. The unused
            // advance is added afterwards, to whatever the deposit has left —
            // it is rent for months that will not happen, not security, so a
            // damage bill bigger than the deposit must not eat into it.
            const deducted = total + unpaidBalance;
            const refund   = Math.max(0, originalDeposit - deducted) + advanceRefund;
            const shortfall = Math.max(0, deducted - originalDeposit);

            setEl('totalDamagesForce'+tenantId,'₱'+fmt(total));
            setEl('deductUnpaidForce'+tenantId,'₱'+fmt(unpaidBalance));
            setEl('advanceAddForce'+tenantId,'₱'+fmt(advanceRefund));

            const refEl = document.getElementById('refundAmountForce'+tenantId);
            if (refEl) {
                refEl.textContent = '₱' + fmt(refund);
                refEl.className = 'pr-info-item__value ' + (refund>=originalDeposit+advanceRefund?'pr-info-item__value--green':refund>0?'':'pr-info-item__value--red');
            }

            // Warn when the deposit runs out — the difference stays owed and
            // has to be chased through Collections, not silently dropped.
            const shortEl = document.getElementById('shortfallNoticeForce'+tenantId);
            if (shortEl) {
                shortEl.style.display = shortfall > 0 ? 'block' : 'none';
                setEl('shortfallAmountForce'+tenantId, '₱' + fmt(shortfall));
            }

            // Only the manager-entered damages go in these fields. The server
            // adds the arrears itself, so posting them here would double them.
            setVal('damageCostForce'+tenantId, total.toFixed(2));
            setVal('refundAmountInputForce'+tenantId, refund.toFixed(2));
            setVal('damagesJsonForce'+tenantId, JSON.stringify(damages));
        }

        function attachForceListeners() {
            const c = document.getElementById('damagesContainerForce' + tenantId);
            if (!c) return;
            c.querySelectorAll('.remove-damage').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (c.querySelectorAll('.damage-row').length > 1) {
                        this.closest('.damage-row, .pr-damage-row').remove(); calcForceDamages();
                    }
                });
            });
            c.querySelectorAll('.damage-cost, .damage-item').forEach(inp => {
                inp.removeEventListener('input', calcForceDamages);
                inp.addEventListener('input', calcForceDamages);
            });
        }
        attachForceListeners();
    })();
    @endforeach
});

function setEl(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }
function setVal(id, val) { const el = document.getElementById(id); if (el) el.value = val; }
function fmt(n) { return Number(n).toLocaleString('en-US',{minimumFractionDigits:2}); }
</script>
@endpush