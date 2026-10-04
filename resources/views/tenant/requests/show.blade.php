{{--
    Tenant view of one maintenance request.

    Route: GET /tenant/requests/{id}   (tenant.requests.show)
    Controller: Tenant\TenantRequestController@show

    This is the tenant-facing half of the repair record. The manager's
    equivalent is manager/requests/show.blade.php, which has all the same
    fields plus the editing forms.

    Cost figures render only when $showCosts is true — see the controller for
    why. Do not "simplify" that by always showing total_cost: when the landlord
    is paying, what the repair cost the landlord is not the tenant's business.
--}}
@extends('layouts.tenantdashboardlayout')

@section('title', 'Request #' . $request->id)
@section('page-title', 'Maintenance Request')

@push('styles')
<style>
    .mrq-back { display:inline-flex; align-items:center; gap:6px; font-size:.82rem; font-weight:600; color:var(--pr-gray-500); text-decoration:none; margin-bottom:16px; transition:color .2s; }
    .mrq-back:hover { color:var(--pr-navy); }

    .mrq-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:22px; }
    .mrq-head__eyebrow { font-size:.72rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:var(--pr-gold); margin-bottom:6px; }
    .mrq-head__title { font-family:var(--pr-font-display); font-size:1.45rem; font-weight:700; color:var(--pr-navy); margin:0 0 4px; line-height:1.2; }
    .mrq-head__sub { font-size:.85rem; color:var(--pr-gray-500); margin:0; }

    .mrq-card { background:var(--pr-white); border:1px solid rgba(12,26,58,.07); border-radius:16px; box-shadow:0 2px 12px rgba(12,26,58,.06); padding:22px 24px; margin-bottom:20px; }
    .mrq-card__title { font-family:var(--pr-font-display); font-size:.95rem; font-weight:700; color:var(--pr-navy); margin:0 0 16px; display:flex; align-items:center; gap:8px; }
    .mrq-card__title i { color:var(--pr-gold); font-size:.9rem; }

    .mrq-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:18px 24px; }
    .mrq-label { font-size:.68rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--pr-gray-500); margin-bottom:4px; }
    .mrq-value { font-size:.9rem; color:var(--pr-gray-800); font-weight:600; }
    .mrq-value--muted { font-weight:400; color:var(--pr-gray-500); font-style:italic; }
    .mrq-prose { font-size:.88rem; color:var(--pr-gray-800); white-space:pre-line; line-height:1.6; }

    .mrq-pill { display:inline-flex; align-items:center; gap:5px; padding:3px 11px; border-radius:999px; font-size:.73rem; font-weight:700; }
    .mrq-pill::before { content:''; width:5px; height:5px; border-radius:50%; background:currentColor; }
    .mrq-pill--green { background:#f0fdf4; color:#16a34a; }
    .mrq-pill--amber { background:#fffbeb; color:#d97706; }
    .mrq-pill--red   { background:#fef2f2; color:#dc2626; }
    .mrq-pill--gray  { background:#f3f4f6; color:#6b7280; }
    .mrq-pill--blue  { background:#eff6ff; color:#2563eb; }

    .mrq-image { max-width:100%; border-radius:12px; border:1px solid rgba(12,26,58,.08); }

    /* Timeline */
    .mrq-timeline { list-style:none; padding:0; margin:0; }
    .mrq-timeline li { position:relative; padding:0 0 22px 26px; border-left:2px solid rgba(12,26,58,.08); }
    .mrq-timeline li:last-child { padding-bottom:0; border-left-color:transparent; }
    .mrq-timeline li::before { content:''; position:absolute; left:-7px; top:2px; width:12px; height:12px; border-radius:50%; background:var(--pr-gray-200); border:2px solid var(--pr-white); }
    .mrq-timeline li.is-done::before { background:var(--pr-gold); }
    .mrq-timeline__when { font-size:.72rem; color:var(--pr-gray-500); margin-bottom:2px; }
    .mrq-timeline__what { font-size:.88rem; font-weight:600; color:var(--pr-navy); }
    .mrq-timeline__note { font-size:.8rem; color:var(--pr-gray-500); margin-top:2px; }

    /* Cost table */
    .mrq-table { width:100%; border-collapse:collapse; font-size:.85rem; }
    .mrq-table thead th { text-align:left; padding:9px 12px; font-size:.67rem; font-weight:700; letter-spacing:.09em; text-transform:uppercase; color:var(--pr-gray-500); border-bottom:1px solid rgba(12,26,58,.08); background:rgba(12,26,58,.02); }
    .mrq-table tbody td { padding:11px 12px; border-bottom:1px solid rgba(12,26,58,.04); color:var(--pr-gray-800); }
    .mrq-table .num { text-align:right; }

    .mrq-notice { display:flex; align-items:flex-start; gap:10px; padding:13px 16px; border-radius:11px; font-size:.85rem; line-height:1.55; margin-bottom:16px; }
    .mrq-notice--info  { background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; }
    .mrq-notice--warn  { background:#fffbeb; border:1px solid #fde68a; color:#92400e; }
    .mrq-notice--good  { background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; }
</style>
@endpush

@section('content')

@if(session('success'))
    <x-alert type="success">{{ session('success') }}</x-alert>
@endif
@if(session('error'))
    <x-alert type="error">{{ session('error') }}</x-alert>
@endif

<a href="{{ route('tenant.requests') }}" class="mrq-back">
    <i class="bi bi-arrow-left"></i> Back to my requests
</a>

@php
    $statPill = match($request->status) {
        'Pending'     => 'mrq-pill--amber',
        'In Progress' => 'mrq-pill--blue',
        'Completed'   => 'mrq-pill--green',
        'Cancelled'   => 'mrq-pill--gray',
        default       => 'mrq-pill--gray',
    };
    $urgPill = match($request->urgency) {
        'high' => 'mrq-pill--red',
        'mid'  => 'mrq-pill--amber',
        default => 'mrq-pill--green',
    };
@endphp

<div class="mrq-head">
    <div>
        <div class="mrq-head__eyebrow">Request #{{ $request->id }}</div>
        <h2 class="mrq-head__title">{{ \Illuminate\Support\Str::limit($request->description, 70) }}</h2>
        <p class="mrq-head__sub">
            Filed {{ $request->created_at?->format('M d, Y') }}
            · {{ $request->unit_type ?? 'Unit' }} {{ $request->room_no ? '· Room ' . $request->room_no : '' }}
        </p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <span class="mrq-pill {{ $urgPill }}">{{ ucfirst($request->urgency) }} urgency</span>
        <span class="mrq-pill {{ $statPill }}">{{ $request->status }}</span>
    </div>
</div>

{{-- What is happening now, in one line, so the tenant does not have to read
     the whole page to answer "is anyone coming?" --}}
@if($request->status === 'Completed')
    <div class="mrq-notice mrq-notice--good">
        <i class="bi bi-check-circle-fill"></i>
        <div>
            This repair was completed
            @if($request->completed_at) on {{ $request->completed_at->format('M d, Y') }} @endif.
            @if($request->turnaroundDays() !== null)
                It took {{ $request->turnaroundDays() }} day{{ $request->turnaroundDays() === 1 ? '' : 's' }} from start to finish.
            @endif
        </div>
    </div>
@elseif($request->scheduled_date)
    <div class="mrq-notice mrq-notice--info">
        <i class="bi bi-calendar-check-fill"></i>
        <div>
            Booked for <strong>{{ \Carbon\Carbon::parse($request->scheduled_date)->format('l, M d, Y') }}</strong>@if($request->assigned_to), with {{ $request->assigned_to }}@endif.
            Please make sure someone can let them into the unit.
        </div>
    </div>
@elseif($request->status === 'Pending')
    <div class="mrq-notice mrq-notice--warn">
        <i class="bi bi-hourglass-split"></i>
        <div>
            This has been received and is waiting to be scheduled. You will be
            notified here and on your notifications page once a date is set.
        </div>
    </div>
@endif

{{-- ============================================================
     What you reported
     ============================================================ --}}
<div class="mrq-card">
    <h3 class="mrq-card__title"><i class="bi bi-chat-left-text-fill"></i> What You Reported</h3>

    <div class="mrq-prose" style="margin-bottom:18px;">{{ $request->description }}</div>

    <div class="mrq-grid" style="margin-bottom:18px;">
        <div>
            <div class="mrq-label">Date Filed</div>
            <div class="mrq-value">{{ $request->created_at?->format('M d, Y g:i A') ?? '—' }}</div>
        </div>
        <div>
            <div class="mrq-label">Your Preferred Date</div>
            <div class="mrq-value">
                {{ $request->supposed_date ? \Carbon\Carbon::parse($request->supposed_date)->format('M d, Y') : '—' }}
            </div>
        </div>
        <div>
            <div class="mrq-label">Unit</div>
            <div class="mrq-value">
                {{ $request->unit_type ?? 'Unit' }}
                @if($request->room_no) · {{ $request->room_no }} @endif
                @if($request->lease?->bed_number) · Bed {{ $request->lease->bed_number }} @endif
            </div>
        </div>
    </div>

    @if($request->issue_image)
        <div>
            <div class="mrq-label" style="margin-bottom:8px;">Photo You Attached</div>
            <img src="{{ asset('storage/' . $request->issue_image) }}" alt="The issue you reported" class="mrq-image">
        </div>
    @endif
</div>

{{-- ============================================================
     The work
     ============================================================ --}}
<div class="mrq-card">
    <h3 class="mrq-card__title"><i class="bi bi-tools"></i> The Work</h3>

    <div class="mrq-grid">
        <div>
            <div class="mrq-label">Assigned To</div>
            <div class="mrq-value {{ $request->assigned_to ? '' : 'mrq-value--muted' }}">
                {{ $request->assigned_to ?: 'Not assigned yet' }}
            </div>
        </div>
        <div>
            <div class="mrq-label">Scheduled For</div>
            <div class="mrq-value {{ $request->scheduled_date ? '' : 'mrq-value--muted' }}">
                {{ $request->scheduled_date ? \Carbon\Carbon::parse($request->scheduled_date)->format('M d, Y') : 'Not scheduled yet' }}
            </div>
        </div>
        <div>
            <div class="mrq-label">Work Started</div>
            <div class="mrq-value {{ $request->started_at ? '' : 'mrq-value--muted' }}">
                {{ $request->started_at?->format('M d, Y g:i A') ?: 'Not started' }}
            </div>
        </div>
        <div>
            <div class="mrq-label">Completed</div>
            <div class="mrq-value {{ $request->completed_at ? '' : 'mrq-value--muted' }}">
                {{ $request->completed_at?->format('M d, Y g:i A') ?: 'Not completed' }}
            </div>
        </div>
    </div>

    @if($request->diagnosis)
        <div style="margin-top:20px;">
            <div class="mrq-label" style="margin-bottom:6px;">What Was Wrong</div>
            <div class="mrq-prose">{{ $request->diagnosis }}</div>
        </div>
    @endif

    @if($request->resolution_notes)
        <div style="margin-top:20px;">
            <div class="mrq-label" style="margin-bottom:6px;">What Was Done</div>
            <div class="mrq-prose">{{ $request->resolution_notes }}</div>
        </div>
    @endif

    @if(!$request->diagnosis && !$request->resolution_notes && $request->status !== 'Cancelled')
        <div style="margin-top:18px;font-size:.83rem;color:var(--pr-gray-500);font-style:italic;">
            Findings and repair notes will appear here once the work is under way.
        </div>
    @endif
</div>

{{-- ============================================================
     Cost — only when the tenant is carrying some of it
     ============================================================ --}}
@if($showCosts)
    <div class="mrq-card">
        <h3 class="mrq-card__title"><i class="bi bi-receipt"></i> Cost &amp; Who Pays</h3>

        @if($request->isCharged())
            <div class="mrq-notice mrq-notice--info">
                <i class="bi bi-info-circle-fill"></i>
                <div>
                    This was billed to you on {{ $request->charged_at->format('M d, Y') }} as a
                    <strong>Repair</strong> bill. It appears on your
                    <a href="{{ route('tenant.payments') }}" style="color:inherit;text-decoration:underline;">Payments page</a>
                    and can be paid in full or in parts, like any other bill.
                </div>
            </div>
        @else
            <div class="mrq-notice mrq-notice--warn">
                <i class="bi bi-hourglass-split"></i>
                <div>
                    This amount has been worked out but not yet billed to you. Nothing
                    is due until it appears on your Payments page.
                </div>
            </div>
        @endif

        <div class="mrq-grid" style="margin-bottom:18px;">
            <div>
                <div class="mrq-label">Total Repair Cost</div>
                <div class="mrq-value">&#8369;{{ number_format((float) $request->total_cost, 2) }}</div>
            </div>
            <div>
                <div class="mrq-label">Responsibility</div>
                <div class="mrq-value">{{ $request->responsibilityLabel() }}</div>
            </div>
            <div>
                <div class="mrq-label">Your Share</div>
                <div class="mrq-value" style="color:#b45309;">
                    &#8369;{{ number_format((float) $request->tenant_charge_amount, 2) }}
                </div>
            </div>
        </div>

        @if($request->responsibility_reason)
            <div style="margin-bottom:18px;">
                <div class="mrq-label" style="margin-bottom:6px;">Why You Are Being Charged</div>
                <div class="mrq-prose">{{ $request->responsibility_reason }}</div>
            </div>
        @endif

        {{-- Itemised on purpose. "PHP 1,800" invites an argument;
             "2 hrs @ 450 + 1 flush valve @ 900" does not. --}}
        @if($request->workItems->isNotEmpty())
            <div class="mrq-label" style="margin-bottom:8px;">Breakdown</div>
            <div style="overflow-x:auto;">
                <table class="mrq-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Description</th>
                            <th class="num">Qty</th>
                            <th class="num">Unit Cost</th>
                            <th class="num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($request->workItems as $item)
                            <tr>
                                <td>
                                    <span class="mrq-pill {{ $item->type === 'labor' ? 'mrq-pill--blue' : 'mrq-pill--gray' }}">
                                        {{ ucfirst($item->type) }}
                                    </span>
                                </td>
                                <td>{{ $item->description }}</td>
                                <td class="num">{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }}{{ $item->unit ? ' ' . $item->unit : '' }}</td>
                                <td class="num">&#8369;{{ number_format((float) $item->unit_cost, 2) }}</td>
                                <td class="num" style="font-weight:700;">&#8369;{{ number_format((float) $item->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@elseif((float) $request->total_cost > 0 && $request->cost_responsibility === 'landlord')
    <div class="mrq-card">
        <h3 class="mrq-card__title"><i class="bi bi-receipt"></i> Cost</h3>
        <div class="mrq-notice mrq-notice--good" style="margin-bottom:0;">
            <i class="bi bi-check-circle-fill"></i>
            <div>There is no charge to you for this repair. It is covered by the property.</div>
        </div>
    </div>
@endif

{{-- ============================================================
     History
     ============================================================ --}}
<div class="mrq-card">
    <h3 class="mrq-card__title"><i class="bi bi-clock-history"></i> History</h3>

    <ul class="mrq-timeline">
        <li class="is-done">
            <div class="mrq-timeline__when">{{ $request->created_at?->format('M d, Y g:i A') }}</div>
            <div class="mrq-timeline__what">You submitted this request</div>
        </li>

        @foreach($request->statusLogs as $log)
            <li class="is-done">
                <div class="mrq-timeline__when">{{ $log->created_at?->format('M d, Y g:i A') }}</div>
                <div class="mrq-timeline__what">
                    {{ $log->from_status && $log->from_status !== $log->to_status
                        ? $log->from_status . ' → ' . $log->to_status
                        : $log->to_status }}
                </div>
                @if($log->notes)
                    <div class="mrq-timeline__note">{{ $log->notes }}</div>
                @endif
            </li>
        @endforeach

        @if($request->statusLogs->isEmpty())
            <li>
                <div class="mrq-timeline__what mrq-value--muted">No updates yet.</div>
            </li>
        @endif
    </ul>
</div>

@if(in_array($request->status, ['Pending', 'In Progress']))
    <form action="{{ route('tenant.requests.cancel', $request->id) }}" method="POST"
          onsubmit="return confirm('Cancel this maintenance request? This cannot be undone.');">
        @csrf @method('PATCH')
        <button type="submit"
                style="display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:10px;font-size:.85rem;font-weight:600;cursor:pointer;background:transparent;border:1.5px solid rgba(239,68,68,.3);color:#dc2626;">
            <i class="bi bi-x-circle"></i> Cancel This Request
        </button>
    </form>
@endif

@endsection
