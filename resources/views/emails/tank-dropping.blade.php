<x-mail::message>
# {{ $device->displayName() }} is dropping

It has gone from **{{ round($drop['from_percent']) }}%** to **{{ round($drop['percent']) }}%** full in the last {{ $within }}, a drop of {{ round($drop['drop']) }}%@if ($drop['litres'] !== null && $drop['from_litres'] !== null) (about {{ number_format($drop['from_litres'] - $drop['litres']) }} litres)@endif. You asked to be warned about a drop of {{ $warningPercent }}% within {{ $within }}.

It could be a leak or a tap left on, or just heavy use.

@if ($device->property)
**Property:** {{ $device->property->name }}
@endif

<x-mail::button :url="route('manage.sensors.show', $device)">
View Tank
</x-mail::button>

You won't get this warning again until the level has steadied. You can change or remove it on the tank's Edit page.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
