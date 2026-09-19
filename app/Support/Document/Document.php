<?php

namespace App\Support\Document;

use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Un document imprimable : ce qu'on met sur le papier, pas comment on l'y met.
 *
 * Tout écran qui doit sortir une liste — demandes à l'économat, journal des
 * encaissements, inventaire, en-cours — décrit son document ici, et le même
 * rendu s'occupe de l'impression, du PDF, du tableur et du traitement de
 * texte. Sans cela, chaque module réinvente son en-tête, son pied de page et
 * son formatage des montants, et l'établissement sort des papiers qui ne se
 * ressemblent pas.
 *
 * L'objet ne sait rien du format de sortie : c'est DocumentExporter qui rend.
 */
class Document
{
    /** @var list<Colonne> */
    private array $colonnes = [];

    private Collection $lignes;

    private ?string $sousTitre = null;
    private ?string $periode = null;
    private array $filtres = [];
    private array $totaux = [];
    private ?string $note = null;

    private function __construct(public readonly string $titre)
    {
        $this->lignes = collect();
    }

    public static function intitule(string $titre): self
    {
        return new self($titre);
    }

    public function sousTitre(?string $texte): self
    {
        $this->sousTitre = $texte;

        return $this;
    }

    /** Période couverte, telle qu'elle s'imprime sous le titre. */
    public function periode(?Carbon $debut, ?Carbon $fin): self
    {
        if ($debut && $fin) {
            $this->periode = 'Période du ' . $debut->format('d/m/Y') . ' au ' . $fin->format('d/m/Y');
        }

        return $this;
    }

    /**
     * Filtres appliqués, imprimés en en-tête.
     *
     * Un tableau sans ses filtres ne se relit pas : six mois plus tard,
     * personne ne sait si « 12 demandes » vaut pour le mois ou pour l'année.
     *
     * @param  array<string, string|null>  $filtres  libellé => valeur
     */
    public function filtres(array $filtres): self
    {
        $this->filtres = array_filter($filtres, fn ($v) => $v !== null && $v !== '');

        return $this;
    }

    /** @param  list<Colonne>  $colonnes */
    public function colonnes(array $colonnes): self
    {
        $this->colonnes = $colonnes;

        return $this;
    }

    public function lignes(iterable $lignes): self
    {
        $this->lignes = collect($lignes);

        return $this;
    }

    /**
     * Totaux explicites, par clé de colonne.
     *
     * Non calculés d'office : un total de quantités mélangeant kilos et pièces
     * n'a aucun sens, et l'écran seul sait lequel en a un.
     */
    public function totaux(array $totaux): self
    {
        $this->totaux = $totaux;

        return $this;
    }

    public function note(?string $texte): self
    {
        $this->note = $texte;

        return $this;
    }

    // ── Lecture ──────────────────────────────────────────────────────────────

    /** @return list<Colonne> */
    public function lesColonnes(): array
    {
        return $this->colonnes;
    }

    public function lesLignes(): Collection
    {
        return $this->lignes;
    }

    public function leSousTitre(): ?string
    {
        return $this->sousTitre;
    }

    public function laPeriode(): ?string
    {
        return $this->periode;
    }

    public function lesFiltres(): array
    {
        return $this->filtres;
    }

    public function lesTotaux(): array
    {
        return $this->totaux;
    }

    public function laNote(): ?string
    {
        return $this->note;
    }

    public function estVide(): bool
    {
        return $this->lignes->isEmpty();
    }

    /**
     * Valeur d'une cellule, chemin pointé accepté : « requestedBy.name ».
     */
    public function valeur(mixed $ligne, Colonne $colonne): mixed
    {
        return data_get($ligne, $colonne->cle);
    }

    /** Nom de fichier sûr, horodaté, dérivé du titre. */
    public function nomDeFichier(string $extension): string
    {
        $base = \Illuminate\Support\Str::slug($this->titre) ?: 'document';

        return $base . '_' . now()->format('Ymd_His') . '.' . $extension;
    }

    /**
     * Identité de l'établissement, telle qu'elle s'imprime en en-tête.
     *
     * Les champs absents sont omis plutôt que remplacés par un texte de
     * substitution : un en-tête qui affiche « Adresse non renseignée » sur un
     * document remis à un client fait mauvais effet.
     */
    public function enTeteEtablissement(): array
    {
        $tenant = auth()->user()?->tenant ?? Tenant::first();

        return array_filter([
            'nom'     => $tenant?->name,
            'adresse' => $tenant?->address,
            'tel'     => $tenant?->phone,
            'email'   => $tenant?->email,
            'devise'  => $tenant?->currency ?: 'FCFA',
            'logo'    => !empty($tenant?->settings['logo'])
                ? storage_path('app/public/' . $tenant->settings['logo'])
                : null,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
