<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tenant Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; margin: 20px; }
        h2, h3 { text-align: center; margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
        th { background-color: #f2f2f2; }
        .section-title { background: #007bff; color: white; padding: 8px; border-radius: 5px; margin-bottom: 8px; }
        .no-data { text-align: center; color: #777; font-style: italic; }
    </style>
</head>
<body>
    <h2>Tenant Report</h2>
    <p><strong>Generated:</strong> {{ now()->format('Y-m-d H:i:s') }}</p>


    {{-- APPROVED TENANTS --}}
    <div class="section">
        <h3
            class="section-title"
            style="background: {{ $title === 'Inactive Tenants' ? '#dc3545' : '#28a745' }};"
        >
            {{ $title }}
        </h3>
        @if($approvedTenants->isNotEmpty())
        <table>
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Contact Number</th>
                    <th>Unit Type</th>
                    <th>Employment Status</th>
                    <th>Source of Income</th>
                    <th>Emergency Name</th>
                    <th>Emergency Number</th>
                    <th>Lease Start</th>
                    <th>Lease End</th>
                </tr>
            </thead>
            <tbody>
                @foreach($approvedTenants as $tenant)
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
                    @endphp
                    <tr>
                        <td>{{ $tenant->name }}</td>
                        <td>{{ $tenant->email }}</td>
                        <td>{{ $app->contact_number ?? 'N/A' }}</td>
                        <td>{{ $unitTypeDisplay }}</td>
                        <td>{{ $app->employment_status ?? 'N/A' }}</td>
                        <td>{{ $app->source_of_income ?? 'N/A' }}</td>
                        <td>{{ $app->emergency_name ?? 'N/A' }}</td>
                        <td>{{ $app->emergency_number ?? 'N/A' }}</td>
                        <td>{{ $lease?->lea_start_date ?? 'N/A' }}</td>
                        <td>{{ $lease?->lea_end_date ?? 'N/A' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p class="no-data">No approved tenants found.</p>
        @endif
    </div>
</body>
</html>
