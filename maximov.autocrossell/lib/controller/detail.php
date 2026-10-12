<?php
/**
 * AutoCrossell Recommendations Controller
 *
 * AJAX-контроллер: отдаёт HTML блока рекомендаций по ID товара.
 * Доступен анонимным гостям (фильтр Authentication НЕ применяется, т.к. блок
 * только читает данные о сопутствующих товарах).
 *
 * @package maximov.autocrossell
 * @source documentation 2e37e6a512263167 (Фильтры контроллеров, score 0.88)
 
 * @author Denis Maximov
 * @link https://marketplace.1c-bitrix.ru/solutions/maximov.autocrossell/
 * @license GPL-3.0-or-later
 */

namespace Maximov\Autocrossell\Controller;

use Bitrix\Main\Engine\ActionFilter\Csrf;
use Bitrix\Main\Engine\ActionFilter\HttpMethod;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

class Detail extends Controller
{
	/**
	 * Фильтры по умолчанию: CSRF + HttpMethod.
	 * Authentication НЕ включаем — виджет работает для гостей.
	 */
	protected function getDefaultPreFilters()
	{
		return [
			new Csrf(),
			new HttpMethod([
				HttpMethod::METHOD_POST,
				HttpMethod::METHOD_GET,
			]),
		];
	}

	/**
	 * Отдаёт HTML компонента maximov.autocrossell:recommendations.
	 *
	 * @param int $productId ID товара детали карточки.
	 * @return array{html: string} Ответ окружается движком в AjaxJson -> response.data.html
	 */
	public function getComponentHtmlAction(int $productId = 0): array
	{
		if ($productId <= 0) {
			return ['html' => ''];
		}

		if (!Loader::includeModule('maximov.autocrossell')) {
			return ['html' => ''];
		}

		/** @var \CMain $APPLICATION */
		$APPLICATION = $GLOBALS['APPLICATION'];

		ob_start();
		try {
			$blockTitle = trim((string) Option::get('maximov.autocrossell', 'block_title', ''));

			$APPLICATION->IncludeComponent(
				'maximov.autocrossell:recommendations',
				'.default',
				[
					'PRODUCT_ID' => $productId,
					'LIMIT'      => 4,
					'TITLE'      => $blockTitle !== '' ? $blockTitle : null,
				],
				null,
				['HIDE_ICONS' => true]
			);
		} catch (\Throwable $e) {
			\Maximov\Autocrossell\Logger::log('Controller getComponentHtmlAction error: ' . $e->getMessage());
			ob_end_clean();

			return ['html' => ''];
		}

		return ['html' => (string) ob_get_clean()];
	}
}
