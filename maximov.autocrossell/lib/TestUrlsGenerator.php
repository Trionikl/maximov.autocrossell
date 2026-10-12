<?php

/**
 * @author Denis Maximov
 * @link https://marketplace.1c-bitrix.ru/solutions/maximov.autocrossell/
 * @license GPL-3.0-or-later
 */

namespace Maximov\Autocrossell;

use Bitrix\Iblock\IblockTable;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

class TestUrlsGenerator
{
	private const COMPONENT_LIMIT = 4;

	public static function generate(string $outputPath): void
	{
		$minThreshold = (int) Option::get('maximov.autocrossell', 'min_threshold', 2);

		// 1. Get all pairs with weight >= threshold
		$pairs = AutocrossellPairsTable::getList([
			'select' => ['PRODUCT_ID_1', 'PRODUCT_ID_2', 'WEIGHT'],
			'filter' => ['>=WEIGHT' => $minThreshold],
		])->fetchAll();

		if (empty($pairs)) {
			self::writeEmpty($outputPath);
			Logger::log('TestUrlsGenerator: нет пар с весом >= ' . $minThreshold);
			return;
		}

		// 2. Aggregate pair counts by PRODUCT_ID_1
		$productStats = [];
		foreach ($pairs as $pair) {
			$pid = (int) $pair['PRODUCT_ID_1'];
			if (!isset($productStats[$pid])) {
				$productStats[$pid] = ['pair_count' => 0];
			}
			$productStats[$pid]['pair_count']++;
		}

		$productIds = array_keys($productStats);

		// Товары, встречающиеся в парах в ЛЮБОЙ роли (PRODUCT_ID_1 или
		// PRODUCT_ID_2). Виджет ищет пары в обе стороны, поэтому negative
		// должен исключать всех, кто участвует в парах, — иначе в «без пар»
		// может попасть товар, который реально показывает рекомендации.
		$excludedIds = [];
		foreach ($pairs as $pair) {
			$excludedIds[(int)$pair['PRODUCT_ID_1']] = true;
			$excludedIds[(int)$pair['PRODUCT_ID_2']] = true;
		}
		$excludedIds = array_map('intval', array_keys($excludedIds));

		if (!Loader::includeModule('iblock')) {
			Logger::error('TestUrlsGenerator: модуль iblock не загружен');
			return;
		}

		// 3. Get catalog iblock IDs via D7 (CIBlock::GetList filter is unreliable)
		$catalogIblockIds = [];
		$iblocksRes = IblockTable::getList([
			'filter' => ['=IBLOCK_TYPE_ID' => 'catalog'],
			'select' => ['ID'],
		]);
		while ($iblock = $iblocksRes->fetch()) {
			$catalogIblockIds[] = (int) $iblock['ID'];
		}

		if (empty($catalogIblockIds)) {
			Logger::log('TestUrlsGenerator: не найдено инфоблоков с типом catalog');
			self::writeEmpty($outputPath);
			return;
		}

		// 4. Get element details for catalog products
		// CIBlockElement::GetList with array IBLOCK_ID is unreliable,
		// so we iterate over each catalog iblock separately
		$positive = [];
		$remainingIds = $productIds;

		foreach ($catalogIblockIds as $iblockId) {
			if (empty($remainingIds)) {
				break;
			}

			// Легаси-API сознательный: только CIBlockElement вычисляет DETAIL_PAGE_URL
			// из шаблона инфоблока; у \Bitrix\Iblock\ElementTable такого поля нет.
			// linter:ignore:DEPRECATED_API
			$elementsRes = \CIBlockElement::GetList(
				[],
				[
					'=ID' => $remainingIds,
					'=ACTIVE' => 'Y',
					'=IBLOCK_ID' => $iblockId,
				],
				false,
				false,
				['ID', 'IBLOCK_ID', 'NAME', 'CODE', 'DETAIL_PAGE_URL']
			);

			while ($el = $elementsRes->GetNext()) {
				$pid = (int) $el['ID'];

				$pairCount = $productStats[$pid]['pair_count'];
				$expectedMin = min(self::COMPONENT_LIMIT, $pairCount);

				$positive[] = [
					'product_id' => $pid,
					'url' => $el['DETAIL_PAGE_URL'],
					'code' => $el['CODE'],
					'name' => $el['NAME'],
					'expected_min' => $expectedMin,
					'pair_count' => $pairCount,
				];

				// Remove found ID from remaining set
				$remainingIds = array_values(array_diff($remainingIds, [$pid]));
			}
		}

		usort($positive, fn($a, $b) => $a['product_id'] - $b['product_id']);

		// 5. Generate negative cases (catalog products without pairs)
		$negative = [];
		if (!empty($excludedIds)) {
			foreach ($catalogIblockIds as $iblockId) {
				if (count($negative) >= 5) {
					break;
				}

				// Легаси-API сознательный: только CIBlockElement вычисляет DETAIL_PAGE_URL
				// из шаблона инфоблока; у \Bitrix\Iblock\ElementTable такого поля нет.
				// linter:ignore:DEPRECATED_API
				$negativeRes = \CIBlockElement::GetList(
					['RAND' => 'ASC'],
					[
						'=IBLOCK_ID' => $iblockId,
						'=ACTIVE' => 'Y',
						'!=ID' => $excludedIds,
					],
					false,
					['nTopCount' => 5 - count($negative)],
					['ID', 'NAME', 'CODE', 'DETAIL_PAGE_URL']
				);

				while ($el = $negativeRes->GetNext()) {
					$negative[] = [
						'product_id' => (int) $el['ID'],
						'url' => $el['DETAIL_PAGE_URL'],
						'code' => $el['CODE'],
						'name' => $el['NAME'],
						'expected_min' => 0,
						'pair_count' => 0,
					];
				}
			}
		}

		// 6. Write JSON
		$data = [
			'positive' => $positive,
			'negative' => $negative,
			'generated_at' => date('Y-m-d H:i:s'),
		];

		$dir = dirname($outputPath);
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}

		if (!is_writable($dir)) {
			return;
		}

		@file_put_contents(
			$outputPath,
			json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
		);

		Logger::log(
			'TestUrlsGenerator: создан ' . basename($outputPath)
			. ' (' . count($positive) . ' positive, ' . count($negative) . ' negative)'
		);
	}

	private static function writeEmpty(string $outputPath): void
	{
		$dir = dirname($outputPath);
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}

		if (!is_writable($dir)) {
			return;
		}

		@file_put_contents(
			$outputPath,
			json_encode([
				'positive' => [],
				'negative' => [],
				'generated_at' => date('Y-m-d H:i:s'),
			], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
		);
	}
}
