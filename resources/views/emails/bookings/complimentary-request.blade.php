<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Réservation Offerte - Validation Requise</title>
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
                            <p style="color: #ffffff; opacity: 0.7; font-size: 11px; text-transform: uppercase; letter-spacing: 2px; margin: 5px 0 0 0;">Prestige &amp; Sérénité</p>
                        </td>
                    </tr>

                    <!-- Alert Banner -->
                    <tr>
                        <td style="background-color: #FEF3C7; border-bottom: 2px solid #F59E0B; padding: 16px 40px; text-align: center;">
                            <p style="margin: 0; font-size: 13px; font-weight: 600; color: #92400E; text-transform: uppercase; letter-spacing: 1px;">
                                ⚠ Action Requise — Validation d'une Chambre Offerte
                            </p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 40px 30px 40px; color: #391F0E;">
                            <h2 style="font-size: 20px; font-weight: 600; margin-top: 0; margin-bottom: 20px; font-family: 'Inter', Arial, sans-serif;">Bonjour {{ $managerName }},</h2>
                            <p style="font-size: 14px; line-height: 1.6; color: #4a4a4a; margin-bottom: 25px;">
                                Une nouvelle réservation a été marquée comme <strong style="color: #391F0E;">chambre offerte</strong> par
                                <strong style="color: #391F0E;">{{ $receptionistName }}</strong> et nécessite votre validation.
                            </p>

                            <!-- Booking Details Card -->
                            <table width="100%" style="background-color: #fcfbfa; border: 1px solid #eed4a3; border-radius: 8px; margin-bottom: 30px; border-collapse: separate; padding: 15px 20px;">
                                <tr>
                                    <td style="font-size: 13px; color: #7a7a7a; padding: 8px 0; border-bottom: 1px solid #f0ebe4;">N° de Réservation :</td>
                                    <td style="font-size: 13px; font-weight: bold; color: #391F0E; text-align: right; padding: 8px 0; border-bottom: 1px solid #f0ebe4;">{{ $bookingNumber }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size: 13px; color: #7a7a7a; padding: 8px 0; border-bottom: 1px solid #f0ebe4;">Client :</td>
                                    <td style="font-size: 13px; font-weight: bold; color: #391F0E; text-align: right; padding: 8px 0; border-bottom: 1px solid #f0ebe4;">{{ $customerName }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size: 13px; color: #7a7a7a; padding: 8px 0; border-bottom: 1px solid #f0ebe4;">Chambre :</td>
                                    <td style="font-size: 13px; font-weight: bold; color: #391F0E; text-align: right; padding: 8px 0; border-bottom: 1px solid #f0ebe4;">{{ $roomNumber }} ({{ $roomType }})</td>
                                </tr>
                                <tr>
                                    <td style="font-size: 13px; color: #7a7a7a; padding: 8px 0; border-bottom: 1px solid #f0ebe4;">Dates du Séjour :</td>
                                    <td style="font-size: 13px; font-weight: bold; color: #391F0E; text-align: right; padding: 8px 0; border-bottom: 1px solid #f0ebe4;">Du {{ $checkIn }} au {{ $checkOut }}</td>
                                </tr>
                                @if($notes)
                                <tr>
                                    <td colspan="2" style="font-size: 13px; color: #7a7a7a; padding: 8px 0;">
                                        <strong style="color: #391F0E;">Notes :</strong><br>
                                        <span style="font-style: italic;">{{ $notes }}</span>
                                    </td>
                                </tr>
                                @endif
                            </table>

                            <!-- Status Badge -->
                            <div style="text-align: center; background-color: #FEF3C7; border: 1px solid #F59E0B; border-radius: 12px; padding: 20px; margin-bottom: 30px;">
                                <p style="font-size: 11px; text-transform: uppercase; letter-spacing: 2px; margin-top: 0; margin-bottom: 8px; color: #92400E; font-weight: 600;">Statut Actuel</p>
                                <p style="font-size: 18px; font-weight: bold; color: #92400E; margin: 0;">En Attente de Validation</p>
                            </div>

                            <!-- CTA Button -->
                            <div style="text-align: center; margin-bottom: 30px;">
                                <a href="{{ $actionUrl }}" style="display: inline-block; background-color: #391F0E; color: #CCAB87; font-size: 14px; font-weight: 600; text-decoration: none; padding: 14px 40px; border-radius: 8px; letter-spacing: 0.5px; box-shadow: 0 4px 10px rgba(57, 31, 14, 0.2);">
                                    Voir la Réservation
                                </a>
                            </div>

                            <p style="font-size: 13px; line-height: 1.6; color: #7a7a7a; margin-bottom: 0;">
                                Vous pouvez approuver ou refuser cette réservation depuis la page de détails. En l'absence de décision, la réservation restera en attente.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f7f5f2; border-top: 1px solid #e2dcd5; padding: 25px 40px; text-align: center; color: #7a7a7a; font-size: 11px;">
                            <p style="margin: 0 0 5px 0;">Villa Boutanga — Prestige &amp; Sérénité</p>
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
