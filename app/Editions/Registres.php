<?php

namespace App\Editions;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\FolioItem;
use App\Models\Payment;
use App\Models\PointOfSale;
use App\Models\ReceptionSale;
use App\Models\RestaurantBanquetPayment;
use App\Models\RestaurantBuffetEntry;
use App\Models\RestaurantCustomerOrder;
use App\Models\Room;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\RestaurantContext;
use App\Support\TenantModules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Les registres d'où sortent les éditions financières : ce qui a été vendu,
 * ce qui a été encaissé, ligne par ligne, tous services réunis.
 *
 * Deux règles, sans lesquelles aucun total ne tomberait juste :
 *
 *  - **Une vente est comptée là où elle naît, une seule fois.** Un repas
 *    porté à la chambre est une vente du restaurant ; la ligne qu'il ajoute
 *    au folio n'en est pas une seconde. Les nuitées se comptent nuit par
 *    nuit, pour les séjours réellement effectués.
 *  - **Un encaissement est compté sur sa pièce.** Une vente de la réception
 *    réglée par un règlement (payments) n'est pas recomptée ; une consommation
 *    portée à la chambre n'est pas un encaissement : elle le sera au règlement
 *    du séjour.
 *
 * Montants en centimes.
 */
class Registres
{
    public const MODES = [
        'cash' => 'Espèces',
        'mobile_money' => 'Mobile money',
        'orange_money' => 'Orange Money',
        'mtn_momo' => 'MTN MoMo',
        'card' => 'Carte',
        'stripe' => 'Carte (en ligne)',
        'bank_transfer' => 'Virement',
        'transfer' => 'Virement',
        'check' => 'Chèque',
        'room_charge' => 'Sur la chambre',
        'other' => 'Autre',
    ];

    public const SERVICE_HEBERGEMENT = 'hebergement';
    public const SERVICE_RECEPTION = 'reception';
    public const SERVICE_BOUTIQUE = 'boutique';
    public const PREFIXE_RESTAURANT = 'restaurant-';

    /** Séjours effectués : leurs nuitées sont des ventes. */
    private const SEJOURS_EFFECTUES = [BookingStatus::CHECKED_IN, BookingStatus::CHECKED_OUT, BookingStatus::COMPLETED];

    /** @var array<int, string>|null */
    private ?array $utilisateurs = null;

    /** @var array<int, PointOfSale>|null */
    private ?array $pointsDeVente = null;

    /**
     * Services proposés au filtre : l'hébergement, la réception, la boutique et
     * chaque restaurant que la personne peut voir.
     *
     * @return array<string, string>
     */
    public function services(User $user): array
    {
        $services = [self::SERVICE_HEBERGEMENT => 'Hébergement', self::SERVICE_RECEPTION => 'POS Réception'];

        if (TenantModules::has('restaurant')) {
            foreach (app(RestaurantContext::class)->accessibles($user) as $restaurant) {
                $services[self::PREFIXE_RESTAURANT . $restaurant->id] = $restaurant->name;
            }
        }

        if (TenantModules::has('shop')) {
            $services[self::SERVICE_BOUTIQUE] = 'Boutique';
        }

        return $services;
    }

    /** @return array<string, string> */
    public function modes(): array
    {
        return array_diff_key(self::MODES, ['room_charge' => true, 'transfer' => true]);
    }

    public static function mode(?string $mode): string
    {
        return self::MODES[$mode ?? ''] ?? ($mode ? ucfirst(str_replace('_', ' ', $mode)) : 'Non précisé');
    }

