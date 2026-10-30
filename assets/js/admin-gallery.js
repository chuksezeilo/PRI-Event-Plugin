/* Event Gallery box (admin): photo picker and video rows. Uses the built-in media uploader. */
jQuery(function ($) {
	var $thumbs = $('#pri-g-thumbs');
	if (!$thumbs.length) { return; }

	/* ---------- Photos ---------- */
	$thumbs.sortable({ tolerance: 'pointer' }); // DOM order = saved order

	function hasPhoto(id) {
		return $thumbs.find('input[value="' + id + '"]').length > 0;
	}

	$('#pri-g-add').on('click', function () {
		var frame = wp.media({
			title: priPmGallery.photoTitle,
			button: { text: priPmGallery.photoButton },
			library: { type: 'image' },
			multiple: 'add'
		});
		frame.on('select', function () {
			frame.state().get('selection').each(function (att) {
				var a = att.toJSON();
				if (hasPhoto(a.id)) { return; } // never add the same photo twice
				var src = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
				var $t = $('<div class="pri-g-thumb"></div>')
					.append($('<img>').attr('src', src).attr('alt', ''))
					.append($('<input type="hidden" name="pri_photos[]">').val(a.id))
					.append($('<button type="button" class="pri-g-remove"></button>').attr('title', priPmGallery.remove).html('&times;'));
				$thumbs.append($t);
			});
		});
		frame.open();
	});

	$thumbs.on('click', '.pri-g-remove', function () {
		$(this).closest('.pri-g-thumb').remove();
	});

	/* ---------- Videos ---------- */
	var $list = $('#pri-v-list');
	var template = $('#pri-v-template').html();
	$list.sortable({ handle: '.pri-v-handle', axis: 'y' });

	$('#pri-v-add').on('click', function () {
		// Unique index per row so posted fields never overwrite each other.
		var index = 'n' + Date.now() + Math.floor(Math.random() * 1000);
		$list.append(template.replace(/__INDEX__/g, index));
	});

	$list.on('click', '.pri-v-remove', function () {
		$(this).closest('.pri-v-row').remove();
	});

	$list.on('click', '.pri-v-upload', function () {
		var $row = $(this).closest('.pri-v-row');
		var frame = wp.media({
			title: priPmGallery.videoTitle,
			button: { text: priPmGallery.videoButton },
			library: { type: 'video' },
			multiple: false
		});
		frame.on('select', function () {
			var f = frame.state().get('selection').first().toJSON();
			$row.find('.pri-v-id').val(f.id);
			$row.find('.pri-v-url').val('').hide(); // a row is either a link or a file, never both
			$row.find('.pri-v-file').text(f.filename || f.title).show();
			var $label = $row.find('.pri-v-label');
			if (!$label.val()) { $label.val(f.title); }
		});
		frame.open();
	});
});
