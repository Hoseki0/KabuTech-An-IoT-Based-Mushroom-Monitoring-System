@php
    $base = rtrim(request()->getSchemeAndHttpHost() . request()->getBaseUrl(), '/');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sign in') — Fiona's Mushroom Farm</title>
    <link rel="icon" type="image/png" sizes="192x192" href="{{ $base }}/images/icon-192.png">
    <link rel="apple-touch-icon" href="{{ $base }}/images/icon-192.png">
    <link rel="manifest" href="{{ $base }}/manifest.json">
    <meta name="theme-color" content="#10b981">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <link href="{{ $base }}/vendor/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ $base }}/vendor/fontawesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ $base }}/css/dashboard.css">
</head>
<body class="auth-layout d-flex align-items-center min-vh-100 py-4">
<div class="container" style="max-width: 420px;">
    <div class="text-center mb-4">
        <h1 class="h4">Fiona's Mushroom Farm</h1>
        <p class="text-muted small mb-0">Automated Mushroom System</p>
    </div>
    <div class="card glass-card border-0 shadow">
        <div class="card-body p-4">
            @yield('content')
        </div>
    </div>
    <p class="text-center text-muted small mt-3 mb-0">@yield('footer-links')</p>
</div>
<script src="{{ $base }}/vendor/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register({!! json_encode($base . '/sw.js') !!}).catch(function(){});
}
</script>
@stack('scripts')
</body>
</html>
