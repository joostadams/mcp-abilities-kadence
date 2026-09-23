# MCP Abilities — Kadence

Geeft een MCP-agent toegang tot Kadence Blocks, Kadence Blocks Pro, Kadence Pro
en het Kadence-thema, via de WordPress Abilities API.

**Zesendertig abilities: negentien lezen, zeventien schrijven.**

Schrijven is hier dus geen uitzondering. Elke schrijfability vraagt drie dingen:
de capability `kadence_mcp_write` (die bij installatie aan niemand wordt
gegeven), bewerkrecht volgens WordPress zelf, en een token uit een voorafgaande
controlestap. Na afloop wordt er teruggelezen en vergeleken met wat er bedoeld
was.

Let op waar ze schrijven. De meeste raken `post_content` en maken dus een
revisie. `set-entity-meta` en `set-card-layout` schrijven post meta, en die kent
**geen revisies** — de oude waarde in het antwoord is je enige weg terug.
`set-global-typography` en `set-site-css` raken de hele site in plaats van één
post.

### Lezen

| Ability | Wat het doet |
|---|---|
| `kadence/find-post` | van paginanaam of slug naar een `post_id` |
| `kadence/inspect-post` | de blokkenboom van een pagina, header of element |
| `kadence/get-raw-markup` | de opgeslagen blokmarkup, zoals die in de database staat |
| `kadence/diff-blocks` | alleen de verschillen tussen twee blokken |
| `kadence/list-blocks` | welke Kadence-blokken er zijn en uit welke plugin |
| `kadence/describe-block` | de attributen van één blok, gegroepeerd en gepagineerd |
| `kadence/list-entities` | de Kadence-posttypes en hun posts |
| `kadence/get-post-meta` | de Kadence-configuratie die niet in blokattributen staat |
| `kadence/find-usages` | welke posts naar een query, navigatie of card verwijzen |
| `kadence/check-bindings` | wijzen de dynamische koppelingen nog naar iets dat bestaat |
| `kadence/get-global-styles` | het kleurenpalet, de basistypografie en de invoegstandaarden |
| `kadence/describe-query` | de drie lagen van een Query Loop naast elkaar, met proefdraai |
| `kadence/describe-post-type` | taxonomieën en meta-velden, als opstap naar een Query Card |
| `kadence/list-recipes` | welke sectiesjablonen er zijn |
| `kadence/generate-section` | bouwt geldige markup uit een sjabloon — slaat niets op |
| `kadence/preview-write` | de markup voor en na, zonder iets op te slaan |
| `kadence/validate-write` | toetst een voorgenomen wijziging — schrijft niets |
| `kadence/verify-markup` | vindt blokken waarvan de klassen niet meer bij de attributen passen |
| `kadence/prepare-import` | controleert markup uit de editor of van een andere site, zet uniqueIDs om, zet media-, term- en post-ID's om volgens een opgegeven kaart, meldt verwijzingen en geeft een token voor `insert-blocks` — schrijft niets |

### Schrijven

| Ability | Wat het raakt |
|---|---|
| `kadence/set-attributes` | attributen op één blok |
| `kadence/style-blocks` | attributen op meerdere blokken, in één revisie |
| `kadence/set-text` | de tekst binnen één tekstblok |
| `kadence/insert-blocks` | markup uit `generate-section` of `prepare-import` toevoegen aan een post |
| `kadence/remove-blocks` | blokken weghalen, inclusief alles eronder |
| `kadence/replace-block` | een blok herbouwen met behoud van zijn `uniqueID` |
| `kadence/duplicate-blocks` | een blok kopiëren naar een andere post |
| `kadence/create-page` | een nieuwe pagina, standaard als concept |
| `kadence/set-page-status` | concept naar gepubliceerd, of terug |
| `kadence/create-query` | een Query Loop kopiëren, met verse `uniqueID`s |
| `kadence/set-query` | wat een Query Loop ophaalt — post meta |
| `kadence/sync-query-facets` | de facetten weer laten kloppen met de filterblokken — post meta |
| `kadence/create-query-card` | een Query Card kopiëren |
| `kadence/set-card-layout` | kolommen en tussenruimte van een Query Card — post meta, geen revisie |
| `kadence/set-entity-meta` | een `_kad_`-instelling op een Kadence-post — post meta, geen revisie |
| `kadence/set-global-typography` | de globale typografie van het thema — site-breed, geen revisie |
| `kadence/set-site-css` | de Extra CSS van de Customizer — site-breed |

