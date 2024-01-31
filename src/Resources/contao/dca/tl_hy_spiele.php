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
 * Table tl_hy_spiele
 */
$GLOBALS['TL_DCA']['tl_hy_spiele'] = [
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
                'label' => &$GLOBALS['TL_LANG']['tl_hy_spiele']['edit'],
//                'href' => 'table=tl_extcss_file',
                'icon' => 'edit.gif',
            ],
            'editheader' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_spiele']['editheader'],
                'href' => 'act=edit',
                'icon' => 'header.gif',
            ],
            'copy' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_spiele']['copy'],
                'href' => 'act=copy',
                'icon' => 'copy.gif',
            ],
            'delete' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_spiele']['delete'],
                'href' => 'act=delete',
                'icon' => 'delete.gif',
                'attributes' => 'onclick="if(!confirm(\''.'Loeschen??'.'\'))return false;Backend.getScrollOffset()"',
            ],
            'show' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_spiele']['show'],
                'href' => 'act=show',
                'icon' => 'show.gif',
            ],
        ],
    ],


    'palettes' => [
        '__selector__' => array('Wettbewerb','Nr','Gruppe','M1','M2','Ort','Datum','Uhrzeit','T1','T2','Link' ),
		'default' => '{title_legend},Wettbewerb;Nr;Gruppe;M1;M2;Ort;Datum;Uhrzeit;T1;T2;Link;'
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
            'label' => &$GLOBALS['TL_LANG']['tl_hy_spiele']['Wettbewerb'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'Nr' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_orte']['Nr'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "int  NOT NULL default -1",
        ],
        'Gruppe' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_orte']['Gruppe'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'M1' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_orte']['M1'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "int  NOT NULL default -1",
        ],
        'M2' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_orte']['M2'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "int(10)  NOT NULL default -1",
        ],
        'Ort' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_orte']['Ort'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "int  NOT NULL default -1",
        ],
        'Datum' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_orte']['Datum'],
            'exclude' => true,
            'inputType' => 'text',
            'sql' => "date",
        ],
        'Uhrzeit' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_orte']['Uhrzeit'],
            'exclude' => true,
            'inputType' => 'text',
            'sql' => "time",
        ],
        'T1' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_orte']['T1'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true],
            'sql' => "int  NOT NULL default -1",
        ],
        'T2' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_orte']['T2'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true],
            'sql' => "int NOT NULL default -1",
        ],
        'Link' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_orte']['Link'],
            'exclude' => true,
            'inputType' => 'text',
            'sql' => "varchar(255) default ''",
        ],
    ],
];

