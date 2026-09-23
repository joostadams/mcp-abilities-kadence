---
name: kadence-blocks
description: "Werkwijze en valkuilen bij het uitlezen én wijzigen van een Kadence-site via de Kadence MCP-server (kadence/list-blocks, describe-block, inspect-post, diff-blocks, list-entities, find-post, get-global-styles, validate-write, preview-write, set-attributes, set-text, duplicate-blocks, prepare-import). Laad dit vóór je een vraag beantwoordt over hoe een Kadence-pagina is opgebouwd, waarom twee blokken er anders uitzien, wat er moet veranderen voor mobiel, of voordat je iets schrijft."
---

# Kadence uitlezen via MCP

Deze skill beschrijft hoe je de Kadence MCP-server gebruikt zonder de fouten te
maken die de toolschema's niet kunnen voorkomen.

De server telt zevenendertig abilities: negentien lezen, achttien schrijven.
**Schrijven is dus geen uitzondering** — controleer per tool of hij schrijft, in
plaats van ervan uit te gaan dat lezen de norm is.

Elke schrijfability vraagt de capability `kadence_mcp_write`, bewerkrecht op de
post volgens WordPress, en een token uit een voorafgaande controlestap. Lees
hieronder eerst hoe die controle werkt voordat je er een gebruikt.

Let op dat ze niet allemaal hetzelfde raken. De meeste schrijven `post_content`
en maken dus een revisie. `set-entity-meta` en `set-card-layout` schrijven post
meta, en die kent géén revisies — de oude waarde in het antwoord is je enige weg
terug. `set-global-typography` en `set-site-css` raken de hele site in plaats van
één post.

## Vóór je een wijziging voorstelt

Zodra je een wijziging AANRAADT of SCHRIJFT, hoort die getoetst te zijn —
anders stel je iets voor dat stil niets doet of stil iets breekt.

**Roep altijd `kadence/validate-write` aan** met het blok en de voorgenomen
attributen. Hij schrijft niets; hij geeft `veilig`, `riskant` of `blokkeer`.

Bij `veilig` komt er een **token** terug. Dat is gebonden aan deze post, dit
blok, deze exacte attributen én de wijzigingsdatum van de post. Een toekomstige
schrijfactie eist dat token: overslaan kan niet, en iets anders schrijven dan
wat getoetst is ook niet. Is de post intussen door iemand anders aangepast, dan
vervalt het token — een goedkeuring op een versie die niet meer bestaat is geen
goedkeuring.

Toon daarna met **`kadence/preview-write`** hoe de markup eruit zou zien. Dat
slaat niets op en is de laatste stap voordat je iets aanraadt: de gebruiker
kijkt er één keer naar in plaats van het achteraf te moeten repareren. Let in
het resultaat vooral op de `innerHTML` — verwijst die nog naar een oude waarde,
dan is de wijziging niet compleet.

Bij `riskant` komt er **geen** token. Dat is opzet: daar hoort een mens te
beslissen, niet een hash.

Waarom de typetoets ertoe doet: `WP_Block_Type::prepare_attributes_for_render()`
draait bij élke render `rest_validate_value_from_schema()` over je attributen en
**gooit een waarde die niet slaagt stil weg**, waarna de standaardwaarde ervoor
in de plaats komt (`wp-includes/class-wp-block-type.php:513-520`). Je
schrijfactie lijkt dan geslaagd en doet niets. `validate-write` gebruikt exact
diezelfde functie, dus wat hij goedkeurt overleeft het renderen.

De kern van die toets is een waarneming, geen lijst. Een blok dat **zelfsluitend**
is opgeslagen (`/-->`, dus lege `innerHTML`) heeft geen eigen markup die kan gaan
afwijken — daar is elk attribuut vrij. Heeft het blok wél markup, dan is de vraag
of de huidige waarde daar **letterlijk** in voorkomt.

Waargenomen voorbeeld:

```html
<!-- wp:kadence/rowlayout {"uniqueID":"228_2b2d4e-25",...} -->
<!-- wp:kadence/column {"uniqueID":"228_4ec348-d0",...} -->
<div class="wp-block-kadence-column kadence-column228_4ec348-d0">
```

De rowlayout slaat **niets** op. De column bakt zijn `uniqueID` in de klasse.
Diezelfde waarde wijzigen is dus op het ene blok risicoloos en op het andere een
stille breuk. `get-raw-markup` laat dat zien; `validate-write` toetst het.

**`is_dynamic` is géén veiligheidssignaal.** Elk Kadence-blok krijgt een
render_callback, dus dat veld is bij állemaal `true`. Het zegt niets over
validatie.

Drie attributen hebben daarnaast een eigen faalpad, geen van drieën zichtbaar in
de markup: `uniqueID` (leeg laat wrapper én CSS vervallen; botsing laat twee
blokken hun CSS delen), `kbVersion` (op 1 schakelt de hele rendertak om), en
`columns` op een rowlayout (klopt de layoutklasse niet meer met het aantal
kinderen).

## Verwijzingen controleren

`kadence/check-bindings` loopt een post na op alles wat naar buiten wijst. Doe dat
vóór je iets aanraadt over dynamische inhoud of een Query Loop, want **Kadence
logt hier niets en toont geen foutmelding**:

- een kapotte dynamische verwijzing laat het blok **stil verdwijnen**
- een `fallback` maskeert dat actief
- een ongeldig posttype in een Query Loop valt terug op `post` en toont
  **blogberichten**
- een taxonomie die aan geen enkel posttype hangt geeft een leeg filter

Let op dat dezelfde instelling op meerdere plekken staat. Een dynamische knop
bewaart zijn koppeling zowel in `kadenceDynamic.link` als **nog een keer** als
`[kb-dynamic field='…']`-shortcode in het `link`-attribuut zelf. Op deze site
lopen die twee al uiteen. Wie er één aanpast, maakt het erger.

## De regel die er het meest toe doet

**Trek nooit een conclusie over wat een blok kan uit een gefilterde lijst.**

`describe-block` heeft `search`, en die matcht op de attribuut**naam**. Kadence
bewaart responsive waarden vaak als array `[desktop, tablet, mobiel]` onder één
naam. Het attribuut dat de mobiele richting van een Sectie bepaalt heet
`direction`. Zoek je op `mobile`, dan vind je hem niet — en concludeer je ten
onrechte dat een Sectie geen mobiele instellingen heeft. Die heeft er dertig.

Gebruik in plaats daarvan `group` (`responsive`, `spacing`, `achtergrond`,
`rand`, `typografie`, `kleur`, `link`, `afmeting`, `conditioneel`,
`identiteit`), of haal alles op met `per_page: 100`. Het veld `groups` in het
antwoord telt per groep over het héle blok, ook wat niet op deze pagina staat.

## De conventies zijn niet uniform

Dit is de tweede valkuil, en hij ziet eruit als de eerste.

| Blok | Hoe responsive is opgeslagen |
|---|---|
| `kadence/column` | `direction`, `justifyContent`, `gutter` als array van 3 |
| `kadence/advancedheading` | `fontSize` als array van 3, met `sizeType` voor de eenheid |
| `kadence/tabs` | `tabWidth` als array van 3, maar `mobileLayout` en `tabletLayout` als losse strings |
| `kadence/rowlayout` | `mobilePadding`, `tabletPadding`, `mobileLayout` als losse attributen |

Er is dus geen regel die voor alle blokken geldt. Controleer per blok.

## Invoegstandaarden: wat een nieuw blok hier krijgt

`get-global-styles` geeft onder `block_defaults` de site-brede standaarden per
blok terug. Lees goed wat dat wel en niet is.

Deze optie gaat **uitsluitend naar de editor** — er is geen render-filter. Het is
een invoegstandaard: bij een nieuw blok vult de editor die waarden in, en ze
worden bij het opslaan in de markup geschreven. Een ontbrekend attribuut op een
**bestaand** blok betekent dus gewoon de standaardwaarde uit `describe-block`.

