<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Disponibilité d'une chambre : ce qu'on affiche, et ce qu'on accepte de
 * réserver.
 *
 * Une chambre est disponible **pour des dates**, pas dans l'absolu. Une suite
 * occupée cette semaine reste vendable pour le mois prochain : c'est pourquoi
 * le catalogue montre tout le parc et que seule la période demandée décide.
 *
 * Deux règles gouvernent l'acceptation :
 *
 *  - Les séjours occupent un intervalle semi-ouvert [arrivée, départ). Deux
 *    séjours qui se touchent bout à bout ne se chevauchent pas : le client
 *    qui part le 12 libère la chambre pour celui qui arrive le 12.
 *
 *  - Entre les deux, il faut le temps du ménage. La rotation le jour même
 *    n'est acceptée que si l'heure d'arrivée tombe après l'heure de départ
 *    augmentée du délai de remise en état — une suite présidentielle ne se
 *    prépare pas aussi vite qu'une chambre économique.
 *
 * Les délais se règlent dans Paramètres → Hébergement, les heures d'arrivée
 * et de départ dans Paramètres → Réception. Le site vitrine et la plateforme
 * interne passent tous deux par ce service : la règle est la même des deux
 * côtés, sinon le site promettrait ce que la réception refuse.
 */
class RoomAvailabilityService
{
    /** Délai retenu quand rien n'est configuré. */
    public const DEFAULT_DELAY_MINUTES = 120;

    public const DEFAULT_CHECK_IN_TIME  = '14:00';
    public const DEFAULT_CHECK_OUT_TIME = '12:00';

    /** Horizon des périodes réservées transmises au site, en mois. */
    private const BUSY_HORIZON_MONTHS = 12;

    /** Statuts du cycle ménage : la chambre est en cours de remise en état. */
    public const CLEANING_STATUSES = [
        RoomStatus::DIRTY,
        RoomStatus::CLEANING,
        RoomStatus::CLEAN,
        RoomStatus::INSPECTED,
    ];

    /** Statuts sans échéance connue : la chambre ne peut pas être vendue. */
    public const UNSELLABLE_STATUSES = [
        RoomStatus::MAINTENANCE,
        RoomStatus::OUT_OF_ORDER,
    ];

    private ?array $settings = null;
    private ?array $receptionSettings = null;

    /** Réglages du tenant, chargés une seule fois par instance. */
    private function tenantSettings(): array
    {
        $all = Tenant::query()->value('settings') ?? [];

        return is_string($all) ? (json_decode($all, true) ?: []) : (array) $all;
    }

    /** Bloc « hebergement » des réglages du tenant. */
    private function settings(): array
    {
        return $this->settings ??= (array) ($this->tenantSettings()['hebergement'] ?? []);
    }

    /** Bloc « reception » : heures d'arrivée et de départ. */
    private function reception(): array
    {
        return $this->receptionSettings ??= (array) ($this->tenantSettings()['reception'] ?? []);
    }

    /** Heure à partir de laquelle un client peut prendre possession (HH:MM). */
    public function checkInTime(): string
    {
        $value = trim((string) ($this->reception()['check_in_time'] ?? ''));

        return preg_match('/^\d{1,2}:\d{2}$/', $value) ? $value : self::DEFAULT_CHECK_IN_TIME;
    }

    /** Heure limite de libération de la chambre (HH:MM). */
    public function checkOutTime(): string
    {
        $value = trim((string) ($this->reception()['check_out_time'] ?? ''));

        return preg_match('/^\d{1,2}:\d{2}$/', $value) ? $value : self::DEFAULT_CHECK_OUT_TIME;
    }

    /** Délai global, en minutes. */
    public function defaultDelayMinutes(): int
    {
        $value = $this->settings()['cleaning_delay_minutes'] ?? null;

        return is_numeric($value) && (int) $value >= 0
            ? (int) $value
            : self::DEFAULT_DELAY_MINUTES;
    }

