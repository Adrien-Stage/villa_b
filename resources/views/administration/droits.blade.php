@extends('layouts.hotel')

@section('title', 'Rôles & droits')

@php
    /**
     * Rôles & droits de l'établissement, réglés par son administrateur.
     *
     * La couche de l'hôtel est la seule que cet écran modifie. Le modèle
     * (catalogue) et la couche de la console d'orchestration s'y lisent ; les
     * exceptions nominatives ont leur onglet. Un refus, quelle que soit sa
     * couche, l'emporte.
     */
    $roles = collect($matrice['roles'])->keyBy('slug');

    $services = [
        'it' => 'Informatique', 'direction' => 'Direction', 'hebergement' => 'Hébergement',
        'housekeeping' => 'Housekeeping', 'restaurant' => 'Restaurant', 'boutique' => 'Boutique',
        'economat' => 'Économat', 'comptabilite' => 'Finances', 'controle' => 'Contrôle',
    ];
    $groupeDe = fn (array $r): string => $r['level'] === null ? 'controle' : ($r['module'] ?? 'autre');
    $rangDuGroupe = fn (string $g): int => ($i = array_search($g, array_keys($services), true)) === false ? 99 : $i;
    $colonnes = $roles
        ->filter(fn (array $r) => $r['reglable'] || $r['slug'] === 'admin')
        ->sortBy(fn (array $r) => sprintf('%02d-%d-%s', $rangDuGroupe($groupeDe($r)), $r['level'] ?? 9, $r['name']))
        ->values();
    $groupes = $colonnes->groupBy($groupeDe);

    $hotel = [];
    $console = [];
    $nominatives = [];
    foreach ($matrice['ecarts'] as $ecart) {
        if ($ecart['subject_type'] === 'role') {
            $cle = $ecart['subject_id'] . '|' . $ecart['permission'];
            if ($ecart['origin'] === 'etablissement') {
                $hotel[$cle] = $ecart;
            } else {
                $console[$cle] = $ecart;
            }
        } else {
            $nominatives[] = $ecart;
        }
    }

    $comptes = collect($matrice['comptes'])->keyBy('id');
    $nomDuCompte = fn ($id) => $comptes[(int) $id]['name'] ?? "Compte #{$id}";
    $nominativesParCase = [];
    foreach ($nominatives as $ecart) {
        foreach ($comptes[(int) $ecart['subject_id']]['roles'] ?? [] as $slug) {
            $nominativesParCase[$slug . '|' . $ecart['permission']][] =
                $nomDuCompte($ecart['subject_id']) . ' : ' . ($ecart['effect'] === 'deny' ? 'refus' : 'autorisation');
        }
    }

    $ecritures = array_flip($matrice['ecritures']);
    $parModule = collect($matrice['catalogue'])->groupBy(fn ($r, $droit) => explode('.', $droit)[0], preserveKeys: true);
    $peutRegler = app(\App\Services\PermissionResolver::class)->allows(auth()->user(), 'droits.modifier');
    $peutExcepter = app(\App\Services\PermissionResolver::class)->allows(auth()->user(), 'droits.exceptions.creer');

    $donneesJs = [
        'cumuls' => $matrice['cumuls'] ?: (object) [],
        'regles' => $matrice['regles_de_cumul'],
        'noms' => $roles->map(fn ($r) => $r['name'])->all(),
        'apercu' => route('droits.apercu'),
    ];
@endphp

@section('content')

<div class="mb-5">
    <h1 class="font-heading text-2xl font-semibold text-primary">Rôles &amp; droits</h1>
    <p class="text-sm text-primary/50 mt-0.5 max-w-3xl">
        Le <strong>modèle</strong> vient de l'application ; la <strong>console</strong> d'orchestration pose sa couche ;
        cet écran règle la <strong>couche de l'hôtel</strong> et les <strong>exceptions nominatives</strong>.
        Un refus, quelle que soit sa couche, l'emporte.
    </p>
</div>

@foreach(['success' => 'border-green-200 bg-green-50 text-green-700', 'error' => 'border-red-200 bg-red-50 text-red-700'] as $cleFlash => $classes)
    @if(session($cleFlash))
        <div class="mb-4 rounded-lg border px-4 py-3 text-sm {{ $classes }}">{{ session($cleFlash) }}</div>
    @endif
