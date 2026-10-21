<?php

namespace App\Http\Controllers;

use App\Enums\RoomStatus;
use App\Models\AuditLog;
use App\Models\MinibarArticle;
use App\Models\MinibarCheck;
use App\Models\MinibarMovement;
use App\Models\MinibarRestock;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\ServiceStore;
use App\Models\StockItem;
use App\Services\MinibarService;
use App\Services\PermissionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Mini-bars des chambres, côté housekeeping.
 *
 * Le responsable des étages règle les articles, leurs prix, le dépôt d'où ils
 * sortent et la dotation de chaque type de chambre. L'équipe relève les
 * consommations ; la gouvernante recharge.
 */
class HousekeepingMinibarController extends Controller
{
    public function __construct(private readonly MinibarService $minibars)
    {
    }

    /** État de tous les mini-bars ; leurs réglages sont dans Paramètres › Housekeeping. */
    public function index(): View
    {
        $chambres = Room::query()
            ->with(['roomType', 'minibarLines'])
            ->where('is_active', true)
            ->orderBy('floor')
            ->orderBy('number')
            ->get();

        $derniersReleves = MinibarCheck::query()
            ->with('user')
            ->whereIn('id', MinibarCheck::query()->selectRaw('MAX(id)')->groupBy('room_id'))
            ->get()
            ->keyBy('room_id');

        return view('housekeeping.minibar.index', [
            'chambres'        => $chambres,
            'derniersReleves' => $derniersReleves,
        ]);
    }

