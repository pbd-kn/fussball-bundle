<?php

use PBDKN\FussballBundle\Backend\BackendModules;
use PBDKN\FussballBundle\EventListener\FrontendStyles;

$GLOBALS['BE_MOD'][BackendModules::GROUP] = BackendModules::definitions();
$GLOBALS['TL_HOOKS']['generatePage'][] = [FrontendStyles::class, 'onGeneratePage'];
