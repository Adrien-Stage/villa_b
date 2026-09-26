<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\GroupBooking;
use App\Models\Invoice;
use App\Models\ReceptionSale;
use App\Models\RestaurantCustomerOrder;
use App\Models\ShopOrder;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Service d'agrégation multi-services des factures et pièces comptables d'un client.
 * 
 * Regroupe et normalise l'ensemble des dépenses et factures de tous les départements :
 * - Hébergement : Factures officielles et notes de séjour
 * - Restaurant & Bar : Additions et commandes restaurant
 * - Boutique : Tickets de caisse et commandes boutique
 * - POS Réception : Ventes directes et prestations de réception
 * - Groupes : Factures et dossiers de réservation de groupe
 */
class CustomerInvoiceService
{
    /**
     * Récupère l'historique financier complet et filtré d'un client.
     *
     * @param Customer $customer
     * @param array $filters
     * @return array
     */
    public function getBillingHistory(Customer $customer, array $filters = []): array
    {
        // 1. Résolution des dates de la période
        [$startDate, $endDate, $period] = $this->resolveDateRange($filters);

        $serviceFilter = $filters['service'] ?? 'all';
        $statusFilter  = $filters['status'] ?? 'all';
        $searchQuery   = trim((string) ($filters['search'] ?? ''));

        // 2. Collecte des documents par domaine
        $documents = collect();

        // A. Hébergement (Invoices & Bookings)
        if ($serviceFilter === 'all' || $serviceFilter === 'accommodation') {
            $documents = $documents->merge($this->fetchAccommodationDocuments($customer, $startDate, $endDate));
        }

        // B. Restaurant
        if ($serviceFilter === 'all' || $serviceFilter === 'restaurant') {
            $documents = $documents->merge($this->fetchRestaurantOrders($customer, $startDate, $endDate));
        }

        // C. Boutique
        if ($serviceFilter === 'all' || $serviceFilter === 'shop') {
            $documents = $documents->merge($this->fetchShopOrders($customer, $startDate, $endDate));
        }

        // D. POS Réception
        if ($serviceFilter === 'all' || $serviceFilter === 'reception') {
            $documents = $documents->merge($this->fetchReceptionSales($customer, $startDate, $endDate));
        }

        // E. Réservations de groupe
        if ($serviceFilter === 'all' || $serviceFilter === 'group') {
            $documents = $documents->merge($this->fetchGroupInvoices($customer, $startDate, $endDate));
        }

        // 3. Filtrage par statut de paiement
        if ($statusFilter !== 'all') {
            $documents = $documents->filter(function ($doc) use ($statusFilter) {
                return match ($statusFilter) {
                    'paid'    => $doc->balance_due <= 0 || in_array($doc->status, ['paid', 'completed']),
                    'unpaid'  => $doc->balance_due > 0 && !in_array($doc->status, ['paid', 'completed']),
                    'partial' => $doc->balance_due > 0 && $doc->paid_amount > 0,
                    default   => true,
                };
            });
        }

        // 4. Filtrage par recherche textuelle (référence, description, etc.)
        if ($searchQuery !== '') {
            $lowerSearch = mb_strtolower($searchQuery);
            $documents = $documents->filter(function ($doc) use ($lowerSearch) {
                return str_contains(mb_strtolower($doc->reference), $lowerSearch)
                    || str_contains(mb_strtolower($doc->description), $lowerSearch)
                    || str_contains(mb_strtolower($doc->service_label), $lowerSearch)
                    || str_contains(mb_strtolower($doc->document_type), $lowerSearch)
                    || str_contains(mb_strtolower($doc->payment_method ?? ''), $lowerSearch);
            });
        }

        // 5. Tri par date décroissante
        $documents = $documents->sortByDesc(function ($doc) {
            return $doc->date ? $doc->date->timestamp : 0;
        })->values();

        // 6. Calcul des totaux financiers stricts (en centimes FCFA)
        $totalInvoiced   = (int) $documents->sum('total_amount');
        $totalPaid       = (int) $documents->sum('paid_amount');
        $totalBalanceDue = (int) $documents->sum('balance_due');
        $totalCount      = $documents->count();

        // Ventilation par service pour badges d'information
        $countsByService = [
            'all'           => $totalCount,
            'accommodation' => $documents->where('service', 'accommodation')->count(),
            'restaurant'    => $documents->where('service', 'restaurant')->count(),
            'shop'          => $documents->where('service', 'shop')->count(),
            'reception'     => $documents->where('service', 'reception')->count(),
            'group'         => $documents->where('service', 'group')->count(),
        ];

        // 7. Pagination
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = 15;
        $paginatedItems = $documents->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $paginatedItems,
            $totalCount,
            $perPage,
            $page,
            [
                'path'  => request()->url(),
                'query' => request()->query(),
            ]
        );