    /**
     * Délai applicable à un type de chambre : sa surcharge si elle existe,
     * sinon le délai global.
     */
    public function delayMinutesFor(?RoomType $type): int
    {
        $overrides = (array) ($this->settings()['cleaning_delay_by_type'] ?? []);
        $override = $type ? ($overrides[$type->id] ?? null) : null;

        return is_numeric($override) && (int) $override >= 0
            ? (int) $override
            : $this->defaultDelayMinutes();
    }

    /** La chambre est-elle en cours de remise en état ? */
    public function isBeingPrepared(Room $room): bool
    {
        return in_array($room->status, self::CLEANING_STATUSES, true);
    }

    /**
     * La chambre peut-elle être vendue, toutes dates confondues ? Faux
     * seulement quand son indisponibilité n'a pas d'échéance connue
     * (maintenance, hors service) : occupée n'empêche pas de vendre plus tard.
     */
    public function isSellable(Room $room): bool
    {
        return $room->is_active && !in_array($room->status, self::UNSELLABLE_STATUSES, true);
    }

    /**
     * Conservé pour l'affichage : la chambre est-elle prête ou en passe de
     * l'être ? Ne décide plus de l'acceptation d'une réservation, qui dépend
     * désormais des dates demandées.
     */
    public function isBookableOnline(Room $room): bool
    {
        return $room->status === RoomStatus::AVAILABLE || $this->isBeingPrepared($room);
    }

    // ── Disponibilité sur une période ─────────────────────────────────────────

    /**
     * Une nouvelle arrivée peut-elle se faire le jour même du départ d'un
     * séjour précédent ? Oui si le ménage a le temps de passer entre l'heure
     * limite de sortie et l'heure d'arrivée.
     */
    public function canTurnOverSameDay(?RoomType $type): bool
    {
        $readyAt = Carbon::parse($this->checkOutTime())
            ->addMinutes($this->delayMinutesFor($type));

        return $readyAt->lessThanOrEqualTo(Carbon::parse($this->checkInTime()));
    }

    /** Séjours actifs de la chambre qui recoupent la période demandée. */
    private function overlappingBookings(Room $room, CarbonInterface $checkIn, CarbonInterface $checkOut, ?int $ignoreBookingId = null)
    {
        return Booking::query()
            ->where('room_id', $room->id)
            ->when($ignoreBookingId, fn ($q) => $q->where('id', '!=', $ignoreBookingId))
            ->whereNotIn('status', [BookingStatus::CANCELLED->value, BookingStatus::NO_SHOW->value])
            // Intervalles semi-ouverts : deux séjours bout à bout ne se
            // chevauchent pas. C'est ce qui autorise la rotation du jour même.
            ->whereDate('check_in', '<', $checkOut->toDateString())
            ->whereDate('check_out', '>', $checkIn->toDateString())
            ->get();
    }

    /**
     * Motif de refus d'une réservation sur la période demandée, ou null si
     * elle peut passer. Le message est destiné au client comme au réceptionniste.
     */
    public function conflictReason(Room $room, CarbonInterface|string $checkIn, CarbonInterface|string $checkOut, ?int $ignoreBookingId = null): ?string
    {
        $checkIn  = Carbon::parse($checkIn)->startOfDay();
        $checkOut = Carbon::parse($checkOut)->startOfDay();

        if ($checkOut->lessThanOrEqualTo($checkIn)) {
            return 'La date de départ doit être postérieure à la date d\'arrivée.';
        }

        if (!$room->is_active) {
            return 'Cette chambre n\'est pas commercialisée.';
        }

        if (in_array($room->status, self::UNSELLABLE_STATUSES, true)) {
            return $room->status === RoomStatus::MAINTENANCE
                ? 'Cette chambre est en maintenance et n\'est pas réservable pour le moment.'
                : 'Cette chambre est hors service.';
        }

        if ($this->overlappingBookings($room, $checkIn, $checkOut, $ignoreBookingId)->isNotEmpty()) {
            return 'Cette chambre est déjà occupée sur cette période.';
        }

        // Séjour qui s'achève le jour de l'arrivée demandée : reste à savoir
        // si le ménage a le temps de passer dans la journée.
        $sameDayDeparture = Booking::query()
            ->where('room_id', $room->id)
            ->when($ignoreBookingId, fn ($q) => $q->where('id', '!=', $ignoreBookingId))
            ->whereNotIn('status', [BookingStatus::CANCELLED->value, BookingStatus::NO_SHOW->value])
            ->whereDate('check_out', $checkIn->toDateString())
            ->exists();

        if ($sameDayDeparture && !$this->canTurnOverSameDay($room->roomType)) {
            $ready = Carbon::parse($this->checkOutTime())
                ->addMinutes($this->delayMinutesFor($room->roomType))
                ->format('H\hi');

            return "Le client précédent part le jour de votre arrivée : la chambre ne sera prête qu'à {$ready}, "
                . 'après le ménage. Choisissez le lendemain ou une autre chambre.';
        }

        // Arrivée du jour sur une chambre encore en remise en état : ce qui
        // compte est l'heure d'arrivée du client, pas l'instant présent. Une
        // chambre prête à 14 h accepte une arrivée à 14 h.
        if ($checkIn->isToday() && $this->isBeingPrepared($room)) {
            $availableAt = $this->availableAt($room);
            $arrival = today()->setTimeFromTimeString($this->checkInTime());

            if ($availableAt && $availableAt->greaterThan($arrival)) {
                return 'Cette chambre est en cours de remise en état, disponible à partir de '
                    . $availableAt->format('H\hi') . '.';
            }
        }

        return null;
    }

