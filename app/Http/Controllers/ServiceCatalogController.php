<?php

namespace App\Http\Controllers;

use App\Models\ServiceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestion du catalogue des prestations (activités, spa, housekeeping,
 * blanchisserie, minibar). Ce catalogue alimente le formulaire d'ajout
 * de prestation au folio d'une réservation.
 */
class ServiceCatalogController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        ServiceItem::create($this->attributes($request, $validated));

        return $this->back('Prestation ajoutée au catalogue.');
    }

    public function update(Request $request, ServiceItem $serviceItem): RedirectResponse
    {
        $validated = $this->validated($request, $serviceItem);

        $serviceItem->update($this->attributes($request, $validated));

        return $this->back('Prestation mise à jour.');
    }

    public function destroy(ServiceItem $serviceItem): RedirectResponse
    {
        $serviceItem->delete();

        return $this->back('Prestation supprimée du catalogue.');
    }

    private function validated(Request $request, ?ServiceItem $serviceItem = null): array
    {
        return $request->validate([
            'category' => ['required', Rule::in(array_keys(ServiceItem::CATEGORIES))],
            'name' => [
                'required',
                'string',
                'max:140',
                Rule::unique('service_items', 'name')
                    ->where(fn ($q) => $q->where('category', $request->input('category')))
                    ->ignore($serviceItem?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            // Saisi en FCFA -> stocké en centimes
            'price' => ['required', 'integer', 'min:0', 'max:5000000'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'Une prestation portant ce nom existe déjà dans cette catégorie.',
        ]);
    }

    private function attributes(Request $request, array $validated): array
    {
        return [
            'category' => (string) $validated['category'],
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'price' => (int) $validated['price'] * 100,
            'duration_minutes' => $validated['duration_minutes'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    private function back(string $message): RedirectResponse
    {
        return redirect()
            ->route('settings.index', ['tab' => 'services'])
            ->with('success', $message);
    }
}
