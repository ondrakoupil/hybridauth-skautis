# Jak na HybridAuth

Rychlý návod, jak zprovoznit HybridAuth ve vaší aplikaci nebo pro přihlašování na web.

Pro hlubší pochopení doporučuji prostudovat User guide: http://hybridauth.sourceforge.net/userguide.html

## Instalace

HybridAuth stáhnete přes composer anebo z webu http://hybridauth.sourceforge.net/

```
composer require hybridauth/hybridauth
```

## Endpoint

Pro komunikaci s různými poskytovateli identit budete potřebovat tzv. endpoint, což je v podstatě
obyčejná adresa dostupná z internetu, v níž je uložený jednoduchý PHP skript, který zavolá
příslušnou metodu v HybridAuth, která se stará o obsloužení požadavku.

```php
// Pokud používáte Composer
include "../vendor/autoload.php";

// Nebo pokud jste knihovnu zkopírovali ručně
require_once( "path/to/Hybrid/Auth.php" );
require_once( "path/to/Hybrid/Endpoint.php" );

Hybrid_Endpoint::process();
```

HybridAuth si pro uchování různých stavových informací ukládá data do $_SESSION. Je tedy nutné
endpoint umístit tak, aby byla session pro něj i pro zbytek avšeho webu společná. Zpravidla to znamená,
že musí být hostován na stejné doméně.


## Nastavení

Dále si v nějakém konfiguračním souboru nadefinujete proměnnou obsahující veškeré potřebné nastavení.
Přesnou definici jednotlivých položek najdete na http://hybridauth.sourceforge.net/userguide/Configuration.html

```php
$hybridAuthConfig = array(

			// Sem přijde URL adresa endpointu
			"base_url" => "http://www.mujweb.cz/endpoint.php",

			// V tomto poli se definují jednotlivé služby, které chcete podporovat.
			"providers" => array(
			
				"Google" => array(
					"enabled" => true,
					"keys" => array("id" => "", "secret" => ""),
				),

				"Facebook" => array(
					"enabled" => true,
					"keys" => array("id" => "", "secret" => ""),
					"trustForwarded" => false
				),

				"SkautIS" => array(
					"enabled" => true,
					"keys" => array("appId" => ""),
					"wrapper" => array(
						"class" => "\\HybridAuth\\SkautIS\\Adapter"
					),					
				)
			),

			// Volitelně lze zapnout logování
			"debug_mode" => false,
			"debug_file" => ""
);
```

V každé službě, kterou chcete podporovat, je třeba zaregistrovat svoji aplikaci a dostat pro ni 
přidělené identifikační údaje. Postup se liší služba od služby, někde je to celkem jednoduché
(např. Twitter), někde je to složitější (např. Facebook).

Bližší informace k běžným přihlašovacím službám najdete na http://hybridauth.sourceforge.net/userguide.html, 
podrobný popis nastavení pro SkautIS je na [předchozí stránce][readme].



## Přihlášení

Skript, který provede přihlášení a získání dat o uživateli, pak může vypadat zhruba takto:

```php
// Vytvoříte si objekt třídy HybridAuth a předáte připravený config
$hybridauth = new Hybrid_Auth( $hybridAuthConfig );

// Pak zavoláte metodu authenticate s názvem služby, přes kterou
// chcete uživatele ověřit.
$adapter = $hybridauth->authenticate( "Twitter" );

// Po úspěšném přihlášení si z vráceného adaptéru můžete
// vytáhnout uživatelův profil.
$profile = $adapter->getUserProfile();

// $profile je objekt třídy Hybrid_User_Profile
// a obsahuje údaje o přihlášeném uživateli.
echo "Ahoj " . $profile->displayName . "!";
```php

Podrobnější popis postupu přihlášení 
najdete na http://hybridauth.sourceforge.net/userguide/Profile_Data_User_Profile.html


## Pod pokličkou

Ve skutečnosti je to o maličko složitější. 

`$hybridauth->authenticate()` se nejprve podívá do své interní cache, 
zda je již uživatel k požadované službě přihlášen. Pokud ano, skript normálně pokračuje dál a adaptér HybridAuthu
při volání `getUserProfile()` vytáhne požadovaná data a vrátí je. Pokud ale ještě přihlášen není, tak prohlížeč přesměruje 
na adresu Endpointu, kterou má v konfiguraci, uloží si do session informace o požadavku a tento skript ukončí. 

Uživatel pokračuje na Endpoint, který si ze session vytáhne parametry původního požadavku a přesměruje uživatele
na přihlašovací obrazovku na vybrané službě. Ta pak buď od uživatele vyžádá jméno a heslo, anebo nějaké povolení
ke sdílení osobních údajů s vaší aplikací, případně jen vše tiše potvrdí. Nakonec se uživatel vrací zpět na Endpoint
a pokud přihlášení proběhlo úspěšně, má již přidělený autentizační token, náhodně vygenerovaný řetězec opravňující vaši aplikaci
k čtení dat a k provádění dalších věcí.

Endpoint návštěvníka vrátí zpět na původní skript, kde se volala metoda authenticate(). Z toho důvodu je nutné, aby skript nedělal
nic, u čeho by vadilo, že se provede dvakrát. Jelikož v cache HybridAuth je již uložen token, tak skript pokračuje dál
a zavolá metodu getUserProfile(), která začne na pozadí komunikovat se službou. Ta díky tokenu ví, že uživatel
sdílení dat povolil, a tímpádem umožní přečíst vše potřebné. HybridAuth to pak jen zabalí do objektu Hybrid_User_Profile, 
který sjednocuje odlišnosti jednotlivých služeb a standardizuje je do snadno použitelné podoby.

Tímto způsobem je možné nejen načítat data o uživateli, ale i získávat seznam přátel, aktualizovat jeho status apod. Podporované
funkce se liší služba od služby, nicméně samotné přihlášení a získání uživatelova profilu podporují všechny (včetně SkautISu).


[readme][./readme.md]


