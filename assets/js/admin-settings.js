/* Programs > Settings: live preview of the gradient button while you pick its colours. */
jQuery(function ($) {
	function preview() {
		$('#pri-grad-preview').css('background', 'linear-gradient(90deg,' + $('#pri_c_from').val() + ',' + $('#pri_c_to').val() + ')');
	}
	$('#pri_c_from, #pri_c_to').on('input change', preview);
});
