<x-mail::message>
# Weekly Summary

Here's what still needs attention across your properties.

@foreach ($summary as $entry)
## {{ $entry['property']->name }}

@if ($entry['jobs']->isNotEmpty())
**Incomplete scheduled jobs**

@foreach ($entry['jobs'] as $job)
- {{ $job->name }} — scheduled {{ $job->scheduled_date->format('j M Y') }}
@endforeach

@endif
@if ($entry['measurements']->isNotEmpty())
**Incomplete metrics**

@foreach ($entry['measurements'] as $measurement)
- {{ $measurement->name }} — due {{ $measurement->period_end->format('j M Y') }}
@endforeach

@endif
@endforeach
Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
