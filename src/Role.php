<?php

namespace HybridAuth\SkautIS;


/**
 * Třída reprezentující jednu roli uživatele
 */
class Role {

	/**
	 * Objekt se všemi údaji tak, jak ho vrací SkautIS
	 *
	 * @var \stdClass
	 */
	public $rawData;

	/**
	 * Název role
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Název role určený k zobrazení
	 *
	 * @var string
	 */
	public $displayName;

	/**
	 * Název jednotky, která s rolí souvisí
	 *
	 * @var string
	 */
	public $unit;

	/**
	 * ID jednotky, která s rolí souvisí
	 *
	 * @var int
	 */
	public $unitId;

	/**
	 * ID této role
	 *
	 * @var int
	 */
	public $id;

	/**
	 * Podrobné detaily jednotky, která s rolí souvisí, jako objekt.
	 *
	 * Pouze pokud je v configu nastaveno data[roleUnitDetails] = true
	 *
	 * @var null|Unit
	 */
	public $unitDetails;


	/**
	 * Klíč (identifikátor, který může upřesňovat význam role)
	 *
	 * @var string
	 */
	public $key;

	/**
	 * Je role aktivní?
	 *
	 * @var bool
	 */
	public $isActive;


	function __construct($rawData) {
		$this->rawData = $rawData;
		$this->populateData($rawData);
	}

	/**
	 * @ignore
	 * @param stdClass $rawData
	 */
	function populateData($rawData) {
		$fields = array(
			"ID" => "id",
			"Role" => "name",
			"ID_Group" => "groupId",
			"ID_Unit" => "unitId",
			"DisplayName" => "displayName",
			"Key" => "key",
			"IsActive" => "isActive",
			"Unit" => "unit"
		);

		foreach($fields as $skautISName=>$localName) {
			if (isset($rawData->$skautISName)) {
				$this->$localName = $rawData->$skautISName;
			} else {
				$this->$localName = null;
			}
		}
	}

}