Waar het wél voor dient: weten welke vorm iets op deze site hoort te krijgen.
Staat er bijvoorbeeld `kadence/advancedheading: {htmlTag: "p"}`, dan is een nieuw
"Geavanceerde tekst"-blok op die site standaard een alinea en geen kop. Dat is
huisstijl, en het is nuttig als je iets voorstelt om te maken.

## Plaatsing en gegevensstroom zijn twee verschillende beperkingen

Vier velden, en ze doen allemaal iets anders.

| Veld | Betekent |
|---|---|
| `parent` | in welk blok dit blok DIRECT mag staan |
| `ancestor` | onder welk blok het ergens moet hangen, hoe diep ook |
| `uses_context` | welke gegevens het van een voorouder ontvangt |
| `provides_context` | welke gegevens het aan zijn nakomelingen levert |

Dat verschil verklaart iets dat anders raadselachtig is. De queryblokken —
`query-filter`, `query-pagination`, `query-sort`, `query-result-count` — hebben
`parent: [kadence/query, kadence/column, kadence/modal]` én
`ancestor: [kadence/query]`. Ze mogen dus direct in een Sectie staan, maar
alleen als die Sectie zelf onder een query hangt. **Daarom verschijnen ze in de
editor pas zodra je in de Query Loop staat.** 29 van de 92 Kadence-blokken
gebruiken `ancestor`.

Twee blokken hebben wél `ancestor` maar géén `parent`:
`kadence/navigation-link` en `kadence/off-canvas-trigger`. Op alleen `parent`
filteren doet die ten onrechte doorgaan voor vrij plaatsbaar.

`uses_context` en `provides_context` gaan niet over plaatsing maar over
gegevens: een query-card weet welke post hij rendert doordat `kadence/query`
die context levert. Werkt dynamische inhoud in het ene blok wel en in het andere
niet, kijk dan daar.

## Waar welke waarde staat

**Tekst staat in de markup, niet in de attributen.** `kadence/listitem`,
`kadence/advancedheading` en `kadence/singlebtn` bewaren hun inhoud in de
innerHTML. Wil je weten wát er staat, gebruik `include_text: true` op
`inspect-post`. Containers leveren dan terecht niets op.

**Of een blok verborgen is, staat in `metadata`.** Dat is de native
verbergoptie van WordPress zelf — `blockVisibility` zit in
`wp-includes/js/dist/block-editor.js` en komt in de Kadence-stack nergens voor.
Hij geldt dus voor élk blok, niet alleen voor Kadence-blokken. Aan de attributen is dat niet te zien.
`inspect-post` geeft `metadata` daarom standaard mee. In datzelfde veld staat
`name` — dat is de naam die de lijstweergave in de editor toont.

**Kleuren zijn verwijzingen.** `palette1` tot en met `palette15` wijzen naar het
globale palet; vraag `get-global-styles` voor de hexwaarden. Let op dat het
palet drie sets kent en dat `active` zegt welke geldt. En achtergrond staat niet
overal op dezelfde plek: `bgColor` op `kadence/rowlayout`, `background` op
`kadence/column`, en soms op het bovenliggende blok — bij een mega-menu kan hij
op de `kadence/navigation-link` zelf staan.

**Een ontbrekend attribuut betekent "niet ingesteld", niet "leeg".** `parse_blocks`
geeft alleen wat expliciet is opgeslagen. Staat er geen `mobileLayout`, dan
geldt de standaardwaarde uit `describe-block` — niet de waarde nul.

**`uniqueID` is `{postID}_{hash}`.** Een blok met `6_` ervoor komt uit post 6,
ook als je het in een andere post tegenkomt: dat gebeurt bij herbruikbare
onderdelen zoals headers en mega-menus.

## Niet alles staat in de blokken

Dit is de valkuil die het duurst is om zelf te ontdekken.

Een **Query Loop** is een `kadence_query`-post die via een `id`-attribuut in een
pagina wordt opgenomen. Het blok `kadence/query` heeft precies zes attributen —
`id`, `uniqueID`, `className`, `style` en twee GPEB-velden — en **geen daarvan
gaat over de query**. Posttype, sortering, `meta_key`, aantal per pagina en de
filters staan in post meta onder `_kad_query_query`. Vraag `get-post-meta` zodra
de vraag is hoe iets is ingesteld in plaats van dat het er staat.

Hetzelfde geldt voor `kadence_query_card` (`_kad_query_card_*`) en voor de
Kadence-header. Deze objecten zijn los bewerkbaar in het beheer — Queries,
Navigaties, Cards — en worden op meerdere plekken hergebruikt. Ga daarom nooit
iets voorstellen aan zo'n object zonder eerst `find-usages` te draaien: dan weet
je waar de wijziging nog meer landt.

## Query Loops, en waarom ze anders zijn dan ze lijken

Een Query Loop is géén blok met instellingen. Het is een **`kadence_query`-post**
die via een `id`-attribuut in een pagina wordt opgenomen:

```html
<!-- wp:kadence/query {"uniqueID":"420_9c962e-39","id":1456} /-->
```

Daaruit volgen vier dingen die je moet weten voordat je iets voorstelt.

**De instellingen staan in post meta, niet in het blok.** Het blok
`kadence/query` heeft zes attributen en geen daarvan gaat over de query. Alles —
posttype, sortering, `meta_key`, aantal per pagina — staat in
`_kad_query_query`. Vraag `get-post-meta`.

De toegestane waarden voor `orderBy` zijn `date`, `title`, `author`,
`menu_order` en `meta_value`. Bij `meta_value` vullen `orderMetaKey` en
`orderMetaKeyType` de `meta_key` en `meta_type` van de onderliggende WP_Query.

**Dezelfde query op twee pagina's is één object.** Kopieer je een Query Loop
naar een andere pagina en pas je daar de filters aan, dan verandert hij op beide
plekken. Een afwijkende weergave vraagt een nieuwe `kadence_query`. Dat is een
beperking van het model, geen instelling die je over het hoofd ziet. Draai
daarom `find-usages` vóór je iets voorstelt aan een bestaande query.

**Filters en paginering zijn losse blokken**, geen instellingen van het
queryblok. `kadence/query-filter`, `-filter-checkbox`, `-filter-search`,
`-filter-reset`, `kadence/query-pagination`, `kadence/query-sort` en
`kadence/query-result-count` staan als eigen blokken in de markup van de
query-post. Hun configuratie staat daarnaast óók in `_kad_query_facets`, per
facet als JSON met de `uniqueID` van het bijbehorende blok erin.

**Er is één card per query.** `kadence_query_card` bepaalt de opmaak van een
resultaat en je kunt er maar één vorm in kwijt. Variatie loopt via dynamische
inhoud en per element wel of niet tonen — niet via een tweede card.

**Een facet op een taxonomie die niet aan het posttype hangt toont niets.**
Controleer dat voordat je concludeert dat een filter stuk is. De taxonomie moet
daadwerkelijk bij het posttype geregistreerd staan.

**Kadence toont alleen gepubliceerde items.** Een taxonomie waaraan nog niets
gekoppeld is verschijnt daarom niet in de taxonomielijst, ook al bestaat hij en
heeft hij termen. Staan alle termen op `count: 0`, dan is dat het signaal — en
meestal betekent het dat de taxonomie aan het verkeerde posttype hangt of dat de
items hun term nog niet dragen. Zeg dan niet "de taxonomie bestaat niet".

**Welke posttypes Kadence aanbiedt** wordt bepaald door `public => true` en
`show_in_rest => true` (`kadence-blocks-pro/includes/init.php:195-201`), níet
door `exclude_from_search`. Die laatste vlag bepaalt alleen welke posttypes de
frontend-filters meenemen.

## Dynamische inhoud: twee mechanismen die op elkaar lijken

**Blokken die normaal statisch zijn** — knoppen, afbeeldingen, koppen — krijgen
een `kadenceDynamic`-object waarin per eigenschap staat of die dynamisch wordt:

