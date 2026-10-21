<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\HousekeepingAssignment;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestEvent;
use App\Models\Room;
use App\Models\ServiceStore;
use App\Models\ServiceStoreMovement;
use App\Models\StockItem;
use App\Models\StockRequisition;
use App\Models\User;
use App\Notifications\MaintenanceRequestNotification as Avis;
use App\Support\TenantModules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Le cycle d'une demande d'intervention technique : signaler, affecter ou
 * prendre, commencer, employer des pièces, terminer, rouvrir, annuler.
 *
 * Chaque étape est inscrite à l'historique de la demande (qui, quand) et
 * annoncée à qui doit agir. Une chambre rendue indisponible pour
 * l'intervention retrouve son cycle à la fin : occupée si le client est
 * toujours là, à nettoyer sinon.
 */
class MaintenanceService
{
    /** Rôles du service technique. */
    public const RESPONSABLES = ['tech_manager'];
    public const TECHNICIENS  = ['technician'];

    public function __construct(
        private readonly Notifier $notifier,
        private readonly ServiceStoreService $depots,
    ) {
    }

    /** Le module est-il en service dans cet établissement ? */
    public static function actif(): bool
    {
        return TenantModules::has('technique');
    }

    /**
     * Services pour lesquels cette personne peut demander : ceux où elle
     * travaille ou qu'elle dirige ; tous pour la direction.
     *
     * @return array<string, string>
     */
    public static function servicesDe(User $user): array
    {
        if ($user->isAdmin() || $user->exerce(['manager'])) {
            return StockRequisition::DEPARTMENTS;
        }

        $services = collect(StockRequisition::DEPARTMENTS)
            ->filter(fn ($libelle, $cle) => $user->exerce(StockRequisition::DEPARTMENT_ROLES[$cle] ?? [])
                || $user->exerce(StockRequisition::CHEFS[$cle] ?? []))
            ->except('autre')
            ->all();

        return $services ?: ['autre' => StockRequisition::DEPARTMENTS['autre']];
    }

    /** Ce que cette personne voit : tout pour le service technique et la direction, sinon les siennes et celles des services qu'elle dirige. */
    public static function visibles(User $user, bool $toutVoir): \Illuminate\Database\Eloquent\Builder
    {
        $query = MaintenanceRequest::query();

        if ($toutVoir) {
            return $query;
        }

        $diriges = StockRequisition::servicesDirigesPar($user);

        return $query->where(fn ($q) => $q->where('requested_by', $user->id)
            ->when($diriges !== [], fn ($q) => $q->orWhereIn('department', $diriges)));
    }

    // ── Signaler ─────────────────────────────────────────────────────────────

    /**
     * @param  array{title: string, description?: ?string, specialty: string, priority: string,
     *               department: string, room_id?: ?int, location?: ?string, bloquer_chambre?: bool}  $donnees
     */
    public function signaler(array $donnees, User $demandeur, string $source = MaintenanceRequest::SOURCE_MANUAL, ?HousekeepingAssignment $affectation = null): MaintenanceRequest
    {
        $demande = DB::transaction(function () use ($donnees, $demandeur, $source, $affectation) {
            $room = !empty($donnees['room_id']) ? Room::query()->lockForUpdate()->find($donnees['room_id']) : null;

            $demande = MaintenanceRequest::create([
                'status'                     => MaintenanceRequest::STATUS_OPEN,
                'priority'                   => $donnees['priority'],
                'specialty'                  => $donnees['specialty'],
                'room_id'                    => $room?->id,
                'location'                   => $donnees['location'] ?? null,
                'title'                      => $donnees['title'],
                'description'                => $donnees['description'] ?? null,
                'department'                 => $donnees['department'],
                'source'                     => $source,
                'housekeeping_assignment_id' => $affectation?->id,
                'requested_by'               => $demandeur->id,
                'room_blocked'               => $room !== null && ($room->status === RoomStatus::MAINTENANCE || !empty($donnees['bloquer_chambre'])),
            ]);

            // Rendre la chambre indisponible pendant l'intervention technique
            if ($room && !empty($donnees['bloquer_chambre']) && $room->status !== RoomStatus::MAINTENANCE) {
                $room->updateStatus(RoomStatus::MAINTENANCE, "Intervention technique {$demande->reference}", $demandeur->id);
                $demande->update(['room_blocked' => true]);
            }

            $this->noter($demande, 'created', $demande->description, $demandeur);

            return $demande;
        });

        $this->notifier->send($this->equipePour($demande)->reject(fn ($u) => $u->id === $demandeur->id), new Avis($demande, Avis::CREATED));

        return $demande;
    }

