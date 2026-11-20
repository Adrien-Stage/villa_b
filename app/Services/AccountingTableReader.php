<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetReadException;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use RuntimeException;

class AccountingTableReader
{
    /** @return list<list<mixed>> */
    public function read(UploadedFile $file, array $header): array
    {
        $rows = strtolower($file->getClientOriginalExtension()) === 'xlsx'
            ? $this->xlsx($file, count($header)) : $this->csv($file);
        $first = array_shift($rows) ?? [];
        if (isset($first[0]) && is_string($first[0])) {
            $first[0] = preg_replace('/^\x{FEFF}/u', '', $first[0]);
        }
        if ($first !== $header || $rows === []) {
            throw new RuntimeException('En-têtes attendus : ' . implode(' ; ', $header) . '. Le fichier doit contenir au moins une ligne.');
        }
        foreach ($rows as $index => &$row) {
            if (count($row) !== count($header)) {
                throw new RuntimeException('Nombre de colonnes incorrect à la ligne ' . ($index + 2) . '.');
            }
            $row = array_map(function ($value) {
                if ($value === null) {
                    return '';
                }
                if (!is_scalar($value) || !mb_check_encoding((string) $value, 'UTF-8')) {
                    throw new RuntimeException('Le fichier doit contenir du texte UTF-8 ou des nombres.');
                }

                return trim((string) $value);
            }, $row);
        }

        return $rows;
    }

    private function csv(UploadedFile $file): array
    {
        $stream = fopen($file->getRealPath(), 'rb');
        $rows = [];
        try {
            while (($row = fgetcsv($stream, 0, ';', '"', '')) !== false) {
                if ($row === [null]) {
                    continue;
                }
                $rows[] = $row;
                if (count($rows) > 5001) {
                    throw new RuntimeException('Limitez le fichier à 5 000 lignes.');
                }
            }
        } finally {
            fclose($stream);
        }

        return $rows;
    }

    private function xlsx(UploadedFile $file, int $columns): array
    {
        $reader = new Xlsx();
        $path = $file->getRealPath();
        try {
            $sheets = $reader->listWorksheetInfo($path);
            if (count($sheets) !== 1 || $sheets[0]['totalRows'] > 5001 || $sheets[0]['totalColumns'] !== $columns) {
                throw new RuntimeException("Utilisez une feuille unique de 5 000 lignes au maximum.");
            }
            $workbook = $reader->setReadDataOnly(true)->load($path);
            try {
                return $workbook->getActiveSheet()->toArray(null, false, false);
            } finally {
                $workbook->disconnectWorksheets();
            }
        } catch (SpreadsheetReadException $error) {
            throw new RuntimeException('Le classeur est illisible.', previous: $error);
        }
    }
}
