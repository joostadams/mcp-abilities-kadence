<?php
/**
 * Blokprofielen: alles wat we per bloktype weten, op één plek.
 *
 * @package Kadence_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wat er per bloktype bekend is, en waarom dat bij elkaar hoort.
 *
 * Deze kennis stond tot 1.10.0 op vier losse plekken: de wrapper in VORMEN, de
 * kleurklassen in één functie, de richtingklassen in een tweede, en de
 * toegestane layoutnamen in een derde. Bij elk nieuw bloktype moest je aan alle
 * vier denken.
 *
 * Twee keer is dat misgegaan, en beide keren op dezelfde manier:
 *
 * - colorClass werd niet als klasse meegeschreven → de tekst bleef zwart
 * - direction werd niet als klasse meegeschreven → de Sectie bleef verticaal
 *
 * In allebei de gevallen was het attribuut correct opgeslagen, gaf
 * validate-write groen licht, en meldde de terugleescontrole succes. De fout
 * zat niet in de logica maar in het feit dat de generator en de validatie niet
 * van elkaar wisten.
 *
 * Daarom staat het hier bij elkaar. De generator leest het profiel om markup te
 * bouwen; validate-write leest hetzelfde profiel om te weten DAT er markup in
 * het spel is. Eén plek om aan te vullen, en onmogelijk om er nog eentje te
 * vergeten.
 *
 * Elk profiel kan bevatten:
 *
 *   open, sluit     De wrapper-HTML. {ID} wordt de uniqueID, {TAG} de HTML-tag.
 *                   Placeholders uit 'klassen' worden ook hierin vervangen.
 *   zelfsluitend    true als het blok nooit inhoud draagt.
 *   klassen         Placeholder => hoe die gevuld wordt (zie KLASSENREGELS).
 *   waardenlijsten  Attribuut => de waarden die Kadence kent, voor attributen
 *                   die geen enum in block.json hebben.
 *   markup_attrs    Attributen die markup AFLEIDEN. Wijzig je zo'n attribuut op
 *                   een bestaand blok, dan verandert alleen het commentaar en
 *                   niet de klasse. Daar hoort validate-write voor te
 *                   waarschuwen.
 */
class Kadence_MCP_Profielen {

	/**
	 * Hoe een klassenplaceholder gevuld wordt.
	 *
	 * Elke regel zegt: kijk naar dit attribuut, en maak daar deze klassen van.
	 *
	 * 'kleur'    één slug → twee klassen, met een streepje vóór het cijfer:
	 *            theme-palette9 → has-theme-palette-9-color has-text-color
	 * 'richting' array van 3 → één klasse per niveau, elk met eigen voorvoegsel.
	 */
	const KLASSENREGELS = array(
		'{KLEUR}'    => array(
			'soort'      => 'kleur',
			'attributen' => array(
				'colorClass'           => array( 'has-%s-color', 'has-text-color' ),
				'backgroundColorClass' => array( 'has-%s-background-color', 'has-background' ),
			),
		),
		'{RICHTING}' => array(
			'soort'      => 'richting',
			'attribuut'  => 'direction',
			'voorvoegsels' => array( 'kb-section-dir-', 'kb-section-md-dir-', 'kb-section-sm-dir-' ),
		),
		// De verbergopties van Kadence. De klassenaam zegt het omgekeerde van
		// het attribuut: vstablet TRUE geeft kvs-md-FALSE. Niet verzinnen dus —
		// afgelezen uit dist/blocks-column.js, de classnames-aanroep in save().
		'{ZICHTBAAR}' => array(
			'soort'      => 'vlaggen',
			'attributen' => array(
				'vsdesk'   => 'kvs-lg-false',
				'vstablet' => 'kvs-md-false',
				'vsmobile' => 'kvs-sm-false',
				'sticky'   => 'kb-section-is-sticky',
			),
		),
		// Het vrije className-attribuut. WordPress hangt dit achteraan via
		// useBlockProps.save(). Zonder deze regel zou opnieuw opbouwen een
		// handmatig toegevoegde klasse wissen — en dat is precies de stille
		// schade die deze plug-in hoort te voorkomen.
		'{KLASSE}' => array(
			'soort'     => 'letterlijk',
			'attribuut' => 'className',
		),
	);

