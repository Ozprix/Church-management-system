<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Finance Report' }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            margin: 0;
            padding: 0;
            color: #111827;
            background-color: #f9fafb;
        }
        .wrapper {
            padding: 24px 32px;
        }
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 4px solid {{ $branding['primary_color'] ?? '#111827' }};
            padding-bottom: 12px;
            margin-bottom: 24px;
        }
        .brand-title {
            font-size: 24px;
            color: {{ $branding['primary_color'] ?? '#111827' }};
            margin: 0;
        }
        .brand-meta {
            font-size: 11px;
            color: #4b5563;
            line-height: 1.4;
            margin-top: 4px;
        }
        .logo {
            max-height: 64px;
            margin-right: 16px;
        }
        .report-title {
            font-size: 20px;
            font-weight: 600;
            color: {{ $branding['primary_color'] ?? '#111827' }};
            margin-bottom: 4px;
        }
        .generated-at {
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        table thead {
            background-color: {{ $branding['primary_color'] ?? '#111827' }};
            color: #ffffff;
        }
        table th, table td {
            font-size: 11px;
            padding: 8px 10px;
            border: 1px solid #d1d5db;
        }
        h2 {
            font-size: 16px;
            margin-top: 24px;
            color: {{ $branding['primary_color'] ?? '#111827' }};
        }
        .section-subtitle {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
            margin-bottom: 8px;
        }
        footer {
            margin-top: 32px;
            border-top: 1px solid #e5e7eb;
            padding-top: 12px;
            font-size: 10px;
            color: #6b7280;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 9999px;
            background-color: {{ $branding['accent_color'] ?? '#047857' }};
            color: #ffffff;
            font-size: 10px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <header>
            <div style="display: flex; align-items: center;">
                @if(!empty($branding['logo_url']))
                    <img src="{{ $branding['logo_url'] }}" alt="Logo" class="logo">
                @endif
                <div>
                    <h1 class="brand-title">{{ $branding['organization'] ?? $tenant->name }}</h1>
                    <div class="brand-meta">
                        @if (!empty($branding['address'])) {{ $branding['address'] }}<br>@endif
                        @if (!empty($branding['phone'])) {{ $branding['phone'] }} @endif
                        @if (!empty($branding['email'])) | {{ $branding['email'] }} @endif
                        @if (!empty($branding['website'])) | {{ $branding['website'] }} @endif
                    </div>
                </div>
            </div>
        </header>

        <section>
            <div class="report-title">{{ $title ?? 'Finance Report' }}</div>
            <div class="generated-at">Generated on {{ ($generated_at ?? now())->format('F j, Y \\a\\t g:i A') }}</div>

            @yield('content')
        </section>

        <footer>
            This document was generated for {{ $branding['organization'] ?? $tenant->name }} using Church Management System.
        </footer>
    </div>
</body>
</html>
