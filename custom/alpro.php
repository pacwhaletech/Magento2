<?php
error_reporting(E_ALL);

define('REGULAR_PRICE_SCHEDULE_ID', 38);
define('MEMBERS_PRICE_SCHEDULE_ID', 40);

/* a class to encapsulate AlPro */
#include_once($_SERVER['DOCUMENT_ROOT'].'/clsParseXML.php');

class AlPro {

  var $pay_debug              = false;
  var $pay_uri                = false;
  var $debug_mode             = false;
  var $test_mode              = false;
  var $xml_parser             = false;
  var $price_sched_id         = 38;
  var $mem_price_sched_id     = 40;
  var $the_topic_node         = null;
  var $topic_node_data        = null;
  var $topic_tourcode         = null;
  var $topic_events           = null;
  var $topic_times            = null;
  var $products               = null;
  var $alpro_products         = null;
  var $reservation_id         = null;
  var $master_reservation_id  = null;
  var $sub_reservation_ids    = array();
  var $total_price            = 0;
  var $conf                   = array(
    '0-12 paid, none free'  =>  array(
      'New Years Eve Dinner Cruise',
      'New Years Dinner Cruise (Premium Seating)',
      'Whale Photo Safari',
      'Ultimate Whalewatch',
      'Halloween Express',
    ),
    '3-12 paid, 0-2 one free'  =>  array(
      "Sunset Cocktail Cruise (Ma'alaea)",
      "Island Rhythms Sunset Cocktail Cruise",
      "July 4Th Fireworks Cruise (Non-Alc; Maalaea)",
      "July 4Th Cocktail Cruise (Maalaea)",
      "July 4Th Cocktail Cruise (Lahaina)",
      "New Years Eve Cocktail Cruise",
#      "Full Moon Cruise",
      "Stargazing Cruise",
      "Halloween Cocktail Cruise",
      "Easter Brunch Cruise",
      "Valentines Dinner Cruise",
      "Valentines Premium Seating Dinner",
      "Christmas Dinner Cruise",
      "Christmas Dinner Cruise (Premium Seating)",
      "Christmas Brunch Cruise",
      "Thanksgiving Dinner Cruise",
      "Thanksgiving Premium Seating Dinner",
      "Dinner Cruise",
      "Dinner Cruise (Premium Seating)",
    ),
    '8-12 paid, none free'  =>  array(
      'Lanai Wild Side Eco-Tour (Raft)', # (note, extra $20 fee per person)  MIKAYA NOTE 6/10/14 fee no longer applicable
      'Molokini Wild Side (OE)',
    ),
    '7-12 paid, 0-6 one free'  =>  array(
      'Molokini & Turtle Arches Snorkel II',
      'Whalewatch Discount Lahaina',
      "Whalewatch Discount Ma'alaea",
      'Whale Search Lahaina',
      "Whale Search Ma'alaea",
      'Whalewatch Sail Deluxe',
      'Whalewatch Sail Special Lahaina',
      'Whalewatch Sail Lahaina',
      'Dolphin Watch',
      'VIP Dolphin Watch',
    ),
    '0-12 one free'  =>  array(
      'Molokini & Turtle Arches Snorkel',
      'Lanai Snorkel & Dolphin Watch',
      'Whalewatch Lahaina',
      "Whalewatch Ma'alaea",
      'VIP Whalewatch',
      'VIP Whale Watch and Lunch With Experts',
      "Sunrise Whalewatch Ma'alaea",
      'Sunrise Whalewatch Lahaina',
      'Molokini & Turtle Arches (Alcohol Free)',
    ),
    '6-12 paid, none free'  =>  array(
      'Whalewatch Lahaina Raft',
      'Whalewatch Raft Special Lahaina',
    ),
  );

  /*
    this is run every night by cron, via pwf_ajax.module
  */
  function run_cron() {

    #db_query(" DELETE FROM pwf_alpro_cache WHERE (created + (60 * 60 * 24)) < UNIX_TIMESTAMP(NOW()) ");
    #turn off caching
    db_query(" DELETE FROM pwf_alpro_cache WHERE (created + (0)) < UNIX_TIMESTAMP(NOW()) ");
  }

