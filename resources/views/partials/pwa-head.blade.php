{{-- Balises rendant l'application installable (à inclure dans <head>). --}}
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="#391F0E">
<meta name="mobile-web-app-capable" content="yes">

{{-- iOS ignore le manifeste : ces balises lui sont indispensables. --}}
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ $tenantName ?? config('app.name') }}">
<link rel="apple-touch-icon" href="{{ url('/pwa/icon-192.png') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ url('/pwa/icon-192.png') }}">
<link rel="icon" type="image/png" sizes="512x512" href="{{ url('/pwa/icon-512.png') }}">
