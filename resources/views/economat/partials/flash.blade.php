@php($inventaireEnCours = \App\Models\StockCount::inProgress())
@if($inventaireEnCours)
    <div class="mb-4 px-4 py-3 bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-lg flex items-center gap-2" role="status">
        <i data-lucide="lock" class="w-4 h-4 shrink-0"></i>
        <span>
            Inventaire <strong>{{ $inventaireEnCours->reference }}</strong> en cours : réceptions, livraisons, ajustements
            et validations de demandes sont suspendus jusqu'à sa clôture ou son annulation.
            @droit('economat.stock_counts.voir')
                <a href="{{ route('economat.stock_counts.show', $inventaireEnCours) }}" class="underline font-medium">Voir l'inventaire</a>
            @enddroit
        </span>
    </div>
@endif
@if(session('success'))
    <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg flex items-center gap-2">
        <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
        {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg flex items-center gap-2">
        <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
        {{ session('error') }}
    </div>
@endif
@if($errors->any())
    <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
