<?php
/**
 * Alles rond de Query Loop: facetten, queryinstellingen en de drie lagen.
 *
 * @package Kadence_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * De Query Loop.
 *
 * Een Query Loop staat op DRIE plekken, en dat is de reden dat deze klasse
 * bestaat.
 *
 * 1. In de PAGINA staat alleen een verwijzing: <!-- wp:kadence/query {"id":1456} /-->
 * 2. In de post kadence_query 1456 staat de hele layout, met de filterblokken.
 * 3. In de POST META van 1456 staat wat de query ophaalt (_kad_query_query) en
 *    een afgeleide beschrijving van elk filter (_kad_query_facets).
 *
 * Die derde laag is de valkuil. _kad_query_facets is GEEN losse instelling maar
 * een afgeleide van de blokken: de editor loopt bij elke wijziging de
 * filterblokken langs, bouwt de lijst opnieuw op en schrijft hem weg. De
 * frontend leest daaruit welke taxonomie een filter toont, want die staat NIET
 * in de blokmarkup. En de index die de filterkeuzes voedt hangt aan de hash in
 * die lijst.
 *
 * Wijzig je dus een filterblok zonder de facetten bij te werken, dan staat er
 * een filter op de pagina dat niets doet — zonder foutmelding, want er is niets
 * kapot: de markup klopt, de meta klopt op zichzelf, alleen de twee horen niet
 * meer bij elkaar. Precies het soort stille fout waar dit project op stukloopt.
 *
 * Daarom rekent deze klasse de facetten na op dezelfde manier als de editor,
 * tot en met de hashfunctie. Afgelezen, niet verzonnen:
 * dist/blocks-query.js (de opbouw en de hash) en dist/early-filters.js (de
 * attributen die per filterblok worden bijgeplaatst).
 */
class Kadence_MCP_Query {

	/**
	 * De blokken die een facet opleveren, in de volgorde van blocks-query.js.
	 *
	 * kadence/query-filter-search staat hier bewust NIET bij: dat blok krijgt
	 * wel dezelfde attributen bijgeplaatst, maar zoekt in de tekst en wordt
	 * niet geïndexeerd.
	 */
	const FACETBLOKKEN = array(
		'kadence/query-filter',
		'kadence/query-filter-checkbox',
		'kadence/query-filter-buttons',
		'kadence/query-filter-date',
		'kadence/query-filter-range',
		'kadence/query-filter-woo-attribute',
		'kadence/query-filter-rating',
	);

	/**
	 * De sleutels die MEETELLEN in de hash, in precies deze volgorde.
	 *
	 * De volgorde is geen smaak: de hash gaat over de JSON-tekst, en die volgt
	 * de volgorde waarin de editor het object opbouwt.
	 */
	const GEHASHT = array( 'source', 'fieldType', 'taxonomy', 'post_field', 'include', 'exclude', 'type', 'customField', 'customMetaKey' );

	/**
	 * De sleutels die WEL worden opgeslagen maar NIET meetellen in de hash.
	 *
	 * Dat uniqueID er niet in zit is belangrijk: twee identiek ingestelde
	 * filters delen dus dezelfde hash en dezelfde index, en een filter mag van
	 * plek of van blok veranderen zonder dat de index opnieuw gevuld hoeft.
	 * Verandert daarentegen de taxonomie of het bloktype, dan verandert de hash
	 * wel en moet de index bijgewerkt worden.
	 */
	const ONGEHASHT = array( 'uniqueID', 'dateFormat', 'comparisonLogic', 'slug' );

