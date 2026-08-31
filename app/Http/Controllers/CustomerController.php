<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                  ->orWhere('last_name',  'ilike', "%{$search}%")
                  ->orWhere('email',      'ilike', "%{$search}%")
                  ->orWhere('phone',      'ilike', "%{$search}%");
            });
        }

        if ($request->filled('level')) {
            $query->where('loyalty_level', $request->level);
        }

        if ($request->boolean('vip_only')) {
            $query->where('is_vip', true);
        }

        // Stats globales pour les badges
        $stats = [
            'total'    => Customer::count(),
            'vip'      => Customer::where('is_vip', true)->count(),
            'platinum' => Customer::where('loyalty_level', 'platinum')->count(),
            'gold'     => Customer::where('loyalty_level', 'gold')->count(),
        ];

        $customers = $query
            ->withCount('bookings')
            ->orderBy('last_name')
            ->paginate(15)
            ->withQueryString();

        return view('customers.index', compact('customers', 'stats'));
    }

    public function create()
    {
        // Seules les conventions en cours sont proposées : contrairement à
        // l'édition, il n'y a pas de rattachement existant à préserver.
        $partnerOrganizations = \App\Models\PartnerOrganization::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('customers.create', compact('partnerOrganizations'));
    }

    public function store(Request $request)
    {
        // Mêmes règles que update() : l'email reste facultatif, un client
        // walk-in se présente souvent sans adresse.
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'nationality' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'id_document_type' => 'nullable|string|in:CNI,Passeport,Permis,CarteSejour',
            'id_document_number' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(\App\Support\Countries::all()))],
            'partner_organization_id' => ['nullable', 'exists:partner_organizations,id'],
            'is_vip' => 'nullable|boolean',
            'is_blacklisted' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $validated['is_vip'] = $request->boolean('is_vip');
        $validated['is_blacklisted'] = $request->boolean('is_blacklisted');

        // Même résolution que partout ailleurs (BookingController, imports CSV) :
        // sans tenant_id, la fiche n'apparaîtrait dans aucune liste filtrée.
        $validated['tenant_id'] = Auth::user()->tenant_id
            ?? \App\Models\Tenant::current()?->id;

        $customer = Customer::create($validated);

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'Le client a été créé avec succès.');
    }

    public function show(Customer $customer)
    {
        $customer->load([
            'bookings' => fn($q) => $q->with('room.roomType')
                                      ->orderBy('check_in', 'desc')
                                      ->limit(10),
            'loyaltyTransactions' => fn($q) => $q->orderBy('created_at', 'desc')->limit(10),
        ]);

        return view('customers.show', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        // Les conventions échues restent proposées si le client y est déjà
        // rattaché, pour ne pas effacer silencieusement l'information.
        $partnerOrganizations = \App\Models\PartnerOrganization::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $customer->partner_organization_id))
            ->orderBy('name')
            ->get();

        return view('customers.edit', compact('customer', 'partnerOrganizations'));
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'nationality' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'id_document_type' => 'nullable|string|in:CNI,Passeport,Permis,CarteSejour',
            'id_document_number' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            // Pays de résidence (code ISO) : marché émetteur du client.
            'country' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(\App\Support\Countries::all()))],
            // Organisation partenaire dont le client est membre.
            'partner_organization_id' => ['nullable', 'exists:partner_organizations,id'],
            'is_vip' => 'nullable|boolean',
            'is_blacklisted' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $validated['is_vip'] = $request->boolean('is_vip');
        $validated['is_blacklisted'] = $request->boolean('is_blacklisted');

        $customer->update($validated);

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'Les informations du client ont été mises à jour avec succès.');
    }
}