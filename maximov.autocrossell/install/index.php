<?php

/**
 * @author Denis Maximov
 * @link https://marketplace.1c-bitrix.ru/solutions/maximov.autocrossell/
 * @license GPL-3.0-or-later
 */
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
	die();
}

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;
use Maximov\Autocrossell\Logger;
use Bitrix\Main\Config\Option;

class maximov_autocrossell extends CModule
{
	public $MODULE_ID = "maximov.autocrossell";
	public $MODULE_VERSION;
	public $MODULE_VERSION_DATE;
	public $MODULE_NAME;
	public $MODULE_DESCRIPTION;
	public $PARTNER_NAME;
	public $PARTNER_URI;
	public $MODULE_GROUP_RIGHTS = "Y";

	public function __construct()
	{
		include __DIR__ . "/version.php";
		Loc::loadMessages(__FILE__);
		$this->MODULE_VERSION = $arModuleVersion["VERSION"] ?? "";
		$this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"] ?? "";
		$this->MODULE_NAME = GetMessage("AUTOCROSSELL_MODULE_NAME");
		$this->MODULE_DESCRIPTION = GetMessage("AUTOCROSSELL_MODULE_DESC");
		$this->PARTNER_NAME = "Денис Максимов";
		$this->PARTNER_URI = "https://github.com/Trionikl";
	}

	public function DoInstall()
	{
		global $APPLICATION, $USER;
		if (!$USER->IsAdmin()) {
			return;
		}
		if (!check_bitrix_sessid()) {
			return false;
		}

		// 1. Явно подключаем класс, так как автозагрузчик lib/ ещё не активен
		$libPath = dirname(__DIR__) . "/lib/AutocrossellPairsTable.php";
		if (file_exists($libPath)) {
			require_once $libPath;
		}
		$schedulerPath = dirname(__DIR__) . "/lib/AgentScheduler.php";
		if (file_exists($schedulerPath)) {
			require_once $schedulerPath;
		}

		// Регистрируем модуль ДО подключения (Loader::includeModule требует регистрации)
		ModuleManager::registerModule($this->MODULE_ID);
		Loader::includeModule($this->MODULE_ID);

		$this->logInstall('Начало установки модуля');

		// 2. Создание таблицы и вторичных индексов через штатный DDL API D7
		$connection = Application::getConnection();
		$tableName = \Maximov\Autocrossell\AutocrossellPairsTable::getTableName();

		// 2.1 Создание таблицы, если её нет
		if (!$connection->isTableExists($tableName)) {
			\Maximov\Autocrossell\AutocrossellPairsTable::getEntity()->createDbTable();
			$this->logInstall("Таблица {$tableName} создана");
		}

		// 2.2 Создание вторичных индексов с проверкой существования
		$indexes = [
			'IDX_P1' => ['PRODUCT_ID_1'],
			'IDX_P2' => ['PRODUCT_ID_2'],
		];
		foreach ($indexes as $indexName => $columns) {
			if (!$connection->isIndexExists($tableName, $columns)) {
				$connection->createIndex($tableName, $indexName, $columns);
				$this->logInstall("Индекс {$indexName} добавлен");
			}
		}

		// 2.3 Уникальный индекс UX_PAIR для ON DUPLICATE KEY UPDATE (идемпотентно)
		$this->ensureUniquePairIndex($connection, $tableName);

		$this->logInstall('Модуль зарегистрирован');

		$this->setDefaultOptions();
		$this->logInstall('Настройки по умолчанию установлены');

		$this->registerAgent();
		$this->logInstall('Агент зарегистрирован');

		// === РЕГИСТРАЦИЯ СОБЫТИЙ (по EVENTS_REGISTRATION.md) ===
		// OnProlog подключает CSS/JS виджета до вывода <head> — ShowHead() в шаблонах
		// отрабатывает после него, поэтому ассеты гарантированно попадают в head.
		// Регистрируем через D7 EventManager — класс подхватится автозагрузчиком.
		\Bitrix\Main\EventManager::getInstance()->registerEventHandler(
			'main',
			'OnProlog',
			$this->MODULE_ID,
			'\Maximov\Autocrossell\Events',
			'onProlog'
		);
		$this->logInstall('Событие OnProlog зарегистрировано');

		// Легаси-чистка: старые версии модуля регистрировали main/OnBeforeCacheClear,
		// обработчик удалён из lib/Events.php — снимаем зависимость (идемпотентно),
		// иначе ядро будет звать несуществующий метод при очистке кэша.
		UnRegisterModuleDependences('main', 'OnBeforeCacheClear', $this->MODULE_ID);
		$this->logInstall('Легаси-регистрация OnBeforeCacheClear снята');

		$this->InstallFiles();
		$this->logInstall('Установка модуля завершена');

		$APPLICATION->IncludeAdminFile(
			GetMessage('AUTOCROSSELL_INSTALL_TITLE') . ' ' . $this->MODULE_NAME,
			__DIR__ . '/step.php'
		);
	}

