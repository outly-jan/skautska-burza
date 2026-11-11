/* Příspěvky z Facebooku: „Zobrazit více“ u dlouhých textů a prohlížeč fotek/videí. */
(function () {
	'use strict';

	// ── Zkrácení dlouhých textů ──
	document.querySelectorAll('.skaut-fb-text').forEach(function (text) {
		text.classList.add('je-zkraceny');
		if (text.scrollHeight <= text.clientHeight + 4) {
			text.classList.remove('je-zkraceny');
			return;
		}
		var tlacitko = document.createElement('button');
		tlacitko.type = 'button';
		tlacitko.className = 'skaut-fb-vice';
		tlacitko.textContent = 'Zobrazit více';
		tlacitko.addEventListener('click', function () {
			var zkraceny = text.classList.toggle('je-zkraceny');
			tlacitko.textContent = zkraceny ? 'Zobrazit více' : 'Zobrazit méně';
		});
		text.insertAdjacentElement('afterend', tlacitko);
	});

	// ── Prohlížeč ──
	var box, obsah, pocitadlo, polozky = [], index = 0;

	function vytvorit() {
		box = document.createElement('div');
		box.className = 'skaut-fb-lightbox';
		box.hidden = true;
		box.setAttribute('role', 'dialog');
		box.setAttribute('aria-modal', 'true');
		box.innerHTML =
			'<div class="skaut-fb-lightbox-obsah"></div>' +
			'<button type="button" class="skaut-fb-lightbox-zavrit" aria-label="Zavřít">&times;</button>' +
			'<button type="button" class="skaut-fb-lightbox-predchozi" aria-label="Předchozí">&#8249;</button>' +
			'<button type="button" class="skaut-fb-lightbox-dalsi" aria-label="Další">&#8250;</button>' +
			'<div class="skaut-fb-lightbox-pocitadlo"></div>';
		document.body.appendChild(box);
		obsah = box.querySelector('.skaut-fb-lightbox-obsah');
		pocitadlo = box.querySelector('.skaut-fb-lightbox-pocitadlo');

		box.querySelector('.skaut-fb-lightbox-zavrit').addEventListener('click', zavrit);
		box.querySelector('.skaut-fb-lightbox-predchozi').addEventListener('click', function () { posun(-1); });
		box.querySelector('.skaut-fb-lightbox-dalsi').addEventListener('click', function () { posun(1); });
		box.addEventListener('click', function (e) {
			if (e.target === box || e.target === obsah) zavrit();
		});

		var startX = null;
		box.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
		box.addEventListener('touchend', function (e) {
			if (startX === null) return;
			var dx = e.changedTouches[0].clientX - startX;
			if (Math.abs(dx) > 50) posun(dx < 0 ? 1 : -1);
			startX = null;
		});
	}

	function zobrazit() {
		var a = polozky[index];
		obsah.innerHTML = '';
		var el;
		if (a.getAttribute('data-skaut-fb-typ') === 'video') {
			el = document.createElement('video');
			el.controls = true;
			el.autoplay = true;
			el.playsInline = true;
			var poster = a.getAttribute('data-skaut-fb-poster');
			if (poster) el.poster = poster;
		} else {
			el = document.createElement('img');
			el.alt = '';
		}
		el.src = a.href;
		obsah.appendChild(el);

		var vice = polozky.length > 1;
		box.querySelector('.skaut-fb-lightbox-predchozi').hidden = !vice;
		box.querySelector('.skaut-fb-lightbox-dalsi').hidden = !vice;
		pocitadlo.textContent = vice ? (index + 1) + ' / ' + polozky.length : '';
	}

	function posun(o) {
		index = (index + o + polozky.length) % polozky.length;
		zobrazit();
	}

	function zavrit() {
		box.hidden = true;
		obsah.innerHTML = '';
		document.documentElement.style.overflow = '';
	}

	document.addEventListener('click', function (e) {
		var a = e.target.closest && e.target.closest('a[data-skaut-fb-typ]');
		if (!a) return;
		e.preventDefault();
		if (!box) vytvorit();
		polozky = Array.prototype.slice.call(
			a.closest('.skaut-fb-media').querySelectorAll('a[data-skaut-fb-typ]')
		);
		index = polozky.indexOf(a);
		box.hidden = false;
		document.documentElement.style.overflow = 'hidden';
		zobrazit();
		box.querySelector('.skaut-fb-lightbox-zavrit').focus();
	});

	document.addEventListener('keydown', function (e) {
		if (!box || box.hidden) return;
		if (e.key === 'Escape') zavrit();
		else if (e.key === 'ArrowLeft') posun(-1);
		else if (e.key === 'ArrowRight') posun(1);
	});
})();