    /**
     * Encaissements de la période, ligne par ligne.
     *
     * @return Collection<int, array{date: CarbonImmutable, service: string, service_cle: string, piece: string, client: string, mode: string, mode_cle: string, par: string, montant: int}>
     */
    public function encaissements(CarbonImmutable $du, CarbonImmutable $au, string $service = '', string $mode = ''): Collection
    {
        [$debut, $fin] = [$du->startOfDay(), $au->endOfDay()];
        $lignes = collect();

        // Règlements : séjours, groupes et ventes de la réception qui en portent un.
        $ventesReception = ReceptionSale::query()->whereNotNull('payment_id')->pluck('sale_number', 'payment_id');
        $reglements = Payment::query()->where('status', 'completed')->whereBetween('paid_at', [$debut, $fin])->get();
        $sejours = Booking::query()->whereIn('id', $reglements->pluck('booking_id')->filter()->unique())->pluck('booking_number', 'id');
        $clients = $this->clients($reglements->pluck('customer_id'));

        foreach ($reglements as $r) {
            $pos = isset($ventesReception[$r->id]);
            $lignes->push($this->ligne(
                $r->paid_at, $pos ? self::SERVICE_RECEPTION : self::SERVICE_HEBERGEMENT, $pos ? 'POS Réception' : 'Hébergement',
                $pos ? 'Vente ' . $ventesReception[$r->id] : ($sejours[$r->booking_id] ?? $r->reference ?? '#' . $r->id),
                $clients[$r->customer_id] ?? '—', $r->method, $r->processed_by, (int) $r->amount
            ));
        }

        // Ventes de la réception réglées sans pièce de règlement.
        foreach (ReceptionSale::query()->whereNull('payment_id')->where('payment_status', 'paid')
            ->where(fn ($q) => $q->where('payment_method', '!=', 'room_charge')->orWhereNull('payment_method'))
            ->whereBetween('paid_at', [$debut, $fin])->get() as $v) {
            $lignes->push($this->ligne($v->paid_at, self::SERVICE_RECEPTION, 'POS Réception', 'Vente ' . $v->sale_number,
                $v->customer_name ?: '—', $v->payment_method, $v->user_id, (int) $v->total_amount));
        }

        if (TenantModules::has('restaurant')) {
            foreach (RestaurantCustomerOrder::query()->where('payment_status', 'paid')
                ->where(fn ($q) => $q->where('payment_method', '!=', 'room_charge')->orWhereNull('payment_method'))
                ->whereBetween('paid_at', [$debut, $fin])->get() as $c) {
                $point = $this->pointDeVente($c->point_of_sale_id);
                $lignes->push($this->ligne($c->paid_at, self::PREFIXE_RESTAURANT . $c->point_of_sale_id, $point?->name ?? 'Restaurant',
                    'Note ' . ($point ? $point->numero($c->id) : $c->id),
                    $c->customer_name ?: ($c->table_number ? 'Table ' . $c->table_number : '—'),
                    $c->payment_method, $c->paid_by, (int) $c->amount_paid));
            }

            foreach (RestaurantBuffetEntry::query()->with('service')
                ->where(fn ($q) => $q->where('payment_method', '!=', 'room_charge')->orWhereNull('payment_method'))
                ->whereBetween('created_at', [$debut, $fin])->get() as $e) {
                $point = $this->pointDeVente($e->point_of_sale_id);
                $lignes->push($this->ligne($e->created_at, self::PREFIXE_RESTAURANT . $e->point_of_sale_id, $point?->name ?? 'Restaurant',
                    'Buffet du ' . ($e->service?->service_date?->format('d/m') ?? '—'),
                    $e->adults . ' adulte(s)' . ($e->children ? ', ' . $e->children . ' enfant(s)' : ''),
                    $e->payment_method, $e->recorded_by, (int) $e->amount));
            }

            foreach (RestaurantBanquetPayment::query()->with('banquet')->whereBetween('paid_at', [$debut, $fin])->get() as $b) {
                $point = $this->pointDeVente($b->banquet?->point_of_sale_id);
                $lignes->push($this->ligne($b->paid_at, self::PREFIXE_RESTAURANT . $b->banquet?->point_of_sale_id, $point?->name ?? 'Restaurant',
                    'Banquet ' . ($b->banquet?->reference ?? '#' . $b->restaurant_banquet_id) . ($b->kind ? ' (' . $b->kind . ')' : ''),
                    $b->banquet?->client_name ?: '—', $b->payment_method, $b->paid_by, (int) $b->amount));
            }
        }

        if (TenantModules::has('shop')) {
            foreach (ShopOrder::query()->where('payment_status', 'paid')
                ->where(fn ($q) => $q->where('payment_method', '!=', 'room_charge')->orWhereNull('payment_method'))
                ->whereBetween('paid_at', [$debut, $fin])->get() as $o) {
                $lignes->push($this->ligne($o->paid_at, self::SERVICE_BOUTIQUE, 'Boutique', 'Vente ' . $o->order_number,
                    $o->customer_name ?: '—', $o->payment_method, $o->created_by, (int) $o->total_amount));
            }
        }

        return $lignes
            ->filter(fn (array $l) => $service === '' || $l['service_cle'] === $service)
            ->filter(fn (array $l) => $mode === '' || $l['mode_cle'] === $mode)
            ->sortBy(fn (array $l) => $l['date']->timestamp)
            ->values();
    }