	private function logInstall(string $message): void
	{
		try {
			if (class_exists('\\Maximov\\Autocrossell\\Logger')) {
				Logger::log('[install] ' . $message);
			}
		} catch (Exception $e) {
			// Игнорируем ошибки логирования
		}
	}

	/**
	 * Добавление уникального индекса UX_PAIR на (PRODUCT_ID_1, PRODUCT_ID_2).
	 * Идемпотентно: при повторном запуске пропускает, если индекс уже существует.
	 *
	 * Индекс необходим для корректной работы ON DUPLICATE KEY UPDATE в
	 * пакетной вставке пар (prepareMergeValues / writeBatchToDb).
	 */
	private function ensureUniquePairIndex($connection, string $tableName): void
	{
		$indexName = 'UX_PAIR';
		$columns = ['PRODUCT_ID_1', 'PRODUCT_ID_2'];

		if ($connection->isIndexExists($tableName, $columns)) {
			return;
		}

		// Добавляем уникальный индекс (прямой DDL — createIndex не поддерживает UNIQUE)
		try {
			$connection->queryExecute(
				"ALTER TABLE `{$tableName}` ADD UNIQUE INDEX `{$indexName}` (`PRODUCT_ID_1`, `PRODUCT_ID_2`)"
			);
			$this->logInstall("Уникальный индекс {$indexName} добавлен");
		} catch (\Exception $e) {
			// Индекс мог быть добавлен параллельно — игнорируем
			$this->logInstall("Уникальный индекс {$indexName} уже существует или ошибка: " . $e->getMessage());
		}
	}

	public function DoUninstall()
	{
		global $APPLICATION, $USER;
		if (!$USER->IsAdmin()) {
			return;
		}
		if (!check_bitrix_sessid()) {
			return false;
		}

		$request = \Bitrix\Main\Context::getCurrent()->getRequest();

		// Двухшаговое удаление: шаг 1 — форма с чекбоксом «Сохранить таблицы»,
		// шаг 2 — собственно деинсталляция на основе выбора администратора.
		$step = (int)($request->get('step') ?? 1);
		if ($step < 2) {
			$APPLICATION->IncludeAdminFile(
				GetMessage('AUTOCROSSELL_UNINSTALL_TITLE') . ' ' . $this->MODULE_NAME,
				__DIR__ . '/uninstall_step1.php'
			);
			return;
		}

		// 1. Удаление агента (сброс зависшего RUNNING/ATTEMPTS при переустановке)
		$this->unregisterAgent();
		$this->logInstall('Агент удалён');

		// 2. Отмена регистрации событий, иначе обработчики остаются "висячими"
		// после удаления модуля.
		\Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
			'main',
			'OnProlog',
			$this->MODULE_ID,
			'\Maximov\Autocrossell\Events',
			'onProlog'
		);
		$this->logInstall('Событие OnProlog отменено');

		// Легаси: снятие регистрации OnBeforeCacheClear (старые версии модуля),
		// обработчик удалён из lib/Events.php. Идемпотентно: если регистрации
		// нет — удаляется ноль строк.
		UnRegisterModuleDependences('main', 'OnBeforeCacheClear', $this->MODULE_ID);
		$this->logInstall('Легаси-событие OnBeforeCacheClear отменено');

		// 3. Удаляем файлы компонента и ассеты виджета
		$this->UninstallFiles();

		// 4. Таблица БД — по выбору администратора (savedata)
		$savedata = $request->get('savedata') ?? 'N';
		$this->UnInstallDB([
			'savedata' => $savedata,
		]);

		// 5. Очистка опций модуля — только при полном удалении.
		//    При savedata=Y настройки сохраняются и переиспользуются
		//    при повторной установке.
		if ($savedata !== 'Y') {
			Option::delete($this->MODULE_ID);
		}

		// 6. Сброс тегированного кэша модуля, чтобы на витрине не висел
		// закешированный HTML удалённого компонента recommendations.
		\Bitrix\Main\Application::getInstance()
			->getTaggedCache()
			->clearByTag('maximov.autocrossell');

		// 7. Отключение модуля
		ModuleManager::unRegisterModule($this->MODULE_ID);

