@extends('layouts.hotel')

@section('title', 'Housekeeping')

@section('content')

@if(session('success'))
<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
    {{ session('success') }}
</div>
@endif

@if($errors->any())
<div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
    {{ $errors->first() }}
</div>
@endif

@if($isChief)
    @include('housekeeping.partials.chief')
@else
    @include('housekeeping.partials.member')
@endif

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
