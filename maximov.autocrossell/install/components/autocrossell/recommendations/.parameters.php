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

$arComponentParameters = [
	'PARAMETERS' => [
		'PRODUCT_ID' => [
			'PARENT' => 'BASE',
			'NAME' => Loc::getMessage('AUTOCROSSELL_PRODUCT_ID'),
			'TYPE' => 'STRING',
			'DEFAULT' => '',
		],
		'LIMIT' => [
			'PARENT' => 'BASE',
			'NAME' => Loc::getMessage('AUTOCROSSELL_LIMIT'),
			'TYPE' => 'STRING',
			'DEFAULT' => '4',
		],
		'IBLOCK_ID' => [
			'PARENT' => 'BASE',
			'NAME' => Loc::getMessage('AUTOCROSSELL_IBLOCK_ID'),
			'TYPE' => 'STRING',
			'DEFAULT' => '',
		],
		'CACHE_TIME' => [
			'PARENT' => 'CACHE_SETTINGS',
			'NAME' => GetMessage('COMP_PROP_CACHE_TIME'),
			'TYPE' => 'STRING',
			'DEFAULT' => '86400',
		],
	],
];
