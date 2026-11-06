<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\ServiceStore;
use App\Models\ServiceStoreCount;
use App\Services\PermissionResolver;
use App\Services\ServiceStoreCountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** Inventaire d'un dépôt de service : ouverture, comptage, clôture. */
class ServiceStoreCountController extends Controller
{
    public function __construct(private ServiceStoreCountService $counts)
    {
    }

    public function store(Request $request, ServiceStore $store): RedirectResponse
    {
        try {
            $inventaire = $this->counts->open($store, $request->user(), $request->input('notes'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('economat.stores.counts.show', $inventaire)
            ->with('success', "Inventaire {$inventaire->reference} ouvert : le dépôt ne reçoit plus de livraison jusqu'à sa clôture.");
    }

    public function show(ServiceStoreCount $count): View
    {
        $count->load('store', 'openedBy', 'submittedBy', 'closedBy', 'validatedBy', 'rejectedBy', 'cancelledBy', 'lines.item.category');
        $droits = app(PermissionResolver::class);
        $user = auth()->user();

        return view('economat.stores.count', [
            'count'     => $count,
            'lines'     => $count->lines->sortBy(fn ($l) => $l->item?->name ?? ''),
            'canCount'  => $droits->allows($user, 'economat.stores.counts.modifier') && $count->canBeModified(),
            'canSubmit' => $droits->allows($user, 'economat.stores.counts.submit'),
            'canClose'  => $droits->allows($user, 'economat.stores.counts.close'),
            'canReject' => $droits->allows($user, 'economat.stores.counts.reject'),
            'canCancel' => $droits->allows($user, 'economat.stores.counts.cancel') && (!$count->isClosed() || ($droits->allows($user, 'economat.stores.counts.close') && $count->canBeCancelledByRaf())),
        ]);
    }

    public function submit(Request $request, ServiceStoreCount $count): RedirectResponse
    {
        try {
            $count = $this->counts->submit($count, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('economat.stores.counts.show', $count)
            ->with('success', "Inventaire {$count->reference} soumis au RAF avec succès. Les comptages sont verrouillés en attente de validation.");
    }

    public function reject(Request $request, ServiceStoreCount $count): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'reason.required' => 'Le motif du rejet est obligatoire pour renvoyer la feuille au contrôleur.',
        ]);

        try {
            $count = $this->counts->reject($count, $validated['reason'], $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('economat.stores.counts.show', $count)
            ->with('success', "Inventaire {$count->reference} rejeté. La feuille est renvoyée en correction au contrôleur de gestion.");
    }

    public function update(Request $request, ServiceStoreCount $count): RedirectResponse
    {
        $validated = $request->validate([
            'lines'                    => ['required', 'array'],
            'lines.*.counted_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.notes'            => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->counts->updateCounts($count, $validated['lines']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Comptages enregistrés.');
    }

    public function close(Request $request, ServiceStoreCount $count): RedirectResponse
    {
        try {
            $count = $this->counts->close($count, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $consommation = number_format($count->consumptionValue() / 100, 0, ',', ' ');

        return back()->with('success', "Inventaire {$count->reference} validé par le RAF. Consommation constatée : {$consommation} FCFA.");
    }

    public function cancel(Request $request, ServiceStoreCount $count): RedirectResponse
    {
        if ($count->isClosed()) {
            if (!app(PermissionResolver::class)->allows($request->user(), 'economat.stores.counts.close')) {
                return back()->with('access_denied_popup', true);
            }
        }

        $reason = $request->input('reason');

        try {
            $this->counts->cancel($count, $request->user(), $reason);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = $count->isClosed()
            ? "Inventaire {$count->reference} annulé par le RAF sous 48h. Les contre-écritures de stock ont été passées et l'ancien niveau de stock est rétabli."
            : "Inventaire {$count->reference} annulé : le dépôt reçoit de nouveau les livraisons.";

        return redirect()
            ->route('economat.stores.show', $count->service_store_id)
            ->with('success', $message);
    }
}