    /** Raccourci booléen sur conflictReason(). */
    public function isFreeBetween(Room $room, CarbonInterface|string $checkIn, CarbonInterface|string $checkOut, ?int $ignoreBookingId = null): bool
    {
        return $this->conflictReason($room, $checkIn, $checkOut, $ignoreBookingId) === null;
    }

    /**
     * Périodes déjà prises, pour que le site grise les dates de son calendrier
     * au lieu de laisser le client choisir puis essuyer un refus.
     *
     * @return list<array{from: string, to: string}>
     */
    public function busyRanges(Room $room): array
    {
        return Booking::query()
            ->where('room_id', $room->id)
            ->whereNotIn('status', [BookingStatus::CANCELLED->value, BookingStatus::NO_SHOW->value])
            ->whereDate('check_out', '>=', today()->toDateString())
            ->whereDate('check_in', '<=', today()->addMonths(self::BUSY_HORIZON_MONTHS)->toDateString())
            ->orderBy('check_in')
            ->get(['check_in', 'check_out'])
            ->map(fn (Booking $b) => [
                'from' => $b->check_in->toDateString(),
                'to'   => $b->check_out->toDateString(),
            ])
            ->values()
            ->all();
    }

    /** Date de départ du séjour en cours, s'il y en a un. */
    public function occupiedUntil(Room $room): ?CarbonInterface
    {
        $current = Booking::query()
            ->where('room_id', $room->id)
            ->whereNotIn('status', [BookingStatus::CANCELLED->value, BookingStatus::NO_SHOW->value])
            ->whereDate('check_in', '<=', today()->toDateString())
            ->whereDate('check_out', '>', today()->toDateString())
            ->orderBy('check_out')
            ->first();

        return $current?->check_out;
    }

    /**
     * Moment où la chambre s'est libérée : dernier passage à « à nettoyer ».
     * Sans trace en historique, on retombe sur la dernière modification de la
     * chambre — approximatif, mais toujours préférable à ne rien afficher.
     */
    public function freedAt(Room $room): ?CarbonInterface
    {
        if (!$this->isBeingPrepared($room)) {
            return null;
        }

        $entry = $room->statusHistory()
            ->whereIn('to_status', [RoomStatus::DIRTY->value, RoomStatus::CLEANING->value])
            ->latest('changed_at')
            ->first();

        $moment = $entry?->changed_at ?? $entry?->created_at ?? $room->updated_at;

        return $moment ? Carbon::parse($moment) : null;
    }

