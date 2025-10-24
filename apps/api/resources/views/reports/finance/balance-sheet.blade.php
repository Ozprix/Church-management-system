@extends('reports.finance.layouts.branded')

@php
    $typeLabels = [
        'asset' => 'Assets',
        'liability' => 'Liabilities',
        'equity' => 'Equity',
        'income' => 'Income',
        'expense' => 'Expenses',
    ];
@endphp

@section('content')
    <h2>Account balances</h2>
    <div class="section-subtitle">
        {{ !empty($filters['from']) ? 'From '.\Illuminate\Support\Carbon::parse($filters['from'])->format('F j, Y').' ' : '' }}
        {{ !empty($filters['to']) ? 'to '.\Illuminate\Support\Carbon::parse($filters['to'])->format('F j, Y') : '' }}
    </div>

    @foreach($entries->groupBy('type') as $type => $accounts)
        <h3 style="margin-top: 20px; font-size: 14px;">{{ $typeLabels[$type] ?? ucfirst($type) }}</h3>
        <table>
            <thead>
                <tr>
                    <th>Account</th>
                    <th style="text-align: right;">Debits</th>
                    <th style="text-align: right;">Credits</th>
                    <th style="text-align: right;">Balance</th>
                </tr>
            </thead>
            <tbody>
            @foreach($accounts as $account)
                <tr>
                    <td>{{ $account['name'] }}</td>
                    <td style="text-align: right;">{{ number_format($account['debits'], 2) }}</td>
                    <td style="text-align: right;">{{ number_format($account['credits'], 2) }}</td>
                    <td style="text-align: right;">{{ number_format($account['balance'], 2) }}</td>
                </tr>
            @endforeach
            @php
                $totals = $totals_by_type[$type] ?? ['debits' => 0, 'credits' => 0, 'balance' => 0];
            @endphp
            <tr>
                <td style="text-align: right; font-weight: 600;">Total {{ $typeLabels[$type] ?? ucfirst($type) }}</td>
                <td style="text-align: right; font-weight: 600;">{{ number_format($totals['debits'], 2) }}</td>
                <td style="text-align: right; font-weight: 600;">{{ number_format($totals['credits'], 2) }}</td>
                <td style="text-align: right; font-weight: 600;">{{ number_format($totals['balance'], 2) }}</td>
            </tr>
            </tbody>
        </table>
    @endforeach
@endsection
