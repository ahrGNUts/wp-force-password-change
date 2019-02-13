/*
	@since 0.7
	@author Patrick Strube
*/

jQuery(function($){
	$('#enforce_admin_pw_change').on('change', function() {
		if($(this).prop('checked')){
			$('#admin_pw_row').show();
		} else {
			$('#admin_pw_row').hide();
		}
	});
	
	$('#custom_pw_redirect_picker').on('change', function() {
		if($(this).val() == "custom"){
			$('#redirect_url_row').show();
		} else {
			$('#redirect_url_row').hide();
		}
	});
});
