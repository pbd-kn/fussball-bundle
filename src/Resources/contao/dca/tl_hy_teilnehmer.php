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
 *
 */

/*
 * Table tl_extcss
 */
$GLOBALS['TL_DCA']['tl_hy_teilnehmer'] = [
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
                'label' => &$GLOBALS['TL_LANG']['tl_hy_teilnehmer']['edit'],
//                'href' => 'table=tl_extcss_file',
                'icon' => 'edit.gif',
            ],
            'editheader' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_teilnehmer']['editheader'],
                'href' => 'act=edit',
                'icon' => 'header.gif',
            ],
            'copy' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_teilnehmer']['copy'],
                'href' => 'act=copy',
                'icon' => 'copy.gif',
            ],
            'delete' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_teilnehmer']['delete'],
                'href' => 'act=delete',
                'icon' => 'delete.gif',
                'attributes' => 'onclick="if(!confirm(\''.'Loeschen??'.'\'))return false;Backend.getScrollOffset()"',
            ],
            'show' => [
                'label' => &$GLOBALS['TL_LANG']['tl_hy_teilnehmer']['show'],
                'href' => 'act=show',
                'icon' => 'show.gif',
            ],
        ],
    ],


    'palettes' => [
        '__selector__' => array('Wettbewerb','Name','Kurzname','Email','Punkte'),
		'default' => '{title_legend},Wettbewerb;Name;Kurzname;Email;Punkte;'
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
            'label' => &$GLOBALS['TL_LANG']['tl_hy_teilnehmer']['Wettbewerb'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'Name' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_teilnehmer']['Name'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'Kurzname' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_teilnehmer']['Kurzname'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'Email' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_teilnehmer']['Email'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'Punkte' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_teilnehmer']['Punkte'],
            'exclude' => true,
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'maxlength' => 64],
            'sql' => "int  default '0'",
        ],
        'Bezahlt' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_teilnehmer']['bezahlt'],
            'exclude' => true,
            'inputType' => 'checkbox',
            'default' => true,
            'sql' => "char(2) NOT NULL default '0'",
        ],
        'Erst' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_teilnehmer']['Erst'],
            'exclude' => true,
            'inputType' => 'checkbox',
            'default' => true,
            'sql' => "char(2) NOT NULL default '0'",
        ],
        'Achtel' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_teilnehmer']['Achtel'],
            'exclude' => true,
            'inputType' => 'checkbox',
            'default' => true,
            'sql' => "char(2) NOT NULL default '0'",
        ],
        'Viertel' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_teilnehmer']['Viertel'],
            'exclude' => true,
            'inputType' => 'checkbox',
            'default' => true,
            'sql' => "char(2) NOT NULL default '0'",
        ],
        'Halb' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_teilnehmer']['Halb'],
            'exclude' => true,
            'inputType' => 'checkbox',
            'default' => true,
            'sql' => "char(2) NOT NULL default '0'",
        ],
        'Finale' => [
            'label' => &$GLOBALS['TL_LANG']['tl_hy_teilnehmer']['Finale'],
            'exclude' => true,
            'inputType' => 'checkbox',
            'default' => true,
            'sql' => "char(2) NOT NULL default '0'",
        ],
        
    ],
];

