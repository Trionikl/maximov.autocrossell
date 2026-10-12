<?php

/**
 * BasketAnalyzer
 *
 * Анализирует корзины и рассчитывает пары товаров для блока «С этим товаром покупают»
 *
 * @package maximov.autocrossell
 
 * @author Denis Maximov
 * @link https://marketplace.1c-bitrix.ru/solutions/maximov.autocrossell/
 * @license GPL-3.0-or-later
 */

namespace Maximov\Autocrossell;

use Bitrix\Main\Loader;
use Maximov\Autocrossell\Logger;
use Bitrix\Catalog\ProductTable;
use CCatalogSKU;

class BasketAnalyzer
{
	private $minThreshold;
	private $historyDays;
	private $debug;
	private $skippedProductIds = [];

	/**
	 * Карта SKU → родитель (после вызова buildSkuMap).
	 * Ключ — ID SKU/товара, значение — ID родительского товара.
	 *
	 * @var array<int, int>
	 */
	private $skuMap = [];

	/**
	 * Конструктор
	 *
	 * @param int $minThreshold Минимальный порог совместных покупок
	 * @param int $historyDays Глубина истории (дней)
	 */
	public function __construct($minThreshold = 2, $historyDays = 180)
	{
		$this->minThreshold = $minThreshold;
		$this->historyDays = $historyDays;
	}

	/**
	 * Построение пакетной карты SKU → родитель.
	 *
	 * Один раз вызывается перед обработкой заказов: получает все уникальные
	 * PRODUCT_ID из корзин, определяет типы (ProductTable), резолвит
	 * офферы в родительские товары через CCatalogSKU и кеширует результат
	 * в $this->skuMap для последующих вызовов mapToParent().
	 *
	 * @param array<int> $productIds Уникальные ID товаров из корзин
	 * @return array<int, int> Карта [originalId => parentId]
	 */
	public function buildSkuMap(array $productIds): array
	{
		$this->skuMap = $this->getParentProductIds($productIds);
		return $this->skuMap;
	}

	/**
	 * Резолв одного товара в родителя по предварительно построенной карте.
	 *
	 * Если карта не построена (buildSkuMap не вызывался), работает аварийно:
	 * возвращает сам товар как родителя.
	 *
	 * @param int $productId ID товара (SKU или обычного)
	 * @return int ID родительского товара
	 */
	public function mapToParent(int $productId): int
	{
		if (empty($this->skuMap)) {
			return $productId;
		}
		return $this->skuMap[$productId] ?? $productId;
	}

	/**
	 * Проверка, пропущен ли товар (осиротевший SKU, некаталожный и т.д.).
	 *
	 * @param int $productId
	 * @return bool
	 */
	public function isSkipped(int $productId): bool
	{
		return isset($this->skippedProductIds[$productId]);
	}

	/**
	 * Публичная обёртка над calculatePairsForUser.
	 *
	 * Генерирует попарные комбинации из уникальных продуктов одного заказа.
	 * Используется потоковым агентом для расчёта пар по одному заказу.
	 *
	 * @param array<int> $products Уникальные родительские ID товаров заказа
	 * @return array<string, array{product_id_1: int, product_id_2: int, weight: int}>
	 */
	public function generatePairs(array $products): array
	{
		return $this->calculatePairsForUser($products);
	}

	/**
	 * Расчёт кросс-селл пар из корзин (полный цикл, для обратной совместимости).
	 *
	 * @deprecated Используйте buildSkuMap() + generatePairs() в потоковом режиме.
	 * @param array $baskets Массив корзин
	 * @return array Массив пар
	 */
	public function calculatePairs(array $baskets)
	{
		if (empty($baskets)) {
			Logger::log('Baskets empty');
			return [];
		}

		// ✅ Данные уже отфильтрованы в RecalculationAgent по дате и статусу
		// Фильтрация здесь не нужна - это дублирующая логика, которая вызывает ошибку DateTime::compile()

		// Group products by user
		$userProducts = $this->groupProductsByUser($baskets);

		Logger::log('calculatePairs: группировка по пользователям', [
			'total_baskets_raw' => count($baskets),
			'total_users' => count($userProducts),
			'users_with_products' => array_map('count', $userProducts),
			'min_threshold' => $this->minThreshold,
			'history_days' => $this->historyDays,
			'skipped_ids_count' => count($this->skippedProductIds),
		]);

		// Calculate pairs
		$pairs = [];
		foreach ($userProducts as $products) {
			$userPairs = $this->calculatePairsForUser($products);
			foreach ($userPairs as $key => $pairData) {
				if (isset($pairs[$key])) {
					$pairs[$key]['weight'] += $pairData['weight'];
				} else {
					$pairs[$key] = $pairData;
				}
			}
		}

		// Filter by minimum threshold
		$filteredPairs = array_filter($pairs, function ($pair) {
			return $pair['weight'] >= $this->minThreshold;
		});

		// Sort by weight (descending)
		usort($filteredPairs, function ($a, $b) {
			return $b['weight'] <=> $a['weight'];
		});

		Logger::log('Calculated ' . count($filteredPairs) . ' pairs with threshold ' . $this->minThreshold);
		return array_values($filteredPairs);
	}

