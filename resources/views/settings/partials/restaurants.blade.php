{{--
    Onglet Restaurant des paramètres : les restaurants de l'hôtel, leurs
    services, leurs salles et leurs équipes.

    La direction les crée et choisit leurs services ; le responsable de
    restaurant compose l'équipe des siens ; le contrôle consulte. Chaque
    bouton pose le droit que pose sa route.
--}}
<div class="flex flex-wrap items-start justify-between gap-3 mb-5">
    <div>
        <h2 class="text-lg font-semibold text-primary">Restaurants</h2>
        <p class="text-sm text-primary/50 mt-0.5 max-w-3xl">
            Chaque restaurant active ses services — salle, cuisine, bar, stock — et a sa carte, sa caisse et son équipe.
            Le personnel ne voit que les restaurants où il est affecté ; la direction les voit tous.
        </p>
    </div>
    @droit('restaurant.restaurants.creer')
        <button type="button" onclick="document.getElementById('restaurant-create').classList.remove('hidden')"
            class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:opacity-95">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i> Nouveau restaurant
        </button>
    @enddroit
</div>

@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="grid gap-5 lg:grid-cols-2">
    @forelse($restaurants as $restaurant)
        <article class="rounded-xl border border-secondary/20 bg-white shadow-sm {{ $restaurant->is_active ? '' : 'opacity-70' }}">
            <header class="flex flex-wrap items-start justify-between gap-3 border-b border-secondary/15 px-5 py-4">
                <div class="min-w-0">
                    <h3 class="font-heading text-lg font-semibold text-primary">{{ $restaurant->name }}</h3>
                    <p class="mt-0.5 text-xs text-primary/50">
                        Code {{ $restaurant->code }}
                        @if($restaurant->series_prefix) · série {{ $restaurant->series_prefix }} @endif
                        · {{ implode(' et ', array_map('mb_strtolower', $restaurant->libellesModes())) }}
                        @unless($restaurant->is_active) · <span class="font-semibold text-red-600">fermé</span> @endunless
                    </p>
                </div>
                @droit('restaurant.restaurants.modifier')
                    <button type="button" onclick="document.getElementById('restaurant-edit-{{ $restaurant->id }}').classList.remove('hidden')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-secondary/25 px-3 py-1.5 text-xs font-semibold text-primary hover:bg-accent/20">
                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Modifier
                    </button>
                @enddroit
            </header>

            <section class="px-5 py-4" aria-label="Services de {{ $restaurant->name }}">
                <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-primary/60">Services</h4>
                <ul class="flex flex-wrap gap-1.5">
                    @foreach($servicesRestaurant as $cle => $libelle)
                        @php $actif = $restaurant->offre($cle); @endphp
                        <li class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-semibold
                                   {{ $actif ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-400 line-through' }}">
                            <i data-lucide="{{ $actif ? 'check' : 'minus' }}" class="w-3 h-3" aria-hidden="true"></i>
                            {{ $libelle }}<span class="sr-only">{{ $actif ? ' : activé' : ' : désactivé' }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="border-t border-secondary/15 px-5 py-4">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-primary/60">Équipe ({{ $restaurant->users->count() }})</h4>
                    @droit('restaurant.restaurants.team.modifier')
                        <button type="button" onclick="document.getElementById('restaurant-team-{{ $restaurant->id }}').classList.remove('hidden')"
                            class="text-xs font-semibold text-primary underline-offset-2 hover:underline">Composer l'équipe</button>
                    @enddroit
                </div>
                @if($restaurant->users->isEmpty())
                    <p class="text-xs text-primary/45">Personne n'est encore affecté à ce restaurant.</p>
                @else
                    <ul class="flex flex-wrap gap-1.5">
                        @foreach($restaurant->users as $membre)
                            <li class="rounded-lg bg-primary/5 px-2 py-1 text-[11px] text-primary">{{ $membre->name }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="border-t border-secondary/15 px-5 py-4">
                <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-primary/60">Salles</h4>
                @if(! $restaurant->offre(\App\Models\PointOfSale::SERVICE_SALLE))
                    <p class="text-xs text-primary/45">Ce restaurant ne sert pas en salle.</p>
                @else
                    @if($restaurant->spaces->isEmpty())
                        <p class="text-xs text-primary/45">Aucune salle : ajoutez-en pour y organiser des banquets.</p>
                    @else
                        <ul class="space-y-1.5">
                            @foreach($restaurant->spaces as $salle)
                                <li class="flex flex-wrap items-center justify-between gap-2 text-xs {{ $salle->is_active ? 'text-primary' : 'text-primary/40 line-through' }}">
                                    <span>{{ $salle->name }}@if($salle->capacity) · {{ $salle->capacity }} places @endif</span>
                                    @droit('restaurant.restaurants.spaces.modifier')
                                        <form method="POST" action="{{ route('restaurant.restaurants.spaces.update', $salle) }}" class="flex items-center gap-1.5">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="name" value="{{ $salle->name }}">
                                            <input type="hidden" name="capacity" value="{{ $salle->capacity }}">
                                            <input type="hidden" name="is_active" value="{{ $salle->is_active ? 0 : 1 }}">
                                            <button type="submit" class="text-[11px] font-semibold text-primary/60 hover:text-primary">
                                                {{ $salle->is_active ? 'Fermer' : 'Rouvrir' }}
                                            </button>
                                        </form>
                                    @enddroit
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @droit('restaurant.restaurants.spaces.creer')
                        <form method="POST" action="{{ route('restaurant.restaurants.spaces.store', $restaurant) }}" class="mt-3 flex flex-wrap items-end gap-2">
                            @csrf
                            <label class="flex-1 min-w-[10rem]">
                                <span class="sr-only">Nom de la salle</span>
                                <input type="text" name="name" required maxlength="100" placeholder="Nouvelle salle (ex. Terrasse)"
                                    class="w-full rounded-lg border border-secondary/30 px-3 py-1.5 text-xs focus:border-secondary outline-none">
                            </label>
                            <label class="w-24">
                                <span class="sr-only">Capacité</span>
                                <input type="number" name="capacity" min="1" max="5000" placeholder="Places"
                                    class="w-full rounded-lg border border-secondary/30 px-3 py-1.5 text-xs focus:border-secondary outline-none">
                            </label>
                            <button type="submit" class="rounded-lg bg-accent/30 px-3 py-1.5 text-xs font-semibold text-primary hover:bg-accent/40">Ajouter</button>
                        </form>
                    @enddroit
                @endif
            </section>
        </article>
    @empty
        <p class="col-span-full rounded-xl bg-gray-50 px-4 py-10 text-center text-sm text-primary/50">
            Aucun restaurant.
            @droit('restaurant.restaurants.creer') Créez le premier avec « Nouveau restaurant ». @enddroit
        </p>
    @endforelse
</div>

@droit('restaurant.restaurants.creer')
    <x-modal id="restaurant-create" title="Nouveau restaurant" max-width="max-w-2xl" formAction="{{ route('restaurant.restaurants.store') }}">
        @include('restaurant.restaurants._champs', ['r' => null])
        <x-slot:footer>
            <button type="button" onclick="document.getElementById('restaurant-create').classList.add('hidden')" class="px-4 py-2 text-xs font-medium rounded-lg border border-secondary/20 text-primary hover:bg-accent/20">Annuler</button>
            <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">Créer le restaurant</button>
        </x-slot:footer>
    </x-modal>
@enddroit

@foreach($restaurants as $restaurant)
    @droit('restaurant.restaurants.modifier')
        <x-modal id="restaurant-edit-{{ $restaurant->id }}" title="Modifier {{ $restaurant->name }}" max-width="max-w-2xl" formAction="{{ route('restaurant.restaurants.update', $restaurant) }}">
            @method('PUT')
            @include('restaurant.restaurants._champs', ['r' => $restaurant])
            <label class="flex items-center gap-2 text-xs text-primary">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('form_restaurant') === (string) $restaurant->id ? old('is_active') : $restaurant->is_active) class="rounded border-secondary/30">
                Restaurant ouvert
            </label>
            <x-slot:footer>
                <button type="button" onclick="document.getElementById('restaurant-edit-{{ $restaurant->id }}').classList.add('hidden')" class="px-4 py-2 text-xs font-medium rounded-lg border border-secondary/20 text-primary hover:bg-accent/20">Annuler</button>
                <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">Enregistrer</button>
            </x-slot:footer>
        </x-modal>
    @enddroit

    @droit('restaurant.restaurants.team.modifier')
        <x-modal id="restaurant-team-{{ $restaurant->id }}" title="Équipe — {{ $restaurant->name }}" subtitle="Une personne peut travailler dans plusieurs restaurants ; elle ne voit que ceux-là." formAction="{{ route('restaurant.restaurants.team.update', $restaurant) }}">
            @method('PUT')
            @php $membres = $restaurant->users->pluck('id')->all(); @endphp
            @if($personnel->isEmpty())
                <p class="text-xs text-primary/50">Aucun compte du personnel de restaurant. Créez-les depuis la gestion des utilisateurs.</p>
            @else
                <div class="max-h-80 space-y-1 overflow-y-auto">
                    @foreach($personnel as $personne)
                        <label class="flex items-center justify-between gap-3 rounded-lg px-2 py-1.5 hover:bg-accent/10">
                            <span class="flex items-center gap-2 text-sm text-primary">
                                <input type="checkbox" name="users[]" value="{{ $personne->id }}" @checked(in_array($personne->id, $membres, true)) class="rounded border-secondary/30">
                                {{ $personne->name }}
                            </span>
                            <span class="text-[11px] text-primary/45">{{ $personne->roles->pluck('name')->implode(', ') ?: $personne->role }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
            <x-slot:footer>
                <button type="button" onclick="document.getElementById('restaurant-team-{{ $restaurant->id }}').classList.add('hidden')" class="px-4 py-2 text-xs font-medium rounded-lg border border-secondary/20 text-primary hover:bg-accent/20">Annuler</button>
                <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">Enregistrer l'équipe</button>
            </x-slot:footer>
        </x-modal>
    @enddroit
@endforeach

{{-- Après un refus, le formulaire qui l'a reçu se rouvre avec sa saisie. --}}
@if($errors->any() && old('form_restaurant'))
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const id = @js(old('form_restaurant') === 'nouveau' ? 'restaurant-create' : 'restaurant-edit-' . old('form_restaurant'));
            document.getElementById(id)?.classList.remove('hidden');
        });
    </script>
@endif
