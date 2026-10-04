<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment History Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        h2 { text-align: center; }
    </style>
</head>
<body>
    <h2>Payment History Report</h2>
    <p><strong>Generated:</strong> {{ now()->format('Y-m-d H:i:s') }}</p>
    @if(!empty($dateRangeLabel))
        <p><strong>Date range:</strong> {{ $dateRangeLabel }} &middot; {{ $payments->count() }} payment(s)</p>
    @endif
    <table>
        <thead>
            <tr>
                <th>Reference #</th>
                <th>Tenant</th>
                <th>Unit</th>
                <th>Amount</th>
                <th>Date</th>
                <th>Purpose</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $payment)
            <tr>
                <td>{{ $payment->reference_number }}</td>
                <td>{{ $payment->tenant->name ?? 'N/A' }}</td>
                <td>
                    @if($payment->lease && $payment->lease->unit)
                        {{ $payment->lease->unit->room_no ?? 'N/A' }}
                    @else
                        N/A
                    @endif
                </td>
                <td>{{ number_format($payment->pay_amount, 2) }}</td>
                <td>{{ $payment->pay_date?->format('Y-m-d') ?? 'N/A' }}</td>
                <td>{{ ucfirst($payment->payment_for) }}</td>
                <td>{{ $payment->pay_status }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
