<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Renewal History Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
        h2 { text-align: center; margin-bottom: 6px; color: #b38f00; }
        .meta { text-align: center; font-size: 10px; color: #666; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: center; }
        th { background-color: #fff3cd; color: #856404; }
        tr:nth-child(even) { background-color: #fffbea; }
        .left { text-align: left; }
        .money { text-align: right; white-space: nowrap; }
        .up { color: #b45309; font-weight: bold; }
        .badge { padding: 2px 6px; border-radius: 6px; color: #fff; font-size: 9px; }
        .manual { background-color: #0c1a3a; }
        .scheduled { background-color: #0d9488; }
        .legacy { background-color: #6c757d; }
        .totals { margin-top: 14px; font-size: 11px; }
        .note { margin-top: 14px; font-size: 9px; color: #777; font-style: italic; }
    </style>
</head>
<body>
    <h2>Renewal History Report</h2>
    <div class="meta">
        Generated: {{ now()->format('Y-m-d H:i:s') }}
        @if(!empty($search)) &middot; Filter: "{{ $search }}" @endif
        &middot; {{ $renewals->count() }} renewal(s)
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Renewed On</th>
                <th>Tenant</th>
                <th>Unit</th>
                <th>Renewal No.</th>
                <th>Rent Before</th>
                <th>Rent After</th>
                <th>Increase</th>
                <th>New Term</th>
                <th>Source</th>
            </tr>
        </thead>
        <tbody>
            @php $totalIncrease = 0; @endphp
            @foreach($renewals as $renewal)
                @php
                    $totalIncrease += (float) $renewal->increase_amount;

                    $tenantName = $renewal->tenant?->name
                        ?? $renewal->tenant?->tenantApplication?->full_name
                        ?? 'Unknown tenant';

                    $unitType = $renewal->unit?->type ?? $renewal->lease?->unit?->type ?? null;
                    $roomNo   = $renewal->room_no ?? $renewal->lease?->room_no ?? null;
                    $unitText = $unitType && $roomNo ? "$unitType - $roomNo" : ($roomNo ?? $unitType ?? 'N/A');

                    $sourceClass = match($renewal->source) {
                        'scheduled' => 'scheduled',
                        'legacy'    => 'legacy',
                        default     => 'manual',
                    };
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $renewal->created_at->format('Y-m-d') }}</td>
                    <td class="left">{{ $tenantName }}</td>
                    <td class="left">{{ $unitText }}</td>
                    <td>{{ $renewal->renewal_no }}</td>
                    <td class="money">PHP {{ number_format((float) $renewal->previous_rent, 2) }}</td>
                    <td class="money">PHP {{ number_format((float) $renewal->new_rent, 2) }}</td>
                    <td class="money up">
                        +PHP {{ number_format((float) $renewal->increase_amount, 2) }}
                        ({{ $renewal->increasePercent() }})
                    </td>
                    <td>
                        {{ $renewal->new_start_date?->format('Y-m-d') ?? '-' }}
                        to
                        {{ $renewal->new_end_date?->format('Y-m-d') ?? '-' }}
                    </td>
                    <td><span class="badge {{ $sourceClass }}">{{ $renewal->sourceLabel() }}</span></td>
                </tr>
            @endforeach

            @if($renewals->isEmpty())
                <tr><td colspan="10">No renewals recorded.</td></tr>
            @endif
        </tbody>
    </table>

    @if($renewals->isNotEmpty())
        <div class="totals">
            <strong>Total monthly rent added by these renewals:</strong>
            PHP {{ number_format($totalIncrease, 2) }}
        </div>
    @endif

    @if($renewals->contains(fn($r) => $r->source === 'legacy'))
        <div class="note">
            Rows marked "Pre-existing" were reconstructed when renewal history was introduced.
            Their rent figures are derived from the current rate rather than recorded at the time.
        </div>
    @endif
</body>
</html>
