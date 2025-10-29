<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Report - {{ $gathering->name }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
            color: #1f2937;
        }

        h1 {
            font-size: 20px;
            margin-bottom: 4px;
        }

        .meta {
            margin-bottom: 16px;
            font-size: 12px;
            color: #4b5563;
        }

        .summary {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
        }

        .summary div {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #f9fafb;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 8px;
            border: 1px solid #d1d5db;
            text-align: left;
        }

        th {
            background: #f3f4f6;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <h1>{{ $gathering->name ?? 'Gathering' }}</h1>
    <div class="meta">
        <div><strong>Date:</strong> {{ optional($gathering->starts_at)->toDayDateTimeString() ?? 'TBD' }}</div>
        @if ($gathering->service?->name)
            <div><strong>Service:</strong> {{ $gathering->service->name }}</div>
        @endif
        @if ($gathering->location)
            <div><strong>Location:</strong> {{ $gathering->location }}</div>
        @endif
    </div>

    <div class="summary">
        <div>
            <strong>Present:</strong>
            <div>{{ $summary['present'] ?? 0 }}</div>
        </div>
        <div>
            <strong>Absent:</strong>
            <div>{{ $summary['absent'] ?? 0 }}</div>
        </div>
        <div>
            <strong>Excused:</strong>
            <div>{{ $summary['excused'] ?? 0 }}</div>
        </div>
        <div>
            <strong>Attendance rate:</strong>
            <div>{{ $summary['attendance_rate'] ?? 0 }}%</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Member</th>
                <th>Status</th>
                <th>Checked in at</th>
                <th>Checked out at</th>
                <th>Check-in method</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['member_name'] }}</td>
                    <td>{{ $row['status_label'] }}</td>
                    <td>{{ $row['checked_in_at'] ?? '—' }}</td>
                    <td>{{ $row['checked_out_at'] ?? '—' }}</td>
                    <td>{{ $row['check_in_method'] ?? '—' }}</td>
                    <td>{{ $row['notes'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No attendance recorded for this gathering.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