@endforeach
@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach</ul>
    </div>
@endif

<nav class="mb-4 flex flex-wrap gap-1 rounded-xl bg-white p-1 shadow-sm" role="tablist" aria-label="Rubriques">
    @foreach(['matrice' => 'Matrice', 'exceptions' => 'Exceptions (' . count($nominatives) . ')', 'alertes' => 'Alertes (' . (count($matrice['constats']) + count($matrice['exceptions_echues'])) . ')'] as $cleOnglet => $libelle)
        <a href="{{ route('droits.index', ['onglet' => $cleOnglet]) }}" role="tab" aria-selected="{{ $onglet === $cleOnglet ? 'true' : 'false' }}"
           class="rounded-lg px-3 py-2 text-xs font-semibold transition {{ $onglet === $cleOnglet ? 'bg-primary text-white' : 'text-primary/60 hover:bg-accent/30 hover:text-primary' }}">
            {{ $libelle }}
        </a>
    @endforeach
</nav>

{{-- ==================== MATRICE ==================== --}}
@if($onglet === 'matrice')
    <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 rounded-xl bg-white px-4 py-3 text-[11px] text-primary/70 shadow-sm">
        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded border-2 border-amber-400"></span> Écart de l'hôtel</span>
        <span class="inline-flex items-center gap-1.5"><span class="rounded bg-red-100 px-1 font-bold text-red-700">C</span> Refus posé par la console</span>
        <span class="inline-flex items-center gap-1.5"><span class="rounded bg-emerald-100 px-1 font-bold text-emerald-700">C</span> Autorisation posée par la console</span>
        <span class="inline-flex items-center gap-1.5"><span class="rounded bg-sky-100 px-1 font-bold text-sky-700">N</span> Exceptions nominatives</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded border-2 border-red-500"></span> Cumul de fonctions incompatibles</span>
    </div>

    <form method="POST" action="{{ route('droits.update') }}" id="form-droits">
        @csrf
        @method('PUT')
        <input type="hidden" name="empreinte" value="{{ $empreinte }}">

        <div class="mb-3 flex flex-wrap items-center gap-2 rounded-xl bg-white px-3 py-2.5 shadow-sm">
            <label for="recherche" class="sr-only">Filtrer les droits</label>
            <input type="search" id="recherche" autocomplete="off" placeholder="Filtrer un droit — « economat », « supprimer »…"
                   class="min-w-[14rem] flex-1 rounded-lg border border-secondary/30 px-3 py-1.5 text-xs">
            <label for="filtre-role" class="sr-only">Rôle affiché</label>
            <select id="filtre-role" class="rounded-lg border border-secondary/30 px-2.5 py-1.5 text-xs">
                <option value="">Tous les rôles</option>
                @foreach($groupes as $groupe => $membres)
                    <optgroup label="{{ $services[$groupe] ?? $groupe }}">
                        @foreach($membres as $role)<option value="{{ $role['slug'] }}">{{ $role['name'] }}</option>@endforeach
                    </optgroup>
                @endforeach
            </select>
            <label class="inline-flex items-center gap-1.5 rounded-lg border border-secondary/30 px-2.5 py-1.5 text-xs text-primary/70">
                <input type="checkbox" id="filtre-ecarts" class="rounded border-secondary/40"> Écarts seulement
            </label>
        </div>

        @foreach($parModule as $module => $droits)
            @php
                $ecartsDuModule = 0;
                foreach ($droits as $droit => $detenteurs) {
                    foreach ($colonnes as $role) {
                        if (isset($hotel[$role['slug'] . '|' . $droit])) {
                            $ecartsDuModule++;
                        }
                    }
                }
            @endphp
            <details class="module mb-3 overflow-hidden rounded-xl bg-white shadow-sm" @if($ecartsDuModule > 0) open @endif>
                <summary class="flex cursor-pointer items-center justify-between gap-3 px-4 py-2.5 hover:bg-accent/20">
                    <span class="text-xs font-bold uppercase tracking-wider text-primary">{{ $module }} <span class="font-normal normal-case text-primary/40">{{ count($droits) }} droit(s)</span></span>
                    <span class="badge-ecarts rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 {{ $ecartsDuModule > 0 ? '' : 'hidden' }}"><span class="compte">{{ $ecartsDuModule }}</span> écart(s)</span>
                </summary>
                <div class="max-h-[60vh] overflow-auto border-t border-secondary/15">
                    <table class="w-full border-separate border-spacing-0 text-xs">
                        <thead>
                            <tr>
                                <th class="sticky left-0 top-0 z-30 min-w-[15rem] border-b border-r border-secondary/20 bg-white px-3 py-2 text-left font-semibold text-primary/50">Droit</th>
                                @foreach($colonnes as $index => $role)
                                    @php $debut = $index === 0 || $groupeDe($colonnes[$index - 1]) !== $groupeDe($role); @endphp
                                    <th scope="col" data-colonne="{{ $role['slug'] }}" title="{{ $role['description'] }}"
                                        class="sticky top-0 z-20 min-w-[5.5rem] border-b border-secondary/20 bg-white px-2 py-1.5 text-center align-bottom font-semibold text-primary/70 {{ $debut ? 'border-l-2 border-l-secondary/40' : '' }}">
                                        @if($debut)<span class="block text-left text-[9px] font-bold uppercase tracking-wider text-primary/40">{{ $services[$groupeDe($role)] ?? '' }}</span>@endif
                                        <span class="block text-[10px] leading-tight">{{ $role['name'] }}</span>
                                        <span class="mt-0.5 block text-[9px] font-normal text-primary/40">{{ $role['level'] === null ? 'Transversal' : 'N' . $role['level'] }} · {{ $role['titulaires'] }} pers.</span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($droits as $droit => $detenteurs)
                                <tr>
                                    <th scope="row" class="sticky left-0 z-10 border-b border-r border-secondary/15 bg-white px-3 py-1.5 text-left font-mono text-[11px] font-normal text-primary/80">
                                        {{ $droit }}
                                        @if(!isset($ecritures[$droit]))<span class="ml-1 rounded bg-accent/40 px-1 font-sans text-[9px] text-primary/60">lecture</span>@endif
                                    </th>
                                    @foreach($colonnes as $index => $role)
                                        @php
                                            $cle = $role['slug'] . '|' . $droit;
                                            $gabarit = in_array($role['slug'], $detenteurs, true);
                                            $ecartHotel = $hotel[$cle] ?? null;
                                            $ecartConsole = $console[$cle] ?? null;
                                            $coche = $ecartHotel ? $ecartHotel['effect'] === 'allow' : $gabarit;
                                            $fige = ! $role['reglable'] || ! $peutRegler;
                                            $debut = $index === 0 || $groupeDe($colonnes[$index - 1]) !== $groupeDe($role);
                                            $nominativesIci = $nominativesParCase[$cle] ?? [];
                                        @endphp
                                        <td data-colonne="{{ $role['slug'] }}" class="border-b border-secondary/10 px-2 py-1.5 text-center {{ $debut ? 'border-l-2 border-l-secondary/20' : '' }}">
                                            <div class="inline-flex items-center gap-1">
                                                <input type="checkbox" aria-label="{{ $role['name'] }} — {{ $droit }}"
                                                       data-role="{{ $role['slug'] }}" data-droit="{{ $droit }}"
                                                       data-gabarit="{{ $gabarit ? '1' : '0' }}"
                                                       data-couche="{{ $ecartHotel['effect'] ?? '' }}"
                                                       data-portee-initiale="{{ $ecartHotel['scope'] ?? '' }}"
                                                       data-raison="{{ $ecartHotel['reason'] ?? '' }}"
                                                       @checked($coche) @disabled($fige)
                                                       class="{{ $fige ? '' : 'case-droit' }} rounded border-secondary/40 {{ $ecartHotel ? 'ring-2 ring-amber-400' : '' }}">
                                                @if($ecartConsole)
                                                    <span class="rounded px-1 text-[9px] font-bold {{ $ecartConsole['effect'] === 'deny' ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' }}"
                                                          title="Posé par la console : {{ $ecartConsole['effect'] === 'deny' ? 'refus' : 'autorisation' }}{{ $ecartConsole['reason'] ? ' — ' . $ecartConsole['reason'] : '' }}">C</span>
                                                @endif
                                                @if($nominativesIci !== [])
                                                    <span class="rounded bg-sky-100 px-1 text-[9px] font-bold text-sky-700" title="{{ implode(' ; ', $nominativesIci) }}">N{{ count($nominativesIci) }}</span>
                                                @endif
                                            </div>
                                            @if(in_array($droit, $matrice['droits_bornes'], true) && ! $fige)
                                                <label class="sr-only" for="portee-{{ md5($cle) }}">Portée</label>
                                                <select id="portee-{{ md5($cle) }}" data-portee-de="{{ $cle }}"
                                                        class="portee mt-1 block w-full rounded border-secondary/30 text-[10px] {{ $coche ? '' : 'invisible' }}">
                                                    @foreach($matrice['portees'] as $portee)
                                                        <option value="{{ $portee['valeur'] }}" @selected(($ecartHotel['scope'] ?? 'etablissement') === $portee['valeur'])>{{ $portee['libelle'] }}</option>
                                                    @endforeach
                                                </select>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endforeach

        @if($peutRegler)
            <div id="panneau-cumuls" class="mb-3 hidden rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-900">
                <p class="font-semibold">Ces réglages font cumuler des fonctions incompatibles.</p>
                <ul id="liste-cumuls" class="mt-1.5 list-disc space-y-0.5 pl-5"></ul>
                <label class="mt-2 inline-flex items-start gap-2 font-semibold">
                    <input type="checkbox" name="derogation" value="1" id="derogation" class="mt-0.5 rounded border-red-300">
                    J'accorde ces dérogations à la séparation des tâches, pour le motif indiqué ci-dessous.
                </label>
            </div>
            <div id="panneau-apercu" class="mb-3 hidden rounded-xl bg-white px-4 py-3 text-xs text-primary/80 shadow-sm" aria-live="polite"></div>

            <div class="sticky bottom-0 z-40 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-secondary/20 bg-white px-4 py-3 shadow-lg">
                <div class="min-w-[16rem] flex-1">
                    <label for="motif" class="sr-only">Motif</label>
                    <input type="text" id="motif" name="motif" maxlength="255" value="{{ old('motif') }}"
                           placeholder="Motif — consigné au journal et sur chaque droit modifié"
                           class="w-full rounded-lg border border-secondary/30 px-3 py-2 text-xs">
                    <p class="mt-1 text-[11px] text-primary/50"><span id="compteur">0</span> case(s) modifiée(s). <span id="exigence" class="hidden font-medium text-amber-700"></span></p>
                </div>
                <div class="flex shrink-0 gap-2">
                    <button type="button" id="voir-apercu" disabled class="rounded-lg border border-secondary/30 px-4 py-2 text-xs font-semibold text-primary disabled:opacity-50">Voir l'aperçu</button>
                    <button type="submit" id="appliquer" disabled class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white disabled:opacity-40">Enregistrer</button>
                </div>
            </div>
        @endif
        <div id="ecarts"></div>
    </form>
