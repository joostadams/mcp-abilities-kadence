=== MCP Abilities - Kadence ===
Contributors: joostadams
Tags: mcp, kadence, abilities, ai
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.19.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Kadence Blocks, Kadence Blocks Pro, Kadence Pro en het Kadence-thema uitlezen en gericht wijzigen via MCP.

== Description ==

Vijfendertig abilities voor de WordPress Abilities API, waarmee een MCP-assistent
de Kadence-opbouw van een site kan uitlezen en gericht kan wijzigen: welke
blokken er zijn, welke attributen die hebben, hoe een pagina is opgebouwd,
welke headers en elementen er staan, en welk kleurenpalet er geldt.

Achttien daarvan zijn alleen-lezen. Zeventien schrijven: set-attributes,
style-blocks, set-text, insert-blocks, remove-blocks, duplicate-blocks,
replace-block, create-page, set-page-status, create-query, create-query-card,
set-query, sync-query-facets, set-card-layout, set-entity-meta,
set-global-typography en set-site-css. Ze vragen alle zeventien een eigen capability (kadence_mcp_write, die na installatie aan
niemand is toegekend), bewerkrecht op de post volgens WordPress, en een token
uit een voorafgaande controlestap. Na elke schrijfactie wordt de post
teruggelezen en vergeleken met wat er bedoeld was.

Alles staat na installatie uit; je zet per tool aan wat je wil aanbieden, onder
Kadence Blocks > MCP.

== Installation ==

1. Upload de map naar `/wp-content/plugins/`
2. Activeer de plugin
3. Ga naar Kadence Blocks > MCP en zet aan wat je wil aanbieden

Vereist de WordPress Abilities API en de MCP Adapter.

== Changelog ==

= 1.19.0 =
* De plugin werkt zichzelf bij vanaf GitHub, via plugin-update-checker. De repo is publiek, dus er is geen token nodig; de release-workflow bouwt een ZIP zodat de automatisch gegenereerde zipball van de tag — die ook .github/ bevat — nooit op een site belandt.
* De documentatie klopte niet meer met de code. Vier plekken beschreven nog zes schrijvers terwijl het er zeventien zijn, waaronder de serverbeschrijving die elke MCP-client bij elke sessie inlaadt: een agent begon dus met de overtuiging dat schrijven een uitzondering was. Dat is precies de fout die in 1.8.0 al eens is rechtgezet voor de plugin-header en README, en hij was teruggeslopen op de plek waar hij het meest kost.
* CONTEXT.md beweerde dat schrijvers alleen post_content aanraken en dat opties nergens geraakt worden. Sinds set-global-typography en set-site-css is dat onjuist. Er staat nu een onderscheid naar wat er terug te draaien valt: post content krijgt een revisie, post meta niet, en site-brede instellingen raken elke pagina.
* Verwijzingen naar een specifieke klantsite zijn uit de codecommentaren gehaald. De herkomstnotities blijven — afgelezen van echte markup is de reden dat de blokprofielen kloppen — maar zonder wie het was.