	/**
	 * Attributen die klassen afleiden waarvan de regel hier NIET staat.
	 *
	 * Opnieuw opbouwen mag alleen als elke afgeleide klasse bekend is. Staat er
	 * een van deze attributen op het blok, dan zou de nieuwe markup een klasse
	 * missen die Kadence er wél in zet, en dan is het blok in de editor
	 * ongeldig — hetzelfde probleem dat we juist aan het repareren zijn.
	 *
	 * Liever hardop weigeren dan de afleiding raden. Wie zo een geval
	 * tegenkomt, leest de klasse af van het echte blok en vult de regel aan.
	 */
	const ONGEDEKTE_MARKUP_ATTRS = array(
		'kadence/column' => array(
			// kb-section-has-link
			'link',
			// align{full|wide}
			'align',
			// inner-column-{id}
			'id',
			// kb-section-has-overlay, afgeleid uit een combinatie van
			// achtergrond- en overlayvelden; die voorwaarde raden we niet.
			'overlay',
			'overlayGradient',
			'bgImg',
			'background',
		),
	);

	/**
	 * De profielen.
	 *
	 * Alles hieronder is AFGELEZEN van markup die Kadence zelf heeft
	 * geschreven, niet uit documentatie. Waar een waarnemingsdatum en bron
	 * staan, is dat waar het vandaan komt.
	 *
	 * @return array
	 */
	public static function alle() {
		return array(

			// Draagt geen eigen markup: zijn kinderen staan rechtstreeks tussen
			// de twee commentaren. Daarom is elk attribuut erop vrij te
			// wijzigen — er kan niets uit de pas lopen.
			'kadence/rowlayout' => array(
				'open'           => '',
				'sluit'          => '',
				'waardenlijsten' => array(
					// colLayout heeft geen enum in block.json, dus
					// rest_validate_value_from_schema() laat élke tekst door.
					// De verzonnen waarde "thirds" kwam er zo doorheen en zette
					// in de editor alle kolommen onder elkaar.
					// Afgelezen uit dist/blocks-rowlayout.js (3.7.8), variabele Zt.
					'colLayout' => array(
						1 => array( 'equal', 'row' ),
						2 => array( 'equal', 'left-golden', 'right-golden', 'row' ),
						3 => array( 'equal', 'left-half', 'right-half', 'center-half', 'center-wide', 'center-exwide', 'first-row', 'last-row', 'row' ),
						4 => array( 'equal', 'left-forty', 'right-forty', 'two-grid', 'row' ),
						5 => array( 'equal', 'row' ),
						6 => array( 'equal', 'two-grid', 'three-grid', 'row' ),
					),
				),
				'afhankelijk_van' => array(
					// colLayout is alleen te toetsen als je weet hoeveel
					// kolommen de rij heeft.
					'colLayout' => 'columns',
				),
			),

			// Dubbele div; de buitenste draagt de uniqueID en de klassen.
			'kadence/column' => array(
				// De volgorde is die van de classnames-aanroep in save():
				// kadence-column{ID}, kvs-*, is-sticky, kb-section-dir-*, en
				// helemaal achteraan het vrije className uit useBlockProps.
				'open'    => '<div class="wp-block-kadence-column kadence-column{ID}{ZICHTBAAR}{RICHTING}{KLEUR}{KLASSE}"><div class="kt-inside-inner-col">',
				'sluit'   => '</div></div>',
				'klassen' => array( '{ZICHTBAAR}', '{RICHTING}', '{KLEUR}', '{KLASSE}' ),
				'markup_attrs' => array( 'direction', 'vsdesk', 'vstablet', 'vsmobile', 'sticky', 'className' ),
				'waardenlijsten' => array(
					// Afgelezen uit dist/blocks-column.js (3.7.8).
					'verticalAlignment' => array( 'top', 'middle', 'bottom', 'stretch' ),
					'direction'         => array( 'vertical', 'horizontal', 'vertical-reverse', 'horizontal-reverse' ),
				),
				'let_op' => 'Bij direction vertical stuurt verticalAlignment de HOOGTE (justify-content) en justifyContent de BREEDTE (align-items). Zie class-kadence-blocks-column-block.php:58 en 320.',
			),

			// Zet de uniqueID twee keer neer: in de klasse en in data-kb-block.
			// Vergeet je er één, dan verliest het blok zijn CSS.
			'kadence/advancedheading' => array(
				'open'    => '<{TAG} class="kt-adv-heading{ID} wp-block-kadence-advancedheading{KLEUR}" data-kb-block="kb-adv-heading{ID}">',
				'sluit'   => '</{TAG}>',
				'klassen' => array( '{KLEUR}' ),
				'markup_attrs' => array( 'colorClass', 'backgroundColorClass' ),
				'let_op' => 'De tekst staat in de innerHTML, niet in een attribuut: het attribuut content heeft source html. Gebruik set-text.',
			),

			'kadence/advancedbtn' => array(
				'open'  => '<div class="wp-block-kadence-advancedbtn kb-buttons-wrap kb-btns{ID}">',
				'sluit' => '</div>',
			),

			'kadence/singlebtn' => array( 'zelfsluitend' => true ),

			// Het pictogram. Afgelezen van kaart 251, 14-09-2026, nadat bleek
			// dat een weggehaald icoon nergens meer vandaan te halen was: er
			// stond op de hele site geen tweede kadence/icon om te kopiëren,
			// en zonder profiel kon generate-section hem ook niet bouwen. Een
			// blok dat je wél kunt verwijderen maar niet kunt terugzetten is
			// een gat in het gereedschap, niet in de site.
			//
			// Let op de twee wrappers. kadence/icon draagt de klasse met het
			// uniqueID; kadence/single-icon zit eronder en heeft een EIGEN
			// uniqueID in een andere klassevorm — kt-svg-item-{ID} in plaats
			// van kt-svg-icons{ID}. De span daarbinnen draagt de icoonnaam en
			// de lijndikte als data-attributen, en Kadence zet daar bij het
			// tonen de SVG in. Die span is dus geen inhoud maar een aanhechting:
			// hij hoort leeg te blijven.
			'kadence/icon' => array(
				'open'  => '<div class="wp-block-kadence-icon kt-svg-icons kt-svg-icons{ID} alignnone">',
				'sluit' => '</div>',
				'let_op' => 'Een omhulling om een of meer kadence/single-icon. Zet de eigenlijke instellingen (icon, size, color) op het kindblok, niet hier.',
			),
			'kadence/single-icon' => array(
				'open'  => '<div class="wp-block-kadence-single-icon kt-svg-style-default kt-svg-icon-wrap kt-svg-item-{ID}"><span data-name="{ATTR:icon}" data-stroke="{ATTR:width}" class="kadence-dynamic-icon">',
				'sluit' => '</span></div>',
				'let_op' => 'Hoort altijd in een kadence/icon. Het attribuut icon is een naam als fe_tag of fas_euro-sign; die namen verzin je niet maar lees je af van een bestaand blok. De span blijft leeg — Kadence zet daar bij het tonen de SVG in — maar de data-attributen erop moeten kloppen, want daar leest hij de naam en de lijndikte uit.',
			),

			// De Query-blokken. Afgelezen van query 1456, 11-09-2026.
			//
			// kadence/query heeft twee gedaanten: in een PAGINA verwijst hij met
			// een id naar een query-post en is hij leeg (dus zelfsluitend); in
			// die query-post zelf draagt hij de hele layout. Daarom geen
			// 'zelfsluitend' maar lege open/sluit — de generator maakt er
			// vanzelf /--> van zodra er geen inhoud is.
			'kadence/query'               => array( 'open' => '', 'sluit' => '' ),
			// Twee gedaanten, net als kadence/query. In een Query Loop is dit
			// een VERWIJZING naar een kaart-post en dus leeg; in die kaart-post
			// zelf is het de omhulling om de hele opmaak. Daarom geen
			// 'zelfsluitend' maar lege open/sluit: de generator maakt er vanzelf
			// /--> van zodra er geen inhoud is. Stond hier wel zelfsluitend, dan
			// zou een kaart met inhoud zijn eigen opmaak verliezen.
			'kadence/query-card'          => array( 'open' => '', 'sluit' => '' ),

			// De dynamische blokken waaruit een Query Card is opgebouwd.
			// Afgelezen van kaart 232, 13-09-2026. Allebei zelfsluitend: ze
			// hebben geen eigen markup in de opgeslagen inhoud, want ze worden
			// bij het tonen door PHP gerenderd.
			'kadence/dynamichtml'         => array(
				'zelfsluitend' => true,
				'let_op'       => 'Toont één veld. Met field post|post_custom_field haal je een eigen veld op; zet dan para op kb_custom_input en custom op de meta-sleutel. Welke sleutels er zijn zie je met describe-post-type. Een verkeerde sleutel geeft een leeg blok zonder foutmelding.',
			),
			'kadence/dynamiclist'         => array(
				'zelfsluitend' => true,
				'let_op'       => 'Toont de termen van een taxonomie, bijvoorbeeld als pill. Het attribuut tax moet de naam van een taxonomie zijn die aan het posttype van de query hangt; describe-post-type laat zien welke dat zijn.',
			),
			'kadence/query-filter'        => array( 'zelfsluitend' => true ),
			'kadence/query-filter-buttons' => array( 'zelfsluitend' => true ),
			'kadence/query-filter-search' => array( 'zelfsluitend' => true ),
			'kadence/query-filter-reset'  => array( 'zelfsluitend' => true ),
			'kadence/query-result-count'  => array( 'zelfsluitend' => true ),
			'kadence/query-sort'          => array( 'zelfsluitend' => true ),
			'kadence/query-pagination'    => array( 'zelfsluitend' => true ),
			'kadence/query-noresults'     => array( 'open' => '', 'sluit' => '' ),
		);
	}

