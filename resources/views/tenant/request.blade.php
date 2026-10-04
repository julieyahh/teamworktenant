@extends('layouts.tenantdashboardlayout')

@section('title', 'My Requests')
@section('page-title', 'Maintenance Requests')

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
    .pr-toolbar {
        display:flex; align-items:center; justify-content:space-between;
        gap:12px; flex-wrap:wrap; margin-bottom:20px;
    }
    .pr-search-wrap { display:flex; align-items:center; border:1.5px solid var(--pr-gray-200); border-radius:10px; overflow:hidden; background:var(--pr-white); transition:border-color .2s; }
    .pr-search-wrap:focus-within { border-color:var(--pr-navy); box-shadow:0 0 0 3px rgba(12,26,58,.07); }
    .pr-search-wrap__icon { padding:0 10px; color:var(--pr-gray-500); font-size:.9rem; }
    .pr-search-input { border:none; outline:none; padding:9px 4px; font-family:var(--pr-font-body); font-size:.85rem; color:var(--pr-gray-800); width:220px; background:transparent; }

    /* ===================== BUTTONS ===================== */
    .pr-btn { display:inline-flex; align-items:center; gap:7px; padding:10px 18px; border-radius:10px; font-family:var(--pr-font-body); font-size:.85rem; font-weight:600; cursor:pointer; border:none; text-decoration:none; white-space:nowrap; transition:all .25s ease; }
    .pr-btn--navy { background:linear-gradient(135deg,var(--pr-navy),var(--pr-navy-mid)); color:var(--pr-white); }
    .pr-btn--navy:hover { color:var(--pr-white); transform:translateY(-2px); box-shadow:0 6px 18px rgba(12,26,58,.22); }
    .pr-btn--outline { background:transparent; border:1.5px solid rgba(12,26,58,.15); color:var(--pr-navy); }
    .pr-btn--outline:hover { background:rgba(12,26,58,.05); color:var(--pr-navy); }
    .pr-btn--danger-outline { background:transparent; border:1.5px solid rgba(239,68,68,.3); color:#dc2626; }
    .pr-btn--danger-outline:hover { background:rgba(239,68,68,.06); color:#dc2626; }
    .pr-btn--sm { padding:6px 12px; font-size:.78rem; }
    .pr-btn:disabled,[disabled] { opacity:.38; cursor:not-allowed; pointer-events:none; }

    /* ===================== TABLE CARD ===================== */
    .pr-table-card { background:var(--pr-white); border:1px solid rgba(12,26,58,.07); border-radius:16px; box-shadow:0 2px 12px rgba(12,26,58,.06); overflow:hidden; }
    .pr-table-card__header { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:16px 22px; border-bottom:1px solid rgba(12,26,58,.06); }
    .pr-table-card__title { font-family:var(--pr-font-display); font-size:.98rem; font-weight:700; color:var(--pr-navy); margin:0; }
    .pr-table-card__count { font-size:.72rem; font-weight:600; padding:3px 10px; border-radius:999px; background:rgba(201,168,76,.1); color:var(--pr-gold); border:1px solid rgba(201,168,76,.2); }
    .pr-table-wrap { overflow-x:auto; }
    .pr-table { width:100%; border-collapse:collapse; font-size:.85rem; }
    .pr-table thead th { font-size:.68rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--pr-gray-500); padding:12px 14px; border-bottom:1px solid rgba(12,26,58,.07); background:rgba(12,26,58,.02); white-space:nowrap; text-align:left; cursor:pointer; user-select:none; }
    .pr-table thead th:hover { color:var(--pr-navy); }
    .pr-table thead th.text-center { text-align:center; }
    .pr-table tbody td { padding:12px 14px; color:var(--pr-gray-800); border-bottom:1px solid rgba(12,26,58,.04); vertical-align:middle; }
    .pr-table tbody td.text-center { text-align:center; }
    .pr-table tbody tr:last-child td { border-bottom:0; }
    .pr-table tbody tr:hover td { background:rgba(12,26,58,.015); }

    /* ===================== PILLS ===================== */
    .pr-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:999px; font-size:.71rem; font-weight:600; }
    .pr-pill::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; }
    .pr-pill--green  { background:#f0fdf4; color:#16a34a; }
    .pr-pill--amber  { background:#fffbeb; color:#d97706; }
    .pr-pill--red    { background:#fef2f2; color:#dc2626; }
    .pr-pill--gray   { background:#f3f4f6; color:#6b7280; }
    .pr-pill--blue   { background:#eff6ff; color:#2563eb; }
    .pr-pill--navy   { background:rgba(12,26,58,.07); color:var(--pr-navy); }
    .pr-room-no { display:inline-flex; align-items:center; gap:4px; padding:2px 9px; border-radius:999px; font-size:.75rem; font-weight:700; background:rgba(12,26,58,.07); color:var(--pr-navy); border:1px solid rgba(12,26,58,.1); }

    /* ===================== EMPTY STATE ===================== */
    .pr-empty { text-align:center; padding:56px 24px; color:var(--pr-gray-500); }
    .pr-empty__icon-wrap { width:72px; height:72px; border-radius:20px; background:rgba(12,26,58,.05); display:inline-flex; align-items:center; justify-content:center; font-size:1.8rem; margin-bottom:16px; opacity:.5; }
    .pr-empty__title { font-family:var(--pr-font-display); font-size:1.05rem; font-weight:700; color:var(--pr-navy); margin-bottom:6px; }
    .pr-empty__sub { font-size:.85rem; }

    /* ===================== MODAL ===================== */
    .pr-modal-content { border:0 !important; border-radius:20px !important; box-shadow:0 32px 80px rgba(12,26,58,.28) !important; overflow:hidden; }
    .pr-modal__header { display:flex; align-items:center; justify-content:space-between; padding:20px 26px; background:linear-gradient(135deg,var(--pr-navy),var(--pr-navy-mid)); border-bottom:1px solid rgba(201,168,76,.15); }
    .pr-modal__header--danger { background:linear-gradient(135deg,#dc2626,#ef4444); }
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
    .pr-form-control:disabled { background:var(--pr-gray-100); opacity:.7; }
    .pr-form-hint { font-size:.72rem; color:var(--pr-gray-500); margin-top:4px; }
    .pr-section-label { font-size:.72rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--pr-gold); margin-bottom:12px; display:flex; align-items:center; gap:8px; }
    .pr-section-label::after { content:''; flex:1; height:1px; background:linear-gradient(to right,rgba(201,168,76,.3),transparent); }
    .pr-notice { display:flex; align-items:flex-start; gap:10px; padding:12px 14px; border-radius:10px; font-size:.85rem; margin-bottom:16px; }
    .pr-notice--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }

    /* Cancel confirm centre layout */
    .pr-cancel-body { text-align:center; padding:8px 0; }
    .pr-cancel-icon { font-size:2.4rem; margin-bottom:14px; }

    @media (max-width:768px) {
        .pr-toolbar { flex-direction:column; align-items:stretch; }
        .pr-search-input { width:100%; }
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

    @if(session('error'))
        <x-alert type="error">{{ session('error') }}</x-alert>
    @endif

    {{-- Page header --}}
    <div class="pr-dash-header">
        <div class="pr-dash-header__eyebrow">Tenant Portal</div>
        <h2 class="pr-dash-header__title">Maintenance Requests</h2>
        <p class="pr-dash-header__sub">Submit and track maintenance issues for your unit.</p>
    </div>

    {{-- Toolbar --}}
    <div class="pr-toolbar">
        @if(!$requests->isEmpty())
            <div class="pr-search-wrap">
                <span class="pr-search-wrap__icon"><i class="bi bi-search"></i></span>
                <input type="text" id="requestSearch" class="pr-search-input" placeholder="Search requests…">
            </div>
        @else
            <div></div>
        @endif

        <button type="button" class="pr-btn pr-btn--navy"
                data-bs-toggle="modal" data-bs-target="#createRequestModal">
            <i class="bi bi-plus-circle"></i> Create Request
        </button>
    </div>

    {{-- Table card --}}
    <div class="pr-table-card">
        <div class="pr-table-card__header">
            <h3 class="pr-table-card__title">
                <i class="bi bi-tools me-2" style="color:var(--pr-gold);font-size:.9rem;"></i>
                All Requests
            </h3>
            <span class="pr-table-card__count">{{ $requests->count() }} request{{ $requests->count() !== 1 ? 's' : '' }}</span>
        </div>

        @if($requests->isEmpty())
            <div class="pr-empty">
                <div class="pr-empty__icon-wrap"><i class="bi bi-tools"></i></div>
                <div class="pr-empty__title">No requests yet</div>
                <div class="pr-empty__sub">Click <strong>Create Request</strong> to report a maintenance issue.</div>
            </div>
        @else
            <div class="pr-table-wrap">
                <table class="pr-table" id="requestsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date Filed</th>
                            <th>Unit Type</th>
                            <th>Room No.</th>
                            <th>Description</th>
                            <th class="text-center">Urgency</th>
                            {{-- These two used to be one column headed
                                 "Scheduled Date" showing supposed_date, which
                                 is the tenant's own PREFERRED date. The date
                                 the office actually booked (scheduled_date)
                                 was never shown to them at all. --}}
                            <th>Preferred</th>
                            <th>Scheduled</th>
                            <th class="text-center">Your Charge</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($requests as $request)
                            @php
                                $urgPill = match($request->urgency) {
                                    'high' => 'pr-pill--red',
                                    'mid'  => 'pr-pill--amber',
                                    default => 'pr-pill--green',
                                };
                                $statPill = match($request->status) {
                                    'Pending'     => 'pr-pill--amber',
                                    'In Progress' => 'pr-pill--blue',
                                    'Completed'   => 'pr-pill--green',
                                    'Cancelled'   => 'pr-pill--gray',
                                    'Rejected'    => 'pr-pill--red',
                                    default       => 'pr-pill--gray',
                                };
                                $canCancel = in_array($request->status, ['Pending','In Progress']);
                            @endphp
                            <tr>
                                <td style="color:var(--pr-gray-500);font-size:.8rem;">{{ $loop->iteration }}</td>
                                <td style="color:var(--pr-gray-500);">
                                    {{ \Carbon\Carbon::parse($request->created_at)->format('M d, Y') }}
                                </td>
                                <td style="font-weight:600;">{{ $request->unit_type ?? '—' }}</td>
                                <td>
                                    <span class="pr-room-no">
                                        <i class="bi bi-door-open" style="font-size:.7rem;"></i>
                                        {{ $request->room_no ?? '—' }}
                                    </span>
                                    @if($request->lease && $request->lease->bed_number)
                                        <div style="font-size:.72rem;color:var(--pr-gray-500);margin-top:3px;">
                                            Bed {{ $request->lease->bed_number }}
                                        </div>
                                    @endif
                                </td>
                                <td style="max-width:200px;white-space:normal;">{{ ucfirst($request->description) }}</td>
                                <td class="text-center">
                                    <span class="pr-pill {{ $urgPill }}">{{ ucfirst($request->urgency) }}</span>
                                </td>
                                <td style="color:var(--pr-gray-500);">
                                    {{ $request->supposed_date ? \Carbon\Carbon::parse($request->supposed_date)->format('M d, Y') : '—' }}
                                </td>
                                <td>
                                    @if($request->scheduled_date)
                                        <span style="font-weight:600;">
                                            {{ \Carbon\Carbon::parse($request->scheduled_date)->format('M d, Y') }}
                                        </span>
                                        @if($request->assigned_to)
                                            <div style="font-size:.72rem;color:var(--pr-gray-500);margin-top:2px;">
                                                {{ $request->assigned_to }}
                                            </div>
                                        @endif
                                    @else
                                        <span style="font-size:.78rem;color:var(--pr-gray-500);font-style:italic;">
                                            Not scheduled
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    {{-- Only what the tenant owes. The repair's
                                         full cost is the landlord's business
                                         when the landlord is paying it. --}}
                                    @if(in_array($request->cost_responsibility, ['tenant','shared'], true)
                                        && (float) $request->tenant_charge_amount > 0)
                                        <span style="font-weight:700;color:#b45309;">
                                            &#8369;{{ number_format((float) $request->tenant_charge_amount, 2) }}
                                        </span>
                                        <div style="font-size:.7rem;color:var(--pr-gray-500);margin-top:2px;">
                                            {{ $request->isCharged() ? 'Billed' : 'Not yet billed' }}
                                        </div>
                                    @elseif($request->cost_responsibility === 'landlord')
                                        <span style="font-size:.75rem;color:#16a34a;font-weight:600;">No charge</span>
                                    @else
                                        <span style="font-size:.78rem;color:var(--pr-gray-500);">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="pr-pill {{ $statPill }}">{{ ucfirst($request->status) }}</span>
                                </td>
                                <td class="text-center">
                                    <div style="display:inline-flex;gap:6px;">
                                        <a href="{{ route('tenant.requests.show', $request->id) }}"
                                           class="pr-btn pr-btn--outline pr-btn--sm">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        @if($canCancel)
                                            <button type="button"
                                                    class="pr-btn pr-btn--danger-outline pr-btn--sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#cancelRequestModal{{ $request->id }}">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

@endsection

@section('modals')

{{-- ============================================================ --}}
{{-- CREATE REQUEST MODAL --}}
{{-- ============================================================ --}}
<div class="modal fade" id="createRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content pr-modal-content">
            <form method="POST" action="{{ route('tenant.requests.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="pr-modal__header">
                    <div>
                        <div class="pr-modal__eyebrow">Maintenance</div>
                        <h5 class="pr-modal__title"><i class="bi bi-tools me-2"></i>New Request</h5>
                    </div>
                    <button type="button" class="pr-modal__close" data-bs-dismiss="modal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="pr-modal__body">

                    @if($activeLeases->isEmpty())
                        <div class="pr-notice pr-notice--warning" style="margin-bottom:16px;">
                            <i class="bi bi-exclamation-triangle-fill" style="flex-shrink:0;"></i>
                            <div>No active leases found. Please contact the administrator before submitting a request.</div>
                        </div>
                    @endif

                    <div class="pr-section-label">Unit Details</div>

                    <div class="pr-form-group">
                        <label class="pr-form-label">Select Room <span class="pr-required">*</span></label>
                        <select name="lease_id" class="pr-form-control" required {{ $activeLeases->isEmpty() ? 'disabled' : '' }}>
                            <option value="">Choose your room…</option>
                            @foreach($activeLeases as $lease)
                                <option value="{{ $lease->id }}" {{ old('lease_id') == $lease->id ? 'selected' : '' }}>
                                    {{ $lease->room_no ?? 'Room' }} · {{ $lease->unit->type ?? 'Unit' }}
                                    @if($lease->bed_number) (Bed {{ $lease->bed_number }}) @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="pr-form-hint">Pick the unit that needs maintenance.</div>
                    </div>

                    <div class="pr-section-label">Request Information</div>

                    <div class="pr-form-group">
                        <label class="pr-form-label">Description <span class="pr-required">*</span></label>
                        <textarea name="description" class="pr-form-control" rows="3"
                                  placeholder="Describe the issue clearly…" required>{{ old('description') }}</textarea>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div class="pr-form-group">
                            <label class="pr-form-label">Urgency</label>
                            <select name="urgency" class="pr-form-control" required>
                                <option value="" disabled>Select…</option>
                                <option value="low" {{ old('urgency', 'mid') === 'low' ? 'selected' : '' }}>Low</option>
                                <option value="mid" {{ old('urgency', 'mid') === 'mid' ? 'selected' : '' }}>Mid</option>
                                <option value="high" {{ old('urgency', 'mid') === 'high' ? 'selected' : '' }}>High</option>
                            </select>
                        </div>
                        <div class="pr-form-group">
                            <label class="pr-form-label">Preferred Date <span class="pr-required">*</span></label>
                            <input type="date" name="supposed_date" class="pr-form-control"
                                   value="{{ old('supposed_date') }}"
                                   min="{{ now()->toDateString() }}" 
                                   required>
                            <div class="pr-form-hint">When it suits you. The office will confirm the actual date.</div>
                        </div>
                    </div>

                    <div class="pr-form-group">
                        <label class="pr-form-label">Attach Image <span style="color:var(--pr-gray-500);font-weight:400;">(optional)</span></label>
                        <input type="file" name="issue_image" class="pr-form-control" accept="image/*">
                        <div class="pr-form-hint">Upload a photo of the issue to help the team assess it faster.</div>
                    </div>

                </div>
                <div class="pr-modal__footer">
                    <button type="button" class="pr-btn pr-btn--outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="pr-btn pr-btn--navy" {{ $activeLeases->isEmpty() ? 'disabled' : '' }}>
                        <i class="bi bi-send-fill"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- CANCEL REQUEST MODALS (one per request) --}}
{{-- ============================================================ --}}
@foreach($requests as $request)
    @if(in_array($request->status, ['Pending','In Progress']))
        <div class="modal fade" id="cancelRequestModal{{ $request->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content pr-modal-content">
                    <div class="pr-modal__header pr-modal__header--danger">
                        <div>
                            <div class="pr-modal__eyebrow">Maintenance</div>
                            <h5 class="pr-modal__title"><i class="bi bi-x-circle me-2"></i>Cancel Request</h5>
                        </div>
                        <button type="button" class="pr-modal__close" data-bs-dismiss="modal">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="pr-modal__body pr-cancel-body">
                        <div class="pr-cancel-icon">⚠️</div>
                        <p style="font-size:.9rem;color:var(--pr-gray-800);margin-bottom:6px;">
                            Are you sure you want to cancel this maintenance request?
                        </p>
                        <p style="font-size:.78rem;color:var(--pr-gray-500);margin:0;">
                            Once cancelled, it cannot be modified.
                        </p>
                    </div>
                    <div class="pr-modal__footer" style="justify-content:center;">
                        <button type="button" class="pr-btn pr-btn--outline" data-bs-dismiss="modal">
                            No, Keep It
                        </button>
                        <form action="{{ route('tenant.requests.cancel', $request->id) }}" method="POST" style="display:inline;">
                            @csrf @method('PATCH')
                            <button type="submit" class="pr-btn pr-btn--navy">
                                <i class="bi bi-x-circle-fill"></i> Yes, Cancel
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endforeach

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('requestSearch');
    const tbody       = document.querySelector('#requestsTable tbody');
    const rows        = tbody ? Array.from(tbody.querySelectorAll('tr')) : [];

    // Search
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const term = this.value.toLowerCase();
            rows.forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
        });
    }

    // Column sorting
    document.querySelectorAll('#requestsTable thead th').forEach((th, idx) => {
        th.addEventListener('click', () => {
            const sorted = rows.sort((a, b) => {
                const aText = a.children[idx]?.textContent.trim() ?? '';
                const bText = b.children[idx]?.textContent.trim() ?? '';
                return aText.localeCompare(bText, undefined, { numeric:true });
            });
            sorted.forEach(row => tbody.appendChild(row));
        });
    });
});
</script>
@endpush