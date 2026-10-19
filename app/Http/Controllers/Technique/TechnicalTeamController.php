<?php

namespace App\Http\Controllers\Technique;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use App\Models\ServiceStoreStock;
use App\Models\TechnicianSpecialty;
use App\Models\User;
use App\Services\MaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * L'équipe technique (spécialités de chaque technicien, charge en cours) et
 * le dépôt d'où sortent ses pièces.
 */
class TechnicalTeamController extends Controller
{
    public function index(): View
    {
        $techniciens = User::query()->active()->havingRole(MaintenanceService::TECHNICIENS)
            ->with('technicianSpecialties')->orderBy('name')->get();

        $charge = MaintenanceRequest::query()->active()->whereNotNull('assigned_to')
            ->selectRaw('assigned_to, COUNT(*) as total')->groupBy('assigned_to')->pluck('total', 'assigned_to');

        return view('technique.equipe', compact('techniciens', 'charge'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->exerce(MaintenanceService::TECHNICIENS), 404);

        $donnees = $request->validate([
            'specialites'   => ['nullable', 'array'],
            'specialites.*' => [Rule::in(array_keys(MaintenanceRequest::SPECIALTIES))],
        ]);

        DB::transaction(function () use ($user, $donnees) {
            $user->technicianSpecialties()->delete();
            foreach (array_unique($donnees['specialites'] ?? []) as $specialite) {
                TechnicianSpecialty::create(['user_id' => $user->id, 'specialty' => $specialite]);
            }
        });

        return back()->with('success', "Spécialités de {$user->name} enregistrées.");
    }

    /** Le dépôt technique : les pièces disponibles et ce qui en est sorti. */
    public function depot(): View
    {
        $depots = MaintenanceService::depotsTechniques();
        $sorties = \App\Models\ServiceStoreMovement::query()->whereIn('service_store_id', $depots->pluck('id'))
            ->where('source_type', \App\Models\ServiceStoreMovement::SOURCE_INTERVENTION)
            ->with('item', 'user')->latest('occurred_at')->take(30)->get();

        return view('technique.depot', [
            'depots'   => $depots,
            'stocks'   => ServiceStoreStock::query()->whereIn('service_store_id', $depots->pluck('id'))
                ->with('item.category')->get()->filter(fn ($s) => $s->item !== null)
                ->sortBy(fn ($s) => $s->item->name)->groupBy('service_store_id'),
            'sorties'  => $sorties,
            'demandes' => MaintenanceRequest::query()->whereKey($sorties->pluck('source_id')->unique())->pluck('reference', 'id'),
        ]);
    }
}
