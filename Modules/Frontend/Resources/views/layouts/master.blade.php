<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark" dir="{{ session()->has('dir') ? session()->get('dir') : 'ltr' }}" data-bs-theme-color={{ getCustomizationSetting('theme_color') }}>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="baseUrl" content="{{url('/')}}" />
    <link rel="icon" type="image/png" href="{{ GetSettingValue('favicon') ?? asset('img/logo/favicon.png')   }}">
    <link rel="apple-touch-icon" sizes="76x76" href="{{ GetSettingValue('favicon') ?? asset('img/logo/favicon.png')  }}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title> {{ config('app.name', 'Laravel') }}</title>

    <meta name="description" content="{{ $description ?? '' }}">
    <meta name="keywords" content="{{ $keywords ?? '' }}">
    <meta name="author" content="{{ $author ?? '' }}">
    <meta name="google" content="notranslate">


    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;1,100;1,300&amp;display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('modules/frontend/style.css') }}">

    <link rel="stylesheet" href="{{ asset('iconly/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('phosphor-icons/regular/style.css') }}">
    <link rel="stylesheet" href="{{ asset('phosphor-icons/fill/style.css') }}">

    <link rel="stylesheet" href="{{ asset('css/customizer.css') }}">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @include('frontend::components.partials.head.plugins')
    @stack('after-styles')
    {{-- Vite CSS --}}
    {{-- {{ module_vite('build-frontend', 'resources/assets/sass/app.scss') }} --}}
</head>

<body class="{{ Route::currentRouteName() == 'search' ? 'search-page' : '' }}">
    @include('frontend::layouts.header')

    @yield('content')

     @include('frontend::layouts.footer')

    @include('frontend::components.partials.back-to-top')

    @include('frontend::components.partials.scripts.plugins')

    @if(session('success'))
    <script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: 'success',
        title: "{{ session('success.title') }}",
        theme: 'dark',
        html: `
            <div class="text-center">
                <p>{{ session('success.message') }}</p>
                <div class="mt-3">
                    <p><strong>Plan:</strong> {{ session('success.plan_name') }}</p>
                    <p><strong>Amount:</strong> {{ session('success.amount') }}</p>
                    <p><strong>Valid Until:</strong> {{ session('success.valid_until') }}</p>
                </div>
            </div>
        `,
        showConfirmButton: true,
        confirmButtonText: 'Continue',
        confirmButtonColor: '#e50914', // Changed to Bootstrap's danger red
        iconColor: '#e50914', // Added to make the success icon red
        customClass: {
            icon: 'swal2-icon-red' // Added custom class for icon color
        }
    });
});
</script>

<style>
.swal2-icon-red {
    border-color: #e50914 !important;
    color: #e50914 !important;
}
</style>
    @endif

    @if(session('error'))
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: "{{ session('error') }}",
            confirmButtonColor: '#dc3545'
        });
    });
    </script>
    @endif

    <script src="{{ mix('modules/frontend/script.js') }}"></script>
    <script src="{{ mix('js/backend-custom.js') }}"></script>
    {{-- Vite JS --}}
    {{-- {{ module_vite('build-frontend', 'resources/assets/js/app.js') }} --}}
    @stack('after-scripts')
    <script>

const currencyFormat = (amount) => {
        const DEFAULT_CURRENCY = JSON.parse(@json(json_encode(Currency::getDefaultCurrency(true))))
         const noOfDecimal = DEFAULT_CURRENCY.no_of_decimal
         const decimalSeparator = DEFAULT_CURRENCY.decimal_separator
         const thousandSeparator = DEFAULT_CURRENCY.thousand_separator
         const currencyPosition = DEFAULT_CURRENCY.currency_position
         const currencySymbol = DEFAULT_CURRENCY.currency_symbol
        return formatCurrency(amount, noOfDecimal, decimalSeparator, thousandSeparator, currencyPosition, currencySymbol)
      }

      window.currencyFormat = currencyFormat
      window.defaultCurrencySymbol = @json(Currency::defaultSymbol())

    </script>
</body>
</html>
