<?php

namespace HybridAuth\SkautIS;

/**
 * Adaptér sloužící pro získávání profilu uživatele ze SkautISu
 */
class Adapter extends \Hybrid_Provider_Model {

	/**
	 * Doména testovacího SkautISu
	 */
	const TEST_URL = "http://test-is.skaut.cz";

	/**
	 * Doména osrého SkautISu
	 */
	const PRODUCTION_URL = "https://is.skaut.cz";

	const TOKEN_TOKEN = "token";
	const TOKEN_ROLE = "role";
	const TOKEN_UNIT = "unit";

	const UM = "UserManagement";
	const OU = "OrganizationUnit";

	protected $appId;

	/**
	 * @var UserProfile
	 */
	public $user;

	protected $unitDataCache = array();

	function __construct($providerId, $config, $params = null) {
		parent::__construct($providerId, $config, $params);

		$this->user = new UserProfile();
	}

	function initialize() {

		if (!isset($this->config["keys"]["appId"]) or !$this->config["keys"]["appId"]) {
			throw new \Hybrid_Exception("Missing AppID in SkautIS config. Add something like this info SkautIS provider config: \"keys\" => array( \"appId\" => \"YOUR-APP-ID\")");
		}

		$this->appId = $this->config["keys"]["appId"];

		if (!isset($this->config["test"]) or $this->config["test"]) {
			$this->endpoint = self::TEST_URL;
		} else {
			$this->endpoint = self::PRODUCTION_URL;
		}

		if (!class_exists("\\SoapClient")) {
			throw new \Exception("Class SoapClient not found. The SOAP extenstion of PHP is required.");
		}
	}

	function loginBegin() {

		\Hybrid_Auth::redirect($this->getLoginPageUrl());

	}

	/**
	 * Vrací LoginID, tj. identifikátor konkrétního přihlášení.
	 * @return string
	 */
	function getLoginId() {
		return $this->token(self::TOKEN_TOKEN);
	}

	function loginFinish() {

		if (!isset($_REQUEST["skautIS_Token"])) {
			throw new \Hybrid_Exception("SkautIS did not return a [skautIS_Token] field in response.");
		}
		if (!isset($_REQUEST["skautIS_IDRole"])) {
			throw new \Hybrid_Exception("SkautIS did not return a [skautIS_IDRole] field in response.");
		}
		if (!isset($_REQUEST["skautIS_IDUnit"])) {
			throw new \Hybrid_Exception("SkautIS did not return a [skautIS_IDUnit] field in response.");
		}

		$this->token(self::TOKEN_TOKEN, $_REQUEST["skautIS_Token"]);
		$this->token(self::TOKEN_ROLE, $_REQUEST["skautIS_IDRole"]);
		$this->token(self::TOKEN_UNIT, $_REQUEST["skautIS_IDUnit"]);

		$this->setUserConnected();

	}

	function getApi($type = null) {
		if ($type == self::OU or $type == self::UM) {
			return new \SoapClient($this->getWsdl($type));
		} else {
			throw new \InvalidArgumentException("getApi() only accept as an argument one of constants Adapter::OU or Adapter::UM");
		}
	}

