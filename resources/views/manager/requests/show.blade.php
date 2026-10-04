{{--
    Maintenance request detail page.

    Rendered by: App\Http\Controllers\Manager\MaintenanceRequestController@show
    Route:       GET /manager/requests/{id}   (manager.requests.show)
    Receives:    $request  (App\Models\MaintenanceRequest, with tenant/unit/lease loaded)
--}}
@extends('layouts.managerdashboardlayout')

@section('title', 'Pinikitan Rental | Maintenance Request')
@section('page-title', 'Maintenance Request')

@push('styles')
<style>
    .mr-card {
        background:#fff; border:1px solid #e5e7eb; border-radius:14px;
        padding:24px; box-shadow:0 2px 10px rgba(12,26,58,.06); margin-bottom:20px;
    }
    .mr-card__title {
        font-size:.78rem; letter-spacing:.08em; text-transform:uppercase;
        color:#64748b; font-weight:700; margin-bottom:16px;
    }
    .mr-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:18px; }
    .mr-label { font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:#94a3b8; font-weight:700; }
    .mr-value { font-size:.95rem; color:#0f172a; margin-top:4px; }
    .mr-badge {
        display:inline-block; padding:4px 12px; border-radius:999px;
        font-size:.75rem; font-weight:700;
    }
    .mr-badge--pending  { background:#fef3c7; color:#92400e; }
    .mr-badge--progress { background:#dbeafe; color:#1e40af; }
    .mr-badge--done     { background:#dcfce7; color:#166534; }
    .mr-badge--high     { background:#fee2e2; color:#991b1b; }
    .mr-badge--medium   { background:#fef3c7; color:#92400e; }
    .mr-badge--low      { background:#f1f5f9; color:#475569; }
    .mr-image { max-width:100%; border-radius:12px; border:1px solid #e5e7eb; }
    .mr-actions { display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; }
    .mr-select {
        padding:9px 12px; border:1px solid #cbd5e1; border-radius:10px;
        font-size:.9rem; min-width:180px;
    }
    .mr-btn {
        padding:9px 18px; border-radius:10px; border:none; cursor:pointer;
        font-size:.88rem; font-weight:600; text-decoration:none; display:inline-block;
    }
    .mr-btn--navy    { background:#0c1a3a; color:#fff; }
    .mr-btn--outline { background:#fff; color:#0c1a3a; border:1px solid #cbd5e1; }
</style>
@endpush

@section('content')

    @if(session('success'))
        <div class="mr-card" style="border-color:#bbf7d0;background:#f0fdf4;color:#166534;">
            {{ session('success') }}
        </div>
    @endif

    {{-- v20: errors and validation messages were never shown on this page,
         so a refused save looked exactly like a save that silently failed. --}}
    @if(session('error'))
        <div class="mr-card" style="border-color:#fecaca;background:#fef2f2;color:#991b1b;">
            {{ session('error') }}
        </div>
    @endif
    @if(session('info'))
        <div class="mr-card" style="border-color:#bfdbfe;background:#eff6ff;color:#1e40af;">
            {{ session('info') }}
        </div>
    @endif
    @if($errors->any())
        <div class="mr-card" style="border-color:#fecaca;background:#fef2f2;color:#991b1b;">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    @php
        $statusClass = match(strtolower($request->status ?? '')) {
            'completed'   => 'mr-badge--done',
            'in progress' => 'mr-badge--progress',
            default       => 'mr-badge--pending',
        };
        $urgencyClass = match(strtolower($request->urgency ?? '')) {
            'high'   => 'mr-badge--high',
            'medium' => 'mr-badge--medium',
            default  => 'mr-badge--low',
        };
    @endphp

    {{-- Summary --}}
    <div class="mr-card">
        <div class="mr-card__title">Request #{{ $request->id }}</div>
        <div class="mr-grid">
            <div>
                <div class="mr-label">Tenant</div>
                <div class="mr-value">
                    {{ trim(($request->tenant->first_name ?? '') . ' ' . ($request->tenant->last_name ?? '')) ?: '—' }}
                </div>
            </div>
            <div>
                <div class="mr-label">Email</div>
                <div class="mr-value">{{ $request->tenant->email ?? '—' }}</div>
            </div>
            <div>
                <div class="mr-label">Unit Type</div>
                <div class="mr-value">{{ $request->unit_type ?? $request->unit->type ?? '—' }}</div>
            </div>
            <div>
                <div class="mr-label">Room No.</div>
                <div class="mr-value">{{ $request->room_no ?? $request->unit->room_no ?? '—' }}</div>
            </div>
            <div>
                <div class="mr-label">Urgency</div>
                <div class="mr-value"><span class="mr-badge {{ $urgencyClass }}">{{ $request->urgency ?? '—' }}</span></div>
            </div>
            <div>
                <div class="mr-label">Status</div>
                <div class="mr-value"><span class="mr-badge {{ $statusClass }}">{{ $request->status ?? '—' }}</span></div>
            </div>
            <div>
                <div class="mr-label">Preferred Date</div>
                <div class="mr-value">
                    {{ $request->supposed_date ? \Carbon\Carbon::parse($request->supposed_date)->format('M d, Y') : '—' }}
                </div>
            </div>
            <div>
                <div class="mr-label">Submitted</div>
                <div class="mr-value">{{ $request->created_at?->format('M d, Y g:i A') ?? '—' }}</div>
            </div>
        </div>
    </div>

    {{-- Description --}}
    <div class="mr-card">
        <div class="mr-card__title">Description</div>
        <div class="mr-value" style="white-space:pre-line;">{{ $request->description ?: 'No description provided.' }}</div>
    </div>

    {{-- Photo --}}
    @if($request->issue_image)
        <div class="mr-card">
            <div class="mr-card__title">Attached Photo</div>
            <img src="{{ asset('storage/' . $request->issue_image) }}" alt="Reported issue" class="mr-image">
        </div>
    @endif

    {{-- Update status --}}
    <div class="mr-card">
        <div class="mr-card__title">Update Status</div>
        <form method="POST" action="{{ route('manager.requests.updateStatus', $request->id) }}">
            @csrf
            @method('PATCH')
            <div class="mr-actions" style="margin-bottom:12px;">
                <select name="status" class="mr-select" required>
                    @foreach(['Pending', 'In Progress', 'Completed', 'Cancelled'] as $option)
                        <option value="{{ $option }}" @selected($request->status === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                <input type="text" name="notes" class="mr-select" style="min-width:280px;"
                       placeholder="Note for the history (optional)">
                <button type="submit" class="mr-btn mr-btn--navy">Save Status</button>
                <a href="{{ route('manager.reports.show', 'maintenance-requests') }}" class="mr-btn mr-btn--outline">
                    Back to Requests
                </a>
            </div>
        </form>
    </div>

    {{-- ==========================================================
         Repair details
         ========================================================== --}}
    <div class="mr-card">
        <div class="mr-card__title">Repair Details</div>
        <form method="POST" action="{{ route('manager.requests.details', $request->id) }}">
            @csrf
            <div class="mr-grid">
                <div>
                    <div class="mr-label">Assigned to</div>
                    <input type="text" name="assigned_to" class="mr-select" style="width:100%;"
                           value="{{ old('assigned_to', $request->assigned_to) }}"
                           placeholder="Technician or contractor">
                </div>
                <div>
                    <div class="mr-label">Scheduled date</div>
                    <input type="date" name="scheduled_date" class="mr-select" style="width:100%;"
                           value="{{ old('scheduled_date', $request->scheduled_date?->toDateString()) }}">
                </div>
            </div>

            <div style="margin-top:16px;">
                <div class="mr-label">Diagnosis</div>
                <textarea name="diagnosis" class="mr-select" style="width:100%;min-height:70px;"
                          placeholder="What was actually wrong">{{ old('diagnosis', $request->diagnosis) }}</textarea>
            </div>

            <div style="margin-top:16px;">
                <div class="mr-label">Resolution notes</div>
                <textarea name="resolution_notes" class="mr-select" style="width:100%;min-height:70px;"
                          placeholder="What was done to fix it">{{ old('resolution_notes', $request->resolution_notes) }}</textarea>
            </div>

            <div style="margin-top:16px;">
                <button type="submit" class="mr-btn mr-btn--navy">Save Details</button>
            </div>
        </form>

        <div style="margin-top:16px;font-size:.82rem;color:#64748b;">
            {{-- started_at is set automatically the first time the status
                 moves to In Progress. It was written and never displayed
                 anywhere, which made turnaround unanswerable. --}}
            Started {{ $request->started_at ? $request->started_at->format('M d, Y g:i A') : 'not yet' }}
        </div>

        <div style="margin-top:10px;font-size:.8rem;color:#94a3b8;">
            The tenant sees the scheduled date, who is assigned, the diagnosis and the
            resolution notes on their own copy of this request as soon as they are saved,
            and is notified when the schedule or assignment changes.
        </div>

        @if($request->completed_at)
            <div style="margin-top:16px;font-size:.82rem;color:#64748b;">
                Completed {{ $request->completed_at->format('M d, Y g:i A') }}
                @if($request->turnaroundDays() !== null)
                    · turnaround {{ $request->turnaroundDays() }} day(s)
                @endif
            </div>
        @endif
    </div>

    {{-- ==========================================================
         Labour and materials
         ========================================================== --}}
    <div class="mr-card">
        <div class="mr-card__title">Labour &amp; Materials</div>

        @if($request->isCharged())
            <div style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:.85rem;">
                This repair was charged to the tenant on {{ $request->charged_at->format('M d, Y') }}.
                The costs are locked — the tenant has already been billed this figure.
            </div>
        @endif

        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:8px 12px;border-bottom:1px solid #e5e7eb;font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;">Type</th>
                        <th style="text-align:left;padding:8px 12px;border-bottom:1px solid #e5e7eb;font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;">Description</th>
                        <th style="text-align:right;padding:8px 12px;border-bottom:1px solid #e5e7eb;font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;">Qty</th>
                        <th style="text-align:right;padding:8px 12px;border-bottom:1px solid #e5e7eb;font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;">Unit cost</th>
                        <th style="text-align:right;padding:8px 12px;border-bottom:1px solid #e5e7eb;font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;">Total</th>
                        <th style="border-bottom:1px solid #e5e7eb;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($request->workItems as $item)
                        <tr>
                            <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;">
                                <span class="mr-badge {{ $item->type === 'labor' ? 'mr-badge--progress' : 'mr-badge--low' }}">
                                    {{ ucfirst($item->type) }}
                                </span>
                            </td>
                            <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;">
                                {{ $item->description }}
                                @if($item->performed_by)
                                    <div style="font-size:.75rem;color:#94a3b8;">by {{ $item->performed_by }}</div>
                                @endif
                                @if($item->receipt_path)
                                    <div style="font-size:.75rem;">
                                        <a href="{{ asset('storage/' . $item->receipt_path) }}" target="_blank">receipt</a>
                                    </div>
                                @endif
                            </td>
                            <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;text-align:right;">
                                {{ rtrim(rtrim((string) $item->quantity, '0'), '.') }} {{ $item->unit }}
                            </td>
                            <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;text-align:right;">
                                {{ number_format((float) $item->unit_cost, 2) }}
                            </td>
                            <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;text-align:right;font-weight:600;">
                                {{ number_format((float) $item->line_total, 2) }}
                            </td>
                            <td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;text-align:right;">
                                @unless($request->isCharged())
                                    <form method="POST"
                                          action="{{ route('manager.requests.workItems.destroy', [$request->id, $item->id]) }}"
                                          onsubmit="return confirm('Remove this line?');" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="mr-btn mr-btn--outline"
                                                style="padding:4px 10px;font-size:.75rem;color:#b91c1c;">Remove</button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding:26px;text-align:center;color:#94a3b8;">
                                No costs recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="padding:10px 12px;text-align:right;color:#64748b;">Labour</td>
                        <td style="padding:10px 12px;text-align:right;">{{ number_format((float) $request->labor_cost, 2) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="4" style="padding:10px 12px;text-align:right;color:#64748b;">Materials</td>
                        <td style="padding:10px 12px;text-align:right;">{{ number_format((float) $request->material_cost, 2) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="4" style="padding:10px 12px;text-align:right;font-weight:700;">Total repair cost</td>
                        <td style="padding:10px 12px;text-align:right;font-weight:700;">
                            PHP {{ number_format((float) $request->total_cost, 2) }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @unless($request->isCharged())
            <form method="POST" action="{{ route('manager.requests.workItems.store', $request->id) }}"
                  enctype="multipart/form-data" style="margin-top:20px;padding-top:20px;border-top:1px solid #e5e7eb;">
                @csrf
                <div class="mr-grid">
                    <div>
                        <div class="mr-label">Type</div>
                        <select name="type" class="mr-select" style="width:100%;" required>
                            <option value="labor">Labour</option>
                            <option value="material">Material</option>
                        </select>
                    </div>
                    <div>
                        <div class="mr-label">Description</div>
                        <input type="text" name="description" class="mr-select" style="width:100%;"
                               placeholder="e.g. Replace flush valve" required>
                    </div>
                    <div>
                        <div class="mr-label">Quantity</div>
                        <input type="number" name="quantity" class="mr-select" style="width:100%;"
                               step="0.01" min="0.01" value="1" required>
                    </div>
                    <div>
                        <div class="mr-label">Unit</div>
                        <input type="text" name="unit" class="mr-select" style="width:100%;" placeholder="hrs, pcs, m">
                    </div>
                    <div>
                        <div class="mr-label">Unit cost (PHP)</div>
                        <input type="number" name="unit_cost" class="mr-select" style="width:100%;"
                               step="0.01" min="0" required>
                    </div>
                    <div>
                        <div class="mr-label">Performed by</div>
                        <input type="text" name="performed_by" class="mr-select" style="width:100%;">
                    </div>
                    <div>
                        <div class="mr-label">Performed on</div>
                        <input type="date" name="performed_on" class="mr-select" style="width:100%;">
                    </div>
                    <div>
                        <div class="mr-label">Receipt photo</div>
                        <input type="file" name="receipt" class="mr-select" style="width:100%;" accept="image/*">
                    </div>
                </div>
                <div style="margin-top:16px;">
                    <button type="submit" class="mr-btn mr-btn--navy">Add Line</button>
                </div>
            </form>
        @endunless
    </div>

    {{-- ==========================================================
         Who pays
         ========================================================== --}}
    <div class="mr-card">
        <div class="mr-card__title">Who Pays</div>

        @if($request->needsResponsibilityDecision())
            <div style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:.85rem;">
                <strong>This needs a decision.</strong>
                PHP {{ number_format((float) $request->total_cost, 2) }} of costs are recorded and
                nobody has been assigned to pay them. Nothing defaults — a repair sits here until
                someone decides, so it does not get quietly absorbed or quietly billed.
            </div>
        @endif

        <div class="mr-grid" style="margin-bottom:18px;">
            <div>
                <div class="mr-label">Current setting</div>
                <div class="mr-value">{{ $request->responsibilityLabel() }}</div>
            </div>
            <div>
                <div class="mr-label">Tenant share</div>
                <div class="mr-value">PHP {{ number_format((float) $request->tenant_charge_amount, 2) }}</div>
            </div>
            <div>
                <div class="mr-label">Landlord share</div>
                <div class="mr-value">PHP {{ number_format($request->landlordShare(), 2) }}</div>
            </div>
            <div>
                <div class="mr-label">Billed?</div>
                <div class="mr-value">
                    @if($request->isCharged())
                        <span class="mr-badge mr-badge--done">Charged {{ $request->charged_at->format('M d, Y') }}</span>
                        @if($chargeBill = $request->currentBill())
                            <div style="font-size:.78rem;margin-top:4px;">
                                <a href="{{ route('manager.bills.show', $chargeBill->id) }}">Bill #{{ $chargeBill->id }}</a>
                                — void it to reverse the charge
                            </div>
                        @endif
                    @else
                        <span class="mr-badge mr-badge--pending">Not yet</span>
                    @endif
                </div>
            </div>
        </div>

        @if($request->responsibility_reason)
            <div style="margin-bottom:18px;">
                <div class="mr-label">Reason on record</div>
                <div class="mr-value" style="white-space:pre-line;">{{ $request->responsibility_reason }}</div>
            </div>
        @endif

        @unless($request->isCharged())
            <form method="POST" action="{{ route('manager.requests.responsibility', $request->id) }}"
                  style="padding-top:18px;border-top:1px solid #e5e7eb;">
                @csrf
                <div class="mr-grid">
                    <div>
                        <div class="mr-label">Responsibility</div>
                        <select name="cost_responsibility" class="mr-select" style="width:100%;" required
                                onchange="document.getElementById('mr-share').style.display = this.value === 'shared' ? '' : 'none';">
                            <option value="undetermined" @selected($request->cost_responsibility === 'undetermined')>Not yet decided</option>
                            <option value="landlord" @selected($request->cost_responsibility === 'landlord')>Landlord pays</option>
                            <option value="tenant"   @selected($request->cost_responsibility === 'tenant')>Tenant pays</option>
                            <option value="shared"   @selected($request->cost_responsibility === 'shared')>Shared</option>
                        </select>
                    </div>
                    <div id="mr-share" style="{{ $request->cost_responsibility === 'shared' ? '' : 'display:none;' }}">
                        <div class="mr-label">Tenant share (%)</div>
                        <input type="number" name="tenant_share_percent" class="mr-select" style="width:100%;"
                               min="1" max="99" step="0.01"
                               value="{{ old('tenant_share_percent', $request->tenant_share_percent) }}">
                    </div>
                </div>

                <div style="margin-top:16px;">
                    <div class="mr-label">Reason (required if the tenant pays any of it)</div>
                    <textarea name="responsibility_reason" class="mr-select" style="width:100%;min-height:70px;"
                              placeholder="e.g. Damage caused by a guest, per the tenant's own account in the description">{{ old('responsibility_reason', $request->responsibility_reason) }}</textarea>
                    <div style="font-size:.78rem;color:#94a3b8;margin-top:5px;">
                        This is what the tenant sees if they query the charge. "The manager ticked
                        tenant" is not an answer; a reason is.
                    </div>
                </div>

                <div style="margin-top:16px;">
                    <button type="submit" class="mr-btn mr-btn--navy">Save Responsibility</button>
                </div>
            </form>

            @if($request->isBillableToTenant())
                <form method="POST" action="{{ route('manager.requests.charge', $request->id) }}"
                      style="margin-top:20px;padding-top:20px;border-top:1px solid #e5e7eb;"
                      onsubmit="return confirm('Charge PHP {{ number_format((float) $request->tenant_charge_amount, 2) }} to the tenant?');">
                    @csrf
                    <div class="mr-label">Bill the tenant's share</div>
                    <div style="font-size:.85rem;color:#64748b;margin:6px 0 12px;">
                        Posts PHP {{ number_format((float) $request->tenant_charge_amount, 2) }} as its own bill,
                        not as rent. It gets its own due date and status, the tenant can pay it in parts
                        like anything else, and it reads as a repair on their ledger rather than as
                        unexplained rent.
                    </div>
                    <div class="mr-actions">
                        <input type="date" name="due_date" class="mr-select"
                               value="{{ \App\Support\RentCalendar::nextDueDate(now())->toDateString() }}">
                        <button type="submit" class="mr-btn mr-btn--navy">Charge Tenant</button>
                    </div>
                </form>
            @endif
        @endunless
    </div>

    {{-- ==========================================================
         Repair history
         ========================================================== --}}
    <div class="mr-card">
        <div class="mr-card__title">History</div>

        @if($request->statusLogs->isEmpty())
            <div style="padding:20px;text-align:center;color:#94a3b8;font-size:.85rem;">
                No status changes recorded yet. Entries appear here from the next change onward.
            </div>
        @else
            <ul style="list-style:none;margin:0;padding:0;">
                @foreach($request->statusLogs as $log)
                    <li style="padding:0 0 14px 20px;border-left:2px solid #e5e7eb;position:relative;">
                        <span style="position:absolute;left:-6px;top:4px;width:10px;height:10px;border-radius:50%;background:#c9a84c;"></span>
                        <div style="font-size:.75rem;color:#94a3b8;">
                            {{ $log->created_at?->format('M d, Y g:i A') }}
                            · {{ trim(($log->changedBy->first_name ?? '') . ' ' . ($log->changedBy->last_name ?? '')) ?: 'system' }}
                        </div>
                        <div style="font-size:.88rem;color:#0f172a;font-weight:600;">
                            {{ $log->from_status ?? 'Created' }} → {{ $log->to_status }}
                        </div>
                        @if($log->notes)
                            <div style="font-size:.82rem;color:#64748b;margin-top:2px;">{{ $log->notes }}</div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

@endsection

{{-- v20: the double-submit guard is loaded once by the layout. Including it
     here as well ran it twice: the second copy saw the form already locked
     by the first and cancelled the submit, so nothing on this page saved. --}}
