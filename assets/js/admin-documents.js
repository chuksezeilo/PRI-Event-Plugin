/* Program Documents repeater (admin). Uses the built-in WordPress media uploader. */
jQuery(function ($) {
	var $list = $('#pri-docs-list');
	if (!$list.length) { return; }

	var template = $('#pri-doc-template').html();
	var $empty = $('#pri-docs-empty');

	// Drag rows to reorder. The browser posts fields in DOM order, so the server keeps this order.
	$list.sortable({ handle: '.pri-doc-handle', axis: 'y' });

	function refreshEmpty() {
		$empty.toggle($list.children().length === 0);
	}

	// A unique index per row stops the posted fields from overwriting each other.
	function addRow() {
		var index = 'n' + Date.now() + Math.floor(Math.random() * 1000);
		var $row = $(template.replace(/__INDEX__/g, index)).appendTo($list);
		refreshEmpty();
		return $row;
	}

	function chooseFor($row) {
		// A fresh media frame per click keeps $row correct for that click.
		var frame = wp.media({
			title: priPmDocs.title,
			button: { text: priPmDocs.button },
			library: { type: 'application/pdf' },
			multiple: false
		});
		frame.on('select', function () {
			var file = frame.state().get('selection').first().toJSON();
			$row.find('.pri-doc-id').val(file.id);
			$row.find('.pri-doc-file').text(file.filename || file.title);
			var $label = $row.find('.pri-doc-label');
			if (!$label.val()) { $label.val(file.title); }
		});
		frame.open();
	}

	// "Add PDF" adds a row and opens the picker straight away: one click instead of two.
	$('#pri-doc-add').on('click', function () { chooseFor(addRow()); });
	$list.on('click', '.pri-doc-choose', function () { chooseFor($(this).closest('.pri-doc-row')); });
	$list.on('click', '.pri-doc-remove', function () {
		$(this).closest('.pri-doc-row').remove();
		refreshEmpty();
	});
});
