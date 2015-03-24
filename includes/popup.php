<?php

require_once('simple_html_dom.php');

# example url http://linuxproblems.org/pnp4nagios/index.php/popup?host=centos&srv=_HOST_&_=1426421049833

$pnp4url=$_GET['pnp4url'];
$host=$_GET['host'];
$srv=$_GET['srv'];

$url=$pnp4url . "/index.php/popup?host=$host&srv=" . urlencode($srv);
$html=new simple_html_dom();
$html->load_file($url);

$table=$html->find('table');

foreach ($table as $s) {
	// remove padding between popup windows
	$s=str_replace('<table', '<table  cellspacing="0" ',$s);
	$s=str_replace('<img', '<img style="float: left; border: 0; margin: 0; padding:0;"',$s);

	$s=str_replace('/pnp4nagios/', $pnp4url ,$s);
	echo $s;
}

?>