= 1.18.0 =
* style-blocks en set-text kennen nu expect_modified als alternatief voor het token. Het token deed twee dingen: een mens de wijziging laten zien, en voorkomen dat je schrijft op een versie die er niet meer is. Bij deze twee schrijvers is dat eerste zwak — er is een schema, elke waarde is al getoetst — terwijl de kosten hoog zijn: op 14-09-2026 kostte het gelijktrekken van 31 hero-koppen 62 aanroepen in plaats van 31. expect_modified houdt de bescherming tegen verouderd schrijven volledig overeind en doet het in één aanroep. Bewust NIET bij remove-blocks, replace-block, insert-blocks en create-page: die veranderen de structuur, en daar valt uit geen schema af te lezen of het de bedoeling was.
* Nieuw: set-global-typography. De enige plek waar je iets kunt doen aan koppen zonder eigen maat. De globale typografie van deze site had alleen desktopwaarden, en zonder mobiele waarde schaalt Kadence niet mee — een telefoon krijgt dan de desktopmaat. Vaste sleutellijst en geen prefixcontrole, want theme mods bevatten ook de header, de kleuren en de layout. Wat je meegeeft wordt samengevoegd, twee niveaus diep: alleen size.mobile opgeven laat family, weight én size.desktop staan.
* Nieuw: set-site-css, voor Weergave > Customizer > Extra CSS. Het veld Custom CSS op een Kadence-header bestaat wél maar wordt op de voorkant niet uitgeserveerd; dat is gemeten en leverde CSS op die nergens terechtkwam. Dit is de enige plek in WordPress zelf die op elke pagina laadt.
* generate-section kent kadence/icon en kadence/single-icon. Aanleiding: een weggehaald pictogram bleek nergens meer vandaan te halen — er stond op de hele site geen tweede om te kopiëren, en zonder profiel kon de generator hem niet bouwen. Een blok dat je wel kunt verwijderen maar niet kunt terugzetten is een gat in het gereedschap.
* De profielen kennen {ATTR:naam}, zodat een blok dat een attribuutwaarde letterlijk in zijn markup zet uitdrukbaar wordt. kadence/single-icon draagt de icoonnaam als data-name op een lege span; zonder die span rendert Kadence niets. De waarde gaat door esc_attr en valt terug op de standaardwaarde uit block.json.
* validate-write waarschuwt bij maxWidth op een Sectie. Kadence schrijft daar margin-left/right: auto bij, en auto-marges op een grid-item zetten dat item op fit-content — het blok krimpt dan naar de breedte van zijn tekst. Gemeten: rastersleuf 88,78px, maxWidth 88px, resultaat 24px. Bewust een notitie en geen blokkade: buiten een rij doet maxWidth precies wat je verwacht.

= 1.17.0 =
* Nieuw: set-entity-meta. De opmaak van een navigatie en de plaatsing van een element staan in post meta met de prefix _kad_, niet in de blokmarkup, en daar kwam geen enkele ability bij. Een menu dat op een zwarte achtergrond terechtkwam bleef daardoor zwart op zwart tot iemand het in de editor repareerde. Twee poorten: alleen posttypes van Kadence, en alleen sleutels die al op die post staan — Kadence schrijft bij elke opslag zijn hele set weg, dus een ontbrekende sleutel is een typefout die anders stil zou worden opgeslagen en genegeerd. Post meta kent geen revisies, dus de oude waarde staat in het antwoord en de status zegt dat erbij.
* set-text kan nu ook blokken zonder uniqueID adresseren, via match_text met de huidige tekst. Kadence deelt uniqueIDs uit, WordPress niet: een core/list-item in een accordeon droeg er geen en was daarmee met geen enkele schrijf-ability te bereiken. Er wordt op de volledige tekst gematcht, niet op een deel: bij nul of meer dan een treffer wordt er niets geschreven en hoor je hoeveel het er waren.
* Het token van generate-section dekt niet langer de letterlijke tekenreeks maar de geserialiseerde blokkenboom. Een agent die de markup overneemt uit een JSON-antwoord levert `\u002d\u002d` terug waar er `--` stond; de blokken zijn dan identiek, de tekenreeks niet, en insert-blocks wees dat af met "je voorstel is anders" zonder te kunnen zeggen waar. Dat hield niets gevaarlijks tegen, alleen iets onschuldigs. Wijzigt er een attribuut of een tekst, dan verandert de boom wel en vervalt het token nog steeds.

= 1.16.1 =
* Reparatie: set-page-status riep mag_schrijven() aan, en die helper bestaat niet in de build-klasse maar in de query-klasse. Elke aanroep gaf een fatale fout. De twee controles staan nu uitgeschreven op de plek zelf.

= 1.16.0 =
* Nieuw: set-page-status. create-page kon alleen bij het AANMAKEN een status meegeven, dus een pagina die als concept was opgebouwd was daarna niet meer te publiceren zonder de editor — en dus ook niet op de voorkant te controleren.

= 1.15.1 =
* Reparatie: create-page faalde voor elke pagina op het hoogste niveau. Het output-schema eiste een object voor parent, en dat is null zonder ouder.