    /** Un agent ou la gouvernante signale un problème en chambre : la technique est saisie, le responsable des étages en reçoit copie. */
    public function depuisHousekeeping(Room $room, string $probleme, HousekeepingAssignment $affectation, User $gouvernante, ?string $specialite = null, ?string $priorite = null, bool $bloquerChambre = false): MaintenanceRequest
    {
        $demande = $this->signaler([
            'title'           => mb_substr(trim(preg_split('/\R/', $probleme)[0]), 0, 150),
            'description'     => $probleme,
            'specialty'       => $specialite ?: 'autre',
            'priority'        => $priorite ?: MaintenanceRequest::PRIORITY_HIGH,
            'department'      => 'housekeeping',
            'room_id'         => $room->id,
            'bloquer_chambre' => $bloquerChambre,
        ], $gouvernante, MaintenanceRequest::SOURCE_HOUSEKEEPING, $affectation);

        // Envoyer une copie au responsable des étages (housekeeping_leader)
        $this->notifier->toRoles(
            ['housekeeping_leader'],
            new Avis($demande, Avis::CREATED, "Copie information : chambre {$room->number} signalée par le ménage"),
            $gouvernante->id
        );

        return $demande;
    }

    // ── Affecter, prendre, commencer ─────────────────────────────────────────

    public function affecter(MaintenanceRequest $demande, User $technicien, User $par, ?string $specialite = null, ?string $priorite = null): MaintenanceRequest
    {
        if (!$demande->isActive()) {
            throw new RuntimeException("La demande {$demande->reference} n'est plus à traiter.");
        }
        if (!$technicien->is_active || !$technicien->exerce(self::TECHNICIENS)) {
            throw new RuntimeException("{$technicien->name} n'est pas technicien.");
        }

        DB::transaction(function () use ($demande, $technicien, $par, $specialite, $priorite) {
            $requalifiee = array_filter([
                'specialty' => $specialite && $specialite !== $demande->specialty ? $specialite : null,
                'priority'  => $priorite && $priorite !== $demande->priority ? $priorite : null,
            ]);

            $demande->update([
                ...$requalifiee,
                'assigned_to' => $technicien->id,
                'assigned_by' => $par->id,
                'assigned_at' => now(),
                'status'      => $demande->status === MaintenanceRequest::STATUS_IN_PROGRESS ? MaintenanceRequest::STATUS_IN_PROGRESS : MaintenanceRequest::STATUS_ASSIGNED,
            ]);

            if ($requalifiee !== []) {
                $this->noter($demande, 'requalified', collect([
                    isset($requalifiee['specialty']) ? $demande->specialtyLabel() : null,
                    isset($requalifiee['priority']) ? 'priorité ' . mb_strtolower($demande->priorityLabel()) : null,
                ])->filter()->implode(', '), $par);
            }
            $this->noter($demande, 'assigned', $technicien->name, $par);
        });

        if ($technicien->id !== $par->id) {
            $this->notifier->send($technicien, new Avis($demande->fresh(), Avis::ASSIGNED));
        }

        return $demande->fresh();
    }

    /** Un technicien prend une demande que personne n'a encore (alerte le responsable technique). */
    public function prendre(MaintenanceRequest $demande, User $technicien): MaintenanceRequest
    {
        $demande = DB::transaction(function () use ($demande, $technicien) {
            $demande = MaintenanceRequest::query()->lockForUpdate()->findOrFail($demande->id);

            if ($demande->status !== MaintenanceRequest::STATUS_OPEN || $demande->assigned_to !== null) {
                throw new RuntimeException("La demande {$demande->reference} est déjà prise en charge.");
            }

            $demande->update([
                'assigned_to' => $technicien->id,
                'assigned_by' => $technicien->id,
                'assigned_at' => now(),
                'status'      => MaintenanceRequest::STATUS_ASSIGNED,
            ]);
            $this->noter($demande, 'taken', "Prise directe par {$technicien->name}", $technicien);

            return $demande;
        });

        // Alerte les responsables techniques (pour que le responsable absent voie ce qui a été pris en son absence)
        $responsables = User::query()->active()->havingRole(self::RESPONSABLES)->get()->reject(fn ($u) => $u->id === $technicien->id);
        if ($responsables->isNotEmpty()) {
            $this->notifier->send($responsables, new Avis($demande, Avis::TAKEN, "Prise par {$technicien->name}"));
        }

        return $demande;
    }

