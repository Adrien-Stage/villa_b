<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PointOfSale;
use App\Models\RestaurantBanquet;
use App\Models\RestaurantBanquetPayment;
use App\Models\Space;
use App\Services\CashRegisterCircuit;
use App\Services\RestaurantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Les banquets : des événements réservés dans l'un ou l'autre restaurant.
 *
 * Le responsable de restaurant établit le devis — date, salle, client,
 * couverts, menu, acompte demandé —, le confirme quand l'acompte est
 * encaissé, puis le marque réalisé. La caisse du restaurant encaisse
 * l'acompte et le solde ; le banquet est soldé quand tout est payé.
 */
class RestaurantBanquetController extends Controller
{
    private const PAYMENT_METHODS = ['cash', 'mobile_money', 'card', 'transfer', 'other'];

    public function __construct(
        private readonly RestaurantContext $contexte,
        private readonly CashRegisterCircuit $circuit,
    ) {}

    public function index(Request $request): View
    {
        $user = Auth::user();

        $requete = RestaurantBanquet::query()
            ->visiblesPour($user)
            ->with(['pointOfSale:id,name', 'space:id,name'])
            ->withSum('payments as total_encaisse', 'amount');

        $vue = $request->query('vue', 'a_venir');
        match ($vue) {
            'passes' => $requete->whereDate('event_date', '<', today())->orderByDesc('event_date'),
            'tous' => $requete->orderByDesc('event_date'),
            default => $requete->whereDate('event_date', '>=', today())->where('status', '!=', RestaurantBanquet::ANNULE)->orderBy('event_date'),
        };

        $restaurants = $this->contexte->accessibles($user);

        return view('restaurant.banquets.index', [
            'banquets' => $requete->paginate(20)->withQueryString(),
            'vue' => $vue,
            'restaurants' => $restaurants,
            'restaurantParDefaut' => $this->contexte->pourCreation($user),
            'salles' => $this->salles($restaurants->pluck('id')->all()),
            'vueEnsemble' => $this->contexte->vueEnsemble($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $valide = $this->valider($request);
        $restaurant = $this->restaurantAccessible((int) $valide['point_of_sale_id']);
        $this->verifierSalle($valide, $restaurant);

        $banquet = DB::transaction(fn () => RestaurantBanquet::create($this->attributs($valide, $restaurant) + [
            'reference' => $this->reference(),
            'status' => RestaurantBanquet::DEVIS,
            'created_by' => Auth::id(),
        ]));

        AuditLog::record(Auth::id(), 'banquet_created', "Banquet {$banquet->reference} — {$banquet->title} ({$restaurant->name}, {$banquet->event_date->format('d/m/Y')})", 'restaurant', [
            'banquet_id' => $banquet->id,
        ]);

        return redirect()->route('restaurant.banquets.show', $banquet)->with('success', "Devis {$banquet->reference} enregistré.");
    }

    public function show(RestaurantBanquet $banquet): View
    {
        $banquet->load(['pointOfSale', 'space', 'createdBy:id,name', 'payments' => fn ($q) => $q->with('paidBy:id,name')->latest('paid_at')]);
        $restaurants = $this->contexte->accessibles(Auth::user());

        return view('restaurant.banquets.show', [
            'banquet' => $banquet,
            'encaisse' => $banquet->encaisse(),
            'resteDu' => $banquet->resteDu(),
            'paymentMethods' => self::PAYMENT_METHODS,
            'caisse' => $this->circuit->ouverte(Auth::user(), 'restaurant'),
            'restaurants' => $restaurants,
            'salles' => $this->salles($restaurants->pluck('id')->all()),
        ]);
    }

    public function update(Request $request, RestaurantBanquet $banquet): RedirectResponse
    {
        if (! $banquet->estModifiable()) {
            return back()->withErrors(['banquet' => 'Un banquet réalisé, soldé ou annulé ne se modifie plus.']);
        }

        $valide = $this->valider($request);
        $restaurant = $this->restaurantAccessible((int) $valide['point_of_sale_id']);
        $this->verifierSalle($valide, $restaurant, $banquet);

        $attributs = $this->attributs($valide, $restaurant);

        if ($attributs['total_amount'] < $banquet->encaisse()) {
            return back()->withInput()->withErrors(['covers' => 'Le nouveau total serait inférieur à ce qui est déjà encaissé.']);
        }

        $banquet->update($attributs);

        return redirect()->route('restaurant.banquets.show', $banquet)->with('success', 'Banquet mis à jour.');
    }

    /** Confirmer, marquer réalisé, annuler. */
    public function status(Request $request, RestaurantBanquet $banquet): RedirectResponse
    {
        $valide = $request->validate([
            'status' => ['required', Rule::in([RestaurantBanquet::CONFIRME, RestaurantBanquet::REALISE, RestaurantBanquet::ANNULE])],
        ]);

        $cible = $valide['status'];
        $permis = [
            RestaurantBanquet::CONFIRME => [RestaurantBanquet::DEVIS],
            RestaurantBanquet::REALISE => [RestaurantBanquet::CONFIRME],
            RestaurantBanquet::ANNULE => [RestaurantBanquet::DEVIS, RestaurantBanquet::CONFIRME],
        ];

        if (! in_array($banquet->status, $permis[$cible], true)) {
            return back()->withErrors(['status' => "Un banquet « {$banquet->libelleStatut()} » ne peut pas passer à « ".RestaurantBanquet::STATUTS[$cible].' ».']);
        }

        // La réservation tient quand l'acompte demandé est encaissé.
        if ($cible === RestaurantBanquet::CONFIRME && $banquet->encaisse() < $banquet->deposit_required) {
            return back()->withErrors(['status' => 'L\'acompte demandé n\'est pas encore encaissé : la réservation ne peut pas être confirmée.']);
        }

        $attributs = ['status' => $cible];
        if ($cible === RestaurantBanquet::CONFIRME) {
            $attributs['confirmed_at'] = now();
        }
        if ($cible === RestaurantBanquet::ANNULE) {
            $attributs['canceled_at'] = now();
        }
        // Réalisé et déjà tout payé : il est soldé.
        if ($cible === RestaurantBanquet::REALISE && $banquet->resteDu() === 0) {
            $attributs['status'] = RestaurantBanquet::SOLDE;
        }

        $banquet->update($attributs);

        AuditLog::record(Auth::id(), 'banquet_status', "Banquet {$banquet->reference} : {$banquet->libelleStatut()}", 'restaurant', [
            'banquet_id' => $banquet->id,
            'status' => $banquet->status,
        ]);

        return redirect()->route('restaurant.banquets.show', $banquet)->with('success', "Banquet {$banquet->libelleStatut()}.");
    }

    /** Acompte ou solde, encaissé à la caisse du restaurant du banquet. */
    public function storePayment(Request $request, RestaurantBanquet $banquet): RedirectResponse
    {
        if ($banquet->status === RestaurantBanquet::ANNULE || $banquet->status === RestaurantBanquet::SOLDE) {
            return back()->withErrors(['payment' => 'Ce banquet n\'attend plus de règlement.']);
        }

        $valide = $request->validate([
            // Saisi en FCFA, stocké en centimes.
            'amount' => ['required', 'integer', 'min:1', 'max:2000000000'],
            'payment_method' => ['required', Rule::in(self::PAYMENT_METHODS)],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $montant = (int) $valide['amount'] * 100;

        if ($montant > $banquet->resteDu()) {
            return back()->withInput()->withErrors(['amount' => 'Le montant dépasse ce qui reste dû ('.number_format($banquet->resteDu() / 100, 0, ',', ' ').' FCFA).']);
        }

        $session = $this->circuit->ouverte(Auth::user(), 'restaurant');

        if (! $session) {
            return back()->withInput()->withErrors(['cash_register' => 'Ouvrez votre caisse avant d\'encaisser.']);
        }

        if ($session->point_of_sale_id && $session->point_of_sale_id !== $banquet->point_of_sale_id) {
            return back()->withInput()->withErrors(['cash_register' => 'Ce banquet est celui d\'un autre restaurant : il s\'encaisse à la caisse de ce restaurant.']);
        }

        RestaurantBanquetPayment::create([
            'restaurant_banquet_id' => $banquet->id,
            // Avant l'événement, c'est un acompte ; après, le solde.
            'kind' => $banquet->status === RestaurantBanquet::REALISE ? RestaurantBanquetPayment::SOLDE : RestaurantBanquetPayment::ACOMPTE,
            'amount' => $montant,
            'payment_method' => $valide['payment_method'],
            'cash_register_session_id' => $session->id,
            'paid_by' => Auth::id(),
            'paid_at' => now(),
            'notes' => $valide['notes'] ?? null,
        ]);

        if ($banquet->status === RestaurantBanquet::REALISE && $banquet->resteDu() === 0) {
            $banquet->update(['status' => RestaurantBanquet::SOLDE]);
        }

        AuditLog::record(Auth::id(), 'banquet_payment', sprintf('Règlement de %s FCFA pour le banquet %s (%s)',
            number_format($montant / 100, 0, ',', ' '), $banquet->reference, $valide['payment_method']), 'restaurant', [
                'banquet_id' => $banquet->id,
                'amount' => $montant,
            ]);

        return redirect()->route('restaurant.banquets.show', $banquet)->with('success', 'Règlement enregistré.');
    }

    /** @return array<string, mixed> */
    private function valider(Request $request): array
    {
        return $request->validate([
            'point_of_sale_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:40'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'event_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'space_id' => ['nullable', 'integer'],
            'covers' => ['required', 'integer', 'min:1', 'max:100000'],
            // Saisis en FCFA, stockés en centimes.
            'price_per_cover' => ['required', 'integer', 'min:0', 'max:5000000'],
            'extras_amount' => ['nullable', 'integer', 'min:0', 'max:2000000000'],
            'deposit_required' => ['nullable', 'integer', 'min:0', 'max:2000000000'],
            'menu' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'end_time.after' => 'L\'heure de fin doit suivre l\'heure de début.',
        ]);
    }

    /** @return array<string, mixed> */
    private function attributs(array $valide, PointOfSale $restaurant): array
    {
        $prix = (int) $valide['price_per_cover'] * 100;
        $supplements = (int) ($valide['extras_amount'] ?? 0) * 100;
        $total = RestaurantBanquet::totalPour((int) $valide['covers'], $prix, $supplements);

        return [
            'point_of_sale_id' => $restaurant->id,
            'title' => trim($valide['title']),
            'client_name' => trim($valide['client_name']),
            'client_phone' => $valide['client_phone'] ?? null,
            'client_email' => $valide['client_email'] ?? null,
            'event_date' => $valide['event_date'],
            'start_time' => $valide['start_time'] ?? null,
            'end_time' => $valide['end_time'] ?? null,
            'space_id' => $valide['space_id'] ?? null,
            'covers' => (int) $valide['covers'],
            'price_per_cover' => $prix,
            'extras_amount' => $supplements,
            'total_amount' => $total,
            'deposit_required' => min($total, (int) ($valide['deposit_required'] ?? 0) * 100),
            'menu' => $valide['menu'] ?? null,
            'notes' => $valide['notes'] ?? null,
        ];
    }

    /** Le banquet se tient dans un restaurant que la personne voit. */
    private function restaurantAccessible(int $id): PointOfSale
    {
        return $this->contexte->accessibles(Auth::user())->firstWhere('id', $id)
            ?? throw ValidationException::withMessages(['point_of_sale_id' => 'Choisissez un restaurant où vous travaillez.']);
    }

    /**
     * La salle est celle du restaurant, ou une salle polyvalente ; et elle
     * n'est pas déjà retenue ce jour-là pour un autre banquet.
     */
    private function verifierSalle(array $valide, PointOfSale $restaurant, ?RestaurantBanquet $banquet = null): void
    {
        if (empty($valide['space_id'])) {
            return;
        }

        $salle = Space::query()->active()->whereKey((int) $valide['space_id'])
            ->where(fn (Builder $q) => $q->where('point_of_sale_id', $restaurant->id)->orWhereNull('point_of_sale_id'))
            ->first();

        if (! $salle) {
            throw ValidationException::withMessages(['space_id' => "Cette salle n'appartient pas à {$restaurant->name}."]);
        }

        $autres = RestaurantBanquet::query()
            ->where('space_id', $salle->id)
            ->whereDate('event_date', $valide['event_date'])
            ->where('status', '!=', RestaurantBanquet::ANNULE)
            ->when($banquet, fn ($q) => $q->whereKeyNot($banquet->id))
            ->get();

        foreach ($autres as $autre) {
            // Deux banquets dans la même salle le même jour ne se tiennent
            // que s'ils ne se chevauchent pas.
            $chevauche = ! ($valide['start_time'] ?? null) || ! ($valide['end_time'] ?? null) || ! $autre->start_time || ! $autre->end_time
                || ($valide['start_time'] < $autre->end_time && $autre->start_time < $valide['end_time']);

            if ($chevauche) {
                throw ValidationException::withMessages(['space_id' => "La salle {$salle->name} est déjà retenue ce jour-là ({$autre->reference} — {$autre->title})."]);
            }
        }
    }

    /** @param list<int> $restaurants @return \Illuminate\Support\Collection<int, Space> */
    private function salles(array $restaurants)
    {
        return Space::query()->active()
            ->where(fn (Builder $q) => $q->whereIn('point_of_sale_id', $restaurants)->orWhereNull('point_of_sale_id'))
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'capacity', 'point_of_sale_id']);
    }

    /** BQT-2026-001 : une série par année. */
    private function reference(): string
    {
        $annee = now()->year;
        $prefixe = "BQT-{$annee}-";
        $n = RestaurantBanquet::query()->where('reference', 'like', $prefixe.'%')->count() + 1;

        while (RestaurantBanquet::query()->where('reference', $prefixe.str_pad((string) $n, 3, '0', STR_PAD_LEFT))->exists()) {
            $n++;
        }

        return $prefixe.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }
}