= 1.15.0 =
* Nieuw: create-page, replace-block en set-card-layout.
* insert-blocks met before en after werkt nu op elke diepte in plaats van alleen op het hoogste niveau, inclusief de plaatshouders in innerContent.
* replace-block bouwde de tekst op met de samenvattende tekst_uit_blok() en gooide daarmee inline HTML weg en kapte af op 300 tekens. Er is nu binnenhtml_uit_blok(), die de inhoud binnen de buitenste tag letterlijk teruggeeft.
* style-blocks meldt klassen-drift: bij een attribuut waar klassen uit volgen (zoals direction) wordt gemeld welke klasse ontbreekt, met de verwijzing naar replace-block.

= 1.9.1 =
* Reparatie van een regressie uit 1.8.0: style-blocks meldde een attribuut als mismatch wanneer het wegviel omdat het gelijk was aan de standaardwaarde. maxWidth op ["","",""] zetten leverde "er is geschreven, maar bij het teruglezen wijkt er iets af", terwijl het weglaten juist de bedoeling was. Zulke attributen komen nu in defaults te staan, niet in mismatch. Dat onderscheid is belangrijk: een melding die vals alarm slaat leert je hem negeren.
* De skill legt uit welk attribuut de uitlijning van een Sectie stuurt. Dat is contra-intuïtief: bij direction vertical gaat verticalAlignment over de hoogte (justify-content) en stuurt justifyContent de breedte (align-items). Toegestane waarden voor verticalAlignment zijn top, middle, bottom en stretch; geen van beide heeft een enum, dus validate-write laat elke tekst door.
* Correctie in de skill en het foutenlogboek: de bewering dat de klasse kb-section-dir-horizontal de weergave stuurt is onjuist. Gemeten in beide richtingen wint het attribuut, en style-blocks kan direction gewoon wijzigen op een bestaand blok. Die bewering kwam uit de review en is overgenomen zonder toetsing.

= 1.9.0 =
* generate-section kent de Query-blokken: kadence/query, query-card, query-filter, query-filter-search, query-filter-reset, query-result-count, query-sort en query-pagination. Afgelezen van een bestaande query; ze zijn allemaal zelfsluitend. Een bestaande Query Loop ergens anders neerzetten is daarmee één blok met het id van de query-post.
* Reparatie van hetzelfde patroon als bij de kleurklassen: het attribuut direction op een Sectie leidt drie klassen af (kb-section-dir-, kb-section-md-dir-, kb-section-sm-dir-) die de generator niet meeschreef. Een Sectie met direction horizontal kreeg het attribuut netjes opgeslagen en bleef verticaal staan, want de CSS hangt aan de klasse. Dit was bevinding 3 uit de review, nu bevestigd op echte markup.
* Een blok zonder inhoud en zonder eigen wrapper wordt zelfsluitend geschreven, zoals WordPress dat zelf doet. Dat is nodig omdat kadence/query twee gedaanten heeft: in een pagina verwijst hij alleen naar een query-post en is hij leeg, in die query-post draagt hij de hele layout.

= 1.8.0 =
Naar aanleiding van een volledige review. De rode draad: een controle die groen geeft omdat hij het verkeerde toetst.

