/* [pri_programs] - filter pills and "Load More". Plain JavaScript, no libraries. */
(function () {
	function init(root) {
		var limit = parseInt(root.getAttribute('data-limit'), 10) || 6;
		var cards = Array.prototype.slice.call(root.querySelectorAll('.pri-card'));
		var pills = Array.prototype.slice.call(root.querySelectorAll('.pri-pill'));
		var moreBtn = root.querySelector('.pri-load-more');
		var emptyMsg = root.querySelector('.pri-empty');

		var filter = root.getAttribute('data-default') || 'all';
		var shown = limit; // how many matching cards are currently visible

		// filter looks like "all", "status:upcoming" or "type:retreat"
		function matches(card) {
			if (filter === 'all') { return true; }
			var parts = filter.split(':');
			if (parts[0] === 'status') { return card.getAttribute('data-status') === parts[1]; }
			// Wrap in spaces so "art" does not match "smart".
			return (' ' + card.getAttribute('data-types') + ' ').indexOf(' ' + parts[1] + ' ') !== -1;
		}

		function apply() {
			var visible = 0;
			var total = 0;
			cards.forEach(function (card) {
				var ok = matches(card);
				if (ok) { total++; }
				// A card shows only if it matches AND is within the current "page" of results.
				var show = ok && visible < shown;
				if (show) { visible++; }
				card.hidden = !show;
			});
			pills.forEach(function (pill) {
				var active = pill.getAttribute('data-filter') === filter;
				pill.classList.toggle('is-active', active);
				pill.setAttribute('aria-pressed', active ? 'true' : 'false');
			});
			if (moreBtn) { moreBtn.hidden = visible >= total; }
			if (emptyMsg) { emptyMsg.hidden = total !== 0; }
		}

		pills.forEach(function (pill) {
			pill.addEventListener('click', function () {
				filter = pill.getAttribute('data-filter');
				shown = limit; // a new filter starts again from the first page
				apply();
			});
		});
		if (moreBtn) {
			moreBtn.addEventListener('click', function () {
				shown += limit;
				apply();
			});
		}
		apply();
	}

	// Enquiry form: disable the button once sending starts, so a double click cannot send two emails.
	function initForm(form) {
		var btn = form.querySelector('.pri-submit');
		if (!btn) { return; }
		form.addEventListener('submit', function () {
			// Deferred: a disabled button's name/value would be left out of the posted data.
			setTimeout(function () { btn.disabled = true; btn.textContent = 'Sending…'; }, 0);
		});
		// Coming back with the browser's Back button must not leave the button stuck.
		window.addEventListener('pageshow', function () { btn.disabled = false; btn.textContent = btn.getAttribute('data-label') || btn.textContent; });
		btn.setAttribute('data-label', btn.textContent);
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('.pri-programs'), init);
		Array.prototype.forEach.call(document.querySelectorAll('.pri-enquiry-form'), initForm);
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
