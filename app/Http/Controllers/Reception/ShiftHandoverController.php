<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Models\ShiftHandover;
use App\Services\PassationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Passations de service de la réception : remettre le tiroir en fin de quart,
 * reprendre le service, relire les passations.
 */
class ShiftHandoverController extends Controller
{
    public function __construct(private readonly PassationService $passations)
    {
    }

    /** Les passations, celles qui attendent d'abord. */
    public function index(): View
    {
        return view('bookings.handovers.index', [
            'enAttente' => $this->passations->enAttente(),
            'passations' => ShiftHandover::query()
                ->where('module', PassationService::MODULE)
                ->where('status', ShiftHandover::STATUS_TAKEN)
                ->with(['fromUser', 'toUser', 'plannedUser'])
                ->latest('handed_at')
                ->paginate(20),
        ]);
    }

    /** La fiche d'une passation : le quart remis, ses consignes, sa reprise. */
    public function show(ShiftHandover $handover): View
    {
        $handover->load(['fromUser', 'toUser', 'plannedUser', 'plannedAssignment.shift', 'fromSession']);

        return view('bookings.handovers.show', [
            'passation' => $handover,
            'rapport'   => $this->passations->rapport($handover->fromSession, $handover->handed_at),
        ]);
    }

    /** Fin de quart : ce que le quart a fait, le comptage et les consignes. */
    public function create(): View|RedirectResponse
    {
        $session = $this->passations->sessionOuverte(Auth::user());
        if ($session === null) {
            return redirect()->route('bookings.cash_register.index')
                ->with('error', "Vous n'avez pas de caisse de réception ouverte à remettre.");
        }

        return view('bookings.handovers.create', [
            'rapport'  => $this->passations->rapport($session),
            'resteAFaire' => $this->passations->resteAFaire(),
            'prevu'    => $this->passations->prevuApres(Auth::user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'handed_amount' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'instructions'  => ['nullable', 'string', 'max:3000'],
        ], [
            'handed_amount.required' => 'Indiquez le montant compté dans le tiroir.',
        ]);

        try {
            $passation = $this->passations->remettre(Auth::user(), (int) round($data['handed_amount'] * 100), $data['instructions'] ?? null);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('bookings.handovers.show', $passation)
            ->with('success', 'Tiroir remis. La passation attend ' . ($passation->plannedUser?->name ?? 'le prochain réceptionniste') . ' : bonne fin de journée.');
    }

    /** Prise de service : lire la passation, recompter le tiroir. */
    public function take(ShiftHandover $handover): View|RedirectResponse
    {
        if (!$handover->isPending()) {
            return redirect()->route('bookings.handovers.show', $handover)
                ->with('info', 'Cette passation a déjà été reprise par ' . ($handover->toUser?->name ?? 'un autre réceptionniste') . '.');
        }

        $handover->load(['fromUser', 'plannedUser', 'plannedAssignment.shift', 'fromSession']);

        return view('bookings.handovers.take', [
            'passation'   => $handover,
            'rapport'     => $this->passations->rapport($handover->fromSession, $handover->handed_at),
            'resteAFaire' => $this->passations->resteAFaire(),
        ]);
    }

    public function takeStore(Request $request, ShiftHandover $handover): RedirectResponse
    {
        $data = $request->validate([
            'received_amount' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'reception_notes' => ['nullable', 'string', 'max:2000'],
            'lu'              => ['accepted'],
        ], [
            'received_amount.required' => 'Indiquez le montant que vous comptez dans le tiroir.',
            'lu.accepted'              => 'Confirmez avoir pris connaissance de la passation.',
        ]);

        try {
            $session = $this->passations->reprendre($handover, Auth::user(), (int) round($data['received_amount'] * 100), $data['reception_notes'] ?? null);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $handover->refresh();
        $ecart = (int) $handover->discrepancy_amount;

        return redirect()->route('bookings.cash_register.index')->with('success',
            'Service repris : caisse ouverte avec ' . number_format($session->opening_amount / 100, 0, ',', ' ') . ' FCFA.'
            . ($ecart !== 0 ? ' Écart de ' . number_format($ecart / 100, 0, ',', ' ') . ' FCFA à la remise, inscrit sur la passation.' : '')
            . ' Bon service.');
    }
}
