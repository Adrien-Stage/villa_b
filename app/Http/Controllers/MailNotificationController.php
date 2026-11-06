<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\MailNotificationService;
use App\Support\RoleCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Contrôleur centralisé pour le déclenchement et l'administration des envois d'e-mails.
 */
class MailNotificationController extends Controller
{
    public function __construct(
        protected MailNotificationService $mailService
    ) {
    }

    /**
     * Envoie un e-mail de test/diagnostic (réservé aux managers et administrateurs).
     */
    public function sendTest(Request $request): JsonResponse|RedirectResponse
    {
        $user = Auth::user();
        if (! $user || (! $user->isAdmin() && ! $user->hasRole(RoleCatalog::MANAGER))) {
            abort(403, 'Action réservée aux responsables d\'établissement.');
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $result = $this->mailService->sendDiagnosticEmail($validated['email']);

        AuditLog::record(
            $user->id,
            'email_diagnostic',
            "Test d'envoi d'e-mail vers {$validated['email']} — Statut : " . ($result['success'] ? 'Succès' : 'Échec'),
            'system',
            ['recipient' => $validated['email'], 'result' => $result]
        );

        if ($request->expectsJson()) {
            return response()->json($result, $result['success'] ? 200 : 500);
        }

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Déclenche l'envoi d'un bon de commande au fournisseur.
     */
    public function sendPurchaseOrder(Request $request, PurchaseOrder $order): JsonResponse|RedirectResponse
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $validated = $request->validate([
            'email' => ['nullable', 'email'],
        ]);

        $targetEmail = $validated['email'] ?? $order->supplier?->email;
        if (! $targetEmail) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Aucune adresse e-mail disponible pour ce fournisseur.'], 422);
            }
            return back()->with('error', 'Aucune adresse e-mail disponible pour ce fournisseur.');
        }

        $success = $this->mailService->sendPurchaseOrderNotification($order, $targetEmail);

        if ($success) {
            AuditLog::record(
                $user->id,
                'purchase_order_sent',
                "Bon de commande #{$order->number} envoyé à {$targetEmail}",
                'purchases',
                ['order_id' => $order->id, 'supplier_id' => $order->supplier_id, 'email' => $targetEmail]
            );

            $message = "Le bon de commande #{$order->number} a été envoyé par e-mail avec succès.";
            return $request->expectsJson()
                ? response()->json(['success' => true, 'message' => $message])
                : back()->with('success', $message);
        }

        $errorMessage = "Échec de l'envoi du bon de commande #{$order->number}. Veuillez vérifier les logs ou le transporteur.";
        return $request->expectsJson()
            ? response()->json(['success' => false, 'message' => $errorMessage], 500)
            : back()->with('error', $errorMessage);
    }

    /**
     * Renvoie l'e-mail de bienvenue à un utilisateur.
     */
    public function resendWelcome(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $currentUser = Auth::user();
        if (! $currentUser || (! $currentUser->isAdmin() && ! $currentUser->hasRole(RoleCatalog::MANAGER))) {
            abort(403, 'Non autorisé.');
        }

        $defaultPassword = config('auth.default_user_password', 'Wetchah@2026!');
        $success = $this->mailService->sendWelcomeNotification($user, $defaultPassword);

        if ($success) {
            AuditLog::record(
                $currentUser->id,
                'user_management',
                "Réexpédition de l'e-mail de bienvenue à {$user->name} ({$user->email})",
                'users',
                ['target_user_id' => $user->id]
            );

            $message = "L'e-mail de bienvenue a été réexpédié à {$user->email}.";
            return $request->expectsJson()
                ? response()->json(['success' => true, 'message' => $message])
                : back()->with('success', $message);
        }

        $errorMessage = "Erreur lors de la réexpédition de l'e-mail à {$user->email}.";
        return $request->expectsJson()
            ? response()->json(['success' => false, 'message' => $errorMessage], 500)
            : back()->with('error', $errorMessage);
    }
}
