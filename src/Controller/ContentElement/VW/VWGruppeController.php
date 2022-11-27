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
 * Class VWGruppeController
 *
 * @ContentElement(VWGruppeController::TYPE, category="fussball-Verwaltung")
 */
class VWGruppeController extends AbstractFussballController
{
    public const TYPE = 'Gruppen verwalten manuell';
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
    
    private $Gruppen = array();

    public function __construct(
      DependencyAggregate $dependencyAggregate, 
      ContaoFramework $framework, 
      TwigEnvironment $twig, 
      HtmlDecoder $htmlDecoder, 
      ?SymfonyResponseTagger $responseTagger)    
    {
        \System::log('PBD VWGruppencontroller ', __METHOD__, TL_GENERAL);

        parent::__construct($dependencyAggregate);  // standard Klassen plus akt. Wettbewerb lesen
                                                    // AbstractFussballController übernimmt sie in die akt Klasse
//        \System::log('PBD VWGruppencontroller nach dependencyAggregate', __METHOD__, TL_GENERAL);
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
    function replace16Bit($str) {
      // ersetze 16-bit Values
      $search  = array("\xC3\xA4", "\xC3\xB6", "\xC3\xBC", "\xC3\x84", "\xC3\x96","\xC3\x9f");
      $replace = array('ä', 'ö', 'ü', 'Ä', 'Ö','Ü','ß');
      $str= str_replace($search, $replace, $str);     
      return $str;     
    }
    
        //$template->text = $model->text;
        $c=$this->cgiUtil;
        $html="";
        // alle aktuelle Gruppen/Mannschaften einlesen
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
        $sql .= " WHERE hy_gruppen.Wettbewerb  ='".$this->aktWettbewerb['aktWettbewerb']."'";
        $sql .= " ORDER BY hy_gruppen.Gruppe ASC , hy_gruppen.Platz ASC ;";

        $stmt = $this->connection->executeQuery($sql);
        $num_rows = $stmt->rowCount();    
        while (($row = $stmt->fetchAssociative()) !== false) {
          $this->Gruppen[]=$row;
        }
        $my_script_txt = <<< EOT
          <script language="javascript" type="text/javascript">
          function gruppeBearbeiten(obj) {  // mit jQuery
            var id=obj.name;	 
	        var par = "ID=" + id + "&aktion=u";
            var url =  '/fussball/anzeigegruppe/u/'+id;
console.log(" Gruppe bearbeiten url " + url);
            jQuery.get(url, function(data, status){
//console.log ("data " + " status " + status);
              jQuery("#result").html(data['data']);
            });
          }    
          function createAlleGruppen() {
            var url =  '/fussball/bearbeitegruppe/a/-1';
console.log(" neue Gruppen url " + url);
            jQuery.get(url, function(data, status){
              errortxt=data['error'];
console.log(" createAlleGruppen errortxt " + errortxt);
              if (errortxt != '') {
                jQuery("#result").html(errortxt);
              } else {
                jQuery("#result").html(data['data']+'<br>'+data['debug']);
                location.reload();
              }
            });
          }
          function gruppeLoeschen(obj) {
            var id=obj.name;	 
            var url =  '/fussball/bearbeitegruppe/d/'+id;
console.log(" Gruppe Loeschen url " + url);
            jQuery.get(url, function(data, status){
              errortxt=data['error'];
console.log(" Gruppe Loeschen errortxt " + errortxt);
              if (errortxt != '') {
                jQuery("#result").html(errortxt);
             } else {
console.log(" Gruppe Loeschen reload " + data['error']);
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
//        $html.=$c->Button(array("onClick"=>"neueGruppe();"),"Neue Gruppe","createGruppe") . "\n"; 
        $html.=$c->Button(array("onClick"=>"createAlleGruppen();"),"Alle Gruppen erstellen","createAlleGruppen") . "\n"; 
        
        $html.=$c->start_form("", null,null,array("id"=>"inputForm"));
        $html.=$c->table(array("class"=>"verwtablecss  sortierbar","border"=>1));
        $html.=$c->thead();
        $html.=$c->tr();
          $html.=$c->th("&nbsp;").$c->th("ID").$c->th(array("class"=>"sortierbar"),"Gruppe").$c->th(array("class"=>"sortierbar"),"M1Ind");
          $html.=$c->th(array("class"=>"sortierbar"),"M1").$c->th(array("class"=>"sortierbar"),"Platz");
          $html.=$c->th(array("class"=>"sortierbar"),"Spiele").$c->th(array("class"=>"sortierbar"),"Sieg").$c->th(array("class"=>"sortierbar"),"Unentschieden");
          $html.=$c->th(array("class"=>"sortierbar"),"Niederlage").$c->th(array("class"=>"sortierbar"),"Tore").$c->th(array("class"=>"sortierbar"),"Gegentore");
          $html.=$c->th(array("class"=>"sortierbar"),"Differenz").$c->th(array("class"=>"sortierbar"),"Punkte");
        $html.=$c->end_tr();
        $html.=$c->end_thead();
        $html.=$c->tbody();
        
        foreach ($this->Gruppen as $k=>$rowGr) {
          $html.=$c->tr();
  	        $html.=$c->td();
	          $html.=$c->Button(array("onClick"=>"gruppeBearbeiten(this);"),"B",$rowGr['ID']);
  	        $html.=$c->end_td();
  	        $html.=$c->td((string)$rowGr['ID']).$c->td((string)$rowGr['Gruppe']).$c->td((string)$rowGr['M1Ind']). "\n";
	        $html.=$c->td();
	          $html.="<img src='".$this->fussballUtil->getImagePath($rowGr['Flagge1']). "' >&nbsp;" . $rowGr['M1Name'] ;
	        $html.=$c->end_td();
            $html.=$c->td((string)$rowGr['Platz']);
            $html.=$c->td((string)$rowGr['Spiele']);
  	        $html.=$c->td((string)$rowGr['Sieg']).$c->td((string)$rowGr['Unentschieden']).$c->td((string)$rowGr['Niederlage']);
            $html.=$c->td((string)$rowGr['Tore']).$c->td((string)$rowGr['Gegentore'])."\n";
            $html.=$c->td((string)$rowGr['Differenz']).$c->td((string)$rowGr['Punkte'])."\n";
	      $html.=$c->end_tr() . "\n";
      }
      $html=replace16Bit($html);
      $html = utf8_encode($html);      
      $response = new Response($html,Response::HTTP_OK,['content-type' => 'text/html']);
      return $response;
    }
}
