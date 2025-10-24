@extends('reports.finance.layouts.branded')

@section('content')
    <h2>Donation totals</h2>
    <div class="section-subtitle">
        Reporting period
        @if(!empty($filters['from'])) from {{ \Illuminate\Support\Carbon::parse($filters['from'])->format('F j, Y') }} @endif
        @if(!empty($filters['to'])) to {{ \Illuminate\Support\Carbon::parse($filters['to'])->format('F j, Y') }} @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Received on</th>
                <th>Donor</th>
                <th>Funds</th>
                <th style="text-align: right;">Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        @forelse($donations as $donation)
            <tr>
                <td>{{ optional($donation->received_at)->format('M j, Y') ?? '—' }}</td>
                <td>{{ $donation->member?->full_name ?? 'Guest donor' }}</td>
                <td>
                    @if($donation->items && $donation->items->isNotEmpty())
                        @foreach($donation->items as $item)
                            <div>{{ $item->fund?->name ?? 'Unassigned' }} — {{ number_format($item->amount, 2) }}</div>
                        @endforeach
                    @else
                        Unassigned
                    @endif
                </td>
                <td style="text-align: right;">{{ number_format($donation->amount, 2) }} {{ $donation->currency }}</td>
                <td><span class="badge">{{ $donation->status }}</span></td>
            </tr>
        @empty
            <tr>
                <td colspan="5" style="text-align: center;">No donations found for this period.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
@endsection
