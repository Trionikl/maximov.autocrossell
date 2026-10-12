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
echo CAdminMessage::ShowNote(GetMessage('AUTOCROSSELL_INSTALL_SUCCESS'));