  /* insert a reservation into alpro and return the reservation id */
  function insert_reservation($order, $product, $product_node) {

    $this->order        = $order;
    $this->product      = $product;
    $this->product_node = $product_node;

#drupal_set_message("<pre>insert_reservation:".print_r($order,true)."</pre>");

    $rv             = array();
    $alpro_res_url  = $this->get_base_uri()."/als_insert_reservation.cgi?active=false&LastName=".urlencode($order->billing_last_name)."&FirstName=".urlencode($order->billing_first_name)."&date=".date("Y-m-d",strtotime($product->data[attributes][Date][0]))."&address1=".urlencode($order->billing_street1)."&address2=".urlencode($order->billing_street2)."&city=".urlencode($order->billing_city)."&state=".urlencode(substr(uc_get_zone_code($order->billing_zone), 0, 2))."&zip=".urlencode($order->billing_postal_code)."&email=".urlencode($order->primary_email)."&otherphone=".urlencode($order->billing_phone)."&country=".urlencode(uc_country_get_by_id($order->billing_country))."&phonecell=".urlencode($order->extra_fields['ucxf_mobile'])."&base=1&hotel=".urlencode($order->extra_fields['ucxf_accomodations']);

    if ($this->master_reservation_id) {
      $alpro_res_url .= "&masterreservation=".$this->master_reservation_id;
    }

    $debug_html    .= $this->parse_simple_array($alpro_res_url, $rv);

    if ($this->debug_mode) {
      drupal_set_message("\$alpro->insert_reservation(): '$alpro_res_url'");
      drupal_set_message("<pre>data from alpro:".print_r($rv,true)."</pre>");
    }

    /* sanity check */
    if (!is_array($rv['CODE']) || !is_numeric($rv['CODE'][0])) {
      return false;
    }

    $this->reservation_id = strval($rv['CODE'][0]);

    if (!$this->master_reservation_id) {
      $this->master_reservation_id = $this->reservation_id;
    }

    $this->sub_reservation_ids[$this->reservation_id] = 0;

    if ($this->debug_mode) {
      drupal_set_message("\$alpro->insert_reservation(): '".$this->reservation_id."'");
    }

    return $this->reservation_id;
  }

  function get_event_code($res_id=false) {
    if (!$res_id) {
      /* stored during insert_reservation() */
      $res_id = $this->reservation_id;
    }

    $temp_date        = $this->product->data['attributes']['Date'][0]." ".$this->product->data['attributes']['Time'][0];
    $military_time    = date("H:i",strtotime($temp_date));
    $eventURI         = $this->get_base_uri()."/als_get_events.cgi?product=".$this->product_node->field_code[0]['value']."&date=".date("Y-m-d",strtotime($this->product->data['attributes']['Date'][0]))."&days=0&equaltime=".$military_time;

    if ($this->debug_mode) {
      drupal_set_message("\$alpro->get_event_code(): '$eventURI'");
    }

    $event            = array();
    $this->parse_simple_array($eventURI, $event);

    if (!is_array($event['EVENTCODE']) || !is_numeric($event['EVENTCODE'][0])) {
      return false;
    }

    $this->event_code = $event['EVENTCODE'][0];

    if ($this->debug_mode) {
      drupal_set_message("\$alpro->get_event_code(): '".$event['EVENTCODE'][0]."'");
    }

    return $this->event_code;
  }

  /* stolen from clsParseXML.php, since it's more appropriately found in this class */
  function parse_simple_array($xml_url, &$simple_array, $print=false) {

#    $xml_parse = $this->xml_parser;
#    $xmldata = $xml_parse->GetXMLTree($xml_url);
    $xmldata = $this->get_alpro_xml_tree($xml_url);

    if ($xmldata == false) {
      return false;
    }

#if ($print) { drupal_set_message("<pre>".print_r($xmldata,true)."</pre>"); }

    foreach($xmldata as $key => $value) {
      if (is_array($value)) {
        $this->make_simple_array($value, $simple_array);
      }
      else {
        $simple_array[$key][] = $value;
      }
    }

    ####### build a table for debug #########

    $debug_table = '<li>'.$xml_url.'<table border=1 cellspacing=0><tr>';

    foreach($simple_array as $key => $value) {
      $debug_table .= '<td><b>'.$key;
      $anykey=$key;
    }

    for ($i = 0; $i < count($simple_array[$anykey]); $i++) {
      $debug_table .= '<tr>';
      foreach($simple_array as $key => $value) {
        $debug_table .= '<td>'.$simple_array[$key][$i];
      }
    }
    $debug_table .= '</table><br>';

    ######## end debug #########

    return $debug_table;

  }

  /* also poached from clsParseXML.php, as above */
  function make_simple_array($xmldata, &$simple_array){
    foreach($xmldata as $key => $value) {
      if (is_array($value)) {
        $this->make_simple_array($value, $simple_array);
      }
      else {
        $simple_array[$key][] = $value;
      }
    }
  }

