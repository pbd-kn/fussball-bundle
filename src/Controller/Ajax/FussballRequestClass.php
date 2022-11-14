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
        $stmt = $this->connection->executeQuery("SELECT * from hy_config WHERE Name='Wettbewerb' AND Aktuell = 1 LIMIT 1");
        $row = $stmt->fetchAssociative();
        $this->aktWettbewerb['id']=$row['ID'];
        $this->aktWettbewerb['aktuell']=$row['Aktuell'];
        $this->aktWettbewerb['aktWettbewerb']=$row['Value1'];
        $this->aktWettbewerb['aktAnzgruppen']=$row['Value2'];
        $this->aktWettbewerb['aktDGruppe']=$row['Value3'];
        $this->aktWettbewerb['aktStartdatum']=$row['Value4'];
        $this->aktWettbewerb['aktEndedatum']=$row['Value5'];
        
    }
    /**
     * @throws \Exception
     *
     * @Route("/fussball/anzeigewettbewerb/{aktion}/{ID}", 
     * name="FussballRequestClass::class\anzeigewettbewerb", 
     * defaults={"_scope" = "frontend"})
     */

/* erzeugt das Formular und Buttons zur Eingabe eines Wettbewerbs
 * Parameter aktion: auszuf�hrende Aktion wird als Hidden ins Formular �bernommen
 *           ID: Wettbewerb Id (-1 kein Wettbewerb ausgewaehlt)
 *
 * Result html
 * hidden("ID", $ID);                    // zur weitergabe bei �bernehmen
 * hidden("aktion", $aktion);                    // zur weitergabe bei �bernehmen
 * Button Name: uebernehmen Value �bernehmen onClick uebernehmen();
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
        $sql="SELECT * from hy_config where ID='$id' LIMIT 1;";
        $debug .= "sql: $sql  ";	
        $stmt = $this->connection->executeQuery(
            'SELECT * FROM hy_config WHERE ID = ? ',
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
        $Name=$row['Value1'];
        $Gruppen=$row['Value2'];
        $DGruppe=$row['Value3'];
        $StartDatum=$row['Value4'];
        $EndeDatum=$row['Value5'];
        $html.="Wettbewerb �ndern<br>\n";
      } else {
        $html.="Wettbewerb neu eintragen<br>\n";
      }
      $debug.='id: '.$id.' Name: '.$Name.' Gruppen: '.$Gruppen.' DGruppe: '.$DGruppe.' StartDatum: '.$StartDatum.' EndeDatum: '.$EndeDatum;

// create output
      $html.= $c->Button(array("onClick"=>"uebernehmen();"),"&Uuml;bernehmen","uebernehmen") . "\n";
      $html.= $c->Button(array("onClick"=>"abbrechen();"),"Abbrechen","Abbrechen") . "<br>\n";
      $html.= $c->start_form("", null,null,array("id"=>"inputForm"));
      $html.= $c->hidden("ID", $id);                    // zur weitergabe bei �bernehmen
      $html.= $c->hidden("aktion", $aktion);                    // zur weitergabe bei �bernehmen
  
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
      $html.= $c->end_tr() . "\n";;
      $html.= $c->td(array("valign"=>"top"),"Beginn JJJJ-MM-TT");
      $html.= $c->td(array("valign"=>"top"),$c->textfield(array("name"=>"startDatum","value"=>"$StartDatum"))) . "\n";
      $html.= $c->end_tr() . "\n";;
      $html.= $c->td(array("valign"=>"top"),"Ende JJJJ-MM-TT");
      $html.= $c->td(array("valign"=>"top"),$c->textfield(array("name"=>"endeDatum","value"=>"$EndeDatum"))) . "\n";
      $html.= $c->end_tr() . "\n";
      $html.= $c->end_table() . "\n";;
      $html.= $c->end_form();
  
      $html = utf8_encode($html);
	  return new JsonResponse(['dir'=>__DIR__,'data' => $html,'debug'=>$debug]); 
  }
  

/* erzeugt das Formular und Buttons zur Eingabe einer Mannschaft
 * Parameter aktion: auszuf�hrende Aktion wird als Hidden ins Formular �bernommen
 *           ID: Mannschaft Id (-1 keine Mannschaft ausgewaehlt)
 *
 * Result html
 * hidden("ID", $ID);                    // zur weitergabe bei �bernehmen
 * hidden("aktion", $aktion);                    // zur weitergabe bei �bernehmen
 * Button Name: uebernehmen Value �bernehmen onClick uebernehmen();
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
	    $sql = "select * From hy_nation ORDER BY Nation ASC";
      }else {
	    $sql = "select * From hy_nation where Type like '$type' ORDER BY Nation ASC";
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
      $replace = array('�', '�', '�', '�', '�','�','�');
      $res= str_replace($search, $replace, $res);          
      return $res;
    }
    function createGruppenOption ($cgi,$name,$GruppenArray,$selected) {
      $res=$cgi->select($name,$GruppenArray,$selected);
      $search  = array("\xC3\xA4", "\xC3\xB6", "\xC3\xBC", "\xC3\x84", "\xC3\x96","\xC3\x9f");
      $replace = array('�', '�', '�', '�', '�','�','�');
      $res= str_replace($search, $replace, $res);          
      return $res;
    }
    
  
      $c=$this->cgiUtil;
      $fkt=$this->fussballUtil;
      $id=$ID;
      $Name="";
      $Nation="";
      $Flagge="";
      $flgindex=-1;
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
        $sql="SELECT * from hy_mannschaft where ID='$id' LIMIT 1;";
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
        $flgindex=$row['flgindex'];
        $Gruppe=$row['Gruppe'];
        $html.="Mannschaft �ndern<br>\n";
      } else {
        $html.="Mannschaft neu<br>\n";
      }
      $debug.='id: '.$id.' Name: '.$Name.' Nation: '.$Nation.' Flagge: '.$Flagge.' flgindex: '.$flgindex .' Gruppe: '.$Gruppe;

// create output
      $html.=$c->Button(array("onClick"=>"uebernehmen();"),"&Uuml;bernehmen","uebernehmen") . "\n";
      $html.=$c->Button(array("onClick"=>"abbrechen();"),"Abbrechen","Abbrechen") . "<br>\n";
      $html.=$c->start_form("", null,null,array("id"=>"inputForm"));
      $html.=$c->hidden("ID", $id);                    // zur weitergabe bei �bernehmen
      $html.=$c->hidden("aktion", $aktion);                    // zur weitergabe bei �bernehmen
  
      $html.=$c->table (array("border"=>1));
      $html.=$c->tr();
        $html.=$c->td(array("valign"=>"top"),"Mannschaft");
        $html.=$c->td(array("valign"=>"top"),"Name").$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"name","id"=>"name","value"=>"$Name")))."\n";
  //echo td(array("valign"=>"top"),"Nation") . td(array("valign"=>"top"),textfield(array("name"=>"nation","id"=>"nation","value"=>"$Nation"))) . "\n";
        $Type="";         // WM oder EM
        if (strpos(strtolower ($this->aktWettbewerb['aktWettbewerb']),'em')!== false) $Type='%:EU:%';
        $html.=$c->td(array("valign"=>"top"),"Nation").$c->td(array("valign"=>"top"),createNationOption ($this->connection,$c,"nation",$Nation,$Type))."\n";
        //$html.=$c->td(array("valign"=>"top"),"Gruppe").$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"Gruppe","id"=>"name","value"=>"$Gruppe")))."\n";
        $grpArray=$fkt->createGruppenArray($this->aktWettbewerb['aktAnzgruppen']);
        $html.=$c->td(array("valign"=>"top"),"Gruppe");
        $html.=$c->td(array("valign"=>"top"),createGruppenOption ($c,"Gruppe",$grpArray,$Gruppe))."\n";
  
        //$html.=$c->td(array("valign"=>"top"),"Flagge") . "\n";
        //$html.=$c->td(array("valign"=>"top")) . "\n";
      $html.= $c->end_tr()."\n";
      $html.= $c->end_table() . "\n";;
      $html.= $c->end_form();
  
      $html = utf8_encode($html);
	  return new JsonResponse(['data' => $html,'debug'=>$debug]); 
  }
  

/* erzeugt das Formular und Buttons zur Eingabe eines Spielorts
 * Parameter aktion: auszuf�hrende Aktion wird als Hidden ins Formular �bernommen
 *           ID: Mannschaft Id (-1 keine Mannschaft ausgewaehlt)
 *
 * Result html
 * hidden("ID", $ID);                    // zur weitergabe bei �bernehmen
 * hidden("aktion", $aktion);            // zur weitergabe bei �bernehmen
 * Button Name: uebernehmen Value �bernehmen onClick uebernehmen();
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
        $sql="SELECT ID,Ort,Beschreibung from hy_orte where ID='$id' LIMIT 1;";
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
        $html.="Ort �ndern<br>\n";
      } else {
        $html.="Ort neu<br>\n";
      }
      $debug.='id: '.$id.' Ort: '.$ort.' Beschreibung: '.$beschreibung;

// create output
      $html.=$c->Button(array("onClick"=>"uebernehmen();"),"&Uuml;bernehmen","uebernehmen") . "\n";
      $html.=$c->Button(array("onClick"=>"abbrechen();"),"Abbrechen","Abbrechen") . "<br>\n";
      $html.=$c->start_form("", null,null,array("id"=>"inputForm"));
      $html.=$c->hidden("ID", $id);                    // zur weitergabe bei �bernehmen
      $html.=$c->hidden("aktion", $aktion);                    // zur weitergabe bei �bernehmen
  
      $html.=$c->table (array("border"=>1));
      $html.=$c->tr();
        $html.=$c->td(array("valign"=>"top"),"Ort");
        $html.=$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"ort","id"=>"ort","value"=>"$ort")))."\n";
        $html.=$c->td(array("valign"=>"top"),$c->textfield(array("name"=>"beschreibung","id"=>"beschreibung","value"=>"$beschreibung")))."\n";
      $html.= $c->end_tr()."\n";
      $html.= $c->end_table() . "\n";;
      $html.= $c->end_form();
  
      $html = utf8_encode($html);
	  return new JsonResponse(['data' => $html,'debug'=>$debug]); 
  }

/* erzeugt das Formular und Buttons zur Eingabe/Veraenderung einer Gruppe
 * Neueingabe geht nur �ber alle l�schen und neu initialisieren
 * Parameter aktion: auszuf�hrende Aktion wird als Hidden ins Formular �bernommen
 *           ID: Mannschaft Id (-1 keine Mannschaft ausgewaehlt)
 *
 * Result html
 * hidden("ID", $ID);                    // zur weitergabe bei �bernehmen
 * hidden("aktion", $aktion);            // zur weitergabe bei �bernehmen
 * Button Name: uebernehmen Value �bernehmen onClick uebernehmen();
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
     * name="FussballRequestClass::class\anzeigeort", 
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
        $sql="SELECT * from hy_gruppen where ID='$id' LIMIT 1;";
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
        $html.="Gruppe �ndern<br>\n";
      } else {
        $html.="Gruppe neu, Bitte alle Gruppen l�schen und neu aufsetzen<br>\n";
        $html = utf8_encode($html);
	    return new JsonResponse(['data' => $html,'debug'=>$debug]); 
      }
      $debug.='id: '.$id.' Ort: '.$ort.' Beschreibung: '.$beschreibung;

// create output
      $html.=$c->Button(array("onClick"=>"uebernehmen();"),"&Uuml;bernehmen","uebernehmen") . "\n";
      $html.=$c->Button(array("onClick"=>"abbrechen();"),"Abbrechen","Abbrechen") . "<br>\n";
      $html.=$c->start_form("", null,null,array("id"=>"inputForm"));
      $html.=$c->hidden("ID", $id);                    // zur weitergabe bei �bernehmen
      $html.=$c->hidden("aktion", $aktion);                    // zur weitergabe bei �bernehmen
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
 * Parameter aktion: auszuf�hrende Aktion wird als Hidden ins Formular �bernommen
 *           ID: Mannschaft Id (-1 keine Mannschaft ausgewaehlt)
 *
 * Result html
 * hidden("ID", $ID);                    // zur weitergabe bei �bernehmen
 * hidden("aktion", $aktion);            // zur weitergabe bei �bernehmen
 * Button Name: uebernehmen Value �bernehmen onClick uebernehmen();
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
	  $sql = "select ID,Name From hy_mannschaft where Wettbewerb='$Wettbewerb' ORDER BY Name ASC";
      $stmt = $conn->executeQuery($sql);
      $optarray= array();
      while (($row = $stmt->fetchAssociative()) !== false) {
        $optarray[$row['Name']] = $row['ID'];
      }
      $res=$cgi->select($name, $optarray,$selected);
      $search  = array("\xC3\xA4", "\xC3\xB6", "\xC3\xBC", "\xC3\x84", "\xC3\x96","\xC3\x9f");
      $replace = array("�", '�', '�', '�', '�','�','�');
      $res= str_replace($search, $replace, $res);          
      return $res;
    }
    function createOrtOption ($conn,$cgi,$Wettbewerb,$name,$selcted) {
      // selected ist der Index des Ortes
	  $sql = "select ID,Ort From hy_orte where Wettbewerb='$Wettbewerb' ORDER By Ort ASC";
//echo " SQL $sql <br>";	
      $stmt = $conn->executeQuery($sql);
      $optarray= array();
      while (($row = $stmt->fetchAssociative()) !== false) {
//echo "Ort gefunden index " . $row['ID'] . " Ort " . $row['Ort'] . "<br>";
        $optarray[$row['Ort']] = $row['ID'];
      }
      return $cgi->select($name, $optarray,$selcted);
    }
    function createGruppenOption ($cgi,$name,$GruppenArray,$selected) {
      $res=$cgi->select($name,$GruppenArray,$selected);
      $search  = array("\xC3\xA4", "\xC3\xB6", "\xC3\xBC", "\xC3\x84", "\xC3\x96","\xC3\x9f");
      $replace = array('�', '�', '�', '�', '�','�','�');
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
        $sql="SELECT Nr,Gruppe,M1,M2,Ort,Datum,Uhrzeit,T1,T2 from hy_spiele where ID='$id' LIMIT 1;";
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
        $html.="Spiel �ndern<br>\n";
      } else {
        // Spielnummer neu setzen
        $html.="Spiel neu<br>\n";
        $sql="SELECT Nr from hy_spiele where Wettbewerb='$Wettbewerb' ORDER BY NR DESC LIMIT 1;";
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
      $html.=$c->hidden("ID", $id);                    // zur weitergabe bei �bernehmen
      $html.=$c->hidden("aktion", $aktion);                    // zur weitergabe bei �bernehmen

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
      $html.= $c->end_table() . "\n";;
      $html.= $c->end_form();
  
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
        $value .= "Value2='" . $anzahlGruppen ."' ," ; 
        $value .= "Value3='" . $deutschlandGruppe ."' ," ;
        $value .= "Value4='" . $startDatum ."' ," ;
        $value .= "Value5='" . $endeDatum ."' " ;
        $sql = "update hy_config $value where Name='Wettbewerb' AND Value1='$Wettbewerb'";
//echo "sql: $sql<br>";	
        $cnt = $this->connection->executeStatement($sql);
	    $html.="Wettbewerb $Wettbewerb  Anzahl Gruppen $anzahlGruppen Deutschlandgruppe $deutschlandGruppe start $startDatum ende $endeDatum neu gesetzt";
        $html = utf8_encode($html);
        return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
      } elseif ($aktion == "n" ) {   // Neuer Wettbewerb
    // zuerst Pr�fen ob schon vorhanden
        $sql = "select ID,Name,Value1 From hy_config where Name='Wettbewerb' AND Value1='$Wettbewerb'";
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
          $value .= "Aktuell=0" ; 
	      $sql = "update hy_config $value;";
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
	      $sql="INSERT INTO hy_config(Name,Aktuell,Value1,Value2,Value3,Value4,Value5) VALUES $value";
//echo "sql: $sql<br>";
	      //$conn->printerror=true;
          $cnt = $this->connection->executeQuery($sql);
	      $html.="Wettbewerb neu eingetragen $w &uuml;bernommen";
          $html = utf8_encode($html);
          return new JsonResponse(['dir'=>__DIR__,'data' => $html,'debug'=>$debug]); 
        }
      } elseif ($aktion == "d" ) {   // Wettbewerb loeschen
        $sql = "select * From hy_config where Name='Wettbewerb' AND ID='$id'";
//echo "sql: $sql<br>";	
        $stmt = $this->connection->executeQuery($sql);
        $row = $stmt->fetchAssociative();  // s. Doctrine\DBAL
        $wb=$row['Value1']; 
        //echo "noch d (loeschen) nicht realisiert ID $id Wettbewerb $wb<br>";
        $tbs = array("hy_wetten","hy_wetteaktuell","hy_teilnehmer","hy_spiele","hy_orte","hy_mannschaft","hy_gruppen","hy_flagge","hy_config");
        foreach ($tbs as $k=>$tab) {
          $sql="DELETE FROM $tab WHERE wettbewerb ='$wb';";
//echo "sql: $sql<br>";	
          $cnt = $this->connection->executeStatement($sql);
          //$cnt=$conn->affected();
          $html.="in Tabelle $tab betroffene Saetze $cnt<br>";
        }
        $sql="DELETE FROM hy_config WHERE Value1 ='$wb';";
//echo "sql: $sql<br>";	
        $cnt = $this->connection->executeStatement($sql);
        //$cnt=$conn->affected();
        $html.="in Tabelle hy_config betroffene Saetze $cnt<br>";
        $html.="<br><strong>!! Achtung evtl neuen aktuellen Wettbewerb w�hlen !! </strong><br><br>";
        $html = utf8_encode($html);
        return new JsonResponse(['dir'=>__DIR__,'data' => $html,'debug'=>$debug]); 
      } elseif ($aktion == "a" ) {   // setze akt Wettbewerb
      // selektierter Wettbewerb
        $sql = "select * From hy_config where Name='Wettbewerb' AND ID='$id'";
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
        $value .= "Aktuell=0" ; 
	    $sql = "update hy_config $value;";
//echo "sql: $sql<br>";	
        $cnt = $this->connection->executeStatement($sql);
        $value = "SET ";
        $value .= "Aktuell=1" ; 
	    $sql = "update hy_config $value where Name='Wettbewerb' AND ID='$id';";
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
      $sql = "select * from hy_nation $value where Nation='$nation'";
      $stmt = $this->connection->executeQuery($sql);
      $debug.="sql: $sql<br>";	
      $anz = $stmt->rowCount();
      $fl="";
      $flid="";
      if ($anz > 0) {
        $rownati = $stmt->fetchAssociative();
	    $fl =   $rownati['Image'];
	    $flid = $rownati['ID'];
      } else {         // default Deutschland
        $sql = "select * from hy_nation $value where Nation='Deutschland'";
        $stmt = $this->connection->executeQuery($sql);
        $rownati = $stmt->fetchAssociative();
	    $fl =   $rownati['Image'];
	    $flid = $rownati['ID'];
      }
      if ($aktion == "n" ) {   // neueintrag
        $sql="SELECT name FROM hy_mannschaft WHERE name='$name' AND Wettbewerb ='$Wettbewerb'"; 
        $stmt = $this->connection->executeQuery($sql);
        $debug.="sql: $sql<br>";	
        $anz = $stmt->rowCount();
        if ($anz > 0) {
          $errortxt.="Mannschaft $name existiert bereits";
          $errortxt = utf8_encode($errortxt);
          return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
        }
        $sql="INSERT INTO hy_mannschaft(Wettbewerb,Name,Nation,Flagge,flgindex,Gruppe) VALUES ('$Wettbewerb','$name','$nation','$fl',$flid,'$Gruppe');";
        $debug.="sql: $sql<br>";	
        $cnt = $this->connection->executeStatement($sql);
	    $html.="Wettbewerb $Wettbewerb  Mannschaft $name Nation $nation Flagge $fl flgindex $flid neu gesetzt";
        $html = utf8_encode($html);
        return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
      }
      if ($aktion == "u" ) {   // Mannschaft uebernehmen

        $value = "SET ";
        $value .= "Name='" . $name ."' ," ; 
        $value .= "Nation='" . $nation ."' ," ;
        $value .= "Flagge='" . $fl ."' ," ;
        $value .= "Gruppe='" . $Gruppe ."' ," ;
        $value .= "flgIndex='" . $flid ."' " ;

	    $sql = "update hy_mannschaft $value where ID='$id'";
//echo "sql: $sql<br>";	
        $cnt = $this->connection->executeStatement($sql);
	    $html.="Wettbewerb $Wettbewerb  Mannschaft $name Nation $nation Flagge $fl flgindex $flid bearbeitet";
        $html = utf8_encode($html);

        return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
      }
    }
    if ($aktion == "d" ) {   // Mannschaft loeschen
	  $sql = "Delete from hy_mannschaft WHERE ID='$id' LIMIT 1";
      $cnt = $this->connection->executeStatement($sql);
      //$html.="in Tabelle hy_mannschaft betroffene Saetze $cnt<br>";
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
	    $sql="INSERT INTO hy_orte(Wettbewerb,Ort,Beschreibung) VALUES $value";
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

	    $sql = "update hy_orte $value where ID='$id'";
//echo "sql: $sql<br>";	
        $cnt = $this->connection->executeStatement($sql);
	    $html.="Wettbewerb $Wettbewerb Ort $ort Beschreibung $beschreibung bearbeitet";
        $html = utf8_encode($html);
        return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
      }
    }
    if ($aktion == "d" ) {   // Mannschaft loeschen
	  $sql = "Delete from hy_orte WHERE ID='$id' LIMIT 1";
      $cnt = $this->connection->executeStatement($sql);
      //$html.="in Tabelle hy_mannschaft betroffene Saetze $cnt<br>";
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
      $replace = array('�', '�', '�', '�', '�','�','�');
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
        $html.="unzul�ssige ID $ID<br>";
        $html = utf8_encode($html);
        $errortxt.="unzul�ssige ID $ID<br><br>";
        $errortxt = utf8_encode($errortxt);
        return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
      }
      $value = "SET Platz=$Platz" ;
  	  $sql = "update hy_gruppen $value where ID='$id'";
      $debug.=" sql: $sql\n";	
      $cnt = $this->connection->executeStatement($sql);
	  $html.="Wettbewerb $Wettbewerb GrupenId $id Platz $Platz";
      $html=replace16Bit($html);
      $html = utf8_encode($html);
      return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
    } 
    if ($aktion == "a") {              // alle Gruppeneintraege l�schen und aus den Spielen neu aufbauen
       $sql="SELECT * FROM hy_gruppen WHERE Wettbewerb='$Wettbewerb'";
       $stmt = $this->connection->executeQuery($sql);
       $Update=0;
       $ExistGruppen=[];
       $num_rows = $stmt->rowCount();
       if ($num_rows > 0) {
         $Update=1;
         while (($row = $stmt->fetchAssociative()) !== false) {
          $ExistGruppen[$row['Gruppe']][$row['M1']]=$row;                 // index Gruppe und Mannschaft
         }
       }    
       //$cnt = $this->connection->executeStatement($sql);
	   $debug.="sql: $sql Anzahl vorhandener Gruppen $num_rows: $num_rows Update |$Update|\n";
       $Mannschaften=[] ;
        // alle aktuellen Mannschaften einlesen
       $sql="SELECT * FROM hy_mannschaft WHERE Wettbewerb='".$this->aktWettbewerb['aktWettbewerb']."' ORDER BY ID"; 
       $stmt = $this->connection->executeQuery($sql);
       $num_rows = $stmt->rowCount();    
       while (($row = $stmt->fetchAssociative()) !== false) {
         $Mannschaften[]=$row;
       }
       // alle aktuellen Spiele einlesen
       foreach ($Mannschaften as $k=>$row) {
         $M1=$row['ID'];$Platz=-1;$Spiele=-1;$Sieg=-1;$Unentschieden=-1;$Niederlage=-1;$Tore=-1;$Gegentore=-1;$Differenz=-1;$Punkte=-1;
         $Gruppe=$row['Gruppe'];    
         $sql  = "SELECT ID,Nr,M1,M2,T1,T2 FROM hy_spiele WHERE Wettbewerb  ='$Wettbewerb' AND M1 = $M1";  // Heim Spiele
         $stmt = $this->connection->executeQuery($sql); $num_rows = $stmt->rowCount();    
         $debug.="|spiele sql: $sql anz: $num_rows | ";	
         while (($spielrow = $stmt->fetchAssociative()) !== false) {
           $debug.="| heimspiel T1 ".$spielrow['T1']."  heimspiel T2 ".$spielrow['T2']." | ";	
           if ($spielrow['T1'] != -1 && $spielrow['T2'] != -1) {   
             if ($Spiele < 0) $Spiele=0; $Spiele ++;            // Spiel hat stattgefunden
             if ($Tore < 0) $Tore=0; $Tore = $Tore+$spielrow['T1'];
             if ($Gegentore < 0) $Gegentore=0; $Gegentore = $Gegentore+$spielrow['T2'];
             if ( $spielrow['T1'] >  $spielrow['T2']) { if ($Sieg < 0) $Sieg=0; $Sieg ++; if ($Punkte < 0) $Punkte=0; $Punkte=$Punkte+3;}
             if ( $spielrow['T1'] <  $spielrow['T2']) { if ($Niederlage < 0) $Niederlage=0; $Niederlage ++;}
             if ( $spielrow['T1'] == $spielrow['T2']){ if ($Unentschieden < 0) $Unentschieden=0; $Unentschieden ++; if ($Punkte < 0) $Punkte=0; $Punkte=$Punkte+1;}
           }
         }
         $sql  = "SELECT ID,Nr,M1,M2,T1,T2 FROM hy_spiele WHERE Wettbewerb  ='$Wettbewerb' AND M2 = $M1";  // Auswaerts Spiele
         $stmt = $this->connection->executeQuery($sql); $num_rows = $stmt->rowCount();    
         while (($spielrow = $stmt->fetchAssociative()) !== false) {
           $debug.="| auswaerts T1 ".$spielrow['T1']."  auswaerts T2 ".$spielrow['T2']." | ";	
           if ($spielrow['T1'] != -1 && $spielrow['T2'] != -1) {   
             if ($Spiele < 0) $Spiele=0; $Spiele ++;        // Spiel hat stattgefunden
             if ($Tore < 0) $Tore=0; $Tore = $Tore+$spielrow['T2'];
             if ($Gegentore < 0) $Gegentore=0; $Gegentore = $Gegentore+$spielrow['T1'];
             if ( $spielrow['T2'] >  $spielrow['T1']) { if ($Sieg < 0) $Sieg=0; $Sieg ++; if ($Punkte < 0) $Punkte=0; $Punkte=$Punkte+3;}
             if ( $spielrow['T2'] <  $spielrow['T1']) { if ($Niederlage < 0) $Niederlage=0; $Niederlage ++;}
             if ( $spielrow['T1'] == $spielrow['T2']) { if ($Unentschieden < 0) $Unentschieden=0; $Unentschieden ++; if ($Punkte < 0) $Punkte=0; $Punkte=$Punkte+1;}
           }
         }
         if ($Tore != -1) { if ($Differenz < 0) $Differenz=0;$Differenz=$Tore-$Gegentore;}
         if ($Update == 1) {
           $oldRow=$ExistGruppen[$row['Gruppe']][$row['ID']];        // ID = MannschaftsID Row der Gruppe
           $Groupid = $oldRow['ID'];
           $value = "SET ";
//           $value .= "Platz=$Platz ," ;                    // Platz bleibt erhalten muss derzeit von Hand eingegeben werden
           $value .= "Spiele=$Spiele ," ;
           $value .= "Sieg=$Sieg ," ;
           $value .= "Unentschieden=$Unentschieden ," ;
           $value .= "Niederlage=$Niederlage ," ;
           $value .= "Tore=$Tore ," ;
           $value .= "Gegentore=$Gegentore ," ;
           $value .= "Differenz=$Differenz ," ;
           $value .= "Punkte=$Punkte " ;
  	       $sql = "UPDATE hy_gruppen $value  where ID='$Groupid'";
         
           $debug.="'|' update sql: $sql '|' ";	
           $cnt = $this->connection->executeStatement($sql);
	       //$html.="Update  Gruppe $Gruppe M1 ".$M1." cnt ".$cnt;
         } else {
           $value = "( '$Wettbewerb','$Gruppe',$M1,$Platz,$Spiele,$Sieg,$Unentschieden,$Niederlage,$Tore,$Gegentore,$Differenz,$Punkte)" ;
           $sql="INSERT INTO hy_gruppen(Wettbewerb,Gruppe,M1,Platz,Spiele,Sieg,Unentschieden,Niederlage,Tore,Gegentore,Differenz,Punkte) VALUES $value";
           $debug.="'|' insert sql: $sql '|' ";	
         //$cnt = $this->connection->executeStatement($sql);
	       //$html.="Eingef�gt  Gruppe $Gruppe M1 $M1 ";
         }
       }
       $debug.="!!!!!!!!!!!!!Platz nicht bestimmt !!!!!!!!!!!!!!!!!!!!!\n";
       $html=replace16Bit($html);
       $html = utf8_encode($html); 
       return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]);  
       // Versuch der Platzbestimmung
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
    // zuerst Pr�fen ob schon vorhanden
        $sql = "select Nr From hy_spiele where Wettbewerb='$Wettbewerb' AND Nr='$Nr'";
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
	    $sql="INSERT INTO hy_spiele(Wettbewerb,Nr,Gruppe,M1,M2,Ort,Datum,Uhrzeit,T1,T2) VALUES $value";
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
	    $sql = "update hy_spiele $value where ID=$id";
$debug.=" sql: $sql\n";	
    //return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
        $cnt = $this->connection->executeStatement($sql);

	    $html.="Wettbewerb $Wettbewerb Spielnummer $Nr Gruppe $Gruppe M1 $M1 M2 $M2  Ort $Ort Datum $Datum Uhrzeit $Uhrzeit T1 $T1 T2 $T2 bearbeitet";
        $html = utf8_encode($html);
        return new JsonResponse(['data' => $html,'error'=>$errortxt, 'debug'=>$debug]); 
      }
    }
    if ($aktion == "d" ) {   // Mannschaft loeschen
	  $sql = "Delete from hy_spiele WHERE ID='$id' LIMIT 1";
      $cnt = $this->connection->executeStatement($sql);
      //$html.="in Tabelle hy_mannschaft betroffene Saetze $cnt<br>";
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