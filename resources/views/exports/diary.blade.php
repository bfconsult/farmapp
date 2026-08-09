<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: sans-serif; font-size: 12px; color: #1f2937; }
    h1 { font-size: 18px; margin-bottom: 0; text-align: center; }
    p.subtitle { color: #6b7280; margin-top: 4px; text-align: center; }
    h2.day { font-size: 13px; text-transform: uppercase; color: #4b5563; margin-top: 20px; margin-bottom: 8px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
    .entry { margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #f3f4f6; }
    .entry-header { width: 100%; }
    .entry-label { font-weight: bold; }
    .entry-time { color: #6b7280; font-size: 11px; float: right; }
    .entry-user { color: #6b7280; margin: 2px 0; }
    .entry-description { white-space: pre-line; margin-top: 4px; }
    .empty { text-align: center; color: #6b7280; padding: 20px 0; }
    h2.section { font-size: 14px; margin-top: 24px; border-bottom: 1px solid #1f2937; padding-bottom: 4px; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th, td { border-bottom: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
    th { background-color: #f9fafb; font-size: 11px; text-transform: uppercase; color: #6b7280; }
</style>
</head>
<body>
    <h1>{{ $property['name'] }}</h1>
    <p class="subtitle">Activity Diary &middot; {{ $dateFrom }} &rarr; {{ $dateTo }}</p>

    @if (count($days) === 0)
        <p class="empty">No finalised activity recorded in this period.</p>
    @else
        @foreach ($days as $day)
            <h2 class="day">{{ \Carbon\Carbon::parse($day['date'])->format('l, j F Y') }}</h2>
            @foreach ($day['entries'] as $entry)
                <div class="entry">
                    <div class="entry-header">
                        <span class="entry-time">
                            {{ $entry['started_at'] ? \Carbon\Carbon::parse($entry['started_at'])->format('H:i') : '—' }}
                            &ndash;
                            {{ $entry['ended_at'] ? \Carbon\Carbon::parse($entry['ended_at'])->format('H:i') : '—' }}
                            @if ($entry['duration_in_hours'])
                                ({{ $entry['duration_in_hours'] }}h)
                            @endif
                        </span>
                        <span class="entry-label">{{ $entry['label'] }}</span>
                    </div>
                    <p class="entry-user">{{ $entry['user_name'] }}</p>
                    @if (!empty($entry['description']))
                        <p class="entry-description">{{ $entry['description'] }}</p>
                    @endif
                </div>
            @endforeach
        @endforeach
    @endif

    @if (count($metrics) > 0)
        <h2 class="section">Metrics</h2>
        <table>
            <thead>
                <tr><th>Metric</th><th>Value</th></tr>
            </thead>
            <tbody>
                @foreach ($metrics as $metric)
                    <tr>
                        <td>{{ $metric['name'] }}</td>
                        <td>
                            @if (!$metric['measurement'] || $metric['measurement']['status'] !== 'complete')
                                Not measured
                            @elseif ($metric['measurement']['answer_type'] === 'number')
                                {{ $metric['measurement']['value_number'] ?? 'No value recorded' }}
                            @else
                                {{ $metric['measurement']['value_text'] ?: 'No value recorded' }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
