<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tenant Feedback Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
        h2 { text-align: center; margin-bottom: 6px; color: #a07c2a; }
        .meta { text-align: center; font-size: 10px; color: #666; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: center; vertical-align: top; }
        th { background-color: #fdf6e3; color: #92722a; }
        tr:nth-child(even) { background-color: #fffdf7; }
        .left { text-align: left; }
        .badge { padding: 2px 6px; border-radius: 6px; color: #fff; font-size: 9px; }
        .new      { background-color: #d97706; }
        .reviewed { background-color: #16a34a; }
        .response { font-size: 9px; color: #666; font-style: italic; }
        .stars { color: #c9a84c; letter-spacing: 1px; }
    </style>
</head>
<body>
    <h2>Tenant Feedback Report</h2>
    <div class="meta">
        Generated: {{ now()->format('Y-m-d H:i:s') }}
        @if(!empty($search)) &middot; Filter: "{{ $search }}" @endif
        &middot; {{ $feedback->count() }} submission(s)
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Tenant</th>
                <th>Category</th>
                <th>Rating</th>
                <th>Feedback</th>
                <th>Status</th>
                <th>Responded</th>
            </tr>
        </thead>
        <tbody>
            @foreach($feedback as $item)
                @php
                    $tenantName  = $item->tenant?->name ?? 'Unknown tenant';
                    $statusClass = $item->status === 'Reviewed' ? 'reviewed' : 'new';
                @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->created_at->format('Y-m-d') }}</td>
                    <td class="left">{{ $tenantName }}</td>
                    <td class="left">{{ $item->category }}</td>
                    <td>
                        @if($item->rating)
                            <span class="stars">{{ str_repeat('★', $item->rating) }}{{ str_repeat('☆', 5 - $item->rating) }}</span>
                        @else
                            -
                        @endif
                    </td>
                    <td class="left">
                        <strong>{{ $item->subject }}</strong><br>
                        {{ $item->message }}
                        @if($item->manager_response)
                            <div class="response">Response: {{ $item->manager_response }}</div>
                        @endif
                    </td>
                    <td><span class="badge {{ $statusClass }}">{{ $item->status }}</span></td>
                    <td>
                        {{ $item->responded_at ? $item->responded_at->format('Y-m-d') : '-' }}
                        @if($item->respondedBy)
                            <div class="response">{{ $item->respondedBy->name }}</div>
                        @endif
                    </td>
                </tr>
            @endforeach

            @if($feedback->isEmpty())
                <tr><td colspan="8">No feedback found.</td></tr>
            @endif
        </tbody>
    </table>
</body>
</html>
