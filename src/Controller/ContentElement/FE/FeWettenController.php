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
 * Class FeWettenController
 *
 * @ContentElement(FeWettenController::TYPE, category="fussball-FE")
 */
class FeWettenController extends AbstractFussballController
{
    public const TYPE = 'AnzeigeWetten';
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
    
    private $Wetten = array();
    private $WettenArten = array();

    public function __construct(
      DependencyAggregate $dependencyAggregate, 
      ContaoFramework $framework, 
      TwigEnvironment $twig, 
      HtmlDecoder $htmlDecoder, 
      ?SymfonyResponseTagger $responseTagger)    
    {
        \System::log('PBD FeWettenController ', __METHOD__, TL_GENERAL);

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

        // --- Wetten laden
        $sql = "SELECT ID, Kommentar, Art, Tipp1, Tipp2, Tipp3, Tipp4, Pok, Ptrend
            FROM tl_hy_wetten
            WHERE Wettbewerb = ?
            ORDER BY Art ASC, Kommentar ASC";

        $stmt = $this->connection->executeQuery($sql, [$Wettbewerb]);

        $wetten = [];
        while ($row = $stmt->fetchAssociative()) {
            $details = [];

            // Zusatzdaten für Spielwetten
            if ($row['Art'] === 's') {
                $sqlSp = "
                    SELECT
                        tl_hy_spiele.ID,
                        man1.Name AS M1Name,
                        man1.Flagge AS Flagge1,
                        man2.Name AS M2Name,
                        man2.Flagge AS Flagge2
                    FROM tl_hy_spiele
                    LEFT JOIN tl_hy_mannschaft AS man1 ON man1.ID = tl_hy_spiele.M1
                    LEFT JOIN tl_hy_mannschaft AS man2 ON man2.ID = tl_hy_spiele.M2
                    WHERE tl_hy_spiele.ID = ?
                ";
                $details = $this->connection->executeQuery($sqlSp, [$row['Tipp1']])->fetchAssociative();
            }

            $wetten[] = [
                'row'     => $row,
                'details' => $details
            ];
        }

        // --- Ausgabe über HTML5-Template
        $tpl = new \Contao\FrontendTemplate('ce_fe_fussball_wetten');
        $tpl->wettbewerb = $Wettbewerb;
        $tpl->datumStart = $this->fussballUtil->getDatum($this->aktWettbewerb,'start');
        $tpl->datumEnde  = $this->fussballUtil->getDatum($this->aktWettbewerb,'ende');
        $tpl->wetten     = $wetten;

        return new Response($tpl->parse());
    }
}
