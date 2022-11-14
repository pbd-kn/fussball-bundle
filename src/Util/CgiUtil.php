<?php

declare(strict_types=1);


namespace PBDKN\FussballBundle\Util;

class CgiUtil
{
    public function __construct()
    {
    }
    /**
     * @throws \Exception
     */
  public function html(){
    $str = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html>';
    return("$str");
  }
  public function end_html() { return("</body>\n</html>"); }
  public function _header($msg=null){
        $str = "<head>";
        if($msg == null) {
          $str = "<title> cgi.php </title>";
        } else {
          $str .= $msg;
        }
        $str .= "</head>\n";
        return($str);
  }
  public function start_html($hatts=null,$batts=null){
    return(html()."\n"._header($hatts)."\n".body($batts));
  }
  public function body($atts=null){
        $str="";
        if(@strstr($atts,"/")){ $str = "\n</body>\n";
        } else{
                $str .= "<body";
                if(is_array($atts)) {
                  foreach ($atts as  $key=>$val) { $str .= " $key=\"$val\"";}
                }
                $str .= ">\n";
        }
        return($str);
  }
  public function h1($atts=null, $stuff=null) { return(h(1, $atts, $stuff)); }
  public function h2($atts=null, $stuff=null) { return(h(2, $atts, $stuff)); }
  public function h3($atts=null, $stuff=null) { return(h(3, $atts, $stuff)); }
  public function h4($atts=null, $stuff=null) { return(h(4, $atts, $stuff)); }
  public function h5($atts=null, $stuff=null) { return(h(5, $atts, $stuff)); }
  public function h6($atts=null, $stuff=null) { return(h(6, $atts, $stuff)); }
  public function h($n, $atts=null, $stuff=null){
        $str = "<h$n";
        if(is_array($atts)){
                foreach ($atts as  $key=>$val){ $str .= " $key=\"$val\"";}
                $str .=">";
                if(is_string($stuff)) $str .= "$stuff</h".$n.">\n";
                return ($str);
        } elseif(is_string($atts)) {
            $str .= ">$atts</h".$n.">\n";
            return ($str);
        }
        $str .= ">\n";
        return ($str);
  }
  public function a($atts, $label) {
    $str = "<a ";
    if(is_array($atts)){
      foreach ($atts as  $key=>$val) { $str .= " $key=\"$val\"";}
  }
    $str .= ">".$label."</a>\n";
    return($str);
  }

  public function br() { return("<br>\n"); }

//hr needs to take optional params, somdeday.
  public function hr() { return("<hr/>\n"); }

  public function center($stuff=null) {
    $str = "<center>";
    if($stuff) { $str .= "$stuff</center>\n"; }
    return ($str);
  }

  public function end_center(){ return("</center>\n"); }

  public function label($label){ return(" &nbsp; $label &nbsp; "); }

  public function b($atts=null, $stuff=null) {
        $str = "<b";
        if(is_array($atts)){
                foreach ($atts as  $key=>$val) { $str .= " $key=\"$val\"";}
                $str .= ">";
                if(is_string($stuff)) $str .= "$stuff</b>";
                return ($str);
        } elseif(is_string($atts)){
                $str .= ">".$atts."</b>";
                return ($str);
        }
        $str .= ">";
        return ($str);
  }
  public function p($atts=null, $stuff=null){
        $str = "<p";
        if(is_array($atts)) {
                foreach ($atts as  $key=>$val){ $str .= " $key=\"$val\""; }
                $str .=">";
                if(is_string($stuff)) $str .= "$stuff</p>";
                return ($str);
        } elseif(is_string($atts)) {
                $str .= ">$atts</p>";
                return ($str);
        }
        $str .= ">";
        return($str);
  }
  public function font($atts, $stuff){
        $str = "<font";
        // better have at least one font attribute
        if(is_array($atts)) {
                foreach ($atts as  $key=>$val) { $str .= " $key=\"$val\""; }
                $str .= ">";
                if(is_string($stuff)) $str .= "$stuff</font>\n";
                return ($str);
        } elseif(is_string($atts)) {
                $str .= "> $atts </font>";
                return ($str);
        }
        $str .= ">\n";
        return($str);
}

  public function table($atts=null, $stuff=null){
        $str = "\n<table ";
        if(is_array($atts)) {
                foreach ($atts as  $key=>$val) { $str .= " $key=\"$val\""; }
                $str .= ">";
                if(is_string($stuff)) $str .= "$stuff </table>\n";
                return ($str);
        } elseif(is_string($atts)) {
                $str .= "> $atts </table>";
                return ($str);
        }
        $str .= ">\n";
        return($str);
}

  public function thead() { return "<thead>";}
  public function end_thead() { return "</thead>"; }
  public function tbody() { return "<tbody>";}
  public function end_tbody() { return "</tbody>"; }

