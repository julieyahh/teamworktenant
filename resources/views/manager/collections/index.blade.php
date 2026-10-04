{{--
    Arrears list.

    Rendered by: App\Http\Controllers\Manager\CollectionsController@index
    Route:       GET /manager/collections   (manager.collections.index)
    Receives:    $cases (lease + assessment), $totals

    Read the banner before changing anything on this screen. Nothing here acts
    on a tenant — it surfaces a stage and a suggested next step, and a person
    decides. There is deliberately no button that evicts, terminates a lease,
    disables a login or cuts a utility.
--}}
@extends('layouts.managerdashboardlayout')

@section('title', 'Pinikitan Rental | Collections')
@section('page-title', 'Collections')

@push('styles')
    @include('partials.manager-ui')
@endpush

@section('content')

    @foreach(['success', 'error', 'info'] as $key)
        @if(session($key))
            <div class="pr-alert pr-alert--{{ $key === 'error' ? 'error' : ($key === 'info' ? 'info' : 'success') }}">
                <i class="bi bi-info-circle-fill pr-alert__icon"></i>
                <div>{{ session($key) }}</div>
            </div>
        @endif
    @endforeach

    <div class="pr-dash-header">
        <div class="pr-dash-header__eyebrow">Manager</div>
        <h2 class="pr-dash-header__title">Collections</h2>
        <p class="pr-dash-header__sub">Who is behind, by how much, and what has been formally demanded.</p>
    </div>

    <div class="pr-legal">
        <strong>This screen builds a record. It does not enforce anything.</strong><br>
        The stages below are thresholds you configured, not legal determinations. Non-payment
        generally requires written demand, a notice period, and a court filing before a tenant
        can be removed — and cutting utilities or changing locks to force payment is generally
        unlawful no matter how far behind someone is. The system has no feature that does any of
        those, on purpose. Its job is to show that proper notice was given, and when.<br>
    </div>

    <div class="pr-stats">
        <div class="pr-stat">
            <div class="pr-stat__label">Leases in arrears</div>
            <div class="pr-stat__value">{{ $totals['cases'] }}</div>
        </div>
        <div class="pr-stat">
            <div class="pr-stat__label">Total owed</div>
            <div class="pr-stat__value pr-stat__value--danger">PHP {{ number_format((float) $totals['arrears'], 2) }}</div>
        </div>
        <div class="pr-stat">
            <div class="pr-stat__label">Notices not yet served</div>
            <div class="pr-stat__value {{ $totals['needs_serve'] > 0 ? 'pr-stat__value--warn' : '' }}">
                {{ $totals['needs_serve'] }}
            </div>
            <div class="pr-stat__note">
                {{ $totals['needs_serve'] > 0
                    ? 'The notice period does not start until service is recorded'
                    : 'Every open notice has a delivery record' }}
            </div>
        </div>
    </div>

    <div class="pr-card">
        <div class="pr-card__header">
            <h3 class="pr-card__title"><i class="bi bi-exclamation-diamond me-2" style="color:var(--pr-gold);"></i>Cases</h3>
            <div style="display:flex;gap:8px;align-items:center;">
                <form method="GET" action="{{ route('manager.collections.index') }}" style="margin:0;">
                    <select name="stage" onchange="this.form.submit()"
                            style="padding:6px 10px;border:1px solid #cbd5e1;border-radius:8px;font-size:.8rem;">
                        <option value="">All stages</option>
                        @foreach(['overdue', 'demand_due', 'grace_expired', 'eligible_for_formal_action'] as $stage)
                            <option value="{{ $stage }}" @selected(request('stage') === $stage)>
                                {{ config("collections.stages.{$stage}.label", $stage) }}
                            </option>
                        @endforeach
                    </select>
                </form>
                <a href="{{ route('manager.collections.export', ['stage' => request('stage')]) }}"
                   class="pr-btn pr-btn--outline pr-btn--sm">
                    <i class="bi bi-file-earmark-pdf"></i> Export PDF
                </a>
            </div>
        </div>

        <div class="pr-table-wrap">
            <table class="pr-table">
                <thead>
                    <tr>
                        <th>Tenant / Room</th>
                        <th class="num">Rent</th>
                        <th class="num">Utilities</th>
                        <th class="num">Other</th>
                        <th class="num">Total</th>
                        <th class="num">Days late</th>
                        <th>Stage</th>
                        <th>Suggested next step</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cases as $case)
                        @php
                            $lease = $case['lease'];
                            $a     = $case['assessment'];
                            $badge = match($a['stage']) {
                                'eligible_for_formal_action' => 'overdue',
                                'grace_expired'              => 'overdue',
                                'demand_due'                 => 'unpaid',
                                default                      => 'muted',
                            };
                        @endphp
                        <tr>
                            <td>
                                <div style="font-weight:600;">
                                    {{ trim(($lease->tenant->first_name ?? '') . ' ' . ($lease->tenant->last_name ?? '')) ?: '—' }}
                                </div>
                                <div style="font-size:.76rem;color:var(--pr-gray-500);">
                                    Room {{ $lease->room_no ?? ($lease->unit->room_no ?? 'N/A') }}
                                </div>
                            </td>
                            <td class="num">{{ number_format($a['arrears']['rent'], 2) }}</td>
                            <td class="num">{{ number_format($a['arrears']['utilities'], 2) }}</td>
                            <td class="num">{{ number_format($a['arrears']['penalty'], 2) }}</td>
                            <td class="num" style="font-weight:700;">{{ number_format($a['arrears']['total'], 2) }}</td>
                            <td class="num">{{ $a['days_past_due'] }}</td>
                            <td>
                                <span class="pr-badge pr-badge--{{ $badge }}">{{ $a['label'] }}</span>
                                @if(!empty($a['blockers']))
                                    <div style="margin-top:3px;">
                                        <span class="pr-badge pr-badge--est" title="{{ implode(' ', $a['blockers']) }}">
                                            {{ count($a['blockers']) }} thing(s) to check
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td style="white-space:normal;font-size:.82rem;color:var(--pr-gray-500);max-width:280px;">
                                {{ $a['next_step'] }}
                            </td>
                            <td>
                                <a href="{{ route('manager.collections.show', $lease->id) }}"
                                   class="pr-btn pr-btn--outline pr-btn--sm">Open file</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="pr-empty">Nobody is in arrears. Good.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
