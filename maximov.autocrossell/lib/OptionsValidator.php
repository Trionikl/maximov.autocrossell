<?php

/**
 * @author Denis Maximov
 * @link https://marketplace.1c-bitrix.ru/solutions/maximov.autocrossell/
 * @license GPL-3.0-or-later
 */

namespace Maximov\Autocrossell;

use Bitrix\Main\Config\Option;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
	die();
}

final class OptionsValidator
{
	private const MODULE_ID = 'maximov.autocrossell';

	private const DEFAULTS = [
		'min_threshold' => '2',
		'history_days' => '180',
		'agent_interval' => '24',
		'agent_window_start' => '02:00',
		'agent_window_end' => '06:00',
		'block_title' => '',
		'block_container_selector' => '',
		'debug' => 'N',
	];

	private const AGENT_OPTION_KEYS = [
		'agent_interval',
		'agent_window_start',
		'agent_window_end',
	];

	public static function sanitize(array $raw): array
	{
		$agentWindowStart = $raw['agent_window_start'] ?? null;
		$agentWindowEnd = $raw['agent_window_end'] ?? null;
		$blockTitle = self::stringValue($raw['block_title'] ?? '');
		$blockContainerSelector = self::stringValue($raw['block_container_selector'] ?? '');

		return [
			'min_threshold' => max(1, min(100, self::integerValue($raw['min_threshold'] ?? null, 2))),
			'history_days' => max(1, min(365, self::integerValue($raw['history_days'] ?? null, 180))),
			'agent_interval' => max(1, min(24, self::integerValue($raw['agent_interval'] ?? null, 24))),
			'agent_window_start' => self::isTime($agentWindowStart) ? $agentWindowStart : '02:00',
			'agent_window_end' => self::isTime($agentWindowEnd) ? $agentWindowEnd : '06:00',
			'block_title' => mb_substr(strip_tags(trim($blockTitle)), 0, 150),
			'block_container_selector' => mb_substr(strip_tags(trim($blockContainerSelector)), 0, 255),
			'debug' => (($raw['debug'] ?? 'N') === 'Y') ? 'Y' : 'N',
		];
	}

	public static function save(array $raw, bool $isLogWritable = true): array
	{
		$current = self::getCurrentSettings();
		$merged = array_replace($current, array_intersect_key($raw, self::DEFAULTS));

		if (!array_key_exists('debug', $raw)) {
			$merged['debug'] = 'N';
		}

		$cleanValues = self::sanitize($merged);
		if (!$isLogWritable) {
			$cleanValues['debug'] = 'N';
		}

		$changes = [];
		foreach ($cleanValues as $name => $value) {
			$newValue = (string)$value;
			$oldValue = (string)$current[$name];
			if ($oldValue === $newValue) {
				continue;
			}

			$changes[$name] = [
				'old' => $oldValue,
				'new' => $newValue,
			];
		}

		if ($changes === []) {
			return [];
		}

		foreach ($changes as $name => $change) {
			Option::set(self::MODULE_ID, $name, $change['new']);
		}

		Logger::clearDebugCache();
		Logger::log('Настройки модуля изменены', [
			'changes' => $changes,
		]);

		if (array_intersect(array_keys($changes), self::AGENT_OPTION_KEYS) !== []) {
			AgentScheduler::syncAgentOptions();
		}

		return $changes;
	}

	private static function getCurrentSettings(): array
	{
		$settings = [];
		foreach (self::DEFAULTS as $name => $default) {
			$settings[$name] = (string)Option::get(self::MODULE_ID, $name, $default);
		}

		return $settings;
	}

	private static function integerValue(mixed $value, int $default): int
	{
		if ($value === null) {
			return $default;
		}

		return is_scalar($value) ? (int)$value : 0;
	}

	private static function stringValue(mixed $value): string
	{
		return is_scalar($value) ? (string)$value : '';
	}

	private static function isTime(mixed $value): bool
	{
		return is_string($value)
			&& preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
	}
}