	/**
	 * Získá ze SkautISu všechny možné údaje (dle config[data]) a vrátí UserProfile objekt.
	 *
	 * @return UserProfile
	 */
	function getUserProfile() {

		$idLogin = $this->token(self::TOKEN_TOKEN);

		$this->clearUnitDetailsCache();

		$umClient = $this->getApi(self::UM);


		//  ******* First, we need UserID and PersonID ******

		$userDetailResult = $umClient->UserDetail(array(
			"userDetailInput" => array("ID_Login" => $idLogin)
		));

		\Hybrid_Logger::debug("Called SkautIS's UM->UserDetail", $userDetailResult);

		$idPerson = $userDetailResult->UserDetailResult->ID_Person;
		$idUser = $userDetailResult->UserDetailResult->ID;

		$personHasPhoto = false;

		$this->user->identifier = $idUser;
		$this->user->personId = $idPerson;

		// ******* Then we need to get Roles array *******

		if ($this->isDataRequired("roles", true)) {

			$userRoleAllResult = $umClient->UserRoleAll(array(
				"userRoleAllInput" => array(
					"ID_Login" => $idLogin,
					"ID_User" => $idUser,
					"IsActive" => true
				)
			));

			\Hybrid_Logger::debug("Called SkautIS's UM->UserRoleAll", $userRoleAllResult);

			$roles = $userRoleAllResult->UserRoleAllResult->UserRoleAllOutput;

			$this->user->roles = array();

			foreach($roles as $roleData) {
				$role = new Role($roleData);
				$this->user->roles[] = $role;
			}

		}


		// ******  Then all user (person) details  *****

		$ouClient = $this->getApi(self::OU);

		$personDetailResult = $ouClient->PersonDetail(array(
			"personDetailInput" => array(
				"ID_Login" => $idLogin,
				"ID" => $idPerson
			)
		));

		\Hybrid_Logger::debug("Called SkautIS's OU->PersonDetail", $personDetailResult);

		$personData = $personDetailResult->PersonDetailResult;

		$this->user->displayName = $personData->DisplayName;
		$this->user->firstName = $personData->FirstName;
		$this->user->lastName = $personData->LastName;
		$this->user->address = $personData->Street;
		$this->user->city = $personData->City;
		$this->user->zip = $personData->Postcode;
		$this->user->country = $personData->State;
		$this->user->email = $personData->Email;
		$this->user->birthYear = $personData->BirthdayYear;

		$personHasPhoto = (isset($personData->PhotoExtension) and isset($personData->PhotoSize) and  $personData->PhotoSize and $personData->PhotoExtension);

		if ($personData->Birthday) {
			$time = new \DateTime($personData->Birthday);
			if ($time) {
				$this->user->birthDay = $time->format("j");
				$this->user->birthMonth = $time->format("n");
			}
		}

		$this->user->gender = $personData->ID_Sex;
		$this->user->isMember = $personData->HasMembership ? true : false;


		// ********  Kontakty na osobu

		if ($this->isDataRequired("contacts", true)) {

			$contactsResult = $ouClient->PersonContactAll(array(
				"personContactAllInput" => array(
					"ID_Login" => $idLogin,
					"ID_Person" => $idPerson
				)
			));

			\Hybrid_Logger::debug("Called SkautIS's OU->PersonContactAll", $contactsResult);

			foreach($contactsResult->PersonContactAllResult->PersonContactAllOutput as $contact) {

				if (!$contact->Value or !$contact->ID_ContactType) continue;

				switch ($contact->ID_ContactType) {
					case "email_hlavni":
						$this->user->email = $contact->Value;
						break;

					case "telefon_hlavni":
						$this->user->phone = $contact->Value;
						break;

					case "web":
						$this->user->webSiteURL = $contact->Value;
						break;
				}

			}

		}



		// ******* PHOTO *******

		if ($this->isDataRequired("photo", true)) {

			if ($personHasPhoto) {

				$photoSize = "medium";
				if (isset($this->config["photoSize"])) {
					if (in_array($this->config["photoSize"], array("big", "normal", "medium", "small"))) {
						$photoSize = $this->config["photoSize"];
					}
				}

				$personPhoto = $ouClient->PersonPhoto(array(
					 "personPhotoInput" => array(
						"ID_Login" => $idLogin,
						"ID" => $idPerson,
						"Size" => $photoSize
					 )
				));

				\Hybrid_Logger::debug("Called SkautIS's OU->PersonPhoto", $personPhoto);

				$photoContent = $personPhoto->PersonPhotoResult->PhotoContent; // Already base64 decoded
				$photoType = null;
				if ($photoContent) {
					$photoType = $personPhoto->PersonPhotoResult->PhotoExtension;
					$this->user->photoData = $photoContent;
					$this->user->photoType = $photoType;
				}

				$photoUrl = $this->savePhotoToProxy($photoContent, $photoType);
				if ($photoUrl) {
					$this->user->photoURL = $photoUrl;
				}
			}

		}


		//  ******** JEDNOTKA ********

		if ($this->isDataRequired("unit", true)) {

			$membershipResult = $ouClient->MembershipAllPerson(array(
				"membershipAllPersonInput" => array(
					"ID_Login" => $idLogin,
					"ID_Person" => $idPerson,
					"ID_MembershipType" => "radne"
				)
			));

			\Hybrid_Logger::debug("Called SkautIS's OU->MembershipAllPerson", $membershipResult);

			$myUnit = $membershipResult->MembershipAllPersonResult->MembershipAllOutput;

			if ($myUnit and isset($myUnit->ID_Unit) and $myUnit->ID_Unit) {

				$unitId = $myUnit->ID_Unit;
				$unitObject = $this->fetchUnitDetails($ouClient, $unitId, $idLogin);
				$this->user->unit = $unitObject;

			}

		}


		// ****** ALL UNIT DETAILS FOR EACH ROLE

		if ($this->isDataRequired("roleUnitDetails", false)) {

			foreach($this->user->roles as $role) {

				$unitId = $role->unitId;
				if ($unitId) {
					$role->unitDetails = $this->fetchUnitDetails($ouClient, $unitId, $idLogin);
				} else {
					$role->unitDetails = null;
				}

			}

		}


		return $this->user;

	}