		$APPLICATION->IncludeAdminFile(
			GetMessage('AUTOCROSSELL_UNINSTALL_TITLE') . ' ' . $this->MODULE_NAME,
			__DIR__ . '/uninstall_step2.php'
		);
	}

	public function UnInstallDB(array $params = [])
	{
		$savedata = $params['savedata'] ?? 'N';
		if ($savedata === 'Y') {
			// Сохраняем накопленные данные сопутствующих товаров: переиспользуются
			// при повторной установке, createDbTable() идемпотентен.
			$this->logInstall('Таблица b_maximov_autocrossell_pairs сохранена (savedata=Y)');
			return true;
		}

		if (\Bitrix\Main\Loader::includeModule($this->MODULE_ID)) {
			$connection = \Bitrix\Main\Application::getConnection();
			$tableName = \Maximov\Autocrossell\AutocrossellPairsTable::getTableName();
			if ($connection->isTableExists($tableName)) {
				$connection->dropTable($tableName);
				$this->logInstall('Таблица ' . $tableName . ' удалена');
			}
		}
		return true;
	}

	private function registerAgent()
	{
		// Идемпотентность: перед добавлением агента снимаем все агенты
		// модуля, чтобы при повторной установке не накапливались дубли.
		\CAgent::RemoveModuleAgents($this->MODULE_ID);

		$agentName = "\\Maximov\\Autocrossell\\RecalculationAgent::execute();";
		$nextExec = \Maximov\Autocrossell\AgentScheduler::getNextWindowStart();
		$agentInterval = \Maximov\Autocrossell\AgentScheduler::getAgentInterval();
		CAgent::AddAgent(
			$agentName,
			$this->MODULE_ID,
			"Y",
			$agentInterval,
			"",
			"Y",
			$nextExec,
		);

		// Страховка: если ядро не выставило NEXT_EXEC / IS_PERIOD / AGENT_INTERVAL корректно — обновляем
		$res = CAgent::GetList(
			[],
			["NAME" => $agentName, "MODULE_ID" => $this->MODULE_ID],
		);
		while ($ar = $res->Fetch()) {
			CAgent::Update($ar["ID"], [
				"IS_PERIOD" => "Y",
				"AGENT_INTERVAL" => $agentInterval,
				"NEXT_EXEC" => $nextExec,
			]);
		}
	}

	private function unregisterAgent()
	{
		// Агенты удаляются всегда, независимо от флага savedata.
		\CAgent::RemoveModuleAgents($this->MODULE_ID);
	}

	private function setDefaultOptions()
	{
		// Пишем только отсутствующие опции, чтобы при повторной установке
		// не затёрлись значения, уже выставленные администратором.
		$defaults = [
			"min_threshold" => "2",
			"history_days" => "180",
			"agent_window_start" => "02:00",
			"agent_window_end" => "06:00",
			"agent_interval" => "24",
			"debug" => "N",
		];
		foreach ($defaults as $name => $value) {
			// Не перезаписываем опцию, если она уже существует в БД
			// (например, осталась после удаления с сохранением данных).
			if (Option::get($this->MODULE_ID, $name, null) === null) {
				Option::set($this->MODULE_ID, $name, $value);
			}
		}
	}

	public function InstallFiles()
	{
		// В D7 страница настроек модуля (options.php) лежит в корне модуля и
		// автоматически подключается ядром — отдельных админ-обёрток не требуется.
		// Копируем компонент в публичную директорию (с очисткой перед копированием).
		$componentFrom = dirname(__DIR__) . "/install/components/autocrossell";
		$componentTo = Application::getDocumentRoot() . "/bitrix/components/maximov.autocrossell";
		$this->removeDirectory($componentTo);
		if (is_dir($componentFrom)) {
			try {
				CopyDirFiles($componentFrom, $componentTo, true, true);
				$this->logInstall('Компонент скопирован в ' . $componentTo);
			} catch (Exception $e) {
				$this->logInstall('Ошибка копирования компонента: ' . $e->getMessage());
			}
		}

		// Копируем фронтенд-ассеты виджета (CSS + JS) для AJAX-инъекции рекомендаций.
		$jsFrom = dirname(__DIR__) . "/install/js/maximov.autocrossell";
		$jsTo = Application::getDocumentRoot() . "/bitrix/js/maximov.autocrossell";
		$this->removeDirectory($jsTo);
		if (is_dir($jsFrom)) {
			try {
				CopyDirFiles($jsFrom, $jsTo, true, true);
				$this->logInstall('Ассеты виджета скопированы в ' . $jsTo);
			} catch (Exception $e) {
				$this->logInstall('Ошибка копирования ассетов виджета: ' . $e->getMessage());
			}
		}
		return true;
	}

	public function UninstallFiles()
	{
		// D7: DeleteDirFilesEx() принимает путь ОТ КОРНЯ САЙТА и сам дописывает
		// DOCUMENT_ROOT. Передавать ему абсолютный путь нельзя — получится двойной
		// DOCUMENT_ROOT, и удаления не произойдёт. Поэтому используем D7 IO,
		// который работает с абсолютными путями от корня сервера.
		$componentDir = Application::getDocumentRoot() . '/bitrix/components/' . $this->MODULE_ID;
		$this->removeDirectory($componentDir);

		$jsDir = Application::getDocumentRoot() . '/bitrix/js/' . $this->MODULE_ID;
		$this->removeDirectory($jsDir);

		return true;
	}

	/**
	 * Удаление каталога через D7 IO с предварительной проверкой существования.
	 * deleteDirectory() рекурсивен и работает с абсолютным путём от корня сервера.
	 */
	private function removeDirectory(string $path): void
	{
		if (!Directory::isDirectoryExists($path)) {
			$this->logInstall('Каталог не найден, пропуск удаления: ' . $path);
			return;
		}

		try {
			Directory::deleteDirectory($path);
			$this->logInstall('Каталог удалён: ' . $path);
		} catch (\Exception $e) {
			$this->logInstall('Ошибка удаления каталога ' . $path . ': ' . $e->getMessage());
		}
	}
}
