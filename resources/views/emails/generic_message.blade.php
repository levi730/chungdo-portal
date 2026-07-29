@component('mail::message')
# {{ $title }}
{{ $body }}
@if($button_text && $button_url)
@component('mail::button', ['url' => $button_url])
{{ $button_text }}
@endcomponent
@endif
{{ $signoff }}<br>
{{ config('app.name') }}
@endcomponent
