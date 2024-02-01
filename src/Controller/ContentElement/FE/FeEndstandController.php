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
 * Class FeEndstandController
 *
 * @ContentElement(FeEndstandController::TYPE, category="fussball-FE")
 */
class FeEndstandController extends AbstractFussballController
{
    public const TYPE = 'AnzeigeEndstand';
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
        \System::log('PBD FeEndstandController ', __METHOD__, TL_GENERAL);

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
     function löscheTlnPunkte($conn,$Wettbewerb) {
        $value = "SET Punkte=0";
	    $sql = "update tl_hy_teilnehmer $value where Wettbewerb='$Wettbewerb'";
//echo "sql: $sql<br>";	
	    $conn->executeQuery ($sql);	
//        echo "ausgef&uuml;hrt";	
     }
     /*
      * Parameter 
      * Art = Arte der Wette (s  .
      * W1, W2 abgegebne Werte
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

     function berechnePunkte($Art,$W1,$W2,$Tipp1,$Tipp2,$Tipp3,$Pok,$Ptrend) {
       $debug = false;
       if ($debug) echo "berechnePunkte($Art,$W1,$W2,$Tipp1,$Tipp2,$Tipp3,$Pok,$Ptrend)<br>";
         //berechnePunkte(S,3,1,159,0,3,3,1)
         if (strtolower ($Art) == 's') {
	       // Spielwette
	       if ($W1 == -1 || $W2 == -1) {
             if ($debug) echo "W1 oder W2 -1 Punkte 0<br>";	  
	         return 0;
	        }
	        if ($Tipp2 == -1 || $Tipp3 == -1) {
              if ($debug) echo "Tipp2 oder Tipp3 -1 Punkte 0<br>";	  
	          return 0;
	        }
            if ($W1 == $Tipp2 && $W2 == $Tipp3) {	  
              if ($debug) echo "Treffer Punkte $Pok<br>";	  
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
              if ($debug) echo "Trend Punkte $Ptrend<br>";	  
	          return $Ptrend;
	        }
            if ($debug) echo "Falsch Punkte 0<br>";	  	  
	        return 0;
        } else if (strtolower ($Art) == 'g') {
	    $Punkte = 0;
	      if ($W1 == -1 || $W2 == -1) return $Punkte;
	      if ($W1 == $Tipp2) $Punkte = $Punkte + $Pok;
	      if ($W2 == $Tipp3) $Punkte = $Punkte + $Pok;
	      if ($W1 == $Tipp3) $Punkte = $Punkte + $Ptrend;
	      if ($W2 == $Tipp2) $Punkte = $Punkte + $Ptrend;
	      return $Punkte;
        } else if (strtolower ($Art) == 'v') {
	      if ($W1 == -1) return 0;
	      if ($W1 == $Tipp1) return $Pok;
	      return 0;
        } else if (strtolower ($Art) == 'p') {
	      if ($W1 == -1) return 0;
	      if ($W1 == $Tipp1) return $Pok;
	      return 0;
	    }  
     }
  
      // extrafunktion für Gruppenpunkte
      /*
       * Parameter 
       * Art = Arte der Wette (s  .
       * W1, W2, W3 abgegebne Werte
       * Tipp1,Tipp2,Tipp3 tatsächliche werte
       * Bedeutung von Art
       * g  Spiele Wette  Platz 1 Platz 2
       *     W1 = Manschaftsindex Platz 1 (gewettet)
       *     W2 = Manschaftsindex Platz 2 (gewettet)
       *     W3 = Manschaftsindex Platz 3 (gewettet)
       *     GRP = Index auf die Gruppe
       *     Tipp1 =  Manschaftsindex Platz 1 (erreicht)
       *     Tipp2 =  Manschaftsindex Platz 2 (erreicht)
       *     Tipp3 =  Manschaftsindex Platz 3 (erreicht)
       *     Pok = Anzahl Punkte erreicht, wenn exakt
       *     Ptrend = Anzahl Punkte wenn der Trend stimmt
       */
  
