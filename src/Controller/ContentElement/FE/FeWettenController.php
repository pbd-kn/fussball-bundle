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
        $sql  = "SELECT";
        $sql .= " hy_wetten.ID as 'ID',";
        $sql .= " hy_wetten.Kommentar as 'Kommentar',";
        $sql .= " hy_wetten.Art as 'Art',";
        $sql .= " hy_wetten.Tipp1 as 'Tipp1',";
        $sql .= " hy_wetten.Tipp2 as 'Tipp2',";
        $sql .= " hy_wetten.Tipp3 as 'Tipp3',";
        $sql .= " hy_wetten.Tipp4 as 'Tipp4',";
        $sql .= " hy_wetten.Pok as 'Pok',";
        $sql .= " hy_wetten.Ptrend as 'Ptrend'";
        $sql .= " FROM hy_wetten";
        $sql .= " WHERE hy_wetten.Wettbewerb  ='".$this->aktWettbewerb['aktWettbewerb']."' ";
        $sql .= " ORDER by hy_wetten.Art ASC,hy_wetten.Kommentar ASC  ;";
        $stmt = $this->connection->executeQuery($sql);
        $num_rows = $stmt->rowCount();    
        //$html.="num_rows $num_rows sql:<br>$sql<br>";
        while (($row = $stmt->fetchAssociative()) !== false) {
          $this->Wetten[]=$row;
          $this->WettenArten[$row['Art']] = $row['Art'];

        }
      $html.="<div class='tabellenueberschrift'>Gruppenplan der ".$Wettbewerb." vom ".$this->fussballUtil->getDatum($this->aktWettbewerb,'start').' bis '.$this->fussballUtil->getDatum($this->aktWettbewerb,'ende')."&nbsp;<input class='druck' type='button' onclick='print()' value='Drucken'></div>\n";
      $html.=$c->table(array("class"=>"tablecss","rules"=>"all","border"=>"1"));
      $html.=$c->thead().$c->tr();
      $html.=$c->th("Kommentar").$c->th("Pok").$c->th("Ptrend").$c->th("Art").$c->th("Tipp1").$c->th("Tipp2").$c->th("Tipp3").$c->th("Tipp4");
