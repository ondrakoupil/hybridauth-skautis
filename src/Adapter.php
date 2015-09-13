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

	function loginFinish() {

		if (!isset($_POST["skautIS_Token"])) {
			throw new \Hybrid_Exception("SkautIS did not return a [skautIS_Token] field in response.");
		}
		if (!isset($_POST["skautIS_IDRole"])) {
			throw new \Hybrid_Exception("SkautIS did not return a [skautIS_IDRole] field in response.");
		}
		if (!isset($_POST["skautIS_IDUnit"])) {
			throw new \Hybrid_Exception("SkautIS did not return a [skautIS_IDUnit] field in response.");
		}

		$this->token(self::TOKEN_TOKEN, $_POST["skautIS_Token"]);
		$this->token(self::TOKEN_ROLE, $_POST["skautIS_IDRole"]);
		$this->token(self::TOKEN_UNIT, $_POST["skautIS_IDUnit"]);

		$this->setUserConnected();

	}

	/**
	 * Získá ze SkautISu všechny možné údaje (dle config[data]) a vrátí UserProfile objekt.
	 *
	 * @return UserProfile
	 */
	function getUserProfile() {

		$idLogin = $this->token(self::TOKEN_TOKEN);

		$this->clearUnitDetailsCache();

		$umClient = new \SoapClient($this->getWsdl(self::UM));


		//  ******* First, we need UserID and PersonID ******

		$userDetailResult = $umClient->UserDetail(array(
			"userDetailInput" => array("ID_Login" => $idLogin)
		));

		/*

    [UserDetailResult] => stdClass Object
        (
            [ID] => 2167
            [IsActive] => 1
            [IsEnabled] => 1
            [IsDebug] =>
            [UserName] => ondrakoupil
            [DatePasswordChange] => 2013-08-17T15:14:38.003
            [IncorrectPasswordCount] => 0
            [PasswordRequest] =>
            [PasswordRequestTimeout] =>
            [ID_Person] => 142665
            [Person] => OndĹ™ej Koupil
            [SecurityCode] => E625ANC9
            [HasMembership] => 1
            [ID_UserAuthentication] => 22
        )

		 */

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




			/*
			stdClass Object
			(
				[UserRoleAllResult] => stdClass Object
					(
						[UserRoleAllOutput] => Array
							(
								[0] => stdClass Object
									(
										[ID] => 3202
										[ID_User] => 2167
										[ID_Role] => 70
										[Role] => AdministrĂˇtor: hodnocenĂ­ kvality
										[ID_Group] => 760932
										[ID_Unit] => 23200
										[Unit] => ĂšstĹ™edĂ­
										[RegistrationNumber] => 1
										[DisplayName] => AdministrĂˇtor: hodnocenĂ­ kvality - 1
										[Key] => adminKvalita
										[IsActive] => 1
										[CanEdit] => 1
									)

								[1] => stdClass Object
									(
										[ID] => 3203
										[ID_User] => 2167
										[ID_Role] => 30
										[Role] => Kraj: aktivnĂ­ ÄŤinovnĂ­k
										[ID_Group] => 763516
										[ID_Unit] => 25784
										[Unit] => KarlovarskĂ˝ kraj
										[RegistrationNumber] => 410
										[DisplayName] => Kraj: aktivnĂ­ ÄŤinovnĂ­k - 410
										[Key] => cinovnikKraj
										[IsActive] => 1
										[CanEdit] => 1
									)

							)

					)

			)
			 *
			 */

			$roles = $userRoleAllResult->UserRoleAllResult->UserRoleAllOutput;

			$this->user->roles = array();

			foreach($roles as $roleData) {
				$role = new Role($roleData);
				$this->user->roles[] = $role;
			}

		}


		// ******  Then all user (person) details  *****

		$ouClient = new \SoapClient($this->getWsdl(self::OU));

		$personDetailResult = $ouClient->PersonDetail(array(
			"personDetailInput" => array(
				"ID_Login" => $idLogin,
				"ID" => $idPerson
			)
		));

		/*

		stdClass Object
		(
			[PersonDetailResult] => stdClass Object
				(
					[ID_Login] => 00000000-0000-0000-0000-000000000000
					[ID] => 142665
					[DisplayName] => Koupil OndĹ™ej
					[DegreeInFrontOf] =>
					[DegreeBehind] =>
					[SecurityCode] => E625ANC9
					[IdentificationCode] => 870513/3668
					[IdentificationCodeHasPermission] => 1
					[FirstName] => OndĹ™ej
					[LastName] => Koupil
					[NickName] =>
					[Street] => LabskĂˇ kotlina 1014/68
					[City] => Hradec KrĂˇlovĂ© 2
					[Postcode] => 50002
					[State] => ÄŚeskĂˇ republika
					[PostalFirstLine] =>
					[PostalStreet] =>
					[PostalCity] =>
					[PostalPostcode] =>
					[PostalState] =>
					[Note] =>
					[ID_Sex] => male
					[Sex] => MuĹľ
					[Birthday] => 1987-05-13T00:00:00
					[BirthdayYear] => 1987
					[IsForeign] =>
					[YearFrom] =>
					[RegistrationNumber] =>
					[ID_User] => 2167
					[GenerateSecurityCode] =>
					[OnlyValidate] =>
					[PhotoExtension] => jpg
					[ID_PersonType] => junak
					[PersonType] => ÄŚlen JunĂˇka
					[Email] => koupil@optimato.cz
					[DisplayAdress] =>
					[DisplayBirthday] =>
					[DisplayEducation] =>
					[DisplayEducationSeminary] =>
					[DisplayFunction] =>
					[DisplayMembership] =>
					[DisplayOffer] =>
					[DisplayPostalAdress] =>
					[DisplaySchool] =>
					[DisplayQualification] =>
					[DisplayYearFrom] =>
					[CatalogDisplay] =>
					[CatalogContactCount] => 0
					[ID_PersonContactGa] =>
					[ID_MemberCard] =>
					[PhotoX] => 0
					[PhotoY] => 238
					[PhotoSize] => 1458
					[IsPostalAuthenticated] =>
					[IsAddressAuthenticated] => 1
					[AddressDistrict] => Hradec KrĂˇlovĂ©
					[RejectDataStorage] =>
					[HasMembership] => 1
				)

		)

		*/

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

			/*

			stdClass Object
			(
				[PersonContactAllResult] => stdClass Object
					(
						[PersonContactAllOutput] => Array
							(
								[0] => stdClass Object
									(
										[ID] => 207611
										[ID_Person] => 142665
										[Person] => Koupil OndĹ™ej
										[PersonCivilName] => Koupil OndĹ™ej
										[ID_ContactType] => email_hlavni
										[ContactType] => E-mail (hlavnĂ­)
										[IsMain] => 1
										[Value] => koupil@optimato.cz
										[Note] =>
										[IsSts] =>
										[IsGa] =>
										[IsCatalog] =>
										[IsUsed] =>
										[DisplayValue] => <a href="mailto:koupil@optimato.cz">koupil@optimato.cz</a>
										[Icon] => email.png
										[Help] => Zadejte e-mail v platnĂ©m formĂˇtu: jmeno@domena.xy
									)
			*/

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

				/*
				 <PersonPhotoResult>
					<ID>142665</ID>
					<PhotoContent>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</PhotoContent>
					<PhotoExtension>jpg</PhotoExtension>
				 </PersonPhotoResult>
				 *
				 */

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
