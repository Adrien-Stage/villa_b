<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\CashRegisterSession;
use App\Models\FolioItem;
use App\Models\MinibarCheck;
use App\Models\Payment;
use App\Models\ReceptionSale;
use App\Models\ShiftAssignment;
use App\Models\ShiftHandover;
use App\Models\User;
use App\Notifications\ShiftHandoverPending;
use App\Notifications\ShiftHandoverTaken;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Passation de service à la réception.
 *
 * À la fin de son quart, le réceptionniste compte son tiroir et le remet,
 * avec ses consignes, à celui qui lui succède. Celui-ci voit tout ce que le
 * quart a fait — encaissements, sorties de caisse, arrivées, départs,
 * réservations — et ce qui reste à faire, recompte le tiroir et ouvre sa
 * caisse avec ce montant.
 *
 * Chacun garde sa session : la comptabilité contrôle celle du quart qui
 * s'achève comme toute caisse comptée. La passation va d'abord à la personne
 * que le planning attend au quart suivant ; si elle est empêchée, un autre
 * réceptionniste la reprend, et cela se voit.
 */
class PassationService
{
    public const MODULE = 'reception';

    public function __construct(
        private readonly CashRegisterCircuit $caisses,
        private readonly Notifier $notifier,
    ) {
    }

    /** Session de caisse ouverte de cette personne, à la réception. */
    public function sessionOuverte(User $user): ?CashRegisterSession
    {
        return CashRegisterSession::query()
            ->where('user_id', $user->id)
            ->where('module', self::MODULE)
            ->whereNull('closed_at')
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }

    /** Passations qui attendent d'être reprises, la plus ancienne d'abord. */
    public function enAttente(): Collection
    {
        return ShiftHandover::query()->pending()
            ->where('module', self::MODULE)
            ->with(['fromUser', 'plannedUser', 'plannedAssignment.shift'])
            ->orderBy('handed_at')
            ->get();
    }

    /**
     * Le réceptionniste que le planning attend après cet instant : celui dont
     * le quart commence le plus tôt, depuis trois heures au plus ou à venir.
     */
    public function prevuApres(User $sortant, ?CarbonImmutable $instant = null): ?ShiftAssignment
    {
        $instant ??= CarbonImmutable::now();

        return ShiftAssignment::query()
            ->with(['user', 'shift'])
            ->whereBetween('date', [$instant->subDay()->toDateString(), $instant->addDay()->toDateString()])
            ->where('user_id', '!=', $sortant->id)
            ->get()
            ->filter(fn (ShiftAssignment $a) => $a->user !== null && $a->user->is_active
                && $a->user->exerce(['reception'])
                && $a->fin()->greaterThan($instant)
                && $a->debut()->greaterThanOrEqualTo($instant->subHours(3)))
            ->sortBy(fn (ShiftAssignment $a) => $a->debut()->timestamp)
            ->first();
    }

