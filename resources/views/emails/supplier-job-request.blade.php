<x-mail::message>
# {{ $quote->requires_quote ? 'New job quote request' : 'New job request' }}

{{ $quote->farmJob->property->name }} would like **{{ $quote->supplier->name }}** to
{{ $quote->requires_quote ? 'provide a quote for' : 'carry out' }} the following job{{ $quote->requires_quote ? '' : ', on a time and materials basis' }}:

**{{ $quote->farmJob->name }}**

@if($quote->message)
{{ $quote->message }}
@endif

<x-mail::button :url="route('jobs.share', $quote->farmJob->share_token)">
View Job Details
</x-mail::button>

Please get in touch to discuss.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
