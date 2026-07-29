{{-- document.getElementById('{{ $attributes->get('pik-trigger-id') }}')--}}
<div
    x-data="{localvalue:$refs.input.value}"
    x-on:change="value = $event.target.value"
    x-init="
        new Pikaday({ field: $refs.input, 'format': 'MM/DD/YYYY', firstDay: 0, setDefaultDate: true, yearRange: 100, trigger: document.getElementById('{{ $attributes->get('pik-trigger-id') }}'), blurFieldOnSelect: false });"
    class="sm:w-27rem sm:w-full">
    <div class="relative mt-2">

        <div class="input-group">
            <input type="text" x-ref="input" x-bind:value="localvalue" {{ $attributes->except('pik_trigger_id')->merge([
                'class' => "form-control",
                'placeholder' => "MM/DD/YYYY",
                'autocomplete' => 'off'
            ]) }}>
            <span class="input-group-text">
                <a href="#" onclick="return false;" tabindex="-1"  class="pik-trigger" id="{{ $attributes->get('pik-trigger-id') }}"><svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><rect x="4" y="5" width="16" height="16" rx="2"></rect><line x1="16" y1="3" x2="16" y2="7"></line><line x1="8" y1="3" x2="8" y2="7"></line><line x1="4" y1="11" x2="20" y2="11"></line><line x1="11" y1="15" x2="12" y2="15"></line><line x1="12" y1="15" x2="12" y2="18"></line></svg></a>
            </span>
        </div>

    </div>
</div>



@once
    @push('css')
        <link rel="stylesheet" type="text/css" href="/css/pikaday.css">
    @endpush
@endonce

@once
    @push('js')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js" integrity="sha512-qTXRIMyZIFb8iQcfjXWCO8+M5Tbc38Qi5WzdPOYZHIlZpzBHG3L3by84BBBOiRGiEb7KKtAOAs5qYdUiZiQNNQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script src="/js/pikaday.js"></script>
    @endpush
@endonce