* KRITIEK: insert-blocks en duplicate-blocks met position inside zetten een blok BUITEN een lege container. De plaatshouder in innerContent kwam op index 0 in plaats van tussen de open- en sluittag, waardoor het blok vóór de openingstag werd geschreven. Bij een container die al kinderen had ging het goed, en juist daardoor viel het niet op.
* KRITIEK: beide terugleescontroles meldden daarbij "geschreven en teruggelezen", want ze keken alleen of het uniqueID érgens in de post stond — niet onder welke ouder. Er is nu een ouder-toets: staat het blok niet onder de opgegeven container, dan volgt een fout met de werkelijke ouder erbij.
* KRITIEK: de recepten tekst, cta en de koprij van kolommen schreven een rij van één kolom zonder het attribuut columns. Kadence gaat dan uit van twee kolommen en zet de inhoud op halve breedte, met een lege sleuf ernaast. columns wordt nu op elke gegenereerde rij afgeleid uit het aantal kinderen.
* Vier van de zes schrijvers gebruikten serialize_blocks() in plaats van de eigen serialiseer(), waardoor bij elke schrijfactie de lege regels tussen álle secties van de pagina verdwenen. Alle zes doen nu hetzelfde.
* De terugleescontrole van style-blocks sloeg precies het geval over dat ertoe doet: een attribuut dat na het opslaan helemaal weg is. Dat is de bug "metadata werd weggegooid" in zijn zuiverste vorm, en de melding luidde "geschreven en teruggelezen".
* COL_LAYOUTS blokkeerde vijf geldige waarden, waaronder "row" bij elk kolomaantal en "first-row" — dat laatste gebruikt de homepage van deze site.
* Een afgewezen token zegt nu WAT er niet klopt. De melding dekte twee heel verschillende situaties ("de post is gewijzigd of je voorstel is anders"), en die dubbelzinnigheid liet duplicate-blocks zes versies lang stuk staan: elke aanroep gaf die melding en hij klonk als normaal gedrag. Het token bestaat nu uit twee helften — zes tekens voor post en wijzigingsdatum, de rest voor het voorstel — zodat de server kan zien welke helft afwijkt.
* remove-blocks, set-attributes, style-blocks en set-text publiceren destructive: true. MCP-clients gebruiken die annotatie om te bepalen of ze om bevestiging vragen; alle zes stonden op false.
* Plugin-header, README.md en CONTEXT.md zeiden dat deze plugin niets schrijft. Dat klopt sinds 1.0.0 niet meer, en het is precies de tekst waarop iemand besluit of kadence_mcp_write uitgedeeld mag worden.
* set-attributes noemde zichzelf "DE ENIGE SCHRIJFACTIE" en generate-section beweerde dat insert-blocks alleen op het hoogste niveau invoegt — achterhaald sinds 1.5.0 respectievelijk 1.7.0.

= 1.7.2 =
* Reparatie: het sjabloon kolommen zette colLayout op "thirds" en "fourths". Die namen bestaan niet. Op de voorkant viel dat niet op omdat de klasse kt-has-N-columns daar de breedtes bepaalt, maar de editor kiest zijn weergave op colLayout en zette alle kolommen onder elkaar. Nu "equal", zoals Kadence het zelf doet.
* validate-write blokkeert een colLayout die niet bij het aantal kolommen past. De geldige namen staan in Kadence alleen in JavaScript, niet in block.json, dus de gewone typetoets liet elke tekst door.
* describe-block zet values_not_validated: true bij elk stringattribuut zonder enum, met een notitie. Zwijgen over de toegestane waarden las als goedkeuring; nu staat er dat er niets te toetsen viel en dat de waarde afgelezen hoort te worden in plaats van verzonnen.

= 1.7.1 =
* Alleen de skill: de werkwijze voor het nabouwen van een Figma-ontwerp is vastgelegd, met de vertaaltabel die in de praktijk bleek te kloppen en de twee valkuilen bij het narekenen — meet op de juiste laag (Kadence zet padding op .kt-row-column-wrap en de kolomachtergrond op .kt-inside-inner-col), en een fullPage-screenshot liegt over een zwevende header.

= 1.7.0 =
* insert-blocks en duplicate-blocks kennen position "inside": blokken worden dan als KIND van een bestaande container geplaatst in plaats van op het hoogste niveau. Daarmee kan een gekopieerde accordeon eindelijk in een kolom landen, binnen de achtergrond en de contentbreedte van die rij; op het hoogste niveau staat hij daarbuiten.
* Het venijn zat in innerContent: die array draagt op de plaats van elk kindblok een null, en een kind toevoegen zonder ook een null toe te voegen laat serialize_block() het laatste kind overslaan. De null wordt nu ingevoegd vlak vóór de afsluitende HTML van de container. Een zelfsluitend blok heeft geen binnenkant en wordt geweigerd.