	/**
	 * Group products by user, resolving SKU offers to parent products
	 */
	private function groupProductsByUser(array $baskets)
	{
		$userProducts = []; // Теперь это будут продукты по заказам: [order_id => [prod1, prod2...]]
		$productIds = [];
		$skippedCount = 0;

		// Collect all unique product IDs from baskets
		foreach ($baskets as $basket) {
			$productId = (int)($basket['PRODUCT_ID'] ?? 0);
			if ($productId > 0) {
				$productIds[$productId] = true;
			}
		}

		// Resolve SKUs to parent products (batch query)
		$parentIds = $this->getParentProductIds(array_keys($productIds));

		foreach ($baskets as $basket) {
			$orderId = (int)($basket['ORDER_ID'] ?? 0);
			$productId = (int)($basket['PRODUCT_ID'] ?? 0);

			if ($orderId > 0 && $productId > 0) {
				// Пропускаем осиротевшие SKU (без родителя)
				if (isset($this->skippedProductIds[$productId])) {
					$skippedCount++;
					continue;
				}

				// Resolve to parent product ID
				$parentId = $parentIds[$productId] ?? $productId;

				if (!isset($userProducts[$orderId])) {
					$userProducts[$orderId] = [];
				}
				// Add unique parent products per order
				if (!in_array($parentId, $userProducts[$orderId], true)) {
					$userProducts[$orderId][] = $parentId;
				}
			}
		}

		if ($skippedCount > 0) {
			Logger::log('groupProductsByUser: пропущено осиротевших SKU', [
				'skipped_count' => $skippedCount,
				'skipped_ids' => array_keys($this->skippedProductIds),
			]);
		}

		return $userProducts;
	}

	/**
	 * Resolve SKU IDs to their parent Product IDs
	 * 
	 * Fixes the issue where SKU IDs were being saved instead of Parent Product IDs.
	 * Uses ProductTable::TYPE to identify offers and CCatalogSKU to resolve parents.
	 *
	 * @param array $productIds Массив ID продуктов
	 * @return array [originalId => parentId]
	 */
	private function getParentProductIds(array $productIds): array
	{
		if (empty($productIds)) {
			return [];
		}

		// Ensure catalog module is loaded
		if (!Loader::includeModule('catalog')) {
			Logger::log('Модуль catalog не загружен');
			$mapping = [];
			foreach ($productIds as $id) $mapping[(int)$id] = (int)$id;
			return $mapping;
		}

		$mapping = [];
		$offerIds = [];

		// 1. Пакетный запрос типов продуктов через D7 ProductTable
		// ИСПРАВЛЕНИЕ: Убрано поле 'SKU' из выборки, так как его нет в entity (вызывает ошибку Unknown field)
		// Вместо этого запрашиваем 'TYPE' и проверяем константы типов (SKU=3, OFFER=4)
		$res = ProductTable::getList([
			'select' => ['ID', 'TYPE'],
			'filter' => ['@ID' => $productIds]
		]);

		while ($product = $res->fetch()) {
			$pid = (int)$product['ID'];
			$type = (int)($product['TYPE'] ?? 0);

			// TYPE=3 — товар, имеющий ТП (родитель), НЕ является оффером.
			// Настоящие офферы: TYPE=4, 5, 6.
			// Родитель и обычные товары — резолвить не нужно, остаются как есть.
			if (in_array($type, [4, 5, 6], true)) {
				$offerIds[] = $pid;
			} else {
				// TYPE=1,2,3,0,... — обычный товар или родитель: родителем является он сам
				$mapping[$pid] = $pid;
			}
		}

		// 1.1. Элементы, отсутствующие в b_catalog_product, — не товары каталога
		//      (новости, статьи и т.п.). Пропускаем: в пары они не попадают.
		$foundIds = array_merge(array_keys($mapping), $offerIds);
		foreach ($productIds as $requestedId) {
			$id = (int)$requestedId;
			if ($id <= 0) {
				continue;
			}
			if (!in_array($id, $foundIds, true)) {
				$this->skippedProductIds[$id] = true;
				Logger::log('Элемент не является товаром каталога, пропускаем', [
					'element_id' => $id,
				]);
			}
		}

		// 2. Разрешаем предложения в родительские товары
		// CCatalogSKU::GetProductInfo - стандартный API для получения данных о привязке SKU
		if (!empty($offerIds)) {
			foreach ($offerIds as $offerId) {
				$info = CCatalogSKU::GetProductInfo($offerId);
				if ($info && !empty($info['ID'])) {
					$mapping[$offerId] = (int)$info['ID'];
				} else {
					// Если не удалось найти родителя — логируем и пропускаем
					// (не записываем мусорный ID в таблицу пар)
					$this->skippedProductIds[$offerId] = true;
					Logger::log('SKU не имеет родителя, пропускаем', [
						'offer_id' => $offerId,
					]);
				}
			}
		}

		return $mapping;
	}

	/**
	 * @deprecated Используйте generatePairs() (публичная обёртка).
	 */
	private function calculatePairsForUser(array $products)
	{
		$pairs = [];
		$count = count($products);
		for ($i = 0; $i < $count; $i++) {
			for ($j = $i + 1; $j < $count; $j++) {
				$id1 = (int)$products[$i];
				$id2 = (int)$products[$j];
				$pId1 = min($id1, $id2);
				$pId2 = max($id1, $id2);
				$pairKey = $pId1 . '_' . $pId2;
				$pairs[$pairKey] = [
					'product_id_1' => $pId1,
					'product_id_2' => $pId2,
					'weight' => ($pairs[$pairKey]['weight'] ?? 0) + 1
				];
			}
		}
		return $pairs;
	}
}
