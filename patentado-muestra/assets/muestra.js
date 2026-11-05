/* Patentado – Clase Muestra: video, PDF, quiz y celebración. */
(function () {
	'use strict';
	if (!window.PM) { return; }

	var SEG = 100;
	var segments = (PM.segments || '').padEnd(SEG, '0').slice(0, SEG).split('');
	var dirty = false;

	function post(action, data) {
		var body = new FormData();
		body.append('action', action);
		body.append('nonce', PM.nonce);
		Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
		return fetch(PM.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json(); });
	}

	post('pm_track', { what: 'start' });

	/* ---------- Video: registra los tramos vistos (adelantar/retroceder es libre) ---------- */
	function mark(current, duration) {
		if (!duration || !isFinite(duration)) { return; }
		var i = Math.min(SEG - 1, Math.floor(current / duration * SEG));
		if (segments[i] !== '1') { segments[i] = '1'; dirty = true; render(); }
	}
	function pct() { return segments.filter(function (s) { return s === '1'; }).length; }
	function render() {
		var p = pct();
		var bar = document.getElementById('pm-video-bar');
		var txt = document.getElementById('pm-video-pct');
		if (bar) { bar.style.width = p + '%'; }
		if (txt) { txt.textContent = p; }
	}
	function flush() {
		if (!dirty) { return; }
		dirty = false;
		var data = new FormData();
		data.append('action', 'pm_track');
		data.append('nonce', PM.nonce);
		data.append('what', 'video');
		data.append('segments', segments.join(''));
		if (navigator.sendBeacon && document.visibilityState === 'hidden') {
			navigator.sendBeacon(PM.ajax, data);
		} else {
			fetch(PM.ajax, { method: 'POST', body: data, credentials: 'same-origin' });
		}
	}
	setInterval(flush, 10000);
	document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') { flush(); } });
	window.addEventListener('pagehide', flush);

	var v = PM.video || {};
	if (v.type === 'file') {
		var el = document.getElementById('pm-html5');
		if (el) {
			el.addEventListener('timeupdate', function () { if (!el.paused) { mark(el.currentTime, el.duration); } });
			el.addEventListener('pause', flush);
			el.addEventListener('ended', function () { mark(el.duration - 0.1, el.duration); flush(); });
		}
	} else if (v.type === 'vimeo') {
		var startVimeo = function () {
			var frame = document.getElementById('pm-vimeo');
			if (!frame || !window.Vimeo) { return setTimeout(startVimeo, 300); }
			var player = new window.Vimeo.Player(frame);
			player.on('timeupdate', function (d) { mark(d.seconds, d.duration); });
			player.on('pause', flush);
			player.on('ended', function () { flush(); });
		};
		startVimeo();
	} else if (v.type === 'youtube') {
		var yt, timer;
		var prev = window.onYouTubeIframeAPIReady;
		window.onYouTubeIframeAPIReady = function () {
			if (prev) { prev(); }
			yt = new window.YT.Player('pm-yt', {
				videoId: v.id,
				playerVars: { rel: 0, modestbranding: 1, playsinline: 1 },
				events: {
					onStateChange: function (e) {
						clearInterval(timer);
						if (e.data === window.YT.PlayerState.PLAYING) {
							timer = setInterval(function () { mark(yt.getCurrentTime(), yt.getDuration()); }, 1000);
						} else {
							flush();
						}
					}
				}
			});
		};
		var s = document.createElement('script');
		s.src = 'https://www.youtube.com/iframe_api';
		document.head.appendChild(s);
	}

	/* ---------- PDF ---------- */
	var toggle = document.getElementById('pm-pdf-toggle');
	var pdf = document.getElementById('pm-pdf');
	if (toggle && pdf) {
		toggle.addEventListener('click', function () {
			var frame = pdf.querySelector('iframe');
			if (!frame.src) { frame.src = frame.getAttribute('data-src'); }
			pdf.hidden = !pdf.hidden;
			toggle.textContent = pdf.hidden ? 'Ver PDF aquí' : 'Ocultar PDF';
			if (!pdf.hidden) { post('pm_track', { what: 'pdf_viewed' }); }
		});
	}
	document.querySelectorAll('[data-pm-track]').forEach(function (a) {
		a.addEventListener('click', function () { post('pm_track', { what: a.getAttribute('data-pm-track') }); });
	});

	/* ---------- Quiz ---------- */
	var form = document.getElementById('pm-quiz');
	if (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var answers = {};
			var missing = false;
			form.querySelectorAll('.pm-q').forEach(function (fs) {
				var q = fs.getAttribute('data-q');
				var picked = Array.prototype.map.call(fs.querySelectorAll('input:checked'), function (i) { return parseInt(i.value, 10); });
				fs.classList.remove('is-right', 'is-wrong', 'is-missing');
				if (!picked.length) { missing = true; fs.classList.add('is-missing'); }
				answers[q] = picked;
			});
			var out = document.getElementById('pm-quiz-result');
			if (missing) { out.innerHTML = '<p class="pm-error">Responde todas las preguntas antes de enviar.</p>'; return; }

			var btn = form.querySelector('button[type=submit]');
			btn.disabled = true;
			post('pm_quiz', { answers: JSON.stringify(answers) }).then(function (r) {
				btn.disabled = false;
				if (!r || !r.success) { out.innerHTML = '<p class="pm-error">No pudimos guardar tus respuestas. Inténtalo de nuevo.</p>'; return; }
				var d = r.data;
				Object.keys(d.detail).forEach(function (q) {
					var fs = form.querySelector('.pm-q[data-q="' + q + '"]');
					if (fs) { fs.classList.add(d.detail[q] ? 'is-right' : 'is-wrong'); }
				});
				if (d.passed) {
					out.innerHTML = '<p class="pm-ok">¡Aprobaste con ' + d.score + '%! (' + d.correct + ' de ' + d.total + ')</p>';
					celebrate();
				} else {
					out.innerHTML = '<p class="pm-error">Obtuviste ' + d.score + '% (' + d.correct + ' de ' + d.total + '). Necesitas ' + PM.passScore + '% para aprobar. Revisa las preguntas marcadas e inténtalo de nuevo.</p>';
				}
			}).catch(function () {
				btn.disabled = false;
				out.innerHTML = '<p class="pm-error">Error de conexión. Inténtalo de nuevo.</p>';
			});
		});
	}

	/* ---------- Celebración ---------- */
	function celebrate() {
		var box = document.getElementById('pm-celebrate');
		if (!box) { return; }
		box.hidden = false;
		document.body.style.overflow = 'hidden';
		box.addEventListener('click', function (e) {
			if (e.target === box) { box.hidden = true; document.body.style.overflow = ''; }
		});
		if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
		var c = document.getElementById('pm-confetti');
		var ctx = c.getContext('2d');
		c.width = window.innerWidth; c.height = window.innerHeight;
		var colors = [getComputedStyle(box).getPropertyValue('--pm-accent') || '#c9a227', '#ffffff', '#4f8cff', '#ff6b6b', '#38c172'];
		var parts = [];
		for (var i = 0; i < 180; i++) {
			parts.push({ x: Math.random() * c.width, y: -20 - Math.random() * c.height, r: 4 + Math.random() * 6,
				vy: 2 + Math.random() * 3, vx: -1 + Math.random() * 2, a: Math.random() * 6, color: colors[i % colors.length] });
		}
		var start = Date.now();
		(function frame() {
			ctx.clearRect(0, 0, c.width, c.height);
			parts.forEach(function (p) {
				p.y += p.vy; p.x += p.vx; p.a += 0.1;
				ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.a);
				ctx.fillStyle = p.color; ctx.fillRect(-p.r / 2, -p.r / 2, p.r, p.r * 0.6);
				ctx.restore();
			});
			if (Date.now() - start < 6000) { requestAnimationFrame(frame); } else { ctx.clearRect(0, 0, c.width, c.height); }
		})();
	}
})();
