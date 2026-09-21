<?php
/**
 * Bouwt en registreert de ability-definities.
 *
 * Eén plek waar een ruwe definitie een volledige wordt: standaardschema,
 * permission_callback uit de capability, meta met annotaties. De abilities
 * zelf hoeven dat dus niet te herhalen, en kunnen het ook niet vergeten.
 *
 * @package Kadence_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * De registry.
 */
class Kadence_MCP_Registry {

	/**
	 * De categorie waaronder alle abilities vallen.
	 */
	const CATEGORY = 'kadence';

	/**
	 * In-request cache van de genormaliseerde definities.
	 *
	 * @var array[]|null
	 */
	private static $cache = null;

	/**
	 * De klassen die ruwe definities leveren.
	 *
	 * @return string[]
	 */
	private static function bronnen() {
		return array(
			'Kadence_MCP_Abilities_Blocks',
			'Kadence_MCP_Abilities_Build',
			'Kadence_MCP_Abilities_Content',
			'Kadence_MCP_Abilities_Query',
			'Kadence_MCP_Abilities_Site',
		);
	}

	/**
	 * Alle definities, genormaliseerd.
	 *
	 * @return array[]
	 */
	public static function get_definitions() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$ruw = array();

		foreach ( self::bronnen() as $klasse ) {
			if ( class_exists( $klasse ) && method_exists( $klasse, 'get_definitions' ) ) {
				$ruw = array_merge( $ruw, call_user_func( array( $klasse, 'get_definitions' ) ) );
			}
		}

		$definities = array();

		foreach ( $ruw as $item ) {
			if ( ! isset( $item['name'], $item['args'] ) || ! is_array( $item['args'] ) ) {
				continue;
			}

			$definities[] = self::normaliseer( $item['name'], $item['args'] );
		}

		self::$cache = $definities;

