<?php

namespace App\Editions;

use App\Editions\Documents;
use App\Models\NightAudit;
use App\Models\User;
use App\Services\PermissionResolver;
use App\Support\Document\Document;
use Carbon\CarbonImmutable;

class DossierJournalier
{
    public const TITRE = 'Dossier journalier hôtelier';

    private const ETATS = [
        'situation' => [Documents\SituationJournaliere::class, 'accounting.journal'],
        'occupation' => ['occupation', 'bookings.voir'],
        'historique-occupation' => ['historiqueOccupation', 'bookings.voir'],
        'comparatif' => ['comparatif', 'accounting.journal'],
        'ventes' => [Documents\JournalVentes::class, 'accounting.journal'],
        'factures' => [Documents\Factures::class, 'accounting.journal'],
        'encaissements' => [Documents\JournalEncaissements::class, 'accounting.journal'],
        'recap-encaissements' => [Documents\RecapEncaissements::class, 'accounting.journal'],
        'caisses' => [Documents\SessionsCaisse::class, 'accounting.cash_reviews'],
        'mouvements' => ['mouvements', 'accounting.cash_reviews'],
        'encours' => ['encours', 'bookings.voir'],
        'prestations' => ['prestations', 'bookings.voir'],
        'balance-agee' => ['balanceAgee', 'accounting.ledger.aged'],
        'arrivees' => ['arrivees', 'bookings.voir'],
        'departs' => ['departs', 'bookings.voir'],
    ];

    public function __construct(
        private readonly EtatsJournaliers $etats,
        private readonly PermissionResolver $permissions,
    ) {}

    /** @return list<string> */
    public function accessibles(User $user): array
    {
        return array_keys(array_filter(self::ETATS, function (array $etat) use ($user) {
            [$source, $droit] = $etat;

            return $this->permissions->allows($user, $droit)
                && (! is_subclass_of($source, Edition::class) || app($source)->accessiblePour($user));
        }));
    }

    /** @return array<string, Document> */
    public function lire(CarbonImmutable $jour, ?NightAudit $audit, array $cles, User $user): array
    {
        $documents = [];
        $figes = $audit?->daily_report;
        $cloture = match (true) {
            $figes !== null => 'Clôturé le ' . $audit->closed_at->format('d/m/Y à H:i') . ' · Clôture n° ' . $audit->id,
            $audit !== null => 'Clôture ancienne · Dossier reconstitué',
            default => 'Provisoire',
        };

        foreach ($cles as $cle) {
            // Une feuille ajoutée après la clôture ne fait pas partie de son dossier.
            if ($figes !== null && ! isset($figes[$cle])) {
                continue;
            }

            $document = $figes === null
                ? $this->produire($cle, $jour, $user)
                : Document::depuisInstantane($figes[$cle]);

            $documents[$cle] = $document->filtres([
                'Date d’exploitation' => $jour->format('d/m/Y'),
                'État du dossier' => $cloture,
            ] + $document->lesFiltres());
        }

        return $documents;
    }

    public function figer(CarbonImmutable $jour): array
    {
        // La clôture conserve le dossier de l’établissement. Les droits du
        // lecteur sont évalués à chaque consultation ou export.
        $user = new User;
        $documents = [];
        foreach (array_keys(self::ETATS) as $cle) {
            $documents[$cle] = $this->produire($cle, $jour, $user)->instantane();
        }

        return $documents;
    }

    private function produire(string $cle, CarbonImmutable $jour, User $user): Document
    {
        [$source] = self::ETATS[$cle];
        if (is_subclass_of($source, Edition::class)) {
            $edition = app($source);
            $date = $jour->toDateString();
            $valeurs = $edition->valeurs(['jour' => $date, 'du' => $date, 'au' => $date], $user);

            return $edition->document($valeurs + ['etablissement' => true], $user);
        }

        return $this->etats->{$source}($jour);
    }
}
