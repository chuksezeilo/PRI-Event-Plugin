/* Programs > Settings: services list (add, remove, reorder) and the button colour preview. */
jQuery(function ($) {
	var $list = $('#pri-svc-list');
	var template = $('#pri-svc-template').html();

	$list.sortable({ handle: '.pri-svc-handle', axis: 'y' }); // saved in on-screen order

	$('#pri-svc-add').on('click', function () {
		// Unique index per row so posted fields never overwrite each other.
		var index = 'n' + Date.now() + Math.floor(Math.random() * 1000);
		var $row = $(template.replace(/__INDEX__/g, index)).appendTo($list);
		$row.find('input[type=text]').focus();
	});

	$list.on('click', '.pri-svc-remove', function () {
		$(this).closest('.pri-svc-row').remove();
	});

	// Live preview of the gradient button.
	function preview() {
		$('#pri-grad-preview').css('background', 'linear-gradient(90deg,' + $('#pri_c_from').val() + ',' + $('#pri_c_to').val() + ')');
	}
	$('#pri_c_from, #pri_c_to').on('input change', preview);
});
