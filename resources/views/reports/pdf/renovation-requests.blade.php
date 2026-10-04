<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Renovation Requests Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
        h2 { text-align: center; margin-bottom: 6px; color: #7c3aed; }
        .meta { text-align: center; font-size: 10px; color: #666; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: center; vertical-align: top; }
        th { background-color: #ede9fe; color: #5b21b6; }
        tr:nth-child(even) { background-color: #faf8ff; }
        .left { text-align: left; }
        .money { text-align: right; white-space: nowrap; }
        .badge { padding: 2px 6px; border-radius: 6px; color: #fff; font-size: 9px; }
        .approved { background-color: #16a34a; }
        .denied { background-color: #dc2626; }
        .pending { background-color: #d97706; }
        .cancelled { background-color: #6c757d; }
        .remarks { font-size: 9px; color: #666; font-style: italic; }
        .totals { margin-top: 14px; font-size: 11px; }
    </style>
</head>
<body>
    <h2>Renovation Requests Report</h2>
    <div class="meta">
        Generated: {{ now()->format('Y-m-d H:i:s') }}
        @if(!empty($search)) &middot; Filter: "{{ $search }}" @endif
        &middot; {{ $requests->count() }} request(s)
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Submitted</th>
                <th>Tenant</th>
                <th>Room</th>
                <th>Type</th>
                <th>Request</th>
                <th>Est. Cost</th>
                <th>Preferred Date</th>
                <th>Status</th>
                <th>Decided</th>
            </tr>
        </thead>
        <tbody>
            @php $estTotal = 0; @endphp
            @foreach($requests as $renovation)
                @php
                    $estTotal += (float) ($renovation->estimated_cost ?? 0);

                    $tenantName = $renovation->tenant?->name ?? 'Unknown tenant';
                    $roomNo     = $renovation->room_no ?? $renovation->lease?->room_no ?? '-';

                    $statusClass = match($renovation->status) {
                        'Approved'  => 'approved',
                        'Denied'    => 'denied',
                        'Cancelled' => 'cancelled',
                        default     => 'pending',
                    };
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $renovation->created_at->format('Y-m-d') }}</td>
                    <td class="left">{{ $tenantName }}</td>
                    <td>{{ $roomNo }}</td>
                    <td class="left">{{ $renovation->renovation_type ?? '-' }}</td>
                    <td class="left">
                        {{-- Older requests still carry a title; newer ones lead
                             with the description. --}}
                        @if($renovation->title)
                            <strong>{{ $renovation->title }}</strong><br>
                        @endif
                        {{ $renovation->description }}
                        @if($renovation->manager_remarks)
                            <div class="remarks">Remarks: {{ $renovation->manager_remarks }}</div>
                        @endif
                    </td>
                    <td class="money">
                        {{ $renovation->estimated_cost ? 'PHP '.number_format($renovation->estimated_cost, 2) : '-' }}
                    </td>
                    <td>{{ $renovation->preferred_date ? $renovation->preferred_date->format('Y-m-d') : '-' }}</td>
                    <td><span class="badge {{ $statusClass }}">{{ $renovation->status }}</span></td>
                    <td>
                        {{ $renovation->decided_at ? $renovation->decided_at->format('Y-m-d') : '-' }}
                        @if($renovation->decidedBy)
                            <div class="remarks">{{ $renovation->decidedBy->name }}</div>
                        @endif
                    </td>
                </tr>
            @endforeach

            @if($requests->isEmpty())
                <tr><td colspan="10">No renovation requests found.</td></tr>
            @endif
        </tbody>
    </table>

    @if($requests->isNotEmpty())
        <div class="totals">
            <strong>Total estimated cost across these requests:</strong>
            PHP {{ number_format($estTotal, 2) }}
            <div class="remarks">
                Estimates are the tenants' own figures and are not binding.
            </div>
        </div>
    @endif
</body>
</html>
