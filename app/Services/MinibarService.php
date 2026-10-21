<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\FolioItem;
use App\Models\HousekeepingLog;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MinibarArticle;
use App\Models\MinibarCheck;
use App\Models\MinibarMovement;
use App\Models\MinibarRestock;
use App\Models\MinibarStandard;
use App\Models\Room;
use App\Models\RoomMinibarLine;
use App\Models\RoomType;
use App\Models\ServiceStore;
use App\Models\ServiceStoreMovement;
use App\Models\ServiceStoreStock;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\MinibarCheckRecorded;
use App\Notifications\MinibarCheckRequested;
use App\Notifications\MinibarDefectiveItemReported;
use App\Notifications\MinibarEmpty;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Mini-bars des chambres : dotation, relevé des consommations, facturation au
 * folio et réapprovisionnement depuis le dépôt du mini-bar.
 *
 * Le relevé est la seule porte de sortie du stock d'un mini-bar : ce que
 * l'équipe constate consommé sort du mini-bar et part au folio du client. Le
 * réapprovisionnement est la seule porte d'entrée : la gouvernante remet la
 * chambre à sa dotation avec les articles du dépôt.
 *
 * Pendant le séjour, le mini-bar ne se recharge que lorsque le client l'a vidé.
 */
class MinibarService
{
    public function __construct(
        private readonly ServiceStoreService $depots,
        private readonly Notifier $notifier,
    ) {
    }

    // ── Réglages ─────────────────────────────────────────────────────────────

    /** Dépôt d'où sortent les articles des mini-bars. */
    public static function depot(): ?ServiceStore
    {
        $id = Tenant::first()?->settings['minibar']['depot_id'] ?? null;

        return $id ? ServiceStore::query()->active()->find($id) : null;
    }

    /**
     * Ce que montrent les réglages des mini-bars (Paramètres › Housekeeping).
     *
     * @return array{articles: Collection, depot: ?ServiceStore, depots: Collection, types: Collection, standards: Collection, articlesLibres: Collection}
     */
    public function reglages(): array
    {
        $articles = MinibarArticle::query()->with('item')->ordered()->get();

        return [
            'articles'       => $articles,
            'depot'          => self::depot(),
            'depots'         => ServiceStore::query()->active()->orderBy('name')->get(),
            'types'          => RoomType::query()->orderBy('name')->get(),
            'standards'      => MinibarStandard::query()->get()->groupBy('room_type_id')
                ->map(fn ($lignes) => $lignes->pluck('quantity', 'minibar_article_id')),
            'articlesLibres' => \App\Models\StockItem::query()->where('is_active', true)
                ->whereNotIn('id', $articles->pluck('stock_item_id'))
                ->orderBy('name')->get(['id', 'name', 'unit']),
        ];
    }

    public function choisirDepot(?ServiceStore $store): void
    {
        $tenant = Tenant::first();
        if ($tenant === null) {
            throw new \RuntimeException("L'établissement n'est pas encore configuré.");
        }

        $reglages = $tenant->settings ?? [];
        $reglages['minibar'] = array_merge($reglages['minibar'] ?? [], ['depot_id' => $store?->id]);
        $tenant->settings = $reglages;
        $tenant->save();
    }

    /**
     * Dotation standard d'un type de chambre, reportée sur chacune de ses
     * chambres. Retourne le nombre de chambres mises à jour.
     *
     * @param  array<int|string, int|string|null>  $quantites  article => quantité
     */
    public function definirDotationDuType(RoomType $type, array $quantites): int
    {
        $quantites = $this->quantitesValides($quantites);

        return DB::transaction(function () use ($type, $quantites) {
            foreach ($quantites as $articleId => $quantite) {
                if ($quantite > 0) {
                    MinibarStandard::query()->updateOrCreate(
                        ['room_type_id' => $type->id, 'minibar_article_id' => $articleId],
                        ['quantity' => $quantite]
                    );
                } else {
                    MinibarStandard::query()->where('room_type_id', $type->id)->where('minibar_article_id', $articleId)->delete();
                }
            }

            $chambres = Room::query()->where('room_type_id', $type->id)->get();
            foreach ($chambres as $chambre) {
                $this->appliquerDotation($chambre, $quantites);
            }

            return $chambres->count();
        });
    }

