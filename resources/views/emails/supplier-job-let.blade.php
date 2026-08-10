<x-mail::message>
# Job update

Thanks for your interest in **{{ $quote->farmJob->name }}** for {{ $quote->farmJob->property->name }}. This job has now been given to another supplier.

We appreciate you taking the time, and will keep you in mind for future work.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