/*
        foreach ($this->WettenArten as $Art) {
          $html.=$c->tr();
          $html.=$c->th("Kommentar").$c->th("Pok").$c->th("Ptrend").$c->th("Art").$c->th("Tipp1").$c->th("Tipp2").$c->th("Tipp3").$c->th("Tipp4");

          switch(strtolower($Art))
          {
            case 's': {   // Spielwette Tipp1 Spiel Tipp2 Tore M1 Tipp 2 Tore M2
              $html.=$c->th("Spiel").$c->th("T1").$c->th("T2");
              break;
            }
            case 'g': {   // Gruppenwette Tipp1 Gruppe Tipp2 M1 Tipp 3 M2 Tipp3 M3
              $html.=$c->th("Gruppe").$c->th("M1").$c->th("M2").$c->th("M3").$c->th("M4");
              break;
            }
            case 'p': {   // Platzwette Tipp1 M1 Tipp2 Tipp 3 Tipp4 irrelevant ??
              $html.=$c->th("M1");
              break;
            }
            case 'v': {   // Vergleichwette Tipp1 Vergleichswert Tipp2 Tipp 3 Tipp4 irrelevant ??
              $html.=$c->th("V");
              break;
            }
          }
          $html.=$c->end_tr();
        }
*/
        $html.=$c->end_tr().$c->end_thead().$c->tbody() . "\n";
        foreach ($this->Wetten as $k=>$row) {
          $html.=$c->tr("&nbsp");
            $html.=$c->td((string)$row['Kommentar']). "\n";
            $html.=$c->td((string)$row['Pok']). "\n";
            $html.=$c->td((string)$row['Ptrend']). "\n";
            $html.=$c->td((string)$row['Art']). "\n";
/*
            $html.=$c->td($row['Spiel']). "\n";
	          $str="<img src='".$this->fussballUtil->getImagePath($row['Flagge1']). "' >&nbsp;" . $row['M1Name'];
            $html.=$c->td($str). "\n";
            $html.=$c->td($row['T1']). "\n";
	          $str="<img src='".$this->fussballUtil->getImagePath($row['Flagge2']). "' >&nbsp;" . $row['M2Name'] ;
            $html.=$c->td($str). "\n";
            $html.=$c->td($row['T2']). "\n";
*/
            switch(strtolower($row['Art']))
            {
              case 's': {   // Spielwette Tipp1 Spiel Tipp2 Tore M1 Tipp 2 Tore M2
                $sql  = "SELECT";
                $sql .= " hy_spiele.ID as 'ID',";
                $sql .= " hy_spiele.Nr as 'Nr',";
                $sql .= " hy_spiele.Gruppe as 'Gruppe',";
                $sql .= " hy_spiele.M1 as 'M1Ind',";
                $sql .= " mannschaft1.Nation as 'M1',";
                $sql .= " mannschaft1.Name as 'M1Name',";
                $sql .= " flagge1.Image as 'Flagge1',";
                $sql .= " hy_spiele.M2 as 'M2Ind',";
                $sql .= " mannschaft2.Nation as 'M2',";
                $sql .= " mannschaft2.Name as 'M2Name',";
                $sql .= " flagge2.Image as 'Flagge2'";
                $sql .= " FROM hy_spiele";
                $sql .= " LEFT JOIN hy_mannschaft AS mannschaft1 ON hy_spiele.M1 = mannschaft1.ID";
                $sql .= " LEFT JOIN hy_nation AS flagge1 ON flagge1.ID = mannschaft1.flgindex";
                $sql .= " LEFT JOIN hy_mannschaft AS mannschaft2 ON hy_spiele.M2 = mannschaft2.ID";
                $sql .= " LEFT JOIN hy_nation AS flagge2 ON flagge2.ID = mannschaft2.flgindex";
                $sql .= " WHERE hy_spiele.ID='".$row['Tipp1']."'";
                $stmt = $this->connection->executeQuery($sql);
                $rowsp = $stmt->fetchAssociative();
	             $str1="<img src='".$this->fussballUtil->getImagePath($rowsp['Flagge1']). "' >&nbsp;" . $rowsp['M1Name'];
	             $str2="<img src='".$this->fussballUtil->getImagePath($rowsp['Flagge2']). "' >&nbsp;" . $rowsp['M2Name'];
                $html.=$c->td($str1.':'.$str2);
                $html.=$c->td((string)$row['Tipp2']).$c->td((string)$row['Tipp3']).$c->td((string)$row['Tipp4']);
               break;
              }
              case 'g': {   // Gruppenwette Tipp1 Gruppe Tipp2 M1 Tipp 3 M2 Tipp3 M3
                $html.=$c->td((string)$row['Tipp1']).$c->td((string)$row['Tipp2']).$c->td((string)$row['Tipp3']).$c->td((string)$row['Tipp4']);
               break;
              }
              case 'p': {   // Platzwette Tipp1 M1 Tipp2 Tipp 3 Tipp4 irrelevant ??
                $html.=$c->td((string)$row['Tipp1']).$c->td((string)$row['Tipp2']).$c->td((string)$row['Tipp3']).$c->td((string)$row['Tipp4']);
               break;
              }
              case 'v': {   // Vergleichwette Tipp1 Vergleichswert Tipp2 Tipp 3 Tipp4 irrelevant ??
                $html.=$c->td((string)$row['Tipp1']).$c->td((string)$row['Tipp2']).$c->td((string)$row['Tipp3']).$c->td((string)$row['Tipp4']);
               break;
              }
            }

	      $html.=$c->end_tr() . "\n";
        }  
      $html.=$c->end_tbody();
      $html.=$c->end_table();
      $response = new Response($html,Response::HTTP_OK,['content-type' => 'text/html']);
      return $response;
    }
}
