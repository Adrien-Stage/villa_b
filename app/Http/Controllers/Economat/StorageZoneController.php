<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\StockItem;
use App\Models\StorageZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Les zones de rangement de l'économat (Paramètres › Économat), et la zone
 * de chaque article. Ranger la marchandise est le travail de l'économat :
 * c'est lui qui déplace un article d'une zone à l'autre.
 */
class StorageZoneController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $valide = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('storage_zones', 'name')],
        ]);

        $zone = StorageZone::create([
            'name' => trim($valide['name']),
            'sort_order' => (int) StorageZone::max('sort_order') + 1,
            'is_active' => true,
        ]);

        $this->journal("Zone de rangement « {$zone->name} » créée", $zone);

        return $this->retour()->with('success', "Zone « {$zone->name} » ajoutée.");
    }

    public function update(Request $request, StorageZone $zone): RedirectResponse
    {
        $valide = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('storage_zones', 'name')->ignore($zone->id)],
        ]);

        $ancien = $zone->name;
        $zone->update(['name' => trim($valide['name']), 'is_active' => $request->boolean('is_active', true)]);

        $this->journal("Zone de rangement « {$ancien} » renommée « {$zone->name} »" . ($zone->is_active ? '' : ' (hors service)'), $zone);

        return $this->retour()->with('success', "Zone « {$zone->name} » mise à jour.");
    }

    public function destroy(StorageZone $zone): RedirectResponse
    {
        $articles = $zone->items()->count();
        if ($articles > 0) {
            return $this->retour()->withErrors([
                'zone' => "La zone « {$zone->name} » range {$articles} article(s) : déplacez-les d'abord, ou mettez-la hors service.",
            ]);
        }

        $nom = $zone->name;
        $zone->delete();
        $this->journal("Zone de rangement « {$nom} » supprimée", $zone);

        return $this->retour()->with('success', "Zone « {$nom} » supprimée.");
    }

    /** L'économat range un article dans une autre zone. */
    public function moveItem(Request $request, StockItem $item): RedirectResponse
    {
        $valide = $request->validate([
            'storage_zone_id' => ['nullable', Rule::exists('storage_zones', 'id')->where('is_active', true)],
        ]);

        $avant = $item->zone?->name ?? 'sans zone';
        $item->update(['storage_zone_id' => $valide['storage_zone_id'] ?? null]);
        $apres = $item->fresh('zone')->zone?->name ?? 'sans zone';

        AuditLog::record(Auth::id(), 'stock_item_zone', "« {$item->name} » rangé : {$avant} → {$apres}", 'economat',
            ['stock_item_id' => $item->id, 'storage_zone_id' => $item->storage_zone_id]);

        return back()->with('success', "« {$item->name} » se range désormais : {$apres}.");
    }

    private function journal(string $action, StorageZone $zone): void
    {
        AuditLog::record(Auth::id(), 'storage_zone', $action, 'economat', ['storage_zone_id' => $zone->id]);
    }

    private function retour(): RedirectResponse
    {
        return redirect()->route('settings.index', ['tab' => 'economat']);
    }
}