     function berechneGruppenPunkte($Art,$W1,$W2,$W3,$GRP,$Tipp1,$Tipp2,$Tipp3,$Pok,$Ptrend) {
       $debug = false;
       if ($debug) echo "\nberechneGruppenPunkte($Art,$W1,$W2,$W3,$GRP,$Tipp1,$Tipp2,$Tipp3,$Pok,$Ptrend)<br>\n";
	   $Punkte = 0;
       if (strtolower ($Art) == 'g') {
	     if ($W1 == -1 || $W2 == -1 || $W3 == -1) return $Punkte;
	     if ($W1 == $Tipp1) $Punkte = $Punkte + $Pok;
	     if ($W2 == $Tipp2) $Punkte = $Punkte + $Pok;
	     //if ($W3 == $Tipp3) $Punkte = $Punkte + $Pok;
      
	     //if ($W1 == $Tipp2 || $W1 == $Tipp3) $Punkte = $Punkte + $Ptrend;
	     //if ($W2 == $Tipp1 || $W2 == $Tipp3) $Punkte = $Punkte + $Ptrend;
	     //if ($W3 == $Tipp1 || $W3 == $Tipp2) $Punkte = $Punkte + $Ptrend;
         if ($W1 == $Tipp2 ) $Punkte = $Punkte + $Ptrend;
         if ($W2 == $Tipp1 ) $Punkte = $Punkte + $Ptrend;
       }
       if ($debug) echo "Punkte: $Punkte<br>\n";
	   return $Punkte;
     }
     function getPunkte($conn,$ID) {
       $sql = "Select Punkte from tl_hy_teilnehmer WHERE ID='$ID'";
	   $conn->executeQuery($sql);
	   $Row=$conn->listen(MYSQL_ASSOC);
	   $pkt = $Row['Punkte'];
	   return $pkt;
     }  

     function addierePunkte($conn,$points,$ID) {
       $sql = "Select Punkte from tl_hy_teilnehmer WHERE ID='$ID'";
       $stmt = $conn->executeQuery ($sql);
       $num_rows = $stmt->rowCount();  
       $pkt = $points;  
       if (($Row = $stmt->fetchAssociative()) !== false) {
         $pkt=$Row['Punkte'] + $points;
       }
       $value = "SET ";
       $value .= "Punkte=" . $pkt ." " ; 
	   $sql = "update tl_hy_teilnehmer $value where ID='$ID'";
//echo "sql: $sql<br>";	
       //$conn->printerror=true;
	   $conn->executeQuery ($sql);
     }
     
     /*
      * $c = cgiUtil
      * $f = fussballUtil
      * $conn = datenbankconnection
      * $Wettbewerb = Wettbewrb as String
      * $deutschlandgruppe = Deutschlandgruppe as String
      * 
      * res gerenderte deutschlandwetten
      */
     
