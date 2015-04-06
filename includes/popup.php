<?php

ini_set("log_errors", 1);

require_once('simple_html_dom.php');

$pnp4url="";
$extinfo="";

if (isset($_GET['url']))
	$url=$_GET['url'];
else
	exit;

$opts = array('http' =>
                array(
                        'method'  => 'GET',
                        'timeout' => 20,
                        'header'  => array (
                                             "User-Agent: Nagios"
                                )
                        )
                );

$context = stream_context_create($opts);

switch(true){
	case preg_match ('/nagios\/cgi-bin\/extinfo.cgi\?type=1&host=/', $url):
		$html=file_get_html( $url, 0, $context );
		$output="";
        	$stateInfoTable1=$html->find('td.stateInfoTable1');
        	foreach ($stateInfoTable1 as $line){
			$line=str_replace('stateInfoTable1','tooltipstateInfoTable1',$line);
        		$output.=$line;
        	}
		print $output;
		exit;

	case preg_match('/pnp4nagios\/graph\?host=/', $url):
		$url=preg_replace('/pnp4nagios\/graph\?host=(\w+)/', "/pnp4nagios/index.php/popup?host=$1", $url);
		$html=file_get_html( $url, 0, $context );
        	$table=$html->find('table');
        	foreach ($table as $s) {
                	// remove padding between popup windows
                	$s=str_replace('<table', '<table  cellspacing="0" ',$s);
                	$s=str_replace('<img', '<img style="float: left; border: 0; margin: 0; padding:0;"',$s);
                	echo $s;
        	}
		exit;

	case preg_match('/status.cgi\?host=(\w+)&servicestatustypes=(\d+)&hoststatustypes=\d+&serviceprops=\d+&hostprops=\d+$/', $url, $match):
		$host=$match[1];
		$servicestatustype=$match[2];
		$servicestate=getServiceState($servicestatustype);

		$lql= <<<EOT
GET services
Columns: description plugin_output last_state_change
Filter: state = $servicestate
Filter: host_name = $host
Limit: 20
EOT;

		$query='live.php?q=' . lqlEncode($lql);
                $url=preg_replace('/cgi-bin\/status.cgi.*$/', "$query", $url);
                $json=file_get_contents( $url, 0, $context );
                $obj = json_decode($json);
                $serviceinfo=$obj[1];
		if($servicestate==2){
                        printServicesList($obj[1]);
                }else{
                        printServicesByLastStateChange($obj[1]);
                }
                exit;


	case preg_match('/status.cgi\?host=(\w+)$/', $url, $match):
		$host=$match[1];
                $lql= <<<EOT
GET services
Columns: description plugin_output last_state_change
Filter: host_name = $host
Limit: 20
EOT;
		$query='live.php?q=' . lqlEncode($lql);
		$url=preg_replace('/cgi-bin\/status.cgi.*$/', "$query", $url);
		$json=file_get_contents( $url, 0, $context );
		$obj = json_decode($json);
		printServicesList($obj[1]);
		exit;

	default:
		print "Error matching url: $url";
		exit;
}

function lqlEncode($str){
	$str=str_replace("\n", '\\\\\\n', $str);
	$str=str_replace(' ', '%20', $str);
	$str.='\\\\\\n';
	return $str;
}

function getServiceState($servicestatustype){
	switch($servicestatustype){
		case 1:         # SERVICE_PENDING
			$servicestate=0;
			break;
		case 2:         # SERVICE_OK
			$servicestate=0;
			break;
		case 4:         # SERVICE_WARNING
			$servicestate=1;
			break;
		case 8:         # SERVICE_UNKNOWN
			$servicestate=3;
			break;
		case 16:        # SERVICE_CRITICAL
			$servicestate=2;
			break;
		default:
                	$servicestate=0;
                        break;
	}
	return $servicestate;
}

function getHostState($hoststatustype){
	switch($hoststatustype){
                case 1:         # HOST_PENDING
                        $state=0;
                        break;
                case 2:         # HOST_UP
                        $state=0;
                        break;
                case 4:         # HOST_DOWN
                        $state=1;
                        break;
                case 8:         # HOST_UNREACHABLE
                        $state=1;
                        break;
                default:
                        $state=0;
                        break;
        }
        return $state;

}

function printServicesByLastStateChange($serviceinfo){
	$times=array();	
	foreach ($serviceinfo as $servicerow){
		$services=array();	
        	$row="$servicerow[0] $servicerow[1]<br>";
                $laststatechange=$servicerow[2];
		if(isset($times["$laststatechange"])){
			$services=$times["$laststatechange"];
		}
		array_push($services,$row);
		$times["$laststatechange"]=$services;
	}
	
	
	krsort($times);
	foreach ($times as $time=>$services){
		#print "$time ---- ";
		foreach ($services as $row){
			print "$row";
		}
	}
}

function printServicesList($serviceinfo){
	$servicerows=array();
	foreach ($serviceinfo as $servicerow){
		$service=$servicerow[0];
		$plugin_output=$servicerow[1];
		$row="$service $plugin_output<br>";
		$servicerows["$service"]=$row;
	}
	ksort($servicerows);
	foreach ($servicerows as $row){
		print $row;
	}
	
}


?>

