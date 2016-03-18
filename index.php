<?php

// Toggl API.
define("TOGGL_AGENT", '');
define("TOGGL_TOKEN", '');
define("TOGGL_WORKSPACE_ID", '');
define("TOGGL_USER_ID", '');

// Info.
define("HOUR_RATE", 1);
define("HOUR_RATE_CURRENCY", 'CZK');
define("FINAL_CURRENCY", 'CZK');


$l_first = date("Y-m-d", strtotime("first day of last month"));
$l_last = date("Y-m-d", strtotime("last day of last month"));
$c_first = date("Y-m-d", strtotime("first day of this month"));
$c_last = date("Y-m-d", strtotime("last day of this month"));
$yesterday = date("Y-m-d", strtotime("yesterday"));
$today = date("Y-m-d", strtotime("today"));

$tm_cf_cl = toggl_m($c_first, $c_last);
sleep(1);
$tm_lf_ll = toggl_m($l_first, $l_last);
sleep(1);
$th_t_t = toggl_h($today, $today);
sleep(1);
$th_y_y = toggl_h($yesterday, $yesterday);

echo 'Vydelano tento mesic: ' . $tm_cf_cl . "\n" .
  'Vydelano minuly mesic: '. $tm_lf_ll . "\n" .
  'Zbyvajici pracovni dny: ' . number_of_working_days($today, $c_last) . "\n" .
  'Na konci mesice pri tempu 4h/den:' . number_format((convert(number_of_working_days($today, $c_last) * 4 * HOUR_RATE) / 1000 + $tm_cf_cl), 0, ",", "") .  "\n" .
  'Na konci mesice pri tempu 6h/den:' . number_format((convert(number_of_working_days($today, $c_last) * 6 * HOUR_RATE) / 1000 + $tm_cf_cl), 0, ",", "") .  "\n" .
  'Na konci mesice pri tempu 8h/den:' . number_format((convert(number_of_working_days($today, $c_last) * 8 * HOUR_RATE) / 1000 + $tm_cf_cl), 0, ",", "") .  "\n" .
  'Do dnesniho cile zbyva: ' . number_format((8 - $th_t_t), 1, ",", "") . 'h'. "\n" .
  'Do vcerejsiho cile zbyva: ' . number_format((8 - $th_y_y), 1, ",", "") . 'h' . "\n";


function toggl_h($first, $last) {
  $url = "https://www.toggl.com/reports/api/v2/summary?workspace_id=" . TOGGL_WORKSPACE_ID . "&user_ids=" . TOGGL_USER_ID . "&since={$first}&until={$last}&user_agent=" . TOGGL_AGENT . "&api_token=" . TOGGL_TOKEN;

  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
  curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 2);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($curl, CURLOPT_USERAGENT, TOGGL_AGENT);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
  curl_setopt($curl, CURLOPT_USERPWD, TOGGL_TOKEN . ':api_token');
  $buffer = curl_exec($curl);
  curl_close($curl);

  $json_result = json_decode($buffer, TRUE);

  if (isset($json_result['total_grand'])) {
    $h = $json_result['total_grand'] / 1000 / 60 / 60; // ms => h
    return $h;
  }
  else {
    return 0;
  }
}

function toggl_m($first, $last) {
  if ($h = toggl_h($first, $last)) {
    $rate_currency = $h * HOUR_RATE;
    $final_currency = convert($rate_currency);
    return number_format($final_currency / 1000, 0, ",", " ");
  }
  else {
    return "Fail";
  }
}

function number_of_working_days($from, $to) {
  $workingDays = [1, 2, 3, 4, 5]; # date format = N (1 = Monday, ...)
  $holidayDays = [
    '*-12-25',
    '*-01-01',
    '2013-12-23'
  ]; # variable and fixed holidays

  $from = new DateTime($from);
  $to = new DateTime($to);
  $to->modify('+1 day');
  $interval = new DateInterval('P1D');
  $periods = new DatePeriod($from, $interval, $to);

  $days = 0;
  foreach ($periods as $period) {
    if (!in_array($period->format('N'), $workingDays)) {
      continue;
    }
    if (in_array($period->format('Y-m-d'), $holidayDays)) {
      continue;
    }
    if (in_array($period->format('*-m-d'), $holidayDays)) {
      continue;
    }
    $days++;
  }
  return $days;
}

function convert($rate_currency) {
  if (HOUR_RATE_CURRENCY == FINAL_CURRENCY) {
    return $rate_currency;
  }

  $rate = file_get_contents('https://api.fixer.io/latest?base=' . HOUR_RATE_CURRENCY . '&symbols=' . FINAL_CURRENCY );
  $rate = json_decode($rate);
  $rate = $rate->rates->{FINAL_CURRENCY};

  $final_currency = $rate_currency * $rate;
  return $final_currency;
}
