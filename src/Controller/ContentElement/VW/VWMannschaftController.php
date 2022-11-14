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

namespace PBDKN\FussballBundle\Controller\ContentElement\VW;

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
 * Class VWMannschaftController
 *
 * @ContentElement(VWMannschaftController::TYPE, category="fussball-Verwaltung")
 */
class VWMannschaftController extends AbstractFussballController
{
    public const TYPE = 'Mannschaften';
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
    
    private $Mannschaften = array();

    public function __construct(
      DependencyAggregate $dependencyAggregate, 
      ContaoFramework $framework, 
      TwigEnvironment $twig, 
      HtmlDecoder $htmlDecoder, 
      ?SymfonyResponseTagger $responseTagger)    
    {
        \System::log('PBD Mannschaftcontroller ', __METHOD__, TL_GENERAL);

        parent::__construct($dependencyAggregate);  // standard Klassen plus akt. Wettbewerb lesen
                                                    // AbstractFussballController übernimmt sie in die akt Klasse
        \System::log('PBD Mannschaftcontroller nach dependencyAggregate', __METHOD__, TL_GENERAL);
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
        // alle aktuellen Mannschaften einlesen
        $sql="SELECT * FROM hy_mannschaft WHERE Wettbewerb='".$this->aktWettbewerb['aktWettbewerb']."' ORDER BY Name"; 
        $stmt = $this->connection->executeQuery($sql);
        $num_rows = $stmt->rowCount();    
        while (($row = $stmt->fetchAssociative()) !== false) {
          $this->Mannschaften[]=$row;
        }
        $my_script_txt = <<< EOT
          <script language="javascript" type="text/javascript">
            function neueMannschaft() {
              var url =  '/fussball/anzeigemannschaft/n/-1';
console.log(" bearbeiten url " + url);
              jQuery.get(url, function(data, status){
                jQuery("#result").html(data['data']);
            });
         }
         function mannschaftBearbeiten(obj) {  // mit jQuery
           var id=obj.name;	 
	       var par = "ID=" + id + "&aktion=u";
           var url =  '/fussball/anzeigemannschaft/u/'+id;
console.log(" bearbeiten url " + url);
           jQuery.get(url, function(data, status){
//alert ("data " + " status " + status);
             jQuery("#result").html(data['data']);
           });
         }    

         function mannschaftLoeschen(obj) {
           var id=obj.name;	 
  	       var par = "ID=" + id + "&aktion=d";
           var url =  '/fussball/bearbeitemannschaft/d/'+id;
console.log(" mannschaftLoeschen url " + url);
           jQuery.get(url, function(data, status){
             errortxt=data['error'];
console.log(" mannschaftLoeschen errortxt " + errortxt);
             if (errortxt != '') {
               jQuery("#result").html(errortxt);
             } else {
console.log(" mannschaftLoeschen reload " + data['error']);
               location.reload();
             }
           });
         }
         
        </script>
EOT;
        $html.=$my_script_txt;        
        $html.=$c->div(array("class"=>"contentverwaltung"));
        $html.=$c->div(array("id"=>"eingabe")) . $c->end_div();    
        $html.=$c->div(array("id"=>"result")) . $c->end_div();
        $html.='aktueller Wettbewerb('.$this->aktWettbewerb['id'].'): '.$this->aktWettbewerb['aktWettbewerb'].'<br>';
        $html.=$c->Button(array("onClick"=>"neueMannschaft();"),"Neue Mannschaft","createMannschaft") . "\n"; 
        
        $html.=$c->start_form("", null,null,array("id"=>"inputForm"));
        $html.=$c->table(array("class"=>"verwtablecss  sortierbar","border"=>1));
        $html.=$c->thead();
        $html.=$c->tr();
          $html.=$c->th("&nbsp;").$c->th(array("class"=>"sortierbar"),"ID").$c->th(array("class"=>"sortierbar"),"Name");
          $html.=$c->th(array("class"=>"sortierbar"),"Nation").$c->th(array("class"=>"sortierbar"),"Flagge").$c->th(array("class"=>"sortierbar"),"Gruppe");
        $html.=$c->end_tr();
        $html.=$c->end_thead();
        $html.=$c->tbody();
        
        foreach ($this->Mannschaften as $k=>$row) {
          $html.=$c->tr();
  	        $html.=$c->td();
	          $html.=$c->Button(array("onClick"=>"mannschaftBearbeiten(this);"),"B",$row['ID']) . "\n";
              $html.=$c->Button(array("onClick"=>"mannschaftLoeschen(this);"),"L",$row['ID']) . "\n";
  	        $html.=$c->end_td();
  	        $html.=$c->td((string)$row['ID']).$c->td($row['Name']).$c->td($row['Nation']). "\n";
	        $html.=$c->td();
              $v= "&nbsp;<img src='".$this->fussballUtil->getImagePath($row['Flagge'])."' title='".ucfirst($row['Nation'])."' >";
	          $html.="$v";
	        $html.=$c->end_td();
            $html.=$c->td($row['Gruppe']);
	      $html.=$c->end_tr() . "\n";
      }
      $response = new Response($html,Response::HTTP_OK,['content-type' => 'text/html']);
      return $response;
    }
}
