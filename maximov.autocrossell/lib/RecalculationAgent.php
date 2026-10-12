<?php

/**
 * RecalculationAgent
 *
 * Агент для фонового пересчёта блока «С этим товаром покупают».
 *
 * Потоковый алгоритм (v2):
 *  — Pass 1: уникальные PRODUCT_ID → карта SKU (BasketAnalyzer::buildSkuMap);
 *  — Pass 2: keyset-курсор по заказам (ORDER_ID ASC, ID ASC),
 *    попарная генерация по одному заказу (BasketAnalyzer::generatePairs),
 *    буферизация в пакеты ≤500, пакетная запись через SqlHelper::prepareMergeValues.
 *    Курсор продвигается по последней СЫРОЙ строке страницы — «дыры»
 *    (корзины с нулевой/старой датой) не обрывают поток;
 *  — Очистка таблицы в начале прогона (детерминированный пересчёт «с нуля»);
 *  — Финальное слияние весов и очистка ниже порога на уровне приложения
 *    (гарантия корректности независимо от наличия уникального индекса UX_PAIR).
 *
 * @package maximov.autocrossell
 
 * @author Denis Maximov
 * @link https://marketplace.1c-bitrix.ru/solutions/maximov.autocrossell/
 * @license GPL-3.0-or-later
 */

namespace Maximov\Autocrossell;

use Bitrix\Main\Config\Option;
use DateTime;
use Exception;
use Throwable;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime as BitrixDateTime;
use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Sale\Internals\BasketTable;

class RecalculationAgent
{
	private AgentGuard $agentGuard;
	private BasketAnalyzer $basketAnalyzer;

	/** @var string|null Дата начала окна истории (Y-m-d H:i:s) для ручной фильтрации */
	private ?string $fromDateStr = null;

	/** Размер пакета для writeBatchToDb */
	private const BATCH_SIZE = 500;

	/** Размер страницы keyset-курсора */
	private const CURSOR_PAGE_SIZE = 2000;

	public function __construct()
	{
		$this->agentGuard = new AgentGuard();
		$this->basketAnalyzer = new BasketAnalyzer();
	}

	/**
	 * Проверка свободной памяти (≥50 MB)
	 */
	private function checkMemoryLimit(): bool
	{
		$memoryLimit = ini_get('memory_limit');
		if ($memoryLimit == -1) {
			return true;
		}
		$memoryBytes = rtrim($memoryLimit, 'MmGg');
		$unit = strtoupper(substr($memoryLimit, -1));
		if ($unit === 'G') {
			$memoryBytes *= 1024 * 1024 * 1024;
		} elseif ($unit === 'M') {
			$memoryBytes *= 1024 * 1024;
		} else {
			$memoryBytes = (int)$memoryLimit;
		}
		$freeMemory = $memoryBytes - memory_get_usage(true);
		if ($freeMemory < 50 * 1024 * 1024) {
			Logger::error("Недостаточно памяти (свободно < 50MB)", [
				'memory_limit' => $memoryLimit,
				'used_memory' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
				'free_memory' => round($freeMemory / 1024 / 1024, 2) . 'MB',
			]);
			return false;
		}
		return true;
	}

