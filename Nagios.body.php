<?php
/**
 * Nagios - this extension places Nagios tables on mediawiki pages
 *
 * original by Edward Quick 24/03/2015
 *
 * Installation:
 *
 * To activate this extension, add the following into your LocalSettings.php file:
 * require_once '$IP/extensions/Nagios/Nagios.php';
 *
 * @ingroup Extensions
 * @author Edward Quick <edwardquick@hotmail.com>
 * @version 1.00
 * @link http://www.mediawiki.org/wiki/Extension:Nagios Documentation
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 *
 * Usage:
 *
 *  <Nagios --arguments-- >Header Text</Nagios>
 *
 *  --arguments--:
 *
 *     nagiosurl		: 
 *     nagioscgi		:
 *     pnp4url			:
 *
 *     extended			: display extended information (true or false)
 *     host			: display all hosts or a specific host whose services should be displayed
 *     hoststatustypes		: display hosts in a given state 1=Pending; 2=Up; 4=Down; 8=Unreachable
 *     service			: display all services or a specific service
 *     servicegroup		: display hosts and services for all servicegroups or one specific servicegroup
 *     servicefilter		: display services matching a given pattern
 *     servicestatustypes	: display state the services should be in 1=Pending; 2=OK, 4=Warning; 8=Unknown; 16=Critical
 *     style			: display overview; detail; summary; grid; hostdetail
 *     type			: hosts; hostgroups; services; servicegroups; contacts; contactgroups; timeperiods; 
 *				  commands; hostescalations; serviceescalations; hostdependencies; servicedependencies
 *
 *  See http://docs.icinga.org/latest/en/cgiparams.html for more details on nagios cgi parameters
 *
*/

 
class Nagios { 

	public static function init( &$parser ) {
                $parser->setHook( 'nagios', array( 'Nagios', 'renderNagios' ) );
                return true;
        }

