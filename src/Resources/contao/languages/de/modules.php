<?php

declare(strict_types=1);

/*
 *
 *  Contao Open Source CMS
 *
 *  Copyright (c) 2005-2014 Leo Feyer
 *
 *
 *  Contao Open Source CMS
 *
 *  Copyright (C) 2005-2013 Leo Feyer
 *   @package   Extassets
 *   @author    r.kaltofen@heimrich-hannot.de
 *   @license   GNU/LGPL
 *   @copyright Heimrich & Hannot GmbH
 *
 *  The namespaces for psr-4 were revised.
 *
 *  @package   contao-extasset-bundle
 *  @author    Peter Broghammer <pb-contao@gmx.de>
 *  @license   http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 *  @copyright Peter Broghammer 2021-
 *
 *  Bootstrap's selection introduced.
 *
 */

/*
 * Back end modules
 */
$GLOBALS['TL_LANG']['MOD']['hy_config'] = 'Konfiguration Fussball';

$GLOBALS['TL_LANG']['MOD'][\PBDKN\FussballBundle\Backend\BackendModules::GROUP] = 'Fußball';
foreach (\PBDKN\FussballBundle\Admin\AdminSchema::ENTITIES as $entity => $schema) {
    $GLOBALS['TL_LANG']['MOD'][\PBDKN\FussballBundle\Backend\BackendModules::moduleName($entity)] = [$schema['title'], $schema['title'].' im Fußball-Tippspiel verwalten.'];
}

/*
 * Front end modules
 */
$GLOBALS['TL_LANG']['FMD'][''] = ['', ''];
