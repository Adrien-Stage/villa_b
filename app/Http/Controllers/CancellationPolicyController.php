<?php

namespace App\Http\Controllers;

use App\Models\CancellationPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CancellationPolicyController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'                     => ['required', 'string', 'max:50', 'alpha_dash', 'unique:cancellation_policies,code'],
            'name'                     => ['required', 'string', 'max:255'],
            'description'              => ['nullable', 'string', 'max:1000'],
            'penalty_type'             => ['required', Rule::in(array_keys(CancellationPolicy::PENALTY_TYPES))],
            'penalty_value'            => ['nullable', 'numeric', 'min:0'],
            'free_cancel_days_before'  => ['required', 'integer', 'min:0', 'max:365'],
            'free_cancel_time'         => ['required', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
            'is_default'               => ['nullable', 'boolean'],
        ]);

        $penaltyValue = null;
        if ($validated['penalty_type'] === CancellationPolicy::PENALTY_PERCENTAGE) {
            $penaltyValue = min(100, (int) round((float) ($validated['penalty_value'] ?? 0)));
        } elseif ($validated['penalty_type'] === CancellationPolicy::PENALTY_FIXED_AMOUNT) {
            // Saisi en FCFA, stocké en centimes
            $penaltyValue = (int) round(((float) ($validated['penalty_value'] ?? 0)) * 100);
        }

        $isDefault = $request->boolean('is_default');

        DB::transaction(function () use ($validated, $penaltyValue, $isDefault) {
            if ($isDefault) {
                CancellationPolicy::query()->update(['is_default' => false]);
            }

            CancellationPolicy::create([
                'code'                     => strtoupper($validated['code']),
                'name'                     => $validated['name'],
                'description'              => $validated['description'] ?? null,
                'penalty_type'             => $validated['penalty_type'],
                'penalty_value'            => $penaltyValue,
                'free_cancel_days_before'  => (int) $validated['free_cancel_days_before'],
                'free_cancel_time'         => $validated['free_cancel_time'],
                'is_default'               => $isDefault,
                'is_active'                => true,
            ]);
        });

        return redirect()
            ->route('settings.index', ['tab' => 'hebergement'])
            ->with('success', 'La politique d\'annulation a été créée avec succès.');
    }

    public function update(Request $request, CancellationPolicy $cancellationPolicy)
    {
        $validated = $request->validate([
            'code'                     => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('cancellation_policies', 'code')->ignore($cancellationPolicy->id)],
            'name'                     => ['required', 'string', 'max:255'],
            'description'              => ['nullable', 'string', 'max:1000'],
            'penalty_type'             => ['required', Rule::in(array_keys(CancellationPolicy::PENALTY_TYPES))],
            'penalty_value'            => ['nullable', 'numeric', 'min:0'],
            'free_cancel_days_before'  => ['required', 'integer', 'min:0', 'max:365'],
            'free_cancel_time'         => ['required', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
            'is_default'               => ['nullable', 'boolean'],
            'is_active'                => ['nullable', 'boolean'],
        ]);

        $penaltyValue = null;
        if ($validated['penalty_type'] === CancellationPolicy::PENALTY_PERCENTAGE) {
            $penaltyValue = min(100, (int) round((float) ($validated['penalty_value'] ?? 0)));
        } elseif ($validated['penalty_type'] === CancellationPolicy::PENALTY_FIXED_AMOUNT) {
            $penaltyValue = (int) round(((float) ($validated['penalty_value'] ?? 0)) * 100);
        }

        $isDefault = $request->boolean('is_default');
        $isActive  = $request->has('is_active') ? $request->boolean('is_active') : $cancellationPolicy->is_active;

        DB::transaction(function () use ($cancellationPolicy, $validated, $penaltyValue, $isDefault, $isActive) {
            if ($isDefault) {
                CancellationPolicy::where('id', '!=', $cancellationPolicy->id)->update(['is_default' => false]);
            }

            $cancellationPolicy->update([
                'code'                     => strtoupper($validated['code']),
                'name'                     => $validated['name'],
                'description'              => $validated['description'] ?? null,
                'penalty_type'             => $validated['penalty_type'],
                'penalty_value'            => $penaltyValue,
                'free_cancel_days_before'  => (int) $validated['free_cancel_days_before'],
                'free_cancel_time'         => $validated['free_cancel_time'],
                'is_default'               => $isDefault,
                'is_active'                => $isActive,
            ]);
        });

        return redirect()
            ->route('settings.index', ['tab' => 'hebergement'])
            ->with('success', 'La politique d\'annulation a été mise à jour.');
    }

    public function destroy(CancellationPolicy $cancellationPolicy)
    {
        if ($cancellationPolicy->is_default) {
            return back()->withErrors(['policy' => 'Impossible de supprimer la politique d\'annulation par défaut. Définissez une autre politique par défaut au préalable.']);
        }

        // Si la politique est déjà liée à des réservations existantes, on la désactive pour préserver l'historique
        if ($cancellationPolicy->bookings()->exists()) {
            $cancellationPolicy->update(['is_active' => false]);

            return redirect()
                ->route('settings.index', ['tab' => 'hebergement'])
                ->with('success', 'Cette politique étant liée à des réservations existantes, elle a été archivée/désactivée.');
        }

        $cancellationPolicy->delete();

        return redirect()
            ->route('settings.index', ['tab' => 'hebergement'])
            ->with('success', 'La politique d\'annulation a été supprimée.');
    }

    public function setDefault(CancellationPolicy $cancellationPolicy)
    {
        DB::transaction(function () use ($cancellationPolicy) {
            CancellationPolicy::query()->update(['is_default' => false]);
            $cancellationPolicy->update(['is_default' => true, 'is_active' => true]);
        });

        return redirect()
            ->route('settings.index', ['tab' => 'hebergement'])
            ->with('success', "« {$cancellationPolicy->name} » est maintenant la politique d'annulation par défaut de l'établissement.");
    }
}
