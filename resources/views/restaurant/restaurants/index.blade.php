@extends('layouts.hotel')

@section('title', 'Restaurants')

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Restaurants</h1>
        <p class="text-sm text-primary/50 mt-0.5 max-w-3xl">
            Chaque restaurant a sa carte, sa cuisine et son bar, son garde-manger, sa caisse et son équipe.
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

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
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
                    <h2 class="font-heading text-lg font-semibold text-primary">{{ $restaurant->name }}</h2>
                    <p class="mt-0.5 text-xs text-primary/50">
                        Code {{ $restaurant->code }}
                        @if($restaurant->series_prefix) · série {{ $restaurant->series_prefix }} @endif
                        @unless($restaurant->is_active) · <span class="font-semibold text-red-600">fermé</span> @endunless
                    </p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach($restaurant->libellesModes() as $mode)
                            <span class="rounded-full bg-accent/40 px-2 py-0.5 text-[11px] font-semibold text-primary">{{ $mode }}</span>
                        @endforeach
                    </div>
                </div>
                @droit('restaurant.restaurants.modifier')
                    <button type="button" onclick="document.getElementById('restaurant-edit-{{ $restaurant->id }}').classList.remove('hidden')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-secondary/25 px-3 py-1.5 text-xs font-semibold text-primary hover:bg-accent/20">
                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Modifier
                    </button>
                @enddroit
            </header>

            <section class="px-5 py-4">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-primary/60">Équipe ({{ $restaurant->users->count() }})</h3>
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
                <h3 class="mb-2 text-xs font-bold uppercase tracking-wider text-primary/60">Salles</h3>
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
            </section>
        </article>
    @empty
        <p class="col-span-full rounded-xl bg-white px-4 py-10 text-center text-sm text-primary/50 shadow-sm">Aucun restaurant.</p>
    @endforelse
</div>

@droit('restaurant.restaurants.creer')
    <x-modal id="restaurant-create" title="Nouveau restaurant" formAction="{{ route('restaurant.restaurants.store') }}">
        @include('restaurant.restaurants._champs', ['r' => null, 'modes' => $modes])
        <x-slot:footer>
            <button type="button" onclick="document.getElementById('restaurant-create').classList.add('hidden')" class="px-4 py-2 text-xs font-medium rounded-lg border border-secondary/20 text-primary hover:bg-accent/20">Annuler</button>
            <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg bg-primary text-white">Créer le restaurant</button>
        </x-slot:footer>
    </x-modal>
@enddroit

@foreach($restaurants as $restaurant)
    @droit('restaurant.restaurants.modifier')
        <x-modal id="restaurant-edit-{{ $restaurant->id }}" title="Modifier {{ $restaurant->name }}" formAction="{{ route('restaurant.restaurants.update', $restaurant) }}">
            @method('PUT')
            @include('restaurant.restaurants._champs', ['r' => $restaurant, 'modes' => $modes])
            <label class="flex items-center gap-2 text-xs text-primary">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked($restaurant->is_active) class="rounded border-secondary/30">
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
@endsection
