<?php

namespace App\Services;

use App\Mail\CheckinCodeMail;
use App\Models\Booking;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * L'envoi du code de check-in, et surtout : à qui.
 *
 * Une réservation prise par un tiers a deux interlocuteurs possibles. Envoyer
 * le code aux deux — ce que faisait l'application — a l'air prudent, mais c'est
 * un code d'accès : le diffuser deux fois double la surface de fuite, et
 * personne ne sait plus qui l'a réellement reçu quand le client appelle en
 * disant ne rien avoir. La réception tranche donc à la dernière étape.
 *
 * Le destinataire est résolu une seule fois ici, coordonnées comprises, pour
 * que tous les canaux visent la même personne.
 */
class CheckinCodeNotifier
{
    public const TO_CUSTOMER = 'customer';
    public const TO_BOOKER   = 'booker';

    /** Choix proposés à l'écran, dans l'ordre d'affichage. */
    public const RECIPIENTS = [
        self::TO_BOOKER   => 'Mandataire',
        self::TO_CUSTOMER => 'Client final',
    ];

    /**
     * Le destinataire retenu.
     *
     * Un mandataire demandé mais absent du dossier retombe sur le client : le
     * code doit partir, même si le formulaire a été bricolé.
     */
    public function recipient(Booking $booking, ?string $type): ?Customer
    {
        if ($type === self::TO_BOOKER && $booking->booker) {
            return $booking->booker;
        }

        return $booking->customer;
    }

    /** Le type effectivement appliqué, une fois le repli pris en compte. */
    public function resolvedType(Booking $booking, ?string $type): string
    {
        return ($type === self::TO_BOOKER && $booking->booker)
            ? self::TO_BOOKER
            : self::TO_CUSTOMER;
    }

    /**
     * Envoie le code au destinataire choisi.
     *
     * Un échec d'envoi ne remonte pas : la réservation est encaissée et
     * enregistrée, la perdre parce qu'un serveur de mail tousse coûterait bien
     * plus cher que de tracer l'incident et de laisser la réception redonner le
     * code de vive voix.
     *
     * @return array{type:string,label:string,name:?string,email:?string,phone:?string,sent:array<int,string>,error:?string}
     */
    public function send(Booking $booking, ?string $type): array
    {
        $booking->loadMissing(['customer', 'booker', 'room.roomType']);

        $resolu       = $this->resolvedType($booking, $type);
        $destinataire = $this->recipient($booking, $type);

        $compte = [
            'type'  => $resolu,
            'label' => self::RECIPIENTS[$resolu],
            'name'  => $destinataire?->full_name,
            'email' => $this->propre($destinataire?->email),
            'phone' => $this->propre($destinataire?->phone),
            'sent'  => [],
            // Pourquoi ça n'est pas parti. Sans cette raison, l'écran ne peut
            // qu'inventer une cause — et accuser une adresse manquante quand
            // c'est le fournisseur qui a refusé le message.
            'error' => null,
        ];

        if ($compte['email'] !== null) {
            try {
                Mail::to($compte['email'])->send(new CheckinCodeMail($booking, $destinataire));
                $compte['sent'][] = 'email';

                Log::info("Code de check-in envoyé par courriel au {$compte['label']} ({$compte['email']}) "
                    . "pour la réservation #{$booking->booking_number}");
            } catch (\Throwable $e) {
                $compte['error'] = $e->getMessage();

                Log::error("Échec de l'envoi du code de check-in pour la réservation #{$booking->booking_number} : "
                    . $e->getMessage(), [
                        'exception'  => $e,
                        'recipient'  => $compte['label'],
                        'email'      => $compte['email'],
                        'mailer'     => config('mail.default'),
                    ]);
            }
        } else {
            $compte['error'] = "Aucune adresse courriel enregistrée pour le {$compte['label']}.";

            Log::warning("Aucune adresse courriel pour le {$compte['label']} "
                . "de la réservation #{$booking->booking_number} : code non envoyé.");
        }

        // WhatsApp : aucun fournisseur n'est configuré dans l'application à ce
        // jour. Le numéro du destinataire est déjà résolu ci-dessus — c'est ici,
        // et nulle part ailleurs, qu'un envoi viendra se brancher, sur
        // $compte['phone']. Rien n'est simulé en attendant : un journal
        // annonçant un envoi qui n'a pas lieu tromperait la réception.

        return $compte;
    }

    /** Une coordonnée vide n'en est pas une. */
    private function propre(?string $valeur): ?string
    {
        $propre = trim((string) $valeur);

        return $propre === '' ? null : $propre;
    }
}
