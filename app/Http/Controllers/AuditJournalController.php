<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Journal d'audit de l'établissement : connexions, refus d'accès, actions
 * sensibles, interventions de l'administrateur et sessions du support.
 *
 * Il vivait dans l'ancienne console « admin global », retirée ; il rejoint
 * l'application, sous le droit audit.voir.
 */
class AuditJournalController extends Controller
{
    public function index(Request $request): View
    {
        $requete = AuditLog::with('user')->latest('id');

        if ($request->filled('user_id')) {
            $requete->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('event_type')) {
            $requete->where('event_type', $request->string('event_type'));
        }
        if ($request->filled('module')) {
            $requete->where('module', $request->string('module'));
        }
        if ($request->filled('date_from')) {
            $requete->whereDate('created_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $requete->whereDate('created_at', '<=', $request->date('date_to'));
        }
        if ($request->filled('q')) {
            $requete->where('action', 'like', '%'.$request->string('q').'%');
        }
        if ($request->boolean('interventions')) {
            // Les actions d'une intervention portent sa référence.
            $requete->where(fn ($q) => $q->where('event_type', 'like', 'intervention%')
                ->orWhere('payload', 'like', '%"intervention_id"%'));
        }

        return view('administration.journal', [
            'journal' => $requete->paginate(30)->withQueryString(),
            'utilisateurs' => User::orderBy('name')->get(['id', 'name']),
            'types' => AuditLog::query()->distinct()->orderBy('event_type')->pluck('event_type'),
            'modules' => AuditLog::query()->whereNotNull('module')->distinct()->orderBy('module')->pluck('module'),
        ]);
    }
}