    public function commencer(MaintenanceRequest $demande, User $par): MaintenanceRequest
    {
        if ($demande->status !== MaintenanceRequest::STATUS_ASSIGNED) {
            throw new RuntimeException("La demande {$demande->reference} n'est pas prête à commencer.");
        }

        $demande->update(['status' => MaintenanceRequest::STATUS_IN_PROGRESS, 'started_at' => $demande->started_at ?? now()]);
        $this->noter($demande, 'started', null, $par);

        return $demande;
    }

    // ── Pièces ───────────────────────────────────────────────────────────────

    /** Les dépôts du service technique, d'où sortent les pièces. */
    public static function depotsTechniques(): Collection
    {
        $depots = ServiceStore::query()->active()->where('department', 'technique')->orderBy('sort_order')->orderBy('name')->get();
        if ($depots->isEmpty()) {
            $depot = ServiceStore::firstOrCreate(
                ['department' => 'technique', 'name' => 'Dépôt Maintenance & Technique'],
                ['is_active' => true, 'sort_order' => 1]
            );
            return collect([$depot]);
        }

        return $depots;
    }

    /**
     * Sort des pièces du dépôt technique pour cette intervention : elles
     * passent en charge du service où l'on répare.
     *
     * @param  array<int, float>  $pieces  [stock_item_id => quantité]
     */
    public function employerPieces(MaintenanceRequest $demande, ServiceStore $depot, array $pieces, User $par, ?string $motif = null): Collection
    {
        if (!$demande->isActive()) {
            throw new RuntimeException("La demande {$demande->reference} n'est plus en cours.");
        }
        if ($depot->department !== 'technique') {
            throw new RuntimeException("Le dépôt {$depot->name} n'est pas celui du service technique.");
        }

        $pieces = array_filter($pieces, fn ($q) => (float) $q > 0);
        if ($pieces === []) {
            throw new RuntimeException('Indiquez au moins une pièce et sa quantité.');
        }

        return DB::transaction(function () use ($demande, $depot, $pieces, $par, $motif) {
            $articles = StockItem::query()->whereKey(array_keys($pieces))->get()->keyBy('id');
            $sorties = collect();
            $detailMotif = $motif ? " — {$motif}" : '';

            foreach ($pieces as $id => $quantite) {
                $article = $articles->get($id) ?? throw new RuntimeException('Pièce introuvable.');
                $sorties->push($this->depots->issue(
                    $depot,
                    $article,
                    (float) $quantite,
                    ServiceStoreMovement::SOURCE_INTERVENTION,
                    $demande->id,
                    "Intervention {$demande->reference} — {$demande->lieu()}{$detailMotif}"
                ));
            }

            $resumePieces = $sorties->map(fn ($m) => rtrim(rtrim(number_format(abs((float) $m->quantity), 3, ',', ' '), '0'), ',')
                . ' ' . $articles[$m->stock_item_id]->unit . ' ' . $articles[$m->stock_item_id]->name)->implode(', ');

            $estResp = $par->exerce(self::RESPONSABLES) || $par->isAdmin() || $par->exerce(['manager']);
            $note = ($estResp ? "Matériel délivré par {$par->name} : " : 'Pièces employées : ') . $resumePieces;
            if ($motif) {
                $note .= " (Motif : {$motif})";
            }

            $this->noter($demande, 'parts', $note, $par);

            return $sorties;
        });
    }

    // ── Soumettre fiche, viser, terminer, rouvrir, annuler ───────────────────

