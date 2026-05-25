/**
 * Searchable admin employee picker (combobox) — uses lightweight user search API.
 *
 * @license AGPL-3.0-or-later
 */
(function () {
	'use strict';

	const Utils = window.ArbeitszeitCheckUtils || {};

	function queryOne(selector) {
		if (Utils.$) {
			return Utils.$(selector);
		}
		return document.querySelector(selector);
	}

	function queryAll(selector, root) {
		const scope = root || document;
		if (Utils.$$) {
			return Utils.$$(selector, scope);
		}
		return scope.querySelectorAll(selector);
	}

	function bind(el, event, handler) {
		if (!el) {
			return;
		}
		if (Utils.on) {
			Utils.on(el, event, handler);
			return;
		}
		el.addEventListener(event, handler);
	}

	function escapeText(text) {
		if (Utils.escapeHtml) {
			return Utils.escapeHtml(text);
		}
		const d = document.createElement('div');
		d.textContent = String(text);
		return d.innerHTML;
	}

	/**
	 * @param {object} options
	 * @param {string} options.hiddenSelector Hidden input storing userId
	 * @param {string} options.searchSelector Visible combobox input
	 * @param {string} options.listSelector Listbox container element
	 * @param {string} options.wrapSelector Container for outside-click close
	 * @param {string} options.searchUrl Base URL for GET ?search=&limit=
	 * @param {string} [options.statusSelector] Live region for screen reader updates
	 * @param {number} [options.limit=15]
	 * @param {object} [options.l10n]
	 * @param {function(string): void} [options.onChange] Called when selection cleared or set
	 */
	function initAdminUserPicker(options) {
		const hidden = queryOne(options.hiddenSelector);
		const search = queryOne(options.searchSelector);
		const list = queryOne(options.listSelector);
		const wrap = queryOne(options.wrapSelector);
		const status = options.statusSelector ? queryOne(options.statusSelector) : null;
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
		let requestGeneration = 0;

		function setStatus(message) {
			if (status) {
				status.textContent = message || '';
			}
		}

		function closeList() {
			list.hidden = true;
			list.innerHTML = '';
			search.setAttribute('aria-expanded', 'false');
			search.removeAttribute('aria-activedescendant');
			activeIndex = -1;
		}

		function openList() {
			list.hidden = false;
			search.setAttribute('aria-expanded', 'true');
		}

		function showMessage(msg, muted) {
			const cls = muted ? 'user-picker__item user-picker__item--muted' : 'user-picker__item user-picker__item--muted';
			list.innerHTML = '<div class="' + cls + '" role="presentation">' + escapeText(msg) + '</div>';
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
			const generation = ++requestGeneration;

			showMessage(l10n.loading || 'Loading…', true);
			setStatus(l10n.loading || 'Loading…');

			const onDone = function (data) {
				if (generation !== requestGeneration) {
					return;
				}
				if (!data || !data.success || !Array.isArray(data.users)) {
					const err = l10n.searchError || l10n.error || 'User search failed';
					showMessage(err, true);
					setStatus(err);
					return;
				}
				renderUsers(data.users);
			};

			const onFail = function () {
				if (generation !== requestGeneration) {
					return;
				}
				const err = l10n.searchError || l10n.error || 'User search failed';
				showMessage(err, true);
				setStatus(err);
			};

			if (Utils.ajax) {
				Utils.ajax(url, {
					method: 'GET',
					onSuccess: onDone,
					onError: onFail,
				});
				return;
			}

			const token = (typeof OC !== 'undefined' && OC.requestToken) ? OC.requestToken : '';
			fetch(url, {
				headers: { requesttoken: token, Accept: 'application/json' },
				credentials: 'same-origin',
			})
				.then(function (res) { return res.json(); })
				.then(onDone)
				.catch(onFail);
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
			const selectedMsg = (l10n.employeeSelected || 'Selected %s')
				.replace('%s', selectedLabel);
			setStatus(selectedMsg);
			onChange(id);
		}

		function clearSelection() {
			hidden.value = '';
			selectedLabel = '';
			search.value = '';
			closeList();
			setStatus(l10n.allEmployees || '');
			onChange('');
		}

		function renderUsers(users) {
			const emptyMsg = l10n.noUsersFound || 'No matching employees found.';
			if (users.length === 0) {
				list.innerHTML = '';
				showMessage(emptyMsg, true);
				setStatus(emptyMsg);
				return;
			}

			list.innerHTML = users.map(function (u, index) {
				const uid = u.userId || u.uid || '';
				const name = (u.displayName && String(u.displayName).trim()) ? String(u.displayName) : uid;
				return '<div role="option" id="user-picker-opt-' + index + '" tabindex="-1" class="user-picker__item" data-user-id="'
					+ escapeText(uid) + '" aria-selected="false">'
					+ '<span class="user-picker__name">' + escapeText(name) + '</span>'
					+ '<span class="user-picker__meta">' + escapeText(uid) + '</span></div>';
			}).join('');
			openList();
			activeIndex = -1;

			const countMsg = (l10n.resultsCount || '%n results')
				.replace('%n', String(users.length));
			setStatus(countMsg);

			queryAll('.user-picker__item[data-user-id]', list).forEach(function (item) {
				bind(item, 'mousedown', function (e) {
					e.preventDefault();
				});
				bind(item, 'click', function () {
					const uid = item.getAttribute('data-user-id') || '';
					const nameEl = item.querySelector('.user-picker__name');
					selectUser(uid, nameEl ? nameEl.textContent : uid);
				});
			});
		}

		function highlightOption(index) {
			const items = list.querySelectorAll('.user-picker__item[data-user-id]');
			if (!items.length) {
				return;
			}
			items.forEach(function (item, i) {
				const active = i === index;
				item.setAttribute('aria-selected', active ? 'true' : 'false');
				if (active) {
					search.setAttribute('aria-activedescendant', item.id || '');
				}
			});
			activeIndex = index;
		}

		bind(search, 'input', function () {
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

		bind(search, 'focus', function () {
			const q = (hidden.value && search.value === selectedLabel) ? hidden.value : search.value.trim();
			fetchUsers(q);
		});

		bind(search, 'keydown', function (e) {
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