	/**
	 * De hashfunctie van Kadence: djb2 met xor, achterstevoren, als uint32.
	 *
	 * Letterlijk overgenomen uit dist/blocks-query.js, module 70236:
	 *
	 *   function n(e){for(var t=5381,o=e.length;o;)t=33*t^e.charCodeAt(--o);return t>>>0}
	 *
	 * Twee dingen die je in PHP zelf moet doen en die JavaScript gratis geeft:
	 * de vermenigvuldiging afkappen op 32 bits mét teken (JavaScript doet dat
	 * bij elke xor), en de uitkomst pas aan het eind als uint32 lezen. Laat je
	 * een van beide weg, dan klopt de hash voor korte teksten nog wel en voor
	 * lange niet meer.
	 *
	 * @param string $tekst De JSON-tekst.
	 *
	 * @return int
	 */
	public static function hash( $tekst ) {
		$t = 5381;

		for ( $o = strlen( $tekst ); $o > 0; ) {
			$o--;
			$m = ( 33 * $t ) & 0xFFFFFFFF;

			if ( $m >= 0x80000000 ) {
				$m -= 0x100000000;
			}

			$t = $m ^ ord( $tekst[ $o ] );
		}

		return $t & 0xFFFFFFFF;
	}

	/**
	 * Draait WooCommerce? Twee standaardwaarden hangen daarvan af.
	 *
	 * @return bool
	 */
	private static function heeft_woocommerce() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * De attributen die Kadence per filterblok bijplaatst, met hun standaard.
	 *
	 * Deze staan in GEEN ENKELE block.json. Ze worden in de editor toegevoegd
	 * met een filter op blocks.registerBlockType (dist/early-filters.js), en
	 * dat is de reden dat describe-block ze niet kent en dat ze niet in de
	 * markup staan: een waarde gelijk aan de standaard schrijft de editor niet
	 * weg. Voor de hash tellen ze wel mee, want daar staan ze voluit in.
	 *
	 * @param string $bloknaam De bloknaam.
	 *
	 * @return array
	 */
	public static function bijgeplaatste_standaarden( $bloknaam ) {
		$woo = self::heeft_woocommerce();

		$source = 'taxonomy';

		if ( 'kadence/query-filter-date' === $bloknaam ) {
			$source = 'wordpress';
		} elseif ( $woo && in_array( $bloknaam, array( 'kadence/query-filter-range', 'kadence/query-filter-woo-attribute', 'kadence/query-filter-rating' ), true ) ) {
			$source = 'woocommerce';
		}

		$post_field = 'post_type';

		if ( 'kadence/query-filter-date' === $bloknaam ) {
			$post_field = 'post_date';
		} elseif ( $woo && 'kadence/query-filter-range' === $bloknaam ) {
			$post_field = '_price';
		} elseif ( $woo && 'kadence/query-filter-rating' === $bloknaam ) {
			$post_field = '_average_rating';
		} elseif ( $woo && 'kadence/query-filter-woo-attribute' === $bloknaam ) {
			$post_field = '1';
		}

		return array(
			'source'     => $source,
			'fieldType'  => 'post_field',
			'taxonomy'   => 'category',
			'post_field' => $post_field,
			'include'    => array(),
			'exclude'    => array(),
			'slug'       => '',
		);
	}

	/**
	 * De standaarden uit de block.json van dit blok, voor de vier sleutels die
	 * per bloktype verschillen.
	 *
	 * Dit is geen detail. customField en customMetaKey staan op query-filter en
	 * query-filter-buttons, maar NIET op query-filter-date. JavaScript laat een
	 * ongedefinieerde sleutel weg bij JSON.stringify, dus die twee sleutels
	 * horen bij een datumfilter helemaal niet in de tekst te staan. Zet je ze
	 * er toch in, dan rolt er een andere hash uit en vindt het filter geen
	 * index.
	 *
	 * @param string $bloknaam De bloknaam.
	 *
	 * @return array Alleen de sleutels die dit bloktype werkelijk heeft.
	 */
	public static function eigen_standaarden( $bloknaam ) {
		$blok = Kadence_MCP_Inventory::get_block( $bloknaam );
		$uit  = array();

		if ( ! $blok || empty( $blok['attributes'] ) ) {
			return $uit;
		}

		foreach ( array( 'customField', 'customMetaKey', 'dateFormat', 'comparisonLogic' ) as $sleutel ) {
			if ( ! isset( $blok['attributes'][ $sleutel ] ) ) {
				continue;
			}

			$definitie     = $blok['attributes'][ $sleutel ];
			$uit[ $sleutel ] = isset( $definitie['default'] ) ? $definitie['default'] : null;
		}

		return $uit;
	}

