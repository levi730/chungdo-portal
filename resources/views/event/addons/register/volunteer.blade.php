{{-- Per-student volunteer roles. Expects $user and $addon in scope and an Alpine
     `volunteer_selections` array. Posting shape matches the legacy column. --}}
<div class="row">
    <b>Volunteer for?:</b><br>
    <small>
        @foreach($addon->setting('options', []) as $vopt)
            <input type="checkbox" x-model="volunteer_selections"
                   value='{ "user_id": {{ $user->id }}, "item": "{{ $vopt }}" }'> {{ $vopt }}
            @if(!$loop->last)
                |
            @endif
        @endforeach
    </small>
</div>
