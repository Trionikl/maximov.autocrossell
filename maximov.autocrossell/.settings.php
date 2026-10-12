<?php
/**
 * AutoCrossell Module Settings (D7)
 *
 * @package maximov.autocrossell
 * @source documentation ed333a42c05faf56 (Создание контроллера, score 0.85)
 
 * @author Denis Maximov
 * @link https://marketplace.1c-bitrix.ru/solutions/maximov.autocrossell/
 * @license GPL-3.0-or-later
 */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
	die();
}

return [
	'controllers' => [
		'value' => [
			'defaultNamespace' => '\\Maximov\\Autocrossell\\Controller',
		],
		'readonly' => true,
	],
];
