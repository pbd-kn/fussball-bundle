<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Controller\ContentElement\FE;

use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;

use PBDKN\FussballBundle\Controller\ContentElement\AbstractFussballController;
use PBDKN\FussballBundle\Controller\ContentElement\DependencyAggregate;

use Contao\CoreBundle\ServiceAnnotation\ContentElement;

/**
 * @ContentElement(FePunkteController::TYPE, category="fussball-FE")
 */
class FePunkteController extends AbstractFussballController
{
    public const TYPE = 'TeilnehmerPunkte';

    protected ContaoFramework $framework;
    protected TwigEnvironment $twig;
    protected Adapter $input;


    public function __construct(
        DependencyAggregate $dependencyAggregate,
        ContaoFramework     $framework,
        TwigEnvironment     $twig
    ) {
        parent::__construct($dependencyAggregate);

        $this->framework = $framework;
        $this->twig      = $twig;

        // Input Adapter
        $this->input = $framework->getAdapter(Input::class);

        // *** WICHTIG ***
        // Die Connection kommt aus AbstractFussballController:
        // $this->connection ist bereits gesetzt!
    }


    public function __invoke(Request $request, ContentModel $model, string $section, array $classes = null, PageModel $pageModel = null): Response
    {
        if ($this->scopeMatcher->isBackendRequest($request)) {
            return new Response(
                $this->twig->render('@Fussball/Backend/backend_element_view.html.twig', [
                    'Wettbewerb' => $this->aktWettbewerb['aktWettbewerb']
                ])
            );
        }

        return parent::__invoke($request, $model, $section, $classes);
    }


    protected function getResponse(\Contao\Template $template, ContentModel $model, Request $request): ?Response
    {
        $Wettbewerb = $this->aktWettbewerb['aktWettbewerb'];

        //----------------------------------------------------------------------
        // 1. Teilnehmer laden
        //----------------------------------------------------------------------
        $teilnehmer = $this->connection->fetchAllAssociative("
            SELECT ID, Name, Kurzname
            FROM tl_hy_teilnehmer
            WHERE Wettbewerb = ?
            ORDER BY Name
        ", [$Wettbewerb]);


        //----------------------------------------------------------------------
        // 2. Mannschaften laden
        //----------------------------------------------------------------------
        $mannschaftRows = $this->connection->fetchAllAssociative("
            SELECT *
            FROM tl_hy_mannschaft
            WHERE Wettbewerb = ?
        ", [$Wettbewerb]);

        $mannschaften = [];
        foreach ($mannschaftRows as $m) {
            $mannschaften[$m['ID']] = $m;
        }


        //----------------------------------------------------------------------
        // 3. Wetten laden
        //----------------------------------------------------------------------
        $wettenRoh = $this->connection->fetchAllAssociative("
            SELECT 
                tln.ID            AS TlnID,
                tln.Name          AS TeilnehmerName,
                wetten.Art        AS Art,
                wetten.Kommentar  AS Kommentar,
                wetten.Tipp1,
                wetten.Tipp2,
                wetten.Tipp3,
                wetten.Tipp4,
                wetten.Pok,
                wetten.Ptrend,
                akt.W1,
                akt.W2,
                akt.W3,
                akt.Wette
            FROM tl_hy_wetteaktuell akt
            LEFT JOIN tl_hy_teilnehmer tln ON akt.Teilnehmer = tln.ID
            LEFT JOIN tl_hy_wetten wetten ON akt.Wette = wetten.ID
            WHERE akt.Wettbewerb = ?
            ORDER BY tln.Name, wetten.Art, wetten.Kommentar
        ", [$Wettbewerb]);


        //----------------------------------------------------------------------
        // 4. Gruppieren + Punkte berechnen
        //----------------------------------------------------------------------
        $wettenProTeilnehmer = [];
        $punkteProTeilnehmer = [];

        foreach ($wettenRoh as $w) {

            $tid = $w['TlnID'];

            if (!isset($wettenProTeilnehmer[$tid])) {
                $wettenProTeilnehmer[$tid] = [];
                $punkteProTeilnehmer[$tid] = 0;
            }

            // Punkte berechnen
            $punkte = $this->berechnePkt($w, $Wettbewerb);

            $w['Punkte'] = $punkte;
            $wettenProTeilnehmer[$tid][] = $w;

            $punkteProTeilnehmer[$tid] += $punkte;
        }


        //----------------------------------------------------------------------
        // 5. Ausgabe per HTML5-Template
        //----------------------------------------------------------------------
        $tpl = new FrontendTemplate('ce_fe_fussball_punkte');

        $tpl->wettbewerb   = $Wettbewerb;
        $tpl->teilnehmer   = $teilnehmer;
        $tpl->wetten       = $wettenProTeilnehmer;
        $tpl->summe        = $punkteProTeilnehmer;
        $tpl->mannschaften = $mannschaften;

        // Helper für Bilder / Formatierung etc.
        $tpl->fussballUtil = $this->fussballUtil;
        $tpl->cgi          = $this->cgiUtil;

        return new Response($tpl->parse());
    }


    private function berechnePkt(array $row, string $Wettbewerb): int
    {
        $Art = strtolower($row['Art']);

        if ($Art === 's') {
            $sp = $this->connection->fetchAssociative("
                SELECT T1, T2 
                FROM tl_hy_spiele 
                WHERE Wettbewerb = ? AND ID = ?
            ", [$Wettbewerb, $row['Tipp1']]);

            if ($sp) {
                $row['Tipp2'] = $sp['T1'];
                $row['Tipp3'] = $sp['T2'];
            }
        }

        return $this->calculatePoints($row);
    }


    private function calculatePoints(array $r): int
    {
        $Art = strtolower($r['Art']);
        $Pok = $r['Pok'];
        $Ptr = $r['Ptrend'];

        if ($Art === 's') {
            if ($r['W1'] == -1 || $r['W2'] == -1 || $r['Tipp2'] == -1 || $r['Tipp3'] == -1) return 0;

            if ($r['W1'] == $r['Tipp2'] && $r['W2'] == $r['Tipp3']) return $Pok;

            $trendW = $r['W1'] <=> $r['W2'];
            $trendT = $r['Tipp2'] <=> $r['Tipp3'];

            return ($trendW === $trendT) ? $Ptr : 0;
        }

        if ($Art === 'p' || $Art === 'v') {
            return ($r['W1'] == $r['Tipp1']) ? $Pok : 0;
        }

        if ($Art === 'g') {
            $sum = 0;
            if ($r['W1'] == $r['Tipp1']) $sum += $Pok;
            if ($r['W2'] == $r['Tipp2']) $sum += $Pok;
            return $sum;
        }

        return 0;
    }
}
