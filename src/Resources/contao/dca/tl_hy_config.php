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
$GLOBALS['TL_DCA']['tl_hy_config'] = [
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
                'label' => &$GLOBALS['TL_LANG']['tl_hy_config']['edit'],
//                'href' => 'table=tl_extcss_file',
                'icon' => 'edit.gif',
            ],
            'editheader' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_config']['editheader'],
                'href' => 'act=edit',
                'icon' => 'header.gif',
            ],
            'copy' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_config']['copy'],
                'href' => 'act=copy',
                'icon' => 'copy.gif',
            ],
            'delete' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_config']['delete'],
                'href' => 'act=delete',
                'icon' => 'delete.gif',
                'attributes' => 'onclick="if(!confirm(\''.'Loeschen??'.'\'))return false;Backend.getScrollOffset()"',
            ],
            'show' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_config']['show'],
                'href' => 'act=show',
                'icon' => 'show.gif',
            ],
        ],
    ],


    'palettes' => [
        '__selector__' => array('Name','aktuell','value1','value2','value3','value3','value5'),
		'default' => '{title_legend},Name;aktuell,value1;value2;value3;value4;value5;setDebug;'
    ],
    // Fields
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'Name' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_config']['Name'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'aktuell' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_config']['aktuell'],
            'sql' => 'int(10) unsigned NOT NULL',
        ],
        'value1' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_config']['value1'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'value2' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_config']['value2'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'value3' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_config']['value3'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'value4' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_config']['value4'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'value5' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_config']['value5'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'setDebug' => [
            'label' => &$GLOBALS['TL_LANG']['tl_extcss']['setDebug'],
            'exclude' => true,
            'inputType' => 'checkbox',
            'default' => true,
            'sql' => "char(1) NOT NULL default '0'",
        ],
    ],
];

