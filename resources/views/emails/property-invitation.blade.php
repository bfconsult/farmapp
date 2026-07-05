<x-mail::message>
# You're invited!

{{ $bodyMessage }}

<x-mail::button :url="route('invitations.accept', $invitation->token)">
Accept Invitation
</x-mail::button>

If you weren't expecting this invitation, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