		return $definities;
	}

	/**
	 * Maak van een ruwe definitie een volledige.
	 *
	 * @param string $naam De naam van de ability.
	 * @param array  $args De ruwe configuratie.
	 *
	 * @return array
	 */
	private static function normaliseer( $naam, $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'label'            => '',
				'description'      => '',
				'summary'          => '',
				'capability'       => Kadence_MCP_Capabilities::VIEW,
				'execute_callback' => null,
				'input_schema'     => array(),
				'output_schema'    => array(),
				'readonly'         => true,
				'destructive'      => false,
				'idempotent'       => true,
			)
		);

		// Een ability zonder invoerschema is via MCP onbruikbaar: WP_Ability
		// weigert dan élke invoer, ook het lege object dat clients altijd
		// meesturen. Geef hem dus een expliciet leeg objectschema.
		if ( empty( $args['input_schema'] ) ) {
			$args['input_schema'] = array(
				'type'                 => 'object',
				'default'              => (object) array(),
				'properties'           => array(),
				'additionalProperties' => false,
			);
		}

		// LET OP — hier stond een verruiming van 'type' naar
		// array('object','array','null') voor schema's zonder verplichte velden.
		// Die is verwijderd en mag niet terugkomen. De MCP Adapter vergelijkt
		// strikt met de string 'object' (SchemaTransformer.php:62); een array
		// valt door naar wrap_in_object() en het gepubliceerde schema wordt dan
		// {type:object, properties:{input:…}, required:['input']}. De client
		// stuurt vervolgens {"search":"row"} plat, de adapter pelt
		// $arguments['input'] eraf, vindt niets, en de ability draait met lege
		// invoer — zonder foutmelding en met een volledige, ongefilterde lijst
		// als antwoord. De toegevoegde 'null' maakte dat onzichtbaar.
		//
		// De juiste oplossing voor "de client stuurt niets" is een 'default' op
		// het roottschema, precies zoals Gravity Forms het doet
		// (class-gf-abilities-registry.php:128-140).
		$callback = $args['execute_callback'];

		$definitie = array(
			'name'                => $naam,
			'label'               => $args['label'],
			'description'         => $args['description'],
			'category'            => self::CATEGORY,
			'ability_class'       => 'Kadence_MCP_Ability',
			'execute_callback'    => static function ( $input = array() ) use ( $callback ) {
				return call_user_func( $callback, is_array( $input ) ? $input : array() );
			},
			'permission_callback' => static function () use ( $args ) {
				return Kadence_MCP_Capabilities::current_user_can( $args['capability'] );
			},
			'input_schema'        => $args['input_schema'],
			'output_schema'       => $args['output_schema'],
			'meta'                => array(
				'mcp'          => array(
					// Op de eigen server staat elke ability al als losse tool in
					// de lijst; hem dáárnaast ook nog publiceren op de gedeelde
					// server levert dezelfde tool twee keer op.
					'public' => ! Kadence_MCP_Settings::is_dedicated_endpoint(),
				),
				'annotations'  => array(
					'readonly'    => (bool) $args['readonly'],
					'destructive' => (bool) $args['destructive'],
					'idempotent'  => (bool) $args['idempotent'],
				),
				// Eén regel mensentaal voor het instellingenscherm. Bewust iets
				// anders dan 'description', die voor de agent geschreven is.
				'summary'      => $args['summary'],
				'show_in_rest' => true,
			),
		);

		$vast = array(
			'name'                => $definitie['name'],
			'ability_class'       => $definitie['ability_class'],
			'permission_callback' => $definitie['permission_callback'],
			'execute_callback'    => $definitie['execute_callback'],
		);

		/**
		 * Filtert een definitie voordat hij geregistreerd wordt.
		 *
		 * Label, omschrijving en schema's mogen aangepast worden. De naam, de
		 * ability-klasse, de permission_callback en de execute_callback worden
		 * er daarna weer overheen gezet: een filter mag de beschrijving
		 * veranderen, nooit de grendel.
		 *
		 * @param array  $definitie De genormaliseerde definitie.
		 * @param string $naam      De naam van de ability.
		 */
		$definitie = apply_filters( 'kadence_mcp_ability_definition', $definitie, $naam );

		if ( ! is_array( $definitie ) ) {
			$definitie = array();
		}

		return array_merge( $definitie, $vast );
	}

	/**
	 * Registreer de categorie.
	 *
	 * @return void
	 */
	public static function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Kadence', 'mcp-abilities-kadence' ),
				'description' => __( 'Lezen uit Kadence Blocks, Kadence Blocks Pro, Kadence Pro en het Kadence-thema.', 'mcp-abilities-kadence' ),
			)
		);
	}

	/**
	 * Registreer de abilities.
	 *
	 * Een uitgezette tool wordt helemaal niet geregistreerd. Dat scheelt de
	 * agent ruis, maar het is niet de beveiliging — die zit in de
	 * permission_callback en in Kadence_MCP_Ability::check_permissions().
	 *
	 * @return void
	 */
	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		require_once KADENCE_MCP_PATH . 'includes/class-kadence-mcp-ability.php';

		foreach ( self::get_definitions() as $definitie ) {
			if ( ! Kadence_MCP_Settings::is_tool_enabled( $definitie['name'] ) ) {
				continue;
			}

			wp_register_ability( $definitie['name'], $definitie );
		}
	}

	/**
	 * De namen van de abilities die daadwerkelijk geregistreerd zijn.
	 *
	 * Gelezen uit het register van WordPress, niet uit onze eigen lijst — dan
	 * kan er geen tool op de server belanden die om welke reden dan ook niet
	 * geregistreerd is geraakt.
	 *
	 * @return string[]
	 */
	public static function get_registered_names() {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array();
		}

		// Op prefix alléén filteren zou betekenen dat een ability van een
		// andere plugin die toevallig 'kadence/' gebruikt op ONZE server
		// belandt, zonder ooit in het instellingenscherm te hebben gestaan.
		// Daarom ook toetsen aan de eigen definities.
		$eigen = wp_list_pluck( self::get_definitions(), 'name' );
		$namen = array();

		foreach ( wp_get_abilities() as $ability ) {
			$naam = $ability->get_name();

			if ( 0 === strpos( $naam, KADENCE_MCP_NAMESPACE . '/' ) && in_array( $naam, $eigen, true ) ) {
				$namen[] = $naam;
			}
		}

		return $namen;
	}
}
