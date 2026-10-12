<?php

/**
 * Logger
 *
 * Служебный класс для логирования событий модуля AutoCrossell
 * Поддерживает два уровня: продакшен-лог и детальную отладку с контекстом
 *
 * @package maximov.autocrossell
 
 * @author Denis Maximov
 * @link https://marketplace.1c-bitrix.ru/solutions/maximov.autocrossell/
 * @license GPL-3.0-or-later
 */

namespace Maximov\Autocrossell;

use Bitrix\Main\Context;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;
use DateTimeInterface;

class Logger
{
	/**
	 * Тег для логов в bx.log
	 */
	private const LOG_TAG = 'autocrossell';

	/**
	 * Кэш значения настройки отладки
	 * @var bool|null
	 */
	private static $debugEnabledCache = null;

	/**
	 * Запись сообщения в лог
	 *
	 * Единая политика логирования: метод пишет в файл отладки ТОЛЬКО
	 * если включена настройка debug. При выключенной отладке (debug=N)
	 * метод ничего не пишет (ни в bx.log, ни в файл отладки).
	 *
	 * @param string $message Текст сообщения
	 * @param array $context Дополнительные данные для отладки (ассоциативный массив)
	 * @return void
	 *
	 * @example
	 * Logger::log('Order processed', ['order_id' => 123, 'products_count' => 5]);
	 * Logger::log('Cache cleared for product', ['product_id' => 456]);
	 */
	public static function log(string $message, array $context = []): void
	{
		if (!self::isDebugEnabled()) {
			return;
		}

		self::writeDebugLog($message, $context);
	}

	/**
	 * Запись ошибки в лог (всегда пишется, независимо от настройки debug)
	 *
	 * Файловая часть (autocrossell.log) молча пропускается, если
	 * папка logs/ недоступна для записи. Системный лог Битрикса
	 * (bx.log через AddMessage2Log) работает всегда.
	 *
	 * @param string $message Текст ошибки
	 * @param array $context Контекст ошибки
	 * @param string $level Уровень ошибки (error, warning, critical)
	 * @return void
	 */
	public static function error(string $message, array $context = [], string $level = 'error'): void
	{
		$logMessage = sprintf(
			'[%s] %s',
			strtoupper($level),
			$message
		);

		// Файловая часть — молча пропускается при read-only logs/
		self::writeProductionLog($logMessage, $level);

		// Если включена отладка — дублируем в файл отладки с контекстом
		if (self::isDebugEnabled()) {
			self::writeDebugLog($logMessage, $context);
		}
	}

	/**
	 * Проверка включена ли отладка в настройках модуля
	 *
	 * @return bool
	 */
	private static function isDebugEnabled(): bool
	{
		if (self::$debugEnabledCache === null) {
			self::$debugEnabledCache =
				Option::get('maximov.autocrossell', 'debug', 'N') === 'Y';
		}
		return self::$debugEnabledCache;
	}

	/**
	 * Запись в продакшен-лог (модульный файл + bx.log)
	 *
	 * @param string $message Сообщение
	 * @param string $level Уровень (для AddMessage2Log)
	 * @return void
	 */
	private static function writeProductionLog(string $message, string $level = ''): void
	{
		// Файловая часть — молча пропускается при read-only logs/
		self::logFile($message, 'autocrossell.log');

		// Дублируем в bx.log через стандартный механизм Битрикс
		if (function_exists('AddMessage2Log')) {
			AddMessage2Log($message, self::LOG_TAG);
		}
	}

	/**
	 * Запись в файл детальной отладки с контекстом
	 *
	 * @param string $message Сообщение
	 * @param array $context Контекст
	 * @return void
	 */
	private static function writeDebugLog(string $message, array $context): void
	{
		$uri = Context::getCurrent()->getRequest()->getRequestUri() ?? 'unknown';
		$addr = Context::getCurrent()->getRequest()->getRemoteAddress() ?? 'unknown';

		$logEntry = $message;

		if (!empty($context)) {
			$logEntry .= PHP_EOL . json_encode(
				self::prepareContext($context),
				JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
			);
		}

		$logEntry .= PHP_EOL . "URI: $uri | IP: $addr";
		$logEntry .= PHP_EOL . str_repeat('-', 80) . PHP_EOL;

		self::logFile($logEntry, 'autocrossell.log');
	}

