<?php

declare(strict_types=1);

/*

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
 * Class VWTeilnehmerController
 *
 * @ContentElement(VWTeilnehmerController::TYPE, category="fussball-Verwaltung")
 */
class VWTeilnehmerController extends AbstractFussballController
{
    public const TYPE = 'Teilnehmer';
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
    
    private $Teilnehmer = array();

    public function __construct(
      DependencyAggregate $dependencyAggregate, 
      ContaoFramework $framework, 
      TwigEnvironment $twig, 
      HtmlDecoder $htmlDecoder, 
      ?SymfonyResponseTagger $responseTagger)    
    {
        \System::log('PBD TeilnehmerController ', __METHOD__, TL_GENERAL);

        parent::__construct($dependencyAggregate);  // standard Klassen plus akt. Wettbewerb lesen
                                                    // AbstractFussballController übernimmt sie in die akt Klasse
        \System::log('PBD TeilnehmerController nach dependencyAggregate', __METHOD__, TL_GENERAL);
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
      function createMannschaftOption ($conn,$cgi,$Wettbewerb,$name,$selected,$gruppe) {
  	    $sql =  "select ";
	    $sql .= " gruppen.ID AS ID,";
	    $sql .= " gruppen.Gruppe AS Gruppe,";
	    $sql .= " gruppen.Platz as Platz,";
        $sql .= " mannschaft1.Nation as 'M1',";
        $sql .= " mannschaft1.Name as 'M1Name',";
        $sql .= " mannschaft1.ID as 'M1Ind',";
        $sql .= " flagge1.Image as 'Flagge1'";
	    $sql .= " FROM hy_gruppen as gruppen";
        $sql .= " LEFT JOIN hy_mannschaft AS mannschaft1 ON gruppen.M1 = mannschaft1.ID";
        $sql .= " LEFT JOIN hy_flagge AS flagge1 ON flagge1.ID = mannschaft1.flgindex";
        $sql .= " WHERE gruppen.Wettbewerb  ='$Wettbewerb'";
        if ($gruppe != -1) {
	      $sql .= " AND gruppen.Gruppe = '$gruppe'";
	    }
        $str='';	
        //$str.=$cgi->td("<br>mannschaftgruppeoption<br>sql: $sql<br>");	
//echo "selected $selected<br>";
        $debug.="erzeuge createMannschaftOption<br>sql: $sql<br>";
        $stmt = $conn->executeQuery($sql);
        $anz = $stmt->rowCount();

        $optarray= array();
	    $optarray['Keine Angabe'] = -1;
        while (($row = $stmt->fetchAssociative()) !== false) {
          $optarray[$row['M1Name']] = $row['M1Ind'];
        }     
        $str.=$cgi->select($name, $optarray,$selected);
        return $str;
      }
      function createAllMannschaftOption ($conn,$cgi,$Wettbewerb,$name,$selected) {
        // selected ist der Index der Mannschaft
        $html='';
	    $sql = "select ID,Name From hy_mannschaft where Wettbewerb='$Wettbewerb' ORDER BY Name ASC";
        $debug.="erzeuge createAllMannschaftOption<br>sql: $sql<br>";
        $stmt = $conn->executeQuery($sql);
        $anz = $stmt->rowCount();
        $optarray= array();
	    $optarray['Keine Angabe'] = -1;
        while (($row = $stmt->fetchAssociative()) !== false) {
          $optarray[$row['Name']] = $row['ID'];
        }     

        $html.=$cgi->select($name, $optarray,$selected);
        return $html;
      }

    
      function 	erzeugeWetttabelle($conn,$cgi,$Wettbewerb,$tid,$debug) {
        $debug.="erzeugeWetttabelle $tid wettbewerb $Wettbewerb<br>";
        $str='';
        $sql =  "SELECT ";
	    $sql .= " hy_wetteaktuell.ID as ID,";
	    $sql .= " hy_wetteaktuell.Wettbewerb as Wettbewerb,";
    	$sql .= " hy_wetteaktuell.W1 as W1,";
	    $sql .= " hy_wetteaktuell.W2 as W2,";
	    $sql .= " hy_wetteaktuell.W3 as W3,";
        $sql .= " hy_wetteaktuell.Wette as Hywettenindex,";
	    $sql .= " hy_wetten.Art as Art,";
	    $sql .= " hy_wetten.Tipp1 as Tipp1,";
	    $sql .= " hy_wetten.Tipp2 as Tipp2,";
	    $sql .= " hy_wetten.Tipp3 as Tipp3,";
	    $sql .= " hy_wetten.Tipp4 as Tipp4,";
	    $sql .= " hy_wetten.Kommentar as Kommentar";
	    $sql .= " FROM hy_wetteaktuell";
	    $sql .= " LEFT JOIN hy_wetten ON hy_wetteaktuell.Wette = hy_wetten.ID";
	    $sql .= " WHERE hy_wetteaktuell.Wettbewerb = '$Wettbewerb' AND hy_wetteaktuell.Teilnehmer = $tid";
	    $sql .= " ORDER BY hy_wetten.Kommentar";
        $debug.="erzeuge Wetttabelle<br>sql: $sql<br>";
        $stmt = $conn->executeQuery($sql);
        $anz = $stmt->rowCount();

	    $wetten = array();
        while (($row = $stmt->fetchAssociative()) !== false) {
          $wetten[] = $row;
        }     
        $str .= $cgi->table(array("border"=>"1")) . "\n";
        $str.=$cgi->thead();
        $str.=$cgi->tr();
          $str.=$cgi->th("&nbsp;").$cgi->th("ID").$cgi->th("WettenIndex").$cgi->th("Kommentar").$cgi->th("Art").$cgi->th("Tipp1").$cgi->th("Tipp2").$cgi->th("Tipp3").$cgi->th("Tipp4");
        $str.=$cgi->end_tr();
        $str.=$cgi->end_thead();
        $str.=$cgi->tbody();
//$str.=$cgi->td('sql wetten: '.$sql);
        // alle Wetten eines Teilnehmers abarbeiten
        // $w ist das Ergebis des sql incl Join
        // indices Tipp1, Tipp2, Tipp3, Tipp4     Werte aus der Tabelle hy_wetten
        //         W1,W2,W3,W4                    Werte der getippten aus hy_wetteaktuell
	    foreach ($wetten as $w) {
	      $wettindex=$w['ID'];    // Id aus wetteaktuell
	      $kommentar=$w['Kommentar'];
	      $id=$w['ID'];
	      $Hywettenindex=$w['Hywettenindex'];
          //$str.=$cgi->hidden("Wette$wettindex", -1);                 // zur weitergabe bei speichern übernehmen
	      $str.=$cgi->tr();
          $str.=$cgi->td($cgi->Button(array("onClick"=>"wetteSpeichern(this);","title"=>"Wette speichern"),"S",$id)) . "\n";
	      $str.=$cgi->td((string)$id);
	      $str.=$cgi->td((string)$Hywettenindex);
	      $str.=$cgi->td((string) $kommentar);
	      $Art=strtolower((string)$w['Art']);
	      $str.=$cgi->td((string)$Art);
          $cgi->td($w['Tipp1']).$cgi->td($w['Tipp2']).$cgi->td($w['Tipp3']).$cgi->td($w['Tipp4']);          
    // Bestimmung der gewetteten Werte Interpretation je nach Wett Typ
          $Wetten1=$w['Tipp1'];            // Wert1 aus hy_wetten nur der ist relevant und wird abhaengig vom Wetttyp interpretiert
          $Wetten2=$w['Tipp2'];       
          $Wetten3=$w['Tipp3'];
          $Wetten4=$w['Tipp4'];
	      $W1=$w['W1'];            // aktuelle Werte aus hy_wetteaktuell des Teilnehmers werden abhaenig vom Wetttyp interpretiert
          $W2=$w['W2'];
          $W3=$w['W3'];
	      $str.=$cgi->td((string)$w['Tipp1']);  // Wert aus Wetten
         
	      if ($Art == 's') {    // Spielausgang Tipp 1 = Spiel

            // Spiel einlesen
            $sql  = "SELECT";
            $sql .= " mannschaft1.Name as 'M1Name',";
            $sql .= " mannschaft2.Name as 'M2Name'";
            $sql .= " FROM hy_spiele";
            $sql .= " LEFT JOIN hy_mannschaft AS mannschaft1 ON hy_spiele.M1 = mannschaft1.ID";
            $sql .= " LEFT JOIN hy_mannschaft AS mannschaft2 ON hy_spiele.M2 = mannschaft2.ID";            
            $sql .= " WHERE hy_spiele.Wettbewerb  ='".$Wettbewerb."' AND hy_spiele.ID=".$w['Tipp1'].";";
            $stmt = $conn->executeQuery($sql);
            $num_rows = $stmt->rowCount();    
            $row = $stmt->fetchAssociative();
            $T1=$w['Tipp2'];
            $T2=$w['Tipp3'];

            $str.=$cgi->hidden("W3$wettindex", -1);                    // zur weitergabe bei übernehmen
            $str.=$cgi->hidden("W4$wettindex", -1);                    // zur weitergabe bei übernehmen
	  	    $str.=$cgi->td($row['M1Name']."/".$row['M2Name']."<br>".$T1.":".$T2);
            $str.=$cgi->td(array("valign"=>"top"),"T1: ".$cgi->textfield(array("name"=>"W1$wettindex","id"=>"W1$wettindex","value"=>"$W1","size"=>"4"))) . "\n";
            $str.=$cgi->td(array("valign"=>"top"),"T2: ".$cgi->textfield(array("name"=>"W2$wettindex","id"=>"W2$wettindex","value"=>"$W2","size"=>"4"))) . "\n";
	      }
	      if ($Art == 'v') {    // Zahl Platz
            $str.=$cgi->hidden("W2$wettindex", -1);                    // zur weitergabe bei übernehmen
            $str.=$cgi->hidden("W3$wettindex", -1);                    // zur weitergabe bei übernehmen
	        $str.=$cgi->td($w['Tipp1']);                               // Wert wird aus der Wettentabelle genommen
            $str.=$cgi->td(array("valign"=>"top"),"T1: ".$cgi->textfield(array("name"=>"W1$id","id"=>"W1$id","value"=>"$W1","size"=>"4"))) . "\n";
	        $str.=$cgi->td("&nbsp;");
          }
	      if ($Art == 'p') {    // Mannschaft 
            $str.=$cgi->hidden("W2$wettindex", -1);                    // zur weitergabe bei übernehmen
            $str.=$cgi->hidden("W3$wettindex", -1);                    // zur weitergabe bei übernehmen
	        //$str.=$cgi->td($w['Tipp1'].'abc');
  	        $sql =  "SELECT hy_mannschaft.Name as 'M1Name',hy_mannschaft.ID as 'M1Ind' FROM hy_mannschaft WHERE Wettbewerb ='".$Wettbewerb."' AND ID=".$w['Tipp1'].";";
            $stmt = $conn->executeQuery($sql); 
            $row = $stmt->fetchAssociative();
            $erster=$row['M1Name'];
	        $str.=$cgi->td($row['M1Name']);
	        $s1=createAllMannschaftOption ($conn,$cgi,$Wettbewerb,"W1$wettindex",$W1);
	        $str.=$cgi->td($W1 . $s1);
	        $str.=$cgi->td("&nbsp;");
          }
	      if ($Art == 'g') {    // Gruppen erster / Zweiter / Dritter

            // gruppe einlesen nach Platz sortiert
            $sql= "SELECT Platz,mannschaft1.Name as 'M1Name' ,mannschaft1.ID as 'M1Ind' FROM  `hy_gruppen`"; 
            $sql.=" LEFT JOIN hy_mannschaft AS mannschaft1 ON hy_gruppen.M1 = mannschaft1.ID"; 
            $sql.=" WHERE hy_gruppen.wettbewerb='".$Wettbewerb."' AND hy_gruppen.Gruppe='".$w['Tipp1']."' ORDER BY Platz";
            $stmt = $conn->executeQuery($sql); 
            $Pl=array();
            while (($row = $stmt->fetchAssociative()) !== false) {
              $Pl[] = $row;
            }     
	        $str.=$cgi->td('Gruppe: '.$w['Tipp1']."<br>1) ".$Pl[0]['M1Name']."<br>2) ".$Pl[1]['M1Name']."<br>3) ".$Pl[2]['M1Name']); 
		    $g1=createMannschaftOption ($conn,$cgi,$Wettbewerb,"W1$wettindex",$W1,$w['Tipp1']);
		    $g2=createMannschaftOption ($conn,$cgi,$Wettbewerb,"W2$wettindex",$W2,$w['Tipp1']);
		    $g3=createMannschaftOption ($conn,$cgi,$Wettbewerb,"W3$wettindex",$W3,$w['Tipp1']);
	        $str.=$cgi->td("<br>1) ".$g1."<br>2) ".$g2."<br>3) ".$g3);   
	      }

	    $str.=$cgi->end_tr();
//        break;
      }
      $str.=$cgi->end_tbody().$cgi->end_table() . "\n";
	  return $str;
    }
    
        //$template->text = $model->text;
        $c=$this->cgiUtil;
        $html="";
        // alle Teilnehmer einlesen
        $sql  = "SELECT";
        $sql .= " teilnehmer.ID as 'ID',";
        $sql .= " teilnehmer.Name as 'Name',";
        $sql .= " teilnehmer.Kurzname as 'Kurzname',";
        $sql .= " teilnehmer.Email as 'Email'";
        $sql .= " FROM hy_teilnehmer as teilnehmer";
        $sql .= " WHERE Wettbewerb  ='".$this->aktWettbewerb['aktWettbewerb']."' ";
        $sql .= " ORDER BY teilnehmer.Kurzname  ;";
        $stmt = $this->connection->executeQuery($sql);
        $num_rows = $stmt->rowCount();    
        //$html.="num_rows $num_rows sql:<br>$sql<br>";
        while (($row = $stmt->fetchAssociative()) !== false) {
          $this->Teilnehmer[]=$row;
        }
        $my_script_txt = <<< EOT
          <script language="javascript" type="text/javascript">
          function neuerSpieler() {                        // Spielergebnis Wette
	        var par = "aktion=n";
            var url =  '/fussball/anzeigeteilnehmer/n/-1';
            console.log ('neuerSpieler url: '+url);
            jQuery.get(url, function(data, status){
              console.log ('res da');
              jQuery("#eingabe").html(data['data']);
            });
          }
          function teilnehmerBearbeiten(obj) {
            var id=obj.name;	 
	        var par = "aktion=u&ID="+id;
            var url =  '/fussball/anzeigeteilnehmer/u/'+id;
            console.log ('teilnehmerBearbeiten url: '+url);
            jQuery.get(url, function(data, status){
              jQuery("#eingabe").html(data['data']);
            });
          }
          function teilnehmerLoeschen(obj) {
            var id=obj.name;	 
  	        var par = "aktion=d&ID=" + id + "&aktion=d";
            //var url =  "bundles/hoyzer/verwaltung/bearbeiteTeilnehmer.php?" + par;
            var url =  '/fussball/bearbeiteteilnehmer/d/'+id;
            console.log ('teilnehmerLoeschen url: '+url);
            jQuery.get(url, function(data, status){
              jQuery("#eingabe").html(data['data']);
            });
          }
          function wettenZeigen(obj) {
              var divid="wett" + obj.name;
            console.log ('wettenZeigen divid: '+divid);
	          if(jQuery('#'+divid).css('display')=="none") {
	            jQuery('#'+divid).css('display',"block");
	          } else {
	            jQuery('#'+divid).css('display',"none");
	          }
          }
          function wetteSpeichern(obj) {
            var id= obj.name;
//            wettindex=obj.parentElement.parentElement.childNodes[3].innerText
//            tlnrwettindex=obj.parentElement.parentElement.childNodes[2].innerText
//            debugger;
          var _par = jQuery("#inputForm :input").serialize();   // ich habe den Eindruck nur so bekomme ich die Werte
//console.log('par: '+_par);
          var _inputArr = _par.split("&");
          let myA=[];
          for (var x = 0; x < _inputArr.length; x++) {
            var _kv = _inputArr[x].split("=");
            myA[_kv[0]] = _kv[1];
            //console.log(_kv);
          }
            
            var Wette = "Wette" +  obj.name; 
            var W1 = "W1" +  obj.name; 
            var W2 = "W2" +  obj.name;
            var W3 = "W3" +  obj.name;
            var V1=myA[W1]
            var V2=myA[W2]
            var V3=myA[W3]
            var par = "aktion=s&ID=" + id + "&W1=" + V1 + "&W2=" + V2 + "&W3=" + V3;
            console.log ('wetteSpeichern par: '+par);
            var url='/fussball/storeteilnehmerwette/s/'+id+'/'+V1+'/'+V2+'/'+V3;
            console.log ('wetteSpeichern url: '+url);
            jQuery.get(url, function(data, status){
              jQuery("#eingabe").html(data['data']);
            });
          }
        </script>
EOT;
        $html.=$my_script_txt;        
        $html.=$c->div(array("class"=>"contentverwaltung"));
        $html.=$c->div(array("id"=>"eingabe")) . $c->end_div();    
        $html.=$c->div(array("id"=>"result")) . $c->end_div();
        $html.='aktueller Wettbewerb('.$this->aktWettbewerb['id'].'): '.$this->aktWettbewerb['aktWettbewerb'].'<br>';
        $html.=$c->Button(array("onClick"=>"neuerSpieler();"),"Neuer Spieler","Neu") . "\n";
        
        $html.=$c->start_form("", null,null,array("id"=>"inputForm"));
        $html.=$c->table(array("class"=>"verwtablecss","border"=>1));
        $html.=$c->thead();
          $html.=$c->tr();
            $html.=$c->th("&nbsp;").$c->th("ID").$c->th("Name").$c->th("Kurzname").$c->th("Email");
          $html.=$c->end_tr();
        $html.=$c->end_thead();
        $html.=$c->tbody();
        
        foreach ($this->Teilnehmer as $k=>$tln) {
          $tid = $tln['ID'];
          $html.=$c->tr();
          $html.=$c->td();
            $html.=$c->Button(array("onClick"=>"teilnehmerBearbeiten(this);","title"=>"Teilnehmer bearbeiten"),"B",$tid) . "\n";
            $html.=$c->Button(array("onClick"=>"teilnehmerLoeschen(this);","title"=>"Teilnehmer l&ouml;schen"),"L",$tid) . "\n";
            $html.=$c->Button(array("onClick"=>"wettenZeigen(this);","title"=>"Wetten anzeigen"),"W",$tid) . "\n";
          $html.=$c->end_td();
          $html.=$c->td((string) $tln['ID']).$c->td($tln['Name']).$c->td($tln['Kurzname']).$c->td($tln['Email']);
	      $html.=$c->end_tr() . "\n";
	      $html.=$c->tr().$c->td(array("colspan"=>"5"));
	        $html.='<div id=wett'.$tid.' style="display:none;">';
	        $html.=erzeugeWetttabelle($this->connection,$c,$this->aktWettbewerb['aktWettbewerb'],$tid,$html);
	        $html.="</div>";	
	      $html.=$c->end_td();	
	      $html.=$c->end_tr()."\n";
      }
      $html.=$c->end_tbody().$c->end_table().$c->end_form();
      $response = new Response($html,Response::HTTP_OK,['content-type' => 'text/html']);
      return $response;
    }
}
