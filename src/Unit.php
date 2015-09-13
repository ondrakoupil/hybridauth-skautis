<?php

namespace HybridAuth\SkautIS;

/**
 * Představuje jednu jednotku (oddíl, středisko...).
 */
class Unit {

	/**
	 * Objekt se všemi údaji tak, jak ho vrací SkautIS
	 *
	 * @var \stdClass
	 */
	public $rawData;

	/**
	 * Název jednotky
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Plný název jednotky
	 *
	 * @var string
	 */
	public $fullDisplayName;

	/**
	 * Celé registrační číslo
	 *
	 * @var string
	 */
	public $registrationNumber;

	/**
	 * Zkrácené registrační číslo
	 *
	 * @var string
	 */
	public $shortRegistrationNumber;

	/**
	 * Lokalita
	 *
	 * @var string
	 */
	public $location;

	/**
	 * IČO
	 *
	 * @var string
	 */
	public $ic;

	/**
	 * Typ jednotky jako běžný řetězec
	 *
	 * @var string
	 */
	public $type;

	/**
	 * Typ jednotky jako speciální identifikátor
	 *
	 * @var string
	 */
	public $typeCode;

	/**
	 * Ulice
	 *
	 * @var string
	 */
	public $street;

	/**
	 * Město
	 *
	 * @var string
	 */
	public $city;

	/**
	 * PSČ
	 *
	 * @var string
	 */
	public $zip;

	/**
	 * Země
	 *
	 * @var string
	 */
	public $state;

	/**
	 * Poznámka
	 *
	 * @var string
	 */
	public $note;


	function __construct($rawData) {
		$this->rawData = $rawData;
		$this->populateData($rawData);
	}

	/**
	 * @ignore
	 * @param \stdClass $rawData
	 */
	function populateData($rawData) {
		$fields = array(
			"DisplayName" => "name",
			"FullDisplayName" => "fullDisplayName",
			"RegistrationNumber" => "registrationNumber",
			"ShortRegistrationNumber" => "shortRegistrationNumber",
			"Location" => "location",
			"IC" => "ic",
			"UnitType" => "type",
			"ID_UnitType" => "typeCode",
			"Street" => "street",
			"City" => "city",
			"Postcode" => "zip",
			"State" => "state",
			"Note" => "note"
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