```json
"kadenceDynamic": {
  "link": { "enable": true, "field": "post|post_url", "before": "• " },
  "text": { "enable": false }
}
```

Dat attribuut staat in géén enkele `block.json` en ook niet in het live register:
Kadence Blocks Pro leest het bij het renderen uit de opgeslagen attributen. Je
ziet het dus wel met `inspect-post`, maar nooit met `describe-block`.

**Blokken die er speciaal voor zijn** — `kadence/dynamichtml`,
`kadence/dynamiclist` — hebben géén `kadenceDynamic`. Hun hele inhoud ís de
koppeling, dus die staat in gewone attributen:

```
source · field · metaField · customMeta · relate · relcustom
wrapTag · stripHTML · limitWords · maxWords · showEllipsis · showAllFields
```

Het formaat van `field` is `bron|veld`, bijvoorbeeld `post|post_title`. ACF- en
andere metavelden lopen via `metaField` of `customMeta`; `relate` en `relcustom`
wijzen naar een gerelateerde post — dat is de route als je op een item iets van
zijn ouder wil tonen.

Zoeken op "dynamic" in `describe-block` levert bij deze blokken **niets** op. De
koppeling zit in `source` en `field`, en die woorden bevatten dat niet.

### Waarom een card wél herbruikbaar is

Een `kadence_query_card` is een gedeeld object: dezelfde card in twee queries is
één post. Dat is meestal een kenmerk en geen risico, want een koppeling als
`post|post_title` blijft betekenisvol op elk posttype.

Het wordt pas een probleem als een koppeling typegebonden is — een `metaField`
of `customMeta` naar een veld dat maar op één posttype bestaat, of een
`relate`/`relcustom` naar een relatie die er elders niet is. Kijk dus naar de
kóppelingen voordat je waarschuwt voor hergebruik, niet naar het feit dát hij
gedeeld wordt.

### Waar de gegevens vandaan komen

`kadence/dynamichtml` heeft `uses_context: postId, queryId,
kadence/dynamicSource, kadence/repeaterRowData, kadence/repeaterRow`. `postId` is
de post die de query op dat moment rendert. Daarom werkt zo'n blok binnen een
query en daarbuiten niet: zonder die context weet het niet welke post het is.
Werkt dynamische inhoud ergens niet, kijk dan eerst of de context er wel is.

## Context is niet altijd van de blokmaker

`uses_context` uit het live register bevat ook sleutels die andere plugins er
tijdens het draaien bij zetten. Gravity Perks Entry Blocks hangt zijn
entry-context via het filter `get_block_type_uses_context` aan **élk** niet-GPEB
blok. Op `kadence/dynamichtml` zie je daardoor twaalf sleutels, waarvan er vijf
van Kadence zijn en zeven van GravityWiz.

`describe-block` splitst dat: **`uses_context_own`** komt uit de eigen
`block.json`, **`uses_context_added`** is er door iets anders bij gezet.
Concludeer niet dat Kadence iets met entry blocks doet omdat die sleutels er
staan — dat is GPEB dat zichzelf breed beschikbaar maakt.

## Zichtbaarheid zit op twee niveaus

Dit verschilt per blok en is een veelgemaakte fout.

| Blok | Zichtbaarheidsopties |
|---|---|
| `kadence/rowlayout` | `loggedIn`, `loggedOut` én `vsdesk`, `vstablet`, `vsmobile` |
| `kadence/column` (Sectie) | alleen `vsdesk`, `vstablet`, `vsmobile` |

Verbergen op grond van inlogstatus kan dus **alleen op een Row Layout**. Moet
een Sectie voor uitgelogde bezoekers verdwijnen, dan hoort daar een Row Layout
omheen. Beide vallen in `describe-block` onder de groep `conditioneel`.

## Werkwijzen

**"Hoe is deze pagina opgebouwd?"**
`find-post` → `inspect-post` met `kadence_only: true`. Voeg `include_text: true`
toe als de vraag over inhoud gaat in plaats van over opmaak.

**"Waarom ziet A er anders uit dan B?"**
Gebruik `diff-blocks`. Zelf attributen selecteren en vergelijken is
onbetrouwbaar: je mist wat je niet hebt opgevraagd. Een `null` in het antwoord
betekent "niet ingesteld bij deze", dus daar geldt de standaardwaarde.

**"Wat moet ik aanpassen voor mobiel?"**
Eerst `inspect-post` voor de structuur, dan `describe-block` met
`group: "responsive"` op het blok in kwestie, dan gericht `inspect-post` met die
attribuutnamen om de huidige waarden te zien. Vergelijk de mobiele waarde met de
desktopwaarde — een mobiele padding die groter is dan de desktopvariant is
bijna altijd onbedoeld.

**"Bouw een Query Loop / pas er een aan"**
`list-entities` met `post_types: ["kadence_query"]` voor de bestaande queries,
dan `find-usages` op elke kandidaat om te zien welke daadwerkelijk in gebruik is
— een verlaten query als model nemen kopieert een vormtaal die niemand meer
ziet. Daarna `get-post-meta` op de query die wél gebruikt wordt, en
`inspect-post` op de query-post voor de blokopbouw eromheen.

Bij het aanmaken toont Kadence een keuzescherm met presets (no filters, simple
filters, advanced filters, sidebar left, sidebar right, sidebar left single).
Die keuze bepaalt welke filterblokken er meteen in staan; hij is over te slaan.
De titel die daarna gevraagd wordt is intern en wordt de titel van de
`kadence_query`-post.

**"Welke kleur is dit?"**
`get-global-styles`, en kijk naar `active` om te weten welke van de drie
paletsets geldt.

## Row Layout tegenover Sectie

Beide zijn containers en een pagina gebruikt ze vaak door elkaar.

`kadence/rowlayout` regelt kolomaantallen, `collapseOrder` en hoe kolommen op
tablet en mobiel opbreken. `kadence/column` (in de editor "Sectie") is een
flex-container met `direction`, `justifyContent`, `gutter`, `flexBasis` en
`flexGrow`, elk per breakpoint.

Zit iets niet goed op mobiel, kijk dan éérst welke van de twee het omhullende
blok is. Een geneste Sectie los je op met `direction`, een Row Layout met
`mobileLayout`.

## Blokken die niet in het register staan

`kadence/pane`, `kadence/tab`, `kadence/countdown-inner` en
`kadence/countdown-timer` registreert Kadence alleen in JavaScript. `describe-block`
valt voor die vier terug op de `block.json` op schijf en zet
`registration: "editor-only"` in het antwoord. Ze bestaan dus wel degelijk; je
komt ze in elke accordeon tegen.

## Meer dan één blok opmaken: `style-blocks`

`set-attributes` doet één blok per aanroep. Zodra je een hele sectie opmaakt is
dat de verkeerde tool, en niet uit gemakzucht: een hero opmaken raakt de rij, de
kolom, twee tekstblokken, de knoppenrij en de knop. Dat is met `validate-write`
plus `set-attributes` veertien aanroepen — en bij elke tussenstap verandert
`post_modified`, dus **elk volgend token vervalt** en je moet opnieuw toetsen.

`style-blocks` neemt een lijst van `{unique_id, attributes}` en werkt in twee
stappen: zonder token wordt elk blok afzonderlijk getoetst (letterlijk met
`validate_write()`, dus exact dezelfde controles), met token gaat alles in één
opslag en dus één revisie.

**Alles of niets.** Is één blok `blokkeer` of `riskant`, dan komt er geen token
en wordt er niets geschreven. Half doorvoeren laat de pagina achter in een staat
die niemand heeft bedoeld en die niet met één revisie terug te draaien is. Haal
dat ene blok uit de lijst en doe het apart.

Een blok dat twee keer in de lijst staat wordt geweigerd — anders overschrijft
de tweede vermelding stil de eerste.

