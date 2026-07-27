<x-mail::message>
# Weekly Hours Summary

You've logged {{ number_format($totalHours, 2) }} hours over the last week (draft and finalised sessions).

@foreach ($summary as $entry)
## {{ $entry['property']->name }} — {{ number_format($entry['total_hours'], 2) }}h

@foreach ($entry['jobs'] as $job)
**{{ $job['label'] }}** — {{ number_format($job['total_hours'], 2) }}h

@if ($job['finalised_sessions']->isNotEmpty())
_Finalised_
@foreach ($job['finalised_sessions'] as $session)
- {{ $session->started_at->format('D j M, g:ia') }}–{{ $session->ended_at ? $session->ended_at->format('g:ia') : 'in progress' }} ({{ $session->duration_in_hours !== null ? number_format($session->duration_in_hours, 2) . 'h' : 'in progress' }})
@endforeach

@endif
@if ($job['draft_sessions']->isNotEmpty())
_Draft_
@foreach ($job['draft_sessions'] as $session)
- {{ $session->started_at->format('D j M, g:ia') }}–{{ $session->ended_at ? $session->ended_at->format('g:ia') : 'in progress' }} ({{ $session->duration_in_hours !== null ? number_format($session->duration_in_hours, 2) . 'h' : 'in progress' }})
@endforeach

@endif
@endforeach
@endforeach
Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
