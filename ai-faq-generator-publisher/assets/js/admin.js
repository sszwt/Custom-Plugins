/**
 * AI FAQ Generator — Admin app.
 */
(function () {
	'use strict';

	var cfg = window.AIFAQ || {};
	var root = document.querySelector('[data-aifaq-app]');
	if (!root) return;

	var state = {
		items: [],
		sets: Array.isArray(cfg.sets) ? cfg.sets : [],
	};

	var titles = {
		generate: {
			title: 'Generate FAQs',
			lead: 'Describe a topic, generate Q&As with AI, edit, then publish with a slide accordion shortcode.',
		},
		library: {
			title: 'FAQ Library',
			lead: 'Edit published sets, copy shortcodes, or remove outdated FAQs.',
		},
		settings: {
			title: 'Settings',
			lead: 'Connect OpenAI or Gemini and tune accordion defaults for the frontend.',
		},
	};

	function $(sel, ctx) {
		return (ctx || root).querySelector(sel);
	}

	function $all(sel, ctx) {
		return Array.prototype.slice.call((ctx || root).querySelectorAll(sel));
	}

	function val(name) {
		var el = $('[data-field="' + name + '"]');
		if (!el) return '';
		if (el.type === 'checkbox') return el.checked ? 1 : 0;
		if (el.type === 'radio') {
			var checked = $('input[data-field="' + name + '"]:checked');
			return checked ? checked.value : '';
		}
		return el.value;
	}

	function setVal(name, value) {
		var el = $('[data-field="' + name + '"]');
		if (!el) return;
		if (el.type === 'checkbox') {
			el.checked = !!value;
			return;
		}
		el.value = value == null ? '' : String(value);
	}

	function toast(msg, isError) {
		var view = root.getAttribute('data-view') || 'generate';
		var el = view === 'generate' ? $('[data-toast]') : $('[data-toast-float]');
		if (!el) el = $('[data-toast]') || $('[data-toast-float]');
		if (!el) return;
		el.hidden = false;
		el.textContent = msg;
		el.classList.toggle('is-error', !!isError);
		try {
			el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
		} catch (e) { /* ignore */ }
		clearTimeout(toast._t);
		toast._t = setTimeout(function () {
			el.hidden = true;
		}, 3800);
	}

	function syncProviderPanels() {
		var checked = document.querySelector('input[name="aifaq_provider"]:checked');
		var provider = checked ? checked.value : 'openai';
		$all('[data-provider-panel]').forEach(function (panel) {
			var match = panel.getAttribute('data-provider-panel') === provider;
			panel.hidden = !match;
			panel.classList.toggle('is-active', match);
		});
	}

	function post(action, data) {
		var body = new FormData();
		body.append('action', action);
		body.append('nonce', cfg.nonce || '');
		Object.keys(data || {}).forEach(function (k) {
			var v = data[k];
			if (typeof v === 'object') {
				body.append(k, JSON.stringify(v));
			} else {
				body.append(k, v);
			}
		});
		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		}).then(function (r) {
			return r.json();
		});
	}

	function setLoading(btn, on) {
		if (!btn) return;
		btn.classList.toggle('is-loading', !!on);
	}

	function switchView(view) {
		$all('[data-nav]').forEach(function (b) {
			b.classList.toggle('is-active', b.getAttribute('data-nav') === view);
		});
		$all('[data-panel]').forEach(function (p) {
			p.classList.toggle('is-active', p.getAttribute('data-panel') === view);
		});
		var meta = titles[view] || titles.generate;
		var t = $('[data-page-title]');
		var l = $('[data-page-lead]');
		if (t) t.textContent = meta.title;
		if (l) l.textContent = meta.lead;
		var steps = $('[data-steps]');
		if (steps) steps.hidden = view !== 'generate';
		root.setAttribute('data-view', view);
		try {
			var url = new URL(window.location.href);
			url.searchParams.set('page', 'aifaq');
			url.searchParams.set('view', view);
			window.history.replaceState({}, '', url.toString());
		} catch (e) { /* ignore */ }
	}

	function renderEditor() {
		var editor = $('[data-editor]');
		var empty = $('[data-editor-empty]');
		var count = $('[data-item-count]');
		if (!editor) return;

		$all('.aifaq-item', editor).forEach(function (n) {
			n.remove();
		});

		if (count) count.textContent = String(state.items.length);

		if (!state.items.length) {
			if (empty) empty.hidden = false;
			return;
		}
		if (empty) empty.hidden = true;

		state.items.forEach(function (item, i) {
			var row = document.createElement('div');
			row.className = 'aifaq-item';
			row.innerHTML =
				'<div class="aifaq-item__bar">' +
				'<strong>Q' + (i + 1) + '</strong>' +
				'<button type="button" data-remove="' + i + '">Remove</button>' +
				'</div>' +
				'<input type="text" data-q="' + i + '" placeholder="Question" />' +
				'<textarea rows="3" data-a="' + i + '" placeholder="Answer"></textarea>';
			editor.appendChild(row);
			row.querySelector('[data-q]').value = item.question || '';
			row.querySelector('[data-a]').value = item.answer || '';
		});
	}

	function syncItemsFromDom() {
		var next = [];
		$all('[data-q]').forEach(function (qEl) {
			var i = parseInt(qEl.getAttribute('data-q'), 10);
			var aEl = $('[data-a="' + i + '"]');
			next.push({
				question: qEl.value || '',
				answer: aEl ? aEl.value : '',
			});
		});
		state.items = next;
	}

	function renderLibrary() {
		var lib = $('[data-library]');
		var badge = $('[data-lib-count]');
		if (badge) badge.textContent = String(state.sets.length);
		if (!lib) return;

		if (!state.sets.length) {
			lib.innerHTML =
				'<div class="aifaq-empty"><p>' +
				(cfg.i18n && cfg.i18n.emptyLib ? cfg.i18n.emptyLib : 'No FAQ sets yet.') +
				'</p></div>';
			return;
		}

		lib.innerHTML = state.sets
			.map(function (set) {
				return (
					'<article class="aifaq-lib-card" data-set-id="' +
					set.id +
					'">' +
					'<div class="aifaq-lib-card__main">' +
					'<div class="aifaq-lib-card__title-row">' +
					'<h3>' +
					escapeHtml(set.title) +
					'</h3>' +
					'<span class="aifaq-status aifaq-status--' +
					escapeHtml(set.status) +
					'">' +
					escapeHtml(set.status) +
					'</span>' +
					'</div>' +
					'<p>' +
					'<span>' +
					set.count +
					' questions</span>' +
					'<span class="aifaq-dot" aria-hidden="true"></span>' +
					'<span class="aifaq-muted">' +
					escapeHtml(set.modified || '') +
					'</span>' +
					'</p>' +
					'<code>' +
					escapeHtml(set.shortcode) +
					'</code>' +
					'</div>' +
					'<div class="aifaq-lib-card__actions">' +
					'<button type="button" class="aifaq-btn aifaq-btn--sm" data-action="edit-set" data-id="' +
					set.id +
					'">Edit</button>' +
					'<button type="button" class="aifaq-btn aifaq-btn--sm aifaq-btn--dark" data-action="copy-lib" data-code="' +
					escapeAttr(set.shortcode) +
					'">Copy</button>' +
					'<button type="button" class="aifaq-btn aifaq-btn--sm aifaq-btn--danger" data-action="delete-set" data-id="' +
					set.id +
					'">Delete</button>' +
					'</div>' +
					'</article>'
				);
			})
			.join('');
	}

	function escapeHtml(s) {
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function escapeAttr(s) {
		return escapeHtml(s).replace(/'/g, '&#39;');
	}

	function copyText(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			return navigator.clipboard.writeText(text);
		}
		var ta = document.createElement('textarea');
		ta.value = text;
		document.body.appendChild(ta);
		ta.select();
		document.execCommand('copy');
		ta.remove();
		return Promise.resolve();
	}

	function showShortcode(code) {
		var box = $('[data-shortcode-box]');
		var codeEl = $('[data-shortcode]');
		if (box) box.hidden = false;
		if (codeEl) codeEl.textContent = code;
	}

	function loadSetIntoEditor(data) {
		setVal('set-id', data.id || 0);
		setVal('title', data.title || '');
		state.items = Array.isArray(data.items) ? data.items : [];
		renderEditor();
		if (data.id) {
			showShortcode('[ai_faq id="' + data.id + '"]');
		}
		switchView('generate');
	}

	function saveSet(status) {
		syncItemsFromDom();
		if (!state.items.length) {
			toast('Add at least one FAQ before saving.', true);
			return;
		}
		var btn = $('[data-action="' + (status === 'draft' ? 'save-draft' : 'publish') + '"]');
		setLoading(btn, true);
		post('aifaq_save_set', {
			id: val('set-id') || 0,
			title: val('title') || 'Frequently Asked Questions',
			status: status,
			items: state.items,
		})
			.then(function (res) {
				setLoading(btn, false);
				if (!res || !res.success) {
					toast((res && res.data && res.data.message) || cfg.i18n.error, true);
					return;
				}
				setVal('set-id', res.data.id);
				state.sets = res.data.sets || [];
				renderLibrary();
				showShortcode(res.data.shortcode);
				toast(res.data.message || cfg.i18n.saved);
			})
			.catch(function () {
				setLoading(btn, false);
				toast(cfg.i18n.error, true);
			});
	}

	/* Events */
	$all('[data-nav]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			switchView(btn.getAttribute('data-nav'));
		});
	});

	root.addEventListener('click', function (e) {
		var t = e.target.closest('[data-action], [data-remove]');
		if (!t) return;

		var action = t.getAttribute('data-action');
		var remove = t.getAttribute('data-remove');

		if (remove != null) {
			syncItemsFromDom();
			state.items.splice(parseInt(remove, 10), 1);
			renderEditor();
			return;
		}

		if (action === 'generate') {
			var btn = t;
			setLoading(btn, true);
			post('aifaq_generate', {
				topic: val('topic'),
				count: val('count'),
				tone: val('tone'),
				extra: val('extra'),
			})
				.then(function (res) {
					setLoading(btn, false);
					if (!res || !res.success) {
						toast((res && res.data && res.data.message) || cfg.i18n.error, true);
						return;
					}
					state.items = res.data.items || [];
					if (!val('title')) {
						setVal('title', 'Frequently Asked Questions');
					}
					renderEditor();
					toast('Generated ' + state.items.length + ' FAQs');
				})
				.catch(function () {
					setLoading(btn, false);
					toast(cfg.i18n.error, true);
				});
			return;
		}

		if (action === 'add-item') {
			syncItemsFromDom();
			state.items.push({ question: '', answer: '' });
			renderEditor();
			return;
		}

		if (action === 'save-draft') {
			saveSet('draft');
			return;
		}

		if (action === 'publish') {
			saveSet('publish');
			return;
		}

		if (action === 'copy-shortcode') {
			var code = ($('[data-shortcode]') || {}).textContent || '';
			copyText(code).then(function () {
				toast(cfg.i18n.copied || 'Copied');
			});
			return;
		}

		if (action === 'copy-lib') {
			copyText(t.getAttribute('data-code') || '').then(function () {
				toast(cfg.i18n.copied || 'Copied');
			});
			return;
		}

		if (action === 'edit-set') {
			var id = t.getAttribute('data-id');
			post('aifaq_get_set', { id: id }).then(function (res) {
				if (!res || !res.success) {
					toast((res && res.data && res.data.message) || cfg.i18n.error, true);
					return;
				}
				loadSetIntoEditor(res.data);
			});
			return;
		}

		if (action === 'delete-set') {
			if (!window.confirm(cfg.i18n.confirmDel || 'Delete?')) return;
			post('aifaq_delete_set', { id: t.getAttribute('data-id') }).then(function (res) {
				if (!res || !res.success) {
					toast((res && res.data && res.data.message) || cfg.i18n.error, true);
					return;
				}
				state.sets = res.data.sets || [];
				renderLibrary();
				toast(res.data.message || 'Deleted');
			});
			return;
		}

		if (action === 'save-settings') {
			var sbtn = t;
			setLoading(sbtn, true);
			post('aifaq_save_settings', {
				provider: val('provider') || (document.querySelector('input[name="aifaq_provider"]:checked') || {}).value || 'openai',
				openai_key: val('openai_key'),
				openai_model: val('openai_model'),
				gemini_key: val('gemini_key'),
				gemini_model: val('gemini_model'),
				default_count: val('default_count'),
				default_tone: val('default_tone'),
				open_first: val('open_first'),
				allow_multiple: val('allow_multiple'),
				show_schema: val('show_schema'),
			})
				.then(function (res) {
					setLoading(sbtn, false);
					if (!res || !res.success) {
						toast((res && res.data && res.data.message) || cfg.i18n.error, true);
						return;
					}
					toast(res.data.message || cfg.i18n.saved);
				})
				.catch(function () {
					setLoading(sbtn, false);
					toast(cfg.i18n.error, true);
				});
		}
	});

	/* Provider tabs — show only selected provider fields */
	$all('input[name="aifaq_provider"]').forEach(function (r) {
		r.setAttribute('data-field', 'provider');
		r.addEventListener('change', syncProviderPanels);
	});
	syncProviderPanels();

	/* Initial view from URL */
	var initial = root.getAttribute('data-view') || 'generate';
	switchView(initial);
	renderEditor();
})();
