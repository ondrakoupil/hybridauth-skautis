# Jednoduché přihlašování přes SkautIS pomocí HybridAuth

HybridAuth je PHP knihovna pro univerzální přihlašování přes různé veřejné služby
a sociální sítě, např. Facebook či Google. S pomocí tohoto rozšíření lze do vaší aplikace
přidat i přihlašování přes SkautIS.

## Instalace

Pokud ve své aplikaci používáte Composer, nainstalujete jednoduše:

`composer require ondrakoupil/hybridauth-skautis`

Pokud nepoužíváte Composer, je třeba si zkopírovat soubory z adresáře `src` a libovolným 
způsobem je všechny includovat. Samotný HybridAuth lze stáhnout z [jeho webu][ha].

## Použití

Přihlášení přes SkautIS probíhá až na několik odlišností stejně jako přes Google či Facebook.
Pokud s HybridAuth nemáte zkušenosti, prostudujte si [jejich dokumentaci][ha-ug], kde je
vše dopodrobna popsané.

Anebo si přečtěte [stručný návod pro začátky s HybridAuthem][article-ha], který vás namíří správným směrem.

Také budete potřebovat svoji aplikaci zaregistrovat do SkautISu a získat její AppID.

## Nastavení

V konfiguraci HybridAuth přidáte nový provider jménem SkautIS. Konfigurace může vypadat zhruba takto:

```php
$config = array(
	"base_url" => "... URL vašeho endpointu ...",
	"providers" => array (
		"SkautIS" => array(
			"enabled" => true,
			"test" => true,
			"wrapper" => array(
				"class" => "\\HybridAuth\\SkautIS\\Adapter"
			),
			"keys" => array(
				"appId" => "... vaše APP ID ..."
			),
			"data" => array(
				"contacts" => true,
				"photo" => true,
				"roles" => true,
				"unit" => true,
				"roleUnitDetails" => false
			),			
			"photoProxy" => "",
			"photoSize" => "medium"
		),
		"Google" => "...",
		"Facebook" => "...",
		"Twitter" => "..."
	)
);
```

Nyní blíže k jednotlivým položkám:

- **test** = true nebo false podle toho, zda chcete používat testovací SkautIS anebo ostrý.
Výchozí hodnota je true (tj. testovací). Po přechodu na ostrý nezapomeňte změnit i AppID.
- **wrapper** = zde je nutno zadat jméno třídy včetně namespaců (tj. `\HybridAuth\SkautIS\Adapter`),
pokud si soubor s třídou `Adapter` nenakopírujete mezi ostatní adaptéry do adresáře se zdrojáky
HybridAuthu.
- **keys** = array s jedinou položkou `appId`. Nezapomeňte, že se liší pro testovací a ostrý SkautIS.
- **data** = nepovinné array umožňující upřesnit, co všecno ze SkautISu potřebujete vytáhnout a případně tak
zrychlit přihlášení tím, že zakážete nepotřebné údaje. Ve výchozím nastavení zapnuté vše kromě `roleUnitDetails`
  - `contacts` = další kontakty jako telefon či web (kromě e-mailu, ten je vždy součást základních dat)
  - `photo` = profilová fotka uživatele
  - `roles` = seznam všech rolí uživatele
  - `unit` = podrobnosti o jednotce (oddílu, středisku...), ve kterém je uživatel registrován
  - `roleUnitDetails` = podrobnosti o všech jednotkách, ve kterých má uživatel nějakou roli
- **photoProxy** = nepovinné, viz níže
- **photoSize** = nepovinná požadovaná velikost fotky: small, medium, normal, big

## Rozdíly oproti standardnímu přihlášení přes HybridAuth 

Jelikož SkautIS je dost specifický a pravděpodobně z něj budete chtít i nějaká jiná data, než HybridAuth vydoluje 
z klasických sociálních sítí, tak volání `$adapter->getUserProfile()` nevrací standardní `Hybrid_User_Profile`, ale jeho 
odděděnou rozšířenou verzi, objekt třídy `\HybridAuth\SkautIS\UserProfile`. Ten obsahuje jak to, co základní profil,
tak i nějaké další údaje navíc specifické pro SkautIS týkající se zejména rolí a jednotek v Junáku. Je na vás,
jak si s nimi pak ve své aplikaci poradíte.

Prohlédněte si třídy [UserProfile][profile-doc], [Role][role-doc] a [Unit][unit-doc], z nichž jistě vše
pochopíte podrobněji.

## Fotky

HybridAuth předpokládá, že poskytovatel přihlášení nabízí ke stažení uživatelův portrét jako na nějaký URL,
který lze získat přes `UserProfile -> $photoURL`. SkautIS ale posílá přímo binární obsah obrázku. 
Ten je sice dostupný v `UserProfile -> $photoData`, ale abychom zachovali kompatibilitu 
s ostatními poskytovateli, lze nastavit v konfiguraci položku `photoProxy`, která práci 
s obrázky sjednotí. Zvolte si takový přístup, který bude lépe vyhovovat dalšímu 
zpracování obrázků po přihlášení.

`photoProxy` může být buď obyčejný string, který představuje cestu k nějakému adresáři. Po přihlášení se v tomto adresáři
vytvoří soubor s obrázkem a $photoURL se nastaví na cestu k němu. Tato cesta bude dostupná jen lokálně.
Po jeho zpracování byste pak tento soubor zase měli smazat.

Druhou možností je zadat array se dvěma položkami, `dir` a `url`. `dir` je stejně jako v předchozím případě
cesta k adresáři pro uložení souboru a `url` je jeho veřejná URL adresa, přes níž je možné
se na soubory v adresáři dostat přes internet. $photoURL se pak nastaví na veřejný URL vzniklého souboru.

```
"photoProxy" => "../some/temp/directory"

"photoProxy" => array(
	"dir" => "../www/portraits",
	"url" => "http://www.moje-aplikace.cz/portraits"
)
```


## Ladění chyb

Pokud něco nefunguje, lze zapnout standardní debug režim v HybridAuth. Stačí do konfigurace
přidat:

```
"debug_mode" => true,
"debug_file" => "cesta_k_souboru/kam_se_bude/logovat.txt"
```

Do tohoto logu se pak kromě ostatních informací o průběhu zpracování 
bude zapisovat i komunikace se SkautISem, každá volaná metoda
a návratová hodnota.


## Pomoc, podpora, hlášení chyb atd.

Pokud jste narazili na jakoukoliv potíž nebo vám něco není jasné, 
přidejte issue zde na GitHubu, anebo [mi napište přímo][me].
Pokusím se poradit, bude-li to v mých silách :-)



[ha]: http://hybridauth.sourceforge.net/
[ha-ug]: http://hybridauth.sourceforge.net/userguide.html
[profile-doc]: ./src/UserProfile.php
[role-doc]: ./src/Role.php
[unit-doc]: ./src/Unit.php
[article-ha]: ./hybridauth.readme.md
[me]: https://github.com/ondrakoupil/


