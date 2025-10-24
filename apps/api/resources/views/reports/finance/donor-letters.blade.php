@extends('reports.finance.layouts.branded')

@section('content')
    <h2>Donor appreciation</h2>
    <div class="section-subtitle">
        Covering gifts from {{ $date_range[0]->format('F j, Y') }} to {{ $date_range[1]->format('F j, Y') }}
    </div>

    @forelse($donors as $donor)
        <div style="margin-top: 24px; page-break-inside: avoid;">
            <h3 style="font-size: 14px; color: {{ $branding['primary_color'] ?? '#111827' }};">
                Dear {{ $donor->preferred_name ?? $donor->full_name }},
            </h3>
            <p style="font-size: 12px; line-height: 1.6; color: #374151;">
                Thank you for your faithful generosity toward {{ $branding['organization'] ?? $tenant->name }}. We
                are grateful for the ways your giving is advancing our shared mission. Below is a summary of your
                contributions during this period for your records.
            </p>

            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th style="text-align: right;">Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($donor->donations as $donation)
                    <tr>
                        <td>{{ optional($donation->received_at)->format('F j, Y') }}</td>
                        <td style="text-align: right;">{{ number_format($donation->amount, 2) }} {{ $donation->currency }}</td>
                        <td><span class="badge">{{ $donation->status }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <p style="font-size: 12px; line-height: 1.6; color: #374151; margin-top: 12px;">
                Please reach out if you have questions or need any adjustments to your records. We are honored to
                partner with you.
            </p>

            <p style="font-size: 12px; line-height: 1.6; color: #374151; margin-top: 16px;">
                With gratitude,<br>
                {{ $branding['organization'] ?? $tenant->name }} Finance Team
            </p>
        </div>

        @if(!$loop->last)
            <div style="page-break-after: always;"></div>
        @endif
    @empty
        <p style="font-size: 12px; color: #6b7280;">No donors found for this period.</p>
    @endforelse
@endsection
