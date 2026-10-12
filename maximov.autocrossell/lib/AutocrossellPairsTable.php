<?php
/**
 * AutocrossellPairsTable D7 Entity
 *
 * @package maximov.autocrossell
 
 * @author Denis Maximov
 * @link https://marketplace.1c-bitrix.ru/solutions/maximov.autocrossell/
 * @license GPL-3.0-or-later
 */
namespace Maximov\Autocrossell;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\IntegerField;
use Bitrix\Main\Entity\DateField;

class AutocrossellPairsTable extends DataManager
{
	/**
	 * Get table name
	 *
	 * @return string
	 */
	public static function getTableName()
	{
		return "b_maximov_autocrossell_pairs";
	}

	/**
	 * Get map of fields
	 *
	 * @return array
	 */
	public static function getMap()
	{
		return [
			new IntegerField("ID", [
				"primary" => true,
				"autocomplete" => true,
			]),
			new IntegerField("PRODUCT_ID_1", [
				"required" => true,
			]),
			new IntegerField("PRODUCT_ID_2", [
				"required" => true,
			]),
			new IntegerField("WEIGHT", [
				"required" => true,
				"default_value" => 0,
			]),
			new DateField("UPDATED_AT", [
				"required" => true,
			]),
		];
	}
}
