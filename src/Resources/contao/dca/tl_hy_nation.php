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
$GLOBALS['TL_DCA']['tl_hy_nation'] = [
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
                'label' => &$GLOBALS['TL_LANG']['tl_hy_nation']['edit'],
//                'href' => 'table=tl_extcss_file',
                'icon' => 'edit.gif',
            ],
            'editheader' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_nation']['editheader'],
                'href' => 'act=edit',
                'icon' => 'header.gif',
            ],
            'copy' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_nation']['copy'],
                'href' => 'act=copy',
                'icon' => 'copy.gif',
            ],
            'delete' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_nation']['delete'],
                'href' => 'act=delete',
                'icon' => 'delete.gif',
                'attributes' => 'onclick="if(!confirm(\''.'Loeschen??'.'\'))return false;Backend.getScrollOffset()"',
            ],
            'show' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_nation']['show'],
                'href' => 'act=show',
                'icon' => 'show.gif',
            ],
        ],
    ],


    'palettes' => [
        '__selector__' => array('Nation','Type','Alfa2','Alfa3','Domain','Image'),
		'default' => '{title_legend},Nation;Type,Alfa2;Alfa3;Domain;Image;'
    ],
    // Fields
    'fields' => [
        'ID' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'Nation' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_nation']['Nation'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'Type' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_nation']['Type'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'Alfa2' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_nation']['Alfa2'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'Alfa3' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_nation']['Alfa3'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'Domain' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_nation']['Domain'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'Image' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_nation']['Image'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
    ],
];