	/**
	 * Vrací adresu WSDL dokumentu
	 *
	 * @ignore
	 * @param string $unit Název služby (self::OU, self::UM atd.)
	 * @return string
	 */
	function getWsdl($unit) {
		return $this->endpoint."/JunakWebservice/$unit.asmx?WSDL";
	}

	/**
	 * Vrací adresu pro přesměrování na přihlašovací stránku
	 *
	 * @return string
	 */
	function getLoginPageUrl() {
		return $this->endpoint . "/Login/?appid=" . $this->appId;
	}


	/**
	 * @ignore
	 * @param binary $photoData
	 * @param string $photoType
	 * @return string URL nebo cesta k souboru
	 * @throws \Hybrid_Exception
	 */
	function savePhotoToProxy($photoData, $photoType) {

		if (!$photoData) return null;

		if ($this->config["photoProxy"]) {

			$photoType = strtolower($photoType);
			if (!$photoType) $photoType = "jpg";

			if (is_string($this->config["photoProxy"])) {
				$filepath = tempnam($this->config["photoProxy"], "ha_skautis");
				unlink($filepath);
				$filepath .= "." . $photoType;
				$filename = basename($filepath);
				file_put_contents($filepath, $photoData);
				return $filepath;
			} elseif (isset($this->config["photoProxy"]["dir"]) and $this->config["photoProxy"]["url"]) {

				$filepath = tempnam($this->config["photoProxy"]["dir"], "ha_skautis");
				unlink($filepath);
				$filepath .= "." . $photoType;
				$filename = basename($filepath);
				file_put_contents($filepath, $photoData);
				return $this->config["photoProxy"]["url"] . "/" . $filename;

			} else {
				throw new \Hybrid_Exception("Photo Proxy is misconfigured.");
			}

		}

		return null;

	}

	/**
	 * @ignore
	 * @param string $code
	 * @param bool $default
	 * @return bool
	 */
	function isDataRequired($code, $default) {
		if (isset($this->config["data"][$code])) {
			return $this->config["data"][$code];
		}
		return $default;
	}


	/**
	 * @ignore
	 */
	protected function clearUnitDetailsCache() {
		$this->unitDataCache = array();
	}

	/**
	 * Call OU->UnitDetail
	 *
	 * @ignore
	 * @param \SoapClient $ouClient Nastavený SoapClient
	 * @param int $unitId
	 * @param string $idLogin
	 * @return \HybridAuth\SkautIS\Unit
	 */
	protected function fetchUnitDetails(\SoapClient $ouClient, $unitId, $idLogin) {

		if (isset($this->unitDataCache[$unitId])) {
			if (!$this->unitDataCache[$unitId]) {
				return null;
			}
			return $this->unitDataCache[$unitId];
		}

		try {
			$unitDetailResult = $ouClient->UnitDetail(array(
				"unitDetailInput" => array(
					"ID_Login" => $idLogin,
					"ID" => $unitId
				)
			));

			\Hybrid_Logger::debug("Called SkautIS's OU->UnitDetail", $unitDetailResult);

		} catch (\Exception $e) {

			$this->unitDataCache[$unitId] = false;
			return null;

		}


		if ($unitDetailResult) {

			$unitObject = new Unit(
				$unitDetailResult->UnitDetailResult
			);

			$this->unitDataCache[$unitId] = $unitObject;
			return $unitObject;

		};

		$this->unitDataCache[$unitId] = false;
		return null;

	}


}
