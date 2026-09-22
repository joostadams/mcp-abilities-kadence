<?php
/**
 * De gedeelde dienst waar alle abilities dun overheen liggen.
 *
 * Alles wat de abilities weten over Kadence staat hier, en nergens anders.
 * De abilities zelf doen niets dan invoer controleren, deze klasse aanroepen
 * en het antwoord vormgeven. Dat scheelt niet alleen dubbele code: het houdt
 * de kennis over Kadence op één plek als Kadence verandert.
 *
 * Niets in deze klasse schrijft. Geen enkele methode raakt een post, een
 * optie, een bestand of een cache aan.
 *
 * @package Kadence_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Leest de Kadence-installatie uit.
 */
class Kadence_MCP_Inventory {

	/**
	 * Het prefix waarop een Kadence-blok te herkennen is.
	 */
	const BLOCK_PREFIX = 'kadence/';

	/**
	 * Het prefix waarop een Kadence-posttype te herkennen is.
	 */
	const POST_TYPE_PREFIX = 'kadence_';

	/**
	 * Kadence-posttypes die het prefix NIET dragen.
	 *
	 * Kadence Blocks Pro registreert zijn eigen-iconenbibliotheek als 'kb_icon'
	 * (class-kadence-blocks-pro-custom-icons.php:463). Een prefixfilter alleen
	 * mist die, en dan bestaat hij voor de agent niet.
	 */
	const EXTRA_POST_TYPES = array( 'kb_icon' );

	/**
	 * Attributen die WordPress zelf op elk blok toestaat.
	 *
	 * Deze staan in geen enkele block.json — ook niet in die van Kadence — en
	 * werden daardoor door normaliseer_attributen() als "onbekend" weggegooid.
	 * Dat kostte op 11-09-2026 de bloknaam "Hero" van een rij op pagina 1471:
	 * de schrijfactie slaagde, de terugleescontrole meldde het keurig, en de
	 * naam was weg. Wat je niet kent gooi je niet weg.
	 *
	 * metadata draagt de bloknaam en de zichtbaarheidsschakelaar, lock het
	 * slotje tegen verplaatsen of verwijderen. Allebei onzichtbaar in de
	 * attributenlijst van het blok en allebei onherstelbaar zonder de bron.
	 */
	const CORE_ATTRIBUTEN = array( 'metadata', 'lock' );

	/**
	 * In-request cache voor de bron-per-blok.
	 *
	 * @var array<string,string>|null
	 */
	private static $bron_cache = null;

	/**
	 * In-request cache van de block.json-definities op schijf.
	 *
	 * @var array<string,array>|null
	 */
	private static $json_cache = null;

	/**
	 * Wat er aan Kadence draait.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_environment() {
		$thema = wp_get_theme();

		// Bij een child theme is de ouder het thema dat ertoe doet.
		$parent = $thema->parent();
		$basis  = $parent ? $parent : $thema;

		return array(
			'theme'                  => $basis->get( 'Name' ),
			'theme_version'          => $basis->get( 'Version' ),
			'child_theme'            => $parent ? $thema->get( 'Name' ) : '',
			'kadence_blocks'         => defined( 'KADENCE_BLOCKS_VERSION' ) ? KADENCE_BLOCKS_VERSION : '',
			'kadence_blocks_pro'     => defined( 'KBP_VERSION' ) ? KBP_VERSION : '',
			'kadence_pro'            => defined( 'KTP_VERSION' ) ? KTP_VERSION : '',
		);
	}

	/**
	 * Het pad van een Kadence-plugin, of een lege string.
	 *
	 * @param string $constante De padconstante die de plugin definieert.
	 * @param string $map       De mapnaam als terugval.
	 *
	 * @return string
	 */
	private static function plugin_pad( $constante, $map ) {
		if ( defined( $constante ) && constant( $constante ) ) {
			return trailingslashit( constant( $constante ) );
		}

		$terugval = trailingslashit( WP_PLUGIN_DIR ) . $map . '/';

		return is_dir( $terugval ) ? $terugval : '';
	}

	/**
	 * Welk blok komt uit welke plugin?
	 *
	 * Afgeleid uit de block.json-bestanden op schijf, want een geregistreerd
	 * WP_Block_Type draagt geen herkomst met zich mee. Levert een blok geen
	 * treffer op, dan krijgt het bewust 'onbekend' in plaats van een gok.
	 *
	 * @return array<string,string>
	 */
	public static function get_block_source_map() {
		if ( null !== self::$bron_cache ) {
			return self::$bron_cache;
		}

		$bronnen = array(
			'kadence-blocks'     => self::plugin_pad( 'KADENCE_BLOCKS_PATH', 'kadence-blocks' ),
			'kadence-blocks-pro' => self::plugin_pad( 'KBP_PATH', 'kadence-blocks-pro' ),
		);

		$kaart = array();
		$jsons = array();

		foreach ( $bronnen as $bron => $pad ) {
			if ( '' === $pad || ! is_dir( $pad . 'dist/blocks' ) ) {
				continue;
			}

			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $pad . 'dist/blocks', FilesystemIterator::SKIP_DOTS )
			);

