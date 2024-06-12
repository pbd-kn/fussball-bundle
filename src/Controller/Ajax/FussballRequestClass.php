<?php
// FussballRequestClass.php
// fussball ajax request class liefert gerenderte Teile als json zurueck
// 'data' => $html,  gerendete Info
// 'debug'=>$debug   evtl. debuginfo
declare(strict_types=1);

namespace PBDKN\FussballBundle\Controller\Ajax;

use Contao\ContentModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Contao\FilesModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DoctrineDBALDriverException;
use Doctrine\DBAL\Exception as DoctrineDBALException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use PBDKN\FussballBundle\Util\CgiUtil;
use PBDKN\FussballBundle\Util\FussballUtil;
use PBDKN\FussballBundle\Controller\ContentElement\DependencyAggregate;
//include_once(__DIR__.'../../../Resources/contao/phpincludes/verwaltung/cgi.php');


class FussballRequestClass extends AbstractController
{
    private ContaoFramework $framework;
    private Connection $connection; 
    private InsertTagParser $insertTagParser; 
    private TranslatorInterface $translator;
    private CgiUtil $cgiUtil; 
    private FussballUtil $fussballUtil; 
    protected $aktWettbewerb=array('aktWettbewerb'=>'','aktAnzgruppen'=>-1,'aktDGruppe'=>'','aktStartdatum'=>'','aktEndedatum'=>'');

        
    public function __construct(
      ContaoFramework $framework,
      Connection $connection,
      InsertTagParser $insertTagParser,
      TranslatorInterface $translator,
      CgiUtil $cgiUtil,
      FussballUtil $fussballUtil)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->cgiUtil=$cgiUtil;
        $this->fussballUtil=$fussballUtil;
        $this->insertTagParser=$insertTagParser; 
        $this->translator=$translator;
                // akt Wettbewerb lesen.
        $stmt = $this->connection->executeQuery("SELECT * from tl_hy_config WHERE Name='Wettbewerb' AND aktuell = 1 LIMIT 1");
        $row = $stmt->fetchAssociative();
        $this->aktWettbewerb['id']=$row['id'];
        $this->aktWettbewerb['aktuell']=$row['aktuell'];
        $this->aktWettbewerb['aktWettbewerb']=$row['value1'];
        $this->aktWettbewerb['aktAnzgruppen']=$row['value2'];
        $this->aktWettbewerb['aktDGruppe']=$row['value3'];
        $this->aktWettbewerb['aktStartdatum']=$row['value4'];
        $this->aktWettbewerb['aktEndedatum']=$row['value5'];
        
    }
    /**
     * @throws \Exception
     *
     * @Route("/fussball/anzeigewettbewerb/{aktion}/{ID}", 
     * name="FussballRequestClass::class\anzeigewettbewerb", 
     * defaults={"_scope" = "frontend"})
     */

/* erzeugt das Formular und Buttons zur Eingabe eines Wettbewerbs
 * Parameter aktion: auszuführende Aktion wird als Hidden ins Formular übernommen
 *           ID: Wettbewerb Id (-1 kein Wettbewerb ausgewaehlt)
 *
 * Result html
 * hidden("ID", $ID);                    // zur weitergabe bei übernehmen
 * hidden("aktion", $aktion);                    // zur weitergabe bei übernehmen
 * Button Name: uebernehmen Value Übernehmen onClick uebernehmen();
 * Button Name: Abbrechen Value Abbrechen onClick abbrechen();
 * Inputfelder im result Formular
 * Name: Wettbewerb bei ID=-1 Eingabefeld zur Angabe der Neuanlage sonst Name des Wettbewerbs (read only)
 * Name: anzahlGruppen value
 * Name: deutschlandGruppe
 * Name: startDatum value
 * Name: endeDatum value
 * jscript: uebernehmen ajaxrequest um den Wettbewerb zu uebernehmen
 *        : abbrechen 
 */
  public function anzeigewettbewerb(string $aktion, int $ID=-1)
  {
      $c=$this->cgiUtil;
      $Name="";   // Wettbewerb Name
      $Gruppen="";   // Wettbewerb Anzahl Gruppen
      $DGruppe="";   // Wettbewerb Deutschland Gruppe
      $StartDatum="";   // Wettbewerb startDatum
      $EndeDatum="";   // Wettbewerb endeDatum
      $html="";      // gerenderte
      $debug="";     //  debuginfo
        $my_script_txt = <<< EOT
        <script language="javascript" type="text/javascript">
        function uebernehmen() {     /* neuer aktueller Wettbewerb */
          var _par = jQuery("#inputForm :input").serialize();   // ich habe den Eindruck nur so bekomme ich die Werte
console.log('par: '+_par);
          var _inputArr = _par.split("&");
          let myA=[];
          for (var x = 0; x < _inputArr.length; x++) {
            var _kv = _inputArr[x].split("=");
            myA[_kv[0]] = _kv[1];
            //console.log(_kv);
          }
          var url =  '/fussball/bearbeitewettbewerb/'+myA['aktion']+'/'+myA['ID']+'/'+myA['Wettbewerb']+'/'+myA['anzahlGruppen']+'/'+myA['deutschlandGruppe']+'/'+myA['startDatum']+'/'+myA['endeDatum'];
console.log('url: '+url);
          jQuery.get(url, function(data, status){
             errortxt=data['error'];
             if (errortxt != '') {
               jQuery("#result").html(errortxt);
             } else {
               location.reload();
             }
          });

        }
        function abbrechen() {
          location.reload();
        }
        </script>
EOT;
      $html.=$my_script_txt;              
      $id=$ID;
      if ($id !=-1) {
        // Wettbewerb einlesen
        $sql="SELECT * from tl_hy_config where ID='$id' LIMIT 1;";
        $debug .= "sql: $sql  ";	
        $stmt = $this->connection->executeQuery(
            'SELECT * FROM tl_hy_config WHERE ID = ? ',
            [$id],
        );
        /*
        while (($row = $stmt->fetchAssociative()) !== false) {
          echo $row['headline'];
        } 
        */  
        $num_rows = $stmt->rowCount();    
        $debug .= "Anzahl: $num_rows  ";	
        $row = $stmt->fetchAssociative();  // s. Doctrine\DBAL
        $Name=$row['value1'];
        $Gruppen=$row['value2'];
        $DGruppe=$row['value3'];
        $StartDatum=$row['value4'];
        $EndeDatum=$row['value5'];
        $html.="Wettbewerb &auml;ndern<br>\n";
      } else {
        $html.="Wettbewerb neu eintragen<br>\n";
      }
      $debug.='id: '.$id.' Name: '.$Name.' Gruppen: '.$Gruppen.' DGruppe: '.$DGruppe.' StartDatum: '.$StartDatum.' EndeDatum: '.$EndeDatum;

// create output
      $html.= $c->Button(array("onClick"=>"uebernehmen();"),"&Uuml;bernehmen","uebernehmen") . "\n";
      $html.= $c->Button(array("onClick"=>"abbrechen();"),"Abbrechen","Abbrechen") . "<br>\n";
      $html.= $c->start_form("", null,null,array("id"=>"inputForm"));
      $html.= $c->hidden("id", $id);                    // zur weitergabe bei übernehmen
      $html.= $c->hidden("aktion", $aktion);                    // zur weitergabe bei übernehmen
  
      $html.= $c->table (array("border"=>1));
      $html.= $c->tr();
      $html.= $c->td(array("valign"=>"top"),"Wettbewerb");
      if ($id==-1) {  // neueEingabe
        $html.= $c->td(array("valign"=>"top"),$c->textfield(array("name"=>"Wettbewerb","value"=>""))) . "\n";
      } else {
        $html.= $c->td(array("valign"=>"top"),$c->textfield(array("name"=>"Wettbewerb","readonly"=>true,"value"=>"$Name"))) . "\n";
      }
      $html.= $c->end_tr() . $c->tr() . "\n";
      $html.= $c->td(array("valign"=>"top"),"Anzahl Gruppen");
      $html.= $c->td(array("valign"=>"top"),$c->textfield(array("name"=>"anzahlGruppen","value"=>"$Gruppen"))) . "\n";
      $html.= $c->end_tr() . $c->tr() . "\n";
      $html.= $c->td(array("valign"=>"top"),"Deutschlandgruppe");
      $html.= $c->td(array("valign"=>"top"),$c->textfield(array("name"=>"deutschlandGruppe","value"=>"$DGruppe"))) . "\n";
      $html.= $c->end_tr() . "\n";
      $html.= $c->td(array("valign"=>"top"),"Beginn JJJJ-MM-TT");
      $html.= $c->td(array("valign"=>"top"),$c->textfield(array("name"=>"startDatum","value"=>"$StartDatum"))) . "\n";
      $html.= $c->end_tr() . "\n";
      $html.= $c->td(array("valign"=>"top"),"Ende JJJJ-MM-TT");
      $html.= $c->td(array("valign"=>"top"),$c->textfield(array("name"=>"endeDatum","value"=>"$EndeDatum"))) . "\n";
      $html.= $c->end_tr() . "\n";
      $html.= $c->end_table() . "\n";
      $html.= $c->end_form();
  
      $html = utf8_encode($html);
	  return new JsonResponse(['dir'=>__DIR__,'data' => $html,'debug'=>$debug]); 
  }
  

/* erzeugt das Formular und Buttons zur Eingabe einer Mannschaft
 * Parameter aktion: auszuführende Aktion wird als Hidden ins Formular übernommen
 *           ID: Mannschaft Id (-1 keine Mannschaft ausgewaehlt)
 *
 * Result html
 * hidden("ID", $ID);                    // zur weitergabe bei übernehmen
 * hidden("aktion", $aktion);                    // zur weitergabe bei übernehmen
 * Button Name: uebernehmen Value Übernehmen onClick uebernehmen();
 * Button Name: Abbrechen Value Abbrechen onClick abbrechen();
 * Inputfelder im result Formular
 * Name: Mannschaft bei ID=-1 Eingabefeld zur Angabe der Neuanlage sonst Name des Mannschaft (read only)
 * Name: name Mannschaftsname
 * Name: nation Nation, aus der dann die Flagge erzeugt wird
 * jscript: uebernehmen ajaxrequest um den Wettbewerb zu uebernehmen
 *        : abbrechen 
 */
    /**
     * @throws \Exception
     *
     * @Route("/fussball/anzeigemannschaft/{aktion}/{ID}", 
     * name="FussballRequestClass::class\anzeigemannschaft", 
     * defaults={"_scope" = "frontend"})
     */

  public function anzeigemannschaft(string $aktion, int $ID=-1)
  {

    function createNationOption ($conn,$cgi,$name,$selected,$type) {
      if (empty($type)) {
	    $sql = "select * From tl_hy_nation ORDER BY Nation ASC";
      }else {
	    $sql = "select * From tl_hy_nation where Type like '$type' ORDER BY Nation ASC";
      }
//echo "sql $sql<br>";
      $stmt = $conn->executeQuery($sql);
      $optarray= array();
      while (($row = $stmt->fetchAssociative()) !== false) {
        $optarray[$row['Nation']] = $row['Nation'];
      }     
      // ersetze 16-bit Values
      $res=$cgi->select($name, $optarray,$selected);
      $search  = array("\xC3\xA4", "\xC3\xB6", "\xC3\xBC", "\xC3\x84", "\xC3\x96","\xC3\x9f");
      $replace = array('ä', 'ö', 'ü', 'Ä', 'Ö','Ü','ß');
      $res= str_replace($search, $replace, $res);          
      return $res;
    }
    function createGruppenOption ($cgi,$name,$GruppenArray,$selected) {
      $res=$cgi->select($name,$GruppenArray,$selected);
      $search  = array("\xC3\xA4", "\xC3\xB6", "\xC3\xBC", "\xC3\x84", "\xC3\x96","\xC3\x9f");
      $replace = array('ä', 'ö', 'ü', 'Ä', 'Ö','Ü','ß');
      $res= str_replace($search, $replace, $res);          
      return $res;
    }
    
  
      $c=$this->cgiUtil;
      $fkt=$this->fussballUtil;
      $id=$ID;
      $Name="";
      $Nation="";
      $Flagge="";
      $Gruppe="";
      $html="";      // gerenderte
      $debug="";     //  debuginfo
      $my_script_txt = <<< EOT
        <script language="javascript" type="text/javascript">
        function uebernehmen() {     /* neuer aktueller Wettbewerb */
          var _par = jQuery("#inputForm :input").serialize();   // ich habe den Eindruck nur so bekomme ich die Werte
console.log('par: '+_par);
          var _inputArr = _par.split("&");
          let myA=[];
          for (var x = 0; x < _inputArr.length; x++) {
            var _kv = _inputArr[x].split("=");
            myA[_kv[0]] = _kv[1];
            //console.log(_kv);
          }
          var url =  '/fussball/bearbeitemannschaft/'+myA['aktion']+'/'+myA['ID']+'/'+myA['name']+'/'+myA['nation']+'/'+myA['Gruppe'];
console.log('url: '+url);
          jQuery.get(url, function(data, status){
console.log('res da ');
             errortxt=data['error'];
             if (errortxt != '') {
console.log('error: '+errortxt);
               jQuery("#result").html(errortxt);
             } else {
               location.reload();
               //jQuery("#result").html("");
               //jQuery("#eingabe").html(data['data']);
             }
          });

        }
        function abbrechen() {
          location.reload();
        }
        </script>
EOT;
      $html.=$my_script_txt;              

      $id=$ID;
      if ($id !=-1) {
        // Mannschaft einlesen
        $sql="SELECT * from tl_hy_mannschaft where ID='$id' LIMIT 1;";
        $debug .= "sql: $sql  ";	
        $stmt = $this->connection->executeQuery($sql);
        /*
        while (($row = $stmt->fetchAssociative()) !== false) {
          echo $row['headline'];
        } 
        */   
        $num_rows = $stmt->rowCount();    
        $debug .= "Anzahl: $num_rows  ";	
        $row = $stmt->fetchAssociative();  // s. Doctrine\DBAL
        $Name=$row['Name'];
        $Nation=$row['Nation'];
        $Flagge=$row['Flagge'];
        $Gruppe=$row['Gruppe'];
        $html.="Mannschaft &auml;ndern<br>\n";
      } else {
        $html.="Mannschaft neu<br>\n";
      }
      $debug.='id: '.$id.' Name: '.$Name.' Nation: '.$Nation.' Flagge: '.$Flagge.' Gruppe: '.$Gruppe;

// create output
      $html.=$c->Button(array("onClick"=>"uebernehmen();"),"&Uuml;bernehmen","uebernehmen") . "\n";
      $html.=$c->Button(array("onClick"=>"abbrechen();"),"Abbrechen","Abbrechen") . "<br>\n";
      $html.=$c->start_form("", null,null,array("id"=>"inputForm"));
      $html.=$c->hidden("ID", $id);                    // zur weitergabe bei übernehmen
      $html.=$c->hidden("aktion", $aktion);                    // zur weitergabe bei übernehmen
  
      $html.=$c->table (array("border"=>1));
      $html.=$c->tr();
        $html.=$c->td(array("valign"=>"top"),"Mannschaft");
        $html.=$c->td(array("valign"=>"top"),"Name").$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"name","id"=>"name","value"=>"$Name")))."\n";
  //echo td(array("valign"=>"top"),"Nation") . td(array("valign"=>"top"),textfield(array("name"=>"nation","id"=>"nation","value"=>"$Nation"))) . "\n";
        $Type="";         // WM oder EM zeigt nur die entsprechenden Nationen an
        if (strpos(strtolower ($this->aktWettbewerb['aktWettbewerb']),'em')!== false) $Type='%:EU:%';
        if (strpos(strtolower ($this->aktWettbewerb['aktWettbewerb']),'wm')!== false) $Type='%:WE:%';
        // %:EU:%  Europa 
        // %:WE:%  Welt 
        // %:AS:%  Asien 
        // %:AF:%  Afrika 
        // %:SA:%  Südamerika 
        // %:MA:%  Mittelamerika 
        // %:NA:%  Nordamerika 
        $html.=$c->td(array("valign"=>"top"),"Nation").$c->td(array("valign"=>"top"),createNationOption ($this->connection,$c,"nation",$Nation,$Type))."\n";
        //$html.=$c->td(array("valign"=>"top"),"Gruppe").$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"Gruppe","id"=>"name","value"=>"$Gruppe")))."\n";
        $html.=$c->td(array("valign"=>"top"),"Gruppe");
        $grpArray=$fkt->createGruppenArray($this->aktWettbewerb['aktAnzgruppen']);
        $html.=$c->td(array("valign"=>"top"),createGruppenOption ($c,"Gruppe",$grpArray,$Gruppe))."\n";
  
        //$html.=$c->td(array("valign"=>"top"),"Flagge") . "\n";
        //$html.=$c->td(array("valign"=>"top")) . "\n";
      $html.= $c->end_tr()."\n";
      $html.= $c->end_table() . "\n";
      $html.= $c->end_form();
  
      $html = utf8_encode($html);
	  return new JsonResponse(['data' => $html,'debug'=>$debug]); 
  }
  

