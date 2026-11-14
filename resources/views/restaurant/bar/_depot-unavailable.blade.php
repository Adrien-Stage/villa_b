@include('restaurant.partials.service-absent', ['service' => \App\Models\PointOfSale::SERVICE_BAR])
@if(!$restaurant)
    <p class="rounded-xl bg-white p-4 text-sm text-primary/70">Sélectionnez un restaurant qui dispose d’un bar en haut de la page.</p>
@elseif($restaurant->offre(\App\Models\PointOfSale::SERVICE_BAR))
    <p class="rounded-xl bg-white p-4 text-sm text-primary/70">Le dépôt Bar est indisponible. Vérifiez la configuration de ce restaurant dans Paramètres › Restaurant.</p>
@endif
