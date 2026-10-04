@extends('layouts.managerdashboardlayout')

@section('title', 'Pinikitan Rental | ' . ($title ?? 'Report'))
@section('page-title', $title ?? 'Report')

@push('styles')
<style>
    /* ===================== ALERTS ===================== */
    .pr-alert {
        display: flex; align-items: flex-start; gap: 12px;
        padding: 14px 18px; border-radius: 12px; font-size: .88rem;
        margin-bottom: 20px; animation: slideDown .3s ease;
    }
    @keyframes slideDown {
        from { opacity:0; transform:translateY(-8px); }
        to   { opacity:1; transform:translateY(0); }
    }
    .pr-alert--success { background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; }
    .pr-alert--error   { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
    .pr-alert__icon  { font-size:1.1rem; flex-shrink:0; margin-top:1px; }
    .pr-alert__close { margin-left:auto; background:none; border:none; cursor:pointer; opacity:.5; font-size:.9rem; padding:0; transition:opacity .2s; }
    .pr-alert__close:hover { opacity:1; }

    /* ===================== PAGE HEADER ===================== */
    .pr-dash-header { margin-bottom: 24px; }
    .pr-dash-header__eyebrow { font-size: .72rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--pr-gold); margin-bottom: 6px; }
    .pr-dash-header__title { font-family: var(--pr-font-display); font-size: 1.5rem; font-weight: 700; color: var(--pr-navy); margin: 0 0 4px; line-height: 1.2; }
    .pr-dash-header__sub { font-size: .85rem; color: var(--pr-gray-500); margin: 0; }

    /* ===================== TOPROW ===================== */
    .pr-report-toprow { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-bottom: 20px; }

    /* ===================== SUMMARY CARD ===================== */
    .pr-summary { background: var(--pr-white); border: 1px solid rgba(12,26,58,.07); border-radius: 16px; box-shadow: 0 2px 12px rgba(12,26,58,.06); padding: 20px 24px; display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap; margin-bottom: 20px; }
    .pr-summary__left { display:flex; align-items:center; gap:14px; }
    .pr-summary__icon { width: 44px; height: 44px; border-radius: 12px; display:flex; align-items:center; justify-content:center; font-size: 1.2rem; flex-shrink: 0; }
    .pr-summary__icon--green  { background:rgba(34,197,94,.1);  color:#16a34a; }
    .pr-summary__icon--amber  { background:rgba(245,158,11,.1);  color:#d97706; }
    .pr-summary__icon--navy   { background:rgba(12,26,58,.07);   color:var(--pr-navy); }
    .pr-summary__icon--teal   { background:rgba(13,148,136,.1);  color:#0d9488; }
    .pr-summary__label { font-size: .78rem; font-weight: 600; color: var(--pr-gray-500); text-transform: uppercase; letter-spacing: .07em; margin-bottom: 3px; }
    .pr-summary__title { font-family: var(--pr-font-display); font-size: 1.05rem; font-weight: 700; color: var(--pr-navy); margin: 0; }
    .pr-summary__meta { font-size: .82rem; color: var(--pr-gray-500); }
    .pr-summary__meta strong { color: var(--pr-navy); }
    .pr-summary__value-wrap { text-align: right; }
    .pr-summary__value-label { font-size: .75rem; color: var(--pr-gray-500); margin-bottom: 2px; }
    .pr-summary__value { font-family: var(--pr-font-display); font-size: 1.9rem; font-weight: 700; color: var(--pr-navy); line-height: 1; }
    .pr-summary__value--green { color: #16a34a; }
    .pr-summary__value--navy  { color: var(--pr-navy); }

    /* ===================== FILTER CARD ===================== */
    .pr-filter { background: var(--pr-white); border: 1px solid rgba(12,26,58,.07); border-radius: 16px; box-shadow: 0 2px 12px rgba(12,26,58,.06); padding: 18px 22px; margin-bottom: 20px; }
    .pr-filter__row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .pr-filter__group { flex: 1; min-width: 200px; }
    .pr-filter__label { display: block; font-size: .78rem; font-weight: 600; color: var(--pr-navy); margin-bottom: 6px; }
    .pr-search-wrap { display: flex; align-items: center; border: 1.5px solid var(--pr-gray-200); border-radius: 10px; background: var(--pr-white); overflow: hidden; transition: border-color .2s; }
    .pr-search-wrap:focus-within { border-color: var(--pr-navy); box-shadow: 0 0 0 3px rgba(12,26,58,.07); }
    .pr-search-wrap__icon { padding: 0 12px; color: var(--pr-gray-500); font-size: .95rem; flex-shrink: 0; }
    .pr-search-input { flex: 1; padding: 10px 12px 10px 0; border: none; outline: none; font-family: var(--pr-font-body); font-size: .88rem; color: var(--pr-gray-800); background: transparent; }
    .pr-search-hint { font-size: .72rem; color: var(--pr-gray-500); margin-top: 5px; }
    .pr-filter__actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }

    /* ===================== BUTTONS ===================== */
    .pr-btn { display: inline-flex; align-items: center; gap: 7px; padding: 10px 20px; border-radius: 10px; font-family: var(--pr-font-body); font-size: .85rem; font-weight: 600; cursor: pointer; transition: all .25s ease; border: none; text-decoration: none; white-space: nowrap; }
    .pr-btn--navy { background: linear-gradient(135deg, var(--pr-navy), var(--pr-navy-mid)); color: var(--pr-white); }
    .pr-btn--navy:hover { color: var(--pr-white); transform: translateY(-2px); box-shadow: 0 6px 18px rgba(12,26,58,.22); }
    .pr-btn--gold { background: linear-gradient(135deg, var(--pr-gold), var(--pr-gold-lt)); color: var(--pr-navy); }
    .pr-btn--gold:hover { color: var(--pr-navy); transform: translateY(-2px); box-shadow: 0 6px 18px rgba(201,168,76,.3); }
    .pr-btn--outline { background: transparent; border: 1.5px solid rgba(12,26,58,.15); color: var(--pr-navy); }
    .pr-btn--outline:hover { background: rgba(12,26,58,.05); border-color: rgba(12,26,58,.3); color: var(--pr-navy); }
    .pr-btn--danger-outline { background: transparent; border: 1.5px solid rgba(239,68,68,.3); color: #dc2626; }
    .pr-btn--danger-outline:hover { background: rgba(239,68,68,.06); border-color: rgba(239,68,68,.5); color: #dc2626; }
    .pr-btn--green { background: #16a34a; color: #fff; }
    .pr-btn--green:hover { background:#15803d; color:#fff; transform:translateY(-1px); }
    .pr-search-btn { padding: 10px 14px; background: var(--pr-navy); border: none; color: var(--pr-gold-lt); cursor: pointer; font-size: .9rem; transition: background .2s; flex-shrink: 0; }
    .pr-search-btn:hover { background: var(--pr-navy-light); }

    /* ===================== TABLE CARD ===================== */
    .pr-table-card { background: var(--pr-white); border: 1px solid rgba(12,26,58,.07); border-radius: 16px; box-shadow: 0 2px 12px rgba(12,26,58,.06); overflow: hidden; }
    .pr-table-card__header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 22px; border-bottom: 1px solid rgba(12,26,58,.06); }
    .pr-table-card__title { font-family: var(--pr-font-display); font-size: .98rem; font-weight: 700; color: var(--pr-navy); margin: 0; }
    .pr-table-card__count { font-size: .72rem; font-weight: 600; padding: 3px 10px; border-radius: 999px; background: rgba(201,168,76,.1); color: var(--pr-gold); border: 1px solid rgba(201,168,76,.2); }

    /* ===================== TABLE ===================== */
    .pr-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .pr-table thead th { font-size: .68rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--pr-gray-500); padding: 12px 18px; border-bottom: 1px solid rgba(12,26,58,.07); background: rgba(12,26,58,.02); text-align: left; white-space: nowrap; }
    .pr-table tbody td { padding: 13px 18px; color: var(--pr-gray-800); border-bottom: 1px solid rgba(12,26,58,.04); vertical-align: middle; }
    .pr-table tbody tr:last-child td { border-bottom: 0; }
    .pr-table tbody tr:hover td { background: rgba(12,26,58,.015); }

    /* ===================== STATUS PILLS ===================== */
    .pr-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: .71rem; font-weight: 600; }
    .pr-pill::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; }
    .pr-pill--green { background:#f0fdf4; color:#16a34a; }
    .pr-pill--amber { background:#fffbeb; color:#d97706; }
    .pr-pill--red   { background:#fef2f2; color:#dc2626; }
    .pr-pill--gray  { background:#f3f4f6; color:#6b7280; }
    .pr-pill--blue  { background:#eff6ff; color:#2563eb; }
    .pr-pill--teal  { background:#f0fdfa; color:#0d9488; }

    /* ===================== AVATAR ===================== */
    .pr-avatar { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .72rem; flex-shrink: 0; }
    .pr-avatar--navy  { background: linear-gradient(135deg,var(--pr-navy),var(--pr-navy-light)); color: var(--pr-gold-lt); }
    .pr-avatar--green { background: linear-gradient(135deg,#16a34a,#4ade80); color:#fff; }

    /* ===================== EMPTY STATE ===================== */
    .pr-empty { text-align: center; padding: 48px 24px; color: var(--pr-gray-500); }
    .pr-empty__icon { font-size: 2.2rem; margin-bottom: 10px; opacity:.35; }
    .pr-empty__text { font-size: .88rem; }

    /* ===================== PAGINATION ===================== */
    .pr-pagination { display: flex; justify-content: center; align-items: center; gap: 4px; padding: 18px 22px; border-top: 1px solid rgba(12,26,58,.06); }
    .pr-page-btn { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .82rem; font-weight: 500; border: 1.5px solid rgba(12,26,58,.1); background: var(--pr-white); color: var(--pr-navy); text-decoration: none; transition: all .2s; }
    .pr-page-btn:hover { background: rgba(12,26,58,.05); color: var(--pr-navy); }
    .pr-page-btn--active { background: var(--pr-navy); color: var(--pr-gold-lt); border-color: var(--pr-navy); box-shadow: 0 3px 8px rgba(12,26,58,.2); }
    .pr-page-btn--active:hover { background: var(--pr-navy); color: var(--pr-gold-lt); }
    .pr-page-btn--disabled { opacity: .35; pointer-events: none; }

    /* ===================== AMOUNT ===================== */
    .pr-amount { font-weight: 700; color: #16a34a; }

    /* ===================== IMAGE THUMB ===================== */
    .pr-view-btn { display:inline-flex; align-items:center; gap:6px; padding: 5px 12px; border-radius: 8px; font-size: .78rem; font-weight: 600; cursor: pointer; background: rgba(12,26,58,.06); border: 1px solid rgba(12,26,58,.1); color: var(--pr-navy); transition: all .2s; }
    .pr-view-btn:hover { background: var(--pr-navy); color: var(--pr-gold-lt); border-color: var(--pr-navy); }

    /* ===================== SELECT INLINE ===================== */
    .pr-select-sm { padding: 6px 10px; border-radius: 8px; border: 1.5px solid rgba(12,26,58,.1); font-family: var(--pr-font-body); font-size: .8rem; color: var(--pr-navy); background: var(--pr-white); outline: none; cursor: pointer; transition: border-color .2s; }
    .pr-select-sm:focus { border-color: var(--pr-navy); }
    .pr-select-sm:disabled { opacity: .5; cursor: not-allowed; }

    /* ===================== MODAL ===================== */
    .pr-modal .modal-content { border: 0; border-radius: 20px !important; box-shadow: 0 32px 80px rgba(12,26,58,.28); overflow: hidden; }
    .pr-modal__header { display: flex; align-items: center; justify-content: space-between; padding: 20px 26px; background: linear-gradient(135deg, var(--pr-navy), var(--pr-navy-mid)); border-bottom: 1px solid rgba(201,168,76,.15); }
    .pr-modal__eyebrow { font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--pr-gold); margin-bottom: 3px; }
    .pr-modal__title { font-family: var(--pr-font-display); font-size: 1.15rem; font-weight: 700; color: var(--pr-white); margin: 0; }
    .pr-modal__close { width:36px; height:36px; border-radius:50%; background:rgba(255,255,255,.1); border:none; display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,.7); cursor:pointer; transition:all .2s; }
    .pr-modal__close:hover { background:rgba(255,255,255,.2); color:#fff; }
    .pr-modal__body { padding: 24px 26px; }
    .pr-modal__footer { display:flex; justify-content:flex-end; gap:10px; padding: 16px 26px; border-top: 1px solid rgba(12,26,58,.07); }
    .pr-form-group { margin-bottom: 16px; }
    .pr-form-label { display:block; font-size:.78rem; font-weight:600; color:var(--pr-navy); margin-bottom:6px; }
    .pr-form-control { width:100%; padding:10px 14px; border:1.5px solid var(--pr-gray-200); border-radius:10px; font-family:var(--pr-font-body); font-size:.88rem; color:var(--pr-gray-800); background:var(--pr-white); outline:none; transition:border-color .2s; box-sizing:border-box; }
    .pr-form-control:focus { border-color:var(--pr-navy); box-shadow:0 0 0 3px rgba(12,26,58,.07); }
    .pr-form-control:disabled { background:var(--pr-gray-100); opacity:.7; }
    .pr-form-hint { font-size:.72rem; color:var(--pr-gray-500); margin-top:4px; }

    /* ===================== RESPONSIVE ===================== */
    @media (max-width:768px) {
        .pr-summary { flex-direction:column; align-items:flex-start; }
        .pr-summary__value-wrap { text-align:left; }
        .pr-filter__row { flex-direction:column; }
        .pr-filter__actions { width:100%; }
        .pr-report-toprow { flex-direction:column; align-items:flex-start; }
        .pr-table thead th, .pr-table tbody td { padding:10px 12px; }
        .pr-btn { padding: 9px 14px; font-size:.82rem; }
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

    {{-- Page header + PDF button --}}
    <div class="pr-report-toprow">
        <div class="pr-dash-header" style="margin-bottom:0;">
            <div class="pr-dash-header__eyebrow">Reports &amp; Analytics</div>
            <h2 class="pr-dash-header__title">
                @php
                    $icons = [
                        'payment-history'      => 'bi-cash-stack',
                        'active-tenants'       => 'bi-people-fill',
                        'lease-summary'        => 'bi-house-fill',
                        'maintenance-requests' => 'bi-tools',
                        'feedback'             => 'bi-chat-square-heart-fill',
                    ];
                @endphp
                <i class="bi {{ $icons[$report] ?? 'bi-bar-chart-line' }} me-2" style="color:var(--pr-gold);font-size:1.2rem;"></i>
                {{ $title }}
            </h2>
            <p class="pr-dash-header__sub">{{ now()->format('F j, Y') }} · Showing all records matching current filters.</p>
        </div>
        <a href="{{ route('manager.reports.viewReportPdf', ['report' => $report]) }}?{{ http_build_query(request()->all()) }}"
           target="_blank" class="pr-btn pr-btn--danger-outline">
            <i class="bi bi-file-earmark-pdf-fill"></i> Export / Preview PDF
        </a>
    </div>

    {{-- Summary card --}}
    @php
        $summaryIconClass = match($report) {
            'payment-history'      => 'pr-summary__icon--green',
            'maintenance-requests' => 'pr-summary__icon--teal',
            'active-tenants'       => 'pr-summary__icon--navy',
            'lease-summary'        => 'pr-summary__icon--amber',
            'renewal-history'      => 'pr-summary__icon--amber',
            'renovation-requests'  => 'pr-summary__icon--teal',
            'feedback'              => 'pr-summary__icon--amber',
            default                => 'pr-summary__icon--navy',
        };
        $summaryIcon = $icons[$report] ?? 'bi-list-ul';
    @endphp
    <div class="pr-summary">
        <div class="pr-summary__left">
            <div class="pr-summary__icon {{ $summaryIconClass }}">
                <i class="bi {{ $summaryIcon }}"></i>
            </div>
            <div>
                <div class="pr-summary__label">Report Summary</div>
                <div class="pr-summary__title">{{ $title }}</div>
                <div class="pr-summary__meta">
                    @if($report === 'payment-history')
                        Filter: <strong>{{ $currentFilter ? ucfirst($currentFilter) : 'All Categories' }}</strong> ·
                        Date range: <strong>{{ $dateRangeLabel ?? 'All time' }}</strong>
                    @elseif($report === 'maintenance-requests')
                        Status: <strong>{{ request('status') ?: 'All' }}</strong> ·
                        Urgency: <strong>{{ request('urgency') ?: 'All' }}</strong>
                    @elseif($report === 'active-tenants')
                        Unit Type: <strong>{{ request('unit_type') ?: 'All' }}</strong> ·
                        Employment: <strong>{{ request('employment_status') ?: 'All' }}</strong>
                    @elseif($report === 'renewal-history')
                        Source: <strong>{{ request('source') ? ucfirst(request('source')) : 'All' }}</strong>
                    @elseif($report === 'renovation-requests')
                        Status: <strong>{{ request('status') ?: 'All' }}</strong>
                    @elseif($report === 'feedback')
                        Status: <strong>{{ request('status') ?: 'All' }}</strong> ·
                        Category: <strong>{{ request('category') ?: 'All' }}</strong>
                    @else
                        All records
                    @endif
                </div>
            </div>
        </div>
        <div class="pr-summary__value-wrap">
            <div class="pr-summary__value-label">
                {{ $report === 'payment-history' ? 'Total Paid' : 'Total Records' }}
            </div>
            <div class="pr-summary__value {{ $report === 'payment-history' ? 'pr-summary__value--green' : 'pr-summary__value--navy' }}">
                @if($report === 'payment-history')
                    ₱{{ number_format($total ?? 0, 2) }}
                @else
                    {{ $total ?? $data->total() }}
                @endif
            </div>
        </div>
    </div>

    {{-- Filter bar --}}
    @if(in_array($report, ['active-tenants','pending-tenants','rejected-tenants','maintenance-requests','payment-history','lease-summary','renewal-history','renovation-requests','feedback']))
    <div class="pr-filter">
        <form method="GET" action="{{ route('manager.reports.show', ['report' => $report]) }}">
            <div class="pr-filter__row">
                <div class="pr-filter__group">
                    @php
                        $searchLabels = [
                            'payment-history'      => ['icon'=>'bi-credit-card-fill', 'label'=>'Search Payments',      'ph'=>'Tenant, amount, date, purpose, status, room, reference...'],
                            'maintenance-requests' => ['icon'=>'bi-tools',            'label'=>'Search Maintenance',    'ph'=>'Issue, tenant name, status or urgency...'],
                            'active-tenants'       => ['icon'=>'bi-people-fill',      'label'=>'Search Active Tenants', 'ph'=>'Tenant name...'],
                            'lease-summary'        => ['icon'=>'bi-file-text-fill',   'label'=>'Search Leases',         'ph'=>'Tenant, unit type, date, status, room...'],
                            'renewal-history'      => ['icon'=>'bi-arrow-repeat',     'label'=>'Search Renewals',       'ph'=>'Tenant, room, rent amount, month or year...'],
                            'renovation-requests'  => ['icon'=>'bi-hammer',           'label'=>'Search Renovations',    'ph'=>'Tenant, room, type, status, description...'],
                            'feedback'              => ['icon'=>'bi-chat-square-heart-fill', 'label'=>'Search Feedback', 'ph'=>'Tenant, subject, message, category, status...'],
                        ];
                        $sl = $searchLabels[$report] ?? ['icon'=>'bi-search','label'=>'Search','ph'=>'Search records...'];
                    @endphp
                    <label class="pr-filter__label">
                        <i class="bi {{ $sl['icon'] }} me-1" style="color:var(--pr-gold);"></i>
                        {{ $sl['label'] }}
                    </label>
                    <div class="pr-search-wrap">
                        <span class="pr-search-wrap__icon"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="pr-search-input"
                               placeholder="{{ $sl['ph'] }}" value="{{ request('search') }}">
                        <button type="submit" class="pr-search-btn"><i class="bi bi-arrow-right"></i></button>
                    </div>
                    @if($report === 'payment-history')
                        <div class="pr-search-hint">Search any column: tenant name, amount, purpose, status, room, reference #</div>
                    @elseif($report === 'lease-summary')
                        <div class="pr-search-hint">Search: tenant name, unit type, lease dates, status (active/terminated), room</div>
                    @elseif($report === 'active-tenants')
                        <div class="pr-search-hint">Search: tenant name (John or John Doe), email (john@example.com)</div>
                    @elseif($report === 'maintenance-requests')
                        <div class="pr-search-hint">Search: Issue, tenant name, status (Pending, In Progress, Completed, Cancelled) or urgency (Low, Mid, High)</div>
                    @elseif($report === 'renewal-history')
                        <div class="pr-search-hint">Search: tenant name, room, rent amount, month (March) or year (2026)</div>
                    @elseif($report === 'renovation-requests')
                        <div class="pr-search-hint">Search: tenant name, room, renovation type, status (Pending, Approved, Denied, Cancelled) or description</div>
                    @elseif($report === 'feedback')
                        <div class="pr-search-hint">Search: tenant name, subject, message, category or status (New, Reviewed)</div>
                    @endif
                </div>
                @if($report === 'payment-history')
                    <div class="pr-filter__group">
                        <label class="pr-filter__label">
                            <i class="bi bi-calendar-range me-1" style="color:var(--pr-gold);"></i>
                            Date Range
                        </label>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                            <select name="range" class="pr-select-sm" onchange="prToggleCustomDateRange(this)">
                                <option value="">All time</option>
                                @foreach(\App\Support\DateRangeResolver::SHORTCUTS as $key => $label)
                                    <option value="{{ $key }}" @selected(request('range') === $key)>{{ $label }}</option>
                                @endforeach
                                <option value="custom" @selected(request()->filled('date_from') || request()->filled('date_to'))>
                                    Custom range…
                                </option>
                            </select>
                            <span id="customDateFields"
                                  style="display:{{ (request()->filled('date_from') || request()->filled('date_to')) ? 'inline-flex' : 'none' }};gap:6px;align-items:center;">
                                <input type="date" name="date_from" value="{{ request('date_from') }}" class="pr-select-sm">
                                <span style="color:var(--pr-gray-500);font-size:.8rem;">to</span>
                                <input type="date" name="date_to" value="{{ request('date_to') }}" class="pr-select-sm">
                                <button type="submit" class="pr-btn pr-btn--outline pr-btn--sm">Apply</button>
                            </span>
                        </div>
                        @if($dateRangeLabel ?? null)
                            <div class="pr-search-hint">
                                Showing: <strong>{{ $dateRangeLabel }}</strong> —
                                <a href="{{ route('manager.reports.show', ['report' => $report, 'search' => request('search')]) }}">clear</a>
                            </div>
                        @endif
                    </div>
                @endif
                <div class="pr-filter__actions">
                    @if($report === 'payment-history')
                        <button type="button" class="pr-btn pr-btn--green"
                                data-bs-toggle="modal" data-bs-target="#makePaymentModal">
                            <i class="bi bi-cash-coin"></i> Make Payment
                        </button>
                    @endif
                    @if(in_array($report, ['payment-history','maintenance-requests','lease-summary','active-tenants','renewal-history','renovation-requests','feedback']))
                        <a href="{{ route('manager.reports.export', ['report' => $report]) }}?{{ http_build_query(request()->except('report')) }}"
                           target="_blank" class="pr-btn pr-btn--outline">
                            <i class="bi bi-file-earmark-pdf-fill"></i> Export PDF
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>
    @endif

    {{-- ======== DATA TABLE ======== --}}
    <div class="pr-table-card">
        <div class="pr-table-card__header">
            <h3 class="pr-table-card__title">{{ $title }} Records</h3>
            <span class="pr-table-card__count">{{ $data->total() }} records</span>
        </div>

        <div style="overflow-x:auto;">
            <table class="pr-table">
                <thead>
                    @if($report === 'payment-history')
                        <tr>
                            <th>Tenant</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Unit</th>
                            <th>Method</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th>Proof</th>
                        </tr>
                    @elseif(in_array($report, ['active-tenants','pending-tenants','rejected-tenants']))
                        <tr>
                            <th>Full Name</th><th>Email</th><th>Contact</th><th>Unit Type</th>
                            <th>Employment</th><th>Income Source</th><th>Emergency Contact</th><th>Status</th>
                        </tr>
                    @elseif($report === 'lease-summary')
                        <tr>
                            <th>Tenant</th><th>Unit No.</th><th>Unit Type</th>
                            <th>Start</th><th>End</th><th>Term</th><th>Status</th>
                        </tr>
                    @elseif($report === 'maintenance-requests')
                        <tr>
                            <th>Tenant</th><th>Unit</th><th>Description</th>
                            <th>Urgency</th><th>Preferred</th><th>Repair</th>
                            <th>Status</th><th>Image</th><th></th>
                        </tr>
                    @elseif($report === 'renewal-history')
                        <tr>
                            <th>Renewed On</th><th>Tenant</th><th>Unit</th><th>Renewal #</th>
                            <th>Rent Before</th><th>Rent After</th><th>Increase</th>
                            <th>New Term</th><th>Source</th>
                        </tr>
                    @elseif($report === 'renovation-requests')
                        <tr>
                            <th>Submitted</th><th>Tenant</th><th>Room</th><th>Type</th>
                            <th>Request</th><th>Est. Cost</th><th>Preferred Date</th>
                            <th class="text-center">Status</th><th class="text-center">Decision</th>
                        </tr>
                    @elseif($report === 'feedback')
                        <tr>
                            <th>Date</th><th>Tenant</th><th>Category</th><th>Rating</th>
                            <th>Feedback</th><th class="text-center">Status</th><th class="text-center">Response</th>
                        </tr>
                    @endif
                </thead>

                <tbody>
                    @forelse($data as $item)

                        {{-- ── PAYMENT HISTORY ── --}}
                        @if($report === 'payment-history')
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:9px;">
                                        <div class="pr-avatar pr-avatar--navy">{{ strtoupper(substr($item->tenant->name ?? '?', 0, 1)) }}</div>
                                        <span style="font-weight:600;color:var(--pr-navy);">{{ $item->tenant->name ?? 'N/A' }}</span>
                                    </div>
                                </td>
                                <td><span class="pr-amount">₱{{ number_format($item->pay_amount, 2) }}</span></td>
                                <td style="color:var(--pr-gray-500);">{{ $item->pay_date?->format('M d, Y') ?? 'N/A' }}</td>
                                <td>
                                    <span style="font-weight:600;">{{ $item->lease->unit->type }}</span><br>
                                    <small style="color:var(--pr-gray-500);">
                                        {{ $item->lease->unit->room_no }}
                                        @if($item->lease->unit->type === 'Bed-Spacer') · Bed {{ $item->lease->bed_number }} @endif
                                    </small>
                                </td>
                                <td style="color:var(--pr-gray-500);">{{ $item->pay_method }}</td>
                                <td>
                                    {{ ucfirst($item->payment_for) }}
                                    {{-- Electricity / Water sub-label. The column on `payments` is
                                         utility_type, NOT billing_type. --}}
                                    @if($item->payment_for === 'Utilities' && $item->utility_type)
                                        <div style="font-size:.72rem;font-weight:600;margin-top:2px;
                                            color:{{ $item->utility_type === 'Electricity' ? '#d97706' : '#2563eb' }};">
                                            {{ $item->utility_type === 'Electricity' ? '⚡' : '💧' }}
                                            {{ $item->utility_type }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @php $pillMap = ['Accepted'=>'pr-pill--green','Rejected'=>'pr-pill--red','Pending'=>'pr-pill--amber']; @endphp
                                    <span class="pr-pill {{ $pillMap[$item->pay_status] ?? 'pr-pill--gray' }}">{{ $item->pay_status }}</span>
                                    @if($item->pay_status === 'Accepted')
                                        <a href="{{ route('manager.payments.invoice', $item->id) }}" title="Download invoice"
                                           style="display:inline-block;margin-left:4px;font-size:.72rem;font-weight:600;color:var(--pr-navy);">
                                            <i class="bi bi-file-earmark-arrow-down"></i> Invoice
                                        </a>
                                    @endif
                                </td>
                                <td>
                                    @if($item->proof)
                                        <button type="button" class="pr-view-btn view-image-btn"
                                                data-bs-toggle="modal" data-bs-target="#viewImageModal"
                                                data-image="{{ asset('storage/' . $item->proof) }}"
                                                data-title="Payment Proof"
                                                data-payment-id="{{ $item->id }}"
                                                data-status="{{ $item->pay_status }}">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                    @else
                                        <span style="font-size:.78rem;color:var(--pr-gray-500);font-style:italic;">No proof</span>
                                    @endif
                                </td>
                            </tr>

                        {{-- ── TENANTS ── --}}
                        @elseif(in_array($report, ['active-tenants','pending-tenants','rejected-tenants']))
                            @php
                                $app = $item->tenantApplication;
                                $lease = $item->leases->first();
                                $unitType = $app->unit_type ?? 'N/A';
                                $roomNo = $lease->room_no ?? $app->room_no ?? 'N/A';
                                $bedNumber = $lease->bed_number ?? $app->bed_number ?? null;
                                if ($unitType === 'Bed-Spacer' && $bedNumber) $unitDisplay = "$unitType · $roomNo · Bed $bedNumber";
                                elseif ($roomNo !== 'N/A') $unitDisplay = "$unitType · $roomNo";
                                else $unitDisplay = $unitType;
                            @endphp
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:9px;">
                                        <div class="pr-avatar pr-avatar--navy">{{ strtoupper(substr($item->name ?? '?', 0, 1)) }}</div>
                                        <span style="font-weight:600;color:var(--pr-navy);">{{ $item->name }}</span>
                                    </div>
                                </td>
                                <td style="color:var(--pr-gray-500);">{{ $item->email }}</td>
                                <td style="color:var(--pr-gray-500);">{{ $app->contact_number ?? 'N/A' }}</td>
                                <td>{{ $unitDisplay }}</td>
                                <td>{{ $app->employment_status ?? 'N/A' }}</td>
                                <td style="color:var(--pr-gray-500);">{{ $app->source_of_income ?? 'N/A' }}</td>
                                <td>
                                    <span style="font-weight:600;">{{ $app->emergency_name ?? 'N/A' }}</span><br>
                                    <small style="color:var(--pr-gray-500);">{{ $app->emergency_number ?? '' }}</small>
                                </td>
                                <td>
                                    @php $sPill = ['approved'=>'pr-pill--green','pending'=>'pr-pill--amber','rejected'=>'pr-pill--red']; @endphp
                                    <span class="pr-pill {{ $sPill[$item->status] ?? 'pr-pill--gray' }}">{{ ucfirst($item->status) }}</span>
                                </td>
                            </tr>

                        {{-- ── LEASE SUMMARY ── --}}
                        @elseif($report === 'lease-summary')
                            @php
                                $leases = $item->leases ?? collect();
                                $firstLease = $leases->first();
                                $unitType = $item->tenantApplication->unit_type ?? 'N/A';
                                $roomNo   = $firstLease->room_no ?? 'N/A';
                                $bedNum   = $firstLease->bed_number ?? null;
                                if ($unitType === 'Bed-Spacer' && $bedNum) $unitDisplay = "$unitType · $roomNo · Bed $bedNum";
                                elseif ($roomNo !== 'N/A') $unitDisplay = "$unitType · $roomNo";
                                else $unitDisplay = $unitType;
                            @endphp
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:9px;">
                                        <div class="pr-avatar pr-avatar--navy">{{ strtoupper(substr($item->name ?? '?', 0, 1)) }}</div>
                                        <span style="font-weight:600;color:var(--pr-navy);">{{ $item->name }}</span>
                                    </div>
                                </td>
                                <td id="unit_number_{{ $item->id }}">{{ $firstLease->unit_id ?? 'N/A' }}</td>
                                <td>
                                    @if($leases->count() > 1)
                                        <select class="pr-select-sm" onchange="updateLeaseDetails(this, {{ $item->id }})">
                                            @foreach($leases as $l)
                                                @php
                                                    $rn = $l->room_no ?? 'N/A'; $bn = $l->bed_number ?? null;
                                                    if ($unitType === 'Bed-Spacer' && $bn) $lDisplay = "$unitType · $rn · Bed $bn";
                                                    elseif ($rn !== 'N/A') $lDisplay = "$unitType · $rn";
                                                    else $lDisplay = $unitType;
                                                @endphp
                                                <option value="{{ $l->id }}"
                                                    data-unit-number="{{ $l->unit_id }}"
                                                    data-start="{{ $l->lea_start_date }}"
                                                    data-end="{{ $l->lea_end_date }}"
                                                    data-terms="{{ $l->lea_terms }}"
                                                    data-status="{{ $l->lea_status }}">
                                                    {{ $lDisplay }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @else
                                        {{ $unitDisplay }}
                                    @endif
                                </td>
                                <td id="start_date_{{ $item->id }}" style="color:var(--pr-gray-500);">
                                    {{ $firstLease?->lea_start_date ? \Carbon\Carbon::parse($firstLease->lea_start_date)->format('M d, Y') : 'N/A' }}
                                </td>
                                <td id="end_date_{{ $item->id }}" style="color:var(--pr-gray-500);">
                                    {{ $firstLease?->lea_end_date ? \Carbon\Carbon::parse($firstLease->lea_end_date)->format('M d, Y') : 'N/A' }}
                                </td>
                                <td id="terms_{{ $item->id }}">{{ $firstLease?->lea_terms ?? 'N/A' }}</td>
                                <td id="status_{{ $item->id }}">
                                    @php $ls = $firstLease?->lea_status; $lPill = ['active'=>'pr-pill--green','terminated'=>'pr-pill--red']; @endphp
                                    <span class="pr-pill {{ $lPill[$ls] ?? 'pr-pill--gray' }}">{{ ucfirst($ls ?? 'N/A') }}</span>
                                </td>
                            </tr>

                        {{-- ── MAINTENANCE ── --}}
                        @elseif($report === 'maintenance-requests')
                            @php
                                $unitType = $item->unit?->type ?? $item->unit_type ?? null;
                                $roomNo   = $item->unit?->room_no ?? $item->room_no ?? null;
                                $bedNum   = $item->tenant?->leases->first()?->bed_number ?? null;
                                if ($unitType && $roomNo) {
                                    $unitDisplay = ($unitType === 'Bed-Spacer' && $bedNum) ? "$unitType · $roomNo · Bed $bedNum" : "$unitType · $roomNo";
                                } else { $unitDisplay = $unitType ?? 'N/A'; }
                                $urgPill = ['high'=>'pr-pill--red','mid'=>'pr-pill--amber','low'=>'pr-pill--teal'];
                            @endphp
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:9px;">
                                        <div class="pr-avatar pr-avatar--navy">{{ strtoupper(substr($item->tenant->name ?? '?', 0, 1)) }}</div>
                                        <span style="font-weight:600;color:var(--pr-navy);">{{ $item->tenant->name ?? 'N/A' }}</span>
                                    </div>
                                </td>
                                <td>{{ $unitDisplay }}</td>
                                <td style="max-width:220px;white-space:normal;">{{ $item->description }}</td>
                                <td><span class="pr-pill {{ $urgPill[$item->urgency] ?? 'pr-pill--gray' }}">{{ ucfirst($item->urgency) }}</span></td>
                                <td style="color:var(--pr-gray-500);">{{ \Carbon\Carbon::parse($item->supposed_date)->format('M d, Y') }}</td>

                                {{-- Repair-at-a-glance: schedule, technician,
                                     cost and who pays. Before this column
                                     existed there was no way to tell from the
                                     list which jobs were still unscheduled or
                                     which finished repairs had a cost sitting
                                     on them with nobody assigned to pay it. --}}
                                <td style="min-width:170px;">
                                    @if($item->scheduled_date)
                                        <div style="font-weight:600;font-size:.82rem;">
                                            <i class="bi bi-calendar-check" style="color:#2563eb;"></i>
                                            {{ \Carbon\Carbon::parse($item->scheduled_date)->format('M d, Y') }}
                                        </div>
                                    @else
                                        <div style="font-size:.78rem;color:var(--pr-gray-500);font-style:italic;">
                                            Not scheduled
                                        </div>
                                    @endif

                                    @if($item->assigned_to)
                                        <div style="font-size:.74rem;color:var(--pr-gray-500);margin-top:2px;">
                                            <i class="bi bi-person-gear"></i> {{ $item->assigned_to }}
                                        </div>
                                    @endif

                                    @if((float) $item->total_cost > 0)
                                        <div style="font-size:.74rem;margin-top:3px;">
                                            &#8369;{{ number_format((float) $item->total_cost, 2) }} ·
                                            @if($item->needsResponsibilityDecision())
                                                <span style="color:#b91c1c;font-weight:700;">who pays?</span>
                                            @else
                                                <span style="color:var(--pr-gray-500);">{{ $item->responsibilityLabel() }}</span>
                                            @endif
                                        </div>
                                        @if($item->isBillableToTenant())
                                            <div style="font-size:.72rem;color:#b45309;font-weight:700;margin-top:2px;">
                                                &#8369;{{ number_format((float) $item->tenant_charge_amount, 2) }} not yet billed
                                            </div>
                                        @endif
                                    @endif
                                </td>

                                <td>
                                    <form action="{{ route('manager.requests.updateStatus', $item->id) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <select name="status" class="pr-select-sm" onchange="this.form.submit()"
                                                {{ $item->status === 'Cancelled' ? 'disabled' : '' }}>
                                            @foreach(['Pending','In Progress','Completed','Cancelled'] as $s)
                                                <option value="{{ $s }}" {{ $item->status === $s ? 'selected' : '' }}>{{ $s }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    @if(!empty($item->issue_image) && file_exists(storage_path('app/public/' . $item->issue_image)))
                                        <button type="button" class="pr-view-btn view-image-btn"
                                                data-bs-toggle="modal" data-bs-target="#viewImageModal"
                                                data-image="{{ asset('storage/' . $item->issue_image) }}"
                                                data-title="Issue Image">
                                            <i class="bi bi-image"></i> View
                                        </button>
                                    @else
                                        <span style="font-size:.78rem;color:var(--pr-gray-500);font-style:italic;">No image</span>
                                    @endif
                                </td>
                                {{-- The detail screen (manager/requests/show)
                                     is where assignment, scheduling, diagnosis,
                                     resolution notes, work items, cost
                                     responsibility and charging all live. It
                                     had no link from anywhere in the app, so
                                     none of those fields were reachable
                                     without typing the URL by hand. --}}
                                <td>
                                    <a href="{{ route('manager.requests.show', $item->id) }}"
                                       class="pr-view-btn" style="white-space:nowrap;">
                                        <i class="bi bi-box-arrow-up-right"></i> Open
                                    </a>
                                </td>
                            </tr>

                        {{-- ── RENEWAL HISTORY ── --}}
                        @elseif($report === 'renewal-history')
                            @php
                                $rTenant  = $item->tenant;
                                $rName    = $rTenant?->name ?? $rTenant?->tenantApplication?->full_name ?? 'Unknown tenant';
                                $rType    = $item->unit?->type ?? $item->lease?->unit?->type ?? null;
                                $rRoom    = $item->room_no ?? $item->lease?->room_no ?? null;
                                $rUnit    = $rType && $rRoom ? "$rType · $rRoom" : ($rRoom ?? $rType ?? 'N/A');
                                $srcPill  = match($item->source) {
                                    'scheduled' => 'pr-pill--teal',
                                    'legacy'    => 'pr-pill--gray',
                                    default     => 'pr-pill--navy',
                                };
                            @endphp
                            <tr>
                                <td style="color:var(--pr-gray-500);white-space:nowrap;">
                                    {{ $item->created_at->format('M d, Y') }}
                                </td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:9px;">
                                        <div class="pr-avatar pr-avatar--navy">{{ strtoupper(substr($rName, 0, 1)) }}</div>
                                        <span style="font-weight:600;color:var(--pr-navy);">{{ $rName }}</span>
                                    </div>
                                </td>
                                <td>{{ $rUnit }}</td>
                                <td style="text-align:center;">#{{ $item->renewal_no }}</td>
                                <td style="white-space:nowrap;">₱{{ number_format((float) $item->previous_rent, 2) }}</td>
                                <td style="white-space:nowrap;font-weight:600;">₱{{ number_format((float) $item->new_rent, 2) }}</td>
                                <td style="white-space:nowrap;color:#b45309;font-weight:600;">
                                    +₱{{ number_format((float) $item->increase_amount, 2) }}
                                    <div style="font-size:.72rem;font-weight:500;color:var(--pr-gray-500);">
                                        {{ $item->increasePercent() }}
                                    </div>
                                </td>
                                <td style="color:var(--pr-gray-500);white-space:nowrap;">
                                    {{ $item->new_start_date?->format('M d, Y') ?? '—' }}
                                    &ndash;
                                    {{ $item->new_end_date?->format('M d, Y') ?? '—' }}
                                </td>
                                <td>
                                    <span class="pr-pill {{ $srcPill }}">{{ $item->sourceLabel() }}</span>
                                    @if($item->renewedBy)
                                        <div style="font-size:.72rem;color:var(--pr-gray-500);margin-top:3px;">
                                            by {{ $item->renewedBy->name }}
                                        </div>
                                    @endif
                                    @if($item->source === 'legacy')
                                        <div style="font-size:.7rem;color:var(--pr-gray-500);margin-top:3px;"
                                             title="Reconstructed when renewal history was introduced. Rent figures are derived, not recorded at the time.">
                                            Reconstructed
                                        </div>
                                    @endif
                                </td>
                            </tr>

                        {{-- ── RENOVATION REQUESTS ── --}}
                        @elseif($report === 'renovation-requests')
                            @php
                                $vTenant = $item->tenant;
                                $vName   = $vTenant?->name ?? 'Unknown tenant';
                                $renPill = match($item->status) {
                                    'Approved'  => 'pr-pill--green',
                                    'Denied'    => 'pr-pill--red',
                                    'Pending'   => 'pr-pill--amber',
                                    default     => 'pr-pill--gray',
                                };
                            @endphp
                            <tr>
                                <td style="color:var(--pr-gray-500);white-space:nowrap;">
                                    {{ $item->created_at->format('M d, Y') }}
                                </td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:9px;">
                                        <div class="pr-avatar pr-avatar--navy">{{ strtoupper(substr($vName, 0, 1)) }}</div>
                                        <span style="font-weight:600;color:var(--pr-navy);">{{ $vName }}</span>
                                    </div>
                                </td>
                                <td>
                                    {{ $item->room_no ?? $item->lease?->room_no ?? '—' }}
                                    @if($item->lease?->unit?->type)
                                        <div style="font-size:.75rem;color:var(--pr-gray-500);margin-top:2px;">
                                            {{ $item->lease->unit->type }}
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $item->renovation_type ?? '—' }}</td>
                                <td style="max-width:260px;white-space:normal;">
                                    {{-- Requests filed before the Title field was removed still
                                         carry one; newer ones lead with the description. --}}
                                    @if($item->title)
                                        <div style="font-weight:600;color:var(--pr-navy);">{{ $item->title }}</div>
                                    @endif
                                    <div style="font-size:.78rem;color:var(--pr-gray-500);margin-top:2px;">
                                        {{ $item->description }}
                                    </div>
                                    @if($item->attachment)
                                        <a href="{{ asset('storage/'.$item->attachment) }}" target="_blank"
                                           class="pr-btn pr-btn--outline pr-btn--sm" style="margin-top:6px;">
                                            <i class="bi bi-image"></i> View Photo
                                        </a>
                                    @endif
                                    @if($item->manager_remarks)
                                        <div style="font-size:.75rem;color:var(--pr-gray-500);margin-top:6px;">
                                            <strong>Remarks:</strong> {{ $item->manager_remarks }}
                                        </div>
                                    @endif
                                </td>
                                <td style="white-space:nowrap;">
                                    {{ $item->estimated_cost ? '₱'.number_format($item->estimated_cost, 2) : '—' }}
                                </td>
                                <td style="color:var(--pr-gray-500);white-space:nowrap;">
                                    {{ $item->preferred_date ? $item->preferred_date->format('M d, Y') : '—' }}
                                </td>
                                <td class="text-center">
                                    <span class="pr-pill {{ $renPill }}">{{ $item->status }}</span>
                                </td>
                                <td class="text-center" style="min-width:230px;">
                                    {{-- The approve/deny controls moved here from the
                                         per-tenant modal in manager/tenants.blade.php. The
                                         deposit gate is re-checked server-side in
                                         Manager\RenovationRequestController@decide. --}}
                                    @if($item->status === 'Pending')
                                        <form method="POST" action="{{ route('manager.renovations.decide', $item->id) }}"
                                              class="pr-single-submit">
                                            @csrf
                                            <textarea name="manager_remarks" class="pr-form-control" rows="2"
                                                      placeholder="Remarks (optional)…"
                                                      style="margin-bottom:8px;font-size:.8rem;"></textarea>
                                            <div style="display:flex;gap:6px;justify-content:center;">
                                                <button type="submit" name="decision" value="approve"
                                                        class="pr-btn pr-btn--green pr-btn--sm">
                                                    <i class="bi bi-check-circle-fill"></i> Approve
                                                </button>
                                                <button type="submit" name="decision" value="deny"
                                                        class="pr-btn pr-btn--danger-outline pr-btn--sm">
                                                    <i class="bi bi-x-circle-fill"></i> Deny
                                                </button>
                                            </div>
                                        </form>
                                    @else
                                        <span style="font-size:.78rem;color:var(--pr-gray-500);">
                                            {{ $item->decided_at ? 'Decided '.$item->decided_at->format('M d, Y') : '—' }}
                                        </span>
                                        @if($item->decidedBy)
                                            <div style="font-size:.72rem;color:var(--pr-gray-500);margin-top:2px;">
                                                by {{ $item->decidedBy->name }}
                                            </div>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @elseif($report === 'feedback')
                            @php
                                $fbName = $item->tenant?->name ?? 'Unknown tenant';
                            @endphp
                            <tr>
                                <td style="color:var(--pr-gray-500);white-space:nowrap;">
                                    {{ $item->created_at->format('M d, Y') }}
                                </td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:9px;">
                                        <div class="pr-avatar pr-avatar--navy">{{ strtoupper(substr($fbName, 0, 1)) }}</div>
                                        <span style="font-weight:600;color:var(--pr-navy);">{{ $fbName }}</span>
                                    </div>
                                </td>
                                <td>{{ $item->category }}</td>
                                <td style="white-space:nowrap;">
                                    @if($item->rating)
                                        <span style="color:var(--pr-gold);">
                                            @for($i = 1; $i <= 5; $i++)
                                                <i class="bi {{ $i <= $item->rating ? 'bi-star-fill' : 'bi-star' }}" style="font-size:.72rem;color:{{ $i <= $item->rating ? 'var(--pr-gold)' : '#d1d5db' }};"></i>
                                            @endfor
                                        </span>
                                    @else
                                        <span style="color:var(--pr-gray-500);">—</span>
                                    @endif
                                </td>
                                <td style="max-width:280px;white-space:normal;">
                                    <div style="font-weight:600;color:var(--pr-navy);">{{ $item->subject }}</div>
                                    <div style="font-size:.78rem;color:var(--pr-gray-500);margin-top:2px;">
                                        {{ $item->message }}
                                    </div>
                                    @if($item->manager_response)
                                        <div style="font-size:.75rem;margin-top:6px;color:var(--pr-navy);">
                                            <strong>Response:</strong> {{ $item->manager_response }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="pr-pill {{ $item->isReviewed() ? 'pr-pill--green' : 'pr-pill--amber' }}">
                                        {{ $item->status }}
                                    </span>
                                </td>
                                <td class="text-center" style="min-width:230px;">
                                    @if(!$item->isReviewed())
                                        <form method="POST" action="{{ route('manager.feedback.respond', $item->id) }}"
                                              class="pr-single-submit">
                                            @csrf
                                            <textarea name="manager_response" class="pr-form-control" rows="2"
                                                      placeholder="Write a response…" required
                                                      style="margin-bottom:8px;font-size:.8rem;"></textarea>
                                            <button type="submit" class="pr-btn pr-btn--navy pr-btn--sm">
                                                <i class="bi bi-reply-fill"></i> Send Response
                                            </button>
                                        </form>
                                    @else
                                        <span style="font-size:.78rem;color:var(--pr-gray-500);">
                                            {{ $item->responded_at ? 'Responded '.$item->responded_at->format('M d, Y') : '—' }}
                                        </span>
                                        @if($item->respondedBy)
                                            <div style="font-size:.72rem;color:var(--pr-gray-500);margin-top:2px;">
                                                by {{ $item->respondedBy->name }}
                                            </div>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endif

                    @empty
                        <tr>
                            <td colspan="100%">
                                <div class="pr-empty">
                                    <div class="pr-empty__icon"><i class="bi bi-inbox"></i></div>
                                    <div class="pr-empty__text">No records found for the current filters.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($data->hasPages())
            <div class="pr-pagination">
                @if($data->onFirstPage())
                    <span class="pr-page-btn pr-page-btn--disabled"><i class="bi bi-chevron-left"></i></span>
                @else
                    <a href="{{ $data->previousPageUrl() }}" class="pr-page-btn"><i class="bi bi-chevron-left"></i></a>
                @endif
                @foreach($data->getUrlRange(1, $data->lastPage()) as $page => $url)
                    <a href="{{ $url }}" class="pr-page-btn {{ $page == $data->currentPage() ? 'pr-page-btn--active' : '' }}">{{ $page }}</a>
                @endforeach
                @if($data->hasMorePages())
                    <a href="{{ $data->nextPageUrl() }}" class="pr-page-btn"><i class="bi bi-chevron-right"></i></a>
                @else
                    <span class="pr-page-btn pr-page-btn--disabled"><i class="bi bi-chevron-right"></i></span>
                @endif
            </div>
        @endif
    </div>

@endsection

{{-- ===================== MODALS ===================== --}}
@section('modals')

    {{-- ============================================================ --}}
    {{-- MAKE PAYMENT MODAL --}}
    {{-- ============================================================ --}}
    @if($report === 'payment-history')
    <div class="modal fade pr-modal" id="makePaymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('manager.payments.store') }}" class="modal-content">
                @csrf @method('PATCH')
                <div class="pr-modal__header">
                    <div>
                        <div class="pr-modal__eyebrow">Finance</div>
                        <h5 class="pr-modal__title">Make a Payment</h5>
                    </div>
                    <button type="button" class="pr-modal__close" data-bs-dismiss="modal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
                <div class="pr-modal__body">

                    {{-- Step 1: Tenant --}}
                    <div class="pr-form-group">
                        <label class="pr-form-label">Select Tenant</label>
                        <select name="tenant_id" id="mgr_tenant_id" class="pr-form-control" required>
                            <option value="">Choose a tenant…</option>
                            @foreach($tenantsForPayment as $tenant)
                                <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Step 2: Lease / Room --}}
                    <div class="pr-form-group">
                        <label class="pr-form-label">Room / Unit</label>
                        <select name="lease_id" id="mgr_lease_id" class="pr-form-control" required disabled>
                            <option value="">Select room / unit</option>
                        </select>
                    </div>

                    {{-- Step 3: Payment For --}}
                    <div class="pr-form-group">
                        <label class="pr-form-label">Payment For</label>
                        <select name="payment_for" id="mgr_payment_for" class="pr-form-control" required disabled>
                            <option value="">Select payment type</option>
                            <option id="mgr_depositOption" value="Deposit" class="d-none">Deposit</option>
                            <option id="mgr_rentOption"    value="Rent">Rent (Select unit first)</option>
                            <option id="mgr_advanceOption" value="Advance Rent">Advance Rent (Select unit first)</option>
                            <option id="mgr_utilitiesOption" value="Utilities">Utilities (Select unit first)</option>
                        </select>
                    </div>

                    {{-- Months ahead — only shown for Advance Rent. Mirrors the
                         tenant modal; the months actually granted are derived
                         server-side from the money received. --}}
                    <div class="pr-form-group d-none" id="mgr_advanceMonthsField">
                        <label class="pr-form-label">
                            Months To Pay Ahead <span style="color:#dc2626;">*</span>
                        </label>
                        <select name="advance_months" id="mgr_advance_months" class="pr-form-control" disabled>
                            <option value="">Select…</option>
                        </select>
                        <div class="pr-form-hint" id="mgr_advanceMonthsHint">
                            Outstanding rent and penalties are settled first, then the balance is
                            held against future rent.
                        </div>
                    </div>

                    {{-- Step 4 (conditional): Billing Type — only shown for Utilities --}}
                    <div class="pr-form-group d-none" id="mgr_billingTypeField">
                        <label class="pr-form-label">
                            Utility Type <span style="color:#dc2626;">*</span>
                        </label>
                        <select name="billing_type" id="mgr_billing_type" class="pr-form-control" disabled>
                            <option value="">Select Utility Type…</option>
                            <option value="Electricity">⚡ Electricity</option>
                            <option value="Water">💧 Water</option>
                        </select>
                        <div class="pr-form-hint">Select which utility bill is being paid.</div>
                    </div>

                    {{-- Step 5: Amount --}}
                    <div class="pr-form-group">
                        <label class="pr-form-label">Amount</label>
                        <input type="number" name="pay_amount" id="mgr_pay_amount"
                               class="pr-form-control" required disabled step="0.01" min="0">
                    </div>

                    {{-- Payment Method (Cash only for manager) --}}
                    <div class="pr-form-group" style="margin-bottom:0;">
                        <label class="pr-form-label">Payment Method</label>
                        <select name="pay_method" class="pr-form-control">
                            <option value="Cash" selected>Cash</option>
                        </select>
                    </div>

                </div>
                <div class="pr-modal__footer">
                    <button type="button" class="pr-btn pr-btn--outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="pr-btn pr-btn--navy">
                        <i class="bi bi-cash-coin"></i> Submit Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- ============================================================ --}}
    {{-- VIEW IMAGE MODAL --}}
    {{-- ============================================================ --}}
    <div class="modal fade pr-modal" id="viewImageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="pr-modal__header">
                    <div>
                        <div class="pr-modal__eyebrow">Attachment</div>
                        <h5 class="pr-modal__title" id="viewImageModalLabel">Payment Proof</h5>
                    </div>
                    <button type="button" class="pr-modal__close" data-bs-dismiss="modal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
                <div class="pr-modal__body" style="text-align:center;">
                    <div id="imageLoading" style="padding:32px 0;">
                        <div class="spinner-border" style="color:var(--pr-navy);"></div>
                        <p style="margin-top:12px;font-size:.85rem;color:var(--pr-gray-500);">Loading image…</p>
                    </div>
                    <img id="modalIssueImage" src="" alt="Attachment"
                         class="img-fluid rounded-3"
                         style="max-height:70vh;display:none;box-shadow:0 8px 24px rgba(12,26,58,.15);">
                    <div id="imageError" class="pr-alert pr-alert--error d-none" style="text-align:left;margin-top:16px;">
                        <span class="pr-alert__icon">⚠</span>
                        <span>Image could not be loaded.</span>
                    </div>
                    @if($report === 'payment-history')
                        <div class="d-flex justify-content-center gap-3 mt-4" id="modalActionButtons">
                            <button type="button" class="pr-btn pr-btn--green" id="acceptBtn">
                                <i class="bi bi-check-circle"></i> Accept
                            </button>
                            <button type="button" class="pr-btn pr-btn--danger-outline" id="rejectBtn">
                                <i class="bi bi-x-circle"></i> Reject
                            </button>
                        </div>
                        <form id="updatePaymentForm" method="POST" style="display:none;">
                            @csrf @method('PATCH')
                            <input type="hidden" name="pay_status" id="modalPayStatus">
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
/* ── Payment History date-range filter ──────────────────────
   Picking a shortcut submits immediately; picking "Custom range…"
   just reveals the two date inputs and waits for Apply. Switching
   AWAY from custom clears any leftover date_from/date_to so they
   don't override the shortcut just chosen (DateRangeResolver gives
   an explicit date_from/date_to priority over ?range=). */
function prToggleCustomDateRange(select) {
    const custom = document.getElementById('customDateFields');
    if (select.value === 'custom') {
        custom.style.display = 'inline-flex';
        return;
    }
    const from = custom.querySelector('input[name="date_from"]');
    const to   = custom.querySelector('input[name="date_to"]');
    if (from) from.value = '';
    if (to)   to.value   = '';
    custom.style.display = 'none';
    select.form.submit();
}

/* ── Lease details updater (lease summary dropdown) ── */
function updateLeaseDetails(select, userId) {
    const opt = select.options[select.selectedIndex];
    document.getElementById(`unit_number_${userId}`).innerText = opt.dataset.unitNumber ?? 'N/A';
    document.getElementById(`start_date_${userId}`).innerText  = opt.dataset.start ? new Date(opt.dataset.start).toDateString() : 'N/A';
    document.getElementById(`end_date_${userId}`).innerText    = opt.dataset.end   ? new Date(opt.dataset.end).toDateString()   : 'N/A';
    document.getElementById(`terms_${userId}`).innerText       = opt.dataset.terms ?? 'N/A';
    const s   = opt.dataset.status ?? '';
    const cls = s === 'active' ? 'pr-pill--green' : s === 'terminated' ? 'pr-pill--red' : 'pr-pill--gray';
    document.getElementById(`status_${userId}`).innerHTML =
        `<span class="pr-pill ${cls}">${s.charAt(0).toUpperCase() + s.slice(1)}</span>`;
}

/* ── Make Payment modal ── */
document.addEventListener('DOMContentLoaded', function () {

    const tenantSelect      = document.getElementById('mgr_tenant_id');
    const leaseSelect       = document.getElementById('mgr_lease_id');
    const payForSelect      = document.getElementById('mgr_payment_for');
    const amountInput       = document.getElementById('mgr_pay_amount');
    const depositOption     = document.getElementById('mgr_depositOption');
    const rentOption        = document.getElementById('mgr_rentOption');
    const utilitiesOption   = document.getElementById('mgr_utilitiesOption');
    const billingTypeField  = document.getElementById('mgr_billingTypeField');
    const billingTypeSelect = document.getElementById('mgr_billing_type');
    const advanceOption     = document.getElementById('mgr_advanceOption');
    const advanceField      = document.getElementById('mgr_advanceMonthsField');
    const advanceSelect     = document.getElementById('mgr_advance_months');
    const advanceHint       = document.getElementById('mgr_advanceMonthsHint');

    if (!tenantSelect) return; // not on payment-history report

    // Holds amounts for the currently selected lease
    let selectedLease = null;

    function fmt(n) { return parseFloat(n || 0).toFixed(2); }

    function resetBillingType() {
        billingTypeField.classList.add('d-none');
        billingTypeSelect.disabled = true;
        billingTypeSelect.required = false;
        billingTypeSelect.value    = '';
    }

    function resetAdvance() {
        if (!advanceField) return;
        advanceField.classList.add('d-none');
        advanceSelect.disabled  = true;
        advanceSelect.required  = false;
        advanceSelect.innerHTML = '<option value="">Select…</option>';
        amountInput.readOnly    = false;
    }

    /* Options are capped at advance_months_left, which the API already
       reduced by whatever is on account, so the dropdown can never offer a
       month PaymentRecordController@paymentStore would refuse. */
    function buildAdvanceOptions() {
        advanceSelect.innerHTML = '<option value="">Select…</option>';

        const rent    = selectedLease.monthlyRent;
        const arrears = selectedLease.rent;
        const cap     = selectedLease.advanceMonthsLeft;

        for (let m = 1; m <= cap; m++) {
            const total = arrears + (rent * m);
            const opt   = document.createElement('option');
            opt.value         = m;
            opt.dataset.total = total.toFixed(2);
            opt.textContent   = `${m} month${m > 1 ? 's' : ''} — ₱${fmt(total)}`;
            advanceSelect.appendChild(opt);
        }

        if (advanceHint) {
            const bits = [];
            if (arrears > 0) bits.push(`₱${fmt(arrears)} currently due is settled first.`);
            if (selectedLease.advanceBalance > 0) {
                bits.push(`₱${fmt(selectedLease.advanceBalance)} already on account` +
                          (selectedLease.paidThrough ? ` (covered to ${selectedLease.paidThrough}).` : '.'));
            }
            bits.push(`One month is ₱${fmt(rent)}. Cap: ${cap} more month${cap > 1 ? 's' : ''}.`);
            advanceHint.textContent = bits.join(' ');
        }
    }

    function resetAll() {
        leaseSelect.innerHTML = '<option value="">Select room / unit</option>';
        leaseSelect.disabled  = true;
        payForSelect.value    = '';
        payForSelect.disabled = true;
        amountInput.value     = '';
        amountInput.disabled  = true;
        depositOption.classList.add('d-none');
        rentOption.textContent      = 'Rent (Select unit first)';
        utilitiesOption.textContent = 'Utilities (Select unit first)';
        if (advanceOption) advanceOption.style.display = 'none';
        resetBillingType();
        resetAdvance();
        selectedLease = null;
    }

    resetAll();

    /* ── Step 1: Tenant selected → fetch leases via API ── */
    tenantSelect.addEventListener('change', function () {
        resetAll();
        const id = this.value;
        if (!id) return;

        leaseSelect.innerHTML = '<option value="">Loading…</option>';

        fetch(`/manager/tenant/${id}/leases`)
            .then(r => r.json())
            .then(leases => {
                leaseSelect.innerHTML = '<option value="">Select room / unit</option>';
                leases.forEach(l => {
                    const label = `${l.room_no} – ${l.unit_type}${l.bed_number ? ` (Bed ${l.bed_number})` : ''}`;
                    const opt   = document.createElement('option');
                    opt.value   = l.id;
                    opt.textContent = label;
                    // Store all amounts as data attributes
                    opt.dataset.deposit             = l.deposit             ?? 0;
                    opt.dataset.rent                = l.room_price          ?? 0;
                    opt.dataset.electricityAmount   = l.electricity_amount  ?? 0;
                    opt.dataset.waterAmount         = l.water_amount        ?? 0;
                    opt.dataset.monthlyRent         = l.monthly_rent        ?? 0;
                    opt.dataset.advanceBalance      = l.advance_balance     ?? 0;
                    opt.dataset.advanceMonthsLeft   = l.advance_months_left ?? 0;
                    opt.dataset.paidThrough         = l.rent_paid_through   ?? '';
                    leaseSelect.appendChild(opt);
                });
                leaseSelect.disabled = false;
            });
    });

    /* ── Step 2: Lease selected → populate payment type labels ── */
    leaseSelect.addEventListener('change', function () {
        payForSelect.value    = '';
        payForSelect.disabled = true;
        amountInput.value     = '';
        amountInput.disabled  = true;
        resetBillingType();

        if (!this.value) { selectedLease = null; return; }

        const opt = this.options[this.selectedIndex];
        selectedLease = {
            deposit:     parseFloat(opt.dataset.deposit             || 0),
            rent:        parseFloat(opt.dataset.rent                || 0),
            electricity: parseFloat(opt.dataset.electricityAmount   || 0),
            water:       parseFloat(opt.dataset.waterAmount         || 0),
            monthlyRent:       parseFloat(opt.dataset.monthlyRent       || 0),
            advanceBalance:    parseFloat(opt.dataset.advanceBalance    || 0),
            advanceMonthsLeft: parseInt(opt.dataset.advanceMonthsLeft   || 0, 10),
            paidThrough:       opt.dataset.paidThrough || '',
        };

        // Advance rent — hidden while a deposit is outstanding, since the
        // deposit gate rejects everything else anyway.
        if (advanceOption) {
            const canAdvance = selectedLease.deposit <= 0
                            && selectedLease.monthlyRent > 0
                            && selectedLease.advanceMonthsLeft > 0;

            if (canAdvance) {
                advanceOption.textContent   = `Advance Rent — ₱${fmt(selectedLease.monthlyRent)} / month`;
                advanceOption.style.display = '';
            } else {
                advanceOption.style.display = 'none';
            }
        }

        // Deposit option
        if (selectedLease.deposit > 0) {
            depositOption.textContent = `Deposit — ₱${fmt(selectedLease.deposit)}`;
            depositOption.classList.remove('d-none');
        } else {
            depositOption.classList.add('d-none');
        }

        // Rent option
        if (selectedLease.rent > 0) {
            rentOption.textContent    = `Rent — ₱${fmt(selectedLease.rent)}`;
            rentOption.style.display  = '';
        } else {
            rentOption.style.display  = 'none';
        }

        // Utilities option — show only if at least one type has a balance
        const hasUtility = selectedLease.electricity > 0 || selectedLease.water > 0;
        if (hasUtility) {
            utilitiesOption.textContent  = 'Utilities';
            utilitiesOption.style.display = '';
        } else {
            utilitiesOption.style.display = 'none';
        }

        payForSelect.disabled = false;
    });

    /* ── Step 3: Payment type selected ── */
    payForSelect.addEventListener('change', function () {
        resetBillingType();
        resetAdvance();
        amountInput.value    = '';
        amountInput.disabled = true;

        if (!this.value || !selectedLease) return;

        if (this.value === 'Advance Rent') {
            advanceField.classList.remove('d-none');
            advanceSelect.disabled = false;
            advanceSelect.required = true;
            buildAdvanceOptions();
            return;   // amount fills once months are picked
        }

        if (this.value === 'Utilities') {
            // Show billing type field — amount fills after step 4
            billingTypeField.classList.remove('d-none');
            billingTypeSelect.disabled = false;
            billingTypeSelect.required = true;
            billingTypeSelect.value    = '';

            // Update billing type option labels with real amounts
            const elecOpt  = billingTypeSelect.querySelector('option[value="Electricity"]');
            const waterOpt = billingTypeSelect.querySelector('option[value="Water"]');

            if (elecOpt) {
                elecOpt.textContent   = selectedLease.electricity > 0
                    ? `⚡ Electricity — ₱${fmt(selectedLease.electricity)}`
                    : '⚡ Electricity (already paid)';
                elecOpt.disabled = selectedLease.electricity <= 0;
            }
            if (waterOpt) {
                waterOpt.textContent  = selectedLease.water > 0
                    ? `💧 Water — ₱${fmt(selectedLease.water)}`
                    : '💧 Water (already paid)';
                waterOpt.disabled = selectedLease.water <= 0;
            }
        } else {
            // Non-utility: set amount directly
            const amountMap = {
                Deposit: selectedLease.deposit,
                Rent:    selectedLease.rent,
            };
            amountInput.value    = fmt(amountMap[this.value] || 0);
            amountInput.disabled = false;
        }
    });

    /* ── Advance rent: months selected → fill amount ── */
    if (advanceSelect) {
        advanceSelect.addEventListener('change', function () {
            if (!this.value || !selectedLease) {
                amountInput.value    = '';
                amountInput.disabled = true;
                amountInput.readOnly = false;
                return;
            }
            const opt = this.options[this.selectedIndex];
            amountInput.value    = opt.dataset.total;
            amountInput.disabled = false;
            // readOnly, not disabled — a disabled input is never posted.
            amountInput.readOnly = true;
        });
    }

    /* ── Step 4: Billing type selected → fill amount ── */
    billingTypeSelect.addEventListener('change', function () {
        if (!this.value || !selectedLease) return;
        const amountMap = {
            Electricity: selectedLease.electricity,
            Water:       selectedLease.water,
        };
        amountInput.value    = fmt(amountMap[this.value] || 0);
        amountInput.disabled = false;
    });
});

/* ── Image modal ── */
document.addEventListener('DOMContentLoaded', function () {
    const imageModal  = document.getElementById('viewImageModal');
    const modalImg    = document.getElementById('modalIssueImage');
    const modalTitle  = document.getElementById('viewImageModalLabel');
    const imgLoading  = document.getElementById('imageLoading');
    const imgError    = document.getElementById('imageError');
    const acceptBtn   = document.getElementById('acceptBtn');
    const rejectBtn   = document.getElementById('rejectBtn');
    const updateForm  = document.getElementById('updatePaymentForm');
    const statusInput = document.getElementById('modalPayStatus');
    let   currentId   = null;

    if (!imageModal) return;

    imageModal.addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        if (!btn) return;
        const url  = btn.dataset.image;
        const stat = btn.dataset.status;
        currentId  = btn.dataset.paymentId;

        if (modalTitle) modalTitle.textContent = btn.dataset.title || 'Attachment';
        modalImg.style.display = 'none'; modalImg.src = '';
        imgLoading.style.display = 'block';
        imgError.classList.add('d-none');

        if (acceptBtn) {
            acceptBtn.disabled = stat === 'Accepted';
            rejectBtn.disabled = stat === 'Accepted';
        }

        if (!url) { imgLoading.style.display = 'none'; imgError.classList.remove('d-none'); return; }
        modalImg.onload  = () => { imgLoading.style.display = 'none'; modalImg.style.display = 'block'; };
        modalImg.onerror = () => { imgLoading.style.display = 'none'; imgError.classList.remove('d-none'); };
        modalImg.src = url;
    });

    imageModal.addEventListener('hidden.bs.modal', function () {
        modalImg.src = ''; modalImg.style.display = 'none';
        if (modalTitle) modalTitle.textContent = 'Attachment';
        imgLoading.style.display = 'none';
        imgError.classList.add('d-none');
    });

    function submitStatus(status) {
        if (!currentId || !updateForm) return;
        statusInput.value  = status;
        updateForm.action  = `/manager/payments/${currentId}/status`;
        updateForm.submit();
    }
    if (acceptBtn) acceptBtn.addEventListener('click', () => submitStatus('Accepted'));
    if (rejectBtn) rejectBtn.addEventListener('click', () => submitStatus('Rejected'));
});
</script>
@endpush