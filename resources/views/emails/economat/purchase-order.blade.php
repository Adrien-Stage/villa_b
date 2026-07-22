@php
    $brand = config('mail.from.name', 'Notre établissement');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bon de commande {{ $order->number }}</title>
</head>
<body style="margin:0; padding:0; background:#f4f2ee; font-family: Arial, Helvetica, sans-serif; color:#391F0E;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f2ee; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e6e0d6;">
                    <tr>
                        <td style="background:#391F0E; padding:24px 32px;">
                            <h1 style="margin:0; font-size:18px; color:#ffffff;">{{ $brand }}</h1>
                            <p style="margin:4px 0 0; font-size:13px; color:#CCAB87;">Bon de commande {{ $order->number }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 32px;">
                            <p style="margin:0 0 16px; font-size:14px;">
                                Bonjour{{ $order->supplier->contact_name ? ' ' . $order->supplier->contact_name : '' }},
                            </p>
                            <p style="margin:0 0 20px; font-size:14px; line-height:1.6;">
                                Veuillez trouver ci-dessous notre commande. Merci de nous confirmer sa disponibilité
                                et le délai de livraison.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px; margin-bottom:20px;">
                                <tr>
                                    <td style="padding:4px 0; color:#8a7d6b;">Référence</td>
                                    <td style="padding:4px 0; text-align:right; font-weight:bold;">{{ $order->number }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 0; color:#8a7d6b;">Date</td>
                                    <td style="padding:4px 0; text-align:right;">{{ $order->created_at->format('d/m/Y') }}</td>
                                </tr>
                                @if($order->expected_at)
                                    <tr>
                                        <td style="padding:4px 0; color:#8a7d6b;">Livraison souhaitée</td>
                                        <td style="padding:4px 0; text-align:right;">{{ $order->expected_at->format('d/m/Y') }}</td>
                                    </tr>
                                @endif
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th align="left" style="padding:8px; background:#f4f2ee; border-bottom:2px solid #e6e0d6;">Article</th>
                                        <th align="right" style="padding:8px; background:#f4f2ee; border-bottom:2px solid #e6e0d6;">Qté</th>
                                        <th align="right" style="padding:8px; background:#f4f2ee; border-bottom:2px solid #e6e0d6;">P.U.</th>
                                        <th align="right" style="padding:8px; background:#f4f2ee; border-bottom:2px solid #e6e0d6;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($order->lines as $line)
                                        <tr>
                                            <td style="padding:8px; border-bottom:1px solid #efeae1;">
                                                {{ $line->item?->name ?? 'Article' }}
                                                <span style="color:#8a7d6b;">({{ $line->item?->unit }})</span>
                                            </td>
                                            <td align="right" style="padding:8px; border-bottom:1px solid #efeae1;">{{ rtrim(rtrim(number_format($line->quantity_ordered, 3, ',', ' '), '0'), ',') }}</td>
                                            <td align="right" style="padding:8px; border-bottom:1px solid #efeae1;">{{ number_format($line->unit_price / 100, 0, ',', ' ') }}</td>
                                            <td align="right" style="padding:8px; border-bottom:1px solid #efeae1;">{{ number_format($line->lineTotal() / 100, 0, ',', ' ') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" align="right" style="padding:10px 8px; font-weight:bold;">Total (FCFA)</td>
                                        <td align="right" style="padding:10px 8px; font-weight:bold; font-size:15px;">{{ number_format($order->total_amount / 100, 0, ',', ' ') }}</td>
                                    </tr>
                                </tfoot>
                            </table>

                            @if($order->notes)
                                <p style="margin:20px 0 0; font-size:13px; color:#5a5045; background:#f4f2ee; padding:12px; border-radius:8px;">
                                    <strong>Note :</strong> {{ $order->notes }}
                                </p>
                            @endif

                            <p style="margin:24px 0 0; font-size:13px; line-height:1.6; color:#5a5045;">
                                Cordialement,<br>
                                Le service économat — {{ $brand }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f4f2ee; padding:16px 32px; font-size:11px; color:#8a7d6b; text-align:center;">
                            Ce bon de commande a été généré automatiquement. Pour toute question, répondez à cet email.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
