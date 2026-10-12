<?php

/**
 * AutoCrossell Module Options Page
 *
 * @package maximov.autocrossell
 
 * @author Denis Maximov
 * @link https://marketplace.1c-bitrix.ru/solutions/maximov.autocrossell/
 * @license GPL-3.0-or-later
 */
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
	die();
}

use Bitrix\Main\Config\Option;
use Bitrix\Main\Context;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Maximov\Autocrossell\Logger;
use Maximov\Autocrossell\RecalculationAgent;

global $APPLICATION;

$moduleID = 'maximov.autocrossell';
Loc::loadMessages(__FILE__);
\Bitrix\Main\UI\Extension::load([
    'ui.buttons',
    'ui.buttons.icons', // Обязательно для поддержки иконок на кнопках
    'ui.dialogs.messagebox',
]);

$request = Context::getCurrent()->getRequest();

Loader::includeModule($moduleID);

// Проверка доступности папки logs/ для записи (read-only сервер → тихий отказ)
$logDir = __DIR__ . '/logs/';
$isLogWritable = is_dir($logDir) ? is_writable($logDir) : is_writable(__DIR__);

$POST_RIGHT = $APPLICATION->GetGroupRight($moduleID);
if ($POST_RIGHT < 'R') {
	return;
}

// Определяем действия по POST-параметрам
$hasSave = $request->isPost() && $request->getPost('Update') !== null && check_bitrix_sessid() && $POST_RIGHT >= 'W';
$hasRecalc = $request->isPost() && $request->getPost('recalculate_now') === 'Y' && check_bitrix_sessid() && $POST_RIGHT >= 'W';

$params = [];

// === Сохранение настроек (только при нажатии «Сохранить») ===
if ($hasSave) {
	\Maximov\Autocrossell\OptionsValidator::save(
		$request->getPostList()->toArray(),
		$isLogWritable
	);

	$params['saved'] = 'Y';
	$activeTab = (string)$request->getPost('tabControl_active_tab');
	$params['tabControl_active_tab'] = in_array($activeTab, ['edit1', 'edit2'], true) ? $activeTab : 'edit1';
}

// === Ручной пересчёт блока «С этим товаром покупают» (независимо от «Сохранить») ===
$recalcSuccess = false;
$recalcWarning = false;
$recalcPairsCount = 0;
$recalcError = '';
if ($hasRecalc) {
	Logger::log('Ручной пересчёт: получен POST-запрос', [
		'recalculate_now' => 'Y',
		'sessid' => check_bitrix_sessid() ? 'OK' : 'FAIL',
	]);
	try {
		$recalcResult = RecalculationAgent::execute(true);
		if (is_array($recalcResult) && ($recalcResult['status'] ?? '') === 'ok') {
			$recalcPairsCount = (int)($recalcResult['pairs_count'] ?? 0);
			// «Успех» без результата — это не успех: нулевой итог показываем предупреждением
			if ($recalcPairsCount > 0) {
				$recalcSuccess = true;
			} else {
				$recalcWarning = true;
			}
			Logger::log('Ручной пересчёт: завершён', [
				'pairs_count' => $recalcPairsCount,
				'pairs_written' => $recalcResult['pairs_written'] ?? 0,
				'duration_ms' => $recalcResult['duration_ms'] ?? null,
			]);
		} else {
			$recalcError = $recalcResult['reason'] ?? Loc::getMessage('AUTOCROSSELL_RECALC_ERROR_DETAIL');
			Logger::error('Ручной пересчёт: неуспех', [
				'status' => $recalcResult['status'] ?? 'unknown',
				'reason' => $recalcResult['reason'] ?? '',
			]);
		}
	} catch (Exception $e) {
		$recalcError = Loc::getMessage('AUTOCROSSELL_RECALC_ERROR_DETAIL');
		Logger::error('Ручной пересчёт: ошибка', [
			'error' => $e->getMessage(),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
		]);
	}

	if ($recalcSuccess) {
		$params['recalculated'] = 'Y';
		$params['recalc_pairs_count'] = $recalcPairsCount;
	} elseif ($recalcWarning) {
		$params['recalc_warning'] = 'Y';
		$params['recalc_pairs_count'] = $recalcPairsCount;
	}
	if (!empty($recalcError)) {
		$params['recalc_error'] = $recalcError;
	}
	// Кнопка на вкладке «Агент» — остаёмся на edit2
	$params['tabControl_active_tab'] = 'edit2';
}

// === Редирект (PRG паттерн) ===
if ($hasSave || $hasRecalc) {
	LocalRedirect($APPLICATION->GetCurPageParam(http_build_query($params), ['saved', 'recalculated', 'recalc_warning', 'recalc_pairs_count', 'recalc_error', 'tabControl_active_tab']));
}

