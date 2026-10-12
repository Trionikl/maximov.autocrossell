<?php
/**
 * Обработчики событий модуля (public static методы).
 *
 * @package maximov.autocrossell
 * @source documentation + EVENTS_REGISTRATION.md (RegisterModuleDependences, snake_case)
 
 * @author Denis Maximov
 * @link https://marketplace.1c-bitrix.ru/solutions/maximov.autocrossell/
 * @license GPL-3.0-or-later
 */

namespace Maximov\Autocrossell;

use Bitrix\Main\Context;
use Bitrix\Main\Page\Asset;
use Bitrix\Main\Config\Option;

class Events
{
	/**
	 * OnProlog: подключаем CSS + JS виджета на публичных страницах каталога.
	 * ShowHead() в шаблонах отрабатывает после OnProlog, поэтому ассеты
	 * гарантированно попадают в <head>. OnEpilog отсекается — слишком поздно.
	 */
	public static function onProlog()
	{
		// Админка не нуждается в ассетах публичного виджета.
		if (defined('ADMIN_SECTION') && ADMIN_SECTION) {
			return;
		}

		$request = Context::getCurrent() ? Context::getCurrent()->getRequest() : null;
		if (!$request) {
			return;
		}

		// Ядровые AJAX-ответы (runAction / rest / крон) — пропускаем.
		if ($request->get('action') || $request->get('bx_action')) {
			return;
		}
		if ($request->isAdminSection()) {
			return;
		}

		// Виджет имеет смысл только на страницах каталога (детали товара).
		$page = strtolower((string) $request->getRequestedPage());
		if ($page === '' || strpos($page, '/catalog/') === false) {
			return;
		}

		$asset = Asset::getInstance();

		// Asset::addCss/addJs ждут WEB-пути (URL), а не файловые пути.
		$asset->addCss('/bitrix/js/maximov.autocrossell/css/style.css');
		$asset->addCss('/bitrix/components/maximov.autocrossell/recommendations/templates/.default/style.css');
		$asset->addJs('/bitrix/js/maximov.autocrossell/js/script.js');

		// Передаём CSS-селектор контейнера для вставки виджету
		$containerSelector = (string) Option::get('maximov.autocrossell', 'block_container_selector', '');
		$asset->addString(
			'<script>window.MaximovAutocrossellInit = '
			. \CUtil::PhpToJSObject(['containerSelector' => $containerSelector])
			. ';</script>',
			false,
			\Bitrix\Main\Page\AssetLocation::AFTER_JS_KERNEL
		);
	}
}