  function insert_reservation_products($product, $product_code) {

    /* total to charge the credit card */
    $total = 0;

    $res_id       = $this->reservation_id;
    $event_code   = $this->event_code;

    $person_types = array('adult','older_child','young_child','infant');
    $member_types = array('members','regular');

    foreach ($person_types as $person_type) {
      foreach ($member_types as $member_type) {

        $data = $product->data['price_details'][$person_type][$member_type];
        if (!is_array($data)) {
          continue;
        }

        foreach ($data as $category_name => $passengers) {
          foreach ($passengers as $passenger) {

            $category_code  = $passenger['code']; // ".$rates2['CATEGORYCODE'][$r].'
            $price_schedule = $member_type == 'members' ? MEMBERS_PRICE_SCHEDULE_ID : REGULAR_PRICE_SCHEDULE_ID;

            $resURI = $this->get_base_uri()."/als_insert_reservation_product.cgi?Reservation={$res_id}&Product={$product_code}&ProductCategory={$category_code}&Qty=1&Event={$event_code}&priceschedule=$price_schedule";

            if ($this->debug_mode) {
              drupal_set_message("\$alpro->insert_reservation_products(): '$resURI'");
            }

            $res = array();
            /* insert a product (event,category,member quantity) into this reservation */
            $debug_html .= $this->parse_simple_array($resURI, $res);

            if (!is_array($res['NET']) || !is_numeric($res['NET'][0])) {
              if ($this->debug_mode) {
                drupal_set_message("\$alpro->insert_reservation_products(): returning FALSE (1)");
              }
              return FALSE;
            }

            /* tally the price for this insert */
            $expected_price = sprintf('%.2f', $passenger['price'] + $passenger['tax']);
            $this_price     = sprintf('%.2f', $res['NET'][0]);
            $total         += $this_price;

            if ($this_price != $expected_price) {
#              drupal_set_message("\$alpro->insert_reservation_products(): expected price ($expected_price) != this price ($this_price)", 'error');
              $subject  = "[pwf rate error] '$this_price' != '$expected_price'";
              $msg      = "person type: $person_type\nmember type: $member_type\ncat name: $category_name\n\nres: ";
              $msg      = $msg.print_r($res, TRUE)."\n\nproduct: ".print_r($product, TRUE)."\n\ndata: ".print_r($data,true);
              global $user;
              if ($user->uid == 1) {
                drupal_set_message("<pre>$subject: $msg</pre>", 'error');
              }
              else {
                @mail('webmaster@pacificwhale.org',$subject,$msg,"From: webmaster@pacificwhale.org");
              }

              /* abort the ordering process */
              if ($this->debug_mode) {
                drupal_set_message("\$alpro->insert_reservation_products(): returning FALSE (2)");
              }
              return FALSE;
            }

            if ($this->debug_mode) {
              drupal_set_message("\$alpro->insert_reservation_products(): net amount: '".$res['NET'][0]."'");
#              drupal_set_message("\$alpro->insert_reservation_products(): <pre>".print_r($res,true)."</pre>");
            }

          }
        }
      }
    }

    $this->sub_reservation_ids[$this->reservation_id] = $total;

    $this->total_price += $total;

    if ($this->debug_mode) {
      drupal_set_message("\$alpro->insert_reservation_products(): returning: '$total'");
    }

    return $total;
  }

  function process_credit_card($total_price, $order=false) {

    if ($this->debug_mode) {
      drupal_set_message("\$alpro->process_credit_card(): starting");
    }

    if (!$order) {
      $order = $this->order;
    }

    $res_id   = $this->master_reservation_id;
    $cc_exp   = str_pad($order->payment_details['cc_exp_month'],2,"0",STR_PAD_LEFT).substr($order->payment_details['cc_exp_year'], -2);
    $cc_name  = urlencode($order->billing_first_name." ".$order->billing_last_name);
    $cc_ccv   = $order->payment_details['cc_cvv'];
    $cc_num   = $order->payment_details['cc_number'];
    $total    = $this->total_price;

    /* timeout=60& */
    $payURI = $this->get_base_uri(true)."/als_process_credit_card.cgi?reservation={$res_id}&ccexp={$cc_exp}&amount={$total}&ccnumber={$cc_num}&cccid={$cc_ccv}&ccname={$cc_name}&resactivate=true";

    /* add the sub reservations if there are any, so we can try to charge the card just once */
    /* the first res is the default one, so unless there are at least two, there's no need to do this */
    if (count($this->sub_reservation_ids) > 1) {

      $payURI .= "&rescount=".count($this->sub_reservation_ids);

      $i = 1;
      foreach ($this->sub_reservation_ids as $resid => $sub_total) {
        $payURI .= "&amount$i={$sub_total}&res$i={$resid}";
        $i++;
      }

    }

    if ($this->test_mode) {
      $payURI .= "&cctestonly=true&ccTestApproval=true";
    }

    // Get a unique request number
    $sql = <<<SQL
      SELECT id
      FROM pwf_alpro_request_numbers
      WHERE res_id = %d
      ORDER BY id DESC
      LIMIT 1
SQL;
    $before_request_number = db_result(db_query($sql, $res_id));

    // Insert the new request number
    db_query(" INSERT INTO pwf_alpro_request_numbers (res_id) VALUES (%d) ", $res_id);

    $sql = <<<SQL
      SELECT id
      FROM pwf_alpro_request_numbers
      WHERE res_id = %d
      ORDER BY id DESC
      LIMIT 1
SQL;
    $after_request_number = db_result(db_query($sql, $res_id));

    if ($before_request_number == $after_request_number) {

      if ($this->debug_mode) {
        drupal_set_message("\$alpro->process_credit_card(): Failed to write res_id ($res_id) to pwf_alpro_request_numbers table.");
      }

      $failure_data = array(
        'RESPONSECODE' => array('Server error: Failed to store unique request record.'),
        'ERROR' => array('Failed to write new record to the pwf_alpro_request_numbers table (res_id:' . $res_id . ')'),
      );

      return $failure_data;

    }

    $payURI .= '&requestNo=' . $after_request_number;

    if ($this->debug_mode) {
      drupal_set_message("\$alpro->process_credit_card(): '$payURI'");
    }

    /* submit the payment to alpro */
    $pay = array();
    $pay_debug = $this->parse_simple_array($payURI, $pay);
    $this->pay_debug = $pay_debug;
    $this->pay_uri = preg_replace('/ccnumber=\d{12}(\d+)/', 'ccnumber=XXXXXXXXXXXX$1', $payURI);

    if ($this->debug_mode) {
      drupal_set_message("\$alpro->process_credit_card(): <pre>pay:".print_r($pay,true)."</pre>");
    }

    return $pay;
  }

