<?php

namespace App\Services;

use App\Mail\PurchaseOrderMail;
use App\Mail\UserPasswordResetMail;
use App\Mail\UserWelcomeMail;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Service centralisé d'envoi d'e-mails pour Wetchah PMS.
 *
 * Gère de manière unifiée et sécurisée l'ensemble des flux transactionnels :
 * - Création de compte (identifiants initiaux et changement de mot de passe)
 * - Réinitialisation de mot de passe (mot de passe temporaire 5h)
 * - Envoi de bons de commande aux fournisseurs
 * - Notifications génériques et tests de diagnostic Brevo SMTP
 */
class MailNotificationService
{
    /**
     * Envoie l'e-mail de bienvenue avec identifiants et mot de passe par défaut.
     */
    public function sendWelcomeNotification(User $user, string $defaultPassword): bool
    {
        try {
            Mail::to($user->email)->send(new UserWelcomeMail($user, $defaultPassword));
            Log::info("E-mail de bienvenue envoyé à l'utilisateur ID: {$user->id} ({$user->email})");

            return true;
        } catch (Throwable $e) {
            Log::error("Échec d'envoi de l'e-mail de bienvenue pour {$user->email} : " . $e->getMessage(), [
                'user_id' => $user->id,
                'exception' => $e,
            ]);

            return false;
        }
    }

    /**
     * Envoie l'e-mail de réinitialisation avec le mot de passe temporaire (5h).
     */
    public function sendPasswordResetNotification(User $user, string $temporaryPassword, int $expiresInHours = 5): bool
    {
        try {
            Mail::to($user->email)->send(new UserPasswordResetMail($user, $temporaryPassword, $expiresInHours));
            Log::info("E-mail de réinitialisation envoyé à l'utilisateur ID: {$user->id} ({$user->email}) [validité {$expiresInHours}h]");

            return true;
        } catch (Throwable $e) {
            Log::error("Échec d'envoi de l'e-mail de réinitialisation pour {$user->email} : " . $e->getMessage(), [
                'user_id' => $user->id,
                'exception' => $e,
            ]);

            return false;
        }
    }

    /**
     * Envoie un bon de commande au fournisseur.
     */
    public function sendPurchaseOrderNotification(PurchaseOrder $order, ?string $recipientEmail = null): bool
    {
        $targetEmail = $recipientEmail ?? $order->supplier?->email;

        if (empty($targetEmail)) {
            Log::warning("Impossible d'envoyer le bon de commande #{$order->number} : aucune adresse e-mail de fournisseur.");
            return false;
        }

        try {
            Mail::to($targetEmail)->send(new PurchaseOrderMail($order));
            Log::info("Bon de commande #{$order->number} transmis par e-mail à {$targetEmail}");

            return true;
        } catch (Throwable $e) {
            Log::error("Échec de transmission du bon de commande #{$order->number} à {$targetEmail} : " . $e->getMessage(), [
                'order_id' => $order->id,
                'exception' => $e,
            ]);

            return false;
        }
    }

    /**
     * Envoie un e-mail brut de test pour diagnostiquer le transport (ex: MailerSend).
     */
    public function sendDiagnosticEmail(string $recipientEmail): array
    {
        try {
            $appName = config('app.name', 'Wetchah PMS');
            $fromAddress = \App\Support\MailIdentity::from();

            Mail::raw("Ceci est un e-mail de diagnostic envoyé depuis {$appName} via le transport " . config('mail.default') . ".", function ($message) use ($recipientEmail, $appName, $fromAddress) {
                $message->to($recipientEmail)
                    ->from($fromAddress->address, $fromAddress->name)
                    ->subject("Diagnostic Envoi E-mail — {$appName}");
            });

            return [
                'success' => true,
                'message' => "E-mail de diagnostic envoyé avec succès à {$recipientEmail} via " . config('mail.default'),
            ];
        } catch (Throwable $e) {
            Log::error("Échec d'envoi de test e-mail à {$recipientEmail} : " . $e->getMessage());

            return [
                'success' => false,
                'message' => "Erreur d'envoi : " . $e->getMessage(),
            ];
        }
    }
}
