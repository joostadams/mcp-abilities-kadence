<?php
/**
 * Abilities over de blokken zelf.
 *
 * @package Kadence_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * kadence/list-blocks en kadence/describe-block.
 */
class Kadence_MCP_Abilities_Blocks {

	/**
	 * Hoeveel attributen er standaard per pagina teruggaan.
	 *
	 * Bewust laag. Het grootste blok, kadence/rowlayout, heeft er 170 en zijn
	 * block.json is 17 KB — dat past niet in één MCP-antwoord.
	 */
	const ATTRIBUTEN_PER_PAGINA = 40;

	/**
	 * De ruwe definities.
	 *
	 * @return array[]
	 */
	public static function get_definitions() {
		return array(
			array(
				'name' => 'kadence/list-blocks',
				'args' => array(
					'label'       => __( 'Kadence-blokken opsommen', 'mcp-abilities-kadence' ),
					'summary'     => __( 'Welke Kadence-blokken op deze site geregistreerd zijn, en uit welke plugin ze komen.', 'mcp-abilities-kadence' ),
					'description' => __( 'Geeft alle Kadence-blokken met hun titel, herkomst (kadence-blocks of kadence-blocks-pro), aantal attributen en of ze een uniqueID voeren. Het veld registration zegt of het blok in PHP geregistreerd is of alleen in de editor bestaat (editor-only) — dat laatste geldt voor kadence/pane, kadence/tab en de countdown-kinderen, die je in de inhoud wél tegenkomt. Begin hiermee: pas als je weet welk blok er is, heeft describe-block zin. Blokken die alleen binnen een ander blok mogen staan dragen dat in "parent" of "ancestor", en die twee zijn niet hetzelfde: parent zegt waar het blok DIRECT in mag, ancestor dat het ergens ONDER dat blok moet hangen. De queryblokken mogen direct in een Sectie staan, maar alleen als die Sectie zelf onder een kadence/query hangt — daarom verschijnen ze in de editor pas zodra je in de Query Loop staat.', 'mcp-abilities-kadence' ),
					'input_schema' => array(
						'type'       => 'object',
						// Een default op het roottschema vangt de lege aanroep
						// op. Het type moet de STRING 'object' blijven: de MCP
						// Adapter vergelijkt daar strikt op.
						'default'    => (object) array(),
						'properties' => array(
							'search' => array(
								'type'        => 'string',
								'description' => __( 'Filter op een deel van de naam of titel.', 'mcp-abilities-kadence' ),
							),
							'source' => array(
								'type'        => 'string',
								'enum'        => array( 'all', 'kadence-blocks', 'kadence-blocks-pro' ),
								'default'     => 'all',
								'description' => __( 'Filter op herkomst.', 'mcp-abilities-kadence' ),
							),
							'top_level_only' => array(
								'type'        => 'boolean',
								'default'     => false,
								'description' => __( 'Laat kindblokken weg die alleen binnen een ander blok mogen staan.', 'mcp-abilities-kadence' ),
							),
						),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'environment' => array( 'type' => 'object' ),
							'total'       => array( 'type' => 'integer' ),
							'blocks'      => array( 'type' => 'array' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'list_blocks' ),
				),
			),
			array(
				'name' => 'kadence/describe-block',
				'args' => array(
					'label'       => __( 'Een Kadence-blok beschrijven', 'mcp-abilities-kadence' ),
					'summary'     => __( 'De attributen van één blok, gefilterd en per pagina.', 'mcp-abilities-kadence' ),
					'description' => __( 'Geeft de attributen van één blok: type, standaardwaarde, toegestane waarden en de groep waar het attribuut in hoort. Grote blokken worden gepagineerd — kadence/rowlayout heeft er ruim 170. LET OP bij zoeken: "search" matcht op de attribuutNAAM, en Kadence bewaart responsive waarden vaak in een array [desktop, tablet, mobiel] onder één naam. Het attribuut dat de mobiele richting van een Sectie bepaalt heet "direction", niet "mobileDirection" — zoeken op "mobile" vindt hem dus niet. Wil je zeker weten wat een blok kan, filter dan op "group" of haal alles op met per_page 100. Een standaardwaarde die zelf een grote structuur is wordt samengevat.', 'mcp-abilities-kadence' ),
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'name' => array(
								'type'        => 'string',
								'description' => __( 'Volledige bloknaam, bijvoorbeeld kadence/rowlayout.', 'mcp-abilities-kadence' ),
							),
							'attributes' => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => __( 'Alleen deze attributen. Gaat voor op search en paginering.', 'mcp-abilities-kadence' ),
							),
							'search' => array(
								'type'        => 'string',
								'description' => __( 'Filter attributen op een deel van hun NAAM. Vindt daarom geen responsive waarden die in een array onder één naam staan — gebruik dan group of haal alles op.', 'mcp-abilities-kadence' ),
							),
							'group' => array(
								'type'        => 'string',
								'enum'        => array( 'responsive', 'indeling', 'spacing', 'achtergrond', 'rand', 'typografie', 'kleur', 'link', 'afmeting', 'conditioneel', 'identiteit', 'overig' ),
								'description' => __( 'Filter op groep. Betrouwbaarder dan search wanneer je wil weten wat een blok op een bepaald vlak kan. "responsive" bevat ook de array-attributen die search mist.', 'mcp-abilities-kadence' ),
							),
							'page' => array(
								'type'    => 'integer',
								'minimum' => 1,
								'default' => 1,
							),
							'per_page' => array(
								'type'    => 'integer',
								'minimum' => 1,
								'maximum' => 100,
								'default' => self::ATTRIBUTEN_PER_PAGINA,
							),
							'include_supports' => array(
								'type'        => 'boolean',
								'default'     => false,
								'description' => __( 'Neem het supports-blok mee.', 'mcp-abilities-kadence' ),
							),
						),
						'required'             => array( 'name' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'block'      => array( 'type' => 'object' ),
							'attributes' => array( 'type' => 'array' ),
							'pagination' => array( 'type' => 'object' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'describe_block' ),
				),
			),
		);
	}

