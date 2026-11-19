<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- ✅ AJOUTER CECI : Chargement de Tailwind et de tes configs -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <!-- Tes polices Google Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap"
            rel="stylesheet">
    </head>
    <body class="antialiased">
        <!-- Ton code de test ici -->
        <div class="bg-primary text-white p-6 rounded-lg">
            <h1 class="font-heading text-3xl">Villa Boutanga</h1>
            <p class="font-body text-accent">Ceci est un test du Design System.</p>
        </div>
    </body>
</html>