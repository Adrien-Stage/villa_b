<?php

namespace App\Http\Controllers;

use App\Models\PartnerOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestion des organisations partenaires et des privilèges qui leur sont
 * accordés. Alimente le rattachement d'un client à une organisation et
 * l'application automatique des remises lors d'une réservation.
 */
class PartnerOrganizationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        PartnerOrganization::create($this->attributes($request, $validated));

        return $this->back('Organisation partenaire créée.');
    }

    public function update(Request $request, PartnerOrganization $partnerOrganization): RedirectResponse
    {
        $validated = $this->validated($request, $partnerOrganization);

        $partnerOrganization->update($this->attributes($request, $validated));

        return $this->back('Organisation partenaire mise à jour.');
    }

    public function destroy(PartnerOrganization $partnerOrganization): RedirectResponse
    {
        // Les séjours et clients rattachés conservent leur historique : la
        // contrainte est en nullOnDelete. On prévient tout de même quand des
        // clients y sont encore rattachés, pour éviter une suppression subie.
        $attached = $partnerOrganization->customers()->count();

        $partnerOrganization->delete();

        return $this->back($attached > 0
            ? "Organisation supprimée. {$attached} client(s) ne sont plus rattachés à aucune organisation."
            : 'Organisation supprimée.');
    }

    private function validated(Request $request, ?PartnerOrganization $organization = null): array
    {
        // Le sens de room_discount_value dépend du type choisi : un pourcentage
        // est borné à 100, un montant négocié ne l'est pas.
        $discountValueRule = $request->input('room_discount_type') === PartnerOrganization::DISCOUNT_PERCENT
            ? ['nullable', 'integer', 'min:0', 'max:100']
            : ['nullable', 'integer', 'min:0', 'max:10000000'];

        return $request->validate([
            'name' => [
                'required', 'string', 'max:160',
                Rule::unique('partner_organizations', 'name')->ignore($organization?->id),
            ],
            'code'          => ['nullable', 'string', 'max:30'],
            'type'          => ['required', Rule::in(array_keys(PartnerOrganization::TYPES))],
            'contact_name'  => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:30'],

            'valid_from'  => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],

            'room_discount_type'  => ['required', Rule::in([
                PartnerOrganization::DISCOUNT_NONE,
                PartnerOrganization::DISCOUNT_PERCENT,
                PartnerOrganization::DISCOUNT_AMOUNT,
            ])],
            'room_discount_value' => $discountValueRule,

            'restaurant_discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'shop_discount_percent'       => ['nullable', 'integer', 'min:0', 'max:100'],

            'free_service_item_ids'   => ['nullable', 'array'],
            'free_service_item_ids.*' => ['integer', 'exists:service_items,id'],

            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.unique'             => 'Une organisation portant ce nom existe déjà.',
            'valid_until.after_or_equal' => 'La date de fin de convention ne peut pas précéder la date de début.',
        ]);
    }

    private function attributes(Request $request, array $validated): array
    {
        $type  = (string) $validated['room_discount_type'];
        $value = (int) ($validated['room_discount_value'] ?? 0);

        // Un montant est saisi en FCFA et stocké en centimes ; un pourcentage
        // se stocke tel quel. Si aucune remise, on remet la valeur à zéro pour
        // ne pas laisser traîner un montant qui ne s'applique plus.
        $roomDiscountValue = match ($type) {
            PartnerOrganization::DISCOUNT_AMOUNT  => $value * 100,
            PartnerOrganization::DISCOUNT_PERCENT => min(100, $value),
            default                               => 0,
        };

        return [
            'name'          => trim($validated['name']),
            // Un champ nullable absent de la requête ne figure pas dans les
            // données validées : on ne peut donc pas y accéder directement.
            'code'          => !empty($validated['code']) ? trim($validated['code']) : null,
            'type'          => $validated['type'],
            'contact_name'  => $validated['contact_name'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'contact_phone' => $validated['contact_phone'] ?? null,

            'valid_from'  => $validated['valid_from'] ?? null,
            'valid_until' => $validated['valid_until'] ?? null,
            'is_active'   => $request->boolean('is_active', true),

            'room_discount_type'  => $type,
            'room_discount_value' => $roomDiscountValue,

            'restaurant_discount_percent' => (int) ($validated['restaurant_discount_percent'] ?? 0),
            'shop_discount_percent'       => (int) ($validated['shop_discount_percent'] ?? 0),

            'free_service_item_ids' => array_values(array_map(
                'intval',
                $validated['free_service_item_ids'] ?? []
            )),

            'late_checkout' => $request->boolean('late_checkout'),
            'early_checkin' => $request->boolean('early_checkin'),

            'notes'     => $validated['notes'] ?? null,
            'tenant_id' => auth()->user()->tenant_id
                ?? \App\Models\Tenant::current()?->id,
        ];
    }

    private function back(string $message): RedirectResponse
    {
        return redirect()
            ->route('settings.index', ['tab' => 'partners'])
            ->with('success', $message);
    }
}
