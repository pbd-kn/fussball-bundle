<?php

use PBDKN\FussballBundle\Backend\BackendModules;

$GLOBALS['TL_LANG']['MOD'][BackendModules::GROUP] = 'Football';
$labels = ['wettbewerb' => 'Competitions', 'mannschaft' => 'Teams', 'nation' => 'Nations', 'ort' => 'Venues', 'spiel' => 'Matches', 'gruppe' => 'Groups', 'wette' => 'Bets', 'teilnehmer' => 'Participants'];
foreach ($labels as $entity => $label) {
    $GLOBALS['TL_LANG']['MOD'][BackendModules::moduleName($entity)] = [$label, 'Manage '.$label.' in the football prediction game.'];
}
