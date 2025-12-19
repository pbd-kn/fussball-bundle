<?php
declare(strict_types=1);


namespace PBDKN\FussballBundle\Util;

class FussballUtil
{
    public function __construct()
    {
    }
    /**
     * @throws \Exception
     */

/*
    public function setWettbewerb () {
      global $Wettbewerb, $AnzahlGruppen, $Deutschlandgruppe,$startDatum,$endeDatum;
      global   $host,$user, $password, $database;
      $conn = new mysql_dialog();
      $conn->connect($host,$user, $password, $database);
//  $conn->execute ("SELECT * from tl_hy_config WHERE Name='aktWettbewerb'");
//  $row = $conn->listen();
//  $w = $row["Value1"];
      $conn->execute ("SELECT * from tl_hy_config WHERE Name='Wettbewerb' AND aktuell = 1 LIMIT 1");
      $a = $conn->listen(); 
      $Wettbewerb=$a["value1"];
      $AnzahlGruppen=$a["value2"];
      $Deutschlandgruppe=$a["value3"];
      $startDatum=$a["value4"];
      $endeDatum=$a["value5"];
      $conn->close();
      return;
    }
*/
/*
    public function getConfigValue($Name) {
      global   $host,$user, $password, $database;
      $res=array();
      $conn = new mysql_dialog();
      $conn->connect($host,$user, $password, $database);
      $sql="SELECT * from tl_hy_config WHERE Name='$Name'"; 
      $conn->execute ("SELECT * from tl_hy_config WHERE Name='$Name'");
      $row = $conn->listen();
      $res["value1"]=$row["value1"];
      $res["value2"]=$row["value2"];
      $res["value3"]=$row["value3"];
      $res["value4"]=$row["value4"];
      $res["value5"]=$row["value5"];
      $conn->close();
      return $res;
    }
*/
    public function getDatum($aktWettbewerb,$type) {
      //global $Wettbewerb, $AnzahlGruppen, $Deutschlandgruppe,$startDatum,$endeDatum;
      $arrMon=["","Jan.","Feb.","Mar.","Apr.","Mai","Jun.","Jul.","Aug.","Sep.","Okt.","Nov.","Dez."];
      $Datum="";
  
      $type =strtolower(trim($type)); 
      if ($type == 'start') {
        $Datum=$aktWettbewerb['aktStartdatum'];
      } elseif ($type == 'ende') {
        $Datum=$aktWettbewerb['aktEndedatum'];
      } else {
        $Datum="yyyy-mm-tt";   
      }
      $arrdate=explode("-",(string)$Datum);
      if (count($arrdate) != 3) {
        $res="$type: Datum |$Datum| fehlerhaft (yyyy-mm-tt)";
      } else {
        $mon=(int)$arrdate[1];
        if (($mon >0)&&($mon<13)) {
          $res=$arrdate[2].'.'.$arrMon[$mon].$arrdate[0];
        } else {
          $res="Monat fehlerhaft. $Datum";
        } 
      }
      return $res;
    }
    public function createGruppenArray ($AnzahlGruppen) {
        $gruppenNamen=array("A","B","C","D","E","F","G","H","I","J","K","L","M","N");
        $optarray= array();
        for ($i = 0; $i < $AnzahlGruppen; $i++) {
          $optarray[] = $gruppenNamen[$i];
        }
        $optarray[]="Achtel 1";
        $optarray[]="Achtel 2";
        $optarray[]="Achtel 3";
        $optarray[]="Achtel 4";
        $optarray[]="Achtel 5";
        $optarray[]="Achtel 6";
        $optarray[]="Achtel 7";
        $optarray[]="Achtel 8";
        $optarray[]="Viertel 1";
        $optarray[]="Viertel 2";
        $optarray[]="Viertel 3";
        $optarray[]="Viertel 4";
        $optarray[]="Halb 1";
        $optarray[]="Halb 2";
        $optarray[]="Platz3";
        $optarray[]="Finale";
        $optarray[]="Sechzehn 1";
        $optarray[]="Sechzehn 2";
        $optarray[]="Sechzehn 3";
        $optarray[]="Sechzehn 4";
        $optarray[]="Sechzehn 5";
        $optarray[]="Sechzehn 6";
        $optarray[]="Sechzehn 7";
        $optarray[]="Sechzehn 8";
        $optarray[]="Sechzehn 9";
        $optarray[]="Sechzehn 10";
        $optarray[]="Sechzehn 11";
        $optarray[]="Sechzehn 12";
        $optarray[]="Sechzehn 13";
        $optarray[]="Sechzehn 14";
        $optarray[]="Sechzehn 15";
        $optarray[]="Sechzehn 16";
        return $optarray;
      }
      public function getImagePath($image) {
        if (empty($image)) {
            return '';
        }
        return 'bundles/fussball/assets/flaggen/'.$image;
        //return 'files/hoyzer-wetten/content/flaggen/'.$image;
        
      }
}

?>