    /**
     * Dotation propre à une chambre, qui s'écarte de celle de son type.
     *
     * @param  array<int|string, int|string|null>  $quantites  article => quantité
     */
    public function definirDotationDeLaChambre(Room $room, array $quantites): void
    {
        $quantites = $this->quantitesValides($quantites);

        DB::transaction(fn () => $this->appliquerDotation($room, $quantites));
    }

    /** @param  array<int, int>  $quantites */
    private function appliquerDotation(Room $room, array $quantites): void
    {
        foreach ($quantites as $articleId => $quantite) {
            $ligne = RoomMinibarLine::query()
                ->where('room_id', $room->id)
                ->where('minibar_article_id', $articleId)
                ->lockForUpdate()
                ->first();

            if ($ligne === null) {
                if ($quantite > 0) {
                    RoomMinibarLine::create([
                        'room_id' => $room->id, 'minibar_article_id' => $articleId,
                        'standard_quantity' => $quantite, 'quantity' => 0, 'unit_cost' => 0,
                    ]);
                }

                continue;
            }

            // Un article retiré de la dotation mais encore présent reste
            // suivi : il sera relevé, puis la ligne s'éteindra d'elle-même.
            if ($quantite === 0 && $ligne->quantity === 0) {
                $ligne->delete();
            } else {
                $ligne->update(['standard_quantity' => $quantite]);
            }
        }
    }

    /**
     * @param  array<int|string, int|string|null>  $quantites
     * @return array<int, int>
     */
    private function quantitesValides(array $quantites): array
    {
        $articles = MinibarArticle::query()->pluck('id')->all();
        $propres = [];

        foreach ($quantites as $articleId => $quantite) {
            if (!in_array((int) $articleId, $articles, true)) {
                continue;
            }
            $valeur = $quantite === null || $quantite === '' ? 0 : $quantite;
            if (!is_numeric($valeur) || (int) $valeur != $valeur || (int) $valeur < 0 || (int) $valeur > 99) {
                throw new \InvalidArgumentException('Chaque quantité de la dotation est un nombre entier de 0 à 99.');
            }
            $propres[(int) $articleId] = (int) $valeur;
        }

        return $propres;
    }

    // ── Relevé ───────────────────────────────────────────────────────────────

    /** Séjour en cours dans la chambre. */
    public static function sejourEnCours(Room $room): ?Booking
    {
        return $room->bookings()
            ->where('status', BookingStatus::CHECKED_IN)
            ->latest('actual_check_in')
            ->first();
    }

    /** Dernier séjour récent (terminé) dans la chambre. */
    public static function dernierSejourRecent(Room $room): ?Booking
    {
        return $room->bookings()
            ->whereIn('status', [BookingStatus::CHECKED_OUT, BookingStatus::COMPLETED])
            ->whereNotNull('actual_check_out')
            ->latest('actual_check_out')
            ->first();
    }

    /** Lignes que le relevé doit couvrir : celles qui ont une dotation ou du contenu. */
    public static function lignesARelever(Room $room): Collection
    {
        return $room->minibarLines()
            ->with('article')
            ->where(fn ($q) => $q->where('standard_quantity', '>', 0)->orWhere('quantity', '>', 0))
            ->get()
            ->sortBy(fn (RoomMinibarLine $l) => [$l->article->sort_order, $l->article->name])
            ->values();
    }

