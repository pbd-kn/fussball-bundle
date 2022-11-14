<?php

declare(strict_types=1);

/*
 * entspiecht dem alten mainwettbewerb.
 * Neuer Wettbewerb oder akt. Wettbewerb bearbeiten
 * 
 * (c) Peter 2022 <pb-tester@gmx.de>
 * ce fussball/wettbewerb
 * stellt alle Wettbewerbe dar
 */

namespace PBDKN\FussballBundle\Controller\ContentElement;

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


/**
 * Class MannschaftController
 *
 * @ContentElement(SpieleController::TYPE, category="fussball-Verwaltung")
 */
class SpieleController extends AbstractFussballController
{
    public const TYPE = 'Spiele';
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
        \System::log('PBD Spielecontroller ', __METHOD__, TL_GENERAL);

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
        $this->galleryCreatorAlbumsModel = $this->framework->getAdapter(GalleryCreatorAlbumsModel::class);
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
        //$template->text = $model->text;
        $c=$this->cgiUtil;
        $html="";
        // alle aktuellen Spiele einlesen
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
        $sql .= " flagge2.Image as 'Flagge2',";
        $sql .= " hy_spiele.Ort as 'OrtInd',";
        $sql .= " hy_orte.Ort as 'Ort',";
        $sql .= " hy_spiele.Datum as 'Datum',";
        $sql .= " hy_spiele.Uhrzeit as 'Uhrzeit',";
        $sql .= " hy_spiele.T1 as 'T1',";
        $sql .= " hy_spiele.T2 as 'T2'";
        $sql .= " FROM hy_spiele";
        $sql .= " LEFT JOIN hy_mannschaft AS mannschaft1 ON hy_spiele.M1 = mannschaft1.ID";
        $sql .= " LEFT JOIN hy_nation AS flagge1 ON flagge1.ID = mannschaft1.flgindex";
        $sql .= " LEFT JOIN hy_mannschaft AS mannschaft2 ON hy_spiele.M2 = mannschaft2.ID";
        $sql .= " LEFT JOIN hy_nation AS flagge2 ON flagge2.ID = mannschaft2.flgindex";
        $sql .= " LEFT JOIN hy_orte ON hy_spiele.Ort = hy_orte.ID";
        $sql .= " WHERE hy_spiele.Wettbewerb  ='".$this->aktWettbewerb['aktWettbewerb']."'";
        $sql .= " ORDER BY hy_spiele.Datum ASC , hy_spiele.Uhrzeit ASC ;";

        $stmt = $this->connection->executeQuery($sql);
        $num_rows = $stmt->rowCount();    
        while (($row = $stmt->fetchAssociative()) !== false) {
          $this->Spiele[]=$row;
        }
        $my_script_txt = <<< EOT
          <script language="javascript" type="text/javascript">
            function neuesSpiel() {
              var url =  '/fussball/anzeigespiel/n/-1';
console.log(" neues Spiel url " + url);
              jQuery.get(url, function(data, status){
                jQuery("#result").html(data['data']);
            });
         }
         function spielBearbeiten(obj) {  // mit jQuery
           var id=obj.name;	 
	       var par = "ID=" + id + "&aktion=u";
           var url =  '/fussball/anzeigespiel/u/'+id;
console.log(" Spiel bearbeiten url " + url);
           jQuery.get(url, function(data, status){
//alert ("data " + " status " + status);
             jQuery("#result").html(data['data']);
           });
         }    

         function spielLoeschen(obj) {
           var id=obj.name;	 
           var url =  '/fussball/bearbeitespiel/d/'+id;
console.log(" spielLoeschen url " + url);
           jQuery.get(url, function(data, status){
             errortxt=data['error'];
console.log(" spielLoeschen errortxt " + errortxt);
             if (errortxt != '') {
               jQuery("#result").html(errortxt);
             } else {
console.log(" spielLoeschen reload " + data['error']);
               location.reload();
             }
           });
         }
         
        </script>
EOT;
        $html.=$my_script_txt; 
//       $html.=$sql;       
        $html.=$c->div(array("class"=>"contentverwaltung"));
        $html.=$c->div(array("id"=>"eingabe")) . $c->end_div();    
        $html.=$c->div(array("id"=>"result")) . $c->end_div();
        $html.='aktueller Wettbewerb('.$this->aktWettbewerb['id'].'): '.$this->aktWettbewerb['aktWettbewerb'].'<br>';
        $html.=$c->Button(array("onClick"=>"neuesSpiel();"),"Neues Spiel","createSpiel") . "\n"; 
        
        $html.=$c->start_form("", null,null,array("id"=>"inputForm"));
        $html.=$c->table(array("class"=>"verwtablecss  sortierbar","border"=>1));
        $html.=$c->thead();
        $html.=$c->tr();
          $html.=$c->th("&nbsp;").$c->th("ID").$c->th(array("class"=>"sortierbar"),"Nr").$c->th(array("class"=>"sortierbar"),"Gruppe");
          $html.=$c->th(array("class"=>"sortierbar"),"M1Ind").$c->th(array("class"=>"sortierbar"),"M1").$c->th(array("class"=>"sortierbar"),"M2Ind");
          $html.=$c->th(array("class"=>"sortierbar"),"M2").$c->th(array("class"=>"sortierbar"),"Ort").$c->th(array("class"=>"sortierbar"),"Datum");
          $html.=$c->th(array("class"=>"sortierbar"),"Uhrzeit").$c->th(array("class"=>"sortierbar"),"T1").$c->th(array("class"=>"sortierbar"),"T2");
        $html.=$c->end_tr();
        $html.=$c->end_thead();
        $html.=$c->tbody();
        
        foreach ($this->Spiele as $k=>$row) {
          $html.=$c->tr();
  	        $html.=$c->td();
	          $html.=$c->Button(array("onClick"=>"spielBearbeiten(this);"),"B",$row['ID']) . "\n";
              $html.=$c->Button(array("onClick"=>"spielLoeschen(this);"),"L",$row['ID']) . "\n";
  	        $html.=$c->end_td();
  	        $html.=$c->td((string)$row['ID']).$c->td((string)$row['Nr']).$c->td($row['Gruppe']).$c->td((string)$row['M1Ind']). "\n";
	        $html.=$c->td();
	          $html.="<img src='".$this->fussballUtil->getImagePath($row['Flagge1']). "' >&nbsp;" . $row['M1Name'] ;
	        $html.=$c->end_td();
            $html.=$c->td((string)$row['M2Ind']);
	        $html.=$c->td();
	          $html.="<img src='".$this->fussballUtil->getImagePath($row['Flagge2']). "' >&nbsp;" . $row['M2Name'] ;
	        $html.=$c->end_td();
  	        $html.=$c->td((string)$row['Ort']).$c->td($row['Datum']).$c->td($row['Uhrzeit']);
            $html.=$c->td((string)$row['T1']).$c->td((string)$row['T2'])."\n";
	      $html.=$c->end_tr() . "\n";
      }
      $response = new Response($html,Response::HTTP_OK,['content-type' => 'text/html']);
      return $response;
    }
}