    /**
     * Heure estimée de remise en vente. Null si la chambre est déjà
     * disponible ou si elle n'est pas dans le cycle ménage (maintenance,
     * hors service, occupée : ce n'est plus une question de ménage).
     */
    public function availableAt(Room $room): ?CarbonInterface
    {
        if (!$this->isBeingPrepared($room)) {
            return null;
        }

        $freedAt = $this->freedAt($room);
        $delay   = $this->delayMinutesFor($room->roomType);

        if (!$freedAt) {
            return now()->addMinutes($delay);
        }

        return $freedAt->copy()->addMinutes($delay);
    }

    /**
     * Minutes restantes avant remise en vente, jamais négatif. Zéro signifie
     * que le délai est écoulé : le ménage a débordé, on n'annonce plus
     * d'attente au client mais la chambre reste hors vente tant que
     * l'équipe ne l'a pas libérée.
     */
    public function minutesRemaining(Room $room): ?int
    {
        $availableAt = $this->availableAt($room);

        if (!$availableAt) {
            return null;
        }

        return max(0, (int) ceil(now()->diffInMinutes($availableAt, false)));
    }

    /**
     * Formulation lisible côté client : « disponible dans 2 h »,
     * « disponible dans 45 min », « disponible d'ici peu ».
     */
    public function label(Room $room): ?string
    {
        $minutes = $this->minutesRemaining($room);

        if ($minutes === null) {
            return null;
        }

        if ($minutes <= 0) {
            return 'Disponible d\'ici peu';
        }

        if ($minutes < 60) {
            return "Disponible dans {$minutes} min";
        }

        $hours = intdiv($minutes, 60);
        $rest  = $minutes % 60;

        return $rest === 0
            ? 'Disponible dans ' . $hours . ' h'
            : 'Disponible dans ' . $hours . ' h ' . str_pad((string) $rest, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Bloc d'informations exposé au site vitrine.
     *
     * `sellable` dit si la chambre peut être réservée pour une date, même
     * lointaine ; `state` décrit sa situation du moment. Une chambre occupée
     * reste affichée et vendable pour plus tard : c'est `busy_ranges` qui
     * indique au calendrier du site quelles dates refuser.
     */
    public function payload(Room $room): array
    {
        $sellable      = $this->isSellable($room);
        $readyNow      = $room->status === RoomStatus::AVAILABLE;
        $occupiedUntil = $sellable ? $this->occupiedUntil($room) : null;

        $state = match (true) {
            !$sellable                  => 'unavailable',
            $occupiedUntil !== null     => 'occupied',
            $this->isBeingPrepared($room) => 'preparing',
            default                     => 'available',
        };

        $label = match ($state) {
            'unavailable' => $room->status === RoomStatus::MAINTENANCE ? 'En maintenance' : 'Hors service',
            'occupied'    => 'Occupée jusqu\'au ' . $occupiedUntil->translatedFormat('j F'),
            'preparing'   => $this->label($room),
            default       => 'Disponible',
        };

        return [
            'sellable'          => $sellable,
            'state'             => $state,
            'label'             => $label,
            'ready_now'         => $readyNow,

            // Première date d'arrivée acceptable, pour préremplir le calendrier.
            'available_from'    => $sellable ? $this->nextBookableDate($room)->toDateString() : null,
            'available_at'      => $readyNow ? null : $this->availableAt($room)?->toIso8601String(),
            'minutes_remaining' => $readyNow ? null : $this->minutesRemaining($room),

            // Dates à griser côté site : le client ne choisit pas pour se voir refuser.
            'busy_ranges'       => $sellable ? $this->busyRanges($room) : [],
            'check_in_time'     => $this->checkInTime(),
            'check_out_time'    => $this->checkOutTime(),
        ];
    }

    /**
     * Première date à laquelle la chambre peut accueillir une arrivée. Sert de
     * point de départ au calendrier du site plutôt que de laisser le client
     * tâtonner sur des dates refusées.
     */
    public function nextBookableDate(Room $room): CarbonInterface
    {
        $day = today();

        // Un mois d'essais suffit : au-delà, le calendrier du site prend le
        // relais avec busy_ranges.
        for ($i = 0; $i <= 31; $i++) {
            if ($this->isFreeBetween($room, $day, $day->copy()->addDay())) {
                return $day;
            }
            $day = $day->copy()->addDay();
        }

        return $day;
    }
}