/* erzeugt das Formular und Buttons zur Eingabe eines Spielorts
 * Parameter aktion: auszuführende Aktion wird als Hidden ins Formular übernommen
 *           ID: Mannschaft Id (-1 keine Mannschaft ausgewaehlt)
 *
 * Result html
 * hidden("ID", $ID);                    // zur weitergabe bei übernehmen
 * hidden("aktion", $aktion);            // zur weitergabe bei übernehmen
 * Button Name: uebernehmen Value Übernehmen onClick uebernehmen();
 * Button Name: Abbrechen Value Abbrechen onClick abbrechen();
 * Inputfelder im result Formular
 * Name: ID bei ID=-1 Eingabefeld zur Angabe der Neuanlage sonst ID des Orts (read only)
 * Name: ort Spielort
 * Name: beschreibung 
 * jscript: uebernehmen ajaxrequest um den Wettbewerb zu uebernehmen
 *        : abbrechen 
 */
    /**
     * @throws \Exception
     *
     * @Route("/fussball/anzeigeort/{aktion}/{ID}", 
     * name="FussballRequestClass::class\anzeigeort", 
     * defaults={"_scope" = "frontend"})
     */

  public function anzeigeort(string $aktion, int $ID=-1)
  {
  
      $c=$this->cgiUtil;
      $id=$ID;
      $ort="";
      $beschreibung="";

      $html="";      // gerenderte
      $debug="";     //  debuginfo
      $my_script_txt = <<< EOT
        <script language="javascript" type="text/javascript">
        function uebernehmen() {     /* neuer aktueller Wettbewerb */
          var _par = jQuery("#inputForm :input").serialize();   // ich habe den Eindruck nur so bekomme ich die Werte
console.log('par: '+_par);
          var _inputArr = _par.split("&");
          let myA=[];
          for (var x = 0; x < _inputArr.length; x++) {
            var _kv = _inputArr[x].split("=");
            myA[_kv[0]] = _kv[1];
            //console.log(_kv);
          }
          var url =  '/fussball/bearbeiteort/'+myA['aktion']+'/'+myA['ID']+'/'+myA['ort']+'/'+myA['beschreibung'];
console.log('url: '+url);
          jQuery.get(url, function(data, status){
console.log('res da ');
             errortxt=data['error'];
             if (errortxt != '') {
console.log('error: '+errortxt);
               jQuery("#result").html(errortxt);
             } else {
               location.reload();
               //jQuery("#result").html("");
               //jQuery("#eingabe").html(data['data']);
             }
          });

        }
        function abbrechen() {
          location.reload();
        }
        </script>
EOT;
      $html.=$my_script_txt;              

      $id=$ID;
      if ($id !=-1) {
        // Ort einlesen
        $sql="SELECT ID,Ort,Beschreibung from tl_hy_orte where ID='$id' LIMIT 1;";
        $debug .= "sql: $sql  ";	
        $stmt = $this->connection->executeQuery($sql);
        /*
        while (($row = $stmt->fetchAssociative()) !== false) {
          echo $row['headline'];
        } 
        */   
        $num_rows = $stmt->rowCount();    
        $debug .= "Anzahl: $num_rows  ";	
        $row = $stmt->fetchAssociative();  // s. Doctrine\DBAL
        $ort=$row['Ort'];
        $beschreibung=$row['Beschreibung'];
        $html.="Ort &auml;ndern<br>\n";
      } else {
        $html.="Ort neu<br>\n";
      }
      $debug.='id: '.$id.' Ort: '.$ort.' Beschreibung: '.$beschreibung;

// create output
      $html.=$c->Button(array("onClick"=>"uebernehmen();"),"&Uuml;bernehmen","uebernehmen") . "\n";
      $html.=$c->Button(array("onClick"=>"abbrechen();"),"Abbrechen","Abbrechen") . "<br>\n";
      $html.=$c->start_form("", null,null,array("id"=>"inputForm"));
      $html.=$c->hidden("ID", $id);                    // zur weitergabe bei übernehmen
      $html.=$c->hidden("aktion", $aktion);                    // zur weitergabe bei übernehmen
  
      $html.=$c->table (array("border"=>1));
      $html.=$c->tr();
        $html.=$c->td(array("valign"=>"top"),"Ort");
        $html.=$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"ort","id"=>"ort","value"=>"$ort")))."\n";
        $html.=$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"beschreibung","id"=>"beschreibung","value"=>"$beschreibung")))."\n";
      $html.= $c->end_tr()."\n";
      $html.= $c->end_table() . "\n";
      $html.= $c->end_form();
  
      $html = utf8_encode($html);
	  return new JsonResponse(['data' => $html,'debug'=>$debug]); 
  }

/* erzeugt das Formular und Buttons zur Eingabe/Veraenderung einer Gruppe
 * Neueingabe geht nur über alle löschen und neu initialisieren
 * Parameter aktion: auszuführende Aktion wird als Hidden ins Formular übernommen
 *           ID: Mannschaft Id (-1 keine Mannschaft ausgewaehlt)
 *
 * Result html
 * hidden("ID", $ID);                    // zur weitergabe bei übernehmen
 * hidden("aktion", $aktion);            // zur weitergabe bei übernehmen
 * Button Name: uebernehmen Value Übernehmen onClick uebernehmen();
 * Button Name: Abbrechen Value Abbrechen onClick abbrechen();
 * Inputfelder im result Formular
 * name aktion
 * Name ID der Gruppe (read only)
 * name Platz
 * jscript: uebernehmen ajaxrequest um den Wettbewerb zu uebernehmen
 *        : abbrechen 
 */

    /**
     * @throws \Exception
     *
     * @Route("/fussball/anzeigegruppe/{aktion}/{ID}", 
     * name="FussballRequestClass::class\anzeigegruppe", 
     * defaults={"_scope" = "frontend"})
     */

  public function anzeigegruppe(string $aktion, int $ID=-1)
  {
  
      $c=$this->cgiUtil;
      $id=$ID;
      $ort="";
      $beschreibung="";

      $html="";      // gerenderte
      $debug="";     //  debuginfo
      $my_script_txt = <<< EOT
        <script language="javascript" type="text/javascript">
        function uebernehmen() {     /* neuer aktueller Gruppe */
          var _par = jQuery("#inputForm :input").serialize();   // ich habe den Eindruck nur so bekomme ich die Werte
console.log('par: '+_par);
          var _inputArr = _par.split("&");
          let myA=[];
          for (var x = 0; x < _inputArr.length; x++) {
            var _kv = _inputArr[x].split("=");
            myA[_kv[0]] = _kv[1];
            console.log(_kv);
          }
    //      1342/1/-1//-1/-1/-1/-1/-1
          var url =  '/fussball/bearbeitegruppe/'+myA['aktion']+'/'+myA['ID']+'/'+myA['Platz'];
console.log('url: '+url);
          jQuery.get(url, function(data, status){
console.log('res da ');
             errortxt=data['error'];
             if (errortxt != '') {
console.log('error: '+errortxt);
               jQuery("#result").html(errortxt);
             } else {
               location.reload();
               //jQuery("#result").html("");
               //jQuery("#eingabe").html(data['data']);
             }
          });

        }
        function abbrechen() {
          location.reload();
        }
        </script>
EOT;
      $html.=$my_script_txt;              

      $id=$ID;
      if ($id !=-1) {
        // Gruppe einlesen
        $sql="SELECT * from tl_hy_gruppen where ID='$id' LIMIT 1;";
        $debug .= "sql: $sql  ";	
        $stmt = $this->connection->executeQuery($sql);
        /*
        while (($row = $stmt->fetchAssociative()) !== false) {
          echo $row['headline'];
        } 
        */   
        $num_rows = $stmt->rowCount();    
        $debug .= "Anzahl: $num_rows  ";	
        $row = $stmt->fetchAssociative();  // s. Doctrine\DBAL
        $Gruppe=$row['Gruppe'];
        $M1=$row['M1'];                    // index
        $Platz=$row['Platz'];
        $Spiele=$row['Spiele'];
        $Sieg=$row['Unentschieden'];
        $Niederlage=$row['Niederlage'];
        $Unentschieden=$row['Unentschieden'];
        $Tore=$row['Tore'];
        $Gegentore=$row['Gegentore'];
        $Differenz=$row['Differenz'];
        $Punkte=$row['Punkte'];
        $html.="Gruppe &auml;ndern<br>\n";
      } else {
        $html.="Gruppe neu, Bitte alle Gruppen löschen und neu aufsetzen<br>\n";
        $html = utf8_encode($html);
	    return new JsonResponse(['data' => $html,'debug'=>$debug]); 
      }
      $debug.='id: '.$id.' Ort: '.$ort.' Beschreibung: '.$beschreibung;

// create output
      $html.=$c->Button(array("onClick"=>"uebernehmen();"),"&Uuml;bernehmen","uebernehmen") . "\n";
      $html.=$c->Button(array("onClick"=>"abbrechen();"),"Abbrechen","Abbrechen") . "<br>\n";
      $html.=$c->start_form("", null,null,array("id"=>"inputForm"));
      $html.=$c->hidden("ID", $id);                    // zur weitergabe bei übernehmen
      $html.=$c->hidden("aktion", $aktion);                    // zur weitergabe bei übernehmen
        $html.= $c->td(array("valign"=>"top"),$c->textfield(array("name"=>"M1","readonly"=>true,"value"=>"$M1"))) . "\n";
  
      $html.=$c->table (array("border"=>1));
        $html.=$c->tr();
          $html.=$c->td(array("valign"=>"top"),"Platz") .$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"Platz","value"=>"$Platz")))."\n";
        $html.=$c->end_tr().$c->tr() . "\n";
          $html.=$c->td(array("valign"=>"top"),"Gruppe").$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"Gruppe","readonly"=>true,"value"=>"$Gruppe")))."\n";
          $html.=$c->td(array("valign"=>"top"),"M1").$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"M1Ind","readonly"=>true,"value"=>"$M1"))) . "\n";
          $html.=$c->td(array("valign"=>"top"),"Spiele") .$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"Spiele","readonly"=>true,"value"=>"$Spiele")))."\n";
          $html.=$c->end_tr() . $c->tr()."\n";
          $html.=$c->td(array("valign"=>"top"),"Sieg") .$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"Sieg","readonly"=>true,"value"=>"$Sieg")))."\n";
          $html.=$c->td(array("valign"=>"top"),"Unentschieden") .$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"Unentschieden","readonly"=>true,"value"=>"$Unentschieden")))."\n";
          $html.=$c->td(array("valign"=>"top"),"Niederlage") .$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"Niederlage","readonly"=>true,"value"=>"$Niederlage")))."\n";
        $html.=$c->end_tr().$c->tr()."\n";
          $html.=$c->td(array("valign"=>"top"),"Tore").$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"Tore","readonly"=>true,"value"=>"$Tore")))."\n";
          $html.=$c->td(array("valign"=>"top"),"Gegentore").$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"Gegentore","readonly"=>true,"value"=>"$Gegentore")))."\n";
          $html.=$c->td(array("valign"=>"top"),"Differenz").$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"Differenz","readonly"=>true,"value"=>"$Differenz")))."\n";
          $html.=$c->td(array("valign"=>"top"),"Punkte").$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"Punkte","readonly"=>true,"value"=>"$Punkte")))."\n";
          $html.=$c->end_tr()."\n";
      $html.=$c->end_table()."\n";
      $html.= $c->end_form();
  
      $html = utf8_encode($html);
	  return new JsonResponse(['data' => $html,'debug'=>$debug]); 
  }
  
