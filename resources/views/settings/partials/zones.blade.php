{{--
    Onglet Économat : les zones de rangement du magasin (cave, épicerie,
    chambres froides…). Chaque article se range dans une zone ; les fiches de
    comptage, les inventaires et les alertes se filtrent par zone.
--}}
@php
    $zones = \App\Models\StorageZone::query()->withCount('items')->dansLOrdre()->get();
    $droits = app(\App\Services\PermissionResolver::class);
    $peutModifierZone = $droits->allows(auth()->user(), 'economat.zones.modifier');
    $peutSupprimerZone = $droits->allows(auth()->user(), 'economat.zones.supprimer');
@endphp

<div class="max-w-4xl mt-10">
    <div>
        <h2 class="text-lg font-semibold text-primary">Zones de rangement</h2>
        <p class="mt-1 text-sm text-primary/60">
            Les endroits où l'économat range la marchandise. Chaque article a sa zone : la réception dit où le ranger,
            et le comptage ou l'inventaire peut se faire zone par zone. Le stock d'un article reste un seul stock.
        </p>
    </div>

    <div class="mt-6 overflow-hidden rounded-xl border border-secondary/20 bg-white shadow-xs">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-secondary/15 text-xs">
                <thead class="bg-gray-50/80 text-[11px] font-semibold uppercase tracking-wider text-primary/60">
                    <tr>
                        <th class="px-4 py-3 text-left">Zone</th>
                        <th class="px-3 py-3 text-center">Articles</th>
                        <th class="px-3 py-3 text-center">Statut</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @forelse($zones as $zone)
                        <tr class="hover:bg-gray-50/50 transition-colors {{ $zone->is_active ? '' : 'opacity-65 bg-gray-50/30' }}"
                            x-data="{ modeEdition: false }">
                            <td class="px-4 py-3">
                                <template x-if="!modeEdition">
                                    <div class="font-semibold text-primary text-sm">{{ $zone->name }}</div>
                                </template>
                                <template x-if="modeEdition">
                                    <input type="text" form="form-zone-{{ $zone->id }}" name="name" value="{{ $zone->name }}" required maxlength="60"
                                           aria-label="Nom de la zone"
                                           class="w-56 rounded-md border border-secondary/30 px-2 py-1 text-xs text-primary outline-none focus:border-primary">
                                </template>
                            </td>

                            <td class="px-3 py-3 text-center text-primary/60 font-mono">{{ $zone->items_count }}</td>

                            <td class="px-3 py-3 text-center">
                                <template x-if="!modeEdition">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold {{ $zone->is_active ? 'text-green-700' : 'text-gray-500' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $zone->is_active ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                        {{ $zone->is_active ? 'En service' : 'Hors service' }}
                                    </span>
                                </template>
                                <template x-if="modeEdition">
                                    <label class="inline-flex items-center gap-1.5 text-xs text-primary cursor-pointer">
                                        <input type="hidden" form="form-zone-{{ $zone->id }}" name="is_active" value="0">
                                        <input type="checkbox" form="form-zone-{{ $zone->id }}" name="is_active" value="1" @checked($zone->is_active) class="rounded border-secondary/30">
                                        <span>En service</span>
                                    </label>
                                </template>
                            </td>

                            <td class="px-4 py-3 text-right">
                                @if($peutModifierZone)
                                    <form id="form-zone-{{ $zone->id }}" method="POST" action="{{ route('economat.zones.update', $zone) }}" class="hidden">
                                        @csrf @method('PUT')
                                    </form>
                                @endif

                                @php $supprimable = $peutSupprimerZone && $zone->items_count === 0; @endphp
                                @if($peutModifierZone || $supprimable)
                                    <template x-if="!modeEdition">
                                        <x-menu-actions :label="'Actions pour la zone '.$zone->name" class="inline-block">
                                            @if($peutModifierZone)
                                                <x-table.action icon="pencil" x-on:click="modeEdition = true">Modifier</x-table.action>
                                            @endif
                                            @if($supprimable)
                                                <x-table.action :action="route('economat.zones.destroy', $zone)" method="DELETE" icon="trash-2" tone="danger"
                                                    :confirm="'Supprimer la zone « '.$zone->name.' » ?'">Supprimer</x-table.action>
                                            @endif
                                        </x-menu-actions>
                                    </template>
                                @endif
                                @if($peutModifierZone)
                                    <template x-if="modeEdition">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button type="submit" form="form-zone-{{ $zone->id }}" class="rounded bg-primary px-2.5 py-1 text-xs font-semibold text-white hover:bg-surface-dark">
                                                Enregistrer
                                            </button>
                                            <button type="button" @click="modeEdition = false" class="rounded border border-secondary/30 px-2 py-1 text-xs text-primary/70 hover:bg-gray-100">
                                                Annuler
                                            </button>
                                        </div>
                                    </template>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-primary/50">Aucune zone de rangement.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @droit('economat.zones.creer')
        <form data-reglages method="POST" action="{{ route('economat.zones.store') }}" class="mt-6 rounded-xl border border-secondary/20 bg-white p-5 shadow-xs">
            @csrf
            <h3 class="text-sm font-semibold text-primary flex items-center gap-2">
                <i data-lucide="warehouse" class="w-4 h-4 text-primary"></i> Ajouter une zone de rangement
            </h3>
            <div class="mt-4 flex flex-wrap items-end gap-3">
                <div class="grow min-w-56">
                    <label class="block text-xs font-medium text-primary/70 mb-1" for="ajout-zone-nom">Nom de la zone <span class="text-red-500">*</span></label>
                    <input id="ajout-zone-nom" type="text" name="name" required maxlength="60" placeholder="Ex: Chambre froide 1, Cave, Épicerie"
                           class="w-full rounded-lg border border-secondary/30 px-3 py-2 text-xs text-primary outline-none focus:border-primary">
                </div>
                <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:bg-surface-dark shadow-sm">
                    Ajouter la zone
                </button>
            </div>
        </form>
    @enddroit
</div>
