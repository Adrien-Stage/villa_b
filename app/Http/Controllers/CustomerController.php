<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

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
            ->paginate(20)
            ->withQueryString();

        return view('customers.index', compact('customers', 'stats'));
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
        return view('customers.edit', compact('customer'));
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