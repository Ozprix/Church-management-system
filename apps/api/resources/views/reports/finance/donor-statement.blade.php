@extends('reports.finance.layouts.branded')

@section('content')
    <h2>Donor summary</h2>
    <div class="section-subtitle">
        Statement for {{ $member->full_name }} &mdash; {{ $member->email ?? 'no email on file' }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
        @forelse($donations as $donation)
            <tr>
                <td>{{ optional($donation->received_at)->format('F j, Y') }}</td>
                <td>{{ number_format($donation->amount, 2) }} {{ $donation->currency }}</td>
                <td><span class="badge">{{ $donation->status }}</span></td>
                <td>{{ $donation->notes ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" style="text-align: center;">No donations recorded within this range.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
@endsection
