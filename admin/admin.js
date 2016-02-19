(function($) {
    'use strict';
    $(function() {
         var button_text=$("#woocommerce_hubspot-settings_connector_button").text();

        $(".hubwoo-preloader").hide();


        $("#woocommerce_hubspot-settings_connector_button").on("click", function(event) {
        	event.preventDefault();
        	if (button_text=="Connect to Hubspot") {
        		// alert ('connnect');
        	}else{
        		// alert ('disconnnect');
        	}

        	var client_id=$('input[name="client_id"]').val();
        	var portal_id=$('input[name="woocommerce_hubspot-settings_connector"]').val();

        	var redirect_uri=$(location).attr('href');  ;
    		var scope='offline';
    		var auth_url=$.param({
    								client_id:client_id,
    								portalId:portal_id,
    								scope:scope,
    								redirect_uri:redirect_uri,
    							});
    		auth_url="https://app.hubspot.com/auth/authenticate?" + auth_url;
     

    		if (portal_id ==""){
    			alert ('Please enter Hub ID to continue');
    		}else{
                
                 $(".hubwoo-preloader").delay(1000).show();
                
                 var data={'connector': portal_id};
                    jQuery.post(
                         hubwooAjax.ajaxurl,
                         {
                         action : 'setting-submit',
                         data : data,
                         } 
                         ).done(function( data ) {
                            $(".hubwoo-preloader").hide();
                            window.location.href = auth_url;
                            $(".hubwoo-preloader").hide();
                         }); 

    			// TODO  save hubid
    			 
    		}

        });
    });
})(jQuery);