Vuistregel: **één blok → `set-attributes`** (met `preview-write` ertussen als je
de markup wil zien). **Meer dan één → `style-blocks`.**

## Tekst schrijven is een andere ability dan attributen schrijven

De tekst van een kop of alinea staat **niet in de attributen**. Hij staat in de
innerHTML:

```html
<!-- wp:kadence/advancedheading {"level":1,"uniqueID":"1471_12622c-65",...} -->
<h1 class="kt-adv-heading1471_12622c-65 wp-block-kadence-advancedheading ..."
    data-kb-block="kb-adv-heading1471_12622c-65">Samen sterk voor de studentensport</h1>
<!-- /wp:kadence/advancedheading -->
```

`kadence/advancedheading` heeft wel een attribuut `content`, maar dat heeft
`source: html` — het wordt uit de markup gelezen, niet uit het blokcommentaar.
Schrijf je het naar het commentaar, dan gebeurt er niets. `validate-write` geeft
daar `blokkeer` op, met precies die reden.

Gebruik daarvoor **`kadence/set-text`**. Die werkt in **twee** stappen, niet in
drie:

1. Roep hem aan **zonder token**. Je krijgt `before`, `after` en een token
   terug, en er wordt niets opgeslagen.
2. Roep hem opnieuw aan **met dat token** om te schrijven.

Een aparte `validate`-stap zou niets toevoegen: er is geen schema waartegen je
een tekst kunt toetsen, dus de enige zinvolle controle is de markup lezen.

`set-text` vervangt uitsluitend het deel tussen de buitenste tag en laat die tag
letterlijk staan. Dat is geen netheid maar noodzaak: die klassen en dat
`data-kb-block` worden door `save()` opnieuw opgebouwd uit de attributen, en
wijkt de opgeslagen markup daarvan af, dan meldt Gutenberg bij het openen "deze
blokinhoud is onverwacht gewijzigd". Hij weigert daarom bij een blok met
kindblokken, bij een innerHTML die niet precies één omhullend element is, en bij
een lege tekst.

HTML in de tekst overleeft alleen als opmaaktag: `strong`, `em`, `b`, `i`, `u`,
`br`, `sub`, `sup`, `del`, `code`, `mark`, `span` en `a`. `mark` staat er bewust
in — dat is waar Kadence Advanced Highlight mee werkt, en die zou anders bij elke
tekstwijziging sneuvelen. Wat verwijderd is staat in `notes`.

## metadata en lock zijn echt, ook al staan ze in geen enkel schema

`metadata` draagt de **bloknaam** (wat je in de lijstweergave ziet) en de
zichtbaarheidsschakelaar; `lock` het slotje tegen verplaatsen of verwijderen.
Allebei van WordPress zelf, allebei afwezig in de `block.json` van Kadence.

Dat heeft op 11-09-2026 een bloknaam gekost: een schrijfactie op pagina 1471
slaagde, en de naam "Hero" van de rij was daarna weg omdat de normalisatie het
attribuut als "onbekend" wegfilterde. Sinds 1.2.0 worden ze ongemoeid
doorgegeven en staan ze achteraan in het commentaar, waar de editor ze ook
neerzet.

Je kunt ze ook zelf zetten: `validate-write` geeft er `ok` op met een notitie
erbij, zodat een naam die door een eerdere versie is weggevallen te herstellen
is. Er valt niets te toetsen — het zijn geen Kadence-attributen, dus geen render
hangt ervan af — maar dat betekent ook dat een verkeerde sleutel nergens opvalt.
Controleer de vorm dus zelf: `metadata` verwacht `{"name":"Hero"}`.

Twee dingen volgen daaruit. Lees de `notes` van elke schrijfactie — daar staat
letterlijk wat er is weggelaten en waarom. En zet bij een blok met een naam die
naam expliciet mee als je twijfelt of hij er nog staat; `inspect-post` geeft
`metadata` standaard mee, dus je kunt het vooraf zien.

## Een nieuwe sectie bouwen: niet zelf markup schrijven

Verzin geen blokmarkup. Vraag `kadence/list-recipes` welke sjablonen er zijn en
laat `kadence/generate-section` hem bouwen.

De reden is niet gemak maar correctheid. Kadence bakt het `uniqueID` van een
blok in zijn eigen klassenamen — bij `advancedheading` zelfs twee keer, in de
klasse én in `data-kb-block` — en de JSON in het blokcommentaar wordt door
WordPress met eigen vlaggen gecodeerd, waarbij een dubbele min `\u002d\u002d`
wordt omdat hij anders het HTML-commentaar zou afsluiten. Met de hand geschreven
markup wijkt daar gegarandeerd van af, en dat zie je niet aan het resultaat: het
blok verschijnt, maar zonder zijn CSS.

De volgorde is:

1. `list-recipes` — welke sjablonen, welke slots
2. `generate-section` met `post_id` — bouwt en controleert, slaat niets op,
   geeft markup plus een token
3. `insert-blocks` met dat token — plaatst het, op `append`, `prepend`, of met
   `position: after`/`before` plus `relative_to`

`post_id` is bij stap 2 verplicht omdat de `uniqueID`s uniek moeten zijn binnen
de post waar de sectie in landt. De bezette IDs van die post worden opgehaald en
vermeden.

Twee controles die je niet kunt overslaan. De gebouwde markup wordt geparsed en
opnieuw geserialiseerd, en komt daar niet identiek uit, dan krijg je een fout in
plaats van markup — dan zou WordPress hem bij het opslaan hebben herschreven.
En `insert-blocks` weigert te schrijven als er door de invoeging een bestaand
`uniqueID` zou verdwijnen of een dubbele zou ontstaan.

### Past het ontwerp in geen sjabloon: `recipe: "custom"`

De vier sjablonen dekken vier vormen. Een echt ontwerp heeft er meer — een hero
met een label boven de kop, een sectie met vijf gekleurde balken, drie kolommen
waarvan er één een afwijkende achtergrond heeft. Dan geef je de structuur zelf
op via `tree`.

Bouw wat bij elkaar hoort in één keer. Sinds 1.7.0 kan er ook achteraf iets in
een bestaande container: `insert-blocks` en `duplicate-blocks` kennen
`position: "inside"` met de `uniqueID` van die container. Dat is de enige manier
om bijvoorbeeld een gekopieerde accordeon in een kolom te krijgen, binnen de
achtergrond en de contentbreedte van die rij — een blok op het hoogste niveau
staat daarbuiten.

Een zelfsluitend blok heeft geen binnenkant en wordt geweigerd: een Row Layout
zonder eigen markup of een knop kan geen kindblokken dragen.

Elke knoop is `{block, attrs, tag, text}` of `{block, attrs, children}` — tekst
en kinderen samen wordt geweigerd, want de tekst zou de kinderen overschrijven.

```json
[{"block": "kadence/rowlayout", "attrs": {"bgColor": "palette1"},
  "children": [
    {"block": "kadence/column", "children": [
      {"block": "kadence/advancedheading", "attrs": {"background": "palette3"},
       "tag": "div", "text": "Over ons"},
      {"block": "kadence/advancedheading", "tag": "h1", "text": "Samen sterk voor…"}
    ]}
  ]}]
```

Wat vastligt verandert niet: de wrapper-markup komt uit dezelfde afgelezen
tabel, de `uniqueID`s worden hier uitgedeeld (geef ze dus niet zelf mee), elk
attribuut wordt getoetst zoals `validate-write` dat doet, en de parse- en
serialiseerrondgang blijft de eindcontrole. Alleen blokken waarvan de vorm
bekend is; voor de rest is `duplicate-blocks` de weg.

`tag` en `level` worden automatisch op elkaar afgestemd. Dat is nodig omdat
`advancedheading` zijn tag uit twee attributen bepaalt: `htmlTag` staat op
`"heading"` en valt dan terug op `level`. Schrijf je een `h3` in de markup maar
blijft `level` op 2, dan rendert Kadence een `h2` terwijl er een `h3` staat —
precies de stille mismatch die Gutenberg later opmerkt.