    /** Le technicien renseigne la fiche d'intervention complète et ses pièces, et la remonte au responsable pour visa. */
    public function soumettreFiche(MaintenanceRequest $demande, array|string $fiche, User $technicien, ?ServiceStore $depot = null, ?array $pieces = null): MaintenanceRequest
    {
        if (!in_array($demande->status, [MaintenanceRequest::STATUS_OPEN, MaintenanceRequest::STATUS_ASSIGNED, MaintenanceRequest::STATUS_IN_PROGRESS, MaintenanceRequest::STATUS_SUBMITTED], true)) {
            throw new RuntimeException("La demande {$demande->reference} n'est pas en cours.");
        }

        $donneesFiche = is_array($fiche) ? $fiche : [
            'diagnostic'       => null,
            'travaux_realises' => $fiche,
            'resultat'         => MaintenanceRequest::RESULTAT_RESOLU,
            'recommandations'  => null,
        ];

        $diagnostic = trim((string) ($donneesFiche['diagnostic'] ?? ''));
        $travaux = trim((string) ($donneesFiche['travaux_realises'] ?? ($donneesFiche['resolution'] ?? '')));
        $resultat = (string) ($donneesFiche['resultat'] ?? MaintenanceRequest::RESULTAT_RESOLU);
        $recommandations = trim((string) ($donneesFiche['recommandations'] ?? ''));

        if (empty($travaux) && empty($diagnostic)) {
            throw new RuntimeException("La fiche d'intervention doit contenir le détail des travaux réalisés.");
        }

        // Consolidation pour la compatibilité avec $demande->resolution
        $sections = [];
        if ($diagnostic !== '') {
            $sections[] = "Diagnostic & Cause :\n" . $diagnostic;
        }
        if ($travaux !== '') {
            $sections[] = "Travaux réalisés :\n" . $travaux;
        }
        $resultatLibelle = MaintenanceRequest::RESULTATS[$resultat] ?? $resultat;
        $sections[] = "Résultat : " . $resultatLibelle;
        if ($recommandations !== '') {
            $sections[] = "Recommandations préventives :\n" . $recommandations;
        }
        $compteRendu = implode("\n\n", $sections);

        DB::transaction(function () use ($demande, $diagnostic, $travaux, $resultat, $resultatLibelle, $recommandations, $compteRendu, $technicien, $depot, $pieces) {
            if ($depot && !empty($pieces)) {
                $this->employerPieces($demande, $depot, $pieces, $technicien);
            }

            $demande->update([
                'status'           => MaintenanceRequest::STATUS_SUBMITTED,
                'started_at'       => $demande->started_at ?? now(),
                'diagnostic'       => $diagnostic ?: null,
                'travaux_realises' => $travaux,
                'resultat'         => $resultat,
                'recommandations'  => $recommandations ?: null,
                'resolution'       => $compteRendu,
                'fiche_soumise_by' => $technicien->id,
                'fiche_soumise_at' => now(),
            ]);

            $this->noter($demande, 'submitted', "Fiche d'intervention soumise par {$technicien->name} (Résultat : {$resultatLibelle})\n\n{$travaux}", $technicien);
        });

        // Alerte les responsables techniques pour visa
        $responsables = User::query()->active()->havingRole(self::RESPONSABLES)->get()->reject(fn ($u) => $u->id === $technicien->id);
        if ($responsables->isNotEmpty()) {
            $this->notifier->send($responsables, new Avis($demande->fresh(), Avis::SUBMITTED, $compteRendu));
        }

        return $demande->fresh();
    }

