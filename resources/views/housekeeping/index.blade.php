@extends('layouts.hotel')

@section('title', 'Housekeeping')

@section('content')

@include('housekeeping.partials.flash')

@include('housekeeping.partials.minibar-alerts')

@if($isChief)
    @include('housekeeping.partials.chief')
@else
    @include('housekeeping.partials.member')
@endif

@include('housekeeping.partials.issue-modal')

{{-- Bouton flottant de rafraîchissement : remplace l'ancienne actualisation
     automatique qui interrompait la saisie. Placé au-dessus de l'assistant IA. --}}
<button type="button" onclick="window.location.reload()"
    class="fixed bottom-24 right-6 z-40 inline-flex items-center gap-2 px-4 py-3 bg-primary text-white text-sm font-semibold rounded-full shadow-lg hover:bg-surface-dark transition-colors"
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