= 1.6.1 =
* Reparatie: gegenereerde tekstblokken bleven zwart, ook met color palette9 en colorClass theme-palette9. De kleur van een Kadence-tekstblok komt niet uit het attribuut maar uit een klasse in de markup (has-theme-palette-9-color has-text-color), en die schreef de generator niet mee. De blokdata was in orde en het stond er toch verkeerd — alleen zichtbaar door in de browser getComputedStyle op te vragen. Nu worden de klassen afgeleid uit colorClass en backgroundColorClass.

= 1.6.0 =
* generate-section kent nu recipe "custom": de structuur wordt dan meegegeven via tree, als een geneste lijst van knopen. Nodig omdat vier vaste sjablonen geen echt ontwerp dekken, en omdat achteraf samenstellen niet kan — insert-blocks voegt alleen op het hoogste niveau toe, dus een blok in een bestaande kolom krijgen gaat niet. Wat in één sectie hoort wordt in één keer gebouwd.
* De manier van genereren blijft vast: dezelfde afgelezen wrapper-markup, dezelfde uitgifte van uniqueIDs (een eigen ID meegeven wordt geweigerd), dezelfde attribuuttoets als validate-write, en dezelfde parse- en serialiseerrondgang als eindcontrole. Alleen de combinatie is vrij.
* tag en level worden op elkaar afgestemd. advancedheading bepaalt zijn tag uit htmlTag (standaard "heading", valt terug op level): een h3 in de markup met level 2 zou als h2 renderen terwijl er h3 staat.

= 1.5.0 =
* Nieuw: kadence/style-blocks schrijft attributen naar meerdere blokken in één opslag. set-attributes doet één blok per aanroep, en een sectie opmaken raakt al snel zes blokken — dat is veertien aanroepen, waarbij elk token vervalt zodra de vorige stap post_modified heeft veranderd. Nu is het er twee, en één revisie voor de hele set.
* De toetsing is niet gedupliceerd: style-blocks roept per blok validate_write() aan, zodat er maar één plek met toetslogica bestaat. Alles of niets — is één blok blokkeer of riskant, dan komt er geen token en wordt er niets geschreven. Een blok dat twee keer in de lijst staat wordt geweigerd.

= 1.4.1 =
* Reparatie: het voorstel van remove-blocks liet het veld text leeg zodra je een container opgaf. tekst_uit_blok() leest alleen de innerHTML van het blok zelf, en een Row Layout heeft die niet — de tekst zit in de kleinkinderen. Het voorstel meldde dus dat er niets verdween terwijl er een hele sectie met koppen aan hing, en dat is precies de informatie waar het voorstel om draait. De hele subboom wordt nu doorlopen.

= 1.4.0 =
* REPARATIE, belangrijk: kadence/duplicate-blocks kon sinds 1.1.0 helemaal niet schrijven. De kaart van oude naar nieuwe uniqueID werd bij elke aanroep opnieuw met willekeurige waarden gevuld, terwijl het token juist over die kaart ging — bij de schrijfaanroep ontstond er dus altijd een ander token dan de droogloop had gegeven, en geen enkele aanroep kon slagen. De id_map is nu invoer bij dry_run false, net zoals de markup dat bij insert-blocks is, en wordt voor het schrijven gecontroleerd op dekking, dubbele waarden en botsingen met de doelpost.
* Reparatie: metadata en lock kregen van validate-write het oordeel riskant, en riskant levert geen token op — waardoor een bloknaam die door een eerdere versie was weggevallen niet te herstellen was. Ze krijgen nu ok met een notitie: er valt niets te toetsen, maar er hangt ook geen render van af.
* Reparatie: de statusregel bij het oordeel riskant noemde altijd dezelfde reden ("minstens één waarde zit vast in de opgeslagen HTML"), ook naast een antwoord dat zelf self_closing: true meldde. Hij noemt nu de betrokken attributen en verwijst naar hun notitie.
* Reparatie: script- en style-tags werden door wp_kses verwijderd terwijl hun inhoud als leesbare tekst bleef staan. Die twee worden nu mét inhoud verwijderd, ook wanneer de tag nooit gesloten wordt.
* Reparatie: set-text weigerde een Kadence-kolom met de melding dat er meerdere elementen "op hetzelfde niveau" stonden, terwijl die divs genest zijn. De twee gevallen worden nu onderscheiden en krijgen elk hun eigen uitleg.
* Nieuw: kadence/remove-blocks haalt blokken uit een post, op elke diepte, ook een enkele knop uit een knoppenrij. Twee stappen: eerst zien wat er zou verdwijnen inclusief alles eronder en welke tekst daarin staat, dan pas doen. Staat een van de opgegeven uniqueIDs niet in de post, dan wordt er niets verwijderd.

