<?php

$url='http://data2.rasp-france.org/thredds/dodsC/rasp/france/2012/12/28/18/rasp-france_2012-12-29_18:00:00.nc.dods?XLAT[0:1:3][0:1:4],P[0:1:2][0:1:3][0:1:4]';

$handle = fopen($url, 'rb');

$buf = stream_get_line($handle, 1024, "\n");
if (trim($buf) != 'Dataset {') die('Erreur format');

// ********* process variables definitions **********************
$vars = array();
$varsCount=0;
while (!feof($handle)) {
  $buf = stream_get_line($handle, 1024, "\n");
  if (substr($buf, 0, 1) == '}') break;
  
  // get type and the name
  preg_match('/^\s*(\w+)\s(\w+)\[/', $buf, $matches);
  $vars['def'][$varsCount]['type'] = $matches[1];
  $vars['def'][$varsCount]['name'] = $matches[2];
  
  // get dimensions
  $count = preg_match_all("/\[(\w+) = (\d)\]/", $buf, $matches);
  $vars['def'][$varsCount]['nDims'] = $count;
  $vars['def'][$varsCount]['dims']['name'] = $matches[1];
  $vars['def'][$varsCount]['dims']['size'] = $matches[2];
  
  $varsCount++;
}

$vars['count'] = $varsCount;

// ********* processing binary data ***************

// go to data
$buf = stream_get_line($handle, 1024, "\n");
while ( trim($buf) != "Data:" && !feof($handle)) {
  $buf = stream_get_line($handle, 1024, "\n");
}

// process each variables
for ($vID=0; $vID<$vars['count']; $vID++) {

  // forward 8 bytes
  $buf = fread($handle, 8);
  //echo bin2hex($buf);
  
  switch ($vars['def'][$vID]['type']) {
    case 'Float32':
      parseFloat32($handle, $vars, $vID);
      break;
      
    default:
      die(sprintf("Error : %s is not implemented\n", $vars['def'][$vID]['type']));
  }
}

fclose($handle);

print_r($vars);

function parseFloat32($handle, &$vars, $vID) {
  $nVals = howManyVals($vars, $vID);
  
  for ($i=0; $i<$nVals; $i++) {
    $buf = fread($handle, 4);
    $val = unpack('f', strrev(substr($buf, 0, 4)))[1]; // needs to make sure it works on every platforms
    $vars['data'][$vID][$i]=$val;
  }
}

function howManyVals (&$vars, $vID) {
  $nVals = 1;
  for ($dim=0; $dim<$vars['def'][$vID]['nDims']; $dim++) {
    $nVals *= $vars['def'][$vID]['dims']['size'][$dim];
  }
  return $nVals;
}
?>