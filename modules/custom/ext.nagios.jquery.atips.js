//This invokes the cluetips functionality that comes with pnp4nagios after a page loads
//and persists it with every refresh
$( document ).on( "mouseover", "a.tips", function() {
	jQuery(document).ready(function() {
		jQuery('a.tips').cluetip({ajaxCache: false, dropShadow: false,showTitle: false });
	});
});