    /**
     * Relevé du mini-bar : la consommation de chaque article, 0 compris.
     *
     * Le client est en séjour : la consommation part à son folio. Il est parti
     * sans relevé de départ : elle reste à lui facturer, et la réception en est
     * avertie.
     *
     * @param  array<int|string, int|string|null>  $consommes  article => quantité consommée
     */
    public function relever(Room $room, array $consommes, ?string $notes = null): MinibarCheck
    {
        $releve = DB::transaction(function () use ($room, $consommes, $notes) {
            $room = Room::query()->lockForUpdate()->findOrFail($room->id);

            $lignes = RoomMinibarLine::query()
                ->with('article.item')
                ->where('room_id', $room->id)
                ->where(fn ($q) => $q->where('standard_quantity', '>', 0)->orWhere('quantity', '>', 0))
                ->lockForUpdate()
                ->get()
                ->sortBy(fn (RoomMinibarLine $l) => [$l->article->sort_order, $l->article->name])
                ->values();

            if ($lignes->isEmpty()) {
                throw new \InvalidArgumentException("La chambre {$room->number} n'a pas de mini-bar.");
            }

            $quantites = [];
            foreach ($lignes as $ligne) {
                $saisie = $consommes[$ligne->minibar_article_id] ?? null;
                if ($saisie === null || $saisie === '') {
                    throw new \InvalidArgumentException(
                        "Indiquez la consommation de chaque article, 0 si rien n'a été pris : « {$ligne->article->name} » manque."
                    );
                }
                if (!is_numeric($saisie) || (int) $saisie != $saisie || (int) $saisie < 0) {
                    throw new \InvalidArgumentException("« {$ligne->article->name} » : la consommation est un nombre entier.");
                }
                if ((int) $saisie > $ligne->quantity) {
                    throw new \InvalidArgumentException(
                        "« {$ligne->article->name} » : {$saisie} consommé(s) pour {$ligne->quantity} dans le mini-bar."
                    );
                }
                $quantites[$ligne->id] = (int) $saisie;
            }

            // À qui rattacher la consommation.
            $sejour = self::sejourEnCours($room);
            if ($sejour !== null) {
                $booking = $sejour;
                $occasion = $sejour->minibar_check_requested_at !== null
                    ? MinibarCheck::OCCASION_DEPART
                    : MinibarCheck::OCCASION_SEJOUR;
            } elseif ($room->minibar_due_booking_id !== null) {
                $booking = Booking::query()->find($room->minibar_due_booking_id);
                $occasion = MinibarCheck::OCCASION_APRES_DEPART;
            } else {
                $dernierSejour = self::dernierSejourRecent($room);
                if ($dernierSejour !== null && $dernierSejour->actual_check_out && $dernierSejour->actual_check_out->diffInHours(now()) <= 48) {
                    $booking = $dernierSejour;
                    $occasion = MinibarCheck::OCCASION_APRES_DEPART;
                } else {
                    throw new \RuntimeException(
                        "Aucun client à qui rattacher un relevé dans la chambre {$room->number} : elle n'est pas occupée et aucun relevé n'y est dû."
                    );
                }
            }

            $total = 0;
            foreach ($lignes as $ligne) {
                $total += $quantites[$ligne->id] * (int) $ligne->article->price;
            }

            $billing = match (true) {
                $total === 0                                    => MinibarCheck::BILLING_AUCUNE,
                $occasion === MinibarCheck::OCCASION_APRES_DEPART => MinibarCheck::BILLING_A_FACTURER,
                default                                         => MinibarCheck::BILLING_FOLIO,
            };

            $assignment = $room->activeHousekeepingAssignment;

            $releve = MinibarCheck::create([
                'room_id'                    => $room->id,
                'booking_id'                 => $booking?->id,
                'housekeeping_assignment_id' => $assignment?->id,
                'occasion'                   => $occasion,
                'billing'                    => $billing,
                'total_amount'               => $total,
                'user_id'                    => Auth::id(),
                'checked_at'                 => now(),
                'notes'                      => $notes !== null && trim($notes) !== '' ? mb_substr(trim($notes), 0, 500) : null,
            ]);

            foreach ($lignes as $ligne) {
                $consomme = $quantites[$ligne->id];
                $article = $ligne->article;
                $folioId = null;

                if ($consomme > 0) {
                    $ligne->update(['quantity' => $ligne->quantity - $consomme]);

                    MinibarMovement::create([
                        'room_id'            => $room->id,
                        'minibar_article_id' => $article->id,
                        'stock_item_id'      => $article->stock_item_id,
                        'type'               => MinibarMovement::TYPE_CONSUMPTION,
                        'quantity'           => -$consomme,
                        'quantity_after'     => $ligne->quantity,
                        'unit_cost'          => $ligne->unit_cost,
                        'stock_account'      => $article->item?->stockAccount(),
                        'source_type'        => MinibarMovement::SOURCE_CHECK,
                        'source_id'          => $releve->id,
                        'booking_id'         => $booking?->id,
                        'user_id'            => Auth::id(),
                        'occurred_at'        => now(),
                    ]);

                    if ($billing === MinibarCheck::BILLING_FOLIO) {
                        $folioId = FolioItem::create([
                            'booking_id'       => $booking->id,
                            'customer_id'      => $booking->customer_id,
                            'type'             => FolioItem::TYPE_MINIBAR,
                            'description'      => "Mini-bar — {$article->name}",
                            'quantity'         => $consomme,
                            'unit_price'       => (int) $article->price,
                            'total_price'      => $consomme * (int) $article->price,
                            'is_complimentary' => false,
                            'earns_points'     => true,
                            'recorded_by'      => Auth::id(),
                            'occurred_at'      => now(),
                            'notes'            => "Relevé du mini-bar n° {$releve->id}",
                        ])->id;
                    }
                }

                $releve->lines()->create([
                    'minibar_article_id' => $article->id,
                    'label'              => $article->name,
                    'quantity_before'    => $ligne->quantity + $consomme,
                    'consumed'           => $consomme,
                    'unit_price'         => (int) $article->price,
                    'total_price'        => $consomme * (int) $article->price,
                    'unit_cost'          => $ligne->unit_cost,
                    'folio_item_id'      => $folioId,
                ]);
            }

            if ($billing === MinibarCheck::BILLING_FOLIO) {
                app(CheckOutService::class)->recalculateTotals($booking->fresh());
            }

            // La note du séjour est close : la consommation part sur une
            // facture complémentaire, à encaisser plus tard.
            if ($billing === MinibarCheck::BILLING_A_FACTURER) {
                $this->facturerApresDepart($releve->load('lines'), $booking);
            }

            // Le relevé dû après le départ est fait : la chambre peut être
            // marquée nettoyée.
            if ($occasion === MinibarCheck::OCCASION_APRES_DEPART) {
                $room->update(['minibar_due_booking_id' => null]);
            }

            $releve->load('lines');
            HousekeepingLog::noter($room, HousekeepingLog::MINIBAR_CHECKED,
                $releve->occasionLabel() . ' — ' . $releve->resume()
                    . ($total > 0 ? ' — ' . number_format($total / 100, 0, ',', ' ') . ' F' : ''),
                $assignment, $booking?->id);

            return $releve;
        });

        $room->refresh()->load('minibarLines');

        // La réception et la gouvernante suivent les consommations relevées.
        $rolesANotifier = ['reception'];
        if ($releve->total_amount > 0 || $releve->lines->contains(fn ($l) => $l->consumed > 0)) {
            $rolesANotifier = ['reception', 'housekeeping_supervisor', 'housekeeping_leader'];
        }
        $this->notifier->toRoles($rolesANotifier, new MinibarCheckRecorded($releve), Auth::id());

        // Pendant le séjour, le mini-bar ne se recharge que vide : la
        // gouvernante est appelée quand le client l'a vidé.
        if ($releve->occasion === MinibarCheck::OCCASION_SEJOUR && $room->miniBarVide()) {
            $this->notifier->toRoles(['housekeeping_supervisor'], new MinibarEmpty($room), Auth::id());
        }

        return $releve;
    }