    /** Le responsable technique vise la fiche et clôture définitivement l'intervention. */
    public function viserEtCloturer(MaintenanceRequest $demande, ?string $avisResponsable, User $responsable): MaintenanceRequest
    {
        if ($demande->status !== MaintenanceRequest::STATUS_SUBMITTED) {
            throw new RuntimeException("Pour clôturer une intervention, il faut obligatoirement que la fiche d'intervention ait été soumise.");
        }

        if (!$demande->aFicheSoumise()) {
            throw new RuntimeException("Pour clôturer une intervention, il faut obligatoirement que la fiche d'intervention ait été renseignée et soumise.");
        }

        DB::transaction(function () use ($demande, $avisResponsable, $responsable) {
            $demande->update([
                'status'       => MaintenanceRequest::STATUS_DONE,
                'started_at'   => $demande->started_at ?? now(),
                'completed_at' => now(),
                'completed_by' => $responsable->id,
            ]);

            $note = $avisResponsable ? "Visé par {$responsable->name} : {$avisResponsable}" : "Fiche visée et clôturée par {$responsable->name}";
            $this->noter($demande, 'approved', $note, $responsable);
            $this->libererChambre($demande, "Intervention {$demande->reference} visée et terminée", $responsable);
        });

        $destinataires = collect([$demande->requester, $demande->technician])->filter()->unique('id');
        $this->notifier->send($destinataires->reject(fn ($u) => $u->id === $responsable->id), new Avis($demande->fresh(), Avis::COMPLETED, $demande->resolution));

        // Le housekeeping reprend la chambre qu'il avait laissée.
        if ($demande->source === MaintenanceRequest::SOURCE_HOUSEKEEPING) {
            $this->notifier->toRoles(['housekeeping_leader', 'housekeeping_supervisor'], new Avis($demande->fresh(), Avis::COMPLETED, $demande->resolution), $responsable->id);
        }

        return $demande->fresh();
    }

    /** Le responsable technique renvoie la fiche au technicien pour travail incomplet ou compléments. */
    public function demanderReprise(MaintenanceRequest $demande, string $motif, User $responsable): MaintenanceRequest
    {
        if ($demande->status !== MaintenanceRequest::STATUS_SUBMITTED) {
            throw new RuntimeException("La demande {$demande->reference} n'est pas en attente de visa.");
        }

        DB::transaction(function () use ($demande, $motif, $responsable) {
            $demande->update([
                'status' => MaintenanceRequest::STATUS_IN_PROGRESS,
            ]);
            $this->noter($demande, 'rework', $motif, $responsable);
        });

        if ($demande->technician && $demande->technician->id !== $responsable->id) {
            $this->notifier->send($demande->technician, new Avis($demande->fresh(), Avis::REOPENED, "Reprise demandée par le responsable : {$motif}"));
        }

        return $demande->fresh();
    }

    public function terminer(MaintenanceRequest $demande, array|string $fiche, User $par): MaintenanceRequest
    {
        if (!in_array($demande->status, [MaintenanceRequest::STATUS_ASSIGNED, MaintenanceRequest::STATUS_IN_PROGRESS, MaintenanceRequest::STATUS_SUBMITTED], true)) {
            throw new RuntimeException("La demande {$demande->reference} n'est pas en cours.");
        }

        // Si la fiche n'a pas encore été soumise, on exige la soumission de la fiche complète
        if (!$demande->aFicheSoumise()) {
            $donneesFiche = is_array($fiche) ? $fiche : ['travaux_realises' => $fiche];
            if (empty($donneesFiche['travaux_realises']) && empty($donneesFiche['diagnostic'])) {
                throw new RuntimeException("La fiche d'intervention doit obligatoirement être renseignée et soumise pour clôturer l'intervention.");
            }
            $demande = $this->soumettreFiche($demande, $fiche, $par);
        }

        // Si l'utilisateur est le responsable technique ou la direction : visa direct
        if ($par->exerce([...self::RESPONSABLES, 'manager']) || $par->isAdmin()) {
            $avis = is_array($fiche) ? ($fiche['note_responsable'] ?? null) : null;
            return $this->viserEtCloturer($demande, $avis, $par);
        }

        // Sinon (technicien opérationnel) : c'est une soumission de fiche au responsable
        return $demande;
    }

    public function rouvrir(MaintenanceRequest $demande, string $motif, User $par): MaintenanceRequest
    {
        if (!$demande->peutEtreRouverte()) {
            throw new RuntimeException("La demande {$demande->reference} ne se rouvre que dans les " . MaintenanceRequest::DELAI_REOUVERTURE . ' jours qui suivent sa fin.');
        }

        DB::transaction(function () use ($demande, $motif, $par) {
            $demande->update([
                'status'         => $demande->assigned_to ? MaintenanceRequest::STATUS_ASSIGNED : MaintenanceRequest::STATUS_OPEN,
                'completed_at'   => null,
                'completed_by'   => null,
                'reopened_count' => $demande->reopened_count + 1,
            ]);
            $this->noter($demande, 'reopened', $motif, $par);

            // La chambre redevient indisponible, si personne n'y loge.
            $room = $demande->room;
            if ($demande->room_blocked && $room && !in_array($room->status, [RoomStatus::OCCUPIED, RoomStatus::MAINTENANCE], true)) {
                $room->updateStatus(RoomStatus::MAINTENANCE, "Intervention {$demande->reference} rouverte", $par->id);
            }
        });

        $destinataires = User::query()->active()->havingRole(self::RESPONSABLES)->get()
            ->push($demande->technician)->filter()->reject(fn ($u) => $u->id === $par->id);
        $this->notifier->send($destinataires, new Avis($demande, Avis::REOPENED, $motif));

        return $demande;
    }

