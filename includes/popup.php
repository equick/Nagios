<?php

ini_set("log_errors", 0);

require_once('simple_html_dom.php');

$pnp4url="";
$extinfo="";

if (isset($_GET['url']))
	$url=urldecode($_GET['url']);
else
	exit;

$opts = array('http' =>
                array(
                        'method'  => 'GET',
                        'timeout' => 20,
                        'header'  => array (
                                             "User-Agent: MediaWiki NagiosExtension"
                                )
                        )
                );

$context = stream_context_create($opts);

switch(true){
	case preg_match ('/nagios\/cgi-bin\/extinfo.cgi\?type=\d+&host=/', $url):
		$url=str_replace(' ','%20',$url);
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
		$url=preg_replace('/pnp4nagios\/graph\?host=([^&]+)/', "pnp4nagios/index.php/popup?host=$1", $url);
		$url=str_replace(' ','%20',$url);

		$baseUrl=getBaseUrl(parse_url($url));
		
		$html=file_get_html( $url, 0, $context );
        	$table=$html->find('table');
        	foreach ($table as $s) {
                	// remove padding between popup windows
                	$s=str_replace('<table', '<table  cellspacing="0" ',$s);
			// prefix /pnp4nagios with remote url
			$s=preg_replace('/src=\"\/pnp4nagios/',"src=\"$baseUrl/pnp4nagios",$s);
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
EOT;

		$query='live.php?q=' . lqlEncode($lql);
                $url=preg_replace('/cgi-bin\/status.cgi.*$/', "$query", $url);
                $json=file_get_contents( $url, 0, $context );
                $obj = json_decode($json);
                $serviceinfo=$obj[1];
		if($servicestate==0){
                        printServicesList($obj[1]);
                }else{
                        printServicesByLastStateChange($obj[1]);
                }
                exit;

	case preg_match('/status.cgi\?servicegroup=([^&]+)&style=detail&servicestatustypes=(\d+)&hoststatustypes=\d+&serviceprops=\d+&hostprops=\d+$/', $url, $match):
		$servicegroup=$match[1];	
		$servicestatustype=$match[2];
                $servicestate=getServiceState($servicestatustype);

		$lql= <<<EOT
GET servicesbygroup
Columns: host_name description plugin_output last_state_change
Filter: state = $servicestate
Filter: servicegroup_name = $servicegroup
EOT;

                $query='live.php?q=' . lqlEncode($lql);
                $url=preg_replace('/cgi-bin\/status.cgi.*$/', "$query", $url);
                $json=file_get_contents( $url, 0, $context );
                $obj = json_decode($json);
                $serviceinfo=$obj[1];
                printServiceGroupList($obj[1]);
                exit;

	case preg_match('/status.cgi\?host=(\w+)$/', $url, $match):
		$host=$match[1];
                $lql= <<<EOT
GET services
Columns: description plugin_output last_state_change
Filter: host_name = $host
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
        	$row="<td>$servicerow[0]</td><td>$servicerow[1]</td>";
                $laststatechange=$servicerow[2];
		if(isset($times["$laststatechange"])){
			$services=$times["$laststatechange"];
		}
		array_push($services,$row);
		$times["$laststatechange"]=$services;
	}
	
	krsort($times);
	print '<div id="tooltip"><table>';
	foreach ($times as $time=>$services){
		foreach ($services as $row){
			print "<tr>$row</tr>";
		}
	}
	print '</table></div>';
}

function printServicesList($serviceinfo){
	$servicerows=array();
	foreach ($serviceinfo as $servicerow){
		$service=$servicerow[0];
		$plugin_output=$servicerow[1];

		if (preg_match('/^WARN/',$plugin_output)){
			$row="<td style='background-color:#FF0'>$service</td><td>$plugin_output</td>";
		} elseif (preg_match('/^CRIT/',$plugin_output)){
			$row="<td style='background-color:#F88888'>$service</td><td>$plugin_output</td>";
		} else {
			$row="<td>$service</td><td>$plugin_output</td>";
		}
		$servicerows["$service"]=$row;
	}
	uksort($servicerows,"strnatcasecmp");
	print '<div id="tooltip"><table>';
	foreach ($servicerows as $row){
		print "<tr>$row</tr>";
	}
	print '</table></div>';
	
}

function printServiceGroupList($serviceinfo){
        $servicerows=array();
        foreach ($serviceinfo as $servicerow){
		$host=$servicerow[0];
                $service=$servicerow[1];
                $plugin_output=$servicerow[2];

                if (preg_match('/^WARN/',$plugin_output)){
                        $row="<td style='color: blue'>$host</td><td style='background-color:#FF0'>$service</td><td>$plugin_output</td>";
                } elseif (preg_match('/^CRIT/',$plugin_output)){
                        $row="<td style='color: blue'>$host</td><td style='background-color:#F88888'>$service</td><td>$plugin_output</td>";
                } else {
                        $row="<td style='color: blue;'>$host</td><td>$service</td><td>$plugin_output</td>";
                }
                $servicerows["$host"]=$row;
        }
        uksort($servicerows,"strnatcasecmp");
        print '<div id="tooltip"><table>';
        foreach ($servicerows as $row){
                print "<tr>$row</tr>";
        }
        print '</table></div>';

}


function getBaseUrl($parsed_url) {
	$scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
	$host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
	$port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
	$user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
	$pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
	$pass     = ($user || $pass) ? "$pass@" : '';
	return "$scheme$user$pass$host$port";
} 

?>
