<?php

declare(strict_types=1);

namespace PBDKN\FussballBundle\Controller\ContentElement\FE;

use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\ContentElement;
use Contao\Environment;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\Template;
use Doctrine\DBAL\Connection;
use PBDKN\FussballBundle\Controller\ContentElement\AbstractFussballController;
use PBDKN\FussballBundle\Controller\ContentElement\DependencyAggregate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @ContentElement(FeUebersichtController::TYPE, category="fussball-FE")
 */
class FeUebersichtController extends AbstractFussballController
{
    public const TYPE = 'AnzeigeUebersicht';

    protected ContaoFramework $framework;
    protected Connection $connection;
    protected ?ContentModel $model = null;
    protected ?PageModel $pageModel = null;

    protected Adapter $config;
    protected Adapter $environment;
    protected Adapter $input;
    protected Adapter $stringUtil;

    private array $Spiele = [];

    public function __construct(
        DependencyAggregate $dependencyAggregate,
        ContaoFramework $framework,
        \Twig\Environment $twig,
        \Contao\CoreBundle\String\HtmlDecoder $htmlDecoder,
        ?\FOS\HttpCacheBundle\Http\SymfonyResponseTagger $responseTagger
    ) {
        parent::__construct($dependencyAggregate);

        $this->framework      = $framework;
        $this->twig           = $twig;
        $this->htmlDecoder    = $htmlDecoder;
        $this->responseTagger = $responseTagger;
        $this->connection     = $dependencyAggregate->connection;

        $this->config      = $framework->getAdapter(Config::class);
        $this->environment = $framework->getAdapter(Environment::class);
        $this->input       = $framework->getAdapter(Input::class);
        $this->stringUtil  = $framework->getAdapter(StringUtil::class);
    }

    public function __invoke(
        Request $request,
        ContentModel $model,
        string $section,
        array $classes = null,
        PageModel $pageModel = null
    ): Response {
        // Backend Vorschau
        if ($this->scopeMatcher->isBackendRequest($request)) {
            return new Response(
                $this->twig->render(
                    '@Fussball/Backend/backend_element_view.html.twig',
                    ['Wettbewerb' => $this->aktWettbewerb['aktWettbewerb']]
                )
            );
        }

        $this->model     = $model;
        $this->pageModel = $pageModel;

        return parent::__invoke($request, $model, $section, $classes);
    }

protected function getResponse(Template $template, ContentModel $model, Request $request): ?Response
{
    // Template bestimmen
    $tplName = 'ce_fe_uebersicht';
    $template = new FrontendTemplate($tplName);

    /* ============================================================
       SPIELE LADEN
    ============================================================ */
    $sql = "
        SELECT
            s.ID,
            s.Nr,
            s.Gruppe,
            s.M1 AS M1Ind,
            m1.Nation AS M1,
            m1.Name AS M1Name,
            m1.Flagge AS Flagge1,
            s.M2 AS M2Ind,
            m2.Nation AS M2,
            m2.Name AS M2Name,
            m2.Flagge AS Flagge2,
            s.Ort AS OrtInd,
            o.Ort AS Ort,
            DATE_FORMAT(s.Datum, '%d.%m') AS Datum,
            DATE_FORMAT(s.Uhrzeit, '%H:%i') AS Uhrzeit,
            s.T1,
            s.T2
        FROM tl_hy_spiele AS s
        LEFT JOIN tl_hy_mannschaft AS m1 ON s.M1 = m1.ID
        LEFT JOIN tl_hy_mannschaft AS m2 ON s.M2 = m2.ID
        LEFT JOIN tl_hy_orte AS o ON s.Ort = o.ID
        WHERE s.Wettbewerb = ?
        ORDER BY s.Gruppe, s.Datum ASC, s.Uhrzeit ASC
    ";

    try {
        $rows = $this->connection
            ->executeQuery($sql, [$this->aktWettbewerb['aktWettbewerb']])
            ->fetchAllAssociative();
        $this->Spiele = is_array($rows) ? $rows : [];
    } catch (\Throwable $e) {
        // Falls DB error: leeres Array
        $this->Spiele = [];
    }


    /* ============================================================
       GRUPPENTABELLE LADEN
    ============================================================ */

    $sql = "
        SELECT s.ID, s.Nr, s.Gruppe, s.Datum, s.Uhrzeit, s.M1, s.M2, m1.Name  AS M1Name, m1.Flagge AS Flagge1, m2.Name  AS M2Name, m2.Flagge AS Flagge2
        FROM tl_hy_spiele s
        LEFT JOIN tl_hy_mannschaft m1 ON s.M1 = m1.ID
        LEFT JOIN tl_hy_mannschaft m2 ON s.M2 = m2.ID
        WHERE s.Wettbewerb = ?
        ORDER BY s.Datum ASC, s.Uhrzeit ASC, s.Nr ASC
        ";


    try {
        $rows = $this->connection
            ->executeQuery($sql, [$this->aktWettbewerb['aktWettbewerb']])
            ->fetchAllAssociative();
        $this->Gruppen = is_array($rows) ? $rows : [];
    } catch (\Throwable $e) {
        $this->Gruppen = [];
    }


    /* ============================================================
       TEMPLATE-VARIABLEN SICHER SETZEN
    ============================================================ */
    $template->Spiele       = $this->Spiele ?? [];
    $template->Gruppen      = $this->Gruppen ?? [];
    $template->Wettbewerb   = $this->aktWettbewerb['aktWettbewerb'] ?? '';
    $template->start        = $this->fussballUtil->getDatum($this->aktWettbewerb, 'start');
    $template->ende         = $this->fussballUtil->getDatum($this->aktWettbewerb, 'ende');
    $template->fussballUtil = $this->fussballUtil;

    return new Response($template->parse());
}
}
