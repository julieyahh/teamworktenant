<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $payment->invoiceNumber() }}</title>
    {{-- dompdf: DejaVu Sans is bundled and has the glyphs we need. Amounts are
         written as "PHP" rather than the peso sign so they print everywhere. --}}
    <style>
        @page { margin: 34px 40px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #1f2937; }
        .top { width: 100%; border-collapse: collapse; }
        .top td { vertical-align: top; }
        .brand { font-size: 20px; font-weight: bold; color: #0c1a3a; }
        .brand-sub { color: #6b7280; font-size: 10px; line-height: 1.5; margin-top: 4px; }
        .doc { text-align: right; }
        .doc-title { font-size: 22px; font-weight: bold; color: #0c1a3a; letter-spacing: 2px; }
        .doc-no { font-size: 11px; color: #374151; margin-top: 2px; }
        .paid { display: inline-block; margin-top: 8px; padding: 4px 12px; border: 2px solid #16a34a; color: #16a34a; font-weight: bold; letter-spacing: 3px; font-size: 12px; }
        .rule { border-top: 3px solid #c9a84c; margin: 16px 0 14px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .meta td { vertical-align: top; width: 50%; padding-right: 16px; }
        .label { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; margin-bottom: 3px; }
        .value { font-size: 11px; line-height: 1.55; }
        .kv { width: 100%; border-collapse: collapse; }
        .kv td { padding: 2px 0; font-size: 10.5px; }
        .kv td.k { color: #6b7280; width: 42%; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.items th { background: #0c1a3a; color: #fff; text-align: left; font-size: 9.5px; text-transform: uppercase; letter-spacing: .8px; padding: 7px 9px; }
        table.items th.num, table.items td.num { text-align: right; }
        table.items td { padding: 8px 9px; border-bottom: 1px solid #e5e7eb; }
        .detail { color: #6b7280; font-size: 9.5px; margin-top: 2px; }
        .totals { width: 46%; margin-left: 54%; border-collapse: collapse; margin-top: 10px; }
        .totals td { padding: 5px 9px; }
        .totals .grand td { border-top: 2px solid #0c1a3a; font-size: 13px; font-weight: bold; color: #0c1a3a; }
        .footer { margin-top: 30px; border-top: 1px solid #e5e7eb; padding-top: 10px; color: #6b7280; font-size: 9.5px; line-height: 1.5; }
    </style>
</head>
<body>

@php
    $tenant = $payment->tenant;
    $lease  = $payment->lease;
    $issued = $payment->invoiceDate();
@endphp

<table class="top">
    <tr>
        <td>
            <div class="brand">{{ $business['business_name'] ?? 'Pinikitan Rental' }}</div>
            <div class="brand-sub">
                @if(!empty($business['address'])){{ $business['address'] }}<br>@endif
                @if(!empty($business['phone']))Tel: {{ $business['phone'] }}@endif
                @if(!empty($business['phone']) && !empty($business['email'])) &middot; @endif
                @if(!empty($business['email'])){{ $business['email'] }}@endif
                @if(!empty($business['tin']))<br>TIN: {{ $business['tin'] }}@endif
            </div>
        </td>
        <td class="doc">
            <div class="doc-title">INVOICE</div>
            <div class="doc-no">{{ $payment->invoiceNumber() }}</div>
            <div class="paid">PAID</div>
        </td>
    </tr>
</table>

<div class="rule"></div>

<table class="meta">
    <tr>
        <td>
            <div class="label">Billed to</div>
            <div class="value">
                <strong>{{ $tenant->name ?? 'Tenant' }}</strong><br>
                @if($tenant?->email){{ $tenant->email }}<br>@endif
                @if($tenant?->contact_number){{ $tenant->contact_number }}<br>@endif
                @if($lease)
                    Room {{ $lease->room_no ?? 'N/A' }}{{ $lease->unit?->type ? ' · ' . $lease->unit->type : '' }}{{ $lease->bed_number ? ' · Bed ' . $lease->bed_number : '' }}
                @endif
            </div>
        </td>
        <td>
            <table class="kv">
                <tr><td class="k">Invoice date</td><td>{{ $issued?->format('F d, Y') }}</td></tr>
                <tr><td class="k">Payment submitted</td><td>{{ \Carbon\Carbon::parse($payment->pay_date ?? $payment->created_at)->format('F d, Y') }}</td></tr>
                <tr><td class="k">Reference no.</td><td>{{ $payment->reference_number ?? '—' }}</td></tr>
                <tr><td class="k">Payment method</td><td>{{ $payment->pay_method ?? '—' }}</td></tr>
                @if($payment->account_no)
                    <tr><td class="k">Account / GCash no.</td><td>{{ $payment->account_no }}</td></tr>
                @endif
                <tr><td class="k">Payment for</td><td>{{ $payment->payment_for }}{{ $payment->utility_type ? ' — ' . $payment->utility_type : '' }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th style="width:6%;">#</th>
            <th>Description</th>
            <th class="num" style="width:24%;">Amount (PHP)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($invoice['lines'] as $i => $line)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>
                    {{ $line['description'] }}
                    @if(!empty($line['detail']))<div class="detail">{{ $line['detail'] }}</div>@endif
                </td>
                <td class="num">{{ number_format($line['amount'], 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr class="grand">
        <td>Total paid</td>
        <td style="text-align:right;">PHP {{ number_format($invoice['total'], 2) }}</td>
    </tr>
    <tr>
        <td style="color:#6b7280;">Balance on this invoice</td>
        <td style="text-align:right;color:#16a34a;font-weight:bold;">PHP 0.00</td>
    </tr>
</table>

<div class="footer">
    Payment accepted by the office on {{ $issued?->format('F d, Y \a\t h:i A') }}.
    For questions about this invoice, quote {{ $payment->invoiceNumber() }} or reference {{ $payment->reference_number }}.<br>
    {{ $business['footer'] ?? '' }}
</div>

</body>
</html>
