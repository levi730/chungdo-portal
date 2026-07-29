<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title>
            @yield('title')
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
    <div class="container-narrow y-6">
        <div class="text-center mb-4">
            <img src="{{ asset('img/CDKTKD_logo.svg') }}" height="100" alt="">

            <span class="d-none d-md-block " style="font-family: 'Cormorant', serif; font-size: 1.6rem;">Chung Do Association</span>
        </div>

        <div class="card card-md col mb-5">
            <div class="card-body row align-items-center">
                <h2 class="text-center display-4">@yield('header_text')</h2>
                <img src="/img/error_gifs/@yield('gif')" height="300">
                <h1 class="display-5">@yield('code')</h1>
                <p>@yield('detail')</p>
                <p class="mt-4">Please try again in a few minutes, or head back to the <a href="/">Home Page</a>.</p>
            </div>
        </div>
    </div>
</div>

</body>
</html>

