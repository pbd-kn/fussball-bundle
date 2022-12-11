<?php

declare(strict_types=1);

/*

 * 
 * (c) Peter 2022 <pb-tester@gmx.de>
 * ce fussball/wettbewerb
 * stellt alle Wettbewerbe dar
 */

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
 * Class FePunkteController
 *
 * @ContentElement(FePunkteController::TYPE, category="fussball-FE")
 */
class FePunkteController extends AbstractFussballController
{
    public const TYPE = 'TeilnehmerPunkte';
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
    
    private $Teilnehmer = array();     // enthaelt alle Teilnehmer fuer die Wetten eingerichtet wurden
    private $Wetten = array();         // enthaelt alle Wetten die Werte Tipp1 bis Tipp4 verwenden es muss also ein Wetten update gemacht sein.
    private $Mannschaften= array();
    public function __construct(
      DependencyAggregate $dependencyAggregate, 
      ContaoFramework $framework, 
      TwigEnvironment $twig, 
      HtmlDecoder $htmlDecoder, 
      ?SymfonyResponseTagger $responseTagger)    
    {
        \System::log('PBD PunkteController ', __METHOD__, TL_GENERAL);

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
      /*
       * Parameter 
       * Art = Arte der Wette (s  .
       * W1, W2 abgegebene Werte
       * Tipp1,Tipp2,Tipp3 tatsächliche werte
       * Bedeutung von Art
       * s  Spiele Wette  auf Tore
       *     W1 = Anzahl Tore Mannnschaft 1 (gewettet)
       *     W2 = Anzahl Tore Mannnschaft 2 (gewettet)
       *     Tipp1 = Index auf das Spiel
       *     Tipp2 =  Anzahl Tore Mannnschaft 1 (erreicht)
       *     Tipp3 =  Anzahl Tore Mannnschaft 2 (erreicht)
       *     Pok = Anzahl Punkte erreicht, wenn exakt
       *     Ptrend = Anzahl Punkte wenn der Trend stimmt
       * g  Spiele Wette  Platz 1 Platz 2
       *     W1 = Manschaftsindex Platz 1 (gewettet)
       *     W2 = Manschaftsindex Platz 2 (gewettet)
       *     Tipp1 = Index auf die Gruppe
       *     Tipp2 =  Manschaftsindex Platz 1 (erreicht)
       *     Tipp3 =  Manschaftsindex Platz 2 (erreicht)
       *     Pok = Anzahl Punkte erreicht, wenn exakt
       *     Ptrend = Anzahl Punkte wenn der Trend stimmt
       * v  Spiele Wette  Platz 1 Platz 2
       *     W1 = Manschaftsindex Platz 1 (gewettet)
       *     W2 = Manschaftsindex Platz 2 (gewettet)
       *     Tipp1 = Index auf die Gruppe
       *     Tipp2 =  Manschaftsindex Platz 1 (erreicht)
       *     Tipp3 =  Manschaftsindex Platz 2 (erreicht)
       *     Pok = Anzahl Punkte erreicht, wenn exakt
       *     Ptrend = Anzahl Punkte wenn der Trend stimmt
       */
       function berechnePunkte($Art,$W1,$W2,$Tipp1,$Tipp2,$Tipp3,$Pok,$Ptrend,&$deb="") {
         if ($deb != "") $debug = true;
         else $debug = false;
         if ($debug) $deb.="berechnePunkte($Art,$W1,$W2,$Tipp1,$Tipp2,$Tipp3,$Pok,$Ptrend)<br>";
         //berechnePunkte(S,3,1,159,0,3,3,1)
         if ($Art == 's') {
    	   // Spielwette
	       if ($W1 == -1 || $W2 == -1) {
             if ($debug) $deb.="W1 oder W2 -1 Punkte 0<br>";	  
	         return 0;
	       }
	       if ($Tipp2 == -1 || $Tipp3 == -1) {
             if ($debug) $deb.="Tipp2 oder Tipp3 -1 Punkte 0<br>";	  
	         return 0;
	       }
	       if ($W1 == $Tipp2 && $W2 == $Tipp3) {	  
             if ($debug) $deb.="Treffer Punkte $Pok<br>";	  
	         return $Pok;  
	       }
	       $erg = 0;             // -1 = Mannnschaft 1 gewonnen 0 unentschieden 1 Mannnschaft 2 gewonnen
	       if ($Tipp2 == $Tipp3) {
	         $erg = 0;
	       } else if ($Tipp2 > $Tipp3) {
	         $erg = -1;
	       } else {
	         $erg = 1;
	       }
//echo "tatsächlich $erg ";
	       $werg = 0;             // -1 = Mannnschaft 1 gewonnen 0 unentschieden 1 Mannnschaft 2 gewonnen
	       if ($W1 == $W2) {
	         $werg = 0;
	       } else if ($W1 > $W2) {
	         $werg = -1;
	       } else {
	         $werg = 1;
	       }
//echo " gewettet $werg<br>";
	       if ( $erg == $werg) {
             if ($debug) $deb.="Trend Punkte $Ptrend<br>";	  
	         return $Ptrend;
	       }
        if ($debug) $deb.="Falsche Punkte 0<br>";	  	  
	    return 0;
      } else if ($Art == 'g') {
	    $Punkte = 0;
	    if ($W1 == -1 || $W2 == -1) return $Punkte;
	    if ($W1 == $Tipp2) $Punkte = $Punkte + $Pok;
	    if ($W2 == $Tipp3) $Punkte = $Punkte + $Pok;
	    if ($W1 == $Tipp3) $Punkte = $Punkte + $Ptrend;
	    if ($W2 == $Tipp2) $Punkte = $Punkte + $Ptrend;
	    return $Punkte;
      } else if ($Art == 'v') {
	    if ($W1 == -1) return 0;
	    if ($W1 == $Tipp1) return $Pok;
	    return 0;
      } else if ($Art == 'p') {
	    if ($W1 == -1) return 0;
	    if ($W1 == $Tipp1) return $Pok;
	    return 0;
	  }  
    }

    
      // $tid = Teilnehmerindex
      // $wetten = Wettenarray nach wettindex sortiert
      function 	erzeugePunktetabelle($conn,$cgi,$Wettbewerb,$tid,$wetten,$mannschaften,&$debug="") {
        if ($debug != "") $deb=true;
        else $deb=false;
        if ($deb)$debug.="erzeugeWetttabelle $tid wettbewerb $Wettbewerb len wetten ".count($wetten)."<br>"; 
        $str='';
        $sql =  "SELECT ";
	    $sql .= " wetteaktuell.ID as ID,";
	    $sql .= " wetteaktuell.Wettbewerb as Wettbewerb,";
	    $sql .= " wetteaktuell.Teilnehmer as Teilnehmer,";
        $sql .= " wetteaktuell.Wette as Hywettenindex,";
    	$sql .= " wetteaktuell.W1 as W1,";
	    $sql .= " wetteaktuell.W2 as W2,";
	    $sql .= " wetteaktuell.W3 as W3";
	    $sql .= " FROM hy_wetteaktuell as wetteaktuell";
	    $sql .= " WHERE wetteaktuell.Wettbewerb = '$Wettbewerb' AND wetteaktuell.Teilnehmer = $tid";
	    //$sql .= " ORDER BY hy_wetten.Kommentar";
        $stmt = $conn->executeQuery($sql);
        $anz = $stmt->rowCount();

	    $aktwetten = array();
        while (($row = $stmt->fetchAssociative()) !== false) {      
          $aktwetten[$row['Hywettenindex']] = $row;    //  aktuelle Wetten nach dem Wettindex der Wetten sortiert
        } 
        $str .= $cgi->table(array("border"=>"1")) . "\n";
        $str.=$cgi->thead();
        $str.=$cgi->tr();
          //$str.=$cgi->th("&nbsp;").$cgi->th("ID").$cgi->th("WettenIndex").$cgi->th("Kommentar").$cgi->th("Art").$cgi->th("Tipp1").$cgi->th("Tipp2").$cgi->th("Tipp3").$cgi->th("Tipp4");
          $str.=$cgi->th("ID").$cgi->th("WettenIndex").$cgi->th("Kommentar").$cgi->th("Art").$cgi->th("Ergebnis").$cgi->th("Wette").$cgi->th("Punkte");
        $str.=$cgi->end_tr();
        $str.=$cgi->end_thead();
        $str.=$cgi->tbody();
        // alle Wetten eines Teilnehmers abarbeiten
        // $w ist das Ergebis des sql incl Join
        // indices Tipp1, Tipp2, Tipp3, Tipp4     Werte aus der Tabelle hy_wetten
        //         W1,W2,W3,W4                    Werte der getippten aus hy_wetteaktuell
	    foreach ($aktwetten as $Wind=>$Aktw) {    // wind ist gleichzeitig der Index für die Wettentabelle
	      $Hywettenindex=$Wind;
	      $wettindex=$Aktw['ID'];    // Id aus wetteaktuell
          $id=$Aktw['ID'];
          //continue;
          $kommentar=$wetten[$Wind]['Kommentar'];
          //$str.=$cgi->hidden("Wette$wettindex", -1);                 // zur weitergabe bei speichern übernehmen
	      $str.=$cgi->tr();
	      $str.=$cgi->td((string)$id);
	      $str.=$cgi->td((string)$Hywettenindex);
	      $str.=$cgi->td((string) $kommentar);
          $Art=strtolower((string)$wetten[$Hywettenindex]['Art']);
	      //$str.=$cgi->td("Hywettenindex $Hywettenindex Art $Art");
//return $str;
	      //$Art=strtolower((string)$Aktw['Art']);
	      $str.=$cgi->td((string)$Art);
    // Bestimmung der gewetteten Werte Interpretation je nach Wett Typ
          $Tipp1=$wetten[$Wind]['Tipp1'];    
          $Tipp2=$wetten[$Wind]['Tipp2'];       
          $Tipp3=$wetten[$Wind]['Tipp3'];
          $Tipp4=$wetten[$Wind]['Tipp4'];
          $Pok=$wetten[$Wind]['Pok'];
          $Ptrend=$wetten[$Wind]['Ptrend'];
          $cgi->td($Tipp1).$cgi->td($Tipp2).$cgi->td($Tipp3).$cgi->td($Tipp4);          
	      $W1=$Aktw['W1'];            // aktuelle Werte aus hy_wetteaktuell des Teilnehmers werden abhaenig vom Wetttyp interpretiert
          $W2=$Aktw['W2'];
          $W3=$Aktw['W3'];
          $Punkte=berechnePunkte($Art,$W1,$W2,$Tipp1,$Tipp2,$Tipp3,$Pok,$Ptrend,$debug);         
	      if ($Art == 's') {    // Spielausgang Tipp 1 = Spiel
            // Spiel einlesen
            $sql  = "SELECT";
            $sql .= " mannschaft1.Name as 'M1Name',";
            $sql .= " mannschaft2.Name as 'M2Name'";
            $sql .= " FROM hy_spiele";
            $sql .= " LEFT JOIN hy_mannschaft AS mannschaft1 ON hy_spiele.M1 = mannschaft1.ID";
            $sql .= " LEFT JOIN hy_mannschaft AS mannschaft2 ON hy_spiele.M2 = mannschaft2.ID";            
            $sql .= " WHERE hy_spiele.Wettbewerb  ='".$Wettbewerb."' AND hy_spiele.ID=".$Tipp1.";";
           //$debug.="Manschaft sql $sql";
            $stmt = $conn->executeQuery($sql);

            $num_rows = $stmt->rowCount();    
            $row = $stmt->fetchAssociative();
            $T1=$W1;
            $T2=$W2;

	  	    $str.=$cgi->td((string)$row['M1Name']."/".(string)$row['M2Name']."($Tipp2/$Tipp3)").$cgi->td("$T1/$T2");
	  	    $str.=$cgi->td((string)$Punkte);
	      }
	      if ($Art == 'v') {    // Zahl Platz
	        $str.=$cgi->td("$Tipp1").$cgi->td("$W1");                               // Wert wird aus der Wettentabelle genommen
	  	    $str.=$cgi->td((string)$Punkte);
          }
	      if ($Art == 'p') {    // Mannschaft 
  	        $sql =  "SELECT hy_mannschaft.Name as 'M1Name',hy_mannschaft.ID as 'M1Ind' FROM hy_mannschaft WHERE Wettbewerb ='".$Wettbewerb."' AND ID=".$Tipp1.";";
            $stmt = $conn->executeQuery($sql); 
            $row = $stmt->fetchAssociative();
            $erster=$row['M1Name'];
	        $str.=$cgi->td($row['M1Name']." ($Tipp1)/".$W1);
	        //$s1=createAllMannschaftOption ($conn,$cgi,$Wettbewerb,"W1$wettindex",$W1);
	        //$str.=$cgi->td($W1 . $s1);
	  	    $str.=$cgi->td((string)$Punkte);
          }
	      if ($Art == 'g') {    // Gruppen erster / Zweiter / Dritter

            // gruppe einlesen nach Platz sortiert
            $sql= "SELECT Platz,mannschaft1.Name as 'M1Name' ,mannschaft1.ID as 'M1Ind' FROM  `hy_gruppen`"; 
            $sql.=" LEFT JOIN hy_mannschaft AS mannschaft1 ON hy_gruppen.M1 = mannschaft1.ID"; 
            $sql.=" WHERE hy_gruppen.wettbewerb='".$Wettbewerb."' AND hy_gruppen.Gruppe='".$Tipp1."' ORDER BY Platz";
            $stmt = $conn->executeQuery($sql); 
            $num_rows = $stmt->rowCount();    
            $Pl=array();
            while (($row = $stmt->fetchAssociative()) !== false) {
              $Pl[] = $row;
            }     
	        $str.=$cgi->td('Gruppe: '.$Tipp1."<br>1) ".$Pl[0]['M1Name']."<br>2) ".$Pl[1]['M1Name']."<br>3) ".$Pl[2]['M1Name']); 
		    //$g1=createMannschaftOption ($conn,$cgi,$Wettbewerb,"W1$wettindex",$W1,$w['Tipp1']);
		    //$g2=createMannschaftOption ($conn,$cgi,$Wettbewerb,"W2$wettindex",$W2,$w['Tipp1']);
		    //$g3=createMannschaftOption ($conn,$cgi,$Wettbewerb,"W3$wettindex",$W3,$w['Tipp1']);
	        //$str.=$cgi->td("<br>1) ".$g1."<br>2) ".$g2."<br>3) ".$g3);   
	      }

	    $str.=$cgi->end_tr();
      }
      $str.=$cgi->end_tbody().$cgi->end_table() . "\n";
	  return $str;
    }
    function löscheTlnPunkte($conn,$Wettbewerb) {
      $value = "SET Punkte=0";
	    $sql = "update hy_teilnehmer $value where Wettbewerb='$Wettbewerb'";
//echo "sql: $sql<br>";	
        $cnt = $conn->executeStatement($sql);
    }
    
        //$template->text = $model->text;
        $c=$this->cgiUtil;
        $html="";
        $Wettbewerb=$this->aktWettbewerb['aktWettbewerb'];
        // alle Teilnehmer einlesen
        $sql  = "SELECT teilnehmer.ID as 'ID',teilnehmer.Name as 'Name',teilnehmer.Kurzname as 'Kurzname',teilnehmer.Email as 'Email'";
        $sql .= " FROM hy_teilnehmer as teilnehmer WHERE Wettbewerb  ='$Wettbewerb' ORDER BY teilnehmer.Kurzname  ;";
        $stmt = $this->connection->executeQuery($sql);
        $num_rows = $stmt->rowCount();    
        //$html.="num_rows $num_rows sql:<br>$sql<br>";
        while (($row = $stmt->fetchAssociative()) !== false) {
          $this->Teilnehmer[]=$row;
        }
        // alle Wetten einlesen
        $sql  = "SELECT wetten.ID as 'ID',wetten.Art as 'Art',wetten.Kommentar as 'Kommentar'";
        $sql.= ",wetten.Tipp1 as 'Tipp1' ,wetten.Tipp2 as 'Tipp2',wetten.Tipp3 as 'Tipp3',wetten.Tipp4 as 'Tipp4'";
        $sql.= ",wetten.Pok as 'Pok' ,wetten.Ptrend as 'Ptrend'";
        $sql .= " FROM hy_Wetten as wetten WHERE Wettbewerb  ='".$this->aktWettbewerb['aktWettbewerb']."' ";
        $sql .= " ORDER BY wetten.Art  ;";
        $stmt = $this->connection->executeQuery($sql);
        $num_rows = $stmt->rowCount();    
        //$html.="num_rows $num_rows sql:<br>$sql<br>";
        while (($row = $stmt->fetchAssociative()) !== false) {
          $this->wetten[$row['ID']]=$row;               // wetten nach id sortiert
        }
        // alle Mannschaften einlesen
        $sql="SELECT * FROM `hy_mannschaft` WHERE `Wettbewerb`='wm2022'"; 
        $stmt = $this->connection->executeQuery($sql);
        $num_rows = $stmt->rowCount();    
        //$html.="num_rows $num_rows sql:<br>$sql<br>";
        while (($row = $stmt->fetchAssociative()) !== false) {
          $this->Mannschaften[$row['ID']]=$row;               // Manschaften nach id sortiert
        }
        $my_script_txt = <<< EOT
          <script language="javascript" type="text/javascript">
          function punkteDetail(obj) {
              var divid="wett" + obj.name;
            console.log ('wettenZeigen divid: '+divid);
	          if(jQuery('#'+divid).css('display')=="none") {
	            jQuery('#'+divid).css('display',"block");
	          } else {
	            jQuery('#'+divid).css('display',"none");
	          }
          }
        </script>
EOT;
        $html.=$my_script_txt;        
        $html.=$c->div(array("class"=>"contentverwaltung"));
        $html.='aktueller Wettbewerb('.$this->aktWettbewerb['id'].'): '.$this->aktWettbewerb['aktWettbewerb'].'<br>';
        löscheTlnPunkte($this->connection,$Wettbewerb);
        foreach ($this->Teilnehmer as $k=>$tln) {   // vorbereitung
          // erzeuge html-code ür Detail-Ausgabe. 
        }
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
            $html.=$c->Button(array("onClick"=>"punkteDetail(this);","title"=>"Details anzeigen"),"W",$tid) . "\n";
          $html.=$c->end_td();
          $html.=$c->td((string) $tln['ID']).$c->td($tln['Name']).$c->td($tln['Kurzname']).$c->td($tln['Email']);
	      $html.=$c->end_tr() . "\n";
	      $html.=$c->tr().$c->td(array("colspan"=>"5"));
	        $html.='<div id=wett'.$tid.' style="display:none;">';
	        //$html.=erzeugePunktetabelle($this->connection,$c,$this->aktWettbewerb['aktWettbewerb'],$tid,$this->wetten,$this->$Mannschaften,$html);
	        $html.=erzeugePunktetabelle($this->connection,$c,$this->aktWettbewerb['aktWettbewerb'],$tid,$this->wetten,$this->$Mannschaften);
	        $html.="</div>";	
	      $html.=$c->end_td();	
	      $html.=$c->end_tr()."\n";
      }
      $html.=$c->end_tbody().$c->end_table().$c->end_form();
      $response = new Response($html,Response::HTTP_OK,['content-type' => 'text/html']);
      return $response;
    }
}