	/**
	 * Het profiel van één bloktype.
	 *
	 * @param string $bloknaam De bloknaam.
	 *
	 * @return array|null
	 */
	public static function van( $bloknaam ) {
		$alle = self::alle();

		if ( ! isset( $alle[ $bloknaam ] ) ) {
			return null;
		}

		// Vul de standaardsleutels aan zodat aanroepers niet hoeven te raden.
		return array_merge(
			array(
				'open'            => '',
				'sluit'           => '',
				'zelfsluitend'    => false,
				'klassen'         => array(),
				'markup_attrs'    => array(),
				'waardenlijsten'  => array(),
				'afhankelijk_van' => array(),
				'let_op'          => '',
			),
			$alle[ $bloknaam ]
		);
	}

	/**
	 * Kent de plug-in dit bloktype?
	 *
	 * @param string $bloknaam De bloknaam.
	 *
	 * @return bool
	 */
	public static function bekend( $bloknaam ) {
		return null !== self::van( $bloknaam );
	}

	/**
	 * Alle bekende bloknamen.
	 *
	 * @return array
	 */
	public static function bloknamen() {
		return array_keys( self::alle() );
	}

	/**
	 * De klassen die uit de attributen volgen, per placeholder.
	 *
	 * @param string $bloknaam De bloknaam.
	 * @param array  $attrs    De genormaliseerde attributen.
	 *
	 * @return array Placeholder => tekst die erin moet (begint met een spatie, of leeg).
	 */
	public static function klassen( $bloknaam, $attrs ) {
		$profiel = self::van( $bloknaam );
		$uit     = array();

		if ( null === $profiel ) {
			return $uit;
		}

		foreach ( $profiel['klassen'] as $plaatshouder ) {
			if ( ! isset( self::KLASSENREGELS[ $plaatshouder ] ) ) {
				continue;
			}

			$regel   = self::KLASSENREGELS[ $plaatshouder ];
			$klassen = array();

			if ( 'kleur' === $regel['soort'] ) {
				foreach ( $regel['attributen'] as $attribuut => $patronen ) {
					if ( empty( $attrs[ $attribuut ] ) ) {
						continue;
					}

					// theme-palette9 wordt theme-palette-9: WordPress' eigen
					// omzetting naar kebab-case zet een streepje vóór het cijfer.
					$slug = preg_replace( '/([a-z])(\d+)$/', '$1-$2', (string) $attrs[ $attribuut ] );

					foreach ( $patronen as $patroon ) {
						$klassen[] = false === strpos( $patroon, '%s' ) ? $patroon : sprintf( $patroon, $slug );
					}
				}
			}

			if ( 'vlaggen' === $regel['soort'] ) {
				foreach ( $regel['attributen'] as $attribuut => $klasse ) {
					if ( ! empty( $attrs[ $attribuut ] ) ) {
						$klassen[] = $klasse;
					}
				}
			}

			if ( 'letterlijk' === $regel['soort'] ) {
				$waarde = isset( $attrs[ $regel['attribuut'] ] ) ? trim( (string) $attrs[ $regel['attribuut'] ] ) : '';

				if ( '' !== $waarde ) {
					$klassen[] = $waarde;
				}
			}

			if ( 'richting' === $regel['soort'] ) {
				$waarde = isset( $attrs[ $regel['attribuut'] ] ) ? $attrs[ $regel['attribuut'] ] : null;

				if ( is_array( $waarde ) ) {
					foreach ( $regel['voorvoegsels'] as $i => $voorvoegsel ) {
						if ( ! empty( $waarde[ $i ] ) ) {
							$klassen[] = $voorvoegsel . $waarde[ $i ];
						}
					}
				}
			}

			$uit[ $plaatshouder ] = empty( $klassen ) ? '' : ' ' . implode( ' ', $klassen );
		}

		// Placeholders die dit blok niet gebruikt moeten alsnog verdwijnen.
		foreach ( array_keys( self::KLASSENREGELS ) as $plaatshouder ) {
			if ( ! isset( $uit[ $plaatshouder ] ) ) {
				$uit[ $plaatshouder ] = '';
			}
		}

		return $uit;
	}

