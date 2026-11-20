<?php

namespace App\Editions;

use App\Editions\Documents\Arrivees;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BreakfastEntitlement;
use App\Models\CashRegisterDisbursement;
use App\Models\CashRegisterSession;
use App\Models\FolioItem;
use App\Models\NightAudit;
use App\Models\Payment;
use App\Models\Room;
use App\Services\AuxiliaryLedgerService;
use App\Support\Document\Colonne;
use App\Support\Document\Document;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EtatsJournaliers
{
    private const SEJOUR_EFFECTUE = [BookingStatus::CHECKED_IN, BookingStatus::CHECKED_OUT, BookingStatus::COMPLETED];

    public function __construct(
        private readonly Registres $registres,
        private readonly AuxiliaryLedgerService $comptes,
    ) {}

    public function occupation(CarbonImmutable $jour): Document
    {
        $lignes = $this->occupationDuJour($jour);

        return Document::intitule('Occupation par catégorie')
            ->colonnes($this->colonnesOccupation())
            ->lignes($lignes)
            ->totaux($this->indicateurs(
                $lignes->sum('capacite'), $lignes->sum('occupees'), $lignes->sum('vendues'), $lignes->sum('revenu')
            ))
            ->note('Taux sur chambres actives. Prix moyen = revenu des chambres / nuitées payantes. '
                . 'RevPAR = revenu des chambres / capacité. Revenu calculé au tarif de la réservation.');
    }

    public function historiqueOccupation(CarbonImmutable $jour): Document
    {
        $archives = $this->archives($jour->startOfMonth(), $jour->subDay())
            ->addSelect('daily_report->occupation->lignes as occupation')->withCasts(['occupation' => 'array'])->get();
        $lignes = $archives->flatMap(fn (NightAudit $audit) => collect($audit->occupation)
            ->map(fn (array $ligne) => ['jour' => $audit->audit_date->toDateString()] + $ligne))
            ->concat($this->occupationDuJour($jour)->map(fn (array $ligne) => ['jour' => $jour->toDateString()] + $ligne));

        return Document::intitule('Historique mensuel d’occupation')
            ->colonnes([Colonne::date('jour', 'Jour'), ...$this->colonnesOccupation()])
            ->lignes($lignes)
            ->note('Le jour sélectionné et les jours antérieurs archivés sont affichés.');
    }

    private function occupationDuJour(CarbonImmutable $jour): Collection
    {
        $chambres = Room::query()->with('roomType')->where('is_active', true)->get();
        $sejours = $this->sejours($jour)->with('room.roomType')->get();
        $capacites = $chambres->groupBy(fn (Room $r) => $r->roomType?->name ?? 'Sans catégorie');
        $parCategorie = $sejours->groupBy(fn (Booking $b) => $b->room?->roomType?->name ?? 'Sans catégorie');

        return $capacites->keys()->merge($parCategorie->keys())->unique()->sort()->values()
            ->map(function (string $categorie) use ($capacites, $parCategorie) {
                $sejours = $parCategorie->get($categorie, collect());
                $vendues = $sejours->filter(fn (Booking $b) => ! $b->is_complimentary && $b->price_per_night > 0);

                return ['categorie' => $categorie] + $this->indicateurs(
                    $capacites->get($categorie, collect())->count(),
                    $sejours->pluck('room_id')->filter()->unique()->count(),
                    $vendues->count(),
                    $vendues->sum('price_per_night'),
                );
            });
    }

    private function indicateurs(int $capacite, int $occupees, int $vendues, int $revenu): array
    {
        return [
            'capacite' => $capacite, 'occupees' => $occupees, 'vendues' => $vendues,
            'taux' => $capacite ? round($occupees * 100 / $capacite, 2) : null,
            'revenu' => $revenu,
            'adr' => $vendues ? (int) round($revenu / $vendues) : null,
            'revpar' => $capacite ? (int) round($revenu / $capacite) : null,
        ];
    }

    private function colonnesOccupation(): array
    {
        return [
            Colonne::texte('categorie', 'Catégorie'),
            Colonne::nombre('capacite', 'Capacité'),
            Colonne::nombre('occupees', 'Occupées'),
            Colonne::nombre('vendues', 'Nuitées payantes'),
            Colonne::nombre('taux', 'Occupation (%)'),
            Colonne::montant('revenu', 'Revenu chambres'),
            Colonne::montant('adr', 'Prix moyen'),
            Colonne::montant('revpar', 'RevPAR'),
        ];
    }

    public function comparatif(CarbonImmutable $jour): Document
    {
        $finPrecedente = $jour->subMonthNoOverflow();
        $totaux = ['daily_report->situation->totaux->ventes as ventes', 'daily_report->situation->totaux->encaisse as encaisse'];
        $mois = $this->archives($jour->startOfMonth(), $jour->subDay())->addSelect($totaux)->get();
        $precedent = $this->archives($finPrecedente->startOfMonth(), $finPrecedente)->addSelect($totaux)->get();
        $sommes = [
            'ventes' => $this->registres->ventes($jour, $jour)->sum('montant'),
            'encaisse' => $this->registres->encaissements($jour, $jour)->sum('montant'),
        ];
        $lignes = collect(['ventes' => 'Ventes', 'encaisse' => 'Encaissements'])
            ->map(fn ($libelle, $cle) => [
                'indicateur' => $libelle,
                'jour' => $sommes[$cle],
                'mois' => $sommes[$cle] + $mois->sum($cle),
                'precedent' => $precedent->isEmpty() ? null : $precedent->sum($cle),
            ]);

        return Document::intitule('Comparatif des ventes et encaissements')
            ->colonnes([
                Colonne::texte('indicateur', 'Indicateur'),
                Colonne::montant('jour', 'Journée'),
                Colonne::montant('mois', 'Cumul du mois'),
                Colonne::montant('precedent', 'Mois précédent à date'),
            ])->lignes($lignes)
            ->note('Couverture du cumul : ' . ($mois->count() + 1) . '/' . $jour->day . ' jours. '
                . 'Mois précédent jusqu’au ' . $finPrecedente->format('d/m/Y') . ' : '
                . $precedent->count() . '/' . $finPrecedente->day . ' jours. '
                . 'Les cumuls comprennent le jour sélectionné et les journées antérieures archivées.');
    }

    private function archives(CarbonImmutable $du, CarbonImmutable $au): Builder
    {
        return NightAudit::query()
            ->whereDate('audit_date', '>=', $du->toDateString())
            ->whereDate('audit_date', '<=', $au->toDateString())
            ->whereNotNull('daily_report')->orderBy('audit_date')->select('audit_date');
    }

    public function arrivees(CarbonImmutable $jour): Document
    {
        return $this->planning($jour, true);
    }

    public function departs(CarbonImmutable $jour): Document
    {
        return $this->planning($jour, false);
    }

    private function planning(CarbonImmutable $jour, bool $arrivees): Document
    {
        $prevu = $arrivees ? 'check_in' : 'check_out';
        $reel = $arrivees ? 'actual_check_in' : 'actual_check_out';
        $date = $jour->toDateString();
        $lignes = Booking::query()->with(['customer', 'room.roomType', 'groupBooking', 'roomPackage'])
            ->whereNotIn('status', [BookingStatus::DRAFT, BookingStatus::CANCELLED])
            ->when(! $arrivees, fn ($q) => $q->where('status', '!=', BookingStatus::NO_SHOW))
            ->where(fn (Builder $q) => $q->whereDate($prevu, $date)->orWhereDate($reel, $date))
            ->orderBy('id')->get()
            ->map(fn (Booking $b) => Arrivees::ligneDeSejour($b) + [
                'prevision' => $b->{$prevu}, 'reel' => $b->{$reel},
                'groupe' => $b->groupBooking?->group_name,
                'source' => $b->sourceLabel(),
                'formule' => $b->roomPackage?->name,
                'tarif' => $b->price_per_night,
                'acompte' => $b->deposit_amount,
                'commentaire' => $b->notes,
                'non_presente' => $b->status === BookingStatus::NO_SHOW,
            ]);
        $compter = fn (string $champ) => $lignes->filter(fn (array $l) => $l[$champ]?->toDateString() === $date)->count();
        $note = 'Prévus : ' . $compter('prevision') . ' · Effectués : ' . $compter('reel');

        return Document::intitule($arrivees ? 'Arrivées de la journée' : 'Départs de la journée')
            ->colonnes([
                Colonne::texte('chambre', 'Chambre'),
                Colonne::texte('numero', 'Réservation'),
                Colonne::texte('client', 'Client'),
                Colonne::texte('personnes', 'Pers.'),
                Colonne::date('prevision', 'Prévu'),
                Colonne::dateHeure('reel', 'Réel'),
                Colonne::texte('statut', 'Statut'),
                Colonne::texte('groupe', 'Groupe'),
                Colonne::texte('source', 'Canal'),
                Colonne::texte('formule', 'Formule'),
                Colonne::montant('tarif', 'Tarif / nuit', false),
                Colonne::montant('acompte', 'Acompte'),
                Colonne::montant('solde', 'Solde dû'),
                Colonne::texte('commentaire', 'Notes'),
            ])->lignes($lignes)
            ->totaux($this->sommes($lignes, ['acompte', 'solde']))
            ->note($arrivees ? $note . ' · Non présentés : ' . $lignes->where('non_presente', true)->count() : $note);
    }

    public function encours(CarbonImmutable $jour): Document
    {
        $lignes = $this->sejours($jour)->with(['customer', 'room.roomType', 'invoice', 'groupBooking', 'folioItems' =>
            fn ($q) => $q->whereBetween('occurred_at', [$jour->startOfDay(), $jour->endOfDay()])])->get()
            ->sortBy(fn (Booking $b) => $b->room?->number)
            ->map(function (Booking $b) {
                $prestations = $b->folioItems->whereNotIn('type', [FolioItem::TYPE_ROOM, FolioItem::TYPE_PAYMENT,
                    FolioItem::TYPE_DISCOUNT]);

                return Arrivees::ligneDeSejour($b) + [
                    'groupe' => $b->groupBooking?->group_name,
                    'jour' => ($b->is_complimentary ? 0 : $b->price_per_night)
                        + $prestations->where('is_complimentary', false)->sum('total_price'),
                    'remises' => abs($b->folioItems->where('type', FolioItem::TYPE_DISCOUNT)->sum('total_price')),
                    'offerts' => $prestations->where('is_complimentary', true)
                        ->sum(fn (FolioItem $f) => (int) round($f->unit_price * $f->quantity)),
                    'facture' => $b->invoice?->invoice_number,
                    'paye' => $b->paid_amount,
                ];
            })->values();

        return Document::intitule('Clients présents et encours de séjour')
            ->colonnes([
                Colonne::texte('chambre', 'Chambre'),
                Colonne::texte('numero', 'Réservation'),
                Colonne::texte('client', 'Client'),
                Colonne::texte('groupe', 'Groupe'),
                Colonne::texte('personnes', 'Pers.'),
                Colonne::date('arrivee', 'Arrivée'),
                Colonne::date('depart', 'Départ'),
                Colonne::montant('jour', 'Prestations du jour'),
                Colonne::montant('remises', 'Remises du jour'),
                Colonne::montant('offerts', 'Extras offerts'),
                Colonne::texte('facture', 'Facture'),
                Colonne::montant('paye', 'Réglé séjour'),
                Colonne::montant('solde', 'Solde séjour'),
            ])->lignes($lignes)
            ->totaux($this->sommes($lignes, ['jour', 'remises', 'offerts', 'paye', 'solde']))
            ->note('Les prestations et remises sont datées du jour. Les règlements et soldes portent sur le séjour.');
    }

    public function prestations(CarbonImmutable $jour): Document
    {
        $lignes = collect();
        $sejours = $this->sejours($jour)->with(['room', 'folioItems' =>
            fn ($q) => $q->where('type', FolioItem::TYPE_ROOM)->orderBy('id')])->orderBy('id')->get();
        foreach ($sejours as $sejour) {
            $ligne = ['numero' => $sejour->booking_number, 'chambre' => $sejour->room?->number];
            $folio = $sejour->folioItems->first();
            $lignes->push($ligne + [
                'prestation' => 'Nuitée',
                'prevu' => $sejour->is_complimentary ? 0 : $sejour->price_per_night,
                'reference' => $folio?->id,
                'etat' => $folio ? 'Folio séjour présent' : 'Ligne hébergement absente',
            ]);
            if ($sejour->tax_amount > 0) {
                $lignes->push($ligne + [
                    'prestation' => 'Taxes du séjour', 'global' => $sejour->tax_amount,
                    'etat' => 'Montant global du séjour',
                ]);
            }
        }
        foreach (BreakfastEntitlement::forDate($jour->toDateString())->with(['booking', 'room'])->orderBy('id')->get() as $droit) {
            $lignes->push([
                'numero' => $droit->booking?->booking_number, 'chambre' => $droit->room?->number,
                'prestation' => 'Petit-déjeuner (' . ($droit->adults_included + $droit->children_included) . ' pers.)',
                'etat' => match ($droit->status) {
                    BreakfastEntitlement::STATUS_AVAILABLE => 'Prévu',
                    BreakfastEntitlement::STATUS_CONSUMED => 'Servi',
                    BreakfastEntitlement::STATUS_PARTIAL => 'Partiellement servi',
                    BreakfastEntitlement::STATUS_CANCELLED => 'Annulé',
                    default => $droit->status,
                },
            ]);
        }

        return Document::intitule('Contrôle des prestations de séjour')
            ->colonnes([
                Colonne::texte('chambre', 'Chambre'),
                Colonne::texte('numero', 'Réservation'),
                Colonne::texte('prestation', 'Prestation'),
                Colonne::montant('prevu', 'Prévu à la date'),
                Colonne::montant('global', 'Montant séjour', false),
                Colonne::nombre('reference', 'Réf. folio'),
                Colonne::texte('etat', 'Constat'),
            ])->lignes($lignes)
            ->note('L’hébergement et les taxes sont enregistrés par séjour. '
                . 'Le petit-déjeuner est suivi à la date de service.');
    }

    public function mouvements(CarbonImmutable $jour): Document
    {
        $periode = [$jour->startOfDay(), $jour->endOfDay()];
        $fonds = CashRegisterSession::query()->with('user')->whereBetween('opened_at', $periode)->get()
            ->map(fn (CashRegisterSession $s) => [
                'date' => $s->opened_at, 'session' => $s->id, 'type' => 'Fonds d’ouverture',
                'mode' => 'Espèces', 'par' => $s->user?->name, 'montant' => (int) $s->opening_amount,
            ]);
        $sorties = CashRegisterDisbursement::query()->with('user')->whereBetween('created_at', $periode)->get()
            ->map(fn (CashRegisterDisbursement $s) => [
                'date' => $s->created_at, 'session' => $s->cash_register_session_id, 'type' => 'Décaissement',
                'mode' => 'Espèces', 'par' => $s->user?->name, 'piece' => '#' . $s->id,
                'motif' => $s->reason, 'montant' => -abs((int) $s->amount),
            ]);
        $paiements = Payment::query()->cashFlows()->with(['processedBy', 'booking'])
            ->where('status', 'completed')->whereBetween('paid_at', $periode)
            ->where(fn ($q) => $q->where('payment_origin', Payment::ORIGIN_ACOMPTE_RESERVATION)->orWhere('amount', '<', 0))
            ->get()->map(fn (Payment $p) => [
                'date' => $p->paid_at, 'session' => $p->cash_register_session_id,
                'type' => $p->amount < 0 ? 'Remboursement' : 'Acompte de réservation',
                'mode' => Registres::mode($p->method), 'par' => $p->processedBy?->name,
                'piece' => $p->reference, 'motif' => $p->booking?->booking_number, 'montant' => (int) $p->amount,
            ]);

        return Document::intitule('Mouvements de caisse et arrhes')
            ->colonnes([
                Colonne::dateHeure('date', 'Date'),
                Colonne::nombre('session', 'Session'),
                Colonne::texte('type', 'Type'),
                Colonne::texte('mode', 'Mode'),
                Colonne::texte('par', 'Opérateur'),
                Colonne::texte('piece', 'Pièce'),
                Colonne::texte('motif', 'Motif / réservation'),
                Colonne::montant('montant', 'Montant'),
            ])->lignes($fonds->concat($sorties)->concat($paiements)->sortBy('date')->values())
            ->note('Les fonds d’ouverture sont des reports d’espèces. Les acomptes et remboursements figurent aussi '
                . 'dans le journal des encaissements.');
    }

    public function balanceAgee(CarbonImmutable $jour): Document
    {
        $lignes = $this->comptes->agedBalanceByMonth($jour);
        $mois = $jour->startOfMonth();

        return Document::intitule('Balance âgée clients par mois')
            ->colonnes([
                Colonne::texte('client', 'Client'),
                Colonne::montant('total', 'Solde'),
                Colonne::montant('m0', $mois->format('m/Y')),
                Colonne::montant('m1', $mois->subMonth()->format('m/Y')),
                Colonne::montant('m2', $mois->subMonths(2)->format('m/Y')),
                Colonne::montant('m3', $mois->subMonths(3)->format('m/Y')),
                Colonne::montant('avant', 'Antérieur'),
            ])->lignes($lignes)->totaux($this->sommes($lignes, ['total', 'm0', 'm1', 'm2', 'm3', 'avant']))
            ->note('Solde des écritures clients validées au jour sélectionné, ventilé selon le mois de chaque écriture. '
                . 'Les crédits non lettrés apparaissent dans le mois de leur écriture.');
    }

    private function sejours(CarbonImmutable $jour): Builder
    {
        $date = $jour->toDateString();

        return Booking::query()->whereIn('status', self::SEJOUR_EFFECTUE)
            ->where(fn (Builder $q) => $q->whereDate('actual_check_in', '<=', $date)
                ->orWhere(fn (Builder $p) => $p->whereNull('actual_check_in')->whereDate('check_in', '<=', $date)))
            ->where(fn (Builder $q) => $q->whereDate('actual_check_out', '>', $date)
                ->orWhere(fn (Builder $p) => $p->whereNull('actual_check_out')->whereDate('check_out', '>', $date)));
    }

    private function sommes(Collection $lignes, array $cles): array
    {
        return array_combine($cles, array_map(fn (string $cle) => (int) $lignes->sum($cle), $cles));
    }
}