**De sjablonen leveren structuur, geen opmaak.** Geen kleuren, geen marges, geen
lettergroottes. Dat is opzet: die komen uit de globale stijlen, en een verzonnen
waarde is ruis die iemand later moet opsporen. Wil je de sectie opmaken, doe dat
erna met `set-attributes` op de `uniqueID`s die `generate-section` teruggaf.

Staat er al iets op de pagina dat lijkt op wat je moet bouwen, gebruik dan
`duplicate-blocks` in plaats van een sjabloon — dan erf je de opmaak die er al
is, in plaats van hem opnieuw te moeten zetten.

## Kopiëren: de id_map moet mee

`duplicate-blocks` geeft in de droogloop drie dingen terug: de markup, een
**`id_map`** van oude naar nieuwe `uniqueID`, en een token. Bij het schrijven
(`dry_run: false`) moet je die `id_map` **letterlijk teruggeven**, net zoals je
bij `insert-blocks` de markup teruggeeft.

Dat is geen formaliteit. Zonder die kaart zou de ability verse ID's genereren en
dus iets anders schrijven dan je hebt goedgekeurd — en tot 1.3.0 deed hij dat
ook, waardoor het token per definitie nooit klopte en schrijven onmogelijk was.
Voor het schrijven wordt gecontroleerd dat de kaart precies de te kopiëren
blokken dekt, geen dubbele waarden bevat, en niet botst met ID's die al in de
doelpost staan.

Gebruik `duplicate-blocks` boven `generate-section` zodra er al iets op de
pagina staat dat lijkt op wat je moet bouwen: je erft dan de opmaak die er al
is, in plaats van hem opnieuw te moeten zetten.

## Wat je niet moet genereren, en waarom

`generate-section` kent vier sjablonen en die gebruiken vijf bloktypes. Voor de
rest geldt: **kopiëren met `duplicate-blocks`, niet bouwen.**

De accordeon laat zien waarom. Zijn wrapper draagt klassen die stuk voor stuk
uit attributen worden afgeleid:

```
kt-accordion-id{uniqueID}  kt-accordion-has-{paneCount}-panes  kt-active-pane-0
kt-pane-header-alignment-left  kt-accodion-icon-style-basic  kt-accodion-icon-side-right
```

plus `data-allow-multiple-open` en `data-start-open` op de binnenste laag, en
per pane een `kt-accordion-pane-{volgnummer}` dat moet meelopen met het `id`-
attribuut. Verander je één attribuut, dan moet de klasse mee — en gokt de
generator verkeerd, dan verschijnt het blok gewoon, zonder zijn CSS.

En dan de twee die niemand ooit zou verzinnen: in de pane staat
`kt-acccordion-button-label-show` met **drie c's**, en in de wrapper
`kt-accodion-icon-style-basic` met **"accodion" zonder r**. Dat zijn typefouten
in Kadence zelf, en ze moeten letterlijk worden overgenomen.

Dat is de regel achter de hele `VORMEN`-tabel in de plug-in: die is afgelezen
van markup die Kadence zelf heeft geschreven, niet uit documentatie. Waar geen
waarneming is, wordt niet gegenereerd.

Praktisch: staat er al een accordeon, tabs, een countdown of een videopopup op
de site, kopieer die dan met `duplicate-blocks` en pas hem daarna aan met
`set-attributes`. Staat er niets, bouw het dan in de editor (zie *Via de editor
bouwen*) of voer editor-markup in met `prepare-import`.

**Kennen is niet bouwen.** Een blokprofiel kan `bouwbaar: false` zijn: de
plug-in kent dan de waardenlijsten, de afgeleide markup en de controles, maar de
generator bouwt het niet. Tabs staan er zo in. `describe-block` en
`validate-write` weten dus wel wat een geldige tabs-waarde is, en
`prepare-import` toetst het aantal tabs, maar `generate-section` weigert.

De Advanced Slider (`kadence/slider` met `kadence/slide`) is wél te bouwen: zijn
`save()` is volledig afgelezen, en gegenereerde slides zijn in de editor geldig
gemeten.

## Markup van elders: `prepare-import`

`insert-blocks` neemt alleen markup met een token. Dat token komt uit
`generate-section` — of uit `prepare-import`, voor markup die de plug-in niet
zelf bouwde: geserialiseerd in de editor, uitgelezen met `get-raw-markup` op een
andere omgeving, of uit een patroon. `prepare-import` schrijft niets.

Wat hij doet, in volgorde:

1. alleen blokken — losse HTML wordt in de editor een Klassiek blok
2. elk bloktype moet op déze site bestaan
3. de markup moet een parse-serialiseerronde overleven
4. elke uniqueID krijgt het prefix van de doelpost, in attribuut én klassen
5. klassen tegen attributen, voor elk blok met een profiel — zoals `verify-markup`
6. schema en waardenlijsten, zoals `validate-write`
7. tellers als `slideCount` en `tabCount` tegen het aantal kindblokken
8. verwijzingen: links naar een ander domein, media, custom SVG-iconen
   (`kb-custom-N`), termen, andere posts (navigatie, header, query, query card,
   vector, formulier, menu-item naar een post), paletkleuren met hun waarde op
   de doelsite, en eigen CSS-klassen

Oordelen: **veilig** geeft een token; **blokkeer** niet; **riskant** — een
verwijzing die op de doelsite niet bestaat — alleen met `accept_warnings`, en
dat beslist een mens.

Omzetten gaat alleen expliciet, er wordt niets geraden:

| wat | hoe |
|---|---|
| domein, icoon, klasse | `replace`: `[{"from":"https://oud","to":"https://nieuw"}]` |
| media-ID | `media_map`: `{"111": 87}` — de url wordt die van het nieuwe bestand; geldt voor `{id, url/img}` én voor achtergrondparen als `bgImg` + `bgImgID` |
| term-ID | `term_map`: `{"3": 12}` — het label wordt de naam van de nieuwe term |
| post-ID | `post_map`: `{"183": 412}` — het `id` van `kadence/navigation`, `header`, `query`, `query-card`, `vector`, `advanced-form`, en van een `navigation-link` met `kind: post-type` (daar wordt de url de permalink van de nieuwe post; het label blijft) |

Een post-ID dat op de doelsite wel bestaat maar een ander type is, is erger dan
een ontbrekend: een navigatieblok met het ID van een pagina toont stil niets.
Het rapport meldt beide (`missing`, `wrong_type`).

Media-, term- en post-ID's zijn getallen; `replace` werkt alleen op tekst en kan ze
dus niet omzetten. En vervang nooit een los getal met `replace` — "112" komt
ook in afmetingen voor.

Wat buiten de markup valt komt niet mee: de CSS van het thema, filters in
`functions.php`, het palet, de contentbreedte. Het rapport noemt de eigen
CSS-klassen en de paletwaarden juist daarom.

**Na het invoegen: open de post één keer in de editor.** Of Kadence de blokken
geldig vindt kan alleen de JavaScript van het blok zeggen; de server kan dat
niet toetsen.

Werkwijze van de ene omgeving naar de andere: `get-raw-markup` op de bron →
`prepare-import` op het doel, met `replace`/`media_map`/`post_map` →
`insert-blocks` met het token → editor openen en `isValid` nalopen.

Hangt er iets aan elkaar via post-ID's — een header met navigaties, een mega
menu met kleinere menu's erin, een footer met een vector — maak dan eerst de
doelposts aan op de doelsite met **`create-entity`** (leeg, met de volledige set
instellingen van Kadence), noteer hun ID's, en zet over van onder naar boven:
eerst wat nergens naar verwijst. Daarna de instellingen met `set-entity-meta`,
afgelezen met `get-post-meta` op de bron. Publiceer een element pas als de inhoud
erin staat: een element op `replace_footer` vervangt de footer meteen.

## Ruimte in een Sectie, en andere stille no-ops