			foreach ( $iterator as $bestand ) {
				if ( 'block.json' !== $bestand->getFilename() ) {
					continue;
				}

				$json = json_decode( (string) file_get_contents( $bestand->getPathname() ), true );

				if ( ! is_array( $json ) ) {
					continue;
				}

				// Eén block.json in de hele stack mist 'name':
				// kadence-blocks/dist/blocks/accordion/pane/block.json. Zonder
				// terugval bestaat kadence/pane voor deze plugin niet, terwijl
				// een accordeon alledaags is. De naam volgt uit de laatste
				// mapnaam, en dat klopt hier aantoonbaar — elk ander bestand,
				// ook header/children/row (kadence/header-row), draagt zijn
				// naam gewoon zelf, dus deze afleiding treft vandaag precies
				// dat ene blok. Hij wordt gemarkeerd zodat het nooit een
				// stille gok is.
				if ( empty( $json['name'] ) ) {
					$json['name']        = self::BLOCK_PREFIX . basename( dirname( $bestand->getPathname() ) );
					$json['name_source'] = 'directory';
				}

				// Een blok dat in beide plugins staat — videopopup is er zo een —
				// houdt de eerste treffer. De gratis plugin laadt als eerste, dus
				// dat is ook de registratie die WordPress uiteindelijk gebruikt.
				if ( ! isset( $kaart[ $json['name'] ] ) ) {
					$kaart[ $json['name'] ] = $bron;
					$jsons[ $json['name'] ] = $json;
				}
			}
		}

		self::$bron_cache = $kaart;
		self::$json_cache = $jsons;

		return $kaart;
	}

	/**
	 * Alle geregistreerde Kadence-blokken.
	 *
	 * De blokkenregistratie is de bron van waarheid voor wát er bestaat; de
	 * bestandsscan hierboven alleen voor waar het vandaan komt.
	 *
	 * @return array[]
	 */
	public static function get_blocks() {
		if ( ! class_exists( 'WP_Block_Type_Registry' ) ) {
			return array();
		}

		$bronnen = self::get_block_source_map();
		$blokken = array();

		foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $naam => $type ) {
			if ( 0 !== strpos( $naam, self::BLOCK_PREFIX ) ) {
				continue;
			}

			$attributen = self::eigen_attributen( $type );

			$blokken[ $naam ] = array(
				'name'            => $naam,
				'title'           => (string) $type->title,
				'category'        => (string) $type->category,
				'source'          => isset( $bronnen[ $naam ] ) ? $bronnen[ $naam ] : 'onbekend',
				'attribute_count' => count( $attributen ),
				'has_unique_id'   => isset( $attributen['uniqueID'] ),
				'parent'          => is_array( $type->parent ) ? $type->parent : array(),
				// ancestor is iets anders dan parent en het verschil doet ertoe:
				// parent zegt in welk blok iets DIRECT mag staan, ancestor dat
				// het ergens ONDER dat blok moet zitten. De queryblokken mogen
				// bijvoorbeeld direct in een kadence/column staan, maar alleen
				// als die column zelf onder een kadence/query hangt. Dat is de
				// reden dat ze in de editor pas verschijnen als je in de query
				// zit. 29 van de 92 Kadence-blokken gebruiken dit.
				'ancestor'        => isset( $type->ancestor ) && is_array( $type->ancestor ) ? $type->ancestor : array(),
				// LET OP: dit is GEEN veiligheidssignaal. De abstracte blokklasse
				// registreert elk Kadence-blok met een render_callback
				// (class-kadence-blocks-abstract-block.php:127-134), dus
				// is_dynamic() is bij alle Kadence-blokken true. Die functie
				// kijkt alleen of er een callback hangt, niet of er markup
				// gegenereerd wordt — en de callback van Kadence rendert niets,
				// hij geeft de opgeslagen markup terug met CSS ervoor.
				'is_dynamic'      => $type->is_dynamic(),
				'is_dynamic_note' => __( 'zegt niets over validatieveiligheid: alle Kadence-blokken hebben een render_callback. Gebruik validate-write.', 'mcp-abilities-kadence' ),
				'registration'    => 'php',
			);
		}

		// Blokken die alleen in JavaScript geregistreerd zijn — kadence/pane,
		// kadence/tab, kadence/countdown-inner en kadence/countdown-timer —
		// staan niet in het PHP-register. Ze komen wél in de inhoud voor, dus
		// ze ontkennen is onjuist. Vul ze aan uit de block.json op schijf.
		foreach ( self::get_block_json_map() as $naam => $json ) {
			if ( isset( $blokken[ $naam ] ) || 0 !== strpos( $naam, self::BLOCK_PREFIX ) ) {
				continue;
			}

			$attributen = isset( $json['attributes'] ) && is_array( $json['attributes'] ) ? $json['attributes'] : array();

			$blokken[ $naam ] = array(
				'name'            => $naam,
				'title'           => isset( $json['title'] ) ? (string) $json['title'] : '',
				'category'        => isset( $json['category'] ) ? (string) $json['category'] : '',
				'source'          => isset( $bronnen[ $naam ] ) ? $bronnen[ $naam ] : 'onbekend',
				'attribute_count' => count( $attributen ),
				'has_unique_id'   => isset( $attributen['uniqueID'] ),
				'parent'          => isset( $json['parent'] ) && is_array( $json['parent'] ) ? $json['parent'] : array(),
				'ancestor'        => isset( $json['ancestor'] ) && is_array( $json['ancestor'] ) ? $json['ancestor'] : array(),
				'is_dynamic'      => false,
				'registration'    => 'editor-only',
			);
		}

		ksort( $blokken );

		return array_values( $blokken );
	}

	/**
	 * Splits de contextsleutels in eigen en toegevoegd.
	 *
	 * @param string        $naam Bloknaam.
	 * @param WP_Block_Type $type Het geregistreerde bloktype.
	 *
	 * @return array{alles:array,eigen:array,toegevoegd:array}
	 */
	private static function splits_context( $naam, $type ) {
		$alles = isset( $type->uses_context ) && is_array( $type->uses_context ) ? $type->uses_context : array();
		$json  = self::get_block_json_map();

		$eigen = isset( $json[ $naam ]['usesContext'] ) && is_array( $json[ $naam ]['usesContext'] )
			? $json[ $naam ]['usesContext']
			: array();

		// Staat er geen block.json op schijf, dan valt er niets te vergelijken
		// en is "toegevoegd" niet vast te stellen. Dan liever niets beweren.
		if ( empty( $eigen ) && ! isset( $json[ $naam ] ) ) {
			return array( 'alles' => $alles, 'eigen' => array(), 'toegevoegd' => array() );
		}

		return array(
			'alles'      => $alles,
			'eigen'      => array_values( array_intersect( $alles, $eigen ) ),
			'toegevoegd' => array_values( array_diff( $alles, $eigen ) ),
		);
	}

	/**
	 * De attributen van een blok zonder de twee die WordPress er zelf bij zet.
	 *
	 * WP_Block_Type voegt 'lock' en 'metadata' aan élk blok toe, maar alleen
	 * wanneer het blok ze niet zelf al definieert
	 * (wp-includes/class-wp-block-type.php:559-563). Meetellen zou betekenen
	 * dat kadence/rowlayout 172 attributen meldt terwijl zijn block.json er 170
	 * heeft. Een blok dat 'lock' wél zelf definieert — kadence/repeatertemplate
	 * doet dat — houdt hem, want dan is het een eigen attribuut. Daarom
	 * vergelijken op identieke definitie en niet op sleutel.
	 *
	 * @param WP_Block_Type $type Het blok.
	 *
	 * @return array
	 */
	private static function eigen_attributen( $type ) {
		$attributen = is_array( $type->attributes ) ? $type->attributes : array();

		// Attributen die Kadence pas in de editor bijplaatst staan niet in het
		// PHP-register. Zonder deze aanvulling geldt "niet gevonden" hier als
		// "bestaat niet", en gooit normaliseer_attributen ze bij elke
		// schrijfactie weg — hetzelfde patroon als de metadata-bug van 1.2.0,
		// maar met gevolgen: op een filterblok zit hier de taxonomie in.
		if ( class_exists( 'Kadence_MCP_Profielen' ) && isset( $type->name ) ) {
			foreach ( Kadence_MCP_Profielen::injecties( (string) $type->name ) as $sleutel => $definitie ) {
				if ( ! isset( $attributen[ $sleutel ] ) ) {
					$attributen[ $sleutel ] = $definitie;
				}
			}
		}

		if ( ! defined( 'WP_Block_Type::GLOBAL_ATTRIBUTES' ) && ! class_exists( 'WP_Block_Type' ) ) {
			return $attributen;
		}

		$globaal = defined( 'WP_Block_Type::GLOBAL_ATTRIBUTES' )
			? constant( 'WP_Block_Type::GLOBAL_ATTRIBUTES' )
			: array();

		foreach ( $globaal as $sleutel => $definitie ) {
			if ( isset( $attributen[ $sleutel ] ) && $attributen[ $sleutel ] === $definitie ) {
				unset( $attributen[ $sleutel ] );
			}
		}

		return $attributen;
	}

	/**
	 * De block.json-definities op schijf, op bloknaam.
	 *
	 * @return array<string,array>
	 */
	public static function get_block_json_map() {
		if ( null === self::$json_cache ) {
			self::get_block_source_map();
		}

		return null === self::$json_cache ? array() : self::$json_cache;
	}

	/**
	 * Eén blok, met zijn attributen.
	 *
	 * @param string $naam Volledige bloknaam, bijvoorbeeld 'kadence/rowlayout'.
	 *
	 * @return array|WP_Error
	 */
	public static function get_block( $naam ) {
		if ( ! class_exists( 'WP_Block_Type_Registry' ) ) {
			return new WP_Error(
				'kadence_mcp_no_block_registry',
				__( 'De blokkenregistratie is niet beschikbaar.', 'mcp-abilities-kadence' )
			);
		}

		$type    = WP_Block_Type_Registry::get_instance()->get_registered( $naam );
		$bronnen = self::get_block_source_map();

		// Niet in het PHP-register betekent niet "bestaat niet". Kadence
		// registreert kadence/pane, kadence/tab, kadence/countdown-inner en
		// kadence/countdown-timer uitsluitend in JavaScript. Die blokken komen
		// wél in de inhoud voor — een accordeon is alledaags — dus ze hier
		// weigeren zou onjuist zijn. De block.json op schijf beschrijft ze
		// volledig; het veld 'registration' zegt waar het vandaan komt.
		if ( ! $type ) {
			$json = self::get_block_json_map();

			if ( ! isset( $json[ $naam ] ) ) {
				return new WP_Error(
					'kadence_mcp_block_not_found',
					sprintf(
						/* translators: %s: block name. */
						__( 'Blok "%s" bestaat niet op deze site — niet in het blokkenregister en niet als block.json in een van de Kadence-plugins.', 'mcp-abilities-kadence' ),
						$naam
					)
				);
			}

			$def = $json[ $naam ];

			return array(
				'name'         => $naam,
				'title'        => isset( $def['title'] ) ? (string) $def['title'] : '',
				'description'  => isset( $def['description'] ) ? (string) $def['description'] : '',
				'category'     => isset( $def['category'] ) ? (string) $def['category'] : '',
				'source'       => isset( $bronnen[ $naam ] ) ? $bronnen[ $naam ] : 'onbekend',
				'parent'       => isset( $def['parent'] ) && is_array( $def['parent'] ) ? $def['parent'] : array(),
				'ancestor'     => isset( $def['ancestor'] ) && is_array( $def['ancestor'] ) ? $def['ancestor'] : array(),
				'uses_context'     => isset( $def['usesContext'] ) ? $def['usesContext'] : array(),
				'provides_context' => isset( $def['providesContext'] ) ? $def['providesContext'] : array(),
				'allowed_blocks'   => isset( $def['allowedBlocks'] ) ? $def['allowedBlocks'] : array(),
				'keywords'         => isset( $def['keywords'] ) ? $def['keywords'] : array(),
				'supports'     => isset( $def['supports'] ) && is_array( $def['supports'] ) ? $def['supports'] : array(),
				'attributes'   => isset( $def['attributes'] ) && is_array( $def['attributes'] ) ? $def['attributes'] : array(),
				'registration' => 'editor-only',
			);
		}

		$context = self::splits_context( $naam, $type );

		return array(
			'name'         => $naam,
			'title'        => (string) $type->title,
			'description'  => (string) $type->description,
			'category'     => (string) $type->category,
			'source'       => isset( $bronnen[ $naam ] ) ? $bronnen[ $naam ] : 'onbekend',
			'parent'       => is_array( $type->parent ) ? $type->parent : array(),
			'ancestor'     => isset( $type->ancestor ) && is_array( $type->ancestor ) ? $type->ancestor : array(),
			// usesContext en providesContext beschrijven de GEGEVENSSTROOM, waar
			// parent en ancestor over PLAATSING gaan. Een query-card weet welke
			// post hij rendert doordat kadence/query die context levert. Zonder
			// deze velden is niet uit te leggen waarom dynamische inhoud in het
			// ene blok werkt en in het andere niet.
			'uses_context'     => $context['alles'],
			// Wat het blok ZELF declareert tegenover wat een andere plugin er
			// tijdens het draaien bij zet. GP Entry Blocks hangt zijn
			// entry-context via het filter get_block_type_uses_context aan élk
			// niet-GPEB blok (gp-entry-blocks/includes/class-conditional-logic.php:19).
			// Zonder dit onderscheid lijkt het alsof Kadence die context
			// bedoeld heeft, en ga je hem in Kadence zoeken.
			'uses_context_own'   => $context['eigen'],
			'uses_context_added' => $context['toegevoegd'],
			'provides_context' => isset( $type->provides_context ) && is_array( $type->provides_context ) ? $type->provides_context : array(),
			'allowed_blocks'   => isset( $type->allowed_blocks ) && is_array( $type->allowed_blocks ) ? $type->allowed_blocks : array(),
			'keywords'         => isset( $type->keywords ) && is_array( $type->keywords ) ? $type->keywords : array(),
			'supports'     => is_array( $type->supports ) ? $type->supports : array(),
			'attributes'   => self::eigen_attributen( $type ),
			'registration' => 'php',
		);
	}

	/**
	 * Maak één attribuutdefinitie klein genoeg om te versturen.
	 *
	 * Een standaardwaarde kan zelf een array van tientallen regels zijn —
	 * rowlayout heeft er zo een paar. Die worden samengevat in plaats van
	 * uitgeschreven, want een agent heeft aan de vorm genoeg en de
	 * uitvoerlimiet van MCP is echt.
	 *
	 * @param string $naam       Attribuutnaam.
	 * @param array  $definitie  De definitie uit block.json.
	 *
	 * @return array
	 */
	public static function vat_attribuut_samen( $naam, $definitie, $bloknaam = '' ) {
		$type = isset( $definitie['type'] ) ? $definitie['type'] : '';

		$samenvatting = array(
			'name' => $naam,
			'type' => is_array( $type ) ? implode( '|', $type ) : (string) $type,
		);

		$bekend = '' === $bloknaam ? null : Kadence_MCP_Profielen::waardenlijst( $bloknaam, $naam );

		if ( '' !== $bloknaam ) {
			$genegeerd = Kadence_MCP_Profielen::genegeerd( $bloknaam, $naam );

			if ( '' !== $genegeerd ) {
				$samenvatting['ignored_by_render'] = true;
				$samenvatting['ignored_note']      = $genegeerd;
			}
		}

		if ( isset( $definitie['enum'] ) && is_array( $definitie['enum'] ) ) {
			$samenvatting['enum'] = array_slice( $definitie['enum'], 0, 20 );
		} elseif ( null !== $bekend ) {
			// Geen enum in block.json, maar de plug-in kent de waarden wel,
			// afgelezen uit Kadence zelf. validate-write toetst hierop.
			$samenvatting['known_values'] = $bekend;
			$samenvatting['note']         = __( 'geen enum in het schema; deze waarden zijn afgelezen uit de editor en de render van Kadence, en validate-write toetst erop.', 'mcp-abilities-kadence' );
		} elseif ( 'string' === $samenvatting['type'] ) {
			// Een kale string zonder enum is een blinde vlek, en zwijgen daarover
			// leest als goedkeuring. Kadence legt de toegestane waarden van zulke
			// attributen niet in block.json vast, dus rest_validate_value_from_schema()
			// — en daarmee validate-write — laat élke tekst door. Op 11-09-2026 kostte
			// dat een ronde: colLayout kreeg de verzonnen waarde "thirds", de toets gaf
			// ok, en de rij stond in de editor als losse blokken onder elkaar.
			$samenvatting['values_not_validated'] = true;
			$samenvatting['note'] = __( 'geen enum in het schema: elke tekst wordt geaccepteerd, ook een die Kadence niet kent. Verzin hier geen waarde — lees hem af van een bestaand blok met get-raw-markup, of uit de broncode van Kadence.', 'mcp-abilities-kadence' );
		}

		if ( ! array_key_exists( 'default', $definitie ) ) {
			return $samenvatting;
		}

		$standaard = $definitie['default'];

		if ( is_scalar( $standaard ) || null === $standaard ) {
			$samenvatting['default'] = $standaard;

			return $samenvatting;
		}

		$gecodeerd = wp_json_encode( $standaard );

		if ( is_string( $gecodeerd ) && strlen( $gecodeerd ) <= 200 ) {
			$samenvatting['default'] = $standaard;

			return $samenvatting;
		}

		// Te groot om mee te sturen: beschrijf hem in plaats van hem te tonen.
		$samenvatting['default_summary'] = is_array( $standaard )
			? sprintf(
				/* translators: %d: number of entries. */
				__( 'array met %d elementen', 'mcp-abilities-kadence' ),
				count( $standaard )
			)
			: __( 'object', 'mcp-abilities-kadence' );

		return $samenvatting;
	}

	/**
	 * Prefixen van post meta die deze plugin mag teruggeven.
	 *
	 * Een allowlist en geen blocklist: post meta is de plek waar plugins van
	 * alles neerzetten, van API-sleutels tot persoonsgegevens. Alleen wat
	 * aantoonbaar van Kadence is gaat eruit. De querydefinitie van een
	 * kadence_query-post staat bijvoorbeeld in _kad_query_query.
	 */
	const META_PREFIXEN = array( '_kad_', '_kt_', 'kadence_', '_kadence' );

	/**
	 * De Kadence-post-meta van één post.
	 *
	 * @param int $post_id Het post-ID.
	 *
	 * @return array {
	 *     @type array $meta      De toegestane sleutels met hun waarde.
	 *     @type int   $withheld  Hoeveel sleutels zijn weggelaten.
	 * }
	 */
	public static function get_kadence_meta( $post_id ) {
		$alle = get_post_meta( $post_id );

		if ( ! is_array( $alle ) ) {
			return array( 'meta' => array(), 'withheld' => 0 );
		}

		$meta       = array();
		$weggelaten = 0;

		foreach ( $alle as $sleutel => $waarden ) {
			$toegestaan = false;

			foreach ( self::META_PREFIXEN as $prefix ) {
				if ( 0 === strpos( $sleutel, $prefix ) ) {
					$toegestaan = true;
					break;
				}
			}

			if ( ! $toegestaan ) {
				++$weggelaten;
				continue;
			}

			// get_post_meta zonder sleutel geeft altijd arrays terug, ook bij
			// één waarde. Uitpakken, en geserialiseerde waarden ontvouwen —
			// juist daar zit de querydefinitie in.
			$waarde = is_array( $waarden ) && 1 === count( $waarden ) ? reset( $waarden ) : $waarden;

			if ( is_string( $waarde ) && is_serialized( $waarde ) ) {
				$waarde = maybe_unserialize( $waarde );
			}

			$meta[ $sleutel ] = $waarde;
		}

		ksort( $meta );

		return array( 'meta' => $meta, 'withheld' => $weggelaten );
	}

	/**
	 * Is deze post ook via een normale query zichtbaar voor de huidige gebruiker?
	 *
	 * read_post is de meta cap van WordPress zelf, maar een plugin die inhoud
	 * afschermt werkt vaak via pre_get_posts en niet via die cap. Dan leest
	 * inspect-post iets dat find-post niet vindt. Deze controle blokkeert
	 * niets — hij maakt dat verschil alleen zichtbaar in de statusregel.
	 *
	 * @param int    $post_id   Het post-ID.
	 * @param string $post_type Het posttype.
	 *
	 * @return bool
	 */
	public static function is_query_zichtbaar( $post_id, $post_type ) {
		$treffers = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => 'any',
				'p'                => (int) $post_id,
				'numberposts'      => 1,
				'fields'           => 'ids',
				'suppress_filters' => false,
			)
		);

		return ! empty( $treffers );
	}

	/**
	 * In welke groep hoort dit attribuut?
	 *
	 * Mechanisch afgeleid uit de naam, niet handgeschreven — dan veroudert het
	 * niet bij een Kadence-update en staat er geen interpretatie in die ik er
	 * zelf bij verzonnen heb. De volgorde telt: de eerste treffer wint, dus
	 * specifieke patronen staan boven algemene.
	 *
	 * @param string $naam Attribuutnaam.
	 *
	 * @return string
	 */
	public static function groep_van_attribuut( $naam ) {
		$laag = strtolower( $naam );

		$regels = array(
			// loggedIn en loggedOut horen hier en niet bij 'overig': dat is
			// zichtbaarheid op grond van inlogstatus, en het is bovendien een
			// instelling die ALLEEN op kadence/rowlayout bestaat. Een Sectie
			// kent enkel de breakpoint-varianten vsdesk, vstablet en vsmobile.
			'conditioneel' => array( 'conditional', 'dynamic', 'visibility', 'inquery', 'loggedin', 'loggedout' ),
			'responsive'   => array( 'mobile', 'tablet', 'collapse', 'direction', 'justifycontent', 'flexbasis', 'flexgrow', 'gridarea', 'vsmobile', 'vstablet', 'vsdesk' ),
			// colLayout bepaalt de kolomverhouding en viel eerder in 'overig'.
			// Juist dat attribuut verklaarde waarom twee mega-menus verschillend
			// oogden, dus het hoort vindbaar te zijn.
			'indeling'     => array( 'collayout', 'columns', 'inheritmaxwidth', 'masonry' ),
			'achtergrond'  => array( 'background', 'bgcolor', 'bgimg', 'gradient', 'overlay' ),
			'rand'         => array( 'border', 'shadow', 'radius' ),
			'spacing'      => array( 'padding', 'margin', 'gutter' ),
			'typografie'   => array( 'font', 'text', 'letterspacing', 'lineheight', 'linetype', 'typography', 'size', 'mark' ),
			'kleur'        => array( 'color' ),
			'link'         => array( 'link', 'href', 'target', 'url' ),
			'afmeting'     => array( 'width', 'height', 'maxwidth', 'minheight', 'align' ),
			'identiteit'   => array( 'uniqueid', 'anchor', 'classname', 'metadata', 'kbversion', 'id', 'htmltag', 'lock' ),
		);

		foreach ( $regels as $groep => $delen ) {
			foreach ( $delen as $deel ) {
				if ( false !== strpos( $laag, $deel ) ) {
					return $groep;
				}
			}
		}

		return 'overig';
	}

	/**
	 * Zoek één blok in een geparste boom op zijn uniqueID.
	 *
	 * @param array  $blokken   Resultaat van parse_blocks().
	 * @param string $unique_id De te zoeken uniqueID.
	 *
	 * @return array|null Het blok, of null.
	 */
	public static function zoek_op_unique_id( $blokken, $unique_id ) {
		foreach ( $blokken as $blok ) {
			$attrs = isset( $blok['attrs'] ) && is_array( $blok['attrs'] ) ? $blok['attrs'] : array();

			if ( isset( $attrs['uniqueID'] ) && (string) $attrs['uniqueID'] === (string) $unique_id ) {
				return $blok;
			}

			if ( ! empty( $blok['innerBlocks'] ) ) {
				$treffer = self::zoek_op_unique_id( $blok['innerBlocks'], $unique_id );

				if ( null !== $treffer ) {
					return $treffer;
				}
			}
		}

		return null;
	}

	/**
	 * De posttypes die Kadence registreert.
	 *
	 * Op prefix ontdekt in plaats van uit een vaste lijst, zodat een nieuwe
	 * Kadence-versie of een ander Kadence-product geen codewijziging vraagt.
	 *
	 * @return WP_Post_Type[]
	 */
	public static function get_post_types() {
		$types = array();

		foreach ( get_post_types( array(), 'objects' ) as $naam => $object ) {
			if ( 0 === strpos( $naam, self::POST_TYPE_PREFIX ) || in_array( $naam, self::EXTRA_POST_TYPES, true ) ) {
				$types[ $naam ] = $object;
			}
		}

		ksort( $types );

		return $types;
	}

	/**
	 * Vlak een blokboom uit tot een lijst die te overzien is.
	 *
	 * @param array $blokken   Het resultaat van parse_blocks().
	 * @param array $opties    {
	 *     @type string[] $attributes Welke attributen mee mogen. Leeg is geen enkele.
	 *     @type int      $max        Hoeveel blokken maximaal.
	 *     @type bool     $alleen_kadence Alleen kadence/*-blokken tonen.
	 * }
	 * @param int   $diepte    Interne teller.
	 * @param array $resultaat Interne verzamelaar.
	 *
	 * @return array
	 */
	public static function plat_blokken( $blokken, $opties, $diepte = 0, &$resultaat = array() ) {
		foreach ( $blokken as $blok ) {
			if ( count( $resultaat ) >= $opties['max'] ) {
				return $resultaat;
			}

			$naam = isset( $blok['blockName'] ) ? (string) $blok['blockName'] : '';

			// parse_blocks() geeft ook de witruimte tussen blokken terug, als
			// een blok zonder naam. Die zijn hier ruis.
			if ( '' === $naam ) {
				continue;
			}

			$is_kadence = 0 === strpos( $naam, self::BLOCK_PREFIX );

			if ( ! $opties['alleen_kadence'] || $is_kadence ) {
				$attrs = isset( $blok['attrs'] ) && is_array( $blok['attrs'] ) ? $blok['attrs'] : array();

				$regel = array(
					'block'    => $naam,
					'depth'    => $diepte,
					'children' => isset( $blok['innerBlocks'] ) ? count( $blok['innerBlocks'] ) : 0,
				);

				// De zichtbare tekst van een blok staat in de markup, niet in
				// de attributen. kadence/listitem, kadence/advancedheading en
				// kadence/singlebtn bewaren hun tekst zo. Bij een container als
				// rowlayout levert dit terecht niets op: daar is de innerHTML
				// alleen het omhullende element.
				if ( ! empty( $opties['tekst'] ) ) {
					$tekst = self::tekst_uit_blok( $blok );

					if ( '' !== $tekst ) {
						$regel['text'] = $tekst;
					}
				}

				foreach ( $opties['attributes'] as $sleutel ) {
					if ( ! array_key_exists( $sleutel, $attrs ) ) {
						continue;
					}

					$waarde = $attrs[ $sleutel ];

					if ( is_scalar( $waarde ) || null === $waarde ) {
						$regel['attrs'][ $sleutel ] = $waarde;
						continue;
					}

					// Een attribuut dat expliciet in full_attributes staat komt
					// ongekort terug. Zonder die ontsnapping is bijvoorbeeld
					// 'titles' op kadence/tabs alleen als "array met 4
					// elementen" te zien, en dan weet je het aantal wel maar de
					// inhoud niet.
					$regel['attrs'][ $sleutel ] = in_array( $sleutel, $opties['volledig'], true )
						? $waarde
						: self::vat_waarde_samen( $waarde );
				}

				$resultaat[] = $regel;
			}

			if ( ! empty( $blok['innerBlocks'] ) ) {
				self::plat_blokken( $blok['innerBlocks'], $opties, $diepte + 1, $resultaat );
			}
		}

		return $resultaat;
	}

	/**
	 * De zichtbare tekst van één blok, zonder die van zijn kinderen.
	 *
	 * @param array $blok Eén blok uit parse_blocks().
	 *
	 * @return string
	 */
	public static function tekst_uit_blok( $blok ) {
		$html = isset( $blok['innerHTML'] ) ? (string) $blok['innerHTML'] : '';

		if ( '' === $html ) {
			return '';
		}

		$tekst = wp_strip_all_tags( $html );

		// Tags weghalen decodeert geen entiteiten. Zonder deze stap komt
		// "Criteria &amp; deadlines" er letterlijk zo uit.
		$tekst = html_entity_decode( $tekst, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$tekst = preg_replace( '/\s+/u', ' ', $tekst );
		$tekst = trim( (string) $tekst );

		if ( '' === $tekst ) {
			return '';
		}

		// Lange lappen tekst afkappen: de vraag is doorgaans wélke tekst er
		// staat, niet de volledige alinea. Wie alles wil, leest de post zelf.
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $tekst ) > 300 ) {
			return mb_substr( $tekst, 0, 300 ) . '…';
		}

		if ( ! function_exists( 'mb_strlen' ) && strlen( $tekst ) > 300 ) {
			return substr( $tekst, 0, 300 ) . '…';
		}

		return $tekst;
	}

	/**
	 * De binnenkant van een blok, ONGEWIJZIGD.
	 *
	 * Het verschil met tekst_uit_blok() is wezenlijk. Die functie is bedoeld om
	 * te LATEN ZIEN welke tekst ergens staat: hij haalt de tags weg en kapt af
	 * op 300 tekens. Prima voor een overzicht, rampzalig om een blok mee te
	 * herbouwen — dan verdwijnt een <mark class="kt-highlight"> of een <a> uit
	 * de tekst, en een alinea langer dan 300 tekens eindigt op een beletselteken.
	 *
	 * Tot 1.14.0 gebruikte replace-block de verkeerde van de twee. Het gevolg
	 * was dat elke herbouw van een chip zijn opmaak verloor, en dat viel alleen
	 * op omdat de kleur zichtbaar wegviel; bij een afgekapte alinea zou niemand
	 * het gemerkt hebben.
	 *
	 * @param array $blok Het blok.
	 *
	 * @return string De inhoud tussen de buitenste tags, zonder die tags.
	 */
	public static function binnenhtml_uit_blok( $blok ) {
		$html = isset( $blok['innerHTML'] ) ? trim( (string) $blok['innerHTML'] ) : '';

		if ( '' === $html ) {
			return '';
		}

		// De buitenste wrapper eraf, de rest ongemoeid laten. De terugverwijzing
		// \1 zorgt dat de sluittag bij de openingstag hoort; .* is greedy, dus
		// bij geneste gelijke tags wordt het BUITENSTE paar gepakt.
		if ( preg_match( '/^<([a-zA-Z][a-zA-Z0-9]*)\b[^>]*>(.*)<\/\1>$/s', $html, $treffer ) ) {
			return trim( $treffer[2] );
		}

		return $html;
	}

	/**
	 * Beschrijf een niet-scalaire waarde kort.
	 *
	 * @param mixed $waarde De waarde.
	 *
	 * @return string
	 */
	private static function vat_waarde_samen( $waarde ) {
		$gecodeerd = wp_json_encode( $waarde );

		if ( is_string( $gecodeerd ) && strlen( $gecodeerd ) <= 160 ) {
			return $gecodeerd;
		}

		return is_array( $waarde )
			? sprintf(
				/* translators: %d: number of entries. */
				__( 'array met %d elementen', 'mcp-abilities-kadence' ),
				count( $waarde )
			)
			: __( 'object', 'mcp-abilities-kadence' );
	}

	/**
	 * Genereer een verse uniqueID in het formaat dat Kadence zelf gebruikt.
	 *
	 * Waargenomen vorm: {postID}_{6 hex}-{2 hex}, bijvoorbeeld 416_748504-1f.
	 * De post-ID als prefix is precies wat botsingen tussen posts onwaarschijnlijk
	 * maakt; daarom krijgt een kopie de ID van zijn NIEUWE post, niet die van de
	 * bron.
	 *
	 * @param int   $post_id  De doelpost.
	 * @param array $bezet    Reeds gebruikte ID's, om botsing uit te sluiten.
	 *
	 * @return string
	 */
	public static function nieuwe_unique_id( $post_id, $bezet = array() ) {
		for ( $poging = 0; $poging < 50; $poging++ ) {
			$kandidaat = sprintf(
				'%d_%s-%s',
				(int) $post_id,
				substr( bin2hex( random_bytes( 3 ) ), 0, 6 ),
				substr( bin2hex( random_bytes( 1 ) ), 0, 2 )
			);

			if ( ! isset( $bezet[ $kandidaat ] ) ) {
				return $kandidaat;
			}
		}

		// Vijftig botsingen op rij is praktisch onmogelijk; als het toch gebeurt
		// is stoppen beter dan een ID uitdelen dat al bestaat.
		return '';
	}

	/**
	 * Vervang alle uniqueID's in een blokboom volgens een kaart.
	 *
	 * De ID staat op twee plekken: in het attribuut en verweven in de markup,
	 * in klassen als kadence-column416_2f6495-5d, kb-btns…, kt-adv-heading… en
	 * in data-kb-block. Door op de hele innerHTML te vervangen hoef ik geen
	 * enkel klassepatroon te kennen — en die patronen verschillen per blok en
	 * per Kadence-versie, dus dat is precies wat je niet wil bijhouden.
	 *
	 * @param array $blokken De boom.
	 * @param array $kaart   oud => nieuw.
	 *
	 * @return array
	 */
	public static function hernoem_unique_ids( $blokken, $kaart ) {
		foreach ( $blokken as $i => $blok ) {
			if ( isset( $blok['attrs']['uniqueID'] ) ) {
				$oud = (string) $blok['attrs']['uniqueID'];

				if ( isset( $kaart[ $oud ] ) ) {
					$blokken[ $i ]['attrs']['uniqueID'] = $kaart[ $oud ];
				}
			}

			foreach ( array( 'innerHTML' ) as $veld ) {
				if ( isset( $blok[ $veld ] ) && is_string( $blok[ $veld ] ) && '' !== $blok[ $veld ] ) {
					$blokken[ $i ][ $veld ] = strtr( $blok[ $veld ], $kaart );
				}
			}

			if ( isset( $blok['innerContent'] ) && is_array( $blok['innerContent'] ) ) {
				foreach ( $blok['innerContent'] as $j => $stuk ) {
					if ( is_string( $stuk ) && '' !== $stuk ) {
						$blokken[ $i ]['innerContent'][ $j ] = strtr( $stuk, $kaart );
					}
				}
			}

			if ( ! empty( $blok['innerBlocks'] ) ) {
				$blokken[ $i ]['innerBlocks'] = self::hernoem_unique_ids( $blok['innerBlocks'], $kaart );
			}
		}

		return $blokken;
	}

	/**
	 * Breng attributen in de vorm die de editor ook zou wegschrijven.
	 *
	 * Gemeten op de productiesite, 11-09-2026: van de 170 attributen van
	 * kadence/rowlayout stonden er 23 in de markup, geen enkele gelijk aan zijn
	 * standaardwaarde, en de volgorde was exact die van block.json — niet
	 * alfabetisch.
	 *
	 * Daaruit volgen drie regels, en die worden hier afgedwongen in plaats van
	 * onthouden:
	 *
	 * 1. Wat gelijk is aan de standaardwaarde valt weg. Anders schrijf je iets
	 *    dat de editor zou hebben weggelaten, en verschijnt er ruis zodra
	 *    iemand het blok opent en opslaat.
	 * 2. De volgorde volgt block.json.
	 * 3. Wat niet in het schema staat gaat eruit — dat wordt bij het renderen
	 *    toch stil genegeerd (WP_Block_Type::prepare_attributes_for_render).
	 *    Uitgezonderd CORE_ATTRIBUTEN: die staan in geen enkele block.json maar
	 *    zijn wel echt, en weggooien is dataverlies.
	 *
	 * @param string $bloknaam De bloknaam.
	 * @param array  $attrs    De attributen.
	 *
	 * @return array{attrs:array,dropped_default:array,dropped_unknown:array}
	 */
	public static function normaliseer_attributen( $bloknaam, $attrs ) {
		$blok = self::get_block( $bloknaam );

		if ( is_wp_error( $blok ) || empty( $blok['attributes'] ) ) {
			// Geen schema bekend: niets filteren, want dan zou je data weggooien
			// op grond van onwetendheid.
			return array( 'attrs' => $attrs, 'dropped_default' => array(), 'dropped_unknown' => array() );
		}

		$schema     = $blok['attributes'];
		$standaard  = array();
		$onbekend   = array();
		$behouden   = array();

		$core = array();

		foreach ( $attrs as $sleutel => $waarde ) {
			if ( in_array( $sleutel, self::CORE_ATTRIBUTEN, true ) ) {
				// Ongemoeid laten: geen schema om tegen te toetsen, dus ook geen
				// grond om iets weg te gooien.
				$core[ $sleutel ] = $waarde;
				continue;
			}

			if ( ! isset( $schema[ $sleutel ] ) ) {
				$onbekend[] = $sleutel;
				continue;
			}

			$def = $schema[ $sleutel ];

			// Vergelijken op de gecodeerde vorm: arrays en objecten zijn anders
			// niet betrouwbaar gelijk te stellen.
			if ( array_key_exists( 'default', $def ) && wp_json_encode( $def['default'] ) === wp_json_encode( $waarde ) ) {
				$standaard[] = $sleutel;
				continue;
			}

			$behouden[ $sleutel ] = $waarde;
		}

		// Sorteren op de volgorde van block.json.
		$geordend = array();

		foreach ( array_keys( $schema ) as $sleutel ) {
			if ( array_key_exists( $sleutel, $behouden ) ) {
				$geordend[ $sleutel ] = $behouden[ $sleutel ];
			}
		}

		// De core-attributen komen erachteraan. Dat is waar de editor ze ook
		// neerzet: op pagina 1471 stond metadata achter kbVersion.
		foreach ( $core as $sleutel => $waarde ) {
			$geordend[ $sleutel ] = $waarde;
		}

		return array(
			'attrs'           => $geordend,
			'dropped_default' => $standaard,
			'dropped_unknown' => $onbekend,
		);
	}

	/**
	 * De markup herleid tot wat er werkelijk opgeslagen zou worden.
	 *
	 * Het token van generate-section dekte tot 1.17.0 de LETTERLIJKE tekenreeks
	 * van de markup. Dat is te streng. Een blokcommentaar bevat JSON, en JSON
	 * kent meer dan één geldige schrijfwijze van dezelfde waarde: een agent die
	 * de markup overneemt uit een JSON-antwoord levert `--` terug waar
	 * er in dat antwoord letterlijk `--` stond. De blokkenboom is dan identiek,
	 * de tekenreeks niet, en insert-blocks wees dat af met "je voorstel is
	 * anders" zonder te kunnen zeggen wáár. Dat is een half uur zoeken waard
	 * geweest en het is geen bescherming: er werd niets gevaarlijks
	 * tegengehouden, alleen iets onschuldigs.
	 *
	 * Door te hashen op de geserialiseerde boom telt alleen wat er straks in de
	 * database komt. Verandert er een attribuut of een tekst, dan verandert die
	 * boom wél en vervalt het token nog steeds. De poort blijft dus dicht voor
	 * wat hij moest tegenhouden.
	 *
	 * @param string $markup De aangeleverde blokmarkup.
	 *
	 * @return string De genormaliseerde markup, of '' bij lege invoer.
	 */
	public static function token_markup( $markup ) {
		$markup = (string) $markup;

		if ( '' === trim( $markup ) ) {
			return '';
		}

		return self::serialiseer( self::schoon_blokken( parse_blocks( $markup ) ) );
	}

	/**
	 * Het token dat een goedgekeurde toetsing bewijst.
	 *
	 * Zonder dit is `validate-write` een aansporing en geen poort: een agent kan
	 * hem gewoon overslaan. De toekomstige schrijf-ability eist dit token en
	 * berekent hem opnieuw uit wat er daadwerkelijk geschreven gaat worden.
	 * Wijkt er iets af — een ander attribuut, een andere waarde, een andere post
	 * — dan komt er een andere hash uit en weigert de schrijfactie.
	 *
	 * De wijzigingsdatum van de post zit erin, zodat een toetsing vervalt zodra
	 * iemand de post intussen heeft aangepast. Een goedkeuring op een versie die
	 * niet meer bestaat is geen goedkeuring.
	 *
	 * wp_hash() gebruikt de salts van de site, dus het token is niet buiten deze
	 * installatie na te maken.
	 *
	 * @param WP_Post $post      De post.
	 * @param string  $unique_id Het blok.
	 * @param array   $attrs     De getoetste attributen.
	 *
	 * @return string
	 */
	public static function schrijf_token( $post, $unique_id, $attrs ) {
		// Sleutels sorteren zodat de volgorde van invoer niet uitmaakt. Dit gaat
		// één niveau diep; geneste arrays houden hun eigen volgorde, en dat is
		// hier juist gewenst — bij een array van 4 IS de volgorde de betekenis
		// (boven, rechts, onder, links).
		$genormaliseerd = $attrs;
		ksort( $genormaliseerd );

		$grondslag = wp_json_encode(
			array(
				'post'     => (int) $post->ID,
				'modified' => (string) $post->post_modified_gmt,
				'block'    => (string) $unique_id,
				'attrs'    => $genormaliseerd,
			)
		);

		// Het token bestaat uit twee helften van samen 32 tekens. De eerste zes
		// dekken post + wijzigingsdatum, de rest dekt het hele voorstel.
		//
		// Daarmee kan een afgewezen token zeggen WAT er niet klopt. Tot 1.7.3
		// luidde elke afwijzing "de post is gewijzigd of je voorstel is anders",
		// en die dubbelzinnigheid liet duplicate-blocks zes versies lang stuk
		// staan: elke aanroep gaf die melding, en hij klonk als normaal gedrag.
		$versie = wp_json_encode( array( 'post' => (int) $post->ID, 'modified' => (string) $post->post_modified_gmt ) );

		return 'kmcp1_' . substr( wp_hash( (string) $versie ), 0, 6 ) . substr( wp_hash( (string) $grondslag ), 0, 26 );
	}

	/**
	 * De attribuutdefinitie van één attribuut op één blok.
	 *
	 * @param string $bloknaam Volledige bloknaam.
	 * @param string $attribuut Attribuutnaam.
	 *
	 * @return array|null
	 */
	public static function attribuut_definitie( $bloknaam, $attribuut ) {
		$blok = self::get_block( $bloknaam );

		if ( is_wp_error( $blok ) || ! isset( $blok['attributes'][ $attribuut ] ) ) {
			return null;
		}

		return is_array( $blok['attributes'][ $attribuut ] ) ? $blok['attributes'][ $attribuut ] : array();
	}

	/**
	 * Toets een waarde aan de gedeclareerde definitie.
	 *
	 * @param array $definitie De attribuutdefinitie uit block.json.
	 * @param mixed $waarde    De voorgestelde waarde.
	 *
	 * @return string '' als de waarde deugt, anders de reden.
	 */
	public static function toets_waarde( $definitie, $waarde ) {
		// Gebruik dezelfde functie als WordPress zelf. Bij het renderen draait
		// WP_Block_Type::prepare_attributes_for_render() precies deze toets
		// (class-wp-block-type.php:517) en gooit een waarde die niet slaagt
		// STIL weg, waarna de standaardwaarde ervoor in de plaats komt. Wie hier
		// een eigen, soepeler toets zou doen, keurt dus iets goed dat straks
		// zonder melding wordt genegeerd.
		if ( function_exists( 'rest_validate_value_from_schema' ) ) {
			$uitkomst = rest_validate_value_from_schema( $waarde, $definitie, 'waarde' );

			if ( is_wp_error( $uitkomst ) ) {
				return sprintf(
					/* translators: %s: the validation message from WordPress. */
					__( 'WordPress keurt deze waarde af bij het renderen (%s) en vervangt hem dan stil door de standaardwaarde.', 'mcp-abilities-kadence' ),
					$uitkomst->get_error_message()
				);
			}

			return '';
		}

		// Terugval voor het geval de REST-laag niet geladen is.
		$types = isset( $definitie['type'] ) ? (array) $definitie['type'] : array();

		if ( ! empty( $types ) ) {
			$past = false;

			foreach ( $types as $type ) {
				switch ( $type ) {
					case 'string':
						$past = $past || is_string( $waarde );
						break;
					case 'number':
					case 'integer':
						$past = $past || is_int( $waarde ) || is_float( $waarde );
						break;
					case 'boolean':
						$past = $past || is_bool( $waarde );
						break;
					case 'array':
						$past = $past || is_array( $waarde );
						break;
					case 'object':
						$past = $past || is_array( $waarde ) || is_object( $waarde );
						break;
					case 'null':
						$past = $past || null === $waarde;
						break;
					default:
						$past = true;
				}
			}

			if ( ! $past ) {
				return sprintf(
					/* translators: 1: declared type, 2: given type. */
					__( 'verwacht type %1$s, kreeg %2$s', 'mcp-abilities-kadence' ),
					implode( '|', $types ),
					gettype( $waarde )
				);
			}
		}

		if ( isset( $definitie['enum'] ) && is_array( $definitie['enum'] ) && ! in_array( $waarde, $definitie['enum'], true ) ) {
			return sprintf(
				/* translators: %s: allowed values. */
				__( 'waarde staat niet in de toegestane lijst (%s)', 'mcp-abilities-kadence' ),
				implode( ', ', array_map( 'strval', array_slice( $definitie['enum'], 0, 12 ) ) )
			);
		}

		return '';
	}

	/**
	 * Zit deze attribuutwaarde vast in de opgeslagen markup van dit blok?
	 *
	 * Dit is de kern van veilig schrijven, en het is een WAARNEMING in plaats
	 * van een lijst. Een blok dat zelfsluitend is opgeslagen (leeg innerHTML)
	 * heeft geen markup die kan gaan afwijken; daar is elk attribuut vrij te
	 * wijzigen. Heeft het blok wél markup, dan is de vraag of de huidige waarde
	 * daar letterlijk in voorkomt. Zo ja, dan hoort de markup mee te veranderen
	 * en is schrijven via alleen het attribuut een stille breuk.
	 *
	 * Waargenomen op de productiesite: kadence/rowlayout slaat niets op, terwijl
	 * kadence/column zijn uniqueID in de klasse bakt
	 * (class="... kadence-column228_4ec348-d0"). Deze toets vangt allebei,
	 * zonder dat er ergens een allowlist bijgehouden hoeft te worden die bij
	 * elke Kadence-update veroudert.
	 *
	 * @param array  $blok   Eén blok uit parse_blocks().
	 * @param string $attr   Attribuutnaam.
	 * @param mixed  $huidig De huidige waarde.
	 *
	 * @return array{gebonden:bool,reden:string}
	 */
	public static function markup_gebonden( $blok, $attr, $huidig ) {
		$html = isset( $blok['innerHTML'] ) ? (string) $blok['innerHTML'] : '';

		if ( '' === trim( $html ) ) {
			return array(
				'gebonden' => false,
				'reden'    => __( 'het blok is zelfsluitend opgeslagen en heeft geen eigen markup', 'mcp-abilities-kadence' ),
			);
		}

		if ( ! is_scalar( $huidig ) || '' === (string) $huidig ) {
			return array(
				'gebonden' => false,
				'reden'    => __( 'de huidige waarde is leeg of niet-scalair en kan niet letterlijk in de markup staan', 'mcp-abilities-kadence' ),
			);
		}

		if ( false !== strpos( $html, (string) $huidig ) ) {
			return array(
				'gebonden' => true,
				'reden'    => sprintf(
					/* translators: %s: the current value. */
					__( 'de huidige waarde "%s" staat letterlijk in de opgeslagen markup van dit blok; wijzigen zonder de markup mee te nemen laat die twee uiteenlopen', 'mcp-abilities-kadence' ),
					self::kort( (string) $huidig )
				),
			);
		}

		return array(
			'gebonden' => false,
			'reden'    => __( 'de huidige waarde komt niet in de markup van dit blok voor', 'mcp-abilities-kadence' ),
		);
	}

	/**
	 * Kort een string in voor een melding.
	 *
	 * @param string $tekst De tekst.
	 *
	 * @return string
	 */
	private static function kort( $tekst ) {
		return strlen( $tekst ) > 60 ? substr( $tekst, 0, 60 ) . '…' : $tekst;
	}

	/**
	 * Alle uniqueID's in een blokkenboom, met hun bloknaam.
	 *
	 * @param array $blokken Resultaat van parse_blocks().
	 * @param array $gevonden Interne verzamelaar.
	 *
	 * @return array<string,string[]>
	 */
	public static function verzamel_unique_ids( $blokken, &$gevonden = array() ) {
		foreach ( $blokken as $blok ) {
			$attrs = isset( $blok['attrs'] ) && is_array( $blok['attrs'] ) ? $blok['attrs'] : array();

			if ( ! empty( $attrs['uniqueID'] ) ) {
				$id = (string) $attrs['uniqueID'];

				if ( ! isset( $gevonden[ $id ] ) ) {
					$gevonden[ $id ] = array();
				}

				$gevonden[ $id ][] = isset( $blok['blockName'] ) ? (string) $blok['blockName'] : '?';
			}

			if ( ! empty( $blok['innerBlocks'] ) ) {
				self::verzamel_unique_ids( $blok['innerBlocks'], $gevonden );
			}
		}

		return $gevonden;
	}

	/**
	 * De globale stijlen van Kadence.
	 *
	 * Leest de bronnen die het thema en de blokken zelf gebruiken. Ontbreekt
	 * er een, dan staat dat er ook zo bij — geen verzonnen standaardwaarden.
	 *
	 * @return array
	 */
	public static function get_global_styles() {
		// De optie wordt als JSON-STRING opgeslagen, niet als array. Het thema
		// hangt een leesfilter op de optie dat eindigt met json_encode()
		// (kadence/inc/components/options/component.php:184) en doet overal
		// waar het de waarde gebruikt zelf een json_decode(). Een is_array()
		// op de ruwe waarde is dus structureel onwaar.
		$ruw   = get_option( 'kadence_global_palette' );
		$palet = null;

		if ( is_string( $ruw ) && '' !== $ruw ) {
			$gedecodeerd = json_decode( $ruw, true );
			$palet       = is_array( $gedecodeerd ) ? $gedecodeerd : null;
		} elseif ( is_array( $ruw ) ) {
			$palet = $ruw;
		}

		// Het thema leeft in de namespace Kadence. function_exists( 'kadence' )
		// is daarom altijd onwaar, ook met het thema actief — de functie heet
		// voluit Kadence\kadence (kadence/inc/functions.php:8,24).
		$thema_actief = function_exists( 'Kadence\\kadence' );

		$stijlen = array(
			'palette'     => array(
				'found'  => is_array( $palet ) && ! empty( $palet ),
				'source' => 'option:kadence_global_palette',
				// Inclusief de sleutel 'active': er zijn drie paletsets
				// (palette, second-palette, third-palette) en zonder te weten
				// welke actief is, is 'palette3' niet op te lossen.
				'value'  => $palet,
			),
			'typography'  => array(),
			// Een leeg resultaat moet zichzelf verklaren. Zonder dit veld is
			// "er is niets ingesteld" niet te onderscheiden van "ik kon niet
			// kijken", en dat is precies het soort stille uitkomst waar een
			// verkeerd antwoord uit voortkomt.
			'typography_status' => '',
			'theme_found' => $thema_actief,
		);

		if ( ! $thema_actief ) {
			$stijlen['typography_status'] = __( 'leeg — het Kadence-thema is niet actief, dus er zijn geen thema-instellingen om te lezen.', 'mcp-abilities-kadence' );

			return $stijlen;
		}

		$thema = \Kadence\kadence();

		if ( ! is_object( $thema ) ) {
			$stijlen['typography_status'] = __( 'leeg — de thema-instellingen zijn niet bereikbaar.', 'mcp-abilities-kadence' );

			return $stijlen;
		}

		// GEEN method_exists() hier. Kadence\Template_Tags heeft geen echte
		// methode option(); het dispatcht via __call()
		// (kadence/inc/components/template_tags.php:76) naar de template tags
		// die de componenten registreren, en method_exists() ziet magische
		// methodes niet. Die controle was dus altijd onwaar en sloeg de hele
		// typografie stilzwijgend over. __call() gooit een
		// BadMethodCallException als de tag niet bestaat, en dát is de guard.
		$sleutels = array(
			'base_font',
			'heading_font',
			'h1_font',
			'h2_font',
			'h3_font',
			'h4_font',
			'h5_font',
			'h6_font',
		);

		foreach ( $sleutels as $sleutel ) {
			try {
				$waarde = $thema->option( $sleutel );
			} catch ( \Throwable $e ) {
				error_log( sprintf(
					'Kadence MCP: thema-optie "%s" is niet op te vragen — %s',
					$sleutel,
					$e->getMessage()
				) );

				$stijlen['typography_status'] = sprintf(
					/* translators: %s: option key. */
					__( 'onvolledig — de thema-optie "%s" gaf een fout; zie het foutenlogboek van de site.', 'mcp-abilities-kadence' ),
					$sleutel
				);

				break;
			}

			// Deze sleutels leveren geneste arrays op (family, size, weight per
			// breakpoint), geen strings. Ze gaan ongewijzigd mee.
			if ( ! empty( $waarde ) ) {
				$stijlen['typography'][ $sleutel ] = $waarde;
			}
		}

		if ( '' === $stijlen['typography_status'] ) {
			$stijlen['typography_status'] = empty( $stijlen['typography'] )
				? sprintf(
					/* translators: %s: comma-separated option keys. */
					__( 'leeg — geen van de opgevraagde themasleutels (%s) had een waarde. Het thema is wél actief, dus dit betekent dat er niets is ingesteld.', 'mcp-abilities-kadence' ),
					implode( ', ', $sleutels )
				)
				: sprintf(
					/* translators: 1: number found, 2: number requested. */
					__( '%1$d van %2$d themasleutels had een waarde.', 'mcp-abilities-kadence' ),
					count( $stijlen['typography'] ),
					count( $sleutels )
				);
		}

		return $stijlen;
	}

	/**
	 * De site-brede standaardinstellingen per blok.
	 *
	 * LET OP wat dit wel en niet is. Deze optie gaat uitsluitend naar de EDITOR
	 * (kadence-blocks/includes/class-kadence-blocks-editor-assets.php:348-349);
	 * er is geen render_block-filter en geen enkel gebruik op de frontend. Het
	 * is dus een INVOEG-standaard: bij een nieuw blok vult de editor deze
	 * waarden vast in, en ze worden bij het opslaan gewoon in de markup
	 * geschreven.
	 *
	 * Gevolg: een ontbrekend attribuut op een BESTAAND blok betekent nog steeds
	 * de standaardwaarde uit block.json. Waar dit wél voor dient, is weten wat
	 * de huisstijl van de site is — welke vorm een nieuw blok hier krijgt.
	 *
	 * Twee optienamen omdat Kadence de oude kt_-naam nooit heeft opgeruimd;
	 * beide worden gelezen en de nieuwe wint.
	 *
	 * @return array
	 */
	public static function get_block_defaults() {
		$oud    = get_option( 'kt_blocks_config_blocks' );
		$nieuw  = get_option( 'kadence_blocks_config_blocks' );
		$bronnen = array();

		foreach ( array( 'kt_blocks_config_blocks' => $oud, 'kadence_blocks_config_blocks' => $nieuw ) as $naam => $waarde ) {
			if ( is_string( $waarde ) && '' !== $waarde ) {
				$gedecodeerd = json_decode( $waarde, true );
				$waarde      = is_array( $gedecodeerd ) ? $gedecodeerd : null;
			}

			if ( is_array( $waarde ) && ! empty( $waarde ) ) {
				$bronnen[ $naam ] = $waarde;
			}
		}

		if ( empty( $bronnen ) ) {
			return array(
				'found'  => false,
				'blocks' => array(),
				'status' => __( 'geen site-brede invoegstandaarden ingesteld — een nieuw blok krijgt hier gewoon de standaardwaarden uit describe-block.', 'mcp-abilities-kadence' ),
			);
		}

		$samen = array();

		foreach ( $bronnen as $waarde ) {
			$samen = array_merge( $samen, $waarde );
		}

		ksort( $samen );

		return array(
			'found'   => true,
			'sources' => array_keys( $bronnen ),
			'blocks'  => $samen,
			'status'  => sprintf(
				/* translators: %d: number of blocks with defaults. */
				__( '%d blokken hebben een invoegstandaard. Dit is wat de editor invult bij een NIEUW blok en het wordt bij opslaan in de markup geschreven — het verandert niets aan bestaande blokken. Gebruik het om te weten welke vorm iets op deze site hoort te krijgen.', 'mcp-abilities-kadence' ),
				count( $samen )
			),
		);
	}

	/**
	 * De HTML die binnen een tekstblok is toegestaan.
	 *
	 * Bewust kort. Alles wat hier niet in staat wordt door wp_kses verwijderd,
	 * en dat is de bedoeling: dit is een tekstveld, geen HTML-editor. mark zit
	 * erin omdat Kadence daar zijn Advanced Highlight mee opmaakt, en die zou
	 * anders bij elke tekstwijziging sneuvelen.
	 *
	 * @return array
	 */
	public static function toegestane_html() {
		return array(
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
			'u'      => array(),
			'br'     => array(),
			'sub'    => array(),
			'sup'    => array(),
			'del'    => array(),
			'code'   => array(),
			'mark'   => array( 'class' => true, 'style' => true ),
			'span'   => array( 'class' => true, 'style' => true ),
			'a'      => array( 'href' => true, 'target' => true, 'rel' => true, 'class' => true, 'title' => true ),
		);
	}

	/**
	 * Haal de lege scheidingsknopen uit een blokkenlijst.
	 *
	 * parse_blocks() geeft voor de witruimte tussen twee blokken een knoop
	 * terug met blockName null. Die horen niet in een boom die je gaat
	 * samenvoegen of tellen.
	 *
	 * Let op de voorwaarde: ALLEEN als de inhoud uit niets dan witruimte
	 * bestaat. Een post die nog klassieke inhoud bevat — HTML zonder blokken —
	 * staat óók als één knoop met blockName null in de boom, en die bevat de
	 * hele pagina. Zonder deze voorwaarde zou "opschonen" die pagina wissen.
	 *
	 * @param array $blokken De blokken.
	 *
	 * @return array
	 */
	public static function schoon_blokken( $blokken ) {
		return array_values(
			array_filter(
				$blokken,
				static function ( $blok ) {
					if ( ! empty( $blok['blockName'] ) ) {
						return true;
					}

					$inhoud = isset( $blok['innerHTML'] ) ? (string) $blok['innerHTML'] : '';

					return '' !== trim( $inhoud );
				}
			)
		);
	}

	/**
	 * Serialiseer blokken zoals WordPress ze in post_content zet.
	 *
	 * serialize_blocks() plakt blokken zonder scheiding aan elkaar. In
	 * post_content staat tussen twee blokken op het hoogste niveau altijd een
	 * lege regel; zonder die regel is de markup geldig maar leest hij als één
	 * muur, en wijkt hij af van wat de editor bij de eerstvolgende opslag zou
	 * wegschrijven.
	 *
	 * @param array $blokken De blokken.
	 *
	 * @return string
	 */
	public static function serialiseer( $blokken ) {
		return implode( "\n\n", array_map( 'serialize_block', self::schoon_blokken( $blokken ) ) );
	}

	/**
	 * Maak tekst schoon voor in een blok.
	 *
	 * wp_kses alleen is hier niet genoeg. Die verwijdert een tag die niet is
	 * toegestaan, maar laat wat ertussen stond gewoon staan — bij een strong
	 * of een div is dat precies wat je wil, maar bij script en style is het
	 * dat niet: <script>alert(1)</script> werd zo "alert(1)", zichtbaar midden
	 * in de lopende tekst. Geen beveiligingslek, wel rommel die niemand ziet
	 * aankomen. Daarom worden die twee eerst mét inhoud verwijderd.
	 *
	 * @param string $tekst De tekst.
	 *
	 * @return string
	 */
	public static function schoon_tekst( $tekst ) {
		$tekst = preg_replace( '#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', (string) $tekst );

		// Ook een script-tag die nooit gesloten wordt: daar zou de rest van de
		// tekst anders alsnog achter verdwijnen of juist blijven staan.
		$tekst = preg_replace( '#<(script|style)\b[^>]*>.*$#is', '', (string) $tekst );

		return wp_kses( (string) $tekst, self::toegestane_html() );
	}

	/**
	 * Verwijder blokken uit een boom, op elke diepte.
	 *
	 * Het lastige zit niet in innerBlocks maar in innerContent. Die array
	 * bevat de HTML van het blok in stukken, met op de plaats van elk kindblok
	 * een null. serialize_block() loopt door innerContent en pakt bij elke null
	 * het volgende element uit innerBlocks. Haal je dus een kindblok weg zonder
	 * ook zijn null weg te halen, dan schuift alles op: het laatste kind wordt
	 * twee keer geschreven of er wordt naar een index gegrepen die niet bestaat.
	 *
	 * Daarom worden beide lijsten hier in één doorloop opnieuw opgebouwd.
	 *
	 * @param array $blokken De blokken.
	 * @param array $weg     De te verwijderen uniqueIDs, als sleutels.
	 * @param int   $geteld  Teller van wat er daadwerkelijk verdween.
	 *
	 * @return array
	 */
	public static function verwijder_blokken( $blokken, $weg, &$geteld = 0 ) {
		$uit = array();

		foreach ( $blokken as $blok ) {
			$id = isset( $blok['attrs']['uniqueID'] ) ? (string) $blok['attrs']['uniqueID'] : '';

			if ( '' !== $id && isset( $weg[ $id ] ) ) {
				// Het blok zelf plus alles eronder telt mee als verdwenen.
				$geteld += count( self::verzamel_unique_ids( array( $blok ) ) );
				continue;
			}

			if ( empty( $blok['innerBlocks'] ) ) {
				$uit[] = $blok;
				continue;
			}

			$kinderen = array();
			$inhoud   = array();
			$index    = 0;

			foreach ( (array) $blok['innerContent'] as $stuk ) {
				if ( is_string( $stuk ) ) {
					$inhoud[] = $stuk;
					continue;
				}

				$kind = isset( $blok['innerBlocks'][ $index ] ) ? $blok['innerBlocks'][ $index ] : null;
				$index++;

				if ( null === $kind ) {
					continue;
				}

				$kind_id = isset( $kind['attrs']['uniqueID'] ) ? (string) $kind['attrs']['uniqueID'] : '';

				if ( '' !== $kind_id && isset( $weg[ $kind_id ] ) ) {
					// Zowel het kind als zijn plaatshouder overslaan.
					$geteld += count( self::verzamel_unique_ids( array( $kind ) ) );
					continue;
				}

				$verwerkt   = self::verwijder_blokken( array( $kind ), $weg, $geteld );
				$kinderen[] = $verwerkt[0];
				$inhoud[]   = null;
			}

			$blok['innerBlocks']  = $kinderen;
			$blok['innerContent'] = $inhoud;

			$uit[] = $blok;
		}

		return $uit;
	}

	/**
	 * Verzamel de tekst uit een blok én alles eronder.
	 *
	 * tekst_uit_blok() kijkt alleen naar de innerHTML van het blok zelf, en dat
	 * is precies het verkeerde antwoord bij een container: een Row Layout heeft
	 * geen innerHTML en een Sectie alleen zijn eigen div. De tekst zit in de
	 * kleinkinderen. Zonder deze doorloop meldde het voorstel van remove-blocks
	 * dat er niets verdween terwijl er een hele sectie met koppen aan hing.
	 *
	 * @param array $blokken De blokken.
	 * @param array $uit     De verzamelde teksten.
	 * @param int   $max     Hoeveel regels maximaal.
	 *
	 * @return array
	 */
	public static function verzamel_teksten( $blokken, $uit = array(), $max = 12 ) {
		foreach ( $blokken as $blok ) {
			if ( count( $uit ) >= $max ) {
				return $uit;
			}

			$tekst = self::tekst_uit_blok( $blok );

			if ( '' !== trim( (string) $tekst ) ) {
				$uit[] = $tekst;
			}

			if ( ! empty( $blok['innerBlocks'] ) ) {
				$uit = self::verzamel_teksten( $blok['innerBlocks'], $uit, $max );
			}
		}

		return $uit;
	}

	/**
	 * Voeg blokken toe binnen een bestaand blok.
	 *
	 * Tot 1.7.0 kon je alleen op het hoogste niveau invoegen. Dat betekende dat
	 * een accordeon of een gekopieerde sectie nooit ín een kolom kon landen —
	 * je kon hem alleen naast de rij zetten, buiten de achtergrond en buiten de
	 * contentbreedte.
	 *
	 * Het venijn zit in innerContent. Die array bevat de HTML van het blok in
	 * stukken, met op de plaats van elk kindblok een null. Een kind toevoegen
	 * zonder ook een null toe te voegen betekent dat serialize_block() er bij
	 * het laatste kind eentje mist: het blok verschijnt dan niet in de uitvoer.
	 *
	 * De null komt vlak vóór het laatste element, want dat is de afsluitende
	 * HTML van de container. Bij een Kadence-kolom is dat </div></div>.
	 *
	 * @param array  $blokken De boom.
	 * @param string $doel_id De uniqueID van de container.
	 * @param array  $nieuw   De toe te voegen blokken.
	 * @param bool   $gelukt  Wordt true zodra de container gevonden is.
	 *
	 * @return array
	 */
	public static function voeg_binnen_in( $blokken, $doel_id, $nieuw, &$gelukt = false ) {
		foreach ( $blokken as $i => $blok ) {
			$id = isset( $blok['attrs']['uniqueID'] ) ? (string) $blok['attrs']['uniqueID'] : '';

			if ( $id === (string) $doel_id ) {
				$inhoud = isset( $blok['innerContent'] ) && is_array( $blok['innerContent'] ) ? $blok['innerContent'] : array();

				if ( empty( $inhoud ) ) {
					// Een zelfsluitend blok heeft geen binnenkant om iets in te
					// zetten. Dat hoort de aanroeper als fout te melden.
					return $blokken;
				}

				$kinderen = isset( $blok['innerBlocks'] ) && is_array( $blok['innerBlocks'] ) ? $blok['innerBlocks'] : array();

				// Waar de plaatshouder heen moet.
				//
				// Heeft de container al kinderen, dan staat er al minstens één null en
				// hoort de nieuwe er direct achter. Is de container LEEG, dan bestaat
				// innerContent uit precies één string — de hele wrapper — en moet die
				// eerst gesplitst worden op het punt waar inhoud hoort.
				//
				// Dit ging tot 1.7.3 mis: count()-1 is bij één element nul, dus de null
				// kwam vóór de wrapper en het blok werd BUITEN de container geschreven.
				// Beide terugleescontroles meldden succes, want die keken alleen of de
				// uniqueIDs in de post stonden — niet onder welke ouder.
				$laatste_null = -1;

				foreach ( $inhoud as $index => $stuk ) {
					if ( null === $stuk ) {
						$laatste_null = $index;
					}
				}

				if ( $laatste_null < 0 ) {
					// Leeg: splits de wrapper op de eerste plek waar een openingstag
					// direct door een sluittag wordt gevolgd. Bij een Kadence-kolom is
					// dat tussen <div class="kt-inside-inner-col"> en </div>.
					$een = '';

					foreach ( $inhoud as $index => $stuk ) {
						if ( is_string( $stuk ) && '' !== trim( $stuk ) ) {
							$een = $stuk;
							$laatste_null = $index;
							break;
						}
					}

					if ( '' === $een || ! preg_match( '#^(.*?<[a-zA-Z][^>]*>)(</[a-zA-Z][^>]*>.*)$#s', $een, $delen ) ) {
						// Geen plek te vinden om iets tussen te zetten. Niet gokken.
						return $blokken;
					}

					array_splice( $inhoud, $laatste_null, 1, array( $delen[1], $delen[2] ) );
					$laatste_null = $laatste_null; // de null komt straks tussen deel 1 en 2
				} 

				$invoeg = $laatste_null + 1;

				foreach ( $nieuw as $toevoeging ) {
					$kinderen[] = $toevoeging;
					array_splice( $inhoud, $invoeg, 0, array( null ) );
					$invoeg++;
				}

				$blokken[ $i ]['innerBlocks']  = $kinderen;
				$blokken[ $i ]['innerContent'] = $inhoud;
				$gelukt                        = true;

				return $blokken;
			}

			if ( ! empty( $blok['innerBlocks'] ) ) {
				$blokken[ $i ]['innerBlocks'] = self::voeg_binnen_in( $blok['innerBlocks'], $doel_id, $nieuw, $gelukt );

				if ( $gelukt ) {
					return $blokken;
				}
			}
		}

		return $blokken;
	}

	/**
	 * De layoutnamen die Kadence per kolomaantal kent.
	 *
	 * colLayout is in block.json een kale string zonder enum, dus
	 * rest_validate_value_from_schema() laat elke waarde door — ook een
	 * verzonnen naam. Op de voorkant valt dat niet op, want daar bepaalt de
	 * klasse kt-has-N-columns de breedtes. In de EDITOR wel: die kiest zijn
	 * weergave op colLayout, en bij een onbekende waarde zet hij de kolommen
	 * onder elkaar.
	 *
	 * Dat kostte op 11-09-2026 een ronde: "thirds" leek te werken en de pagina
	 * was in de editor onbruikbaar.
	 *
	 * Afgelezen uit dist/blocks-rowlayout.js van Kadence Blocks 3.7.8.
	 */
	const COL_LAYOUTS = array(
		// 'row' is bij elk kolomaantal geldig. De eerste versie van deze lijst
		// miste hem, plus first-row/last-row bij 3 en two-grid/three-grid bij 4
		// en 6 — en blokkeerde daarmee waarden die Kadence wél rendert. De
		// homepage van deze site gebruikt first-row.
		1 => array( 'equal', 'row' ),
		2 => array( 'equal', 'left-golden', 'right-golden', 'row' ),
		3 => array( 'equal', 'left-half', 'right-half', 'center-half', 'center-wide', 'center-exwide', 'first-row', 'last-row', 'row' ),
		4 => array( 'equal', 'left-forty', 'right-forty', 'two-grid', 'row' ),
		5 => array( 'equal', 'row' ),
		6 => array( 'equal', 'two-grid', 'three-grid', 'row' ),
	);

	/**
	 * Toets een colLayout tegen het aantal kolommen.
	 *
	 * @param string $waarde  De voorgenomen colLayout.
	 * @param int    $kolommen Het aantal kolommen van de rij.
	 *
	 * @return string Leeg als er geen bezwaar is.
	 */
	public static function toets_col_layout( $waarde, $kolommen ) {
		$waarde   = (string) $waarde;
		$kolommen = (int) $kolommen;

		if ( '' === $waarde ) {
			return '';
		}

		if ( ! isset( self::COL_LAYOUTS[ $kolommen ] ) ) {
			return '';
		}

		if ( in_array( $waarde, self::COL_LAYOUTS[ $kolommen ], true ) ) {
			return '';
		}

		return sprintf(
			/* translators: 1: given value, 2: number of columns, 3: comma separated valid values. */
			__( 'Kadence kent de layout "%1$s" niet bij %2$d kolommen. Op de voorkant valt dat niet op — daar bepaalt de klasse kt-has-%2$d-columns de breedtes — maar in de EDITOR komen de kolommen dan onder elkaar te staan. Geldig zijn: %3$s.', 'mcp-abilities-kadence' ),
			$waarde,
			$kolommen,
			implode( ', ', self::COL_LAYOUTS[ $kolommen ] )
		);
	}

	/**
	 * Vind de uniqueID van de ouder van een blok.
	 *
	 * Bestaat omdat "het blok staat in de post" geen bewijs is dat het op de
	 * bedoelde plek staat. Een invoegfout zette een blok bewijsbaar buiten de
	 * container terwijl beide terugleescontroles "geschreven en teruggelezen"
	 * meldden — die keken alleen of het uniqueID ergens in de boom voorkwam.
	 *
	 * @param array  $blokken   De boom.
	 * @param string $kind_id   De uniqueID waarvan je de ouder zoekt.
	 * @param string $ouder_id  Interne parameter; laat leeg.
	 *
	 * @return string De uniqueID van de ouder, of '' als het blok op het
	 *                hoogste niveau staat of niet gevonden is.
	 */
	public static function ouder_van( $blokken, $kind_id, $ouder_id = '' ) {
		foreach ( $blokken as $blok ) {
			$id = isset( $blok['attrs']['uniqueID'] ) ? (string) $blok['attrs']['uniqueID'] : '';

			if ( $id === (string) $kind_id ) {
				return $ouder_id;
			}

			if ( ! empty( $blok['innerBlocks'] ) ) {
				$gevonden = self::ouder_van( $blok['innerBlocks'], $kind_id, $id );

				if ( '' !== $gevonden ) {
					return $gevonden;
				}

				// Een leeg antwoord kan ook "gevonden op het hoogste niveau van
				// deze tak" betekenen; controleer dat apart.
				foreach ( $blok['innerBlocks'] as $kind ) {
					$kid = isset( $kind['attrs']['uniqueID'] ) ? (string) $kind['attrs']['uniqueID'] : '';

					if ( $kid === (string) $kind_id ) {
						return $id;
					}
				}
			}
		}

		return '';
	}

	/**
	 * Waarom een token is afgewezen.
	 *
	 * @param string   $meegegeven Het token dat de aanroeper meegaf.
	 * @param string   $verwacht   Het token dat nu zou gelden.
	 * @param WP_Post  $post       De post.
	 *
	 * @return string Een uitlegbare reden.
	 */
	public static function token_reden( $meegegeven, $verwacht, $post ) {
		$meegegeven = (string) $meegegeven;

		if ( '' === $meegegeven ) {
			return __( 'Er is geen token meegegeven. Roep deze ability eerst zonder token aan; je krijgt er dan een terug.', 'mcp-abilities-kadence' );
		}

		if ( 0 !== strpos( $meegegeven, 'kmcp1_' ) || 38 !== strlen( $meegegeven ) ) {
			return __( 'Dit is geen token van deze plug-in. Roep de ability eerst zonder token aan.', 'mcp-abilities-kadence' );
		}

		$versie_mee      = substr( $meegegeven, 6, 6 );
		$versie_verwacht = substr( (string) $verwacht, 6, 6 );

		if ( $versie_mee !== $versie_verwacht ) {
			return sprintf(
				/* translators: %s: the post's last modified date. */
				__( 'De post is gewijzigd sinds je dit token kreeg (laatste wijziging: %s). Kijk opnieuw naar het blok en laat een vers token maken — een goedkeuring op een versie die niet meer bestaat is geen goedkeuring.', 'mcp-abilities-kadence' ),
				(string) $post->post_modified_gmt
			);
		}

		return __( 'De post is niet gewijzigd, maar JOUW VOORSTEL wel: je vraagt nu iets anders dan wat er getoetst is. Roep de ability opnieuw zonder token aan met precies deze invoer. Blijft dit terugkomen bij ongewijzigde invoer, dan klopt er iets niet aan de ability zelf.', 'mcp-abilities-kadence' );
	}

	/**
	 * Vervang één blok in de boom, op zijn eigen plek.
	 *
	 * Het oude blok wordt herkend aan zijn uniqueID en verruild voor het
	 * nieuwe. De plek in de boom blijft, dus ook de innerContent van de ouder
	 * klopt nog: er verdwijnt geen kind en er komt er geen bij, alleen de
	 * inhoud van dat ene blok verandert. Precies daarom hoeft hier niets aan
	 * null-plaatshouders geschoven te worden.
	 *
	 * @param array  $blokken   De boom.
	 * @param string $unique_id Het uniqueID van het te vervangen blok.
	 * @param array  $nieuw     Het nieuwe, geparseerde blok.
	 * @param bool   $gelukt    Wordt true zodra er vervangen is.
	 *
	 * @return array
	 */
	public static function vervang_blok( $blokken, $unique_id, $nieuw, &$gelukt = false ) {
		$uit = array();

		foreach ( $blokken as $blok ) {
			$eigen = isset( $blok['attrs']['uniqueID'] ) ? (string) $blok['attrs']['uniqueID'] : '';

			if ( '' !== $eigen && $eigen === $unique_id ) {
				$gelukt = true;
				$uit[]  = $nieuw;
				continue;
			}

			if ( ! empty( $blok['innerBlocks'] ) ) {
				$blok['innerBlocks'] = self::vervang_blok( $blok['innerBlocks'], $unique_id, $nieuw, $gelukt );
			}

			$uit[] = $blok;
		}

		return $uit;
	}
}
