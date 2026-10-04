@extends('layouts.managerdashboardlayout')

@section('title', 'Pinikitan Rental | Manage Units')
@section('page-title', 'Unit Management')

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

    /* ===================== FILTER BAR ===================== */
    .pr-filter {
        background: var(--pr-white);
        border: 1px solid rgba(12,26,58,.07);
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(12,26,58,.06);
        padding: 16px 22px;
        margin-bottom: 20px;
    }
    .pr-filter__row {
        display: flex;
        align-items: flex-end;
        gap: 12px;
        flex-wrap: wrap;
    }
    /* Pushes the action buttons to the far right */
    .pr-filter__actions {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-left: auto;
        flex-shrink: 0;
    }
    .pr-filter__group { display: flex; flex-direction: column; gap: 5px; }
    .pr-filter__group { flex:1; min-width:160px; }
    .pr-filter__label { display:block; font-size:.78rem; font-weight:600; color:var(--pr-navy); margin-bottom:6px; }
    .pr-select-filter {
        padding: 9px 13px; border: 1.5px solid var(--pr-gray-200); border-radius: 10px;
        font-family: var(--pr-font-body); font-size: .85rem; color: var(--pr-gray-800);
        background: var(--pr-white); outline: none; cursor: pointer; transition: border-color .2s;
        height: 40px;
    }
    .pr-select-filter:focus { border-color: var(--pr-navy); box-shadow: 0 0 0 3px rgba(12,26,58,.07); }
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
    .pr-btn--gold { background:linear-gradient(135deg,var(--pr-gold),var(--pr-gold-lt)); color:var(--pr-navy); }
    .pr-btn--gold:hover { color:var(--pr-navy); transform:translateY(-2px); box-shadow:0 6px 18px rgba(201,168,76,.3); }
    .pr-btn--outline { background:transparent; border:1.5px solid rgba(12,26,58,.15); color:var(--pr-navy); }
    .pr-btn--outline:hover { background:rgba(12,26,58,.05); color:var(--pr-navy); }
    .pr-btn--danger-outline { background:transparent; border:1.5px solid rgba(239,68,68,.3); color:#dc2626; }
    .pr-btn--danger-outline:hover { background:rgba(239,68,68,.06); color:#dc2626; }
    .pr-btn--green { background:#16a34a; color:#fff; }
    .pr-btn--green:hover { background:#15803d; color:#fff; transform:translateY(-1px); }
    .pr-btn--red { background:#dc2626; color:#fff; }
    .pr-btn--red:hover { background:#b91c1c; color:#fff; transform:translateY(-1px); }
    .pr-btn--sm { padding:7px 12px; font-size:.78rem; }
    .pr-btn:disabled, .pr-btn[disabled] { opacity:.38; cursor:not-allowed; pointer-events:none; }

    /* Badge on button */
    .pr-btn-badge {
        display:inline-flex; align-items:center; justify-content:center;
        min-width:18px; height:18px; padding:0 5px;
        border-radius:999px; background:#ef4444; color:#fff;
        font-size:.65rem; font-weight:700; line-height:1;
    }

    /* ===================== TABLE CARD ===================== */
    .pr-table-card {
        background:var(--pr-white); border:1px solid rgba(12,26,58,.07);
        border-radius:16px; box-shadow:0 2px 12px rgba(12,26,58,.06); overflow:hidden;
    }
    .pr-table-card__header {
        display:flex; align-items:center; justify-content:space-between;
        gap:12px; padding:16px 22px; border-bottom:1px solid rgba(12,26,58,.06);
    }
    .pr-table-card__title { font-family:var(--pr-font-display); font-size:.98rem; font-weight:700; color:var(--pr-navy); margin:0; }
    .pr-table-card__count { font-size:.72rem; font-weight:600; padding:3px 10px; border-radius:999px; background:rgba(201,168,76,.1); color:var(--pr-gold); border:1px solid rgba(201,168,76,.2); }

    /* ===================== TABLE ===================== */
    .pr-table-wrap { overflow-x:auto; }
    .pr-table { width:100%; border-collapse:collapse; font-size:.85rem; }
    .pr-table thead th {
        font-size:.68rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase;
        color:var(--pr-gray-500); padding:12px 16px; border-bottom:1px solid rgba(12,26,58,.07);
        background:rgba(12,26,58,.02); white-space:nowrap; text-align:left;
    }
    .pr-table thead th.text-center { text-align:center; }
    .pr-table tbody td { padding:13px 16px; color:var(--pr-gray-800); border-bottom:1px solid rgba(12,26,58,.04); vertical-align:middle; }
    .pr-table tbody td.text-center { text-align:center; }
    .pr-table tbody tr:last-child td { border-bottom:0; }
    .pr-table tbody tr:hover td { background:rgba(12,26,58,.015); }

    /* ===================== STATUS PILLS ===================== */
    .pr-pill {
        display:inline-flex; align-items:center; gap:5px;
        padding:3px 10px; border-radius:999px; font-size:.71rem; font-weight:600;
    }
    .pr-pill::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; }
    .pr-pill--green { background:#f0fdf4; color:#16a34a; }
    .pr-pill--red   { background:#fef2f2; color:#dc2626; }
    .pr-pill--amber { background:#fffbeb; color:#d97706; }
    .pr-pill--gray  { background:#f3f4f6; color:#6b7280; }
    .pr-pill--blue  { background:#eff6ff; color:#2563eb; }

    /* ===================== ICON BUTTONS ===================== */
    .pr-icon-btn {
        width:32px; height:32px; border-radius:8px;
        display:inline-flex; align-items:center; justify-content:center;
        font-size:.9rem; cursor:pointer; transition:all .2s;
        border:1px solid transparent;
    }
    .pr-icon-btn--edit  { background:rgba(245,158,11,.1); color:#d97706; border-color:rgba(245,158,11,.2); }
    .pr-icon-btn--edit:hover  { background:#d97706; color:#fff; border-color:#d97706; }
    .pr-icon-btn--delete { background:rgba(239,68,68,.08); color:#dc2626; border-color:rgba(239,68,68,.2); }
    .pr-icon-btn--delete:hover { background:#dc2626; color:#fff; border-color:#dc2626; }
    .pr-icon-btn:disabled, .pr-icon-btn[disabled] { opacity:.35; cursor:not-allowed; pointer-events:none; }

    /* ===================== PRICE ===================== */
    .pr-price { font-weight:700; color:#16a34a; }

    /* ===================== ROOM BADGE ===================== */
    .pr-room-no {
        display:inline-flex; align-items:center; gap:4px;
        padding:2px 9px; border-radius:999px; font-size:.75rem; font-weight:700;
        background:rgba(12,26,58,.07); color:var(--pr-navy);
        border:1px solid rgba(12,26,58,.1);
    }

    /* ===================== EMPTY STATE ===================== */
    .pr-empty { text-align:center; padding:48px 24px; color:var(--pr-gray-500); }
    .pr-empty__icon { font-size:2.5rem; margin-bottom:10px; opacity:.3; }
    .pr-empty__text { font-size:.88rem; }

    /* ===================== OCCUPANCY ===================== */
    .pr-occupancy { font-size:.82rem; display:flex; align-items:center; gap:8px; }
    .pr-occupancy__bar {
        width:56px; height:5px; background:var(--pr-gray-100);
        border-radius:999px; overflow:hidden; display:inline-block; flex-shrink:0;
    }
    .pr-occupancy__fill { height:100%; border-radius:999px; background:var(--pr-navy); }

    /* ===================== MODAL ===================== */
    .pr-modal-content {
        border:0 !important; border-radius:20px !important;
        box-shadow:0 32px 80px rgba(12,26,58,.28) !important; overflow:hidden;
    }
    .pr-modal__header {
        display:flex; align-items:center; justify-content:space-between;
        padding:20px 26px;
        background:linear-gradient(135deg,var(--pr-navy),var(--pr-navy-mid));
        border-bottom:1px solid rgba(201,168,76,.15);
    }
    .pr-modal__header--danger { background:linear-gradient(135deg,#dc2626,#ef4444); }
    .pr-modal__eyebrow { font-size:.68rem; font-weight:600; letter-spacing:.14em; text-transform:uppercase; color:rgba(255,255,255,.65); margin-bottom:3px; }
    .pr-modal__title { font-family:var(--pr-font-display); font-size:1.1rem; font-weight:700; color:var(--pr-white); margin:0; }
    .pr-modal__close { width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,.12); border:none; display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,.8); cursor:pointer; transition:all .2s; }
    .pr-modal__close:hover { background:rgba(255,255,255,.22); color:#fff; }
    .pr-modal__body { padding:24px 26px; }
    .pr-modal__footer { display:flex; justify-content:flex-end; gap:10px; padding:16px 26px; border-top:1px solid rgba(12,26,58,.07); background:rgba(12,26,58,.02); }

    /* Form fields */
    .pr-form-group { margin-bottom:16px; }
    .pr-form-group:last-child { margin-bottom:0; }
    .pr-form-label { display:block; font-size:.78rem; font-weight:600; color:var(--pr-navy); margin-bottom:6px; }
    .pr-form-label .pr-required { color:#dc2626; }
    .pr-form-control {
        width:100%; padding:10px 14px; border:1.5px solid var(--pr-gray-200); border-radius:10px;
        font-family:var(--pr-font-body); font-size:.88rem; color:var(--pr-gray-800);
        background:var(--pr-white); outline:none; transition:border-color .2s; box-sizing:border-box;
    }
    .pr-form-control:focus { border-color:var(--pr-navy); box-shadow:0 0 0 3px rgba(12,26,58,.07); }
    .pr-form-control:disabled, .pr-form-control[readonly] { background:var(--pr-gray-100); opacity:.7; }
    .pr-form-hint { font-size:.72rem; color:var(--pr-gray-500); margin-top:4px; }

    .pr-form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

    /* Section label inside modals */
    .pr-section-label { font-size:.72rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--pr-gold); margin-bottom:12px; display:flex; align-items:center; gap:8px; }
    .pr-section-label::after { content:''; flex:1; height:1px; background:linear-gradient(to right,rgba(201,168,76,.3),transparent); }

    .pr-notice { display:flex; align-items:flex-start; gap:10px; padding:12px 14px; border-radius:10px; font-size:.85rem; margin-bottom:16px; }
    .pr-notice--danger { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
    .pr-notice--info   { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }

    /* Lease applications table in modal */
    .pr-lease-table { width:100%; border-collapse:collapse; font-size:.84rem; }
    .pr-lease-table thead th {
        font-size:.67rem; font-weight:700; letter-spacing:.09em; text-transform:uppercase;
        color:var(--pr-gray-500); padding:10px 14px; border-bottom:1px solid rgba(12,26,58,.07);
        background:rgba(12,26,58,.02); white-space:nowrap; text-align:left;
    }
    .pr-lease-table thead th.text-center { text-align:center; }
    .pr-lease-table tbody td { padding:12px 14px; color:var(--pr-gray-800); border-bottom:1px solid rgba(12,26,58,.04); vertical-align:middle; }
    .pr-lease-table tbody tr:last-child td { border-bottom:0; }
    .pr-lease-table tbody tr:hover td { background:rgba(12,26,58,.015); }

    /* ===================== RESPONSIVE ===================== */
    @media (max-width: 768px) {
        .pr-filter { padding: 14px 16px; }
        .pr-filter__row { flex-direction: column; align-items: stretch; gap: 10px; }
        .pr-filter__group--status { min-width: unset; }
        .pr-filter__actions {
            margin-left: 0;
            flex-wrap: wrap;
            width: 100%;
            border-top: 1px solid rgba(12,26,58,.07);
            padding-top: 12px;
            margin-top: 4px;
        }
        .pr-filter__actions .pr-btn { flex: 1; justify-content: center; }
        .pr-search-input { width: 100%; }
        .pr-search-wrap { width: 100%; }
        .pr-form-row { grid-template-columns: 1fr; }
        .pr-btn { font-size: .8rem; padding: 9px 14px; }
    }
</style>
@endpush

@section('content')

    {{-- Flash Alerts --}}
    @if(session('success'))
        <x-alert type="success">{{ session('success') }}</x-alert>
    @endif

    @if($errors->any())
        <x-alert type="error">{{ $errors->first() }}</x-alert>
    @endif

    {{-- Page header --}}
    <div class="pr-dash-header">
        <div class="pr-dash-header__eyebrow">Manager Portal</div>
        <h2 class="pr-dash-header__title">Unit Management</h2>
        <p class="pr-dash-header__sub">Add, edit, and manage all rental units and lease applications.</p>
    </div>

    {{-- Filter Bar --}}
    <div class="pr-filter">
        <form method="GET" action="{{ route('manager.units.index') }}">
            <div class="pr-filter__row">

                {{-- Type Filter --}}
                <div class="pr-filter__group pr-filter__group--status">
                    <label class="pr-filter__label">
                        <i class="bi bi-funnel-fill" style="color:var(--pr-gold);"></i> Unit Type
                    </label>
                    <select name="filter" class="pr-select-filter" onchange="this.form.submit()">
                        <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>All Types</option>
                        @foreach(['Studio','1-Bedroom','2-Bedroom','Commercial','Bed-Spacer'] as $type)
                            <option value="{{ $type }}" {{ $filter === $type ? 'selected' : '' }}>
                                {{ $type }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Search --}}
                <div class="pr-filter__group">
                    <label class="pr-filter__label">
                        <i class="bi bi-search" style="color:var(--pr-gold);"></i> Search Unit
                    </label>
                    <div class="pr-search-wrap">
                        <span class="pr-search-wrap__icon"><i class="bi bi-building"></i></span>
                        <input type="text" name="search" class="pr-search-input"
                            placeholder="Seach for room no." value="{{ $search }}">
                        <button type="submit" class="pr-search-btn">
                            <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <div class="pr-filter__actions">
                    <button type="button" class="pr-btn pr-btn--navy"
                            data-bs-toggle="modal" data-bs-target="#createUnitModal">
                        <i class="bi bi-plus-circle"></i> Add New Unit
                    </button>

                    <button type="button" class="pr-btn pr-btn--gold"
                            data-bs-toggle="modal" data-bs-target="#leaseApplicationsModal">
                        <i class="bi bi-file-earmark-text"></i> Lease Applications
                        @if($pendingLeases->count() > 0)
                            <span class="pr-btn-badge">{{ $pendingLeases->count() }}</span>
                        @endif
                    </button>
                </div>

            </div>
        </form>
    </div>

    {{-- Units table card --}}
    <div class="pr-table-card">
        <div class="pr-table-card__header">
            <h3 class="pr-table-card__title">All Units</h3>
            <span class="pr-table-card__count">{{ $units->count() }} unit{{ $units->count() !== 1 ? 's' : '' }}</span>
        </div>

        <div class="pr-table-wrap">
            <table class="pr-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Room No.</th>
                        <th class="text-center">Room Price</th>
                        <th class="text-center">Occupancy</th>
                        <th class="text-center">Applications</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($units as $unit)
                        @php
                            $occPct = $unit->capacity > 0
                                ? round(($unit->no_of_occupants / $unit->capacity) * 100)
                                : 0;

                            $pendingLeaseCount = $unit->leases
                                ->where('lea_status', 'pending')
                                ->count();

                            $pendingApplicationCount = $unit->tenantApplications
                                ->filter(fn($app) => optional($app->user)->status === 'pending')
                                ->count();

                            $pendingCount = $pendingLeaseCount + $pendingApplicationCount;

                            $isLocked = in_array($unit->status, ['occupied', 'maintenance']);
                        @endphp
                        <tr>
                            <td style="color:var(--pr-gray-500);font-size:.8rem;">#{{ $unit->id }}</td>
                            <td style="font-weight:600;color:var(--pr-navy);">{{ $unit->type }}</td>
                            <td>
                                <span class="pr-room-no">
                                    <i class="bi bi-door-open" style="font-size:.7rem;"></i>
                                    {{ $unit->room_no }}
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="pr-price">₱{{ number_format($unit->room_price, 2) }}</span>
                            </td>
                            <td class="text-center">
                                <div class="pr-occupancy" style="justify-content:center;">
                                    <span>{{ $unit->no_of_occupants }}/{{ $unit->capacity }}</span>
                                    <div class="pr-occupancy__bar">
                                        <div class="pr-occupancy__fill" style="width:{{ $occPct }}%;"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="pr-pill {{ $pendingCount > 0 ? 'pr-pill--amber' : 'pr-pill--gray' }}">
                                    {{ $pendingCount }}/{{ $unit->application_limit }}
                                </span>
                            </td>
                            <td class="text-center">
                                @php
                                    $statusPill = match($unit->status) {
                                        'vacant'      => 'pr-pill--green',
                                        'occupied'    => 'pr-pill--red',
                                        'maintenance' => 'pr-pill--amber',
                                        default       => 'pr-pill--gray',
                                    };
                                @endphp
                                <span class="pr-pill {{ $statusPill }}">
                                    {{ ucfirst($unit->status) }}
                                </span>
                            </td>
                            <td class="text-center">
                                <div style="display:flex;align-items:center;justify-content:center;gap:6px;">
                                    <button type="button"
                                            class="pr-icon-btn pr-icon-btn--edit"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editUnitModal{{ $unit->id }}"
                                            title="Edit Unit"
                                            @if($isLocked) disabled @endif>
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button type="button"
                                            class="pr-icon-btn pr-icon-btn--delete"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteUnitModal{{ $unit->id }}"
                                            title="Delete Unit"
                                            @if($isLocked) disabled @endif>
                                        <i class="bi bi-trash3-fill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="pr-empty">
                                    <div class="pr-empty__icon"><i class="bi bi-building"></i></div>
                                    <div class="pr-empty__text">
                                        @if($search || $filter !== 'all')
                                            No units match your filters.
                                            <a href="{{ route('manager.units.index') }}">Clear filters</a>
                                        @else
                                            No units available. Add your first unit.
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection

@section('modals')

{{-- ============================================================ --}}
{{-- CREATE UNIT MODAL --}}
{{-- ============================================================ --}}
<div class="modal fade" id="createUnitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content pr-modal-content">
            <form method="POST" action="{{ route('manager.units.store') }}">
                @csrf
                <div class="pr-modal__header">
                    <div>
                        <div class="pr-modal__eyebrow">Unit Management</div>
                        <h5 class="pr-modal__title">
                            <i class="bi bi-plus-circle me-2"></i>Add New Unit
                        </h5>
                    </div>
                    <button type="button" class="pr-modal__close" data-bs-dismiss="modal" aria-label="Close">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
                <div class="pr-modal__body">
                    <div class="pr-section-label">Unit Details</div>

                    <div class="pr-form-row">
                        <div class="pr-form-group">
                            <label class="pr-form-label">Type <span class="pr-required">*</span></label>
                            <select name="type" class="pr-form-control" required>
                                <option value="">Select Type…</option>
                                @foreach(['Studio','1-Bedroom','2-Bedroom','Commercial','Bed-Spacer'] as $type)
                                    <option value="{{ $type }}"
                                        {{ old('type') === $type ? 'selected' : '' }}>
                                        {{ $type }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="pr-form-group">
                            <label class="pr-form-label">Room No. <span class="pr-required">*</span></label>
                            <input type="text" name="room_no" class="pr-form-control"
                                   placeholder="e.g. 101" value="{{ old('room_no') }}" required>
                        </div>
                    </div>

                    <div class="pr-form-row">
                        <div class="pr-form-group">
                            <label class="pr-form-label">Room Price <span class="pr-required">*</span></label>
                            <input type="number" step="0.01" name="room_price" class="pr-form-control"
                                   placeholder="0.00" min="0" value="{{ old('room_price') }}" required>
                        </div>
                        <div class="pr-form-group">
                            <label class="pr-form-label">Capacity <span class="pr-required">*</span></label>
                            <input type="number" name="capacity" class="pr-form-control"
                                   min="1" placeholder="1" value="{{ old('capacity') }}" required>
                        </div>
                    </div>

                    <div class="pr-form-row">
                        <div class="pr-form-group">
                            <label class="pr-form-label">Application Limit <span class="pr-required">*</span></label>
                            <input type="number" name="application_limit" class="pr-form-control"
                                   min="1" placeholder="1" value="{{ old('application_limit') }}" required>
                            <div class="pr-form-hint">Max simultaneous pending applications allowed.</div>
                        </div>
                        <div class="pr-form-group">
                            <label class="pr-form-label">Status</label>
                            <select name="status" class="pr-form-control">
                                <option value="vacant"   {{ old('status', 'vacant') === 'vacant'   ? 'selected' : '' }}>Vacant</option>
                                <option value="occupied" {{ old('status') === 'occupied' ? 'selected' : '' }}>Occupied</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="pr-modal__footer">
                    <button type="button" class="pr-btn pr-btn--outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="pr-btn pr-btn--navy">
                        <i class="bi bi-plus-circle"></i> Save Unit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- LEASE APPLICATIONS MODAL --}}
{{-- ============================================================ --}}
<div class="modal fade" id="leaseApplicationsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content pr-modal-content">
            <div class="pr-modal__header">
                <div>
                    <div class="pr-modal__eyebrow">Pending Reviews</div>
                    <h5 class="pr-modal__title">
                        <i class="bi bi-file-earmark-text me-2"></i>Lease Applications
                        @if($pendingLeases->count() > 0)
                            <span class="pr-btn-badge ms-1">{{ $pendingLeases->count() }}</span>
                        @endif
                    </h5>
                </div>
                <button type="button" class="pr-modal__close" data-bs-dismiss="modal" aria-label="Close">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            <div class="pr-modal__body" style="padding:0;">
                @if($pendingLeases->isEmpty())
                    <div class="pr-empty" style="padding:56px 24px;">
                        <div class="pr-empty__icon"><i class="bi bi-inbox"></i></div>
                        <div class="pr-empty__text">No pending lease applications at the moment.</div>
                    </div>
                @else
                    <div style="overflow-x:auto;">
                        <table class="pr-lease-table">
                            <thead>
                                <tr>
                                    <th>Tenant</th>
                                    <th>Unit #</th>
                                    <th>Type</th>
                                    <th>Room No.</th>
                                    <th>Price</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pendingLeases as $lease)
                                    <tr>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:9px;">
                                                <div style="width:28px;height:28px;border-radius:50%;
                                                     background:linear-gradient(135deg,var(--pr-navy),var(--pr-navy-mid));
                                                     display:flex;align-items:center;justify-content:center;
                                                     color:var(--pr-gold-lt);font-weight:700;font-size:.7rem;flex-shrink:0;">
                                                    {{ strtoupper(substr(optional($lease->tenant)->name ?? '?', 0, 1)) }}
                                                </div>
                                                <span style="font-weight:600;color:var(--pr-navy);">
                                                    {{ optional($lease->tenant)->name ?? 'Unknown Tenant' }}
                                                </span>
                                            </div>
                                        </td>
                                        <td style="color:var(--pr-gray-500);">{{ $lease->unit_id }}</td>
                                        <td style="font-weight:600;">{{ optional($lease->unit)->type }}</td>
                                        <td>
                                            <span class="pr-room-no">
                                                <i class="bi bi-door-open" style="font-size:.7rem;"></i>
                                                {{ optional($lease->unit)->room_no }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="pr-price">
                                                ₱{{ number_format(optional($lease->unit)->room_price ?? 0, 2) }}
                                            </span>
                                        </td>
                                        <td style="color:var(--pr-gray-500);">
                                            {{ $lease->lea_start_date
                                                ? \Carbon\Carbon::parse($lease->lea_start_date)->format('M d, Y')
                                                : 'TBD' }}
                                        </td>
                                        <td style="color:var(--pr-gray-500);">
                                            {{ $lease->lea_end_date
                                                ? \Carbon\Carbon::parse($lease->lea_end_date)->format('M d, Y')
                                                : 'TBD' }}
                                        </td>
                                        <td>
                                            <div style="display:flex;align-items:center;justify-content:center;gap:8px;">
                                                @if(optional($lease->unit)->status === 'occupied')
                                                    <span class="pr-pill pr-pill--gray" style="font-size:.7rem;">
                                                        <i class="bi bi-lock-fill" style="font-size:.6rem;"></i> Occupied
                                                    </span>
                                                @else
                                                    <form method="POST"
                                                          action="{{ route('manager.approve-unit', [
                                                              'user'  => $lease->user_id,
                                                              'unit'  => $lease->unit_id,
                                                              'lease' => $lease->id,
                                                          ]) }}">
                                                        @csrf
                                                        <button type="submit"
                                                                class="pr-btn pr-btn--green pr-btn--sm"
                                                                onclick="return confirm('Approve this lease application?')">
                                                            <i class="bi bi-check-circle"></i> Approve
                                                        </button>
                                                    </form>
                                                @endif

                                                <form method="POST"
                                                      action="{{ route('manager.reject-unit', [
                                                          optional($lease->tenant)->id,
                                                          optional($lease->unit)->id,
                                                          $lease->id,
                                                      ]) }}">
                                                    @csrf
                                                    <button type="submit"
                                                            class="pr-btn pr-btn--danger-outline pr-btn--sm"
                                                            onclick="return confirm('Reject this lease application?')">
                                                        <i class="bi bi-x-circle"></i> Reject
                                                    </button>
                                                </form>
                                            </div>
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

{{-- ============================================================ --}}
{{-- EDIT & DELETE MODALS (one pair per unit) --}}
{{-- ============================================================ --}}
@foreach($units as $unit)

    {{-- ---- Edit Modal ---- --}}
    <div class="modal fade" id="editUnitModal{{ $unit->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content pr-modal-content">
                <form method="POST" action="{{ route('manager.units.update', $unit->id) }}">
                    @csrf
                    @method('PUT')
                    <div class="pr-modal__header">
                        <div>
                            <div class="pr-modal__eyebrow">Unit Management</div>
                            <h5 class="pr-modal__title">
                                <i class="bi bi-pencil-square me-2"></i>Edit Unit
                            </h5>
                        </div>
                        <button type="button" class="pr-modal__close" data-bs-dismiss="modal" aria-label="Close">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                    <div class="pr-modal__body">
                        <div class="pr-section-label">Unit Details</div>

                        <div class="pr-form-row">
                            <div class="pr-form-group">
                                <label class="pr-form-label">Type</label>
                                <input type="text" class="pr-form-control" value="{{ $unit->type }}" readonly>
                                {{-- Hidden so the value is still submitted --}}
                                <input type="hidden" name="type" value="{{ $unit->type }}">
                                <div class="pr-form-hint">Unit type cannot be changed after creation.</div>
                            </div>
                            <div class="pr-form-group">
                                <label class="pr-form-label">Room No. <span class="pr-required">*</span></label>
                                <input type="text" name="room_no" class="pr-form-control"
                                       value="{{ $unit->room_no }}" required>
                            </div>
                        </div>

                        <div class="pr-form-row">
                            <div class="pr-form-group">
                                <label class="pr-form-label">Room Price <span class="pr-required">*</span></label>
                                <input type="number" step="0.01" min="0" name="room_price"
                                       class="pr-form-control" value="{{ $unit->room_price }}" required>
                            </div>
                            <div class="pr-form-group">
                                <label class="pr-form-label">Capacity <span class="pr-required">*</span></label>
                                <input type="number" name="capacity" class="pr-form-control"
                                       min="1" value="{{ $unit->capacity }}" required>
                            </div>
                        </div>

                        <div class="pr-form-row">
                            <div class="pr-form-group">
                                <label class="pr-form-label">Application Limit <span class="pr-required">*</span></label>
                                <input type="number" name="application_limit" class="pr-form-control"
                                       min="1" value="{{ $unit->application_limit }}" required>
                            </div>
                            <div class="pr-form-group">
                                <label class="pr-form-label">Status</label>
                                <select name="status" class="pr-form-control">
                                    <option value="vacant"      {{ $unit->status === 'vacant'      ? 'selected' : '' }}>Vacant</option>
                                    <option value="occupied"    {{ $unit->status === 'occupied'    ? 'selected' : '' }}>Occupied</option>
                                    <option value="maintenance" {{ $unit->status === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="pr-modal__footer">
                        <button type="button" class="pr-btn pr-btn--outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="pr-btn pr-btn--navy">
                            <i class="bi bi-check-circle"></i> Update Unit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ---- Delete Modal ---- --}}
    <div class="modal fade" id="deleteUnitModal{{ $unit->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content pr-modal-content">
                <form method="POST" action="{{ route('manager.units.destroy', $unit->id) }}">
                    @csrf
                    @method('DELETE')
                    <div class="pr-modal__header pr-modal__header--danger">
                        <div>
                            <div class="pr-modal__eyebrow">Irreversible Action</div>
                            <h5 class="pr-modal__title">
                                <i class="bi bi-trash3-fill me-2"></i>Delete Unit
                            </h5>
                        </div>
                        <button type="button" class="pr-modal__close" data-bs-dismiss="modal" aria-label="Close">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                    <div class="pr-modal__body">
                        <div class="pr-notice pr-notice--danger">
                            <i class="bi bi-exclamation-triangle-fill" style="flex-shrink:0;"></i>
                            <div>This action is <strong>permanent</strong> and cannot be undone.</div>
                        </div>
                        <p style="font-size:.88rem;color:var(--pr-gray-800);margin:0;">
                            You are about to delete
                            <strong style="color:var(--pr-navy);">Room {{ $unit->room_no }}</strong>
                            ({{ $unit->type }}). Are you sure?
                        </p>
                    </div>
                    <div class="pr-modal__footer">
                        <button type="button" class="pr-btn pr-btn--outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="pr-btn pr-btn--red">
                            <i class="bi bi-trash3-fill"></i> Yes, Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endforeach

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Bootstrap tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"], [title]:not([data-bs-toggle])').forEach(el => {
        new bootstrap.Tooltip(el, { trigger: 'hover' });
    });

    // Auto-dismiss alerts after 5 seconds
    document.querySelectorAll('.pr-alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity .4s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 400);
        }, 5000);
    });

    // Re-open the correct modal if validation failed (page reloaded with errors)
    @if($errors->any())
        // If you track which modal was open via a session flash or hidden field,
        // you can re-open it here. Example for createUnitModal:
        // var modal = new bootstrap.Modal(document.getElementById('createUnitModal'));
        // modal.show();
    @endif
});
</script>
@endpush