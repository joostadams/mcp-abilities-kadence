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
 *   bouwbaar        false als de plug-in het blok KENT (waarden, afgeleide
 *                   markup, controles) maar het niet mag bouwen. Standaard
 *                   true. Kennen en bouwen zijn twee dingen: een blok waarvan
 *                   je de waardenlijsten weet, weet je nog niet te schrijven.
 *   genegeerd       Attribuut => waarom. Attributen die in het schema staan
 *                   maar die de render van Kadence niet gebruikt. Schrijven
 *                   slaagt, er verandert niets. Geen blokkade, wel een melding.
 *   aantal_kinderen Attribuut dat gelijk moet zijn aan het aantal kindblokken,
 *                   zoals columns op een rij. De generator vult hem in; de
 *                   import toetst hem.
 *   kbversion       De kbVersion die de editor bij dit blok schrijft, als die
 *                   afwijkt van 2. kbVersion kiest de rendertak, dus een
 *                   verkeerde waarde geeft een andere pagina zonder melding.
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
		// De uitlijning van een slide, op de TWEEDE div van de slide en niet
		// op de buitenste. Kadence wisselt de klassen per breakpoint af:
		// desktop-align, desktop-valign, tablet-align, tablet-valign, enz.
		// Afgelezen uit de save() van kadence/slide (Blocks Pro 2.8.19).
		'{SLIDE_UITLIJNING}' => array(
			'soort'   => 'reeks',
			'overal'  => true,
			'reeks'   => array(
				array( 'align', 0, 'kb-slide-align-' ),
				array( 'vAlign', 0, 'kb-slide-valign-' ),
				array( 'align', 1, 'kb-slide-tab-align-' ),
				array( 'vAlign', 1, 'kb-slide-tab-valign-' ),
				array( 'align', 2, 'kb-slide-mobile-align-' ),
				array( 'vAlign', 2, 'kb-slide-mobile-valign-' ),
			),
		),
		// Een heel element dat er alleen staat als een van deze attributen een
		// waarde heeft. Bij de slide is dat de overlay: zet je via een
		// attribuut alleen backgroundOverlay, dan krijgt het blok de kleur maar
		// niet de div waar die kleur op hoort, en zie je niets.
		'{SLIDE_OVERLAY}' => array(
			'soort'      => 'element',
			'overal'     => true,
			'attributen' => array( 'backgroundOverlay', 'overlayGradient' ),
			'klasse'     => 'kb-advanced-slide-overlay',
			'html'       => '<div class="kb-advanced-slide-overlay"></div>',
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
		'kadence/slide' => array(
			// ariaLabel gaat in save() als prop ariaLabel naar het li-element.
			// Hoe dat in de opgeslagen HTML terechtkomt is niet waargenomen.
			'ariaLabel',
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
					// Afgelezen uit dist/blocks-column.js (3.7.8). De werkbalk biedt
					// top, middle, bottom en stretch; het paneel "Vertical
					// Alignment" bij direction vertical biedt daarnaast
					// space-between, space-around en space-evenly, en de render
					// kent ze alle zeven (class-kadence-blocks-column-block.php).
					// Tot 1.21.0 stonden alleen de eerste vier hier, en werd
					// space-between ten onrechte geblokkeerd.
					'verticalAlignment' => array( 'top', 'middle', 'bottom', 'stretch', 'space-between', 'space-around', 'space-evenly' ),
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
				'genegeerd'    => array(
					'className' => 'Geavanceerde tekst zet zijn className niet in de HTML: het attribuut staat in het commentaar, maar de klasse komt niet op de h- of p-tag. Een CSS-regel die erop leunt pakt dus niet. Zet de opmaak in de blokattributen, of hang de klasse aan een omhullende Sectie.',
				),
				'let_op' => 'De tekst staat in de innerHTML, niet in een attribuut: het attribuut content heeft source html. Gebruik set-text.',
			),

			'kadence/advancedbtn' => array(
				'open'  => '<div class="wp-block-kadence-advancedbtn kb-buttons-wrap kb-btns{ID}">',
				'sluit' => '</div>',
				'waardenlijsten' => array(
					// Afgelezen uit class-kadence-blocks-advancedbtn-block.php
					// (3.7.11), de switch per breakpoint. De t- en m-varianten
					// zijn tablet en mobiel en kennen dezelfde waarden.
					'hAlign'  => array( 'left', 'center', 'right', 'space-between' ),
					'thAlign' => array( 'left', 'center', 'right', 'space-between' ),
					'mhAlign' => array( 'left', 'center', 'right', 'space-between' ),
					'vAlign'  => array( 'top', 'center', 'bottom' ),
					'tvAlign' => array( 'top', 'center', 'bottom' ),
					'mvAlign' => array( 'top', 'center', 'bottom' ),
				),
			),

			'kadence/singlebtn' => array(
				'zelfsluitend'   => true,
				'waardenlijsten' => array(
					// Afgelezen uit dist/blocks-singlebtn.js en de render
					// (3.7.11). inherit en inherit-secondary nemen de knopstijl
					// van het THEMA over; fill is Kadence' eigen gevulde knop.
					'inheritStyles' => array( 'fill', 'outline', 'inherit', 'inherit-secondary' ),
				),
				'let_op' => 'Het icoon heeft geen eigen achtergrond: iconColor en iconColorHover kleuren alleen het pictogram. Kadence geeft de knop overflow: hidden en een ::before-laag die bij backgroundHoverType gradient de hoverkleur draagt; in de editor krijgt die laag bij kb-btn-global-fill de hoverkleur van de themaknop. De editor bouwt de knop met andere klassen (.kt-button, .kt-btn-svg-icon, .kt-button-text) dan de voorkant (.kb-button, .kb-svg-icon-wrap, .kt-btn-inner-text).',
			),

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

			// Post Grid (Blocks Pro). Zelfsluitend: de hele kaart wordt bij het
			// tonen door PHP opgebouwd, er staat niets tussen de commentaren.
			'kadence/postgrid' => array(
				'zelfsluitend'   => true,
				'waardenlijsten' => array(
					// Afgelezen uit dist/blocks-postgrid.js (Pro 2.8.19): de
					// editor biedt grid, masonry en carousel. fluidcarousel komt
					// alleen nog in de render voor, voor oude inhoud.
					'layout' => array( 'grid', 'masonry', 'carousel', 'fluidcarousel' ),
				),
				'let_op' => 'Een carousel loopt altijd rond: het script valt terug op loop als data-slider-loop-type ontbreekt, en de Post Grid zet dat nooit. Wil je dat niet, gebruik dan een Advanced Slider met loopType none. De kaart is niet vrij op te bouwen; afwijkende kaarten lopen via de hooks kadence_blocks_post_loop_*.',
			),

			// Advanced Slider (Blocks Pro). Draagt geen eigen markup: de slides
			// staan rechtstreeks tussen de twee commentaren, net als bij een
			// rij. Afgelezen van een slider die de editor schreef, 22-09-2026
			// (Blocks Pro 2.8.19).
			'kadence/slider' => array(
				'open'            => '',
				'sluit'           => '',
				'kbversion'       => 3,
				'aantal_kinderen' => 'slideCount',
				'kinderen'        => array( 'kadence/slide' ),
				'waardenlijsten'  => array(
					// Alle afgelezen uit dist/blocks-slider.js.
					'sliderType'    => array( 'slider', 'carousel' ),
					'loopType'      => array( 'loop', 'rewind', 'none' ),
					'arrowPosition' => array( 'center', 'top-left', 'top-right', 'bottom-left', 'bottom-right', 'outside-top', 'outside-top-left', 'outside-top-right', 'outside-bottom', 'outside-bottom-left', 'outside-bottom-right' ),
					'arrowStyle'    => array( 'whiteondark', 'blackonlight', 'outlineblack', 'outlinewhite', 'custom', 'none' ),
					'dotStyle'      => array( 'dark', 'light', 'outlinedark', 'outlinelight', 'none' ),
					'heightType'    => array( 'ratio', 'fixed', 'inherit', '' ),
				),
				'genegeerd'       => array(
					'slidesScroll' => 'Het attribuut staat in het schema, maar de render schrijft altijd data-slider-scroll="1" (Blocks Pro 2.8.19) en de editor heeft er geen keuzeveld voor. Het script schuift wél per pagina bij elk getal behalve 1 — per pagina schuiven vraagt dus een render_block-filter op kadence/slider dat dat data-attribuut zet.',
				),
				'let_op'          => 'loopType none = niet rondlopen, met een uitgeschakelde vorige-pijl op de eerste pagina. De padding van de slider staat op .kb-advanced-slide-inner-wrap (standaard 20/48); een padding van 0 geldt op de voorkant, maar de editor toont dan toch de standaard. slideCount moet gelijk zijn aan het aantal slides. In een Kadence-tab start Kadence de slider pas als de tab zichtbaar is, en logt dan eenmalig "[splide] Already mounted!" — onschuldig.',
			),

			// Eén slide. Afgelezen uit de save() van kadence/slide (Blocks Pro
			// 2.8.19) en gecontroleerd tegen slides die de editor schreef. De
			// uitlijning zit op de tweede div, de overlay is een eigen element
			// dat alleen bestaat als er een overlaykleur of -verloop is.
			'kadence/slide' => array(
				'open'         => '<li class="wp-block-kadence-slide kb-advanced-slide-item kb-slide-{ID}{KLASSE}"><div class="kb-advanced-slide"><div class="kb-advanced-slide-inner-wrap{SLIDE_UITLIJNING}">{SLIDE_OVERLAY}<div class="kb-advanced-slide-inner">',
				'sluit'        => '</div></div></div></li>',
				'klassen'      => array( '{KLASSE}', '{SLIDE_UITLIJNING}', '{SLIDE_OVERLAY}' ),
				'markup_attrs' => array( 'align', 'vAlign', 'backgroundOverlay', 'overlayGradient', 'className', 'ariaLabel' ),
				'let_op'       => 'Hoort in een kadence/slider. De achtergrond (backgroundImg) staat op .kb-advanced-slide-inner-wrap; de overlay is absoluut met inset 0, maar de wrap is niet gepositioneerd, dus de overlay rekent vanaf de li en valt over een rand op de wrap heen. align en de overlay zitten in de opgeslagen markup: wijzig ze via replace-block of de editor, niet alleen als attribuut.',
			),

			// Tabs worden bewust NIET gebouwd. De wrapper draagt een reeks
			// klassen die uit attributen volgen (kt-tabs-id, kt-tabs-has-N-tabs,
			// kt-active-tab-N, layout per breakpoint), en de titellijst wordt
			// uit het attribuut titles opgebouwd, met ankers die moeten
			// meelopen met de kindblokken. Dat is niet waargenomen genoeg om te
			// genereren. Wel bekend, zodat de import het aantal tabs kan toetsen.
			// Bouw tabs in de editor of kopieer ze met duplicate-blocks.
			'kadence/tabs' => array(
				'bouwbaar'        => false,
				'aantal_kinderen' => 'tabCount',
				'kinderen'        => array( 'kadence/tab' ),
				'let_op'          => 'Niet te bouwen met generate-section: de wrapper en de titellijst worden uit attributen afgeleid. Kopieer tabs met duplicate-blocks, bouw ze in de editor, of voer editor-markup in met prepare-import. gutter zet geen ruimte tussen de titels; innerPadding is de ruimte tussen de titelbalk en de inhoud.',
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
				'bouwbaar'        => true,
				'genegeerd'       => array(),
				'aantal_kinderen' => '',
				'kinderen'        => array(),
				'kbversion'       => 0,
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
		$profiel = self::van( $bloknaam );

		return null !== $profiel && false !== $profiel['bouwbaar'];
	}

	/**
	 * Heeft de plug-in een profiel van dit bloktype, bouwbaar of niet?
	 *
	 * bekend() zegt of de generator het blok mag bouwen. Dit zegt alleen of er
	 * iets over bekend is — waardenlijsten, afgeleide markup, controles.
	 *
	 * @param string $bloknaam De bloknaam.
	 *
	 * @return bool
	 */
	public static function heeft_profiel( $bloknaam ) {
		return null !== self::van( $bloknaam );
	}

	/**
	 * De bloknamen die de generator kan bouwen.
	 *
	 * @return array
	 */
	public static function bloknamen() {
		$uit = array();

		foreach ( array_keys( self::alle() ) as $naam ) {
			if ( self::bekend( $naam ) ) {
				$uit[] = $naam;
			}
		}

		return $uit;
	}

	/**
	 * Waarom de render dit attribuut negeert, of leeg.
	 *
	 * @param string $bloknaam  De bloknaam.
	 * @param string $attribuut Het attribuut.
	 *
	 * @return string
	 */
	public static function genegeerd( $bloknaam, $attribuut ) {
		$profiel = self::van( $bloknaam );

		if ( null === $profiel || ! isset( $profiel['genegeerd'][ $attribuut ] ) ) {
			return '';
		}

		return (string) $profiel['genegeerd'][ $attribuut ];
	}

	/**
	 * De waarden die Kadence voor dit attribuut kent, of null.
	 *
	 * Alleen de platte lijsten; een lijst die van een ander attribuut afhangt
	 * (colLayout per aantal kolommen) komt als geheel terug.
	 *
	 * @param string $bloknaam  De bloknaam.
	 * @param string $attribuut Het attribuut.
	 *
	 * @return array|null
	 */
	public static function waardenlijst( $bloknaam, $attribuut ) {
		$profiel = self::van( $bloknaam );

		if ( null === $profiel || ! isset( $profiel['waardenlijsten'][ $attribuut ] ) ) {
			return null;
		}

		return $profiel['waardenlijsten'][ $attribuut ];
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

			if ( 'reeks' === $regel['soort'] ) {
				foreach ( $regel['reeks'] as $stap ) {
					list( $attribuut, $index, $voorvoegsel ) = $stap;

					if ( isset( $attrs[ $attribuut ] ) && is_array( $attrs[ $attribuut ] ) && isset( $attrs[ $attribuut ][ $index ] ) && '' !== (string) $attrs[ $attribuut ][ $index ] ) {
						$klassen[] = $voorvoegsel . $attrs[ $attribuut ][ $index ];
					}
				}
			}

			if ( 'element' === $regel['soort'] ) {
				$aanwezig = false;

				foreach ( $regel['attributen'] as $attribuut ) {
					if ( ! empty( $attrs[ $attribuut ] ) ) {
						$aanwezig = true;
					}
				}

				// Een element is geen klasse maar HTML; het komt zonder spatie
				// ervoor in het sjabloon.
				$uit[ $plaatshouder ] = $aanwezig ? $regel['html'] : '';
				continue;
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