	/**
	 * Toets een waarde tegen de lijst die Kadence kent.
	 *
	 * Alleen voor attributen zonder enum in block.json. Voor de rest doet
	 * rest_validate_value_from_schema() het werk al.
	 *
	 * @param string $bloknaam De bloknaam.
	 * @param string $attribuut Het attribuut.
	 * @param mixed  $waarde    De voorgenomen waarde.
	 * @param array  $huidig    De huidige attributen van het blok, voor
	 *                          afhankelijkheden als colLayout ↔ columns.
	 *
	 * @return string Leeg als er geen bezwaar is.
	 */
	public static function toets_waarde( $bloknaam, $attribuut, $waarde, $huidig = array() ) {
		$profiel = self::van( $bloknaam );

		if ( null === $profiel || ! isset( $profiel['waardenlijsten'][ $attribuut ] ) ) {
			return '';
		}

		$lijst = $profiel['waardenlijsten'][ $attribuut ];

		// Een responsive attribuut is een array van drie: [desktop, tablet,
		// mobiel]. Elk element afzonderlijk toetsen, want (string) op een array
		// levert "Array" op — en dan komt er een foutmelding uit die zegt dat
		// Kadence de waarde "Array" niet kent. Die melding is inhoudelijk waar
		// en volstrekt nutteloos, en hij blokkeerde het opnieuw opbouwen van
		// een kolom die alleen maar een geldige direction had.
		if ( is_array( $waarde ) ) {
			foreach ( $waarde as $element ) {
				if ( is_array( $element ) ) {
					continue;
				}

				$bezwaar = self::toets_waarde( $bloknaam, $attribuut, $element, $huidig );

				if ( '' !== $bezwaar ) {
					return $bezwaar;
				}
			}

			return '';
		}

		// Sommige lijsten hangen af van een ander attribuut, zoals colLayout
		// van het aantal kolommen.
		if ( isset( $profiel['afhankelijk_van'][ $attribuut ] ) ) {
			$sleutel = $profiel['afhankelijk_van'][ $attribuut ];
			$aantal  = isset( $huidig[ $sleutel ] ) ? (int) $huidig[ $sleutel ] : 2;

			if ( ! isset( $lijst[ $aantal ] ) ) {
				return '';
			}

			$lijst = $lijst[ $aantal ];

			if ( '' === (string) $waarde || in_array( (string) $waarde, $lijst, true ) ) {
				return '';
			}

			return sprintf(
				/* translators: 1: value, 2: attribute, 3: dependency value, 4: allowed values. */
				__( 'Kadence kent de waarde "%1$s" niet voor %2$s bij %3$d kolommen. Op de voorkant valt dat vaak niet op, maar in de editor wel. Geldig zijn: %4$s.', 'mcp-abilities-kadence' ),
				$waarde,
				$attribuut,
				$aantal,
				implode( ', ', $lijst )
			);
		}

		if ( '' === (string) $waarde || in_array( (string) $waarde, $lijst, true ) ) {
			return '';
		}

		return sprintf(
			/* translators: 1: value, 2: attribute, 3: allowed values. */
			__( 'Kadence kent de waarde "%1$s" niet voor %2$s. Dit attribuut heeft geen enum in block.json, dus de gewone typetoets laat elke tekst door. Geldig zijn: %3$s.', 'mcp-abilities-kadence' ),
			$waarde,
			$attribuut,
			implode( ', ', $lijst )
		);
	}

