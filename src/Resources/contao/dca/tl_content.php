<?php

declare(strict_types=1);

// Keep the persisted element types identical in Contao 4.13 and 5.7.
$types = [
    'AnzeigeEndstand',
    'AnzeigeGruppen',
    'TeilnehmerPunkte',
    'AnzeigeRegel',
    'AnzeigeSpiele',
    'AnzeigeUebersicht',
    'AnzeigeWetten',
    'Orte',
    'Spiele',
    'Gruppen verwalten manuell',
    'Mannschaften',
    'Nation',
    'Teilnehmer',
    'Wetten',
    'Wettbewerbe',
];

foreach ($types as $type) {
    $GLOBALS['TL_DCA']['tl_content']['palettes'][$type] = '{type_legend},type,headline;{template_legend:hide},customTpl;{protected_legend:hide},protected;{expert_legend:hide},cssID;{invisible_legend:hide},invisible,start,stop';
}
