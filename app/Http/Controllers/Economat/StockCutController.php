<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\StockCut;
use Illuminate\View\View;

/** Le bon de découpe : à l'écran et à imprimer. */
class StockCutController extends Controller
{
    public function show(StockCut $cut): View
    {
        return view('economat.cuts.show', ['decoupe' => $cut->load('lines.pantryItem', 'item', 'restaurant', 'cutBy')]);
    }

    public function print(StockCut $cut): View
    {
        return view('economat.cuts.print', [
            'decoupe' => $cut->load('lines.pantryItem', 'item', 'restaurant', 'cutBy'),
            'tenant'  => \App\Models\Tenant::first(),
        ]);
    }
}