// Загрузка текущих настроек
$minThreshold = (int)Option::get($moduleID, 'min_threshold', '2');
$historyDays = (int)Option::get($moduleID, 'history_days', '180');
$agentWindowStart = Option::get($moduleID, 'agent_window_start', '02:00');
$agentWindowEnd = Option::get($moduleID, 'agent_window_end', '06:00');
$agentInterval = (int)Option::get($moduleID, 'agent_interval', '24');
$debug = Option::get($moduleID, 'debug', 'N') === 'Y';
$blockTitle = Option::get($moduleID, 'block_title', '');
$blockContainerSelector = Option::get($moduleID, 'block_container_selector', '');

// На read-only сервере: гасим чекбокс и форсируем debug=N
if (!$isLogWritable) {
	$debug = false;
}

// Системные уведомления через CAdminMessage (перед $tabControl->Begin())
if ($request->get('saved') === 'Y') {
	CAdminMessage::ShowNote(Loc::getMessage('AUTOCROSSELL_SETTINGS_SAVED'));
}
if ($request->get('recalculated') === 'Y') {
	$pairsCount = (int)$request->get('recalc_pairs_count');
	if ($pairsCount > 0) {
		CAdminMessage::ShowNote(Loc::getMessage('AUTOCROSSELL_RECALC_SUCCESS', ['#COUNT#' => $pairsCount]));
	}
}
if ($request->get('recalc_warning') === 'Y') {
	CAdminMessage::ShowMessage([
		'TYPE' => 'ERROR',
		'MESSAGE' => Loc::getMessage('AUTOCROSSELL_RECALC_EMPTY'),
		'DETAILS' => Loc::getMessage('AUTOCROSSELL_RECALC_EMPTY_DETAIL'),
		'HTML' => true,
	]);
}
if ($request->get('recalc_error') !== null && !empty($request->get('recalc_error'))) {
	CAdminMessage::ShowMessage([
		'TYPE' => 'ERROR',
		'MESSAGE' => Loc::getMessage('AUTOCROSSELL_RECALC_ERROR'),
		'DETAILS' => htmlspecialcharsbx($request->get('recalc_error')),
		'HTML' => true,
	]);
}

// Вкладки
$tabControl = new CAdminTabControl('tabControl', [
	[
		'DIV' => 'edit1',
		'TAB' => Loc::getMessage('AUTOCROSSELL_TAB_MAIN'),
		'TITLE' => Loc::getMessage('AUTOCROSSELL_TAB_MAIN_TITLE'),
	],
	[
		'DIV' => 'edit2',
		'TAB' => Loc::getMessage('AUTOCROSSELL_TAB_AGENT'),
		'TITLE' => Loc::getMessage('AUTOCROSSELL_TAB_AGENT_TITLE'),
	],
]);
?>
<form method="POST" id="autocrossell_options"
	action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= htmlspecialcharsbx($moduleID) ?>&amp;lang=<?= LANGUAGE_ID ?>">
	<?= bitrix_sessid_post() ?>
	<?php $tabControl->Begin(); ?>
	<?php $tabControl->BeginNextTab(); ?>
	<!-- Вкладка: Основные настройки -->
	<tr class="heading">
		<td colspan="2"><b><?= Loc::getMessage('AUTOCROSSELL_TAB_MAIN') ?></b></td>
	</tr>
	<tr>
		<td width="40%" title="<?= htmlspecialcharsbx(Loc::getMessage('AUTOCROSSELL_MIN_THRESHOLD_TITLE')) ?>">
			<?= Loc::getMessage('AUTOCROSSELL_MIN_THRESHOLD') ?>:
		</td>
		<td width="60%">
			<input type="number" name="min_threshold" value="<?= $minThreshold ?>" min="1" max="100" />
		</td>
	</tr>
	<tr>
		<td title="<?= htmlspecialcharsbx(Loc::getMessage('AUTOCROSSELL_HISTORY_DAYS_TITLE')) ?>">
			<?= Loc::getMessage('AUTOCROSSELL_HISTORY_DAYS') ?>:
		</td>
		<td>
			<input type="number" name="history_days" value="<?= $historyDays ?>" min="1" max="365" />
		</td>
	</tr>
	<tr>
		<td title="<?= htmlspecialcharsbx(Loc::getMessage('AUTOCROSSELL_DEBUG_TITLE')) ?>">
			<?= Loc::getMessage('AUTOCROSSELL_DEBUG') ?>:
		</td>
		<td>
<?php if (!$isLogWritable): ?>
			<div class="adm-info-message-wrap" style="margin-bottom:10px;">
				<div class="adm-info-message"><?= Loc::getMessage('AUTOCROSSELL_DEBUG_LOG_NOT_WRITABLE') ?></div>
			</div>
