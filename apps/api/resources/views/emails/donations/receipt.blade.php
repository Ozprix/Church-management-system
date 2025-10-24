<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Donation receipt</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f9fafb; color: #1f2937; margin: 0; padding: 24px;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; padding: 24px;">
        <tr>
            <td>
                <p style="margin: 0 0 16px;">Hi @if($member) {{ $member->first_name }} {{ $member->last_name }} @else there @endif,</p>
                <p style="margin: 0 0 16px;">
                    Thank you for your generous contribution to {{ $tenant->name ?? 'our church' }}. Your official receipt is attached for your records.
                </p>
                <div style="padding: 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; margin-bottom: 16px;">
                    <p style="margin: 0; font-weight: bold;">Gift summary</p>
                    <p style="margin: 8px 0 0;">
                        Amount: <strong>{{ number_format((float) $donation->amount, 2) }} {{ strtoupper($donation->currency ?? 'USD') }}</strong><br>
                        Receipt #: <strong>{{ $donation->receipt_number ?? 'N/A' }}</strong><br>
                        Received: <strong>{{ optional($donation->received_at)->format('F j, Y g:i a') }}</strong>
                    </p>
                </div>
                <p style="margin: 0 0 16px;">
                    We are deeply grateful for your partnership in ministry. If you have any questions about this receipt, simply reply to this email.
                </p>
                <p style="margin: 0;">Blessings,<br>{{ $tenant->name ?? 'Our Church' }} Finance Team</p>
            </td>
        </tr>
    </table>
</body>
</html>