  /* get the scheduled departures for this cruise product */
  function get_events($date=false) {
    $tourcode = $this->get_topic_tourcode();

    if (!$date) {
      $date = date('Y-m-d');
    }
    $base_uri = $this->get_base_uri();
    $uri = "$base_uri/als_get_events.cgi?date={$date}&days=0&tour={$tourcode}";

    if ($this->debug_mode) {
      echo("get_events(): $uri\n");
    }

    $xml = $this->get_alpro_xml_tree($uri);
#    $xml = $this->xml_parser->GetXMLTree($uri);
    #print_r($xml);

    if (isset($xml['ROOT'][0]['EVENTS'][0]['ROW']) && is_array($xml['ROOT'][0]['EVENTS'][0]['ROW'])) {
      $available_events = $xml['ROOT'][0]['EVENTS'][0]['ROW'];
      foreach ($available_events as $event) {
        $time_arr[] = date("h:i A",strtotime($event['ATTRIBUTES']['TIME']));
      }
    }
    else {
      $available_events = array();
      $time_arr         = array();
    }

    $this->topic_events = $available_events;
    $this->topic_times  = $time_arr;

    return array(
      'events'  =>  $available_events,
      'times'   =>  $time_arr,
    );
  }

  /* this will organize the data, but it already needs to know the specific customer category to use */
  function get_rate_config($type, $r_rates, $m_rates, $price_schedule, $free_rate_name) {

#if ($type == 'infant') {
#  echo "<hr/><hr/>$free_rate_name<hr/><pre>r_rates:";
#  print_r($r_rates);
  #echo "<hr/><hr/><hr/><pre>r_rates:";
  #print_r($m_rates);
  #echo "<hr/><hr/><hr/><pre>ps:";
  #print_r($price_schedule);
#  exit;
#}

    $r_fixed_tax      = $r_rates['FIXEDTAX'];
    $r_variable_tax   = $r_rates['VARIABLETAXAMOUNT'];
    $r_web_tax        = sprintf('%.2f', floatval($r_fixed_tax) + floatval($r_variable_tax));
    $m_fixed_tax      = $m_rates['FIXEDTAX'];
    $m_variable_tax   = $m_rates['VARIABLETAXAMOUNT'];
    $m_web_tax        = sprintf('%.2f', floatval($m_fixed_tax) + floatval($m_variable_tax));
    $return_data      = array(
      'regular_name'  =>  strval($r_rates['NAME']),
      'members_name'  =>  strval($m_rates['NAME']),
      'regular_code'  =>  strval($r_rates['CODE']),
      'members_code'  =>  strval($m_rates['CODE']),
      'web'           =>  floatval($r_rates['DISCOUNTEDRATE']),
      'member'        =>  floatval($m_rates['DISCOUNTEDRATE']),
      'retail'        =>  floatval($r_rates['PRICE']),
      'web_tax'       =>  floatval($r_web_tax),
      'member_tax'    =>  floatval($m_web_tax),
    );

    /* not sure if this will ever happen, since the intuit code doesn't allow for free adults as there are none yet existing */
    if ($free_rate_name) {
      foreach ($price_schedule['rates'] as $rate) {
        if ($rate['CATEGORYNAME'] == $free_rate_name) {
          $return_data['free']      = true;
          $return_data['free_name'] = $rate['CATEGORYNAME'];
          $return_data['free_code'] = $rate['CATEGORYCODE'];
          break;
        }
      }
    }

    return $return_data;
  }

