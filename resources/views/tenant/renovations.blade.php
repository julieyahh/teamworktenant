@extends('layouts.tenantdashboardlayout')

@section('title', 'Renovation Requests')
@section('page-title', 'Renovation Requests')

@push('styles')
<style>
    /* ===================== PAGE HEADER ===================== */
    .pr-dash-header { margin-bottom:24px; }
    .pr-dash-header__eyebrow { font-size:.72rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:var(--pr-gold); margin-bottom:6px; }
    .pr-dash-header__title { font-family:var(--pr-font-display); font-size:1.5rem; font-weight:700; color:var(--pr-navy); margin:0 0 4px; line-height:1.2; }
    .pr-dash-header__sub { font-size:.85rem; color:var(--pr-gray-500); margin:0; }

    /* ===================== TOOLBAR ===================== */
    .pr-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:20px; flex-wrap:wrap; }

    /* ===================== BUTTONS ===================== */
    .pr-btn { display:inline-flex; align-items:center; gap:7px; padding:10px 18px; border-radius:10px; font-family:var(--pr-font-body); font-size:.85rem; font-weight:600; cursor:pointer; border:none; text-decoration:none; white-space:nowrap; transition:all .25s ease; }
    .pr-btn--navy { background:linear-gradient(135deg,var(--pr-navy),var(--pr-navy-mid)); color:var(--pr-white); }
    .pr-btn--navy:hover { color:var(--pr-white); transform:translateY(-2px); box-shadow:0 6px 18px rgba(12,26,58,.22); }
    .pr-btn--outline { background:transparent; border:1.5px solid rgba(12,26,58,.15); color:var(--pr-navy); }
    .pr-btn--outline:hover { background:rgba(12,26,58,.05); color:var(--pr-navy); }
    .pr-btn--danger-outline { background:transparent; border:1.5px solid rgba(239,68,68,.3); color:#dc2626; }
    .pr-btn--danger-outline:hover { background:rgba(239,68,68,.06); color:#dc2626; }
    .pr-btn--sm { padding:7px 12px; font-size:.78rem; }

    /* ===================== TABLE CARD ===================== */
    .pr-table-card { background:var(--pr-white); border:1px solid rgba(12,26,58,.07); border-radius:16px; box-shadow:0 2px 12px rgba(12,26,58,.06); overflow:hidden; }
    .pr-table-card__header { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:16px 22px; border-bottom:1px solid rgba(12,26,58,.06); }
    .pr-table-card__title { font-family:var(--pr-font-display); font-size:.98rem; font-weight:700; color:var(--pr-navy); margin:0; }
    .pr-table-card__count { font-size:.72rem; font-weight:600; padding:3px 10px; border-radius:999px; background:rgba(201,168,76,.1); color:var(--pr-gold); border:1px solid rgba(201,168,76,.2); }

    .pr-table-wrap { overflow-x:auto; }
    .pr-table { width:100%; border-collapse:collapse; font-size:.85rem; }
    .pr-table thead th { font-size:.68rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--pr-gray-500); padding:12px 16px; border-bottom:1px solid rgba(12,26,58,.07); background:rgba(12,26,58,.02); text-align:left; white-space:nowrap; }
    .pr-table tbody td { padding:13px 16px; color:var(--pr-gray-800); border-bottom:1px solid rgba(12,26,58,.04); vertical-align:middle; }
    .pr-table tbody tr:last-child td { border-bottom:0; }
    .pr-table tbody tr:hover td { background:rgba(12,26,58,.015); }

    /* ===================== PILLS ===================== */
    .pr-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:999px; font-size:.71rem; font-weight:600; }
    .pr-pill::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; }
    .pr-pill--green { background:#f0fdf4; color:#16a34a; }
    .pr-pill--amber { background:#fffbeb; color:#d97706; }
    .pr-pill--red   { background:#fef2f2; color:#dc2626; }
    .pr-pill--gray  { background:#f3f4f6; color:#6b7280; }

    .pr-room-no { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:999px; font-size:.72rem; font-weight:600; background:rgba(12,26,58,.07); color:var(--pr-navy); border:1px solid rgba(12,26,58,.1); }

    /* ===================== EMPTY ===================== */
    .pr-empty { text-align:center; padding:56px 24px; color:var(--pr-gray-500); }
    .pr-empty__icon-wrap { width:72px; height:72px; border-radius:20px; background:rgba(12,26,58,.05); display:inline-flex; align-items:center; justify-content:center; font-size:1.8rem; margin-bottom:16px; opacity:.5; }
    .pr-empty__title { font-family:var(--pr-font-display); font-size:1.05rem; font-weight:700; color:var(--pr-navy); margin-bottom:6px; }
    .pr-empty__sub { font-size:.85rem; }

    /* ===================== MODAL ===================== */
    .pr-modal-content { border:0 !important; border-radius:20px !important; box-shadow:0 32px 80px rgba(12,26,58,.28) !important; overflow:hidden; }
    .pr-modal__header { display:flex; align-items:center; justify-content:space-between; padding:20px 26px; background:linear-gradient(135deg,var(--pr-navy),var(--pr-navy-mid)); }
    .pr-modal__header--danger { background:linear-gradient(135deg,#dc2626,#ef4444); }
    .pr-modal__eyebrow { font-size:.68rem; font-weight:600; letter-spacing:.14em; text-transform:uppercase; color:rgba(255,255,255,.65); margin-bottom:3px; }
    .pr-modal__title { font-family:var(--pr-font-display); font-size:1.1rem; font-weight:700; color:var(--pr-white); margin:0; }
    .pr-modal__close { width:34px; height:34px; border-radius:50%; background:rgba(255,255,255,.12); border:none; display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,.8); cursor:pointer; }
    .pr-modal__close:hover { background:rgba(255,255,255,.22); color:#fff; }
    .pr-modal__body { padding:24px 26px; }
    .pr-modal__footer { display:flex; justify-content:flex-end; gap:10px; padding:16px 26px; border-top:1px solid rgba(12,26,58,.07); background:rgba(12,26,58,.02); }

    .pr-form-group { margin-bottom:16px; }
    .pr-form-group:last-child { margin-bottom:0; }
    .pr-form-label { display:block; font-size:.78rem; font-weight:600; color:var(--pr-navy); margin-bottom:6px; }
    .pr-required { color:#dc2626; }
    .pr-form-control { width:100%; padding:10px 14px; border:1.5px solid var(--pr-gray-200); border-radius:10px; font-family:var(--pr-font-body); font-size:.88rem; color:var(--pr-gray-800); background:var(--pr-white); outline:none; transition:border-color .2s; box-sizing:border-box; }
    .pr-form-control:focus { border-color:var(--pr-navy); box-shadow:0 0 0 3px rgba(12,26,58,.07); }
    .pr-form-hint { font-size:.72rem; color:var(--pr-gray-500); margin-top:4px; }
    .pr-invalid-feedback { font-size:.78rem; color:#dc2626; margin-top:4px; }
    .pr-section-label { font-size:.72rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--pr-gold); margin-bottom:12px; display:flex; align-items:center; gap:8px; }
    .pr-section-label::after { content:''; flex:1; height:1px; background:linear-gradient(to right,rgba(201,168,76,.3),transparent); }
    .pr-notice { display:flex; align-items:flex-start; gap:10px; padding:12px 14px; border-radius:10px; font-size:.85rem; margin-bottom:16px; }
    .pr-notice--warning { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .pr-notice--info { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }
    .pr-confirm-body { text-align:center; padding:8px 0; }
    .pr-confirm-icon { font-size:2.4rem; margin-bottom:14px; }
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
    @if($errors->any())
        <x-alert type="error">{{ $errors->first() }}</x-alert>
    @endif

    {{-- Page header --}}
    <div class="pr-dash-header">
        <div class="pr-dash-header__eyebrow">Tenant Portal</div>
        <h2 class="pr-dash-header__title">Renovation Requests</h2>
        <p class="pr-dash-header__sub">Ask the manager for permission to renovate or improve your unit.</p>
    </div>

    {{-- Toolbar --}}
    <div class="pr-toolbar">
        <div></div>
        @if($activeLeases->isEmpty())
            <button type="button" class="pr-btn pr-btn--navy" disabled
                    title="You need an active lease with the deposit fully paid to request a renovation"
                    style="opacity:.55;cursor:not-allowed;">
                <i class="bi bi-lock-fill"></i> Request Renovation
            </button>
        @else
            <button type="button" class="pr-btn pr-btn--navy"
                    data-bs-toggle="modal" data-bs-target="#createRenovationModal">
                <i class="bi bi-hammer"></i> Request Renovation
            </button>
        @endif
    </div>

    {{-- Deposit gate.
         A room with an unpaid deposit is filtered out of the dropdown by
         Tenant\RenovationRequestController@index. Without this block the room
         would just silently vanish and the page would look broken, so say
         plainly why it is missing and what clears it. --}}
    @if(!empty($blockedLeases) && $blockedLeases->isNotEmpty())
        <div class="pr-notice pr-notice--warning">
            <i class="bi bi-cash-coin"></i>
            <div>
                <strong>Renovation requests are locked while a deposit is outstanding.</strong>
                <ul style="margin:8px 0 0 0;padding-left:18px;">
                    @foreach($blockedLeases as $blocked)
                        <li>
                            Room {{ $blocked->room_no ?? ($blocked->unit->room_no ?? 'N/A') }} —
                            PHP {{ number_format((float) $blocked->deposit_balance, 2) }} deposit still due
                        </li>
                    @endforeach
                </ul>
                <div style="margin-top:8px;">
                    <a href="{{ route('tenant.payments') }}">Settle the deposit</a>
                    and this room becomes available for renovation requests.
                </div>
            </div>
        </div>
    @endif

    @if($activeLeases->isEmpty())
        <div class="pr-notice pr-notice--warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                @if(!empty($blockedLeases) && $blockedLeases->isNotEmpty())
                    None of your rooms are eligible right now — see the deposit note above.
                @else
                    You need an active lease before you can submit a renovation request.
                @endif
            </div>
        </div>
    @endif

    {{-- Table card --}}
    <div class="pr-table-card">
        <div class="pr-table-card__header">
            <h3 class="pr-table-card__title">
                <i class="bi bi-hammer me-2" style="color:var(--pr-gold);font-size:.9rem;"></i>
                My Renovation Requests
            </h3>
            <span class="pr-table-card__count">
                {{ $requests->count() }} request{{ $requests->count() !== 1 ? 's' : '' }}
            </span>
        </div>

        @if($requests->isEmpty())
            <div class="pr-empty">
                <div class="pr-empty__icon-wrap"><i class="bi bi-hammer"></i></div>
                <div class="pr-empty__title">No renovation requests yet</div>
                <div class="pr-empty__sub">Click <strong>Request Renovation</strong> to submit one for manager approval.</div>
            </div>
        @else
            <div class="pr-table-wrap">
                <table class="pr-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date Filed</th>
                            <th>Room No.</th>
                            <th>Request</th>
                            <th>Type</th>
                            <th>Est. Cost</th>
                            <th>Preferred Date</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($requests as $renovation)
                            @php
                                $statPill = match($renovation->status) {
                                    'Approved'  => 'pr-pill--green',
                                    'Denied'    => 'pr-pill--red',
                                    'Pending'   => 'pr-pill--amber',
                                    default     => 'pr-pill--gray',
                                };
                            @endphp
                            <tr>
                                <td style="color:var(--pr-gray-500);font-size:.8rem;">{{ $loop->iteration }}</td>
                                <td style="color:var(--pr-gray-500);white-space:nowrap;">
                                    {{ $renovation->created_at->format('M d, Y') }}
                                </td>
                                <td>
                                    <span class="pr-room-no">
                                        <i class="bi bi-door-open" style="font-size:.7rem;"></i>
                                        {{ $renovation->room_no ?? '—' }}
                                    </span>
                                </td>
                                <td style="max-width:240px;white-space:normal;">
                                    {{-- Requests filed before the Title field was removed still
                                         have one and it is the best label for them; newer ones
                                         fall back to the type. --}}
                                    @if($renovation->title)
                                        <div style="font-weight:600;color:var(--pr-navy);">{{ $renovation->title }}</div>
                                    @endif
                                    <div style="font-size:.78rem;color:var(--pr-gray-500);margin-top:2px;">
                                        {{ $renovation->description }}
                                    </div>
                                    @if($renovation->manager_remarks)
                                        <div style="font-size:.75rem;margin-top:6px;color:var(--pr-navy);">
                                            <strong>Manager:</strong> {{ $renovation->manager_remarks }}
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $renovation->renovation_type ?? '—' }}</td>
                                <td style="white-space:nowrap;">
                                    {{ $renovation->estimated_cost ? '₱'.number_format($renovation->estimated_cost, 2) : '—' }}
                                </td>
                                <td style="color:var(--pr-gray-500);white-space:nowrap;">
                                    {{ $renovation->preferred_date ? $renovation->preferred_date->format('M d, Y') : '—' }}
                                </td>
                                <td class="text-center">
                                    <span class="pr-pill {{ $statPill }}">{{ $renovation->status }}</span>
                                </td>
                                <td class="text-center">
                                    @if($renovation->status === 'Pending')
                                        <button type="button" class="pr-btn pr-btn--danger-outline pr-btn--sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#cancelRenovationModal{{ $renovation->id }}">
                                            <i class="bi bi-x-circle"></i> Cancel
                                        </button>
                                    @else
                                        <span style="font-size:.78rem;color:var(--pr-gray-500);">—</span>
                                    @endif
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
{{-- CREATE RENOVATION REQUEST MODAL --}}
{{-- ============================================================ --}}
@if($activeLeases->isNotEmpty())
<div class="modal fade" id="createRenovationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content pr-modal-content">
            <form method="POST" action="{{ route('tenant.renovations.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="pr-modal__header">
                    <div>
                        <div class="pr-modal__eyebrow">Tenant Portal</div>
                        <h5 class="pr-modal__title"><i class="bi bi-hammer me-2"></i>Request Renovation</h5>
                    </div>
                    <button type="button" class="pr-modal__close" data-bs-dismiss="modal">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="pr-modal__body">

                    <div class="pr-notice pr-notice--info">
                        <i class="bi bi-info-circle-fill" style="flex-shrink:0;"></i>
                        <div>Your request will be reviewed by the property manager. You'll be notified once it is approved or denied.</div>
                    </div>

                    <div class="pr-section-label">Room</div>

                    <div class="pr-form-group">
                        <label class="pr-form-label">Select Room <span class="pr-required">*</span></label>
                        <select name="lease_id" class="pr-form-control @error('lease_id') is-invalid @enderror" required>
                            <option value="">Select a room…</option>
                            @foreach($activeLeases as $lease)
                                <option value="{{ $lease->id }}" {{ old('lease_id') == $lease->id ? 'selected' : '' }}>
                                    {{ $lease->room_no ?? 'Room' }} · {{ $lease->unit->type ?? 'Unit' }}
                                    @if($lease->bed_number) · Bed {{ $lease->bed_number }} @endif
                                </option>
                            @endforeach
                        </select>
                        @error('lease_id')<div class="pr-invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="pr-section-label">Renovation Details</div>

                    {{-- The Title field was removed. The type below plus the
                         description already describe the request, and asking a
                         tenant to invent a headline for "the cabinet door is
                         loose" was friction for nothing. --}}

                    <div class="pr-form-group">
                        <label class="pr-form-label">Renovation Type <span class="pr-required">*</span></label>
                        <select name="renovation_type" id="renovationTypeSelect"
                                class="pr-form-control @error('renovation_type') is-invalid @enderror" required>
                            <option value="">Select type…</option>
                            @foreach(\App\Models\RenovationRequest::TYPES as $type)
                                <option value="{{ $type }}" {{ old('renovation_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                            @endforeach
                        </select>
                        @error('renovation_type')<div class="pr-invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Revealed only when "Others" is picked. Hidden by
                         default and unhidden by the script at the bottom of
                         this file; it re-opens on validation failure so the
                         tenant does not lose what they typed. --}}
                    <div class="pr-form-group" id="renovationTypeOtherGroup"
                         style="display:{{ old('renovation_type') === \App\Models\RenovationRequest::TYPE_OTHERS ? 'block' : 'none' }};">
                        <label class="pr-form-label">Please specify <span class="pr-required">*</span></label>
                        <input type="text" name="renovation_type_other" id="renovationTypeOtherInput" maxlength="100"
                               class="pr-form-control @error('renovation_type_other') is-invalid @enderror"
                               placeholder="e.g. Aircon installation"
                               value="{{ old('renovation_type_other') }}">
                        <div class="pr-form-hint">Tell us what kind of work this is.</div>
                        @error('renovation_type_other')<div class="pr-invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="pr-form-group">
                        <label class="pr-form-label">Description <span class="pr-required">*</span></label>
                        <textarea name="description" rows="4" required maxlength="2000"
                                  class="pr-form-control @error('description') is-invalid @enderror"
                                  placeholder="Describe what you want to change and why…">{{ old('description') }}</textarea>
                        @error('description')<div class="pr-invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="pr-form-group">
                        <label class="pr-form-label">Estimated Cost</label>
                        <input type="number" name="estimated_cost" step="0.01" min="0"
                               class="pr-form-control @error('estimated_cost') is-invalid @enderror"
                               placeholder="0.00" value="{{ old('estimated_cost') }}">
                        <div class="pr-form-hint">Optional — helps the manager assess the request.</div>
                        @error('estimated_cost')<div class="pr-invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="pr-form-group">
                        <label class="pr-form-label">Preferred Start Date <span class="pr-required">*</span></label>
                        <input type="date" name="preferred_date" required
                               min="{{ now()->toDateString() }}"
                               class="pr-form-control @error('preferred_date') is-invalid @enderror"
                               value="{{ old('preferred_date') }}">
                        @error('preferred_date')<div class="pr-invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="pr-form-group">
                        <label class="pr-form-label">Reference Photo</label>
                        <input type="file" name="attachment" accept="image/jpeg,image/jpg,image/png"
                               class="pr-form-control @error('attachment') is-invalid @enderror">
                        <div class="pr-form-hint">Optional — JPG or PNG, max 5 MB.</div>
                        @error('attachment')<div class="pr-invalid-feedback">{{ $message }}</div>@enderror
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
@endif

{{-- ============================================================ --}}
{{-- CANCEL RENOVATION MODALS --}}
{{-- ============================================================ --}}
@foreach($requests as $renovation)
    @if($renovation->status === 'Pending')
    <div class="modal fade" id="cancelRenovationModal{{ $renovation->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content pr-modal-content">
                <form method="POST" action="{{ route('tenant.renovations.cancel', $renovation->id) }}">
                    @csrf @method('PATCH')
                    <div class="pr-modal__header pr-modal__header--danger">
                        <div>
                            <div class="pr-modal__eyebrow">Renovation</div>
                            <h5 class="pr-modal__title"><i class="bi bi-x-circle me-2"></i>Cancel Request</h5>
                        </div>
                        <button type="button" class="pr-modal__close" data-bs-dismiss="modal">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                    <div class="pr-modal__body pr-confirm-body">
                        <div class="pr-confirm-icon">⚠️</div>
                        <p style="font-size:.9rem;color:var(--pr-gray-800);margin-bottom:6px;">
                            Cancel this {{ strtolower($renovation->summaryLabel()) }} request?
                        </p>
                        <p style="font-size:.78rem;color:var(--pr-gray-500);margin:0;">
                            The manager will no longer see this request as pending.
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
    @endif
@endforeach

@endsection

@push('scripts')
<script>
/* Reveal the free-text box only when "Others" is the selected type.

   The input is left in the DOM rather than created on the fly so that a
   validation failure can re-render it with old() intact. It is cleared when
   the tenant switches away, otherwise a stale value posts with a type that
   ignores it. */
document.addEventListener('DOMContentLoaded', function () {
    const select = document.getElementById('renovationTypeSelect');
    const group  = document.getElementById('renovationTypeOtherGroup');
    const input  = document.getElementById('renovationTypeOtherInput');

    if (!select || !group || !input) return;

    const OTHERS = @json(\App\Models\RenovationRequest::TYPE_OTHERS);

    function sync() {
        const isOthers = select.value === OTHERS;
        group.style.display = isOthers ? 'block' : 'none';
        input.required = isOthers;
        if (!isOthers) input.value = '';
    }

    select.addEventListener('change', sync);
    sync();
});
</script>
@endpush
