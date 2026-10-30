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

	/* ---------- Link check (same rules as the server, so what you see here is what gets saved) ----------
	   The server cannot warn you after saving: the block editor saves this box in the background without
	   reloading the page, so a notice sent back from the server would never be shown. */
	function videoProvider(url) {
		var a;
		try { a = new URL(String(url).trim()); } catch (e) { return null; }
		if (a.protocol !== 'http:' && a.protocol !== 'https:') { return null; }
		var host = a.hostname.toLowerCase().replace(/^(www\.|m\.|music\.)/, '');
		var path = a.pathname;
		if (host === 'youtu.be' && /^\/[A-Za-z0-9_-]{11}(\/|$)/.test(path)) { return 'YouTube'; }
		if (host === 'youtube.com' || host === 'youtube-nocookie.com') {
			if (path === '/watch' && /^[A-Za-z0-9_-]{11}$/.test(a.searchParams.get('v') || '')) { return 'YouTube'; }
			if (/^\/(embed|shorts|live|v)\/[A-Za-z0-9_-]{11}(\/|$)/.test(path)) { return 'YouTube'; }
		}
		if ((host === 'vimeo.com' || host === 'player.vimeo.com') &&
			/^\/(video\/|channels\/[^\/]+\/|groups\/[^\/]+\/videos\/)?\d{5,12}(\/[A-Za-z0-9]+)?\/?$/.test(path)) { return 'Vimeo'; }
		return null;
	}

	// Shows a green tick or a red warning under a row's link field. Returns true when the row has a bad link.
	function checkRow($row) {
		var $url = $row.find('.pri-v-url');
		var $msg = $row.find('.pri-v-msg');
		if (!$msg.length) {
			$msg = $('<div class="pri-v-msg" style="flex-basis:100%;margin:0 0 0 26px;font-size:13px"></div>').appendTo($row);
		}
		var val = String($url.val() || '').trim();
		var visible = $url.is(':visible');
		if (!visible || val === '') { $msg.text('').hide(); $row.css('border-color', ''); return false; }
		var provider = videoProvider(val);
		if (provider) {
			$msg.text('✓ ' + provider + ' video').css('color', '#00a32a').show();
			$row.css('border-color', '');
			return false;
		}
		$msg.text(priPmGallery.badLink).css('color', '#d63638').show();
		$row.css('border-color', '#d63638');
		return true;
	}

	$list.on('input change blur', '.pri-v-url', function () { checkRow($(this).closest('.pri-v-row')); });
	$list.find('.pri-v-row').each(function () { checkRow($(this)); });

	// Block editor: when a save finishes, remind the editor if a bad link was left behind (and so not saved).
	try {
		var editor = window.wp && wp.data && wp.data.select('core/editor');
		if (editor && editor.isSavingPost) {
			var wasSaving = false;
			wp.data.subscribe(function () {
				var saving = wp.data.select('core/editor').isSavingPost();
				if (wasSaving && !saving) {
					var bad = 0;
					$list.find('.pri-v-row').each(function () { if (checkRow($(this))) { bad++; } });
					if (bad) {
						wp.data.dispatch('core/notices').createWarningNotice(priPmGallery.badLinkNotice, { id: 'pri-bad-video-link', isDismissible: true });
					}
				}
				wasSaving = saving;
			});
		}
	} catch (e) { /* classic editor: the server-side notice after saving is used instead */ }

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
