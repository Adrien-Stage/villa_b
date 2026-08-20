<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Bouton « Suggestion » de la barre supérieure : ouvert à tout le personnel,
 * quel que soit son rôle. Un serveur qui bute sur un écran n'a pas à passer
 * par son manager pour que le problème arrive au support technique.
 */
class SupportTicketController extends Controller
{
    /** Les tickets de l'utilisateur connecté, avec l'état de leur traitement. */
    public function index(): JsonResponse
    {
        $tickets = SupportTicket::where('user_id', Auth::id())
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (SupportTicket $t) => [
                'id'      => $t->id,
                'type'    => $t->type,
                'subject' => $t->subject,
                'status'  => $t->status,
                'label'   => $t->statusLabel(),
                'reply'   => $t->reply,
                'at'      => $t->created_at?->format('d/m/Y H:i'),
                'ago'     => $t->created_at?->diffForHumans(),
            ]);

        return response()->json([
            'tickets' => $tickets,
            'ouverts' => $tickets->whereIn('status', ['nouveau', 'en_cours'])->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $valide = $request->validate([
            'type'        => ['required', 'in:' . implode(',', SupportTicket::TYPES)],
            'subject'     => ['required', 'string', 'min:5', 'max:160'],
            'message'     => ['required', 'string', 'min:10', 'max:2000'],
            'context_url' => ['nullable', 'string', 'max:255'],
        ]);

        $user = Auth::user();

        $ticket = SupportTicket::create([
            'user_id'     => $user->id,
            'author_name' => $user->name,
            'author_role' => $user->role,
            'type'        => $valide['type'],
            'subject'     => $valide['subject'],
            'message'     => $valide['message'],
            'context_url' => $valide['context_url'] ?? null,
            'status'      => 'nouveau',
        ]);

        return response()->json([
            'ok'     => true,
            'ticket' => ['id' => $ticket->id, 'subject' => $ticket->subject],
        ], 201);
    }
}