  function get_rates($date=false) {
    $tourcode = $this->get_topic_tourcode();

    /* default to today */
    if (!$date) {
      $date = date('Y-m-d');
    }
    /* ensure the format */
    else {
      $date = date('Y-m-d', strtotime($date));
    }

    if (!$this->topic_events) {
      $fetch_data = $this->get_events($date);
    }

    $price_schedules = $this->get_price_schedules($date);

#echo "price schedules:";
#print_r($price_schedules);
#echo "\n";

    $regular_rates = $price_schedules['regular']['rates'];
    $members_rates = $price_schedules['members']['rates'];

    if (!is_array($regular_rates) || !is_array($members_rates)) {
      return array();
    }

    foreach ($regular_rates as $rate) {
      if ($rate['TOURCODE'] == $tourcode) {
        $regular_rates[$rate['CATEGORYNAME']] = array(
          "CODE"              => $rate['CATEGORYCODE'],
          "NAME"              => $rate['CATEGORYNAME'],
          "PRICE"             => $rate['PRICE'],
          "DISCOUNTEDRATE"    => $rate['DISCOUNTEDRATE'],
          "FIXEDTAX"          => $rate['FIXEDTAX'],
          "VARIABLETAXAMOUNT" => $rate['VARIABLETAXAMOUNT'],
        );
      }
    }

#echo("<hr/>regular rates ($regular_uri): ");
#print_r($regular_rates);
#echo "<hr/>\n";

    foreach ($members_rates as $rate) {
      if ($rate['TOURCODE'] == $tourcode) {
        $members_rates[$rate['CATEGORYNAME']] = array(
          "CODE"              => $rate['CATEGORYCODE'],
          "NAME"              => $rate['CATEGORYNAME'],
          "PRICE"             => $rate['PRICE'],
          "DISCOUNTEDRATE"    => $rate['DISCOUNTEDRATE'],
          "FIXEDTAX"          => $rate['FIXEDTAX'],
          "VARIABLETAXAMOUNT" => $rate['VARIABLETAXAMOUNT'],
        );
      }
    }

#echo("<hr/>members rates ($members_uri): ");
#print_r($members_rates);
#echo "<hr/>\n";

    $person_types = array('adult','older_child','young_child','infant');
    $meal_types   = array('chicken','fish','steak','vegetarian');
    $return_data  = array();

    foreach ($person_types as $person_type) {

      /* a meal cruise */
      if ($person_type != 'infant' && isset($price_schedules['regular']['meals'][$person_type]['chicken'])) {
        foreach ($meal_types as $meal_type) {
          $local_r_rates  = $regular_rates[$price_schedules['regular']['meals'][$person_type][$meal_type]];
          $local_m_rates  = $members_rates[$price_schedules['members']['meals'][$person_type][$meal_type]];
          $free_rate_name = $price_schedules['regular']["free_$person_type"]; # adult will be ignored

          $data = $this->get_rate_config($person_type, $local_r_rates, $local_m_rates, $price_schedules['regular'], $free_rate_name);

          $return_data[$person_type][$meal_type] = $data;

          /* store the prices as if they are non-meal prices (all prices will be the same, we have been told) */
          foreach ($return_data[$person_type][$meal_type] as $key => $value) {
            if (!isset($return_data[$person_type][$key])) {
              $return_data[$person_type][$key] = $value;
            }
          }
        }
      }
      /* not a meal cruise */
      else {
        $key = $person_type == 'adult' ? 'adult' : "paid_$person_type";
        $local_r_rates      = $regular_rates[$price_schedules['regular'][$key]];
        $local_m_rates      = $members_rates[$price_schedules['members'][$key]];
        $free_rate_name     = $price_schedules['regular']["free_$person_type"]; # adult will be ignored

#if ($person_type == 'infant') {
#  echo "<pre>testing ($free_rate_name)";
#  print_r($price_schedules['regular']);
#  echo "<pre>testing 1:";
#  print_r($regular_rates);
#  print_r($members_rates);
#  echo "<pre>testing 2:".$price_schedules['regular'][$person_type].':'.$price_schedules['members'][$person_type].':';
#  print_r($local_r_rates);
#  print_r($local_m_rates);
#  exit;
#}

        $data = $this->get_rate_config($person_type, $local_r_rates, $local_m_rates, $price_schedules['regular'], $free_rate_name);

        $return_data[$person_type] = $data;
      }

#      $return_data[$type] = $this->get_rate_config($type, $local_r_rates, $local_m_rates, $price_schedules['regular']);
#echo "<pre>";
#print_r($return_data);
#exit;
    }

    return $return_data;

  }

  function get_base_uri($secure=false) {

    /* MHS: this is the old way of connecting to either the test or live server al-pro api webserver
    if ($_SERVER['SERVER_NAME']=="www.pacificwhale.org") {
      $xml_server_url = "http://alspwf.dnsalias.com:8087/";
      $sxml_server_url = "https://alspwf.dnsalias.com/";
    }
    else {
      $xml_server_url = "http://alspwf.dnsalias.com:8086/";
      $sxml_server_url = "https://alspwf.dnsalias.com:442/";
    }

    if ($secure) {
      $protocol = 'https';
      $port     = $this->test_mode ? ':442' : '';
    }
    else {
      $protocol = 'http';
      $port     = $this->test_mode ? ':8086' : ':8087';
    }
 */

    if ($secure) {
      $protocol = 'https';
      $URL     = $this->test_mode ? 'webportal25.com/pwftrain' : 'webportal25.com/pwf';
    }
    else {
      $protocol = 'http';
      $URL     = $this->test_mode ? 'webportal25.com/pwftrain' : 'webportal25.com/pwf';
    }

    return "$protocol://$URL";
  }

  function __construct() {
    $this->xml_parser = &new ParseXML;

    if ($_SERVER['HTTP_HOST'] != 'www.pacificwhale.org') {
      $this->test_mode = true;
    }
  }

  function get_rate_rule() {
    $product = $this->get_product();

    $product_title = $product['PRODUCTNAME'];

    foreach ($this->conf as $rule => $titles) {
      foreach ($titles as $this_title) {
        if ($this_title == $product_title) {
          return $rule;
        }
      }
    }

    return false;
  }

  /* the node we're working on */
  function set_topic_node($node) {
    $this->the_topic_node   = $node;
    $this->topic_tourcode   = null;
    $this->topic_node_data  = null;
    $this->topic_events     = null;
    $this->topic_times      = null;
  }

  function set_topic_shortcode($shortcode) {
    $mock_node = new stdClass();
    $mock_node->field_productshortcode = array(
      array(
        'value' => $shortcode,
      ),
    );
    $this->set_topic_node($mock_node);
  }