	/**
	 * Подготовка контекста к сериализации
	 * - Обрабатывает объекты с __toString()
	 * - Экранирует чувствительные данные
	 * - Ограничивает глубину вложенности
	 *
	 * @param array $context Исходный контекст
	 * @param int $depth Текущая глубина рекурсии
	 * @return array Подготовленный контекст
	 */
	private static function prepareContext(array $context, int $depth = 0): array
	{
		$maxDepth = 3;
		$result = [];

		foreach ($context as $key => $value) {
			if (self::isSensitiveKey($key)) {
				$result[$key] = '***REDACTED***';
				continue;
			}

			if ($depth >= $maxDepth) {
				$result[$key] = is_array($value) ? '[...]' : (string)$value;
				continue;
			}

			if (is_object($value)) {
				if (method_exists($value, '__toString')) {
					$result[$key] = (string)$value;
				} elseif ($value instanceof DateTimeInterface) {
					$result[$key] = $value->format('c');
				} else {
					$result[$key] = sprintf(
						'Object(%s)',
						get_class($value)
					);
				}
			} elseif (is_array($value)) {
				$result[$key] = self::prepareContext($value, $depth + 1);
			} elseif (is_resource($value)) {
				$result[$key] = sprintf('Resource(%s)', get_resource_type($value));
			} else {
				$result[$key] = $value;
			}
		}

		return $result;
	}

	/**
	 * Проверка, является ли ключ чувствительным (пароль, токен и т.п.)
	 *
	 * @param string $key Имя ключа
	 * @return bool
	 */
	private static function isSensitiveKey(string $key): bool
	{
		$sensitivePatterns = [
			'password',
			'passwd',
			'pwd',
			'secret',
			'token',
			'api_key',
			'apikey',
			'auth',
			'credential',
			'private',
			'license'
		];

		$lowerKey = strtolower($key);
		foreach ($sensitivePatterns as $pattern) {
			if (str_contains($lowerKey, $pattern)) {
				return true;
			}
		}
		return false;
	}

	// ================================================================
	// ФАЙЛОВЫЕ ЗАПИСИ (garantirovanno quiet)
	// ================================================================

	/**
	 * Абсолютный путь к папке logs/ модуля или null если модуль не найден
	 *
	 * @return string|null
	 */
	private static function getLogDir(): ?string
	{
		$modulePath = \getLocalPath('modules/maximov.autocrossell');
		if ($modulePath === false) {
			return null;
		}

		return Application::getDocumentRoot() . $modulePath . '/logs/';
	}

	/**
	 * Проверка доступности папки logs/ для записи
	 *
	 * Если logs/ существует — проверяем её. Если нет — проверяем саму
	 * папку модуля (logFile может создать logs/ при первом обращении).
	 *
	 * @return bool
	 */
	private static function isLogWritable(): bool
	{
		$logDir = self::getLogDir();
		if ($logDir === null) {
			return false;
		}

		return is_dir($logDir)
			? is_writable($logDir)
			: is_writable(dirname($logDir));
	}

	/**
	 * Запись произвольных данных в файл лога внутри модуля.
	 *
	 * Все логи пишутся в папку <корень сайта>/<bitrix|local>/modules/maximov.autocrossell/logs/
	 * (папка и .htaccess создаются автоматически при первом обращении).
	 *
	 * Если папка logs/ недоступна для записи (read-only сервер) — метод
	 * молча завершается без Warning'ов и исключений. bx.log при этом
	 * продолжает работать через AddMessage2Log.
	 *
	 * @param mixed $data Строка, массив или объект (массивы/объекты — через print_r)
	 * @param string $filename Имя файла лога (по умолчанию render.log)
	 * @param int $mode File::APPEND (дописать) или File::REWRITE (перезаписать)
	 * @return void
	 */
	public static function logFile($data, string $filename = 'render.log', int $mode = File::APPEND): void
	{
		$filename = basename($filename);
		if ($filename === '' || $filename === '.' || $filename === '..') {
			return;
		}

		$logDir = self::getLogDir();
		if ($logDir === null) {
			return;
		}

		// 1. Проверка прав — тихий выход без Warning'ов
		if (!self::isLogWritable()) {
			return;
		}

		// 2. try/catch(Throwable) — гарантированно тихо
		try {
			self::ensureLogDir($logDir);

			$formatted = date('Y-m-d H:i:s') . ' - '
				. (is_array($data) || is_object($data)
					? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
					: $data)
				. "\n";

			File::putFileContents($logDir . $filename, $formatted, $mode);
		} catch (\Throwable $e) {
			// read-only сервер или dev-mode: лог не критичен, тихий выход
			return;
		}
	}

	/**
	 * Гарантирует существование директории логов и .htaccess для блокировки веб-доступа
	 *
	 * @param string $logDir Абсолютный путь к папке logs/
	 * @return void
	 */
	private static function ensureLogDir(string $logDir): void
	{
		try {
			if (Directory::isDirectoryExists($logDir)) {
				return;
			}

			Directory::createDirectory($logDir);

			$htaccess = $logDir . '.htaccess';
			if (!File::isFileExists($htaccess)) {
				File::putFileContents($htaccess, "Deny from all\nRequire all denied\n");
			}
		} catch (\Throwable $e) {
			// read-only сервер: логи не создаются, тихий выход
			return;
		}
	}

	/**
	 * Очистка кэша настройки debug (для тестов)
	 *
	 * @return void
	 * @internal
	 */
	public static function clearDebugCache(): void
	{
		self::$debugEnabledCache = null;
	}
}
