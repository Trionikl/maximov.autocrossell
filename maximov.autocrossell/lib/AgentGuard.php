<?php

/**
 * AgentGuard
 *
 * Мьютекс через нативный D7-замок СУБД + проверка временного окна.
 *
 * - acquire/release — атомарная блокировка через Application::getConnection()->lock()
 *   (MySQL GET_LOCK / PostgreSQL advisory lock). При падении PHP соединение
 *   закрывается, и СУБД сама снимает замок.
 * - canRun() — проверка временного окна агента (по настройкам модуля).
 *
 * @package maximov.autocrossell
 
 * @author Denis Maximov
 * @link https://marketplace.1c-bitrix.ru/solutions/maximov.autocrossell/
 * @license GPL-3.0-or-later
 */

namespace Maximov\Autocrossell;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use DateTime;

class AgentGuard
{
	/** Имя замка (ядра хэшируют: md5(UniqID).md5(name) → MySQL ≤64, PG → crc32) */
	public const LOCK_NAME = 'maximov_autocrossell_recalc';

	private $windowStart;
	private $windowEnd;

	public function __construct()
	{
		$this->windowStart = Option::get('maximov.autocrossell', 'agent_window_start', '02:00');
		$this->windowEnd = Option::get('maximov.autocrossell', 'agent_window_end', '06:00');
	}

	/**
	 * Захват глобального замка СУБД.
	 *
	 * @return bool true — замок получен, false — уже занят другим процессом
	 */
	public function acquire(): bool
	{
		return Application::getConnection()->lock(self::LOCK_NAME, 0);
	}

	/**
	 * Снятие замка СУБД.
	 *
	 * Безопасно вызывать всегда: если замок чужой — СУБД вернёт 0 и проигнорирует.
	 */
	public function release(): void
	{
		Application::getConnection()->unlock(self::LOCK_NAME);
	}

	/**
	 * Проверка временного окна (только для агента по расписанию).
	 *
	 * @return bool
	 */
	public function canRun(): bool
	{
		return $this->isTimeInWindow();
	}

	/**
	 * Проверка, находится ли текущее время в допустимом окне.
	 */
	private function isTimeInWindow(): bool
	{
		$now = new DateTime();
		$currentMinutes = (int)$now->format('H') * 60 + (int)$now->format('i');

		$startParts = explode(':', $this->windowStart);
		$endParts = explode(':', $this->windowEnd);

		$startMinutes = (int)$startParts[0] * 60 + (int)($startParts[1] ?? 0);
		$endMinutes = (int)$endParts[0] * 60 + (int)($endParts[1] ?? 0);

		if ($startMinutes > $endMinutes) {
			return $currentMinutes >= $startMinutes || $currentMinutes < $endMinutes;
		}

		return $currentMinutes >= $startMinutes && $currentMinutes < $endMinutes;
	}
}