	/**
	 * Bouw het facet van één filterblok.
	 *
	 * @param array $blok Een geparseerd blok.
	 *
	 * @return array|null
	 */
	public static function facet_van_blok( $blok ) {
		$bloknaam = isset( $blok['blockName'] ) ? (string) $blok['blockName'] : '';

		if ( ! in_array( $bloknaam, self::FACETBLOKKEN, true ) ) {
			return null;
		}

		$attrs = isset( $blok['attrs'] ) && is_array( $blok['attrs'] ) ? $blok['attrs'] : array();
		$vast  = array_merge( self::bijgeplaatste_standaarden( $bloknaam ), self::eigen_standaarden( $bloknaam ) );

		// type is de bloknaam zonder kadence/ — zie blocks-query.js.
		$vast['type'] = substr( $bloknaam, strlen( 'kadence/' ) );

		if ( isset( $attrs['uniqueID'] ) ) {
			$vast['uniqueID'] = (string) $attrs['uniqueID'];
		}

		$gedefinieerd = array_merge( $vast, array_intersect_key( $attrs, $vast ) );

		// JavaScript laat een ongedefinieerde sleutel weg bij JSON.stringify.
		// Daarom komt een sleutel die dit bloktype niet heeft hier ook niet in
		// de tekst, en houden we de volgorde van de editor aan.
		$voor_hash = array();

		foreach ( self::GEHASHT as $sleutel ) {
			if ( array_key_exists( $sleutel, $gedefinieerd ) && null !== $gedefinieerd[ $sleutel ] ) {
				$voor_hash[ $sleutel ] = $gedefinieerd[ $sleutel ];
			}
		}

		$rest = array();

		foreach ( self::ONGEHASHT as $sleutel ) {
			if ( array_key_exists( $sleutel, $gedefinieerd ) && null !== $gedefinieerd[ $sleutel ] ) {
				$rest[ $sleutel ] = $gedefinieerd[ $sleutel ];
			}
		}

		return array(
			'hash'       => self::hash( self::json( $voor_hash ) ),
			'attributes' => self::json( array_merge( $voor_hash, $rest ) ),
		);
	}

