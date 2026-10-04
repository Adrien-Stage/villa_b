{{--
    Mise en page commune des documents imprimés depuis le navigateur.
    À inclure dans la balise <style> du document : le partiel n'écrit que
    du CSS.

    Chrome ajoute son propre en-tête et son propre pied de page (titre et
    adresse de la page, date, « 1 sur 3 ») dès que la feuille a une marge
    haute ou basse. Ces deux marges sont donc nulles, et le blanc du haut
    et du bas est refait dans le document : le corps reçoit un rembourrage
    recopié en tête et en pied de chaque feuille (box-decoration-break).
    Le numéro de page s'écrit dans la marge de droite, et le texte de pied
    éventuel se répète au bas de chaque feuille.

    Paramètres, tous facultatifs :
      $format    taille de la feuille (« A4 portrait »)
      $haut      blanc en tête de chaque feuille (12mm)
      $bas       blanc au pied de chaque feuille (14mm)
      $cotes     marges gauche et droite (12mm)
      $pied      texte répété au bas de chaque feuille
      $numeroter numéro « 2 / 5 » dans la marge de droite (oui)
--}}
@php
    $format = $format ?? 'A4 portrait';
    $haut = $haut ?? '12mm';
    $bas = $bas ?? '14mm';
    $cotes = $cotes ?? '12mm';
    $numeroter = $numeroter ?? true;
    // Chaîne CSS : on échappe ce qui fermerait la chaîne ou la balise <style>.
    $pied = filled($pied ?? null)
        ? str_replace(['\\', '"', '<', "\r", "\n"], ['\\\\', '\\"', '\\3C ', ' ', ' '], (string) $pied)
        : null;
@endphp
        @page {
            size: {{ $format }};
            margin: 0 {{ $cotes }};
@if($numeroter)
            @right-bottom {
                content: counter(page) " / " counter(pages);
                vertical-align: bottom;
                padding-bottom: 6mm;
                font-family: Arial, Helvetica, sans-serif;
                font-size: 7pt;
                color: #666;
            }
@endif
        }

        @media print {
            body {
                margin: 0 !important;
                padding-top: {{ $haut }} !important;
                padding-bottom: {{ $bas }} !important;
                -webkit-box-decoration-break: clone;
                box-decoration-break: clone;
            }
@if($pied)

            body::after {
                content: "{!! $pied !!}";
                position: fixed;
                left: 0;
                right: 0;
                bottom: 6mm;
                font-family: Arial, Helvetica, sans-serif;
                font-size: 7pt;
                color: #666;
            }
@endif
        }
