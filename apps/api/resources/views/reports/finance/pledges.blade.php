@extends('reports.finance.layouts.branded')

@section('content')
    <h2>Pledge commitments</h2>
    <table>
        <thead>
            <tr>
                <th>Pledger</th>
                <th>Fund</th>
                <th>Frequency</th>
                <th style="text-align: right;">Pledged</th>
                <th style="text-align: right;">Fulfilled</th>
                <th>Status</th>
                <th>Start</th>
                <th>End</th>
            </tr>
        </thead>
        <tbody>
        @forelse($pledges as $pledge)
            <tr>
                <td>{{ $pledge->member?->full_name ?? 'Anonymous supporter' }}</td>
                <td>{{ $pledge->fund?->name ?? 'General fund' }}</td>
                <td>{{ str_replace('_', ' ', $pledge->frequency) }}</td>
                <td style="text-align: right;">{{ number_format($pledge->amount, 2) }} {{ $pledge->currency }}</td>
                <td style="text-align: right;">{{ number_format($pledge->fulfilled_amount ?? 0, 2) }} {{ $pledge->currency }}</td>
                <td><span class="badge">{{ $pledge->status }}</span></td>
                <td>{{ optional($pledge->start_date)->format('M j, Y') ?? '—' }}</td>
                <td>{{ optional($pledge->end_date)->format('M j, Y') ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" style="text-align: center;">No pledges found.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
@endsection
