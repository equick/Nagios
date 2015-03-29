// refresh the nagios tables at a given interval

( function ( mw, $ ) {

	
	 mw.hook( 'wikipage.content' ).add( function ( $content ) {
		var nagiosRefreshInterval = mw.config.get( 'wgNagiosRefresh' );

		$('.status').each(function(i, obj) {
			if(obj.id!=""){
				var div_id='#' + obj.id;
				setInterval("$('" + div_id + "').load(\"" + location.href + " " + div_id + "\");",nagiosRefreshInterval);
			}
		});

		$('.stateInfoPanel').each(function(i, obj) {
                        if(obj.id!=""){
                                var div_id='#' + obj.id;
                                setInterval("$('" + div_id + "').load(\"" + location.href + " " + div_id + "\");",nagiosRefreshInterval);
                        }
                });

	} );
} )( mediaWiki, jQuery );


