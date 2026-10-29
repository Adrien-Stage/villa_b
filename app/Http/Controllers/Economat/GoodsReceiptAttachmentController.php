<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptAttachment;
use App\Services\GoodsReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bordereaux de livraison d'un bon de réception : les joindre après coup,
 * les ouvrir, les retirer. Retiré, un bordereau part à la corbeille : seule
 * la console peut le rendre.
 */
class GoodsReceiptAttachmentController extends Controller
{
    /** Règles de validation partagées avec le formulaire de réception. */
    public static function regles(): array
    {
        return [
            'bordereaux'   => ['nullable', 'array', 'max:' . GoodsReceiptAttachment::NOMBRE_MAX],
            'bordereaux.*' => ['file', 'mimes:' . implode(',', GoodsReceiptAttachment::TYPES), 'max:' . GoodsReceiptAttachment::TAILLE_MAX],
        ];
    }

    public static function messages(): array
    {
        return [
            'bordereaux.max'     => 'Au plus ' . GoodsReceiptAttachment::NOMBRE_MAX . ' bordereaux à la fois.',
            'bordereaux.*.mimes' => 'Un bordereau est un PDF ou une image (JPG, PNG, WEBP, HEIC).',
            'bordereaux.*.max'   => 'Un bordereau ne dépasse pas ' . (GoodsReceiptAttachment::TAILLE_MAX / 1024) . ' Mo.',
        ];
    }

    public function store(Request $request, GoodsReceipt $receipt, GoodsReceiptService $service): RedirectResponse
    {
        if ($receipt->status === GoodsReceipt::STATUS_CANCELLED) {
            return back()->with('error', 'Ce bon de réception est annulé : il ne reçoit plus de bordereau.');
        }

        $request->validate(['bordereaux' => ['required', ...array_slice(self::regles()['bordereaux'], 1)]] + self::regles(), [
            'bordereaux.required' => 'Choisissez au moins un fichier.',
        ] + self::messages());

        $joints = $service->joindre($receipt, $request->file('bordereaux', []), Auth::user());

        return back()->with('success', "{$joints} bordereau(x) joint(s) au bon {$receipt->number}.");
    }

    public function show(GoodsReceipt $receipt, GoodsReceiptAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->goods_receipt_id === $receipt->id, 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        // Ouvert dans le navigateur (PDF, image), sous son nom d'origine.
        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
        ], 'inline');
    }

    public function destroy(GoodsReceipt $receipt, GoodsReceiptAttachment $attachment): RedirectResponse
    {
        abort_unless($attachment->goods_receipt_id === $receipt->id, 404);

        $nom = $attachment->original_name;
        $attachment->delete();

        AuditLog::record(Auth::id(), 'bordereau_retire',
            "Bordereau « {$nom} » retiré du bon {$receipt->number} (mis à la corbeille)", 'economat',
            ['goods_receipt_id' => $receipt->id, 'attachment_id' => $attachment->id]);

        return back()->with('success', "Bordereau « {$nom} » retiré du bon. Il est conservé dans la corbeille de l'établissement.");
    }
}
