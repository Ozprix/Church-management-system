<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Donation Receipt</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1f2937;
            line-height: 1.5;
        }
        .header {
            border-bottom: 2px solid #10b981;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }
        .header h1 {
            font-size: 20px;
            margin: 0;
            color: #047857;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .details-table th,
        .details-table td {
            text-align: left;
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        .summary {
            margin-top: 20px;
            padding: 12px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
        }
        .footer {
            margin-top: 30px;
            font-size: 10px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $tenant->name ?? 'Donation Receipt' }}</h1>
        <p>
            Receipt #: {{ $donation->receipt_number ?? 'N/A' }}<br>
            Date: {{ optional($donation->received_at)->format('F j, Y g:i a') }}
        </p>
    </div>

    <p>
        Thank you @if($member) {{ $member->first_name }} {{ $member->last_name }} @else valued supporter @endif for your generous gift.
    </p>

    <table class="details-table">
        <tr>
            <th>Amount</th>
            <td>{{ number_format((float) $donation->amount, 2) }} {{ strtoupper($donation->currency ?? 'USD') }}</td>
        </tr>
        <tr>
            <th>Status</th>
            <td>{{ ucfirst($donation->status) }}</td>
        </tr>
        @if($donation->provider)
            <tr>
                <th>Payment Method</th>
                <td>{{ ucfirst($donation->provider) }} @if($donation->provider_reference) ({{ $donation->provider_reference }}) @endif</td>
            </tr>
        @endif
        @if($donation->items && $donation->items->count())
            <tr>
                <th>Designation</th>
                <td>
                    <ul style="margin: 0; padding-left: 16px;">
                        @foreach($donation->items as $item)
                            <li>
                                {{ $item->fund?->name ?? 'General Fund' }} - {{ number_format((float) $item->amount, 2) }} {{ strtoupper($donation->currency ?? 'USD') }}
                            </li>
                        @endforeach
                    </ul>
                </td>
            </tr>
        @endif
    </table>

    <div class="summary">
        This receipt acknowledges that no goods or services were provided in exchange for this contribution, unless otherwise noted. Please retain this document for your records.
    </div>

    <div class="footer">
        {{ $tenant->name ?? 'Our Church' }}<br>
        {{ $tenant->domain ?? '' }}<br>
        Generated {{ now()->format('F j, Y g:i a') }}
    </div>
</body>
</html>