  function set_topic_code($code) {
    $mock_node = new stdClass();
    $mock_node->field_code = array(
      array(
        'value' => $code,
      ),
    );
    $this->set_topic_node($mock_node);
  }

  function get_topic_node() {
    return $this->the_topic_node;
  }

  function XXset_topic_tourcode($tourcode) {
    $this->topic_tourcode = $tourcode;

    $mock_node = new stdClass();
    $mock_node->field_tourcode = array(
      array(
        'value' => $tourcode,
      ),
    );
    $this->set_topic_node($mock_node);
  }

  function get_topic_tourcode() {
    if (!$this->topic_tourcode) {
      if ($this->the_topic_node) {
        $product = $this->get_product();
#echo "<pre>"; print_r($product); exit;
        $this->topic_tourcode = $product['TOURCODE'];
      }
      else {
        die("you have not set the topic node in AlPro.php. Cannot continue.");
      }
    }
    return $this->topic_tourcode;
  }

  function get_extra_fees() {
    $product = $this->get_product();

    /* Lana'i Wild Side Eco-Tour; Lana'i landing fees is $20 extra.
    MIKAYA NOTE 6/10/14 - we no longer have the landing fee for Lahaina Wild Side, so disabling this extra fee
    if ($product['CODE'] == 185) {
      return 20;
    }
  */

    /* everything else */
    return 0;
  }

  /*
    this data is used to:
      hide unavailable options on the cruise configuration/detail pages
      re-label the older_child category
  */
  function get_kids_allowed() {

    $types    = array('young_child','infant');
    $allowed  = array();

    /* if there's a price schedule for paying kids, they're allowed */
    $price_schedules = $this->get_price_schedules();
    foreach ($types as $type) {
      $allowed[$type] = $price_schedules['regular']["paid_$type"] != '';
    }

    /* find the min age of the older_child category */
    $allowed['older_child'] = 0;
    if (preg_match('/(\d)\s*-\s*12/', $price_schedules['regular']['paid_older_child'], $match)) {
      $allowed['older_child'] = $match[1];
    }

    return $allowed;
  }

  function get_alpro_xml_tree($url) {

    $xml = false;

    /* load mostly static data from the cache */
    if (preg_match('@/als_get_(products|rates)?\.cgi\b@', $url)) {
      $xml = db_result(db_query(" SELECT xml FROM pwf_alpro_cache WHERE url = '%s' ", $url));
      if ($xml) {
        $xml = unserialize($xml);
      }
    }

    if (!is_array($xml)) {
      $xml = $this->xml_parser->GetXMLTree($url);
      if ($xml != false && preg_match('@/als_get_(products|rates)?\.cgi\b@', $url)) {
        db_query(" REPLACE INTO pwf_alpro_cache (url,xml,created) VALUES ('%s','%s',UNIX_TIMESTAMP(NOW())) ", $url, serialize($xml));
      }
    }

    return $xml;
  }

  /* get products from alpro's server; caching not yet implemented */
  function get_products() {

    if (!is_array($this->products)) {

      $products     = array();

      $base_uri     = $this->get_base_uri();

      $url = "$base_uri/als_get_products.cgi";

      $topic_node = $this->get_topic_node();
      if ($topic_node && isset($topic_node->field_code[0]['value'])) {
        $code = $topic_node->field_code[0]['value'];
        $url .= "?product=$code";
      }

      if ($this->debug_mode) {
        echo("get_products(): $url<br/>");
      }

      $products_xml   = $this->get_alpro_xml_tree($url);
      $this->products = $this->xml_to_array($products_xml, 'PRODUCTS');

    }

    return $this->products;

  }

  function get_product($short_code_or_code=false, $return_false_if_not_found_right_away=false) {

    if (!$short_code_or_code) {
      $topic_node = $this->get_topic_node();
      if ($topic_node) {
        if (isset($topic_node->field_code[0]['value'])) {
          $short_code_or_code = $topic_node->field_code[0]['value'];
        }
        else if (isset($topic_node->field_productshortcode[0]['value'])) {
          $short_code_or_code = $topic_node->field_productshortcode[0]['value'];
        }
        else {
          return false;
        }
      }
      else {
        return false;
      }
    }

    if (preg_match('/^\d+$/', $short_code_or_code)) {
      $field = 'CODE';
    }
    else {
      $field = 'PRODUCTSHORTNAME';
    }

    if ($this->debug_mode) {
      echo("get_product(): $short_code_or_code/$field<br/>");
    }

    $products = $this->get_products();
    foreach ($products as $product) {
      if ($product[$field] == $short_code_or_code) {
        return $product;
      }
    }

#    if ($return_false_if_not_found_right_away) {
      return false;
#    }

    /* not in there, fetch all products and try again */
#    $this->products = null;
#    return $this->get_product($short_code_or_code, true);

  }

  function get_price_schedules($date=false) {

    $product = $this->get_product();

    $regular_price_schedule = $this->intuit_price_schedules($product, REGULAR_PRICE_SCHEDULE_ID, $date);
    $members_price_schedule = $this->intuit_price_schedules($product, MEMBERS_PRICE_SCHEDULE_ID, $date);

    return array(
      'regular' =>  $regular_price_schedule,
      'members' =>  $members_price_schedule,
    );

  }

