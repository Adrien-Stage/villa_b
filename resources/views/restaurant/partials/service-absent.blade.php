{{--
    Le restaurant choisi n'exploite pas le service de cet écran.
    $service : PointOfSale::SERVICE_*. Rien depuis la vue d'ensemble : on y
    voit ce que les autres restaurants font.
--}}
@php $sansService = app(\App\Services\RestaurantContext::class)->serviceAbsentIci(auth()->user(), $service); @endphp
@if($sansService)
    <div class="mb-5 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="status">
        <i data-lucide="info" class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true"></i>
        <p>
            {{ $sansService->name }} n'a pas de {{ mb_strtolower(\App\Models\PointOfSale::SERVICES[$service]) }} : cet écran ne le concerne pas.
            Ce service s'active dans Paramètres › Restaurant.
        </p>
    </div>
@endif
