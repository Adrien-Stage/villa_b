@php
    // Charte de l'établissement, résolue par MailIdentity. Les valeurs sont
    // toujours présentes : le service replie sur la charte d'origine quand rien
    // n'est configuré, on peut donc les poser sans garde-fou.
    $nom       = $brand['name'];
    $primaire  = $brand['primary'];
    $secondaire = $brand['secondary'];
    $accent    = $brand['accent'];

    // Le message s'adresse à qui le reçoit — client ou mandataire — et non
    // systématiquement au client du dossier.
    $prenom = $recipient?->first_name ?: $booking->customer?->first_name;

    // Un mandataire ne réserve pas pour lui : le dire évite qu'il croie à une
    // erreur en lisant un nom qui n'est pas le sien.
    $pourAutrui = $recipient && $booking->customer && $recipient->id !== $booking->customer->id;

    // Coordonnées de pied de page, sans séparateur orphelin si l'une manque.
    $coordonnees = implode(' | ', array_filter([
        $brand['address'],
        $brand['phone'] ? 'Tél : ' . $brand['phone'] : null,
        $brand['email'],
    ]));
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Votre code d'accès — {{ $nom }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f7f5f2; font-family: 'Inter', Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f7f5f2; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="100%" style="max-width: 600px; background-color: #ffffff; border: 1px solid #e2dcd5; border-radius: 16px; overflow: hidden; border-collapse: collapse;">

                    {{-- En-tête aux couleurs de l'établissement. Le logo peut être
                         bloqué par la messagerie : le nom reste écrit en dessous,
                         sans quoi l'en-tête arriverait vide. --}}
                    <tr>
                        <td style="background-color: {{ $primaire }}; padding: 30px 40px; text-align: center;">
                            @if($brand['logo'])
                                <img src="{{ $brand['logo'] }}" alt="{{ $nom }}" width="64"
                                     style="display: block; margin: 0 auto 12px auto; width: 64px; height: 64px; border-radius: 50%; object-fit: cover; border: 0;">
                            @endif
                            <h1 style="color: {{ $secondaire }}; font-family: 'Playfair Display', Georgia, serif; font-size: 24px; font-weight: 600; margin: 0; letter-spacing: 1px;">
                                {{ mb_strtoupper($nom) }}
                            </h1>
                        </td>
                    </tr>

                    {{-- Contenu --}}
                    <tr>
                        <td style="padding: 40px 40px 30px 40px; color: {{ $primaire }};">
                            <h2 style="font-size: 20px; font-weight: 600; margin-top: 0; margin-bottom: 20px; font-family: 'Inter', Arial, sans-serif;">
                                Bonjour {{ $prenom }},
                            </h2>

                            <p style="font-size: 14px; line-height: 1.6; color: #4a4a4a; margin-top: 0; margin-bottom: 25px;">
                                @if($pourAutrui)
                                    La réservation que vous avez effectuée au nom de
                                    <strong>{{ $booking->customer->full_name }}</strong> à
                                    <strong>{{ $nom }}</strong> est bien enregistrée. Voici les détails du séjour :
                                @else
                                    Votre réservation à <strong>{{ $nom }}</strong> a bien été enregistrée.
                                    Voici les détails de votre séjour :
                                @endif
                            </p>

                            {{-- Détails de la réservation --}}
                            <table width="100%" style="background-color: #fcfbfa; border: 1px solid {{ $accent }}; border-radius: 8px; margin-bottom: 30px; border-collapse: separate; padding: 15px 20px;">
                                <tr>
                                    <td style="font-size: 13px; color: #7a7a7a; padding: 5px 0;">N° de réservation :</td>
                                    <td style="font-size: 13px; font-weight: bold; color: {{ $primaire }}; text-align: right; padding: 5px 0;">{{ $booking->booking_number }}</td>
                                </tr>
                                @if($pourAutrui)
                                    <tr>
                                        <td style="font-size: 13px; color: #7a7a7a; padding: 5px 0;">Client :</td>
                                        <td style="font-size: 13px; font-weight: bold; color: {{ $primaire }}; text-align: right; padding: 5px 0;">{{ $booking->customer->full_name }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="font-size: 13px; color: #7a7a7a; padding: 5px 0;">Chambre :</td>
                                    <td style="font-size: 13px; font-weight: bold; color: {{ $primaire }}; text-align: right; padding: 5px 0;">{{ $booking->room->number }} ({{ $booking->room->roomType->name }})</td>
                                </tr>
                                <tr>
                                    <td style="font-size: 13px; color: #7a7a7a; padding: 5px 0;">Dates du séjour :</td>
                                    <td style="font-size: 13px; font-weight: bold; color: {{ $primaire }}; text-align: right; padding: 5px 0;">Du {{ $booking->check_in->format('d/m/Y') }} au {{ $booking->check_out->format('d/m/Y') }}</td>
                                </tr>
                            </table>

                            {{-- Le code, mis en avant --}}
                            <div style="text-align: center; background-color: {{ $primaire }}; color: #ffffff; border-radius: 12px; padding: 25px; margin-bottom: 30px;">
                                <p style="font-size: 11px; text-transform: uppercase; letter-spacing: 2px; margin-top: 0; margin-bottom: 10px; color: {{ $secondaire }}; font-weight: 600;">Code de check-in sécurisé</p>
                                <p style="font-size: 38px; font-weight: bold; font-family: Courier, monospace; letter-spacing: 8px; margin: 0;">{{ $booking->checkin_code }}</p>
                                <p style="font-size: 11px; opacity: 0.7; margin-top: 10px; margin-bottom: 0;">Ce code à 6 chiffres est obligatoire pour valider l'arrivée à la réception.</p>
                            </div>

                            <p style="font-size: 13px; line-height: 1.6; color: #7a7a7a; margin-bottom: 0;">
                                * Pour des raisons de sécurité, ne partagez pas ce code.
                                @if($pourAutrui)
                                    Transmettez-le au client au moment de son arrivée.
                                @endif
                                Nos équipes ne le demanderont qu'à l'enregistrement sur place.
                            </p>
                        </td>
                    </tr>

                    {{-- Pied de page --}}
                    <tr>
                        <td style="background-color: #f7f5f2; border-top: 1px solid #e2dcd5; padding: 25px 40px; text-align: center; color: #7a7a7a; font-size: 11px;">
                            <p style="margin: 0 0 5px 0;">{{ $nom }}</p>
                            @if($coordonnees !== '')
                                <p style="margin: 0 0 15px 0;">{{ $coordonnees }}</p>
                            @endif
                            <p style="margin: 0; opacity: 0.6;">&copy; {{ date('Y') }} {{ $nom }}. Tous droits réservés.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