     function createDeutschlandgruppe($c,$f,$conn,$Wettbewerb,$deutschlandgruppe ) {
       //echo "createDeutschlandgruppe<br>";
       $debug = false;
	   // lese die gewetteten Spiele
       $res="";               // result html
	   $rowcnt=0;
       $sql  = "SELECT";
       $sql .= " tl_hy_spiele.ID as 'ID',";
       $sql .= " tl_hy_spiele.Nr as 'Nr',";
       $sql .= " tl_hy_spiele.Gruppe as 'Gruppe',";
       $sql .= " tl_hy_spiele.M1 as 'M1Ind',";
       $sql .= " mannschaft1.Nation as 'M1',";
       $sql .= " mannschaft1.Flagge as 'Flagge1',";
       $sql .= " tl_hy_spiele.M2 as 'M2Ind',";
       $sql .= " mannschaft2.Nation as 'M2',";
       $sql .= " mannschaft2.Flagge as 'Flagge2',";
       $sql .= " tl_hy_spiele.Ort as 'OrtInd',";
       $sql .= " tl_hy_orte.Ort as 'Ort',";
       $sql .= " DATE_FORMAT(tl_hy_spiele.Datum,'%e.%m.%y') as 'Datum',";
       $sql .= " DATE_FORMAT(tl_hy_spiele.Uhrzeit,'%H:%S') as 'Uhrzeit',";
       $sql .= " tl_hy_spiele.T1 as 'T1',";
       $sql .= " tl_hy_spiele.T2 as 'T2'";
       $sql .= " FROM tl_hy_spiele";
       $sql .= " LEFT JOIN tl_hy_mannschaft AS mannschaft1 ON tl_hy_spiele.M1 = mannschaft1.ID";
       $sql .= " LEFT JOIN tl_hy_mannschaft AS mannschaft2 ON tl_hy_spiele.M2 = mannschaft2.ID";
       $sql .= " LEFT JOIN tl_hy_orte ON tl_hy_spiele.Ort = tl_hy_orte.ID";
       $sql .= " WHERE tl_hy_spiele.Wettbewerb  = '$Wettbewerb'  AND LOWER(tl_hy_spiele.Gruppe) like '$deutschlandgruppe' ";
       $sql .= " ORDER BY tl_hy_spiele.Datum ASC , tl_hy_spiele.Uhrzeit ASC ;";
       if ($debug) echo "SQL<br>$sql<br>";	
	   $tippspiele=array();
       $stmt = $conn->executeQuery ($sql);
       $num_rows = $stmt->rowCount();    
       while (($row = $stmt->fetchAssociative()) !== false) {
	     if ((strtolower($row['M1']) == 'deutschland') || (strtolower($row['M2']) == 'deutschland')) $tippspiele[] = $row;
	   }
       if ($debug)  {	
         echo "<br>--------tippspiele alle Deutschland Spiele-------------------------<br>";
         foreach ($tippspiele as $k=>$tln) {
           echo "<br>";
           foreach ($tln as $k1=>$v1) echo "  $k1: $v1  ";
         } 
       }
	
	   // lese nur eine Wette die das deutschlandspiel beinhalten.
       // also die Angaben zu Ptrend ....
	   // in row wird der Wettstand ausgelesen 
	   $sql =  "Select";
	   $sql .= " Pok, Ptrend from tl_hy_wetten where LOWER(Kommentar) like '%deutschlandspiel%'  AND wettbewerb='$Wettbewerb' LIMIT 1;";
       if ($debug)  echo " SQL wettstand $sql<br>";	
       $rowstmt = $conn->executeQuery ($sql);
       $anz = $rowstmt->rowCount();    
       if ($anz == 0) {      // keine Spiele vorhanden
         //$str.=file_get_contents ("html_Templates/pageHeader.html");
         $res.="Keine Deutschlandspiele in den Wetten";
         //$str.=file_get_contents ("html_Templates/pageFooter.html");
         //echo "Keine Deutschlandspiele in den Wetten<br>\n";
         return $res;
       }
       $row = $rowstmt->fetchAssociative();
         $sql  = "SELECT";
         $sql .= " tl_hy_teilnehmer.ID as 'ID'";
         $sql .= " , tl_hy_teilnehmer.Kurzname as 'Kurzname'";
         $sql .= " ,tl_hy_teilnehmer.Name as 'Name'";
         $sql .= " , tl_hy_wetteaktuell.W1 as 'W1'";    // Wettwert 1 des Teilnehmers
         $sql .= " , tl_hy_wetteaktuell.W2 as 'W2'";    // Wettwert 2 des Teilnehmers
         $sql .= " , tl_hy_wetteaktuell.Wette as 'Windex'";
         $sql .= " , tl_hy_wetten.Art as 'Art'";       // S
         $sql .= " , tl_hy_wetten.Tipp1 as 'Tipp1'";   // Spiel
         $sql .= " , tl_hy_wetten.Tipp2 as 'Tipp2'";   // irrelevantWird aus Spiel gelesen
         $sql .= " , tl_hy_wetten.Tipp3 as 'Tipp3'";   // irrelevantWird aus Spiel gelesen
         $sql .= " ,tl_hy_spiele.Datum as 'Datum'";   //
         $sql .= " , tl_hy_wetten.Pok as 'Pok'";   //
         $sql .= " , tl_hy_wetten.Ptrend as 'Ptrend'";   // 
         $sql .= " FROM tl_hy_teilnehmer";
         $sql .= " LEFT JOIN tl_hy_wetteaktuell  ON tl_hy_wetteaktuell.Teilnehmer = tl_hy_teilnehmer.ID";
         $sql .= " LEFT JOIN tl_hy_wetten  ON tl_hy_wetteaktuell.Wette = tl_hy_wetten.ID";
         $sql .= " LEFT JOIN tl_hy_spiele  ON tl_hy_spiele.ID = tl_hy_wetten.Tipp1";
         $sql .= " WHERE tl_hy_teilnehmer.Wettbewerb  = '$Wettbewerb' ORDER BY tl_hy_teilnehmer.Kurzname ASC, Datum ASC ";
         $sql .= ";";
         if ($debug) echo "SQL<br>$sql<br>";	

         $stmt = $conn->executeQuery ($sql);   // lies die Wetten die zu den Teilnehmer gehoeren
         $anz = $stmt->rowCount();    
         if ($debug) echo "Anzahl: $anz<br>";
         $teilnehmer=array();
         while (($rowtln = $stmt->fetchAssociative()) !== false) {
           $teilnehmer[]=$rowtln;
	     }
         if ($debug)  {	
           echo "<br>--------Teilnehmer Wetten Deutschland-------------------------<br>";
           foreach ($teilnehmer as $k=>$tln) {
             echo "<br>";
             foreach ($tln as $k1=>$v1) echo "  $k1: $v1  ";             
           }
           echo "<br>--------Teilnehmer Wetten Deutschland-------------------------<br>";
         }
  
         //$res.=.=file_get_contents ("html_Templates/pageHeader.html");
       $res.=$c->table(array("class"=>"tablecss","rules"=>"all","border"=>"1","cellspacing"=>"1","cellpadding"=>"5"));
       $res.=$c->tr(array("height"=>"30"));
       $res.=$c->th(array("colspan"=>"4","align"=>"center","height"=>"30"),"<b>Deutschland Gruppenspiele</b>");
       //$str.=$c->th(array("width"=>"122"),"<input class='druck' type='button' onclick='print()' value='Drucken'></h2>");
       $res.=$c->end_tr();
       $res.=$c->tr();
       $res.=$c->td(array("align"=>"left","valign"=>"top"),"Name");	   
	   foreach ($tippspiele as $sp) {   // alle Deutschlandspiele mit akt Ergebnis und Ptren/Pok ausgeben
	     $fl1 = "<img src='".$f->getImagePath($sp['Flagge1']). "' >&nbsp;";
	     $fl2 = "<img src='".$f->getImagePath($sp['Flagge2']). "' >&nbsp;";
	     $str="";
	     if ($debug) $str .= "<b>Deutschland Spiel Nr: " . $sp['ID'] . " "; 
	     $str .=$sp['Datum'] . "<br>";
	     $str .= $fl1 . $sp['M1'] . " : " . $sp['T1'] . "<br>";
	     $str .= $fl2 . $sp['M2'] . " : " . $sp['T2'] . "<br>";
         $str .= "Punkte:" . $row['Ptrend'] ."/". $row['Pok'];
         
         if ($debug) echo "str: $str<br>\n";		  
         $res.=$c->td(array("align"=>"left","valign"=>"top"),$str);	   
       }
       $res.=$c->td(array("align"=>"left","valign"=>"top"),"Gesamtpunkte");	   
       $res.=$c->end_tr();         // Deutschlandspiel mit ptrend und pok eine Zeile 
       $akttlnmr="";
	   $gesamtsumme=0;
	   $gesamttext="";
	   foreach ($teilnehmer as $tln) {   // alle Spiele aus der Deutschlandgruppe nach teilnehmer bewerten
           $sp="";
           foreach ($tippspiele as $spiel) {
             //$res.=$c->td("spiel sp Art ".$tln['Art'].' Tipp1 '.$tln['Tipp1'].' ID '.$sp['ID']);
	         if (strtolower ($tln['Art']) == 's' && $tln['Tipp1'] == $spiel['ID']) { // Spiel gefunden
               $sp=$spiel;
             }
           }
           if ($sp == "") continue;
         $rowcnt++;
         if ($tln['Kurzname'] != $akttlnmr) { // neue Zeile
           if ( $akttlnmr != "") {
		       // Vorher noch Gesamtsumme ausgeben
		     $res.=$c->td("$gesamttext = $gesamtsumme");
		     $gesamttext="";
		     $gesamtsumme=0;
		     $res.=$c->end_tr();
		   }
         
		   $res.=$c->tr();
		   $res.=$c->td($tln['Name']);
		   $akttlnmr = $tln['Kurzname'];
           if ($debug) echo "neuer Teilnehmer $akttlnmr<br>";
         }
           //$res.=$c->td("spiel tln Art ".$tln['Art']);
             //$res.=$c->td("spiel sp Art ".$tln['Art'].' Tipp1 '.$tln['Tipp1'].' ID '.$sp['ID']);
               if ($debug) echo "Spiel gefunden M1: ".$sp['M1']." M2: ".$sp['M2']."<br";
		       $str=" gewettet: " . $tln['W1'] . ":" . $tln['W2'] . "<br>";
		       $points=berechnePunkte($tln['Art'],$tln['W1'],$tln['W2'],$tln['Tipp1'],$sp['T1'],$sp['T2'],$tln['Pok'],$tln['Ptrend']);
		       if ( $points >0 ) { addierePunkte($conn,$points,$tln['ID']);	 }
		  
		       $str .= "Punkte $points";
		       $gesamtsumme = $gesamtsumme + $points;
		       if ($gesamttext == "") $gesamttext = "$points";
		       else $gesamttext .= " + $points";
               if ($debug) echo "str: $str<br>\n";		  
               $res.=$c->td($str);
           
      }          // schleife teilnehmer
         if ( $akttlnmr != "")  {
           // Vorher noch Gesamtsumme ausgeben
	       $res.=$c->td("$gesamttext = $gesamtsumme");
	       $gesamttext="";
	       $gesamtsumme=0;
	       $res.=$c->end_tr();
	     }

      $res.=$c->end_table();
      //$res.=file_get_contents ("html_Templates/pageFooter.html");
      return $res;
     }                   // ende Deutschlandgruppe
    
     $c=$this->cgiUtil;
     $html="";
     $Wettbewerb=$this->aktWettbewerb['aktWettbewerb'];
     $deutschlandgruppe=$this->aktWettbewerb['aktDGruppe'];
     $conn=$this->connection;
      
     löscheTlnPunkte($this->connection,$Wettbewerb);
     //echo "<br>Alle Teilnehmerpunkte gel&ouml;scht<br>";
     //echo "<br>erzeuge Deutschlandgruppe<br>";
     $html.=createDeutschlandgruppe($this->cgiUtil,$this->fussballUtil,$conn,$Wettbewerb,$deutschlandgruppe);
     $html.=$c->end_tbody();
     $response = new Response($html,Response::HTTP_OK,['content-type' => 'text/html']);
     return $response;
 

    }
}