    /**
     * Le quart d'une session : sa caisse et ce que la personne a fait entre
     * l'ouverture et la fin.
     */
    public function rapport(CashRegisterSession $session, ?CarbonInterface $fin = null): array
    {
        $debut = $session->opened_at;
        $fin ??= now();
        $qui = $session->user_id;
        $periode = [$debut, $fin];

        $paiements = Payment::query()
            ->with(['booking.customer', 'booking.room', 'customer'])
            ->where('cash_register_session_id', $session->id)
            ->orderBy('paid_at')
            ->get();

        $encaisses = $paiements->where('status', 'completed');

        return [
            'session'      => $session,
            'debut'        => $debut,
            'fin'          => $fin,
            'fond'         => (int) $session->opening_amount,
            'paiements'    => $paiements,
            'parMode'      => $encaisses->groupBy('method')
                ->map(fn (Collection $p) => ['libelle' => $p->first()->methodLabel(), 'total' => (int) $p->sum('amount'), 'nombre' => $p->count()])
                ->sortByDesc('total'),
            'totalEncaisse' => (int) $encaisses->sum('amount'),
            'especes'      => (int) $encaisses->where('method', 'cash')->sum('amount'),
            'decaissements' => $session->disbursements()->with('user')->orderBy('created_at')->get(),
            'ventesPos'    => ReceptionSale::query()->where('cash_register_session_id', $session->id)->orderBy('created_at')->get(),
            'theorique'    => $session->theoreticalBalance(),
            'compte'       => $session->actual_closing_amount,
            'ecart'        => $session->discrepancy_amount,
            'activite'     => [
                'arrivees' => Booking::query()->with(['customer', 'room'])->where('checked_in_by', $qui)
                    ->whereBetween('actual_check_in', $periode)->orderBy('actual_check_in')->get(),
                'departs' => Booking::query()->with(['customer', 'room'])->where('checked_out_by', $qui)
                    ->whereBetween('actual_check_out', $periode)->orderBy('actual_check_out')->get(),
                'reservations' => Booking::query()->with(['customer', 'room'])->where('created_by', $qui)
                    ->whereBetween('created_at', $periode)->orderBy('created_at')->get(),
                'annulations' => BookingCancellation::query()->with('booking.customer')->where('cancelled_by', $qui)
                    ->whereBetween('created_at', $periode)->orderBy('created_at')->get(),
                'prestations' => FolioItem::query()->with('booking.room')->where('recorded_by', $qui)
                    ->whereBetween('occurred_at', $periode)
                    ->whereNotIn('type', [FolioItem::TYPE_ROOM, FolioItem::TYPE_PAYMENT])
                    ->orderBy('occurred_at')->get(),
                'minibar' => Booking::query()->with('room')->where('minibar_check_requested_by', $qui)
                    ->whereBetween('minibar_check_requested_at', $periode)->get(),
            ],
        ];
    }