Kadence gebruikt `gutter` en `rowGap` op een Sectie alleen als
`gutterVariable` en `rowGapVariable` op dezelfde plek `"custom"` staan; anders
geldt een preset (1rem). `gutter` is bovendien de ruimte NAAST elkaar: in een
verticale Sectie is de ruimte tussen de kinderen `rowGap`. `validate-write`
blokkeert een getal zonder zijn *Variable, en `generate-section` zet het zelf
goed (zie `notes` in het antwoord).

Vier dingen die werken maar anders dan de naam belooft, gemeld door
`validate-write` of `set-entity-meta`:

| | wat er gebeurt | in plaats daarvan |
|---|---|---|
| `flexBasis` op een Sectie | geldt voor alle kinderen van die Sectie | `maxWidth` op het kind (wordt `flex: 0 1 …`) |
| `background: "transparent"` op een knop | voorkant doorzichtig, editor toont de themaknop | `rgba(0,0,0,0)` |
| `borderWidth` op een Sectie | het oude randpad: breedte zonder de kleur uit `borderStyle` | alleen `borderStyle` met breedte, `borderWidth` leeg |
| `_kad_navigation_spacing` | standaard in em (`spacingUnit`), dus 20 wordt 340 px | `spacingUnit` op `px` meezetten |

Een navigatie heeft in een pagina of header de vorm van een verwijzing
(`kadence/navigation` met `id`, zelfsluitend); in de navigatiepost zelf staan de
`kadence/navigation-link`-blokken erin. Allebei kan `generate-section` bouwen,
net als `kadence/vector`.

## Via de editor bouwen (`wp.data`)

Voor wat de generator niet mag bouwen, of voor een wijziging die de opgeslagen
markup verandert, kun je de editor zelf laten werken: Playwright in de
blokeditor, en daar `wp.data` — de gegevenslaag van de editor. `createBlock`,
`replaceBlock` en `updateBlockAttributes` op `core/block-editor`; `editPost` en
`savePost` op `core/editor`. De `save()` van elk blok draait dan mee, dus de
markup is per definitie die van Kadence.

Het recept dat werkt:

1. blokken maken of wijzigen
2. elk nieuw blok één keer `selectBlock()`-en en wachten tot het een
   `uniqueID` heeft — Kadence zet die pas als het blok gerenderd is, en als
   niet-persistente wijziging
3. `editPost({ content: wp.blocks.serialize( getBlocks() ) })` — zonder deze stap
   schrijft `savePost()` de oude inhoud weg, zonder de uniqueIDs (`…-idnotset`)
4. `savePost()`
5. nalezen met `inspect-post` vanuit de database, niet in de editor

Waarom dit niet de standaard is: er is **geen vangnet**. Geen toets op waarden,
geen token, geen teruglees-vergelijking; en `savePost()` schrijft de héle post,
inclusief wat de editor bij het openen zelf aanpaste en wat een ander intussen
wijzigde. Het vraagt ook een ingelogde browser. Gebruik het dus alleen waar de
MCP het niet kan, en lees daarna na met de MCP.

| | MCP | `wp.data` |
|---|---|---|
| lezen, meten, controleren | ✓ | |
| attributen die alleen in het blokcommentaar staan | ✓ | |
| blokken die de generator niet mag bouwen (tabs) | | ✓ — of `prepare-import` |
| attributen waar markup uit volgt | `replace-block` als het profiel het kent | ✓ |

Twee dingen die je tegenkomt:

- **Een "pagina verlaten?"-melding** bij navigeren blokkeert een script dat nog
  draait. Controleer daarna in de database of het opslaan gelukt is.
- **Een post die net geopend is heet al "gewijzigd"** als er blokken in staan
  die de editor nooit zelf heeft opgeslagen. Kadence migreert dan attributen bij
  het laden — gemeten bij Geavanceerde tekst: `markBorder` → `markBorderStyles`.
  Onschuldig; één keer opslaan in de editor en het is weg.

## Carrousel: Advanced Slider of Post Grid

| | Advanced Slider | Post Grid (layout carousel) |
|---|---|---|
| inhoud | vast, per slide | dynamisch uit een posttype |
| niet rondlopen | `loopType: none`, native | loopt áltijd rond; alleen met een `render_block`-filter dat `data-slider-loop-type` zet |
| per pagina schuiven | attribuut `slidesScroll` wordt door de render genegeerd — filter dat `data-slider-scroll` op een getal ≠ 1 zet | `slidesScroll: all`, native |
| eigen kaartopbouw | ja, elke slide is een container | alleen via de hooks `kadence_blocks_post_loop_*` |
| foto achter de kaart | `backgroundImg` + overlay op de slide | niet native |

Details bij de slider die tijd kosten:

- De padding van de slider zit op `.kb-advanced-slide-inner-wrap` (standaard
  20/48). Een padding van 0 geldt op de voorkant; de editor negeert hem en toont
  de standaard.
- `arrowPosition: outside-top-right` zet de pijlen boven de slides;
  `arrowMargin` is een object per breakpoint (`[{desk:[…],tablet:[…],mobile:[…]}]`).
- De overlay is absoluut met `inset: 0`, maar de wrap is niet gepositioneerd —
  hij rekent vanaf de `li` en valt over een rand op de wrap heen. Eén regel CSS:
  `position: relative` op de wrap.
- De overlay-div bestaat alleen als er een overlaykleur is, en `align` zet een
  klasse op de wrap. Beide staan in de opgeslagen markup: wijzigen via
  `replace-block` of de editor, niet alleen als attribuut.
- Een slider in een tab start pas als de tab zichtbaar wordt, en logt eenmalig
  `[splide] Already mounted!` — onschuldig.
- Kadence vult het `aria-label` van slides niet in: er staat letterlijk
  `%1$s of %2$s`. Geldt ook voor de Post Grid-carousel.

## Een knop die meer moet dan Kadence kan

Zet in het blok wat het blok kan — tekst, icoon, typografie, kleuren, padding,
radius, marge — en in CSS alleen de rest, met een eigen klasse op de knop
(`singlebtn` zet `className` wél op het element). Wat je daarbij tegenkomt:

- Het icoon heeft **geen eigen achtergrond**. Een icoon in een eigen vlak naast
  het label is CSS.
- Kadence geeft de knop `overflow: hidden`. Iets buiten de knop tekenen vraagt
  `overflow: visible`.
- De knop heeft een `::before` (absoluut, z-index -1, opacity 0) die alleen bij
  `backgroundHoverType: gradient` een kleur krijgt. **In de editor** krijgt hij
  via `.kt-button.kb-btn-global-fill::before` de hoverkleur van de themaknop en
  fadet hij in op hover. Gebruik je hem niet, zet hem dan uit
  (`content: none`), anders zie je in de editor een vreemde kleur.
- **Twee lagen van dezelfde afgeronde vorm** — een achtergrond met een bovenlaag,
  ook een achtergrondkleur met een verloop erover in één element — geven op de
  hoeken een randje van de onderste kleur. Eén verloop per vlak lost het op.
- Kadence' knop-CSS is (0,3,0) en laadt na de stylesheet van het thema. Een
  eigen regel moet (0,4,0) zijn.
- Kadence wisselt de icoonkleur op `:hover` én `:focus`; laat een eigen
  hovereffect op dezelfde twee reageren, anders loopt het na een klik uit de pas.
- `inheritStyles: inherit` neemt de knopstijl van het thema over. Voor een eigen
  knop: laten staan op `fill`.

## Editor en voorkant zijn twee verschillende DOM's

- **Andere klassen.** De knop is in de editor `.kt-button` in
  `.kb-btns-outer-wrap`, met `.kt-btn-svg-icon` en `.kt-button-text`; op de
  voorkant `.kb-button` in `.kb-buttons-wrap`, met `.kb-svg-icon-wrap` en
  `.kt-btn-inner-text`. Schrijf CSS voor allebei met `:is()`; `:is()` neemt de
  hoogste specificiteit van zijn lijst.
