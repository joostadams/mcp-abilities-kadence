# Ontwerpbesluiten

Waarom deze plugin is zoals hij is. Voor wie hem later aanpast.

## Schrijven kan, en het is omheind

> **Bijgewerkt 11-09-2026.** Dit hoofdstuk beschreef een plugin die niet kon
> schrijven. Dat klopt sinds 1.0.0 niet meer: er zijn zes schrijfabilities en
> evenzoveel `wp_update_post`-aanroepen. De tekst hieronder is blijven staan als
> beschrijving van hoe het schrijven is omheind — want dat is wat er van het
> oorspronkelijke besluit over is.
>
> **Opnieuw bijgewerkt.** Het waren er zes; het zijn er inmiddels achttien, en
> ze raken niet langer alleen `post_content`. De bewering dat opties nergens
> worden aangeraakt is sinds `set-global-typography` en `set-site-css` onjuist.

Er zijn drie soorten schrijvers, en het verschil zit in wat er terug te draaien
valt.

**Post content.** De meeste. Ze raken `post_content` van de post die je noemt en
WordPress maakt er een revisie van, dus terugdraaien kan via het
revisieoverzicht.

**Nieuwe posts.** `create-page`, `create-query`, `create-query-card` en
`create-entity`. Die maken iets aan in plaats van iets te wijzigen; terugdraaien
is weggooien. `create-entity` schrijft bij een navigatie, header of element de
hele set geregistreerde instellingen mee, omdat `set-entity-meta` alleen
sleutels aanneemt die er al staan.

**Post meta.** `set-entity-meta`, `set-card-layout`, `set-query` en
`sync-query-facets`. WordPress bewaart post meta niet in revisies, dus de oude
waarde in het veld `before` van het antwoord is de enige weg terug. Dat staat ook
in elke statusregel.

**Site-breed.** `set-global-typography` schrijft theme mods, `set-site-css`
schrijft de Extra CSS van de Customizer. Die raken elke pagina, en theme mods
kennen evenmin revisies. Ze zitten daarom achter een extra poort:
`edit_theme_options` bovenop `kadence_mcp_write`, en de typografie accepteert
alleen een vaste lijst sleutels — theme mods bevatten ook de header, de kleuren
en de layout, en daar hoort een typefout niet in te kunnen landen.

Alle achttien vragen de capability `kadence_mcp_write` (bij installatie aan
niemand gegeven), het bijbehorende WordPress-recht, en een token uit een
voorafgaande controlestap. Bestanden en caches worden nergens aangeraakt.

De oorspronkelijke tekst, die nog steeds geldt voor de achttien leestools:

Er is geen schrijfability, en de plugin heeft geen enkele plek waar hij een
post, optie, bestand of cache aanraakt. "Alleen lezen" is hier dus geen
afspraak maar een controleerbaar feit: `grep` op `update_option`,
`wp_update_post` en `wp_insert_post` levert niets op.

Dat is bewust. Een generieke blok-editor-plugin die schrijven meebrengt
installeer je niet op een site die volgende week live gaat, ook niet met de
afspraak dat je die tools niet aanroept — ze bestaan dan.

## De grendel zit op uitvoeringstijd, niet op registratietijd

`Kadence_MCP_Ability::check_permissions()` stelt de vraag "staat deze tool
aan?" opnieuw bij elke aanroep. Een vlag die bij registratie wordt gezet ligt
vast zolang het request duurt.

Dat is overgenomen uit Gravity Forms, waar de reden expliciet in de code staat:
de poort moet op elk oppervlak gelden — de REST-run-route van de Abilities API,
de gedeelde MCP-server en de eigen server — en niet alleen op de plek waar de
ability geregistreerd werd.

De klasse faalt daarbij dicht: ontbreekt `Kadence_MCP_Settings`, dan geldt de
tool als uit. Een halve bootstrap mag de poort nooit openzetten.

## Twee lagen, en ze zijn niet inwisselbaar

De toggle bepaalt of een ability wordt aangeboden. De capability bepaalt of dit
account hem mag uitvoeren.

Een uitgezette toggle die je weer aanzet geeft meteen toegang; een ontbrekende
capability niet. Wie wil weten wat een MCP-account écht kan, kijkt dus naar de
rol en niet naar dit scherm.

## Geen verruiming van het invoerschema

Er stond hier eerder een verruiming van `type` naar `["object","array","null"]`
voor schema's zonder verplichte velden, overgenomen uit een andere add-on. Die
is verwijderd en mag niet terugkomen.

De MCP Adapter vergelijkt strikt met de string `'object'`
(`SchemaTransformer.php:62`). Een array valt door naar `wrap_in_object()` en het
gepubliceerde schema wordt `{type:object, properties:{input:…},
required:["input"]}`. De client stuurt dan `{"search":"row"}` plat, de adapter
pelt `$arguments['input']` eraf, vindt niets, en de ability draait met lege
invoer — zonder foutmelding, met een volledige ongefilterde lijst als antwoord.
De toegevoegde `"null"` maakte dat onzichtbaar: zonder verruiming zou de
validatie netjes `ability_invalid_input` hebben gegeven.

