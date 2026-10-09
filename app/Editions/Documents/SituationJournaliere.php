<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Editions\Registres;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CashRegisterSession;
use App\Models\NightAudit;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/**
 * La main courante d'une journée : ce que chaque service a vendu et encaissé,
 * par mode de règlement, avec l'occupation et l'état des caisses.
 */
class SituationJournaliere extends Edition
{
    public function __construct(private readonly Registres $registres) {}

    public function cle(): string { return 'situation-journaliere'; }

    public function famille(): string { return self::FINANCES; }

    public function titre(): string { return 'Situation journalière'; }

    public function description(): string
    {
        return "La main courante d'un jour : ventes et encaissements de chaque service, ventilés par mode de règlement, avec l'occupation, les arrivées et départs, et l'état des caisses.";
    }

    public function droits(): array { return ['accounting.voir', 'accounting.journal', 'analytics.voir']; }

    public function filtres(): array
    {
        return [
            Filtre::jour('Journée'),
            Filtre::choix('service', 'Service', fn (User $u) => $this->registres->services($u), 'Tous les services'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        $jour = $valeurs['jour'];
        $ventes = $this->registres->ventes($jour, $jour, $valeurs['service']);
        $encaissements = $this->registres->encaissements($jour, $jour, $valeurs['service']);

        $services = collect($this->registres->services($user))
            ->filter(fn ($libelle, $cle) => $valeurs['service'] === '' || $valeurs['service'] === $cle);

        $lignes = $services->map(function (string $libelle, string $cle) use ($ventes, $encaissements) {
            $v = $ventes->where('service_cle', $cle);
            $e = $encaissements->where('service_cle', $cle);
            $parFamille = fn (string $famille) => (int) $e->filter(fn ($l) => Registres::familleDeMode($l['mode_cle']) === $famille)->sum('montant');

            return [
                'service' => $libelle,
                'ventes' => (int) $v->sum('montant'),
                'chambre' => (int) $v->where('reglement', 'Sur la chambre')->sum('montant'),
                'encaisse' => (int) $e->sum('montant'),
                'especes' => $parFamille('especes'),
                'mobile' => $parFamille('mobile'),
                'carte' => $parFamille('carte'),
                'autres' => $parFamille('autres'),
            ];
        })->values();

        $totaux = collect(['ventes', 'chambre', 'encaisse', 'especes', 'mobile', 'carte', 'autres'])
            ->mapWithKeys(fn ($c) => [$c => (int) $lignes->sum($c)])->all();

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::texte('service', 'Service'),
                Colonne::montant('ventes', 'Ventes'),
                Colonne::montant('chambre', 'dont sur la chambre'),
                Colonne::montant('encaisse', 'Encaissé'),
                Colonne::montant('especes', 'Espèces'),
                Colonne::montant('mobile', 'Mobile money'),
                Colonne::montant('carte', 'Carte'),
                Colonne::montant('autres', 'Autres'),
            ])
            ->lignes($lignes)
            ->totaux($totaux)
            ->note($this->constat($jour));
    }

    private function constat(\Carbon\CarbonImmutable $jour): string
    {
        $h = $this->registres->hebergement($jour, $jour);
        $chambres = $h['capacite'];
        $to = $chambres > 0 ? round($h['nuitees'] * 100 / $chambres) : 0;
        $date = $jour->toDateString();

        $arrivees = Booking::query()->whereDate('check_in', $date)
            ->whereIn('status', [BookingStatus::CONFIRMED, BookingStatus::CHECKED_IN, BookingStatus::CHECKED_OUT, BookingStatus::COMPLETED])->count();
        $departs = Booking::query()->whereDate('check_out', $date)
            ->whereIn('status', [BookingStatus::CHECKED_IN, BookingStatus::CHECKED_OUT, BookingStatus::COMPLETED])->count();

        $sessions = CashRegisterSession::query()->whereDate('opened_at', $date)->get();
        $ouvertes = $sessions->whereNull('closed_at')->count();
        $ecart = (int) $sessions->whereNotNull('closed_at')->sum('discrepancy_amount');

        return "Hébergement : {$h['nuitees']} chambre(s) occupée(s) sur {$chambres} ({$to} %), {$arrivees} arrivée(s), {$departs} départ(s)"
            . ($h['gratuites'] ? ", dont {$h['gratuites']} offerte(s)" : '') . '. '
            . "Caisses ouvertes ce jour : {$sessions->count()}, dont {$ouvertes} encore ouverte(s) ; écart constaté : "
            . number_format($ecart / 100, 0, ',', ' ') . ' FCFA. '
            . (NightAudit::isClosed($jour) ? 'Journée clôturée par l\'audit de nuit.' : 'Journée non encore clôturée par l\'audit de nuit : les chiffres peuvent encore bouger.');
    }
}
