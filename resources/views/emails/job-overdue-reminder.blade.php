<x-mail::message>
# Job overdue: {{ $farmJob->name }}

This job was scheduled for {{ $farmJob->scheduled_date->format('j M Y') }}, but no time has been logged against it yet.

@if ($farmJob->property)
**Property:** {{ $farmJob->property->name }}
@endif

<x-mail::button :url="route('jobs.show', $farmJob)">
View Job
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
