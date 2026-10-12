<?php

/**
 * AutoCrossell Recommendations Component (class.php)
 *
 * @package maximov.autocrossell
 
 * @author Denis Maximov
 * @link https://marketplace.1c-bitrix.ru/solutions/maximov.autocrossell/
 * @license GPL-3.0-or-later
 */

use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use Bitrix\Catalog\PriceTable;
use Bitrix\Iblock\SectionTable;
use Maximov\Autocrossell\AutocrossellPairsTable;
use Maximov\Autocrossell\Logger;
use Bitrix\Iblock\ElementTable;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
	die();
}

Loc::loadMessages(__FILE__);

class AutocrossellRecommendationsComponent extends CBitrixComponent
{
	/**
	 * Подготовка параметров компонента
	 */
	public function onPrepareComponentParams($arParams)
	{
		$arParams['PRODUCT_ID'] = (int)($arParams['PRODUCT_ID'] ?? 0);
		$arParams['LIMIT'] = (int)($arParams['LIMIT'] ?? 4);
		$arParams['LIMIT'] = max(0, min(4, $arParams['LIMIT']));
		$arParams['IBLOCK_ID'] = (int)($arParams['IBLOCK_ID'] ?? 0);
		$arParams['CACHE_TIME'] = isset($arParams['CACHE_TIME']) ? (int)$arParams['CACHE_TIME'] : 86400;
		$arParams['TITLE'] = trim((string)($arParams['TITLE'] ?? ''));

		return $arParams;
	}

	/**
	 * Основная логика выполнения компонента
	 */
	public function executeComponent()
	{
		try {
			if (!Loader::includeModule('maximov.autocrossell') || !Loader::includeModule('iblock')) {
				return;
			}

			$productId = $this->arParams['PRODUCT_ID'];

			if ($productId <= 0) {
				$productId = $this->determineProductId();
			}

			if ($productId <= 0) {
				$this->arResult['has_recommendations'] = false;
				$this->arResult['products'] = [];
				$this->includeComponentTemplate();
				return;
			}

			$additionalCacheId = ['PID' => $productId, 'LIMIT' => $this->arParams['LIMIT']];

			if ($this->startResultCache($this->arParams['CACHE_TIME'], $additionalCacheId)) {

				$taggedCache = Application::getInstance()->getTaggedCache();
				$taggedCache->startTagCache($this->getCachePath());
				// Тег модуля формируется из нативного идентификатора 'maximov.autocrossell'
				// — изоляция от коллизий с чужими модулями.
				$taggedCache->registerTag('maximov.autocrossell');

				$this->arResult['PRODUCT_ID'] = $productId;

				$minThreshold = (int)Option::get('maximov.autocrossell', 'min_threshold', 2);
				$limit = $this->arParams['LIMIT'];

				$queryParams = [
					'select' => ['PRODUCT_ID_1', 'PRODUCT_ID_2', 'WEIGHT'],
					'filter' => [
						'LOGIC' => 'AND',
						'>=WEIGHT' => $minThreshold,
						[
							'LOGIC' => 'OR',
							['PRODUCT_ID_1' => $productId],
							['PRODUCT_ID_2' => $productId],
						],
					],
					'order' => ['WEIGHT' => 'DESC'],
				];
				if ($limit > 0) {
					$queryParams['limit'] = $limit;
				}

				try {
					$pairsData = AutocrossellPairsTable::getList($queryParams)->fetchAll();
				} catch (Throwable $t) {
					Logger::log("DB Query Error: " . $t->getMessage());
					$pairsData = [];
				}

				// Нормализуем: определяем партнёра (то поле, которое ≠ текущему товару)
				$normalizedPairs = [];
				foreach ($pairsData as $row) {
					$p1 = (int)$row['PRODUCT_ID_1'];
					$p2 = (int)$row['PRODUCT_ID_2'];
					$partnerId = ($p1 === $productId) ? $p2 : $p1;
					$weight = (int)$row['WEIGHT'];
					if ($partnerId > 0 && $weight >= $minThreshold) {
						// Если товар уже был — берём максимальный вес (на случай дублей)
						$normalizedPairs[$partnerId] = max($normalizedPairs[$partnerId] ?? 0, $weight);
					}
				}

				if (!empty($normalizedPairs)) {
					$products = $this->getProductsInfo($normalizedPairs);
					$this->arResult['products'] = $products;
					$this->arResult['has_recommendations'] = !empty($products);
				} else {
					$this->arResult['has_recommendations'] = false;
					$this->arResult['products'] = [];
				}
				$taggedCache->endTagCache();

				$this->includeComponentTemplate();
			}
		} catch (Throwable $t) {
			$this->abortResultCache();
			Logger::log("Component Error: " . $t->getMessage());
			$this->arResult['error_message'] = Loc::getMessage('AUTOCROSSELL_ERROR');
			$this->includeComponentTemplate();
		}
	}

