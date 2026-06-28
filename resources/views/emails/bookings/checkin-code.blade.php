<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Votre Code d'Accès - Villa Boutanga</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f7f5f2; font-family: 'Inter', Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f7f5f2; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="100%" style="max-width: 600px; background-color: #ffffff; border: 1px solid #e2dcd5; border-radius: 16px; overflow: hidden; border-collapse: collapse;">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #391F0E; padding: 30px 40px; text-align: center;">
                            <h1 style="color: #CCAB87; font-family: 'Playfair Display', Georgia, serif; font-size: 24px; font-weight: 600; margin: 0; letter-spacing: 1px;">VILLA BOUTANGA</h1>
                            <p style="color: #ffffff; opacity: 0.7; font-size: 11px; text-transform: uppercase; letter-spacing: 2px; margin: 5px 0 0 0;">Prestige & Sérénité</p>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 40px 30px 40px; color: #391F0E;">
                            <h2 style="font-size: 20px; font-weight: 600; margin-top: 0; margin-bottom: 20px; font-family: 'Inter', Arial, sans-serif;">Bonjour {{ $booking->customer->first_name }},</h2>
                            <p style="font-size: 14px; line-height: 1.6; color: #4a4a4a; margin-bottom: 25px;">
                                Votre réservation à la <strong>Villa Boutanga</strong> a bien été enregistrée avec succès. Voici les détails de votre séjour :
                            </p>
                            
                            <!-- Booking Details Card -->
                            <table width="100%" style="background-color: #fcfbfa; border: 1px solid #eed4a3; border-radius: 8px; margin-bottom: 30px; border-collapse: separate; padding: 15px 20px;">
                                <tr>
                                    <td style="font-size: 13px; color: #7a7a7a; padding: 5px 0;">N° de Réservation :</td>
                                    <td style="font-size: 13px; font-weight: bold; color: #391F0E; text-align: right; padding: 5px 0;">{{ $booking->booking_number }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size: 13px; color: #7a7a7a; padding: 5px 0;">Chambre :</td>
                                    <td style="font-size: 13px; font-weight: bold; color: #391F0E; text-align: right; padding: 5px 0;">{{ $booking->room->number }} ({{ $booking->room->roomType->name }})</td>
                                </tr>
                                <tr>
                                    <td style="font-size: 13px; color: #7a7a7a; padding: 5px 0;">Dates du Séjour :</td>
                                    <td style="font-size: 13px; font-weight: bold; color: #391F0E; text-align: right; padding: 5px 0;">Du {{ $booking->check_in->format('d/m/Y') }} au {{ $booking->check_out->format('d/m/Y') }}</td>
                                </tr>
                            </table>
                            
                            <!-- Check-in Code Box -->
                            <div style="text-align: center; background-color: #391F0E; color: #ffffff; border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 10px rgba(57, 31, 14, 0.15);">
                                <p style="font-size: 11px; text-transform: uppercase; letter-spacing: 2px; margin-top: 0; margin-bottom: 10px; color: #CCAB87; font-weight: 600;">Code de Check-in Sécurisé</p>
                                <p style="font-size: 38px; font-weight: bold; font-family: Courier, monospace; letter-spacing: 8px; margin: 0; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">{{ $booking->checkin_code }}</p>
                                <p style="font-size: 11px; opacity: 0.7; margin-top: 10px; margin-bottom: 0;">Ce code à 6 chiffres est obligatoire pour valider votre arrivée à la réception.</p>
                            </div>
                            
                            <p style="font-size: 13px; line-height: 1.6; color: #7a7a7a; margin-bottom: 0;">
                                * Pour votre sécurité, veuillez ne pas partager ce code d'accès. Nos équipes ne vous le demanderont qu'au moment de votre enregistrement physique à l'hôtel.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f7f5f2; border-top: 1px solid #e2dcd5; padding: 25px 40px; text-align: center; color: #7a7a7a; font-size: 11px;">
                            <p style="margin: 0 0 5px 0;">Villa Boutanga — Prestige & Sérénité</p>
                            <p style="margin: 0 0 15px 0;">Bangoulap, Ouest Cameroun | Tél : +237 699 000 000</p>
                            <p style="margin: 0; opacity: 0.6;">&copy; {{ date('Y') }} Villa Boutanga. Tous droits réservés.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
