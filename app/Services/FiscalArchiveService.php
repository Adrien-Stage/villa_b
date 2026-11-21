<?php

namespace App\Services;

use App\Models\FiscalArchive;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Support\Document\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FiscalArchiveService
{
    public function __construct(
        private readonly LedgerBooks $books,
        private readonly FiscalStatements $statements,
    ) {
    }

    /** @param list<UploadedFile> $evidence */
    public function close(FiscalYear $year, string $regime, string $notes, array $evidence, array $flowMap): FiscalArchive
    {
        if (!array_key_exists($regime, FiscalStatements::REGIMES)) {
            throw new RuntimeException('Choisissez un référentiel comptable.');
        }
        if (trim($notes) === '' || $evidence === []) {
            throw new RuntimeException('Joignez les justificatifs d’inventaire et renseignez les notes de l’exercice.');
        }

        return DB::transaction(function () use ($year, $regime, $notes, $evidence, $flowMap) {
            $year = FiscalYear::query()->lockForUpdate()->findOrFail($year->id);
            if ($archive = FiscalArchive::query()->where('fiscal_year_id', $year->id)->first()) {
                return $archive;
            }
            if ($year->isClosed() || $year->ends_on->greaterThanOrEqualTo(today())) {
                throw new RuntimeException('Choisissez un exercice terminé et encore ouvert.');
            }
            $periods = $year->periods()->orderBy('starts_on')->lockForUpdate()->get();
            if ($periods->count() !== 12 || $periods->contains(fn ($period) => !$period->isLocked())) {
                throw new RuntimeException('Les douze périodes mensuelles doivent être verrouillées avant l’arrêté.');
            }
            if (JournalEntry::query()->whereIn('fiscal_period_id', $periods->modelKeys())->whereNull('posted_at')->exists()) {
                throw new RuntimeException('Des écritures restent à valider ou à retirer.');
            }

            $from = $year->starts_on;
            $to = $year->ends_on;
            $lines = iterator_to_array($this->books->lines($from, $to), false);
            if ($lines === [] || array_sum(array_column($lines, 'debit')) !== array_sum(array_column($lines, 'credit'))) {
                throw new RuntimeException('Le grand livre de l’exercice doit être renseigné et équilibré.');
            }
            $statements = $this->statements->build($year, $regime, $notes, $flowMap);
            if ($regime === 'normal' && end($statements)->lesLignes()->contains('type', 'À ventiler')) {
                throw new RuntimeException('Renseignez la rubrique des flux de trésorerie encore à ventiler.');
            }
            $attachments = [];
            foreach ($evidence as $file) {
                $contents = $file->get();
                if ($contents === false || $contents === '') {
                    throw new RuntimeException('Un justificatif d’inventaire est illisible.');
                }
                $attachments[] = [
                    'name' => basename($file->getClientOriginalName()),
                    'sha256' => hash('sha256', $contents), 'contents' => base64_encode($contents),
                ];
            }

            $sealed = now();
            $documents = array_merge($this->books->documents($from, $to), $statements);
            $payload = json_encode([
                'version' => 1, 'regime' => $regime, 'year' => (int) $from->year,
                'sealed_at' => $sealed->toIso8601String(), 'documents' => array_map(fn (Document $d) => $d->instantane(), $documents),
                'ledger' => $lines, 'attachments' => $attachments,
            ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);

            $archive = FiscalArchive::create([
                'fiscal_year_id' => $year->id, 'regime' => $regime, 'sha256' => hash('sha256', $payload),
                'payload' => Crypt::encryptString($payload),
                'retain_until' => $to->copy()->addYears(10)->toDateString(), 'sealed_by' => Auth::id(),
            ]);
            $year->update(['closed_at' => $sealed]);

            return $archive;
        });
    }

    public function read(FiscalArchive $archive): array
    {
        $contents = Crypt::decryptString($archive->payload);
        if (!hash_equals($archive->sha256, hash('sha256', $contents))) {
            throw new RuntimeException('L’empreinte du dossier d’archive a changé.');
        }

        $payload = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (($payload['version'] ?? null) !== 1 || empty($payload['documents'])) {
            throw new RuntimeException('Le format du dossier d’archive ne peut pas être relu.');
        }
        foreach ($payload['attachments'] as $attachment) {
            $file = base64_decode($attachment['contents'], true);
            if ($file === false || !hash_equals($attachment['sha256'], hash('sha256', $file))) {
                throw new RuntimeException('Un justificatif d’inventaire est altéré.');
            }
        }

        return $payload;
    }
}
