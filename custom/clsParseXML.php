<?php

class HTTPRequest {
  var $_fp;       // HTTP socket
  var $_url;      // full URL
  var $_host;     // HTTP host
  var $_protocol; // protocol (HTTP/HTTPS)
  var $_uri;      // request URI
  var $_port;     // port

  // scan url
  function _scan_url() {
    $req = $this->_url;

    $pos = strpos($req, '://');
    $this->_protocol = strtolower(substr($req, 0, $pos));

    $req = substr($req, $pos+3);
    $pos = strpos($req, '/');
    if($pos === false) {
      $pos = strlen($req);
    }
    $host = substr($req, 0, $pos);

    if(strpos($host, ':') !== false) {
      list($this->_host, $this->_port) = explode(':', $host);
    }
    else {
      $this->_host = $host;
      $this->_port = ($this->_protocol == 'https') ? 443 : 80;
    }

    $this->_uri = substr($req, $pos);
    if($this->_uri == '') {
      $this->_uri = '/';
    }
  }

  // constructor
  function HTTPRequest($url) {
    $this->_url = $url;
    $this->_scan_url();
  }

  // download URL to string
  function DownloadToString() {
    $crlf = "\r\n";

    // generate request
    $req = 'GET ' . $this->_uri . ' HTTP/1.1' . $crlf
           . 'Host: ' . $this->_host . $crlf
           . $crlf;

    // fetch
    $this->_fp = @fsockopen(($this->_protocol == 'https' ? 'ssl://' : '') . $this->_host, $this->_port, $_SESSION['al_err_num'], $_SESSION['al_err_msg']);
    if(!is_resource($this->_fp)) {
      if($_SESSION['al_err_num'] !='0') {
        $mailTo        = 'webmaster@pacificwhale.org';
        $mailSubject   = "Problem with the Alpro server";
        $mailMessage  .='There is a problem with the alpro server<br /><li>ERROR '.$_SESSION['al_err_num'].': '.$_SESSION['al_err_msg'].'<br />From page http://'.$_SERVER{'HTTP_HOST'}.$_SERVER{'SCRIPT_NAME'}.'?'.$_SERVER{'QUERY_STRING'}.'<br />Client Address: '.$_SERVER{'REMOTE_ADDR'}.'<br />Client Browser: '.$_SERVER{'HTTP_USER_AGENT'}.' at '.date('Y-m-d');
        $mailMessage  .=$this->_protocol.':'.$this->_host.':'.$this->_port.':'.$this->_uri.":$req:";
        $headers  = "From: webmaster@pacificwhale.org\n";
        $headers .= "MIME-Version: 1.0\n";
        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\n";
        @mail ($mailTo, $mailSubject, $mailMessage, $headers);
      }
    }
    @fwrite($this->_fp, $req);
    $response = '';
    while(is_resource($this->_fp) && $this->_fp && !feof($this->_fp))
      $response .= fread($this->_fp, 1024);
    @fclose($this->_fp);

    // split header and body
    $pos = strpos($response, $crlf . $crlf);
    if($pos === false) {
      return($response);
    }
    $header = substr($response, 0, $pos);
    $body = substr($response, $pos + 2 * strlen($crlf));

    // parse headers
    $headers = array();
    $lines = explode($crlf, $header);
    foreach($lines as $line) {
      if(($pos = strpos($line, ':')) !== false) {
        $headers[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos+1));
      }
    }

    // redirection?
    if(isset($headers['location'])) {
      $http = new HTTPRequest($headers['location']);
      return($http->DownloadToString($http));
    }
    else {
      return($body);
    }
  }
}

//make_simple_array added by eli
function make_simple_array($xmldata, &$simple_array){
  foreach($xmldata as $key => $value) {
    if (is_array($value)) {
      make_simple_array($value, $simple_array);
    }
    else {
      $simple_array[$key][] = $value;
    }
  }
}

//parse_simple_array added by eli
function parse_simple_array($xml_url, &$simple_array){
  $xml_parse = new ParseXML();
  $xmldata = $xml_parse->GetXMLTree($xml_url);
  foreach($xmldata as $key => $value) {
    if (is_array($value)) {
      make_simple_array($value, $simple_array);
    }
    else {
      $simple_array[$key][] = $value;
    }
  }

####### build a table for debug #########
  $debug_table = '<li>'.$xml_url.'<table border=1 cellspacing=0><tr>';
  foreach($simple_array as $key => $value){
    $debug_table .= '<td><b>' . $key;
    $anykey=$key;
  }
  for ($i=0;$i<count($simple_array[$anykey]);$i++){
    $debug_table .= '<tr>';
    foreach($simple_array as $key => $value) {
      $debug_table .= '<td>' . $simple_array[$key][$i];
    }
  }
  $debug_table .= '</table><br>';
######## end debug #########

  return $debug_table;
}
/*

XML Parser Class
by Eric Rosebrock
http://www.phpfreaks.com

Class originated from: kris@h3x.com AT: http://www.devdump.com/phpxml.php

Usage:

<?php
include 'clsParseXML.php';

$xmlparse = &new ParseXML;
$xml = $xmlparse->GetXMLTree('/path/to/xmlfile.xml');

echo "<pre>";
print_r($xml);
echo "</pre>";


The path to the XML file may be a local file or a URL.
Returns the elements of the XML file into an array with
it's subelements as keys and subarrays.

*/

class ParseXML {

