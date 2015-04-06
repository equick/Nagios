$(document).on('mouseover', '.tips', function(event) { 
	$(this).qtip({
		overwrite: false,
		content: {
			text: function(event, api) {
				return $.ajax({
					type: 'GET',
					url: '/w/extensions/Nagios/includes/popup.php',
					dataType : 'html',
					data: {
						url : api.elements.target.attr('href')
					},
					once: false
                    		})
                    		.then(function(content) {
                        		//api.set('content.text', content);
					return content
                    		}, function(xhr, status, error) {
                        		// Upon failure... set the tooltip content to error
					console.log("error");
                        		api.set('content.text', status + ': ' + error);
                    		});

				return 'Loading...'; // Set some initial text
                	}
            	},
            	position: {
                	viewport: $(window)
            	},
		style: {
        		classes: 'qtip-light qtip-rounded qtip-shadow',
    		},
		show: {
			event: event.type,
			ready: true,
        		effect: function(offset) {
            			$(this).slideDown(100);
        		}
    		}
         },event)
});

$(document).on('mouseover', $(".status"), function(event) {
	$('.status [title]').each(function() { // Grab all elements with a title attribute,and set "this"
		$(this).qtip({ //
			overwrite: false,
       			content: {
                		attr: 'title'
                	},
                	style: {
                		classes: 'qtip-light qtip-rounded qtip-shadow',
                	},
        	})
	});
});
