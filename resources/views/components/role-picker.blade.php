@props([
    'rolesByModule',
    'moduleLabels' => [],
    'selected' => [],       // slugs déjà cochés (édition)
    'levels' => [],         // slug => 'read'|'write' (édition)
])

{{--
    Sélection des rôles sous forme de cartes cochables. Chaque carte porte
    l'icône du rôle ; une fois cochée, elle déplie le choix du niveau d'accès
    (lecture seule / lecture-écriture). Un utilisateur peut cumuler plusieurs
    modules avec des niveaux différents.

    Soumet : roles[] (slugs cochés) et levels[slug] (niveau du rôle coché).
--}}
<div class="space-y-4">
    @foreach($rolesByModule as $module => $moduleRoles)
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/40 mb-2">
                {{ $moduleLabels[$module] ?? 'Autre' }}
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach($moduleRoles as $role)
                    @php
                        $isChecked = in_array($role->slug, (array) $selected, true);
                        $lvl = $levels[$role->slug] ?? 'write';
                    @endphp
                    <div x-data="{ checked: @js($isChecked), level: @js($lvl) }"
                         @click="checked = !checked"
                         :class="checked ? 'border-primary bg-accent/20' : 'border-secondary/20 hover:bg-accent/5'"
                         class="cursor-pointer rounded-xl border p-3 transition-colors select-none">

                        <input type="checkbox" name="roles[]" value="{{ $role->slug }}" x-model="checked" class="sr-only">
                        <template x-if="checked">
                            <input type="hidden" name="levels[{{ $role->slug }}]" :value="level">
                        </template>

                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
                                 :class="checked ? 'bg-primary text-white' : 'bg-accent/30 text-primary/60'">
                                <i data-lucide="{{ $role->iconName() }}" class="w-4 h-4"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-primary truncate">{{ $role->name }}</p>
                                <p class="text-[11px] text-primary/40 line-clamp-1">{{ $role->description }}</p>
                            </div>
                            <div class="w-5 h-5 rounded-md border flex items-center justify-center shrink-0"
                                 :class="checked ? 'bg-primary border-primary' : 'border-secondary/40'">
                                <i data-lucide="check" class="w-3 h-3 text-white" x-show="checked" x-cloak></i>
                            </div>
                        </div>

                        {{-- Niveau d'accès, déplié une fois le rôle coché --}}
                        <div x-show="checked" x-cloak class="mt-3 flex gap-2" @click.stop>
                            <button type="button" @click="level = 'read'"
                                :class="level === 'read' ? 'bg-primary text-white border-primary' : 'bg-white text-primary/60 border-secondary/30 hover:border-secondary'"
                                class="flex-1 inline-flex items-center justify-center gap-1 px-2 py-1.5 text-[11px] font-medium rounded-lg border transition-colors">
                                <i data-lucide="eye" class="w-3 h-3"></i> Lecture
                            </button>
                            <button type="button" @click="level = 'write'"
                                :class="level === 'write' ? 'bg-primary text-white border-primary' : 'bg-white text-primary/60 border-secondary/30 hover:border-secondary'"
                                class="flex-1 inline-flex items-center justify-center gap-1 px-2 py-1.5 text-[11px] font-medium rounded-lg border transition-colors">
                                <i data-lucide="pencil" class="w-3 h-3"></i> Lecture / écriture
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