	/**
	 * Точка входа агента (контракт с планировщиком Битрикс).
	 *
	 * Блокировка через нативный D7-замок СУБД (MySQL GET_LOCK / PG advisory lock).
	 * При падении PHP соединение закрывается, СУБД снимает замок автоматически.
	 *
	 * @param bool $forced Принудительный запуск (обходит проверку окна)
	 * @return string|array Строка вызова агента (normal) или массив статуса (forced)
	 */
	public static function execute($forced = false)
	{
		$instance = new self();

		if (!$instance->checkMemoryLimit()) {
			if ($forced) {
				Logger::error('Ручной пересчёт: недостаточно памяти', [
					'memory_limit' => ini_get('memory_limit'),
					'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
				]);
				return ['status' => 'error', 'reason' => 'Недостаточно памяти для расчёта (свободно < 50MB).'];
			}
			return "\\Maximov\\Autocrossell\\RecalculationAgent::execute();";
		}

		$guard = $instance->agentGuard;
		$locked = false;

		// Плановый агент: проверка окна — до захвата замка (не трогаем СУБД без необходимости)
		if (!$forced && !$guard->canRun()) {
			Logger::log("Агент не может быть запущен", [
				'reason' => 'time_window',
			]);
			return "\\Maximov\\Autocrossell\\RecalculationAgent::execute();";
		}

		// Атомарный замок СУБД (timeout=0 — моментальный выход)
		$locked = $guard->acquire();
		if (!$locked) {
			Logger::log('Пересчёт уже выполняется (замок СУБД занят)');
			return $forced
				? ['status' => 'busy', 'reason' => 'Расчёт уже выполняется. Попробуйте позже.']
				: "\\Maximov\\Autocrossell\\RecalculationAgent::execute();";
		}

		try {
			$startTime = microtime(true);
			Logger::log("Начало пересчёта (потоковый алгоритм)", [
				'start_time' => date('Y-m-d H:i:s'),
			]);

			// --- Параметры ---
			$historyDays = (int)Option::get('maximov.autocrossell', 'history_days', '180');
			$minThreshold = (int)Option::get('maximov.autocrossell', 'min_threshold', '2');

			$phpDate = new DateTime();
			$phpDate->modify("-{$historyDays} days");
			$fromDate = BitrixDateTime::createFromPhp($phpDate);
			$instance->fromDateStr = $fromDate->format('Y-m-d H:i:s');

			// --- Очистка таблицы перед пересчётом (детерминированный расчёт «с нуля») ---
			$instance->clearPairsTable();

			// --- Pass 1: карта SKU ---
			$uniqueProductIds = $instance->collectUniqueProductIds();
			if (empty($uniqueProductIds)) {
				$reason = 'Нет товаров в корзинах для анализа';
				Logger::log("Пересчёт завершён: " . $reason);
				return $forced
					? ['status' => 'empty', 'reason' => $reason]
					: "\\Maximov\\Autocrossell\\RecalculationAgent::execute();";
			}

			$skuMap = $instance->basketAnalyzer->buildSkuMap($uniqueProductIds);
			Logger::log("Pass 1: карта SKU построена", [
				'unique_product_ids' => count($uniqueProductIds),
				'sku_mappings' => count($skuMap),
			]);

			// --- Pass 2: потоковый расчёт ---
			$batch = [];
			$totalPairs = 0;
			$flushedCount = 0;
			$currentOrderId = null;
			$orderProducts = [];
			$ordersProcessed = 0;

			$lastOrderId = 0;
			$lastBasketId = 0;
			$page = 0;

			while (true) {
				if (!$instance->checkMemoryLimit()) {
					Logger::error("Прерывание потока: мало памяти", ['page' => $page]);
					break;
				}

				$pageData = $instance->streamBaskets($lastOrderId, $lastBasketId);

				// Реальный конец данных: страница не вернула ни одной сырой строки
				if ($pageData['raw_count'] === 0) {
					break;
				}

				// Курсор продвигаем по последней СЫРОЙ строке страницы (не по
				// отфильтрованным), чтобы «дыры» — страницы, целиком состоящие
				// из корзин с нулевой/старой датой — не обрывали поток
				$lastOrderId = (int)$pageData['last_raw_order_id'];
				$lastBasketId = (int)$pageData['last_raw_basket_id'];

				foreach ($pageData['rows'] as $row) {
					$orderId = (int)$row['ORDER_ID'];
					$productId = (int)$row['PRODUCT_ID'];

					if ($currentOrderId !== null && $orderId !== $currentOrderId) {
						$batch = $instance->flushOrder($orderProducts, $batch, $totalPairs, $flushedCount);
						$orderProducts = [];
						$ordersProcessed++;
					}
					$currentOrderId = $orderId;

					$parentId = $instance->basketAnalyzer->mapToParent($productId);

					if ($parentId <= 0 || $parentId === $productId) {
						if ($instance->basketAnalyzer->isSkipped($productId)) {
							continue;
						}
					}

					$orderProducts[$parentId] = true;
				}

				$page++;
			}

			if (!empty($orderProducts)) {
				$batch = $instance->flushOrder($orderProducts, $batch, $totalPairs, $flushedCount);
				$ordersProcessed++;
			}

			if (!empty($batch)) {
				$instance->writeBatchToDb($batch);
				$flushedCount += count($batch);
				$batch = [];
			}

			Logger::log("Потоковый расчёт завершён", [
				'orders_processed' => $ordersProcessed,
				'total_pairs_generated' => $totalPairs,
				'total_flushed' => $flushedCount,
				'batch_size_limit' => self::BATCH_SIZE,
			]);

			$pairsSaved = $instance->mergeAndCleanupBelowThreshold($minThreshold);
			$instance->generateRenderLog();

			$docRoot = Application::getDocumentRoot();
			TestUrlsGenerator::generate(
				$docRoot . '/bitrix/modules/maximov.autocrossell/tests/e2e/test_urls.json'
			);
			Application::getInstance()->getTaggedCache()->clearByTag('maximov.autocrossell');

			$duration = round((microtime(true) - $startTime) * 1000, 2);
			Logger::log("Пересчёт завершён успешно", [
				'end_time' => date('Y-m-d H:i:s'),
				'duration' => $duration . 'ms',
				'total_pairs_written' => $flushedCount,
				'pairs_saved' => $pairsSaved,
			]);

			if ($forced) {
				return [
					'status' => 'ok',
					'reason' => 'Сохранено пар: ' . $pairsSaved,
					'pairs_count' => $pairsSaved,
					'pairs_written' => $flushedCount,
					'duration_ms' => $duration,
				];
			}
			return "\\Maximov\\Autocrossell\\RecalculationAgent::execute();";
		} catch (Exception $e) {
			Logger::error("Ошибка при пересчёте: " . $e->getMessage(), [
				'exception' => get_class($e),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
			]);
			return $forced
				? ['status' => 'error', 'reason' => 'Ошибка при пересчёте: ' . $e->getMessage()]
				: "\\Maximov\\Autocrossell\\RecalculationAgent::execute();";
		} finally {
			if ($locked) {
				$guard->release();
			}
		}
	}

