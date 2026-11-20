<a href="{{ route('editions.dossier.print', $selection) }}" target="_blank" rel="noopener"
   class="{{ $bouton }}" aria-label="Imprimer {{ $contexte }}">Imprimer</a>
@droit('editions.export')
    <a href="{{ route('editions.dossier.export', $selection + ['format' => 'pdf']) }}"
       class="{{ $bouton }}" aria-label="Télécharger {{ $contexte }} en PDF">PDF</a>
    <a href="{{ route('editions.dossier.export', $selection + ['format' => 'excel']) }}"
       class="{{ $bouton }}" aria-label="Télécharger {{ $contexte }} en Excel">Excel</a>
@enddroit