- **Iconen worden anders uitgelijnd.** De editor zet een niet-vierkante SVG
  links in een vierkant van 1em (`xMinYMin`), de voorkant centreert hem. Een
  breedte naar verhouding (`width: calc(1em * 20 / 24)`) trekt ze gelijk;
  `width: auto` werkt op een inline SVG niet.
- **Een server-render in de editor draait geen voorkant-JavaScript.** Een blok
  dat in de editor via `ServerSideRender` getoond wordt, krijgt zijn
  `viewScript` niet. Alles wat dat script berekent — een clip-path, een schaal —
  moet daarom al in de markup kloppen, anders ziet de editor iets anders (en een
  bezoeker zonder JavaScript ook).
- **Een CSS-regel in een Post Grid-footer** (`.kt-blocks-post-footer svg { top:
  .125em }`) schuift elke SVG daarin omlaag. Meet een knop op de plek waar hij
  echt staat.

## Kleur zit in een klasse, niet alleen in een attribuut

Een Kadence-tekstblok haalt zijn kleur niet uit `color`, maar uit een klasse in
de markup. `color: "palette9"` alleen levert zwarte tekst op. Je hebt er
`colorClass: "theme-palette9"` bij nodig, en die moet als klasse in de HTML
staan:

```html
<h1 class="kt-adv-heading… wp-block-kadence-advancedheading has-theme-palette-9-color has-text-color">
```

Let op het streepje dat er vóór het cijfer bij komt: `theme-palette9` wordt
`has-theme-palette-9-color`. Hetzelfde geldt voor `backgroundColorClass` →
`has-…-background-color has-background`.

`generate-section` doet dit sinds 1.6.1 zelf: geef `color` én `colorClass` mee en
de klassen komen vanzelf in de markup. Schrijf je met `set-attributes` op een
bestaand blok, zet dan altijd allebei — één van de twee is een halve wijziging
die er in de blokdata correct uitziet.

Dit is precies het soort fout dat alleen in de browser zichtbaar is. Meet het:
Playwright kan `getComputedStyle` uitvoeren, en dan zie je `rgb(10,10,10)` waar
je wit verwachtte.

## Een highlight vraagt om `mark.kt-highlight`

`markBG`, `markColor` en `markPadding` maken een gekleurd kadertje om een stukje
tekst — handig voor een label boven een kop, want een tekstblok neemt zelf de
volle breedte en een highlight past om de tekst heen.

De CSS die Kadence genereert hangt aan `mark.kt-highlight`
(`class-kadence-blocks-advanced-heading-block.php:310`). Een kale `<mark>` krijgt
dus niets. Schrijf `<mark class="kt-highlight">Over ons</mark>`.

## Beelden staan op twee plekken, en dat is een echte keuze

Een afbeelding kan een eigen blok zijn (`kadence/image`) of de achtergrond van
een Row Layout (`bgImg`, meestal met `overlayGradient` eroverheen zodat er tekst
overheen kan). Kadence biedt allebei.

Welke van de twee een site gebruikt is geen detail: bouw je een image-blok waar
de site achtergronden gebruikt, dan ziet het er goed uit en klopt het toch niet.
Kijk dus eerst met `inspect-post` naar een bestaande pagina hoe het daar is
gedaan, voordat je een ontwerp vertaalt.

## Blokken weghalen

`remove-blocks` haalt blokken uit een post, op elke diepte — ook één knop uit
een knoppenrij. Twee stappen, zoals `set-text`: zonder token krijg je te zien
wat er zou verdwijnen, met token gebeurt het.

Lees dat voorstel echt. **Een container neemt alles mee wat eronder staat**: een
rij weghalen verwijdert zijn kolommen en hun inhoud. Het antwoord geeft daarom
`removing` (elk `uniqueID` dat verdwijnt, niet alleen het blok dat je noemde),
`blocks` (hoeveel per type) en `text` (de tekst die weg zou gaan). Zou de pagina
er leeg van worden, dan staat dat er expliciet bij.

Staat een van de opgegeven `uniqueID`s niet in de post, dan wordt er niets
verwijderd — een verzoek dat half klopt wordt niet half uitgevoerd.

## Een ontwerp uit Figma nabouwen

Dit is uitgevoerd op 11-09-2026: een frame van vier secties, nagebouwd en daarna
op zeventien punten nagemeten. Dat leverde deze werkwijze op.

**Lees eerst hoe de site bouwt, niet alleen hoe het ontwerp eruitziet.** Een
beeld in een ontwerp kan op deze stack een `bgImg` op de rij zijn in plaats van
een image-blok; een rij gekleurde balken kan een accordeon zijn. Kijk met
`inspect-post` naar een bestaande pagina voordat je vertaalt.

**Leg de kleuren tegen het palet.** `get-global-styles` geeft de hexwaarden.
Vallen ze samen, gebruik dan `palette1` en niet de hex — dan blijft de pagina
meebewegen als het palet verandert.

**Vertaaltabel die bleek te kloppen:**

| Figma | Kadence |
|---|---|
| `px-[80px]` op een frame van 1440 | `inheritMaxWidth: true` |
| `pt-[176px] pb-[72px]` | `padding: [176,"",72,""]` |
| `text-[72px]` | `fontSize: [72,"",mobiel]` |
| `leading-[0.95]` | `fontHeight: [0.95,"",""]` |
| `w-[880px]` | `maxWidth: [880,"",""]` |
| `tracking-[1px]` | `letterSpacing: 1` |
| een strak kadertje om tekst | `markBG` + `<mark class="kt-highlight">` |

**Zet `columns` expliciet.** Laat je het weg bij één kolom, dan gaat de rij uit
van twee en krijgt je kolom de halve breedte. Dat kost je een ronde.

**Zet `margin` expliciet op 0 waar het ontwerp geen marge toont.** Het thema geeft
een `h1` ruim honderd pixels bovenmarge, en die wint als je niets zegt.

### Meet het resultaat, kijk er niet naar

Playwright kan JavaScript in de pagina draaien. `getComputedStyle` en
`getBoundingClientRect` geven precies wat DevTools laat zien, maar dan
vergelijkbaar met de waarden uit `get_design_context`.

Dat is geen luxe. Twee fouten uit deze ronde waren met het oog niet te
verklaren: tekst die zwart bleef terwijl het attribuut klopte (de kleurklasse
ontbrak in de markup), en een kolom die de halve breedte kreeg. En twee keer
sloeg het beeld vals alarm — een kop leek achter het menu te vallen terwijl de
meting zei dat er negentien pixels ruimte was.

**Twee valkuilen bij het meten zelf:**

1. **Meet op de juiste laag.** Kadence zet de rijpadding op
   `.kt-row-column-wrap` en de kolomachtergrond op `.kt-inside-inner-col`, niet
   op de buitenste div. Meet je die, dan lees je nul terwijl alles goed staat.
   Loop desnoods met `parentElement` naar buiten en toon elke laag.
2. **Een `fullPage`-screenshot liegt over een zwevende header.** Playwright
   scrollt door de pagina en een `position: fixed` element schuift mee. Voor
   alles rond de header: viewport-screenshot op scrollpositie 0, of meten.

Nog vier, uit het meten van beweging en hover:

3. **Een geforceerde stand is geen hover.** Zet je een eindstand direct (een
   custom property, een klasse), dan draaien de `:hover`-regels van Kadence
   niet mee — en juist daar zat de fout. Meet ook met een echte muis-hover.
4. **Parkeer de muis.** Na een scroll staat de muis ineens boven een ander
   element, dat dan in hoverstand staat. Hover eerst iets neutraals.
5. **Meet beweging per frame.** Een `requestAnimationFrame`-lus die de
   waarde en de stand per frame opslaat laat zien wat een screenshot mist: een
   fade die op een bug lijkt, een kleurwissel die een frame te laat komt.
