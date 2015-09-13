<?php

if (!file_exists("../vendor")) {
	die("Run 'composer install' first.");
}

include "../vendor/autoload.php";

$config = array(
	// Sem přijde adresa endpointu pro HybridAuth.
	// Stejná adresa by měla být nastavena ve SkautISu jako
	// adresa pro zaslání výsledku přihlášení.
	// Mělo by jít o absolutní URL adresu, protože na ni bude přesměrován
	// prohlížeč po úspěšném přihlášení ze SkautISu.
	"base_url" => "./endpoint.php", 

	"providers" => array (
		"SkautIS" => array(
			"enabled" => true,
			"test" => true,
			"wrapper" => array(
				"class" => "\\HybridAuth\\SkautIS\\Adapter"
			),
			"keys" => array(
				// Sem přijde tvoje AppID
				"appId" => "Your App ID"
			),
			"photoProxy" => ".",
			"photoSize" => "medium",
			"data" => array(
				"roleUnitDetails" => true,
				"contacts" => true,
				"photo" => true,
				"roles" => true,
				"unit" => true
			)
		)
	)
);

$ha = new Hybrid_Auth($config);

try {
	$adapter = $ha->authenticate("SkautIS");
	$profile = $adapter->getUserProfile();

	echo "Authentication succees: ";
	echo "<pre>";
	print_r($profile);
	echo "</pre>";

} catch (Exception $e) {
	echo "The authentication process failed: " . $e->getMessage();

}
