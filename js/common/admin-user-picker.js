/**
 * Searchable admin employee picker (combobox) — uses lightweight user search API.
 *
 * @license AGPL-3.0-or-later
 */
(function () {
	'use strict';

	const Utils = window.ArbeitszeitCheckUtils || {};

	/**
	 * @param {object} options
	 * @param {string} options.hiddenSelector Hidden input storing userId
	 * @param {string} options.searchSelector Visible combobox input
	 * @param {string} options.listSelector Listbox container element
	 * @param {string} options.wrapSelector Container for outside-click close
	 * @param {string} options.searchUrl Base URL for GET ?search=&limit=
	 * @param {number} [options.limit=15]
	 * @param {object} [options.l10n]
	 * @param {function(string): void} [options.onChange] Called when selection cleared or set
	 */
	function initAdminUserPicker(options) {
		const hidden = Utils.$(options.hiddenSelector);
		const search = Utils.$(options.searchSelector);
		const list = Utils.$(options.listSelector);
		const wrap = Utils.$(options.wrapSelector);
		const baseUrl = options.searchUrl || '';
		const l10n = options.l10n || {};
		const limit = options.limit || 15;
		const onChange = typeof options.onChange === 'function' ? options.onChange : function () {};

		if (!hidden || !search || !list || !baseUrl) {
			return null;
		}

		let debounceTimer = null;
		let selectedLabel = '';
		let activeIndex = -1;

		function closeList() {
			list.hidden = true;
			list.innerHTML = '';
			search.setAttribute('aria-expanded', 'false');
			activeIndex = -1;
		}

		function openList() {
			list.hidden = false;
			search.setAttribute('aria-expanded', 'true');
		}

		function showLoading() {
			const msg = l10n.loading || 'Loading…';
			list.innerHTML = '<li class="user-picker__item user-picker__item--muted" role="presentation">'
				+ (Utils.escapeHtml ? Utils.escapeHtml(msg) : msg) + '</li>';
			openList();
		}

		function fetchUsers(query) {
			const q = typeof query === 'string' ? query.trim() : '';
			const params = new URLSearchParams({ limit: String(limit) });
			if (q !== '') {
				params.set('search', q);
			}
			const sep = baseUrl.indexOf('?') >= 0 ? '&' : '?';
			const url = baseUrl + sep + params.toString();
			showLoading();
			Utils.ajax(url, {
				method: 'GET',
				onSuccess: function (data) {
					if (!data || !data.success || !Array.isArray(data.users)) {
						closeList();
						return;
					}
					renderUsers(data.users);
				},
				onError: function () {
					closeList();
				},
			});
		}

		function selectUser(uid, displayName) {
			const id = String(uid || '').trim();
			if (id === '') {
				return;
			}
			const name = (displayName && String(displayName).trim()) ? String(displayName).trim() : id;
			hidden.value = id;
			selectedLabel = name + ' (' + id + ')';
			search.value = selectedLabel;
			closeList();
			onChange(id);
		}

		function clearSelection() {
			hidden.value = '';
			selectedLabel = '';
			search.value = '';
			closeList();
			onChange('');
		}

		function renderUsers(users) {
			const emptyMsg = l10n.noUsersFound || 'No users found';
			if (users.length === 0) {
				list.innerHTML = '<div class="user-picker__item user-picker__item--muted" role="presentation">'
					+ (Utils.escapeHtml ? Utils.escapeHtml(emptyMsg) : emptyMsg) + '</div>';
				openList();
				return;
			}
			list.innerHTML = users.map(function (u, index) {
				const uid = u.userId || u.uid || '';
				const name = (u.displayName && String(u.displayName).trim()) ? String(u.displayName) : uid;
				const meta = uid;
				return '<div role="option" id="user-picker-opt-' + index + '" tabindex="-1" class="user-picker__item" data-user-id="'
					+ (Utils.escapeHtml ? Utils.escapeHtml(uid) : uid) + '">'
					+ '<span class="user-picker__name">' + (Utils.escapeHtml ? Utils.escapeHtml(name) : name) + '</span>'
					+ '<span class="user-picker__meta">' + (Utils.escapeHtml ? Utils.escapeHtml(meta) : meta) + '</span></div>';
			}).join('');
			openList();
			activeIndex = -1;

			const items = Utils.$$ ? Utils.$$('.user-picker__item[data-user-id]', list) : list.querySelectorAll('.user-picker__item[data-user-id]');
			items.forEach(function (item) {
				Utils.on(item, 'mousedown', function (e) {
					e.preventDefault();
				});
				Utils.on(item, 'click', function () {
					const uid = item.getAttribute('data-user-id') || '';
					const nameEl = item.querySelector('.user-picker__name');
					selectUser(uid, nameEl ? nameEl.textContent : uid);
				});
			});
		}

		function highlightOption(index) {
			const items = list.querySelectorAll('.user-picker__item[data-user-id]');
			items.forEach(function (item, i) {
				const active = i === index;
				item.setAttribute('aria-selected', active ? 'true' : 'false');
				if (active) {
					item.focus();
				}
			});
			activeIndex = index;
		}

		Utils.on(search, 'input', function () {
			if (search.value !== selectedLabel) {
				hidden.value = '';
				selectedLabel = '';
				onChange('');
			}
			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(function () {
				fetchUsers(search.value);
			}, 280);
		});

		Utils.on(search, 'focus', function () {
			const q = (hidden.value && search.value === selectedLabel) ? hidden.value : search.value.trim();
			fetchUsers(q);
		});

		Utils.on(search, 'keydown', function (e) {
			const items = list.querySelectorAll('.user-picker__item[data-user-id]');
			if (e.key === 'Escape') {
				closeList();
				return;
			}
			if (list.hidden || items.length === 0) {
				return;
			}
			if (e.key === 'ArrowDown') {
				e.preventDefault();
				const next = activeIndex < items.length - 1 ? activeIndex + 1 : 0;
				highlightOption(next);
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				const prev = activeIndex > 0 ? activeIndex - 1 : items.length - 1;
				highlightOption(prev);
			} else if (e.key === 'Enter' && activeIndex >= 0 && items[activeIndex]) {
				e.preventDefault();
				const item = items[activeIndex];
				const uid = item.getAttribute('data-user-id') || '';
				const nameEl = item.querySelector('.user-picker__name');
				selectUser(uid, nameEl ? nameEl.textContent : uid);
			}
		});

		document.addEventListener('click', function (ev) {
			if (!wrap || wrap.contains(ev.target)) {
				return;
			}
			closeList();
		});

		return {
			clear: clearSelection,
			getUserId: function () {
				return String(hidden.value || '').trim();
			},
		};
	}

	window.ArbeitszeitCheck = window.ArbeitszeitCheck || {};
	window.ArbeitszeitCheck.initAdminUserPicker = initAdminUserPicker;
})();
