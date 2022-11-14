<?php

declare(strict_types=1);

/*
 * entspiecht dem alten mainwettbewerb.
 * Neuer Wettbewerb oder akt. Wettbewerb bearbeiten
 * 
 * (c) Peter 2022 <pb-tester@gmx.de>
 * ce fussball/wettbewerbe
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
 * Class WettbewerbController
 *
 * @ContentElement(WettbewerbController::TYPE, category="fussball-Verwaltung")
 */
class WettbewerbController extends AbstractFussballController
{
    public const TYPE = 'Wettbewerbe';
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
    
    private $Wettbewerbe = array();

    public function __construct(
      DependencyAggregate $dependencyAggregate, 
      ContaoFramework $framework, 
      TwigEnvironment $twig, 
      HtmlDecoder $htmlDecoder, 
      ?SymfonyResponseTagger $responseTagger)    
    {
        \System::log('PBD Wettbewerbcontroller ', __METHOD__, TL_GENERAL);

        parent::__construct($dependencyAggregate);  // standard Klassen plus akt. Wettbewerb lesen
                                                    // AbstractFussballController übernimmt sie in die akt Klasse
        \System::log('PBD Wettbewerbcontroller nach dependencyAggregate', __METHOD__, TL_GENERAL);
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

        // alle Wettbewerbe einlesen
        $sql = "select ID,Aktuell,Name,Value1,Value2,Value3,Value4,Value5 From hy_config where Name='Wettbewerb' ORDER BY Name ASC";
        $stmt = $this->connection->executeQuery($sql);
        $num_rows = $stmt->rowCount();    
        while (($row = $stmt->fetchAssociative()) !== false) {
          $this->Wettbewerbe[]=$row;
        }
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
        $my_script_txt = <<< EOT
          <script language="javascript" type="text/javascript">
          function createWettbewerb() {
            var url =  '/fussball/anzeigewettbewerb/n/-1';
            jQuery.get(url, function(data, status){
              jQuery("#eingabe").html(data['data']);
            });
         }
         function wettbewerbBearbeiten(obj) {
           // erzeuge form zur bearbeitung
           var id=obj.name;	 
           var url =  '/fussball/anzeigewettbewerb/u/'+id;
           console.log('wettbewerb Bearbeiten '+url);
           jQuery.get(url, function(data, status){
             jQuery("#eingabe").html(data['data']);
           }); 
         }  
         function wettbewerbLoeschen(obj) {
           var id=obj.name;	 
  	       var par = "ID=" + id + "&aktion=d";
           var par = jQuery("#inputForm :input").serialize() + "&ID=" + id + "&aktion=d"; 
           var url =  '/fussball/bearbeitewettbewerb/d/'+id;
//alert('wettbewerb Löschen '+url);
           jQuery.get(url, function(data, status){
             jQuery("#result").html(data['data']);        // ergebnis in div result ablegen
           });
        }
        function wettbewerbAktuell(obj) {
          var id=obj.name;	 
  	      var par = "ID=" + id + "&aktion=a";
          var par = jQuery("#inputForm :input").serialize()  + "&ID=" + id + "&aktion=a"; 
          //var url =  "bundles/hoyzer/verwaltung/bearbeiteWettbewerb.php?" +par;
          var url =  '/fussball/bearbeitewettbewerb/a/'+id;
//console.log('wettbewerb Aktuell '+url);
          jQuery.get(url, function(data, status){
             //jQuery("#result").html(data['data']);
             location.reload();
          });
        }
        
        </script>
EOT;
 
        $html.=$my_script_txt;        
        $html.=$c->div(array("class"=>"contentverwaltung"));
        $html.=$c->div(array("id"=>"eingabe")) . $c->end_div();    
        $html.=$c->div(array("id"=>"result")) . $c->end_div();
        $html.='aktueller Wettbewerb('.$this->aktWettbewerb['id'].'): '.$this->aktWettbewerb['aktWettbewerb'].'<br>';
        $html.=$c->Button(array("onClick"=>"createWettbewerb();"),"Neuer Wettbewerb","createWettbewerb") . "\n"; 
        
        $html.=$c->start_form("", null,null,array("id"=>"inputForm"));
        $html.=$c->table(array("class"=>"verwtablecss  sortierbar","border"=>1));
        $html.=$c->thead();
        $html.=$c->tr();
          $html.=$c->th("&nbsp;").$c->th("A").$c->th(array("class"=>"sortierbar"),"Wettbewerb").$c->th(array("class"=>"sortierbar"),"AnzGruppen").$c->th(array("class"=>"sortierbar"),"Deutschlandgruppe").$c->th(array("class"=>"sortierbar"),"startDatum").$c->th(array("class"=>"sortierbar"),"endeDatum"); 
        $html.=$c->end_tr();
        $html.=$c->end_thead();
        $html.=$c->tbody();
        
        foreach ($this->Wettbewerbe as $k=>$row) {
          $Wb=$row["Value1"];
          $AG=$row["Value2"];
          $DG=$row["Value3"];
          if (empty($DG)) $DG="";
          $sD=$row["Value4"];
          $eD=$row["Value5"];
          $akt=$row['Aktuell'];
          $html.=$c->tr();
            $html.=$c->td();
	          $html.=$c->Button(array("onClick"=>"wettbewerbBearbeiten(this);","title"=>"Wettbewerb bearbeiten"),'B',$row['ID'])."\n";
              $html.=$c->Button(array("onClick"=>"wettbewerbLoeschen(this);","title"=>"Wettbewerb loeschen")  ,"L",$row['ID'])."\n";
              $html.=$c->Button(array("onClick"=>"wettbewerbAktuell(this);","title"=>"Wettbewerb wird aktueller")   ,"A",$row['ID'])."\n";
            $html.=$c->end_td();
            if ($akt != 0) $html.=$c->td("A");
            else $html.=$c->td("&nbsp;");
            $html.=$c->td($Wb).$c->td($AG).$c->td($DG).$c->td($sD).$c->td($eD); 
          $html.=$c->end_tr();
        }
        $html.=$c->end_tbody();
        $html.=$c->end_table();
        $html.=$c->end_form();
        $html.=$c->end_div(); 
        $response = new Response($html,Response::HTTP_OK,['content-type' => 'text/html']);
        return $response;
        //return $template->getResponse();
    }
}
