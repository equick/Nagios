//This invokes the cluetips functionality that comes with pnp4nagios after a page loads
//and persists it with every refresh
$(".status").on("mouseover", function () {
        jQuery.noConflict();
        jQuery(document).ready(function() {
                jQuery('a.tips').cluetip({ajaxCache: false, dropShadow: false,showTitle: false });
        });
});
