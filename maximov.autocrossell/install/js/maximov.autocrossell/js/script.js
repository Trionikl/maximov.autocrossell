/**
 * AutoCrossell Recommendations — client-side widget (Vanilla JS, IIFE)
 *
 * Инъецирует блок рекомендаций в настраиваемую точку страницы
 * со скелетоном против CLS, затем подменяет его HTML из D7-контроллера
 * через BX.ajax.runAction.
 *
 * Точка вставки (приоритет):
 *  1. Пользовательский селектор из window.MaximovAutocrossellInit.containerSelector (after)
 *  2. Блоки «Просматривали»/сопутствующие (.catalog-products-viewed и др.) (after)
 *  3. Карточка товара (.bx-catalog-element и др.) (after)
 *  4. Контентная область (.bx-content-section / .workarea) (append)
 *  5. Фолбэк: перед <footer> → в конец document.body (append)
 *
 * @package maximov.autocrossell
 * @source documentation ed333a42c05faf56 (runAction naming), 2e37e6a512263167 (filters)
 */

(function () {
	'use strict';
	try {
		var ACTION = 'maximov:autocrossell.detail.getComponentHtml';
		var DEFAULT_TITLE = 'С этим покупают';

		var getAnchor = function () {
			var cfg;
			// Приоритет 1 — пользовательский селектор из настроек модуля (вставляем ПОСЛЕ)
			try {
				cfg = (window.MaximovAutocrossellInit || {}).containerSelector || '';
				if (cfg && typeof cfg === 'string') {
					var userEl = document.querySelector(cfg);
					if (userEl) return { el: userEl, position: 'after' };
				}
			} catch (e) {
				// Невалидный CSS-селектор — молча игнорируем, идём в фолбэк
			}
			// Приоритет 2 — блоки «Просматривали»/сопутствующие (вставляем ПОСЛЕ)
			var related = [
				'.catalog-products-viewed',
				'[data-entity="catalog-products-viewed-container"]',
				'[data-entity="parent-container"]'
			];
			for (var i = 0; i < related.length; i++) {
				try {
					var relEl = document.querySelector(related[i]);
					if (relEl) return { el: relEl, position: 'after' };
				} catch (e) { /* ignore */ }
			}
			// Приоритет 3 — карточка товара (вставляем ПОСЛЕ)
			var cards = ['.bx-catalog-element', '.catalog-element', '.product-item-detail'];
			for (var j = 0; j < cards.length; j++) {
				try {
					var cardEl = document.querySelector(cards[j]);
					if (cardEl) return { el: cardEl, position: 'after' };
				} catch (e) { /* ignore */ }
			}
			// Приоритет 4 — контентная область (вставляем В КОНЕЦ)
			var areas = ['.bx-content-section', '.workarea'];
			for (var k = 0; k < areas.length; k++) {
				try {
					var areaEl = document.querySelector(areas[k]);
					if (areaEl) return { el: areaEl, position: 'append' };
				} catch (e) { /* ignore */ }
			}
			// Приоритет 5 — фолбэк: перед <footer>, иначе в конец body
			try {
				var footer = document.querySelector('footer');
				if (footer) return { el: footer, position: 'before' };
			} catch (e) { /* ignore */ }
			return { el: document.body, position: 'append' };
		};

		var hasContainerParent = function (node) {
			var cur = node;
			while (cur && cur.nodeType === 1) {
				if (cur.classList && cur.classList.contains('container')) {
					return true;
				}
				if (cur === document.body || cur === document.documentElement) {
					break;
				}
				cur = cur.parentNode;
			}
			return false;
		};

		var Skeleton = {
			html: function () {
				var cols = [];
				for (var i = 0; i < 4; i++) {
					cols.push(
						'<div class="col-6 col-md-3 product-item-small-card">'
						+ '<div class="autocrossell-skeleton-col">'
						+ '<div class="autocrossell-skeleton-img"></div>'
						+ '<div class="autocrossell-skeleton-title"></div>'
						+ '<div class="autocrossell-skeleton-price"></div>'
						+ '<div class="autocrossell-skeleton-btn"></div>'
						+ '</div></div>'
					);
				}
				// Сетка скелетона зеркалит реальный шаблон
				// (row + product-item-list-col-4 + col-6 col-md-3), иначе
				// 4 колонки с собственным flex/gap не влезают в один ряд.
				return '<div class="row mb-5 autocrossell-skeleton">'
					+ '<div class="col">'
					+ '<div class="autocrossell-skeleton-header"></div>'
					+ '<div class="row product-item-list-col-4 autocrossell-skeleton-row">' + cols.join('') + '</div>'
					+ '</div>'
					+ '</div>';
			},
			insert: function () {
				var anchor = getAnchor();
				if (!anchor || !anchor.el || !anchor.el.parentNode) {
					return null;
				}
				var el = anchor.el;
				var position = anchor.position;

				var wrap = document.createElement('div');
				wrap.className = 'autocrossell-inject-wrap';
				// Auto-container: если якорь вне .container — сужаем блок до ширины каталога
				if (!hasContainerParent(el)) {
					wrap.className += ' container';
				}
				wrap.innerHTML = this.html();

				if (position === 'after') {
					// Кроссбраузерный аналог el.after(wrap) — вставляем ПОСЛЕ якоря
					el.parentNode.insertBefore(wrap, el.nextSibling);
				} else if (position === 'append') {
					// В конец контентной области / body
					el.appendChild(wrap);
				} else {
					// before — перед якорем (footer)
					el.parentNode.insertBefore(wrap, el);
				}

				this.el = wrap;
				return wrap;
			},
			remove: function () {
				if (this.el && this.el.parentNode) {
					this.el.parentNode.removeChild(this.el);
				}
				this.el = null;
			}
		};

		var getProductId = function () {
			var root = document.querySelector('.bx-catalog-element');
			var id;
			if (root) {
				id = root.id;
			} else {
				var node = document.querySelector('.product-item-detail-pay-block [id*="bx_"]');
				if (node) {
					id = node.id;
				}
			}
			if (!id) {
				return 0;
			}
			var m = id.match(/_(\d+)$/);
			return m ? parseInt(m[1], 10) : 0;
		};

		var run = function () {
			// Не дублируем, если блок уже отрисован сервером или виджетом
			if (document.querySelector('.autocrossell-recommendations')) {
				return;
			}

			var productId = getProductId();
			if (!productId) {
				return;
			}

			var skeleton = Skeleton.insert();
			if (!skeleton) {
				return;
			}

			if (typeof BX === 'undefined' || typeof BX.ajax === 'undefined' || typeof BX.ajax.runAction !== 'function') {
				Skeleton.remove();
				return;
			}

			var promise = BX.ajax.runAction(ACTION, {
				data: { productId: productId }
			});

			promise.then(function (response) {
				// html может содержать только BOM/пробелы (например, при
				// отсутствии рекомендаций). В JS "\uFEFF" является truthy,
				// поэтому явно отсекаем BOM и пробелы по краям — иначе
				// обёртка остаётся в DOM пустым отступом.
				var html = (response && response.data && response.data.html)
					? String(response.data.html).replace(/^\uFEFF+/, '').trim()
					: '';
				if (!html) {
					Skeleton.remove();
					return;
				}
				// Сохраняем обёртку (.container / autocrossell-inject-wrap), чтобы
				// блок не растягивался на 100% ширины после подмены на реальный HTML
				skeleton.innerHTML = html;
				Skeleton.el = null;
			}).catch(function () {
				Skeleton.remove();
			});
		};

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', run);
		} else {
			run();
		}
	} catch (e) {
		if (window.console && console.error) {
			console.error('[maximov.autocrossell] widget init failed:', e);
		}
	}
})();
