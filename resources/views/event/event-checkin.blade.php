
@extends('layouts.dashboard')

@section('subnav')
    @include('partials.event.subnav')
@endsection

@section('page-title')
    {{ $event->name }}
@endsection

@section('content')
    <div class="content">

        <button type="button" id="check-in-camera-button" class="btn btn-primary w-100 mb-3" hidden>Scan QR</button>

        <livewire:event.check-in :slug="$slug"></livewire:event.check-in>

    </div>

    <div id="check-in-camera"
         data-check-in-url="{{ route('event.check-in', $slug) }}"
         data-scanner-url="{{ asset('js/qr-scanner/qr-scanner.min.js') }}"
         hidden>
        <video id="check-in-camera-video" muted playsinline></video>
        <div class="check-in-camera-bar">
            <p id="check-in-camera-message" role="status">Point the camera at a check-in QR code</p>
            <button type="button" id="check-in-camera-close" class="btn btn-white w-100">Close camera</button>
        </div>
    </div>
@endsection


@push('css')
    <style>
        #check-in-camera {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            /* Not bottom: 0. The wide table stretches the layout past the
               screen on a phone, and that would push the bar off the bottom. */
            height: 100vh;
            height: 100dvh;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            background: #000;
        }
        #check-in-camera[hidden],
        #check-in-camera-button[hidden] {
            display: none;
        }
        #check-in-camera video {
            flex: 1;
            min-height: 0;
            width: 100%;
            object-fit: cover;
        }
        .check-in-camera-bar {
            padding: 1rem 1rem calc(1rem + env(safe-area-inset-bottom));
            color: #fff;
            text-align: center;
        }
        #check-in-camera-close {
            background: #fff;
            color: #1d273b;
        }
        .check-in-camera-bar p {
            margin-bottom: .75rem;
            font-size: 1rem;
        }
    </style>
@endpush

@push('js')

    <script src="{{ asset('js/barcode_scanner.js') }}"></script>

    <script src="{{ asset('js/check_in_camera.js') }}"></script>
@endpush