	// ================================================================
	// PASS 1: сбор уникальных PRODUCT_ID
	// ================================================================

	/**
	 * Сбор уникальных PRODUCT_ID из корзин (для построения карты SKU).
	 *
	 * @return array<int> Уникальные ID
	 */
	private function collectUniqueProductIds(): array
	{
		if (!Loader::includeModule('sale')) {
			return [];
		}

		$res = BasketTable::getList([
			'select' => ['PRODUCT_ID'],
			'filter' => ['>ORDER_ID' => 0, 'ORDER.STATUS_ID' => ['P', 'F']],
			'group' => ['PRODUCT_ID'],
		]);

		$ids = [];
		while ($row = $res->fetch()) {
			$id = (int)$row['PRODUCT_ID'];
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	// ================================================================
	// PASS 2: потоковый курсор
	// ================================================================

	/**
	 * Страница keyset-курсора: следующие строки корзин.
	 *
	 * Сортировка ORDER_ID ASC, ID ASC — обеспечивает последовательное
	 * прохождение заказов без пропусков и дублей.
	 *
	 * Метод возвращает не только отфильтрованные по дате строки, но и
	 * позицию последней СЫРОЙ строки страницы. Это позволяет курсору
	 * перешагивать «дыры» (страницы целиком из корзин с нулевой/старой
	 * датой), не прерывая поток: стоп наступает только когда raw-страница пуста.
	 *
	 * @param int $lastOrderId ID последнего обработанного заказа
	 * @param int $lastBasketId ID последней обработанной строки корзины
	 * @return array{rows: array, raw_count: int, last_raw_order_id: int, last_raw_basket_id: int}
	 */
	private function streamBaskets(int $lastOrderId, int $lastBasketId): array
	{
		if (!Loader::includeModule('sale')) {
			return ['rows' => [], 'raw_count' => 0, 'last_raw_order_id' => 0, 'last_raw_basket_id' => 0];
		}

		$filter = [
			'>ORDER_ID' => 0,
			'ORDER.STATUS_ID' => ['P', 'F'],
		];

		// Keyset-условие: после последнего обработанного элемента
		if ($lastOrderId > 0 || $lastBasketId > 0) {
			$filter = [
				'LOGIC' => 'AND',
				['ORDER.STATUS_ID' => ['P', 'F']],
				[
					'LOGIC' => 'OR',
					['>ORDER_ID' => $lastOrderId],
					['=ORDER_ID' => $lastOrderId, '>ID' => $lastBasketId],
				],
			];
		}

		$res = BasketTable::getList([
			'select' => ['ID', 'ORDER_ID', 'PRODUCT_ID', 'DATE_INSERT'],
			'filter' => $filter,
			'order' => ['ORDER_ID' => 'ASC', 'ID' => 'ASC'],
			'limit' => self::CURSOR_PAGE_SIZE,
		]);

		$rows = [];
		$rawCount = 0;
		$lastRawOrderId = 0;
		$lastRawBasketId = 0;
		while ($row = $res->fetch()) {
			$rawCount++;
			$lastRawOrderId = (int)$row['ORDER_ID'];
			$lastRawBasketId = (int)$row['ID'];

			// Ручная фильтрация по дате (ORM-фильтр по дате вызывает ошибки MySQL)
			$insertDate = $row['DATE_INSERT'];
			if (empty($insertDate) || $insertDate == '0000-00-00 00:00:00') {
				continue;
			}
			if (strtotime($insertDate) < strtotime($this->fromDateStr ?? '2000-01-01')) {
				continue;
			}
			$rows[] = $row;
		}

		return [
			'rows' => $rows,
			'raw_count' => $rawCount,
			'last_raw_order_id' => $lastRawOrderId,
			'last_raw_basket_id' => $lastRawBasketId,
		];
	}

	// ================================================================
	// ОБРАБОТКА ЗАКАЗА
	// ================================================================

	/**
	 * Сброс накопленных товаров одного заказа: generatePairs → аккумуляция в буфер.
	 *
	 * @param array<int, true> $orderProducts Уникальные родительские ID товаров заказа
	 * @param array $batch Текущий буфер пакетной записи
	 * @param int &$totalPairs Счётчик сгенерированных пар (passed by reference)
	 * @param int &$flushedCount Счётчик записанных пар (passed by reference)
	 * @return array Обновлённый буфер
	 */
	private function flushOrder(
		array $orderProducts,
		array $batch,
		int &$totalPairs,
		int &$flushedCount
	): array {
		if (count($orderProducts) < 2) {
			return $batch;
		}

		$pairs = $this->basketAnalyzer->generatePairs(array_keys($orderProducts));

		foreach ($pairs as $key => $pair) {
			$totalPairs++;

			if (isset($batch[$key])) {
				$batch[$key]['weight'] += $pair['weight'];
			} else {
				$batch[$key] = [
					'product_id_1' => $pair['product_id_1'],
					'product_id_2' => $pair['product_id_2'],
					'weight' => $pair['weight'],
				];
			}

			// Пакетная запись при достижении лимита
			if (count($batch) >= self::BATCH_SIZE) {
				$this->writeBatchToDb($batch);
				$flushedCount += count($batch);
				$batch = [];
			}
		}

		return $batch;
	}

	// ================================================================
	// ПАКЕТНАЯ ЗАПИСЬ (writeBatchToDb)
	// ================================================================

	/**
	 * Пакетная вставка/обновление пар через SqlHelper::prepareMergeValues.
	 *
	 * Генерирует INSERT ... ON DUPLICATE KEY UPDATE_WEIGHT = WEIGHT + VALUES(WEIGHT),
	 *UPDATED_AT = VALUES(UPDATED_AT) одним запросом на пачку.
	 *
	 * Все переменные экранируются через forSql/SqlExpression (защита от SQL-инъекций).
	 * Имя таблицы берётся динамически через AutocrossellPairsTable::getTableName().
	 *
	 * Требует уникальный индекс UX_PAIR (PRODUCT_ID_1, PRODUCT_ID_2) для
	 * корректной работы ON DUPLICATE KEY UPDATE.
	 *
	 * @param array<string, array{product_id_1: int, product_id_2: int, weight: int}> $batch
	 */
	private function writeBatchToDb(array $batch): void
	{
		if (empty($batch)) {
			return;
		}

		$connection = Application::getConnection();
		$helper = $connection->getSqlHelper();
		$tableName = AutocrossellPairsTable::getTableName();

		$now = BitrixDateTime::createFromPhp(new DateTime());

		$insertRows = [];
		foreach ($batch as $pair) {
			$insertRows[] = [
				'PRODUCT_ID_1' => (int)$pair['product_id_1'],
				'PRODUCT_ID_2' => (int)$pair['product_id_2'],
				'WEIGHT' => (int)$pair['weight'],
				'UPDATED_AT' => $now,
			];
		}

		$updateFields = [
			'WEIGHT' => new \Bitrix\Main\DB\SqlExpression('?# + ?i', 'WEIGHT', 1),
			'UPDATED_AT' => $now,
		];

		// prepareMergeValues: пакетный INSERT ... ON DUPLICATE KEY UPDATE
		if (method_exists($helper, 'prepareMergeValues')) {
			$sql = $helper->prepareMergeValues(
				$tableName,
				['PRODUCT_ID_1', 'PRODUCT_ID_2'],
				$insertRows,
				$updateFields
			);
			$connection->queryExecute($sql);
		} else {
			// Fallback для старых ядер: prepareMerge по одной строке
			foreach ($insertRows as $row) {
				[$mergeSql] = $helper->prepareMerge(
					$tableName,
					['PRODUCT_ID_1', 'PRODUCT_ID_2'],
					$row,
					$updateFields
				);
				if ($mergeSql) {
					$connection->query($mergeSql);
				}
			}
		}
	}

	// ================================================================
	// ОЧИСТКА ТАБЛИЦЫ ПАР
	// ================================================================

	/**
	 * Полная очистка таблицы пар через ORM пакетами.
	 *
	 * Вызывается перед пересчётом (детерминированный расчёт «с нуля»),
	 * а также внутри финального слияния перед перезаписью сохранённых пар.
	 *
	 * @return void
	 */
	private function clearPairsTable(): void
	{
		$deleted = 0;

		while (true) {
			$ids = [];
			$res = AutocrossellPairsTable::getList([
				'select' => ['ID'],
				'order' => ['ID' => 'ASC'],
				'limit' => 1000,
			]);
			while ($row = $res->fetch()) {
				$ids[] = (int)$row['ID'];
			}

			if (empty($ids)) {
				break;
			}

			foreach ($ids as $id) {
				$result = AutocrossellPairsTable::delete($id);
				if ($result->isSuccess()) {
					$deleted++;
				}
			}
		}

		if ($deleted > 0) {
			Logger::log("Таблица пар очищена", ['deleted' => $deleted]);
		}
	}

	// ================================================================
	// ФИНАЛЬНОЕ СЛИЯНИЕ И ОЧИСТКА НИЖЕ ПОРОГА
	// ================================================================

	/**
	 * Слияние весов и удаление пар ниже порога.
	 *
	 * Уникальный индекс UX_PAIR создаётся установщиком
	 * (migrateAddUniquePairIndex), но таблицы, созданные до его появления
	 * (сохранённые при переустановке с savedata=Y), не имеют уникального
	 * ключа — ON DUPLICATE KEY UPDATE в них не сливает пары из разных
	 * батчей (дубли и дробление веса). Метод выполняет слияние на уровне
	 * приложения (идемпотентно и при наличии UX_PAIR):
	 *  1. читает все строки таблицы (после очистки в начале — только
	 *     строки текущего прогона);
	 *  2. агрегирует веса по паре (с нормализацией порядка товаров);
	 *  3. отбрасывает пары с WEIGHT < min_threshold;
	 *  4. переписывает таблицу.
	 *
	 * @param int $minThreshold Минимальный порог веса
	 * @return int Количество пар, оставшихся в таблице после порога
	 */
	private function mergeAndCleanupBelowThreshold(int $minThreshold): int
	{
		$res = AutocrossellPairsTable::getList([
			'select' => ['PRODUCT_ID_1', 'PRODUCT_ID_2', 'WEIGHT'],
		]);

		$merged = [];
		while ($row = $res->fetch()) {
			$a = (int)$row['PRODUCT_ID_1'];
			$b = (int)$row['PRODUCT_ID_2'];
			$weight = (int)$row['WEIGHT'];

			if ($a <= 0 || $b <= 0) {
				continue;
			}

			$key = $a < $b ? $a . ':' . $b : $b . ':' . $a;
			$merged[$key] = ($merged[$key] ?? 0) + $weight;
		}

		$kept = [];
		foreach ($merged as $key => $weight) {
			if ($weight < $minThreshold) {
				continue;
			}
			[$a, $b] = explode(':', $key);
			$kept[$key] = [
				'PRODUCT_ID_1' => (int)$a,
				'PRODUCT_ID_2' => (int)$b,
				'WEIGHT' => (int)$weight,
			];
		}

		$this->clearPairsTable();

		$batch = [];
		$saved = 0;
		foreach ($kept as $key => $pair) {
			$batch[$key] = [
				'product_id_1' => $pair['PRODUCT_ID_1'],
				'product_id_2' => $pair['PRODUCT_ID_2'],
				'weight' => $pair['WEIGHT'],
			];

			if (count($batch) >= self::BATCH_SIZE) {
				$this->writeBatchToDb($batch);
				$saved += count($batch);
				$batch = [];
			}
		}

		if (!empty($batch)) {
			$this->writeBatchToDb($batch);
			$saved += count($batch);
		}

		Logger::log("Финальное слияние завершено", [
			'merged_candidates' => count($merged),
			'pairs_saved' => $saved,
			'min_threshold' => $minThreshold,
		]);

		return $saved;
	}

	// ================================================================
	// ГЕНЕРАЦИЯ ЛОГ-ФАЙЛА (без изменений — см. оригинал)
	// ================================================================

	private function generateRenderLog(): void
	{
		try {
			$minThreshold = (int)Option::get('maximov.autocrossell', 'min_threshold', 2);

			$pairs = AutocrossellPairsTable::getList([
				'select' => ['PRODUCT_ID_1', 'PRODUCT_ID_2', 'WEIGHT'],
				'filter' => ['>=WEIGHT' => $minThreshold],
			])->fetchAll();

			if (empty($pairs)) {
				Logger::log('Нет товаров с рекомендациями для лог-файла');
				return;
			}

			$p1Ids = [];
			$p2Ids = [];
			foreach ($pairs as $pair) {
				$p1 = (int)$pair['PRODUCT_ID_1'];
				$p2 = (int)$pair['PRODUCT_ID_2'];
				if ($p1 > 0) {
					$p1Ids[$p1] = true;
				}
				if ($p2 > 0) {
					$p2Ids[$p2] = true;
				}
			}

			$allIds = array_unique(array_merge(array_keys($p1Ids), array_keys($p2Ids)));

			if (!Loader::includeModule('iblock')) {
				Logger::log('Модуль iblock не загружен для генерации render-лога');
				return;
			}

			// Легаси-API сознательный: только CIBlockElement вычисляет DETAIL_PAGE_URL
			// из шаблона инфоблока; у \Bitrix\Iblock\ElementTable такого поля нет.
			// linter:ignore:DEPRECATED_API
			$elements = \CIBlockElement::GetList(
				[],
				['=ID' => $allIds, '=ACTIVE' => 'Y'],
				false,
				false,
				['ID', 'IBLOCK_ID', 'NAME', 'CODE', 'IBLOCK_SECTION_ID', 'DETAIL_PAGE_URL']
			);

			$request = Context::getCurrent()->getRequest();
			$scheme = $request->isHttps() ? 'https' : 'http';
			$host = $request->getHttpHost() ?: '';
			$host = rtrim(preg_replace('/:\d+$/', '', $host), '/');

			$catalog = [];
			while ($el = $elements->GetNext()) {
				$url = $el['DETAIL_PAGE_URL'];
				if (!empty($url)) {
					$catalog[(int)$el['ID']] = [
						'name' => $el['NAME'],
						'url' => $scheme . '://' . $host . $url,
					];
				}
			}

			$logLines = [];
			$logLines[] = '=== AutoCrossell Render Log ===';
			$logLines[] = 'Generated: ' . date('Y-m-d H:i:s');
			$logLines[] = 'Min threshold: ' . $minThreshold;
			$logLines[] = 'Total pairs: ' . count($pairs);
			$logLines[] = 'Unique products: ' . count($catalog);
			$logLines[] = '';

			$logLines[] = '=== PRODUCT_ID_1 (главные товары) ===';
			foreach (array_keys($p1Ids) as $id) {
				if (isset($catalog[$id])) {
					$logLines[] = $catalog[$id]['url'] . ' (ID: ' . $id . ', Name: ' . $catalog[$id]['name'] . ')';
				} else {
					$logLines[] = '(ID: ' . $id . ', Name: НЕ НАЙДЕН)';
				}
			}
			$logLines[] = 'Total: ' . count($p1Ids);
			$logLines[] = '';

			$logLines[] = '=== PRODUCT_ID_2 (партнёры) ===';
			foreach (array_keys($p2Ids) as $id) {
				if (isset($catalog[$id])) {
					$logLines[] = $catalog[$id]['url'] . ' (ID: ' . $id . ', Name: ' . $catalog[$id]['name'] . ')';
				} else {
					$logLines[] = '(ID: ' . $id . ', Name: НЕ НАЙДЕН)';
				}
			}
			$logLines[] = 'Total: ' . count($p2Ids);
			$logLines[] = '';

			$logLines[] = '=== Проверка парных товаров ===';
			$logLines[] = '';

			$partnersByProduct = [];
			foreach ($pairs as $pair) {
				$p1 = (int)$pair['PRODUCT_ID_1'];
				$p2 = (int)$pair['PRODUCT_ID_2'];
				if ($p1 > 0 && $p2 > 0) {
					$partnersByProduct[$p1][$p2] = true;
					$partnersByProduct[$p2][$p1] = true;
				}
			}

			$allProductIds = array_keys($partnersByProduct);
			sort($allProductIds, SORT_NUMERIC);

			foreach ($allProductIds as $productId) {
				$partnerIds = array_keys($partnersByProduct[$productId]);
				sort($partnerIds, SORT_NUMERIC);

				$name = isset($catalog[$productId]) ? $catalog[$productId]['name'] : 'НЕ НАЙДЕН';
				$url = isset($catalog[$productId]) ? $catalog[$productId]['url'] : '(нет URL)';

				$logLines[] = '[ID: ' . $productId . ', ' . $name . '] ' . $url;
				$hasSelf = in_array($productId, $partnerIds, true);
				$logLines[] = '  -> сам себя: ' . ($hasSelf ? 'ПОКАЗЫВАЕТ (ОШИБКА!)' : 'НЕ показывает (OK)');
				$logLines[] = '  -> партнёры (' . count($partnerIds) . '):';

				if (empty($partnerIds)) {
					$logLines[] = '     (нет партнёров)';
				} else {
					foreach ($partnerIds as $partnerId) {
						$partnerName = isset($catalog[$partnerId]) ? $catalog[$partnerId]['name'] : 'НЕ НАЙДЕН';
						$partnerUrl = isset($catalog[$partnerId]) ? $catalog[$partnerId]['url'] : '(нет URL)';
						$logLines[] = '     - ' . $partnerUrl . ' (ID: ' . $partnerId . ', ' . $partnerName . ')';
					}
				}
				$logLines[] = '';
			}

			$logLines[] = '=== End ===';

			Logger::logFile(
				implode("\n", $logLines),
				'autocrossell_render.log',
				\Bitrix\Main\IO\File::REWRITE
			);

			Logger::log('Render log created: ' . count($catalog) . ' products, ' . count($pairs) . ' pairs');
		} catch (Throwable $t) {
			Logger::error('Render log generation failed', [
				'error' => $t->getMessage(),
				'file' => $t->getFile(),
				'line' => $t->getLine(),
			]);
		}
	}
}