    /** Facture complémentaire des consommations relevées après le départ. */
    private function facturerApresDepart(MinibarCheck $releve, Booking $booking): Invoice
    {
        $decomposition = app(TaxationService::class)->breakdown($releve->total_amount);

        $facture = Invoice::create([
            'kind'           => Invoice::KIND_COMPLEMENTAIRE,
            'booking_id'     => $booking->id,
            'customer_id'    => $booking->customer_id,
            'invoice_number' => Invoice::prochainNumero(),
            'invoice_date'   => now(),
            'subtotal'       => $decomposition->ht,
            'tax_amount'     => $decomposition->vat,
            'total_amount'   => $releve->total_amount,
            'paid_amount'    => 0,
            'balance_due'    => $releve->total_amount,
            'status'         => 'sent',
            'legal_notes'    => 'République du Cameroun',
            'internal_notes' => "Consommations du mini-bar relevées après le départ (relevé n° {$releve->id}, séjour {$booking->booking_number}).",
        ]);

        foreach ($releve->lines->where('consumed', '>', 0) as $ligne) {
            InvoiceItem::create([
                'invoice_id'  => $facture->id,
                'description' => "Mini-bar — {$ligne->label}",
                'quantity'    => $ligne->consumed,
                'unit_price'  => $ligne->unit_price,
                'total_price' => $ligne->total_price,
                'tax_rate'    => 0.0,
                'tax_amount'  => 0,
                'category'    => FolioItem::TYPE_MINIBAR,
                'source_type' => \App\Models\MinibarCheckLine::class,
                'source_id'   => $ligne->id,
            ]);
        }

        $releve->update(['invoice_id' => $facture->id]);

        return $facture;
    }