6. **Een randje zie je alleen in pixels.** Vergelijk hoekpixels van twee
   screenshots; een verschil in de tekst van minder dan een pixel is ruis van
   een fractionele positie, geen fout. Let op dat Playwright zijn eigen
   inspectie-overlay in een screenshot kan zetten.

De lus is dus: bouwen, meten tegen de Figma-waarden, corrigeren met
`style-blocks`, opnieuw meten. Niet: bouwen, screenshot, turen.

## Een groene toets is geen bewijs dat de waarde bestaat

`validate-write` toetst drie dingen: bestaat het attribuut, past de waarde bij
het gedeclareerde type, en zit hij vast in de markup. Wat hij **niet** kan
toetsen is of een tekst een waarde is die Kadence kent — want dat staat voor veel
attributen niet in `block.json`.

Zo ging het mis met `colLayout` op een Row Layout. Het schema zegt alleen
`type: string`, dus de verzonnen waarde `"thirds"` kwam er zonder bezwaar door.
Op de voorkant viel het niet op, want daar bepaalt de klasse `kt-has-3-columns`
de breedtes. In de **editor** wel: die kiest zijn weergave op `colLayout` en zet
bij een onbekende naam alle kolommen onder elkaar.

De geldige namen, afgelezen uit `dist/blocks-rowlayout.js`:

| kolommen | `colLayout` |
|---|---|
| 1 | `equal` |
| 2 | `equal`, `left-golden`, `right-golden` |
| 3 | `equal`, `left-half`, `right-half`, `center-half`, `center-wide`, `center-exwide` |
| 4 | `equal`, `left-forty`, `right-forty` |
| 5 en 6 | `equal` |

`validate-write` blokkeert sinds 1.7.2 een onbekende combinatie, en
`describe-block` zet bij elk stringattribuut zonder enum
`values_not_validated: true` met een notitie erbij.

**De regel die hieronder ligt:** bij zo'n attribuut verzin je geen waarde. Lees
hem af van een bestaand blok met `get-raw-markup`, of uit de broncode van
Kadence. Zwijgen van de toets is geen goedkeuring — het betekent dat er niets te
toetsen viel.

## Query Loops zitten in drie lagen

Een Query Loop is niet één blok maar een keten van verwijzingen:

```
de pagina         kadence/query {id:1456}        zelfsluitend, verwijst alleen
  query-post 1456   de hele loop-layout          filters, sortering, paginering
      kadence/query-card {id:232}                verwijst weer door
        card-post 232   de kaartopmaak
  + post meta op 1456   _kad_query_query         WAT er opgehaald wordt
```

Daaruit volgen drie dingen.

**Een bestaande query hergebruiken is één blok.** `kadence/query` met het `id`
van de query-post, zelfsluitend. Dat is waarom dezelfde loop op meerdere
pagina's kan staan.

**Alle query-onderdelen zijn zelfsluitend** — `query-card`, `query-filter`,
`query-filter-search`, `query-filter-reset`, `query-result-count`, `query-sort`
en `query-pagination`. Ze dragen geen eigen markup, dus `generate-section` kan
ze bouwen.

**Wat de query ophaalt staat NIET in blokattributen.** `postType`, `perPage`,
`orderBy` en de facetten staan in post meta op de query-post, onder
`_kad_query_query` en `_kad_query_facets`. Lees dat met `get-post-meta` en schrijf het met `set-query`; de facetten werk je
bij met `sync-query-facets`. Let op: post meta heeft geen `block.json`, dus daar
is niets om een waarde tegen te toetsen. Een typefout in `postType` levert een
lege loop op zonder enige melding — `set-query` weigert daarom onbekende sleutels
en draait de query proef voordat hij schrijft.

Kolommen binnen een query dragen `inQueryBlock: true` en meestal
`noCustomDefaults: true`.

## Een blok zonder inhoud is zelfsluitend

`kadence/query` laat zien dat dit geen eigenschap van een bloktype is maar van
het geval: in een pagina verwijst hij alleen en is hij leeg, in zijn eigen
query-post draagt hij de hele layout. Eén vorm, twee gedaanten.

`generate-section` volgt daarin WordPress zelf: staat er niets tussen de tags en
heeft het blok geen eigen wrapper, dan wordt het `/-->`.

## De uitlijning van een Sectie: waar je het niet zoekt

Een Sectie is een flex-container, en welk attribuut welke CSS stuurt hangt af
van `direction`. Dat is contra-intuïtief en kostte op 12-09-2026 drie rondes.

**Bij `direction: vertical`:**

| je wilt | attribuut | wordt |
|---|---|---|
| de inhoud verticaal uitlijnen | `verticalAlignment` | `justify-content` |
| de inhoud op volle breedte | `justifyContent: ["stretch","",""]` | `align-items` |

Ja: **`justifyContent` stuurt `align-items`.** Zo staat het in de render
(`class-kadence-blocks-column-block.php:58, 320`). Bij een verticale kolom is de
hoofdas verticaal, dus `verticalAlignment` gaat over de hoogte en blijft de
breedte op `flex-start` staan — waardoor kinderen hun natuurlijke breedte
krijgen in plaats van de volle.

De toegestane waarden voor `verticalAlignment` zijn `top`, `middle`, `bottom`,
`stretch`, `space-between`, `space-around` en `space-evenly`. De werkbalk biedt
de eerste vier; het paneel *Vertical Alignment* bij een verticale Sectie ook de
laatste drie. `space-between` is de nette manier om een knop onderaan een
kaart te krijgen: tekst bovenaan, knop onderaan, zonder `flexGrow` (dat werkt
hier niet, want de binnenste laag is bij een verticale Sectie zonder
uitlijning geen flexcontainer). `describe-block` toont de lijst als
`known_values`, en `validate-write` toetst erop.

**En let op wat hier NIET waar is.** Een Sectie draagt klassen als
`kb-section-dir-horizontal` in zijn markup, en het ligt voor de hand te denken
dat je die moet meewijzigen. Dat hoeft niet: gemeten in beide richtingen stuurt
het **attribuut** de weergave, en een achtergebleven klasse verandert daar
niets aan. `set-attributes` en `style-blocks` kunnen `direction` dus gewoon
wijzigen op een bestaand blok.

## Grenzen

- Achttien abilities schrijven, alle met token of `expect_modified`. De
  overige negentien zijn alleen-lezen. Ga niet af op de naam: `generate-section`,
  `prepare-import` en `preview-write` klinken als schrijvers maar slaan niets
  op, terwijl
  `sync-query-facets` en `set-card-layout` dat wél doen.
- `inspect-post` kapt af op `max_blocks` (standaard 200, maximaal 400) en meldt
  dat in `truncated` en `status`.
- Grote attribuutwaarden worden samengevat als "array met N elementen". Wil je
  de inhoud, noem het attribuut in `full_attributes`.
- Elk antwoord heeft een `status`-regel. Is een resultaat leeg, lees die regel
  vóór je concludeert dat er niets is — hij zegt of er niets ís of dat je niet
  kon kijken.
- Concepten en niet-gepubliceerde Kadence-objecten vereisen extra rechten op de
  rol. Krijg je `kadence_mcp_post_forbidden`, meld dat aan de gebruiker in
  plaats van een omweg te zoeken.
- `find-post` gaat door WP_Query en respecteert dus plugins die inhoud
  afschermen; `inspect-post` gaat via `get_post` en doet dat niet. Een post die
  `find-post` niet vindt kan `inspect-post` soms wél lezen — de statusregel van
  `inspect-post` meldt dat. Verzwijg dat verschil niet tegen de gebruiker.
- `get-post-meta` geeft alleen sleutels op `_kad_`, `_kt_` en `kadence_` terug.
  Het aantal weggelaten sleutels staat in `withheld`; vraag daar niet omheen.
- `find-usages` scant een begrensd aantal posts. Staat er in de statusregel dat
  de scan begrensd was, meld dat dan mee in plaats van "nergens gebruikt" te
  zeggen.
