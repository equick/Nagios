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

 
if (!defined( 'MEDIAWIKI' ) ) {
	echo( "This is an extension to the MediaWiki package and cannot be run standalone.\n" );
	die( -1 );
}

// Hook to load this extension into mediawiki
$wgHooks['ParserFirstCallInit'][] = 'wfNagios';

function wfNagios( Parser $parser ) {
        $parser->setHook( 'Nagios', 'wfNagiosRender' );
        return true;
}

// Special Page Info
$wgExtensionCredits['parserhook'][] = array(
        'name' => 'Nagios (version 1.00)',
	'version' => '1.00',
        'author' => 'Edward Quick (email: edwardquick@hotmail.com)',
        'url' => 'http://linuxproblems.org',
        'description' => 'Add Nagios Service Groups to mediawiki pages'
);

// default refresh set to 1 minute
$wgNagiosRefresh=60000;

//stylesheets and js files used from these packages on centos
$wgNagiosVersion="nagios-3.5.1-1.el6.x86_64";
$wgPNP4NagiosVersion="pnp4nagios-0.6.22-2.el6.x86_64";

// Hook file to pass value of wgNagiosRefresh to ext.nagios.refresh.js
$dir = __DIR__;
$wgAutoloadClasses['NagiosHooks'] = $dir . '/Nagios.hooks.php';
$wgHooks['ResourceLoaderGetConfigVars'][] = 'NagiosHooks::onResourceLoaderGetConfigVars';

// Resource Loader config for css and js files
$nagiosResourceTemplate = array(
        'localBasePath' => __DIR__,
        'remoteExtPath' => 'Nagios',
);

// Resources common to all pages
$wgResourceModules['ext.nagios.common'] = $nagiosResourceTemplate + array(
        'styles' => array ( 'modules/custom/ext.nagios.custom.css' ),
        'scripts' => array( 'modules/custom/ext.nagios.refresh.js' ),
        'position' => 'top',
);

// Resources required for status pages
$wgResourceModules['ext.nagios.status'] = $nagiosResourceTemplate + array(
        'styles' => array ( "modules/$wgNagiosVersion/ext.nagios.common.css", "modules/$wgNagiosVersion/ext.nagios.status.css" ),
	'position' => 'top',
);

// Resources for pnp4nagios
$wgResourceModules['ext.nagios.pnp4nagios'] = $nagiosResourceTemplate + array(
        'scripts' => array( "modules/$wgPNP4NagiosVersion/ext.nagios.jquery-min.js", "modules/$wgPNP4NagiosVersion/ext.nagios.jquery.cluetip.js", 'modules/custom/ext.nagios.jquery.atips.js' ),
        'position' => 'bottom',
);

// Resources for extended information
$wgResourceModules['ext.nagios.extinfo'] = $nagiosResourceTemplate + array(
	'styles' => array( "modules/$wgNagiosVersion/ext.nagios.extinfo.css" ),
        'position' => 'bottom',
);



function wfNagiosRender ( $input, array $args, Parser $parser, PPFrame $frame ) {
	global $wgNagiosRefresh, $wgScriptPath, $wgOut;

	wfDebugLog( 'Nagios', "wfNagiosRender" );

	$output="";

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
                        break;
		case 'nagioscgi':
                        $nagioscgi=htmlspecialchars($value);
                        break;
		case 'pnp4url':
                        $pnp4url=htmlspecialchars($value);
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


	// check the nagios url exists 
	if(!urlExists($nagiosurl) && !urlExists("$nagiosurl/")){
		wfDebugLog( 'Nagios', "nagiosurl=$nagiosurl does not exist" );
		$output= <<< EOT
<br><font color=red>
ERROR: (nagiosurl)<br>
Check your URL: $nagiosurl <br>
</font><br>
EOT;
		return $output;
	}

	wfDebugLog( 'Nagios', "nagiosurl=$nagiosurl, path=$path, extended=$extended, style=$style" );

	// base urls for nagios/pnp4nagios (set defaults if not defined)
	if ( $nagioscgi=="" ){	
        	$nagioscgi=$nagiosurl . "/cgi-bin/";
	}
	if ( $pnp4url=="" ){
        	$parse=parse_url($nagiosurl);
        	$domain=$parse['host'];  
        	$pnp4url='http://' . $domain . "/pnp4nagios/";
	}

	wfDebugLog( 'Nagios', "nagioscgi=$nagioscgi, pnp4url=$pnp4url" );

	// fetch the nagios page
	require_once('includes/simple_html_dom.php');
	$html=new simple_html_dom();
	if($extended){
		$url=$nagioscgi . "extinfo.cgi?$path";
	}else{
		$url=$nagioscgi . "status.cgi?$path";
	}

	wfDebugLog( 'Nagios', "Fetching url=$url" );	

        $html->load_file( $url );

	// Add the css and js files to the output
	$wgOut->addModules( 'ext.nagios.common' );
	if($extended){
		$wgOut->addModules( 'ext.nagios.extinfo' );
	}else{
		$wgOut->addModules( 'ext.nagios.status' );
	}
	$wgOut->addModules( 'ext.nagios.pnp4nagios' );

	if(!$extended){
		// get the nagios status table and replace local links with remote nagios url
		wfDebugLog( 'Nagios', "Writing nagiosstatus div" );

		$output='<div id="nagiosstatus">';
		$statustable=$html->find('table.status');
		foreach ($statustable as $s){
			$line="";
			$line=str_replace("/nagios",$nagiosurl ,$s);
			$line=str_replace("status.cgi",$nagioscgi . "status.cgi",$line);

			// removes sort columns header for tidiness
			$line=preg_replace('/<th class=\'status\'>(\w+)((?!<\/th>).)*<\/th>/',"<th class='status'>$1&nbsp;</th>",$line);

			$line=str_replace("extinfo.cgi",$nagioscgi . "extinfo.cgi",$line);
			$line=str_replace("/pnp4nagios/index.php/graph",$pnp4url . "/index.php/graph",$line);
			$line=str_replace("/pnp4nagios/index.php/popup?", $wgScriptPath . '/extensions/Nagios/includes/popup.php?pnp4url=' . $pnp4url . '&',$line);
			$output.=$line;
		}
	}else{
		// get the extended information table
		wfDebugLog( 'Nagios', "Writing nagiosextinfo div" );

		$output='<div id="nagiosextinfo">';
		$output.= "<TABLE BORDER=1 CELLSPACING=0 CELLPADDING=0>";
	
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

?>