    /** Ce qui attend la réception en ce moment. */
    public function resteAFaire(): array
    {
        $aujourdhui = today()->toDateString();

        return [
            'arrivees' => Booking::query()->with(['customer', 'room'])
                ->whereIn('status', [BookingStatus::CONFIRMED, BookingStatus::PENDING])
                ->whereDate('check_in', '<=', $aujourdhui)
                ->orderBy('check_in')->limit(50)->get(),
            'departs' => Booking::query()->with(['customer', 'room'])
                ->where('status', BookingStatus::CHECKED_IN)
                ->whereDate('check_out', '<=', $aujourdhui)
                ->orderBy('check_out')->limit(50)->get(),
            'soldes' => Booking::query()->with(['customer', 'room'])
                ->where('status', BookingStatus::CHECKED_IN)
                ->where('balance_due', '>', 0)
                ->orderByDesc('balance_due')->limit(50)->get(),
            'aConfirmer' => Booking::query()->with(['customer', 'room'])
                ->where('status', BookingStatus::PENDING)
                ->where('source', 'website')
                ->orderBy('created_at')->limit(50)->get(),
            'minibar' => Booking::query()->with('room')
                ->where('status', BookingStatus::CHECKED_IN)
                ->whereNotNull('minibar_check_requested_at')
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('minibar_checks')
                    ->whereColumn('minibar_checks.booking_id', 'bookings.id')
                    ->where('minibar_checks.occasion', MinibarCheck::OCCASION_DEPART))
                ->get(),
        ];
    }

    /**
     * Fin de quart : le réceptionniste compte son tiroir et le remet, avec
     * ses consignes. Sa caisse attend ensuite le contrôle de la comptabilité,
     * comme toute caisse comptée.
     *
     * @throws \DomainException
     */
    public function remettre(User $sortant, int $compteCentimes, ?string $consignes = null): ShiftHandover
    {
        $session = $this->sessionOuverte($sortant);
        if ($session === null) {
            throw new \DomainException("Vous n'avez pas de caisse de réception ouverte à remettre.");
        }
        if ($compteCentimes < 0) {
            throw new \DomainException('Le montant compté ne peut pas être négatif.');
        }

        $prevu = $this->prevuApres($sortant);
        $consignes = $consignes !== null && trim($consignes) !== '' ? trim($consignes) : null;

        $passation = DB::transaction(function () use ($session, $sortant, $compteCentimes, $consignes, $prevu) {
            $this->caisses->compter($session, $compteCentimes, 'Tiroir remis en passation de service.');

            return ShiftHandover::create([
                'module'                => self::MODULE,
                'status'                => ShiftHandover::STATUS_PENDING,
                'from_user_id'          => $sortant->id,
                'from_session_id'       => $session->id,
                'handed_amount'         => $compteCentimes,
                'instructions'          => $consignes,
                'handed_at'             => now(),
                'planned_user_id'       => $prevu?->user_id,
                'planned_assignment_id' => $prevu?->id,
            ]);
        });

        AuditLog::record($sortant->id, 'passation_remise',
            'Passation de service : tiroir de ' . number_format($compteCentimes / 100, 0, ',', ' ') . ' FCFA remis'
                . ($prevu ? ' (prévu : ' . $prevu->user->name . ')' : ''),
            'bookings', ['shift_handover_id' => $passation->id]);

        // La personne attendue d'abord ; toute la réception, au cas où elle
        // serait empêchée.
        $notification = new ShiftHandoverPending($passation->load('fromUser'));
        if ($prevu?->user) {
            $this->notifier->send($prevu->user, $notification);
        }
        $this->notifier->toRoles(['reception'], $notification, $sortant->id);

        return $passation;
    }

    /**
     * Prise de service : l'entrant a lu la passation, recompte le tiroir et
     * ouvre sa caisse avec le montant compté. Un écart à la remise reste
     * inscrit, avec les deux noms.
     *
     * @throws \DomainException
     */
    public function reprendre(ShiftHandover $passation, User $entrant, int $compteCentimes, ?string $notes = null): CashRegisterSession
    {
        if ($compteCentimes < 0) {
            throw new \DomainException('Le montant compté ne peut pas être négatif.');
        }

        $session = DB::transaction(function () use ($passation, $entrant, $compteCentimes, $notes) {
            $passation = ShiftHandover::query()->lockForUpdate()->findOrFail($passation->id);

            if (!$passation->isPending()) {
                throw new \DomainException('Cette passation a déjà été reprise par ' . ($passation->toUser?->name ?? 'un autre réceptionniste') . '.');
            }
            if ($passation->from_user_id === $entrant->id) {
                throw new \DomainException('Vous ne pouvez pas reprendre votre propre passation : un autre réceptionniste ou le chef de réception la reprend.');
            }

            $enCours = CashRegisterSession::query()
                ->where('user_id', $entrant->id)
                ->where('module', self::MODULE)
                ->whereNull('closed_at')
                ->first();
            if ($enCours !== null) {
                throw new \DomainException($enCours->isPendingReview()
                    ? 'Votre dernier comptage attend encore le contrôle de la comptabilité : vous reprendrez le service ensuite.'
                    : 'Vous avez déjà une caisse de réception ouverte.');
            }

            $session = CashRegisterSession::create([
                'user_id'        => $entrant->id,
                'module'         => self::MODULE,
                'status'         => 'open',
                'opening_amount' => $compteCentimes,
                'opened_at'      => now(),
                'notes'          => 'Reprise de la passation de ' . ($passation->fromUser?->name ?? '—'),
            ]);

            $passation->update([
                'status'             => ShiftHandover::STATUS_TAKEN,
                'to_user_id'         => $entrant->id,
                'to_session_id'      => $session->id,
                'received_amount'    => $compteCentimes,
                'discrepancy_amount' => $compteCentimes - $passation->handed_amount,
                'reception_notes'    => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
                'taken_at'           => now(),
            ]);

            return $session;
        });

        $passation->refresh()->load(['fromUser', 'toUser', 'plannedUser']);

        AuditLog::record($entrant->id, 'passation_reprise',
            'Prise de service : tiroir repris à ' . ($passation->fromUser?->name ?? '—') . ', '
                . number_format($compteCentimes / 100, 0, ',', ' ') . ' FCFA comptés'
                . ($passation->discrepancy_amount !== 0 ? ' (écart ' . number_format($passation->discrepancy_amount / 100, 0, ',', ' ') . ' FCFA)' : '')
                . ($passation->reprisHorsPlanning() ? ' — à la place de ' . $passation->plannedUser?->name : ''),
            'bookings', ['shift_handover_id' => $passation->id]);

        $notification = new ShiftHandoverTaken($passation);
        $this->notifier->send($passation->fromUser, $notification);
        if ($passation->discrepancy_amount !== 0) {
            $this->notifier->toRoles(['reception_chief'], $notification, $entrant->id);
        }

        return $session;
    }
}
