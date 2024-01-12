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
 * Fields
 */
$GLOBALS['TL_LANG']['tl_extcss']['title'] = ['Title', 'Please enter a title.'];
$GLOBALS['TL_LANG']['tl_extcss']['addBootstrapPrint'] = ['Enable print.css', 'Enable twitter bootstrap print.css support.'];
$GLOBALS['TL_LANG']['tl_extcss']['variablesSRC'] = ['Variable sources', 'If global variables, such as the bootstrap variables should be overwritten, add the files here.'];
$GLOBALS['TL_LANG']['tl_extcss']['variablesOrderSRC'] = ['Sort order ', 'Sort order of the variables sources.'];
$GLOBALS['TL_LANG']['tl_extcss']['observeFolderSRC'] = ['Observer folder', 'Specify a folder to be monitored, and new files are added automatically.'];
$GLOBALS['TL_LANG']['tl_extcss']['addElegantIcons'] = ['Add elegant icons', 'Add Elegant Icon Font to group.'];
$GLOBALS['TL_LANG']['tl_extcss']['addingbootstrap'] = ['Add Bootstrap', 'Add Bootstrap from '.BOOTSTRAPDISTDIR.'css/bootstrap.min.css. Please clear less cache. Bootstrap javascript please add in extJs and enable extJs in layout'];
$GLOBALS['TL_LANG']['tl_extcss']['addFontAwesome'] = array('Font Awesome no longer supported', 'Using bundle contao-tinymce-plugin-bundle');
$GLOBALS['TL_LANG']['tl_extcss']['setDebug'] = ['set Debug', 'write Debug to var/logs/prod-[Date]-extasset_debug.log'];

/*
 * Legends
 */
$GLOBALS['TL_LANG']['tl_extcss']['title_legend'] = 'Title';
$GLOBALS['TL_LANG']['tl_extcss']['config_legend'] = 'Configuration';
$GLOBALS['TL_LANG']['tl_extcss']['font_legend'] = 'Icon-Fonts';
$GLOBALS['TL_LANG']['tl_extcss']['bootstrap_legend'] = 'bootstrap config';
$GLOBALS['TL_LANG']['tl_extcss']['less_legend'] = 'lessfiles config';




/*
 * Buttons
 */
$GLOBALS['TL_LANG']['tl_extcss']['new'] = ['New group', 'Create a new css group.'];
$GLOBALS['TL_LANG']['tl_extcss']['show'] = ['Group details', 'Show group ID %s details'];
$GLOBALS['TL_LANG']['tl_extcss']['edit'] = ['Edit group', 'Edit group ID %s'];
$GLOBALS['TL_LANG']['tl_extcss']['editheader'] = ['Edit group-settings', 'Edit group ID %s settings'];
$GLOBALS['TL_LANG']['tl_extcss']['cut'] = ['Move group', 'Move group ID %s'];
$GLOBALS['TL_LANG']['tl_extcss']['copy'] = ['Copy group ', 'Copy group ID %s'];
$GLOBALS['TL_LANG']['tl_extcss']['delete'] = ['Delete group', 'Delete group ID %s'];