    /**
     * Ventes de la période, ligne par ligne, chacune comptée là où elle naît.
     *
     * @return Collection<int, array{date: CarbonImmutable, service: string, service_cle: string, piece: string, client: string, designation: string, reglement: string, montant: int}>
     */
    public function ventes(CarbonImmutable $du, CarbonImmutable $au, string $service = ''): Collection
    {
        [$debut, $fin] = [$du->startOfDay(), $au->endOfDay()];
        $lignes = collect();

        // Nuitées des séjours effectués, nuit par nuit, hors gratuités.
        $sejours = Booking::query()
            ->whereIn('status', self::SEJOURS_EFFECTUES)
            ->where('is_complimentary', false)
            ->whereDate('check_in', '<=', $au->toDateString())
            ->whereDate('check_out', '>', $du->toDateString())
            ->get();
        $clients = $this->clients($sejours->pluck('customer_id'));
        $chambres = Room::query()->whereIn('id', $sejours->pluck('room_id')->unique())->pluck('number', 'id');

        foreach ($sejours as $s) {
            $premiere = CarbonImmutable::parse($s->check_in)->max($du);
            $derniere = CarbonImmutable::parse($s->check_out)->subDay()->min($au);
            $nuits = (int) $premiere->diffInDays($derniere) + 1;

            if ($nuits <= 0 || (int) $s->price_per_night === 0) {
                continue;
            }

            $lignes->push($this->vente($premiere, self::SERVICE_HEBERGEMENT, 'Hébergement', $s->booking_number,
                $clients[$s->customer_id] ?? '—',
                $nuits . ' nuitée(s) — Ch. ' . ($chambres[$s->room_id] ?? '?'), 'Séjour', $nuits * (int) $s->price_per_night));
        }

        // Prestations portées au folio par la réception : tout sauf les nuitées,
        // les règlements et ce qu'un autre service a déjà vendu.
        $nees = $this->lignesDeFolioNeesAilleurs();
        $prestations = FolioItem::query()
            ->whereNotIn('type', [FolioItem::TYPE_ROOM, FolioItem::TYPE_PAYMENT])
            ->where('is_complimentary', false)
            ->whereBetween('occurred_at', [$debut, $fin])
            ->when($nees !== [], fn ($q) => $q->whereNotIn('id', $nees))
            ->get();
        $sejoursPrestations = Booking::query()->whereIn('id', $prestations->pluck('booking_id')->unique())->pluck('booking_number', 'id');
        $clientsPrestations = $this->clients($prestations->pluck('customer_id'));

        foreach ($prestations as $f) {
            $lignes->push($this->vente(CarbonImmutable::parse($f->occurred_at), self::SERVICE_HEBERGEMENT, 'Hébergement',
                $sejoursPrestations[$f->booking_id] ?? '—', $clientsPrestations[$f->customer_id] ?? '—',
                (string) $f->description, 'Séjour', (int) $f->total_price));
        }

        foreach (ReceptionSale::query()->whereIn('payment_status', ['paid', 'charged_to_room'])
            ->whereBetween('created_at', [$debut, $fin])->get() as $v) {
            $lignes->push($this->vente(CarbonImmutable::parse($v->created_at), self::SERVICE_RECEPTION, 'POS Réception', 'Vente ' . $v->sale_number,
                $v->customer_name ?: ($v->room_number ? 'Ch. ' . $v->room_number : '—'), 'Vente au comptoir',
                $v->payment_status === 'charged_to_room' ? 'Sur la chambre' : self::mode($v->payment_method), (int) $v->total_amount));
        }

        if (TenantModules::has('restaurant')) {
            foreach (RestaurantCustomerOrder::query()->where('status', '!=', 'canceled')->where('is_complimentary', false)
                ->whereBetween('placed_at', [$debut, $fin])->withCount('items')->get() as $c) {
                $point = $this->pointDeVente($c->point_of_sale_id);
                $lignes->push($this->vente(CarbonImmutable::parse($c->placed_at), self::PREFIXE_RESTAURANT . $c->point_of_sale_id, $point?->name ?? 'Restaurant',
                    'Note ' . ($point ? $point->numero($c->id) : $c->id),
                    $c->customer_name ?: ($c->table_number ? 'Table ' . $c->table_number : '—'),
                    $c->items_count . ' article(s)', $this->reglementCommande($c->payment_status, $c->payment_method), (int) $c->total_amount));
            }

            foreach (RestaurantBuffetEntry::query()->with('service')->whereBetween('created_at', [$debut, $fin])->get() as $e) {
                $point = $this->pointDeVente($e->point_of_sale_id);
                $lignes->push($this->vente(CarbonImmutable::parse($e->created_at), self::PREFIXE_RESTAURANT . $e->point_of_sale_id, $point?->name ?? 'Restaurant',
                    'Buffet du ' . ($e->service?->service_date?->format('d/m') ?? '—'), '—',
                    $e->adults . ' adulte(s)' . ($e->children ? ', ' . $e->children . ' enfant(s)' : ''),
                    self::mode($e->payment_method), (int) $e->amount));
            }

            foreach (\App\Models\RestaurantBanquet::query()->whereIn('status', ['realise', 'solde'])
                ->whereDate('event_date', '>=', $du->toDateString())->whereDate('event_date', '<=', $au->toDateString())->get() as $b) {
                $point = $this->pointDeVente($b->point_of_sale_id);
                $lignes->push($this->vente(CarbonImmutable::parse($b->event_date), self::PREFIXE_RESTAURANT . $b->point_of_sale_id, $point?->name ?? 'Restaurant',
                    'Banquet ' . $b->reference, $b->client_name ?: '—', $b->title . ' — ' . $b->covers . ' couvert(s)',
                    $b->status === 'solde' ? 'Soldé' : 'À solder', (int) $b->total_amount));
            }
        }

        if (TenantModules::has('shop')) {
            foreach (ShopOrder::query()->where('payment_status', '!=', 'cancelled')
                ->whereBetween('created_at', [$debut, $fin])->get() as $o) {
                $lignes->push($this->vente(CarbonImmutable::parse($o->created_at), self::SERVICE_BOUTIQUE, 'Boutique', 'Vente ' . $o->order_number,
                    $o->customer_name ?: '—', $o->total_items . ' article(s)',
                    $this->reglementCommande($o->payment_status, $o->payment_method), (int) $o->total_amount));
            }
        }

        return $lignes
            ->filter(fn (array $l) => $service === '' || $l['service_cle'] === $service)
            ->sortBy(fn (array $l) => $l['date']->timestamp)
            ->values();
    }