= 1.3.0 =
* Nieuw: kadence/list-recipes, kadence/generate-section en kadence/insert-blocks bouwen een complete sectie — een Row Layout met Secties en tekstblokken erin — uit een sjabloon, in plaats van markup te laten verzinnen. Dat is nodig omdat Kadence elk uniqueID in de klassenamen van het blok bakt (bij advancedheading twee keer, in de klasse en in data-kb-block) en de JSON in het blokcommentaar met eigen vlaggen codeert. Met de hand geschreven markup wijkt daar gegarandeerd van af, en dat is aan het resultaat niet te zien: het blok verschijnt, maar zonder zijn CSS.
* De gebouwde markup wordt geparsed en opnieuw geserialiseerd voordat hij wordt teruggegeven. Komt daar niet exact hetzelfde uit, dan volgt een fout in plaats van markup — dan zou WordPress hem bij het opslaan hebben herschreven.
* insert-blocks weigert te schrijven wanneer door de invoeging een bestaand uniqueID zou verdwijnen of een dubbele zou ontstaan. Aan een uniqueID hangt de CSS van een blok, dus een ID dat weg is betekent een blok dat weg is.
* De sjablonen leveren bewust alleen structuur, geen opmaak. Kleuren, marges en lettergroottes komen uit de globale stijlen; een verzonnen waarde is ruis die iemand later moet opsporen.

= 1.2.0 =
* Reparatie van stil dataverlies: metadata en lock zijn attributen van WordPress zelf en staan in geen enkele block.json, waardoor de normalisatie ze bij elke schrijfactie als "onbekend" wegfilterde. Dat kostte op 11-09-2026 de bloknaam van een rij: de schrijfactie slaagde, de terugleescontrole meldde het, en de naam was weg. Ze worden nu ongemoeid doorgegeven en achteraan in het commentaar gezet, waar de editor ze ook neerzet.
* Nieuw: kadence/set-text schrijft de tekst van een blok. Nodig omdat de tekst van een kop of alinea niet in de attributen staat maar in de innerHTML — set-attributes weigert die terecht, want daar worden ook de klassen en data-attributen uit opgebouwd. set-text vervangt alleen het deel tussen de buitenste tag en laat die tag letterlijk staan, zodat de blokvalidatie van Gutenberg blijft kloppen. Weigert bij een blok met kindblokken, bij een innerHTML die niet precies één omhullend element is, en bij een lege tekst.
* validate-write blokkeert metadata en lock niet langer als "bestaat niet"; ze krijgen het oordeel riskant, omdat er geen schema is om hun inhoud tegen te toetsen.

= 1.1.0 =
* Nieuw: kadence/duplicate-blocks kopieert een bestaand blok met alles eronder naar een andere post, en geeft elke kopie een verse uniqueID geprefixt met de doelpost. De ID wordt op beide plekken tegelijk vervangen — in het attribuut en in de klassen in de markup — zodat er geen mismatch kan ontstaan en er geen klassepatroon gekend hoeft te worden. Standaard een droogloop met token; schrijven vraagt dry_run false en dat token.

= 1.0.1 =
* Reparatie: kadence_mcp_write was in Members onvindbaar omdat die plugin zijn lijst afleidt uit capabilities die ergens in gebruik zijn — en deze wordt bewust aan niemand toegekend. De capabilities worden nu met een leesbaar label geregistreerd via members_register_caps, met members_get_capabilities als vangnet.