  /* returns 'RATES' or 'PRODUCTS' */
  function xml_to_array($xml, $type) {
    $a = array();

    if (is_array($xml['ROOT'][0][$type][0]['ROW'])) {
      foreach ($xml['ROOT'][0][$type][0]['ROW'] as $product) {
        $a[] = $product['ATTRIBUTES'];
      }
    }

    return $a;
  }

  function intuit_price_schedules($product, $price_schedule, $date_param=false) {
    if (!$date_param) {
      $date = date('Y-m-d');
    }
    else {
      $date = date('Y-m-d', strtotime($date_param));
    }

    if ($date == '1969-12-31') {
      $date = date('Y-m-d');
    }

    /* get the rate data for this product */
    $tour       = $product['TOURCODE'];
    $base_uri   = $this->get_base_uri();
    $url        = "$base_uri/als_get_rates.cgi?date=$date&tour=$tour&priceschedule=$price_schedule";
#    $rates_xml  = $this->xml_parser->GetXMLTree($url);
    $rates_xml  = $this->get_alpro_xml_tree($url);
    $rates      = $this->xml_to_array($rates_xml, 'RATES');

#  echo('<pre>');
#  print_r($rates);
#  exit;

    $conf       = array(
      'rates'             =>  $rates,
      'adult'             =>  false,
      'paid_older_child'  =>  false,
      'free_older_child'  =>  false,
      'paid_young_child'  =>  false,
      'free_young_child'  =>  false,
      'paid_infant'       =>  false,
      'free_infant'       =>  false,
      'meals'             =>  array(
         'adult'       =>  array(),
        'older_child' =>  array(),
        'young_child' =>  array(),
        'infant'      =>  array(),
      ),
    );

    /* find the adult category */
    $meal_cruise = false;
    $tmp_adult = '';
    foreach ($rates as $rate) {
      if (preg_match('/^Adult(\s+Chicken)?$/i', $rate['CATEGORYNAME'])) {
        if ($rate['CATEGORYNAME'] == 'Adult Chicken') {
          $tmp_adult = $rate['CATEGORYNAME'];
          $meal_cruise = true;
        }
        else if ($tmp_adult != 'Adult Chicken') {
          $tmp_adult = 'Adult';
        }
      }
    }

    if ($tmp_adult) {
      $conf['adult'] = $tmp_adult;
    }
    else {
#      die("I failed to find the adult category name for '".$product['PRODUCTNAME']."': [url: $url][rates_xml: $rates_xml]".print_r($rates,true));
      if ($product['PRODUCTNAME'] != 'Run For The Whales' && $product['PRODUCTNAME'] != 'Ocean Discovery Camp') {
        mail('webmaster@pacificwhale.org','[pwf alpro error] Failed to find adult category name for a cruise for date ('.$date_param.')',"I failed to find the adult category name for '".$product['PRODUCTNAME']."': [url: $url][rates_xml: $rates_xml]".print_r($rates,true)."\n\n--\n\n".print_r(debug_backtrace(),true),"From: webmaster@pacificwhale.org\r\n");
      }
      return;
    }

    if ($tmp_adult == 'Adult Chicken') {
      foreach ($rates as $rate) {
        if (preg_match('/Adult (chicken|fish|steak|vegetarian)/i', $rate['CATEGORYNAME'], $match)) {
          $conf['meals']['adult'][strtolower($match[1])] = $rate['CATEGORYNAME'];
        }
      }
    }

    /* find the older kid category */
    foreach ($rates as $rate) {

      if ($meal_cruise) {
        if (preg_match('/^Child(\s+Chicken)?$/i', $rate['CATEGORYNAME']) || preg_match('/\s*12 (&|and) Under/i', $rate['CATEGORYNAME'])) {
          if (preg_match('/(chicken|fish|steak|vegetarian)/i', $rate['CATEGORYNAME'], $match)) {
            $conf['meals']['older_child'][strtolower($match[1])] = $rate['CATEGORYNAME'];
            $conf['meals']['young_child'][strtolower($match[1])] = $rate['CATEGORYNAME'];
          }
        }
      }
      else {

        /* is this the right age? */
        /* hack to exclude free 7-12 */
        if (preg_match('/12/', $rate['CATEGORYNAME']) && preg_match('/Chi?ld/', $rate['CATEGORYNAME']) && !preg_match('/-Child/', $rate['CATEGORYNAME']) && !($product['PRODUCTNAME'] == 'Molokini & Turtle Arches Snorkel II' && $rate['CATEGORYNAME'] == 'Free Child Ages 12 & Under (1/ A)')) {

          /* free */
          if (preg_match('/Free/i', $rate['CATEGORYNAME'])) {
            $conf['free_older_child']       = $rate['CATEGORYNAME'];
            $conf['free_older_child_code']  = $rate['CATEGORYCODE'];
          }
          /* paid */
          else {
            $conf['paid_older_child'] = $rate['CATEGORYNAME'];
          }
        }
      }
    }

    /* default to adult pricing */
    if ($conf['paid_older_child'] === false) {
      $conf['paid_older_child'] = $conf['adult'];
    }

    /* find the young kid category */
    foreach ($rates as $rate) {

      if ($meal_cruise) {
        if (preg_match('/3\s*-\s*12/', $rate['CATEGORYNAME']) || preg_match('/\s*12 (&|and) Under/i', $rate['CATEGORYNAME'])) {
          if (preg_match('/(chicken|fish|steak|vegetarian)/i', $rate['CATEGORYNAME'], $match)) {
            $conf['meals']['young_child'][strtolower($match[1])] = $rate['CATEGORYNAME'];
          }
        }
      }
      else {

        /* is this the right age? */
        if (preg_match('/Child/', $rate['CATEGORYNAME']) && !preg_match('/-Child/', $rate['CATEGORYNAME'])) {
          if (preg_match('/-\s*6/', $rate['CATEGORYNAME']) || preg_match('/3\s*-/', $rate['CATEGORYNAME']) || preg_match('/12 & Under/', $rate['CATEGORYNAME'])) {

            /* free */
            if (preg_match('/Free/i', $rate['CATEGORYNAME'])) {
              $conf['free_young_child']       = $rate['CATEGORYNAME'];
              $conf['free_young_child_code']  = $rate['CATEGORYCODE'];
            }
            /* paid */
            else {
              $conf['paid_young_child'] = $rate['CATEGORYNAME'];
            }
          }
        }
      }
    }

    /* default to older kid pricing */
    if ($conf['paid_young_child'] === false && (!preg_match('/[678]\s*-/', $conf['paid_older_child']) || preg_match('/or Additional Child/i', $conf['paid_older_child']))) {
      $conf['paid_young_child'] = $conf['paid_older_child'];
    }

    /* find the infant category */
    foreach ($rates as $rate) {

      if ($meal_cruise) {
        if (preg_match('/12 (&|and) Under/i', $rate['CATEGORYNAME']) || preg_match('/0\s*-/', $rate['CATEGORYNAME'])) {
          if (preg_match('/free/i', $rate['CATEGORYNAME'])) {
            $conf['free_infant'] = $rate['CATEGORYNAME'];
          }
          else {
            $conf['paid_infant'] = $rate['CATEGORYNAME'];
          }
        }
      }
      else {

      if (preg_match('/Child/', $rate['CATEGORYNAME']) && !preg_match('/-Child/', $rate['CATEGORYNAME'])) {
        /* is this the right age? */
        if (preg_match('/0\s*-/', $rate['CATEGORYNAME']) || preg_match('/\d\s+(&|and)\s+Under/', $rate['CATEGORYNAME'])) {

          /* free */
          if (preg_match('/Free/i', $rate['CATEGORYNAME'])) {
            $conf['free_infant']      = $rate['CATEGORYNAME'];
            $conf['free_infant_code'] = $rate['CATEGORYCODE'];
          }
          /* paid */
          else {
            $conf['paid_infant'] = $rate['CATEGORYNAME'];
          }
        }

      }

      }
    }

    /* default to older kid pricing */
    if ($conf['paid_infant'] === false) { # && !preg_match('/[234]\s*-/', $conf['paid_young_child'])) {
      if ($meal_cruise) {
        foreach ($rates as $rate) {
          if (preg_match('/-\s*12/i', $rate['CATEGORYNAME'])) {
            if (preg_match('/free/i', $rate['CATEGORYNAME'])) {
              $conf['free_infant'] = $rate['CATEGORYNAME'];
            }
            else {
              $conf['paid_infant'] = $rate['CATEGORYNAME'];
            }
          }
        }
      }
      else {
        $conf['paid_infant'] = $conf['paid_young_child'];
      }
    }

#if ($name == 'Whale Photo Safari') {
#  echo('<pre>');
#  echo('<hr/>conf:<br/>');
#  print_r($rates);
#  print_r($conf);
#  exit;
#}
    return $conf;

  }


