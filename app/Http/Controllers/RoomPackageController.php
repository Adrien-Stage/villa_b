<?php

namespace App\Http\Controllers;

use App\Models\RestaurantMenuItem;
use App\Models\RoomPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestion des packs d'hébergement (demi-pension, pension complète, formules
 * avec blanchisserie…) proposés au client au moment de la réservation.
 */
class RoomPackageController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        RoomPackage::create($this->attributes($request, $validated));

        return $this->back('Pack créé.');
    }

    public function update(Request $request, RoomPackage $roomPackage): RedirectResponse
    {
        $validated = $this->validated($request, $roomPackage);

        $roomPackage->update($this->attributes($request, $validated));

        return $this->back('Pack mis à jour.');
    }

    public function destroy(RoomPackage $roomPackage): RedirectResponse
    {
        // Les séjours déjà vendus gardent leur montant figé (package_amount)
        // et la contrainte est en nullOnDelete : la facture reste juste.
        $sold = $roomPackage->bookings()->count();

        $roomPackage->delete();

        return $this->back($sold > 0
            ? "Pack supprimé. {$sold} séjour(s) conservent le montant déjà facturé."
            : 'Pack supprimé.');
    }

    private function validated(Request $request, ?RoomPackage $package = null): array
    {
        // Un pourcentage est borné à 100 ; un montant par nuitée ne l'est pas.
        $discountValueRule = $request->input('room_discount_type') === RoomPackage::DISCOUNT_PERCENT
            ? ['nullable', 'integer', 'min:0', 'max:100']
            : ['nullable', 'integer', 'min:0', 'max:10000000'];

        return $request->validate([
            'name' => [
                'required', 'string', 'max:140',
                Rule::unique('room_packages', 'name')->ignore($package?->id),
            ],
            'code'        => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:500'],

            'meals'   => ['nullable', 'array'],
            'meals.*' => [Rule::in(array_keys(RestaurantMenuItem::MEAL_SERVICES))],

            'service_item_ids'   => ['nullable', 'array'],
            'service_item_ids.*' => ['integer', 'exists:service_items,id'],

            'pricing_mode' => ['required', Rule::in(array_keys(RoomPackage::PRICING_MODES))],
            // Saisi en FCFA, stocké en centimes.
            'price'        => ['required', 'integer', 'min:0', 'max:10000000'],

            'room_discount_type'  => ['required', Rule::in([
                RoomPackage::DISCOUNT_NONE,
                RoomPackage::DISCOUNT_PERCENT,
                RoomPackage::DISCOUNT_AMOUNT,
            ])],
            'room_discount_value' => $discountValueRule,

            'room_type_ids'   => ['nullable', 'array'],
            'room_type_ids.*' => ['integer', 'exists:room_types,id'],

            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ], [
            'name.unique' => 'Un pack portant ce nom existe déjà.',
        ]);
    }

    private function attributes(Request $request, array $validated): array
    {
        $discountType  = (string) $validated['room_discount_type'];
        $discountValue = (int) ($validated['room_discount_value'] ?? 0);

        // Un montant se saisit en FCFA et se stocke en centimes ; un
        // pourcentage se stocke tel quel. Sans remise, on remet à zéro pour ne
        // pas laisser traîner une valeur qui ne s'applique plus.
        $roomDiscountValue = match ($discountType) {
            RoomPackage::DISCOUNT_AMOUNT  => $discountValue * 100,
            RoomPackage::DISCOUNT_PERCENT => min(100, $discountValue),
            default                       => 0,
        };

        return [
            'name'        => trim($validated['name']),
            'code'        => !empty($validated['code']) ? trim($validated['code']) : null,
            'description' => $validated['description'] ?? null,

            // On réordonne les repas selon le déroulé de la journée : le
            // libellé affiché reste lisible quel que soit l'ordre de saisie.
            'meals' => array_values(array_intersect(
                array_keys(RestaurantMenuItem::MEAL_SERVICES),
                $validated['meals'] ?? []
            )),
            'service_item_ids' => array_values(array_map('intval', $validated['service_item_ids'] ?? [])),

            'pricing_mode' => $validated['pricing_mode'],
            'price'        => (int) $validated['price'] * 100,

            'room_discount_type'  => $discountType,
            'room_discount_value' => $roomDiscountValue,

            'room_type_ids' => array_values(array_map('intval', $validated['room_type_ids'] ?? [])),
            'sort_order'    => (int) ($validated['sort_order'] ?? 0),
            'is_active'     => $request->boolean('is_active', true),

            'tenant_id' => auth()->user()->tenant_id
                ?? \App\Models\Tenant::current()?->id,
        ];
    }

    private function back(string $message): RedirectResponse
    {
        return redirect()
            ->route('settings.index', ['tab' => 'hebergement'])
            ->with('success', $message);
    }
}
