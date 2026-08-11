<x-mail::message>
# Weekly Hours Summary

You've logged {{ format_number($totalHours) }} hours over the last week (draft and finalised sessions).

@foreach ($summary as $entry)
## {{ $entry['property']->name }} — {{ format_number($entry['total_hours']) }}h

@foreach ($entry['jobs'] as $job)
**{{ $job['label'] }}** — {{ format_number($job['total_hours']) }}h

@if ($job['finalised_sessions']->isNotEmpty())
_Finalised_
@foreach ($job['finalised_sessions'] as $session)
- {{ $session->started_at->format('D j M, g:ia') }}–{{ $session->ended_at ? $session->ended_at->format('g:ia') : 'in progress' }} ({{ $session->duration_in_hours !== null ? format_number($session->duration_in_hours) . 'h' : 'in progress' }})
@endforeach

@endif
@if ($job['draft_sessions']->isNotEmpty())
_Draft_
@foreach ($job['draft_sessions'] as $session)
- {{ $session->started_at->format('D j M, g:ia') }}–{{ $session->ended_at ? $session->ended_at->format('g:ia') : 'in progress' }} ({{ $session->duration_in_hours !== null ? format_number($session->duration_in_hours) . 'h' : 'in progress' }})
@endforeach

@endif
@endforeach
@endforeach
Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
