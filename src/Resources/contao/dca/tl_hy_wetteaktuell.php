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
$GLOBALS['TL_DCA']['tl_hy_wetteaktuell'] = [
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
                'label' => &$GLOBALS['TL_LANG']['tl_hy_wetteaktuell']['edit'],
//                'href' => 'table=tl_extcss_file',
                'icon' => 'edit.gif',
            ],
            'editheader' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_wetteaktuell']['editheader'],
                'href' => 'act=edit',
                'icon' => 'header.gif',
            ],
            'copy' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_wetteaktuell']['copy'],
                'href' => 'act=copy',
                'icon' => 'copy.gif',
            ],
            'delete' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_wetteaktuell']['delete'],
                'href' => 'act=delete',
                'icon' => 'delete.gif',
                'attributes' => 'onclick="if(!confirm(\''.'Loeschen??'.'\'))return false;Backend.getScrollOffset()"',
            ],
            'show' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_wetteaktuell']['show'],
                'href' => 'act=show',
                'icon' => 'show.gif',
            ],
        ],
    ],


    'palettes' => [
        '__selector__' => array('Wettbewerb','Teilnehmer','Wette','W1','W2','W3'),
		'default' => '{title_legend},Wettbewerb;Teilnehmer;Wette;W1;W2;W3;'
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
            'label' => &$GLOBALS['TL_LANG']['tl_hy_wetteaktuell']['Wettbewerb'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'Teilnehmer' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_wetteaktuell']['Teilnehmer'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 64],
            'sql' => "int  NOT NULL default '-1'",
        ],
        'Wette' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_wetteaktuell']['Wette'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 64],
            'sql' => "int  NOT NULL default '-1'",
        ],
        'W1' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_wetteaktuell']['W1'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 64],
            'sql' => "int  NOT NULL default '-1'",
        ],
        'W2' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_wetteaktuell']['W2'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 64],
            'sql' => "int  NOT NULL default '-1'",
        ],
        'W3' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_wetteaktuell']['W3'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['maxlength' => 64],
            'sql' => "int  NOT NULL default '-1'",
        ],
    ],
];

