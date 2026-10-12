<?php
/**
 * run_recalc.php
 *
 * Запуск пересчёта блока «С этим товаром покупают» вручную из CLI.
 * Использование: php run_recalc.php
 *
 * Доступен ТОЛЬКО из командной строки. Прямой HTTP-доступ запрещён (CWE-425).
 *
 * @package maximov.autocrossell
 
 * @author Denis Maximov
 * @link https://marketplace.1c-bitrix.ru/solutions/maximov.autocrossell/
 * @license GPL-3.0-or-later
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	die('Forbidden');
}

$docRoot = __DIR__;
while (!file_exists($docRoot . '/bitrix/modules/main/include/prolog_before.php')) {
	$parent = dirname($docRoot);
	if ($parent === $docRoot) {
		break;
	}
	$docRoot = $parent;
}
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!\Bitrix\Main\Loader::includeModule('maximov.autocrossell')) {
	fwrite(STDERR, "Ошибка: не удалось загрузить модуль maximov.autocrossell\n");
	exit(1);
}

$result = \Maximov\Autocrossell\RecalculationAgent::execute(true);

if (is_array($result)) {
	if ($result['status'] === 'busy') {
		fwrite(STDERR, "Расчёт уже выполняется другим процессом. Попробуйте позже.\n");
		exit(2);
	}
	if ($result['status'] === 'error') {
		fwrite(STDERR, "Ошибка: " . ($result['reason'] ?? 'неизвестная') . "\n");
		exit(1);
	}
	if ($result['status'] === 'empty') {
		echo "Нет данных для расчёта: " . ($result['reason'] ?? '') . "\n";
		exit(0);
	}
}

// Check result by looking at the table
$conn = \Bitrix\Main\Application::getConnection();
if ($conn->isTableExists('b_maximov_autocrossell_pairs')) {
	$countResult = $conn->query("SELECT COUNT(*) as cnt FROM b_maximov_autocrossell_pairs")->fetch();
	$cnt = (int)($countResult['cnt'] ?? 0);
	if ($cnt > 0) {
		echo "Пересчёт завершён успешно. Пар в таблице: {$cnt}\n";
		exit(0);
	} else {
		fwrite(STDERR, "Ошибка: пересчёт завершён, но таблица пуста. Проверьте bx.log и logs/autocrossell.log внутри модуля\n");
		exit(1);
	}
} else {
	fwrite(STDERR, "Ошибка: таблица b_maximov_autocrossell_pairs не существует\n");
	exit(1);
}
