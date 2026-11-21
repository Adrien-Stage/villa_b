<?php

namespace App\Console\Commands;

use App\Models\FiscalArchive;
use App\Models\FiscalYear;
use App\Services\FiscalArchiveService;
use App\Support\Document\Document;
use Illuminate\Console\Command;
use Throwable;

class VerifyFiscalArchive extends Command
{
    protected $signature = 'ledger:verify-archive {year : Année à vérifier sur la base restaurée}';

    protected $description = 'Relit un arrêté chiffré et vérifie l’intégrité de ses livres et justificatifs';

    public function handle(FiscalArchiveService $archives): int
    {
        try {
            $exercise = FiscalYear::query()->whereYear('starts_on', (int) $this->argument('year'))->firstOrFail();
            $archive = FiscalArchive::query()->where('fiscal_year_id', $exercise->id)->firstOrFail();
            $payload = $archives->read($archive);
            $documents = array_map(Document::depuisInstantane(...), $payload['documents']);
        } catch (Throwable $error) {
            $this->error('Le dossier n’a pas pu être relu et vérifié.');
            report($error);

            return self::FAILURE;
        }
        $this->info(sprintf('%s — %d éditions, %d lignes comptables et %d justificatifs vérifiés. Conservation jusqu’au %s.',
            $exercise->label, count($documents), count($payload['ledger']), count($payload['attachments']),
            $archive->retain_until->format('d/m/Y')));

        return self::SUCCESS;
    }
}