  public function tr($atts=null, $stuff=null) {
        $str = "\n<tr";
        if(is_array($atts)) {
                foreach ($atts as  $key=>$val){ $str .= " $key='" . $val . "'";}
                $str.=">";
                if(is_string($stuff)) $str .= "$stuff</tr>\n";
                return ($str);
        } elseif(is_string($atts)) { // if we sent a string
                $str .= ">$atts</tr>\n";
                return ($str);
        }
        $str.=">";
        return ($str);
  }
  public function td($atts=null, $stuff=null) {
    $str = "<td";
    if(is_array($atts)) {                        // attribute zu td
      foreach ($atts as  $key=>$val) { $str .= " $key=\"$val\""; }
    $str .= ">";
    if(is_string($stuff)) $str .= "$stuff</td>";
    return ($str);
  } elseif(is_string($atts)) {
      $str .= ">$atts</td>";
      return $str;
  }
  $str .= ">";
  return($str);
}
  public function end_tr() { return("</tr>\n"); }
  public function end_td() { return("</td>"); }
  public function end_th() { return("</th>"); }
  public function end_form() { return("</form>"); }
  public function end_table() { return("</table>"); }
  public function th($atts=null, $stuff=null){
        $str = "<th";
        if(is_array($atts)) { 
          foreach ($atts as  $key=>$val) {$str .= " $key=\"$val\"";}
          $str .= ">";
          if(is_string($stuff)) $str .= "$stuff</th>";
          return ($str);
        } elseif(is_string($atts)) {
                $str .= ">$atts</th>";
                return ($str);
        }
        $str .= ">";
        return($str);
  }
  public function start_form($action, $method=null, $target=null,$atts=null){
        $str =  "<form action='$action' ";
        if($target != null) $str .= " target='$target' ";
        if($method != null) $str .= " METHOD='$method'";
        else                $str .= " METHOD='POST'";
        if(is_array($atts)) { foreach ($atts as  $key=>$val) $str .= " $key='$val'"; }
        $str .= ">\n";
        return($str);
  }

  public function hidden($name, $value){
        $str = "<input type=\"hidden\" name=\"" . $name."\" id=\"" . $name."\" value=\"" .$value."\">";
        return($str);
  }

  public function textfield($atts){
    $str = "<input type='text' ";
    foreach ($atts as  $key=>$val){ $str .= " $key='$val'"; }
    $str .= "></input>";
    return($str);
}

  public function radioButton($atts,$value){
    $str = "<input type='radio' ";
    foreach ($atts as  $key=>$val) {
      if (strtolower($key) == "checked") {            // Sonderbehandlung für checked
        if ($val != "") {
          $str .= "checked";
        }
      } else {
        $str .= " $key='$val'";
      }
    }
    $str .= ">";
    if($value != null) {
      $str .= $value;
    }
    $str .= "</input>";
    return($str);
  }

  public function textarea($atts=null, $value=null){
     $str = "<textarea ";
     if(is_array($atts)){
        foreach ($atts as  $key=>$val) $str .= " $key=\"$val\"";
        $str .= ">";
     } elseif(is_string($atts)) {
       $str .= ">$atts";
     }
     if($value != null){
       $str .= "$value";
     }
     $str .= "</textarea>";
     return($str);
}

// input is not a hashed array
  public function select($name, $options=null,$sel=null){
        $str = "<select name=\"" . $name . "\" id=\"" . $name . "\" >\n";
        if(is_array($options)) {
                $cnt = count($options);
                $ss="";
                if (!empty($sel)) $ss=(string) $sel;
                $ss=strtolower($ss);
                foreach ($options as  $key=>$val) {
                        $str .= "<option value='" . $val . "'";
                        if (strtolower((string)$val) == $ss) $str .= " selected";
                        if (is_numeric($key)) { $str .= " > " . $val . "</option>\n";
                        } else { $str .= " > " . $key . "</option>\n"; }
                }
        }
        $str .= "</select>\n";
        return($str);
  }



  public function submit($value=null,$name=null){
        if($value == null)$value="submit";
        if($name == null) $name=$value;
        $str = "<input type='submit' name='$name' value='$value'>\n";
        return($str);
  }

  public function resetButton($value=null,$name=null){
        if($value == null)$value="Reset";
        if($name == null) $name=$value;
        $str = "<input type='submit' name='$name' value='$value'>\n";
        return($str);
 }
  public function Button($atts=null,$value=null,$name=null){
        if($value == null)$value="Reset";
        if($name == null) $name=$value;
        if (is_array($atts)) {
          $str = "<input type='button' name='$name' value='$value' ";
          foreach ($atts as  $key=>$val) $str .= " $key=\"$val\"";
          $str .= ">\n";
        } else {
          $str = "<input type='button' name='$name' value='$value'>\n";
        }
        return($str);
  }
  public function space($cnt){
    for($i=0; $i<$cnt; $i++) $str .= " &nbsp; ";
    return($str);
  }
 
  public function div($atts=null, $stuff=null){
        $str = "\n<div";
        if(is_array($atts)) {
                foreach ($atts as  $key=>$val) { $str .= " $key='" . $val . "'"; }
                $str .=">";                // div abschliessen
                if(is_string($stuff)) $str .= "$stuff</div>\n";
                return ($str);
        } elseif(is_string($atts)) { // if we sent a string
                $str .= ">$atts</div>\n";  // div abschliessen ende div
                return ($str);
        }
        $str .= ">";
        return ($str);
  }
  public function end_div() { return("</div>\n"); }
}
 
 