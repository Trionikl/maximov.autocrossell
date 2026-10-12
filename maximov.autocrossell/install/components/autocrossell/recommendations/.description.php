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

$arComponentDescription = [
	'NAME' => Loc::getMessage('AUTOCROSSELL_COMPONENT_NAME'),
	'DESCRIPTION' => Loc::getMessage('AUTOCROSSELL_COMPONENT_DESC'),
	'COMPLEX' => 'N',
	'PATH' => [
		'ID' => 'autocrossell',
		'NAME' => Loc::getMessage('AUTOCROSSELL_SECTION_NAME'),
	],
];