    public function depot(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'service_store_id' => ['nullable', 'integer', Rule::exists('service_stores', 'id')->where('is_active', true)],
        ], [
            'service_store_id.exists' => 'Choisissez un dépôt en service.',
        ]);

        $depot = isset($data['service_store_id']) ? ServiceStore::find($data['service_store_id']) : null;
        $this->minibars->choisirDepot($depot);

        AuditLog::record(Auth::id(), 'minibar_depot', 'Dépôt du mini-bar : ' . ($depot?->name ?? 'aucun'), 'housekeeping');

        return back()->with('success', $depot ? "Les mini-bars se rechargent depuis le dépôt {$depot->name}." : 'Dépôt du mini-bar retiré.');
    }

    public function storeArticle(Request $request): RedirectResponse
    {
        // Sans dépôt, rien ne garnirait les mini-bars.
        if (MinibarService::depot() === null) {
            return back()->withInput()->with('error',
                "Choisissez d'abord le dépôt du mini-bar. S'il n'existe pas encore, demandez à l'économe de créer un dépôt « Mini-bar » dans l'Économat.");
        }

        $data = $request->validate([
            'stock_item_id' => ['required', 'integer', Rule::exists('stock_items', 'id'), Rule::unique('minibar_articles', 'stock_item_id')],
            'name'          => ['nullable', 'string', 'max:120'],
            'price'         => ['required', 'integer', 'min:0', 'max:10000000'],
            'sort_order'    => ['nullable', 'integer', 'min:0', 'max:65535'],
        ], [
            'stock_item_id.required' => "Choisissez l'article de l'économat.",
            'stock_item_id.unique'   => 'Cet article est déjà au mini-bar.',
            'price.required'         => 'Indiquez le prix de vente au client.',
        ]);

        $item = StockItem::findOrFail($data['stock_item_id']);
        $article = MinibarArticle::create([
            'stock_item_id' => $item->id,
            'name'          => trim((string) ($data['name'] ?? '')) ?: $item->name,
            'price'         => (int) $data['price'] * 100,
            'sort_order'    => (int) ($data['sort_order'] ?? 0),
            'is_active'     => true,
        ]);

        AuditLog::record(Auth::id(), 'minibar_article', "Article du mini-bar ajouté : {$article->name}", 'housekeeping');

        return back()->with('success', "« {$article->name} » ajouté au mini-bar.");
    }

    public function updateArticle(Request $request, MinibarArticle $article): RedirectResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:120'],
            'price'      => ['required', 'integer', 'min:0', 'max:10000000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active'  => ['nullable', 'boolean'],
        ]);

        $avant = $article->price;
        $article->update([
            'name'       => trim($data['name']),
            'price'      => (int) $data['price'] * 100,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active'  => $request->boolean('is_active'),
        ]);

        if ($avant !== $article->price) {
            AuditLog::record(Auth::id(), 'minibar_article', "Prix de « {$article->name} » : "
                . number_format($avant / 100, 0, ',', ' ') . ' F → ' . number_format($article->price / 100, 0, ',', ' ') . ' F', 'housekeeping');
        }

        return back()->with('success', "« {$article->name} » mis à jour.");
    }

    /** Dotation d'un type de chambre, reportée sur toutes ses chambres. */
    public function updateStandards(Request $request, RoomType $roomType): RedirectResponse
    {
        $data = $request->validate([
            'quantites'   => ['nullable', 'array'],
            'quantites.*' => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);

        try {
            $nombre = $this->minibars->definirDotationDuType($roomType, $data['quantites'] ?? []);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::record(Auth::id(), 'minibar_dotation', "Dotation du mini-bar du type {$roomType->name}", 'housekeeping');

        return back()->with('success', "Dotation du type {$roomType->name} enregistrée et reportée sur {$nombre} chambre" . ($nombre > 1 ? 's' : '') . '.');
    }

    /** Le mini-bar d'une chambre : contenu, relevé, rechargement, historique. */
    public function show(Room $room): View
    {
        $room->load(['roomType', 'minibarLines.article', 'minibarDueBooking']);
        $sejour = MinibarService::sejourEnCours($room);
        $dernierSejour = MinibarService::dernierSejourRecent($room);
        $resolveur = app(PermissionResolver::class);

        $peutRelever = $sejour !== null
            || $room->minibar_due_booking_id !== null
            || (in_array($room->status->value, [RoomStatus::DIRTY->value, RoomStatus::CLEANING->value], true)
                && $dernierSejour !== null
                && $dernierSejour->actual_check_out?->diffInHours(now()) <= 48);

        return view('housekeeping.minibar.room', [
            'room'          => $room,
            'lignes'        => MinibarService::lignesARelever($room),
            'sejour'        => $sejour,
            'releveDu'      => MinibarService::releveDuAuMenage($room),
            'peutRelever'   => $peutRelever,
            'depot'         => MinibarService::depot(),
            'voitMontants'  => $resolveur->allows(Auth::user(), 'housekeeping.minibar.voir'),
            'articles'      => MinibarArticle::query()->active()->ordered()->get(),
            'releves'       => MinibarCheck::query()->with(['user', 'lines'])->where('room_id', $room->id)
                ->latest('checked_at')->latest('id')->take(10)->get(),
            'recharges'     => MinibarRestock::query()->with(['user', 'store'])->where('room_id', $room->id)
                ->latest('restocked_at')->latest('id')->take(10)->get(),
            'defectueux'    => MinibarMovement::query()->with(['article', 'user'])->where('room_id', $room->id)
                ->where('type', MinibarMovement::TYPE_DEFECTIVE)
                ->latest('occurred_at')->latest('id')->take(10)->get(),
            'aControler'    => $room->status === RoomStatus::CLEAN,
        ]);
    }

    /** Dotation propre à la chambre. */
    public function updateRoom(Request $request, Room $room): RedirectResponse
    {
        $data = $request->validate([
            'quantites'   => ['nullable', 'array'],
            'quantites.*' => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);

        try {
            $this->minibars->definirDotationDeLaChambre($room, $data['quantites'] ?? []);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::record(Auth::id(), 'minibar_dotation', "Dotation du mini-bar de la chambre {$room->number}", 'housekeeping');

        return back()->with('success', "Dotation du mini-bar de la chambre {$room->number} enregistrée.");
    }

    /** Relevé des consommations : chaque article, 0 compris. */
    public function check(Request $request, Room $room): RedirectResponse
    {
        $data = $request->validate([
            'consommes'   => ['required', 'array'],
            'consommes.*' => ['nullable', 'integer', 'min:0', 'max:999'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ], [
            'consommes.required' => "Indiquez la consommation de chaque article, 0 si rien n'a été pris.",
        ]);

        try {
            $releve = $this->minibars->relever($room, $data['consommes'], $data['notes'] ?? null);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $message = "Relevé du mini-bar de la chambre {$room->number} enregistré : {$releve->resume()}.";
        if ($releve->billing === MinibarCheck::BILLING_A_FACTURER) {
            $message .= ' Le client est parti : la consommation lui est facturée à part et la réception est prévenue.';
        }

        return redirect()->route('housekeeping.index')->with('success', $message);
    }

    /** Remise à la dotation depuis le dépôt du mini-bar. */
    public function restock(Room $room): RedirectResponse
    {
        try {
            [, $manquants] = $this->minibars->recharger($room);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($manquants === [] ? 'success' : 'warning',
            "Mini-bar de la chambre {$room->number} rechargé."
            . ($manquants !== [] ? ' Le dépôt n\'avait pas tout : il manque encore ' . implode(', ', $manquants) . '.' : ''));
    }

    /** Signaler un produit défectueux ou avarié dans le mini-bar. */
    public function reportDefective(Request $request, Room $room): RedirectResponse
    {
        $data = $request->validate([
            'minibar_article_id' => ['required', 'integer', Rule::exists('minibar_articles', 'id')],
            'quantity'           => ['required', 'integer', 'min:1', 'max:99'],
            'reason'             => ['required', 'string', 'max:100'],
            'notes'              => ['nullable', 'string', 'max:500'],
        ], [
            'minibar_article_id.required' => "Choisissez l'article concerné.",
            'quantity.required'           => 'Indiquez la quantité défectueuse.',
            'quantity.min'                => 'La quantité doit être au moins de 1.',
            'reason.required'             => 'Indiquez le motif (ex: Périmé, Cassé, Descellé...).',
        ]);

        try {
            $mouvement = $this->minibars->declarerDefectueux(
                $room,
                (int) $data['minibar_article_id'],
                (int) $data['quantity'],
                $data['reason'],
                $data['notes'] ?? null,
                Auth::user()
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        AuditLog::record(
            Auth::id(),
            'minibar_defective',
            "Produit défectueux dans Ch. {$room->number} : {$data['quantity']} × {$mouvement->article?->name} ({$data['reason']})",
            'housekeeping'
        );

        return back()->with(
            'success',
            "{$data['quantity']} × {$mouvement->article?->name} retiré(s) du mini-bar de la chambre {$room->number}. Aucune facturation client. La gouvernante a été notifiée pour le remplacement."
        );
    }
}
