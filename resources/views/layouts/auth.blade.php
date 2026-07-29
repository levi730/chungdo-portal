<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title>
        @if(View::hasSection('page_title'))
            @yield('page_title')
            -
        @endif
        {{ config('app.name', 'Laravel') }}
    </title>

    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">
    <meta name="msapplication-TileColor" content="#da532c">
    <meta name="theme-color" content="#ffffff">

    <!-- CSS files -->
    <link href="{{ asset('dist/css/tabler.min.css') }}" rel="stylesheet"/>
    <link href="{{ asset('dist/css/tabler-flags.min.css') }}" rel="stylesheet"/>
    <link href="{{ asset('dist/css/tabler-payments.min.css') }}" rel="stylesheet"/>
    <link href="{{ asset('dist/css/tabler-vendors.min.css') }}" rel="stylesheet"/>
    <link href="{{ asset('dist/css/demo.min.css') }}" rel="stylesheet"/>
    <link href="{{ asset('/css/custom.css') }}" rel="stylesheet"/>
    @stack('css')
</head>
<body class="antialiased border-top-wide border-primary d-flex flex-column">
<div class="flex-fill d-flex flex-column justify-content-center">
    <div class="{{ $container_class ?? 'container-tight' }} py-6">
        <div class="text-center mb-4">
            <img src="{{ asset('img/CDKTKD_logo.svg') }}" height="200" alt="">

            <span class="d-none d-md-block " style="font-family: 'Cormorant', serif; font-size: 1.6rem;">Chung Do Association</span>

        </div>
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                {{ __('auth.error') }}: {{ $errors->first() }}
            </div>
        @endif
        @yield('content')
    </div>
    <footer class="mt-5 border-top-wide text-center">
        Need help?
        <span class='punch'>s</span><span class='kick'>d</span><span class='punch'>u</span><span class='punch'>p</span><span class='punch'>p</span><span class='kick'>o</span><span class='kick'>n</span><span class='punch'>o</span><span class='punch'>r</span><span class='kick'>t</span><span class='punch'>t</span><span class='punch'>@</span><span class='punch'>c</span><span class='kick'>s</span><span class='punch'>h</span><span class='punch'>u</span><span class='kick'>c</span><span class='kick'>r</span><span class='punch'>n</span><span class='kick'>a</span><span class='punch'>g</span><span class='punch'>d</span><span class='kick'>p</span><span class='punch'>o</span><span class='punch'>.</span><span class='punch'>o</span><span class='punch'>r</span><span class='kick'>e</span><span class='punch'>g</span>
    </footer>
</div>
<!-- Libs JS -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="{{ asset('dist/libs/apexcharts/dist/apexcharts.min.js') }}"></script>
<!-- Tabler Core -->
<script src="{{ asset('dist/js/tabler.min.js') }}"></script>
<script src="{{ asset('dist/js/demo.min.js') }}"></script>

@stack('js')
</body>
</html>

