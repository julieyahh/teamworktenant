@extends('layouts.managerdashboardlayout')

@section('title', 'Pinikitan Rental | Utilities')
@section('page-title', 'Utility Management')

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
        color:var(--pr-gray-500); padding:12px 18px; border-bottom:1px solid rgba(12,26,58,.07);
        background:rgba(12,26,58,.02); white-space:nowrap; text-align:left;
    }
    .pr-table tbody td { padding:13px 18px; color:var(--pr-gray-800); border-bottom:1px solid rgba(12,26,58,.04); vertical-align:middle; }
    .pr-table tbody tr:last-child td { border-bottom:0; }
    .pr-table tbody tr:hover td { background:rgba(12,26,58,.015); }

    /* ===================== AVATAR ===================== */
    .pr-avatar {
        width:32px; height:32px; border-radius:50%;
        display:inline-flex; align-items:center; justify-content:center;
        font-weight:700; font-size:.75rem; flex-shrink:0;
        background:linear-gradient(135deg,var(--pr-navy),var(--pr-navy-light));
        color:var(--pr-gold-lt);
    }

    /* ===================== ROOM BADGE ===================== */
    .pr-room-badge {
        display:inline-flex; align-items:center; gap:5px;
        padding:3px 10px; border-radius:999px; font-size:.75rem; font-weight:600;
        background:rgba(12,26,58,.07); color:var(--pr-navy);
        border:1px solid rgba(12,26,58,.1);
    }

    /* ===================== BALANCE ===================== */
    .pr-balance-cell { display:flex; flex-direction:column; gap:2px; }
    .pr-balance-amount { font-weight:700; font-size:.92rem; }
    .pr-balance-amount--paid   { color:#16a34a; }
    .pr-balance-amount--unpaid { color:#dc2626; }
    .pr-balance-amount--zero   { color:var(--pr-gray-500); }
    .pr-balance-meta { font-size:.72rem; color:var(--pr-gray-500); }

    /* ===================== STATUS BADGES ===================== */
    .pr-status-badge {
        display:inline-flex; align-items:center; gap:4px;
        padding:2px 8px; border-radius:999px; font-size:.68rem; font-weight:700;
        letter-spacing:.04em; text-transform:uppercase;
    }
    .pr-status-badge--paid    { background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; }
    .pr-status-badge--unpaid  { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; }
    .pr-status-badge--unbilled { background:#fefce8; color:#a16207; border:1px solid #fef08a; }

    /* ===================== BILLING BUTTONS ===================== */
    .pr-billing-btns { display:flex; gap:8px; flex-wrap:wrap; }

    .pr-billing-btn {
        display:inline-flex; align-items:center; gap:6px;
        padding:7px 13px; border-radius:9px; font-size:.78rem; font-weight:600;
        border:none; cursor:pointer; transition:all .2s; white-space:nowrap;
        font-family:var(--pr-font-body);
    }

    /* Electricity */
    .pr-billing-btn--electricity { background:rgba(245,158,11,.1); color:#d97706; border:1px solid rgba(245,158,11,.25); }
    .pr-billing-btn--electricity:hover:not(:disabled) { background:#d97706; color:#fff; border-color:#d97706; }
    .pr-billing-btn--electricity.is-unbilled {
        background:rgba(245,158,11,.18); color:#b45309; border:1px solid rgba(245,158,11,.5);
        animation: pr-pulse-amber 2s ease-in-out infinite;
    }
    .pr-billing-btn--electricity.is-billed-unpaid { background:rgba(220,38,38,.08); color:#dc2626; border:1px solid rgba(220,38,38,.25); cursor:not-allowed; }
    .pr-billing-btn--electricity.is-paid { background:rgba(22,163,74,.08); color:#16a34a; border:1px solid rgba(22,163,74,.2); opacity:.7; cursor:not-allowed; }

    /* Water */
    .pr-billing-btn--water { background:rgba(37,99,235,.08); color:#2563eb; border:1px solid rgba(37,99,235,.2); }
    .pr-billing-btn--water:hover:not(:disabled) { background:#2563eb; color:#fff; border-color:#2563eb; }
    .pr-billing-btn--water.is-unbilled {
        background:rgba(37,99,235,.15); color:#1d4ed8; border:1px solid rgba(37,99,235,.45);
        animation: pr-pulse-blue 2s ease-in-out infinite;
    }
    .pr-billing-btn--water.is-billed-unpaid { background:rgba(220,38,38,.08); color:#dc2626; border:1px solid rgba(220,38,38,.25); cursor:not-allowed; }
    .pr-billing-btn--water.is-paid { background:rgba(22,163,74,.08); color:#16a34a; border:1px solid rgba(22,163,74,.2); opacity:.7; cursor:not-allowed; }

    @keyframes pr-pulse-amber {
        0%,100% { box-shadow: 0 0 0 0 rgba(245,158,11,.35); }
        50%      { box-shadow: 0 0 0 5px rgba(245,158,11,0); }
    }
    @keyframes pr-pulse-blue {
        0%,100% { box-shadow: 0 0 0 0 rgba(37,99,235,.3); }
        50%      { box-shadow: 0 0 0 5px rgba(37,99,235,0); }
    }

    .pr-billing-btn:disabled { cursor:not-allowed; }

    .pr-billing-btn__icon {
        display:inline-flex; align-items:center; justify-content:center;
        width:14px; height:14px; border-radius:50%; font-size:.65rem;
    }
    .pr-billing-btn__icon--check  { background:rgba(22,163,74,.15);  color:#16a34a; }
    .pr-billing-btn__icon--warn   { background:rgba(220,38,38,.12);  color:#dc2626; }
    .pr-billing-btn__icon--unbill { background:rgba(245,158,11,.15); color:#d97706; }

    /* ===================== EMPTY STATE ===================== */
    .pr-empty { text-align:center; padding:48px 24px; color:var(--pr-gray-500); }
    .pr-empty__icon { font-size:2.5rem; margin-bottom:10px; opacity:.3; }
    .pr-empty__text { font-size:.88rem; }

    /* ===================== MODAL ===================== */
    .pr-modal-content { border:0 !important; border-radius:20px !important; box-shadow:0 32px 80px rgba(12,26,58,.28) !important; overflow:hidden; }
    .pr-modal__header { display:flex; align-items:center; justify-content:space-between; padding:20px 26px; background:linear-gradient(135deg,var(--pr-navy),var(--pr-navy-mid)); border-bottom:1px solid rgba(201,168,76,.15); }
    .pr-modal__eyebrow { font-size:.68rem; font-weight:600; letter-spacing:.14em; text-transform:uppercase; color:rgba(255,255,255,.65); margin-bottom:3px; }
    .pr-modal__title { font-family:var(--pr-font-display); font-size:1.1rem; font-weight:700; color:var(--pr-white); margin:0; }
    .pr-modal__close { width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,.12); border:none; display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,.8); cursor:pointer; transition:all .2s; }
    .pr-modal__close:hover { background:rgba(255,255,255,.22); color:#fff; }
    .pr-modal__body { padding:24px 26px; }
    .pr-modal__footer { display:flex; justify-content:flex-end; gap:10px; padding:16px 26px; border-top:1px solid rgba(12,26,58,.07); background:rgba(12,26,58,.02); }

    /* Form */
    .pr-form-group { margin-bottom:16px; }
    .pr-form-group:last-child { margin-bottom:0; }
    .pr-form-label { display:block; font-size:.78rem; font-weight:600; color:var(--pr-navy); margin-bottom:6px; }
    .pr-form-control { width:100%; padding:10px 14px; border:1.5px solid var(--pr-gray-200); border-radius:10px; font-family:var(--pr-font-body); font-size:.88rem; color:var(--pr-gray-800); background:var(--pr-white); outline:none; transition:border-color .2s; box-sizing:border-box; }
    .pr-form-control:focus { border-color:var(--pr-navy); box-shadow:0 0 0 3px rgba(12,26,58,.07); }
    .pr-form-control[readonly] { background:rgba(12,26,58,.03); cursor:default; }
    .pr-form-hint { font-size:.72rem; color:var(--pr-gray-500); margin-top:4px; }
    .pr-form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

    .pr-section-label { font-size:.72rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--pr-gold); margin-bottom:12px; display:flex; align-items:center; gap:8px; }
    .pr-section-label::after { content:''; flex:1; height:1px; background:linear-gradient(to right,rgba(201,168,76,.3),transparent); }

    .pr-btn { display:inline-flex; align-items:center; gap:7px; padding:10px 18px; border-radius:10px; font-family:var(--pr-font-body); font-size:.85rem; font-weight:600; cursor:pointer; border:none; text-decoration:none; white-space:nowrap; transition:all .25s ease; }
    .pr-btn--navy { background:linear-gradient(135deg,var(--pr-navy),var(--pr-navy-mid)); color:var(--pr-white); }
    .pr-btn--navy:hover { color:var(--pr-white); transform:translateY(-2px); box-shadow:0 6px 18px rgba(12,26,58,.22); }
    .pr-btn--outline { background:transparent; border:1.5px solid rgba(12,26,58,.15); color:var(--pr-navy); }
    .pr-btn--outline:hover { background:rgba(12,26,58,.05); color:var(--pr-navy); }

    .pr-bill-type-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:6px; }
    .pr-bill-type-dot--electricity { background:#d97706; }
    .pr-bill-type-dot--water       { background:#2563eb; }

    @media (max-width:768px) {
        .pr-form-row { grid-template-columns:1fr; }
        .pr-billing-btns { flex-direction:column; }
    }
</style>
@endpush

@section('content')

    @if(session('success'))
        <x-alert type="success">{{ session('success') }}</x-alert>
    @endif
    @if($errors->any())
        <x-alert type="error">{{ $errors->first() }}</x-alert>
    @endif

    {{-- Page header --}}
    <div class="pr-dash-header">
        <div class="pr-dash-header__eyebrow">Manager Portal</div>
        <h2 class="pr-dash-header__title">Utility Management</h2>
        <p class="pr-dash-header__sub">Track and update electricity &amp; water billing for all tenants.</p>
    </div>

    {{-- Legend --}}
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:18px;font-size:.78rem;color:var(--pr-gray-500);">
        <span style="font-weight:600;color:var(--pr-navy);">Legend:</span>
        <span style="display:inline-flex;align-items:center;gap:5px;">
            <span class="pr-status-badge pr-status-badge--paid"><i class="bi bi-check-circle-fill"></i> Paid</span>
            Tenant has paid this month
        </span>
        <span style="display:inline-flex;align-items:center;gap:5px;">
            <span class="pr-status-badge pr-status-badge--unpaid"><i class="bi bi-exclamation-circle-fill"></i> Unpaid</span>
            Bill sent, awaiting payment
        </span>
        <span style="display:inline-flex;align-items:center;gap:5px;">
            <span class="pr-status-badge pr-status-badge--unbilled"><i class="bi bi-dash-circle-fill"></i> Unbilled</span>
            Not yet billed this month
        </span>
    </div>

    {{-- Utilities table --}}
    <div class="pr-table-card">
        <div class="pr-table-card__header">
            <h3 class="pr-table-card__title">
                <i class="bi bi-lightning-charge-fill me-2" style="color:var(--pr-gold);font-size:.9rem;"></i>
                Tenant Utilities Overview
            </h3>
            @php $totalGroups = count($tenantGroups ?? []); @endphp
            <span class="pr-table-card__count">{{ $totalGroups }} tenant{{ $totalGroups !== 1 ? 's' : '' }}</span>
        </div>

        <div class="pr-table-wrap">
            <table class="pr-table">
                <thead>
                    <tr>
                        <th>Tenant</th>
                        <th>Room / Unit</th>
                        <th>Utility Balance</th>
                        <th>Elec. Status</th>
                        <th>Water Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tenantGroups as $group)
                        @php $roomCount = count($group->room_entries); @endphp
                        @foreach($group->room_entries as $index => $entry)
                            @php
                                $previousMonth = now()->subMonth()->month;
                                $currentMonth = now()->month;
                                $currentYear  = now()->year;

                                $lease = $group->all_leases->firstWhere('id', $entry->lease_id);


                                /*
                                 * Each utility type gets its OWN proof record lookup.
                                 * Electricity and Water are completely independent —
                                 * one being paid/unpaid has zero effect on the other.
                                 *
                                 * State is driven by UtilityBillingProof.status:
                                 *   no record        → 'unbilled'       (pulse button, can add bill)
                                 *   status='unpaid'  → 'billed_unpaid'  (disabled red, awaiting payment)
                                 *   status='paid'    → 'paid'           (disabled green, all clear)
                                 */
                                $elecProof = $lease
                                    ? \App\Models\UtilityBillingProof::where('lease_id', $lease->id)
                                        ->where('billing_type', 'Electricity')
                                        ->whereMonth('created_at', $currentMonth)
                                        ->whereYear('created_at', $currentYear)
                                        // ->where(function ($q) use ($currentMonth, $previousMonth) {
                                        //     $q->whereMonth('created_at', $currentMonth)
                                        //     ->orWhereMonth('created_at', $previousMonth);
                                        // })
                                        ->latest()
                                        ->first()
                                    : null;

                                $waterProof = $lease
                                    ? \App\Models\UtilityBillingProof::where('lease_id', $lease->id)
                                        ->where('billing_type', 'Water')
                                        ->whereMonth('created_at', $currentMonth)
                                        ->whereYear('created_at', $currentYear)
                                        // ->where(function ($q) use ($currentMonth, $previousMonth) {
                                        //     $q->whereMonth('created_at', $currentMonth)
                                        //     ->orWhereMonth('created_at', $previousMonth);
                                        // })
                                        ->latest()
                                        ->first()
                                    : null;

                                $electricityAmount = $elecProof?->amount  ?? 0;
                                $waterAmount       = $waterProof?->amount ?? 0;

                                $elecState = match(true) {
                                    $elecProof === null             => 'unbilled',
                                    $elecProof->status === 'paid'   => 'paid',
                                    default                         => 'billed_unpaid',
                                };

                                $waterState = match(true) {
                                    $waterProof === null            => 'unbilled',
                                    $waterProof->status === 'paid'  => 'paid',
                                    default                         => 'billed_unpaid',
                                };

                                $elecDisabled  = ($elecState  !== 'unbilled');
                                $waterDisabled = ($waterState !== 'unbilled');

                                $elecBalance  = ($elecState  !== 'paid') ? $electricityAmount : 0;
                                $waterBalance = ($waterState !== 'paid') ? $waterAmount       : 0;
                                $totalBalance = $elecBalance + $waterBalance;

                                $leasesJson = $group->all_leases->map(fn($l) => [
                                    'id'         => $l->id,
                                    'room_no'    => $l->room_no ?? 'N/A',
                                    'unit_type'  => $l->unit->type ?? 'Unit',
                                    'bed_number' => $l->bed_number,
                                ])->toJson();
                            @endphp
                            <tr id="lease-row-{{ $entry->lease_id }}">

                                {{-- Tenant name — first row only --}}
                                @if($index === 0)
                                    <td rowspan="{{ $roomCount }}" style="vertical-align:middle;">
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <div class="pr-avatar">{{ strtoupper(substr($group->tenant->name ?? '?', 0, 1)) }}</div>
                                            <span style="font-weight:600;color:var(--pr-navy);">{{ $group->tenant->name }}</span>
                                        </div>
                                    </td>
                                @endif

                                {{-- Room --}}
                                <td>
                                    <span class="pr-room-badge">
                                        <i class="bi bi-door-open" style="font-size:.72rem;"></i>
                                        {{ $entry->room_label }}
                                    </span>
                                </td>

                                {{-- Balance --}}
                                <td>
                                    <div class="pr-balance-cell">
                                        <span class="pr-balance-amount {{ $totalBalance > 0 ? 'pr-balance-amount--unpaid' : 'pr-balance-amount--zero' }}">
                                            ₱{{ number_format($totalBalance, 2) }}
                                        </span>
                                        @if($entry->created_at)
                                            <span class="pr-balance-meta">
                                                <i class="bi bi-clock" style="font-size:.65rem;"></i>
                                                {{ $entry->created_at->diffForHumans() }}
                                            </span>
                                        @endif
                                        @if($elecBalance > 0 || $waterBalance > 0)
                                            <div style="display:flex;gap:10px;margin-top:4px;flex-wrap:wrap;">
                                                @if($elecBalance > 0)
                                                    <span style="font-size:.72rem;color:#d97706;">
                                                        <span class="pr-bill-type-dot pr-bill-type-dot--electricity"></span>
                                                        ₱{{ number_format($elecBalance, 2) }}
                                                    </span>
                                                @endif
                                                @if($waterBalance > 0)
                                                    <span style="font-size:.72rem;color:#2563eb;">
                                                        <span class="pr-bill-type-dot pr-bill-type-dot--water"></span>
                                                        ₱{{ number_format($waterBalance, 2) }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </td>

                                {{-- Electricity Status --}}
                                <td>
                                    @if($elecState === 'paid')
                                        <span class="pr-status-badge pr-status-badge--paid">
                                            <i class="bi bi-check-circle-fill"></i> Paid
                                        </span>
                                    @elseif($elecState === 'billed_unpaid')
                                        <span class="pr-status-badge pr-status-badge--unpaid">
                                            <i class="bi bi-exclamation-circle-fill"></i> Unpaid
                                        </span>
                                    @else
                                        <span class="pr-status-badge pr-status-badge--unbilled">
                                            <i class="bi bi-dash-circle-fill"></i> Unbilled
                                        </span>
                                    @endif
                                </td>

                                {{-- Water Status --}}
                                <td>
                                    @if($waterState === 'paid')
                                        <span class="pr-status-badge pr-status-badge--paid">
                                            <i class="bi bi-check-circle-fill"></i> Paid
                                        </span>
                                    @elseif($waterState === 'billed_unpaid')
                                        <span class="pr-status-badge pr-status-badge--unpaid">
                                            <i class="bi bi-exclamation-circle-fill"></i> Unpaid
                                        </span>
                                    @else
                                        <span class="pr-status-badge pr-status-badge--unbilled">
                                            <i class="bi bi-dash-circle-fill"></i> Unbilled
                                        </span>
                                    @endif
                                </td>

                                {{-- Billing buttons --}}
                                <td>
                                    <div class="pr-billing-btns">

                                        {{-- Electricity button --}}
                                        <button type="button"
                                                class="pr-billing-btn pr-billing-btn--electricity edit-btn
                                                    {{ $elecState === 'unbilled'      ? 'is-unbilled'      : '' }}
                                                    {{ $elecState === 'billed_unpaid' ? 'is-billed-unpaid' : '' }}
                                                    {{ $elecState === 'paid'          ? 'is-paid'          : '' }}"
                                                data-id="{{ $entry->lease_id }}"
                                                data-name="{{ $group->tenant->name }}"
                                                data-room="{{ $entry->room_label }}"
                                                data-balance="{{ number_format($electricityAmount, 2, '.', '') }}"
                                                data-leases='{{ $leasesJson }}'
                                                data-bill-type="Electricity"
                                                data-unit-type="{{ $lease->unit->type ?? '' }}"
                                                data-occupants="{{ $occupantsByUnit[$lease->unit_id ?? 0] ?? 1 }}"
                                                {{ $elecDisabled ? 'disabled' : '' }}>
                                            <i class="bi bi-lightning-charge-fill"></i>
                                            Electricity
                                            @if($elecState === 'paid')
                                                <span class="pr-billing-btn__icon pr-billing-btn__icon--check"><i class="bi bi-check"></i></span>
                                            @elseif($elecState === 'billed_unpaid')
                                                <span class="pr-billing-btn__icon pr-billing-btn__icon--warn"><i class="bi bi-exclamation"></i></span>
                                            @else
                                                <span class="pr-billing-btn__icon pr-billing-btn__icon--unbill"><i class="bi bi-plus"></i></span>
                                            @endif
                                        </button>

                                        {{-- Water button --}}
                                        <button type="button"
                                                class="pr-billing-btn pr-billing-btn--water edit-btn
                                                    {{ $waterState === 'unbilled'      ? 'is-unbilled'      : '' }}
                                                    {{ $waterState === 'billed_unpaid' ? 'is-billed-unpaid' : '' }}
                                                    {{ $waterState === 'paid'          ? 'is-paid'          : '' }}"
                                                data-id="{{ $entry->lease_id }}"
                                                data-name="{{ $group->tenant->name }}"
                                                data-room="{{ $entry->room_label }}"
                                                data-balance="{{ number_format($waterAmount, 2, '.', '') }}"
                                                data-leases='{{ $leasesJson }}'
                                                data-bill-type="Water"
                                                {{ $waterDisabled ? 'disabled' : '' }}>
                                            <i class="bi bi-droplet-fill"></i>
                                            Water
                                            @if($waterState === 'paid')
                                                <span class="pr-billing-btn__icon pr-billing-btn__icon--check"><i class="bi bi-check"></i></span>
                                            @elseif($waterState === 'billed_unpaid')
                                                <span class="pr-billing-btn__icon pr-billing-btn__icon--warn"><i class="bi bi-exclamation"></i></span>
                                            @else
                                                <span class="pr-billing-btn__icon pr-billing-btn__icon--unbill"><i class="bi bi-plus"></i></span>
                                            @endif
                                        </button>

                                    </div>
                                </td>

                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="pr-empty">
                                    <div class="pr-empty__icon"><i class="bi bi-lightning-charge"></i></div>
                                    <div class="pr-empty__text">No tenant utility records found.</div>
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
{{-- UPDATE UTILITY MODAL --}}
{{-- ============================================================ --}}
<div class="modal fade" id="editUtilityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content pr-modal-content">
            <div class="pr-modal__header" id="editUtilityModalHeader">
                <div>
                    <div class="pr-modal__eyebrow" id="editUtilityModalEyebrow">Utility Billing</div>
                    <h5 class="pr-modal__title" id="editUtilityModalTitle">
                        <i class="bi bi-pencil-square me-2"></i>Update Billing
                    </h5>
                </div>
                <button type="button" class="pr-modal__close" data-bs-dismiss="modal">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            <form id="editUtilityForm" method="POST" enctype="multipart/form-data">
                @csrf @method('PUT')
                <input type="hidden" id="leaseId"  name="lease_id">
                <input type="hidden" id="billType" name="bill_type">

                <div class="pr-modal__body">

                    <div class="pr-section-label">Tenant Details</div>
                    <div class="pr-form-row" style="margin-bottom:16px;">
                        <div class="pr-form-group">
                            <label class="pr-form-label">Tenant Name</label>
                            <input type="text" id="tenantName" class="pr-form-control" readonly>
                        </div>
                        <div class="pr-form-group">
                            <label class="pr-form-label">Room / Unit</label>
                            <input type="text" id="selectedRoom" class="pr-form-control" readonly>
                        </div>
                    </div>

                    <div class="pr-section-label">Bill Information</div>
                    <div class="pr-form-group" id="utilityBalanceField">
                        <label class="pr-form-label" id="utilityBalanceLabel">Bill Amount (₱) <span style="color:#dc2626;">*</span></label>
                        <input type="number" step="0.01" min="0" id="utilityBalance"
                               name="utility_balance" class="pr-form-control"
                               placeholder="0.00" required>
                    </div>

                    {{-- Electricity: billed by meter reading, same as the Bills
                         ledger. Rate + Bed-Spacer division both happen
                         server-side — this hint is only a preview. --}}
                    <div class="pr-form-group d-none" id="utilityKwhField">
                        <label class="pr-form-label">Meter Reading (kWh) <span style="color:#dc2626;">*</span></label>
                        <input type="number" step="0.01" min="0.01" id="utilityKwh" name="kwh_reading" class="pr-form-control" placeholder="0.00">
                        <div class="pr-form-hint" id="utilityKwhHint">
                            PHP {{ number_format((float) config('billing.electricity.rate_per_kwh', 18), 2) }} per kWh.
                        </div>
                    </div>

                    <div class="pr-form-row">
                        <div class="pr-form-group">
                            <label class="pr-form-label" id="proofLabel">Proof of Billing</label>
                            <input type="file" id="proofOfUtilityBilling"
                                   name="proof_of_utility_billing"
                                   class="pr-form-control" accept="image/*">
                            <div class="pr-form-hint">Upload a bill image (JPG, PNG).</div>
                        </div>
                        <div class="pr-form-group">
                            <label class="pr-form-label">Billing Month</label>
                            <input type="month" min="{{ now()->format('Y-m') }}"  id="billingMonth" name="billing_month" class="pr-form-control">
                            <div class="pr-form-hint">Optional — leave blank if not needed.</div>
                        </div>
                    </div>

                    <div class="pr-form-group">
                        <label class="pr-form-label">Notes <span style="color:var(--pr-gray-500);font-weight:400;">(optional)</span></label>
                        <textarea name="proof_notes" class="pr-form-control" rows="2"
                                  placeholder="Short note about this bill…"></textarea>
                    </div>

                </div>

                <div class="pr-modal__footer">
                    <button type="button" class="pr-btn pr-btn--outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="pr-btn pr-btn--navy" id="saveUtilityBtn">
                        <i class="bi bi-check-circle"></i> Save Changes
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
    const modalEl   = document.getElementById('editUtilityModal');
    const modal     = new bootstrap.Modal(modalEl);
    const editForm  = document.getElementById('editUtilityForm');

    const tenantNameInput   = document.getElementById('tenantName');
    const selectedRoomInput = document.getElementById('selectedRoom');
    const utilityBalInput   = document.getElementById('utilityBalance');
    const utilityBalField   = document.getElementById('utilityBalanceField');
    const kwhField          = document.getElementById('utilityKwhField');
    const kwhInput          = document.getElementById('utilityKwh');
    const kwhHint           = document.getElementById('utilityKwhHint');
    const proofInput        = document.getElementById('proofOfUtilityBilling');
    const billingMonthInput = document.getElementById('billingMonth');
    const proofNotesInput   = document.querySelector('textarea[name="proof_notes"]');
    const leaseIdInput      = document.getElementById('leaseId');
    const billTypeInput     = document.getElementById('billType');

    const modalHeader     = document.getElementById('editUtilityModalHeader');
    const modalEyebrow    = document.getElementById('editUtilityModalEyebrow');
    const modalTitle      = document.getElementById('editUtilityModalTitle');
    const utilityBalLabel = document.getElementById('utilityBalanceLabel');
    const proofLabel      = document.getElementById('proofLabel');
    const saveBtn         = document.getElementById('saveUtilityBtn');

    const RATE = {{ (float) config('billing.electricity.rate_per_kwh', 18) }};

    const routeTemplate = "{{ route('manager.utilities.update', ['id' => ':id']) }}";

    const headerThemes = {
        Electricity: 'linear-gradient(135deg, #92400e, #d97706)',
        Water:       'linear-gradient(135deg, #1e3a8a, #2563eb)',
    };

    function fmt(n) {
        return n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Purely a preview — the amount actually saved is always computed
    // server-side from kwh_reading, config('billing.electricity.rate_per_kwh'),
    // and the room's live occupant count.
    function updateKwhPreview(isBedSpacer, occupants) {
        const kwh   = parseFloat(kwhInput.value || 0);
        const total = kwh * RATE;

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

    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (btn.disabled) return;

            const leaseId  = btn.dataset.id;
            const billType = btn.dataset.billType;
            const balance  = btn.dataset.balance;
            const isElectricity = billType === 'Electricity';
            const isBedSpacer   = btn.dataset.unitType === 'Bed-Spacer';
            const occupants     = parseInt(btn.dataset.occupants, 10) || 1;

            leaseIdInput.value  = leaseId;
            billTypeInput.value = billType;

            tenantNameInput.value   = btn.dataset.name;
            selectedRoomInput.value = btn.dataset.room;
            utilityBalInput.value   = balance ?? '';

            const icon = billType === 'Electricity'
                ? '<i class="bi bi-lightning-charge-fill me-2"></i>'
                : '<i class="bi bi-droplet-fill me-2"></i>';

            modalHeader.style.background = headerThemes[billType] || headerThemes.Electricity;
            modalEyebrow.textContent     = `${billType} Billing`;
            modalTitle.innerHTML         = `${icon}Update ${billType} Billing`;
            utilityBalLabel.textContent  = `${billType} Bill Amount (₱)`;
            proofLabel.textContent       = `Proof of ${billType} Billing`;
            saveBtn.innerHTML = `<i class="bi bi-check-circle"></i> Save ${billType} Bill`;

            // Electricity: meter reading instead of a typed amount.
            // NOTE: these fields start with Bootstrap's `d-none` class,
            // which is `!important` — toggling only `style.display` (as an
            // earlier version of this did) can't override that, so the kWh
            // field would never actually appear. Toggle the class itself.
            utilityBalField.classList.toggle('d-none', isElectricity);
            kwhField.classList.toggle('d-none', !isElectricity);
            utilityBalInput.required      = !isElectricity;
            kwhInput.required             = isElectricity;
            kwhInput.value                = '';

            if (isElectricity) {
                updateKwhPreview(isBedSpacer, occupants);
                kwhInput.oninput = () => updateKwhPreview(isBedSpacer, occupants);
            }

            proofInput.value        = '';
            billingMonthInput.value = '';
            proofNotesInput.value   = '';

            editForm.action = routeTemplate.replace(':id', leaseId);
            modal.show();
        });
    });

    // Sanitise numeric input
    utilityBalInput.addEventListener('input', function () {
        let val = this.value.replace(/[^0-9.]/g, '');
        const parts = val.split('.');
        if (parts.length > 2) val = parts[0] + '.' + parts.slice(1).join('');
        this.value = val;
    });
});
</script>
@endpush