= 1.0.0 =
* EERSTE SCHRIJFACTIE: kadence/set-attributes wijzigt attributen op één bestaand blok. Drie grendels ervoor — een eigen capability kadence_mcp_write die niemand automatisch krijgt, bewerkrecht op de post volgens WordPress, en een geldig token uit validate-write. Eén erna: de post wordt teruggelezen en vergeleken met wat er bedoeld was, want Kadence herschrijft post_content bij het opslaan.
* Attributen worden genormaliseerd zoals de editor het doet: wat gelijk is aan de standaardwaarde valt weg, de rest komt in de volgorde van het blokschema. Gemeten op een echte pagina: van 170 attributen stonden er 23 opgeslagen, geen enkele gelijk aan zijn default, in exact block.json-volgorde.
* De groep Schrijven op het instellingenscherm is niet langer leeg.

= 0.9.3 =
* Correctie in de serverbeschrijving, de skill en de documentatie: blockVisibility werd toegeschreven aan Kadence, maar het is de native verbergoptie van WordPress zelf en geldt voor elk blok.

= 0.9.2 =
* Reparatie: de inkorting van preview-write zat in 0.9.1 wel in het invoerschema maar niet in de code, waardoor full_markup werd geadverteerd maar genegeerd.

= 0.9.1 =
* check-bindings toetst een taxonomie nu ook tegen het posttype dat de query opvraagt. Bestaan alleen is niet genoeg: een facet op een taxonomie die niet aan het opgevraagde posttype hangt levert een filter dat nooit iets toont. Gevonden op een echte site.
* preview-write geeft standaard alleen de openingscomment van het gewijzigde blok in plaats van de volledige boom. De volledige markup is op te vragen met full_markup.

= 0.9.0 =
* Nieuw: kadence/preview-write — toont de blokmarkup voor en na een voorgenomen wijziging, zonder iets op te slaan. Bedoeld als droogloop tussen validate-write en de uiteindelijke schrijfactie, zodat er een keer gekeken wordt in plaats van achteraf gerepareerd. Waarschuwt expliciet als de innerHTML zou veranderen, wat bij een attribuutwijziging niet hoort te gebeuren.

= 0.8.0 =
* validate-write geeft bij het oordeel veilig een token terug, gebonden aan post, blok, exacte attributen en de wijzigingsdatum van de post. Een toekomstige schrijfactie kan dat token eisen, waarmee toetsen niet langer over te slaan is. Bij riskant komt er bewust geen token.
* validate-write gebruikt nu rest_validate_value_from_schema(), dezelfde functie die WordPress bij het renderen draait. Een waarde die daar niet doorheen komt wordt stil weggegooid en vervangen door de standaardwaarde; de toets is daarmee gelijk aan de werkelijkheid.

= 0.7.0 =
* Nieuw: kadence/get-raw-markup — de opgeslagen blokmarkup van een post of een enkel blok. De andere tools tonen alleen geparste attributen, terwijl juist de markup bepaalt of iets veilig te wijzigen is.
* Nieuw: kadence/validate-write — toetst een voorgenomen attribuutwijziging zonder iets te schrijven. Kern is een waarneming: staat de huidige waarde letterlijk in de innerHTML van dat blok, dan is wijzigen een stille breuk. Plus type- en enumtoets, source-attributen als no-op, en de drie eigen faalpaden uniqueID, kbVersion en columns inclusief botsingscontrole.
* Nieuw: kadence/check-bindings — controleert alle verwijzingen in een post op vijf opslagplaatsen, inclusief de shortcodekopie en inline spans.
* describe-block en list-blocks vermelden bij is_dynamic nu expliciet dat het geen veiligheidssignaal is: alle Kadence-blokken hebben een render_callback.

= 0.6.0 =
* De agent skill is nu te downloaden vanaf het instellingenscherm, als zip, met dezelfde capability-poort als de instellingen zelf.