    /**
     * Indicateurs d'hébergement de la période : capacité, nuitées occupées et
     * payantes, revenu des chambres, gratuités.
     *
     * @return array{capacite: int, nuitees: int, nuitees_payantes: int, revenu_chambres: int, gratuites: int, valeur_gratuites: int}
     */
    public function hebergement(CarbonImmutable $du, CarbonImmutable $au): array
    {
        $jours = (int) $du->diffInDays($au) + 1;
        $capacite = Room::query()->where('is_active', true)->count() * $jours;
        $indicateurs = ['capacite' => $capacite, 'nuitees' => 0, 'nuitees_payantes' => 0, 'revenu_chambres' => 0, 'gratuites' => 0, 'valeur_gratuites' => 0];

        $sejours = Booking::query()
            ->whereIn('status', self::SEJOURS_EFFECTUES)
            ->whereDate('check_in', '<=', $au->toDateString())
            ->whereDate('check_out', '>', $du->toDateString())
            ->get();

        foreach ($sejours as $s) {
            $nuits = (int) CarbonImmutable::parse($s->check_in)->max($du)
                ->diffInDays(CarbonImmutable::parse($s->check_out)->subDay()->min($au)) + 1;

            if ($nuits <= 0) {
                continue;
            }

            $indicateurs['nuitees'] += $nuits;

            if ($s->is_complimentary || (int) $s->price_per_night === 0) {
                $indicateurs['gratuites'] += $nuits;
                $indicateurs['valeur_gratuites'] += (int) round(((int) $s->complimentary_value) * $nuits / max(1, (int) $s->total_nights));
            } else {
                $indicateurs['nuitees_payantes'] += $nuits;
                $indicateurs['revenu_chambres'] += $nuits * (int) $s->price_per_night;
            }
        }

        return $indicateurs;
    }