	/**
	 * Verklaar de uitkomst van describe-block in één regel.
	 *
	 * @param array  $uitvoer  De teruggegeven attributen.
	 * @param int    $totaal   Het aantal treffers vóór paginering.
	 * @param array  $gevraagd De expliciet gevraagde attributen.
	 * @param string $zoek     De zoekterm.
	 * @param string $groep    De groepsfilter.
	 *
	 * @return string
	 */
	private static function describe_status( $uitvoer, $totaal, $gevraagd, $zoek, $groep ) {
		if ( ! empty( $uitvoer ) ) {
			return '' === $zoek && '' === $groep && empty( $gevraagd )
				? __( 'volledige attributenlijst, op groep gesorteerd.', 'mcp-abilities-kadence' )
				: __( 'gefilterde lijst. Kijk in "groups" hoeveel attributen dit blok in totaal per groep heeft.', 'mcp-abilities-kadence' );
		}

		if ( ! empty( $gevraagd ) ) {
			return sprintf(
				/* translators: %s: comma-separated attribute names. */
				__( 'leeg — geen van de gevraagde attributen (%s) bestaat op dit blok. Controleer de spelling; attribuutnamen zijn hoofdlettergevoelig.', 'mcp-abilities-kadence' ),
				implode( ', ', $gevraagd )
			);
		}

		if ( '' !== $groep ) {
			return __( 'leeg — dit blok heeft geen attributen in deze groep. Kijk in "groups" welke groepen het wél heeft.', 'mcp-abilities-kadence' );
		}

		if ( '' !== $zoek ) {
			return __( 'leeg — geen attribuutnaam bevat deze term. Let op dat responsive waarden vaak in een array onder één naam staan, zonder "mobile" of "tablet" in de naam. Probeer group of haal alles op.', 'mcp-abilities-kadence' );
		}

		return __( 'leeg — dit blok heeft geen attributen.', 'mcp-abilities-kadence' );
	}

