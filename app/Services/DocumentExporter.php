<?php

namespace App\Services;

use App\Support\Document\Colonne;
use App\Support\Document\Document;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rend un Document dans le format demandé.
 *
 * Un seul endroit pour les quatre sorties : l'aperçu imprimable, le PDF, le
 * tableur et le traitement de texte. Chaque écran décrit son document, aucun
 * ne décrit sa mise en page — sans quoi l'établissement sort des papiers qui
 * ne se ressemblent pas, et corriger un en-tête demande de passer partout.
 */
class DocumentExporter
{
    public const FORMAT_IMPRESSION = 'impression';
    public const FORMAT_PDF        = 'pdf';
    public const FORMAT_EXCEL      = 'excel';
    public const FORMAT_WORD       = 'word';

    /** Formats proposés à l'écran, dans l'ordre d'affichage. */
    public const FORMATS = [
        self::FORMAT_IMPRESSION => 'Imprimer',
        self::FORMAT_PDF        => 'PDF',
        self::FORMAT_EXCEL      => 'Excel',
        self::FORMAT_WORD       => 'Word',
    ];

    public static function formatValide(?string $format): bool
    {
        return $format !== null && array_key_exists($format, self::FORMATS);
    }

    public function rendre(Document $document, string $format): Response|StreamedResponse
    {
        return match ($format) {
            self::FORMAT_PDF   => $this->pdf($document),
            self::FORMAT_EXCEL => $this->excel($document),
            self::FORMAT_WORD  => $this->word($document),
            // L'aperçu imprimable est la même page que le PDF : ce que
            // l'utilisateur voit à l'écran est ce qui sortira de l'imprimante.
            default            => $this->impression($document),
        };
    }

    /** Page autonome, qui déclenche l'impression à l'ouverture. */
    private function impression(Document $document): Response
    {
        $html = view('documents.base', ['document' => $document, 'pourPdf' => false])->render();

        // Injecté après le rendu plutôt que placé dans le gabarit : le PDF
        // partage ce gabarit et n'a pas à embarquer de script.
        $html = str_replace(
            '</body>',
            "<script>window.addEventListener('load', () => window.print());</script></body>",
            $html
        );

        return response($html);
    }

    private function pdf(Document $document): Response
    {
        $pdf = Pdf::loadView('documents.base', ['document' => $document, 'pourPdf' => true])
            ->setPaper('a4', $this->orientation($document));

        return $pdf->download($document->nomDeFichier('pdf'));
    }

    /**
     * Au-delà de six colonnes, le portrait écrase le texte : on bascule en
     * paysage plutôt que de rendre un tableau illisible.
     */
    private function orientation(Document $document): string
    {
        return count($document->lesColonnes()) > 6 ? 'landscape' : 'portrait';
    }

