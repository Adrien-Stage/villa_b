<?php

namespace App\Http\Controllers;

use App\Models\FiscalArchive;
use App\Models\FiscalYear;
use App\Services\DocumentExporter;
use App\Services\FiscalArchiveService;
use App\Services\FiscalStatements;
use App\Services\LedgerBooks;
use App\Support\Document\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class LedgerBooksController extends Controller
{
    public function __construct(
        private readonly LedgerBooks $books,
        private readonly FiscalStatements $statements,
        private readonly FiscalArchiveService $archives,
        private readonly DocumentExporter $exporter,
    ) {
    }

    public function index(Request $request)
    {
        $years = FiscalYear::query()->orderByDesc('starts_on')->get();
        $selected = $request->filled('year') ? $years->firstWhere('id', $request->integer('year')) : $years->first();
        $archive = $selected ? FiscalArchive::query()->where('fiscal_year_id', $selected->id)
            ->first(['id', 'regime', 'retain_until', 'created_at']) : null;
        $flows = $selected && !$archive
            ? collect($this->statements->build($selected, 'normal'))->last()->lesLignes() : collect();

        return response()->view('accounting.ledger.statutory', compact('years', 'selected', 'archive', 'flows'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['books', 'annual', 'fiscal'])],
            'format' => ['required', Rule::in(['csv', 'excel', 'pdf'])],
            'year' => ['required_if:kind,annual', 'nullable', 'exists:fiscal_years,id'],
            'regime' => ['nullable', Rule::in(array_keys(FiscalStatements::REGIMES))],
            'from' => ['required_unless:kind,annual', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_unless:kind,annual', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        if ($data['kind'] === 'annual') {
            $year = FiscalYear::query()->findOrFail($data['year']);
            $archive = FiscalArchive::query()->where('fiscal_year_id', $year->id)->first();
            $documents = $archive ? array_map(Document::depuisInstantane(...), $this->archives->read($archive)['documents'])
                : $this->statements->build($year, $data['regime'] ?? 'normal');

            return $this->exporter->rendreDossier(
                ($archive ? 'Arrêté comptable ' : 'Projet d’arrêté ') . $year->starts_on->year,
                $documents,
                $data['format'] === 'csv' ? 'excel' : $data['format'],
            )->header('Cache-Control', 'private, no-store');
        }

        $from = Carbon::parse($data['from']);
        $to = Carbon::parse($data['to']);
        if ($from->year !== $to->year) {
            throw ValidationException::withMessages(['to' => 'Sélectionnez une période au sein d’un même exercice.']);
        }
        if ($data['kind'] === 'fiscal') {
            $documents = app(\App\Services\PeriodicTaxReport::class)->documents($from, $to);

            return $this->exporter->rendreDossier('États préparatoires fiscaux', $documents,
                $data['format'] === 'csv' ? 'excel' : $data['format'])->header('Cache-Control', 'private, no-store');
        }
        if ($data['format'] === 'csv') {
            return $this->exportCsv($from, $to);
        }

        return $this->exporter->rendreDossier('Livres comptables', $this->books->documents($from, $to), $data['format'])
            ->header('Cache-Control', 'private, no-store');
    }

    public function close(Request $request, FiscalYear $year)
    {
        $data = $request->validate([
            'regime' => ['required', Rule::in(array_keys(FiscalStatements::REGIMES))],
            'notes' => ['required', 'string', 'max:20000'],
            'evidence' => ['required', 'array', 'min:1', 'max:5'],
            'evidence.*' => ['required', 'file', 'mimes:pdf,csv,txt,xlsx', 'max:4096'],
            'flows' => ['nullable', 'array'],
            'flows.*' => ['nullable', Rule::in(['Exploitation', 'Investissement', 'Financement'])],
        ]);

        try {
            $this->archives->close($year, $data['regime'], $data['notes'], $data['evidence'],
                array_filter($data['flows'] ?? []));
        } catch (RuntimeException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('accounting.ledger.statutory.index', ['year' => $year->id])
            ->with('success', 'L’arrêté et les justificatifs ont été archivés.');
    }

    public function dsf(Request $request, FiscalYear $year, \App\Services\DsfWorkbook $dsf)
    {
        $data = $request->validate([
            'template' => ['required', 'file', 'mimes:xlsx', 'max:4096'],
            'mapping' => ['required', 'file', 'mimes:csv,txt', 'max:512'],
        ]);
        $archive = FiscalArchive::query()->where('fiscal_year_id', $year->id)->firstOrFail();
        try {
            $book = $dsf->fill($archive, $data['template'], $data['mapping']);
        } catch (\PhpOffice\PhpSpreadsheet\Exception | RuntimeException $error) {
            return back()->with('error', 'Le projet DSF n’a pas été produit. ' . $error->getMessage());
        }

        return response()->streamDownload(function () use ($book) {
            try {
                (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))
                    ->setPreCalculateFormulas(false)->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, "projet-dsf-{$archive->regime}-{$year->starts_on->year}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function exportCsv(Carbon $from, Carbon $to)
    {
        return response()->streamDownload(function () use ($from, $to) {
            $stream = fopen('php://output', 'w');
            $fields = [
                'number', 'line_id', 'date', 'value_date', 'journal', 'reference', 'account', 'label',
                'debit', 'credit', 'center', 'source_type', 'source_id', 'auxiliary_type', 'auxiliary_id',
                'validated_at', 'validated_by', 'reverses', 'reversed_by',
            ];
            fputcsv($stream, array_map(fn ($field) => match ($field) {
                'debit', 'credit' => $field . '_centimes_xaf',
                default => $field,
            }, $fields), ';', '"', '');
            foreach ($this->books->lines($from, $to) as $line) {
                fputcsv($stream, array_map(function ($field) use ($line) {
                    $value = $line[$field];
                    return is_string($value) && preg_match('/^[\s]*[=+@-]/u', $value) ? "'" . $value : $value;
                }, $fields), ';', '"', '');
            }
            fclose($stream);
        }, "livre-journal-{$from->toDateString()}-{$to->toDateString()}.csv", [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