@endif

{{-- ==================== EXCEPTIONS ==================== --}}
@if($onglet === 'exceptions')
    @if($peutExcepter)
        <form method="POST" action="{{ route('droits.exceptions.store') }}" class="mb-5 grid gap-3 rounded-xl bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6">
            @csrf
            <h2 class="font-heading text-lg font-semibold text-primary sm:col-span-2 lg:col-span-6">Accorder ou refuser un droit à une personne</h2>
            <div class="lg:col-span-2">
                <label for="e-personne" class="mb-1 block text-xs font-semibold text-primary/70">Personne</label>
                <select id="e-personne" name="user_id" required class="w-full rounded-lg border border-secondary/30 px-2.5 py-2 text-xs">
                    <option value="">Choisir…</option>
                    @foreach($personnel as $p)
                        <option value="{{ $p->id }}" @selected(old('user_id') == $p->id)>{{ $p->name }} — {{ implode(', ', array_map(fn ($s) => $roles[$s]['name'] ?? $s, $p->rolesDetenus())) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="lg:col-span-2">
                <label for="e-droit" class="mb-1 block text-xs font-semibold text-primary/70">Droit</label>
                <input id="e-droit" name="permission" list="liste-droits" required value="{{ old('permission') }}" placeholder="economat.items.creer"
                       class="w-full rounded-lg border border-secondary/30 px-2.5 py-2 font-mono text-xs">
                <datalist id="liste-droits">@foreach(array_keys($matrice['catalogue']) as $d)<option value="{{ $d }}">@endforeach</datalist>
            </div>
            <div>
                <label for="e-effet" class="mb-1 block text-xs font-semibold text-primary/70">Effet</label>
                <select id="e-effet" name="effect" class="w-full rounded-lg border border-secondary/30 px-2.5 py-2 text-xs">
                    <option value="deny" @selected(old('effect') === 'deny')>Refus</option>
                    <option value="allow" @selected(old('effect') === 'allow')>Autorisation</option>
                </select>
            </div>
            <div>
                <label for="e-echeance" class="mb-1 block text-xs font-semibold text-primary/70">Jusqu'au <span class="font-normal">(facultatif)</span></label>
                <input type="date" id="e-echeance" name="expires_at" value="{{ old('expires_at') }}" class="w-full rounded-lg border border-secondary/30 px-2.5 py-1.5 text-xs">
            </div>
            <div class="sm:col-span-2 lg:col-span-4">
                <label for="e-motif" class="mb-1 block text-xs font-semibold text-primary/70">Motif</label>
                <input id="e-motif" name="reason" required maxlength="255" value="{{ old('reason') }}" class="w-full rounded-lg border border-secondary/30 px-2.5 py-2 text-xs">
            </div>
            <div class="flex items-end gap-3 sm:col-span-2">
                <label class="inline-flex items-center gap-1.5 text-[11px] text-primary/70">
                    <input type="checkbox" name="derogation" value="1" class="rounded border-secondary/40"> Dérogation à la séparation des tâches
                </label>
                <button type="submit" class="ml-auto rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white">Enregistrer</button>
            </div>
        </form>
    @endif

    <div class="mb-5 overflow-hidden rounded-xl bg-white shadow-sm">
        <h2 class="border-b border-secondary/15 px-4 py-3 text-sm font-semibold text-primary">Exceptions nominatives en vigueur</h2>
        <table class="w-full text-left text-xs">
            <thead class="bg-accent/30 text-[11px] uppercase tracking-wider text-primary/50">
                <tr><th class="px-4 py-2">Personne</th><th class="px-4 py-2">Droit</th><th class="px-4 py-2">Effet</th><th class="px-4 py-2">Posée par</th><th class="px-4 py-2">Motif</th><th class="px-4 py-2">Jusqu'au</th><th class="px-4 py-2"></th></tr>
            </thead>
            <tbody class="divide-y divide-secondary/10">
                @forelse($nominatives as $e)
                    <tr>
                        <td class="px-4 py-2 text-primary">{{ $nomDuCompte($e['subject_id']) }}</td>
                        <td class="px-4 py-2 font-mono text-primary/70">{{ $e['permission'] }}</td>
                        <td class="px-4 py-2">{{ $e['effect'] === 'deny' ? 'Refus' : 'Autorisation' }}</td>
                        <td class="px-4 py-2">{{ $e['origin'] === 'erp' ? 'Console' : 'Hôtel' }}</td>
                        <td class="px-4 py-2 text-primary/60">{{ $e['reason'] ?? '—' }}</td>
                        <td class="px-4 py-2 text-primary/60">{{ $e['expires_at'] ? \Illuminate\Support\Carbon::parse($e['expires_at'])->format('d/m/Y') : 'Sans échéance' }}</td>
                        <td class="px-4 py-2 text-right">
                            @if($e['origin'] === 'etablissement')
                                @droit('droits.exceptions.supprimer')
                                    <form method="POST" action="{{ route('droits.exceptions.destroy', $e['id']) }}" onsubmit="return confirm('Retirer cette exception ?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-[11px] font-semibold text-red-700 hover:underline">Retirer</button>
                                    </form>
                                @enddroit
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-primary/50">Aucune.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm">
        <h2 class="border-b border-secondary/15 px-4 py-3 text-sm font-semibold text-primary">Restrictions de service</h2>
        <p class="px-4 pt-2 text-[11px] text-primary/50">Posées autrefois depuis la console sur des personnes : exclusion d'un service, ou lecture seule. Elles l'emportent sur les rôles.</p>
        <table class="w-full text-left text-xs">
            <tbody class="divide-y divide-secondary/10">
                @forelse($matrice['restrictions'] as $r)
                    <tr>
                        <td class="px-4 py-2 text-primary">{{ $nomDuCompte($r['user_id']) }}</td>
                        <td class="px-4 py-2 font-mono text-primary/70">{{ $r['service'] }}</td>
                        <td class="px-4 py-2">{{ $r['niveau'] === 'none' ? 'Exclu' : 'Lecture seule' }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-center text-primary/50">Aucune.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endif

{{-- ==================== ALERTES ==================== --}}
@if($onglet === 'alertes')
    <div class="mb-5 overflow-hidden rounded-xl bg-white shadow-sm">
        <h2 class="border-b border-secondary/15 px-4 py-3 text-sm font-semibold text-primary">Revue des comptes</h2>
        <ul class="divide-y divide-secondary/10">
            @forelse($matrice['constats'] as $c)
                <li class="px-4 py-3 text-xs">
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $c['gravite'] === 'à corriger' ? 'bg-red-100 text-red-700' : ($c['gravite'] === 'à confirmer' ? 'bg-amber-100 text-amber-800' : 'bg-accent/40 text-primary/70') }}">{{ $c['gravite'] }}</span>
                    <span class="ml-1 font-semibold text-primary">{{ $c['constat'] }}</span>
                    <p class="mt-1 text-primary/70">{{ $c['decision'] }}</p>
                    @if($c['comptes'])<p class="mt-1 font-mono text-[11px] text-primary/50">{{ implode(' · ', $c['comptes']) }}</p>@endif
                </li>
            @empty
                <li class="px-4 py-6 text-center text-xs text-primary/50">Rien à signaler.</li>
            @endforelse
        </ul>
    </div>
    <div class="overflow-hidden rounded-xl bg-white shadow-sm">
        <h2 class="border-b border-secondary/15 px-4 py-3 text-sm font-semibold text-primary">Exceptions arrivées à échéance</h2>
        <ul class="divide-y divide-secondary/10">
            @forelse($matrice['exceptions_echues'] as $e)
                <li class="px-4 py-3 text-xs text-primary/80">
                    <span class="font-semibold text-primary">{{ $nomDuCompte($e['subject_id']) }}</span> — <span class="font-mono">{{ $e['permission'] }}</span>
                    ({{ $e['effect'] === 'deny' ? 'refus' : 'autorisation' }}), échue le {{ \Illuminate\Support\Carbon::parse($e['expires_at'])->format('d/m/Y') }}.
                    Elle ne s'applique plus : la renouveler ou la retirer.
                </li>
            @empty
                <li class="px-4 py-6 text-center text-xs text-primary/50">Aucune.</li>
            @endforelse
        </ul>
    </div>
@endif

@endsection

@if($onglet === 'matrice' && $peutRegler)
@push('scripts')
<script type="application/json" id="droits-donnees">{!! json_encode($donneesJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
<script>
    (function () {
        const donnees = JSON.parse(document.getElementById('droits-donnees').textContent);
        const formulaire = document.getElementById('form-droits');
        const cases = [...document.querySelectorAll('.case-droit')];
        const motif = document.getElementById('motif');
        const compteur = document.getElementById('compteur');
        const exigence = document.getElementById('exigence');
        const derogation = document.getElementById('derogation');
        const boutonApercu = document.getElementById('voir-apercu');
        const boutonEnregistrer = document.getElementById('appliquer');
        const panneauApercu = document.getElementById('panneau-apercu');
        const panneauCumuls = document.getElementById('panneau-cumuls');
        const listeCumuls = document.getElementById('liste-cumuls');
        const touchees = new Set();
        let apercuAJour = false;

        const cle = (c) => `${c.dataset.role}|${c.dataset.droit}`;
        const portee = (c) => document.querySelector(`[data-portee-de="${cle(c)}"]`);

        // Écart de l'hôtel tel qu'il était à l'ouverture de l'écran.
        function initial(c) {
            if (!c.dataset.couche) return null;
            return { effect: c.dataset.couche, scope: c.dataset.porteeInitiale || null, reason: c.dataset.raison || null };
        }

        // Écart voulu pour une case touchée : aucun si elle revient au modèle.
        function voulu(c) {
            const gabarit = c.dataset.gabarit === '1';
            const p = portee(c);
            const restreinte = c.checked && p && p.value !== 'etablissement';
            if (c.checked === gabarit && !restreinte) return null;
            return { effect: c.checked ? 'allow' : 'deny', scope: restreinte ? p.value : null };
        }

        const ecartDe = (c) => (touchees.has(cle(c)) ? voulu(c) : initial(c));
        const differe = (a, b) => (a === null || b === null) ? a !== b : (a.effect !== b.effect || (a.scope || null) !== (b.scope || null));

        // La couche entière : elle est remplacée d'un bloc.
        function lot() {
            return cases.map((c) => {
                const e = ecartDe(c);
                if (e === null) return null;
                const inchange = !differe(e, initial(c));
                return {
                    role: c.dataset.role, permission: c.dataset.droit, effect: e.effect, scope: e.scope,
                    reason: inchange ? (initial(c).reason || motif.value) : motif.value,
                };
            }).filter(Boolean);
        }

        function cumulsDuLot() {
            const cumuls = [];
            cases.forEach((c) => {
                c.classList.remove('ring-red-500');
                const e = ecartDe(c);
                if (e === null || e.effect !== 'allow' || c.dataset.gabarit === '1') return;
                (donnees.cumuls[cle(c)] || []).forEach((i) => {
                    c.classList.remove('ring-amber-400');
                    c.classList.add('ring-2', 'ring-red-500');
                    cumuls.push({ role: c.dataset.role, droit: c.dataset.droit, motif: donnees.regles[i].motif });
                });
            });
            return cumuls;
        }

        function recalculer() {
            let changements = 0;
            cases.forEach((c) => {
                const change = touchees.has(cle(c)) && differe(voulu(c), initial(c));
                if (change) changements++;
                const marque = change || c.dataset.couche !== '';
                c.classList.toggle('ring-2', marque);
                c.classList.toggle('ring-amber-400', marque);
            });
            compteur.textContent = changements;

            const cumuls = cumulsDuLot();
            listeCumuls.innerHTML = '';
            cumuls.forEach((cu) => {
                const li = document.createElement('li');
                li.textContent = `${donnees.noms[cu.role] || cu.role} reçoit ${cu.droit} — ${cu.motif}`;
                listeCumuls.appendChild(li);
            });
            panneauCumuls.classList.toggle('hidden', cumuls.length === 0);

            const manques = [];
            if (changements > 0 && motif.value.trim() === '') manques.push('indiquez un motif');
            if (changements > 0 && cumuls.length > 0 && !derogation.checked) manques.push('accordez ou retirez les dérogations');
            if (changements > 0 && !apercuAJour) manques.push("consultez l'aperçu");
            exigence.textContent = manques.length ? 'Pour enregistrer : ' + manques.join(', ') + '.' : '';
            exigence.classList.toggle('hidden', manques.length === 0);

            boutonApercu.disabled = changements === 0;
            boutonEnregistrer.disabled = changements === 0 || manques.length > 0;

            document.querySelectorAll('.module').forEach((module) => {
                let n = 0;
                module.querySelectorAll('.case-droit').forEach((c) => { if (ecartDe(c) !== null) n++; });
                const badge = module.querySelector('.badge-ecarts');
                badge.querySelector('.compte').textContent = n;
                badge.classList.toggle('hidden', n === 0);
            });
        }

        function toucher(c) {
            touchees.add(cle(c));
            apercuAJour = false;
            panneauApercu.classList.add('hidden');
            recalculer();
        }

        cases.forEach((c) => c.addEventListener('change', () => {
            const p = portee(c);
            if (p) p.classList.toggle('invisible', !c.checked);
            toucher(c);
        }));
        document.querySelectorAll('.portee').forEach((p) => p.addEventListener('change', () => {
            const c = cases.find((x) => cle(x) === p.dataset.porteeDe);
            if (c) toucher(c);
        }));
        motif.addEventListener('input', recalculer);
        derogation.addEventListener('change', recalculer);

        function puce(texte, classes) {
            const span = document.createElement('span');
            span.className = 'mr-1 mb-1 inline-block rounded px-1.5 py-0.5 font-mono text-[10px] ' + classes;
            span.textContent = texte;
            return span;
        }

        boutonApercu.addEventListener('click', async () => {
            boutonApercu.disabled = true;
            panneauApercu.classList.remove('hidden');
            panneauApercu.textContent = "Calcul de l'aperçu…";
            try {
                const reponse = await fetch(donnees.apercu, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json', 'Accept': 'application/json',
                        'X-CSRF-TOKEN': formulaire.querySelector('input[name="_token"]').value,
                    },
                    body: JSON.stringify({ ecarts: lot() }),
                });
                const corps = await reponse.json();
                panneauApercu.innerHTML = '';
                if (!reponse.ok || !corps.ok) {
                    panneauApercu.textContent = corps.message || "L'aperçu n'a pas pu être calculé.";
                    apercuAJour = false;
                } else {
                    const personnes = corps.apercu.personnes;
                    const titre = document.createElement('p');
                    titre.className = 'font-semibold text-primary';
                    titre.textContent = personnes.length === 0 ? 'Aucune personne ne change de droits.' : `${personnes.length} personne(s) touchée(s) :`;
                    panneauApercu.appendChild(titre);
                    personnes.forEach((p) => {
                        const bloc = document.createElement('div');
                        bloc.className = 'mt-2 border-t border-secondary/15 pt-2';
                        const nom = document.createElement('p');
                        nom.className = 'font-semibold text-primary/80';
                        nom.textContent = `${p.name} — ${p.roles.map((r) => donnees.noms[r] || r).join(', ')}`;
                        bloc.appendChild(nom);
                        const droits = document.createElement('div');
                        droits.className = 'mt-1';
                        p.gagnes.forEach((d) => droits.appendChild(puce('+ ' + d, 'bg-emerald-50 text-emerald-700')));
                        p.perdus.forEach((d) => droits.appendChild(puce('− ' + d, 'bg-red-50 text-red-700')));
                        p.portees.forEach((d) => droits.appendChild(puce(`${d.permission} : ${d.avant} → ${d.apres}`, 'bg-amber-50 text-amber-800')));
                        bloc.appendChild(droits);
                        panneauApercu.appendChild(bloc);
                    });
                    apercuAJour = true;
                }
            } catch (e) {
                panneauApercu.textContent = "L'aperçu n'a pas pu être calculé.";
                apercuAJour = false;
            }
            recalculer();
        });

        formulaire.addEventListener('submit', (evenement) => {
            if (boutonEnregistrer.disabled) { evenement.preventDefault(); return; }
            const panier = document.getElementById('ecarts');
            panier.innerHTML = '';
            lot().forEach((e, n) => Object.entries(e).forEach(([champ, valeur]) => {
                if (valeur === null || valeur === undefined) return;
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `ecarts[${n}][${champ}]`;
                input.value = valeur;
                panier.appendChild(input);
            }));
        });

        // Filtrage : masquer n'est pas annuler, les lignes restent dans le document.
        const recherche = document.getElementById('recherche');
        const filtreRole = document.getElementById('filtre-role');
        const filtreEcart = document.getElementById('filtre-ecarts');
        function filtrer() {
            const terme = recherche.value.trim().toLowerCase();
            const role = filtreRole.value;
            document.querySelectorAll('.module').forEach((module) => {
                let visibles = 0;
                module.querySelectorAll('tbody tr').forEach((ligne) => {
                    const boites = [...ligne.querySelectorAll('input[type="checkbox"][data-droit]')];
                    const droit = boites[0]?.dataset.droit ?? '';
                    const parTexte = terme === '' || droit.toLowerCase().includes(terme);
                    const parEcart = !filtreEcart.checked || boites.some((c) => c.classList.contains('case-droit')
                        && (role === '' || c.dataset.role === role) && ecartDe(c) !== null);
                    ligne.classList.toggle('hidden', !(parTexte && parEcart));
                    if (parTexte && parEcart) visibles++;
                });
                module.classList.toggle('hidden', visibles === 0);
                if ((terme || role || filtreEcart.checked) && visibles > 0) module.open = true;
                module.querySelectorAll('[data-colonne]').forEach((cellule) =>
                    cellule.classList.toggle('hidden', role !== '' && cellule.dataset.colonne !== role));
            });
        }
        recherche.addEventListener('input', filtrer);
        filtreRole.addEventListener('change', filtrer);
        filtreEcart.addEventListener('change', filtrer);

        recalculer();
    })();
</script>
@endpush
@endif
