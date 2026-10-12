<?php

/**
 * AgentScheduler
 *
 * Синхронизация настроек фонового агента (b_option) с записью агента в b_agent
 * (NEXT_EXEC, AGENT_INTERVAL) и расчёт ближайшего старта окна пересчёта.
 *
 * Используется:
 *  — install/index.php (первичная регистрация агента);
 *  — options.php (применение изменённых настроек окна/интервала без
 *    переустановки модуля).
 *
 * @package maximov.autocrossell
 
 * @author Denis Maximov
 * @link https://marketplace.1c-bitrix.ru/solutions/maximov.autocrossell/
 * @license GPL-3.0-or-later
 */

namespace Maximov\Autocrossell;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

class AgentScheduler
{
	/**
	 * Имя агента (сигнатура вызова из b_agent)
	 */
	private const AGENT_NAME = '\\Maximov\\Autocrossell\\RecalculationAgent::execute();';

	/**
	 * Интервал по умолчанию (часы)
	 */
	private const DEFAULT_INTERVAL_HOURS = 24;

	/**
	 * Время старта окна по умолчанию
	 */
	private const DEFAULT_WINDOW_START = '02:00';

	/**
	 * Интервал выполнения агента в секундах из настроек модуля.
	 *
	 * Опция agent_interval хранится в часах (1..24).
	 *
	 * @return int Интервал в секундах
	 */
	public static function getAgentInterval(): int
	{
		$hours = (int)Option::get('maximov.autocrossell', 'agent_interval', (string)self::DEFAULT_INTERVAL_HOURS);
		$hours = max(1, min(24, $hours));
		return $hours * 3600;
	}

	/**
	 * Ближайшее время старта окна пересчёта (agent_window_start).
	 *
	 * Если старт окна сегодня уже прошёл — возвращается время старта
	 * следующего дня.
	 *
	 * @param string|null $windowStart Время старта окна (HH:MM). Если не задано —
	 *                                 берётся из настроек модуля.
	 * @return string Дата в формате DD.MM.YYYY HH:MM:SS (FULL)
	 */
	public static function getNextWindowStart(?string $windowStart = null): string
	{
		$windowStart = $windowStart ?? Option::get('maximov.autocrossell', 'agent_window_start', self::DEFAULT_WINDOW_START);
		$parts = array_pad(explode(':', $windowStart), 2, '00');
		$hour = (int)$parts[0];
		$minute = (int)$parts[1];

		$ts = mktime($hour, $minute, 0);
		if ($ts <= time()) {
			$ts += 86400;
		}

		return date('d.m.Y H:i:s', $ts);
	}

	/**
	 * Синхронизация настроек агента из b_option с записью в b_agent.
	 *
	 * Обновляет AGENT_INTERVAL (по опции agent_interval) и NEXT_EXEC
	 * (по опции agent_window_start) для всех активных записей агента.
	 * Ничего не делает, если агент не найден.
	 *
	 * @return bool True, если запись агента найдена и обновлена
	 */
	public static function syncAgentOptions(): bool
	{
		if (!Loader::includeModule('maximov.autocrossell')) {
			return false;
		}

		$res = \CAgent::GetList(
			[],
			['NAME' => self::AGENT_NAME, 'MODULE_ID' => 'maximov.autocrossell']
		);

		$updated = false;
		while ($ar = $res->Fetch()) {
			\CAgent::Update($ar['ID'], [
				'IS_PERIOD' => 'Y',
				'AGENT_INTERVAL' => self::getAgentInterval(),
				'NEXT_EXEC' => self::getNextWindowStart(),
			]);
			$updated = true;
		}

		return $updated;
	}

	/**
	 * Получить текущую запись агента из b_agent.
	 *
	 * @return array|null Массив полей агента или null, если агент не найден
	 */
	public static function getAgentRecord(): ?array
	{
		if (!Loader::includeModule('maximov.autocrossell')) {
			return null;
		}

		$res = \CAgent::GetList(
			['ID' => 'DESC'],
			['NAME' => self::AGENT_NAME, 'MODULE_ID' => 'maximov.autocrossell']
		);

		$agent = $res->Fetch();
		return $agent ?: null;
	}
}
