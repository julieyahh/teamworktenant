{{--
    One lease's collections file.

    Rendered by: App\Http\Controllers\Manager\CollectionsController@show
    Route:       GET /manager/collections/{lease}   (manager.collections.show)
    Receives:    $lease, $assessment, $demands, $openBills

    This page IS the evidence file. If a case is ever filed, what matters is
    what is recorded here: what was demanded, when it was served, how, and that
    the notice period ran before anything else happened.
--}}
@extends('layouts.managerdashboardlayout')

@section('title', 'Pinikitan Rental | Collections File')
@section('page-title', 'Collections File')

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

    @if($errors->any())
        <div class="pr-alert pr-alert--error">
            <i class="bi bi-exclamation-octagon-fill pr-alert__icon"></i>
            <div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
        </div>
    @endif

    <div class="pr-dash-header">
        <div class="pr-dash-header__eyebrow">Collections</div>
        <h2 class="pr-dash-header__title">
            {{ trim(($lease->tenant->first_name ?? '') . ' ' . ($lease->tenant->last_name ?? '')) ?: 'Tenant' }}
            — Room {{ $lease->room_no ?? ($lease->unit->room_no ?? 'N/A') }}
        </h2>
        <p class="pr-dash-header__sub">{{ $lease->tenant->email ?? '' }}</p>
    </div>

    {{-- Where things stand --}}
    <div class="pr-card">
        <div class="pr-card__header">
            <h3 class="pr-card__title">Position</h3>
            <span class="pr-badge pr-badge--{{ in_array($assessment['stage'], ['grace_expired','eligible_for_formal_action']) ? 'overdue' : 'unpaid' }}">
                {{ $assessment['label'] }}
            </span>
        </div>
        <div class="pr-card__body">
            <div class="pr-dl">
                <div><div class="pr-dl__label">Rent owed</div><div class="pr-dl__value">PHP {{ number_format($assessment['arrears']['rent'], 2) }}</div></div>
                <div><div class="pr-dl__label">Utilities owed</div><div class="pr-dl__value">PHP {{ number_format($assessment['arrears']['utilities'], 2) }}</div></div>
                <div><div class="pr-dl__label">Other charges</div><div class="pr-dl__value">PHP {{ number_format($assessment['arrears']['penalty'], 2) }}</div></div>
                <div><div class="pr-dl__label">Total</div><div class="pr-dl__value" style="font-weight:700;color:#b91c1c;">PHP {{ number_format($assessment['arrears']['total'], 2) }}</div></div>
                <div><div class="pr-dl__label">Days past due</div><div class="pr-dl__value">{{ $assessment['days_past_due'] }}</div></div>
            </div>

            <div class="pr-alert pr-alert--info" style="margin:20px 0 0;">
                <i class="bi bi-signpost-2 pr-alert__icon"></i>
                <div><strong>Suggested next step:</strong> {{ $assessment['next_step'] }}</div>
            </div>

            @if(!empty($assessment['blockers']))
                <div class="pr-alert pr-alert--warning" style="margin:14px 0 0;">
                    <i class="bi bi-exclamation-triangle-fill pr-alert__icon"></i>
                    <div>
                        <strong>Check these before escalating:</strong>
                        <ul style="margin:6px 0 0;padding-left:18px;">
                            @foreach($assessment['blockers'] as $blocker)
                                <li>{{ $blocker }}</li>
                            @endforeach
                        </ul>
                        <div style="margin-top:6px;font-style:italic;">
                            These are warnings, not blocks — you can still proceed if you have a reason to.
                        </div>
                    </div>
                </div>
            @endif

            @if($assessment['stage'] === 'eligible_for_formal_action')
                <div class="pr-legal" style="margin:14px 0 0;">
                    <strong>This is as far as the system goes.</strong>
                    Everything that can be done administratively has been done and recorded.
                    Filing an unlawful detainer case is a court process — it cannot be started
                    from here, and it should not be started without a lawyer reviewing this file.
                    Do not disconnect utilities or change locks in the meantime.
                </div>
            @endif
        </div>
    </div>

    {{-- What the money is made of --}}
    <div class="pr-card">
        <div class="pr-card__header">
            <h3 class="pr-card__title"><i class="bi bi-receipt me-2" style="color:var(--pr-gold);"></i>Open bills</h3>
            <span class="pr-card__count">{{ $openBills->count() }}</span>
        </div>

        @if($openBills->isEmpty())
            <div class="pr-empty">No open utility bills. Any balance above is rent or penalties.</div>
        @else
            <div class="pr-table-wrap">
                <table class="pr-table">
                    <thead>
                        <tr><th>Type</th><th>Month</th><th>Due</th><th class="num">Due amount</th><th class="num">Paid</th><th class="num">Outstanding</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach($openBills as $bill)
                            <tr>
                                <td>{{ $bill->billing_type }}</td>
                                <td>{{ $bill->billing_month }}</td>
                                <td>{{ $bill->due_date?->format('M d, Y') ?? '—' }}</td>
                                <td class="num">{{ number_format($bill->totalDue(), 2) }}</td>
                                <td class="num">{{ number_format((float) $bill->amount_paid, 2) }}</td>
                                <td class="num" style="font-weight:700;">{{ number_format($bill->outstanding(), 2) }}</td>
                                <td>
                                    <span class="pr-badge pr-badge--{{ $bill->status === 'partial' ? 'partial' : 'unpaid' }}">
                                        {{ $bill->statusLabel() }}
                                    </span>
                                    @if($bill->isUnreconciledEstimate())
                                        <div><span class="pr-badge pr-badge--est">Estimate</span></div>
                                    @endif
                                </td>
                                <td><a href="{{ route('manager.bills.show', $bill->id) }}" class="pr-btn pr-btn--outline pr-btn--sm">Open</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Demand history --}}
    <div class="pr-card">
        <div class="pr-card__header">
            <h3 class="pr-card__title"><i class="bi bi-file-earmark-text me-2" style="color:var(--pr-gold);"></i>Notices issued</h3>
            <span class="pr-card__count">{{ $demands->count() }}</span>
        </div>

        @if($demands->isEmpty())
            <div class="pr-empty">Nothing has been formally demanded yet.</div>
        @else
            <div class="pr-card__body">
                <ul class="pr-timeline">
                    @foreach($demands as $demand)
                        <li>
                            <div class="pr-timeline__when">
                                {{ $demand->issued_at?->format('M d, Y g:i A') }}
                                · issued by {{ trim(($demand->issuedBy->first_name ?? '') . ' ' . ($demand->issuedBy->last_name ?? '')) ?: 'system' }}
                            </div>
                            <div class="pr-timeline__what">
                                {{ $demand->typeLabel() }}
                                — PHP {{ number_format((float) $demand->amount_claimed, 2) }} claimed
                                <span class="pr-badge pr-badge--{{ $demand->isOpen() ? 'unpaid' : 'muted' }}">{{ ucfirst($demand->outcome) }}</span>
                            </div>

                            <div class="pr-timeline__note">
                                @if($demand->isDelivered())
                                    Served {{ $demand->delivered_at->format('M d, Y') }}
                                    via {{ str_replace('_', ' ', $demand->delivery_channel) }}
                                    @if($demand->delivery_reference)
                                        (ref {{ $demand->delivery_reference }})
                                    @endif
                                    · comply by {{ $demand->comply_by?->format('M d, Y') }}
                                    @if($demand->graceHasExpired())
                                        <span class="pr-badge pr-badge--overdue">Period expired</span>
                                    @elseif($demand->isOpen())
                                        <span class="pr-badge pr-badge--partial">{{ max(0, (int) $demand->daysRemaining()) }} day(s) left</span>
                                    @endif
                                @else
                                    <span class="pr-badge pr-badge--overdue">Not yet served</span>
                                    — the notice period has not started.
                                @endif
                            </div>

                            @if($demand->document_path)
                                <div style="margin-top:5px;">
                                    <a href="{{ asset('storage/' . $demand->document_path) }}" target="_blank"
                                       style="font-size:.8rem;">View served copy</a>
                                </div>
                            @endif

                            {{-- Record service --}}
                            @if($demand->isOpen() && !$demand->isDelivered())
                                <form method="POST" action="{{ route('manager.collections.deliver', $demand->id) }}"
                                      enctype="multipart/form-data"
                                      style="margin-top:10px;padding:12px;background:rgba(12,26,58,.03);border-radius:10px;">
                                    @csrf
                                    <div class="pr-grid-2">
                                        <div class="pr-field">
                                            <label>How was it served?</label>
                                            <select name="delivery_channel" required>
                                                @foreach(config('collections.delivery_channels', []) as $channel)
                                                    <option value="{{ $channel }}">{{ ucfirst(str_replace('_', ' ', $channel)) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="pr-field">
                                            <label>When</label>
                                            <input type="datetime-local" name="delivered_at"
                                                   value="{{ now()->format('Y-m-d\TH:i') }}" required>
                                        </div>
                                        <div class="pr-field">
                                            <label>Reference / receipt no.</label>
                                            <input type="text" name="delivery_reference" placeholder="Registry receipt, message ID">
                                            <div class="pr-field__hint">This is the field that proves service.</div>
                                        </div>
                                        <div class="pr-field">
                                            <label>Scan of the served copy</label>
                                            <input type="file" name="document" accept="image/*,.pdf">
                                        </div>
                                    </div>
                                    <button type="submit" class="pr-btn pr-btn--navy pr-btn--sm">Record service</button>
                                </form>
                            @endif

                            {{-- Close it off --}}
                            @if($demand->isOpen())
                                <form method="POST" action="{{ route('manager.collections.close', $demand->id) }}"
                                      style="margin-top:10px;display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                                    @csrf
                                    <div class="pr-field" style="margin-bottom:0;">
                                        <label>Close as</label>
                                        <select name="outcome" required>
                                            <option value="paid">Paid</option>
                                            <option value="arrangement">Payment arrangement agreed</option>
                                            <option value="withdrawn">Withdrawn</option>
                                            <option value="escalated">Referred to counsel</option>
                                        </select>
                                    </div>
                                    <div class="pr-field" style="margin-bottom:0;flex:1;min-width:200px;">
                                        <label>Note</label>
                                        <input type="text" name="notes">
                                    </div>
                                    <button type="submit" class="pr-btn pr-btn--outline pr-btn--sm">Close</button>
                                </form>
                            @endif

                            @if($demand->notes)
                                <div class="pr-timeline__note" style="margin-top:6px;white-space:pre-line;">{{ $demand->notes }}</div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    {{-- Issue a new notice --}}
    <div class="pr-card">
        <div class="pr-card__header">
            <h3 class="pr-card__title"><i class="bi bi-send-plus me-2" style="color:var(--pr-gold);"></i>Issue a notice</h3>
        </div>
        <div class="pr-card__body">
            <div class="pr-alert pr-alert--warning">
                <i class="bi bi-exclamation-triangle-fill pr-alert__icon"></i>
                <div>
                    This records the notice and drafts the wording. <strong>It does not send it.</strong>
                    Send it yourself through a channel you can prove — then come back and record the
                    service details above. A notice the system merely believes was delivered is worth
                    nothing as evidence. Have a lawyer review the wording of a formal demand or a
                    notice to vacate before you use it.
                </div>
            </div>

            <form method="POST" action="{{ route('manager.collections.issue', $lease->id) }}">
                @csrf
                <div class="pr-grid-2">
                    <div class="pr-field">
                        <label>Type</label>
                        <select name="type" required>
                            @foreach(config('collections.demand_sequence', []) as $type)
                                <option value="{{ $type }}">{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pr-field">
                        <label>Grace period (days)</label>
                        <input type="number" name="grace_period_days" min="1" max="120"
                               placeholder="Leave blank for the configured default">
                        <div class="pr-field__hint">
                            Defaults come from <code>config/collections.php</code> and still need
                            a lawyer's confirmation.
                        </div>
                    </div>
                </div>

                <div class="pr-field">
                    <label>Wording (edit before sending)</label>
                    <textarea name="body" rows="10" placeholder="Leave blank to use the generated draft"></textarea>
                </div>

                <div class="pr-field">
                    <label>Internal note</label>
                    <input type="text" name="notes" placeholder="Not shown to the tenant">
                </div>

                <div class="pr-field">
                    <label style="display:flex;align-items:flex-start;gap:8px;text-transform:none;letter-spacing:0;font-size:.86rem;font-weight:500;color:var(--pr-gray-800);">
                        <input type="checkbox" name="acknowledge_review" value="1" required style="width:auto;margin-top:3px;">
                        I have checked the amounts above and reviewed the wording. I understand this
                        records a notice — it does not send it, and it does not authorise any action
                        against the tenant.
                    </label>
                </div>

                <button type="submit" class="pr-btn pr-btn--navy"><i class="bi bi-file-earmark-plus"></i> Record notice</button>
            </form>
        </div>
    </div>

    <a href="{{ route('manager.collections.index') }}" class="pr-btn pr-btn--outline">← Back to collections</a>

@endsection

{{-- v20: the double-submit guard is loaded once by the layout. Including it
     here as well ran it twice: the second copy saw the form already locked
     by the first and cancelled the submit, so nothing on this page saved. --}}
