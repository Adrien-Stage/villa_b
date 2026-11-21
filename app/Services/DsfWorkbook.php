<?php

namespace App\Services;

use App\Models\FiscalArchive;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use RuntimeException;

class DsfWorkbook
{
    public const HEADER = ['feuille', 'cellule', 'prefixe_compte', 'valeur'];

    public function __construct(
        private readonly AccountingTableReader $reader,
        private readonly FiscalArchiveService $archives,
    ) {
    }

    public function fill(FiscalArchive $archive, UploadedFile $template, UploadedFile $mapping): Spreadsheet
    {
        $references = $this->reader->read($mapping, self::HEADER);
        $archiveDocuments = collect($this->archives->read($archive)['documents']);
        $balance = collect($archiveDocuments->firstWhere('titre', 'Balance générale')['lignes'] ?? []);
        if ($balance->isEmpty()) {
            throw new RuntimeException('La balance générale est absente de l’archive.');
        }
        $reader = new Xlsx();
        $path = $template->getRealPath();
        $sheets = $reader->listWorksheetInfo($path);
        if (count($sheets) > 100 || collect($sheets)->sum(fn ($sheet) => $sheet['totalRows'] * $sheet['totalColumns']) > 500_000) {
            throw new RuntimeException('Le modèle dépasse la taille prise en charge.');
        }
        $book = $reader->load($path);
        $usedCells = [];
        try {
            foreach ($references as $index => [$name, $cell, $prefix, $measure]) {
                $fn = match ($measure) {
                    'debit', 'credit', 'opening', 'closing' => fn ($row) => $row[$measure],
                    'closing_credit' => fn ($row) => -$row['closing'],
                    default => null,
                };
                $sheet = $book->getSheetByName($name);
                if (!$sheet || !$fn || !preg_match('/^[1-9][0-9]{0,19}$/', $prefix)
                    || !preg_match('/^[A-Z]{1,3}[1-9][0-9]{0,5}$/', $cell) || isset($usedCells[$name][$cell])) {
                    throw new RuntimeException('Mapping DSF invalide à la ligne ' . ($index + 2) . '.');
                }
                $target = $sheet->getCell($cell);
                if ($target->getValue() !== null && $target->getDataType() !== DataType::TYPE_NUMERIC) {
                    throw new RuntimeException("La cellule {$name}!{$cell} contient déjà une formule ou un libellé.");
                }
                $amount = $balance->filter(fn ($row) => str_starts_with((string) $row['code'], $prefix))->sum($fn);
                $target->setValueExplicit((int) round($amount / 100), DataType::TYPE_NUMERIC);
                $usedCells[$name][$cell] = true;
            }
        } catch (\Throwable $error) {
            $book->disconnectWorksheets();
            throw $error;
        }

        return $book;
    }
}
