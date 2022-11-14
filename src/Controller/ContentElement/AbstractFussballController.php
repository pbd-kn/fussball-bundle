<?php

declare(strict_types=1);

/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

namespace PBDKN\FussballBundle\Controller\ContentElement;
use Contao\Config;
use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\File\Metadata;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Routing\ResponseContext\HtmlHeadBag\HtmlHeadBag;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\String\HtmlDecoder;
use Contao\Date;
use Contao\Environment;
use Contao\FilesModel;
use Contao\Input;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DoctrineDBALDriverException;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Contao\Pagination;
use PBDKN\FussballBundle\Util\CgiUtil;
use PBDKN\FussballBundle\Util\FussballUtil;
/*
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorAlbumsModel;
use Markocupic\GalleryCreatorBundle\Model\GalleryCreatorPicturesModel;
use Markocupic\GalleryCreatorBundle\Util\AlbumUtil;
use Markocupic\GalleryCreatorBundle\Util\MarkdownUtil;
use Markocupic\GalleryCreatorBundle\Util\PictureUtil;
use Markocupic\GalleryCreatorBundle\Util\SecurityUtil;
vergl. auch AbstractGalleryCreator
*/
abstract class AbstractFussballController extends AbstractContentElementController
{
    protected CgiUtil $cgiUtil;
    protected FussballUtil $fussballUtil;
    protected Connection $connection;
    protected ScopeMatcher $scopeMatcher;
    protected ResponseContextAccessor $responseContextAccessor;
    protected InsertTagParser $insertTagParser;
    protected HtmlDecoder $htmlDecoder;
    protected $aktWettbewerb=array('aktWettbewerb'=>'','aktAnzgruppen'=>-1,'aktDGruppe'=>'','aktStartdatum'=>'','aktEndedatum'=>'');

    public function __construct(DependencyAggregate $dependencyAggregate)
    {
        $this->cgiUtil = $dependencyAggregate->cgiUtil;
        $this->fussballUtil = $dependencyAggregate->fussballUtil;
        $this->connection = $dependencyAggregate->connection;
        $this->scopeMatcher = $dependencyAggregate->scopeMatcher;
        $this->responseContextAccessor = $dependencyAggregate->responseContextAccessor;
        $this->insertTagParser = $dependencyAggregate->insertTagParser;
        $this->htmlDecoder = $dependencyAggregate->htmlDecoder;
                // akt Wettbewerb lesen.
        $stmt = $this->connection->executeQuery("SELECT * from hy_config WHERE Name='Wettbewerb' AND Aktuell = 1 LIMIT 1");
        $row = $stmt->fetchAssociative();
        $this->aktWettbewerb['id']=$row['ID'];
        $this->aktWettbewerb['aktuell']=$row['Aktuell'];
        $this->aktWettbewerb['aktWettbewerb']=$row['Value1'];
        $this->aktWettbewerb['aktAnzgruppen']=$row['Value2'];
        $this->aktWettbewerb['aktDGruppe']=$row['Value3'];
        $this->aktWettbewerb['aktStartdatum']=$row['Value4'];
        $this->aktWettbewerb['aktEndedatum']=$row['Value5'];
    }

}
