<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Intervention;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Trace des interventions de l'administrateur auprès de la console
 * d'orchestration.
 *
 * La console doit savoir quand le service informatique d'un hôtel a écrit
 * dans l'exploitation : le support et le propriétaire y voient chaque
 * intervention. Si elle est injoignable, l'intervention a lieu quand même —
 * on ne bloque pas un service informatique en urgence — et la trace part plus
 * tard, marquée tardive pour de bon.
 */
class InterventionTrace
{
    public function transmettre(Intervention $intervention): bool
    {
        $url = rtrim((string) config('orchestration.erp_url'), '/');
        $secret = (string) config('orchestration.secret');
        $slug = (string) config('orchestration.tenant_slug');

        if ($url !== '' && $secret !== '' && $slug !== '') {
            try {
                $reponse = Http::withToken($secret)->acceptJson()->connectTimeout(2)->timeout(4)
                    ->post("{$url}/api/etablissements/{$slug}/interventions", $this->charge($intervention));

                if ($reponse->successful()) {
                    $intervention->forceFill(['erp_a_transmettre' => false, 'erp_transmis_at' => now()])->save();

                    return true;
                }

                Log::warning("[Intervention] Trace refusée par la console : {$reponse->status()}");
            } catch (\Throwable $e) {
                Log::info('[Intervention] Console injoignable : '.$e->getMessage());
            }
        }

        $intervention->forceFill([
            'erp_echecs' => $intervention->erp_echecs + 1,
            'erp_tardive' => true,
        ])->save();

        return false;
    }

    /**
     * Clôt les interventions arrivées au bout de leur durée, puis transmet
     * toutes les traces en attente.
     *
     * @return array{expirees: int, transmises: int, en_attente: int}
     */
    public function rattraper(): array
    {
        $expirees = 0;
        foreach (Intervention::whereNull('fin_reelle')->where('fin_prevue', '<=', now())->get() as $intervention) {
            $intervention->clore(Intervention::EXPIREE);
            AuditLog::record($intervention->user_id, 'intervention_fin',
                "Fin de l'intervention #{$intervention->id} : durée écoulée", 'security',
                ['intervention_id' => $intervention->id, 'cloture' => Intervention::EXPIREE]);
            $expirees++;
        }

        $transmises = 0;
        $enAttente = 0;
        foreach (Intervention::where('erp_a_transmettre', true)->orderBy('id')->get() as $intervention) {
            $this->transmettre($intervention) ? $transmises++ : $enAttente++;
        }

        return ['expirees' => $expirees, 'transmises' => $transmises, 'en_attente' => $enAttente];
    }

    /** @return array<string, mixed> */
    public function charge(Intervention $intervention): array
    {
        $intervention->loadMissing('user');

        return [
            'reference' => $intervention->id,
            'administrateur' => $intervention->user?->name,
            'email' => $intervention->user?->email,
            'motif' => $intervention->motif,
            'perimetres' => $intervention->libellesPerimetres(),
            'debut' => $intervention->debut->toIso8601String(),
            'fin_prevue' => $intervention->fin_prevue->toIso8601String(),
            'fin_reelle' => $intervention->fin_reelle?->toIso8601String(),
            'cloture' => $intervention->cloture,
            'tardive' => (bool) $intervention->erp_tardive,
            // Ce que l'administrateur a fait pendant l'intervention.
            'actions' => AuditLog::where('payload->intervention_id', $intervention->id)
                ->where('event_type', 'activity')->count(),
        ];
    }
}
