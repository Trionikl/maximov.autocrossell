<?php

/**
 * @author Denis Maximov
 * @link https://marketplace.1c-bitrix.ru/solutions/maximov.autocrossell/
 * @license GPL-3.0-or-later
 */
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
	die();
}

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$arTemplateParameters = [
	'TITLE' => [
		'PARENT' => 'BASE',
		'NAME' => Loc::getMessage('AUTOCROSSELL_TITLE_PARAM'),
		'TYPE' => 'STRING',
		'DEFAULT' => Loc::getMessage('AUTOCROSSELL_TITLE_DEFAULT'),
	],
];