    /** Famille de mode : espèces, mobile money, carte, ou autre. */
    public static function familleDeMode(string $mode): string
    {
        return match ($mode) {
            'cash' => 'especes',
            'mobile_money', 'orange_money', 'mtn_momo' => 'mobile',
            'card', 'stripe' => 'carte',
            default => 'autres',
        };
    }

        /**
     * Totaux par service d'une collection de lignes, dans l'ordre des services.
     *
     * @return array<string, int>
     */
    public static function parService(Collection $lignes): array
    {
        return $lignes->groupBy('service')->map(fn ($l) => (int) $l->sum('montant'))->all();
    }

    private function reglementCommande(?string $statut, ?string $mode): string
    {
        return match (true) {
            $mode === 'room_charge' || $statut === 'transferred_to_folio' => 'Sur la chambre',
            $statut === 'paid' => self::mode($mode),
            default => 'À régler',
        };
    }

    /**
     * Lignes de folio créées par un autre service (repas, boutique, comptoir,
     * buffet) : leur vente est déjà comptée chez lui.
     *
     * @return list<int>
     */
    private function lignesDeFolioNeesAilleurs(): array
    {
        $ids = collect();

        foreach (['restaurant_customer_orders', 'shop_orders', 'reception_sale_items', 'restaurant_buffet_entries'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'folio_item_id')) {
                $ids = $ids->merge(DB::table($table)->whereNotNull('folio_item_id')->pluck('folio_item_id'));
            }
        }

        return $ids->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** @return array<int, string> */
    private function clients(Collection $ids): array
    {
        return Customer::query()->whereIn('id', $ids->filter()->unique())->get()
            ->mapWithKeys(fn (Customer $c) => [$c->id => $c->full_name])->all();
    }

    private function utilisateur(?int $id): string
    {
        $this->utilisateurs ??= User::query()->pluck('name', 'id')->all();

        return $id ? ($this->utilisateurs[$id] ?? '—') : '—';
    }

    private function pointDeVente(?int $id): ?PointOfSale
    {
        $this->pointsDeVente ??= PointOfSale::query()->get()->keyBy('id')->all();

        return $id ? ($this->pointsDeVente[$id] ?? null) : null;
    }

    private function ligne(mixed $date, string $serviceCle, string $service, string $piece, string $client, ?string $mode, ?int $par, int $montant): array
    {
        return [
            'date' => CarbonImmutable::parse($date),
            'service' => $service,
            'service_cle' => $serviceCle,
            'piece' => $piece,
            'client' => $client,
            'mode' => self::mode($mode),
            'mode_cle' => $mode ?? '',
            'par' => $this->utilisateur($par),
            'montant' => $montant,
        ];
    }

    private function vente(CarbonImmutable $date, string $serviceCle, string $service, string $piece, string $client, string $designation, string $reglement, int $montant): array
    {
        return compact('date', 'service', 'piece', 'client', 'designation', 'reglement', 'montant') + ['service_cle' => $serviceCle];
    }
}
