<?php

declare(strict_types=1);


namespace PBDKN\FussballBundle\Controller\ContentElement\FE;

use Contao\Config;
use Contao\ContentModel;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\String\HtmlDecoder;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\Environment;
use Contao\CoreBundle\ServiceAnnotation\ContentElement;
use Contao\Template;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\PageModel;
use Contao\Pagination;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DoctrineDBALDriverException;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use PBDKN\FussballBundle\Util\CgiUtil;
use FOS\HttpCacheBundle\Http\SymfonyResponseTagger;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use PBDKN\FussballBundle\Controller\ContentElement\AbstractFussballController;
use PBDKN\FussballBundle\Controller\ContentElement\DependencyAggregate;

/**
 * Class FeGruppeController
 *
 * @ContentElement(FeGruppeController::TYPE, category="fussball-FE")
 */
class FeGruppeController extends AbstractFussballController
{
    public const TYPE = 'AnzeigeGruppen';
    protected ContaoFramework $framework;
    protected Connection $connection;
    protected ?SymfonyResponseTagger $responseTagger;
    protected ?string $viewMode = null;
    protected ?ContentModel $model;
    protected ?PageModel $pageModel;
    protected TwigEnvironment $twig;

    // Adapters
    protected Adapter $config;
    protected Adapter $environment;
    protected Adapter $input;
    protected Adapter $stringUtil;
    
    private $Spiele = array();

    public function __construct(
      DependencyAggregate $dependencyAggregate, 
      ContaoFramework $framework, 
      TwigEnvironment $twig, 
      HtmlDecoder $htmlDecoder, 
      ?SymfonyResponseTagger $responseTagger)    
    {
        \System::log('PBD Gruppecontroller ', __METHOD__, TL_GENERAL);

        parent::__construct($dependencyAggregate);  // standard Klassen plus akt. Wettbewerb lesen
                                                    // AbstractFussballController übernimmt sie in die akt Klasse
//        \System::log('PBD Spielecontroller nach dependencyAggregate', __METHOD__, TL_GENERAL);
        $this->framework = $framework;
        $this->twig = $twig;
        $this->htmlDecoder = $htmlDecoder;
        $this->responseTagger = $responseTagger;         //  FriendsOfSymfony/FOSHttpCacheBundle https://foshttpcachebundle.readthedocs.io/en/latest/ 

        // Adapters
        $this->config = $this->framework->getAdapter(Config::class);
        $this->environment = $this->framework->getAdapter(Environment::class);
        $this->input = $this->framework->getAdapter(Input::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);

    }
    public function __invoke(Request $request, ContentModel $model, string $section, array $classes = null, PageModel $pageModel = null): Response
    {
        // Do not parse the content element in the backend
        if ($this->scopeMatcher->isBackendRequest($request)) {
            return new Response(
                $this->twig->render('@Fussball/Backend/backend_element_view.html.twig',  
                ['Wettbewerb'=>$this->aktWettbewerb['aktWettbewerb']])
            );
        }

        $this->model = $model;
        $this->pageModel = $pageModel;

        // Set the item from the auto_item parameter and remove auto_item from unused route parameters
        if (isset($_GET['auto_item']) && '' !== $_GET['auto_item']) {
            $this->input->setGet('auto_item', $_GET['auto_item']);
        }
        return parent::__invoke($request, $this->model, $section, $classes);
    }
    

    /**
     * Generate the content element
     */
    protected function getResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        $Wettbewerb = $this->aktWettbewerb['aktWettbewerb'];

        // Definition der WHERE-Bedingungen für die Gruppen
        $whereArr = [
            " AND NOT (tl_hy_gruppen.Gruppe LIKE '%Sechz%') 
              AND NOT (tl_hy_gruppen.Gruppe LIKE '%Achtel%')
              AND NOT (tl_hy_gruppen.Gruppe LIKE '%Viertel%')
              AND NOT (tl_hy_gruppen.Gruppe LIKE '%Halb%')
              AND NOT (tl_hy_gruppen.Gruppe LIKE '%Finale%')",

            " AND tl_hy_gruppen.Gruppe LIKE '%Sechz%'",
            " AND tl_hy_gruppen.Gruppe LIKE '%Achtel%'",
            " AND tl_hy_gruppen.Gruppe LIKE '%Viertel%'",
            " AND tl_hy_gruppen.Gruppe LIKE '%Halb%'",
            " AND tl_hy_gruppen.Gruppe LIKE '%Platz%'",
            " AND tl_hy_gruppen.Gruppe LIKE '%Finale%'"
        ];

        // Funktion zum Laden der Gruppen
        $loadGruppen = function($strWhere) use ($Wettbewerb) {
            $sql = "
                SELECT
                    tl_hy_gruppen.ID,
                    tl_hy_gruppen.Gruppe,
                    mannschaft1.Name AS M1Name,
                    mannschaft1.Flagge AS Flagge1,
                    tl_hy_gruppen.Spiele,
                    tl_hy_gruppen.Sieg,
                    tl_hy_gruppen.Unentschieden,
                    tl_hy_gruppen.Niederlage,
                    tl_hy_gruppen.Tore,
                    tl_hy_gruppen.Gegentore,
                    tl_hy_gruppen.Differenz,
                    tl_hy_gruppen.Platz,
                    tl_hy_gruppen.Punkte
                FROM tl_hy_gruppen
                LEFT JOIN tl_hy_mannschaft AS mannschaft1 
                    ON tl_hy_gruppen.M1 = mannschaft1.ID
                WHERE tl_hy_gruppen.Wettbewerb = ?
                $strWhere
                ORDER BY tl_hy_gruppen.Gruppe ASC, tl_hy_gruppen.Platz ASC
            ";

            return $this->connection->fetchAllAssociative($sql, [$Wettbewerb]);
        };

        // Alle Gruppensätze laden
        $gruppenListen = [];
        foreach ($whereArr as $strWhere) {
            $gruppen = $loadGruppen($strWhere);
            if (!empty($gruppen)) {
                $gruppenListen[] = $gruppen;
            }
        }

        // Template erstellen
        $tpl = new \Contao\FrontendTemplate('ce_fe_fussball_gruppen');

        $tpl->wettbewerb = $Wettbewerb;
        $tpl->datumStart = $this->fussballUtil->getDatum($this->aktWettbewerb,'start');
        $tpl->datumEnde  = $this->fussballUtil->getDatum($this->aktWettbewerb,'ende');
        $tpl->gruppenListen = $gruppenListen;
        $tpl->fussballUtil = $this->fussballUtil;

        return new Response($tpl->parse());
    }
}