	/**
	 * Som de blokken op.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array
	 */
	public static function list_blocks( $input = array() ) {
		$zoek   = isset( $input['search'] ) ? strtolower( trim( (string) $input['search'] ) ) : '';
		$bron   = isset( $input['source'] ) ? (string) $input['source'] : 'all';
		$alleen = ! empty( $input['top_level_only'] );

		$blokken = array();

		foreach ( Kadence_MCP_Inventory::get_blocks() as $blok ) {
			if ( 'all' !== $bron && $blok['source'] !== $bron ) {
				continue;
			}

			// Zowel parent als ancestor beperken waar een blok mag staan. Alleen
			// op parent filteren laat kadence/navigation-link en
			// kadence/off-canvas-trigger door als "vrij plaatsbaar", terwijl die
			// uitsluitend binnen een navigatie of header bestaan.
			if ( $alleen && ( ! empty( $blok['parent'] ) || ! empty( $blok['ancestor'] ) ) ) {
				continue;
			}

			if ( '' !== $zoek ) {
				$haystack = strtolower( $blok['name'] . ' ' . $blok['title'] );

				if ( false === strpos( $haystack, $zoek ) ) {
					continue;
				}
			}

			$blokken[] = $blok;
		}

		$status = '';

		if ( ! empty( $blokken ) ) {
			$status = sprintf(
				/* translators: %d: number of blocks. */
				__( '%d blokken. Gebruik describe-block voor de attributen van één blok.', 'mcp-abilities-kadence' ),
				count( $blokken )
			);
		} elseif ( '' !== $zoek || 'all' !== $bron || $alleen ) {
			$status = __( 'leeg — geen blok voldoet aan dit filter. Roep list-blocks zonder filters aan om te zien wat er is.', 'mcp-abilities-kadence' );
		} else {
			$status = __( 'leeg — er is geen enkel kadence/*-blok geregistreerd. Draait Kadence Blocks wel, en is deze aanroep op de frontend of in de admin gedaan?', 'mcp-abilities-kadence' );
		}

		return array(
			'environment' => Kadence_MCP_Inventory::get_environment(),
			'total'       => count( $blokken ),
			'status'      => $status,
			'blocks'      => $blokken,
		);
	}

