/* Photo lightbox for the program gallery. Plain JavaScript, no libraries.
   Without JavaScript the thumbnails are ordinary links to the large image. */
(function () {
	var overlay, imgEl, capEl, counter, current = [], index = 0, lastFocus = null;

	function build() {
		overlay = document.createElement('div');
		overlay.className = 'pri-lb';
		overlay.hidden = true;
		overlay.setAttribute('role', 'dialog');
		overlay.setAttribute('aria-modal', 'true');
		overlay.setAttribute('aria-label', 'Photo viewer');
		overlay.innerHTML =
			'<button type="button" class="pri-lb-close" aria-label="Close">&times;</button>' +
			'<button type="button" class="pri-lb-prev" aria-label="Previous photo">&#8249;</button>' +
			'<figure class="pri-lb-fig"><img alt=""><figcaption></figcaption></figure>' +
			'<button type="button" class="pri-lb-next" aria-label="Next photo">&#8250;</button>' +
			'<div class="pri-lb-count"></div>';
		document.body.appendChild(overlay);
		imgEl = overlay.querySelector('img');
		capEl = overlay.querySelector('figcaption');
		counter = overlay.querySelector('.pri-lb-count');

		overlay.addEventListener('click', function (e) {
			// Click on the dark background (not the photo or buttons) closes.
			if (e.target === overlay || e.target.classList.contains('pri-lb-fig')) { close(); }
		});
		overlay.querySelector('.pri-lb-close').addEventListener('click', close);
		overlay.querySelector('.pri-lb-prev').addEventListener('click', function () { show(index - 1); });
		overlay.querySelector('.pri-lb-next').addEventListener('click', function () { show(index + 1); });
		document.addEventListener('keydown', function (e) {
			if (overlay.hidden) { return; }
			if (e.key === 'Escape') { close(); }
			else if (e.key === 'ArrowLeft') { show(index - 1); }
			else if (e.key === 'ArrowRight') { show(index + 1); }
		});

		// Simple swipe for phones.
		var startX = null;
		overlay.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
		overlay.addEventListener('touchend', function (e) {
			if (startX === null) { return; }
			var dx = e.changedTouches[0].clientX - startX;
			startX = null;
			if (Math.abs(dx) > 50) { show(index + (dx < 0 ? 1 : -1)); }
		});
	}

	function show(i) {
		if (!current.length) { return; }
		index = (i + current.length) % current.length; // wrap around
		var a = current[index];
		imgEl.src = a.getAttribute('data-full') || a.href;
		imgEl.alt = (a.querySelector('img') || {}).alt || '';
		var cap = a.getAttribute('data-caption') || '';
		capEl.textContent = cap;
		capEl.hidden = !cap;
		counter.textContent = (index + 1) + ' / ' + current.length;
		var single = current.length < 2;
		overlay.querySelector('.pri-lb-prev').hidden = single;
		overlay.querySelector('.pri-lb-next').hidden = single;
		counter.hidden = single;
	}

	function open(group, i) {
		if (!overlay) { build(); }
		current = group;
		lastFocus = document.activeElement;
		overlay.hidden = false;
		document.documentElement.classList.add('pri-lb-open'); // stops the page scrolling behind
		show(i);
		overlay.querySelector('.pri-lb-close').focus();
	}

	function close() {
		overlay.hidden = true;
		imgEl.removeAttribute('src');
		document.documentElement.classList.remove('pri-lb-open');
		if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
	}

	document.addEventListener('click', function (e) {
		var a = e.target.closest ? e.target.closest('.pri-photos a.pri-photo') : null;
		if (!a || e.ctrlKey || e.metaKey || e.shiftKey) { return; } // let "open in new tab" work
		e.preventDefault();
		var group = Array.prototype.slice.call(a.parentNode.querySelectorAll('a.pri-photo'));
		open(group, group.indexOf(a));
	});
})();
