<?php

namespace App\Services;

use App\Models\Department;
use App\Models\ShiftAssignment;
use App\Models\ShiftWeek;
use App\Models\User;
use App\Models\WorkRegime;
use App\Models\WorkShift;
use App\Notifications\ShiftPlanningPublished;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Le planning des quarts : qui travaille, quel jour, sur quel quart.
 *
 * La direction définit les quarts de l'hôtel (Paramètres › Quarts). Chaque
 * chef de service y répartit le personnel de son département, semaine par
 * semaine, puis envoie le planning : chacun reçoit ses quarts. La direction
 * voit et planifie tous les services.
 *
 * Le planning informe ; il ne verrouille rien. Un remplaçant de dernière
 * minute travaille, et son chef corrige le planning.
 */
class PlanningService
{
    /** Droit de planifier : celui des chefs de service et de la direction. */
    public const DROIT_PLANIFIER = 'planning.affectations.creer';

    /** Voient tous les services. Le responsable financier, comme le contrôle de gestion. */
    public const VUE_GLOBALE = ['admin', 'manager', 'controller', 'finance_manager', 'support'];

    /** Planifient tous les services, et non le seul leur. */
    public const PLANIFIENT_TOUT = ['manager'];

    public function __construct(private readonly PermissionResolver $resolveur, private readonly Notifier $notifier) {}

    /** Lundi de la semaine d'une date (aujourd'hui par défaut). */
    public static function lundi(CarbonInterface|string|null $date = null): CarbonImmutable
    {
        return CarbonImmutable::parse($date ?? now())->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
    }

    /** @return list<CarbonImmutable> les sept jours de la semaine */
    public static function jours(CarbonImmutable $lundi): array
    {
        return array_map(fn (int $i) => $lundi->addDays($i), range(0, 6));
    }

    /** @return Collection<int, WorkShift> */
    public function quarts(): Collection
    {
        return WorkShift::query()->active()->dansLOrdre()->get();
    }