De juiste oplossing voor "de client stuurt niets" is een `default` op het
roottschema, zoals Gravity Forms het doet.

## De capabilities blijven bij deactivering staan

`WP_Role::remove_cap()` schrijft persistent weg, terwijl de activering alleen
`kadence_mcp_view` en `kadence_mcp_manage` teruggeeft, en alleen aan
administrator. Een eigen rol `mcp-agent` — precies wat het instellingenscherm
aanraadt — zou na één deactiveer-activeercyclus leeg zijn, en elke tool zou
zonder melding permission denied geven.

Opruimen gebeurt daarom in `uninstall.php`, niet in een deactivation hook.
Gravity Forms raakt bij deactivering evenmin een capability aan. Let op dat
WordPress bij uninstall alléén dat bestand laadt, dus de capability-klasse wordt
daar apart ingeladen.

## Een eigen server, niet de gedeelde

Op de gedeelde standaardserver van de MCP Adapter staan abilities achter
generieke discovery-tools. Een assistent moet eerst vragen wát er is en daarna
via een omweg uitvoeren. Op een eigen server staat elke ingeschakelde ability
als losse tool in de lijst.

Gravity Forms komt tot dezelfde keuze en zet dedicated als standaard, met
dezelfde motivering. De gedeelde stand blijft beschikbaar voor wie bewust één
verbinding voor de hele site wil.

`meta.mcp.public` volgt die keuze: op de eigen server wordt hij `false`, want
een ability die daar al als losse tool staat hoef je niet óók nog op de
gedeelde server te publiceren — dan verschijnt dezelfde tool twee keer.

## Een ability zonder invoerschema is onbruikbaar

`WP_Ability::execute()` weigert élke invoer wanneer er geen schema is, ook het
lege object dat MCP-clients altijd meesturen. `Kadence_MCP_Registry` geeft een
schemaloze ability daarom een expliciet leeg objectschema.

Dat is geen theorie. De ACF-abilities op deze site lopen er live op vast:

```
Ability "mcp-adapter/discover-abilities" definieert geen invoerschema
vereist om de invoer te valideren.
```

Let op dat het type de STRING `'object'` moet blijven. Er stond hier eerder een
verruiming naar `["object","array","null"]`; die is verwijderd om de reden die
hierboven bij "Geen verruiming van het invoerschema" staat, en dit hoofdstuk
beschreef hem daarna nog als huidig gedrag. Dat klopte niet.

## Foutteksten worden geschoond

De Abilities API vangt een exception uit een callback af en geeft het bericht
letterlijk terug, inclusief absolute serverpaden, en dat bericht gaat door naar
de MCP-client. `Kadence_MCP_Ability::do_execute()` logt het echte bericht en
geeft een algemeen bericht terug. Een `WP_Error` die een handler zelf
teruggeeft is een bewuste melding en gaat ongemoeid door.

## Uitvoergrootte is een ontwerpeis, geen comfort

`kadence/rowlayout` heeft 170 attributen en zijn `block.json` is 17,6 KB. Dat
past niet in één MCP-antwoord.

Daarom pagineert `describe-block` standaard op 40 attributen, en worden
standaardwaarden die zelf een grote structuur zijn samengevat in plaats van
uitgeschreven. Gemeten: hetzelfde blok komt er als 3,0 KB uit in plaats van
17,6 KB.

Een expliciete lijst via `attributes` wordt niet gepagineerd — dat is per
definitie de hele vraag, en er minder van teruggeven dan gevraagd is verwarrend.

## Herkomst komt van schijf, bestaan uit het register

Een geregistreerd `WP_Block_Type` draagt niet met zich mee uit welke plugin hij
komt. De herkomst wordt daarom afgeleid uit de `block.json`-bestanden onder
`dist/blocks/` van beide plugins.

Het blokkenregister blijft de bron van waarheid voor wát er bestaat. Levert de
bestandsscan geen treffer op, dan krijgt het blok `onbekend` in plaats van een
gok.

`attribute_count` telt de twee attributen die WordPress zelf aan elk blok
toevoegt — `lock` en `metadata` — niet mee, want anders meldt
`kadence/rowlayout` 172 terwijl zijn `block.json` er 170 heeft. De vergelijking
gaat op identieke definitie en niet op sleutel: `kadence/repeatertemplate`
definieert `lock` zelf, met een eigen default, en houdt hem daarom.

`kadence/videopopup` staat in beide plugins. De eerste treffer wint, en dat is
de gratis plugin — dezelfde die WordPress ook als eerste registreert.

## Posttypes worden ontdekt, niet opgesomd

`Kadence_MCP_Inventory::get_post_types()` filtert op het prefix `kadence_` in
plaats van een vaste lijst aan te houden. Een nieuwe Kadence-versie of een
ander Kadence-product verschijnt daarmee vanzelf, zonder codewijziging.

Op het moment van schrijven zijn dat `kadence_header`, `kadence_navigation`,
`kadence_element`, `kadence_form`, `kadence_lottie`, `kadence_vector`,
`kadence_adv_page`, `kadence_query` en `kadence_query_card` — maar de code gaat
daar niet van uit.