= 0.5.0 =
* describe-block splitst uses_context nu in uses_context_own (uit de eigen block.json) en uses_context_added (door een andere plugin toegevoegd). GP Entry Blocks hangt zijn entry-context aan elk blok, wat anders ten onrechte als Kadence-eigenschap leest.
* Skill uitgebreid met de twee mechanismen voor dynamische inhoud, waarom een query-card herbruikbaar is zolang de koppelingen generiek zijn, en waar dynamische blokken hun context vandaan halen.

= 0.4.1 =
* Reparatie: ancestor en de contextvelden kwamen door een misgelopen bewerking niet in de uitvoer van describe-block, en top_level_only filterde nog steeds alleen op parent.
* Correctie: block_defaults werd beschreven als iets dat de betekenis van een ontbrekend attribuut verandert. Dat klopt niet — kadence_blocks_config_blocks gaat alleen naar de editor en is een invoegstandaard die bij opslaan in de markup wordt geschreven. Bestaande blokken volgen gewoon block.json.

= 0.4.0 =
* list-blocks en describe-block lezen nu ancestor. Dat is iets anders dan parent en verklaart waarom de queryblokken pas binnen een Query Loop beschikbaar zijn; 29 van de 92 Kadence-blokken gebruiken het.
* Reparatie: top_level_only filterde alleen op parent en liet daardoor kadence/navigation-link en kadence/off-canvas-trigger door als vrij plaatsbaar.
* describe-block geeft nu ook uses_context, provides_context, allowed_blocks en keywords.
* get-global-styles geeft nu block_defaults: de site-brede standaardinstellingen per blok uit kadence_blocks_config_blocks. Belangrijk, want daar waar die bestaan betekent een ontbrekend attribuut niet de standaardwaarde uit block.json.

= 0.3.1 =
* describe-block: nieuwe groep "indeling" voor colLayout, columns en inheritMaxWidth — die vielen eerder onder "overig" terwijl juist colLayout kolomverhoudingen verklaart.
* describe-block: loggedIn en loggedOut vallen nu onder "conditioneel" in plaats van "overig".
* Skill uitgebreid met Query Loops (post meta, gedeeld object, losse filterblokken, één card per query) en met het verschil in zichtbaarheidsopties tussen Row Layout en Sectie.

= 0.3.0 =
* Nieuw: kadence/get-post-meta — de Kadence-configuratie die niet in blokattributen staat, zoals de querydefinitie in _kad_query_query. Allowlist op _kad_, _kt_ en kadence_; meta van andere plugins wordt niet teruggegeven.
* Nieuw: kadence/find-usages — welke posts via een id-attribuut naar een query, navigatie, card of header verwijzen.
* inspect-post: from_unique_id om de boom bij een bepaald blok te laten beginnen.
* inspect-post: nieuw filter kadence_mcp_can_read_post, en de statusregel meldt nu wanneer een post wel leesbaar is maar niet uit een normale query komt (bijvoorbeeld door Members).
* Reparatie: HTML-entiteiten in include_text werden niet gedecodeerd.
* Reparatie: de beschrijving van find-post zei dat search alleen op de titel zoekt; WordPress doorzoekt ook de inhoud.

= 0.2.0 =
* Nieuw: kadence/diff-blocks — geeft alleen de verschillen tussen twee blokken.
* Nieuw: kadence/find-post — van paginanaam of slug naar een post_id.
* inspect-post: metadata standaard mee (bevat blockVisibility en de naam uit de lijstweergave), plus include_text en full_attributes.
* describe-block: attributen gegroepeerd in plaats van alfabetisch, met een group-filter en een telling per groep.
* Serverbeschrijving draagt nu een primer over hoe Kadence waarden opslaat.
* Elk antwoord heeft een status-regel, zodat een leeg resultaat zichzelf verklaart.
* Reparatie: typografie kwam altijd leeg terug door een onjuiste method_exists-controle op een klasse met __call.
* Agent skill toegevoegd onder skill/.

= 0.1.0 =
* Eerste versie. Vijf leesabilities, eigen MCP-server, instellingenscherm en capabilities.
