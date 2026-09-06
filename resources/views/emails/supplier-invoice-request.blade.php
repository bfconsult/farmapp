<x-mail::message>
# Invoice request

{{ $quote->farmJob->property->name }} would like **{{ $quote->supplier->name }}** to submit an invoice for:

**{{ $quote->farmJob->name }}**

@if($requestMessage)
{{ $requestMessage }}
@endif

<x-mail::button :url="route('quotes.share', $quote->share_token)">
Submit Invoice
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