	/**
	 * Leidt dit attribuut markup af?
	 *
	 * @param string $bloknaam  De bloknaam.
	 * @param string $attribuut Het attribuut.
	 *
	 * @return bool
	 */
	public static function raakt_markup( $bloknaam, $attribuut ) {
		$profiel = self::van( $bloknaam );

		return null !== $profiel && in_array( $attribuut, $profiel['markup_attrs'], true );
	}

	/**
	 * De filterblokken waar Kadence attributen bij plaatst.
	 *
	 * Let op dat query-filter-search hier WEL bij staat en in
	 * Kadence_MCP_Query::FACETBLOKKEN niet: hij krijgt dezelfde attributen maar
	 * levert geen facet op. Twee lijsten die op elkaar lijken en het niet zijn.
	 */
	const FILTERBLOKKEN_MET_INJECTIE = array(
		'kadence/query-filter',
		'kadence/query-filter-buttons',
		'kadence/query-filter-date',
		'kadence/query-filter-checkbox',
		'kadence/query-filter-range',
		'kadence/query-filter-search',
		'kadence/query-filter-woo-attribute',
		'kadence/query-filter-rating',
	);

	/**
	 * Attributen die in JavaScript worden bijgeplaatst en in GEEN block.json staan.
	 *
	 * Dit is het metadata-probleem in het groot. Kadence voegt deze attributen
	 * in de editor toe met een filter op blocks.registerBlockType
	 * (dist/early-filters.js). Het PHP-register kent ze dus niet, en alles wat
	 * in deze plug-in "bestaat dit attribuut?" vraagt kreeg tot 1.10.1 nee te
	 * horen — waarna normaliseer_attributen ze bij elke schrijfactie weggooide.
	 *
	 * Voor een filterblok is dat geen schoonheidsfoutje: daar staat in taxonomy
	 * WELKE taxonomie het filter toont, en die waarde bepaalt ook de facet-hash.
	 * Eén set-attributes op zo een blok zou de taxonomie wissen, de hash laten
	 * verspringen en het filter stil leegmaken.
	 *
	 * Alleen de vorm en de standaardwaarde staan hier; de defaults die van het
	 * bloktype afhangen (source en post_field) rekent Kadence_MCP_Query uit.
	 * Afgelezen uit dist/early-filters.js, 13-09-2026.
	 */
	public static function injecties( $bloknaam ) {
		$uit = array();

		// Op elk blok dat ktanimate ondersteunt. Onschuldig om breed te laten
		// gelden: het gaat om twee attributen die anders stil zouden sneuvelen.
		$uit['kadenceAnimation']  = array( 'type' => 'string' );
		$uit['kadenceAOSOptions'] = array( 'type' => 'array' );
		$uit['kadenceDynamic']    = array( 'type' => 'object' );
		$uit['kadenceConditional'] = array( 'type' => 'object' );

		if ( 'kadence/column' === $bloknaam ) {
			$uit['backdropFilterType']   = array( 'type' => 'string', 'default' => '' );
			$uit['backdropFilterSize']   = array( 'type' => 'number', 'default' => 1 );
			$uit['backdropFilterString'] = array( 'type' => 'string', 'default' => '' );
		}

		if ( 'kadence/table' === $bloknaam ) {
			$uit['stickyFirstRow']    = array( 'type' => 'boolean', 'default' => false );
			$uit['stickyFirstColumn'] = array( 'type' => 'boolean', 'default' => false );
		}

		if ( in_array( $bloknaam, self::FILTERBLOKKEN_MET_INJECTIE, true ) ) {
			// Bewust GEEN default op source en post_field: die hangen van het
			// bloktype en van WooCommerce af. Zou hier een verkeerde standaard
			// staan, dan zou normaliseer_attributen een expliciet ingestelde
			// waarde als "gelijk aan de standaard" weglaten — en dat is precies
			// de fout die we hier repareren.
			$uit['source']            = array( 'type' => 'string' );
			$uit['fieldType']         = array( 'type' => 'string', 'default' => 'post_field' );
			$uit['taxonomy']          = array( 'type' => 'string', 'default' => 'category' );
			$uit['post_field']        = array( 'type' => 'string' );
			$uit['include']           = array( 'type' => 'array', 'default' => array() );
			$uit['exclude']           = array( 'type' => 'array', 'default' => array() );
			$uit['label']             = array( 'type' => 'string', 'default' => '' );
			$uit['showLabel']         = array( 'type' => 'boolean', 'default' => true );
			$uit['labelIcon']         = array( 'type' => 'string', 'default' => '' );
			$uit['labelIconPosition'] = array( 'type' => 'string', 'default' => 'before' );
			$uit['showLabelIcon']     = array( 'type' => 'boolean', 'default' => false );
			$uit['slug']              = array( 'type' => 'string', 'default' => '' );
			$uit['color']             = array( 'type' => 'string', 'default' => '' );
			$uit['borderStyle']       = array( 'type' => 'array' );
			$uit['tabletBorderStyle'] = array( 'type' => 'array' );
			$uit['mobileBorderStyle'] = array( 'type' => 'array' );
			$uit['borderRadius']       = array( 'type' => 'array', 'default' => array( '', '', '', '' ) );
			$uit['tabletBorderRadius'] = array( 'type' => 'array', 'default' => array( '', '', '', '' ) );
			$uit['mobileBorderRadius'] = array( 'type' => 'array', 'default' => array( '', '', '', '' ) );
			$uit['borderRadiusUnit']   = array( 'type' => 'string', 'default' => 'px' );
			$uit['background']         = array( 'type' => 'string', 'default' => '' );
			$uit['gradient']           = array( 'type' => 'string', 'default' => '' );
			$uit['backgroundType']     = array( 'type' => 'string', 'default' => 'normal' );
			$uit['typography']         = array( 'type' => 'array' );
		}

		return $uit;
	}

	/**
	 * Attributen op dit blok waarvan de klasse-afleiding niet bekend is.
	 *
	 * @param string $bloknaam De bloknaam.
	 * @param array  $attrs    De attributen van het blok.
	 *
	 * @return array Lege array als opnieuw opbouwen veilig is.
	 */
	public static function ongedekt( $bloknaam, $attrs ) {
		if ( ! isset( self::ONGEDEKTE_MARKUP_ATTRS[ $bloknaam ] ) ) {
			return array();
		}

		$uit = array();

		foreach ( self::ONGEDEKTE_MARKUP_ATTRS[ $bloknaam ] as $attribuut ) {
			if ( ! array_key_exists( $attribuut, $attrs ) ) {
				continue;
			}

			$waarde = $attrs[ $attribuut ];

			// Een lege waarde levert geen klasse op en is dus onschadelijk.
			if ( null === $waarde || '' === $waarde || array() === $waarde || false === $waarde ) {
				continue;
			}

			$uit[] = $attribuut;
		}

		return $uit;
	}
}
