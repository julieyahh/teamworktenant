<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tenant Report ({{ ucfirst($filter) }})</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        h2 {
            text-align: center;
            margin-bottom: 5px;
        }
        p {
            font-size: 11px;
            margin-top: 0;
            color: #555;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 6px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }
        td.status {
            font-weight: bold;
            text-align: center;
        }
        .status-approved { background-color: #d4edda; color: #155724; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        .no-data {
            text-align: center;
            color: #777;
            font-style: italic;
        }
    </style>
</head>
<body>
    <h2>Tenant Report - {{ ucfirst($filter) }}</h2>
    <p><strong>Generated:</strong> {{ $generatedAt }}</p>

    @if($tenants->isNotEmpty())
        <table>
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Unit Type</th>
                    <th>Employment Status</th>
                    <th>Source of Income</th>
                    <th>Emergency Contact</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tenants as $tenant)
                    @php
                        $app = $tenant->tenantApplication;
                        $lease = $tenant->leases->first();
                        $unitType = $app->unit_type ?? 'N/A';

                        // Get room number and bed number from lease if available, otherwise from application
                        $roomNo = $lease->room_no ?? $app->room_no ?? 'N/A';
                        $bedNumber = $lease->bed_number ?? $app->bed_number ?? null;

                        // Format unit type with room number and bed number (if Bed-Spacer)
                        if ($unitType === 'Bed-Spacer' && $bedNumber) {
                            $unitTypeDisplay = $unitType . ' - ' . $roomNo . ' - Bed ' . $bedNumber;
                        } elseif ($roomNo !== 'N/A') {
                            $unitTypeDisplay = $unitType . ' - ' . $roomNo;
                        } else {
                            $unitTypeDisplay = $unitType;
                        }

                        $statusClass = match($tenant->status) {
                            'approved' => 'status-approved',
                            'pending' => 'status-pending',
                            'rejected' => 'status-rejected',
                            default => ''
                        };
                    @endphp
                    <tr>
                        <td>{{ $tenant->name }}</td>
                        <td>{{ $tenant->email }}</td>
                        <td>{{ $unitTypeDisplay }}</td>
                        <td>{{ $app->employment_status ?? 'N/A' }}</td>
                        <td>{{ $app->source_of_income ?? 'N/A' }}</td>
                        <td>{{ $app->emergency_name ?? 'N/A' }} ({{ $app->emergency_number ?? 'N/A' }})</td>
                        <td class="status {{ $statusClass }}">{{ ucfirst($tenant->status) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="no-data">No tenants found for this filter.</p>
    @endif
</body>
</html>
