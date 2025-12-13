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
     function loescheTlnPunkte($conn,$Wettbewerb) {
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
      * $Wettbewerb = Wettbewerb as String
      * $wherelike = like Parameter fuer Where as String, Auswahl der gruppe (deutschlandgruppe)
      * $teilnehmer = Teilnehmerarray art s mit wettenaktuell und wetten
      * $tabtitle  Tittel in der Ueberschrift
      * $nation = Auswahl der Nation innerhalb der ausgewaehlten Gruppe($wherelike). z.B Spiele nur mit Deutschland
      * 
      * res gerenderte spielwetten
      */
     
     function createSpielegruppe($c,$f,$conn,$Wettbewerb,$wherelike,$teilnehmer,$tabtitle,$nation="") {
       //echo "like Gruppe $wherelike<br>";
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
       $sql .= " WHERE tl_hy_spiele.Wettbewerb  ='$Wettbewerb'  AND LOWER(tl_hy_spiele.Gruppe) like '$wherelike' ";
       $sql .= " ORDER BY tl_hy_spiele.Datum ASC , tl_hy_spiele.Uhrzeit ASC ;";
       if ($debug) echo "SQL<br>$sql<br>";	
	   $tippspiele=array();
       $stmt = $conn->executeQuery ($sql);
       $num_rows = $stmt->rowCount();    
       while (($row = $stmt->fetchAssociative()) !== false) {
         if ($nation != "") {
	       if ((strtolower($row['M1']) == strtolower($nation)) || (strtolower($row['M2']) == strtolower($nation))) $tippspiele[] = $row;
         } else $tippspiele[] = $row;
	   }
       if ($debug)  {	
         echo "<br>--------tippspiele alle  Spiele-------------------------<br>";
         foreach ($tippspiele as $k=>$tln) {
           echo "<br>";
           foreach ($tln as $k1=>$v1) echo "  $k1: $v1  ";
         } 
       }
	
	   // lese Pok und Ptrend aus dem ersten teilnehmersatz.
       $tln=$teilnehmer[array_key_first($teilnehmer)];
       $Pok=$tln['Pok'];
       $Ptrend=$tln['Ptrend'];
       // also die Angaben zu Ptrend ....
  
         //$res.=.=file_get_contents ("html_Templates/pageHeader.html");
       // thead start
       $res.=$c->table(array("class"=>"tablecss","rules"=>"all","border"=>"1","cellspacing"=>"1","cellpadding"=>"5"));
       $res.=$c->thead();                      // start ueberschrift
       $res.=$c->tr(array("height"=>"30"));
       $res.=$c->th(array("colspan"=>count($tippspiele)+2,"align"=>"center","height"=>"30"),"<b>$tabtitle</b>");
       //$str.=$c->th(array("width"=>"122"),"<input class='druck' type='button' onclick='print()' value='Drucken'></h2>");
       $res.=$c->end_tr();
       $res.=$c->tr();
       $res.=$c->td(array("align"=>"left","valign"=>"top"),"Name");	 
       $headerSpielList=array();      // in headerSpielList sind die Ergebnisse der Spiele gespeichert
	   foreach ($tippspiele as $sp) {   // alle Tippspiele mit akt Ergebnis und Ptren/Pok ausgeben
           // spiel ergebnis lesen und in headerSpielList merken
         $headerSpielList[]=$sp;
	     $fl1 = "<img src='".$f->getImagePath($sp['Flagge1']). "' >&nbsp;";
	     $fl2 = "<img src='".$f->getImagePath($sp['Flagge2']). "' >&nbsp;";
	     $str="";
	     if ($debug) $str .= "<b>Spiel Nr: " . $sp['ID'] . " "; 
	     //$str .= "<b>Spiel Nr: " . $sp['ID'] . "<br>"; 
	     $str .=$sp['Datum'] . "<br>";
	     $str .= $fl1 . $sp['M1'] . " : " . $sp['T1'] . "<br>";
	     $str .= $fl2 . $sp['M2'] . " : " . $sp['T2'] . "<br>";
         $str .= "Punkte:" . $Ptrend ."/". $Pok;
         
         //if ($debug) echo "str: $str<br>\n";		  
         $res.=$c->td(array("align"=>"left","valign"=>"top"),$str);	   
       }
       $res.=$c->td(array("align"=>"left","valign"=>"top"),"Gesamtpunkte");	   
       $res.=$c->end_tr();         // Spiel mit ptrend und pok eine Zeile 
       $res.=$c->end_thead();      
       // ende ueberschrift
       $akttlnmr="";
	   $gesamtsumme=0;
	   $gesamttext="";
       $str.=$c->tbody();
	   foreach ($teilnehmer as $tln) {   // alle Spiele aus der teilnehmer bewerten
         if ($tln['Kurzname'] != $akttlnmr) { // neue Zeile wenn neuer Teilnehmer
//echo 'neuer Teilnehmer id '.$tln['ID'].' Kurzname '.$tln['Kurzname'] ." akttlnmr: $akttlnmr<br>";
         if ( $akttlnmr != "") {
		       // Vorher noch Gesamtsumme ausgeben
		   $res.=$c->td("$gesamttext = $gesamtsumme");
		   $gesamttext="";
		   $gesamtsumme=0;
           $teilnehmerId=$tln['ID'];
		   $res.=$c->end_tr();
		 }
         
		 $res.=$c->tr();
		 $res.=$c->td($tln['Name']);
		 $akttlnmr = $tln['Kurzname'];
         if ($debug) echo "neuer Teilnehmer $akttlnmr<br>";
         //}
         foreach ($headerSpielList as $sp) {   // fuer jedes Spiel eine Spalte. identisch mit der Ueberschrift $headSpindex = auszuwertende Spielenummer
           $spielIndex=$sp['ID'];
           
           // aktuelle wette einlesen
           $sql = 'select * from tl_hy_wetteaktuell ';
           $sql .= ' LEFT JOIN tl_hy_wetten ON tl_hy_wetten.ID = tl_hy_wetteaktuell.Wette ';
           $sql .= 'WHERE tl_hy_wetten.Tipp1 = '.$spielIndex.' AND tl_hy_wetteaktuell.Teilnehmer="'.$tln['ID'].'"';
           $sql .= ' AND tl_hy_wetteaktuell.Wettbewerb="'.$Wettbewerb.'"';
           $stmt = $conn->executeQuery ($sql);
           $num_rows = $stmt->rowCount();    
           $aktWetteTlnrow = $stmt->fetchAssociative();
//echo "aktWett sql $sql<br>";
           if ($debug) echo "Spiel gefunden M1: ".$sp['M1']." M2: ".$sp['M2']."<br";
		   //$str="Spiel: ".$sp['ID']."<br>gewettet: " . $tln['W1'] . ":" . $tln['W2'] . "<br>";
		   $str="gewettet: " . $aktWetteTlnrow['W1'] . ":" . $aktWetteTlnrow['W2'] . "<br>";
		   $points=berechnePunkte($aktWetteTlnrow['Art'],$aktWetteTlnrow['W1'],$aktWetteTlnrow['W2'],$aktWetteTlnrow['Tipp1'],$sp['T1'],$sp['T2'],$tln['Pok'],$tln['Ptrend']);
		   if ( $points >0 ) { addierePunkte($conn,$points,$tln['ID']);	 }
		  
		   $str .= "Punkte $points";
		   $gesamtsumme = $gesamtsumme + $points;
		   if ($gesamttext == "") $gesamttext = "$points";
		   else $gesamttext .= " + $points";
           if ($debug) echo "str: $str<br>\n";		  
           $res.=$c->td($str);
         }  
         }
       }          // schleife teilnehmer
       if ( $akttlnmr != "")  {
           // Vorher noch Gesamtsumme ausgeben
	       $res.=$c->td("$gesamttext = $gesamtsumme");
	       $gesamttext="";
	       $gesamtsumme=0;
	       $res.=$c->end_tr();
	    }

       $res.=$c->end_tbody().$c->end_table();
       //$res.=file_get_contents ("html_Templates/pageFooter.html");
       return $res;
     }                   // ende Spielgruppe

     /*
      * $c = cgiUtil
      * $f = fussballUtil
      * $conn = datenbankconnection
      * $Wettbewerb = Wettbewrb as String
      * $teilnehmer = Teilnehmerarray art g mit wettenaktuell und wetten
      * 
      * res gerenderte Gruppenwetten
      */

      function createGruppen($c,$f,$conn,$Wettbewerb,$teilnehmer) {
        $debug = false;  
        $res="";           // Result html
        $rowcnt=0;
        if ($debug) echo "Start Gruppenerzeugung<br>\n";
	    // selectiere alle Gruppen mit den aktuellen Plätzen Gruppen einlesen
        $sql  = "SELECT";
        $sql .= " tl_hy_gruppen.ID as 'ID',";
        $sql .= " tl_hy_gruppen.Gruppe as 'Gruppe',";
        $sql .= " tl_hy_gruppen.M1 as 'M1Ind',";
        $sql .= " mannschaft1.Nation as 'M1',";
        $sql .= " mannschaft1.ID as 'MID',";
        $sql .= " mannschaft1.Flagge as 'Flagge1',";
        $sql .= " tl_hy_gruppen.Platz as 'Platz'";
        $sql .= " FROM tl_hy_gruppen";
        $sql .= " LEFT JOIN tl_hy_mannschaft AS mannschaft1 ON tl_hy_gruppen.M1 = mannschaft1.ID";
        $sql .= " WHERE tl_hy_gruppen.Wettbewerb  ='$Wettbewerb'";
        $sql .= " ORDER BY tl_hy_gruppen.Gruppe ASC , tl_hy_gruppen.Platz ASC ;";
        if ($debug) echo "SQL<br>$sql<br>";	
    	$gruppen=array();
        $stmt = $conn->executeQuery ($sql);
        $num_rows = $stmt->rowCount();    
        while ($row = $stmt->fetchAssociative()) {   // gruppen und ergebnisse merken
	      $gruppen[$row["Gruppe"]][$row["Platz"]] = $row;
	    }

    
        if ($num_rows == 0) {      // keine gruppenwette vorhanden
          //$res.=file_get_contents ("html_Templates/pageHeader.html");
          $res.="Keine Gruppenwetten in den Wetten";
          //$res.=file_get_contents ("html_Templates/pageFooter.html");
          echo "Keine Gruppenwetten in den Wetten<br>\n";
          return $res;
        }
        if ($debug) {
          echo "<br>Gruppen-------------------------------------------------<br>";
          foreach ($gruppen as $k=>$grp) {
            echo "<br>GRUPPE $k<br>";
            foreach ($grp as $k1=>$v1) {
              echo "  $k1:<br> ";
              foreach ($v1 as $k2=>$v2) {
                echo "    $k2: $v2";
    	      }
            }
          }
          echo "<br>-------------------------------------------------------------------<br>";
        }

        // lese Pok und Ptrend result in $row
    	$sql =  "Select";
    	$sql .= " Pok, Ptrend from tl_hy_wetten where LOWER(Art) like 'g' AND wettbewerb='$Wettbewerb 'LIMIT 1;";
        $stmt = $conn->executeQuery ($sql);
        $Pok = 0;
        $Ptrend = 0;
	    if( $row = $stmt->fetchAssociative()) {
          $Pok = $row['Pok'];
          $Ptrend = $row['Ptrend'];
        } 

        //$res.=$c->file_get_contents ("html_Templates/pageHeader.html");
        // Tabellen ausgabe
        $res.=$c->table(array("class"=>"tablecss","rules"=>"all","border"=>"1","cellspacing"=>"1","cellpadding"=>"5"));
        // Header Ueberschrift
        $res.=$c->thead();
        $res.=$c->tr(array("height"=>"30"));
        $res.=$c->th(array("colspan"=>"7","align"=>"center","height"=>"30"),"<b>Gruppenspiele</b>");
        $res.=$c->end_tr();
        $res.=$c->tr();     
        $res.=$c->td(array("align"=>"left","valign"=>"top"),"<b>Name</b>");
	    $str="";
	    foreach ($gruppen as $k=>$grp) {                  // Schleife Gruppen index 1,2 erster zweiter der gruppe in die Ueberschrift
	      if (strlen((string) $k)==1) {       // nur standardgruppen
            if ($debug) echo "erzeuge gruppe  aus gruppentabelle $k <br>";
            if (isset ($grp[1])&&isset ($grp[2])) {   // erster und zweiter vorhanden
	          $erster = $grp[1];
              $zweiter = $grp[2];
              //$dritter = $grp[3];
	          $str = "<b>Gruppe: " . $erster['Gruppe'] . "<br>";      
              if ($debug)	$str  .= $erster['MID'] . " ";                  // Mannschaftsindex
	          $fl1 = "<img src='".$f->getImagePath($erster['Flagge1']). "' >&nbsp;";
	          $str  .= "1: " . $fl1 . " " . $erster['M1'] ."<br>" ;
              if($debug)	$str  .= $zweiter['MID'] . " ";                 // Mannschaftsindex
	          $fl2 = "<img src='".$f->getImagePath($zweiter['Flagge1']). "' >&nbsp;";
	          $str  .= "2: " . $fl2 . " " . $zweiter['M1'] ."<br>" ;	  
              //if($debug)	$str  .= $dritter['MID'] . " ";                 // Mannschaftsindex
              //$fl3 = "<img src='files/hoyzer-wetten/content/" . $dritter['Flagge1'] . "' >&nbsp;";
              //$str  .= "3: " . $fl3 . " " . $dritter['M1'] ."</b>" ;	  
              $res.=$c->td(array("align"=>"left","valign"=>"top"),$str);	   
	        } else {
	          $erster = $grp[-1];
	          $zweiter = $grp[-1];
	          //$dritter = $grp[-1];
	          $str  = "<b>Gruppe: " . $erster['Gruppe'] . "</b><br>";      
	          $str .= "1: noch nicht gesetzt<br>" ;
	          $str .= "2: noch nicht gesetzt<br>" ;
              $res.=$c->td(array("align"=>"left","valign"=>"top"),$str);	   
	        }
          }
	   }  // ende Schleife ueberschriftszeile
       $res.=$c->td(array("align"=>"center","valign"=>"top"),"<b>Punkte</b><br><b>" . $row['Ptrend'] ."/". $row['Pok']."</b>");
       $res.=$c->end_tr();  // Ende ueberschriftszeile
       $res.=$c->end_thead();$res.=$c->tbody();
       $rowcnt++;
       $akttlnmr="";         // aktueller Teilnehmername. bei aenderung nue Zeile ausgeben
       $gesamtsumme=0;       // Summe der Punkte für diesen Teilnehmer (akttlnmr)
	   $gesamttext="";       // Text der Punkte für diesen Teilnehmer (akttlnmr)
       if($debug) echo "tkn lng ".count($teilnehmer)."<br>";
       foreach ($teilnehmer as $tln) {
	     if (strtolower ($tln['Art']) == 'g' ) {     // nur Gruppenwetten Auswerten
           if ($tln['Kurzname'] != $akttlnmr) { // neue Zeile
             if ( $akttlnmr != "") {
		      // Vorher noch Gesamtsumme ausgeben in der aktuellen Zeile für akttlnmr ausgeben
		      $res.=$c->td("$gesamttext = $gesamtsumme");
		      $gesamttext="";
		      $gesamtsumme=0;
		      $res.=$c->end_tr();         // ende Aktueller Teilnehmer
		    }
		    $res.=$c->tr();
		    $res.=$c->td($tln['Name']);
		    $akttlnmr = $tln['Kurzname'];
	      }	 
	      $str = "";
	      $Wette = $tln['Windex'];            // Wettindex in tl_hy_wetten
	      $Tipp1 = $tln['Tipp1'];
		  //$str .= "wettea: " + $Wette + " Tipp1: " + $Tipp1 + "<br>\n"; 
		  $W1 = $tln['W1'];                   // getippter erster
		  $W2 = $tln['W2'];                   // getippter zweiter
		  //$W3 = $tln['W3'];                   // getippter dritter
          $W3=0;
          if ($debug) echo "Teilnehmer  $akttlnmr  Wette $Wette Tipp1 $Tipp1 W1 $W1 W2 $W2 W3 $W3<br>";		
		  //if ($W1 != -1 && $W2 != -1 && $W3 != -1) 
		  if ($W1 != -1 && $W2 != -1 && $W3 != -1) {              // es ist gewettet woreden
		    if (isset($gruppen[$Tipp1][1])) {
              if ($debug) echo "Gruppen tipp1 gesetzt <br>";		  
		      $erster = $gruppen[$Tipp1][1];    // aktuell erster
			  $zweiter = $gruppen[$Tipp1][2];   // aktuell zweiter
			  //$dritter = $gruppen[$Tipp1][3];   // aktuell dritter
			  //$str .= "wette: " . $Wette . " Tipp1: " . $Tipp1 . " W1: " . $W1 . " W2: " . $W2 . " W3: " . $W3;
			  $str .= "wette: " . $Wette . " Tipp1: " . $Tipp1 . " W1: " . $W1 . " W2: " . $W2 ;
              $points=berechneGruppenPunkte($tln['Art'],$tln['W1'],$tln['W2'],0,$tln['Tipp1'],$erster['MID'],$zweiter['MID'],0,$tln['Pok'],$tln['Ptrend']);
		    } else {
			      $points=0;
			}
		    if ( $points >0 ) { addierePunkte($conn,$points,$tln['ID']);	 }
            if ($debug) {
	          $str = "1: " . $tln['M1Ind'] . "&nbsp;" . $tln['M1'] . "<br>2: " . $tln['M2Ind'] . "&nbsp;" . $tln['M2'] .  "&nbsp;" . "<br>Punkte: " . $points;
            } else {		  
		      $str = '1: '.$tln['M1'].'<br>2: '.$tln['M2'].'<br>Punkte: '.$points;
            }	
		    $gesamtsumme = $gesamtsumme + $points;
		    if ($gesamttext == "") $gesamttext = "$points";
		    else $gesamttext .= " + $points";
            if ($debug) echo "str : $str<br>\n";
            $res.=$c->td($str);
		  } else {
            $res.=$c->td("&nbsp;");
		  }
	    }        // ende Abfrage Gruppenwette
	  }            // ende foreach teilnehmer
      if ( $akttlnmr != "")  {
        // Vorher noch Gesamtsumme ausgeben
	    $res.=$c->td("$gesamttext = $gesamtsumme");
	    $gesamttext="";
	    $gesamtsumme=0;
	    $res.=$c->end_tr();
	  }
      $res.=$c->end_tbody().$c->end_table();
      //$res.=$c->file_get_contents ("html_Templates/pageFooter.html");
      return $res;
    }    // ende create Gruppen
     
     /*
      * $c = cgiUtil
      * $f = fussballUtil
      * $conn = datenbankconnection
      * $Wettbewerb = Wettbewrb as String
      * $wetten = verfuegbare wetten zum Wetbewerb value wettenrow index (id)
      * wetten[]
      *    tl_hy_wetten.ID as 'ID'";       
      *    tl_hy_wetten.Art as 'Art'";  
      *    tl_hy_wetten.Tipp1 as 'Tipp1'";         Bei platzwetten index der Mannschaft
      *                                            bei gruppenwetten Gruppenname A,B,C....
      *    tl_hy_wetten.Tipp2 as 'Tipp2'";  
      *    tl_hy_wetten.Tipp3 as 'Tipp3'";  
      *    tl_hy_wetten.Tipp4 as 'Tipp4'";  
      *    tl_hy_wetten.Pok as 'Pok'";   // 
      *    tl_hy_wetten.Ptrend as 'Ptrend'";   
      *    tl_hy_wetten.Kommentar as 'Kommentar'";   
      *    mannschaftP.Nation as 'MPNation'";      Gilt für Platzwette Mannschafts id aus Tipp1
      *    mannschaftP.Flagge as 'MPFlagge'";      Gilt für Platzwette Mannschafts id aus Tipp1
      *    mannschaftP.ID as 'MPInd'";             Gilt für Platzwette Mannschafts id aus Tipp1    
      * 
      * $teilnehmeraktWetten = Akutelle Wetten aller Teilnehmer index strtolower(Name)
      *    tl_hy_teilnehmer.ID as 'ID'";
      *    tl_hy_teilnehmer.Kurzname as 'Kurzname'";
      *    tl_hy_teilnehmer.Name as 'Name'";
      *    tl_hy_wetteaktuell.W1 as 'W1'";    // Wettwert 1 des Teilnehmers
      *    tl_hy_wetteaktuell.W2 as 'W2'";    // Wettwert 2 des Teilnehmers
      *    tl_hy_wetteaktuell.W3 as 'W3'";    // Wettwert 2 des Teilnehmers
      *    tl_hy_wetteaktuell.Wette as 'Windex'";  // index der aktuellen zeigt auf tl_hy_wetten
      *    tl_hy_wetten.Art as 'Art'";       // S
      *    tl_hy_wetten.Tipp1 as 'Tipp1'";   // wette g Gruppe Wette S Spieleindex P Platz wird eigentlich nicht mehr benoetiget  da in Wetten
      *    tl_hy_wetten.ID as 'WettenId'";   // wette g Gruppe Wette S Spieleindex P Platz wird eigentlich nicht mehr benoetiget  da in Wetten
      *    tl_hy_wetten.Pok as 'Pok'";   // 
      *    tl_hy_wetten.Ptrend as 'Ptrend'";            
      *    tl_hy_wetten.Kommentar as 'Kommentar'";   
      *    mannschaft1.Nation as 'M1'";
      *    mannschaft2.Nation as 'M2'";
      *    mannschaft3.Nation as 'M3'";
      *    mannschaftP.Nation as 'MPNation'";           wird eigentlich nicht mehr benoetiget da in Wetten
      *    mannschaft1.ID as 'M1Ind'";
      *    mannschaft2.ID as 'M2Ind'";
      *    mannschaft3.ID as 'M3Ind'";
      *    mannschaftP.ID as 'MPInd'";                   wird eigentlich nicht mehr benoetiget da in Wetten
      *    FROM tl_hy_teilnehmer";
      * 
      * $selectArray = Array das die zu verwendeten Wettarten angibt z.b array("p","v")  Platz und vergleichsweten
      * $tabtitle  Titel in der Ueberschrift
      * 
      * res P-Wette ($teilnehmeraktWetteMeister) V-Wette (Deutchland wird) Kommentare werden uebernommen
      */
    
     function createPuV($c,$f,$conn,$Wettbewerb,$wetten,$teilnehmeraktWetten,$selectArray,$tabtitle) {
       //echo "like Gruppe $wherelike<br>";
       $debug = false;
       $res="";
       $tippwetten=array();    // selektiere relevante Wetten
       foreach ($wetten as $Windex=>$row) {
         //if ($debug) echo "wette Id $Windex Art: ".$row['Art']."<br>";
         if (in_array(strtolower($row['Art']), $selectArray) || in_array($row['Art'], $selectArray)) { // ergibt auch die Reihenfolge der Spalten und der Ueberschrift
           $tippwetten[$Windex]=$row;
           if ($debug) echo "wette Id $Windex gespeichert<br>";
         }
       } 
         
         //$res.=.=file_get_contents ("html_Templates/pageHeader.html");
       $res.=$c->table(array("class"=>"tablecss","rules"=>"all","border"=>"1","cellspacing"=>"1","cellpadding"=>"5"));
       $res.=$c->thead();                      // start ueberschrift
         $res.=$c->tr(array("height"=>"30"));
         $res.=$c->th(array("colspan"=>count($tippwetten)+2,"align"=>"center","height"=>"30"),"<b>$tabtitle</b>");
         //$str.=$c->th(array("width"=>"122"),"<input class='druck' type='button' onclick='print()' value='Drucken'></h2>");
         $res.=$c->end_tr();
         $res.=$c->tr();
         $res.=$c->td(array("align"=>"left","valign"=>"top"),"Name");	   
	     foreach ($tippwetten as $Windex=>$wette) {   // alle Wetten ausgeben in die Ueberschrift uebernehmen
	       if ($debug)$str="$Windex:<br>";
           else $str="";
	       if ($debug)$str .= 'Kommentar: '.$wette['Kommentar']."<br>";
           else $str .= $wette['Kommentar']."<br>";
	       if ($debug) $str .= 'Wert: ';
           if ($wette['Art'] == 'P') {
             $str.=$wette['MPNation'];
             $res.=$c->td(array("align"=>"left","valign"=>"top"),$str);	   
           } else {
             $str.=$wette['Tipp1'];
             $infotext="1: Meister
2: Ausscheiden in Finale
4: Ausscheiden in Halbfinale
8: Ausscheiden in Viertelfinale
16: Ausscheiden in Achtelfinale
32: Ausscheiden in Gruppenspielen
";
             $res.=$c->td(array("align"=>"left","valign"=>"top","title"=>$infotext),$str);	   
           }
         }
         $res.=$c->td(array("align"=>"left","valign"=>"top"),"Gesamtpunkte");	   
         $res.=$c->end_tr();         // Spiel mit ptrend und pok eine Zeile 
       $res.=$c->end_thead();      // ende ueberschrift
       $akttlnmr="";
	   $gesamtsumme=0;
	   $gesamttext="";
	   $gesamtpunkte=0;
       $str.=$c->tbody();
	   foreach ($teilnehmeraktWetten as $akttlnmr=>$tlwetten) {  
	   $gesamtsumme=0;
	   $gesamttext="";
	   $gesamtpunkte=0;
         if ($debug) echo "aktuelle Wetten des Teilnehmers $akttlnmr<br>";
         // selektiere aktuelle Wetten des Teilnehmers
         $akttippwetten=array();    // selektiere relevante Wetten
         foreach ($tlwetten as $k=>$rowaktwett) {
           $Windex=$rowaktwett['Windex'];
           //if ($debug) echo "wette Id $wettId Art: ".$row['Art']."<br>";
           if (isset($tippwetten[$Windex])) {        // ist diese aktuelle Wette in den Wetten vorhanden (sollte sein)
             $akttippwetten[$Windex]=$rowaktwett;    // sortiert nach dem Index von Wetten
             if ($debug) echo "akttippwetten index $k Id $Windex gespeichert<br>";
           }
         } 
         if ($debug) echo "anz akttippwetten: ".count($akttippwetten)."<br>";
         if (count($akttippwetten) == 0) continue;

         //$rowcnt++;
		 $res.=$c->tr();                     // eine zeile pro Teilnehmer
		 $res.=$c->td($akttlnmr);
         $points=0;
         $gesamttext == "";
		 //$akttlnmr = $tln['Kurzname'];
         if ($debug) echo "aktueller Teilnehmer $akttlnmr<br>";
         // Suche aktuelleWette anhand es Wettindex
	     foreach ($tippwetten as $Windex=>$wette) {    // in der Reihenfolge der Spalten
           $tln=$akttippwetten[$Windex];         // Teilnehmer aktuelle Wette
           if ($debug) echo "Windex $Windex Art: ".$tln['Art']."<br>";
         
		   $str=" gewettet: " . $tln['W1'] .":". $tln['M1'] ."<br>";
           if ($debug) echo "$str<br>";
		   $points=berechnePunkte($tln['Art'],$tln['W1'],$tln['W2'],$wette['Tipp1'],$wette['Tipp2'],$wette['Tipp3'],$wette['Pok'],$wette['Ptrend']);
           $str.="Punkte: $points";
	       $res.=$c->td($str);
           if ($debug) echo "Points: $points<br>";
		   if ( $points >0 ) { 
             addierePunkte($conn,$points,$tln['ID']);	
             if ($gesamttext == "") $gesamttext=$points;
             else $gesamttext .= " + $points";
             $gesamtpunkte += $points;
           }
           if ($debug) echo "gesamttext: $gesamttext<br>\n";		  
         }      // Schleife ueber spalten    
         if ($debug) echo "gesamttext: $gesamttext<br>\n";		  
	     $res.=$c->td("$gesamttext = $gesamtpunkte");
	     $res.=$c->end_tr();         
       }// schleife teilnehmer
       $res.=$c->end_tbody();
       $res.=$c->end_table();
       //$res.=file_get_contents ("html_Templates/pageFooter.html");
       return $res;
     }                   // ende createPuV
    
    $c=$this->cgiUtil;
    $conn=$this->connection;
    $Wettbewerb=$this->aktWettbewerb['aktWettbewerb'];

    $html="";
    $debug=false;
    // Teilehmer einlesen mit Wetteaktuell und Wetten
    $sql  = "SELECT";
    $sql .= " tl_hy_teilnehmer.ID as 'ID'";
    $sql .= " ,tl_hy_teilnehmer.Kurzname as 'Kurzname'";
    $sql .= " ,tl_hy_teilnehmer.Name as 'Name'";
    $sql .= " ,tl_hy_wetteaktuell.W1 as 'W1'";    // Wettwert 1 des Teilnehmers
    $sql .= " ,tl_hy_wetteaktuell.W2 as 'W2'";    // Wettwert 2 des Teilnehmers
    $sql .= " ,tl_hy_wetteaktuell.W3 as 'W3'";    // Wettwert 2 des Teilnehmers
    $sql .= " ,tl_hy_wetteaktuell.Wette as 'Windex'";  // index der aktuellen zeigt auf tl_hy_wetten
    $sql .= " ,tl_hy_wetten.Art as 'Art'";       // S
    $sql .= " ,tl_hy_wetten.Tipp1 as 'Tipp1'";   // wette g Gruppe Wette S Spieleindex P Platz
    $sql .= " ,tl_hy_wetten.ID as 'WettenId'";   // wette g Gruppe Wette S Spieleindex P Platz
    $sql .= " ,tl_hy_wetten.Pok as 'Pok'";   // 
    $sql .= " ,tl_hy_wetten.Ptrend as 'Ptrend'";   
    $sql .= " ,tl_hy_wetten.Kommentar as 'Kommentar'";   
  	$sql .= " ,mannschaft1.Nation as 'M1'";
	$sql .= " ,mannschaft2.Nation as 'M2'";
	$sql .= " ,mannschaft3.Nation as 'M3'";
	$sql .= " ,mannschaftP.Nation as 'MPNation'";
	$sql .= " ,mannschaft1.ID as 'M1Ind'";
	$sql .= " ,mannschaft2.ID as 'M2Ind'";
	$sql .= " ,mannschaft3.ID as 'M3Ind'";
	$sql .= " ,mannschaftP.ID as 'MPInd'";
    $sql .= " FROM tl_hy_teilnehmer";
    $sql .= " LEFT JOIN tl_hy_wetteaktuell  ON tl_hy_wetteaktuell.Teilnehmer = tl_hy_teilnehmer.ID";
    $sql .= " LEFT JOIN tl_hy_wetten  ON tl_hy_wetteaktuell.Wette = tl_hy_wetten.ID";
	$sql .= " LEFT JOIN tl_hy_mannschaft AS mannschaft1 ON tl_hy_wetteaktuell.W1 = mannschaft1.ID";
	$sql .= " LEFT JOIN tl_hy_mannschaft AS mannschaft2 ON tl_hy_wetteaktuell.W2 = mannschaft2.ID";
	$sql .= " LEFT JOIN tl_hy_mannschaft AS mannschaft3 ON tl_hy_wetteaktuell.W3 = mannschaft3.ID";
	$sql .= " LEFT JOIN tl_hy_mannschaft AS mannschaftP ON tl_hy_wetten.Tipp1 = mannschaftP.ID";
    $sql .= " WHERE tl_hy_teilnehmer.Wettbewerb  ='$Wettbewerb' ORDER BY tl_hy_teilnehmer.Kurzname ASC, tl_hy_wetten.Art, tl_hy_wetten.Tipp1 ASC";
    $sql .= ";";
    

    if ($debug) echo "SQL<br>$sql<br>";
    $teilnehmer=array();
    $teilnehmeraktWetten=array();
    $stmt = $conn->executeQuery ($sql);
    $num_rows = $stmt->rowCount();    
    while ($row = $stmt->fetchAssociative()) {   // teilnehmer merken sortiert nach Wettart incl. zugehörigen Wettaktuall und wette
      $teilnehmer[strtolower((string)$row['Art'])][]=$row;
      $teilnehmeraktWetten[strtolower((string)$row['Name'])][]=$row;
	}

    // Wetten einlesen 
    $sql  = "SELECT";
    $sql .= " tl_hy_wetten.ID as 'ID'";       
    $sql .= " ,tl_hy_wetten.Art as 'Art'";  
    $sql .= " ,tl_hy_wetten.Tipp1 as 'Tipp1'";  
    $sql .= " ,tl_hy_wetten.Tipp2 as 'Tipp2'";  
    $sql .= " ,tl_hy_wetten.Tipp3 as 'Tipp3'";  
    $sql .= " ,tl_hy_wetten.Tipp4 as 'Tipp4'";  
    $sql .= " ,tl_hy_wetten.Pok as 'Pok'";   // 
    $sql .= " ,tl_hy_wetten.Ptrend as 'Ptrend'";   
    $sql .= " ,tl_hy_wetten.Kommentar as 'Kommentar'";   
	$sql .= " ,mannschaftP.Nation as 'MPNation'";
	$sql .= " ,mannschaftP.Flagge as 'MPFlagge'";
	$sql .= " ,mannschaftP.ID as 'MPInd'";
    $sql .= " FROM tl_hy_wetten";
	$sql .= " LEFT JOIN tl_hy_mannschaft AS mannschaftP ON tl_hy_wetten.Tipp1 = mannschaftP.ID";
    $sql .= " WHERE tl_hy_wetten.Wettbewerb  ='$Wettbewerb' ORDER BY tl_hy_wetten.Art";
    $sql .= ";";
    

    if ($debug) echo "SQL<br>$sql<br>";

    $wetten=array();
    $stmt = $conn->executeQuery ($sql);
    $num_rows = $stmt->rowCount();    
    while ($row = $stmt->fetchAssociative()) {   // wette nach index sortieren
      $wetten[$row['ID']]=$row;
	}

    /*
     * ORDER BY tl_hy_teilnehmer.Kurzname ASC, tl_hy_wetten.Art, tl_hy_wetten.Tipp1 ASC";
     * Array teilnehmer [art]
     * Werte eines eintrags
     *     tl_hy_teilnehmer.ID as 'ID'";
     *     tl_hy_teilnehmer.Kurzname as 'Kurzname'";
     *     tl_hy_teilnehmer.Name as 'Name'";
     *     tl_hy_wetteaktuell.W1 as 'W1'";    // Wettwert 1 des Teilnehmers
     *     tl_hy_wetteaktuell.W2 as 'W2'";    // Wettwert 2 des Teilnehmers
     *     tl_hy_wetteaktuell.W3 as 'W3'";    // Wettwert 2 des Teilnehmers
     *     tl_hy_wetteaktuell.Wette as 'Windex'";  // index der aktuellen zeigt auf tl_hy_wetten
     *     tl_hy_wetten.Art as 'Art'";       // S
     *     tl_hy_wetten.Tipp1 as 'Tipp1'";   // Wettart s = Speilenummer
     *                                       // Wettart g = Gruppe als Text
     *                                       // Wettart p = Index Manschaft
     *                                       // Wettart v = Vergleichswert zu W1
     *     tl_hy_wetten.ID as 'WettenId'";   // index der Wette
     *     tl_hy_wetten.Pok as 'Pok'";       // Punkte OK
     *     tl_hy_wetten.Ptrend as 'Ptrend'"; // Punkte Trend  
     *     tl_hy_wetten.Kommentar as 'Kommentar'"; // Kommentar aus Wetten z.b
     *                                             // Deutschland wird
     *                                             // Deutschlandspiel
     *                                             // Gruppe A, Gruppe B,..
     *                                             // Achtel
     *                                             // Viertel
     *                                             // Halb
     *                                             // Finale
     *                                             // Meister (EM oder WM)
     *     mannschaft1.Nation as 'M1'";            // mannschaft1 mannschaft ausgewählt aus M1 (bei Spielewette);
     *     mannschaft1.ID as 'M1Ind'";
     *     mannschaft2.Nation as 'M2'";            // mannschaft2 mannschaft ausgewählt aus M2 (bei Spielewette);
     *     mannschaft2.ID as 'M2Ind'";
     *     mannschaft3.Nation as 'M3'";            // mannschaft3 mannschaft ausgewählt aus M3 (bei Spielewette);
     *     mannschaft3.ID as 'M3Ind'";
     *     mannschaftP.Nation as 'MPNation'";      // mannschaftP mannschaft ausgewählt aus tl_hy_wetten.Tipp1 as 'Tipp1' (P-Wette);
     *     mannschaftP.ID as 'MPInd'";
     */

    
     $deutschlandgruppe=$this->aktWettbewerb['aktDGruppe'];
      
     loescheTlnPunkte($this->connection,$Wettbewerb);
     //echo "<br>Alle Teilnehmerpunkte gel&ouml;scht<br>";
     //echo "<br>erzeuge Deutschlandgruppe<br>";
     if (count($teilnehmer) !=  0) {
     $html.=createSpielegruppe($this->cgiUtil,$this->fussballUtil,$conn,$Wettbewerb,$deutschlandgruppe,$teilnehmer['s'],'Deutschland Gruppenspiele','Deutschland');
     $html.=createGruppen($this->cgiUtil,$this->fussballUtil,$conn,$Wettbewerb,$teilnehmer['g']);
     $html.=createSpielegruppe($this->cgiUtil,$this->fussballUtil,$conn,$Wettbewerb,'achtel%',$teilnehmer['s'],'Achtelfinale');
     $html.=createSpielegruppe($this->cgiUtil,$this->fussballUtil,$conn,$Wettbewerb,'viertel%',$teilnehmer['s'],'Viertelfinale');
     $html.=createSpielegruppe($this->cgiUtil,$this->fussballUtil,$conn,$Wettbewerb,'halb%',$teilnehmer['s'],'Halbfinale');
     $html.=createSpielegruppe($this->cgiUtil,$this->fussballUtil,$conn,$Wettbewerb,'finale',$teilnehmer['s'],'Finale');
     $html.=createPuV($this->cgiUtil,$this->fussballUtil,$conn,$Wettbewerb,$wetten,$teilnehmeraktWetten,["p","v"],'Platz und Vergleich');
     } else {
        $html.= "<p>es existierern noch keine Teilnehmer</p>";             
     }

     $response = new Response($html,Response::HTTP_OK,['content-type' => 'text/html']);
     return $response;
 

    }
}