    /** Un relevé est-il dû avant de marquer la chambre nettoyée ? */
    public static function releveDuAuMenage(Room $room): bool
    {
        return $room->minibar_due_booking_id !== null && $room->aUnMiniBar();
    }

    // ── Départ du client ─────────────────────────────────────────────────────

    /**
     * La réception demande le contrôle du mini-bar avant de clore la note.
     */
    public function demanderControle(Booking $booking): void
    {
        $booking->loadMissing('room');

        if ($booking->status !== BookingStatus::CHECKED_IN) {
            throw new \RuntimeException('Le contrôle du mini-bar se demande pendant le séjour, avant le départ.');
        }
        if ($booking->room === null || !$booking->room->aUnMiniBar()) {
            throw new \RuntimeException("La chambre {$booking->room?->number} n'a pas de mini-bar.");
        }
        if ($booking->minibar_check_requested_at !== null) {
            throw new \RuntimeException('Le contrôle du mini-bar est déjà demandé.');
        }

        DB::transaction(function () use ($booking) {
            $booking->update([
                'minibar_check_requested_at' => now(),
                'minibar_check_requested_by' => Auth::id(),
            ]);
            HousekeepingLog::noter($booking->room, HousekeepingLog::MINIBAR_REQUESTED,
                'Départ du client — demandé par la réception', $booking->room->latestHousekeepingAssignment, $booking->id);
        });

        // L'équipe de la chambre, les gouvernantes et le responsable.
        $equipe = $booking->room->latestHousekeepingAssignment?->team?->members ?? collect();
        $this->notifier->send($equipe, new MinibarCheckRequested($booking->room));
        $this->notifier->toRoles(['housekeeping_supervisor'], new MinibarCheckRequested($booking->room), Auth::id());
    }

    /** Relevé de départ fait pour ce séjour ? */
    public static function releveDeDepart(Booking $booking): ?MinibarCheck
    {
        return MinibarCheck::query()
            ->where('booking_id', $booking->id)
            ->where('occasion', MinibarCheck::OCCASION_DEPART)
            ->latest('checked_at')
            ->first();
    }

    /**
     * Au check-out : sans relevé de départ, le relevé reste dû au ménage qui
     * suit. La chambre ne pourra pas être marquée nettoyée avant.
     */
    public function auDepart(Booking $booking): void
    {
        $room = $booking->room;
        if ($room === null || !$room->aUnMiniBar() || self::releveDeDepart($booking) !== null) {
            return;
        }

        $room->update(['minibar_due_booking_id' => $booking->id]);
    }

    // ── Réapprovisionnement ──────────────────────────────────────────────────