	/**
	 * Beschrijf één blok.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function describe_block( $input = array() ) {
		$naam = isset( $input['name'] ) ? trim( (string) $input['name'] ) : '';

		if ( '' === $naam ) {
			return new WP_Error(
				'kadence_mcp_missing_name',
				__( 'Geef de volledige bloknaam op, bijvoorbeeld kadence/rowlayout.', 'mcp-abilities-kadence' )
			);
		}

		$blok = Kadence_MCP_Inventory::get_block( $naam );

		if ( is_wp_error( $blok ) ) {
			return $blok;
		}

		$attributen = $blok['attributes'];
		$gevraagd   = isset( $input['attributes'] ) && is_array( $input['attributes'] ) ? $input['attributes'] : array();
		$zoek       = isset( $input['search'] ) ? strtolower( trim( (string) $input['search'] ) ) : '';

		$groep = isset( $input['group'] ) ? (string) $input['group'] : '';

		if ( ! empty( $gevraagd ) ) {
			$attributen = array_intersect_key( $attributen, array_flip( $gevraagd ) );
		} elseif ( '' !== $groep ) {
			$attributen = array_filter(
				$attributen,
				static function ( $sleutel ) use ( $groep ) {
					return Kadence_MCP_Inventory::groep_van_attribuut( $sleutel ) === $groep;
				},
				ARRAY_FILTER_USE_KEY
			);
		} elseif ( '' !== $zoek ) {
			$attributen = array_filter(
				$attributen,
				static function ( $sleutel ) use ( $zoek ) {
					return false !== strpos( strtolower( $sleutel ), $zoek );
				},
				ARRAY_FILTER_USE_KEY
			);
		}

		ksort( $attributen );

		$totaal   = count( $attributen );
		$per_page = isset( $input['per_page'] ) ? (int) $input['per_page'] : self::ATTRIBUTEN_PER_PAGINA;
		$per_page = max( 1, min( 100, $per_page ) );
		$pagina   = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;

		// Een expliciete lijst is per definitie de hele vraag; die niet ook nog
		// eens pagineren, anders krijgt de agent minder terug dan hij vroeg.
		$plak = empty( $gevraagd )
			? array_slice( $attributen, ( $pagina - 1 ) * $per_page, $per_page, true )
			: $attributen;

		$uitvoer = array();

		foreach ( $plak as $sleutel => $definitie ) {
			$samenvatting          = Kadence_MCP_Inventory::vat_attribuut_samen( $sleutel, is_array( $definitie ) ? $definitie : array(), $naam );
			$samenvatting['group'] = Kadence_MCP_Inventory::groep_van_attribuut( $sleutel );

			$uitvoer[] = $samenvatting;
		}

		// Op groep sorteren in plaats van alfabetisch: dan staat alles wat bij
		// elkaar hoort ook bij elkaar, en zie je in één oogopslag wat een blok
		// op een bepaald vlak kan.
		usort(
			$uitvoer,
			static function ( $x, $y ) {
				$vergelijk = strcmp( $x['group'], $y['group'] );

				return 0 !== $vergelijk ? $vergelijk : strcmp( $x['name'], $y['name'] );
			}
		);

		// Een telling over ALLE attributen van het blok, niet alleen de
		// teruggegeven pagina — anders is niet te zien wat je nog mist.
		$per_groep = array();

		foreach ( array_keys( $blok['attributes'] ) as $sleutel ) {
			$g = Kadence_MCP_Inventory::groep_van_attribuut( $sleutel );

			$per_groep[ $g ] = isset( $per_groep[ $g ] ) ? $per_groep[ $g ] + 1 : 1;
		}

		ksort( $per_groep );

		$kop = array(
			'name'            => $blok['name'],
			'title'           => $blok['title'],
			'description'     => $blok['description'],
			'category'        => $blok['category'],
			'source'          => $blok['source'],
			// 'php' of 'editor-only'. Bij editor-only is het blok alleen in
			// JavaScript geregistreerd; de gegevens komen dan van de block.json
			// op schijf in plaats van uit het blokkenregister.
			'registration'    => isset( $blok['registration'] ) ? $blok['registration'] : 'php',
			'parent'          => $blok['parent'],
			// ancestor is iets anders dan parent: parent zegt waar het blok
			// DIRECT in mag, ancestor dat het ergens ONDER dat blok moet hangen.
			'ancestor'         => isset( $blok['ancestor'] ) ? $blok['ancestor'] : array(),
			// Deze twee gaan niet over plaatsing maar over gegevensstroom. Een
			// query-card weet welke post hij rendert doordat kadence/query die
			// context levert; zonder deze velden is niet uit te leggen waarom
			// dynamische inhoud in het ene blok werkt en in het andere niet.
			'uses_context'     => isset( $blok['uses_context'] ) ? $blok['uses_context'] : array(),
			// Wat het blok zelf declareert tegenover wat een andere plugin er bij
			// heeft gezet. GP Entry Blocks doet dat op élk blok, dus zonder dit
			// onderscheid lijkt zijn context een Kadence-eigenschap.
			'uses_context_own'   => isset( $blok['uses_context_own'] ) ? $blok['uses_context_own'] : array(),
			'uses_context_added' => isset( $blok['uses_context_added'] ) ? $blok['uses_context_added'] : array(),
			'provides_context' => isset( $blok['provides_context'] ) ? $blok['provides_context'] : array(),
			'allowed_blocks'   => isset( $blok['allowed_blocks'] ) ? $blok['allowed_blocks'] : array(),
			'keywords'         => isset( $blok['keywords'] ) ? $blok['keywords'] : array(),
			'attribute_count' => count( $blok['attributes'] ),
		);

		if ( ! empty( $input['include_supports'] ) ) {
			$kop['supports'] = $blok['supports'];
		}

		return array(
			'block'      => $kop,
			'groups'     => $per_groep,
			'attributes' => $uitvoer,
			'status'     => self::describe_status( $uitvoer, $totaal, $gevraagd, $zoek, $groep ),
			'pagination' => array(
				'matched'   => $totaal,
				'returned'  => count( $uitvoer ),
				'page'      => empty( $gevraagd ) ? $pagina : 1,
				'per_page'  => empty( $gevraagd ) ? $per_page : $totaal,
				'has_more'  => empty( $gevraagd ) && ( $pagina * $per_page ) < $totaal,
			),
		);
	}
}
