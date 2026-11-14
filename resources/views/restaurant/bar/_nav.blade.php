@php
    $barContext = app(\App\Services\RestaurantContext::class);
    $selectedBarRestaurant = $barContext->courant(auth()->user());
    $barEnabled = $selectedBarRestaurant
        ? $selectedBarRestaurant->offre(\App\Models\PointOfSale::SERVICE_BAR)
        : $barContext->offrant(auth()->user(), \App\Models\PointOfSale::SERVICE_BAR)->isNotEmpty();
    $tabs = [
        ['Préparation', 'restaurant.bar.index', 'restaurant.bar.voir', 'restaurant.bar.index'],
        ['Dépôt et bouteilles', 'restaurant.bar.depot.index', 'restaurant.bar.depot.voir', 'restaurant.bar.depot.*|restaurant.bar.bottles.*'],
        ['Carte et fiches', 'restaurant.bar.menu.index', 'restaurant.bar.menu.voir', 'restaurant.bar.menu.*'],
        ['Ventes', 'restaurant.bar.sales.index', 'restaurant.bar.sales.voir', 'restaurant.bar.sales.*'],
    ];
@endphp

@if($barEnabled)
    <nav aria-label="Espace Bar" class="mb-5 flex flex-wrap gap-2">
        @foreach($tabs as [$label, $route, $permission, $patterns])
            @droit($permission)
                @php $current = request()->routeIs(...explode('|', $patterns)); @endphp
                <a href="{{ route($route) }}" @if($current) aria-current="page" @endif
                    class="rounded-lg border px-3 py-2 text-sm font-medium {{ $current ? 'border-primary bg-primary text-white' : 'border-secondary/20 bg-white text-primary' }}">
                    {{ $label }}
                </a>
            @enddroit
        @endforeach
    </nav>
@endif

@if(session('success'))
    <p role="status" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</p>
@endif
@if($errors->any())
    <ul role="alert" class="mb-4 list-disc rounded-lg border border-red-200 bg-red-50 px-8 py-3 text-sm text-red-800">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif
