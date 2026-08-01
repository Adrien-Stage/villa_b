{{-- Balises rendant l'application installable (à inclure dans <head>). --}}
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="#391F0E">
<meta name="mobile-web-app-capable" content="yes">

{{-- iOS ignore le manifeste : ces balises lui sont indispensables. --}}
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ $tenantName ?? config('app.name') }}">

{{-- Icônes composées à partir du logo importé dans Paramètres → Général.
     Les URL viennent de la route nommée : écrites à la main ici, elles ont
     déjà cassé silencieusement le favicon quand le routage a changé. Le
     suffixe de version force le navigateur à recharger l'icône d'onglet
     dès qu'un nouveau logo est importé, au lieu de garder l'ancienne. --}}
@php $pwaIcon = \App\Http\Controllers\PwaController::class; @endphp
<link rel="icon" type="image/png" sizes="32x32" href="{{ $pwaIcon::iconUrl(32) }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ $pwaIcon::iconUrl(192) }}">
<link rel="icon" type="image/png" sizes="512x512" href="{{ $pwaIcon::iconUrl(512) }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ $pwaIcon::iconUrl(180) }}">
