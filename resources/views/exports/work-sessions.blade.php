<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: sans-serif; font-size: 12px; color: #1f2937; }
    h1 { font-size: 18px; margin-bottom: 0; }
    p.subtitle { color: #6b7280; margin-top: 4px; }
    table { width: 100%; border-collapse: collapse; margin-top: 16px; }
    th, td { border-bottom: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
    th { background-color: #f9fafb; font-size: 11px; text-transform: uppercase; color: #6b7280; }
    td.amount, th.amount { text-align: right; }
    tfoot td { font-weight: bold; border-top: 2px solid #1f2937; }
    .billing-header { margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb; }
    .billing-name { font-size: 16px; font-weight: bold; margin: 0 0 4px; }
    .billing-line { margin: 0; color: #4b5563; }
</style>
</head>
<body>
    @if ($billingDetails || $fromLine)
        <div class="billing-header">
            @if (!empty($billingDetails['Company/Business Name']))
                <p class="billing-name">{{ $billingDetails['Company/Business Name'] }}</p>
            @endif
            @foreach ($billingDetails as $label => $value)
                @if ($label !== 'Company/Business Name')
                    <p class="billing-line">{{ $label }}: {{ $value }}</p>
                @endif
            @endforeach
            @if ($fromLine)
                <p class="billing-line">{{ $fromLine }}</p>
            @endif
        </div>
    @endif

    <h1>Work Sessions</h1>
    <p class="subtitle">{{ $dateFrom }} &rarr; {{ $dateTo }}</p>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Job</th>
                <th>Start</th>
                <th>End</th>
                <th class="amount">Hours</th>
                @if ($rateMode === 'billing')
                    <th class="amount">Amount (Ex GST)</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['job'] }}</td>
                    <td>{{ $row['start'] }}</td>
                    <td>{{ $row['end'] }}</td>
                    <td class="amount">{{ $row['duration'] }}</td>
                    @if ($rateMode === 'billing')
                        <td class="amount">{{ $row['amount'] !== null ? '$' . format_number($row['amount']) : '—' }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4">Total</td>
                <td class="amount">{{ format_number($totalHours) }}</td>
                @if ($rateMode === 'billing')
                    <td class="amount">${{ format_number($totalBilling) }}</td>
                @endif
            </tr>
        </tfoot>
    </table>
</body>
</html>
