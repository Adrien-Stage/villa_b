<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ServiceStore;
use App\Models\StockItem;
use App\Models\StockTransfer;
use App\Services\StockTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

/**
 * Approvisionner un dépôt de l'économat par un bon de transfert : la
 * marchandise quitte le magasin central et entre au dépôt, au coût moyen,
 * sans demande d'un service.
 */
class StockTransferController extends Controller
{
    public function __construct(private StockTransferService $service)
    {
    }

    public function create(ServiceStore $store): View|RedirectResponse
    {
        if (! StockTransferService::peutRecevoir($store)) {
            return redirect()->route('economat.stores.show', $store)
                ->with('error', "Le dépôt « {$store->name} » n'appartient pas à l'économat : il se sert par une demande de son service.");
        }

        return view('economat.transfers.create', [
            'depot'    => $store,
            'articles' => StockItem::active()->with('packagings')->where('current_stock', '>', 0)->orderBy('name')
                ->get(['id', 'name', 'reference', 'unit', 'current_stock', 'average_cost']),
        ]);
    }

    public function store(Request $request, ServiceStore $store): RedirectResponse
    {
        $data = $request->validate([
            'notes'                 => ['nullable', 'string', 'max:1000'],
            'lines'                 => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.stock_item_id' => ['required', 'integer', 'exists:stock_items,id'],
            'lines.*.quantity'      => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.packaging'     => ['nullable', 'string', 'max:40'],
        ], [
            'lines.required'      => 'Ajoutez au moins un article.',
            'lines.*.quantity.gt' => 'La quantité doit être positive.',
        ]);

        try {
            $transfert = $this->service->transferer($store, $data, Auth::user());
        } catch (RuntimeException|\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        AuditLog::record(Auth::id(), 'transfert_depot', "Transfert {$transfert->number} vers le dépôt {$store->name} — "
            . number_format($transfert->total_value / 100, 0, ',', ' ') . ' FCFA', 'economat',
            ['stock_transfer_id' => $transfert->id, 'service_store_id' => $store->id]);

        return redirect()->route('economat.transfers.show', $transfert)
            ->with('success', "Bon de transfert {$transfert->number} enregistré : la marchandise est entrée au dépôt {$store->name}.");
    }

    public function show(StockTransfer $transfert): View
    {
        return view('economat.transfers.show', [
            'transfert' => $transfert->load(['lines.item.packagings', 'depot', 'transferredBy', 'cancelledBy']),
        ]);
    }

    /** Le bon de transfert imprimable, à signer par qui sort et qui reçoit. */
    public function print(StockTransfer $transfert): View
    {
        return view('economat.transfers.print', [
            'transfert' => $transfert->load(['lines.item.packagings', 'depot', 'transferredBy', 'cancelledBy']),
            'tenant'    => \App\Models\Tenant::first(),
        ]);
    }

    public function cancel(Request $request, StockTransfer $transfert): RedirectResponse
    {
        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:500'],
        ], [
            'cancellation_reason.required' => 'Donnez le motif de l\'annulation.',
        ]);

        try {
            $transfert = $this->service->annuler($transfert, Auth::user(), trim($data['cancellation_reason']));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::record(Auth::id(), 'transfert_depot', "Annulation du transfert {$transfert->number} : {$transfert->cancellation_reason}",
            'economat', ['stock_transfer_id' => $transfert->id]);

        return back()->with('success', "Bon {$transfert->number} annulé : la marchandise est revenue au magasin central.");
    }
}