    public function annuler(MaintenanceRequest $demande, string $motif, User $par): MaintenanceRequest
    {
        if (!$demande->peutEtreAnnulee()) {
            throw new RuntimeException("La demande {$demande->reference} est commencée : elle ne s'annule plus.");
        }

        DB::transaction(function () use ($demande, $motif, $par) {
            $demande->update([
                'status'        => MaintenanceRequest::STATUS_CANCELLED,
                'cancelled_at'  => now(),
                'cancelled_by'  => $par->id,
                'cancel_reason' => $motif,
            ]);
            $this->noter($demande, 'cancelled', $motif, $par);
            $this->libererChambre($demande, "Intervention {$demande->reference} annulée", $par);
        });

        // Le technicien qui l'avait reçue ne se déplace pas pour rien ; le responsable le sait.
        $destinataires = User::query()->active()->havingRole(self::RESPONSABLES)->get()
            ->push($demande->technician)->filter()->unique('id')->reject(fn ($u) => $u->id === $par->id);
        $this->notifier->send($destinataires, new Avis($demande, Avis::CANCELLED, $motif));

        return $demande;
    }

    // ── Outils ───────────────────────────────────────────────────────────────

    /**
     * Le responsable technique et les techniciens de la spécialité ; tous
     * les techniciens si personne ne l'a, ou si la demande est à qualifier.
     */
    public function equipePour(MaintenanceRequest $demande): Collection
    {
        $techniciens = User::query()->active()->havingRole(self::TECHNICIENS)->with('technicianSpecialties')->get();
        $specialistes = $techniciens->filter(fn (User $u) => $u->technicianSpecialties->contains('specialty', $demande->specialty));

        return User::query()->active()->havingRole(self::RESPONSABLES)->get()
            ->merge($demande->specialty !== 'autre' && $specialistes->isNotEmpty() ? $specialistes : $techniciens)
            ->unique('id')
            ->values();
    }

    private function noter(MaintenanceRequest $demande, string $etape, ?string $notes, ?User $par): void
    {
        MaintenanceRequestEvent::create([
            'maintenance_request_id' => $demande->id,
            'user_id'                => $par?->id ?? Auth::id(),
            'event'                  => $etape,
            'notes'                  => $notes,
            'created_at'             => now(),
        ]);
    }

    /**
     * La chambre rendue indisponible retrouve son cycle : occupée si le
     * client loge encore, à nettoyer sinon. Une autre intervention qui la
     * bloque la garde indisponible. L'affectation de ménage interrompue par
     * le signalement reprend.
     */
    private function libererChambre(MaintenanceRequest $demande, string $motif, User $par): void
    {
        $room = $demande->room;

        if ($room && ($demande->room_blocked || $room->status === RoomStatus::MAINTENANCE)) {
            $encoreBloquee = MaintenanceRequest::query()->active()->where('room_id', $room->id)
                ->where('room_blocked', true)->whereKeyNot($demande->id)->exists();

            if (!$encoreBloquee) {
                // Dès lors que l'intervention technique est clôturée, la chambre passe directement à "À nettoyer"
                $room->updateStatus(RoomStatus::DIRTY, $motif, $par->id);
            }
        }

        $affectation = $demande->housekeepingAssignment;
        if ($affectation && $affectation->status === 'blocked') {
            $affectation->update([
                'status'      => 'cancelled',
                'released_at' => now(),
                'released_by' => $par->id,
            ]);
            if ($room) {
                \App\Models\HousekeepingLog::noter(
                    $room,
                    \App\Models\HousekeepingLog::RELEASED,
                    "Intervention technique clôturée ({$demande->reference}) : chambre remise au statut À nettoyer",
                    $affectation
                );
            }
        }
    }
}