	public static function renderNagios ( $input, array $args, Parser $parser, PPFrame $frame ) {
		global $wgNagiosRefresh, $wgScriptPath, $wgOut, $nagiosStatusCounter, $nagiosExtinfoCounter;
		global $wgNagiosUser,$wgNagiosPassword,$wgNagiosPnp4User,$wgNagiosPnp4Password;

		wfDebugLog( 'Nagios', "wfNagiosRender" );
		wfDebugLog( 'Nagios', "input=$input" );

		$output="";

		//table header
		$caption="<CAPTION>$input</CAPTION>";

		//invalidate cache
		$parser->disableCache();
	 
		//defaults
		$extended=false;
		$style="summary";

		$domain="";
		$host="";
		$hoststatustypes="";
		$nagioscgi="";
		$nagiosurl="";
		$path="";
		$pnp4url="";
		$service="";
		$servicegroup="";
		$type="";

		//parse xx values in <Nagios xx=yy> block and build the nagios url path
		foreach( $args as $name => $value ){
			switch(strtolower(htmlspecialchars($name))){ 
			case 'extended':
				$extended=htmlspecialchars($value);
				break;
			case 'host':
				$host=htmlspecialchars($value);
				$path.="&host=$host";
				break;
			case 'hoststatustypes':
				$hoststatustypes=htmlspecialchars($value);
				$path.="&hoststatustypes=$hoststatustypes";
				break;
			case 'nagiosurl':
				$nagiosurl=htmlspecialchars($value);
				$nagiosurl=self::sanitize($nagiosurl);
				break;
			case 'nagioscgi':
				$nagioscgi=htmlspecialchars($value);
				$nagioscgi=self::sanitize($nagioscgi);
				break;
			case 'pnp4url':
				$pnp4url=htmlspecialchars($value);
				$pnp4urli=self::sanitize($pnp4url);
				break;
			case 'service':
				$service=htmlspecialchars($value);
				$path.="&service=$service";
				break;
			case 'servicegroup':
				$servicegroup=htmlspecialchars($value);
				$path.="&servicegroup=$servicegroup";
				break;
			case 'servicestatustypes':
				$servicestatustypes=htmlspecialchars($value);
				$path.="&servicestatustypes=$servicestatustypes";
				break;
			case 'style':
				$style=htmlspecialchars($value);
				$path.="&style=$style";
				break;
			case 'type':
				$type=htmlspecialchars($value);
				$path.="&type=$type";
				break;
			}
		}

		// -------------------------------------------


		// check the nagios url can be accessed
		if(! self::nagiosUrlExists($nagiosurl)){
			wfDebugLog( 'Nagios', "nagiosurl=$nagiosurl could not connect" );
			$output= <<< EOT
<br><font color=red>
ERROR: (nagiosurl)<br>
Failed to connect to nagios URL: $nagiosurl <br>
</font><br>
EOT;
			return $output;
		}
		$output="";

		wfDebugLog( 'Nagios', "nagiosurl=$nagiosurl, path=$path, extended=$extended, style=$style" );

		// set up default cgi-bin url if not specified
		if ( $nagioscgi=="" ){	
			$nagioscgi=$nagiosurl . "/cgi-bin/";
		}

		// set up default pnp4nagios url if not specified
		if ( $pnp4url=="" ){
			$parse=parse_url($nagiosurl);
			$domain=$parse['host'];  
			$pnp4url='http://' . $domain . "/pnp4nagios/";
		}

		wfDebugLog( 'Nagios', "nagioscgi=$nagioscgi, pnp4url=$pnp4url" );


		// -------------------------------------------

		// fetch the nagios page
		if($extended){
			$url=$nagioscgi . "extinfo.cgi?$path";
		}else{
			$url=$nagioscgi . "status.cgi?$path";
		}

		wfDebugLog( 'Nagios', "url=$url" );	

		// set up the requests headers
		$wgNagiosUserAgent="Mediawiki NagiosExtension/1.0";
		$nagiosUserAgentHeader = "User-Agent: $wgNagiosUserAgent";
		$headers= array ( "User-Agent: $wgNagiosUserAgent" );

		// add auth header
		if (!($wgNagiosUser=="" && $wgNagiosPassword=="")){
			$nagiosPassBasicHeader = "Authorization: Basic " . base64_encode($wgNagiosUser . ':' . $wgNagiosPassword); 
			wfDebugLog( 'Nagios', "Adding authorization header: $nagiosPassBasicHeader" );	
			array_push($headers, $nagiosPassBasicHeader);

			// assume pnp4nagios user name and password are the same as nagios ig empty
			if($wgNagiosPnp4User==""){
				$wgNagiosPnp4User=$wgNagiosUser;
			}
			if($wgNagiosPnp4Password==""){
				$wgNagiosPnp4Password=$wgNagiosPassword;
			}
		}

		$opts = array('http' =>
                    array(
                        'method'  => 'GET',
                        'timeout' => 20,
                        'header'  => $headers
                        )
                );

		$context = stream_context_create($opts);
		require_once('includes/simple_html_dom.php');

		wfDebugLog( 'Nagios', 'Fetching the url');
		$html=file_get_html( $url, 0, $context );

		if($html==false){
			wfDebugLog( 'Nagios', "Failed to retrieve url." );
                        $output= <<< EOT
<br><font color=red>
ERROR: (nagiosurl)<br>
Failed to retrieve URL: $nagiosurl <br>
</font><br>
EOT;
                        return $output;

		}

		// -------------------------------------------

		// Add the css and js files to the output
		$wgOut->addModules( 'ext.nagios.common' );
		if($extended){
			$wgOut->addModules( 'ext.nagios.extinfo' );
		}else{
			$wgOut->addModules( 'ext.nagios.status' );
		}
		$wgOut->addModules( 'ext.nagios.pnp4nagios' );

		if(!$extended){
			$pnp4BasicAuth = base64_encode($wgNagiosPnp4User . ':' . $wgNagiosPnp4Password); 
			// get the nagios status table and replace local links with remote nagios url
			wfDebugLog( 'Nagios', "Writing nagiosstatus div" );

			$output='<div class="status" id="nagiosstatus' . $nagiosStatusCounter++ . '">';
			$statustable=$html->find('table.status');
			foreach ($statustable as $s){
				$line="";
				$line=str_replace("/nagios",$nagiosurl ,$s);
				$line=str_replace('<table border=0 width=100% class=\'status\'>',"<table border=0 width=100% class='status'>$caption", $line);
				$line=str_replace("status.cgi",$nagioscgi . "status.cgi",$line);

				// removes sort columns header for tidiness
				$line=preg_replace('/<th class=\'status\'>(\w+)((?!<\/th>).)*<\/th>/',"<th class='status'>$1&nbsp;</th>",$line);

				$line=str_replace("extinfo.cgi",$nagioscgi . "extinfo.cgi",$line);
				$line=str_replace("/pnp4nagios/graph",$pnp4url . "/graph",$line);
				$line=str_replace("/pnp4nagios/popup?", $wgScriptPath . '/extensions/Nagios/includes/popup.php?pnp4url=' . $pnp4url . "&pnp4BasicAuth=$pnp4BasicAuth&wgNagiosUserAgent=$wgNagiosUserAgent&",$line);
				$output.=$line;
			}
		}else{
			// get the extended information table
			wfDebugLog( 'Nagios', "Writing nagiosextinfo div" );

			$output='<div class="stateInfoPanel" id="nagiosextinfo' . $nagiosExtinfoCounter++ . '">';
			$output.= "<TABLE BORDER=1 CELLSPACING=0 CELLPADDING=0>$caption";
		
			$stateInfoTable1=$html->find('td.stateInfoTable1');	
			foreach ($stateInfoTable1 as $line){
				$output.=$line;
			}

			$output.= " </TD></TR></TABLE>";
			
		}

		// close the div containuer
		$output.='</div>';

		$html->clear();
		unset($html);

		return $output;

	}

	/**
         * Checks a url exists
         *
         * @param string $url
         *
         * return boolean
         */	
	protected static function nagiosUrlExists( $url ){
		if (is_array(@get_headers($url))){
			return true;
		}else{
			return false;
		}
	}

	/**
         * Format url string correctly. 
         *
         * @param string $url
         *
         * return string
         */
	protected static function sanitize($url){
		if ((strpos($url, "http")) === false) {
			$url = "http://" . $url;
		}
		return $url;
	}
}

?>
