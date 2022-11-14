<?php



$PHP_SELF = $_SERVER['PHP_SELF'];




function html()

{

  $str = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html>';

  return("$str");

}

function end_html() { return("</body>\n</html>"); }



function _header($msg=null)

{

        global $PHP_SELF;

        $str = "<head>";

        if($msg == null) {

          $str = "<title> cgi.php </title>";

        } else {

          $str .= $msg;

        }

        $str .= "</head>\n";

        return($str);

}



function start_html($hatts=null,$batts=null)

{

  return(html()."\n"._header($hatts)."\n".body($batts));

}



function body($atts=null)

{

        $str="";

        if(@strstr($atts,"/"))

        {

                $str = "\n</body>\n";

        }

        else

        {

                $str .= "<body";

                if(is_array($atts))

                {

                        foreach ($atts as  $key=>$val)

                        {

                                $str .= " $key=\"$val\"";

                        }

                }

                $str .= ">\n";

        }

        return($str);

}







function h1($atts=null, $stuff=null) { return(h(1, $atts, $stuff)); }

function h2($atts=null, $stuff=null) { return(h(2, $atts, $stuff)); }

function h3($atts=null, $stuff=null) { return(h(3, $atts, $stuff)); }

function h4($atts=null, $stuff=null) { return(h(4, $atts, $stuff)); }

function h5($atts=null, $stuff=null) { return(h(5, $atts, $stuff)); }

function h6($atts=null, $stuff=null) { return(h(6, $atts, $stuff)); }



function h($n, $atts=null, $stuff=null)

{
        $str = "<h$n";
        if(is_array($atts)){
                foreach ($atts as  $key=>$val){
                        $str .= " $key=\"$val\"";
                }
                $str .=">";
                if(is_string($stuff)) $str .= "$stuff</h".$n.">\n";
                return ($str);
        }
        elseif(is_string($atts)) {
            $str .= ">$atts</h".$n.">\n";
            return ($str);
        }
        $str .= ">\n";
        return ($str);
}

function a($atts, $label) {
  $str = "<a ";
  if(is_array($atts)){
    foreach ($atts as  $key=>$val) {
      $str .= " $key=\"$val\"";
    }
  }
  $str .= ">".$label."</a>\n";
  return($str);
}

function br() { return("<br>\n"); }

//hr needs to take optional params, somdeday.
function hr() { return("<hr/>\n"); }

function center($stuff=null) {
  $str = "<center>";
  if($stuff) {
    $str .= "$stuff</center>\n";
  }
  return ($str);
}

function end_center(){ return("</center>\n"); }

function label($label)
{
        return(" &nbsp; $label &nbsp; ");
}

function b($atts=null, $stuff=null) {
        $str = "<b";
        if(is_array($atts)){
                foreach ($atts as  $key=>$val) {
                        $str .= " $key=\"$val\"";
                }
                $str .= ">";
                if(is_string($stuff)) $str .= "$stuff</b>";
                return ($str);
        }
        elseif(is_string($atts))
        {
                $str .= ">".$atts."</b>";
                return ($str);
        }
        $str .= ">";
        return ($str);
}
function p($atts=null, $stuff=null){
        $str = "<p";
        if(is_array($atts)) {
                foreach ($atts as  $key=>$val){
                        $str .= " $key=\"$val\"";
                }
                $str .=">";
                if(is_string($stuff)) $str .= "$stuff</p>";
                return ($str);
        }
        elseif(is_string($atts)) {
                $str .= ">$atts</p>";
                return ($str);
        }

        $str .= ">";
        return($str);
}