    /**
     * Remet le mini-bar à sa dotation avec les articles du dépôt. Pendant un
     * séjour, seulement si le client l'a vidé. Ce que le dépôt n'a plus reste
     * manquant et se signale.
     *
     * @return array{0: MinibarRestock, 1: list<string>} le réapprovisionnement et les articles manquants
     */
    public function recharger(Room $room): array
    {
        $depot = self::depot();
        if ($depot === null) {
            throw new \RuntimeException("Le dépôt du mini-bar n'est pas choisi : le responsable des étages le règle dans Mini-bars.");
        }

        return DB::transaction(function () use ($room, $depot) {
            $room = Room::query()->lockForUpdate()->findOrFail($room->id);
            $lignes = RoomMinibarLine::query()->with('article.item')->where('room_id', $room->id)->lockForUpdate()->get();
            $room->setRelation('minibarLines', $lignes);

            $sejour = self::sejourEnCours($room);
            if ($sejour !== null && !$room->miniBarVide()) {
                throw new \RuntimeException(
                    "Séjour en cours : le mini-bar de la chambre {$room->number} ne se recharge que lorsque le client l'a vidé."
                );
            }

            $aCompleter = $lignes->filter(fn (RoomMinibarLine $l) => $l->manquant() > 0);
            if ($aCompleter->isEmpty()) {
                throw new \InvalidArgumentException("Le mini-bar de la chambre {$room->number} est déjà complet.");
            }

            $raison = match (true) {
                $sejour !== null => MinibarRestock::REASON_VIDE,
                in_array($room->status->value, ['dirty', 'cleaning', 'clean'], true) => MinibarRestock::REASON_DEPART,
                default => MinibarRestock::REASON_MISE_EN_PLACE,
            };

            $recharge = MinibarRestock::create([
                'room_id'          => $room->id,
                'service_store_id' => $depot->id,
                'booking_id'       => $sejour?->id,
                'reason'           => $raison,
                'total_cost'       => 0,
                'user_id'          => Auth::id(),
                'restocked_at'     => now(),
            ]);

            $mis = [];
            $manquants = [];
            $total = 0;

            foreach ($aCompleter as $ligne) {
                $article = $ligne->article;
                $besoin = $ligne->manquant();
                $disponible = (int) floor((float) (ServiceStoreStock::query()
                    ->where('service_store_id', $depot->id)
                    ->where('stock_item_id', $article->stock_item_id)
                    ->value('current_stock') ?? 0) + 0.0001);
                $pris = min($besoin, $disponible);

                if ($pris < $besoin) {
                    $manquants[] = ($besoin - $pris) . ' × ' . $article->name;
                }
                if ($pris <= 0) {
                    continue;
                }

                $sortie = $this->depots->issue($depot, $article->item, $pris, ServiceStoreMovement::SOURCE_MINIBAR,
                    $recharge->id, "Mini-bar de la chambre {$room->number}");

                $avant = $ligne->quantity;
                $apres = $avant + $pris;
                $ligne->update([
                    'quantity'  => $apres,
                    'unit_cost' => (int) round(($avant * $ligne->unit_cost + $pris * $sortie->unit_cost) / $apres),
                ]);

                MinibarMovement::create([
                    'room_id'            => $room->id,
                    'minibar_article_id' => $article->id,
                    'stock_item_id'      => $article->stock_item_id,
                    'type'               => MinibarMovement::TYPE_RESTOCK,
                    'quantity'           => $pris,
                    'quantity_after'     => $apres,
                    'unit_cost'          => (int) $sortie->unit_cost,
                    'stock_account'      => $article->item?->stockAccount(),
                    'source_type'        => MinibarMovement::SOURCE_RESTOCK,
                    'source_id'          => $recharge->id,
                    'booking_id'         => $sejour?->id,
                    'user_id'            => Auth::id(),
                    'occurred_at'        => now(),
                ]);

                $total += $pris * (int) $sortie->unit_cost;
                $mis[] = "{$pris} × {$article->name}";
            }

            if ($mis === []) {
                throw new \RuntimeException("Le dépôt {$depot->name} n'a aucun des articles qui manquent : " . implode(', ', $manquants) . '.');
            }

            $recharge->update(['total_cost' => $total]);

            HousekeepingLog::noter($room, HousekeepingLog::MINIBAR_RESTOCKED,
                $recharge->reasonLabel() . ' — ' . implode(', ', $mis)
                    . ($manquants !== [] ? ' — manque au dépôt : ' . implode(', ', $manquants) : ''),
                $room->latestHousekeepingAssignment, $sejour?->id);

            return [$recharge, $manquants];
        });
    }

