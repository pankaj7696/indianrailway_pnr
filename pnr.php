<?php
header('Access-Control-Allow-Origin: *'); //This allow to consume from any where
// To get the JSON string from array	
function array2json($arr) {
    if(function_exists('json_encode')) return json_encode($arr); //Lastest versions of PHP already has this functionality.
    $parts = array();
    $is_list = false;

    //Find out if the given array is a numerical array
    $keys = array_keys($arr);
    $max_length = count($arr)-1;
    if(($keys[0] == 0) and ($keys[$max_length] == $max_length)) {//See if the first key is 0 and last key is length - 1
        $is_list = true;
        for($i=0; $i<count($keys); $i++) { //See if each key correspondes to its position
            if($i != $keys[$i]) { //A key fails at position check.
                $is_list = false; //It is an associative array.
                break;
            }
        }
    }

    foreach($arr as $key=>$value) {
        if(is_array($value)) { //Custom handling for arrays
            if($is_list) $parts[] = array2json($value); /* :RECURSION: */
            else $parts[] = '"' . $key . '":' . array2json($value); /* :RECURSION: */
        } else {
            $str = '';
            if(!$is_list) $str = '"' . $key . '":';

            //Custom handling for multiple data types
            if(is_numeric($value)) $str .= $value; //Numbers
            elseif($value === false) $str .= 'false'; //The booleans
            elseif($value === true) $str .= 'true';
            else $str .= '"' . addslashes($value) . '"'; //All other things
            // :TODO: Is there any more datatype we should be in the lookout for? (Object?)

            $parts[] = $str;
        }
    }
    $json = implode(',',$parts);
    
    if($is_list) return '[' . $json . ']';//Return numerical JSON
    return '{' . $json . '}';//Return associative JSON
} 

// function definition to convert array to xml
function array2xml($student_info, &$xml_student_info) {
    foreach($student_info as $key => $value) {
        if(is_array($value)) {
            if(!is_numeric($key)){
                $subnode = $xml_student_info->addChild("$key");
                array2xml($value, $subnode);
            }
            else{
                array2xml($value, $xml_student_info);
            }
        }
        else {
            $xml_student_info->addChild("$key","$value");
        }
    }
}

//Function to Retrieve the data
function makeWebCall($urlto,$postData = null,$refer=null){

//create cURL connection
$curl_connection = curl_init($urlto);
//set options
curl_setopt($curl_connection, CURLOPT_CONNECTTIMEOUT, 30);
curl_setopt($curl_connection, CURLOPT_USERAGENT,
  "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
curl_setopt($curl_connection, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl_connection, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($curl_connection, CURLOPT_FOLLOWLOCATION, true);
//if 
if(isset($postData)){
    //set data to be posted
    curl_setopt($curl_connection, CURLOPT_POST,true);	
	curl_setopt($curl_connection, CURLOPT_POSTFIELDS, $postData);
}


if(isset($refer)){
 //set referer
 curl_setopt($curl_connection, CURLOPT_REFERER, $refer);
}
//perform our request
$result = curl_exec($curl_connection);
// Debug -Data
//show information regarding the request
//var_dump(curl_getinfo($curl_connection));
// echo curl_errno($curl_connection) . '-' .
//                curl_error($curl_connection);

//close the connection
curl_close($curl_connection);
return $result;
}

// Function to construct the post request
function createPostString($postArray){
//traverse array and prepare data for posting (key1=value1)
foreach ( $postArray as $key => $value) {
    $post_items[] = $key . '=' . $value;
}
//create the final string to be posted using implode()
return implode ('&', $post_items);
}

// This support bot hPOST and get methods
// Initial check is for post if not found then get request is used.
// the allowed fields are 
// KEY      Datatype           Mandatory      Description
//-------------------------------------------------------
// pnrno    Integer(10)        true           PNR number to be fetched 10 digit
// rtype    String(XML/JSON)   false          Return type format 
// callback String 			   false          Support for JSONP only supported for GET
$pnt_no = isset($_POST['pnrno'])? $_POST['pnrno']:(isset($_GET['pnrno'])?$_GET['pnrno']:'');
$rtype = isset($_POST['rtype'])? $_POST['rtype']:(isset($_GET['rtype'])?$_GET['rtype']:'');

$url_captch = 'http://www.indianrail.gov.in/pnr_Enq.html';
$url_pnr = 'http://www.indianrail.gov.in/cgi_bin/inet_pnstat_cgi_10521.cgi';
// Submit the captcha and PNR
//create array of data to be posted
$post_data['lccp_pnrno1'] = $pnt_no;
$post_data['lccp_cap_val'] = 12345; //dummy captcha
$post_data['lccp_capinp_val'] = 12345;
$post_data['submit'] = "Get Status";
$post_string = createPostString($post_data);

$result = makeWebCall($url_pnr,$post_string,$url_captch );

//Debug
//var_dump($result);
// Parse Logic
// I have not used DOM lib it is simple regEx parse.
//Change here when the Page layout of the page changes.
$matches = array();
preg_match_all('/<td class="table_border_both">(.*)<\/td>/i',$result,$matches);
//DEBUG
//var_dump($matches);
$resultVal = array(
    'status'    =>    "INVALID",
    'data'      =>    array()                
);

if (count($matches)>1&&count($matches[1])>8) {
 $arr = $matches[1];
 $i=0;
 $j=0;
 $tmpValue =array(
          "pnr" => $pnt_no,
          "train_name" => "",
          "train_number" => "",
          "from" => "",
          "to" => "",
          "reservedto" => "",
          "board" => "",
          "class" => "",
          "travel_date" => "",
          "passenger" => array()
 );
 
 $tmpValue['train_number'] = $arr[0];
 $tmpValue['train_name'] = $arr[1];
 $tmpValue['travel_date'] = $arr[2];
 $tmpValue['from'] = $arr[3];
 $tmpValue['to'] = $arr[4];
 $tmpValue['reservedto'] = $arr[5];
 $tmpValue['board'] = $arr[6];
 $tmpValue['class'] = $arr[7];
 $stnum="";
 foreach ($arr as $value) {
 
  $i++;
  if($i>8){
   $value=trim(preg_replace('/<B>/', '', $value));
   $value=trim(preg_replace('/<\/B>/', '', $value));
   
   $ck=$i%3;
    if($ck==1){      
     $stnum = $value;
    }
    else if($ck==2) {
      array_push($tmpValue["passenger"],array(
           "seat_number" => $stnum, 
           "status" => $value 
        ));
    }
  }
 }
 $resultVal['data'] = $tmpValue;
 $resultVal['status'] = 'OK';
}
if($rtype=='XML'){
$xmlresult = new SimpleXMLElement("<?xml version=\"1.0\"?><result></result>");
array2xml($resultVal,$xmlresult);
echo $xmlresult->asXML();
}
else{
$jsondata =  array2json($resultVal);
 if(array_key_exists('callback', $_GET)){

    header('Content-Type: text/javascript; charset=utf8');
    header('Access-Control-Max-Age: 3628800');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

    $callback = $_GET['callback'];
    echo $callback.'('.$jsondata.');';

}else{
    // normal JSON string
    header('Content-Type: application/json; charset=utf8');

    echo $jsondata;
}
}

?>
