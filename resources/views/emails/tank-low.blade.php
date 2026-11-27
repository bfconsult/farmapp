<x-mail::message>
# {{ $device->displayName() }} is running low

It's down to **{{ $percent }}%**@if ($litres !== null) (about {{ number_format($litres) }} litres)@endif, below the {{ $warnBelow + 0 }}% set for it.

@if ($device->property)
**Property:** {{ $device->property->name }}
@endif

<x-mail::button :url="route('manage.sensors.show', $device)">
View Tank
</x-mail::button>

You won't be emailed again until it has refilled. You can change or turn off this warning on the tank's Edit page.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
