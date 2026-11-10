{{--
    Onglet Horaires & quarts : régimes horaires configurables et leurs plages.
    - Horaire fixe (sans quart, ex. Administration 08:00–17:00 Lun–Ven) :
      pré-rempli automatiquement chaque semaine selon les jours ouvrés.
    - Système de quarts / Jour-Nuit (ex. 08:00–15:00 & 15:00–22:00, ou Jour/Nuit) :
      planifié semaine par semaine dans le planning.
    Chaque service porte un régime par défaut ; chaque collaborateur peut recevoir
    une exception individuelle depuis sa fiche ou sa modification.
--}}
@php
    $regimes = \App\Models\WorkRegime::query()
        ->dansLOrdre()
        ->with([
            'departments' => fn ($q) => $q->orderBy('sort_order')->orderBy('name'),
            'shifts'      => fn ($q) => $q->dansLOrdre()->withCount('assignments'),
        ])
        ->withCount('users')
        ->get();

    $departementsDisponibles = \App\Models\Department::query()
        ->where('is_active', true)
        ->with('workRegime:id,name')
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get();

    $orphelins = \App\Models\WorkShift::query()
        ->whereNull('work_regime_id')
        ->dansLOrdre()
        ->withCount('assignments')
        ->get();

    $joursAnciens = collect(old('working_days', [1, 2, 3, 4, 5]))->map(fn ($j) => (int) $j)->all();
    $deptsAnciens = collect(old('department_ids', []))->map(fn ($id) => (int) $id)->all();
@endphp

