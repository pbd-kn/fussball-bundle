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
use Contao\CoreBundle\Routing\ResponseContext\HtmlHeadBag\HtmlHeadBag;
use Contao\CoreBundle\Routing\ScopeMatcher;
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
abstract class AbstractFussballController extends AbstractContentElementController
{
    protected CgiUtil $cgiUtil;
    protected FussballUtil $fussballUtil;
    protected Connection $connection;
    protected ScopeMatcher $scopeMatcher;
    protected $aktWettbewerb=array('aktWettbewerb'=>'','aktWettbewerbId'=>'','id'=>'','aktAnzgruppen'=>-1,'aktDGruppe'=>'','aktStartdatum'=>'','aktEndedatum'=>'');

    public function __construct(DependencyAggregate $dependencyAggregate)
    {
        $this->cgiUtil = $dependencyAggregate->cgiUtil;
        $this->fussballUtil = $dependencyAggregate->fussballUtil;
        $this->connection = $dependencyAggregate->connection;
        $this->scopeMatcher = $dependencyAggregate->scopeMatcher;
    }

    protected function loadAktWettbewerb(): void
    {
        $row = $this->connection->executeQuery("SELECT * FROM tl_hy_config WHERE Name='Wettbewerb' AND aktuell=1 LIMIT 1")->fetchAssociative();
        $this->aktWettbewerb = [
            'id' => $row['id'] ?? '',
            'aktWettbewerbId' => $row['id'] ?? '',
            'aktuell' => $row['aktuell'] ?? 0,
            'aktWettbewerb' => $row['value1'] ?? '',
            'aktAnzgruppen' => $row['value2'] ?? -1,
            'aktDGruppe' => $row['value3'] ?? '',
            'aktStartdatum' => $row['value4'] ?? '',
            'aktEndedatum' => $row['value5'] ?? '',
        ];
    }

    protected function berechneWettPunkte(string $Art, $W1, $W2, $Tipp1, $Tipp2, $Tipp3, $Pok, $Ptrend): int
    {
        $art = strtolower(trim($Art));
        $pok = (int) $Pok;
        $ptrend = (int) $Ptrend;

        if ($art === 's') {
            $w1 = $this->normalizeScore($W1);
            $w2 = $this->normalizeScore($W2);
            $tipp2 = $this->normalizeScore($Tipp2);
            $tipp3 = $this->normalizeScore($Tipp3);

            if ($w1 === null || $w2 === null || $tipp2 === null || $tipp3 === null) {
                return 0;
            }

            if ($w1 === $tipp2 && $w2 === $tipp3) {
                return $pok;
            }

            return (($w1 <=> $w2) === ($tipp2 <=> $tipp3)) ? $ptrend : 0;
        }

        if ($art === 'g') {
            $w1 = (int) $W1;
            $w2 = (int) $W2;
            $tipp2 = (int) $Tipp2;
            $tipp3 = (int) $Tipp3;

            if ($w1 === -1 || $w2 === -1 || $tipp2 === -1 || $tipp3 === -1) {
                return 0;
            }

            $punkte = 0;
            if ($w1 === $tipp2) $punkte += $pok;
            if ($w2 === $tipp3) $punkte += $pok;
            if ($w1 === $tipp3) $punkte += $ptrend;
            if ($w2 === $tipp2) $punkte += $ptrend;

            return $punkte;
        }

        if ($art === 'p' || $art === 'v') {
            $w1 = (int) $W1;
            $tipp1 = (int) $Tipp1;

            if ($w1 === -1 || $tipp1 === -1) {
                return 0;
            }

            return ($w1 === $tipp1) ? $pok : 0;
        }

        return 0;
    }

    private function normalizeScore($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $score = (int) $value;

        return $score < 0 ? null : $score;
    }

}