Op één uitzondering na: Kadence Blocks Pro registreert zijn iconenbibliotheek
als **`kb_icon`**, zonder prefix. Die staat in `EXTRA_POST_TYPES`. Een
prefixfilter alleen zou hem missen, en dan bestaat hij voor de agent niet.

## Vier blokken die er niet lijken te zijn

Kadence registreert `kadence/pane`, `kadence/tab`, `kadence/countdown-inner` en
`kadence/countdown-timer` uitsluitend in JavaScript. Ze staan dus niet in
`WP_Block_Type_Registry`, terwijl `inspect-post` ze wél in de blokkenboom laat
zien — een accordeon is alledaags. `describe-block` antwoordde daarop "bestaat
niet op deze site", en dat was onjuist.

De terugval leest nu de `block.json` op schijf en markeert het resultaat met
`registration: "editor-only"`, zodat het verschil zichtbaar blijft. Pas als een
blok ook daar niet staat volgt een `WP_Error`.

Eén van die vier, `accordion/pane/block.json`, mist bovendien het veld `name` —
als enige in de hele stack. De naam wordt daar uit de mapnaam afgeleid en
gemarkeerd met `name_source: "directory"`. Dat is geen algemene regel: elk ander
bestand draagt zijn naam gewoon zelf, en blind afleiden zou van
`header/children/row` ten onrechte `kadence/row` maken in plaats van
`kadence/header-row`.

## Leesrecht op een post is iets anders dan recht op de tool

`kadence_mcp_view` zegt dat dit account de tool mag gebruiken. Of het déze post
mag lezen is een aparte vraag, en `inspect-post` en `list-entities` stellen hem
allebei met `current_user_can( 'read_post', $id )`.

Zonder die controle zou een MCP-account met alleen `kadence_mcp_view` de inhoud
van concepten en privéposts kunnen uitlezen.

## Wat hier bewust niet staat

**De plugin van bjornfix is niet geforkt.** `mcp-abilities-block-editor` biedt
generieke `parse_blocks`/`serialize_blocks` en blok-CRUD die ook op
Kadence-blokken werkt, want die is formaatonafhankelijk. Forken zou betekenen
dat je 67 abilities gaat onderhouden en upstream-fixes misloopt. De auteur
gebruikt zelf het patroon basis-stack plus losse add-ons, met 22 add-ons naast
elkaar; deze plugin volgt dat patroon.

**Er is geen cache-flush.** Kadence Blocks genereert zijn CSS per request via
`wp_add_inline_style` op `wp_enqueue_scripts` prioriteit 180. Er is geen
bestandscache om leeg te maken. Komt er ooit schrijven bij, dan is dit het
eerste dat opnieuw nagekeken moet worden.


## Kennen en bouwen zijn twee dingen (1.21.0)

Tot 1.21.0 betekende "er is een profiel" ook "de generator mag het bouwen".
Daardoor kon je een blok niet deels kennen: wie de waardenlijsten van tabs
wilde vastleggen, maakte tabs daarmee bouwbaar — met een wrapper die niet is
waargenomen. `bouwbaar: false` scheidt die twee. `bekend()` zegt of er gebouwd
mag worden, `heeft_profiel()` of er iets bekend is.

Afgeleide markup zit niet altijd op het buitenste element. De slide zet zijn
uitlijning op de tweede div en heeft een overlay-element dat alleen bestaat als
er een overlaykleur is. Daarvoor zijn twee regelsoorten bij gekomen: `reeks`
(klassen uit een array-attribuut, per breakpoint) en `element` (een stuk HTML
dat er wel of niet staat). Regels met `overal` worden in de hele eigen
innerHTML getoetst, niet alleen op het eerste class-attribuut.

## Import zonder tweede schrijfroute (1.21.0)

`prepare-import` is bewust een LEES-ability. Hij geeft hetzelfde token uit als
`generate-section`, over zijn eigen opgeschoonde markup, en het schrijven
blijft bij `insert-blocks` met al zijn controles. Een aparte import-schrijver
zou een tweede plek zijn waar dezelfde grendels moeten kloppen.

Verwijzingen worden gemeld en nooit geraden. Een media-ID op een andere site is
een ander bestand; een term-ID ook. Omzetten gaat alleen met een kaart die de
gebruiker opgeeft (`replace`, `media_map`, `term_map`, en sinds 1.22.0
`post_map`). Welke blokken met hun `id` naar een post verwijzen staat in één
tabel (`post_verwijzing()`), afgelezen uit de render van Kadence, die bij elk van
die blokken het posttype controleert. Andere blokken gebruiken `id` voor iets
anders — een bijlage, een volgnummer — en blijven erbuiten. Een onopgeloste
verwijzing geeft geen token zonder `accept_warnings`, omdat "het icoon bestaat
hier niet" een beslissing is en geen detail.

Wat de import niet kan: de geldigheid in de editor bewijzen. Die toets zit in de
JavaScript van elk blok. De skill schrijft daarom voor de post na het invoegen
in de editor te openen.
