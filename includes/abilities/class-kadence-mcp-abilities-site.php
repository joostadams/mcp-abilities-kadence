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
					'description' => __( 'Geeft het globale kleurenpalet, de basistypografie en de site-brede standaardinstellingen per blok. Die laatste zijn INVOEGstandaarden: wat de editor invult bij een nieuw blok, en wat bij opslaan in de markup terechtkomt. Ze veranderen niets aan bestaande blokken — een ontbrekend attribuut daar betekent nog steeds de standaardwaarde uit describe-block. Waar ze voor dienen is weten welke vorm iets op deze site hoort te krijgen als je iets nieuws voorstelt. Ontbreekt een bron, dan staat dat er zo bij — er worden geen waarden verzonnen. Ook bruikbaar om te weten welke kleur een blok bedoelt als het naar "palette3" verwijst.', 'mcp-abilities-kadence' ),
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

			$items = array();

			foreach ( $posts as $post ) {
				// Dezelfde regel als bij inspect-post: de tool mogen gebruiken
				// is niet hetzelfde als elke post mogen zien.
				if ( ! current_user_can( 'read_post', $post->ID ) ) {
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
				'items'     => $items,
			);
		}

		$totaal   = array_sum( wp_list_pluck( $uitvoer, 'returned' ) );
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
		// niet op het kale getal, anders matcht elk toevallig voorkomen.
		$naald    = '"id":' . $object_id;
		$gevonden = array();

		foreach ( $posts as $post ) {
			if ( false === strpos( $post->post_content, $naald ) ) {
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
				'hits'      => substr_count( $post->post_content, $naald ),
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

		self::loop_blokken( parse_blocks( $post->post_content ), $bindingen );
		self::lees_query_meta( $post_id, $bindingen );

		$kapot   = 0;
		$onbekend = 0;

		foreach ( $bindingen as $b ) {
			if ( 'dangelt' === $b['status'] ) {
				++$kapot;
			} elseif ( 'onverifieerbaar' === $b['status'] ) {
				++$onbekend;
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

		return array(
			'post'     => array(
				'id'        => $post->ID,
				'title'     => get_the_title( $post ),
				'post_type' => $post->post_type,
			),
			'bindings' => $bindingen,
			'broken'   => $kapot,
			'status'   => $status,
		);
	}

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
}