        return [
            'documents' => $paginator,
            'totals'    => [
                'total_invoiced'    => $totalInvoiced,
                'total_paid'        => $totalPaid,
                'total_balance_due' => $totalBalanceDue,
                'count'             => $totalCount,
                'counts_by_service' => $countsByService,
            ],
            'filters'   => [
                'start_date' => $startDate?->format('Y-m-d'),
                'end_date'   => $endDate?->format('Y-m-d'),
                'period'     => $period,
                'service'    => $serviceFilter,
                'status'     => $statusFilter,
                'search'     => $searchQuery,
            ],
        ];
    }

    /**
     * Résout la plage de dates à partir des filtres passés (preset ou dates manuelles).
     */
    private function resolveDateRange(array $filters): array
    {
        $period = $filters['period'] ?? null;
        $startDate = null;
        $endDate = null;

        if ($period) {
            switch ($period) {
                case 'today':
                    $startDate = Carbon::today()->startOfDay();
                    $endDate   = Carbon::today()->endOfDay();
                    break;
                case 'yesterday':
                    $startDate = Carbon::yesterday()->startOfDay();
                    $endDate   = Carbon::yesterday()->endOfDay();
                    break;
                case 'this_week':
                    $startDate = Carbon::now()->startOfWeek();
                    $endDate   = Carbon::now()->endOfWeek();
                    break;
                case 'this_month':
                    $startDate = Carbon::now()->startOfMonth();
                    $endDate   = Carbon::now()->endOfMonth();
                    break;
                case 'last_month':
                    $startDate = Carbon::now()->subMonth()->startOfMonth();
                    $endDate   = Carbon::now()->subMonth()->endOfMonth();
                    break;
                case 'this_year':
                    $startDate = Carbon::now()->startOfYear();
                    $endDate   = Carbon::now()->endOfYear();
                    break;
                case 'all':
                    $startDate = null;
                    $endDate   = null;
                    break;
            }
        }

        if (!empty($filters['start_date'])) {
            $parsed = $this->safeParseDate($filters['start_date']);
            if ($parsed) {
                $startDate = $parsed->startOfDay();
                if ($period !== 'custom') {
                    $period = 'custom';
                }
            }
        }

        if (!empty($filters['end_date'])) {
            $parsed = $this->safeParseDate($filters['end_date']);
            if ($parsed) {
                $endDate = $parsed->endOfDay();
                if ($period !== 'custom') {
                    $period = 'custom';
                }
            }
        }

        return [$startDate, $endDate, $period];
    }

    /**
     * Parse une date en tolérant les incohérences (ex: 31 septembre).
     */
    private function safeParseDate(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Récupère les pièces d'hébergement : Factures officielles et notes de séjours.
     */
    private function fetchAccommodationDocuments(Customer $customer, ?Carbon $startDate, ?Carbon $endDate): Collection
    {
        $docs = collect();

        // 1. Factures officielles d'hébergement
        $invoicesQuery = Invoice::query()
            ->where(function ($q) use ($customer) {
                $q->where('customer_id', $customer->id)
                  ->orWhereHas('booking', fn($b) => $b->where('customer_id', $customer->id));
            })
            ->with(['booking.room.roomType']);

        if ($startDate) {
            $invoicesQuery->whereDate('invoice_date', '>=', $startDate->toDateString());
        }
        if ($endDate) {
            $invoicesQuery->whereDate('invoice_date', '<=', $endDate->toDateString());
        }

        $invoices = $invoicesQuery->get();
        $invoicedBookingIds = $invoices->pluck('booking_id')->filter()->unique()->toArray();

        foreach ($invoices as $inv) {
            $roomLabel = $inv->booking?->room
                ? "Chambre {$inv->booking->room->number} ({$inv->booking->room->roomType?->name})"
                : "Séjour hôtelier";
            $nightsText = $inv->booking?->total_nights
                ? " • {$inv->booking->total_nights} nuit(s)"
                : "";

            $docs->push((object) [
                'id'                  => 'inv_' . $inv->id,
                'source_type'         => 'invoice',
                'source_id'           => $inv->id,
                'service'             => 'accommodation',
                'service_label'       => 'Hébergement',
                'service_badge_class' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                'service_icon'        => 'bed',
                'document_type'       => 'Facture hébergement',
                'reference'           => $inv->invoice_number,
                'date'                => Carbon::parse($inv->invoice_date ?? $inv->created_at),
                'total_amount'        => (int) $inv->total_amount,
                'paid_amount'         => (int) $inv->paid_amount,
                'balance_due'         => (int) $inv->balance_due,
                'status'              => $inv->status,
                'status_label'        => match ($inv->status) {
                    'paid'      => 'Réglée',
                    'sent'      => 'Émise (En attente)',
                    'draft'     => 'Brouillon',
                    'cancelled' => 'Annulée',
                    default     => ucfirst($inv->status),
                },
                'status_badge_class'  => match ($inv->status) {
                    'paid'      => 'bg-green-50 text-green-700 border-green-200',
                    'sent'      => 'bg-amber-50 text-amber-700 border-amber-200',
                    'cancelled' => 'bg-red-50 text-red-700 border-red-200',
                    default     => 'bg-gray-50 text-gray-700 border-gray-200',
                },
                'description'         => $roomLabel . $nightsText,
                'url_show'            => route('invoices.show', $inv),
                'url_print'           => route('invoices.show', $inv),
                'payment_method'      => 'Compte séjour',
            ]);
        }

        // 2. Séjours n'ayant pas encore de facture finale officielle (séjours en cours, confirmés ou terminés)
        $bookingsQuery = Booking::query()
            ->where('customer_id', $customer->id)
            ->whereDoesntHave('invoice')
            ->whereNotIn('status', ['cancelled'])
            ->with(['room.roomType']);

        if ($startDate) {
            $bookingsQuery->where(function ($q) use ($startDate) {
                $q->whereDate('check_in', '>=', $startDate->toDateString())
                  ->orWhereDate('created_at', '>=', $startDate->toDateString());
            });
        }
        if ($endDate) {
            $bookingsQuery->where(function ($q) use ($endDate) {
                $q->whereDate('check_in', '<=', $endDate->toDateString())
                  ->orWhereDate('created_at', '<=', $endDate->toDateString());
            });
        }

        $bookings = $bookingsQuery->get();

        foreach ($bookings as $bkg) {
            $roomLabel = $bkg->room
                ? "Chambre {$bkg->room->number} ({$bkg->room->roomType?->name})"
                : "Séjour hôtelier";
            $nightsText = " • {$bkg->total_nights} nuit(s)";
            $isPaid = $bkg->balance_due <= 0;

            $docs->push((object) [
                'id'                  => 'bkg_' . $bkg->id,
                'source_type'         => 'booking',
                'source_id'           => $bkg->id,
                'service'             => 'accommodation',
                'service_label'       => 'Hébergement',
                'service_badge_class' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                'service_icon'        => 'bed',
                'document_type'       => 'Note de séjour',
                'reference'           => $bkg->booking_number,
                'date'                => Carbon::parse($bkg->created_at ?? $bkg->check_in),
                'total_amount'        => (int) $bkg->total_amount,
                'paid_amount'         => (int) $bkg->paid_amount,
                'balance_due'         => (int) $bkg->balance_due,
                'status'              => $isPaid ? 'paid' : ($bkg->paid_amount > 0 ? 'partial' : 'unpaid'),
                'status_label'        => $isPaid ? 'Réglée' : ($bkg->paid_amount > 0 ? 'Partiel' : 'Impayée'),
                'status_badge_class'  => $isPaid
                    ? 'bg-green-50 text-green-700 border-green-200'
                    : ($bkg->paid_amount > 0 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-red-50 text-red-700 border-red-200'),
                'description'         => $roomLabel . $nightsText . ' (' . $bkg->status->label() . ')',
                'url_show'            => route('bookings.show', $bkg),
                'url_print'           => route('bookings.summary', $bkg),
                'payment_method'      => 'Réservation',
            ]);
        }

        return $docs;
    }

    /**
     * Récupère les commandes et additions de restaurant liées au client.
     */
    private function fetchRestaurantOrders(Customer $customer, ?Carbon $startDate, ?Carbon $endDate): Collection
    {
        $docs = collect();

        $query = RestaurantCustomerOrder::query()
            ->where(function ($q) use ($customer) {
                $q->whereHas('booking', fn($b) => $b->where('customer_id', $customer->id));

                if (!empty($customer->phone)) {
                    $phoneClean = preg_replace('/[^\d]/', '', $customer->phone);
                    $q->orWhere('customer_phone', 'like', "%{$customer->phone}%");
                    if (strlen($phoneClean) >= 8) {
                        $q->orWhere('customer_phone', 'like', "%{$phoneClean}%");
                    }
                }

                if (!empty($customer->full_name)) {
                    $q->orWhereRaw('LOWER(customer_name) LIKE ?', ['%' . mb_strtolower($customer->full_name) . '%']);
                }
            })
            ->with(['items', 'booking.room']);

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate->toDateString());
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate->toDateString());
        }

        $orders = $query->get();

        foreach ($orders as $order) {
            $total = (int) $order->total_amount;
            $paid = (int) ($order->amount_paid ?? ($order->payment_status === 'paid' ? $total : 0));
            $balance = max(0, $total - $paid);
            $isPaid = $order->payment_status === 'paid' || $balance === 0;

            $tableDesc = $order->table_number ? "Table {$order->table_number}" : "Commande restaurant";
            $itemsCount = $order->items->count();
            if ($itemsCount > 0) {
                $tableDesc .= " • {$itemsCount} article(s)";
            }
            if ($order->booking?->room) {
                $tableDesc .= " (Ch. {$order->booking->room->number})";
            }

            $docs->push((object) [
                'id'                  => 'rest_' . $order->id,
                'source_type'         => 'restaurant_order',
                'source_id'           => $order->id,
                'service'             => 'restaurant',
                'service_label'       => 'Restaurant',
                'service_badge_class' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                'service_icon'        => 'utensils',
                'document_type'       => 'Addition Restaurant',
                'reference'           => 'CMD-REST-' . str_pad($order->id, 5, '0', STR_PAD_LEFT),
                'date'                => Carbon::parse($order->placed_at ?? $order->created_at),
                'total_amount'        => $total,
                'paid_amount'         => $paid,
                'balance_due'         => $balance,
                'status'              => $isPaid ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
                'status_label'        => $isPaid ? 'Réglée' : ($paid > 0 ? 'Partiel' : 'À régler'),
                'status_badge_class'  => $isPaid
                    ? 'bg-green-50 text-green-700 border-green-200'
                    : ($paid > 0 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-red-50 text-red-700 border-red-200'),
                'description'         => $tableDesc,
                'url_show'            => route('restaurant.billing.show', $order),
                'url_print'           => route('restaurant.billing.receipt', $order),
                'payment_method'      => match ($order->payment_method) {
                    'cash'         => 'Espèces',
                    'card'         => 'Carte bancaire',
                    'mobile_money' => 'Mobile Money',
                    'room_charge'  => 'Note de chambre',
                    default        => $order->payment_method ? ucfirst($order->payment_method) : 'Non spécifié',
                },
            ]);
        }

        return $docs;
    }

    /**
     * Récupère les tickets et commandes de boutique liés au client.
     */
    private function fetchShopOrders(Customer $customer, ?Carbon $startDate, ?Carbon $endDate): Collection
    {
        $docs = collect();

        $query = ShopOrder::query()
            ->where(function ($q) use ($customer) {
                $q->where('customer_id', $customer->id)
                  ->orWhereHas('booking', fn($b) => $b->where('customer_id', $customer->id));

                if (!empty($customer->phone)) {
                    $phoneClean = preg_replace('/[^\d]/', '', $customer->phone);
                    $q->orWhere('customer_phone', 'like', "%{$customer->phone}%");
                    if (strlen($phoneClean) >= 8) {
                        $q->orWhere('customer_phone', 'like', "%{$phoneClean}%");
                    }
                }

                if (!empty($customer->full_name)) {
                    $q->orWhereRaw('LOWER(customer_name) LIKE ?', ['%' . mb_strtolower($customer->full_name) . '%']);
                }
            })
            ->with(['items.product', 'booking.room']);

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate->toDateString());
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate->toDateString());
        }

        $orders = $query->get();

        foreach ($orders as $order) {
            $total = (int) $order->total_amount;
            $isPaid = $order->payment_status === 'paid';
            $paid = $isPaid ? $total : 0;
            $balance = $isPaid ? 0 : $total;

            $itemsDesc = ($order->total_items ?? $order->items->count()) . " article(s) boutique";
            if ($order->booking?->room) {
                $itemsDesc .= " (Ch. {$order->booking->room->number})";
            }

            $docs->push((object) [
                'id'                  => 'shop_' . $order->id,
                'source_type'         => 'shop_order',
                'source_id'           => $order->id,
                'service'             => 'shop',
                'service_label'       => 'Boutique',
                'service_badge_class' => 'bg-amber-50 text-amber-700 border-amber-200',
                'service_icon'        => 'shopping-bag',
                'document_type'       => 'Ticket Boutique',
                'reference'           => $order->order_number,
                'date'                => Carbon::parse($order->created_at),
                'total_amount'        => $total,
                'paid_amount'         => $paid,
                'balance_due'         => $balance,
                'status'              => $order->payment_status,
                'status_label'        => match ($order->payment_status) {
                    'paid'      => 'Réglée',
                    'refunded'  => 'Remboursée',
                    'cancelled' => 'Annulée',
                    default     => 'En attente',
                },
                'status_badge_class'  => match ($order->payment_status) {
                    'paid'      => 'bg-green-50 text-green-700 border-green-200',
                    'refunded'  => 'bg-purple-50 text-purple-700 border-purple-200',
                    'cancelled' => 'bg-red-50 text-red-700 border-red-200',
                    default     => 'bg-amber-50 text-amber-700 border-amber-200',
                },
                'description'         => $itemsDesc,
                'url_show'            => route('shop.orders.show', $order),
                'url_print'           => route('shop.orders.receipt', $order),
                'payment_method'      => match ($order->payment_method) {
                    'cash'        => 'Espèces',
                    'card'        => 'Carte bancaire',
                    'room_charge' => 'Note de chambre',
                    default       => $order->payment_method ? ucfirst($order->payment_method) : 'Non spécifié',
                },
            ]);
        }

        return $docs;
    }

    /**
     * Récupère les ventes directes et reçus de POS Réception.
     */
    private function fetchReceptionSales(Customer $customer, ?Carbon $startDate, ?Carbon $endDate): Collection
    {
        $docs = collect();

        $query = ReceptionSale::query()
            ->where(function ($q) use ($customer) {
                $q->where('customer_id', $customer->id)
                  ->orWhereHas('booking', fn($b) => $b->where('customer_id', $customer->id));

                if (!empty($customer->phone)) {
                    $phoneClean = preg_replace('/[^\d]/', '', $customer->phone);
                    $q->orWhere('customer_phone', 'like', "%{$customer->phone}%");
                    if (strlen($phoneClean) >= 8) {
                        $q->orWhere('customer_phone', 'like', "%{$phoneClean}%");
                    }
                }

                if (!empty($customer->full_name)) {
                    $q->orWhereRaw('LOWER(customer_name) LIKE ?', ['%' . mb_strtolower($customer->full_name) . '%']);
                }
            })
            ->with(['items', 'booking.room']);

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate->toDateString());
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate->toDateString());
        }

        $sales = $query->get();

        foreach ($sales as $sale) {
            $total = (int) $sale->total_amount;
            $isPaid = in_array($sale->payment_status, ['paid', 'completed']);
            $paid = $isPaid ? $total : 0;
            $balance = $isPaid ? 0 : $total;

            $itemsDesc = $sale->items->count() . " service(s) réception";
            if ($sale->room_number) {
                $itemsDesc .= " (Ch. {$sale->room_number})";
            }

            $docs->push((object) [
                'id'                  => 'rec_' . $sale->id,
                'source_type'         => 'reception_sale',
                'source_id'           => $sale->id,
                'service'             => 'reception',
                'service_label'       => 'POS Réception',
                'service_badge_class' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
                'service_icon'        => 'receipt',
                'document_type'       => 'Reçu Réception',
                'reference'           => $sale->sale_number,
                'date'                => Carbon::parse($sale->created_at),
                'total_amount'        => $total,
                'paid_amount'         => $paid,
                'balance_due'         => $balance,
                'status'              => $isPaid ? 'paid' : 'unpaid',
                'status_label'        => $isPaid ? 'Réglée' : 'En attente',
                'status_badge_class'  => $isPaid
                    ? 'bg-green-50 text-green-700 border-green-200'
                    : 'bg-red-50 text-red-700 border-red-200',
                'description'         => $itemsDesc,
                'url_show'            => route('reception.pos.receipt', $sale),
                'url_print'           => route('reception.pos.receipt', $sale),
                'payment_method'      => $sale->payment_method ? ucfirst($sale->payment_method) : 'Non spécifié',
            ]);
        }

        return $docs;
    }

    /**
     * Récupère les dossiers et factures de réservation de groupe.
     */
    private function fetchGroupInvoices(Customer $customer, ?Carbon $startDate, ?Carbon $endDate): Collection
    {
        $docs = collect();

        $query = GroupBooking::query()
            ->where('contact_customer_id', $customer->id)
            ->with(['bookings']);

        if ($startDate) {
            $query->whereDate('start_date', '>=', $startDate->toDateString());
        }
        if ($endDate) {
            $query->whereDate('start_date', '<=', $endDate->toDateString());
        }

        $groups = $query->get();

        foreach ($groups as $grp) {
            $total = (int) ($grp->total_deposit_required ?: $grp->bookings->sum('total_amount'));
            $paid = (int) $grp->total_deposit_paid;
            $balance = max(0, $total - $paid);
            $isPaid = $balance === 0 && $total > 0;

            $docs->push((object) [
                'id'                  => 'grp_' . $grp->id,
                'source_type'         => 'group_booking',
                'source_id'           => $grp->id,
                'service'             => 'group',
                'service_label'       => 'Groupe',
                'service_badge_class' => 'bg-purple-50 text-purple-700 border-purple-200',
                'service_icon'        => 'users',
                'document_type'       => 'Dossier Groupe',
                'reference'           => $grp->group_code,
                'date'                => Carbon::parse($grp->created_at ?? $grp->start_date),
                'total_amount'        => $total,
                'paid_amount'         => $paid,
                'balance_due'         => $balance,
                'status'              => $isPaid ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
                'status_label'        => $isPaid ? 'Réglée' : ($paid > 0 ? 'Acompte versé' : 'En attente'),
                'status_badge_class'  => $isPaid
                    ? 'bg-green-50 text-green-700 border-green-200'
                    : ($paid > 0 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-red-50 text-red-700 border-red-200'),
                'description'         => "Groupe {$grp->group_name} ({$grp->bookings->count()} chambre(s))",
                'url_show'            => route('groups.show', $grp),
                'url_print'           => route('groups.invoice', $grp),
                'payment_method'      => 'Dossier groupe',
            ]);
        }

        return $docs;
    }
}
