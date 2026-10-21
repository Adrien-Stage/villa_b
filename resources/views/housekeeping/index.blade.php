@extends('layouts.hotel')

@section('title', 'Housekeeping')

@section('content')

@include('housekeeping.partials.flash')

@include('housekeeping.partials.minibar-alerts')

@include('housekeeping.partials.issue-modal')

@if($isChief)
    @include('housekeeping.partials.chief')
@else
    @include('housekeeping.partials.member')
@endif

{{-- Bouton flottant de rafraîchissement : remplace l'ancienne actualisation
     automatique qui interrompait la saisie. Placé au-dessus de l'assistant IA. --}}
<button type="button" onclick="window.location.reload()"
    class="fixed bottom-20 right-4 lg:bottom-6 lg:right-6 z-30 inline-flex items-center gap-2 px-3.5 py-2.5 sm:px-4 sm:py-3 bg-primary text-white text-xs sm:text-sm font-semibold rounded-full shadow-lg hover:bg-surface-dark transition-all"
    title="Actualiser le tableau">
    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
    <span class="hidden sm:inline">Actualiser</span>
</button>

@if($isChief)
@push('scripts')
<script>
// Code de l'équipe suggéré depuis le nom, à la création (modifiable).
(function () {
    function wire() {
        window.wireAutoCode(
            document.getElementById('create-team-name'),
            document.getElementById('create-team-code')
        );
    }
    if (document.readyState !== 'loading') wire();
    else document.addEventListener('DOMContentLoaded', wire);
})();
</script>
@endpush
@endif

@endsection
