<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\FixedAsset;
use App\Services\FixedAssetRegister;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use RuntimeException;

class FixedAssetController extends Controller
{
    public function index(Request $request, FixedAssetRegister $register)
    {
        $assets = FixedAsset::query()->with('acquisitionLine.entry')->orderBy('reference')->paginate(25);
        $selected = $request->filled('asset') ? FixedAsset::query()->findOrFail($request->integer('asset')) : null;
        $accounts = Account::query()->postable()->whereIn('account_class', [2, 6])->orderBy('code')->get();
        $amortization = $assets->getCollection()->mapWithKeys(fn ($asset) => [$asset->id => $register->bookedDepreciation($asset, today())]);

        return response()->view('accounting.ledger.assets', compact('assets', 'selected', 'accounts', 'amortization'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, FixedAssetRegister $register)
    {
        $action = $request->validate(['action' => ['required', Rule::in(['register', 'depreciate', 'dispose'])]])['action'];
        try {
            if ($action === 'register') {
                $data = $request->validate([
                    'reference' => ['required', 'string', 'max:60'],
                    'label' => ['required', 'string', 'max:180'],
                    'journal_entry_line_id' => ['required', 'integer', 'exists:journal_entry_lines,id'],
                    'in_service_on' => ['required', 'date_format:Y-m-d'],
                    'useful_months' => ['required', 'integer', 'min:0', 'max:1200'],
                    'residual_amount' => ['required', 'integer', 'min:0', 'max:2000000000'],
                    'depreciation_account' => ['required', 'regex:/^28[0-9]+$/', 'exists:accounts,code'],
                    'charge_account' => ['required', 'regex:/^68[0-9]+$/', 'exists:accounts,code'],
                ]);
                $data['residual_amount'] = (int) $data['residual_amount'] * 100;
                $asset = $register->register($data);

                return redirect()->route('accounting.ledger.assets.index', ['asset' => $asset->id])->with('success', 'L’actif figure au registre.');
            }
            $data = $request->validate([
                'asset_id' => ['required', 'exists:fixed_assets,id'],
                'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'reference' => ['required_if:action,dispose', 'nullable', 'string', 'max:60'],
                'proceeds' => ['required_if:action,dispose', 'nullable', 'integer', 'min:0', 'max:2000000000'],
                'vat' => ['required_if:action,dispose', 'nullable', 'integer', 'min:0', 'lte:proceeds'],
                'ordinary' => ['nullable', 'boolean'],
            ]);
            $asset = FixedAsset::findOrFail($data['asset_id']);
            $date = Carbon::parse($data['date']);
            $entry = $action === 'depreciate' ? $register->depreciation($asset, $date)
                : $register->dispose($asset, $date, $data['reference'], (int) $data['proceeds'] * 100,
                    (int) $data['vat'] * 100, $request->boolean('ordinary'));
        } catch (RuntimeException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return $entry ? redirect()->route('accounting.ledger.entry', $entry)->with('success', 'L’écriture d’immobilisation a été validée.')
            : back()->with('success', 'Aucune dotation complémentaire à enregistrer à cette date.');
    }
}