    /**
     * Plages horaires visibles dans la grille d'un service : celles de son
     * régime effectif, celles des régimes individuels de son personnel, et
     * toute plage déjà planifiée cette semaine-là.
     *
     * @param  Collection<int, ShiftAssignment>|null  $affectations
     * @param  Collection<int, User>|null  $personnel
     * @return Collection<int, WorkShift>
     */
    public function quartsDuDepartement(Department $departement, ?Collection $affectations = null, ?Collection $personnel = null): Collection
    {
        $departement->loadMissing('workRegime');
        $personnel ??= $this->personnel($departement);

        $regimeIds = collect([$departement->regimeEffectif()?->id])
            ->merge($personnel->map(fn (User $u) => $u->regimeEffectif()?->id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $quartIds = $regimeIds !== []
            ? WorkShift::query()->active()->whereIn('work_regime_id', $regimeIds)->pluck('id')
            : collect();

        if ($affectations !== null && $affectations->isNotEmpty()) {
            $quartIds = $quartIds->merge($affectations->pluck('work_shift_id'));
        }

        $ids = $quartIds->filter()->unique()->values()->all();

        if ($ids === []) {
            return collect();
        }

        return WorkShift::query()->whereIn('id', $ids)->dansLOrdre()->get();
    }

    /**
     * Services que cette personne planifie : tous pour la direction, le sien
     * pour un chef. Un chef sans département n'en planifie aucun.
     *
     * @return Collection<int, Department>
     */
    public function departementsPlanifiables(?User $user): Collection
    {
        if ($user === null || ! $this->resolveur->allows($user, self::DROIT_PLANIFIER)) {
            return collect();
        }

        $requete = Department::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name');

        return $user->isAdmin() || $user->hasAnyRole(self::PLANIFIENT_TOUT)
            ? $requete->get()
            : $requete->whereKey($user->department_id ?? 0)->get();
    }

    /**
     * Services dont cette personne consulte le planning. Sans aucun, elle ne
     * voit que ses propres quarts.
     *
     * @return Collection<int, Department>
     */
    public function departementsVisibles(?User $user): Collection
    {
        if ($user !== null && $user->hasAnyRole(self::VUE_GLOBALE)) {
            return Department::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        }

        return $this->departementsPlanifiables($user);
    }

    public function peutPlanifier(?User $user, Department $departement, CarbonImmutable $lundi): bool
    {
        // Le passé ne se réécrit pas : on planifie la semaine en cours et les suivantes.
        return $lundi->greaterThanOrEqualTo(self::lundi())
            && $this->departementsPlanifiables($user)->contains('id', $departement->id);
    }

    /** @return Collection<int, User> le personnel actif du service */
    public function personnel(Department $departement): Collection
    {
        return User::query()->active()
            ->with(['workRegime', 'department.workRegime'])
            ->where('department_id', $departement->id)
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('slug', ['admin', 'support']))
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, User> les membres du service travaillant par quarts (non fixes) */
    public function personnelEnQuarts(Department $departement): Collection
    {
        return $this->personnel($departement)
            ->filter(fn (User $u) => ! ($u->regimeEffectif()?->estFixe() ?? false))
            ->values();
    }

    /** @return Collection<int, ShiftAssignment> les quarts d'un service sur la semaine */
    public function semaine(Department $departement, CarbonImmutable $lundi): Collection
    {
        $this->assurerPreRemplissage($departement, $lundi);

        return ShiftAssignment::query()
            ->with(['user:id,name', 'shift'])
            ->where('department_id', $departement->id)
            ->whereBetween('date', [$lundi->toDateString(), $lundi->addDays(6)->toDateString()])
            ->get()
            ->sortBy(fn (ShiftAssignment $a) => [$a->jour()->toDateString(), $a->shift->sort_order, $a->user->name])
            ->values();
    }

    /** @return Collection<int, ShiftAssignment> les quarts d'une personne sur la semaine */
    public function semaineDe(User $user, CarbonImmutable $lundi): Collection
    {
        $user->loadMissing(['workRegime', 'department.workRegime']);
        if ($user->department !== null && $user->regimeEffectif()?->estFixe()) {
            $this->assurerPreRemplissage($user->department, $lundi);
        }

        return ShiftAssignment::query()
            ->with(['shift', 'department:id,name'])
            ->where('user_id', $user->id)
            ->whereBetween('date', [$lundi->toDateString(), $lundi->addDays(6)->toDateString()])
            ->get()
            ->sortBy(fn (ShiftAssignment $a) => [$a->jour()->toDateString(), $a->shift->sort_order])
            ->values();
    }

    /** @throws ValidationException */
    public function affecter(User $par, Department $departement, User $employe, WorkShift $quart, CarbonImmutable $jour): ShiftAssignment
    {
        $this->exigerDePouvoirPlanifier($par, $departement, self::lundi($jour));

        if ($employe->department_id !== $departement->id || ! $employe->is_active) {
            throw ValidationException::withMessages(['user_id' => "{$employe->name} ne fait pas partie du personnel actif de ce service."]);
        }

        if (! $quart->is_active) {
            throw ValidationException::withMessages(['work_shift_id' => "Le quart « {$quart->name} » n'est plus en service."]);
        }

        return ShiftAssignment::firstOrCreate(
            ['user_id' => $employe->id, 'date' => $jour->toDateString(), 'work_shift_id' => $quart->id],
            ['department_id' => $departement->id, 'assigned_by' => $par->id, 'source' => 'manual']
        );
    }

    /** @throws ValidationException */
    public function retirer(User $par, ShiftAssignment $affectation): void
    {
        $departement = Department::find($affectation->department_id)
            ?? throw ValidationException::withMessages(['affectation' => 'Ce quart ne relève plus d\'aucun service.']);

        $this->exigerDePouvoirPlanifier($par, $departement, self::lundi($affectation->jour()));

        $affectation->delete();
    }

    /**
     * Recopie la semaine précédente là où rien n'est encore planifié : la
     * plupart des semaines ressemblent à la précédente.
     *
     * @return int quarts ajoutés
     */
    public function recopier(User $par, Department $departement, CarbonImmutable $lundi): int
    {
        $this->exigerDePouvoirPlanifier($par, $departement, $lundi);

        $personnel = $this->personnel($departement)->pluck('id')->all();
        $quarts = $this->quartsDuDepartement($departement)->where('is_active', true)->pluck('id')->all();
        $ajoutes = 0;

        DB::transaction(function () use ($departement, $lundi, $personnel, $quarts, $par, &$ajoutes) {
            foreach ($this->semaine($departement, $lundi->subWeek()) as $precedente) {
                if (! in_array($precedente->user_id, $personnel, true) || ! in_array($precedente->work_shift_id, $quarts, true)) {
                    continue;
                }

                $copie = ShiftAssignment::firstOrCreate(
                    ['user_id' => $precedente->user_id, 'date' => $precedente->jour()->addWeek()->toDateString(), 'work_shift_id' => $precedente->work_shift_id],
                    ['department_id' => $departement->id, 'assigned_by' => $par->id, 'source' => 'manual']
                );
                $ajoutes += $copie->wasRecentlyCreated ? 1 : 0;
            }
        });

        return $ajoutes;
    }

    /**
     * Envoie le planning de la semaine au personnel du service. Seuls ceux
     * dont les quarts ont changé depuis le dernier envoi sont prévenus — y
     * compris ceux qui n'en ont plus.
     *
     * @return int personnes prévenues
     */
    public function publier(User $par, Department $departement, CarbonImmutable $lundi): int
    {
        $this->exigerDePouvoirPlanifier($par, $departement, $lundi);

        $parPersonne = $this->semaine($departement, $lundi)->groupBy('user_id');
        $signatures = $parPersonne->map(fn (Collection $quarts) => $this->signature($quarts))->all();

        $semaine = ShiftWeek::firstOrNew(['department_id' => $departement->id, 'week_start' => $lundi->toDateString()]);
        $avant = $semaine->sent ?? [];

        $concernes = collect(array_keys($signatures + $avant))
            ->filter(fn ($id) => ($signatures[$id] ?? null) !== ($avant[$id] ?? null))
            ->values();

        $semaine->fill(['published_at' => now(), 'published_by' => $par->id, 'sent' => $signatures])->save();

        foreach (User::query()->whereIn('id', $concernes)->get() as $employe) {
            $this->notifier->send($employe, new ShiftPlanningPublished(
                $lundi,
                $departement->name,
                $this->resume($parPersonne->get($employe->id, collect()))
            ));
        }

        return $concernes->count();
    }

    /** La semaine a-t-elle changé depuis son dernier envoi ? Null si jamais envoyée. */
    public function modifieeDepuisEnvoi(Department $departement, CarbonImmutable $lundi, ?ShiftWeek $semaine = null): ?bool
    {
        $semaine ??= ShiftWeek::query()->where('department_id', $departement->id)->where('week_start', $lundi->toDateString())->first();

        if ($semaine?->published_at === null) {
            return null;
        }

        $actuel = $this->semaine($departement, $lundi)->groupBy('user_id')
            ->map(fn (Collection $quarts) => $this->signature($quarts))->all();

        ksort($actuel);
        $envoye = $semaine->sent ?? [];
        ksort($envoye);

        return $actuel != $envoye;
    }

    /**
     * Qui est en service à cet instant : les quarts commencés ce jour-là, et
     * ceux de la veille qui finissent après minuit.
     *
     * @param  list<int>|null  $departements  null : tout l'hôtel
     * @return Collection<int, ShiftAssignment>
     */
    public function enService(?array $departements = null, ?CarbonImmutable $instant = null): Collection
    {
        $instant ??= CarbonImmutable::now();

        $lundiCourant = self::lundi();
        $lundis = collect([self::lundi($instant), self::lundi($instant->subDay())])
            ->unique(fn (CarbonImmutable $d) => $d->toDateString())
            ->filter(fn (CarbonImmutable $d) => $d->greaterThanOrEqualTo($lundiCourant));

        if ($lundis->isNotEmpty()) {
            $cibles = Department::query()
                ->where('is_active', true)
                ->when($departements !== null, fn ($q) => $q->whereIn('id', $departements))
                ->with('workRegime')
                ->get();

            foreach ($cibles as $dept) {
                foreach ($lundis as $lundi) {
                    $this->assurerPreRemplissage($dept, $lundi);
                }
            }
        }

        return ShiftAssignment::query()
            ->with(['user:id,name', 'shift', 'department:id,name'])
            ->whereBetween('date', [$instant->subDay()->toDateString(), $instant->toDateString()])
            ->when($departements !== null, fn ($q) => $q->whereIn('department_id', $departements))
            ->get()
            ->filter(fn (ShiftAssignment $a) => $a->enCoursA($instant))
            ->sortBy(fn (ShiftAssignment $a) => [$a->department?->name, $a->user->name])
            ->values();
    }

    /** Le prochain quart d'une personne, ou celui qu'elle fait en ce moment. */
    public function prochainQuart(User $user, ?CarbonImmutable $instant = null): ?ShiftAssignment
    {
        $instant ??= CarbonImmutable::now();

        $user->loadMissing(['workRegime', 'department.workRegime']);
        if ($user->department !== null && $user->regimeEffectif()?->estFixe()) {
            $lundiCourant = self::lundi();
            foreach ([self::lundi($instant), self::lundi($instant)->addWeek(), self::lundi($instant)->addWeeks(2)] as $lundi) {
                if ($lundi->greaterThanOrEqualTo($lundiCourant)) {
                    $this->assurerPreRemplissage($user->department, $lundi);
                }
            }
        }

        return ShiftAssignment::query()
            ->with('shift')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$instant->subDay()->toDateString(), $instant->addDays(14)->toDateString()])
            ->get()
            ->filter(fn (ShiftAssignment $a) => $a->fin()->greaterThan($instant))
            ->sortBy(fn (ShiftAssignment $a) => $a->debut()->timestamp)
            ->first();
    }