<?php endif; ?>
			<input type="checkbox" name="debug" value="Y" <?= $debug ? 'checked' : '' ?> <?= $isLogWritable ? '' : 'disabled' ?> />
		</td>
	</tr>
	<tr>
		<td width="40%" title="<?= htmlspecialcharsbx(Loc::getMessage('AUTOCROSSELL_BLOCK_TITLE_TITLE')) ?>">
			<?= Loc::getMessage('AUTOCROSSELL_BLOCK_TITLE') ?>:
		</td>
		<td width="60%">
			<input type="text" name="block_title" value="<?= htmlspecialcharsbx($blockTitle) ?>" maxlength="150" style="width:100%" />
			<div class="adm-info-message" style="margin-top:4px;font-size:11px;"><?= Loc::getMessage('AUTOCROSSELL_BLOCK_TITLE_TITLE') ?></div>
		</td>
	</tr>
	<tr>
		<td title="<?= htmlspecialcharsbx(Loc::getMessage('AUTOCROSSELL_BLOCK_CONTAINER_SELECTOR_TITLE')) ?>">
			<?= Loc::getMessage('AUTOCROSSELL_BLOCK_CONTAINER_SELECTOR') ?>:
		</td>
		<td>
			<input type="text" name="block_container_selector" value="<?= htmlspecialcharsbx($blockContainerSelector) ?>" maxlength="255" style="width:100%" placeholder=".workarea > footer" />
			<div class="adm-info-message" style="margin-top:4px;font-size:11px;"><?= Loc::getMessage('AUTOCROSSELL_BLOCK_CONTAINER_SELECTOR_TITLE') ?></div>
		</td>
	</tr>
	<?php $tabControl->BeginNextTab(); ?>
	<!-- Вкладка: Агент -->
	<tr class="heading">
		<td colspan="2"><b><?= Loc::getMessage('AUTOCROSSELL_TAB_AGENT') ?></b></td>
	</tr>
	<tr>
		<td title="<?= htmlspecialcharsbx(Loc::getMessage('AUTOCROSSELL_AGENT_WINDOW_START_TITLE')) ?>">
			<?= Loc::getMessage('AUTOCROSSELL_AGENT_WINDOW_START') ?>:
		</td>
		<td>
			<input type="time" name="agent_window_start" value="<?= htmlspecialcharsbx($agentWindowStart) ?>" />
		</td>
	</tr>
	<tr>
		<td title="<?= htmlspecialcharsbx(Loc::getMessage('AUTOCROSSELL_AGENT_WINDOW_END_TITLE')) ?>">
			<?= Loc::getMessage('AUTOCROSSELL_AGENT_WINDOW_END') ?>:
		</td>
		<td>
			<input type="time" name="agent_window_end" value="<?= htmlspecialcharsbx($agentWindowEnd) ?>" />
		</td>
	</tr>
	<tr>
		<td title="<?= htmlspecialcharsbx(Loc::getMessage('AUTOCROSSELL_AGENT_INTERVAL_TITLE')) ?>">
			<?= Loc::getMessage('AUTOCROSSELL_AGENT_INTERVAL') ?>:
		</td>
		<td>
			<input type="number" name="agent_interval" value="<?= $agentInterval ?>" min="1" max="24" />
			<?= Loc::getMessage('AUTOCROSSELL_AGENT_INTERVAL_HOURS') ?>
		</td>
	</tr>
	<tr>
		<td colspan="2">
<button type="button" 
        id="btn-recalculate"
        onclick="runRecalculation()"
        class="ui-btn ui-btn-light-border">
    <?= Loc::getMessage('AUTOCROSSELL_RUN_NOW') ?>
</button>
		</td>
	</tr>
	<?php $tabControl->Buttons(); ?>
	<input type="submit" name="Update" value="<?= Loc::getMessage('MAIN_SAVE') ?>" class="adm-btn-save" />
	<?php $tabControl->End(); ?>
</form>

<script>
function runRecalculation() {
	BX.UI.Dialogs.MessageBox.confirm(
		'<?= CUtil::JSEscape(Loc::getMessage('AUTOCROSSELL_RECALC_CONFIRM')) ?>',
		'<?= CUtil::JSEscape(Loc::getMessage('AUTOCROSSELL_RECALC_CONFIRM_TITLE')) ?>',
		function(messageBox) {
			messageBox.close();
			var form = document.getElementById('autocrossell_options');
			if (form) {
				var recalcInput = document.createElement('input');
				recalcInput.type = 'hidden';
				recalcInput.name = 'recalculate_now';
				recalcInput.value = 'Y';
				form.appendChild(recalcInput);

				BX.showWait();
				form.submit();
			}
		},
		'<?= CUtil::JSEscape(Loc::getMessage('AUTOCROSSELL_RECALC_CONFIRM_YES')) ?>',
		function(messageBox) {
			messageBox.close();
		},
		'<?= CUtil::JSEscape(Loc::getMessage('AUTOCROSSELL_RECALC_CONFIRM_NO')) ?>'
	);
}
</script>
