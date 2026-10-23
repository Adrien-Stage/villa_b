<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Services\PermissionResolver;
use App\Services\TarifSejour;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Hôtel › Tarifs : la grille tarifaire des chambres. Le prix de base de chaque
 * type, ses tarifs par critère (événement, saison, jours de la semaine, long
 * séjour, dernière minute) et une simulation de séjour qui montre le prix de
 * chaque nuit, tel que la réservation le reprendra (TarifSejour).
 *
 * Le responsable financier fixe la grille, avec la direction et le chef de
 * réception ; la réception la consulte.
 */
class RoomRateController extends Controller
{
    public function __construct(private readonly TarifSejour $tarifs)
    {
    }

    public function index(Request $request): View
    {
        $types = RoomType::query()->with(['rates' => fn ($q) => $q->orderByDesc('priority')->orderBy('name')])
            ->orderBy('name')->get();

        $simulation = null;
        if ($request->filled(['type', 'arrivee', 'depart'])) {
            $donnees = $request->validate([
                'type' => ['required', 'integer', Rule::exists('room_types', 'id')],
                'arrivee' => ['required', 'date'],
                'depart' => ['required', 'date', 'after:arrivee', 'before_or_equal:' . CarbonImmutable::parse($request->input('arrivee'))->addDays(90)->toDateString()],
                'adultes' => ['nullable', 'integer', 'min:1', 'max:20'],
                'enfants' => ['nullable', 'integer', 'min:0', 'max:20'],
            ], [
                'depart.after' => 'Le départ doit suivre l’arrivée.',
                'depart.before_or_equal' => 'La simulation couvre 90 nuits au plus.',
            ]);
            $type = $types->firstWhere('id', (int) $donnees['type']);
            $calcul = $this->tarifs->calculer(
                $type,
                CarbonImmutable::parse($donnees['arrivee']),
                CarbonImmutable::parse($donnees['depart']),
                (int) ($donnees['adultes'] ?? 1),
                (int) ($donnees['enfants'] ?? 0),
            );
            $simulation = $calcul + ['type' => $type, 'resume' => $this->tarifs->resume($calcul)];
        }

        $droits = app(PermissionResolver::class);
        $user = Auth::user();

        return view('rooms.rates.index', [
            'types' => $types,
            'simulation' => $simulation,
            'peutCreer' => $droits->allows($user, 'rooms.rates.creer'),
            'peutModifier' => $droits->allows($user, 'rooms.rates.modifier'),
            'peutSupprimer' => $droits->allows($user, 'rooms.rates.supprimer'),
            'peutRegleBase' => $droits->allows($user, 'rooms.rates.types.modifier'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $donnees = $this->valider($request);
        $tarif = RoomRate::create($donnees);

        AuditLog::record(Auth::id(), 'room_rate', "Tarif « {$tarif->name} » créé pour {$tarif->roomType->name} ({$tarif->typeLabel()}, {$tarif->ajustementLabel()})", 'rooms', ['room_rate_id' => $tarif->id]);

        return redirect()->route('rooms.rates.index')->with('success', "Tarif « {$tarif->name} » ajouté à la grille.");
    }

    public function update(Request $request, RoomRate $rate): RedirectResponse
    {
        $avant = $rate->ajustementLabel();
        $rate->update($this->valider($request, $rate));

        AuditLog::record(Auth::id(), 'room_rate', "Tarif « {$rate->name} » de {$rate->roomType->name} modifié ({$avant} → {$rate->ajustementLabel()})", 'rooms', ['room_rate_id' => $rate->id]);

        return redirect()->route('rooms.rates.index')->with('success', "Tarif « {$rate->name} » enregistré.");
    }

    public function destroy(RoomRate $rate): RedirectResponse
    {
        $nom = $rate->name;
        $type = $rate->roomType->name;
        $rate->delete();

        AuditLog::record(Auth::id(), 'room_rate', "Tarif « {$nom} » de {$type} supprimé", 'rooms', ['room_rate_id' => $rate->id]);

        return redirect()->route('rooms.rates.index')->with('success', "Tarif « {$nom} » retiré de la grille.");
    }

    /** Le prix de base d'un type de chambre, sans le reste de sa fiche. */
    public function updateType(Request $request, RoomType $roomType): RedirectResponse
    {
        $donnees = $request->validate([
            'base_price' => ['required', 'integer', 'min:0', 'max:100000000'],
        ], [
            'base_price.required' => 'Le prix de base est obligatoire.',
        ]);

        $avant = (int) $roomType->base_price;
        $roomType->update(['base_price' => (int) $donnees['base_price'] * 100]);

        AuditLog::record(Auth::id(), 'room_rate', "Prix de base de {$roomType->name} : "
            . number_format($avant / 100, 0, ',', ' ') . ' → ' . number_format($roomType->base_price / 100, 0, ',', ' ') . ' FCFA', 'rooms',
            ['room_type_id' => $roomType->id]);

        return redirect()->route('rooms.rates.index')->with('success', "Prix de base de {$roomType->name} enregistré.");
    }

    /** @return array<string, mixed> champs prêts pour RoomRate (montants en centimes) */
    private function valider(Request $request, ?RoomRate $tarif = null): array
    {
        $donnees = $request->validate([
            'room_type_id' => ['required', 'integer', Rule::exists('room_types', 'id')],
            'name' => ['required', 'string', 'max:120'],
            'rate_type' => ['required', Rule::in(array_keys(RoomRate::TYPES))],
            'start_date' => ['nullable', 'date', Rule::requiredIf(in_array($request->input('rate_type'), [RoomRate::TYPE_SEASONAL, RoomRate::TYPE_EVENT], true))],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', Rule::requiredIf(in_array($request->input('rate_type'), [RoomRate::TYPE_SEASONAL, RoomRate::TYPE_EVENT], true))],
            'days_of_week' => ['nullable', 'array', Rule::requiredIf($request->input('rate_type') === RoomRate::TYPE_WEEKLY)],
            'days_of_week.*' => ['integer', 'between:0,6'],
            'price_adjustment_type' => ['required', Rule::in(array_keys(RoomRate::AJUSTEMENTS))],
            'price_adjustment' => ['required', 'integer', 'min:-100', 'max:100000000'],
            'min_nights' => ['nullable', 'integer', 'min:1', 'max:365', Rule::requiredIf($request->input('rate_type') === RoomRate::TYPE_LONG_STAY)],
            'max_nights' => ['nullable', 'integer', 'min:1', 'max:365', 'gte:min_nights'],
            'min_advance_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'max_advance_days' => ['nullable', 'integer', 'min:0', 'max:365', Rule::requiredIf($request->input('rate_type') === RoomRate::TYPE_LAST_MINUTE)],
            'event_name' => ['nullable', 'string', 'max:120', Rule::requiredIf($request->input('rate_type') === RoomRate::TYPE_EVENT)],
            'priority' => ['nullable', 'integer', 'min:1', 'max:99'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'start_date.required' => 'Une saison ou un événement a une date de début.',
            'end_date.required' => 'Une saison ou un événement a une date de fin.',
            'end_date.after_or_equal' => 'La fin doit suivre le début.',
            'days_of_week.required' => 'Cochez les jours concernés (par exemple vendredi et samedi).',
            'min_nights.required' => 'Un tarif long séjour part d’un nombre de nuits.',
            'max_nights.gte' => 'Le maximum de nuits ne peut pas être inférieur au minimum.',
            'max_advance_days.required' => 'Un tarif dernière minute fixe le délai maximal entre réservation et arrivée.',
            'event_name.required' => 'Nommez l’événement (FESPAM, salon…).',
        ]);

        $type = $donnees['price_adjustment_type'];
        $valeur = (int) $donnees['price_adjustment'];
        if ($type === RoomRate::AJUSTEMENT_POURCENTAGE && ($valeur < -100 || $valeur > 500)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['price_adjustment' => 'Un pourcentage va de −100 % à +500 %.']);
        }
        if ($type !== RoomRate::AJUSTEMENT_POURCENTAGE && $valeur < 0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['price_adjustment' => 'Saisissez un montant positif : la remise en montant se déduit d’elle-même.']);
        }

        return [
            'room_type_id' => (int) $donnees['room_type_id'],
            'name' => trim($donnees['name']),
            'rate_type' => $donnees['rate_type'],
            'start_date' => $donnees['start_date'] ?? null,
            'end_date' => $donnees['end_date'] ?? null,
            'days_of_week' => isset($donnees['days_of_week']) && $donnees['days_of_week'] !== []
                ? array_values(array_unique(array_map('intval', $donnees['days_of_week'])))
                : null,
            'price_adjustment_type' => $type,
            // Montants saisis en FCFA, gardés en centimes ; un pourcentage reste tel quel.
            'price_adjustment' => $type === RoomRate::AJUSTEMENT_POURCENTAGE ? $valeur : $valeur * 100,
            'min_nights' => $donnees['min_nights'] ?? null,
            'max_nights' => $donnees['max_nights'] ?? null,
            'min_advance_days' => $donnees['min_advance_days'] ?? null,
            'max_advance_days' => $donnees['max_advance_days'] ?? null,
            'event_name' => isset($donnees['event_name']) ? trim($donnees['event_name']) : null,
            'priority' => (int) ($donnees['priority'] ?? ($tarif?->priority ?? 1)),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
