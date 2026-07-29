
@extends('layouts.dashboard')

@section('subnav')
    @include('partials.event.subnav')
@endsection

@section('page-title')
    {{ $event->name }}
@endsection

@section('content')
    <div class="content">

       <livewire:event.check-in :slug="$slug"></livewire:event.check-in>

    </div>
@endsection


@push('css')
@endpush

@push('js')

    <script src="{{ asset('js/barcode_scanner.js') }}"></script>

    <script>

        // listen to `scanComplete` event on document
        document.addEventListener('scanComplete', function(e) {
            let json = e.detail;
            let obj = JSON.parse(json);
            location.href = '/event/{{$slug}}/check-in/'+obj.registrants.join(',')+'?scan=1';
        })

    </script>
@endpush
