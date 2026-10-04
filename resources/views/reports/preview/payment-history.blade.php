@extends('layouts.managerdashboardlayout')

@section('title', 'Payment History Preview')
@section('page-title', 'Payment History PDF Preview')

@section('content')
<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-secondary">
            <i class="bi bi-file-earmark-pdf me-2"></i> Payment History Report
        </h4>
        <a href="{{ route('manager.reports.export', ['report' => $report]) }}" 
           class="btn btn-success btn-sm">
            <i class="bi bi-download me-1"></i> Download PDF
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-secondary">
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
                    @forelse($payments as $payment)
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
                        <td>₱{{ number_format($payment->pay_amount, 2) }}</td>
                        <td>{{ $payment->pay_date?->format('Y-m-d') ?? 'N/A' }}</td>
                        <td>{{ ucfirst($payment->payment_for) }}</td>
                        <td>{{ $payment->pay_status }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-3">No records found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