function font($atts, $stuff){
        $str = "<font";
        // better have at least one font attribute
        if(is_array($atts)) {
                foreach ($atts as  $key=>$val) {
                        $str .= " $key=\"$val\"";
                }
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

function table($atts=null, $stuff=null){
        $str = "\n<table ";
        if(is_array($atts)) {
                foreach ($atts as  $key=>$val) {
                        $str .= " $key=\"$val\"";
                }
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

function thead() {
  $str="<thead>";
  return $str;
}
function end_thead() {
  $str="</thead>";
  return $str;
}
function tbody() {
  $str="<tbody>";
  return $str;
}
function end_tbody() {
  $str="</tbody>";
  return $str;
}

function tr($atts=null, $stuff=null) {
        $str = "\n<tr";
        if(is_array($atts)) {
                foreach ($atts as  $key=>$val){
                        $str .= " $key='" . $val . "'";
                }
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

function td($atts=null, $stuff=null) {
  $str = "<td";
  if(is_array($atts)) {                        // attribute zu td
    foreach ($atts as  $key=>$val) {
      $str .= " $key=\"$val\"";
    }
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

function end_tr() { return("</tr>\n"); }
function end_td() { return("</td>"); }

function end_th() { return("</th>"); }

function end_form() { return("</form>"); }

function end_table() { return("</table>"); }

function th($atts=null, $stuff=null){
        $str = "<th";
        if(is_array($atts)) {
                foreach ($atts as  $key=>$val) {
                        $str .= " $key=\"$val\"";
                }
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

function start_form($action, $method=null, $target=null,$atts=null){
        $str =  "<form action='$action' ";
        if($target != null) $str .= " target='$target' ";
        if($method != null) $str .= " METHOD='$method'";
        else                $str .= " METHOD='POST'";
        if(is_array($atts)) {
				  foreach ($atts as  $key=>$val) $str .= " $key='$val'";
        }
        $str .= ">\n";
        return($str);
}

function hidden($name, $value){
        $str = "<input type=\"hidden\" name=\"" . $name."\" id=\"" . $name."\" value=\"" .$value."\">";
        return($str);
}

function textfield($atts){
    $str = "<input type='text' ";
    foreach ($atts as  $key=>$val){
      $str .= " $key='$val'";
    }
    $str .= "></input>";
    return($str);
}

function radioButton($atts,$value)
{
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

function textarea($atts=null, $value=null){
     $str = "<textarea ";
     if(is_array($atts))
     {
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
function select($name, $options=null,$sel=null){
        $str = "<select name=\"" . $name . "\" id=\"" . $name . "\" >\n";
        if(is_array($options))
        {
//echo "option is array len ".count($options)."<br>";
                $cnt = count($options);
                $ss =strtolower($sel);
                while (list($key, $val) = each ($options)) {
                        $str .= "<option value='" . $val . "'";
                        if (strtolower($val) == $ss) $str .= " selected";
                        if (is_numeric($key)) {
                          $str .= " > " . $val . "</option>\n";
                        } else {
                          $str .= " > " . $key . "</option>\n";
                        }
                }
        }
        $str .= "</select>\n";
        return($str);
}



function submit($value=null,$name=null){
        if($value == null)$value="submit";
        if($name == null) $name=$value;
        $str = "<input type='submit' name='$name' value='$value'>\n";
        return($str);
}

function resetButton($value=null,$name=null){
        if($value == null)$value="Reset";
        if($name == null) $name=$value;
        $str = "<input type='submit' name='$name' value='$value'>\n";
        return($str);
}

function Button($atts=null,$value=null,$name=null){
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

function space($cnt){
  for($i=0; $i<$cnt; $i++) $str .= " &nbsp; ";
  return($str);
}
 
function div($atts=null, $stuff=null){
        $str = "\n<div";
        if(is_array($atts)) {
                foreach ($atts as  $key=>$val)
                {
                        $str .= " $key='" . $val . "'";
                }
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
function end_div() { return("</div>\n"); }

/*    Test suite
///MAIN===============================
echo html();
echo _header();
echo body();

echo h1("this is an h1");
echo h3(font(array("color"=>"red"),b("this is an h3")));
echo p(b(font(array("color"=>"#23a7f0"), "Outside of a dog, the world is a lamppost")));
echo p(b("Inside of a man, it smells bad"));
echo start_form("$PHP_SELF","GET"), submit("clear"), end_form();
echo start_form("$PHP_SELF?Made_in=Montana&Fish_smells_like=other_good_things");
echo table(array("border"=>"1"));
echo tr(td(array("colspan"=>"2"),b(label("First Name")) . textfield("Firstname","Sandy",32)));
$options = array("good","bad","ugly");
echo tr(td(array("align"=>"right"),font(array("color"=>"#aa1111"),b(label("Fishing")))) . td(array("align"=>"right"),select("Fishing", $options)));
echo tr(td(array("colspan"=>"2"),submit("Submit")));
echo end_table();
echo end_form();
if($REQUEST_METHOD == 'POST')
{
        foreach ($HTTP_POST_VARS as  $key=>$val)
        {
                echo "<b>$key</b> = $val<br>";
        }
        echo br();
        foreach ($HTTP_GET_VARS as  $key=>$val)
        {
                echo "<b>$key</b> = $val<br>";
        }
}
echo br(),hr(),br();
echo a(array("href"=>"showcode.php?filename=cgi.php", "target"=>"_top"),
         font(array("color"=>"#aa8866","size"=>"22"), b(label("=> show the source <="))));
*/
?>