<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\PartnerOrganization;
use App\Models\Payment;
use App\Models\WalletEntry;
use App\Services\PartnerWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PartnerWalletController extends Controller
{
    public const METHODS = PartnerWalletService::METHODS;

    public function index(Request $request)
    {
        $partners = PartnerOrganization::with('wallet')->withCount('customers')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.trim($request->string('q')).'%'))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('partners.wallets.index', compact('partners'));
    }

    public function show(PartnerOrganization $partner)
    {
        $partner->load('wallet');
        $entries = WalletEntry::where('wallet_id', $partner->wallet?->id ?? 0)
            ->with(['createdBy', 'reversal', 'attachment'])->latest('id')->paginate(30);
        $payments = Payment::whereIn('wallet_entry_id', $entries->pluck('id'))->with(['booking', 'invoice'])->get()->keyBy('wallet_entry_id');
        $methods = self::METHODS;

        return view('partners.wallets.show', compact('partner', 'entries', 'payments', 'methods'));
    }

    public function credit(Request $request, PartnerOrganization $partner, PartnerWalletService $service)
    {
        $data = $request->validate($this->commonRules() + [
            'method' => ['required', Rule::in(array_keys(self::METHODS))],
            'reference' => ['required', 'string', 'max:200', 'regex:/\S/'],
        ]);

        return $this->execute(fn () => $service->topup($partner, $data, $request->user(), $request->file('proof')), $partner);
    }

    public function debit(Request $request, PartnerOrganization $partner, PartnerWalletService $service)
    {
        $data = $request->validate($this->commonRules() + [
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'authorized_by' => ['required', 'string', 'max:150', 'regex:/\S/'],
            'authorization_reference' => ['required', 'string', 'max:200', 'regex:/\S/'],
        ]);
        $booking = Booking::findOrFail($data['booking_id']);
        $invoice = isset($data['invoice_id']) ? Invoice::findOrFail($data['invoice_id']) : null;

        return $this->execute(fn () => $service->settle($partner, $booking, $invoice, $data, $request->user(), $request->file('proof')), $partner);
    }

    public function reverse(Request $request, PartnerOrganization $partner, WalletEntry $entry, PartnerWalletService $service)
    {
        $data = $request->validate(['request_key' => ['required', 'uuid'], 'reason' => ['required', 'string', 'max:500', 'regex:/\S/']]);

        return $this->execute(fn () => $service->reverse($partner, $entry, $data, $request->user()), $partner);
    }

    public function proof(PartnerOrganization $partner, WalletEntry $entry)
    {
        abort_unless($entry->wallet?->owner_type === $partner->getMorphClass() && $entry->wallet->owner_id === $partner->id, 404);
        $attachment = $entry->attachment;
        abort_unless($attachment && Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    private function commonRules(): array
    {
        return ['request_key' => ['required', 'uuid'], 'amount' => ['required', 'integer', 'min:1', 'max:1000000000000'],
            'notes' => ['nullable', 'string', 'max:1000'], 'proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240']];
    }

    private function execute(callable $operation, PartnerOrganization $partner)
    {
        try {
            $payment = $operation();
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['wallet' => $e->getMessage()]);
        }

        return back()->with('success', $payment->reference.' enregistré. Solde du compte dépôt : '
            .number_format($partner->wallet()->value('balance') / 100, 0, ',', ' ').' FCFA.');
    }
}