<div class="regimes-form-scope max-w-4xl">
    <h2 class="text-lg font-semibold text-primary">Régimes horaires et Quarts de travail</h2>
    <p class="mt-1 text-sm text-primary/65">
        Adaptez l'organisation horaire à chaque service : <strong>horaire fixe</strong> (sans quart, pré-rempli automatiquement sur les jours ouvrés) ou <strong>système de quarts / Jour-Nuit</strong> planifié dans le
        <a href="{{ route('planning.index') }}" class="font-semibold text-primary underline underline-offset-2 hover:opacity-80">planning</a>.
        Une plage qui finit avant l'heure où elle commence se termine le lendemain. Pour un collaborateur précis, une exception individuelle reste possible depuis
        <a href="{{ route('users.index') }}" class="font-semibold text-primary underline underline-offset-2 hover:opacity-80">Personnel</a>.
    </p>

    @if($errors->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
            <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="mt-6 space-y-6">
        @forelse($regimes as $regime)
            @php
                $totalAffectationsRegime = $regime->shifts->sum('assignments_count');
                $joursRegime = $regime->joursTravailles();
                $modeActuel = $regime->mode;
            @endphp
            <section aria-labelledby="regime-heading-{{ $regime->id }}"
                     class="rounded-2xl border border-secondary/25 bg-gray-50/70 p-5 shadow-xs {{ $regime->is_active ? '' : 'opacity-75' }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 id="regime-heading-{{ $regime->id }}" class="text-base font-semibold text-primary">{{ $regime->name }}</h3>
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $regime->estFixe() ? 'bg-blue-50 text-blue-800 border border-blue-200' : 'bg-accent/50 text-primary border border-secondary/30' }}">
                                {{ $regime->estFixe() ? 'Horaire fixe' : 'Système de quarts' }}
                            </span>
                            @if($regime->is_default)
                                <span class="rounded-full border border-green-200 bg-green-50 px-2.5 py-0.5 text-[11px] font-semibold text-green-800">Par défaut</span>
                            @endif
                            @unless($regime->is_active)
                                <span class="rounded-full border border-red-200 bg-red-50 px-2.5 py-0.5 text-[11px] font-semibold text-red-700">Hors service</span>
                            @endunless
                        </div>
                        <p class="mt-1 text-xs text-primary/60">
                            @if($regime->estFixe())
                                Jours ouvrés : <strong>{{ $regime->workingDaysLabel() }}</strong> ·
                            @endif
                            @if($regime->departments->isNotEmpty())
                                Services : {{ $regime->departments->pluck('name')->implode(', ') }}
                            @else
                                Aucun service rattaché directement
                            @endif
                            @if($regime->users_count > 0)
                                · {{ $regime->users_count }} exception(s) individuelle(s)
                            @endif
                        </p>
                    </div>
                </div>

                @droit('settings.regimes.modifier')
                    <form data-reglages aria-labelledby="regime-heading-{{ $regime->id }}"
                          method="POST" action="{{ route('settings.regimes.update', $regime) }}"
                          x-data="{ mode: @js($modeActuel) }"
                          class="mt-4 space-y-4 rounded-xl border border-secondary/15 bg-white p-4">
                        @csrf @method('PUT')
                        <input type="hidden" name="working_days_present" value="1">
                        <input type="hidden" name="department_ids_present" value="1">

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-[1.2fr_1fr]">
                            <div>
                                <label for="regime-{{ $regime->id }}-name" class="block text-xs font-semibold text-primary/80">Nom du régime <span aria-hidden="true">*</span></label>
                                <input id="regime-{{ $regime->id }}-name" type="text" name="name" value="{{ $regime->name }}" required maxlength="80"
                                       class="mt-1 w-full rounded-lg border border-secondary/35 bg-white px-3 py-2 text-base sm:text-sm text-primary">
                            </div>

                            <fieldset>
                                <legend class="text-xs font-semibold text-primary/80">Type d'organisation</legend>
                                <div class="mt-1 flex flex-wrap gap-2">
                                    <label for="regime-{{ $regime->id }}-mode-fixed"
                                           class="wetchah-check rounded-lg border px-2.5 py-1.5 text-xs font-medium text-primary transition-colors"
                                           :class="mode === 'fixed' ? 'border-primary bg-accent/25 font-semibold' : 'border-secondary/30 bg-white'">
                                        <input id="regime-{{ $regime->id }}-mode-fixed" type="radio" name="mode" value="fixed" x-model="mode"
                                               @checked($modeActuel === 'fixed') class="wetchah-check-input">
                                        <span>Horaire fixe (sans quart)</span>
                                    </label>
                                    <label for="regime-{{ $regime->id }}-mode-shifts"
                                           class="wetchah-check rounded-lg border px-2.5 py-1.5 text-xs font-medium text-primary transition-colors"
                                           :class="mode === 'shifts' ? 'border-primary bg-accent/25 font-semibold' : 'border-secondary/30 bg-white'">
                                        <input id="regime-{{ $regime->id }}-mode-shifts" type="radio" name="mode" value="shifts" x-model="mode"
                                               @checked($modeActuel === 'shifts') class="wetchah-check-input">
                                        <span>Système de quarts / Jour-Nuit</span>
                                    </label>
                                </div>
                            </fieldset>
                        </div>

                        <fieldset x-show="mode === 'fixed'" @if($modeActuel !== 'fixed') x-cloak @endif
                                  class="rounded-xl border border-secondary/20 bg-gray-50/60 p-3">
                            <legend class="px-1 text-xs font-semibold text-primary/80">Jours ouvrés pré-remplis automatiquement</legend>
                            <div class="mt-1.5 flex flex-wrap gap-2">
                                @foreach(\App\Models\WorkRegime::JOURS_COURTS as $jourIso => $libelleJour)
                                    <label for="regime-{{ $regime->id }}-jour-{{ $jourIso }}"
                                           class="wetchah-check rounded-lg border border-secondary/30 bg-white px-2.5 py-1 text-xs font-medium text-primary">
                                        <input id="regime-{{ $regime->id }}-jour-{{ $jourIso }}" type="checkbox" name="working_days[]" value="{{ $jourIso }}"
                                               @checked(in_array($jourIso, $joursRegime, true)) class="wetchah-check-input">
                                        <span>{{ $libelleJour }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <fieldset class="rounded-xl border border-secondary/20 bg-gray-50/60 p-3">
                            <legend class="px-1 text-xs font-semibold text-primary/80">Services rattachés à ce régime</legend>
                            <div class="mt-1.5 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach($departementsDisponibles as $dept)
                                    @php $estRattache = (int) $dept->work_regime_id === (int) $regime->id; @endphp
                                    <label for="regime-{{ $regime->id }}-dept-{{ $dept->id }}"
                                           class="wetchah-check justify-between rounded-lg border px-2.5 py-1 text-xs {{ $estRattache ? 'border-primary/40 bg-accent/15 text-primary' : 'border-secondary/25 bg-white text-primary/80' }}">
                                        <input id="regime-{{ $regime->id }}-dept-{{ $dept->id }}" type="checkbox" name="department_ids[]" value="{{ $dept->id }}"
                                               @checked($estRattache) class="wetchah-check-input">
                                        <span class="flex-1 truncate">
                                            {{ $dept->name }}
                                            @if(!$estRattache && $dept->workRegime)
                                                <span class="block text-[10px] text-primary/50">Actuel : {{ $dept->workRegime->name }}</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-secondary/10 pt-3">
                            <div class="flex flex-wrap items-center gap-4">
                                <input type="hidden" name="is_active" value="0">
                                <label for="regime-{{ $regime->id }}-active" class="wetchah-check text-xs font-medium text-primary">
                                    <input id="regime-{{ $regime->id }}-active" type="checkbox" name="is_active" value="1"
                                           @checked($regime->is_active) class="wetchah-check-input">
                                    <span>Régime en service</span>
                                </label>

                                <input type="hidden" name="is_default" value="0">
                                <label for="regime-{{ $regime->id }}-default" class="wetchah-check text-xs font-medium text-primary">
                                    <input id="regime-{{ $regime->id }}-default" type="checkbox" name="is_default" value="1"
                                           @checked($regime->is_default) class="wetchah-check-input">
                                    <span>Régime par défaut de l'établissement</span>
                                </label>
                            </div>

                            <button type="submit" class="rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white hover:opacity-95">
                                Enregistrer le régime<span class="sr-only"> {{ $regime->name }}</span>
                            </button>
                        </div>
                    </form>
                @enddroit

                @if(! $regime->is_default && $totalAffectationsRegime === 0)
                    @droit('settings.regimes.supprimer')
                        <div class="mt-2 flex justify-end">
                            <form method="POST" action="{{ route('settings.regimes.destroy', $regime) }}"
                                  onsubmit="return confirm(@js('Supprimer le régime « '.$regime->name.' » et ses plages non utilisées ?'))">
                                @csrf @method('DELETE')
                                <button type="submit" class="rounded-md px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-50 hover:underline">
                                    Supprimer ce régime<span class="sr-only"> ({{ $regime->name }})</span>
                                </button>
                            </form>
                        </div>
                    @enddroit
                @endif

                {{-- Plages horaires / Quarts propres à ce régime --}}
                <div class="mt-4 border-t border-secondary/20 pt-4">
                    <h4 id="regime-{{ $regime->id }}-plages" class="text-xs font-bold uppercase tracking-wider text-primary/70">
                        {{ $regime->estFixe() ? 'Plages de l\'horaire fixe' : 'Quarts de ce régime' }}
                    </h4>

                    <ul class="mt-2.5 space-y-2.5">
                        @forelse($regime->shifts as $quart)
                            <li class="rounded-xl border border-secondary/20 bg-white p-3.5 {{ $quart->is_active ? '' : 'opacity-70' }}">
                                @droit('settings.quarts.modifier')
                                    <form data-reglages aria-labelledby="regime-{{ $regime->id }}-plages"
                                          method="POST" action="{{ route('settings.quarts.update', $quart) }}"
                                          class="grid grid-cols-1 items-end gap-3 sm:grid-cols-[1fr_7.5rem_7.5rem_auto_auto]">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="work_regime_id" value="{{ $regime->id }}">
                                        <div>
                                            <label for="quart-{{ $quart->id }}-name" class="block text-xs text-primary/65">Nom</label>
                                            <input id="quart-{{ $quart->id }}-name" type="text" name="name" value="{{ $quart->name }}" required maxlength="60"
                                                   class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-base sm:text-sm">
                                        </div>
                                        <div>
                                            <label for="quart-{{ $quart->id }}-start" class="block text-xs text-primary/65">Début</label>
                                            <input id="quart-{{ $quart->id }}-start" type="time" name="starts_at" value="{{ $quart->debut() }}" required
                                                   class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-base sm:text-sm">
                                        </div>
                                        <div>
                                            <label for="quart-{{ $quart->id }}-end" class="block text-xs text-primary/65">Fin</label>
                                            <input id="quart-{{ $quart->id }}-end" type="time" name="ends_at" value="{{ $quart->fin() }}" required
                                                   class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-base sm:text-sm">
                                        </div>
                                        <label for="quart-{{ $quart->id }}-active" class="wetchah-check text-xs text-primary">
                                            <input type="hidden" name="is_active" value="0">
                                            <input id="quart-{{ $quart->id }}-active" type="checkbox" name="is_active" value="1"
                                                   @checked($quart->is_active) class="wetchah-check-input">
                                            <span>En service</span>
                                        </label>
                                        <button type="submit" class="rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white hover:opacity-95">Enregistrer<span class="sr-only"> {{ $quart->name }}</span></button>
                                    </form>
                                @else
                                    <p class="text-sm font-semibold text-primary">{{ $quart->name }} <span class="font-normal text-primary/60">· {{ $quart->horaire() }}</span></p>
                                @enddroit

                                <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-[11px] text-primary/55">
                                    <span>{{ $quart->horaire() }} · {{ rtrim(rtrim(number_format($quart->duree(), 2, ',', ''), '0'), ',') }} h · {{ $quart->assignments_count }} affectation(s)</span>
                                    @if($quart->assignments_count === 0)
                                        @droit('settings.quarts.supprimer')
                                            <form method="POST" action="{{ route('settings.quarts.destroy', $quart) }}"
                                                  onsubmit="return confirm(@js('Supprimer la plage « '.$quart->name.' » ?'))">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="font-semibold text-red-700 hover:underline">Supprimer<span class="sr-only"> {{ $quart->name }}</span></button>
                                            </form>
                                        @enddroit
                                    @endif
                                </div>
                            </li>
                        @empty
                            <li class="rounded-xl border border-dashed border-secondary/30 bg-white px-4 py-5 text-center text-xs text-primary/55">
                                Aucune plage horaire définie dans ce régime : ajoutez-en une ci-dessous.
                            </li>
                        @endforelse
                    </ul>

                    @droit('settings.quarts.creer')
                        <form data-reglages aria-labelledby="ajout-quart-heading-{{ $regime->id }}"
                              method="POST" action="{{ route('settings.quarts.store') }}"
                              class="mt-3 rounded-xl border border-dashed border-secondary/30 bg-white/80 p-3.5">
                            @csrf
                            <input type="hidden" name="work_regime_id" value="{{ $regime->id }}">
                            <h5 id="ajout-quart-heading-{{ $regime->id }}" class="text-xs font-semibold text-primary">
                                Ajouter une plage horaire à « {{ $regime->name }} »
                            </h5>
                            <div class="mt-2.5 grid grid-cols-1 items-end gap-3 sm:grid-cols-[1fr_7.5rem_7.5rem_auto]">
                                <div>
                                    <label for="new-quart-{{ $regime->id }}-name" class="block text-xs text-primary/65">Nom</label>
                                    <input id="new-quart-{{ $regime->id }}-name" type="text" name="name" required maxlength="60"
                                           placeholder="{{ $regime->estFixe() ? 'Ex. Journée' : 'Ex. Quart Matin' }}"
                                           class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-base sm:text-sm">
                                </div>
                                <div>
                                    <label for="new-quart-{{ $regime->id }}-start" class="block text-xs text-primary/65">Début</label>
                                    <input id="new-quart-{{ $regime->id }}-start" type="time" name="starts_at" required
                                           class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-base sm:text-sm">
                                </div>
                                <div>
                                    <label for="new-quart-{{ $regime->id }}-end" class="block text-xs text-primary/65">Fin</label>
                                    <input id="new-quart-{{ $regime->id }}-end" type="time" name="ends_at" required
                                           class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-base sm:text-sm">
                                </div>
                                <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:opacity-95">Ajouter<span class="sr-only"> une plage à {{ $regime->name }}</span></button>
                            </div>
                        </form>
                    @enddroit
                </div>
            </section>
        @empty
            <p class="rounded-xl border border-dashed border-secondary/30 px-4 py-8 text-center text-sm text-primary/55">
                Aucun régime horaire configuré : créez-en un ci-dessous.
            </p>
        @endforelse

        @if($orphelins->isNotEmpty())
            <section aria-labelledby="quarts-sans-regime" class="rounded-2xl border border-amber-200 bg-amber-50/40 p-4">
                <h3 id="quarts-sans-regime" class="text-sm font-semibold text-primary">Plages sans régime attribué</h3>
                <ul class="mt-2 space-y-2">
                    @foreach($orphelins as $quart)
                        <li class="rounded-xl border border-secondary/20 bg-white p-3.5 text-sm text-primary">
                            {{ $quart->name }} · {{ $quart->horaire() }}
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>

    {{-- Création d'un nouveau régime horaire --}}
    @droit('settings.regimes.creer')
        @php $modeCreation = old('mode', 'fixed'); @endphp
        <form data-reglages aria-labelledby="nouveau-regime-heading"
              method="POST" action="{{ route('settings.regimes.store') }}"
              x-data="{ mode: @js($modeCreation) }"
              class="mt-8 space-y-4 rounded-2xl border border-secondary/30 bg-white p-5 shadow-xs">
            @csrf
            <div>
                <h3 id="nouveau-regime-heading" class="text-base font-semibold text-primary">Nouveau régime horaire</h3>
                <p class="mt-0.5 text-xs text-primary/60">Créez un régime personnalisé (par exemple une rotation 08:00–18:00 / 18:00–08:00 ou un horaire de bureau) et rattachez-lui ses services.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-[1.2fr_1fr]">
                <div>
                    <label for="new-regime-name" class="block text-xs font-semibold text-primary/80">Nom du régime <span aria-hidden="true">*</span></label>
                    <input id="new-regime-name" type="text" name="name" value="{{ old('name') }}" required maxlength="80"
                           placeholder="Ex. Garde 08h–18h / 18h–08h"
                           class="mt-1 w-full rounded-lg border border-secondary/35 px-3 py-2 text-base sm:text-sm">
                </div>

                <fieldset>
                    <legend class="text-xs font-semibold text-primary/80">Type d'organisation</legend>
                    <div class="mt-1 flex flex-wrap gap-2">
                        <label for="new-regime-mode-fixed"
                               class="wetchah-check rounded-lg border px-2.5 py-1.5 text-xs font-medium text-primary transition-colors"
                               :class="mode === 'fixed' ? 'border-primary bg-accent/25 font-semibold' : 'border-secondary/30 bg-white'">
                            <input id="new-regime-mode-fixed" type="radio" name="mode" value="fixed" x-model="mode"
                                   @checked($modeCreation === 'fixed') class="wetchah-check-input">
                            <span>Horaire fixe (sans quart)</span>
                        </label>
                        <label for="new-regime-mode-shifts"
                               class="wetchah-check rounded-lg border px-2.5 py-1.5 text-xs font-medium text-primary transition-colors"
                               :class="mode === 'shifts' ? 'border-primary bg-accent/25 font-semibold' : 'border-secondary/30 bg-white'">
                            <input id="new-regime-mode-shifts" type="radio" name="mode" value="shifts" x-model="mode"
                                   @checked($modeCreation === 'shifts') class="wetchah-check-input">
                            <span>Système de quarts / Jour-Nuit</span>
                        </label>
                    </div>
                </fieldset>
            </div>

            <fieldset x-show="mode === 'fixed'" @if($modeCreation !== 'fixed') x-cloak @endif
                      class="rounded-xl border border-secondary/20 bg-gray-50/60 p-3">
                <legend class="px-1 text-xs font-semibold text-primary/80">Jours ouvrés par défaut</legend>
                <div class="mt-1.5 flex flex-wrap gap-2">
                    @foreach(\App\Models\WorkRegime::JOURS_COURTS as $jourIso => $libelleJour)
                        <label for="new-regime-jour-{{ $jourIso }}"
                               class="wetchah-check rounded-lg border border-secondary/30 bg-white px-2.5 py-1 text-xs font-medium text-primary">
                            <input id="new-regime-jour-{{ $jourIso }}" type="checkbox" name="working_days[]" value="{{ $jourIso }}"
                                   @checked(in_array($jourIso, $joursAnciens, true)) class="wetchah-check-input">
                            <span>{{ $libelleJour }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <fieldset class="rounded-xl border border-secondary/20 bg-gray-50/60 p-3">
                <legend class="px-1 text-xs font-semibold text-primary/80">Services utilisant ce régime (facultatif)</legend>
                <div class="mt-1.5 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($departementsDisponibles as $dept)
                        <label for="new-regime-dept-{{ $dept->id }}"
                               class="wetchah-check rounded-lg border border-secondary/25 bg-white px-2.5 py-1 text-xs text-primary/85">
                            <input id="new-regime-dept-{{ $dept->id }}" type="checkbox" name="department_ids[]" value="{{ $dept->id }}"
                                   @checked(in_array((int) $dept->id, $deptsAnciens, true)) class="wetchah-check-input">
                            <span class="truncate">{{ $dept->name }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <fieldset class="rounded-xl border border-secondary/20 p-3">
                <legend class="px-1 text-xs font-semibold text-primary/80">Première plage horaire (facultatif)</legend>
                <div class="mt-1.5 grid grid-cols-1 items-end gap-3 sm:grid-cols-[1fr_7.5rem_7.5rem]">
                    <div>
                        <label for="new-regime-starter-name" class="block text-xs text-primary/65">Nom de la plage</label>
                        <input id="new-regime-starter-name" type="text" name="starter_name" value="{{ old('starter_name') }}" maxlength="60"
                               placeholder="Ex. Journée ou Quart Jour"
                               class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-base sm:text-sm">
                    </div>
                    <div>
                        <label for="new-regime-starter-start" class="block text-xs text-primary/65">Début</label>
                        <input id="new-regime-starter-start" type="time" name="starter_starts_at" value="{{ old('starter_starts_at') }}"
                               class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-base sm:text-sm">
                    </div>
                    <div>
                        <label for="new-regime-starter-end" class="block text-xs text-primary/65">Fin</label>
                        <input id="new-regime-starter-end" type="time" name="starter_ends_at" value="{{ old('starter_ends_at') }}"
                               class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-base sm:text-sm">
                    </div>
                </div>
            </fieldset>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                <input type="hidden" name="is_default" value="0">
                <label for="new-regime-default" class="wetchah-check text-xs font-medium text-primary">
                    <input id="new-regime-default" type="checkbox" name="is_default" value="1"
                           @checked((bool) old('is_default')) class="wetchah-check-input">
                    <span>Définir comme régime par défaut de l'établissement</span>
                </label>

                <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:opacity-95">
                    Créer le régime
                </button>
            </div>
        </form>
    @enddroit
</div>
