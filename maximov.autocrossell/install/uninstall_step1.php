<?php

/**
 * @author Denis Maximov
 * @link https://marketplace.1c-bitrix.ru/solutions/maximov.autocrossell/
 * @license GPL-3.0-or-later
 */
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
	die();
}

if (!check_bitrix_sessid()) {
	return;
}

\Bitrix\Main\Localization\Loc::loadMessages(dirname(__FILE__) . '/index.php');
?>
<form action="<?= $APPLICATION->GetCurPage() ?>">
	<?= bitrix_sessid_post() ?>
	<input type="hidden" name="lang" value="<?= LANG ?>">
	<input type="hidden" name="id" value="maximov.autocrossell">
	<input type="hidden" name="uninstall" value="Y">
	<input type="hidden" name="step" value="2">

	<p><?= GetMessage('AUTOCROSSELL_UNINSTALL_STEP1_TEXT') ?></p>
	<p>
		<input type="checkbox" name="savedata" id="savedata" value="Y" checked>
		<label for="savedata"><?= GetMessage('AUTOCROSSELL_UNINSTALL_SAVEDATA_LABEL') ?></label>
	</p>

	<input type="submit" name="inst" value="<?= GetMessage('AUTOCROSSELL_UNINSTALL_BUTTON') ?>">
</form>
