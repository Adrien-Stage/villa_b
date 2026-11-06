<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenue sur {{ $appName }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #fdfbf7; color: #391F0E; margin: 0; padding: 30px 15px;">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; background-color: #ffffff; border-radius: 12px; border: 1px solid #eed4a3; overflow: hidden; box-shadow: 0 4px 12px rgba(57, 31, 14, 0.05);">
        <!-- Header -->
        <tr>
            <td style="background-color: #391F0E; padding: 25px 30px; text-align: center;">
                <h1 style="color: #ffffff; font-size: 22px; margin: 0; font-weight: 600; letter-spacing: 0.5px;">{{ $appName }}</h1>
                <p style="color: #ccab87; font-size: 13px; margin: 5px 0 0 0; text-transform: uppercase; letter-spacing: 1px;">Plateforme hôtelière & restauration</p>
            </td>
        </tr>
        <!-- Content -->
        <tr>
            <td style="padding: 30px;">
                <h2 style="color: #391F0E; font-size: 18px; margin: 0 0 15px 0;">Bonjour {{ $user->name }},</h2>
                <p style="font-size: 14px; line-height: 1.6; color: #5a4233; margin: 0 0 20px 0;">
                    Votre compte d'accès à l'application <strong>{{ $appName }}</strong> vient d'être créé avec succès par votre responsable.
                </p>

                <!-- Credentials Box -->
                <div style="background-color: #faf6f0; border-radius: 8px; border: 1px solid #eed4a3; padding: 20px; margin: 0 0 25px 0;">
                    <h3 style="color: #391F0E; font-size: 14px; margin: 0 0 12px 0; text-transform: uppercase; letter-spacing: 0.5px;">Vos identifiants de connexion</h3>
                    <table border="0" cellpadding="4" cellspacing="0" width="100%" style="font-size: 14px;">
                        <tr>
                            <td width="130" style="color: #8c7362; font-weight: 500;">Identifiant (Email) :</td>
                            <td style="color: #391F0E; font-weight: 600;">{{ $user->email }}</td>
                        </tr>
                        <tr>
                            <td style="color: #8c7362; font-weight: 500;">Mot de passe initial :</td>
                            <td>
                                <code style="background-color: #ffffff; border: 1px solid #ccab87; border-radius: 4px; padding: 4px 8px; color: #391F0E; font-size: 15px; font-weight: bold; font-family: monospace;">{{ $defaultPassword }}</code>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Instructions Alert -->
                <div style="background-color: #fff9e6; border-left: 4px solid #ccab87; padding: 12px 15px; margin: 0 0 25px 0; border-radius: 0 6px 6px 0;">
                    <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #7a5814;">
                        <strong>Important :</strong> Pour garantir la sécurité de vos opérations, il vous sera demandé de modifier obligatoirement ce mot de passe dès votre première connexion.
                    </p>
                </div>

                <!-- Action Button -->
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 10px 0 25px 0;">
                    <tr>
                        <td align="center">
                            <a href="{{ $loginUrl }}" target="_blank" style="background-color: #391F0E; color: #ffffff; text-decoration: none; padding: 12px 30px; border-radius: 6px; font-weight: 600; font-size: 14px; display: inline-block;">
                                Accéder à l'application
                            </a>
                        </td>
                    </tr>
                </table>

                <p style="font-size: 13px; line-height: 1.5; color: #8c7362; margin: 0;">
                    Si vous rencontrez la moindre difficulté, contactez le responsable informatique ou votre directeur d'établissement.
                </p>
            </td>
        </tr>
        <!-- Footer -->
        <tr>
            <td style="background-color: #faf6f0; border-top: 1px solid #eed4a3; padding: 20px; text-align: center; font-size: 12px; color: #8c7362;">
                Cet e-mail automatique a été émis par le système de gestion <strong>{{ $appName }}</strong>.<br>
                Merci de ne pas répondre directement à ce message.
            </td>
        </tr>
    </table>
</body>
</html>
