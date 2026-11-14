<x-mail::message>
# {{ $device->name }} is still running

It has been running for {{ $minutes }} minutes, longer than the {{ $limit }} minutes set for it.

@if ($device->property)
**Property:** {{ $device->property->name }}
@endif

<x-mail::button :url="route('manage.sensors.show', $device)">
View Pump
</x-mail::button>

You can change or turn off this warning on the pump's Edit page.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
