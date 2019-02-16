/*
	@since 0.8
	@author Patrick Strube
*/
jQuery(function($) {
	var $pw_cb = $('input[name="pw_weak"]');
	var $pass_strength = $('#pass-strength-result');
	
	$('body').on('input', $('#pass1-text'), function() {
		if($pass_strength.hasClass('good') || $pass_strength.hasClass('strong')){
			if($pw_cb.prop('checked')){
				$pw_cb.prop('checked', false);
			}
		}
	});
});