    private function excel(Document $document): StreamedResponse
    {
        $classeur = new Spreadsheet();
        $feuille  = $classeur->getActiveSheet();
        $feuille->setTitle(mb_substr($document->titre, 0, 31));

        $etab   = $document->enTeteEtablissement();
        $devise = $etab['devise'] ?? 'FCFA';
        $colonnes = $document->lesColonnes();
        $derniere = max(1, count($colonnes));
        $ligne = 1;

        // En-tête : la même information que sur le papier, pour qu'un fichier
        // détaché de son écran reste interprétable.
        $feuille->setCellValue([1, $ligne], $etab['nom'] ?? 'Établissement');
        $feuille->getStyle([1, $ligne])->getFont()->setBold(true)->setSize(14);
        $ligne++;

        $feuille->setCellValue([1, $ligne++], $document->titre);
        foreach (array_filter([$document->leSousTitre(), $document->laPeriode()]) as $texte) {
            $feuille->setCellValue([1, $ligne++], $texte);
        }
        foreach ($document->lesFiltres() as $libelle => $valeur) {
            $feuille->setCellValue([1, $ligne++], $libelle . ' : ' . $valeur);
        }
        $feuille->setCellValue([1, $ligne++], 'Édité le ' . now()->format('d/m/Y à H:i')
            . ' par ' . (auth()->user()?->name ?? '—'));
        $ligne++;

        $ligneEntete = $ligne;
        foreach ($colonnes as $index => $colonne) {
            $feuille->setCellValue([$index + 1, $ligne], $colonne->libelle);
        }

        $feuille->getStyle([1, $ligne, $derniere, $ligne])->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'EED4A3']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '391F0E']],
        ]);
        $ligne++;

        foreach ($document->lesLignes() as $donnee) {
            foreach ($colonnes as $index => $colonne) {
                // Valeur brute : un montant doit rester un nombre dans un
                // tableur, sinon aucune somme n'est possible.
                $feuille->setCellValue([$index + 1, $ligne], $colonne->valeurBrute($document->valeur($donnee, $colonne)));
            }
            $ligne++;
        }

        if ($document->lesTotaux() !== []) {
            $feuille->setCellValue([1, $ligne], 'Total (' . $document->lesLignes()->count() . ' ligne(s))');
            foreach ($colonnes as $index => $colonne) {
                if (array_key_exists($colonne->cle, $document->lesTotaux())) {
                    $feuille->setCellValue([$index + 1, $ligne], $colonne->valeurBrute($document->lesTotaux()[$colonne->cle]));
                }
            }
            $feuille->getStyle([1, $ligne, $derniere, $ligne])->getFont()->setBold(true);
        }

        foreach ($colonnes as $index => $colonne) {
            $lettre = $feuille->getCell([$index + 1, $ligneEntete])->getColumn();
            $feuille->getColumnDimension($lettre)->setAutoSize(true);

            if ($colonne->alignementDroite()) {
                $feuille->getStyle($lettre . ($ligneEntete + 1) . ':' . $lettre . $ligne)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            if ($colonne->type === Colonne::MONTANT) {
                $feuille->getStyle($lettre . ($ligneEntete + 1) . ':' . $lettre . $ligne)
                    ->getNumberFormat()->setFormatCode('# ##0 "' . $devise . '"');
            }
        }

        $feuille->freezePane('A' . ($ligneEntete + 1));

        return $this->flux($document->nomDeFichier('xlsx'),
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            fn () => (new Xlsx($classeur))->save('php://output'));
    }

    private function word(Document $document): StreamedResponse
    {
        $etab   = $document->enTeteEtablissement();
        $devise = $etab['devise'] ?? 'FCFA';
        $colonnes = $document->lesColonnes();

        $word = new PhpWord();
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(9);

        $section = $word->addSection(count($colonnes) > 6
            ? ['orientation' => 'landscape', 'marginTop' => 700, 'marginBottom' => 700]
            : ['marginTop' => 700, 'marginBottom' => 700]);

        $section->addText($etab['nom'] ?? 'Établissement', ['bold' => true, 'size' => 14, 'color' => '391F0E']);
        foreach (array_filter([$etab['adresse'] ?? null, $etab['tel'] ?? null]) as $ligneEtab) {
            $section->addText($ligneEtab, ['size' => 8, 'color' => '6B5744']);
        }
        $section->addTextBreak();

        $section->addText($document->titre, ['bold' => true, 'size' => 13, 'color' => '391F0E']);
        foreach (array_filter([$document->leSousTitre(), $document->laPeriode()]) as $texte) {
            $section->addText($texte, ['size' => 9, 'color' => '6B5744']);
        }
        foreach ($document->lesFiltres() as $libelle => $valeur) {
            $section->addText($libelle . ' : ' . $valeur, ['size' => 8, 'color' => '6B5744']);
        }
        $section->addTextBreak();

        if ($document->estVide()) {
            $section->addText('Aucune donnée pour ces critères.', ['italic' => true, 'color' => 'A3917E']);
        } else {
            $tableau = $section->addTable([
                'borderSize' => 6, 'borderColor' => 'ECE3D6', 'cellMargin' => 60, 'width' => 100 * 50,
                'unit' => 'pct',
            ]);

            $tableau->addRow();
            foreach ($colonnes as $colonne) {
                $tableau->addCell(null, ['bgColor' => '391F0E'])
                    ->addText($colonne->libelle, ['bold' => true, 'color' => 'EED4A3', 'size' => 8]);
            }

            foreach ($document->lesLignes() as $donnee) {
                $tableau->addRow();
                foreach ($colonnes as $colonne) {
                    $tableau->addCell()->addText(
                        $colonne->formater($document->valeur($donnee, $colonne), $devise),
                        [],
                        ['alignment' => $colonne->alignementDroite() ? Jc::END : Jc::START]
                    );
                }
            }

            if ($document->lesTotaux() !== []) {
                $tableau->addRow();
                foreach ($colonnes as $index => $colonne) {
                    $total = $document->lesTotaux()[$colonne->cle] ?? null;

                    if ($index === 0) {
                        $texte = 'Total — ' . $document->lesLignes()->count() . ' ligne(s)';
                    } else {
                        $texte = $total === null ? '' : $colonne->formater($total, $devise);
                    }

                    $tableau->addCell(null, ['bgColor' => 'FAF5EE'])
                        ->addText($texte, ['bold' => true, 'color' => '391F0E'],
                            ['alignment' => $colonne->alignementDroite() ? Jc::END : Jc::START]);
                }
            }
        }

        $section->addTextBreak();
        $section->addText('Édité le ' . now()->format('d/m/Y à H:i') . ' par ' . (auth()->user()?->name ?? '—'),
            ['size' => 7, 'color' => 'A3917E']);

        return $this->flux($document->nomDeFichier('docx'),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            fn () => \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007')->save('php://output'));
    }

    private function flux(string $nom, string $type, callable $ecrire): StreamedResponse
    {
        return response()->streamDownload($ecrire, $nom, [
            'Content-Type'  => $type,
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
