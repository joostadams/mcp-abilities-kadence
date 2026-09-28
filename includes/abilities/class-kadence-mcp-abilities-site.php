<?php
/**
 * Abilities over de Kadence-installatie als geheel.
 *
 * @package Kadence_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * kadence/list-entities en kadence/get-global-styles.
 */
class Kadence_MCP_Abilities_Site {

	/**
	 * De ruwe definities.
	 *
	 * @return array[]
	 */
	public static function get_definitions() {
		return array(
			array(
				'name' => 'kadence/list-entities',
				'args' => array(
					'label'       => __( 'Kadence-objecten opsommen', 'mcp-abilities-kadence' ),
					'summary'     => __( 'De headers, elementen, navigaties en andere Kadence-posttypes op deze site.', 'mcp-abilities-kadence' ),
					'description' => __( 'Somt op wat Kadence als eigen posttype bewaart: kadence_header, kadence_element, kadence_navigation, kadence_form, kadence_lottie en wat er verder geregistreerd staat. Gebruik dit om aan een post_id te komen die je daarna met inspect-post kunt openen. LET OP: dit somt alleen KADENCE-posttypes op, geen gewone pagina of bericht — het post_id van een gewone pagina moet je elders vandaan halen. De lijst wordt op prefix ontdekt plus een korte uitzonderingslijst, dus een nieuw Kadence-posttype verschijnt vanzelf.', 'mcp-abilities-kadence' ),
					'input_schema' => array(
						'type'       => 'object',
						'default'    => (object) array(),
						'properties' => array(
							'post_types' => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => __( 'Beperk tot deze posttypes. Leeg is alle.', 'mcp-abilities-kadence' ),
							),
							'status' => array(
								'type'        => 'string',
								'default'     => 'any',
								'description' => __( 'Poststatus, bijvoorbeeld publish of draft. "any" is alles behalve prullenbak.', 'mcp-abilities-kadence' ),
							),
							'per_type_limit' => array(
								'type'    => 'integer',
								'minimum' => 1,
								'maximum' => 200,
								'default' => 50,
							),
						),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_types' => array( 'type' => 'array' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'list_entities' ),
				),
			),
			array(
				'name' => 'kadence/find-post',
				'args' => array(
					'label'       => __( 'Een post zoeken op naam of slug', 'mcp-abilities-kadence' ),
					'summary'     => __( 'Van paginanaam naar post_id, zodat inspect-post erop kan.', 'mcp-abilities-kadence' ),
					'description' => __( 'Zoekt posts en geeft hun post_id terug, als opstap naar inspect-post. LET OP: "search" gebruikt de zoekfunctie van WordPress en die doorzoekt titel ÉN inhoud — zoeken op een woord dat in een blok staat levert dus de post op waarin dat blok zit, niet alleen posts met die titel. Wil je exact één post, gebruik dan slug. Zonder post_type wordt er in alle publiek opvraagbare posttypes plus de Kadence-posttypes gezocht. Een post die door een plugin als Members wordt afgeschermd komt hier niet terug, ook niet als inspect-post hem wel kan lezen.', 'mcp-abilities-kadence' ),
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'search' => array(
								'type'        => 'string',
								'description' => __( 'Zoekterm. Doorzoekt titel én inhoud. Laat leeg als je op slug zoekt.', 'mcp-abilities-kadence' ),
							),
							'slug' => array(
								'type'        => 'string',
								'description' => __( 'Exacte slug. Gaat voor op search.', 'mcp-abilities-kadence' ),
							),
							'unique_id' => array(
								'type'        => 'string',
								'description' => __( 'Zoek de post(s) waarin een blok met deze uniqueID staat. Gaat voor op slug en search. Dé manier om na een overzetting het tegenstuk op de andere site te vinden: de uniqueID blijft gelijk, het post-ID niet.', 'mcp-abilities-kadence' ),
							),
							'post_type' => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => __( 'Beperk tot deze posttypes, bijvoorbeeld ["page"].', 'mcp-abilities-kadence' ),
							),
							'status' => array(
								'type'        => 'string',
								'default'     => 'any',
								'description' => __( 'Poststatus. "any" is alles behalve prullenbak.', 'mcp-abilities-kadence' ),
							),
							'limit' => array(
								'type'    => 'integer',
								'minimum' => 1,
								'maximum' => 100,
								'default' => 20,
							),
						),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'posts'  => array( 'type' => 'array' ),
							'status' => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'find_post' ),
				),
			),
			array(
				'name' => 'kadence/get-post-meta',
				'args' => array(
					'label'       => __( 'De Kadence-instellingen van een post', 'mcp-abilities-kadence' ),
					'summary'     => __( 'De post meta waarin Kadence zijn configuratie bewaart — onmisbaar bij Queries en Cards.', 'mcp-abilities-kadence' ),
					'description' => __( 'Geeft de post meta van een post terug, beperkt tot sleutels die van Kadence zijn (_kad_, _kt_, kadence_). Dit is nodig omdat een groot deel van de Kadence-configuratie NIET in blokattributen staat. Het duidelijkste voorbeeld is kadence/query: dat blok heeft maar zes attributen en geen daarvan gaat over de query — posttype, sortering, meta_key en filters staan in _kad_query_query op de kadence_query-post. Zonder deze tool kun je een Query Loop dus wel zien staan maar niet zien hoe hij is ingesteld. Meta van andere plugins wordt bewust niet teruggegeven; het aantal weggelaten sleutels staat in withheld.', 'mcp-abilities-kadence' ),
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'        => 'integer',
								'minimum'     => 1,
								'description' => __( 'Het ID van de post.', 'mcp-abilities-kadence' ),
							),
							'keys' => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => __( 'Alleen deze sleutels. Moeten nog steeds op een toegestaan prefix beginnen.', 'mcp-abilities-kadence' ),
							),
						),
						'required'             => array( 'post_id' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post'     => array( 'type' => 'object' ),
							'meta'     => array( 'type' => 'object' ),
							'withheld' => array( 'type' => 'integer' ),
							'status'   => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'get_post_meta_ability' ),
				),
			),
			array(
				'name' => 'kadence/find-usages',
				'args' => array(
					'label'       => __( 'Zoeken waar een Kadence-object gebruikt wordt', 'mcp-abilities-kadence' ),
					'summary'     => __( 'Welke posts verwijzen naar deze query, navigatie, card of header.', 'mcp-abilities-kadence' ),
					'description' => __( 'Zoekt posts waarvan de inhoud naar een bepaald Kadence-object verwijst. Blokken als kadence/query, kadence/navigation, kadence/query-card en kadence/header nemen het object op via een id-attribuut; deze tool zoekt op dat id. Gebruik dit voordat je iets aanpast aan een gedeeld object, om te weten waar de wijziging landt. De zoekopdracht scant de inhoud van posts en is daarom begrensd: het aantal gescande posts staat in de statusregel, zodat een onvolledige uitkomst niet als volledig leest.', 'mcp-abilities-kadence' ),
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'object_id' => array(
								'type'        => 'integer',
								'minimum'     => 1,
								'description' => __( 'Het post-ID van het object waarnaar gezocht wordt, bijvoorbeeld een kadence_query of kadence_navigation.', 'mcp-abilities-kadence' ),
							),
							'post_type' => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => __( 'Beperk het zoekgebied tot deze posttypes.', 'mcp-abilities-kadence' ),
							),
							'scan_limit' => array(
								'type'    => 'integer',
								'minimum' => 1,
								'maximum' => 1000,
								'default' => 300,
							),
						),
						'required'             => array( 'object_id' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'usages' => array( 'type' => 'array' ),
							'scanned' => array( 'type' => 'integer' ),
							'status'  => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'find_usages' ),
				),
			),
			array(
				'name' => 'kadence/check-bindings',
				'args' => array(
					'label'       => __( 'Verwijzingen in een post controleren', 'mcp-abilities-kadence' ),
					'summary'     => __( 'Wijst elke dynamische koppeling, taxonomiefilter en objectverwijzing nog naar iets dat bestaat?', 'mcp-abilities-kadence' ),
					'description' => __( 'Loopt alle verwijzingen in een post na en zegt per stuk of hij geldig is, dangelt, of niet te verifieren valt. Nodig omdat Kadence nergens logt en geen foutmelding toont: een kapotte dynamische verwijzing laat het blok stil verdwijnen, en een ongeldig posttype in een Query Loop valt terug op blogberichten. Kijkt op vijf plekken, want dezelfde instelling staat vaak dubbel: de gewone attributen field, tax, metaField en customMeta; het kadenceDynamic-object per slot; dezelfde koppeling nog eens als kb-dynamic-shortcode in het doelattribuut; inline spans met data-field in de markup; en de post meta _kad_query_query en _kad_query_facets. Verwijzingen naar losse Kadence-objecten (query, card, navigatie, header) worden ook getoetst op bestaan, posttype en publicatiestatus.', 'mcp-abilities-kadence' ),
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
						),
						'required'             => array( 'post_id' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post'     => array( 'type' => 'object' ),
							'bindings' => array( 'type' => 'array' ),
							'broken'   => array( 'type' => 'integer' ),
							'status'   => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'check_bindings' ),
				),
			),
			array(
				'name' => 'kadence/get-global-styles',
				'args' => array(
					'label'       => __( 'Globale stijlen opvragen', 'mcp-abilities-kadence' ),
					'summary'     => __( 'Het kleurenpalet en de basistypografie van het Kadence-thema.', 'mcp-abilities-kadence' ),
					'description' => __( 'Geeft het globale kleurenpalet, de basistypografie en de site-brede standaardinstellingen per blok. Die laatste zijn INVOEGstandaarden: wat de editor invult bij een nieuw blok, en wat bij opslaan in de markup terechtkomt. Ze veranderen niets aan bestaande blokken — een ontbrekend attribuut daar betekent nog steeds de standaardwaarde uit describe-block. Waar ze voor dienen is weten welke vorm iets op deze site hoort te krijgen als je iets nieuws voorstelt. Ontbreekt een bron, dan staat dat er zo bij — er worden geen waarden verzonnen. Ook bruikbaar om te weten welke kleur een blok bedoelt als het naar "palette3" verwijst. LET OP bij de typografie: het thema vult een niet-ingestelde sleutel aan met zijn standaardwaarde, dus een waarde zegt niet dat hij is ingesteld — typography_sources zegt per sleutel opgeslagen of standaard. fonts somt de font-faces op die de site zelf levert (Kadence Custom Fonts) en waarschuwt als een family of gewicht uit de typografie er geen heeft; de browser valt dan stil terug of bootst het gewicht na. environment noemt ook de versie van het child theme en of er een paginacache draait (leeg die eerst voordat je een verschil na een release als fout ziet).', 'mcp-abilities-kadence' ),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'environment' => array( 'type' => 'object' ),
							'styles'      => array( 'type' => 'object' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'get_global_styles' ),
				),
			),
			array(
				'name' => 'kadence/check-access',
				'args' => array(
					'label'       => __( 'Rechten van dit account controleren', 'mcp-abilities-kadence' ),
					'summary'     => __( 'Wat dit account per posttype mag lezen en bewerken, en welke rechten ontbreken.', 'mcp-abilities-kadence' ),
					'description' => __( 'Controle vooraf, vóór je iets aanmaakt of overzet. Een ontbrekend recht ziet er in de andere tools uit als "bestaat niet" of als een stille aanpassing: zonder edit_theme_options zijn headers, elementen, navigaties en vectoren niet te bewerken (en concepten niet te lezen), en zonder unfiltered_html haalt WordPress bij het opslaan SVG en scripts weg en wordt & in een titel &amp;. Geeft per posttype het aantal posts, hoeveel daarvan leesbaar en bewerkbaar zijn, en of aanmaken en publiceren mag. Posts die er zijn maar niet leesbaar zijn, niet opnieuw aanmaken.', 'mcp-abilities-kadence' ),
					'input_schema' => array(
						'type'       => 'object',
						'default'    => (object) array(),
						'properties' => array(
							'post_types' => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => __( 'Beperk tot deze posttypes. Leeg is alle Kadence- en publieke posttypes.', 'mcp-abilities-kadence' ),
							),
						),
						'additionalProperties' => false,
					),
					'execute_callback' => array( __CLASS__, 'check_access' ),
				),
			),
			array(
				'name' => 'kadence/audit-colors',
				'args' => array(
					'label'       => __( 'Kleurgebruik op de site nalopen', 'mcp-abilities-kadence' ),
					'summary'     => __( 'Per kleurwaarde waar hij staat: blokattributen, Kadence-meta en typografie.', 'mcp-abilities-kadence' ),
					'description' => __( 'Loopt de blokattributen van alle posts, de _kad-meta van de Kadence-objecten en de typografie van het thema af, en groepeert per kleurwaarde waar die staat (post:ID uniqueID attribuut, of meta:ID sleutel). Per waarde de soort: palet (palette3), palet-variabele (var(--global-palette3)), variabele (var(--eigen-token)), hex of rgb. Een hex die gelijk is aan een paletkleur krijgt same_as: die ziet er goed uit maar beweegt niet mee als het palet verandert. Met only_off_palette alleen hex, rgb en de rest; met value alleen die ene waarde. Kleuren in CSS-bestanden van het thema zie je hier niet. Schrijft niets; omzetten doe je daarna met style-blocks of set-entity-meta.', 'mcp-abilities-kadence' ),
					'input_schema' => array(
						'type'       => 'object',
						'default'    => (object) array(),
						'properties' => array(
							'only_off_palette' => array(
								'type'        => 'boolean',
								'default'     => false,
								'description' => __( 'Alleen waarden die geen paletverwijzing of variabele zijn.', 'mcp-abilities-kadence' ),
							),
							'value' => array(
								'type'        => 'string',
								'description' => __( 'Alleen deze waarde, bijvoorbeeld "#04201a" of "palette4" (hoofdletterongevoelig).', 'mcp-abilities-kadence' ),
							),
							'scan_limit' => array(
								'type'    => 'integer',
								'minimum' => 1,
								'maximum' => 2000,
								'default' => 500,
							),
						),
						'additionalProperties' => false,
					),
					'execute_callback' => array( __CLASS__, 'audit_colors' ),
				),
			),
			array(
				'name' => 'kadence/replace-colors',
				'args' => array(
					'label'       => __( 'Kleuren omzetten over de hele site', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Zet kleurwaarden om in blokattributen en Kadence-meta, met een kaart {oud: nieuw}.', 'mcp-abilities-kadence' ),
					'description' => __( 'Zet kleuren om in de blokattributen van alle posts (of van post_ids) en in de _kad-meta van de Kadence-objecten, met een kaart {"#04201a":"palette3", "#dcdcdc":"#e6e6e6"}; oud is hoofdletterongevoelig. Draai eerst audit-colors om te zien wat er staat. Het voorstel geeft per post en per meta-sleutel elke wijziging, en in skipped wat bewust niet wordt omgezet, met reden: een var(--…) waar een opacity bij hoort (Kadence rekent die om naar rgba en dat breekt een variabele), een paletnaam in een niet-Kadence-blok (core kent palette3 niet), en een niet-hex waarde in een Gravity Forms-blok. Posts krijgen één revisie per post; meta kent geen revisies, de oude waarde staat in changes[].from. Kleuren in CSS-bestanden raakt dit niet. Twee stappen: eerst zonder token, daarna met token en dezelfde invoer.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'map'          => array( 'type' => 'object', 'additionalProperties' => array( 'type' => 'string' ) ),
							'post_ids'     => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'description' => __( 'Beperk tot deze posts (inhoud én meta). Leeg is de hele site.', 'mcp-abilities-kadence' ) ),
							'include_meta' => array( 'type' => 'boolean', 'default' => true ),
							'token'        => array( 'type' => 'string' ),
						),
						'required'             => array( 'map' ),
						'additionalProperties' => false,
					),
					'execute_callback' => array( __CLASS__, 'replace_colors' ),
				),
			),
			array(
				'name' => 'kadence/site-fingerprint',
				'args' => array(
					'label'       => __( 'Vingerafdruk van de site', 'mcp-abilities-kadence' ),
					'summary'     => __( 'Hashes van palet, typografie, fonts, termen, Kadence-objecten en plugins, om twee sites te vergelijken.', 'mcp-abilities-kadence' ),
					'description' => __( 'Voor het vergelijken van twee sites (lokaal en staging) op wat niet in de blokken staat en wat een pixelvergelijking pas laat zien: palet, typografie (met opgeslagen of standaard), fonts, termen met hun beschrijving, of taxonomieën publiek opvraagbaar zijn, Kadence-objecten (per titel een hash van inhoud en meta, zonder ID\'s en domein), actieve plugins met versie en de leesinstellingen. Draai het op beide sites en vergelijk de hashes; waar er een verschilt, vraag opnieuw met detail: true en vergelijk dat onderdeel. Schrijft niets.', 'mcp-abilities-kadence' ),
					'input_schema' => array(
						'type'       => 'object',
						'default'    => (object) array(),
						'properties' => array(
							'detail' => array(
								'type'        => 'boolean',
								'default'     => false,
								'description' => __( 'Ook de waarden zelf teruggeven, niet alleen de hashes.', 'mcp-abilities-kadence' ),
							),
						),
						'additionalProperties' => false,
					),
					'execute_callback' => array( __CLASS__, 'site_fingerprint' ),
				),
			),
			array(
				'name' => 'kadence/set-global-typography',
				'args' => array(
					'label'       => __( 'De globale typografie wijzigen', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Zet de lettergroottes van het thema, per breakpoint.', 'mcp-abilities-kadence' ),
					'description' => __( 'Wijzigt de basistypografie van het Kadence-thema: base_font, heading_font en h1_font tot en met h6_font. Dit is de enige plek waar je iets kunt doen aan koppen die GEEN eigen maat meekrijgen — thematekst, formulierlabels, koppen in een accordeon. Let op de reikwijdte: een kadence/advancedheading met een eigen fontSize negeert deze waarden volledig, dus hiermee maak je bestaande hero-koppen niet kleiner. Iedere sleutel is een object met onder meer size en lineHeight, en die hebben elk een desktop, tablet en mobile. Ontbreekt mobile, dan schaalt Kadence NIET mee en krijgt een telefoon de desktopmaat. Wat je meegeeft wordt samengevoegd met wat er staat, niet overheen geschreven: alleen size opgeven laat family en weight met rust. LET OP: dit zijn theme mods en die kennen geen revisies. Terugdraaien kan alleen met de waarden uit het veld before, dus bewaar die. Twee stappen: eerst zonder token voor een voorstel met oud en nieuw naast elkaar, daarna opnieuw met dat token.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'typography' => array(
								'type'                 => 'object',
								'additionalProperties' => true,
								'description'          => __( 'De sleutels die moeten wijzigen. Toegestaan: base_font, heading_font, h1_font, h2_font, h3_font, h4_font, h5_font, h6_font. Neem de vorm letterlijk over van get-global-styles, bijvoorbeeld {"h1_font":{"size":{"desktop":32,"tablet":28,"mobile":24}}}.', 'mcp-abilities-kadence' ),
							),
							'token' => array( 'type' => 'string' ),
						),
						'required'             => array( 'typography' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'before'  => array( 'type' => 'object' ),
							'after'   => array( 'type' => 'object' ),
							'changed' => array( 'type' => 'array' ),
							'written' => array( 'type' => 'boolean' ),
							'token'   => array( 'type' => 'string' ),
							'status'  => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'set_global_typography' ),
				),
			),
			array(
				'name' => 'kadence/set-site-css',
				'args' => array(
					'label'       => __( 'De extra CSS van de site wijzigen', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Schrijft de Extra CSS uit de Customizer.', 'mcp-abilities-kadence' ),
					'description' => __( 'Schrijft naar Weergave > Customizer > Extra CSS. Dat is de enige plek in WordPress zelf waar CSS op ELKE pagina wordt uitgeserveerd. Bestaat omdat het veld Custom CSS op een Kadence-header of -element WEL bestaat maar op de voorkant niet wordt uitgeserveerd; dat is op 14-09-2026 gemeten en leverde CSS op die nergens terechtkwam. Gebruik dit voor regels die over de hele site gelden, zoals het positioneren van een uitklapmenu. Hoort de regel bij het ontwerp van het thema zelf, zet hem dan liever in de stylesheet van het child theme — die staat in versiebeheer en dit veld niet. Vervangt standaard de hele inhoud; zet append aan om eronder te plakken. LET OP: de vorige inhoud staat in het veld before en is de enige manier om terug te draaien. Twee stappen: eerst zonder token, daarna met.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'css'    => array( 'type' => 'string' ),
							'append' => array(
								'type'        => 'boolean',
								'default'     => false,
								'description' => __( 'Standaard onwaar: de meegegeven CSS vervangt alles. Op waar wordt hij achter de bestaande CSS geplakt.', 'mcp-abilities-kadence' ),
							),
							'token'  => array( 'type' => 'string' ),
						),
						'required'             => array( 'css' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'before'  => array( 'type' => 'string' ),
							'after'   => array( 'type' => 'string' ),
							'written' => array( 'type' => 'boolean' ),
							'token'   => array( 'type' => 'string' ),
							'status'  => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'set_site_css' ),
				),
			),
		);
	}

	/**
	 * De themasleutels die set-global-typography mag aanraken.
	 *
	 * Bewust een vaste lijst en geen prefixcontrole. Theme mods zijn één grote
	 * bak waar ook de headerindeling, de kleuren en de layoutkeuzes in zitten;
	 * een agent die zich vergist in een sleutelnaam zou daar zonder deze lijst
	 * zo in kunnen schrijven, en er is geen revisie om dat mee terug te halen.
	 */
	const TYPOGRAFIE_SLEUTELS = array(
		'base_font',
		'heading_font',
		'h1_font',
		'h2_font',
		'h3_font',
		'h4_font',
		'h5_font',
		'h6_font',
	);

	/**
	 * Voeg twee typografie-objecten samen, één niveau diep.
	 *
	 * Eén niveau is precies goed. De vorm is {size:{desktop,tablet,mobile}},
	 * dus alleen size meegeven moet family en weight met rust laten — dat is
	 * het eerste niveau. Maar binnen size moet alleen mobile meegeven óók de
	 * desktopwaarde laten staan, en dat is het tweede. Dieper dan dat komt de
	 * vorm niet.
	 *
	 * @param mixed $oud    Wat er staat.
	 * @param mixed $nieuw  Wat erbij komt.
	 *
	 * @return mixed
	 */
	private static function voeg_typografie_samen( $oud, $nieuw ) {
		if ( ! is_array( $oud ) || ! is_array( $nieuw ) ) {
			return $nieuw;
		}

		$uit = $oud;

		foreach ( $nieuw as $sleutel => $waarde ) {
			$uit[ $sleutel ] = ( is_array( $waarde ) && isset( $oud[ $sleutel ] ) && is_array( $oud[ $sleutel ] ) )
				? array_merge( $oud[ $sleutel ], $waarde )
				: $waarde;
		}

		return $uit;
	}

	/**
	 * Schrijf de globale typografie.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function set_global_typography( $input = array() ) {
		if ( ! Kadence_MCP_Capabilities::current_user_can( Kadence_MCP_Capabilities::WRITE ) ) {
			return new WP_Error(
				'kadence_mcp_write_denied',
				__( 'Je hebt de capability kadence_mcp_write niet. Die wordt bij installatie aan niemand gegeven en moet bewust worden toegekend.', 'mcp-abilities-kadence' ),
				array( 'status' => 403 )
			);
		}

		// Theme mods horen bij het uiterlijk van de hele site, niet bij één
		// post. edit_posts is daar niet het juiste recht voor.
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error(
				'kadence_mcp_theme_denied',
				__( 'Je mag de thema-instellingen van deze site niet wijzigen (edit_theme_options).', 'mcp-abilities-kadence' ),
				array( 'status' => 403 )
			);
		}

		$voorstel = isset( $input['typography'] ) && is_array( $input['typography'] ) ? $input['typography'] : array();

		if ( empty( $voorstel ) ) {
			return new WP_Error( 'kadence_mcp_no_typography', __( 'Geef in typography op welke sleutels moeten wijzigen.', 'mcp-abilities-kadence' ) );
		}

		$onbekend = array_diff( array_keys( $voorstel ), self::TYPOGRAFIE_SLEUTELS );

		if ( ! empty( $onbekend ) ) {
			return new WP_Error(
				'kadence_mcp_unknown_typography_key',
				sprintf(
					/* translators: 1: unknown keys, 2: allowed keys. */
					__( 'Onbekende sleutels: %1$s. Toegestaan zijn alleen %2$s. Theme mods bevatten ook de header, de kleuren en de layout; daarom staat hier een vaste lijst en geen prefixcontrole.', 'mcp-abilities-kadence' ),
					implode( ', ', $onbekend ),
					implode( ', ', self::TYPOGRAFIE_SLEUTELS )
				)
			);
		}

		$voor      = array();
		$na        = array();
		$gewijzigd = array();

		foreach ( $voorstel as $sleutel => $waarde ) {
			$huidig            = get_theme_mod( $sleutel );
			$voor[ $sleutel ]  = $huidig;
			$samen             = self::voeg_typografie_samen( $huidig, $waarde );
			$na[ $sleutel ]    = $samen;

			if ( wp_json_encode( $huidig ) !== wp_json_encode( $samen ) ) {
				$gewijzigd[] = array( 'key' => $sleutel, 'from' => $huidig, 'to' => $samen );
			}
		}

		// Het token bindt aan de HUIDIGE waarden plus het voorstel. Is er
		// tussendoor iets veranderd in de Customizer, dan klopt het token niet
		// meer — dezelfde bescherming als de wijzigingsdatum bij een post, maar
		// theme mods hebben zo'n datum niet.
		$verwacht = 'kmcp1_' . substr(
			wp_hash( (string) wp_json_encode( array( 'wat' => 'typografie', 'voor' => $voor, 'na' => $na ) ) ),
			0,
			32
		);

		$token = isset( $input['token'] ) ? (string) $input['token'] : '';

		$rapport = array(
			'before'  => (object) $voor,
			'after'   => (object) $na,
			'changed' => $gewijzigd,
		);

		if ( '' === $token ) {
			return array_merge(
				$rapport,
				array(
					'written' => false,
					'token'   => $verwacht,
					'status'  => empty( $gewijzigd )
						? __( 'Voorstel, er is NIETS opgeslagen — en er zou ook niets veranderen: deze waarden staan er al.', 'mcp-abilities-kadence' )
						: sprintf(
							/* translators: %d: number of keys. */
							__( 'Voorstel, er is NIETS opgeslagen. Er zouden %d themasleutels wijzigen. Theme mods kennen geen revisies, dus bewaar het veld before voordat je doorgaat. Roep opnieuw aan met het token om te schrijven.', 'mcp-abilities-kadence' ),
							count( $gewijzigd )
						),
				)
			);
		}

		if ( ! hash_equals( $verwacht, $token ) ) {
			return new WP_Error(
				'kadence_mcp_bad_token',
				__( 'Het token klopt niet. Of het voorstel is anders dan wat er getoetst is, of de thema-instellingen zijn intussen gewijzigd. Vraag een nieuw voorstel aan.', 'mcp-abilities-kadence' )
			);
		}

		foreach ( $na as $sleutel => $waarde ) {
			set_theme_mod( $sleutel, $waarde );
		}

		// Teruglezen: opgeslagen is niet hetzelfde als opgeslagen zoals bedoeld.
		$controle  = array();
		$afwijking = array();

		foreach ( $na as $sleutel => $waarde ) {
			$controle[ $sleutel ] = get_theme_mod( $sleutel );

			if ( wp_json_encode( $controle[ $sleutel ] ) !== wp_json_encode( $waarde ) ) {
				$afwijking[] = $sleutel;
			}
		}

		return array_merge(
			$rapport,
			array(
				'after'   => (object) $controle,
				'written' => empty( $afwijking ),
				'token'   => '',
				'status'  => empty( $afwijking )
					? __( 'geschreven en teruggelezen: wat er staat komt overeen met wat er bedoeld was. Er is GEEN revisie — theme mods kennen die niet. Terugdraaien kan alleen met de waarden uit before.', 'mcp-abilities-kadence' )
					: sprintf(
						/* translators: %s: the keys that differ. */
						__( 'LET OP: er is geschreven, maar bij het teruglezen wijken deze sleutels af: %s. Waarschijnlijk heeft een filter of een sanitizer ingegrepen. Controleer het veld after.', 'mcp-abilities-kadence' ),
						implode( ', ', $afwijking )
					),
			)
		);
	}

	/**
	 * Schrijf de Extra CSS van de Customizer.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function set_site_css( $input = array() ) {
		if ( ! Kadence_MCP_Capabilities::current_user_can( Kadence_MCP_Capabilities::WRITE ) ) {
			return new WP_Error(
				'kadence_mcp_write_denied',
				__( 'Je hebt de capability kadence_mcp_write niet. Die wordt bij installatie aan niemand gegeven en moet bewust worden toegekend.', 'mcp-abilities-kadence' ),
				array( 'status' => 403 )
			);
		}

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error(
				'kadence_mcp_theme_denied',
				__( 'Je mag de thema-instellingen van deze site niet wijzigen (edit_theme_options).', 'mcp-abilities-kadence' ),
				array( 'status' => 403 )
			);
		}

		if ( ! function_exists( 'wp_get_custom_css' ) || ! function_exists( 'wp_update_custom_css_post' ) ) {
			return new WP_Error(
				'kadence_mcp_no_custom_css',
				__( 'Deze WordPress-installatie kent de Extra CSS van de Customizer niet.', 'mcp-abilities-kadence' )
			);
		}

		$css    = isset( $input['css'] ) ? (string) $input['css'] : '';
		$append = ! empty( $input['append'] );
		$voor   = (string) wp_get_custom_css();

		if ( '' === trim( $css ) && ! $append ) {
			return new WP_Error(
				'kadence_mcp_css_empty',
				__( 'Lege CSS zou alles wat er staat wissen. Wil je dat echt, geef dan een spatie mee; wil je iets toevoegen, zet append aan.', 'mcp-abilities-kadence' )
			);
		}

		$na = $append ? rtrim( $voor ) . "\n\n" . $css : $css;

		$verwacht = 'kmcp1_' . substr(
			wp_hash( (string) wp_json_encode( array( 'wat' => 'sitecss', 'voor' => $voor, 'na' => $na ) ) ),
			0,
			32
		);

		$token = isset( $input['token'] ) ? (string) $input['token'] : '';

		if ( '' === $token ) {
			return array(
				'before'  => $voor,
				'after'   => $na,
				'written' => false,
				'token'   => $verwacht,
				'status'  => sprintf(
					/* translators: 1: current length, 2: proposed length. */
					__( 'Voorstel, er is NIETS opgeslagen. Er staat nu %1$d tekens CSS, het worden er %2$d. Bewaar het veld before: dit is de enige manier om terug te draaien. Roep opnieuw aan met het token om te schrijven.', 'mcp-abilities-kadence' ),
					strlen( $voor ),
					strlen( $na )
				),
			);
		}

		if ( ! hash_equals( $verwacht, $token ) ) {
			return new WP_Error(
				'kadence_mcp_bad_token',
				__( 'Het token klopt niet. Of het voorstel is anders dan wat er getoetst is, of de Extra CSS is intussen gewijzigd. Vraag een nieuw voorstel aan.', 'mcp-abilities-kadence' )
			);
		}

		$resultaat = wp_update_custom_css_post( $na );

		if ( is_wp_error( $resultaat ) ) {
			return $resultaat;
		}

		$controle = (string) wp_get_custom_css();

		return array(
			'before'  => $voor,
			'after'   => $controle,
			'written' => ( $controle === $na ),
			'token'   => '',
			'status'  => ( $controle === $na )
				? __( 'geschreven en teruggelezen: wat er staat komt overeen met wat er bedoeld was. WordPress bewaart de vorige versie als revisie van de custom_css-post.', 'mcp-abilities-kadence' )
				: __( 'LET OP: er is geschreven, maar bij het teruglezen wijkt de CSS af van wat er verstuurd is. Waarschijnlijk heeft de sanitizer van WordPress ingegrepen. Vergelijk after met wat je bedoelde.', 'mcp-abilities-kadence' ),
		);
	}

	/**
	 * Som de Kadence-posttypes en hun posts op.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array
	 */
	public static function list_entities( $input = array() ) {
		$gevraagd = isset( $input['post_types'] ) && is_array( $input['post_types'] )
			? array_map( 'strval', $input['post_types'] )
			: array();

		$status = isset( $input['status'] ) ? (string) $input['status'] : 'any';
		$limiet = isset( $input['per_type_limit'] ) ? (int) $input['per_type_limit'] : 50;
		$limiet = max( 1, min( 200, $limiet ) );

		$uitvoer = array();

		foreach ( Kadence_MCP_Inventory::get_post_types() as $naam => $object ) {
			if ( ! empty( $gevraagd ) && ! in_array( $naam, $gevraagd, true ) ) {
				continue;
			}

			$posts = get_posts(
				array(
					'post_type'        => $naam,
					'post_status'      => $status,
					'numberposts'      => $limiet,
					'orderby'          => 'modified',
					'order'            => 'DESC',
					'suppress_filters' => false,
				)
			);

			$items      = array();
			$onleesbaar = 0;

			foreach ( $posts as $post ) {
				// Dezelfde regel als bij inspect-post: de tool mogen gebruiken
				// is niet hetzelfde als elke post mogen zien. Wel tellen: een
				// post die er is maar niet leesbaar, zag er tot 1.26.0 uit als
				// een post die er niet is — en werd dan opnieuw aangemaakt.
				if ( ! current_user_can( 'read_post', $post->ID ) ) {
					$onleesbaar++;
					continue;
				}

				$items[] = array(
					'id'       => $post->ID,
					'title'    => get_the_title( $post ),
					'slug'     => $post->post_name,
					'status'   => $post->post_status,
					'modified' => $post->post_modified_gmt,
				);
			}

			$uitvoer[] = array(
				'post_type' => $naam,
				'label'     => isset( $object->labels->name ) ? $object->labels->name : $naam,
				'public'    => (bool) $object->public,
				'returned'  => count( $items ),
				'unreadable' => $onleesbaar,
				'items'     => $items,
			);
		}

		$totaal     = array_sum( wp_list_pluck( $uitvoer, 'returned' ) );
		$verborgen  = array_sum( wp_list_pluck( $uitvoer, 'unreadable' ) );
		$onbekend = array_values( array_diff( $gevraagd, wp_list_pluck( $uitvoer, 'post_type' ) ) );

		$status = '';

		if ( ! empty( $onbekend ) ) {
			$status = sprintf(
				/* translators: %s: comma-separated post type names. */
				__( 'let op — deze gevraagde posttypes bestaan niet op deze site: %s. Laat post_types weg om te zien wat er wél is.', 'mcp-abilities-kadence' ),
				implode( ', ', $onbekend )
			);
		} elseif ( empty( $uitvoer ) ) {
			$status = __( 'leeg — er is geen enkel Kadence-posttype geregistreerd. Draait Kadence Blocks wel?', 'mcp-abilities-kadence' );
		} elseif ( 0 === $totaal ) {
			$status = __( 'leeg — de posttypes bestaan wel maar bevatten geen posts die deze rol mag lezen. Bij concepten is daar edit_theme_options of edit_posts voor nodig.', 'mcp-abilities-kadence' );
		} else {
			$status = sprintf(
				/* translators: 1: number of posts, 2: number of post types. */
				__( '%1$d posts gevonden in %2$d posttypes.', 'mcp-abilities-kadence' ),
				$totaal,
				count( $uitvoer )
			);
		}

		if ( $verborgen > 0 ) {
			$status .= ' ' . sprintf(
				/* translators: %d: number of posts. */
				__( 'LET OP: %d posts bestaan wel maar zijn voor dit account niet leesbaar (unreadable per posttype). Maak ze niet opnieuw aan; kijk met check-access welk recht er ontbreekt.', 'mcp-abilities-kadence' ),
				$verborgen
			);
		}

		return array(
			'post_types' => $uitvoer,
			'status'     => $status,
		);
	}

	/**
	 * Zoek posts op titel of slug.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array
	 */
	public static function find_post( $input = array() ) {
		$unique_id = isset( $input['unique_id'] ) ? trim( (string) $input['unique_id'] ) : '';

		if ( '' !== $unique_id ) {
			return self::find_post_op_unique_id( $unique_id, isset( $input['limit'] ) ? (int) $input['limit'] : 20 );
		}

		$slug  = isset( $input['slug'] ) ? trim( (string) $input['slug'] ) : '';
		$zoek  = isset( $input['search'] ) ? trim( (string) $input['search'] ) : '';
		$limit = isset( $input['limit'] ) ? (int) $input['limit'] : 20;
		$limit = max( 1, min( 100, $limit ) );

		$types = isset( $input['post_type'] ) && is_array( $input['post_type'] ) && ! empty( $input['post_type'] )
			? array_map( 'strval', $input['post_type'] )
			: array_values(
				array_unique(
					array_merge(
						array_keys( get_post_types( array( 'public' => true ), 'names' ) ),
						array_keys( Kadence_MCP_Inventory::get_post_types() )
					)
				)
			);

		$args = array(
			'post_type'        => $types,
			'post_status'      => isset( $input['status'] ) ? (string) $input['status'] : 'any',
			'numberposts'      => $limit,
			'orderby'          => 'modified',
			'order'            => 'DESC',
			'suppress_filters' => false,
		);

		if ( '' !== $slug ) {
			$args['name'] = sanitize_title( $slug );
		} elseif ( '' !== $zoek ) {
			$args['s'] = $zoek;
		}

		$posts = get_posts( $args );
		$items = array();

		foreach ( $posts as $post ) {
			// Dezelfde regel als overal: de tool mogen gebruiken is niet
			// hetzelfde als elke post mogen zien.
			if ( ! current_user_can( 'read_post', $post->ID ) ) {
				continue;
			}

			$items[] = array(
				'id'        => $post->ID,
				'title'     => get_the_title( $post ),
				'slug'      => $post->post_name,
				'post_type' => $post->post_type,
				'status'    => $post->post_status,
				'modified'  => $post->post_modified_gmt,
			);
		}

		if ( empty( $items ) ) {
			$status = '' === $slug && '' === $zoek
				? __( 'leeg — er is niet gezocht en er zijn geen recente posts leesbaar. Geef search of slug op.', 'mcp-abilities-kadence' )
				: __( 'leeg — geen treffers. Let op dat search op de titel zoekt en slug exact moet zijn. Concepten verschijnen alleen als de rol ze mag lezen.', 'mcp-abilities-kadence' );
		} else {
			$status = sprintf(
				/* translators: %d: number of posts. */
				__( '%d treffers. Gebruik het id met inspect-post.', 'mcp-abilities-kadence' ),
				count( $items )
			);
		}

		return array(
			'posts'  => $items,
			'status' => $status,
		);
	}

	/**
	 * Zoek de posts waarin een blok met deze uniqueID staat.
	 *
	 * Een uniqueID blijft gelijk als een post naar een andere site gaat; het
	 * post-ID niet. Hiermee vind je het tegenstuk op staging zonder ID-kaart.
	 * Kadence bakt het post-ID van de bron in de uniqueID ("306_…"), dus die
	 * wijst na een overzetting niet naar de juiste post — deze zoektocht wel.
	 *
	 * @param string $unique_id De uniqueID.
	 * @param int    $limit     Maximaal aantal treffers.
	 *
	 * @return array
	 */
	private static function find_post_op_unique_id( $unique_id, $limit ) {
		global $wpdb;

		$limit = max( 1, min( 100, $limit ) );
		$rijen = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status NOT IN ('trash','auto-draft','inherit') AND post_type <> 'revision' AND post_content LIKE %s ORDER BY post_modified_gmt DESC LIMIT %d",
				'%' . $wpdb->esc_like( '"uniqueID":"' . $unique_id . '"' ) . '%',
				$limit
			)
		);
		$items = array();

		foreach ( $rijen as $rij ) {
			$post = get_post( (int) $rij->ID );

			if ( ! $post || ! current_user_can( 'read_post', $post->ID ) ) {
				continue;
			}

			$blok = Kadence_MCP_Inventory::zoek_op_unique_id( parse_blocks( $post->post_content ), $unique_id );

			// De LIKE vindt ook een uniqueID in een attribuutwaarde van een
			// ander blok; alleen een echt blok telt.
			if ( null === $blok ) {
				continue;
			}

			$items[] = array(
				'id'        => $post->ID,
				'title'     => get_the_title( $post ),
				'slug'      => $post->post_name,
				'post_type' => $post->post_type,
				'status'    => $post->post_status,
				'modified'  => $post->post_modified_gmt,
				'block'     => isset( $blok['blockName'] ) ? (string) $blok['blockName'] : '',
			);
		}

		return array(
			'posts'  => $items,
			'status' => empty( $items )
				? sprintf(
					/* translators: %s: uniqueID. */
					__( 'leeg — geen blok met uniqueID "%s" op deze site, of niet leesbaar voor dit account.', 'mcp-abilities-kadence' ),
					$unique_id
				)
				: sprintf(
					/* translators: 1: number of posts, 2: uniqueID. */
					__( '%1$d post(s) met een blok "%2$s". Staat hij in meer dan één post, dan is er gedupliceerd; kies op post_type en titel.', 'mcp-abilities-kadence' ),
					count( $items ),
					$unique_id
				),
		);
	}

	/**
	 * De Kadence-post-meta van één post.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function get_post_meta_ability( $input = array() ) {
		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		$post    = $post_id > 0 ? get_post( $post_id ) : null;

		if ( ! $post ) {
			return new WP_Error(
				'kadence_mcp_post_not_found',
				sprintf(
					/* translators: %d: post ID. */
					__( 'Er bestaat geen post met ID %d.', 'mcp-abilities-kadence' ),
					$post_id
				)
			);
		}

		/** This filter is documented in includes/abilities/class-kadence-mcp-abilities-content.php */
		if ( ! apply_filters( 'kadence_mcp_can_read_post', current_user_can( 'read_post', $post_id ), $post_id ) ) {
			return new WP_Error(
				'kadence_mcp_post_forbidden',
				sprintf(
					/* translators: %d: post ID. */
					__( 'Geen leesrecht op post %d.', 'mcp-abilities-kadence' ),
					$post_id
				)
			);
		}

		$resultaat = Kadence_MCP_Inventory::get_kadence_meta( $post_id );
		$meta      = $resultaat['meta'];

		if ( isset( $input['keys'] ) && is_array( $input['keys'] ) && ! empty( $input['keys'] ) ) {
			$meta = array_intersect_key( $meta, array_flip( array_map( 'strval', $input['keys'] ) ) );
		}

		if ( empty( $meta ) ) {
			$status = 0 === $resultaat['withheld']
				? __( 'leeg — deze post heeft geen post meta. Dat is normaal voor een gewone pagina; Kadence-meta bestaat vooral op kadence_query, kadence_query_card en kadence_header.', 'mcp-abilities-kadence' )
				: sprintf(
					/* translators: %d: number of withheld keys. */
					__( 'leeg — deze post heeft wel %d meta-sleutels, maar geen daarvan is van Kadence. Alleen sleutels op _kad_, _kt_ of kadence_ worden teruggegeven.', 'mcp-abilities-kadence' ),
					$resultaat['withheld']
				);
		} else {
			$status = sprintf(
				/* translators: 1: number of keys, 2: number withheld. */
				__( '%1$d Kadence-sleutels; %2$d sleutels van andere plugins zijn weggelaten.', 'mcp-abilities-kadence' ),
				count( $meta ),
				$resultaat['withheld']
			);
		}

		return array(
			'post' => array(
				'id'        => $post->ID,
				'title'     => get_the_title( $post ),
				'post_type' => $post->post_type,
			),
			'meta'     => $meta,
			'withheld' => $resultaat['withheld'],
			'status'   => $status,
		);
	}

	/**
	 * Zoek posts die naar een Kadence-object verwijzen.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function find_usages( $input = array() ) {
		$object_id = isset( $input['object_id'] ) ? (int) $input['object_id'] : 0;

		if ( $object_id <= 0 ) {
			return new WP_Error(
				'kadence_mcp_missing_object_id',
				__( 'Geef het post-ID op van het object waarnaar je zoekt.', 'mcp-abilities-kadence' )
			);
		}

		$limiet = isset( $input['scan_limit'] ) ? (int) $input['scan_limit'] : 300;
		$limiet = max( 1, min( 1000, $limiet ) );

		$types = isset( $input['post_type'] ) && is_array( $input['post_type'] ) && ! empty( $input['post_type'] )
			? array_map( 'strval', $input['post_type'] )
			: array_values(
				array_unique(
					array_merge(
						array_keys( get_post_types( array( 'public' => true ), 'names' ) ),
						array_keys( Kadence_MCP_Inventory::get_post_types() )
					)
				)
			);

		$posts = get_posts(
			array(
				'post_type'        => $types,
				'post_status'      => 'any',
				'numberposts'      => $limiet,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);

		// Het id staat in de blokmarkup als "id":232. Op die vorm zoeken en
		// niet op het kale getal, anders matcht elk toevallig voorkomen. En met
		// een grens erachter: tot 1.26.0 vond "id":2 ook "id":24 en "id":200.
		$patroon  = '/"id":' . $object_id . '(?![0-9])/';
		$gevonden = array();

		foreach ( $posts as $post ) {
			if ( ! preg_match( $patroon, (string) $post->post_content ) ) {
				continue;
			}

			// Dan per blok: kadence/tab en kadence/slide gebruiken "id" als
			// volgnummer (1, 2, 3 …), niet als verwijzing. Zonder deze stap werd
			// elke pagina met een tweede tab een "gebruik" van post 2.
			$hits = self::tel_id_verwijzingen( parse_blocks( $post->post_content ), $object_id );

			if ( ! $hits ) {
				continue;
			}

			if ( ! current_user_can( 'read_post', $post->ID ) ) {
				continue;
			}

			$gevonden[] = array(
				'id'        => $post->ID,
				'title'     => get_the_title( $post ),
				'post_type' => $post->post_type,
				'status'    => $post->post_status,
				'hits'      => $hits,
			);
		}

		$gescand  = count( $posts );
		$begrensd = $gescand >= $limiet;

		if ( empty( $gevonden ) ) {
			$status = $begrensd
				? sprintf(
					/* translators: %d: number of posts scanned. */
					__( 'geen treffers, maar de scan is begrensd op %d posts — dat is mogelijk niet de hele site. Verhoog scan_limit of beperk post_type.', 'mcp-abilities-kadence' ),
					$gescand
				)
				: sprintf(
					/* translators: %d: number of posts scanned. */
					__( 'geen treffers in alle %d gescande posts. Dit object wordt nergens via een id-attribuut opgenomen.', 'mcp-abilities-kadence' ),
					$gescand
				);
		} else {
			$status = sprintf(
				/* translators: 1: number of matches, 2: number scanned. */
				__( '%1$d posts verwijzen naar dit object, van %2$d gescande posts.', 'mcp-abilities-kadence' ),
				count( $gevonden ),
				$gescand
			) . ( $begrensd ? ' ' . __( 'De scan was begrensd, dus er kunnen er meer zijn.', 'mcp-abilities-kadence' ) : '' );
		}

		return array(
			'usages'  => $gevonden,
			'scanned' => $gescand,
			'status'  => $status,
		);
	}

	/**
	 * Blokken die "id" als volgnummer gebruiken en niet als verwijzing naar een post.
	 */
	const ID_ALS_VOLGNUMMER = array( 'kadence/tab', 'kadence/slide', 'kadence/pane' );

	/**
	 * Tel de blokken waarvan het attribuut id naar dit object wijst.
	 *
	 * @param array $blokken   De boom.
	 * @param int   $object_id Het object.
	 *
	 * @return int
	 */
	private static function tel_id_verwijzingen( $blokken, $object_id ) {
		$aantal = 0;

		foreach ( $blokken as $blok ) {
			$naam = isset( $blok['blockName'] ) ? (string) $blok['blockName'] : '';

			if ( '' !== $naam
				&& ! in_array( $naam, self::ID_ALS_VOLGNUMMER, true )
				&& isset( $blok['attrs']['id'] )
				&& is_numeric( $blok['attrs']['id'] )
				&& (int) $blok['attrs']['id'] === (int) $object_id ) {
				$aantal++;
			}

			if ( ! empty( $blok['innerBlocks'] ) ) {
				$aantal += self::tel_id_verwijzingen( $blok['innerBlocks'], $object_id );
			}
		}

		return $aantal;
	}

	/**
	 * De groepen die vóór de pipe in een `field` mogen staan.
	 *
	 * Vaste lijst uit kadence-blocks-pro/includes/dynamic-content/class-kadence-blocks-pro-dynamic-content.php:29,
	 * aangevuld met de prefixen voor externe veldenbronnen uit
	 * class-kadence-blocks-dynamic-content-controller.php:1832.
	 */
	const BRON_GROEPEN = array(
		'post', 'archive', 'author', 'site', 'user', 'comments', 'media',
		'relationship', 'repeater', 'mb_repeater', 'acf_repeater', 'woo',
		'tec', 'other', 'time', 'url',
		'acf_meta', 'acf_option', 'mb_meta', 'mb_option', 'pod_meta', 'pod_option',
	);

	/**
	 * Controleer alle verwijzingen in een post.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function check_bindings( $input = array() ) {
		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		$post    = $post_id > 0 ? get_post( $post_id ) : null;

		if ( ! $post ) {
			return new WP_Error(
				'kadence_mcp_post_not_found',
				sprintf(
					/* translators: %d: post ID. */
					__( 'Er bestaat geen post met ID %d.', 'mcp-abilities-kadence' ),
					$post_id
				)
			);
		}

		/** This filter is documented in includes/abilities/class-kadence-mcp-abilities-content.php */
		if ( ! apply_filters( 'kadence_mcp_can_read_post', current_user_can( 'read_post', $post_id ), $post_id ) ) {
			return new WP_Error( 'kadence_mcp_post_forbidden', __( 'Geen leesrecht op deze post.', 'mcp-abilities-kadence' ) );
		}

		$bindingen = array();

		// Een query card en een Element (als kaart in een Post Grid) renderen
		// per resultaat; daar gelden de lusregels.
		self::$in_lus = in_array( $post->post_type, array( 'kadence_query_card', 'kadence_element' ), true );

		self::loop_blokken( parse_blocks( $post->post_content ), $bindingen );
		self::lees_query_meta( $post_id, $bindingen );

		$kapot    = 0;
		$onbekend = 0;
		$waarsch  = 0;

		foreach ( $bindingen as $b ) {
			if ( 'dangelt' === $b['status'] ) {
				++$kapot;
			} elseif ( 'onverifieerbaar' === $b['status'] ) {
				++$onbekend;
			} elseif ( 'waarschuwing' === $b['status'] ) {
				++$waarsch;
			}
		}

		if ( empty( $bindingen ) ) {
			$status = __( 'leeg — in deze post staan geen dynamische verwijzingen, taxonomiefilters of verwijzingen naar Kadence-objecten. Dat is normaal voor een pagina met alleen statische blokken.', 'mcp-abilities-kadence' );
		} elseif ( $kapot > 0 ) {
			$status = sprintf(
				/* translators: 1: broken, 2: total, 3: unverifiable. */
				__( '%1$d van %2$d verwijzingen dangelt, %3$d zijn niet te verifiëren. LET OP: Kadence logt hier niets over en toont geen foutmelding — een kapotte verwijzing laat het blok gewoon verdwijnen of toont blogberichten.', 'mcp-abilities-kadence' ),
				$kapot,
				count( $bindingen ),
				$onbekend
			);
		} else {
			$status = sprintf(
				/* translators: 1: total, 2: unverifiable. */
				__( 'alle %1$d verwijzingen wijzen naar iets dat bestaat; %2$d zijn niet te verifiëren zonder te weten welk posttype ze moeten dragen.', 'mcp-abilities-kadence' ),
				count( $bindingen ),
				$onbekend
			);
		}

		if ( $waarsch > 0 ) {
			$status .= ' ' . sprintf(
				/* translators: %d: number of warnings. */
				__( '%d waarschuwingen (status waarschuwing): de verwijzing bestaat, maar zal in de praktijk niet doen wat je verwacht — lees de note.', 'mcp-abilities-kadence' ),
				$waarsch
			);
		}

		return array(
			'post'     => array(
				'id'        => $post->ID,
				'title'     => get_the_title( $post ),
				'post_type' => $post->post_type,
			),
			'bindings' => $bindingen,
			'broken'   => $kapot,
			'warnings' => $waarsch,
			'status'   => $status,
		);
	}

	/**
	 * Rendert de post die check-bindings naloopt per resultaat van een lus?
	 *
	 * @var bool
	 */
	private static $in_lus = false;

	/**
	 * Loop de blokkenboom af en verzamel verwijzingen uit vier opslagplaatsen.
	 *
	 * @param array $blokken   De blokken.
	 * @param array $bindingen Verzamelaar.
	 *
	 * @return void
	 */
	private static function loop_blokken( $blokken, &$bindingen ) {
		foreach ( $blokken as $blok ) {
			$naam  = isset( $blok['blockName'] ) ? (string) $blok['blockName'] : '';
			$attrs = isset( $blok['attrs'] ) && is_array( $blok['attrs'] ) ? $blok['attrs'] : array();
			$uid   = isset( $attrs['uniqueID'] ) ? (string) $attrs['uniqueID'] : '';

			// 1. Gewone attributen op de dynamische blokken.
			if ( isset( $attrs['field'] ) && is_string( $attrs['field'] ) && '' !== $attrs['field'] ) {
				$bindingen[] = self::toets_field( $naam, $uid, 'attribuut field', $attrs['field'] );
			}

			if ( isset( $attrs['tax'] ) && is_string( $attrs['tax'] ) && '' !== $attrs['tax'] ) {
				$bindingen[] = self::toets_taxonomie( $naam, $uid, 'attribuut tax', $attrs['tax'] );
			}

			foreach ( array( 'metaField', 'customMeta' ) as $sleutel ) {
				if ( isset( $attrs[ $sleutel ] ) && is_string( $attrs[ $sleutel ] ) && '' !== $attrs[ $sleutel ] ) {
					$bindingen[] = array(
						'block'     => $naam,
						'unique_id' => $uid,
						'where'     => 'attribuut ' . $sleutel,
						'reference' => $attrs[ $sleutel ],
						'status'    => 'onverifieerbaar',
						'note'      => __( 'een kale meta-sleutel is niet te toetsen zonder te weten welk posttype hem hoort te dragen. Kadence leest hem met get_field() of get_post_meta(), en die falen nooit — ze geven gewoon niets terug.', 'mcp-abilities-kadence' ),
					);
				}
			}

			// 2. Het kadenceDynamic-object, per slot.
			if ( isset( $attrs['kadenceDynamic'] ) && is_array( $attrs['kadenceDynamic'] ) ) {
				foreach ( $attrs['kadenceDynamic'] as $slot => $conf ) {
					// Een dynamische achtergrond in een lus zonder inQueryBlock:
					// Kadence plakt dan geen post-ID aan de klasse, en alle
					// kaarten delen één CSS-regel en dus één foto. De editor
					// haalt de vlag bovendien weg zodra het object los wordt
					// opgeslagen (kadence-blocks-pro, query-rest-api.php).
					if ( ( self::$in_lus || 'kadence/query-card' === $naam ) && is_array( $conf ) && ! empty( $conf['enable'] ) && 0 === strpos( (string) $slot, 'backgroundImg' ) && empty( $attrs['inQueryBlock'] ) ) {
						$bindingen[] = array(
							'block'     => $naam,
							'unique_id' => $uid,
							'where'     => 'kadenceDynamic.' . $slot,
							'reference' => isset( $conf['field'] ) ? (string) $conf['field'] : '',
							'status'    => 'waarschuwing',
							'note'      => __( 'dynamische achtergrond in een lus zonder inQueryBlock: alle kaarten krijgen dezelfde foto. Zet inQueryBlock: true op dit blok — en weet dat de editor die vlag weghaalt zodra dit object los wordt opgeslagen; een filter kadence_blocks_in_query_block in het thema is duurzamer.', 'mcp-abilities-kadence' ),
						);
					}

					if ( ! is_array( $conf ) || empty( $conf['enable'] ) || empty( $conf['field'] ) ) {
						continue;
					}

					$bindingen[] = self::toets_field( $naam, $uid, 'kadenceDynamic.' . $slot, (string) $conf['field'] );
				}
			}

			// 3. Dezelfde instelling nog eens als shortcode in het doelattribuut.
			//    De editor schrijft beide weg en ze kunnen uiteenlopen; op de
			//    productiesite stond in het link-attribuut een shortcode zonder
			//    de `before` die in kadenceDynamic wél stond.
			foreach ( $attrs as $sleutel => $waarde ) {
				if ( ! is_string( $waarde ) || false === strpos( $waarde, '[kb-dynamic' ) ) {
					continue;
				}

				if ( preg_match( "/field='([^']+)'/", $waarde, $m ) ) {
					$bindingen[] = self::toets_field( $naam, $uid, 'shortcode in attribuut ' . $sleutel, $m[1] );
				}
			}

			// 4. Inline dynamische tekst midden in RichText-HTML.
			$html = isset( $blok['innerHTML'] ) ? (string) $blok['innerHTML'] : '';

			if ( false !== strpos( $html, 'kb-inline-dynamic' ) && preg_match_all( '/data-field="([^"]+)"/', $html, $mm ) ) {
				foreach ( $mm[1] as $veld ) {
					$bindingen[] = self::toets_field( $naam, $uid, 'inline span in de markup', $veld );
				}
			}

			// 5. Verwijzingen naar een los Kadence-object.
			if ( isset( $attrs['id'] ) && is_numeric( $attrs['id'] ) && (int) $attrs['id'] > 0 ) {
				$verwacht = array(
					'kadence/query'      => 'kadence_query',
					'kadence/query-card' => 'kadence_query_card',
					'kadence/navigation' => 'kadence_navigation',
					'kadence/header'     => 'kadence_header',
				);

				if ( isset( $verwacht[ $naam ] ) ) {
					$bindingen[] = self::toets_object( $naam, $uid, (int) $attrs['id'], $verwacht[ $naam ] );
				}
			}

			if ( ! empty( $blok['innerBlocks'] ) ) {
				self::loop_blokken( $blok['innerBlocks'], $bindingen );
			}
		}
	}

	/**
	 * Toets een `groep|veld`-verwijzing.
	 *
	 * @param string $blok Bloknaam.
	 * @param string $uid  uniqueID.
	 * @param string $waar Waar de verwijzing staat.
	 * @param string $ref  De verwijzing.
	 *
	 * @return array
	 */
	private static function toets_field( $blok, $uid, $waar, $ref ) {
		$regel = array(
			'block'     => $blok,
			'unique_id' => $uid,
			'where'     => $waar,
			'reference' => $ref,
			'status'    => 'ok',
			'note'      => '',
		);

		$delen = explode( '|', $ref );
		$groep = $delen[0];
		$veld  = isset( $delen[1] ) ? $delen[1] : '';

		if ( ! in_array( $groep, self::BRON_GROEPEN, true ) ) {
			$regel['status'] = 'dangelt';
			$regel['note']   = sprintf(
				/* translators: %s: group name. */
				__( 'de groep "%s" vóór de pipe bestaat niet. Een onbekende groep eindigt in de default-tak en levert een lege string op, waarna het hele blok niet rendert — zonder spoor.', 'mcp-abilities-kadence' ),
				$groep
			);

			return $regel;
		}

		// ACF koppelt op veldNAAM, niet op field_key. acf_get_field() accepteert
		// naam, key of ID en is de enige echte bestaanscontrole; get_field()
		// faalt nooit en geeft bij een onbekend veld gewoon niets terug.
		if ( in_array( $groep, array( 'acf_meta', 'acf_option', 'acf_repeater' ), true ) ) {
			if ( ! function_exists( 'acf_get_field' ) ) {
				$regel['status'] = 'onverifieerbaar';
				$regel['note']   = __( 'ACF is niet actief, dus dit veld is niet te toetsen.', 'mcp-abilities-kadence' );

				return $regel;
			}

			$gevonden = acf_get_field( $veld );

			if ( ! $gevonden ) {
				$regel['status'] = 'dangelt';
				$regel['note']   = sprintf(
					/* translators: %s: field name. */
					__( 'er bestaat geen ACF-veld met de naam "%s". Let op dat Kadence op NAAM koppelt en niet op field_key; hernoemen van een veld breekt deze verwijzing stil.', 'mcp-abilities-kadence' ),
					$veld
				);
			} else {
				$regel['note'] = __( 'het ACF-veld bestaat. Of het aan dít posttype hangt is hiermee niet gezegd — acf_get_field() zegt daar niets over.', 'mcp-abilities-kadence' );
			}

			return $regel;
		}

		if ( '' === $veld ) {
			$regel['status'] = 'dangelt';
			$regel['note']   = __( 'er staat een groep maar geen veldnaam achter de pipe.', 'mcp-abilities-kadence' );

			return $regel;
		}

		$regel['note'] = __( 'de groep is geldig. De veldnaam zelf is niet te toetsen zonder de volledige veldenlijst van Kadence Pro.', 'mcp-abilities-kadence' );

		if ( 'post_custom_field' === $veld ) {
			$regel['status'] = 'onverifieerbaar';
			$regel['note']   = __( 'dit is de drietrapsvariant: de echte sleutel staat in metaField, en als die op kb_custom_input staat weer in customMeta. Beide zijn kale meta-sleutels en niet te toetsen.', 'mcp-abilities-kadence' );
		}

		return $regel;
	}

	/**
	 * Toets een kale taxonomie-slug.
	 *
	 * @param string $blok Bloknaam.
	 * @param string $uid  uniqueID.
	 * @param string $waar Waar de verwijzing staat.
	 * @param string $slug De taxonomie.
	 *
	 * @return array
	 */
	private static function toets_taxonomie( $blok, $uid, $waar, $slug, $tegen_posttypes = array() ) {
		$regel = array(
			'block'     => $blok,
			'unique_id' => $uid,
			'where'     => $waar,
			'reference' => $slug,
			'status'    => 'ok',
			'note'      => '',
		);

		if ( ! taxonomy_exists( $slug ) ) {
			$regel['status'] = 'dangelt';
			$regel['note']   = sprintf(
				/* translators: %s: taxonomy slug. */
				__( 'de taxonomie "%s" bestaat niet.', 'mcp-abilities-kadence' ),
				$slug
			);

			return $regel;
		}

		$tax = get_taxonomy( $slug );

		if ( $tax && empty( $tax->object_type ) ) {
			$regel['status'] = 'dangelt';
			$regel['note']   = sprintf(
				/* translators: %s: taxonomy slug. */
				__( 'de taxonomie "%s" bestaat maar hangt aan géén enkel posttype. Dit treedt op na het hernoemen van een taxonomie: de termen blijven bestaan met count 0 en elk filter blijft leeg.', 'mcp-abilities-kadence' ),
				$slug
			);

			return $regel;
		}

		$hangt_aan = $tax ? (array) $tax->object_type : array();

		// Bestaan is niet genoeg. Een facet op een taxonomie die wél bestaat maar
		// niet aan het OPGEVRAAGDE posttype hangt levert een filter op dat nooit
		// iets kan tonen. Gevonden in de praktijk: een Query Loop op een eigen posttype
		// met een facet op 'category', en die hangt alleen aan 'post'.
		if ( ! empty( $tegen_posttypes ) ) {
			$overlap = array_intersect( $hangt_aan, $tegen_posttypes );

			if ( empty( $overlap ) ) {
				$regel['status'] = 'dangelt';
				$regel['note']   = sprintf(
					/* translators: 1: taxonomy, 2: attached post types, 3: queried post types. */
					__( 'de taxonomie "%1$s" hangt aan %2$s, maar deze query vraagt %3$s op. Het filter kan dus nooit iets tonen — de taxonomie bestaat wel, maar niet voor deze posts.', 'mcp-abilities-kadence' ),
					$slug,
					implode( ', ', $hangt_aan ),
					implode( ', ', $tegen_posttypes )
				);

				return $regel;
			}
		}

		$regel['note'] = $hangt_aan
			? sprintf(
				/* translators: %s: comma separated post types. */
				__( 'hangt aan: %s', 'mcp-abilities-kadence' ),
				implode( ', ', $hangt_aan )
			)
			: '';

		return $regel;
	}

	/**
	 * Toets een verwijzing naar een los Kadence-object.
	 *
	 * @param string $blok     Bloknaam.
	 * @param string $uid      uniqueID.
	 * @param int    $id       Het post-ID waarnaar verwezen wordt.
	 * @param string $verwacht Het verwachte posttype.
	 *
	 * @return array
	 */
	private static function toets_object( $blok, $uid, $id, $verwacht ) {
		$regel = array(
			'block'     => $blok,
			'unique_id' => $uid,
			'where'     => 'attribuut id',
			'reference' => (string) $id,
			'status'    => 'ok',
			'note'      => '',
		);

		$doel = get_post( $id );

		if ( ! $doel ) {
			$regel['status'] = 'dangelt';
			$regel['note']   = sprintf(
				/* translators: %d: post ID. */
				__( 'post %d bestaat niet. Kadence geeft dan null terug en het blok rendert niets.', 'mcp-abilities-kadence' ),
				$id
			);

			return $regel;
		}

		if ( $doel->post_type !== $verwacht ) {
			$regel['status'] = 'dangelt';
			$regel['note']   = sprintf(
				/* translators: 1: actual type, 2: expected type. */
				__( 'post is van type %1$s terwijl %2$s verwacht wordt. Ook dat geeft null.', 'mcp-abilities-kadence' ),
				$doel->post_type,
				$verwacht
			);

			return $regel;
		}

		if ( 'publish' !== $doel->post_status ) {
			$regel['status'] = 'dangelt';
			$regel['note']   = sprintf(
				/* translators: %s: post status. */
				__( 'het object staat op status "%s". Kadence weigert alles wat niet gepubliceerd is, en ook alles met een wachtwoord.', 'mcp-abilities-kadence' ),
				$doel->post_status
			);

			return $regel;
		}

		$regel['note'] = get_the_title( $doel );

		return $regel;
	}

	/**
	 * Lees de verwijzingen uit de query-meta.
	 *
	 * @param int   $post_id   Het post-ID.
	 * @param array $bindingen Verzamelaar.
	 *
	 * @return void
	 */
	private static function lees_query_meta( $post_id, &$bindingen ) {
		$meta = Kadence_MCP_Inventory::get_kadence_meta( $post_id );
		$q    = isset( $meta['meta']['_kad_query_query'] ) ? $meta['meta']['_kad_query_query'] : null;

		$gevraagde_types = array();

		if ( is_array( $q ) ) {
			$types           = isset( $q['postType'] ) ? (array) $q['postType'] : array();
			$gevraagde_types = array_values( array_filter( array_map( 'strval', $types ) ) );

			foreach ( $types as $type ) {
				$bestaat = post_type_exists( $type );

				$bindingen[] = array(
					'block'     => 'meta',
					'unique_id' => '',
					'where'     => '_kad_query_query.postType',
					'reference' => (string) $type,
					'status'    => $bestaat ? 'ok' : 'dangelt',
					'note'      => $bestaat
						? ''
						: __( 'dit posttype bestaat niet. De Query Loop valt dan stil terug op "post" en toont blogberichten in plaats van niets — de meest misleidende faalwijze in de hele stack.', 'mcp-abilities-kadence' ),
				);
			}

			if ( ! empty( $q['orderMetaKey'] ) ) {
				$bindingen[] = array(
					'block'     => 'meta',
					'unique_id' => '',
					'where'     => '_kad_query_query.orderMetaKey',
					'reference' => (string) $q['orderMetaKey'],
					'status'    => 'onverifieerbaar',
					'note'      => __( 'een sorteersleutel is niet te toetsen op bestaan. Wel belangrijk: sorteren op meta gebruikt een INNER JOIN, dus posts zonder deze sleutel vallen volledig uit de lijst.', 'mcp-abilities-kadence' ),
				);
			}

			$taxen = isset( $q['taxonomy'] ) ? (array) $q['taxonomy'] : array();

			foreach ( $taxen as $t ) {
				$waarde = is_array( $t ) && isset( $t['value'] ) ? (string) $t['value'] : ( is_string( $t ) ? $t : '' );

				if ( '' === $waarde ) {
					continue;
				}

				$slug = explode( '|', $waarde );

				$bindingen[] = self::toets_taxonomie( 'meta', '', '_kad_query_query.taxonomy', $slug[0], $gevraagde_types );
			}
		}

		$facets = isset( $meta['meta']['_kad_query_facets'] ) ? $meta['meta']['_kad_query_facets'] : null;

		if ( is_array( $facets ) ) {
			foreach ( $facets as $facet ) {
				$json = isset( $facet['attributes'] ) ? json_decode( (string) $facet['attributes'], true ) : null;

				if ( ! is_array( $json ) || empty( $json['taxonomy'] ) ) {
					continue;
				}

				$bindingen[] = self::toets_taxonomie( 'meta', isset( $json['uniqueID'] ) ? (string) $json['uniqueID'] : '', '_kad_query_facets.taxonomy', (string) $json['taxonomy'], $gevraagde_types );
			}
		}
	}

	/**
	 * Geef de globale stijlen.
	 *
	 * @param array $input De invoer. Wordt niet gebruikt.
	 *
	 * @return array
	 */
	public static function get_global_styles( $input = array() ) {
		unset( $input );

		return array(
			'environment'    => Kadence_MCP_Inventory::get_environment(),
			'styles'         => Kadence_MCP_Inventory::get_global_styles(),
			'block_defaults' => Kadence_MCP_Inventory::get_block_defaults(),
		);
	}

	/**
	 * Wat mag dit account hier, per soort object?
	 *
	 * Een ontbrekend recht ziet er in de andere tools uit als "bestaat niet"
	 * of als een stille aanpassing: zonder edit_theme_options zijn headers,
	 * elementen, navigaties en vectoren niet te bewerken, en zonder
	 * unfiltered_html haalt WordPress bij het opslaan SVG en scripts weg en
	 * wordt & in een titel &amp;. Op 28-09-2026 leidde dat op staging tot
	 * dubbel aangemaakte vectoren. Deze tool zegt het vooraf.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array
	 */
	public static function check_access( $input = array() ) {
		$gebruiker = wp_get_current_user();
		$types     = isset( $input['post_types'] ) && is_array( $input['post_types'] ) && ! empty( $input['post_types'] )
			? array_map( 'strval', $input['post_types'] )
			: array_values(
				array_unique(
					array_merge(
						array_keys( Kadence_MCP_Inventory::get_post_types() ),
						array_keys( get_post_types( array( 'public' => true ), 'names' ) )
					)
				)
			);

		$rechten = array();

		foreach ( array(
			Kadence_MCP_Capabilities::VIEW => __( 'Kadence MCP lezen', 'mcp-abilities-kadence' ),
			Kadence_MCP_Capabilities::WRITE => __( 'Kadence MCP schrijven (elke schrijfability)', 'mcp-abilities-kadence' ),
			'edit_theme_options' => __( 'headers, elementen, navigaties, vectoren, typografie en Extra CSS', 'mcp-abilities-kadence' ),
			'unfiltered_html'    => __( 'SVG, scripts en & in titels ongeschonden opslaan', 'mcp-abilities-kadence' ),
			'upload_files'       => __( 'media uploaden', 'mcp-abilities-kadence' ),
			'manage_options'     => __( 'instellingen van plugins', 'mcp-abilities-kadence' ),
		) as $cap => $waarvoor ) {
			$rechten[] = array(
				'capability' => $cap,
				'granted'    => 0 === strpos( $cap, 'kadence_mcp_' ) ? Kadence_MCP_Capabilities::current_user_can( $cap ) : current_user_can( $cap ),
				'for'        => $waarvoor,
			);
		}

		$per_type       = array();
		$waarschuwingen = array();

		foreach ( $types as $type ) {
			$object = get_post_type_object( $type );

			if ( ! $object ) {
				continue;
			}

			$caps  = $object->cap;
			$ids   = get_posts(
				array(
					'post_type'        => $type,
					'post_status'      => array( 'publish', 'private', 'draft', 'pending', 'future' ),
					'numberposts'      => 200,
					'fields'           => 'ids',
					'suppress_filters' => true,
				)
			);
			$lees  = 0;
			$bewerk = 0;

			foreach ( $ids as $id ) {
				$lees   += current_user_can( 'read_post', $id ) ? 1 : 0;
				$bewerk += current_user_can( 'edit_post', $id ) ? 1 : 0;
			}

			$regel = array(
				'post_type' => $type,
				'label'     => isset( $object->labels->name ) ? $object->labels->name : $type,
				'posts'     => count( $ids ),
				'readable'  => $lees,
				'editable'  => $bewerk,
				'can_create'  => current_user_can( $caps->create_posts ),
				'can_publish' => current_user_can( $caps->publish_posts ),
				'cap_type'    => $caps->edit_posts,
			);

			if ( $lees < count( $ids ) ) {
				$waarschuwingen[] = sprintf(
					/* translators: 1: post type, 2: hidden, 3: total, 4: capability. */
					__( '%1$s: %2$d van de %3$d posts zijn voor dit account onleesbaar. list-entities en find-post tonen die niet, dus ze lijken er niet te zijn — maak ze dan niet opnieuw aan. Nodig: %4$s.', 'mcp-abilities-kadence' ),
					$type,
					count( $ids ) - $lees,
					count( $ids ),
					$caps->edit_posts
				);
			} elseif ( $bewerk < count( $ids ) ) {
				$waarschuwingen[] = sprintf(
					/* translators: 1: post type, 2: not editable, 3: total, 4: capability. */
					__( '%1$s: %2$d van de %3$d posts zijn wel leesbaar maar niet te bewerken (nodig: %4$s).', 'mcp-abilities-kadence' ),
					$type,
					count( $ids ) - $bewerk,
					count( $ids ),
					$caps->edit_posts
				);
			}

			$per_type[] = $regel;
		}

		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$waarschuwingen[] = __( 'Geen unfiltered_html: WordPress haalt bij het opslaan <svg>, <script> en inline-stijlen door kses, en maakt van & in een titel &amp;. Vectoren en custom SVG\'s kunnen daardoor leeg of beschadigd opgeslagen worden zonder foutmelding.', 'mcp-abilities-kadence' );
		}

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			$waarschuwingen[] = __( 'Geen edit_theme_options: headers, elementen, navigaties, vectoren, de typografie en de Extra CSS zijn niet te bewerken, en concepten daarvan niet te lezen.', 'mcp-abilities-kadence' );
		}

		return array(
			'user'         => array(
				'id'    => $gebruiker->ID,
				'login' => $gebruiker->user_login,
				'roles' => array_values( (array) $gebruiker->roles ),
			),
			'capabilities' => $rechten,
			'post_types'   => $per_type,
			'warnings'     => $waarschuwingen,
			'status'       => empty( $waarschuwingen )
				? __( 'Geen beperkingen gevonden voor de gevraagde posttypes.', 'mcp-abilities-kadence' )
				: sprintf(
					/* translators: %d: number of warnings. */
					__( '%d beperkingen; lees warnings voordat je iets aanmaakt of overzet.', 'mcp-abilities-kadence' ),
					count( $waarschuwingen )
				),
		);
	}

	/**
	 * Welke kleur staat waar.
	 *
	 * Loopt de blokattributen van alle posts af, de _kad-meta van de
	 * Kadence-objecten en de typografie van het thema, en groepeert per waarde:
	 * paletverwijzing (palette3), CSS-variabele (var(--global-palette3),
	 * var(--ol-…)), hex of rgba. Een hex die gelijk is aan een paletkleur wordt
	 * apart gemeld: die ziet er goed uit maar beweegt niet mee als het palet
	 * verandert.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array
	 */
	public static function audit_colors( $input = array() ) {
		global $wpdb;

		$limiet       = isset( $input['scan_limit'] ) ? max( 1, min( 2000, (int) $input['scan_limit'] ) ) : 500;
		$alleen_buiten = ! empty( $input['only_off_palette'] );
		$filter_waarde = isset( $input['value'] ) ? strtolower( trim( (string) $input['value'] ) ) : '';

		$palet_hex = self::palet_hex();
		$treffers  = array();

		$noteer = static function ( $waarde, $waar ) use ( &$treffers, $palet_hex ) {
			$waarde = trim( (string) $waarde );
			$sleutel = strtolower( $waarde );

			if ( ! isset( $treffers[ $sleutel ] ) ) {
				$soort = 'overig';
				$gelijk = '';

				if ( preg_match( '/^palette\d+$/', $sleutel ) ) {
					$soort = 'palet';
				} elseif ( preg_match( '/^var\(\s*--global-palette\d+/', $sleutel ) ) {
					$soort = 'palet-variabele';
				} elseif ( 0 === strpos( $sleutel, 'var(' ) ) {
					$soort = 'variabele';
				} elseif ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/', $sleutel ) ) {
					$soort = 'hex';
					$zes   = strlen( $sleutel ) === 4 ? '#' . $sleutel[1] . $sleutel[1] . $sleutel[2] . $sleutel[2] . $sleutel[3] . $sleutel[3] : substr( $sleutel, 0, 7 );
					$gelijk = isset( $palet_hex[ $zes ] ) ? $palet_hex[ $zes ] : '';
				} elseif ( 0 === strpos( $sleutel, 'rgb' ) ) {
					$soort = 'rgb';
				}

				$treffers[ $sleutel ] = array(
					'value'       => $waarde,
					'kind'        => $soort,
					'same_as'     => $gelijk,
					'count'       => 0,
					'where'       => array(),
				);
			}

			$treffers[ $sleutel ]['count']++;

			if ( count( $treffers[ $sleutel ]['where'] ) < 25 && ! in_array( $waar, $treffers[ $sleutel ]['where'], true ) ) {
				$treffers[ $sleutel ]['where'][] = $waar;
			}
		};

		$is_kleur = static function ( $v ) {
			return is_string( $v ) && (
				preg_match( '/^palette\d+$/', trim( $v ) )
				|| preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', trim( $v ) )
				|| preg_match( '/^rgba?\(/i', trim( $v ) )
				|| preg_match( '/^var\(\s*--[a-z0-9-]+\s*\)$/i', trim( $v ) )
			);
		};

		// Blokattributen.
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_type, post_title, post_content FROM {$wpdb->posts} WHERE post_status IN ('publish','private','draft','future') AND post_type NOT IN ('revision','attachment','nav_menu_item','customize_changeset','oembed_cache','wp_font_face','wp_font_family') AND post_content LIKE %s ORDER BY ID LIMIT %d",
				'%<!-- wp:%',
				$limiet
			)
		);

		foreach ( $posts as $post ) {
			if ( ! current_user_can( 'read_post', $post->ID ) ) {
				continue;
			}

			$loop = static function ( $blokken ) use ( &$loop, $noteer, $is_kleur, $post ) {
				foreach ( $blokken as $blok ) {
					$naam = isset( $blok['blockName'] ) ? (string) $blok['blockName'] : '';

					if ( '' !== $naam && ! empty( $blok['attrs'] ) && is_array( $blok['attrs'] ) ) {
						$uid = isset( $blok['attrs']['uniqueID'] ) ? (string) $blok['attrs']['uniqueID'] : $naam;

						array_walk_recursive(
							$blok['attrs'],
							static function ( $v, $k ) use ( $noteer, $is_kleur, $post, $uid ) {
								if ( $is_kleur( $v ) ) {
									$noteer( $v, $post->post_type . ':' . $post->ID . ' ' . $uid . ' ' . $k );
								}
							}
						);
					}

					if ( ! empty( $blok['innerBlocks'] ) ) {
						$loop( $blok['innerBlocks'] );
					}
				}
			};
			$loop( parse_blocks( $post->post_content ) );
		}

		// Kadence-meta.
		$meta = $wpdb->get_results(
			"SELECT m.post_id, m.meta_key, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_status IN ('publish','private','draft') AND p.post_type <> 'revision' AND m.meta_key LIKE '\\_kad\\_%' AND m.meta_key NOT LIKE '\\_kad\\_font\\_%'"
		);

		foreach ( $meta as $rij ) {
			if ( ! current_user_can( 'read_post', (int) $rij->post_id ) ) {
				continue;
			}

			$waarde = maybe_unserialize( $rij->meta_value );

			if ( is_string( $waarde ) && ( '{' === substr( $waarde, 0, 1 ) || '[' === substr( $waarde, 0, 1 ) ) ) {
				$json   = json_decode( $waarde, true );
				$waarde = null === $json ? $waarde : $json;
			}

			$waar = 'meta:' . $rij->post_id . ' ' . $rij->meta_key;

			if ( is_array( $waarde ) ) {
				array_walk_recursive(
					$waarde,
					static function ( $v ) use ( $noteer, $is_kleur, $waar ) {
						if ( $is_kleur( $v ) ) {
							$noteer( $v, $waar );
						}
					}
				);
			} elseif ( $is_kleur( $waarde ) ) {
				$noteer( $waarde, $waar );
			}
		}

		// Typografie van het thema.
		$stijlen = Kadence_MCP_Inventory::get_global_styles();

		foreach ( $stijlen['typography'] as $sleutel => $waarde ) {
			if ( is_array( $waarde ) && isset( $waarde['color'] ) && $is_kleur( $waarde['color'] ) ) {
				$noteer( $waarde['color'], 'theme_mod ' . $sleutel . '.color' );
			}
		}

		$lijst = array_values( $treffers );

		if ( '' !== $filter_waarde ) {
			$lijst = array_values( array_filter( $lijst, static function ( $t ) use ( $filter_waarde ) {
				return strtolower( $t['value'] ) === $filter_waarde;
			} ) );
		}

		if ( $alleen_buiten ) {
			$lijst = array_values( array_filter( $lijst, static function ( $t ) {
				return in_array( $t['kind'], array( 'hex', 'rgb', 'overig' ), true );
			} ) );
		}

		usort( $lijst, static function ( $a, $b ) {
			return $b['count'] - $a['count'];
		} );

		$hardgecodeerd = count( array_filter( $lijst, static function ( $t ) {
			return 'hex' === $t['kind'] && '' !== $t['same_as'];
		} ) );
		$buiten = count( array_filter( $lijst, static function ( $t ) {
			return 'hex' === $t['kind'] && '' === $t['same_as'];
		} ) );

		return array(
			'palette' => $palet_hex,
			'colors'  => $lijst,
			'scanned' => array(
				'posts' => count( $posts ),
				'meta'  => count( $meta ),
				'limit' => $limiet,
			),
			'status'  => sprintf(
				/* translators: 1: distinct values, 2: hex equal to palette, 3: hex outside palette, 4: posts scanned. */
				__( '%1$d verschillende kleurwaarden; %2$d hex-waarden zijn gelijk aan een paletkleur (hardgecodeerd, bewegen niet mee), %3$d hex-waarden staan buiten het palet. %4$d posts gescand; rgba met een alfa telt als eigen waarde. Wat in CSS-bestanden van het thema staat, zie je hier niet.', 'mcp-abilities-kadence' ),
				count( $lijst ),
				$hardgecodeerd,
				$buiten,
				count( $posts )
			) . ( count( $posts ) >= $limiet ? ' ' . __( 'LET OP: de scanlimiet is bereikt; verhoog scan_limit voor een volledig beeld.', 'mcp-abilities-kadence' ) : '' ),
		);
	}

	/**
	 * Zet kleuren om over de hele site, of over een paar posts.
	 *
	 * Op 28-09-2026 ging dit met eigen WP-CLI- en REST-scripts (menulinks van
	 * #04201A naar palette3, formulierranden, iconen). Hier met één kaart
	 * {oud: nieuw}, een voorstel per post en per meta-sleutel, een token, één
	 * revisie per post en teruglezen. Drie gevallen worden overgeslagen, met
	 * de reden erbij:
	 * - een var(--…) waar Kadence een opacity op toepast (hex2rgba breekt een
	 *   variabele);
	 * - een paletN in een blok dat Kadence niet is (core kent die naam niet);
	 * - een niet-hex waarde in een Gravity Forms-blok (dat neemt alleen hex).
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function replace_colors( $input = array() ) {
		$kaart = array();

		foreach ( ( isset( $input['map'] ) && is_array( $input['map'] ) ? $input['map'] : array() ) as $van => $naar ) {
			$van  = strtolower( trim( (string) $van ) );
			$naar = trim( (string) $naar );

			if ( '' !== $van && '' !== $naar && $van !== strtolower( $naar ) ) {
				$kaart[ $van ] = $naar;
			}
		}

		if ( empty( $kaart ) ) {
			return new WP_Error( 'kadence_mcp_no_color_map', __( 'Geef map op: {"#04201a":"palette3", …}. Oud is hoofdletterongevoelig.', 'mcp-abilities-kadence' ) );
		}

		$post_ids = isset( $input['post_ids'] ) && is_array( $input['post_ids'] ) ? array_map( 'intval', $input['post_ids'] ) : array();
		$met_meta = ! isset( $input['include_meta'] ) || ! empty( $input['include_meta'] );
		$plan     = self::kleurplan( $kaart, $post_ids, $met_meta );

		$aantal = 0;

		foreach ( $plan['posts'] as $p ) {
			$aantal += count( $p['changes'] );
		}

		foreach ( $plan['meta'] as $m ) {
			$aantal += count( $m['changes'] );
		}

		$verwacht = 'kmcp1_' . substr( wp_hash( (string) wp_json_encode( array( $kaart, $plan['fingerprint'] ) ) ), 0, 32 );
		$token    = isset( $input['token'] ) ? (string) $input['token'] : '';

		if ( '' === $token || 0 === $aantal ) {
			return array(
				'map'      => $kaart,
				'posts'    => $plan['posts'],
				'meta'     => $plan['meta'],
				'skipped'  => $plan['skipped'],
				'written'  => false,
				'token'    => $aantal > 0 ? $verwacht : '',
				'status'   => 0 === $aantal
					? __( 'Niets om te wijzigen: geen van de oude waarden staat (nog) in de gescande inhoud of meta.', 'mcp-abilities-kadence' ) . ( empty( $plan['skipped'] ) ? '' : ' ' . __( 'Wel overgeslagen plekken, zie skipped.', 'mcp-abilities-kadence' ) )
					: sprintf(
						/* translators: 1: changes, 2: posts, 3: meta items, 4: skipped. */
						__( 'Voorstel, er is NIETS opgeslagen. %1$d wijzigingen in %2$d posts en %3$d meta-sleutels; %4$d plekken overgeslagen (zie skipped, met reden). Posts krijgen een revisie, meta niet: bewaar per meta-item het veld from. Roep opnieuw aan met dezelfde invoer en het token om alles te schrijven.', 'mcp-abilities-kadence' ),
						$aantal,
						count( $plan['posts'] ),
						count( $plan['meta'] ),
						count( $plan['skipped'] )
					),
			);
		}

		if ( ! Kadence_MCP_Capabilities::current_user_can( Kadence_MCP_Capabilities::WRITE ) ) {
			return new WP_Error( 'kadence_mcp_write_denied', __( 'Je hebt de capability kadence_mcp_write niet.', 'mcp-abilities-kadence' ), array( 'status' => 403 ) );
		}

		if ( ! hash_equals( $verwacht, $token ) ) {
			return new WP_Error( 'kadence_mcp_invalid_token', __( 'Het token hoort niet bij deze invoer, of er is intussen iets gewijzigd in een van de posts of meta. Vraag opnieuw een voorstel.', 'mcp-abilities-kadence' ) );
		}

		$fouten = array();

		foreach ( $plan['posts'] as $p ) {
			if ( ! current_user_can( 'edit_post', $p['post_id'] ) ) {
				$fouten[] = sprintf( __( 'post %d: geen bewerkrecht', 'mcp-abilities-kadence' ), $p['post_id'] );
				continue;
			}

			$resultaat = wp_update_post( array( 'ID' => $p['post_id'], 'post_content' => wp_slash( $p['content'] ) ), true );

			if ( is_wp_error( $resultaat ) ) {
				$fouten[] = sprintf( 'post %d: %s', $p['post_id'], $resultaat->get_error_message() );
				continue;
			}

			clean_post_cache( $p['post_id'] );

			if ( (string) get_post_field( 'post_content', $p['post_id'] ) !== $p['content'] ) {
				$fouten[] = sprintf( __( 'post %d: na het opslaan wijkt de inhoud af (een filter heeft ingegrepen)', 'mcp-abilities-kadence' ), $p['post_id'] );
			}
		}

		foreach ( $plan['meta'] as $m ) {
			if ( ! current_user_can( 'edit_post', $m['post_id'] ) ) {
				$fouten[] = sprintf( __( 'meta %1$d %2$s: geen bewerkrecht', 'mcp-abilities-kadence' ), $m['post_id'], $m['key'] );
				continue;
			}

			update_post_meta( $m['post_id'], $m['key'], $m['value'] );

			if ( ! Kadence_MCP_Inventory::meta_gelijk( get_post_meta( $m['post_id'], $m['key'], true ), $m['value'] ) ) {
				$fouten[] = sprintf( __( 'meta %1$d %2$s: na het opslaan wijkt de waarde af', 'mcp-abilities-kadence' ), $m['post_id'], $m['key'] );
			}
		}

		$weergave = static function ( $lijst ) {
			return array_map(
				static function ( $x ) {
					unset( $x['content'], $x['value'] );

					return $x;
				},
				$lijst
			);
		};

		return array(
			'map'     => $kaart,
			'posts'   => $weergave( $plan['posts'] ),
			'meta'    => $weergave( $plan['meta'] ),
			'skipped' => $plan['skipped'],
			'written' => empty( $fouten ),
			'errors'  => $fouten,
			'token'   => '',
			'status'  => empty( $fouten )
				? sprintf(
					/* translators: 1: changes, 2: posts, 3: meta. */
					__( 'geschreven en teruggelezen: %1$d wijzigingen in %2$d posts (elk één revisie) en %3$d meta-sleutels (geen revisie; oude waarden in meta[].changes[].from).', 'mcp-abilities-kadence' ),
					$aantal,
					count( $plan['posts'] ),
					count( $plan['meta'] )
				)
				: __( 'LET OP: niet alles is geschreven zoals bedoeld; zie errors. De rest staat er wel.', 'mcp-abilities-kadence' ),
		);
	}

	/**
	 * Bouw het plan voor replace_colors.
	 *
	 * @param array $kaart    Oud (lowercase) => nieuw.
	 * @param int[] $post_ids Beperk tot deze posts; leeg is alles.
	 * @param bool  $met_meta Ook _kad-meta.
	 *
	 * @return array
	 */
	private static function kleurplan( $kaart, $post_ids, $met_meta ) {
		global $wpdb;

		$waar = empty( $post_ids ) ? '' : ' AND ID IN (' . implode( ',', array_map( 'intval', $post_ids ) ) . ')';
		$rijen = $wpdb->get_results( "SELECT ID FROM {$wpdb->posts} WHERE post_status IN ('publish','private','draft','future','pending') AND post_type NOT IN ('revision','attachment','nav_menu_item','customize_changeset','oembed_cache')" . $waar . ' ORDER BY ID' );

		$posts   = array();
		$meta    = array();
		$overslaan = array();
		$vinger  = array();

		foreach ( $rijen as $rij ) {
			$post = get_post( (int) $rij->ID );

			if ( ! $post || ! current_user_can( 'read_post', $post->ID ) || false === strpos( $post->post_content, '<!-- wp:' ) ) {
				continue;
			}

			$wijzigingen = array();
			$boom        = parse_blocks( $post->post_content );
			$nieuwe_boom = self::vervang_kleuren_in_boom( $boom, $kaart, $post, $wijzigingen, $overslaan, array() );

			if ( ! empty( $wijzigingen ) ) {
				$inhoud  = Kadence_MCP_Inventory::serialiseer( $nieuwe_boom );
				$posts[] = array(
					'post_id' => $post->ID,
					'title'   => get_the_title( $post ),
					'type'    => $post->post_type,
					'changes' => $wijzigingen,
					'content' => $inhoud,
				);
				$vinger[] = $post->ID . ':' . $post->post_modified_gmt;
			}
		}

		if ( $met_meta ) {
			$metarijen = $wpdb->get_results(
				"SELECT m.post_id, m.meta_key FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_status IN ('publish','private','draft') AND p.post_type <> 'revision' AND m.meta_key LIKE '\\_kad\\_%' AND m.meta_key NOT LIKE '\\_kad\\_font\\_%'" . ( empty( $post_ids ) ? '' : ' AND m.post_id IN (' . implode( ',', array_map( 'intval', $post_ids ) ) . ')' )
			);

			foreach ( $metarijen as $rij ) {
				if ( ! current_user_can( 'read_post', (int) $rij->post_id ) ) {
					continue;
				}

				$oud      = get_post_meta( (int) $rij->post_id, $rij->meta_key, true );
				$gevonden = array();
				$nieuw    = self::vervang_kleuren_in_waarde( $oud, $kaart, $gevonden, '', is_array( $oud ) ? $oud : array() );
				$wijzigingen = array();

				// Dezelfde uitzonderingen als bij blokken: een schaduw in meta is
				// {color, opacity}, en daar breekt een var() net zo goed.
				foreach ( $gevonden as $w ) {
					$reden = self::kleur_overslaan( Kadence_MCP_Inventory::BLOCK_PREFIX . 'meta', $w, is_array( $oud ) ? $oud : array() );

					if ( '' !== $reden ) {
						$overslaan[] = array( 'post_id' => (int) $rij->post_id, 'meta_key' => $rij->meta_key, 'attribute' => $w['path'], 'from' => $w['from'], 'to' => $w['to'], 'reason' => $reden );
						$nieuw       = self::zet_op_pad( $nieuw, $w['sub'], $w['from'] );
						continue;
					}

					$wijzigingen[] = array( 'attribute' => $w['path'], 'from' => $w['from'], 'to' => $w['to'] );
				}

				if ( ! empty( $wijzigingen ) ) {
					$meta[]   = array(
						'post_id' => (int) $rij->post_id,
						'key'     => $rij->meta_key,
						'changes' => $wijzigingen,
						'value'   => $nieuw,
					);
					$vinger[] = $rij->post_id . ':' . $rij->meta_key . ':' . md5( maybe_serialize( $oud ) );
				}
			}
		}

		return array(
			'posts'       => $posts,
			'meta'        => $meta,
			'skipped'     => $overslaan,
			'fingerprint' => $vinger,
		);
	}

	/**
	 * Vervang kleuren in de attributen van een boom.
	 *
	 * @param array   $blokken     De boom.
	 * @param array   $kaart       Oud => nieuw.
	 * @param WP_Post $post        De post.
	 * @param array   $wijzigingen Verzamelaar.
	 * @param array   $overslaan   Verzamelaar.
	 * @param int[]   $pad         Intern.
	 *
	 * @return array
	 */
	private static function vervang_kleuren_in_boom( $blokken, $kaart, $post, &$wijzigingen, &$overslaan, $pad ) {
		foreach ( $blokken as $i => $blok ) {
			$hier = array_merge( $pad, array( (int) $i ) );
			$naam = isset( $blok['blockName'] ) ? (string) $blok['blockName'] : '';

			if ( '' !== $naam && ! empty( $blok['attrs'] ) && is_array( $blok['attrs'] ) ) {
				$sleutel = isset( $blok['attrs']['uniqueID'] ) ? (string) $blok['attrs']['uniqueID'] : Kadence_MCP_Inventory::PAD_PREFIX . implode( '.', $hier );

				foreach ( $blok['attrs'] as $attr => $waarde ) {
					$hier_w = array();
					$nieuw  = self::vervang_kleuren_in_waarde( $waarde, $kaart, $hier_w, (string) $attr, $blok['attrs'] );

					foreach ( $hier_w as $w ) {
						$reden = self::kleur_overslaan( $naam, $w, $blok['attrs'] );

						if ( '' !== $reden ) {
							$overslaan[] = array( 'post_id' => $post->ID, 'block' => $sleutel, 'attribute' => $w['path'], 'from' => $w['from'], 'to' => $w['to'], 'reason' => $reden );
							// Terugzetten: deze ene waarde blijft zoals hij was.
							$nieuw = self::zet_op_pad( $nieuw, $w['sub'], $w['from'] );
							continue;
						}

						$wijzigingen[] = array( 'block' => $sleutel, 'name' => $naam, 'attribute' => $w['path'], 'from' => $w['from'], 'to' => $w['to'] );
					}

					$blokken[ $i ]['attrs'][ $attr ] = $nieuw;
				}
			}

			if ( ! empty( $blok['innerBlocks'] ) ) {
				$blokken[ $i ]['innerBlocks'] = self::vervang_kleuren_in_boom( $blok['innerBlocks'], $kaart, $post, $wijzigingen, $overslaan, $hier );
			}
		}

		return $blokken;
	}

	/**
	 * Vervang kleuren in één (geneste) waarde.
	 *
	 * @param mixed  $waarde      De waarde.
	 * @param array  $kaart       Oud => nieuw.
	 * @param array  $wijzigingen Verzamelaar: path, sub (sleutelpad), from, to.
	 * @param string $pad         Het pad tot hier, voor de melding.
	 * @param array  $broers      De attributen van het blok (voor de opacity-toets).
	 * @param array  $sub         Intern sleutelpad binnen de waarde.
	 *
	 * @return mixed
	 */
	private static function vervang_kleuren_in_waarde( $waarde, $kaart, &$wijzigingen, $pad, $broers = array(), $sub = array() ) {
		if ( is_string( $waarde ) ) {
			$sleutel = strtolower( trim( $waarde ) );

			if ( isset( $kaart[ $sleutel ] ) ) {
				$wijzigingen[] = array( 'path' => $pad, 'sub' => $sub, 'from' => $waarde, 'to' => $kaart[ $sleutel ], 'siblings' => $broers );

				return $kaart[ $sleutel ];
			}

			return $waarde;
		}

		if ( is_array( $waarde ) ) {
			foreach ( $waarde as $k => $v ) {
				$waarde[ $k ] = self::vervang_kleuren_in_waarde( $v, $kaart, $wijzigingen, '' === $pad ? (string) $k : $pad . '.' . $k, is_array( $v ) ? $v : $waarde, array_merge( $sub, array( $k ) ) );
			}
		}

		return $waarde;
	}

	/**
	 * Waarom deze ene vervanging niet door mag gaan, of ''.
	 *
	 * @param string $bloknaam De bloknaam.
	 * @param array  $w        De vervanging.
	 * @param array  $attrs    Alle attributen van het blok.
	 *
	 * @return string
	 */
	private static function kleur_overslaan( $bloknaam, $w, $attrs ) {
		$naar     = (string) $w['to'];
		$is_hex   = (bool) preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $naar );
		$is_palet = (bool) preg_match( '/^palette\d+$/', $naar );
		$is_var   = 0 === strpos( $naar, 'var(' );

		if ( 0 === strpos( $bloknaam, 'gravityforms/' ) && ! $is_hex ) {
			return __( 'Gravity Forms neemt in het formulierblok alleen hex aan.', 'mcp-abilities-kadence' );
		}

		if ( $is_palet && 0 !== strpos( $bloknaam, Kadence_MCP_Inventory::BLOCK_PREFIX ) ) {
			return __( 'een paletnaam werkt alleen in Kadence-blokken; core leest palette3 niet (gebruik var(--global-palette3)).', 'mcp-abilities-kadence' );
		}

		if ( $is_var ) {
			// Een opacity naast de kleur: in hetzelfde object (schaduw
			// {color, opacity}) of als broer met Opacity in de naam.
			$broers = isset( $w['siblings'] ) && is_array( $w['siblings'] ) ? $w['siblings'] : array();

			if ( isset( $broers['opacity'] ) && is_numeric( $broers['opacity'] ) && (float) $broers['opacity'] < 1 ) {
				return __( 'er hoort een opacity bij; Kadence rekent dan hex om naar rgba, en dat breekt een var(). Gebruik een hex of een paletnaam.', 'mcp-abilities-kadence' );
			}

			// De oudere schaduwvorm als lijst: [aan, kleur, opacity, x, y, blur, spread, inset].
			$laatste = empty( $w['sub'] ) ? null : end( $w['sub'] );

			if ( 1 === $laatste && isset( $broers[0], $broers[2] ) && is_bool( $broers[0] ) && is_numeric( $broers[2] ) && (float) $broers[2] < 1 ) {
				return __( 'dit is een schaduw [aan, kleur, opacity, …] met een opacity; Kadence rekent de kleur dan om naar rgba, en dat breekt een var(). Gebruik een hex of een paletnaam.', 'mcp-abilities-kadence' );
			}

			$attr = strtok( (string) $w['path'], '.' );

			foreach ( array( $attr . 'Opacity', $attr . 'opacity' ) as $o ) {
				if ( isset( $attrs[ $o ] ) && is_numeric( $attrs[ $o ] ) && (float) $attrs[ $o ] < 1 ) {
					return sprintf( __( '%s staat onder de 1; Kadence rekent dan hex om naar rgba, en dat breekt een var(). Gebruik een hex of een paletnaam.', 'mcp-abilities-kadence' ), $o );
				}
			}
		}

		return '';
	}

	/**
	 * Zet een waarde op een sleutelpad binnen een geneste waarde.
	 *
	 * @param mixed $waarde De waarde.
	 * @param array $pad    De sleutels.
	 * @param mixed $nieuw  De nieuwe waarde.
	 *
	 * @return mixed
	 */
	private static function zet_op_pad( $waarde, $pad, $nieuw ) {
		if ( empty( $pad ) ) {
			return $nieuw;
		}

		$k = array_shift( $pad );

		if ( is_array( $waarde ) && array_key_exists( $k, $waarde ) ) {
			$waarde[ $k ] = self::zet_op_pad( $waarde[ $k ], $pad, $nieuw );
		}

		return $waarde;
	}

	/**
	 * Het actieve palet als hex => paletN.
	 *
	 * @return array<string,string>
	 */
	private static function palet_hex() {
		$stijlen = Kadence_MCP_Inventory::get_global_styles();
		$waarde  = isset( $stijlen['palette']['value'] ) && is_array( $stijlen['palette']['value'] ) ? $stijlen['palette']['value'] : array();
		$actief  = isset( $waarde['active'] ) ? (string) $waarde['active'] : 'palette';
		$set     = isset( $waarde[ $actief ] ) && is_array( $waarde[ $actief ] ) ? $waarde[ $actief ] : array();
		$uit     = array();

		foreach ( $set as $kleur ) {
			if ( isset( $kleur['color'], $kleur['slug'] ) ) {
				$hex = strtolower( (string) $kleur['color'] );

				if ( ! isset( $uit[ $hex ] ) ) {
					$uit[ $hex ] = (string) $kleur['slug'];
				}
			}
		}

		return $uit;
	}

	/**
	 * Een vingerafdruk van de site, om twee sites structureel te vergelijken.
	 *
	 * Alles op stabiele sleutels (slug, titel, meta-sleutel) en niet op ID's,
	 * want die verschillen tussen lokaal en staging. Per onderdeel een hash, zodat
	 * twee vingerafdrukken in één oogopslag te vergelijken zijn; met detail aan
	 * komen de waarden zelf mee. Bedoeld voor wat een pixelvergelijking pas laat
	 * ziet: een term-beschrijving die alleen op staging staat, typografie die
	 * lokaal niet is ingesteld, een ontbrekend font.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array
	 */
	public static function site_fingerprint( $input = array() ) {
		global $wpdb;

		$detail = ! empty( $input['detail'] );
		$delen  = array();

		$stijlen = Kadence_MCP_Inventory::get_global_styles();
		$omgeving = Kadence_MCP_Inventory::get_environment();

		$delen['environment'] = $omgeving;
		$delen['palette']     = isset( $stijlen['palette']['value'] ) ? $stijlen['palette']['value'] : null;
		$delen['typography']  = array(
			'values'  => $stijlen['typography'],
			'sources' => isset( $stijlen['typography_sources'] ) ? $stijlen['typography_sources'] : array(),
		);
		$delen['fonts'] = array_map(
			static function ( $face ) {
				// De URL's verschillen per domein; de bestandsnaam niet.
				$face['files'] = array_map( 'wp_basename', $face['files'] );
				unset( $face['post_id'] );

				return $face;
			},
			$stijlen['fonts']['faces']
		);

		// Termen van publieke taxonomieën, met hun beschrijving.
		$termen = array();

		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
			if ( in_array( $tax->name, array( 'post_format' ), true ) ) {
				continue;
			}

			foreach ( get_terms( array( 'taxonomy' => $tax->name, 'hide_empty' => false ) ) as $term ) {
				$termen[ $tax->name ][ $term->slug ] = array(
					'name'        => $term->name,
					'description' => $term->description,
					'parent'      => $term->parent ? get_term( $term->parent )->slug : '',
				);
			}

			$delen['taxonomies'][ $tax->name ] = array(
				'public'             => (bool) $tax->public,
				'publicly_queryable' => (bool) $tax->publicly_queryable,
				'object_type'        => array_values( (array) $tax->object_type ),
			);
		}

		$delen['terms'] = $termen;

		// Kadence-objecten: per posttype op titel, met een hash van inhoud en meta.
		// Inhoud en meta bevatten ID's en domeinen, dus die gaan er voor de hash
		// uit — anders verschilt alles, altijd.
		$entiteiten = array();
		$domein     = wp_parse_url( home_url(), PHP_URL_HOST );

		foreach ( array_keys( Kadence_MCP_Inventory::get_post_types() ) as $type ) {
			foreach ( get_posts( array( 'post_type' => $type, 'post_status' => array( 'publish', 'private', 'draft' ), 'numberposts' => 300, 'suppress_filters' => true ) ) as $post ) {
				if ( ! current_user_can( 'read_post', $post->ID ) ) {
					continue;
				}

				$meta = array();

				foreach ( get_post_meta( $post->ID ) as $sleutel => $waarden ) {
					if ( 0 === strpos( $sleutel, '_kad_' ) && 0 !== strpos( $sleutel, '_kad_font_' ) ) {
						$meta[ $sleutel ] = $waarden[0];
					}
				}

				ksort( $meta );

				$schoon = static function ( $tekst ) use ( $domein ) {
					$tekst = str_replace( (string) $domein, '{domein}', (string) $tekst );
					$tekst = preg_replace( '/"(id|postId|ID|mediaId|bgImgID|imgID|parent)":\d+/', '"$1":0', $tekst );

					return preg_replace( '/"uniqueID":"\d+_/', '"uniqueID":"_', $tekst );
				};

				$sleutel = $post->post_title . ( '' !== $post->post_name ? ' (' . $post->post_name . ')' : '' );

				$entiteiten[ $type ][ $sleutel ] = array(
					'status'       => $post->post_status,
					'content_hash' => md5( $schoon( $post->post_content ) ),
					'meta_hash'    => md5( $schoon( wp_json_encode( $meta ) ) ),
				);
			}
		}

		$delen['entities'] = $entiteiten;

		// Actieve plugins met versie.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = array();

		foreach ( get_plugins() as $bestand => $data ) {
			if ( is_plugin_active( $bestand ) ) {
				$plugins[ dirname( $bestand ) ] = $data['Version'];
			}
		}

		ksort( $plugins );
		$delen['plugins'] = $plugins;

		$delen['reading'] = array(
			'show_on_front'       => get_option( 'show_on_front' ),
			'page_on_front'       => (int) get_option( 'page_on_front' ) ? get_post_field( 'post_name', (int) get_option( 'page_on_front' ) ) : '',
			'permalink_structure' => get_option( 'permalink_structure' ),
			'blog_public'         => (int) get_option( 'blog_public' ),
		);

		$hashes = array();

		foreach ( $delen as $naam => $inhoud ) {
			$hashes[ $naam ] = substr( md5( wp_json_encode( 'environment' === $naam ? array_diff_key( $inhoud, array( 'page_cache' => 1 ) ) : $inhoud ) ), 0, 12 );
		}

		return array(
			'site'   => home_url(),
			'hashes' => $hashes,
			'parts'  => $detail ? $delen : null,
			'status' => $detail
				? __( 'Vingerafdruk met details. Vergelijk hashes met die van de andere site; waar een hash verschilt, vergelijk dat onderdeel in parts. entities vergelijkt op titel (slug), zonder ID\'s en domein.', 'mcp-abilities-kadence' )
				: __( 'Alleen hashes. Draai dit op beide sites; waar een hash verschilt, vraag opnieuw met detail: true en vergelijk dat onderdeel.', 'mcp-abilities-kadence' ),
		);
	}
}
