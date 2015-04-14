// refresh the nagios tables at a given interval
// http://stackoverflow.com/questions/5052543/how-to-fire-ajax-request-periodically using setTimeout

( function ( mw, $ ) {

	var nagiosRefreshInterval = mw.config.get( 'wgNagiosRefresh' ) * 1000;
	mw.hook( 'wikipage.content' ).add( function ( $content ) {
		setTimeout(worker, nagiosRefreshInterval);
	});

	function worker() {
		$.ajax({
                        url: location.href,
			success: function(data){
				var $data = $(data);
				$(".qtip").remove();
				$('.status, .stateInfoPanel').each(function(i, obj) {
					if(obj.id!=""){
						var mydiv='#'+obj.id;
						$(this).html($data.find(mydiv));
					}
				})
			},
			error: function(){
      				console.log("An error occured with the refresh");
    			}
		});
		// Schedule the next request when the current one's complete
		setTimeout(worker, nagiosRefreshInterval);
	}

} )( mediaWiki, jQuery );


