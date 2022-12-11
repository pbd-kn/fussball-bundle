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
      function getGruppen($conn,$Wettbewerb,$strWhere,&$debug) {
        $arrRes=[];
        $sql  = "SELECT";
        $sql .= " hy_gruppen.ID as 'ID',";
        $sql .= " hy_gruppen.Gruppe as 'Gruppe',";
        $sql .= " hy_gruppen.M1 as 'M1Ind',";
        $sql .= " mannschaft1.Nation as 'M1',";
        $sql .= " mannschaft1.Name as 'M1Name',";
        $sql .= " flagge1.Image as 'Flagge1',";
        $sql .= " hy_gruppen.Spiele as 'Spiele',";
        $sql .= " hy_gruppen.Sieg as 'Sieg',";
        $sql .= " hy_gruppen.Unentschieden as 'Unentschieden',";
        $sql .= " hy_gruppen.Niederlage as 'Niederlage',";
        $sql .= " hy_gruppen.Tore as 'Tore',";
        $sql .= " hy_gruppen.Gegentore as 'Gegentore',";
        $sql .= " hy_gruppen.Differenz as 'Differenz',";
        $sql .= " hy_gruppen.Platz as 'Platz',";
        $sql .= " hy_gruppen.Punkte as 'Punkte'";
        $sql .= " FROM hy_gruppen";
        $sql .= " LEFT JOIN hy_mannschaft AS mannschaft1 ON hy_gruppen.M1 = mannschaft1.ID";
        $sql .= " LEFT JOIN hy_nation AS flagge1 ON flagge1.ID = mannschaft1.flgindex";
        $sql .= " WHERE hy_gruppen.Wettbewerb  ='$Wettbewerb' "; 
        if ($strWhere != '') $sql .= $strWhere;             
        $sql .= " ORDER BY hy_gruppen.Gruppe ASC , hy_gruppen.Platz ASC ;";
        //$debug.="sql: $sql<br>";
        $stmt = $conn->executeQuery($sql);
        $num_rows = $stmt->rowCount();    
        while (($row = $stmt->fetchAssociative()) !== false) {
          $arrRes[]=$row;
        }
        return $arrRes;
      }
    
      //$template->text = $model->text;
      $c=$this->cgiUtil;
      $html="";
      $Wettbewerb=$this->aktWettbewerb['aktWettbewerb'];

      // alle aktuelle Gruppen einlesen  
      $whereArr = [
          " AND NOT (hy_gruppen.Gruppe LIKE '%Achtel%') AND NOT (hy_gruppen.Gruppe LIKE '%Viertel%') AND NOT (hy_gruppen.Gruppe LIKE '%Halb%') AND NOT (hy_gruppen.Gruppe LIKE '%Finale%')",
          " AND hy_gruppen.Gruppe LIKE '%Achtel%'",
          " AND hy_gruppen.Gruppe LIKE '%Viertel%'",
          " AND hy_gruppen.Gruppe LIKE '%Halb%'",
          " AND hy_gruppen.Gruppe LIKE '%Platz%'",
          " AND hy_gruppen.Gruppe LIKE '%Finale%'"
      ];
      $html.="<div class='tabellenueberschrift'>Gruppenplan der ".$Wettbewerb." vom ".$this->fussballUtil->getDatum($this->aktWettbewerb,'start').' bis '.$this->fussballUtil->getDatum($this->aktWettbewerb,'ende')."&nbsp;<input class='druck' type='button' onclick='print()' value='Drucken'></div>\n";
      $html.=$c->table(array("class"=>"tablecss sortierbar","rules"=>"all","border"=>"1"));
      $html.=$c->thead().$c->tr();
      $html.=$c->th(array("class"=>"vorsortiert"),"Gr");
      $html.=$c->th("M");
      $html.=$c->th("Pl").$c->th("Sp").$c->th("G").$c->th("U").$c->th("N").$c->th("T").$c->th("GT").$c->th("Di").$c->th("Pkt");
      $html.=$c->end_tr().$c->end_thead().$c->tbody() . "\n";
      foreach ($whereArr as $k=>$strWhere) {
        $arr = getGruppen($this->connection,$Wettbewerb,$strWhere,$html);
        
        if (count($arr) > 0 ) {
          foreach ($arr as $k=>$row) {
	        $html.=$c->tr();
	          $html.=$c->td($row['Gruppe']);
	          $str="<img src='".$this->fussballUtil->getImagePath($row['Flagge1']). "' >&nbsp;" . $row['M1Name'] ;
              $html.=$c->td($str);
              $html.=$c->td((string)$row['Platz']);
              $html.=$c->td((string)$row['Spiele']);
              $html.=$c->td((string)$row['Sieg']);
              $html.=$c->td((string)$row['Unentschieden']);
              $html.=$c->td((string)$row['Niederlage']);
              $html.=$c->td((string)$row['Tore']);
              $html.=$c->td((string)$row['Gegentore']);
              $html.=$c->td((string)$row['Differenz']);
              $html.=$c->td((string)$row['Punkte']);
            $html.=$c->end_tr() . "\n";
          }
        }
      }    
      $html.=$c->end_tbody();
      $html.=$c->end_table();

      $response = new Response($html,Response::HTTP_OK,['content-type' => 'text/html']);
      return $response;
    }
}