	/**
	 * JSON zoals JavaScript hem maakt.
	 *
	 * JSON.stringify ontsnapt geen schuine strepen en geen accenten. Doet PHP
	 * dat wel, dan is het een andere tekst en dus een andere hash — en een
	 * categorie met een accent in de naam is niet bedacht.
	 *
	 * @param array $data De data.
	 *
	 * @return string
	 */
	private static function json( $data ) {
		return (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Alle facetten van een blokkenboom, in volgorde van voorkomen.
	 *
	 * @param array $blokken De boom.
	 * @param array $uit     De verzameling.
	 *
	 * @return array
	 */
	public static function facetten( $blokken, $uit = array() ) {
		foreach ( $blokken as $blok ) {
			$facet = self::facet_van_blok( $blok );

			if ( null !== $facet ) {
				$uit[] = $facet;
			}

			if ( ! empty( $blok['innerBlocks'] ) ) {
				$uit = self::facetten( $blok['innerBlocks'], $uit );
			}
		}

		return $uit;
	}

	/**
	 * Vergelijk de opgeslagen facetten met wat de blokken zeggen.
	 *
	 * @param int $post_id Een kadence_query post.
	 *
	 * @return array|WP_Error
	 */
	public static function facetstand( $post_id ) {
		$post = get_post( (int) $post_id );

		if ( ! $post || 'kadence_query' !== $post->post_type ) {
			return new WP_Error(
				'kadence_mcp_not_a_query',
				sprintf(
					/* translators: %d: post ID. */
					__( 'Post %d is geen kadence_query. Facetten bestaan alleen op een Query Loop.', 'mcp-abilities-kadence' ),
					(int) $post_id
				)
			);
		}

		$berekend  = self::facetten( parse_blocks( $post->post_content ) );
		$opgeslagen = get_post_meta( $post->ID, '_kad_query_facets', true );
		$opgeslagen = is_array( $opgeslagen ) ? $opgeslagen : array();

		$sleutel = static function ( $facet ) {
			return (string) $facet['hash'] . '|' . (string) $facet['attributes'];
		};

		$a = array_map( $sleutel, $opgeslagen );
		$b = array_map( $sleutel, $berekend );

		return array(
			'post_id'    => $post->ID,
			'stored'     => $opgeslagen,
			'computed'   => $berekend,
			'in_sync'    => $a === $b,
			'toegevoegd' => array_values( array_diff( $b, $a ) ),
			'verdwenen'  => array_values( array_diff( $a, $b ) ),
		);
	}

	/**
	 * Schrijf de berekende facetten weg.
	 *
	 * Kadence hangt zelf aan updated_post_meta: zodra _kad_query_facets
	 * verandert roept de indexer potentially_reindex_facets() aan, verwijdert
	 * de index van verdwenen hashes en zet de nieuwe in de wachtrij
	 * (query-indexer.php:172 en 277). Het vullen gebeurt dus op de achtergrond;
	 * een filter kan daardoor een moment leeg zijn.
	 *
	 * @param int $post_id De query.
	 *
	 * @return array|WP_Error
	 */
	public static function schrijf_facetten( $post_id ) {
		$stand = self::facetstand( $post_id );

		if ( is_wp_error( $stand ) ) {
			return $stand;
		}

		if ( $stand['in_sync'] ) {
			return array_merge( $stand, array( 'written' => false ) );
		}

		update_post_meta( $stand['post_id'], '_kad_query_facets', $stand['computed'] );

		$na = self::facetstand( $stand['post_id'] );

		return array_merge(
			is_wp_error( $na ) ? $stand : $na,
			array( 'written' => true )
		);
	}

	/**
	 * De queryinstellingen (_kad_query_query), met hun betekenis.
	 *
	 * Er is geen schema voor deze meta: register_post_meta zet hem als object
	 * zonder eigenschappen. Wat er in mag staat dus nergens vast, en een
	 * verzonnen sleutel wordt gewoon opgeslagen en daarna genegeerd. Deze lijst
	 * is afgelezen van een werkende query en van de queryopbouwer.
	 */
	const QUERY_SLEUTELS = array(
		'postType'         => 'array',
		'taxonomy'         => 'array',
		'taxQuery'         => 'gemengd',
		'perPage'          => 'tekst',
		'pages'            => 'getal',
		'offset'           => 'getal',
		'order'            => 'tekst',
		'orderBy'          => 'tekst',
		'orderMetaKey'     => 'tekst',
		'orderMetaKeyType' => 'tekst',
		'author'           => 'gemengd',
		'search'           => 'tekst',
		'exclude'          => 'array',
		'sticky'           => 'tekst',
		'inherit'          => 'bool',
		'parents'          => 'array',
		'limit'            => 'getal',
		'comparisonLogic'  => 'tekst',
		'infiniteScroll'   => 'bool',
		'specificPosts'    => 'array',
		'related'          => 'gemengd',
	);

	/**
	 * De queryinstellingen van een query.
	 *
	 * @param int $post_id De query.
	 *
	 * @return array
	 */
	public static function instellingen( $post_id ) {
		$waarde = get_post_meta( (int) $post_id, '_kad_query_query', true );

		return is_array( $waarde ) ? $waarde : array();
	}

	/**
	 * Draai de query echt en tel wat hij oplevert.
	 *
	 * Dit is de enige controle die iets zegt. De meta heeft geen schema, dus
	 * "opgeslagen" betekent hier niet "geldig": een posttype dat niet bestaat
	 * wordt even hard weggeschreven als een dat wel bestaat, en het verschil
	 * zie je pas als de pagina leeg blijft.
	 *
	 * @param array $instellingen De instellingen.
	 *
	 * @return array
	 */
	public static function proefdraai( $instellingen ) {
		$post_types = isset( $instellingen['postType'] ) ? (array) $instellingen['postType'] : array( 'post' );
		$onbekend   = array();

		foreach ( $post_types as $type ) {
			if ( ! post_type_exists( (string) $type ) ) {
				$onbekend[] = (string) $type;
			}
		}

		$per_page = isset( $instellingen['perPage'] ) ? (int) $instellingen['perPage'] : 10;

		$args = array(
			'post_type'              => $post_types,
			'post_status'            => 'publish',
			'posts_per_page'         => $per_page > 0 ? $per_page : 10,
			'fields'                 => 'ids',
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		if ( ! empty( $instellingen['orderBy'] ) ) {
			$args['orderby'] = (string) $instellingen['orderBy'];

			// Sorteren op een meta-waarde vraagt óók de sleutel. Geef je die
			// niet mee, dan negeert WP_Query de sortering zonder te klagen en
			// toont deze proefdraai een volgorde die op de pagina niet
			// voorkomt. Een controle die het verkeerde meet is erger dan geen
			// controle: hij wekt vertrouwen.
			if ( 0 === strpos( $args['orderby'], 'meta_value' ) ) {
				if ( empty( $instellingen['orderMetaKey'] ) ) {
					$args['orderby'] = 'date';
					$uit_de_pas      = __( 'Er is op een meta-waarde gesorteerd zonder orderMetaKey. WordPress negeert die sortering; de volgorde hieronder is op datum.', 'mcp-abilities-kadence' );
				} else {
					$args['meta_key'] = (string) $instellingen['orderMetaKey']; //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key

					if ( ! empty( $instellingen['orderMetaKeyType'] ) ) {
						$args['meta_type'] = (string) $instellingen['orderMetaKeyType'];
					}
				}
			}
		}

		if ( ! empty( $instellingen['order'] ) ) {
			$args['order'] = strtoupper( (string) $instellingen['order'] );
		}

		// Het taxonomiefilter meenemen, op dezelfde manier als Kadence Pro het
		// leest: een lijst met value "taxonomie|term_id".
		//
		// Tot 1.14.0 deed deze proefdraai dat niet, en daarmee mat hij het
		// verkeerde. Bij een query die op één term filtert meldde hij het totaal
		// van het hele posttype — 31 items terwijl er 0 in die term
		// zaten. Een controle die het verkeerde meet is erger dan geen controle,
		// want hij wekt vertrouwen.
		$tax_query = array();

		if ( ! empty( $instellingen['taxonomy'] ) ) {
			$per_taxonomie = array();

			foreach ( (array) $instellingen['taxonomy'] as $regel ) {
				$waarde = is_array( $regel ) && isset( $regel['value'] ) ? (string) $regel['value'] : '';
				$delen  = explode( '|', $waarde );

				if ( count( $delen ) < 2 ) {
					continue;
				}

				$slug    = sanitize_key( $delen[0] );
				$term_id = absint( $delen[1] );

				if ( '' === $slug || ! $term_id || ! taxonomy_exists( $slug ) ) {
					continue;
				}

				$per_taxonomie[ $slug ][] = $term_id;
			}

			foreach ( $per_taxonomie as $slug => $termen ) {
				$tax_query[] = array(
					'taxonomy'         => $slug,
					'terms'            => array_values( array_unique( $termen ) ),
					'include_children' => is_taxonomy_hierarchical( $slug ),
				);
			}
		}

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query; //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$vraag = new WP_Query( $args );

		$titels = array();

		foreach ( array_slice( $vraag->posts, 0, 5 ) as $id ) {
			$titels[] = get_the_title( $id );
		}

		return array(
			'post_types'       => $post_types,
			'unknown_post_types' => $onbekend,
			'found'            => (int) $vraag->found_posts,
			'first'            => $titels,
			'taxonomy_filter'  => $tax_query,
			'order_note'       => isset( $uit_de_pas ) ? $uit_de_pas : '',
		);
	}

	/**
	 * Waar wordt deze query gebruikt?
	 *
	 * @param int $query_id De query.
	 *
	 * @return array
	 */
	public static function gebruikt_in( $query_id ) {
		$zoek = new WP_Query(
			array(
				'post_type'              => 'any',
				'post_status'            => array( 'publish', 'draft', 'private' ),
				'posts_per_page'         => 50,
				's'                      => 'wp:kadence/query',
				'fields'                 => 'ids',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$uit = array();

		foreach ( $zoek->posts as $id ) {
			$post = get_post( $id );

			if ( ! $post ) {
				continue;
			}

			foreach ( parse_blocks( $post->post_content ) as $blok ) {
				if ( self::verwijst_naar( $blok, (int) $query_id ) ) {
					$uit[] = array(
						'id'    => (int) $id,
						'title' => get_the_title( $id ),
						'type'  => $post->post_type,
					);
					break;
				}
			}
		}

		return $uit;
	}

	/**
	 * Verwijst dit blok (of iets eronder) naar deze query?
	 *
	 * @param array $blok     Het blok.
	 * @param int   $query_id De query.
	 *
	 * @return bool
	 */
	private static function verwijst_naar( $blok, $query_id ) {
		if ( isset( $blok['blockName'] ) && 'kadence/query' === $blok['blockName'] && isset( $blok['attrs']['id'] ) && (int) $blok['attrs']['id'] === $query_id ) {
			return true;
		}

		if ( ! empty( $blok['innerBlocks'] ) ) {
			foreach ( $blok['innerBlocks'] as $kind ) {
				if ( self::verwijst_naar( $kind, $query_id ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Een waarschuwing als de facetten na een blokwijziging uit de pas lopen.
	 *
	 * Dit hoort achter élke schrijfactie op een kadence_query. De editor werkt
	 * de facetten vanzelf bij; deze MCP schrijft rechtstreeks in de blokken en
	 * doet dat dus niet. Blijft de melding uit, dan zou er een filter op de
	 * pagina staan dat niets doet zonder dat iets dat zegt.
	 *
	 * @param WP_Post|null $post De post waarop geschreven is.
	 *
	 * @return string Lege tekst als er niets aan de hand is.
	 */
	public static function facetwaarschuwing( $post ) {
		if ( ! $post || 'kadence_query' !== $post->post_type ) {
			return '';
		}

		$stand = self::facetstand( $post->ID );

		if ( is_wp_error( $stand ) || $stand['in_sync'] ) {
			return '';
		}

		return ' ' . __( 'LET OP: dit is een Query Loop en de facetten in _kad_query_facets lopen nu uit de pas met de filterblokken. Zolang dat zo is doet minstens één filter op de pagina niets, zonder foutmelding. Werk ze bij met sync-query-facets.', 'mcp-abilities-kadence' );
	}

	/**
	 * Kan elk facet werkelijk iets tonen?
	 *
	 * De drie lagen kunnen perfect met elkaar kloppen terwijl een filter leeg
	 * blijft. Gemeten in de praktijk: een taxonomiefilter op een eigen posttype
	 * rendert als <div class="buttons-options"></div> — geldig, met de juiste
	 * hash, en zonder één optie, want dat posttype heeft helemaal geen
	 * taxonomieën.
	 *
	 * Dat is dezelfde soort stilte als een posttype dat niet bestaat, en het
	 * verdient dezelfde behandeling: niet tegenhouden, wel benoemen. Zonder
	 * deze controle meldt describe-query "de drie lagen kloppen met elkaar"
	 * over een filter dat niets kan doen.
	 *
	 * @param array $facetten     De berekende facetten.
	 * @param array $instellingen De queryinstellingen.
	 *
	 * @return array Eén regel per facet dat iets mankeert.
	 */
	public static function facetcontrole( $facetten, $instellingen ) {
		$post_types = isset( $instellingen['postType'] ) ? (array) $instellingen['postType'] : array();
		$uit        = array();

		foreach ( $facetten as $facet ) {
			$attrs = json_decode( (string) $facet['attributes'], true );

			if ( ! is_array( $attrs ) || 'taxonomy' !== ( isset( $attrs['source'] ) ? $attrs['source'] : '' ) ) {
				continue;
			}

			$taxonomie = isset( $attrs['taxonomy'] ) ? (string) $attrs['taxonomy'] : '';
			$blok      = isset( $attrs['uniqueID'] ) ? (string) $attrs['uniqueID'] : '';

			if ( '' === $taxonomie || ! taxonomy_exists( $taxonomie ) ) {
				$uit[] = array(
					'unique_id' => $blok,
					'probleem'  => 'taxonomie_bestaat_niet',
					'uitleg'    => sprintf(
						/* translators: %s: taxonomy name. */
						__( 'De taxonomie "%s" bestaat niet. Dit filter blijft leeg.', 'mcp-abilities-kadence' ),
						$taxonomie
					),
				);
				continue;
			}

			// Gekoppeld aan de posttypes die deze query ophaalt? Zo niet, dan
			// kan er geen enkel bericht bij een term horen.
			$gekoppeld = array();

			foreach ( $post_types as $type ) {
				if ( is_object_in_taxonomy( (string) $type, $taxonomie ) ) {
					$gekoppeld[] = (string) $type;
				}
			}

			if ( empty( $gekoppeld ) ) {
				$eigen = get_object_taxonomies( $post_types, 'names' );

				$uit[] = array(
					'unique_id' => $blok,
					'probleem'  => 'taxonomie_niet_gekoppeld',
					'uitleg'    => sprintf(
						/* translators: 1: taxonomy, 2: post types, 3: available taxonomies or a note. */
						__( 'De taxonomie "%1$s" hangt niet aan %2$s, dus dit filter blijft leeg. %3$s', 'mcp-abilities-kadence' ),
						$taxonomie,
						implode( ', ', $post_types ),
						empty( $eigen )
							? __( 'Dat posttype heeft helemaal geen taxonomieën; een taxonomiefilter kan hier dus niet werken. Kies een ander soort filter, of koppel er eerst een taxonomie aan.', 'mcp-abilities-kadence' )
							: sprintf(
								/* translators: %s: comma separated taxonomies. */
								__( 'Wel beschikbaar: %s.', 'mcp-abilities-kadence' ),
								implode( ', ', $eigen )
							)
					),
				);
				continue;
			}

			$termen = get_terms(
				array(
					'taxonomy'   => $taxonomie,
					'hide_empty' => true,
					'fields'     => 'ids',
					'number'     => 1,
				)
			);

			if ( is_wp_error( $termen ) || empty( $termen ) ) {
				$uit[] = array(
					'unique_id' => $blok,
					'probleem'  => 'geen_termen_in_gebruik',
					'uitleg'    => sprintf(
						/* translators: %s: taxonomy name. */
						__( 'De taxonomie "%s" bestaat en is gekoppeld, maar geen enkel bericht gebruikt een term. Dit filter blijft leeg tot er termen toegekend zijn.', 'mcp-abilities-kadence' ),
						$taxonomie
					),
				);
			}
		}

		return $uit;
	}
}
