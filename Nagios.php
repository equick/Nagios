<?php
 
if (!defined( 'MEDIAWIKI' ) ) {
	echo( "This is an extension to the MediaWiki package and cannot be run standalone.\n" );
	die( -1 );
}

$dir = __DIR__;
$wgAutoloadClasses['Nagios'] = $dir . '/Nagios.body.php';
$wgAutoloadClasses['NagiosHooks'] = $dir . '/Nagios.hooks.php';
$wgHooks['ParserFirstCallInit'][] = 'wfNagios';
$wgHooks['ResourceLoaderGetConfigVars'][] = 'NagiosHooks::onResourceLoaderGetConfigVars';

function wfNagios( Parser $parser ) {
        $parser->setHook( 'Nagios', 'Nagios::Render' );
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


$nagiosStatusCounter=1;
$nagiosExtinfoCounter=1;
?>
