<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lease Summary Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; margin: 20px; }
        h2 { text-align: center; margin-bottom: 10px; color: #b38f00; }
        p { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background-color: #fff3cd; color: #856404; }
        tr:nth-child(even) { background-color: #fffbea; }
        .badge { padding: 3px 6px; border-radius: 6px; color: #fff; font-size: 11px; }
        .active { background-color: #28a745; }
        .terminated { background-color: #dc3545; }
        .pending { background-color: #6c757d; }
        .header-info { margin-bottom: 15px; }
    </style>
</head>
<body>
    <h2>Active Lease Summary Report</h2>
    <p><strong>Generated:</strong> {{ now()->format('Y-m-d H:i:s') }}</p>
    <p><strong>Total Active Leases:</strong> {{ $total ?? $data->count() }}</p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Tenant Name</th>
                <th>Email</th>
                <th>Unit Type</th>
                <th>Room No.</th>
                <th>Lease Start</th>
                <th>Lease End</th>
                <th>Monthly Rent</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data as $lease)
                @php
                    $app = $lease->unit;
                    $statusClass = match($lease->lea_status ?? 'active') {
                        'active' => 'active',
                        'terminated' => 'terminated',
                        default => 'pending',
                    };

                    $unitType = $app->type ?? 'N/A';
                    $roomNo = $lease->room_no ?? 'N/A';
                    $bedNumber = $lease->bed_number ?? null;

                    if ($unitType === 'Bed-Spacer' && $bedNumber) {
                        $unitTypeDisplay = $unitType . ' - ' . $roomNo . ' - Bed ' . $bedNumber;
                    } elseif ($roomNo !== 'N/A') {
                        $unitTypeDisplay = $unitType . ' - ' . $roomNo;
                    } else {
                        $unitTypeDisplay = $unitType;
                    }
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $lease->user->first_name }} {{ $lease->user->last_name }}</td>
                    <td>{{ $lease->user->email }}</td>
                    <td>{{ $unitTypeDisplay }}</td>
                    <td>{{ $roomNo }}</td>
                    <td>{{ \Carbon\Carbon::parse($lease->lea_start_date ?? $lease->lea_start ?? now())->format('Y-m-d') }}</td>
                    <td>{{ \Carbon\Carbon::parse($lease->lea_end_date ?? $lease->lea_end ?? now())->format('Y-m-d') }}</td>
                    <td>{{ number_format($lease->rent_amount ?? 0, 2) }}</td>
                    <td><span class="badge {{ $statusClass }}">{{ ucfirst($lease->lea_status ?? 'Active') }}</span></td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">No active leases found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
