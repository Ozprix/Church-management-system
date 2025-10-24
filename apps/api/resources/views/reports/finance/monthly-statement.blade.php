@extends('reports.finance.layouts.branded')

@section('content')
    <h2>Monthly activity</h2>
    <div class="section-subtitle">
        Period: {{ $date_range[0]->format('F j, Y') }} &mdash; {{ $date_range[1]->format('F j, Y') }}
    </div>

    <h3 style="margin-top: 16px; font-size: 14px;">Daily totals</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
        @forelse($daily_totals as $day => $amount)
            <tr>
                <td>{{ \Illuminate\Support\Carbon::parse($day)->format('M j, Y') }}</td>
                <td style="text-align: right;">{{ number_format($amount, 2) }} {{ $donations->first()->currency ?? 'USD' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="2" style="text-align: center;">No giving activity recorded in this period.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <h3 style="margin-top: 24px; font-size: 14px;">Totals by fund</h3>
    <table>
        <thead>
            <tr>
                <th>Fund</th>
                <th style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
        @forelse($fund_totals as $fund)
            <tr>
                <td>{{ $fund['fund_name'] }}</td>
                <td style="text-align: right;">{{ number_format($fund['total'], 2) }} {{ $donations->first()->currency ?? 'USD' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="2" style="text-align: center;">No fund allocations available.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <h3 style="margin-top: 24px; font-size: 14px;">Detailed donations</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Donor</th>
                <th>Funds</th>
                <th style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
        @forelse($donations as $donation)
            <tr>
                <td>{{ optional($donation->received_at)->format('M j, Y') }}</td>
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
            </tr>
        @empty
            <tr>
                <td colspan="4" style="text-align: center;">No donations recorded.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
@endsection
