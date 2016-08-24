<?php

//Load Store (drupal+ubercart) files

include_once($_SERVER['DOCUMENT_ROOT'].'/m/custom/clsParseXML.php');
include_once($_SERVER['DOCUMENT_ROOT'].'/m/custom/AlPro.php');

if ($_SERVER['SERVER_NAME']=="www.pacificwhale.org") {
  # AlPro's webportal production server
  $xml_server_url = "http://webportal25.com/pwf/";
  $sxml_server_url = "https://webportal25.com/pwf/";
  # MHS: OLD SOURCE URL $sxml_server_url = "https://alspwf.dnsalias.com/";
}
else {
  # AlPro's webportal training/test server
  $xml_server_url = "http://webportal25.com/pwftrain/";
  $sxml_server_url = "https://webportal25.com/pwftrain/";
}

#$xmlparse = &new ParseXML;

#$ps1 = 38;
#$ps2 = 40;
#$psch    ="&priceschedule=".$ps1;
#$mempsch ="&priceschedule=".$ps2;


$date = date("Y-m-d");
$tourcode = 16; //trim($_REQUEST['tourcode']);
$event_URI = $xml_server_url."als_get_events.cgi?seats=1&date=".$date."&days=40&tour=".$tourcode;

$alpro = new AlPro();

#$xmlE = $xmlparse->GetXMLTree($event_URI);
$xmlE = $alpro->get_alpro_xml_tree($event_URI);

if (is_array($xmlE['ROOT'][0]['EVENTS'][0]['ROW'])) {
  $avail_events = $xmlE['ROOT'][0]['EVENTS'][0]['ROW'];
} else {
  //No upcoming events. Skip to next instance
  //echo "No seats available for this cruise this day.";
  echo '[]';
  exit;
}

$event_count = 0;
$event_dates = [];
foreach ($avail_events as $event) {
  $event = $event['ATTRIBUTES'];
  $date = $event['DATE'];
  $time = $event['TIME'];
  $seats = $event['SEATS'];
  $date_milliseconds = $date;
  if($seats != '0'){
      if(!isset ($event_dates[$date_milliseconds])){
          $event_dates[$date_milliseconds] = [];
      }
      $event_dates[$date_milliseconds][] = ['time' => $time, 'seats' => $seats];
  }
}

echo json_encode($event_dates);