Bestanden en caches worden nergens aangeraakt.

Elk antwoord draagt een `status`-regel die zegt of een leeg resultaat betekent
dat er niets ís, of dat er niet gekeken kon worden.

## Nodig

- WordPress 6.9 of hoger, PHP 7.4 of hoger. Vanaf 6.9 zit de
  [**Abilities API**](https://github.com/WordPress/abilities-api) in core;
  daaronder heb je die als losse plugin nodig, en daar ligt de ondergrens dus
- De [**MCP Adapter**](https://github.com/WordPress/mcp-adapter), als eigen
  plugin ernaast. Hij staat niet in de plugin-directory, dus zoeken onder
  **Plugins → Nieuwe plugin** levert niets op. Haal de ZIP van zijn
  [Releases-pagina](https://github.com/WordPress/mcp-adapter/releases/latest),
  of met WP-CLI:

      wp plugin install https://github.com/WordPress/mcp-adapter/releases/latest/download/mcp-adapter.zip --activate

  Een gevendorde kopie binnen een andere plugin — Gravity Forms levert er een
  mee — laat de klasse wél bestaan maar start niets. Deze plugin start de
  adapter daarom zelf zodra je de hoofdschakelaar aanzet, maar alleen dan.
  Bouw daar niet op: de adapter raadt bundelen zelf af en verwijdert die vorm
  in een latere versie
- Kadence Blocks. De rest van de Kadence-stack is optioneel; wat er niet is
  wordt gewoon niet gemeld

Ontbreekt de Abilities API of de adapter, dan registreert de plugin niets en
legt het instellingenscherm uit wat er mist.

## Downloaden

Eén bestand, en het staat niet achter de groene knop.

Pak `mcp-abilities-kadence.zip` van de
[Releases-pagina](https://github.com/joostadams/mcp-abilities-kadence/releases/latest).
Dat is de ZIP die bij die versietag is gebouwd, met de mapnaam die WordPress
verwacht en zonder de ontwikkelbestanden.

Dus niet **Code → Download ZIP**. Die geeft je de huidige staat van `main` — dat
hoeft geen uitgebrachte versie te zijn — in een map met `-main` erachter.

De agent skill hoef je hier niet apart te halen. Die zit in de plugin en staat
na installatie als download onder **Kadence Blocks → MCP**.

## Installeren

1. De ZIP installeren via **Plugins → Nieuwe plugin → Plugin uploaden**, en
   activeren
2. Naar **Kadence Blocks → MCP**
3. *MCP inschakelen* aanzetten
4. De tools aanvinken die je wil aanbieden — standaard staat alles uit
5. De endpoint-URL kopiëren en in je MCP-client zetten

Updates komen daarna via WP-admin. De plugin controleert de GitHub-releases van
deze repo en biedt een nieuwe versie aan als gewone plugin-update; er is geen
token nodig, want de repo is publiek.

## Endpoint

Twee standen.

**Eigen endpoint** (standaard) — `/wp-json/mcp/kadence`. Elke ingeschakelde
ability staat als losse tool in de lijst van de assistent. De namespace `mcp`
is dezelfde waar de adapter zijn standaardserver neerzet en waar Gravity Forms
zijn server onderhangt, zodat alle MCP-endpoints van een site bij elkaar staan.

De assistent ziet de toolnamen zonder schuine streep: MCP staat die niet toe,
dus `kadence/list-blocks` komt binnen als **`kadence-list-blocks`**. In de
gedeelde stand is juist de naam mét schuine streep de waarde die je aan
`ability_name` meegeeft.

**Gedeelde site-endpoint** — `/wp-json/mcp/mcp-adapter-default-server`. De
endpoint die alle MCP-plugins op de site delen. Eén verbinding, maar de
Kadence-tools zijn alleen bereikbaar via de generieke discovery-tools van
WordPress: de assistent moet eerst vragen wát er is en daarna via een omweg
uitvoeren.

Kies de gedeelde stand alleen als je bewust één verbinding voor de hele site
wil.

## Rechten

Drie eigen capabilities. Bij activering krijgt alleen de beheerdersrol
`kadence_mcp_view` en `kadence_mcp_manage`; de hoofdsleutel krijgt niemand
automatisch.

**Deactiveren raakt je rollen niet aan.** Opruimen gebeurt pas bij verwijderen
van de plugin. Anders zou één deactiveer-activeercyclus je eigen MCP-rol
leeghalen.

| Capability | Geeft recht op |
|---|---|
| `read` | langs de REST-poort komen en posts mogen zien (WordPress-standaard) |
| `kadence_mcp_view` | de leestools uitvoeren, en de toolcatalogus opvragen |
| `kadence_mcp_manage` | het instellingenscherm zien en opslaan |
| `kadence_mcp_full_access` | alles hierboven, ook wat er later bij komt |

**Geef de assistent een eigen gebruiker.** Die rol heeft er **twee** nodig:
`kadence_mcp_view` én `read` — dat laatste is het gewone abonneerecht. Zonder
`read` komt het account niet langs de REST-poort en kunnen de tools die posts
uitlezen geen enkele post zien. Wil de assistent ook concepten en
niet-gepubliceerde headers en elementen kunnen lezen, dan is daarbovenop
`edit_theme_options` nodig; laat dat weg als je dat niet wil. Hang er een
account aan en verbind met een applicatiewachtwoord van dat account. Wat die rol niet mag, kan de assistent
niet — ongeacht welke tools aanstaan. Een rechtenplugin als Members kan de
capabilities per rol toekennen zonder dat deze plugin daarvan hoeft te weten.

De toggles en de capabilities doen iets verschillends:

- de **toggle** bepaalt of een ability wordt aangeboden
- de **capability** bepaalt of dit account hem mag uitvoeren

De toggle is oppervlakteverkleining. De capability is de grendel.

## Agent skill

Onder `skill/` staat een skill met de werkwijzen en valkuilen die niet in een
toolschema passen. De serverbeschrijving draagt de conventies al en wordt
automatisch geladen; de skill is het niveau daarboven.

Je haalt hem op met de knop *Skill downloaden* onder **Kadence Blocks → MCP** —
die levert dezelfde map, meegeleverd met de geïnstalleerde versie. Pak de ZIP
uit in de skills-map van je agent, zodat `kadence-blocks` daar rechtstreeks in
staat. `skill/README.md` beschrijft hetzelfde voor wie vanuit de repo werkt.

## Uitbreiden

Een ability toevoegen is één ruwe definitie in een klasse die
`get_definitions()` heeft, plus die klasse in `Kadence_MCP_Registry::bronnen()`.
De registry vult schema, `permission_callback` en meta aan, en het
instellingenscherm bouwt zijn lijst uit diezelfde registry — je hoeft nergens
een tweede lijst bij te werken.

Het filter `kadence_mcp_ability_definition` mag label, omschrijving en schema's
aanpassen. Naam, ability-klasse, `permission_callback` en `execute_callback`
worden daarna teruggezet: een filter mag de beschrijving veranderen, nooit de
grendel.

## Licentie

GPL-2.0-or-later.