  function available_seat_count($date, $time) {
    $tourcode = $this->get_topic_tourcode();

    if (!$date) {
      $date = date('Y-m-d');
    }

    if (preg_match('/(\d\d?):(\d\d) ((A|P)M)/', $time, $match)) {
      $hours    = $match[1];
      $minutes  = $match[2];
      $meridian = $match[3];

      if ($meridian == 'PM') {
        $hours += 12;
      }

      $time = "$hours:$minutes";
    }

    $base_uri = $this->get_base_uri();
    $uri = "$base_uri/als_get_events.cgi?date={$date}&days=0&tour={$tourcode}";

    if ($this->debug_mode) {
      echo("available_seat_count(): $uri\n");
    }

    $xml = $this->get_alpro_xml_tree($uri);
#    $xml = $this->xml_parser->GetXMLTree($uri);
    #print_r($xml);

    if (isset( $xml['ROOT'][0]['EVENTS'][0]['ROW'] ) && is_array( $xml['ROOT'][0]['EVENTS'][0]['ROW'] )) {
      $available_events = $xml['ROOT'][0]['EVENTS'][0]['ROW'];
      foreach ($available_events as $event) {
#drupal_set_message("<pre>".print_r($event,true)."</pre>");
        if ($event['ATTRIBUTES']['TIME'] == $time) {
          return intval( $event['ATTRIBUTES']['SEATSAVAIL'] );
        }
      }
    }

    return 0;
  }
}