/* erzeugt das Formular und Buttons zur Eingabe eines Spiels
 * Parameter aktion: auszuführende Aktion wird als Hidden ins Formular übernommen
 *           ID: Mannschaft Id (-1 keine Mannschaft ausgewaehlt)
 *
 * Result html
 * hidden("ID", $ID);                    // zur weitergabe bei übernehmen
 * hidden("aktion", $aktion);            // zur weitergabe bei übernehmen
 * Button Name: uebernehmen Value Übernehmen onClick uebernehmen();
 * Button Name: Abbrechen Value Abbrechen onClick abbrechen();
 * Inputfelder im result Formular
 * Name: ID bei ID=-1 Eingabefeld zur Angabe der Neuanlage sonst ID des Spiels (read only)
 * Name: Nr Spielnr
 * Name: M1 
 * Name: M2 
 * Name: Ort 
 * Name: Datum 
 * Name: Uhrzeit 
 * Name: T1   Tore Mannschaft 1
 * Name: T2   Tore Mannschaft 2
 * jscript: uebernehmen ajaxrequest um den Wettbewerb zu uebernehmen
 *        : abbrechen 
 */
    /**
     * @throws \Exception
     *
     * @Route("/fussball/anzeigespiel/{aktion}/{ID}", 
     * name="FussballRequestClass::class\anzeigespiel", 
     * defaults={"_scope" = "frontend"})
     */

  public function anzeigespiel(string $aktion, int $ID=-1)
  {  
    function createMannschaftOption ($conn,$cgi,$Wettbewerb,$name,$selected) {
      // selected ist der Index der Mannschaft
	  $sql = "select ID,Name From tl_hy_mannschaft where Wettbewerb='$Wettbewerb' ORDER BY Name ASC";
      $stmt = $conn->executeQuery($sql);
      $optarray= array();
      while (($row = $stmt->fetchAssociative()) !== false) {
        $optarray[$row['Name']] = $row['ID'];
      }
      $res=$cgi->select($name, $optarray,$selected);
      $search  = array("\xC3\xA4", "\xC3\xB6", "\xC3\xBC", "\xC3\x84", "\xC3\x96","\xC3\x9f");
      $replace = array("ä", 'ö', 'ü', 'Ä', 'Ö','Ü','ß');
      $res= str_replace($search, $replace, $res);          
      return $res;
    }
    function createOrtOption ($conn,$cgi,$Wettbewerb,$name,$selcted) {
      // selected ist der Index des Ortes
	  $sql = "select ID,Ort From tl_hy_orte where Wettbewerb='$Wettbewerb' ORDER By Ort ASC";
//echo " SQL $sql <br>";	 
      $stmt = $conn->executeQuery($sql);
      $optarray= array();
      while (($row = $stmt->fetchAssociative()) !== false) {
//echo "Ort gefunden index " . $row['ID'] . " Ort " . $row['Ort'] . "<br>";
        $optarray[$row['Ort']] = $row['ID'];
      }
      $res= $cgi->select($name, $optarray,$selcted);
      $res = iconv('UTF-8', 'ISO-8859-1', $res);
//      $search  = array("\xC3\xA4", "\xC3\xB6", "\xC3\xBC", "\xC3\x84", "\xC3\x96","\xC3\x9f");
//      $replace = array("ä", 'ö', 'ü', 'Ä', 'Ö','Ü','ß');
//      $res= str_replace($search, $replace, $res);          
      return $res;
      
    }
    function createGruppenOption ($cgi,$name,$GruppenArray,$selected) {
      $res=$cgi->select($name,$GruppenArray,$selected);
      $search  = array("\xC3\xA4", "\xC3\xB6", "\xC3\xBC", "\xC3\x84", "\xC3\x96","\xC3\x9f");
      $replace = array('ä', 'ö', 'ü', 'Ä', 'Ö','Ü','ß');
      $res= str_replace($search, $replace, $res);          
      return $res;
    }
      
      $c=$this->cgiUtil;
      $fkt=$this->fussballUtil;
      $id=$ID;
      $Nr=1;
      $Gruppe="H";
      $M1=-1;
      $M2=-1;
      $Ort=-1;
      $Datum="2022-11-01";
      $Uhrzeit="16:00";
      $T1=-1;
      $T2=-1;
      $Wettbewerb = $this->aktWettbewerb['aktWettbewerb'];
      $conn=$this->connection;


      $html="";      // gerenderte
      $debug="";     //  debuginfo
      $my_script_txt = <<< EOT
        <script language="javascript" type="text/javascript">
        function uebernehmen() {     /* neuer aktueller Wettbewerb */
          var _par = jQuery("#inputForm :input").serialize();   // ich habe den Eindruck nur so bekomme ich die Werte
console.log('par: '+_par);
          var _inputArr = _par.split("&");
          let myA=[];
          for (var x = 0; x < _inputArr.length; x++) {
            var _kv = _inputArr[x].split("=");
            myA[_kv[0]] = _kv[1];
            //console.log(_kv);
          }
          var url='/fussball/bearbeitespiel/'+myA['aktion']+'/'+myA['ID']+'/'+myA['Nr']+'/'+myA['Gruppe']+'/'+myA['M1']+'/'+myA['M2']+'/'+myA['Ort']+'/'+myA['Datum']+'/'+myA['Uhrzeit']+'/'+myA['T1']+'/'+myA['T2'];
console.log('url: '+url);
          jQuery.get(url, function(data, status){
console.log('res da ');
             errortxt=data['error'];
             if (errortxt != '') {
console.log('error: '+errortxt);
               jQuery("#result").html(errortxt);
             } else {
               location.reload();
               //jQuery("#result").html("");
               //jQuery("#eingabe").html(data['data']);
             }
          });

        }
        function abbrechen() {
          location.reload();
        }
        </script>
EOT;
      $html.=$my_script_txt;              

      $id=$ID;
      if ($id !=-1) {
        // Spiel einlesen
        $sql="SELECT Nr,Gruppe,M1,M2,Ort,Datum,Uhrzeit,T1,T2 from tl_hy_spiele where ID='$id' LIMIT 1;";
        $debug .= "sql: $sql  ";	
        $stmt = $this->connection->executeQuery($sql);
        /*
        while (($row = $stmt->fetchAssociative()) !== false) {
          echo $row['headline'];
        } 
        */   
        $num_rows = $stmt->rowCount();    
        $debug .= "Anzahl: $num_rows  ";	
        $row = $stmt->fetchAssociative();  // s. Doctrine\DBAL
        $Nr=$row['Nr'];
        $Gruppe=$row['Gruppe'];
        $M1=$row['M1'];
        $M2=$row['M2'];
        $Ort=$row['Ort'];
        $Datum=$row['Datum'];
        $Uhrzeit=$row['Uhrzeit'];
        $T1=$row['T1'];
        $T2=$row['T2'];
        $html.="Spiel &auml;ndern<br>\n";
      } else {
        // Spielnummer neu setzen
        $html.="Spiel neu<br>\n";
        $sql="SELECT Nr from tl_hy_spiele where Wettbewerb='$Wettbewerb' ORDER BY NR DESC LIMIT 1;";
        $stmt = $this->connection->executeQuery($sql);
        $num_rows = $stmt->rowCount();    
        $debug .= "Anzahl: $num_rows  ";
        if ($num_rows > 0) {	
          $row = $stmt->fetchAssociative();  // s. Doctrine\DBAL
          $Nr=$row['Nr']+1;
        }

      }
      $debug.='id: '.$id.' Nr: '.$Nr.' Gruppe: '.$Gruppe.' M1: '.$M1.' $M2: '.$M2.' Ort: '.$Ort.' Datum: '.$Datum.' Uhrzeit: '.$Uhrzeit.' T1: '.$T1.' $T2: '.$T2;

// create output
      $html.=$c->Button(array("onClick"=>"uebernehmen();"),"&Uuml;bernehmen","uebernehmen") . "\n";
      $html.=$c->Button(array("onClick"=>"abbrechen();"),"Abbrechen","Abbrechen") . "<br>\n";
      $html.=$c->start_form("", null,null,array("id"=>"inputForm"));
      $html.=$c->hidden("ID", $id);                    // zur weitergabe bei übernehmen
      $html.=$c->hidden("aktion", $aktion);                    // zur weitergabe bei übernehmen

      $html.=$c->table (array("border"=>1));
      $html.=$c->tr();
        $html.=$c->td(array("valign"=>"top"),"Nr");
        $html.=$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"Nr","id"=>"Nr","value"=>"$Nr"))) . "\n";
        $html.=$c->td(array("valign"=>"top"),"Gruppe");        
        $grpArray=$fkt->createGruppenArray($this->aktWettbewerb['aktAnzgruppen']);
        $html.=$c->td(array("valign"=>"top"),createGruppenOption ($c,"Gruppe",$grpArray,$Gruppe))."\n";
        $html.=$c->end_tr().$c->tr() . "\n";
        $html.=$c->td(array("valign"=>"top"),"M1").$c->td(array("valign"=>"top"),createMannschaftOption ($conn,$c,$Wettbewerb,"M1",$M1))."\n";
        $html.=$c->td(array("valign"=>"top"),"M2").$c->td(array("valign"=>"top"),createMannschaftOption ($conn,$c,$Wettbewerb,"M2",$M2))."\n";
        $html.=$c->end_tr().$c->tr()."\n";
        $html.=$c->td(array("valign"=>"top"),"Ort").$c->td(array("valign"=>"top"),createOrtOption ($conn,$c,$Wettbewerb,"Ort",$Ort)) . "\n";
        $html.=$c->td(array("valign"=>"top"),"Datum").$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"Datum","id"=>"Datum","value"=>"$Datum"))) . "\n";
        $html.=$c->td(array("valgn"=>"top"),"Uhrzeit").$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"Uhrzeit","id"=>"Uhrzeit","value"=>"$Uhrzeit"))) . "\n";"\n";
        $html.=$c->end_tr().$c->tr()."\n";
        $html.=$c->td(array("valign"=>"top"),"T1").$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"T1","id"=>"T1","value"=>"$T1"))) . "\n";
        $html.=$c->td(array("valign"=>"top"),"T2").$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"T2","id"=>"T2","value"=>"$T2"))) . "\n";
      $html.= $c->end_tr()."\n";
      $html.= $c->end_table() . "\n";
      $html.= $c->end_form();
  
      $html = utf8_encode($html);
	  return new JsonResponse(['data' => $html,'debug'=>$debug]); 
  }

    /**
     * @throws \Exception
     *
     * @Route("/fussball/anzeigewette/{aktion}/{ID}/{Type}", 
     * name="FussballRequestClass::class\anzeigewette", 
     * defaults={"_scope" = "frontend"})
     */

  public function anzeigewette(string $aktion, int $ID=-1,string $Type='')
  { 
    function createSpieleOption ($conn,$cgi,$Wettbewerb,$name,$selected) {
      // selected ist der Index der des Spiels
      $sql =  "SELECT ";
      $sql .= " spiele.ID AS ID,"; 
	  $sql .= " spiele.Nr AS NR,";
	  $sql .= " spiele.Gruppe as Gruppe, ";
	  $sql .= " spiele.M1 as 'M1Ind',";
      $sql .= " mannschaft1.Nation as 'M1',";
      $sql .= " mannschaft1.Name as 'M1Name',";
      $sql .= " mannschaft1.Flagge as 'Flagge1',";
      $sql .= " spiele.M2 as 'M2Ind',";
      $sql .= " mannschaft2.Nation as 'M2',";
      $sql .= " mannschaft2.Name as 'M2Name',";
      $sql .= " mannschaft2.Flagge as 'Flagge2'";
      $sql .= " FROM tl_hy_spiele as spiele";
      $sql .= " LEFT JOIN tl_hy_mannschaft AS mannschaft1 ON spiele.M1 = mannschaft1.ID";
      $sql .= " LEFT JOIN tl_hy_mannschaft AS mannschaft2 ON spiele.M2 = mannschaft2.ID";
      $sql .= " WHERE spiele.Wettbewerb  ='$Wettbewerb'";
      $sql .= " ORDER BY spiele.Nr ASC , spiele.Datum ASC, spiele.Uhrzeit ASC ;";
//echo "create Spiele option sql: $sql<br>";	
      $stmt = $conn->executeQuery($sql);
      $optarray= array();
      while (($row = $stmt->fetchAssociative()) !== false) {
	    $str = $row['NR']  . ":" .  $row['M1Name'] . "/" . $row['M2Name'];
        $optarray[$str] = $row['ID'];
      }
      $res=$cgi->select($name, $optarray,$selected);
      $search  = array("\xC3\xA4", "\xC3\xB6", "\xC3\xBC", "\xC3\x84", "\xC3\x96","\xC3\x9f");
      $replace = array("ä", 'ö', 'ü', 'Ä', 'Ö','Ü','ß');
      $res= str_replace($search, $replace, $res);          
      return $res;
    }

    function createMannschaftOption ($conn,$cgi,$Wettbewerb,$name,$selected,$gruppe) {
  	  $sql =  "SELECT ";
	  $sql .= " gruppen.ID AS ID,";
	  $sql .= " gruppen.Gruppe AS Gruppe,";
	  $sql .= " gruppen.M1 As M1ind,";
	  $sql .= " gruppen.Platz as Platz,";
      $sql .= " mannschaft1.Nation as 'M1',";
      $sql .= " mannschaft1.Name as 'M1Name',";
      $sql .= " mannschaft1.Flagge as 'Flagge1'";
	  $sql .= " FROM tl_hy_gruppen as gruppen";
      $sql .= " LEFT JOIN tl_hy_mannschaft AS mannschaft1 ON gruppen.M1 = mannschaft1.ID";
      $sql .= " WHERE gruppen.Wettbewerb  ='$Wettbewerb'";
      if ($gruppe != -1) {
	    $sql .= " AND gruppen.Gruppe = '$gruppe'";
	  }	
//echo "Mannschaft Option sql: $sql<br>";	
      $stmt = $conn->executeQuery($sql);
      $optarray= array();
	  $optarray['Keine Angabe'] = -1;
      while (($row = $stmt->fetchAssociative()) !== false) {
	    $str = $row['Gruppe'] . ":&nbsp;" . $row['M1'];
        $optarray[$str] = $row['M1ind'];
      }
      $res=$cgi->select($name, $optarray,$selected);
      $search  = array("\xC3\xA4", "\xC3\xB6", "\xC3\xBC", "\xC3\x84", "\xC3\x96","\xC3\x9f");
      $replace = array("ä", 'ö', 'ü', 'Ä', 'Ö','Ü','ß');
      $res= str_replace($search, $replace, $res);          
      return $res;
    }

    function createGruppenOption ($cgi,$name,$GruppenArray,$selected) {
      $res=$cgi->select($name,$GruppenArray,$selected);
      $search  = array("\xC3\xA4", "\xC3\xB6", "\xC3\xBC", "\xC3\x84", "\xC3\x96","\xC3\x9f");
      $replace = array('ä', 'ö', 'ü', 'Ä', 'Ö','Ü','ß');
      $res= str_replace($search, $replace, $res);          
      return $res;
    }
    function createWettheader($Row,$cgi) {
  	  $ID=$Row['ID'];
	  $Kommentar=$Row['Kommentar'];
      $Pok=$Row['Pok'];
      $Ptrend=$Row['Ptrend'];
	  $Art=  $Row['Art'];
	  if ($ID == -1) { $str =  "<center><h2>Wette eintragen</h2></center><br>\n";
	  } else { $str =  "<center><h2>Wette bearbeiten</h2></center><br>\n";
	  }
      $str .= $cgi->Button(array("onClick"=>"uebernehmen();"),"&Uuml;bernehmen","Übernehmen") . "\n";
      $str .= $cgi->Button(array("onClick"=>"abbrechen();"),"Abbrechen","Abbrechen") . "<br>\n";
      $str .= $cgi->start_form("", null,null,array("id"=>"inputForm"));
      $str .= $cgi->hidden("ID", $ID);                    // zur weitergabe bei übernehmen
      $str .= $cgi->table (array("border"=>1));
      $str .= $cgi->tr();
      $str .= $cgi->td(array("valign"=>"top"),"Art");
      $str .= $cgi->td(array("valign"=>"top"),$cgi->textfield(array("name"=>"Art","id"=>"Art","value"=>"$Art","size"=>"4"))) . "\n";
      $str .= $cgi->td(array("valign"=>"top"),"Kommentar");
      $str .= $cgi->td(array("valign"=>"top"),$cgi->textfield(array("name"=>"Kommentar","id"=>"Kommentar","value"=>"$Kommentar"))) . "\n";
      $str .= $cgi->td(array("valign"=>"top"),"Pok");
      $str .= $cgi->td(array("valign"=>"top"),$cgi->textfield(array("name"=>"Pok","id"=>"Pok","value"=>"$Pok","size"=>"4"))) . "\n";
      $str .= $cgi->td(array("valign"=>"top"),"Ptrend");
      $str .= $cgi->td(array("valign"=>"top"),$cgi->textfield(array("name"=>"Ptrend","id"=>"Ptrend","value"=>"$Ptrend","size"=>"4"))) . "\n";
	  $str .= $cgi->end_tr().$cgi->tr();
      return $str;
    }

    function createWettbottom($row,$cgi) {
      $str =$cgi->end_tr()."\n";
      $str .=$cgi->end_table()."\n";
      $str .=$cgi->end_form();
	  return $str;
    }

    function createSpielwette($conn,$cgi,$Wettbewerb,$row,$debug) {
      $str=createWettheader($row,$cgi);
      $S1 = $row['Tipp1'];  // Spiel
	  $T1 = $row['Tipp2'];  // T1
	  $T2 = $row['Tipp3'];  // T2
      $debug.=" S1 $S1 T1 $T1 T2 $T2";
	  $str.=$cgi->td(array("valign"=>"top"),"Spiel");
	  $str.=$cgi->td(array("valign"=>"top"),createSpieleOption ($conn,$cgi,$Wettbewerb,"Tipp1",$S1)) . "\n";
      $str.=$cgi->td(array("valign"=>"top"),"T1");
      $str.=$cgi->td(array("valign"=>"top"),$cgi->textfield(array("name"=>"Tipp2","id"=>"Tipp2","value"=>"$T1","size"=>"4"))) . "\n";
      $str.=$cgi->td(array("valign"=>"top"),"T2");
      $str.=$cgi->td(array("valign"=>"top"),$cgi->textfield(array("name"=>"Tipp3","id"=>"Tipp3","value"=>"$T2","size"=>"4"))) . "\n";
	  $str.=createWettbottom($row,$cgi);
	  return $str;  
    }
    function createGruppenwette($conn,$cgi,$fkt,$GruppenArray,$Wettbewerb,$row,$debug) {
      $str=createWettheader($row,$cgi);
	  $G1=$row['Tipp1'];             // Tipp1 ist hier der Gruppenname
	  $M1=$row['Tipp2'];             // Gruppenerster
	  $M2=$row['Tipp3'];             // Gruppenzweiter
	  $M3=$row['Tipp4'];             // Gruppendritter
      $debug.="Create Gruppenwette G1 $G1 M1 $M1 M2 $M2 M3 $M3";
	  $str .= $cgi->td(array("valign"=>"top"),"G1");
      $str.=$cgi->td(array("valign"=>"top"),createGruppenOption ($cgi,"Tipp1",$GruppenArray,$G1))."\n";
      $str.=$cgi->td(array("valign"=>"top"),"Erster");
	  $str.=$cgi->td(array("valign"=>"top"),createMannschaftOption ($conn,$cgi,$Wettbewerb,"Tipp2",$M1,$G1)) . "\n";
      $str.=$cgi->td(array("valign"=>"top"),"Zweiter");
	  $str.=$cgi->td(array("valign"=>"top"),createMannschaftOption ($conn,$cgi,$Wettbewerb,"Tipp3",$M2,$G1)) . "\n";
      $str.=$cgi->td(array("valign"=>"top"),"Dritter");
	  $str.=$cgi->td(array("valign"=>"top"),createMannschaftOption ($conn,$cgi,$Wettbewerb,"Tipp4",$M3,$G1)) . "\n";
	  $str.=createWettbottom($row,$cgi);
	  return $str;
    }

    function createVergleichswette($conn,$cgi,$Row) {
      $str=createWettheader($Row,$cgi);
	  $V1=$Row['Tipp1'];
	  $str.=$cgi->td(array("valign"=>"top"),"Wert");
      $str.=$cgi->td(array("valign"=>"top"),$cgi->textfield(array("name"=>"Tipp1","id"=>"Tipp1","value"=>"$V1","size"=>"4"))) . "\n";
	  $str.=$cgi->hidden("Tipp2", -1);  // damit speichern klappt
	  $str.=$cgi->hidden("Tipp3", -1);  // damit speichern klappt
	  $str.=$cgi->hidden("Tipp4", -1);  // damit speichern klappt
      //$str .= td(array("valign"=>"top"),"Erster");
	  //$str .= td(array("valign"=>"top"),createMannschaftOption ($conn,"T1",$M1,$G1)) . "\n";
      //$str .= td(array("valign"=>"top"),"Zweiter");
	  //$str .= td(array("valign"=>"top"),createMannschaftOption ($conn,"T2",$M2,$G1)) . "\n";
	  $str.=createWettbottom($Row,$cgi);
	  return $str;
    }

    function createPlatzwette($conn,$cgi,$Wettbewerb,$Row) {
      $str=createWettheader($Row,$cgi);
	  $M1=$Row['Tipp1'];
	  $str.=$cgi->td(array("valign"=>"top"),"Mannschaft");
	  $str.=$cgi->td(array("valign"=>"top"),createMannschaftOption ($conn,$cgi,$Wettbewerb,"Tipp1",$M1,-1)) . "\n";
	  $str.=$cgi->hidden("Tipp2", -1);  // damit speichern klappt
	  $str.=$cgi->hidden("Tipp3", -1);  // damit speichern klappt
	  $str.=$cgi->hidden("Tipp4", -1);  // damit speichern klappt
      //$str .= td(array("valign"=>"top"),"Erster");
	  //$str .= td(array("valign"=>"top"),createMannschaftOption ($conn,"T1",$M1,$G1)) . "\n";
      //$str .= td(array("valign"=>"top"),"Zweiter");
	  //$str .= td(array("valign"=>"top"),createMannschaftOption ($conn,"T2",$M2,$G1)) . "\n";
	  $str.=createWettbottom($Row,$cgi);
	  return $str;
    }
      
      $c=$this->cgiUtil;
      $fkt=$this->fussballUtil;
      $id=$ID;
      $Nr=1;
      $Gruppe="H";
      $M1=-1;
      $M2=-1;
      $Ort=-1;
      $Datum="2022-11-01";
      $Uhrzeit="16:00";
      $T1=-1;
      $T2=-1;
      $Wettbewerb = $this->aktWettbewerb['aktWettbewerb'];
      $conn=$this->connection;


      $html="";      // gerenderte
      $debug="";     //  debuginfo
      $my_script_txt = <<< EOT
        <script language="javascript" type="text/javascript">
        function uebernehmen() {     /* neuer aktuelle Wette */
          var _par = jQuery("#inputForm :input").serialize();   // ich habe den Eindruck nur so bekomme ich die Werte
console.log('par: '+_par);
          var _inputArr = _par.split("&");
          let myA=[];
          for (var x = 0; x < _inputArr.length; x++) {
            var _kv = _inputArr[x].split("=");
            myA[_kv[0]] = _kv[1];
            //console.log(_kv);
          }
          var url='/fussball/bearbeitewette/'+myA['aktion']+'/'+myA['ID']+'/'+myA['Kommentar']+'/'+myA['Art']+'/'+myA['Pok']+'/'+myA['Ptrend']+'/'+myA['Tipp1']+'/'+myA['Tipp2']+'/'+myA['Tipp3']+'/'+myA['Tipp4'];
console.log('url: '+url);
          jQuery.get(url, function(data, status){
             errortxt=data['error'];
console.log('res da errortxt '+errortxt);
             if (errortxt != '') {
console.log('error: '+errortxt);
               jQuery("#result").html(errortxt);
             } else {
               jQuery("#result").html("");
               jQuery("#eingabe").html(data['data']);
console.log('data: '+data['data']);
               location.reload();
             }
          });

        }
        function abbrechen() {
          location.reload();
        }
        </script>
EOT;
      $html.=$my_script_txt;              

      $id=$ID;
      $aktion=strtolower($aktion);
      $Type=strtolower(trim($Type));
      $debug.='id: '.$id.' aktion: '.$aktion.' Type: '.$Type;
// create output
      $html.=$c->start_form("", null,null,array("id"=>"inputForm"));
      $html.=$c->hidden("ID", $id);                    // zur weitergabe bei übernehmen
      $html.=$c->hidden("aktion", $aktion);                    // zur weitergabe bei übernehmen
      if ($aktion == 'n') {
        // neue Wette
        $debug.=' Create Neue Wette TYPE |'.$Type.'|';
	    $Row = array();
	    $Row['ID']=-1;
	    $Row['Kommentar'] = "";
        $Row['Pok']=-1;
        $Row['Ptrend']=-1;
	    if ($Type == 's') {  // Spielwette
    	  $Row['Art']= 'S';
	      $Row['Tipp1']=-1;          // Spiel
          $Row['Tipp2']=-1;          // T1 
          $Row['Tipp3']=-1;          // T2
          $Row['Tipp4']=-1;          // irrelevant
          $debug.=' Create Spielwette';
	      $html.=createSpielwette($conn,$c,$Wettbewerb,$Row,$debug);
	    } else if ($Type == 'g') {  // Gruppenwette
	      $Row['Art']='G';
	      $Row['Tipp1']=-1;          // Gruppe
          $Row['Tipp2']=-1;          // M1 
          $Row['Tipp3']=-1;          // M2
          $Row['Tipp4']=-1;          // M3
          $debug.=' Create Spielwette';
          $grpArray=$fkt->createGruppenArray($this->aktWettbewerb['aktAnzgruppen']);
          $debug.=' Create Spielwette len grpArray '.count($grpArray);
	      $html.=createGruppenwette($conn,$c,$fkt,$grpArray,$Wettbewerb,$Row,$debug);
        } else if ($Type == 'p') {  // Platz Wette
	      $Row['Art']='P';
	      $Row['Tipp1']=-1;          // Mannschaftsindex
          $Row['Tipp2']=-1;          // irrelevant
          $Row['Tipp3']=-1;          // irrelevant
          $Row['Tipp4']=-1;          // irrelevant
          $debug.=' Create Platzwette';
	      $html.=createPlatzwette($conn,$c,$Wettbewerb,$Row);
	    } else if ($Type == 'v') {  // Vergleich Wette
	      $Row['Art']='V';
	      $Row['Tipp1']=-1;          // Vergleichswert
          $Row['Tipp2']=-1;          // irrelevant
          $Row['Tipp3']=-1;          // irrelevant
          $Row['Tipp4']=-1;          // irrelevant
	      $html.=createVergleichswette($conn,$c,$Row);
	    }
      } else if ($aktion == 'b') { // Wette bearbeiten	
  	    $id = $ID;
	    // Besorge Wettdaten
        $sql=" select ID,Kommentar,Pok,Ptrend,Art,Tipp1,Tipp2,Tipp3,Tipp4 from  tl_hy_wetten Where ID = '$id';";
        $debug.="Wettdaten sql: $sql";
        $stmt=$conn->executeQuery($sql);
	    $Row=$stmt->fetchAssociative();
	    $type = strtolower($Row['Art']);
        $debug.= " type $type";
	    if ($type == 's') {  // Spielwette  
          $html.=createSpielwette($conn,$c,$Wettbewerb,$Row,$debug); //createSpielwette($conn,$row,$cgi)		
	    } else if ($type == 'g') {  // Gruppenwette
          $grpArray=$fkt->createGruppenArray($this->aktWettbewerb['aktAnzgruppen']);
          $html.=createGruppenwette($conn,$c,$fkt,$grpArray,$Wettbewerb,$Row,$debug);		
	    } else if ($type == 'p') {  // Platz Wette
          $debug.="Create Platzwette Anzeigen";
          $html.=createPlatzwette($conn,$c,$Wettbewerb,$Row);		
	    } else if ($type == 'v') {  // Vergleich Wette
	      $html.=createVergleichswette($conn,$c,$Row);
	    }
    }
    $html.= $c->end_table() . "\n";
    $html.= $c->end_form();
    $html = utf8_encode($html);
	return new JsonResponse(['data' => $html,'debug'=>$debug]); 
  }
    /**
     * @throws \Exception
     *
     * @Route("/fussball/anzeigeteilnehmer/{aktion}/{ID}", 
     * name="FussballRequestClass::class\anzeigeteilnehmer", 
     * defaults={"_scope" = "frontend"})
     */

  public function anzeigeteilnehmer(string $aktion, int $ID=-1)
  {
    function displayTeilnehmer($cgi,$aktion,$row) {
  	  $ID=$row['ID'];
	  $Name=$row['Name'];
      $Email=$row['Email'];
      $Kurzname=$row['Kurzname'];
      $Bezahlt=$row['Bezahlt'];
      //$Bezahlt=0;
      $Erst=$row['Erst'];
      $Achtel=$row['Achtel'];
      $Viertel=$row['Viertel'];
      $Halb=$row['Halb'];
      $Finale=$row['Finale'];
      $str="";
	  if ($ID == -1) {
        $str.="<center><h3>Neuer Teilnehmer eintragen</h3></center><br>\n";
	  } else {
        $str.="<center><h3>Teilnehmer bearbeiten</h3></center><br>\n";
	  }
      $str.=$cgi->Button(array("onClick"=>"uebernehmen();"),"&Uuml;bernehmen","Übernehmen") . "\n";
      $str.=$cgi->Button(array("onClick"=>"abbrechen();"),"Abbrechen","Abbrechen") . "<br>\n";
      $str.=$cgi->start_form("", null,null,array("id"=>"inputForm"));
      $str.=$cgi->hidden("ID", $ID);                    // zur weitergabe bei übernehmen
      $str.=$cgi->hidden("aktion", $aktion);            // zur weitergabe bei übernehmen
      $str.=$cgi->hidden("Art", $row['Art']);           // zuwas das dient weiss ich noch nicht
      $str.=$cgi->table (array("border"=>1));
      $str.=$cgi->tr();
      $str.=$cgi->td(array("valign"=>"top"),"ID");
      $str.=$cgi->td(array("valign"=>"top"),$cgi->textfield(array("name"=>"ID","id"=>"ID","value"=>"$ID","size"=>"4"))) . "\n";
      $str.=$cgi->td(array("valign"=>"top"),"Kurzname");
      $str.=$cgi->td(array("valign"=>"top"),$cgi->textfield(array("name"=>"Kurzname","id"=>"Kurzname","value"=>"$Kurzname"))) . "\n";
      $str.=$cgi->td(array("valign"=>"top"),"Name");
      $str.=$cgi->td(array("valign"=>"top"),$cgi->textfield(array("name"=>"Name","id"=>"Name","value"=>"$Name"))) . "\n";
      $str.=$cgi->td(array("valign"=>"top"),"Email");
      $str.=$cgi->td(array("valign"=>"top"),$cgi->textfield(array("name"=>"Email","id"=>"Email","value"=>"$Email"))) . "\n";
      $str.=$cgi->end_tr() . $cgi->tr()."\n";
      $str.=$cgi->td(array("valign"=>"top"),"Bezahlt");
      $str.=$cgi->td(array("valign"=>"top"),$cgi->textfield(array("name"=>"Bezahlt","id"=>"Bezahlt","value"=>"$Bezahlt"))) . "\n";
      $str.=$cgi->td(array("valign"=>"top"),"1. W");
      $str.=$cgi->td(array("valign"=>"top"),$cgi->textfield(array("name"=>"Erst","id"=>"Erst","value"=>"$Erst"))) . "\n";
      $str.=$cgi->td(array("valign"=>"top"),"Achtel");
      $str.=$cgi->td(array("valign"=>"top"),$cgi->textfield(array("name"=>"Achtel","id"=>"Achtel","value"=>"$Achtel"))) . "\n";
      $str.=$cgi->td(array("valign"=>"top"),"Viertel");
      $str.=$cgi->td(array("valign"=>"top"),$cgi->textfield(array("name"=>"Viertel","id"=>"Viertel","value"=>"$Viertel"))) . "\n";
      $str.=$cgi->end_tr() . $cgi->tr()."\n";
      $str.=$cgi->td(array("valign"=>"top"),"Halb");
      $str.=$cgi->td(array("valign"=>"top"),$cgi->textfield(array("name"=>"Halb","id"=>"Halb","value"=>"$Halb"))) . "\n";
      $str.=$cgi->td(array("valign"=>"top"),"Finale");
      $str.=$cgi->td(array("valign"=>"top"),$cgi->textfield(array("name"=>"Finale","id"=>"Finale","value"=>"$Finale"))) . "\n";
      $str.=$cgi->end_tr() . "\n";
      $str.=$cgi->end_table() . "\n";
      $str.=$cgi->end_form();
      return $str;
    }
      $c=$this->cgiUtil;
      $id=$ID;

      $html="";      // gerenderte
      $debug="";     //  debuginfo
      $my_script_txt = <<< EOT
        <script language="javascript" type="text/javascript">
        function uebernehmen() {     /* neuer Teilnehmer */
          var _par = jQuery("#inputForm :input").serialize();   // ich habe den Eindruck nur so bekomme ich die Werte
console.log('Teilnehmer uebernehmen: '+_par);
          var _inputArr = _par.split("&");
          let myA=[];
          for (var x = 0; x < _inputArr.length; x++) {
            var _kv = _inputArr[x].split("=");
            myA[_kv[0]] = _kv[1];
            console.log(_kv);
          }
          var url =  '/fussball/bearbeiteteilnehmer/'+myA['aktion']+'/'+myA['ID']+'/'+myA['Art']+'/'+myA['Kurzname']+'/'+myA['Name']+'/'+myA['Email']+'/'+myA['Bezahlt']+'/'+myA['Erst']+'/'+myA['Achtel']+'/'+myA['Viertel']+'/'+myA['Halb']+'/'+myA['Finale'];
console.log('url: '+url);
          jQuery.get(url, function(data, status){
console.log('res da ');
             errortxt=data['error'];
             if (errortxt != '') {
console.log('error: '+errortxt);
               jQuery("#result").html(errortxt);
             } else {
               //location.reload();
               jQuery("#result").html("");
               jQuery("#eingabe").html(data['data']);
             }
          });

        }
        function abbrechen() {
          location.reload();
        }
        </script>
EOT;
      $html.=$my_script_txt;              

      if (strtolower($aktion) == 'n') {
      // neue Teilnehmer
	    $Row = array();
	    $Row['ID'] = -1;
	    $Row['Name'] = -1;
	    $Row['Kurzname'] = -1;
	    $Row['Email'] = -1;
	    $Row['Art'] = "T";                // Teilnehmerdaten
	    $Row['Bezahlt'] = false;   
	    $Row['Erst'] = false;   
	    $Row['Achtel'] = false;   
	    $Row['Viertel'] = false;   
	    $Row['Halb'] = false;   
	    $Row['Finale'] = false;   
	    $html.= displayTeilnehmer($c,$aktion,$Row);
      } 
      if ($aktion == 'u') { // bearbeiten	
	    // Besorge Teilnehmerdafalsten
        $sql = " select * from tl_hy_teilnehmer Where ID = '$id';";
        $debug .= "sql: $sql  ";	
        $stmt = $this->connection->executeQuery($sql);
        $Row = $stmt->fetchAssociative();
	    $Row['Art'] = "T";                // Teilnehmerdaten
        $html.=displayTeilnehmer($c,$aktion,$Row);
      }
      $html = utf8_encode($html);
	  return new JsonResponse(['data' => $html,'debug'=>$debug]); 
  }

    /**
     * @throws \Exception
     *
     * @Route("/fussball/anzeigenation/{aktion}/{ID}", 
     * name="FussballRequestClass::class\anzeigenation", 
     * defaults={"_scope" = "frontend"})
     */

  public function anzeigenation(string $aktion, int $ID=-1)
  {
    function displayNation($cgi,$aktion,$row) {
  	  $ID=$row['ID'];
	  $Nation=$row['Nation'];
      $Type=$row['Type'];
      $Alfa2=$row['Alfa2'];
      $Alfa3=$row['Alfa3'];
      $Domain=$row['Domain'];
      $Image=$row['Image'];
      $str="";
	  if ($ID == -1) {
        $str.="<center><h3>Neue Nation eintragen</h3></center><br>\n";
	  } else {
        $str.="<center><h3>Nation bearbeiten</h3></center><br>\n";
	  }
      $str.=$cgi->Button(array("onClick"=>"uebernehmen();"),"&Uuml;bernehmen","Übernehmen") . "\n";
      $str.=$cgi->Button(array("onClick"=>"abbrechen();"),"Abbrechen","Abbrechen") . "<br>\n";
      $str.=$cgi->start_form("", null,null,array("id"=>"inputForm"));
      $str.=$cgi->hidden("ID", $ID);                    // zur weitergabe bei übernehmen
      $str.=$cgi->hidden("aktion", $aktion);            // zur weitergabe bei übernehmen
//      $str.=$cgi->hidden("Art", $row['Art']);           // zuwas das dient weiss ich noch nicht
      $str.=$cgi->table (array("border"=>1));
      $str.=$cgi->tr();
      $str.=$cgi->td(array("valign"=>"top"),"ID");
      $str.=$cgi->td(array("valign"=>"top"),$cgi->textfield(array("disabled"=>"true","name"=>"ID","id"=>"ID","value"=>"$ID","size"=>"4"))) . "\n";
      $str.=$cgi->end_tr() . $cgi->tr()."\n";
      $str.=$cgi->td(array("valign"=>"top"),"Nation");
      $str.=$cgi->td(array("valign"=>"top"),$cgi->textfield(array("placeholder"=>"Nation Name eindeutig","name"=>"Nation","id"=>"Nation","value"=>"$Nation"))) . "\n";
      $str.=$cgi->td(array("valign"=>"top"),"Type");
      $titleType ="Kennzeichnung der Nation mehrfach Angaben möglich\n";
      $titleType.="%:EU:%  Europa wird bei Europameisterschaften angezeigt\n"; 
      $titleType.="%:WE:%  Welt  wird bei Weltmeisterschaften angezeigt\n";
      $titleType.="%:AS:%  Asien\n"; 
      $titleType.="%:AF:%  Afrika\n"; 
      $titleType.="%:SA:%  Südamerika\n"; 
      $titleType.="%:MA:%  Mittelamerika\n"; 
      $titleType.="%:NA:%  Nordamerika\n"; 
      $str.=$cgi->td(array("valign"=>"top"),$cgi->textfield(array("title"=>$titleType,"name"=>"Type","id"=>"Type","value"=>"$Type"))) . "\n";
      $str.=$cgi->end_tr() . $cgi->tr()."\n";
      $str.=$cgi->td(array("valign"=>"top"),"Alfa2");
      $str.=$cgi->td(array("valign"=>"top"),$cgi->textfield(array("name"=>"Alfa2","id"=>"Alfa2","value"=>"$Alfa2"))) . "\n";
      $str.=$cgi->td(array("valign"=>"top"),"Alfa3");
      $str.=$cgi->td(array("valign"=>"top"),$cgi->textfield(array("name"=>"Alfa3","id"=>"Alfa3","value"=>"$Alfa3"))) . "\n";
      $str.=$cgi->end_tr() . $cgi->tr(). "\n";
      $str.=$cgi->td(array("valign"=>"top"),"Domain");
      $str.=$cgi->td(array("valign"=>"top"),$cgi->textfield(array("name"=>"Domain","id"=>"Domain","value"=>"$Domain"))) . "\n";
      $str.=$cgi->td(array("valign"=>"top"),"Image");
      $str.=$cgi->td(array("valign"=>"top"),$cgi->textfield(array("placeholder"=>"Imagename aus /assets/flaggen evtl. hochladen","name"=>"Image","id"=>"Image","value"=>"$Image"))) . "\n";
      $str.=$cgi->end_tr() . "\n";
      $str.=$cgi->end_table() . "\n";
      $str.=$cgi->end_form();
      return $str;
    }
      $c=$this->cgiUtil;
      $id=$ID;

      $html="";      // gerenderte
      $debug="";     //  debuginfo
      $my_script_txt = <<< EOT
        <script language="javascript" type="text/javascript">
        function uebernehmen() {     /* neuer Nation */
          var _par = jQuery("#inputForm :input").serialize();   // ich habe den Eindruck nur so bekomme ich die Werte
console.log('Nation uebernehmen: '+_par);
          var _inputArr = _par.split("&");
          let myA=[];
          for (var x = 0; x < _inputArr.length; x++) {
            var _kv = _inputArr[x].split("=");
            myA[_kv[0]] = _kv[1];
            console.log(_kv);
          }
          var url =  '/fussball/bearbeitenation/'+myA['aktion']+'/'+myA['ID']+'/'+myA['Nation']+'/'+myA['Type']+'/'+myA['Alfa2']+'/'+myA['Alfa3']+'/'+myA['Domain']+'/'+myA['Image'];
console.log('url: '+url);
          jQuery.get(url, function(data, status){
console.log('res da ');
             errortxt=data['error'];
             if (errortxt != '') {
console.log('error: '+errortxt);
               jQuery("#result").html(errortxt);
             } else {
               //location.reload();
               jQuery("#result").html("");
               jQuery("#eingabe").html(data['data']);
             }
          });

        }
        function abbrechen() {
          location.reload();
        }
        </script>
EOT;
      $html.=$my_script_txt;              

      if (strtolower($aktion) == 'n') {
      // neue Nation
	    $Row = array();
	    $Row['ID'] = -1;
	    $Row['Nation'] = "";
	    $Row['Type'] = ':WE:';
	    $Row['Alfa2'] = -1;
	    $Row['Alfa3'] = -1;                
	    $Row['Domain'] = -1;                
	    $Row['Image'] = "";                
	    $html.= displayNation($c,$aktion,$Row);
      } 
      if ($aktion == 'u') { // bearbeiten	
	    // Besorge Nationdaten
        $sql="SELECT *  FROM tl_hy_nation Where ID = $ID;"; 

        $debug .= "sql: $sql  ";	
        $stmt = $this->connection->executeQuery($sql);
        $Row = $stmt->fetchAssociative();
        $html.=displayNation($c,$aktion,$Row);
      }
      $html = utf8_encode($html);
	  return new JsonResponse(['data' => $html,'debug'=>$debug]); 
  }
  
    /**
     * @throws \Exception
     * @throws DoctrineDBALException
     *
     * @Route("/fussball/bearbeitewettbewerb/{aktion}/{ID}/{Wettbewerb}/{anzahlGruppen}/{deutschlandGruppe}/{startDatum}/{endeDatum}", 
     * name="FussballRequestClass::class\bearbeitewettbewerb", 
     * defaults={"_scope" = "frontend"})
     */

  public function bearbeitewettbewerb(
     string $aktion,
     int $ID=-1,
     string $Wettbewerb='',
     int $anzahlGruppen=-1,
     string $deutschlandGruppe='',
     string $startDatum='',
     string $endeDatum='')
  {
    function checkDatum(string $Datum) {
      //if (isempty($Datum) || $Datum == "") return false;   // Datum Pflicht
      $arrdate=explode("-",$Datum);
      if (count($arrdate) != 3) {
        return false;
      } else {
        $mon=(int)$arrdate[1];
        if (($mon >0)&&($mon<13)) {
          return true;
        } else {
          return false;
        } 
      }
    }
    $debug="aktion: $aktion";
    if (!isset($aktion)) { return; }
    $c=$this->cgiUtil;
    $id = $ID;
    $html="";
    $errortxt="";
    $Wettbewerb = trim($Wettbewerb);
    $deutschlandGruppe = trim($deutschlandGruppe);
    $startDatum = trim($startDatum);
    $endeDatum = trim($endeDatum);
    $debug.=" id: $id, Wettbewerb: $Wettbewerb, deutschlandGruppe: $deutschlandGruppe";
    if ($aktion == "u" || $aktion == "n") { 
      if (false === checkDatum($startDatum)) {
          $errortxt.="startDatum $startDatum fehlerhaft (yyyy-mm-tt)<br>";
          $errortxt = utf8_encode($errortxt);
          return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
        }
        if (false === checkDatum($endeDatum)) {
          //throw $this->createNotFoundException("endeDatum $endeDatum fehlerhaft (yyyy-mm-tt)<br>");
          $errortxt.="endeDatum $endeDatum fehlerhaft (yyyy-mm-tt)<br>";
          $errortxt = utf8_encode($errortxt);
          return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
        }
      }
      $debug.=' Datum checked';
      if ($aktion == "u" ) {   // Wettbewerb uebernehmen
        $value = "SET ";
        $value .= "value2='" . $anzahlGruppen ."' ," ; 
        $value .= "value3='" . $deutschlandGruppe ."' ," ;
        $value .= "value4='" . $startDatum ."' ," ;
        $value .= "value5='" . $endeDatum ."' " ;
        $sql = "update tl_hy_config $value where Name='Wettbewerb' AND value1='$Wettbewerb'";
//echo "sql: $sql<br>";	
        $cnt = $this->connection->executeStatement($sql);
	    $html.="Wettbewerb $Wettbewerb  Anzahl Gruppen $anzahlGruppen Deutschlandgruppe $deutschlandGruppe start $startDatum ende $endeDatum neu gesetzt";
        $html = utf8_encode($html);
        return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
      } elseif ($aktion == "n" ) {   // Neuer Wettbewerb
    // zuerst Prüfen ob schon vorhanden
        $sql = "select ID,Name,Value1 From tl_hy_config where Name='Wettbewerb' AND value1='$Wettbewerb'";
//echo "<br>sql: $sql<br>";
        $stmt = $this->connection->executeQuery($sql);
        $anz = $stmt->rowCount();
        //echo "neu anzahl eintr&auml;ge $anz<br>";
        if ($anz > 0) {
          $errortxt.="Wettbewerb $Wettbewerb existiert bereits";
          $errortxt = utf8_encode($errortxt);
          return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
        }
        if ($Wettbewerb == "" || $anzahlGruppen == "" || $deutschlandGruppe == "" || $startDatum == "" || $endeDatum == "") {
          $errortxt.="Kein Feld darf leer sein";
          $errortxt = utf8_encode($errortxt);
          return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
        } elseif (!is_numeric($anzahlGruppen)) {
          $errortxt.="Fehlerhafte Gruppenzahl";
          $errortxt = utf8_encode($errortxt);
          return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
        } else {
        // als aktuellen Wettbewerb entfernen
          $value = "SET ";
          $value .= "aktuell=0" ; 
	      $sql = "update tl_hy_config $value;";
//echo "sql: $sql<br>";	
          $cnt = $this->connection->executeQuery($sql);
          $value = "( ";
          $value .= "'Wettbewerb' ," ; 
          $value .= "'1' ," ; 
          $value .= "'" . $Wettbewerb ."' ," ; 
          $value .= "'" . $anzahlGruppen ."' ," ; 
          $value .= "'" . $deutschlandGruppe ."' ," ; 
          $value .= "'" . $startDatum ."' ," ;
          $value .= "'" . $endeDatum ."' " ;
          $value .= ")" ; 
	      $sql="INSERT INTO tl_hy_config(Name,aktuell,value1,value2,value3,value4,value5) VALUES $value";
//echo "sql: $sql<br>";
	      //$conn->printerror=true;
          $cnt = $this->connection->executeQuery($sql);
	      $html.="Wettbewerb neu eingetragen $w &uuml;bernommen";
          $html = utf8_encode($html);
          return new JsonResponse(['dir'=>__DIR__,'data' => $html,'debug'=>$debug]); 
        }
      } elseif ($aktion == "d" ) {   // Wettbewerb loeschen
        $sql = "select * From tl_hy_config where Name='Wettbewerb' AND ID='$id'";
//echo "sql: $sql<br>";	
        $stmt = $this->connection->executeQuery($sql);
        $row = $stmt->fetchAssociative();  // s. Doctrine\DBAL
        $wb=$row['value1']; 
        //echo "noch d (loeschen) nicht realisiert ID $id Wettbewerb $wb<br>";
        $tbs = array("tl_hy_wetteaktuell","tl_hy_teilnehmer","tl_hy_spiele","tl_hy_orte","tl_hy_mannschaft","tl_hy_gruppen");
        foreach ($tbs as $k=>$tab) {
          $sql="DELETE FROM $tab WHERE wettbewerb ='$wb';";
//echo "sql: $sql<br>";	
          $cnt = $this->connection->executeStatement($sql);
          //$cnt=$conn->affected();
          $html.="in Tabelle $tab betroffene Saetze $cnt<br>";
        }
        $sql="DELETE FROM tl_hy_config WHERE value1 ='$wb';";
//echo "sql: $sql<br>";	
        $cnt = $this->connection->executeStatement($sql);
        //$cnt=$conn->affected();
        $html.="in Tabelle tl_hy_config betroffene Saetze $cnt<br>";
        $html.="<br><strong>!! Achtung evtl neuen aktuellen Wettbewerb wählen !! </strong><br><br>";
        $html = utf8_encode($html);
        return new JsonResponse(['dir'=>__DIR__,'data' => $html,'debug'=>$debug]); 
      } elseif ($aktion == "a" ) {   // setze akt Wettbewerb
      // selektierter Wettbewerb
        $sql = "select * From tl_hy_config where Name='Wettbewerb' AND ID='$id'";
//echo "<br>sql: $sql<br>";
        $stmt = $this->connection->executeQuery($sql);
        $anz = $stmt->rowCount();
        //echo "neu anzahl eintr&auml;ge $anz<br>";
        if ($anz < 1) {
          $html.="Wettbewerb existiert nicht";
        $html = utf8_encode($html);
        return new JsonResponse(['dir'=>__DIR__,'data' => $html,'debug'=>$debug]); 
        }
        $value = "SET ";
        $value .= "aktuell=0" ; 
	    $sql = "update tl_hy_config $value;";
//echo "sql: $sql<br>";	
        $cnt = $this->connection->executeStatement($sql);
        $value = "SET ";
        $value .= "aktuell=1" ; 
	    $sql = "update tl_hy_config $value where Name='Wettbewerb' AND ID='$id';";
//echo "sql: $sql<br>";	
        $cnt = $this->connection->executeStatement($sql);
        $html.="neuer aktueller Wettbewerb bitte F5 dr&uuml;cken<br>";
      } else {
        $html.="fehlerhafte Aktion $aktion<br>";
        $html = utf8_encode($html);
        return new JsonResponse(['dir'=>__DIR__,'data' => $html,'debug'=>$debug]); 
      }
      $html = utf8_encode($html);
      return new JsonResponse(['dir'=>__DIR__,'data' => $html,'debug'=>$debug]); 
  }
  
    /**
     * @throws \Exception
     * @throws DoctrineDBALException
     *
     * @Route("/fussball/bearbeitemannschaft/{aktion}/{ID}/{name}/{nation}/{Gruppe}", 
     * name="FussballRequestClass::class\bearbeitemannschaft", 
     * defaults={"_scope" = "frontend"})
     */

  public function bearbeitemannschaft(string $aktion,int $ID=-1,string $name="",string $nation='',string $Gruppe='')
  {
    if (!isset($aktion)) {
      $html.="fehlerhafte Aktion empty<br>";
      $errortxt.="fehlerhafte Aktion empty<br>";
      $errortxt = utf8_encode($errortxt);
      return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
    }
    $c=$this->cgiUtil;
    $id = $ID;
    $html="";
    $debug="aktion: $aktion";
    $errortxt="";
    $Wettbewerb = $this->aktWettbewerb['aktWettbewerb'];
    $name = trim($name);
    $nation = trim($nation);
    $Gruppe = trim($Gruppe);
    $debug.=" id: $id, Wettbewerb: $Wettbewerb, name: $name, nation $nation, nation $Gruppe";
    if ($aktion == "u" || $aktion == "n") { 
      // flagge lesen
      $sql = "select * from tl_hy_nation where Nation='$nation'";
      $stmt = $this->connection->executeQuery($sql);
      $debug.="sql: $sql<br>";	
      $anz = $stmt->rowCount();
      $flagge="";
//      $flid="";
      if ($anz > 0) {
        $rownati = $stmt->fetchAssociative();
	    $flagge =   $rownati['Image'];
//	    $flid = $rownati['ID'];
      } else {         // default Deutschland
        $sql = "select * from tl_hy_nation where Nation='Deutschland'";
        $stmt = $this->connection->executeQuery($sql);
        $rownati = $stmt->fetchAssociative();
	    $flagge =   $rownati['Image'];
//	    $flid = $rownati['ID'];
      }
      if ($aktion == "n" ) {   // neueintrag
        $sql="SELECT name FROM tl_hy_mannschaft WHERE name='$name' AND Wettbewerb ='$Wettbewerb'"; 
        $stmt = $this->connection->executeQuery($sql);
        $debug.="sql: $sql<br>";	
        $anz = $stmt->rowCount();
        if ($anz > 0) {
          $errortxt.="Mannschaft $name existiert bereits";
          $errortxt = utf8_encode($errortxt);
          return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
        }
        $sql="INSERT INTO tl_hy_mannschaft(Wettbewerb,Name,Nation,Flagge,Gruppe) VALUES ('$Wettbewerb','$name','$nation','$flagge','$Gruppe');";
        $debug.="sql: $sql<br>";	
        $cnt = $this->connection->executeStatement($sql);
	    $html.="Wettbewerb $Wettbewerb  Mannschaft $name Nation $nation Flagge $flagge  $flid neu gesetzt";
        $html = utf8_encode($html);
        return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
      }
      if ($aktion == "u" ) {   // Mannschaft uebernehmen

        $value = "SET ";
        $value .= "Name='" . $name ."' ," ; 
        $value .= "Nation='" . $nation ."' ," ;
        $value .= "Flagge='" . $flagge ."' ," ;
        $value .= "Gruppe='" . $Gruppe ."' " ;

	    $sql = "update tl_hy_mannschaft $value where ID='$id'";
//echo "sql: $sql<br>";	
        $cnt = $this->connection->executeStatement($sql);
	    $html.="Wettbewerb $Wettbewerb  Mannschaft $name Nation $nation Flagge $flagge bearbeitet";
        $html = utf8_encode($html);

        return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
      }
    }
    if ($aktion == "d" ) {   // Mannschaft loeschen
	  $sql = "Delete from tl_hy_mannschaft WHERE ID='$id' LIMIT 1";
      $cnt = $this->connection->executeStatement($sql);
      //$html.="in Tabelle tl_hy_mannschaft betroffene Saetze $cnt<br>";
	  $html.="Mannschaft gel&ouml;scht";
      $html = utf8_encode($html);
      return new JsonResponse(['data' => $html,'error'=>$errortxt,'debug'=>$debug]); 
    }
    $html.="fehlerhafte Aktion $aktion<br>";
    $errortxt.="fehlerhafte Aktion $aktion<br>";
    $errortxt = utf8_encode($errortxt);
    return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
  } 
    /**
     * @throws \Exception
     * @throws DoctrineDBALException
     *
     * @Route("/fussball/bearbeitenation/{aktion}/{ID}/{Nation}/{Type}/{Alfa2}/{Alfa3}/{Domain}/{Image}", 
     * name="FussballRequestClass::class\bearbeitenation", 
     * defaults={"_scope" = "frontend"})
     */

  public function bearbeitenation(string $aktion,int $ID=-1,string $Nation='',string $Type='',string $Alfa2='',string $Alfa3='',string $Domain='',string $Image='')
  {
    if (!isset($aktion)) {
      $html.="fehlerhafte Aktion empty<br>";
      $errortxt.="fehlerhafte Aktion empty<br>";
      $errortxt = utf8_encode($errortxt);
      return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
    }
    $c=$this->cgiUtil;
    $id = $ID;
    $html="";
    $debug="aktion: $aktion";
    $errortxt="";
    $Wettbewerb = $this->aktWettbewerb['aktWettbewerb'];
    $Nation = trim($Nation);
    $Type = trim($Type);
    $Alfa2 = trim($Alfa2);
    $Alfa3 = trim($Alfa3);
    $Domain = trim($Domain);
    $Image = trim($Image);
    $debug.=" id: $id, Wettbewerb: $Wettbewerb, Nation $Nation, Type $Type, Alfa2 $Alfa2, Alfa3 $Alfa3, Domain $Domain, Image $Image";
    if ($aktion == "u" || $aktion == "n") { 
      if ($aktion == "n" ) {   // neueintrag
        if (empty($Nation)||empty($Type)||empty($Image)) {
          $errortxt.="Nation/ Type/ Image Eingabe notwendig";
          $errortxt = utf8_encode($errortxt);
          return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
        }
        $sql="SELECT Nation FROM tl_hy_nation WHERE Nation='$Nation'"; 
        $stmt = $this->connection->executeQuery($sql);
        $debug.="sql: $sql<br>";	
        $anz = $stmt->rowCount();
        if ($anz > 0) {
          $errortxt.="Nation $Nation existiert bereits";
          $errortxt = utf8_encode($errortxt);
          return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
        }
        $sql="INSERT INTO tl_hy_nation(Nation,Type,Alfa2,Alfa3,Domain,Image) VALUES ('$Nation','$Type','$Alfa2','$Alfa3','$Domain','$Image');";
        $debug.="sql: $sql<br>";	
        $cnt = $this->connection->executeStatement($sql);
        $html.=" Nation: $Nation, Type: $Type, Alfa2: $Alfa2, Alfa3: $Alfa3, Domain: $Domain, Image: $Image";
        $html = utf8_encode($html);
        return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
      }
      if ($aktion == "u" ) {   // Nation uebernehmen

        $value = "SET ";
        $value .= "Nation='" . $Nation ."' ," ;
        $value .= "Type='" . $Type ."' ," ;
        $value .= "Alfa2='" . $Alfa2 ."' ," ;
        $value .= "Alfa3='" . $Alfa3 ."' ," ;
        $value .= "Domain='" . $Domain ."' ," ;
        $value .= "Image='" . $Image ."' " ;

	    $sql = "update tl_hy_nation $value where ID='$id'";
//echo "sql: $sql<br>";	
        $cnt = $this->connection->executeStatement($sql);
        $html.=" Nation: $Nation, Type: $Type, Alfa2: $Alfa2, Alfa3: $Alfa3, Domain: $Domain, Image: $Image";
        $html = utf8_encode($html);

        return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
      }
    }
    if ($aktion == "d" ) {   // Mannschaft loeschen
	  $sql = "Delete from tl_hy_nation WHERE ID='$id' LIMIT 1";
      $cnt = $this->connection->executeStatement($sql);
      //$html.="in Tabelle tl_hy_nation betroffene Saetze $cnt<br>";
	  $html.="Nation gel&ouml;scht";
      $html = utf8_encode($html);
      return new JsonResponse(['data' => $html,'error'=>$errortxt,'debug'=>$debug]); 
    }
    $html.="fehlerhafte Aktion $aktion<br>";
    $errortxt.="fehlerhafte Aktion $aktion<br>";
    $errortxt = utf8_encode($errortxt);
    return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
  } 
    
    /**
     * @throws \Exception
     * @throws DoctrineDBALException
     *
     * @Route("/fussball/bearbeiteort/{aktion}/{ID}/{ort}/{beschreibung}", 
     * name="FussballRequestClass::class\bearbeiteort", 
     * defaults={"_scope" = "frontend"})
     */

  public function bearbeiteort(string $aktion,int $ID=-1,string $ort="",string $beschreibung='')
  {
    if (!isset($aktion)) {
      $html.="fehlerhafte Aktion empty<br>";
      $errortxt.="fehlerhafte Aktion empty<br>";
      $errortxt = utf8_encode($errortxt);
      return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
    }
    $c=$this->cgiUtil;
    $id = $ID;
    $html="";
    $debug="aktion: $aktion";
    $errortxt="";
    $Wettbewerb = $this->aktWettbewerb['aktWettbewerb'];
    $ort = trim($ort);
    $beschreibung = trim($beschreibung);
    $debug.=" id: $id, Wettbewerb: $Wettbewerb, Ort: $ort, Beschreibung $beschreibung";
    if ($aktion == "u" || $aktion == "n") { 
      if ($aktion == "n" ) {   // neueintrag
        $value = "( ";
        $value .= "'" . $Wettbewerb ."' ," ; 
        $value .= "'" . $ort ."' ," ; 
        $value .= "'" . $beschreibung ."' )" ;
	    $sql="INSERT INTO tl_hy_orte(Wettbewerb,Ort,Beschreibung) VALUES $value";
        $cnt = $this->connection->executeStatement($sql);
	    $html.="Wettbewerb $Wettbewerb Ort $ort Beschreibung $beschreibung neu gesetzt";
        $html = utf8_encode($html);
        return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
      }
      if ($aktion == "u" ) {   // Mannschaft uebernehmen
        $value = "SET ";
        $value .= "Wettbewerb='" . $Wettbewerb ."' ," ; 
        $value .= "Ort='" . $ort ."' ," ;
        $value .= "Beschreibung='" . $beschreibung ."' " ;

	    $sql = "update tl_hy_orte $value where ID='$id'";
//echo "sql: $sql<br>";	
        $cnt = $this->connection->executeStatement($sql);
	    $html.="Wettbewerb $Wettbewerb Ort $ort Beschreibung $beschreibung bearbeitet";
        $html = utf8_encode($html);
        return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
      }
    }
    if ($aktion == "d" ) {   // Ort loeschen
	  $sql = "Delete from tl_hy_orte WHERE ID='$id' LIMIT 1";
      $cnt = $this->connection->executeStatement($sql);
      //$html.="in Tabelle tl_hy_orte betroffene Saetze $cnt<br>";
	  $html.="Ort gel&ouml;scht";
      $html = utf8_encode($html);
      return new JsonResponse(['data' => $html,'error'=>$errortxt,'debug'=>$debug]); 
    }
    $html.="fehlerhafte Aktion $aktion<br>";
    $errortxt.="fehlerhafte Aktion $aktion<br>";
    $errortxt = utf8_encode($errortxt);
    return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
  } 
  
  /* Bei bearbeite Gruppe 
   * aktion u es kann nur der Platz gesetzt werden.
   * aktion a Spiele ... werden neu berehnet und gestzt. Platz wirdderzeit noch nicht gesetzt er bleibt erhalten
   */
    /**
     * @throws \Exception
     * @throws DoctrineDBALException
     *
     * @Route("/fussball/bearbeitegruppe/{aktion}/{ID}/{Platz}", 
     * name="FussballRequestClass::class\bearbeitegruppe", 
     * defaults={"_scope" = "frontend"})Sieg
     */
  public function bearbeitegruppe(string $aktion,int $ID=-1,int $Platz=-1)
  {
    function replace16Bit($str) {
      // ersetze 16-bit Values
      $search  = array("\xC3\xA4", "\xC3\xB6", "\xC3\xBC", "\xC3\x84", "\xC3\x96","\xC3\x9f");
      $replace = array('ä', 'ö', 'ü', 'Ä', 'Ö','Ü','ß');
      $str= str_replace($search, $replace, $str);     
      return $str;     
    }    
    if (!isset($aktion)) {
      $html.="fehlerhafte Aktion empty<br>";
      $errortxt.="fehlerhafte Aktion empty<br>";
      $errortxt = utf8_encode($errortxt);
      return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
    }
    $c=$this->cgiUtil;
    $id = $ID;
    $html="";
    $debug="aktion: |$aktion|";
    $errortxt="";
    $Wettbewerb = $this->aktWettbewerb['aktWettbewerb'];
    if ($aktion == "u") {
      if ($ID<0) {
        $html.="unzulässige ID $ID<br>";
        $html = utf8_encode($html);
        $errortxt.="unzulässige ID $ID<br><br>";
        $errortxt = utf8_encode($errortxt);
        return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
      }
      $value = "SET Platz=$Platz" ;
  	  $sql = "update tl_hy_gruppen $value where ID='$id'";
      $debug.=" sql: $sql\n";	
      $cnt = $this->connection->executeStatement($sql);
	  $html.="Wettbewerb $Wettbewerb GrupenId $id Platz $Platz";
      $html=replace16Bit($html);
      $html = utf8_encode($html);
      return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
    } 
    if ($aktion == "a") {              // alle Gruppeneintraege löschen und aus den Spielen neu aufbauen
       $sql="SELECT * FROM tl_hy_gruppen WHERE Wettbewerb='$Wettbewerb'";
       $stmt = $this->connection->executeQuery($sql);
       $Update=0;
       $ExistGruppen=[];
       $num_rows = $stmt->rowCount();
       if ($num_rows > 0) {
         $Update=1;
         while (($row = $stmt->fetchAssociative()) !== false) {
          $ExistGruppen[$row['Gruppe']][$row['M1']]=$row;                 // index Gruppe und Mannschaft
          //$debug.='gespeichert als ExistGruppen['.$row['Gruppe'].']['.$row['M1'].']<br>';
         }
       }    
       //$cnt = $this->connection->executeStatement($sql);
	   $debug.="sql: $sql Anzahl vorhandener Gruppen $num_rows: $num_rows Update |$Update|\n";
       $Mannschaften=[] ;
        // alle aktuellen Mannschaften einlesen
       $sql="SELECT * FROM tl_hy_mannschaft WHERE Wettbewerb='".$this->aktWettbewerb['aktWettbewerb']."' ORDER BY ID"; 
       $stmt = $this->connection->executeQuery($sql);
       $num_rows = $stmt->rowCount();    
       while (($row = $stmt->fetchAssociative()) !== false) {
         $Mannschaften[]=$row;
       }
        // alle aktuellen Spiele einlesen
       foreach ($Mannschaften as $k=>$row) {
         $M1=$row['ID'];$Platz=-1;$Spiele=-1;$Sieg=-1;$Unentschieden=-1;$Niederlage=-1;$Tore=-1;$Gegentore=-1;$Differenz=-1;$Punkte=-1;
         $Gruppe=$row['Gruppe'];    
         $sql  = "SELECT ID,Nr,M1,M2,T1,T2 FROM tl_hy_spiele WHERE Wettbewerb  ='$Wettbewerb' AND M1 = $M1";  // Heim Spiele
         $stmt = $this->connection->executeQuery($sql); $num_rows = $stmt->rowCount();    
         //$debug.="|spiele sql: $sql anz: $num_rows | ";	
         while (($spielrow = $stmt->fetchAssociative()) !== false) {
           //$debug.="| heimspiel T1 ".$spielrow['T1']."  heimspiel T2 ".$spielrow['T2']." | ";	
           if ($spielrow['T1'] != -1 && $spielrow['T2'] != -1) {   
             if ($Spiele < 0) $Spiele=0; $Spiele ++;            // Spiel hat stattgefunden
             if ($Tore < 0) $Tore=0; $Tore = $Tore+$spielrow['T1'];
             if ($Gegentore < 0) $Gegentore=0; $Gegentore = $Gegentore+$spielrow['T2'];
             if ( $spielrow['T1'] >  $spielrow['T2']) { if ($Sieg < 0) $Sieg=0; $Sieg ++; if ($Punkte < 0) $Punkte=0; $Punkte=$Punkte+3;}
             if ( $spielrow['T1'] <  $spielrow['T2']) { if ($Niederlage < 0) $Niederlage=0; $Niederlage ++; if ($Punkte < 0) $Punkte=0;}
             if ( $spielrow['T1'] == $spielrow['T2']) { 
               if ($Unentschieden < 0) $Unentschieden=0; $Unentschieden ++; if ($Punkte < 0) $Punkte=0; $Punkte=$Punkte+1;
            }
           }
         }
         $sql  = "SELECT ID,Nr,M1,M2,T1,T2 FROM tl_hy_spiele WHERE Wettbewerb  ='$Wettbewerb' AND M2 = $M1";  // Auswaerts Spiele
         $stmt = $this->connection->executeQuery($sql); $num_rows = $stmt->rowCount();    
         while (($spielrow = $stmt->fetchAssociative()) !== false) {
           //$debug.="| auswaerts T1 ".$spielrow['T1']."  auswaerts T2 ".$spielrow['T2']." | ";	
           if ($spielrow['T1'] != -1 && $spielrow['T2'] != -1) {   
             if ($Spiele < 0) $Spiele=0; $Spiele ++;        // Spiel hat stattgefunden
             if ($Tore < 0) $Tore=0; $Tore = $Tore+$spielrow['T2'];
             if ($Gegentore < 0) $Gegentore=0; $Gegentore = $Gegentore+$spielrow['T1'];
             if ( $spielrow['T2'] >  $spielrow['T1']) { if ($Sieg < 0) $Sieg=0; $Sieg ++; if ($Punkte < 0) $Punkte=0; $Punkte=$Punkte+3;}
             if ( $spielrow['T2'] <  $spielrow['T1']) { if ($Niederlage < 0) $Niederlage=0; $Niederlage ++; if ($Punkte < 0) $Punkte=0;}
             if ( $spielrow['T1'] == $spielrow['T2']) { if ($Unentschieden < 0) $Unentschieden=0; $Unentschieden ++; if ($Punkte < 0) $Punkte=0; $Punkte=$Punkte+1;}
           }
         }
         if ($Tore != -1) { if ($Differenz < 0) $Differenz=0;$Differenz=$Tore-$Gegentore;}
         if ($Update == 1) {
           $oldRow=$ExistGruppen[$row['Gruppe']][$row['ID']];        // ID = MannschaftsID Row der Gruppe
           $Groupid = $oldRow['ID'];
           $value = "SET ";
           $value .= "Spiele=$Spiele ," ;
           $value .= "Sieg=$Sieg ," ;
           $value .= "Unentschieden=$Unentschieden ," ;
           $value .= "Niederlage=$Niederlage ," ;
           $value .= "Tore=$Tore ," ;
           $value .= "Gegentore=$Gegentore ," ;
           $value .= "Differenz=$Differenz ," ;
           $value .= "Punkte=$Punkte " ;
  	       $sql = "UPDATE tl_hy_gruppen $value  where ID='$Groupid'";
         
           //$debug.="'|' update sql: $sql '|' ";	
           $cnt = $this->connection->executeStatement($sql);
	       $html.="Update  Gruppe $Gruppe M1 ".$M1." cnt ".$cnt;
         } else {
           $value = "( '$Wettbewerb','$Gruppe',$M1,$Platz,$Spiele,$Sieg,$Unentschieden,$Niederlage,$Tore,$Gegentore,$Differenz,$Punkte)" ;
           $sql="INSERT INTO tl_hy_gruppen(Wettbewerb,Gruppe,M1,Platz,Spiele,Sieg,Unentschieden,Niederlage,Tore,Gegentore,Differenz,Punkte) VALUES $value";
           //$debug.="'|' insert sql: $sql '|' ";	
           $cnt = $this->connection->executeStatement($sql);
	       $html.="Eingefügt  Gruppe $Gruppe M1 $M1 ";
         }
       }
       $debug.="!!!!!!!!!!!!!Platz bestimmen !!!!!!!!!!!!!!!!!!!!!\n";
       //return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]);  
       // Versuch der Platzbestimmung
       // lies alle Gruppen nochmals ein
       $sql="SELECT * FROM tl_hy_gruppen WHERE Wettbewerb='$Wettbewerb' ORDER BY Gruppe";
       $stmt = $this->connection->executeQuery($sql);
       $Update=0;
       $ExistGruppen=[];
       $num_rows = $stmt->rowCount();
       if ($num_rows > 0) {
         $Update=1;
         while (($row = $stmt->fetchAssociative()) !== false) {
          $ExistGruppen[$row['Gruppe']][$row['M1']] = $row;
         }
       } else {
         $errortxt.="Fehler bei Platzbestimmung Anz gruppen $num_rows";
         return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]);  
       } 
       $grpNameSelect="";
       //$debug="";
       
       foreach ($ExistGruppen as $k=>$grp) {
         $grpName=$k;
         $debug.="index $k grpName $grpName<br>";
         if ($grpNameSelect != $grpName) {          // neue Gruppe
           /*
           foreach ($ExistGruppen[$k] as $k1=>$v1) {
             $debug.="ExistGruppen[$k][$k1] Plaetze berechnen vor sort len ".count($ExistGruppen[$k])."<br>";
             foreach ($v1 as $k2=>$v2) $debug.="ExistGruppen[$k][$k1][$k2]: $v2 ";
             $debug.="<br>";
           }
           */
           // dient zum sortieren
           /* GRUPPENwertung Platz
             a) Für die Tabellenplatzierung sind die erspielten Punkte entscheidend. 
             b) Bei Punktgleichheit entscheidet zunächst das Torverhältnis/Differenz und 
             c) schließlich die höhere Anzahl der erzielten Tore. 
             Wenn zwei oder mehrere Mannschaften in den drei erwähnten Kriterien gleich abschneiden, entscheiden folgende Kriterien: 
             1. Punkte im direkten Vergleich 
             2. Torverhältnis/Differenz im direkten Vergleich 
             3. Anzahl der erzielten Tore im direkten Vergleich 
             4. Fair-Play-Wertung 
             5. Losentscheid
           */
           usort($ExistGruppen[$k], function ($a, $b) use (&$debug)
                {
                  $ap = $a['Punkte'];
                  $bp = $b['Punkte'];
                  //$debug.="SORT grp: ".$a['Gruppe']." a:(" . $a['M1'] . ") Punkte " . $a['Punkte']  . "($ap)) b:(" . $b['M1'] . ") Punkte " . $b['Punkte'] . "($bp)<br>";
                  if ($a['Punkte'] == $b['Punkte'])  {  // punktestand gleich
//                  $debug.=" Punkte gleich<br>";
                    if ($a['Differenz'] == $b['Differenz']) {    // Differenz gleich
//                    $debug.=" Differenz gleich<br> ";
                        if ($a['Tore'] == $b['Tore']) {    // Tore gleich   jetzt gilt der direkte Vergleich
//                        $debug.=" Tore gleich<br> ";
/*                        Hier muss noch das Spiel eingelesen werden
                            $M1 = $a['MID'];
                            $M2 = $b['MID'];      // Suche das entsprechende Spiel
                            echo "($M1) " . $a['M1'] . " ($M2) " . $b['M1'] . " !!!!!!!!!!!!!!!!!!!! Platz von Hand eintragen   !!! <br>";
                            foreach ($spiele as $k=>$row)  {
                              $M1Ind = $row['M1Ind'];
                              $M2Ind = $row['M2Ind'];
                              if ( ($M1 = $M1Ind && $M2 = $M2Ind) || ($M2 = $M2Ind && $M1 = $M1Ind)) {   // Spiel gefunden
                            }
                            return 0;
                          }
*/
//                        $debug.=" return 0<br> ";
                          return 0;
                        }
//                    $debug.=" return Tore Ungleich<br> ";
                      return ($a['Tore'] > $b['Tore']) ? -1 : 1;
                    }
//                $debug.=" return Differenz Ungleich<br> ";
                  return ($a['Differenz'] > $b['Differenz']) ? -1 : 1;
                  }
//              $debug.=" return Punkte Ungleich<br> ";
                return ($a['Punkte'] > $b['Punkte']) ? -1 : 1;
               }
           );   // usort ende
           foreach ($ExistGruppen[$k] as $mid=>$row) {
             $Platz = $mid + 1;    // gruppen durch usort umsortiert nach index 0,1,2,3
             $Spiele=$row['Spiele'];
             if ($Spiele != -1) {
               $value = "SET Platz=$Platz" ;
  	           $sql = "update tl_hy_gruppen $value where ID='".$row['ID']."'";
               $debug.= "Update K $k Spiele $Spiele sql $sql<br>";    
               $cnt = $this->connection->executeStatement($sql);
             }
           }
         }
       }
       $html=replace16Bit($html);
       $html = utf8_encode($html); 
       return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]);           
    }
  }  

    /**
     * @throws \Exception
     * @throws DoctrineDBALException
     *
     * @Route("/fussball/bearbeitespiel/{aktion}/{ID}/{Nr}/{Gruppe}/{M1}/{M2}/{Ort}/{Datum}/{Uhrzeit}/{T1}/{T2}", 
     * name="FussballRequestClass::class\bearbeitespiel", 
     * defaults={"_scope" = "frontend"})
     */

  public function bearbeitespiel(
    string $aktion,
    int $ID=-1,
    int $Nr=-1,
    string $Gruppe='',
    int $M1=-1,
    int $M2=-1,
    int $Ort=-1,
    string $Datum='',
    string $Uhrzeit='',
    int $T1=-2,
    int $T2=-2)
  {
    function checkDatum(string $Datum) {
      //if (isempty($Datum) || $Datum == "") return false;   // Datum Pflicht
      $arrdate=explode("-",$Datum);
      if (count($arrdate) != 3) {
        return false;
      } else {
        $mon=(int)$arrdate[1];
        if (($mon >0)&&($mon<13)) {
          return true;
        } else {
          return false;
        } 
      }
    }
    $aktion=trim(strtolower($aktion));
    if (!isset($aktion)) {
      $html.="fehlerhafte Aktion empty<br>";
      $errortxt.="fehlerhafte Aktion empty<br>";
      $errortxt = utf8_encode($errortxt);
      return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
    }
    $c=$this->cgiUtil;
    $id = $ID;
    $html="";
    $debug="aktion: |$aktion|";
    $errortxt="";
    $Wettbewerb = $this->aktWettbewerb['aktWettbewerb'];
    if ($aktion == "u" || $aktion == "n") { 
    // check kein leerer Parameter
    $debug.= '      aaaa';
      if ($Nr== -1 || $Gruppe=='' || $M1==-1 || $M2==-1 || $Ort==-1 || $Datum=='' || $Uhrzeit=='' || $T1==-2 || $T2==-2) {
	      $errortxt.="ID $ID Wettbewerb $Wettbewerb Spielnummer $Nr Gruppe $Gruppe M1 $M1 M2 $M2  Ort $Ort Datum $Datum Uhrzeit $Uhrzeit T1 $T1 T2 $T2 neu";
          //$errortxt = utf8_encode($errortxt);
          return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
      }
      if (false === checkDatum($Datum)) {
          $errortxt.="startDatum $Datum fehlerhaft (yyyy-mm-tt)<br>";
          $errortxt = utf8_encode($errortxt);
          return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
      }

      if ($aktion == "n" ) {   // neueintrag
        // check spielnummer
    // zuerst Prüfen ob schon vorhanden
        $sql = "select Nr From tl_hy_spiele where Wettbewerb='$Wettbewerb' AND Nr='$Nr'";
//echo "<br>sql: $sql<br>";
        $stmt = $this->connection->executeQuery($sql);
        $anz = $stmt->rowCount();
        $debug .=" neu anzahl eintraege $anz\n";
        if ($anz > 0) {
          $errortxt.=" Spiel $Nr existiert bereits";
          //$errortxt = utf8_encode($errortxt);
          $debug = utf8_encode($debug);
          return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
        }
        
        $value = "( ";
        $value .= "'" . $Wettbewerb ."' ," ; 
        $value .= "'" . $Nr ."' ," ; 
        $value .= "'" . $Gruppe ."' ," ; 
        $value .= "'" . $M1 ."' ," ; 
        $value .= "'" . $M2 ."' ," ;
        $value .= "'" . $Ort ."' ," ; 
        $value .= "'" . $Datum ."' ," ; 
        $value .= "'" . $Uhrzeit ."' ," ; 
        $value .= "'" . $T1 ."' ," ; 
        $value .= "'" . $T2 ."' )" ;
	    $sql="INSERT INTO tl_hy_spiele(Wettbewerb,Nr,Gruppe,M1,M2,Ort,Datum,Uhrzeit,T1,T2) VALUES $value";
        $cnt = $this->connection->executeStatement($sql);
        $debug.="sql: $sql<br>";	
	    $html.="Wettbewerb $Wettbewerb Spielnummer $Nr Gruppe $Gruppe M1 $M1 M2 $M2  Ort $Ort Datum $Datum Uhrzeit $Uhrzeit T1 $T1 T2 $T2 neu";
        $html = utf8_encode($html); 
        return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]);  
      }
      if ($aktion == "u" ) {   // Spiel uebernehmen
        $value = "SET ";
        $value .= "Wettbewerb='$Wettbewerb' ," ; 
        $value .= "Nr=$Nr ," ; 
        $value .= "Gruppe='$Gruppe' ," ; 
        $value .= "M1=$M1 ," ; 
        $value .= "M2=$M2 ," ;
        $value .= "Ort=$Ort ," ; 
        $value .= "Datum='$Datum' ," ; 
        $value .= "Uhrzeit='$Uhrzeit' ," ; 
        $value .= "T1=$T1 ," ; 
        $value .= "T2=$T2 " ;
	    $sql = "update tl_hy_spiele $value where ID=$id";
$debug.=" sql: $sql\n";	
    //return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
        $cnt = $this->connection->executeStatement($sql);

	    $html.="Wettbewerb $Wettbewerb Spielnummer $Nr Gruppe $Gruppe M1 $M1 M2 $M2  Ort $Ort Datum $Datum Uhrzeit $Uhrzeit T1 $T1 T2 $T2 bearbeitet";
        $html = utf8_encode($html);
        return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
      }
    }
    if ($aktion == "d" ) {   // Spiel loeschen
	  $sql = "Delete from tl_hy_spiele WHERE ID='$id' LIMIT 1";
      $cnt = $this->connection->executeStatement($sql);
      //$html.="in Tabelle tl_hy_spiele betroffene Saetze $cnt<br>";
	  $html.="Spiel $id Nr $Nr gel&ouml;scht";
      $html = utf8_encode($html);
      return new JsonResponse(['data' => $html,'error'=>$errortxt,'debug'=>$debug]); 
    }
    $html.="fehlerhafte Aktion $aktion Spiel bearbeiten<br>";
    $errortxt.="fehlerhafte Aktion $aktion\n";
    $errortxt = utf8_encode($errortxt);
    return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
  } 

    /**
     * @throws \Exception
     * @throws DoctrineDBALException
     *
     * @Route("/fussball/bearbeitewette/{aktion}/{ID}/{Kommentar}/{Art}/{Pok}/{Ptrend}/{Tipp1}/{Tipp2}/{Tipp3}/{Tipp4}", 
     * name="FussballRequestClass::class\bearbeitewette", 
     * defaults={"_scope" = "frontend"})
     */

  public function bearbeitewette(
    string $aktion,
    int $ID=-1,
    string $Kommentar='',
    string $Art='',
    int $Pok=-1,
    int $Ptrend=-1,
    string $Tipp1='',        
    string $Tipp2='',
    string $Tipp3='',
    string $Tipp4='',
    )
  {
    $aktion=trim(strtolower($aktion));
    if (!isset($aktion)) {
      $html.="fehlerhafte Aktion empty<br>";
      $errortxt.="fehlerhafte Aktion empty<br>";
      $errortxt = utf8_encode($errortxt);
      return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
    }
    $c=$this->cgiUtil;
    $id = $ID;
    $html="";
    $debug="aktion: |$aktion|";
    $errortxt="";
    $Wettbewerb = $this->aktWettbewerb['aktWettbewerb'];
    // check kein leerer Parameter
    if ($aktion != 'd' && $aktion != 'u' && ($Kommentar=='' || $Art=='')) {
	  $errortxt.="Fehlerhafte Eingabe ";
      $errortxt.="Kommentar: $Kommentar Art: $Art";
      //$errortxt = utf8_encode($errortxt);
      return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
    }
    if ($aktion == "n" ) {   // neueintrag
      $value = "( '$Wettbewerb','$Kommentar','$Art',$Pok,$Ptrend,'$Tipp1','$Tipp2','$Tipp3','$Tipp4')";
	  $sql="INSERT INTO  tl_hy_wetten(Wettbewerb,Kommentar,Art,Pok,Ptrend,Tipp1,Tipp2,Tipp3,Tipp4) VALUES $value";
      $cnt = $this->connection->executeStatement($sql);
      $debug.="sql: $sql<br>";	
      $html.="Kommentar: $Kommentar Art: $Art Pok: $Pok Ptrend: $Ptrend Tipp1: $Tipp1 Tipp2: $Tipp2 Tipp3: $Tipp3 Tipp4: $Tipp4 neu";
      $html = utf8_encode($html); 
      // Wettid Lesen
      $sql="SELECT * FROM  tl_hy_wetten WHERE ID = LAST_INSERT_ID();";
      $stmt = $this->connection->executeQuery($sql);
      $row = $stmt->fetchAssociative();
      $lastWettid=$row['ID'];
      //  tl_hy_wettenaktuell eintragen
      // alle Teilnehmer einlesen
      $sql  = "SELECT";
      $sql .= " teilnehmer.ID as 'ID',";
      $sql .= " teilnehmer.Name as 'Name',";
      $sql .= " teilnehmer.Kurzname as 'Kurzname',";
      $sql .= " teilnehmer.Email as 'Email'";
      $sql .= " FROM tl_hy_teilnehmer as teilnehmer";
      $sql .= " WHERE Wettbewerb  ='".$this->aktWettbewerb['aktWettbewerb']."' ";
      $sql .= " ORDER BY teilnehmer.Kurzname  ;";
      $stmt = $this->connection->executeQuery($sql);
      $num_rows = $stmt->rowCount();    
//$errortext.="sql: $sql<br>anz $num_rows";
//$html.="sql: $sql<br>anz $num_rows";
//$debug.="lastWettid $lastWettid";
      $Teilnehmer=array();
      while (($row = $stmt->fetchAssociative()) !== false) {
        $Teilnehmer[]=$row;
      }
      foreach ($Teilnehmer as $k=>$tln) {
          $tid = $tln['ID'];
          $wettid=$lastWettid;
	      $value = "( '$Wettbewerb' , $tid , $wettid,-1 ,-1,-1 )" ; 
	      $sql="INSERT INTO  tl_hy_wetteaktuell(Wettbewerb,Teilnehmer,Wette,W1,W2,W3) VALUES $value";
          $debug.="Teilnehmerwetten: $sql<br>";
          $cnt = $this->connection->executeStatement($sql);  
//          $errortext.="wette fuer Tln $tid Name: ".$tln['Name'].' Wette '.$wettid.' geschrieben';       
          $html.="wette fuer Tln $tid Name: ".$tln['Name'].' Wette '.$wettid.' geschrieben';       
      }
      return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]);  
    }
    if ($aktion == "b" ) {   // Wette uebernehmen
      if ($Tipp2 == 'undefined') $Tipp2 = -1;
      if ($Tipp3 == 'undefined') $Tipp3 = -1;
      if ($Tipp4 == 'undefined') $Tipp4 = -1;
      $value = "SET ";
      $value .= "Wettbewerb='$Wettbewerb' ," ; 
      $value .= "Kommentar='$Kommentar' ," ; 
      $value .= "Art='$Art' ," ; 
      $value .= "Pok=$Pok ," ; 
      $value .= "Ptrend=$Ptrend ," ; 
      $value .= "Tipp1='$Tipp1' ," ; 
      $value .= "Tipp2='$Tipp2'," ; 
      $value .= "Tipp3='$Tipp3' ," ; 
      $value .= "Tipp4='$Tipp4' " ; 
	  $sql = "update  tl_hy_wetten $value where ID=$id";$debug.=" sql: $sql\n";	
      $cnt = $this->connection->executeStatement($sql);
      $html.="Wettbewerb $Wettbewerb Kommentar: $Kommentar Art: $Art Pok: $Pok Ptrend: $Ptrend Tipp1: $Tipp1 Tipp2: $Tipp2 Tipp3: $Tipp3 Tipp4: $Tipp4 neu";
      //$html = utf8_encode($html);
      return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
    }
    if ($aktion == "u" ) {   // alle Wetten updaten, d.h. die Werte tipp1 Tipp2 Tipp3 abhängig vom aktuellen Stand neu eintragen
      // zuerst alle Wetten zum Wettbewerb einlesen
      $sql="SELECT * FROM ` tl_hy_wetten` WHERE  Wettbewerb='$Wettbewerb' ORDER By 'Art'";
      $stmt = $this->connection->executeQuery($sql);
      $anz = $stmt->rowCount();
      $debug.="sql: $sql";
      $wetten = array();
      while (($row = $stmt->fetchAssociative()) !== false) {
        $wetten[] = $row;
      }     
      foreach ($wetten as $w) {
        $wettindex=$w['ID'];    // Id aus  tl_hy_wetten 
        $kommentar=$w['Kommentar'];
        $Art=strtolower($w['Art']);
	    if ($Art == 's') {    // Spielausgang Tipp 1 = Spiel
          // Spiel einlesen Tipp1 ist die Spielnummer
          $sql  = "SELECT mannschaft1.ID as 'M1Ind',mannschaft2.ID as 'M2Ind',tl_hy_spiele.T1 as 'T1',tl_hy_spiele.T2 as 'T2'";
          $sql .= " FROM tl_hy_spiele";
          $sql .= " LEFT JOIN tl_hy_mannschaft AS mannschaft1 ON tl_hy_spiele.M1 = mannschaft1.ID";
          $sql .= " LEFT JOIN tl_hy_mannschaft AS mannschaft2 ON tl_hy_spiele.M2 = mannschaft2.ID";
          $sql .= " WHERE tl_hy_spiele.Wettbewerb  ='".$Wettbewerb."' AND tl_hy_spiele.ID=".$w['Tipp1'].";";
          $stmt = $this->connection->executeQuery($sql);
          $num_rows = $stmt->rowCount();    
          $row = $stmt->fetchAssociative();
          $T1=$row['T1'];
          $T2=$row['T2'];
          // Tipp2 = T1 Tipp3 = T2
          $value = "SET Tipp2='$T1',Tipp3='$T2'" ; 
	      $sql = "update  tl_hy_wetten $value where ID=$wettindex";
          $debug.=" sql: $sql\n";	
          $cnt = $this->connection->executeStatement($sql);
          $html.="Wettbewerb $Wettbewerb Spielwette $wettindex Tipp2: $T1 Tipp3: $T2 neu<br>";           
        }
        if ($Art == 'v') {    // Zahl
          // bleibt erhalten, da beim Wetten einrichte vergeben
        }
	    if ($Art == 'p') {    // Mannschaft einlesen 
          // bleibt erhalten, da es keinen aktuellen Wert gibt
        }
	    if ($Art == 'g') {    // Gruppen erster / Zweiter / Dritter
          // gruppe einlesen nach Platz sortiert Tipp1 ist die Gruppe (A,B...Achtel 1..)
          $sql= "SELECT Platz,mannschaft1.Name as 'M1Name' ,mannschaft1.ID as 'M1Ind' FROM  `tl_hy_gruppen`"; 
          $sql.=" LEFT JOIN tl_hy_mannschaft AS mannschaft1 ON tl_hy_gruppen.M1 = mannschaft1.ID"; 
          $sql.=" WHERE tl_hy_gruppen.wettbewerb='".$Wettbewerb."' AND tl_hy_gruppen.Gruppe='".$w['Tipp1']."' ORDER BY Platz";
          $stmt = $this->connection->executeQuery($sql); 
          $Pl=array();
          while (($row = $stmt->fetchAssociative()) !== false) {
            $Pl[] = $row;
          }     
          // Tipp2 = erster Tipp3 = zweiter Tipp4 dritter der Gruppe
            $value = "SET Tipp2='".$Pl[0]['M1Ind']."',Tipp3='".$Pl[1]['M1Ind']."',Tipp4='".$Pl[2]['M1Ind']."'" ; 
	        $sql = "update  tl_hy_wetten $value where ID=$wettindex";
            $debug.=" sql: $sql\n";	
            $cnt = $this->connection->executeStatement($sql);
            $html.="Wettbewerb $Wettbewerb Spielwette $wettindex Tipp2: $T1 Tipp3: $T2 neu<br>";           
        }
      }
      return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
    }
    if ($aktion == "d" ) {   // Wette loeschen
	  $sql = "Delete from  tl_hy_wetten WHERE ID='$id' LIMIT 1";
      $cnt = $this->connection->executeStatement($sql);
      //$html.="in Tabelle  tl_hy_wetten betroffene Saetze $cnt<br>";
	  $html.="Wette $id Nr $Nr gel&ouml;scht";
	  $sql = "Delete from  tl_hy_wetteaktuell WHERE Wette='$id'";
      $cnt = $this->connection->executeStatement($sql);
	  $html.="Wette $id in $cnt Teilnehmern gel&ouml;scht";
      $html = utf8_encode($html);
      return new JsonResponse(['data' => $html,'error'=>$errortxt,'debug'=>$debug]); 
    }
    $html.="fehlerhafte Aktion $aktion Wette bearbeiten<br>";
    $errortxt.="fehlerhafte Aktion $aktion\n";
    $errortxt = utf8_encode($errortxt);
    return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
  } 
    
    /**
     * @throws \Exception
     * @throws DoctrineDBALException
     * @Route("/fussball/bearbeiteteilnehmer/{aktion}/{ID}/{Art}/{Kurzname}/{Name}/{Email}/{Bezahlt}/{Erst}/{Achtel}/{Viertel}/{Halb}/{Finale}", 
     * name="FussballRequestClass::class\bearbeiteteilnehmer", 
     * defaults={"_scope" = "frontend"})
     */

  public function bearbeiteteilnehmer(string $aktion,int $ID=-1,string $Art="",string $Kurzname='',string $Name='',string $Email='',
    string $Bezahlt="0", string $Erst="0", string $Achtel="0", string $Viertel="0", string $Halb="0", string $Finale="0")
  {
    if (!isset($aktion)) {
      $html.="fehlerhafte Aktion empty<br>";
      $errortxt.="fehlerhafte Aktion empty<br>";
      $errortxt = utf8_encode($errortxt);
      return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
    }
    if (!isset($Bezahlt) || $Bezahlt == 'undefined') $Bezahlt = 0;
    if (!isset($Erst)) $Erst = 0;
    if (!isset($Achtel)) $Achtel = 0;
    if (!isset($Viertel)) $Viertel = 0;
    if (!isset($Halb)) $Halb = 0;
    if (!isset($Finale)) $Finale = 0;
    // checkboxwerte prüfen
    if ( ($Erst != "0" && $Erst != 1) ||
         ($Achtel != "0" && $Achtel != 1) ||
         ($Viertel != "0" && $Viertel != 1) ||
         ($Halb != "0" && $Halb != 1) ||
         ($Finale != "0" && $Finale != 1) 
      ) {
      $html.="fehlerhafte Werte bei Bezahlt $Bezahlt, Erst $Erst, Achtel $Achtel, Viertel $Viertel, Halb $Halb, Finale $Finale nur 0 oder 1 zugelassen<br>";
      $errortxt.="fehlerhafte Werte bei Bezahlt $Bezahlt, Erst $Erst, Achtel $Achtel, Viertel $Viertel, Halb $Halb, Finale $Finale nur 0 odeer 1 zugelassen<br><br>";
      $errortxt = utf8_encode($errortxt);
      return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
    } 
    $c=$this->cgiUtil;
    $id = $ID;
    $html="";
    $aktion=strtolower($aktion);
    $debug="aktion: $aktion";
    $errortxt="";
    $Wettbewerb = $this->aktWettbewerb['aktWettbewerb'];
    $debug.=" id: $id, Wettbewerb: $Wettbewerb";
    if ($aktion == "u" || $aktion == "n") { 
      if ($Kurzname=='' || $Kurzname == '-1') {
          $html.="Kurzname fehlerhafter Wert ($Kurzname)<br>";
          $errortxt.="Kurzname fehlerhafter Wert ($Kurzname)<br>";
          $errortxt = utf8_encode($errortxt);
          return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
      }
      if ($aktion == "n" ) {   // neueintrag
        $value = "( '$Wettbewerb' ,'$Kurzname' , '$Name' , '$Email', '$Bezahlt', '$Erst', '$Achtel', '$Viertel', '$Halb', '$Finale' )" ; 
        $sql="INSERT INTO tl_hy_teilnehmer(Wettbewerb,Kurzname,Name,Email,Bezahlt,Erst,Achtel,Viertel,Halb,Finale) VALUES $value";
        $cnt = $this->connection->executeStatement($sql);
	    $html.="Wettbewerb $Wettbewerb Teilnehmer $Kurzname Name $Name Email $Email neu gesetzt";
      // TeilnehmerId besorgen
	    $sql = "Select ID from tl_hy_teilnehmer where Wettbewerb = '$Wettbewerb'  and Kurzname='" . $Kurzname."';";
        $stmt = $this->connection->executeQuery($sql);
        $debug.="sql: $sql<br>";	
        $anz = $stmt->rowCount();
        $row = $stmt->fetchAssociative();
        $tid=$row['ID'];
        $html.="Teilnehmer &uuml;bernommen<br> Wetten einrichten <br>";
	    // Wetten fuer Teilnehmer uebernehmen aus wetten Tabelle
        $sql = "Select ID,Kommentar,Art From  tl_hy_wetten WHERE Wettbewerb='$Wettbewerb'";
        $debug.="Wetten sql: $sql<br>";
        $stmt = $this->connection->executeQuery($sql);
        $anz = $stmt->rowCount();
        $wetten=array();
        while (($row = $stmt->fetchAssociative()) !== false) {
	      $wetten[]=$row;
        }
	    foreach ($wetten as $data) {
          $wettid=$data['ID'];
	      $value = "( '$Wettbewerb' , $tid , $wettid,-1 ,-1,-1 )" ; 
	      $sql="INSERT INTO  tl_hy_wetteaktuell(Wettbewerb,Teilnehmer,Wette,W1,W2,W3) VALUES $value";
          $debug.="Teilnehmerwetten: $sql<br>";
          $cnt = $this->connection->executeStatement($sql);
	    }      
      } elseif ($aktion == 'u') {
        // TeilnehmerId besorgen/pruefen
	    $sql = "Select ID from tl_hy_teilnehmer where Wettbewerb = '$Wettbewerb'  and ID='$id.';";
        $debug.="sql: $sql<br>";	
        $stmt = $this->connection->executeQuery($sql);
        $anz = $stmt->rowCount();
        if ($anz > 0) {
          $row = $stmt->fetchAssociative();
          $tid=$row['ID'];
        } else {
          $html.="Teilnehmer bearbeiten ID nicht vorhanden ($ID)<br>";
          $errortxt.="Teilnehmer bearbeiten ID nicht vorhanden ($ID)<br>";
          $errortxt = utf8_encode($errortxt);
          return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
        }
        $value = "SET Wettbewerb='$Wettbewerb' ,Kurzname='$Kurzname', Name='$Name', Email='$Email'" ; 
        $value .= " ,Bezahlt='$Bezahlt' ,Erst='$Erst', Achtel='$Achtel', Viertel='$Viertel', Halb='$Halb', Finale='$Finale'" ; 
	    $sql = "UPDATE tl_hy_teilnehmer $value where ID='$id'";
        $debug.="UPDATE Teilnehmer sql: $sql<br>";	
        $cnt = $this->connection->executeStatement($sql);
	    $html.="Teilnehmer " . $Kurzname . " ge&auml;ndert";
      }
      $html = utf8_encode($html);
      return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
    }
    if ($aktion == "d" ) {   
      $tid=$id;
	  $sql = "Delete from tl_hy_teilnehmer WHERE ID=$tid LIMIT 1";
      $cnt = $this->connection->executeStatement($sql);
      //$html.="in Tabelle tl_hy_teilnehmer betroffene Saetze $cnt<br>";
	  $html.="Teilnehmer gel&ouml;scht";                     // eigentlich muessen auch noch die Teilnehmerwetten in  tl_hy_wetteaktuell
                                                             // geloescht werden
	  $sql = "Delete from  tl_hy_wetteaktuell where Wettbewerb = '$Wettbewerb' AND Teilnehmer =$tid";
      $cnt = $this->connection->executeStatement($sql);
      $debug.="Teilnehmer $id und in Tabelle  tl_hy_wetteaktuell betroffene Saetze $cnt<br>";
      $html.="Teilnehmer $id und in Tabelle  tl_hy_wetteaktuell betroffene Saetze $cnt<br>";
      $html = utf8_encode($html);
      return new JsonResponse(['data' => $html,'error'=>$errortxt,'debug'=>$debug]); 
    }
    $html.="fehlerhafte Aktion $aktion<br>";
    $errortxt.="fehlerhafte Aktion $aktion<br>";
    $errortxt = utf8_encode($errortxt);
    return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
  } 

    /**
     * @throws \Exception
     * @throws DoctrineDBALException
     * @Route("/fussball/storeteilnehmerwette/{aktion}/{Idwettaktuell}/{W1}/{W2}/{W3}", 
     * name="FussballRequestClass::class\storeteilnehmerwette", 
     * defaults={"_scope" = "frontend"})
     */
     
  public function storeteilnehmerwette(string $aktion,int $Idwettaktuell=-1,string $W1='-1',string $W2='-1',string $W3='-1')
  {
    if (!isset($aktion) || $aktion != 's' || $Idwettaktuell < 0 ) {
      $html.="fehlerhafte Aktion oder ID <br>";
      $errortxt.="fehlerhafte Aktion ID: $Idwettaktuell <br>";
      $errortxt = utf8_encode($errortxt);
      return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
    }
    $c=$this->cgiUtil;
    $id = $Idwettaktuell;
    $html="";
    $aktion=strtolower($aktion);
    $debug="aktion: $aktion";
    $errortxt="";
    $Wettbewerb = $this->aktWettbewerb['aktWettbewerb'];
    $debug.=" id: $id, Wettbewerb: $Wettbewerb, W1: $W1, W2 $W2, W3 $W3";
    if ($aktion == "s" ) {   // update so wie store
        $value = "SET W1='$W1' ,W2='$W2', W3='$W3' " ;
	    $sql = "update  tl_hy_wetteaktuell $value where Wettbewerb='$Wettbewerb' AND ID=$Idwettaktuell;";
        $cnt = $this->connection->executeStatement($sql);
	    $html.="Wetteaktuell $Wettbewerb  tl_hy_wetteaktuell $Idwettaktuell SET W1='$W1' ,W2='$W2', W3='$W3' gesetzt";
      $html = utf8_encode($html);
      return new JsonResponse(['data' => $html,'error'=>$errortxt,'debug'=>$debug]); 
    }
    $html.="fehlerhafte Aktion $aktion<br>";
    $errortxt.="fehlerhafte Aktion $aktion<br>";
    $errortxt = utf8_encode($errortxt);
    return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
  } 
  
    /**
     * @throws \Exception
     *
     * @Route("/fussball/testAjax/{article}", 
     * name="FussballRequestClass::class\testAjax", 
     * defaults={"_scope" = "frontend"})
     */

	public function testAjax(string $article)
	{
      $dir=__DIR__;
      $path=realpath(__DIR__.'../../../');   // ebene src
      $path=realpath(__DIR__.'../../../Resources/contao/phpincludes');
      $res=$this->cgiUtil->Button(array("onClick"=>"uebernehmen();"),"&Uuml;bernehmen","uebernehmen");
//https://api.drupal.org/api/drupal/vendor%21symfony%21http-foundation%21Response.php/8.2.x      // so nicht erzeugt einen 404 Error
      // erzeugt einen 404
      //throw $this->createNotFoundException("endeDatum fehlerhaft (yyyy-mm-tt)<br>");

	  return new JsonResponse(['article' => $article,'data'=>'123','dir'=>__DIR__,'realpath'=>$path,'res'=>$res], Response::HTTP_BAD_REQUEST); 
    } 
   
}