{{--
    Filtres de la liste des demandes.

    En GET, et repris tels quels par l'export : le document imprimé porte les
    mêmes critères que l'écran, et les affiche en en-tête — un tableau sans ses
    filtres ne se relit pas six mois plus tard.
--}}
<form method="GET" action="{{ route('economat.requisitions.index') }}"
      class="mb-4 rounded-xl border border-secondary/20 bg-white px-4 py-3">
    <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-[12rem] flex-1">
            <label for="recherche" class="block text-[11px] font-medium text-primary/50">Numéro ou motif</label>
            <input type="search" name="recherche" id="recherche" value="{{ request('recherche') }}"
                   placeholder="DEM-000123, « produits d'entretien »…"
                   class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-1.5 text-sm outline-none focus:border-primary">
        </div>

        <div>
            <label for="statut" class="block text-[11px] font-medium text-primary/50">Statut</label>
            <select name="statut" id="statut"
                    class="mt-1 rounded-lg border border-secondary/30 px-2.5 py-1.5 text-sm outline-none focus:border-primary">
                <option value="">Tous</option>
                @foreach(\App\Models\StockRequisition::STATUSES as $cle => $libelle)
                    <option value="{{ $cle }}" @selected(request('statut') === $cle)>{{ $libelle }}</option>
                @endforeach
            </select>
        </div>

        @if($isKeeper)
            <div>
                <label for="service" class="block text-[11px] font-medium text-primary/50">Service</label>
                <select name="service" id="service"
                        class="mt-1 rounded-lg border border-secondary/30 px-2.5 py-1.5 text-sm outline-none focus:border-primary">
                    <option value="">Tous</option>
                    @foreach(\App\Models\StockRequisition::DEPARTMENTS as $cle => $libelle)
                        <option value="{{ $cle }}" @selected(request('service') === $cle)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <label for="du" class="block text-[11px] font-medium text-primary/50">Du</label>
            <input type="date" name="du" id="du" value="{{ request('du') }}"
                   class="mt-1 rounded-lg border border-secondary/30 px-2.5 py-1.5 text-sm outline-none focus:border-primary">
        </div>

        <div>
            <label for="au" class="block text-[11px] font-medium text-primary/50">Au</label>
            <input type="date" name="au" id="au" value="{{ request('au') }}"
                   class="mt-1 rounded-lg border border-secondary/30 px-2.5 py-1.5 text-sm outline-none focus:border-primary">
        </div>

        <div class="flex items-center gap-2">
            <button type="submit"
                    class="rounded-lg bg-primary px-3.5 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-surface-dark">
                Filtrer
            </button>
            @if($filtres)
                <a href="{{ route('economat.requisitions.index') }}"
                   class="text-xs font-medium text-primary/50 hover:text-primary">Réinitialiser</a>
            @endif
        </div>
    </div>

    @if($filtres)
        <p class="mt-2 text-[11px] text-primary/40">
            {{ $requisitions->total() }} demande(s) —
            {{ collect($filtres)->map(fn ($v, $k) => $k . ' : ' . $v)->implode(' · ') }}
        </p>
    @endif
</form>