    /**
     * Les services en quarts dont la semaine suivante n'est pas encore envoyée,
     * avec ceux qui doivent la programmer. Les services exclusivement en horaire
     * fixe se pré-remplissent d'office et ne nécessitent pas ce rappel.
     *
     * @return Collection<int, array{departement: Department, chefs: Collection<int, User>}>
     */
    public function semainesAProgrammer(CarbonImmutable $lundi): Collection
    {
        $envoyees = ShiftWeek::query()->where('week_start', $lundi->toDateString())->whereNotNull('published_at')->pluck('department_id')->all();

        return Department::query()->where('is_active', true)->whereNotIn('id', $envoyees)->with('workRegime')->get()
            ->filter(fn (Department $d) => $this->personnelEnQuarts($d)->isNotEmpty())
            ->map(fn (Department $d) => ['departement' => $d, 'chefs' => $this->chefsDe($d)])
            ->filter(fn (array $a) => $a['chefs']->isNotEmpty())
            ->values();
    }

    /**
     * Pré-remplit les plages des collaborateurs en horaire fixe pour une
     * semaine en cours ou future. Chaque personne n'est pré-remplie qu'une
     * fois par semaine : une suppression ou modification manuelle reste respectée.
     */
    public function assurerPreRemplissage(Department $departement, CarbonImmutable $lundi): void
    {
        if ($lundi->lessThan(self::lundi())) {
            return;
        }

        $fixes = $this->personnel($departement)
            ->filter(fn (User $u) => $u->regimeEffectif()?->estFixe() ?? false);

        if ($fixes->isEmpty()) {
            return;
        }

        $semaine = ShiftWeek::query()
            ->where('department_id', $departement->id)
            ->where('week_start', $lundi->toDateString())
            ->first();

        $dejaPreRemplis = array_map('intval', $semaine?->autofilled_users ?? []);
        $aTraiter = $fixes->filter(fn (User $u) => ! in_array((int) $u->id, $dejaPreRemplis, true));

        if ($aTraiter->isEmpty()) {
            return;
        }

        $regimeIds = $aTraiter->map(fn (User $u) => $u->regimeEffectif()?->id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $plagesParRegime = WorkShift::query()
            ->active()
            ->whereIn('work_regime_id', $regimeIds)
            ->dansLOrdre()
            ->get()
            ->groupBy('work_regime_id');

        $jours = self::jours($lundi);

        DB::transaction(function () use ($departement, $lundi, $aTraiter, $plagesParRegime, $jours) {
            $semaine = ShiftWeek::firstOrCreate([
                'department_id' => $departement->id,
                'week_start'    => $lundi->toDateString(),
            ]);

            $dejaPreRemplis = array_map('intval', $semaine->autofilled_users ?? []);
            $nouveaux = [];

            foreach ($aTraiter as $employe) {
                if (in_array((int) $employe->id, $dejaPreRemplis, true)) {
                    continue;
                }

                $regime = $employe->regimeEffectif();
                if ($regime === null || ! $regime->estFixe()) {
                    continue;
                }

                $joursOuvres = $regime->joursTravailles();
                $plages = $plagesParRegime->get($regime->id, collect());

                if ($joursOuvres !== [] && $plages->isNotEmpty()) {
                    foreach ($jours as $jour) {
                        if (! in_array($jour->dayOfWeekIso, $joursOuvres, true)) {
                            continue;
                        }

                        foreach ($plages as $quart) {
                            ShiftAssignment::firstOrCreate(
                                [
                                    'user_id'       => $employe->id,
                                    'date'          => $jour->toDateString(),
                                    'work_shift_id' => $quart->id,
                                ],
                                [
                                    'department_id' => $departement->id,
                                    'assigned_by'   => null,
                                    'source'        => 'auto',
                                ]
                            );
                        }
                    }
                }

                $nouveaux[] = (int) $employe->id;
            }

            if ($nouveaux !== []) {
                $semaine->forceFill([
                    'autofilled_users' => array_values(array_unique(array_merge($dejaPreRemplis, $nouveaux))),
                    'autofilled_at'    => now(),
                ])->save();
            }
        });
    }

    /**
     * Lorsqu'un régime, une affectation de service ou une exception individuelle
     * change, purge les créneaux pré-remplis automatiquement à compter
     * d'aujourd'hui pour les personnes concernées (les retouches manuelles
     * restent intactes) et autorise le recalcul des semaines en cours et futures.
     */
    public function invaliderPreRemplissageFutur(?Department $departement = null, ?User $user = null, ?WorkRegime $regime = null): void
    {
        $aujourdhui = CarbonImmutable::today();
        $lundiCourant = self::lundi($aujourdhui)->toDateString();
        $aPartirDe = $aujourdhui->toDateString();

        $userIds = collect();

        if ($user !== null) {
            $userIds->push((int) $user->id);
        }

        if ($departement !== null) {
            $userIds = $userIds->merge(
                User::query()->where('department_id', $departement->id)->pluck('id')
            )->merge(
                ShiftAssignment::query()
                    ->where('department_id', $departement->id)
                    ->where('date', '>=', $aPartirDe)
                    ->where('source', 'auto')
                    ->pluck('user_id')
            );
        }

        if ($regime !== null) {
            $deptIds = Department::query()->where('work_regime_id', $regime->id)->pluck('id');
            if ($regime->is_default) {
                $deptIds = $deptIds->merge(Department::query()->whereNull('work_regime_id')->pluck('id'));
            }
            $deptIds = $deptIds->unique()->values();

            $shiftIds = WorkShift::query()->where('work_regime_id', $regime->id)->pluck('id');

            $userIds = $userIds
                ->merge(User::query()->where('work_regime_id', $regime->id)->pluck('id'))
                ->when($deptIds->isNotEmpty(), fn ($c) => $c->merge(User::query()->whereIn('department_id', $deptIds)->pluck('id')))
                ->when($shiftIds->isNotEmpty(), fn ($c) => $c->merge(
                    ShiftAssignment::query()
                        ->whereIn('work_shift_id', $shiftIds)
                        ->where('date', '>=', $aPartirDe)
                        ->where('source', 'auto')
                        ->pluck('user_id')
                ));
        }

        $cibles = $userIds->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        if ($cibles === []) {
            return;
        }

        DB::transaction(function () use ($cibles, $aPartirDe, $lundiCourant) {
            ShiftAssignment::query()
                ->whereIn('user_id', $cibles)
                ->where('date', '>=', $aPartirDe)
                ->where('source', 'auto')
                ->delete();

            $semaines = ShiftWeek::query()
                ->where('week_start', '>=', $lundiCourant)
                ->whereNotNull('autofilled_users')
                ->get();

            foreach ($semaines as $semaine) {
                $avant = array_map('intval', $semaine->autofilled_users ?? []);
                $apres = array_values(array_diff($avant, $cibles));

                if ($apres !== $avant) {
                    $semaine->forceFill(['autofilled_users' => $apres === [] ? null : $apres])->save();
                }
            }
        });
    }

    /**
     * Ceux qui planifient un service : ses chefs ; à défaut, la direction.
     *
     * @return Collection<int, User>
     */
    public function chefsDe(Department $departement): Collection
    {
        $chefs = $this->personnel($departement)->filter(fn (User $u) => $this->resolveur->allows($u, self::DROIT_PLANIFIER));

        if ($chefs->isNotEmpty()) {
            return $chefs->values();
        }

        return User::query()->active()->havingRole(self::PLANIFIENT_TOUT)->get()
            ->filter(fn (User $u) => $this->resolveur->allows($u, self::DROIT_PLANIFIER))
            ->values();
    }

    /** @throws ValidationException */
    private function exigerDePouvoirPlanifier(User $par, Department $departement, CarbonImmutable $lundi): void
    {
        if ($lundi->lessThan(self::lundi())) {
            throw ValidationException::withMessages(['semaine' => 'Une semaine passée ne se modifie plus.']);
        }

        if (! $this->departementsPlanifiables($par)->contains('id', $departement->id)) {
            throw ValidationException::withMessages(['departement' => "Vous ne planifiez pas le service « {$departement->name} »."]);
        }
    }

    /** Empreinte des quarts d'une personne : change dès qu'un quart change. */
    private function signature(Collection $quarts): string
    {
        return sha1($quarts->map(fn (ShiftAssignment $a) => $a->jour()->toDateString() . '#' . $a->work_shift_id)->sort()->implode('|'));
    }

    /** @return list<array{jour: string, quart: string, horaire: string}> */
    private function resume(Collection $quarts): array
    {
        return $quarts->map(fn (ShiftAssignment $a) => [
            'jour' => $a->jour()->locale('fr')->isoFormat('ddd D'),
            'quart' => $a->shift->name,
            'horaire' => $a->shift->horaire(),
        ])->values()->all();
    }
}