    /**
     * Signale un produit défectueux, périmé ou avarié dans le mini-bar.
     * Le produit est déduit du stock du mini-bar SANS AUCUNE facturation au client.
     * La gouvernante et le chef d'équipe housekeeping sont immédiatement notifiés pour remplacement.
     */
    public function declarerDefectueux(
        Room $room,
        int $minibarArticleId,
        int $quantite,
        string $motif,
        ?string $remarques = null,
        ?User $par = null,
    ): MinibarMovement {
        if ($quantite <= 0) {
            throw new \InvalidArgumentException('La quantité défectueuse doit être supérieure à zéro.');
        }

        $userId = $par?->id ?? Auth::id();
        $user = $par ?? Auth::user();

        $mouvement = DB::transaction(function () use ($room, $minibarArticleId, $quantite, $motif, $remarques, $userId) {
            $room = Room::query()->lockForUpdate()->findOrFail($room->id);

            $ligne = RoomMinibarLine::query()
                ->with('article.item')
                ->where('room_id', $room->id)
                ->where('minibar_article_id', $minibarArticleId)
                ->lockForUpdate()
                ->first();

            if ($ligne === null) {
                throw new \InvalidArgumentException("Cet article ne fait pas partie du mini-bar de la chambre {$room->number}.");
            }

            if ($ligne->quantity < $quantite) {
                throw new \InvalidArgumentException(
                    "Impossible de déclarer {$quantite} unité(s) défectueuse(s) : il n'en reste que {$ligne->quantity} dans le mini-bar de la chambre {$room->number}."
                );
            }

            $article = $ligne->article;
            $nouvelleQuantite = $ligne->quantity - $quantite;
            $ligne->update(['quantity' => $nouvelleQuantite]);

            $sejour = self::sejourEnCours($room);
            $bookingId = $sejour?->id ?? $room->minibar_due_booking_id;
            $notesFinales = trim($motif . ($remarques ? ' — ' . $remarques : ''));

            $mouvement = MinibarMovement::create([
                'room_id'            => $room->id,
                'minibar_article_id' => $article->id,
                'stock_item_id'      => $article->stock_item_id,
                'type'               => MinibarMovement::TYPE_DEFECTIVE,
                'quantity'           => -$quantite,
                'quantity_after'     => $nouvelleQuantite,
                'unit_cost'          => $ligne->unit_cost,
                'stock_account'      => $article->item?->stockAccount(),
                'source_type'        => MinibarMovement::SOURCE_DEFECTIVE,
                'source_id'          => null,
                'booking_id'         => $bookingId,
                'user_id'            => $userId,
                'notes'              => $notesFinales,
                'occurred_at'        => now(),
            ]);

            $assignment = $room->activeHousekeepingAssignment ?? $room->latestHousekeepingAssignment;
            HousekeepingLog::noter(
                $room,
                HousekeepingLog::MINIBAR_DEFECTIVE,
                "Produit défectueux / avarié : {$quantite} × {$article->name} ({$notesFinales}) — retiré sans facturation",
                $assignment,
                $bookingId
            );

            return $mouvement;
        });

        // Alerter immédiatement la gouvernante générale et les chefs d'équipe
        $this->notifier->toRoles(
            ['housekeeping_supervisor', 'housekeeping_leader'],
            new MinibarDefectiveItemReported($room, $mouvement->article, $quantite, $motif, $remarques, $user),
            $userId
        );

        return $mouvement;
    }
}