	/**
	 * Определение ID товара из контекста запроса
	 */
	private function determineProductId(): int
	{
		$request = Context::getCurrent()->getRequest();

		$possibleVars = ['PRODUCT_ID', 'ELEMENT_ID', 'ID'];
		foreach ($possibleVars as $var) {
			$val = (int)$request->get($var);
			if ($val > 0) {
				return $val;
			}
		}

		$elementCode = ($this->arParams['ELEMENT_CODE'] ?? '') ?: $request->get('ELEMENT_CODE');
		$sectionCode = ($this->arParams['SECTION_CODE'] ?? '') ?: $request->get('SECTION_CODE');
		$iblockId = $this->arParams['IBLOCK_ID'];

		if ($elementCode && $iblockId) {
			$res = ElementTable::getList([
				'select' => ['ID'],
				'filter' => ['=CODE' => $elementCode, '=IBLOCK_ID' => $iblockId, '=ACTIVE' => 'Y'],
				'limit' => 1,
			]);
			if ($element = $res->fetch()) {
				return (int)$element['ID'];
			}

			if ($sectionCode) {
				$sectionData = SectionTable::getList([
					'select' => ['ID'],
					'filter' => ['=IBLOCK_ID' => $iblockId, '=CODE' => $sectionCode],
					'limit' => 1,
				])->fetch();

				if ($sectionData) {
					$res = ElementTable::getList([
						'select' => ['ID'],
						'filter' => [
							'=CODE' => $elementCode,
							'=IBLOCK_ID' => $iblockId,
							'=SECTION_ID' => $sectionData['ID'],
							'=ACTIVE' => 'Y',
						],
						'limit' => 1,
					]);
					if ($element = $res->fetch()) {
						return (int)$element['ID'];
					}
				}
			}
		}

		return 0;
	}

	/**
	 * Получение сведений о сопутствующих товарах (ЧПУ-ссылки, фото, цены)
	 *
	 * @param array $weights Массив [partnerId => weight] (нормализованный)
	 */
	private function getProductsInfo(array $weights): array
	{
		if (empty($weights)) {
			return [];
		}

		$productIds = array_keys($weights);
		if (empty($productIds)) {
			return [];
		}

		$filter = [
			'ID' => $productIds,
			'ACTIVE' => 'Y',
		];
		if ($this->arParams['IBLOCK_ID'] > 0) {
			$filter['IBLOCK_ID'] = $this->arParams['IBLOCK_ID'];
		}

		$products = [];
		$elements = \CIBlockElement::GetList( // linter:ignore:DEPRECATED_API — D7 не формирует DETAIL_PAGE_URL
			[],
			$filter,
			false,
			false,
			['ID', 'IBLOCK_ID', 'NAME', 'PREVIEW_PICTURE', 'DETAIL_PICTURE', 'DETAIL_PAGE_URL', 'IBLOCK_SECTION_ID', 'CODE']
		);
		while ($element = $elements->GetNext()) {
			$pid = (int)$element['ID'];

			$previewPic = (int)($element['PREVIEW_PICTURE'] ?? 0);
			$detailPic = (int)($element['DETAIL_PICTURE'] ?? 0);

			$products[$pid] = [
				'id' => $pid,
				'name' => $element['NAME'],
				'preview_picture' => $previewPic > 0 ? CFile::GetPath($previewPic) : null,
				'detail_picture' => $detailPic > 0 ? CFile::GetPath($detailPic) : null,
				'url' => $element['DETAIL_PAGE_URL'],
				'weight' => $weights[$pid] ?? 0,
				'price' => null,
			];
		}

		if (empty($products)) {
			return [];
		}

		$prices = $this->getProductsPrices(array_keys($products));
		foreach ($prices as $pId => $priceData) {
			if (isset($products[$pId])) {
				$products[$pId]['price'] = $priceData;
			}
		}

		uasort($products, function ($a, $b) {
			return $b['weight'] <=> $a['weight'];
		});

		return $products;
	}

	/**
	 * Пакетное получение цен через D7 ORM PriceTable
	 */
	private function getProductsPrices(array $productIds): array
	{
		if (empty($productIds) || !Loader::includeModule('catalog')) {
			return [];
		}

		$priceGroupId = (int)Option::get('maximov.autocrossell', 'price_group_id', 1);

		$priceMap = [];
		$rsPrices = PriceTable::getList([
			'select' => ['PRODUCT_ID', 'PRICE', 'CURRENCY'],
			'filter' => [
				'=CATALOG_GROUP_ID' => $priceGroupId,
				'=PRODUCT_ID' => $productIds,
			],
		]);

		while ($row = $rsPrices->fetch()) {
			$pId = (int)$row['PRODUCT_ID'];
			$priceMap[$pId] = [
				'value' => (float)$row['PRICE'],
				'currency' => $row['CURRENCY'],
				'formatted' => \CurrencyFormat($row['PRICE'], $row['CURRENCY']),
			];
		}

		return $priceMap;
	}
}
