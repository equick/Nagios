<?php

//This is for passing the value of wgNagiosRefresh to the javascript

class NagiosHooks {

	public static function onResourceLoaderGetConfigVars( array &$vars ) {
		global $wgNagiosRefresh;
		$vars['wgNagiosRefresh'] = $wgNagiosRefresh;
		return true;
	}
}

?>
