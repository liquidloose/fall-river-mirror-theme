/**
 * Sticky player: uses the existing in-page YouTube iframe.
 * Click timestamp → set iframe src (start + autoplay) → fix embed to viewport corner.
 */
(function () {
	'use strict';

	var LINK_CLASS = 'video-jump-link';
	var EMBED_MARK = '[data-fr-mirror-youtube-embed]';
	var SLOT_CLASS = 'fr-mirror-video-slot';
	var STICKY_CLASS = 'fr-mirror-youtube-embed--sticky';
	var PLACEHOLDER_CLASS = 'fr-mirror-video-slot__placeholder';

	var config = window.frMirrorStickyVideo || {};
	var embedEl = null;
	var slotEl = null;
	var placeholderEl = null;
	var closeBtn = null;
	var videoId = '';
	var isSticky = false;

	function parseSeconds(href) {
		try {
			var url = new URL(href, window.location.href);
			var t = url.searchParams.get('t');
			if (!t && url.hash.indexOf('t=') === 1) {
				t = url.hash.slice(2);
			}
			if (!t) {
				return 0;
			}
			return parseInt(String(t).replace(/s$/i, ''), 10) || 0;
		} catch (e) {
			return 0;
		}
	}

	function getIframe() {
		return embedEl ? embedEl.querySelector('iframe') : null;
	}

	function resolveVideoId() {
		if (config.youtubeId) {
			return config.youtubeId;
		}
		if (embedEl && embedEl.getAttribute('data-youtube-id')) {
			return embedEl.getAttribute('data-youtube-id');
		}
		var iframe = getIframe();
		if (iframe) {
			var m = (iframe.getAttribute('src') || '').match(/\/embed\/([^?&/]+)/);
			if (m) {
				return m[1];
			}
		}
		var link = document.querySelector('a.' + LINK_CLASS + '[href*="v="]');
		if (link) {
			try {
				return new URL(link.href, window.location.href).searchParams.get('v') || '';
			} catch (e) {
				return '';
			}
		}
		return '';
	}

	function findEmbed() {
		var el = document.querySelector(EMBED_MARK);
		if (el) {
			return el;
		}
		var iframe = document.querySelector(
			'.wp-block-post-content iframe[src*="youtube.com/embed"], main iframe[src*="youtube.com/embed"]'
		);
		if (!iframe) {
			return null;
		}
		return (
			iframe.closest('figure.wp-block-embed') ||
			iframe.closest('.wp-block-embed') ||
			iframe.parentElement
		);
	}

	function embedSrc(seconds) {
		return (
			'https://www.youtube.com/embed/' +
			encodeURIComponent(videoId) +
			'?start=' +
			Math.max(0, seconds) +
			'&autoplay=1&playsinline=1&rel=0'
		);
	}

	function seekAndPlay(seconds) {
		var iframe = getIframe();
		if (!iframe || !videoId) {
			return;
		}
		iframe.src = embedSrc(seconds);
	}

	function idleEmbedSrc() {
		var src =
			'https://www.youtube.com/embed/' +
			encodeURIComponent(videoId) +
			'?rel=0&playsinline=1&enablejsapi=1&origin=' +
			encodeURIComponent(window.location.origin);
		return src;
	}

	function stopVideo() {
		var iframe = getIframe();
		if (!iframe || !videoId) {
			return;
		}
		iframe.src = idleEmbedSrc();
	}

	function wrapSlot() {
		if (!embedEl) {
			return;
		}
		// Already portaled to body on a prior stick — keep the original slot.
		if (embedEl.parentElement === document.body) {
			return;
		}
		if (
			embedEl.parentElement &&
			embedEl.parentElement.classList.contains(SLOT_CLASS)
		) {
			slotEl = embedEl.parentElement;
			return;
		}
		slotEl = document.createElement('div');
		slotEl.className = SLOT_CLASS;
		embedEl.parentNode.insertBefore(slotEl, embedEl);
		slotEl.appendChild(embedEl);
	}

	function portalToBody() {
		if (!embedEl || embedEl.parentElement === document.body) {
			return;
		}
		document.body.appendChild(embedEl);
	}

	function restoreToSlot() {
		if (!slotEl || !embedEl || embedEl.parentElement !== document.body) {
			return;
		}
		if (placeholderEl && placeholderEl.parentElement === slotEl) {
			slotEl.insertBefore(embedEl, placeholderEl.nextSibling);
		} else {
			slotEl.appendChild(embedEl);
		}
	}

	function removeCloseButton() {
		if (closeBtn && closeBtn.parentNode) {
			closeBtn.parentNode.removeChild(closeBtn);
		}
		closeBtn = null;
	}

	function showPlaceholder(heightPx) {
		if (!slotEl) {
			return;
		}
		if (!placeholderEl) {
			placeholderEl = document.createElement('div');
			placeholderEl.className = PLACEHOLDER_CLASS;
			placeholderEl.setAttribute('aria-hidden', 'true');
			slotEl.insertBefore(placeholderEl, slotEl.firstChild);
		}
		placeholderEl.style.minHeight = Math.max(heightPx, 180) + 'px';
		placeholderEl.hidden = false;
	}

	function hidePlaceholder() {
		if (placeholderEl) {
			placeholderEl.hidden = true;
			placeholderEl.style.minHeight = '';
		}
	}

	function ensureCloseButton() {
		if (!embedEl || closeBtn) {
			return;
		}
		closeBtn = document.createElement('button');
		closeBtn.type = 'button';
		closeBtn.className = 'fr-mirror-sticky-video__close';
		closeBtn.setAttribute('aria-label', 'Close video');
		closeBtn.innerHTML =
			'<span class="fr-mirror-sticky-video__close-icon" aria-hidden="true">&times;</span>';
		closeBtn.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			unstick();
		});
		embedEl.appendChild(closeBtn);
	}

	function stickAt(seconds) {
		embedEl = findEmbed();
		videoId = resolveVideoId();
		if (!embedEl || !videoId) {
			return;
		}

		wrapSlot();
		seekAndPlay(seconds);

		if (!isSticky) {
			var rect = embedEl.getBoundingClientRect();
			showPlaceholder(rect.height);
			portalToBody();
			embedEl.style.top = rect.top + 'px';
			embedEl.style.left = rect.left + 'px';
			embedEl.style.width = Math.max(rect.width, 280) + 'px';
			embedEl.style.right = 'auto';
			embedEl.style.bottom = 'auto';
			embedEl.classList.add(STICKY_CLASS);
			ensureCloseButton();
			isSticky = true;

			requestAnimationFrame(function () {
				requestAnimationFrame(function () {
					embedEl.classList.add('is-settled');
				});
			});
		}
	}

	function unstick() {
		if (!embedEl) {
			return;
		}
		stopVideo();
		isSticky = false;
		embedEl.classList.remove(STICKY_CLASS, 'is-settled', 'is-animating');
		embedEl.style.top = '';
		embedEl.style.left = '';
		embedEl.style.width = '';
		embedEl.style.right = '';
		embedEl.style.bottom = '';
		removeCloseButton();
		restoreToSlot();
		hidePlaceholder();
	}

	function isTimestampLink(link) {
		if (!link || !link.href) {
			return false;
		}
		if (link.classList.contains(LINK_CLASS)) {
			return true;
		}
		return (
			/youtube\.com\/watch|youtu\.be\//.test(link.href) &&
			/[?&#]t=\d/i.test(link.href)
		);
	}

	function onClick(event) {
		var link = event.target.closest('a');
		if (!isTimestampLink(link)) {
			return;
		}
		event.preventDefault();
		event.stopImmediatePropagation();
		stickAt(parseSeconds(link.href));
	}

	function onKeyDown(event) {
		if (event.key === 'Escape' && isSticky) {
			event.preventDefault();
			unstick();
		}
	}

	function init() {
		embedEl = findEmbed();
		videoId = resolveVideoId();
		document.addEventListener('click', onClick, true);
		document.addEventListener('keydown', onKeyDown);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
