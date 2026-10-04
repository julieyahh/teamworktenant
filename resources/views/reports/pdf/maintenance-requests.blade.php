<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Maintenance Requests Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
        h2 { text-align: center; margin-bottom: 20px; color: #b38f00; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background-color: #fff3cd; color: #856404; }
        tr:nth-child(even) { background-color: #fffbea; }
        .badge { padding: 3px 6px; border-radius: 6px; color: #fff; }
        .high { background-color: #dc3545; }
        .mid { background-color: #ffc107; color: #212529; }
        .low { background-color: #28a745; }
        .pending { background-color: #6c757d; }
        .accepted { background-color: #28a745; }
        .rejected { background-color: #dc3545; }
    </style>
</head>
<body>
    <h2>Maintenance Requests Report</h2>
    <p><strong>Generated:</strong> {{ now()->format('Y-m-d H:i:s') }}</p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date Filed</th>
                <th>Unit Type</th>
                <th>Request</th>
                <th>Urgency</th>
                <th>Supposed Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($requests as $request)
            @php
                $unitType = $request->unit_type ?? null;
                $roomNo = $request->room_no ?? null;
                $bedNumber = null;
                
                // Try to get bed number from tenant's lease if available
                if ($request->tenant) {
                    $lease = $request->tenant->leases->first();
                    $bedNumber = $lease->bed_number ?? null;
                }
                
                // Format unit type with room number and bed number (if Bed-Spacer)
                if ($unitType && $roomNo) {
                    if ($unitType === 'Bed-Spacer' && $bedNumber) {
                        $unitTypeDisplay = $unitType . ' - ' . $roomNo . ' - Bed ' . $bedNumber;
                    } else {
                        $unitTypeDisplay = $unitType . ' - ' . $roomNo;
                    }
                } elseif ($unitType) {
                    $unitTypeDisplay = $unitType;
                } else {
                    $unitTypeDisplay = '-';
                }
                
                $urgencyClass = match($request->urgency) {
                    'high' => 'high',
                    'mid' => 'mid',
                    default => 'low',
                };
                $statusClass = match($request->status) {
                    'Pending' => 'pending',
                    'Accepted' => 'accepted',
                    'Rejected' => 'rejected',
                    default => 'pending',
                };
            @endphp
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ \Carbon\Carbon::parse($request->created_at)->format('Y-m-d') }}</td>
                <td>{{ $unitTypeDisplay }}</td>
                <td>{{ ucfirst($request->description) }}</td>
                <td><span class="badge {{ $urgencyClass }}">{{ ucfirst($request->urgency) }}</span></td>
                <td>{{ \Carbon\Carbon::parse($request->supposed_date)->format('Y-m-d') }}</td>
                <td><span class="badge {{ $statusClass }}">{{ ucfirst($request->status) }}</span></td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