  function GetChildren($vals, &$i) {
    $children = array(); // Contains node data
    if (isset($vals[$i]['value'])){
      $children['VALUE'] = $vals[$i]['value'];
    }

    while (++$i < count($vals)) {
      switch ($vals[$i]['type']) {

        case 'cdata':
          if (isset($children['VALUE'])){
            $children['VALUE'] .= $vals[$i]['value'];
          }
          else {
            $children['VALUE'] = $vals[$i]['value'];
          }
          break;

        case 'complete':
          if (isset($vals[$i]['attributes'])) {
            $children[$vals[$i]['tag']][]['ATTRIBUTES'] = $vals[$i]['attributes'];
            $index = count($children[$vals[$i]['tag']]) - 1;

            if (isset($vals[$i]['value'])) {
              $children[$vals[$i]['tag']][$index]['VALUE'] = $vals[$i]['value'];
            }
            else {
              $children[$vals[$i]['tag']][$index]['VALUE'] = '';
            }
          }
          else {
            if (isset($vals[$i]['value'])) {
              $children[$vals[$i]['tag']][]['VALUE'] = $vals[$i]['value'];
            }
            else {
              $children[$vals[$i]['tag']][]['VALUE'] = '';
            }
          }
          break;

        case 'open':
          if (isset($vals[$i]['attributes'])) {
            $children[$vals[$i]['tag']][]['ATTRIBUTES'] = $vals[$i]['attributes'];
            $index = count($children[$vals[$i]['tag']]) - 1;
            $children[$vals[$i]['tag']][$index] = array_merge($children[$vals[$i]['tag']][$index], $this->GetChildren($vals, $i));
          }
          else {
            $children[$vals[$i]['tag']][] = $this->GetChildren($vals, $i);
          }
          break;

        case 'close':
          return $children;
      }
    }
  }

  function GetVarsTree($xml) {
    global $xml;
    $num=0;
    $eventarray=array();
    foreach($xml[ROOT][0][EVENTS][0][ROW] as $root) {
			foreach($root[ATTRIBUTES] as $var => $val) {
				//$var = $var;

				$attarray[$var] = $val;
				//$object->$var = $val;$xml[ROOT][0][EVENTS]
			}
			array_push($eventarray, $attarray);
    } //foreach
    return $eventarray;
  }

  /**
   * The attempt_number param works with the requestNo param.
   * When the request fails due to a curl_error
   */
  function GetXMLTree($xmlloc, $attempt_number=0) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $xmlloc);
    curl_setopt($ch, CURLOPT_VERBOSE, 0);
##  curl_setopt($ch, CURLOPT_POST, 1);
##  curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $post_fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
#  curl_setopt($ch, CURLOPT_NOPROGRESS, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    $data = curl_exec($ch);

    $curl_error = curl_error($ch);

    // Try again a few times if we get a buggy connection
    // Only do this if we are trying to charge a credit card with a
    // requestNo param, which allows us to resubmit without the possibility of
    // double-charging a card.
    if (!$data
    && preg_match('/als_process_credit_card.+requestNo/', $xmlloc)
    && preg_match('/bad record mac/', $curl_error)) {

      if (++$attempt_number >= 3) { // Try three times
        return FALSE;
      }

      // Send debug message
      $this_xmlloc = preg_replace('/ccnumber=\d{12}(\d+)/', 'ccnumber=XXXXXXXXXXXX$1', $xmlloc);
      $this_subject = '[curl error, trying again(' . $attempt_number . ')]';
      $this_msg = "failed to get anything here(curl_error:$curl_error): xmlloc:'$this_xmlloc'/data:'$data'";
      @mail('webmaster@pacificwhale.org', $this_subject, $this_msg, "From:webmaster@pacificwhale.org\r\n");

      return $this->GetXMLTree($xmlloc, $attempt_number);
    }

    if (!$data || $curl_error) {
      $xmlloc = preg_replace('/ccnumber=\d{12}(\d+)/', 'ccnumber=XXXXXXXXXXXX$1', $xmlloc);
      @mail('webmaster@pacificwhale.org','[curl error]',"failed to get anything here(curl_error:$curl_error): xmlloc:'$xmlloc'/data:'$data'","From:webmaster@pacificwhale.org\r\n");
      curl_close($ch);
      return FALSE;
    }

    if ($attempt_number > 0) {
      $my_xmlloc = preg_replace('/ccnumber=\d{12}(\d+)/', 'ccnumber=XXXXXXXXXXXX$1', $xmlloc);
      $my_subject = '[curl error, success after ' . $attempt_number . ' failures]';
      $my_msg = "failed to get anything here(curl_error:$curl_error): xmlloc:'$my_xmlloc'/data:'$data'";
      @mail('webmaster@pacificwhale.org', $my_subject, $my_msg, "From:webmaster@pacificwhale.org\r\n");
    }

    curl_close($ch);

    $parser = xml_parser_create('ISO-8859-1');
    xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
    xml_parse_into_struct($parser, $data, $vals, $index);
    xml_parser_free($parser);

    $tree = array();
    $i = 0;

    if (isset($vals[$i]['attributes'])) {
      $tree[$vals[$i]['tag']][]['ATTRIBUTES'] = $vals[$i]['attributes'];
      $index = count($tree[$vals[$i]['tag']]) - 1;
      $tree[$vals[$i]['tag']][$index] =  array_merge($tree[$vals[$i]['tag']][$index], $this->GetChildren($vals, $i));
    }
    else {
      $tree[$vals[$i]['tag']][] = $this->GetChildren($vals, $i);
    }
    return $tree;
  }

}