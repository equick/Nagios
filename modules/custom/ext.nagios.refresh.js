var nagiosDiv = {
    refreshDiv: function ( $element ) {
       	$element.load(document.URL +  ' #' + $element[0].id); 
    }
};

window.nagiosDiv = nagiosDiv;

// refresh the nagios tables at a given interval
( function ( mw, $ ) {
	'use strict';

	 mw.hook( 'wikipage.content' ).add( function ( $content ) {
		var nagiosRefreshInterval = mw.config.get( 'wgNagiosRefresh' );
		setInterval("nagiosDiv.refreshDiv($( '#nagiosstatus' ))", nagiosRefreshInterval);
		setInterval("nagiosDiv.refreshDiv($( '#nagiosextinfo' ))", nagiosRefreshInterval);
	} );
} )( mediaWiki, jQuery );


