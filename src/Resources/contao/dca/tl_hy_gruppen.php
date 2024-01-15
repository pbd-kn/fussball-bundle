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
 * Table tl_extcss
 */
$GLOBALS['TL_DCA']['tl_hy_gruppen'] = [
//        'ctable' => ['tl_extcss_file'],
    // Config
    'config' => [
        'dataContainer' => 'Table',
        'enableVersioning' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],

    // List
    'list' => [
        'sorting' => [
            'mode' => 1,
            'fields' => ['Name'],
            'flag' => 1,
        ],
        'label' => [
            'fields' => ['Name'],
            'format' => '%s',
        ],
        'global_operations' => [
            'all' => [
                'label' => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href' => 'act=select',
                'class' => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
            ],
        ],
        'operations' => [
            'edit' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_gruppen']['edit'],
//                'href' => 'table=tl_extcss_file',
                'icon' => 'edit.gif',
            ],
            'editheader' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_gruppen']['editheader'],
                'href' => 'act=edit',
                'icon' => 'header.gif',
            ],
            'copy' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_gruppen']['copy'],
                'href' => 'act=copy',
                'icon' => 'copy.gif',
            ],
            'delete' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_gruppen']['delete'],
                'href' => 'act=delete',
                'icon' => 'delete.gif',
                'attributes' => 'onclick="if(!confirm(\''.'Loeschen??'.'\'))return false;Backend.getScrollOffset()"',
            ],
            'show' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_gruppen']['show'],
                'href' => 'act=show',
                'icon' => 'show.gif',
            ],
        ],
    ],


    'palettes' => [
        '__selector__' => array('Wettbewerb','Gruppe','M1','Platz','Spiele','Sieg','Unentschieden','Niederlage','Tore','Gegentore','Differenz','Punkte' ),
		'default' => '{title_legend},Wettbewerb;Gruppe;M1;Platz;Spiele;Sieg;Unentschieden;Niederlage;Tore;Gegentore;Differenz;Punkte;'
    ],
    // Fields
    'fields' => [
        'ID' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'Wettbewerb' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_gruppen']['Wettbewerb'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'Gruppe' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_gruppen']['Gruppe'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'M1' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_gruppen']['M1'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'Platz' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_gruppen']['Platz'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'Spiele' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_gruppen']['Spiele'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'Sieg' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_gruppen']['Sieg'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'Unentschieden' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_gruppen']['Unentschieden'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'Niederlage' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_gruppen']['Niederlage'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'Tore' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_gruppen']['Tore'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'Gegentore' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_gruppen']['Gegentore'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'Differenz' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_gruppen']['Differenz'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'Punkte' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_gruppen']['Punkte'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
    ],
];

