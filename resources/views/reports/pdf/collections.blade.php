<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Collections Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
        h2 { text-align: center; margin-bottom: 6px; color: #0c1a3a; }
        .meta { text-align: center; font-size: 10px; color: #666; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: center; vertical-align: top; }
        th { background-color: #eef1f8; color: #0c1a3a; }
        tr:nth-child(even) { background-color: #fafbfd; }
        .left { text-align: left; }
        .num  { text-align: right; }
        .total-row td { font-weight: bold; background-color: #f3f4f6; }
        .badge { padding: 2px 6px; border-radius: 6px; color: #fff; font-size: 9px; }
        .stage-demand_due                  { background-color: #d97706; }
        .stage-grace_expired                { background-color: #dc2626; }
        .stage-eligible_for_formal_action   { background-color: #7c2d12; }
        .stage-overdue                       { background-color: #b45309; }
    </style>
</head>
<body>
    <h2>Collections Report</h2>
    <div class="meta">
        Generated: {{ now()->format('Y-m-d H:i:s') }}
        @if(!empty($stage)) &middot; Stage: "{{ config("collections.stages.{$stage}.label", $stage) }}" @endif
        &middot; {{ $cases->count() }} case(s) &middot; Total owed: PHP {{ number_format($total, 2) }}
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Tenant</th>
                <th>Room</th>
                <th>Rent</th>
                <th>Utilities</th>
                <th>Other</th>
                <th>Total</th>
                <th>Days Late</th>
                <th>Stage</th>
                <th>Suggested Next Step</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cases as $case)
                @php
                    $lease = $case['lease'];
                    $a     = $case['assessment'];
                    $name  = trim(($lease->tenant->first_name ?? '') . ' ' . ($lease->tenant->last_name ?? '')) ?: '—';
                    $room  = $lease->room_no ?? ($lease->unit->room_no ?? 'N/A');
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td class="left">{{ $name }}</td>
                    <td>{{ $room }}</td>
                    <td class="num">{{ number_format($a['arrears']['rent'], 2) }}</td>
                    <td class="num">{{ number_format($a['arrears']['utilities'], 2) }}</td>
                    <td class="num">{{ number_format($a['arrears']['penalty'], 2) }}</td>
                    <td class="num">{{ number_format($a['arrears']['total'], 2) }}</td>
                    <td>{{ $a['days_past_due'] }}</td>
                    <td><span class="badge stage-{{ $a['stage'] }}">{{ $a['label'] }}</span></td>
                    <td class="left">{{ $a['next_step'] }}</td>
                </tr>
            @endforeach

            @if($cases->isEmpty())
                <tr><td colspan="10">No cases in arrears.</td></tr>
            @else
                <tr class="total-row">
                    <td colspan="6" class="left">Total</td>
                    <td class="num">{{ number_format($total, 2) }}</td>
                    <td colspan="3"></td>
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
