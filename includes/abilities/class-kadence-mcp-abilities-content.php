<?php
/**
 * Abilities die de inhoud van een post uitlezen.
 *
 * @package Kadence_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * kadence/inspect-post en kadence/diff-blocks.
 */
class Kadence_MCP_Abilities_Content {

	/**
	 * Attributen die standaard meegaan.
	 *
	 * uniqueID is de sleutel waarmee Kadence zijn CSS aan een blok koppelt;
	 * zonder die waarde kun je twee identieke blokken niet uit elkaar houden.
	 *
	 * id betekent per blok iets anders en dat is bewust: bij kadence/header,
	 * kadence/navigation en kadence/advanced-form is het het post-ID van de CPT
	 * waar de inhoud staat — de enige route van een pagina naar dat object.
	 * Bij core/image is het een attachment-ID.
	 *
	 * metadata staat er sinds 11-09-2026 standaard bij. Daar zit namelijk twee
	 * dingen in die nergens anders staan: de naam die de lijstweergave toont,
	 * en blockVisibility. Dat laatste is de verbergoptie van WordPress zelf
	 * (wp-includes/js/dist/block-editor.js) en dus niet iets van Kadence — hij
	 * geldt voor elk blok. Een verborgen blok is aan zijn attributen niet te
	 * herkennen, dus zonder metadata kijk je eroverheen.
	 */
	const STANDAARD_ATTRIBUTEN = array( 'uniqueID', 'id', 'className', 'align', 'metadata' );

	/**
	 * Hoeveel blokken er maximaal teruggaan.
	 */
	const MAX_BLOKKEN = 400;

	/**
	 * De ruwe definities.
	 *
	 * @return array[]
	 */
	public static function get_definitions() {
		return array(
			array(
				'name' => 'kadence/inspect-post',
				'args' => array(
					'label'       => __( 'De blokopbouw van een post bekijken', 'mcp-abilities-kadence' ),
					'summary'     => __( 'De blokkenboom van een pagina, header of element — namen, nesting, uniqueID en metadata.', 'mcp-abilities-kadence' ),
					'description' => __( 'Leest de blokstructuur van een post zonder hem te wijzigen. Werkt op gewone pagina\'s en berichten én op de Kadence-posttypes. Geeft per blok de naam, de nestingdiepte, het aantal kindblokken en een beperkte set attributen — standaard uniqueID, id, className, align en metadata. In metadata zit de naam uit de lijstweergave en blockVisibility; staat die laatste op false, dan is het blok verborgen met de native verbergoptie van WordPress en zie je dat nergens anders aan. Grote of geneste attribuutwaarden worden samengevat; wil je er een ongekort zien, noem hem dan in full_attributes. Vraag extra attributen gericht op — een volledige dump van een opgemaakte pagina is duizenden regels. De zichtbare tekst krijg je alleen met include_text: blokken als kadence/listitem en kadence/advancedheading bewaren hun tekst in de markup, niet in een attribuut.', 'mcp-abilities-kadence' ),
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'        => 'integer',
								'minimum'     => 1,
								'description' => __( 'Het ID van de post. Gebruik find-post als je alleen de titel of slug weet.', 'mcp-abilities-kadence' ),
							),
							'attributes' => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => __( 'Welke attributen mee moeten. Weglaten geeft de standaardset; een lege array laat alle attributen weg.', 'mcp-abilities-kadence' ),
							),
							'full_attributes' => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => __( 'Deze attributen komen ongekort terug in plaats van samengevat. Gebruik dit voor waarden waarvan je de inhoud nodig hebt, zoals titles op kadence/tabs.', 'mcp-abilities-kadence' ),
							),
							'from_unique_id' => array(
								'type'        => 'string',
								'description' => __( 'Begin de boom bij dit blok in plaats van bovenaan de post. Scheelt op grote pagina\'s veel ruis: het blok zelf komt op diepte 0 terug, met alles eronder. De uniqueID vind je met een eerdere aanroep.', 'mcp-abilities-kadence' ),
							),
							'include_text' => array(
								'type'        => 'boolean',
								'default'     => false,
								'description' => __( 'Geef per blok ook de zichtbare tekst. Nodig voor blokken die hun inhoud in de markup bewaren in plaats van in attributen — kadence/listitem, kadence/advancedheading en kadence/singlebtn doen dat. Containers leveren terecht niets op. Tekst boven 300 tekens wordt afgekapt.', 'mcp-abilities-kadence' ),
							),
							'kadence_only' => array(
								'type'        => 'boolean',
								'default'     => false,
								'description' => __( 'Toon alleen kadence/*-blokken. De nestingdiepte blijft die van de volledige boom.', 'mcp-abilities-kadence' ),
							),
							'max_blocks' => array(
								'type'    => 'integer',
								'minimum' => 1,
								'maximum' => self::MAX_BLOKKEN,
								'default' => 200,
							),
						),
						'required'             => array( 'post_id' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post'      => array( 'type' => 'object' ),
							'blocks'    => array( 'type' => 'array' ),
							'counts'    => array( 'type' => 'object' ),
							'truncated' => array( 'type' => 'boolean' ),
							'status'    => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'inspect_post' ),
				),
			),
			array(
				'name' => 'kadence/get-raw-markup',
				'args' => array(
					'label'       => __( 'De opgeslagen markup van een post of blok', 'mcp-abilities-kadence' ),
					'summary'     => __( 'De letterlijke blokmarkup, zoals die in de database staat.', 'mcp-abilities-kadence' ),
					'description' => __( 'Geeft de opgeslagen post_content terug, of die van één blok. Nodig omdat de andere tools alleen geparste ATTRIBUTEN tonen, en de vraag of een attribuut veilig te wijzigen is juist afhangt van wat er in de MARKUP staat. Een blok dat zelfsluitend is opgeslagen (eindigt op /-->) heeft geen eigen markup en is vrij te wijzigen; een blok met een wrapper bakt zijn uniqueID meestal in een klassenaam en is dat niet. Gebruik dit voordat je een schrijfactie voorstelt, en gebruik max_chars om niet een hele opgemaakte pagina op te halen.', 'mcp-abilities-kadence' ),
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							'unique_id' => array(
								'type'        => 'string',
								'description' => __( 'Alleen de markup van dit blok in plaats van de hele post.', 'mcp-abilities-kadence' ),
							),
							'max_chars' => array(
								'type'    => 'integer',
								'minimum' => 200,
								'maximum' => 20000,
								'default' => 6000,
							),
						),
						'required'             => array( 'post_id' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post'   => array( 'type' => 'object' ),
							'markup' => array( 'type' => 'string' ),
							'status' => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'get_raw_markup' ),
				),
			),
			array(
				'name' => 'kadence/duplicate-blocks',
				'args' => array(
					'label'       => __( 'Een blok kopiëren naar een andere post', 'mcp-abilities-kadence' ),
					'summary'     => __( 'Neemt een bestaande sectie over met verse uniqueIDs. Droogloop tenzij je hem expliciet aanzet.', 'mcp-abilities-kadence' ),
					'description' => __( 'Kopieert een bestaand blok, met alles eronder, naar een andere post en geeft elke kopie een nieuwe uniqueID die geprefixt is met de doelpost. Dit is de veiligste manier om iets te bouwen: de markup komt uit de editor en is dus geldig, en er wordt niets verzonnen. Gebruik dit voor maak-deze-layout-na. De uniqueID staat op twee plekken — in het attribuut en verweven in klassen als kadence-column..., kb-btns... en data-kb-block — en beide worden samen vervangen, dus er kan geen mismatch ontstaan. Standaard is dit een DROOGLOOP: hij toont de markup die hij zou invoegen, de kaart van oude naar nieuwe uniqueID, en een token. Schrijven doe je met dry_run false plus datzelfde token EN diezelfde id_map — die kaart moet mee, anders zouden er verse ID\'s gemaakt worden en zou je iets anders schrijven dan je hebt gezien. Voor het schrijven wordt gecontroleerd dat de kaart precies de te kopiëren blokken dekt, geen dubbele waarden bevat en niet botst met ID\'s die al in de doelpost staan. Verder gelden dezelfde grendels als bij set-attributes. Na het schrijven wordt teruggelezen en gecontroleerd of elk gekopieerd blok er ook echt staat.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'idempotent'  => false,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'source_post_id'   => array( 'type' => 'integer', 'minimum' => 1 ),
							'source_unique_id' => array( 'type' => 'string', 'description' => __( 'Het blok dat je wil kopiëren, inclusief alles eronder.', 'mcp-abilities-kadence' ) ),
							'target_post_id'   => array( 'type' => 'integer', 'minimum' => 1 ),
							'position' => array( 'type' => 'string', 'default' => 'append', 'enum' => array( 'append', 'prepend', 'inside' ), 'description' => __( 'append of prepend zet de kopie op het hoogste niveau van de doelpost. inside zet hem ALS KIND van target_unique_id — zo krijg je een gekopieerde sectie in een bestaande kolom.', 'mcp-abilities-kadence' ) ),
						'target_unique_id' => array( 'type' => 'string', 'description' => __( 'Alleen bij position inside: de container in de doelpost waar de kopie in moet.', 'mcp-abilities-kadence' ) ),
							'dry_run' => array(
								'type'        => 'boolean',
								'default'     => true,
								'description' => __( 'Standaard true: tonen wat er zou gebeuren. Zet op false om echt te schrijven; dan zijn ook het token en de id_map uit de droogloop verplicht.', 'mcp-abilities-kadence' ),
							),
							'id_map' => array(
								'type'                 => 'object',
								'description'          => __( 'De kaart van oude naar nieuwe uniqueID die de droogloop teruggaf, letterlijk. Verplicht bij dry_run false: zonder die kaart zouden er nieuwe ID\'s gemaakt worden en zou je iets anders schrijven dan je hebt goedgekeurd.', 'mcp-abilities-kadence' ),
								'additionalProperties' => true,
							),
							'token' => array( 'type' => 'string' ),
						),
						'required'             => array( 'source_post_id', 'source_unique_id', 'target_post_id' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'source'   => array( 'type' => 'object' ),
							'target'   => array( 'type' => 'object' ),
							'id_map'   => array( 'type' => 'object' ),
							'markup'   => array( 'type' => 'string' ),
							'token'    => array( 'type' => 'string' ),
							'verified' => array( 'type' => 'array' ),
							'missing'  => array( 'type' => 'array' ),
							'status'   => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'duplicate_blocks' ),
				),
			),
			array(
				'name' => 'kadence/set-attributes',
				'args' => array(
					'label'       => __( 'Attributen schrijven naar een blok', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Wijzigt attributen op ÉÉN bestaand blok — voor meerdere blokken tegelijk: style-blocks.', 'mcp-abilities-kadence' ),
					'description' => __( 'Schrijft attributen naar één bestaand blok. Maak je meer dan één blok tegelijk op, gebruik dan style-blocks: dat scheelt aanroepen en levert één revisie op in plaats van een reeks, en elk tussenliggend token zou hier toch vervallen zodra de vorige stap de post heeft gewijzigd. Vereist de capability kadence_mcp_write, bewerkrecht op de post volgens WordPress, en een geldig token uit validate-write — zonder dat token weigert hij, en het token is gebonden aan deze post, dit blok, deze exacte attributen en de wijzigingsdatum van de post. Verander je iets aan je voorstel, dan moet je opnieuw toetsen. De volgorde is dus altijd: validate-write, preview-write, dan pas dit. Attributen die gelijk zijn aan de standaardwaarde worden weggelaten en de rest wordt in de volgorde van het blokschema gezet, precies zoals de editor het zou doen. Na het opslaan wordt de post teruggelezen en vergeleken met wat er bedoeld was; wijkt het af, dan staat dat in mismatch. Er wordt een revisie gemaakt, dus terugdraaien kan.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id'   => array( 'type' => 'integer', 'minimum' => 1 ),
							'unique_id' => array( 'type' => 'string' ),
							'attributes' => array(
								'type'                 => 'object',
								'description'          => __( 'De te schrijven attributen. Moeten exact overeenkomen met wat validate-write heeft getoetst.', 'mcp-abilities-kadence' ),
								'additionalProperties' => true,
							),
							'token' => array(
								'type'        => 'string',
								'description' => __( 'Het token dat validate-write teruggaf bij het oordeel veilig.', 'mcp-abilities-kadence' ),
							),
						),
						'required'             => array( 'post_id', 'unique_id', 'attributes', 'token' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'block'    => array( 'type' => 'object' ),
							'written'  => array( 'type' => 'object' ),
							'notes'    => array( 'type' => 'array' ),
							'mismatch' => array( 'type' => 'array' ),
							'revision' => array( 'type' => 'string' ),
							'status'   => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'set_attributes' ),
				),
			),
			array(
				'name' => 'kadence/style-blocks',
				'args' => array(
					'label'       => __( 'Meerdere blokken in één keer opmaken', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Zet attributen op een hele set blokken, in één opslag.', 'mcp-abilities-kadence' ),
					'description' => __( 'Schrijft attributen naar meerdere blokken tegelijk. Gebruik dit zodra je meer dan één blok opmaakt — een sectie opmaken raakt al snel de rij, de kolom, de tekstblokken en de knoppen, en dat met set-attributes doen is per blok twee aanroepen én een token dat bij elke tussenstap vervalt omdat de post intussen is gewijzigd. Werkt in twee stappen: roep hem eerst zonder token aan en elk blok wordt afzonderlijk getoetst met exact dezelfde controles als validate-write, waarna je per blok het oordeel terugkrijgt; roep hem daarna opnieuw aan met het token en alles wordt in één opslag geschreven, dus één revisie. Alles of niets: is één blok blokkeer of riskant, dan komt er geen token en wordt er niets geschreven — half doorvoeren laat de pagina achter in een staat die niemand heeft bedoeld. Een blok dat twee keer in de lijst staat wordt geweigerd. Attributen die gelijk zijn aan de standaardwaarde vallen weg — dat is wat de editor ook doet — en komen terug in defaults, niet in mismatch. Vereist de capability kadence_mcp_write en bewerkrecht op de post.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
							'blocks'  => array(
								'type'        => 'array',
								'description' => __( 'De blokken met hun attributen. Elk item heeft unique_id en attributes.', 'mcp-abilities-kadence' ),
								'items'       => array(
									'type'       => 'object',
									'properties' => array(
										'unique_id'  => array( 'type' => 'string' ),
										'attributes' => array( 'type' => 'object', 'additionalProperties' => true ),
									),
									'required'   => array( 'unique_id', 'attributes' ),
								),
							),
							'token' => array(
								'type'        => 'string',
								'description' => __( 'Laat leeg voor een toetsing zonder op te slaan. Vul het token in dat je dan terugkrijgt om te schrijven.', 'mcp-abilities-kadence' ),
							),
							'expect_modified' => array(
								'type'        => 'string',
								'description' => __( 'Alternatief voor token, en scheelt een aanroep. Geef de post_modified_gmt die je van deze post kent — uit inspect-post, get-raw-markup of het veld modified van een eerdere toetsing hier. Klopt hij nog, dan wordt er meteen getoetst én geschreven; is de post intussen gewijzigd, dan wordt er niets geschreven en krijg je beide tijdstippen te zien. Dezelfde bescherming tegen schrijven op een verouderde versie als het token, maar zonder de tussenstap. Geef er hooguit een van de twee mee.', 'mcp-abilities-kadence' ),
							),
						),
						'required'             => array( 'post_id', 'blocks' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post'     => array( 'type' => 'object' ),
							'results'  => array( 'type' => 'array' ),
							'verdict'  => array( 'type' => 'string' ),
							'written'  => array( 'type' => 'boolean' ),
							'token'    => array( 'type' => 'string' ),
							'modified'    => array( 'type' => 'string' ),
							'mismatch'    => array( 'type' => 'array' ),
							'class_drift' => array( 'type' => 'array' ),
							'dropped'  => array( 'type' => 'object' ),
							'defaults' => array( 'type' => 'object' ),
							'revision' => array( 'type' => 'string' ),
							'status'   => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'style_blocks' ),
				),
			),
			array(
				'name' => 'kadence/set-text',
				'args' => array(
					'label'       => __( 'De tekst van een blok schrijven', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Vervangt de tekst binnen één tekstblok.', 'mcp-abilities-kadence' ),
					'description' => __( 'Vervangt de tekst van één blok. Nodig omdat de tekst van een kop of alinea niet in de attributen staat maar in de innerHTML: set-attributes weigert die terecht, want daar worden ook de klassen en data-attributen uit opgebouwd. Deze ability raakt uitsluitend het deel tussen de buitenste tag aan en laat die tag letterlijk staan, zodat de blokvalidatie van Gutenberg blijft kloppen. Werkt in twee stappen en niet in drie: roep hem eerst zonder token aan en je krijgt de oude tekst, de nieuwe en een token terug zonder dat er iets is opgeslagen; roep hem daarna opnieuw aan met dat token en hij schrijft. Een aparte validate-stap zou hier niets toevoegen, want er is geen schema om een tekst tegen te toetsen. Weigert bij een blok met kindblokken, bij een innerHTML die niet precies één omhullend element is, en bij een lege tekst. HTML in de tekst wordt door wp_kses teruggebracht tot opmaaktags als strong, em, a, br, span en mark; wat eruit gaat staat in notes. Vereist de capability kadence_mcp_write. Er wordt een revisie gemaakt.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id'   => array( 'type' => 'integer', 'minimum' => 1 ),
							'unique_id' => array(
								'type'        => 'string',
								'description' => __( 'Het blok, op uniqueID. Kadence-blokken hebben er een; core-blokken zoals core/paragraph en core/list-item niet. Gebruik daar match_text.', 'mcp-abilities-kadence' ),
							),
							'match_text' => array(
								'type'        => 'string',
								'description' => __( 'Alternatief adres voor blokken zonder uniqueID: de HUIDIGE tekst van het blok, letterlijk. Er moet precies een blok zijn dat hem draagt; bij nul of meer dan een treffer wordt er niets geschreven en krijg je te horen hoeveel het er waren. Voorloop- en volgspaties doen niet mee, de rest wel.', 'mcp-abilities-kadence' ),
							),
							'text'      => array(
								'type'        => 'string',
								'description' => __( 'De nieuwe tekst. Opmaak mag als HTML, maar alleen de toegestane tags overleven.', 'mcp-abilities-kadence' ),
							),
							'token' => array(
								'type'        => 'string',
								'description' => __( 'Laat leeg voor een voorstel zonder op te slaan. Vul het token in dat je dan terugkrijgt om echt te schrijven.', 'mcp-abilities-kadence' ),
							),
							'expect_modified' => array(
								'type'        => 'string',
								'description' => __( 'Alternatief voor token, en scheelt een aanroep. Geef de post_modified_gmt die je van deze post kent; klopt hij nog, dan wordt er meteen geschreven, en anders niets. Geef er hooguit een van de twee mee.', 'mcp-abilities-kadence' ),
							),
						),
						'required'             => array( 'post_id', 'text' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'block'   => array( 'type' => 'object' ),
							'before'  => array( 'type' => 'string' ),
							'after'   => array( 'type' => 'string' ),
							'markup'  => array( 'type' => 'string' ),
							'notes'   => array( 'type' => 'array' ),
							'written' => array( 'type' => 'boolean' ),
							'token'   => array( 'type' => 'string' ),
							'modified' => array( 'type' => 'string' ),
							'status'  => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'set_text' ),
				),
			),
			array(
				'name' => 'kadence/preview-write',
				'args' => array(
					'label'       => __( 'Tonen wat een wijziging zou worden', 'mcp-abilities-kadence' ),
					'summary'     => __( 'De markup voor en na, zonder iets op te slaan.', 'mcp-abilities-kadence' ),
					'description' => __( 'Laat zien hoe de blokmarkup eruit zou zien na een voorgenomen attribuutwijziging, zonder iets te schrijven. Gebruik dit nadat validate-write groen licht heeft gegeven en voordat je de wijziging aanraadt, zodat de gebruiker er een keer naar kijkt in plaats van hem achteraf te moeten repareren. Geeft per attribuut de oude en de nieuwe waarde, plus de openingscomment van het blok voor en na — dat is de regel waar de attributen in staan en dus de enige die verandert. Zet full_markup aan als je de hele boom inclusief kindblokken wil zien. Let bij het lezen vooral op de innerHTML: verwijst die nog naar een oude waarde, dan is de wijziging niet compleet en hoort validate-write dat als riskant te hebben gemeld.', 'mcp-abilities-kadence' ),
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id'   => array( 'type' => 'integer', 'minimum' => 1 ),
							'unique_id' => array( 'type' => 'string' ),
							'attributes' => array(
								'type'                 => 'object',
								'description'          => __( 'De voorgenomen wijzigingen als attribuutnaam naar nieuwe waarde.', 'mcp-abilities-kadence' ),
								'additionalProperties' => true,
							),
							'full_markup' => array(
								'type'        => 'boolean',
								'default'     => false,
								'description' => __( 'Geef de volledige markup inclusief alle kindblokken terug in plaats van alleen de openingscomment van het gewijzigde blok. Zelden nodig: een attribuutwijziging raakt alleen die ene regel, en de rest is duizenden tekens die niemand naleest.', 'mcp-abilities-kadence' ),
							),
						),
						'required'             => array( 'post_id', 'unique_id', 'attributes' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'block'     => array( 'type' => 'object' ),
							'changes'   => array( 'type' => 'array' ),
							'before'    => array( 'type' => 'string' ),
							'after'     => array( 'type' => 'string' ),
							'identical' => array( 'type' => 'boolean' ),
							'status'    => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'preview_write' ),
				),
			),
			array(
				'name' => 'kadence/validate-write',
				'args' => array(
					'label'       => __( 'Een voorgenomen wijziging toetsen', 'mcp-abilities-kadence' ),
					'summary'     => __( 'Controleert vóóraf of een attribuutwijziging veilig is. Schrijft zelf niets.', 'mcp-abilities-kadence' ),
					'description' => __( 'Toetst een VOORGENOMEN attribuutwijziging op één blok, zonder iets te wijzigen. Roep dit altijd aan voordat je een wijziging voorstelt aan de gebruiker. Controleert per attribuut: bestaat het op dit blok, past de waarde bij het gedeclareerde type en de toegestane waarden, wordt het attribuut uit de markup geparsed (dan is schrijven een stille no-op), en staat de huidige waarde letterlijk in de opgeslagen markup (dan hoort de markup mee te veranderen en is alleen het attribuut wijzigen een stille breuk). Kent daarnaast drie attributen met een eigen faalpad: uniqueID, kbVersion en columns. Bij uniqueID wordt ook op botsing gecontroleerd — Kadence doet dat zelf alleen in de editor en alleen binnen de post die openstaat. Bij het oordeel veilig komt er een token terug dat aan deze post, dit blok, deze exacte attributen en de huidige wijzigingsdatum gebonden is; een toekomstige schrijfactie eist dat token, zodat toetsen niet over te slaan is en je niet iets anders kunt schrijven dan wat is getoetst.', 'mcp-abilities-kadence' ),
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							'unique_id' => array(
								'type'        => 'string',
								'description' => __( 'Het blok waarop je wil schrijven.', 'mcp-abilities-kadence' ),
							),
							'attributes' => array(
								'type'                 => 'object',
								'description'          => __( 'De voorgenomen wijzigingen als attribuutnaam naar nieuwe waarde.', 'mcp-abilities-kadence' ),
								'additionalProperties' => true,
							),
						),
						'required'             => array( 'post_id', 'unique_id', 'attributes' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'block'      => array( 'type' => 'object' ),
							'checks'     => array( 'type' => 'array' ),
							'verdict'    => array( 'type' => 'string' ),
							'status'     => array( 'type' => 'string' ),
							'token'      => array( 'type' => 'string' ),
							'token_note' => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'validate_write' ),
				),
			),
			array(
				'name' => 'kadence/diff-blocks',
				'args' => array(
					'label'       => __( 'Twee blokken vergelijken', 'mcp-abilities-kadence' ),
					'summary'     => __( 'Geeft alleen de attributen waarin twee blokken van elkaar verschillen.', 'mcp-abilities-kadence' ),
					'description' => __( 'Vergelijkt twee blokken op hun uniqueID en geeft uitsluitend de verschillen terug, per groep gesorteerd. Gebruik dit altijd wanneer de vraag is waarom twee dingen er anders uitzien — bijvoorbeeld twee mega-menus of twee secties. Zelf een selectie attributen ophalen en die vergelijken is onbetrouwbaar: je mist wat je niet hebt opgevraagd. De twee blokken mogen in dezelfde post staan of in verschillende; laat post_id_b weg als het dezelfde post is. Een attribuut dat maar bij één van beide is ingesteld verschijnt met null aan de andere kant — dat betekent "niet gezet", dus de standaardwaarde van het blok geldt daar.', 'mcp-abilities-kadence' ),
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id_a' => array(
								'type'        => 'integer',
								'minimum'     => 1,
								'description' => __( 'Post waarin blok A staat.', 'mcp-abilities-kadence' ),
							),
							'unique_id_a' => array(
								'type'        => 'string',
								'description' => __( 'De uniqueID van blok A.', 'mcp-abilities-kadence' ),
							),
							'post_id_b' => array(
								'type'        => 'integer',
								'minimum'     => 1,
								'description' => __( 'Post waarin blok B staat. Weglaten als dat dezelfde post is als A.', 'mcp-abilities-kadence' ),
							),
							'unique_id_b' => array(
								'type'        => 'string',
								'description' => __( 'De uniqueID van blok B.', 'mcp-abilities-kadence' ),
							),
							'include_children' => array(
								'type'        => 'boolean',
								'default'     => false,
								'description' => __( 'Vergelijk ook de structuur eronder: bloknamen en aantallen per niveau.', 'mcp-abilities-kadence' ),
							),
						),
						'required'             => array( 'post_id_a', 'unique_id_a', 'unique_id_b' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'a'          => array( 'type' => 'object' ),
							'b'          => array( 'type' => 'object' ),
							'differences' => array( 'type' => 'array' ),
							'identical'  => array( 'type' => 'integer' ),
							'structure'  => array( 'type' => 'object' ),
							'status'     => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'diff_blocks' ),
				),
			),
		);
	}

	/**
	 * Haal een post op en controleer het leesrecht.
	 *
	 * @param int $post_id Het post-ID.
	 *
	 * @return WP_Post|WP_Error
	 */
	private static function haal_post( $post_id ) {
		$post_id = (int) $post_id;

		if ( $post_id <= 0 ) {
			return new WP_Error(
				'kadence_mcp_missing_post_id',
				__( 'Geef een post_id op.', 'mcp-abilities-kadence' )
			);
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return new WP_Error(
				'kadence_mcp_post_not_found',
				sprintf(
					/* translators: %d: post ID. */
					__( 'Er bestaat geen post met ID %d. Gebruik find-post om het juiste ID te vinden.', 'mcp-abilities-kadence' ),
					$post_id
				)
			);
		}

		// De capability kadence_mcp_view zegt dat dit account de tool mag
		// gebruiken, niet dat het élke post mag lezen. Concepten, privéposts en
		// de niet-publieke Kadence-posttypes hebben hun eigen rechten, en die
		// gelden hier onverkort.
		/**
		 * Filtert of deze post gelezen mag worden.
		 *
		 * read_post is de meta cap van WordPress, maar een plugin die inhoud
		 * afschermt werkt vaak via queryfilters en niet via die cap. Wie dat
		 * wél als grens wil, hangt hier een eigen controle in — bijvoorbeeld
		 * een die Kadence_MCP_Inventory::is_query_zichtbaar() eist.
		 *
		 * @param bool $toegestaan Of lezen is toegestaan.
		 * @param int  $post_id    Het post-ID.
		 */
		$toegestaan = apply_filters( 'kadence_mcp_can_read_post', current_user_can( 'read_post', $post_id ), $post_id );

		if ( ! $toegestaan ) {
			return new WP_Error(
				'kadence_mcp_post_forbidden',
				sprintf(
					/* translators: %1$d: post ID, %2$s: post status. */
					__( 'Geen leesrecht op post %1$d (status: %2$s). Voor concepten en niet-gepubliceerde Kadence-objecten heeft de rol edit_theme_options of edit_posts nodig; dat is een bewuste keuze van de beheerder.', 'mcp-abilities-kadence' ),
					$post_id,
					$post->post_status
				)
			);
		}

		return $post;
	}

	/**
	 * Lees de blokkenboom.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function inspect_post( $input = array() ) {
		$post = self::haal_post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$attributen = isset( $input['attributes'] ) && is_array( $input['attributes'] )
			? array_map( 'strval', $input['attributes'] )
			: self::STANDAARD_ATTRIBUTEN;

		$volledig = isset( $input['full_attributes'] ) && is_array( $input['full_attributes'] )
			? array_map( 'strval', $input['full_attributes'] )
			: array();

		// Een attribuut dat ongekort gevraagd wordt maar niet in de selectie
		// staat, zou anders stil wegvallen.
		$attributen = array_values( array_unique( array_merge( $attributen, $volledig ) ) );

		$max = isset( $input['max_blocks'] ) ? (int) $input['max_blocks'] : 200;
		$max = max( 1, min( self::MAX_BLOKKEN, $max ) );

		$boom  = parse_blocks( $post->post_content );
		$vanaf = isset( $input['from_unique_id'] ) ? trim( (string) $input['from_unique_id'] ) : '';

		if ( '' !== $vanaf ) {
			$start = Kadence_MCP_Inventory::zoek_op_unique_id( $boom, $vanaf );

			if ( null === $start ) {
				return new WP_Error(
					'kadence_mcp_start_not_found',
					sprintf(
						/* translators: 1: uniqueID, 2: post ID. */
						__( 'Blok met uniqueID "%1$s" staat niet in post %2$d.', 'mcp-abilities-kadence' ),
						$vanaf,
						$post->ID
					)
				);
			}

			$boom = array( $start );
		}

		// Eén blok meer ophalen dan gevraagd: alleen zo is het verschil te zien
		// tussen "precies vol" en "er viel iets af".
		$verzameld    = array();
		$platgeslagen = Kadence_MCP_Inventory::plat_blokken(
			$boom,
			array(
				'attributes'     => $attributen,
				'volledig'       => $volledig,
				'tekst'          => ! empty( $input['include_text'] ),
				'max'            => $max + 1,
				'alleen_kadence' => ! empty( $input['kadence_only'] ),
			),
			0,
			$verzameld
		);

		$afgekapt     = count( $platgeslagen ) > $max;
		$platgeslagen = array_slice( $platgeslagen, 0, $max );

		$per_blok = array();

		foreach ( $platgeslagen as $regel ) {
			$naam = $regel['block'];

			$per_blok[ $naam ] = isset( $per_blok[ $naam ] ) ? $per_blok[ $naam ] + 1 : 1;
		}

		arsort( $per_blok );

		return array(
			'post'   => array(
				'id'        => $post->ID,
				'title'     => get_the_title( $post ),
				'post_type' => $post->post_type,
				'status'    => $post->post_status,
				'modified'  => $post->post_modified_gmt,
			),
			'blocks' => $platgeslagen,
			'counts' => array(
				'returned'  => count( $platgeslagen ),
				'per_block' => $per_blok,
			),
			'truncated' => $afgekapt,
			'status'    => self::inspect_status(
				$platgeslagen,
				$afgekapt,
				! empty( $input['kadence_only'] ),
				$post,
				$vanaf
			),
		);
	}

	/**
	 * Verklaar de uitkomst van inspect-post in één regel.
	 *
	 * @param array $regels        De teruggegeven blokken.
	 * @param bool  $afgekapt      Of er is afgekapt.
	 * @param bool  $alleen_kadence Of er gefilterd is.
	 *
	 * @return string
	 */
	private static function inspect_status( $regels, $afgekapt, $alleen_kadence, $post = null, $vanaf = '' ) {
		$extra = '';

		// find-post gaat door WP_Query en respecteert dus queryfilters; deze
		// tool gaat via get_post en doet dat niet. Verschillen die twee, dan
		// hoort dat hier te staan in plaats van stil te blijven.
		if ( $post && ! Kadence_MCP_Inventory::is_query_zichtbaar( $post->ID, $post->post_type ) ) {
			$extra = ' ' . __( 'Let op: deze post komt niet terug uit een normale query voor dit account — waarschijnlijk schermt een plugin als Members hem af. find-post vindt hem dus niet, terwijl deze tool hem wel leest.', 'mcp-abilities-kadence' );
		}

		if ( '' !== $vanaf ) {
			$extra .= ' ' . sprintf(
				/* translators: %s: uniqueID. */
				__( 'De boom begint bij %s, niet bovenaan de post.', 'mcp-abilities-kadence' ),
				$vanaf
			);
		}

		if ( empty( $regels ) ) {
			return ( $alleen_kadence
				? __( 'leeg — de post bevat blokken, maar geen enkele kadence/*-blok. Zet kadence_only uit om alles te zien.', 'mcp-abilities-kadence' )
				: __( 'leeg — de post bevat geen blokken. Mogelijk is de inhoud klassieke HTML in plaats van blokken.', 'mcp-abilities-kadence' ) ) . $extra;
		}

		if ( $afgekapt ) {
			return __( 'afgekapt op max_blocks — er zijn meer blokken dan teruggegeven. Verhoog max_blocks, zet kadence_only aan, of begin dieper met from_unique_id.', 'mcp-abilities-kadence' ) . $extra;
		}

		return __( 'volledig — dit is de hele blokkenboom.', 'mcp-abilities-kadence' ) . $extra;
	}

	/**
	 * De opgeslagen markup van een post of één blok.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function get_raw_markup( $input = array() ) {
		$post = self::haal_post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$max = isset( $input['max_chars'] ) ? (int) $input['max_chars'] : 6000;
		$max = max( 200, min( 20000, $max ) );

		$unique_id = isset( $input['unique_id'] ) ? trim( (string) $input['unique_id'] ) : '';
		$markup    = (string) $post->post_content;
		$bron      = __( 'de hele post', 'mcp-abilities-kadence' );

		if ( '' !== $unique_id ) {
			$blok = Kadence_MCP_Inventory::zoek_op_unique_id( parse_blocks( $markup ), $unique_id );

			if ( null === $blok ) {
				return new WP_Error(
					'kadence_mcp_block_not_in_post',
					sprintf(
						/* translators: 1: uniqueID, 2: post ID. */
						__( 'Blok met uniqueID "%1$s" staat niet in post %2$d.', 'mcp-abilities-kadence' ),
						$unique_id,
						$post->ID
					)
				);
			}

			// serialize_block() geeft het blok terug precies zoals het wordt
			// opgeslagen, inclusief commentaar en innerHTML.
			$markup = serialize_block( $blok );
			$bron   = sprintf(
				/* translators: %s: uniqueID. */
				__( 'alleen blok %s', 'mcp-abilities-kadence' ),
				$unique_id
			);
		}

		$lengte   = strlen( $markup );
		$afgekapt = $lengte > $max;

		return array(
			'post'   => array(
				'id'        => $post->ID,
				'title'     => get_the_title( $post ),
				'post_type' => $post->post_type,
			),
			'markup' => $afgekapt ? substr( $markup, 0, $max ) : $markup,
			'status' => $afgekapt
				? sprintf(
					/* translators: 1: source, 2: returned chars, 3: total chars. */
					__( '%1$s, afgekapt op %2$d van %3$d tekens. Verhoog max_chars of vraag één blok op met unique_id.', 'mcp-abilities-kadence' ),
					$bron,
					$max,
					$lengte
				)
				: sprintf(
					/* translators: 1: source, 2: total chars. */
					__( '%1$s, volledig (%2$d tekens).', 'mcp-abilities-kadence' ),
					$bron,
					$lengte
				),
		);
	}

	/**
	 * Toon wat een wijziging zou opleveren, zonder iets op te slaan.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function preview_write( $input = array() ) {
		$post = self::haal_post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$unique_id = isset( $input['unique_id'] ) ? trim( (string) $input['unique_id'] ) : '';
		$voorstel  = isset( $input['attributes'] ) ? (array) $input['attributes'] : array();

		if ( '' === $unique_id || empty( $voorstel ) ) {
			return new WP_Error(
				'kadence_mcp_preview_incomplete',
				__( 'Geef zowel unique_id als minstens één attribuut met de voorgenomen waarde.', 'mcp-abilities-kadence' )
			);
		}

		$boom = parse_blocks( $post->post_content );
		$blok = Kadence_MCP_Inventory::zoek_op_unique_id( $boom, $unique_id );

		if ( null === $blok ) {
			return new WP_Error(
				'kadence_mcp_block_not_in_post',
				sprintf(
					/* translators: 1: uniqueID, 2: post ID. */
					__( 'Blok met uniqueID "%1$s" staat niet in post %2$d.', 'mcp-abilities-kadence' ),
					$unique_id,
					$post->ID
				)
			);
		}

		// Standaard alleen de openingscomment van het blok zelf. De volledige
		// markup van een opgemaakte sectie is duizenden tekens waarvan er één
		// verandert; dat leest niemand na. Wie de hele boom wil vraagt erom.
		$volledig = ! empty( $input['full_markup'] );
		$voor     = serialize_block( $blok );

		// Op een KOPIE werken. $blok komt uit parse_blocks() en is een gewone
		// array, dus dit raakt de post nergens — maar expliciet kopiëren maakt
		// dat zichtbaar in plaats van iets dat je moet weten.
		$gewijzigd = $blok;
		$huidig    = isset( $blok['attrs'] ) && is_array( $blok['attrs'] ) ? $blok['attrs'] : array();
		$verschil  = array();

		foreach ( $voorstel as $attr => $nieuw ) {
			$attr = (string) $attr;
			$oud  = array_key_exists( $attr, $huidig ) ? $huidig[ $attr ] : null;

			$verschil[] = array(
				'attribute' => $attr,
				'before'    => $oud,
				'after'     => $nieuw,
				'was_set'   => array_key_exists( $attr, $huidig ),
			);

			$gewijzigd['attrs'][ $attr ] = $nieuw;
		}

		$na = serialize_block( $gewijzigd );

		if ( ! $volledig ) {
			$voor = self::eerste_regel( $voor );
			$na   = self::eerste_regel( $na );
		}

		// De innerHTML hoort niet mee te veranderen — serialize_block() raakt
		// hem niet aan. Verandert hij toch, dan is er iets grondig mis en moet
		// dat opvallen in plaats van doorglippen.
		$html_voor = isset( $blok['innerHTML'] ) ? (string) $blok['innerHTML'] : '';
		$html_na   = isset( $gewijzigd['innerHTML'] ) ? (string) $gewijzigd['innerHTML'] : '';

		$status = $voor === $na
			? __( 'geen verschil — de voorgestelde waarden zijn gelijk aan wat er al staat. Schrijven zou niets veranderen.', 'mcp-abilities-kadence' )
			: __( 'dit is de markup zoals hij eruit zou zien. Er is NIETS opgeslagen. Vergelijk before en after voordat je dit aanraadt, en let er vooral op of de innerHTML nog naar de oude waarden verwijst.', 'mcp-abilities-kadence' );

		if ( $html_voor !== $html_na ) {
			$status = __( 'LET OP: de innerHTML is veranderd. Dat hoort niet te gebeuren bij een attribuutwijziging en wijst op een fout in deze tool. Niet schrijven.', 'mcp-abilities-kadence' );
		}

		return array(
			'block'  => array(
				'post_id'   => $post->ID,
				'unique_id' => $unique_id,
				'block'     => isset( $blok['blockName'] ) ? $blok['blockName'] : '',
			),
			'changes' => $verschil,
			'before'  => $voor,
			'after'   => $na,
			'identical' => $voor === $na,
			'status'  => $status,
		);
	}

	/**
	 * Schrijf attributen naar één bestaand blok.
	 *
	 * De eerste en voorlopig enige schrijfactie. Bewust klein: één blok, alleen
	 * attributen, geen structuurwijziging. Drie grendels ervoor en één erna.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function set_attributes( $input = array() ) {
		// Grendel 1: een eigen capability. Wie alleen leest kan hier niet komen,
		// ook niet als de tool in het instellingenscherm aanstaat.
		if ( ! Kadence_MCP_Capabilities::current_user_can( Kadence_MCP_Capabilities::WRITE ) ) {
			return new WP_Error(
				'kadence_mcp_no_write_capability',
				__( 'Dit account heeft de capability kadence_mcp_write niet. Die wordt bij activering aan niemand toegekend; hij moet bewust aan een rol gegeven worden.', 'mcp-abilities-kadence' )
			);
		}

		$post = self::haal_post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		// Grendel 2: WordPress moet deze gebruiker de post ook echt laten
		// bewerken. read_post is niet genoeg om te mogen schrijven.
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error(
				'kadence_mcp_cannot_edit',
				sprintf(
					/* translators: %d: post ID. */
					__( 'Geen bewerkrecht op post %d volgens WordPress zelf.', 'mcp-abilities-kadence' ),
					$post->ID
				)
			);
		}

		$unique_id = isset( $input['unique_id'] ) ? trim( (string) $input['unique_id'] ) : '';
		$voorstel  = isset( $input['attributes'] ) ? (array) $input['attributes'] : array();
		$token     = isset( $input['token'] ) ? trim( (string) $input['token'] ) : '';

		if ( '' === $unique_id || empty( $voorstel ) ) {
			return new WP_Error( 'kadence_mcp_write_incomplete', __( 'Geef unique_id en minstens één attribuut.', 'mcp-abilities-kadence' ) );
		}

		// Grendel 3: het token van validate-write. Zonder geldig token geen
		// schrijfactie, en het token is gebonden aan deze post, dit blok, deze
		// exacte attributen en de wijzigingsdatum. Toetsen is daarmee niet over
		// te slaan, en je kunt niet iets anders schrijven dan wat getoetst is.
		$verwacht = Kadence_MCP_Inventory::schrijf_token( $post, $unique_id, $voorstel );

		if ( ! hash_equals( $verwacht, $token ) ) {
			return new WP_Error(
				'kadence_mcp_invalid_token',
				Kadence_MCP_Inventory::token_reden( $token, $verwacht, $post )
			);
		}

		$boom = parse_blocks( $post->post_content );
		$blok = Kadence_MCP_Inventory::zoek_op_unique_id( $boom, $unique_id );

		if ( null === $blok ) {
			return new WP_Error( 'kadence_mcp_block_not_in_post', __( 'Dat blok staat niet in deze post.', 'mcp-abilities-kadence' ) );
		}

		$bloknaam = isset( $blok['blockName'] ) ? (string) $blok['blockName'] : '';
		$nieuw    = array_merge(
			isset( $blok['attrs'] ) && is_array( $blok['attrs'] ) ? $blok['attrs'] : array(),
			$voorstel
		);

		$genormaliseerd = Kadence_MCP_Inventory::normaliseer_attributen( $bloknaam, $nieuw );

		$gewijzigd = self::vervang_attrs( $boom, $unique_id, $genormaliseerd['attrs'] );
		$content   = Kadence_MCP_Inventory::serialiseer( $gewijzigd );

		// wp_update_post verwacht geslashte data; zonder wp_slash verdwijnen
		// backslashes uit de inhoud.
		$resultaat = wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_content' => wp_slash( $content ),
			),
			true
		);

		if ( is_wp_error( $resultaat ) ) {
			return $resultaat;
		}

		// Het sluitstuk: teruglezen. Kadence hangt op wp_insert_post_data een
		// filter dat post_content herschrijft (filter_dynamic_content), dus wat
		// er belandt is niet per se wat er verstuurd is. Dat hoort zichtbaar te
		// zijn en niet als "geslaagd" weg te vallen.
		clean_post_cache( $post->ID );

		$na        = get_post( $post->ID );
		$na_boom   = $na ? parse_blocks( $na->post_content ) : array();
		$na_blok   = Kadence_MCP_Inventory::zoek_op_unique_id( $na_boom, $unique_id );
		$na_attrs  = $na_blok && isset( $na_blok['attrs'] ) ? $na_blok['attrs'] : array();

		$afwijkend = array();

		foreach ( $genormaliseerd['attrs'] as $sleutel => $waarde ) {
			$staat_er = array_key_exists( $sleutel, $na_attrs ) ? $na_attrs[ $sleutel ] : null;

			if ( wp_json_encode( $staat_er ) !== wp_json_encode( $waarde ) ) {
				$afwijkend[] = array(
					'attribute' => $sleutel,
					'intended'  => $waarde,
					'stored'    => $staat_er,
				);
			}
		}

		$notities = array();

		if ( ! empty( $genormaliseerd['dropped_default'] ) ) {
			$notities[] = sprintf(
				/* translators: %s: comma separated attribute names. */
				__( 'weggelaten omdat ze gelijk zijn aan de standaardwaarde: %s. Dat is wat de editor ook doet.', 'mcp-abilities-kadence' ),
				implode( ', ', $genormaliseerd['dropped_default'] )
			);
		}

		if ( ! empty( $genormaliseerd['dropped_unknown'] ) ) {
			$notities[] = sprintf(
				/* translators: %s: comma separated attribute names. */
				__( 'weggelaten omdat ze niet in het blokschema staan: %s. Die zouden bij het renderen toch stil genegeerd worden.', 'mcp-abilities-kadence' ),
				implode( ', ', $genormaliseerd['dropped_unknown'] )
			);
		}

		return array(
			'block'    => array(
				'post_id'   => $post->ID,
				'unique_id' => $unique_id,
				'block'     => $bloknaam,
			),
			'written'  => $genormaliseerd['attrs'],
			'notes'    => $notities,
			'mismatch' => $afwijkend,
			'revision' => __( 'Er is een revisie gemaakt; terugdraaien kan via het revisieoverzicht van de post.', 'mcp-abilities-kadence' ),
			'status'   => empty( $afwijkend )
				? __( 'geschreven en teruggelezen: wat er staat komt overeen met wat er bedoeld was.', 'mcp-abilities-kadence' ) . Kadence_MCP_Query::facetwaarschuwing( get_post( $post->ID ) )
				: __( 'LET OP: na het opslaan wijkt de opgeslagen waarde af van wat er verstuurd is. Kadence herschrijft post_content bij het opslaan (filter op wp_insert_post_data), of er is iets anders tussengekomen. Kijk bij mismatch wat er verschilt voordat je verdergaat.', 'mcp-abilities-kadence' ),
		);
	}

	/**
	 * Kopieer een blok naar een andere post, met verse uniqueID's.
	 *
	 * Standaard een droogloop: hij toont wat hij zou invoegen en geeft een
	 * token. Pas met dry_run false én dat token wordt er geschreven.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function duplicate_blocks( $input = array() ) {
		$bron_post = self::haal_post( isset( $input['source_post_id'] ) ? $input['source_post_id'] : 0 );

		if ( is_wp_error( $bron_post ) ) {
			return $bron_post;
		}

		$doel_post = self::haal_post( isset( $input['target_post_id'] ) ? $input['target_post_id'] : 0 );

		if ( is_wp_error( $doel_post ) ) {
			return $doel_post;
		}

		$bron_id = isset( $input['source_unique_id'] ) ? trim( (string) $input['source_unique_id'] ) : '';

		if ( '' === $bron_id ) {
			return new WP_Error( 'kadence_mcp_missing_source', __( 'Geef de uniqueID op van het blok dat je wil kopiëren.', 'mcp-abilities-kadence' ) );
		}

		$bron_blok = Kadence_MCP_Inventory::zoek_op_unique_id( parse_blocks( $bron_post->post_content ), $bron_id );

		if ( null === $bron_blok ) {
			return new WP_Error(
				'kadence_mcp_block_not_in_post',
				sprintf(
					/* translators: 1: uniqueID, 2: post ID. */
					__( 'Blok "%1$s" staat niet in post %2$d.', 'mcp-abilities-kadence' ),
					$bron_id,
					$bron_post->ID
				)
			);
		}

		// Alle ID's in de kopie vernieuwen, en ze prefixen met de DOELpost.
		// Dat is wat botsingen tussen posts onwaarschijnlijk maakt.
		$doel_boom = parse_blocks( $doel_post->post_content );
		$bezet     = Kadence_MCP_Inventory::verzamel_unique_ids( $doel_boom );
		$in_kopie  = Kadence_MCP_Inventory::verzamel_unique_ids( array( $bron_blok ) );
		$kaart     = array();
		$droog     = ! isset( $input['dry_run'] ) || ! empty( $input['dry_run'] );
		$aangeleverd = isset( $input['id_map'] ) && is_array( $input['id_map'] ) ? $input['id_map'] : array();

		if ( ! $droog ) {
			// Bij het SCHRIJVEN moet de kaart meekomen uit de droogloop.
			//
			// Dit was tot 1.3.0 anders en daardoor was schrijven onmogelijk: de
			// kaart werd bij elke aanroep opnieuw met willekeurige ID's gevuld,
			// het token ging over die kaart, en bij de schrijfaanroep ontstond
			// er dus altijd een ander token dan de droogloop had gegeven. Geen
			// enkele aanroep kon slagen. Nu is de kaart invoer, net zoals de
			// markup dat bij insert-blocks is.
			if ( empty( $aangeleverd ) ) {
				return new WP_Error(
					'kadence_mcp_missing_id_map',
					__( 'Geef bij dry_run false de id_map mee die de droogloop teruggaf. Zonder die kaart zouden er nieuwe uniqueIDs gemaakt worden en zou je iets anders schrijven dan je hebt goedgekeurd.', 'mcp-abilities-kadence' )
				);
			}

			$verwachte_sleutels = array_keys( $in_kopie );
			$gekregen_sleutels  = array_keys( $aangeleverd );
			sort( $verwachte_sleutels );
			sort( $gekregen_sleutels );

			if ( $verwachte_sleutels !== $gekregen_sleutels ) {
				return new WP_Error(
					'kadence_mcp_id_map_mismatch',
					__( 'De id_map dekt niet precies de blokken die gekopieerd worden. Draai de droogloop opnieuw; het bronblok is kennelijk veranderd.', 'mcp-abilities-kadence' )
				);
			}

			$waarden = array_values( $aangeleverd );

			if ( count( array_unique( $waarden ) ) !== count( $waarden ) ) {
				return new WP_Error(
					'kadence_mcp_id_map_duplicates',
					__( 'De id_map wijst twee blokken dezelfde uniqueID toe. Twee blokken met hetzelfde ID delen hun CSS.', 'mcp-abilities-kadence' )
				);
			}

			$botsingen = array_intersect( $waarden, array_keys( $bezet ) );

			if ( ! empty( $botsingen ) ) {
				return new WP_Error(
					'kadence_mcp_id_map_collision',
					sprintf(
						/* translators: %s: comma separated uniqueIDs. */
						__( 'De id_map gebruikt uniqueIDs die al in de doelpost voorkomen (%s). Draai de droogloop opnieuw.', 'mcp-abilities-kadence' ),
						implode( ', ', array_slice( $botsingen, 0, 5 ) )
					)
				);
			}

			$kaart = array_map( 'strval', $aangeleverd );
		} else {
			foreach ( array_keys( $in_kopie ) as $oud ) {
				$nieuw = Kadence_MCP_Inventory::nieuwe_unique_id( $doel_post->ID, $bezet );

				if ( '' === $nieuw ) {
					return new WP_Error( 'kadence_mcp_id_exhausted', __( 'Kon geen vrije uniqueID genereren.', 'mcp-abilities-kadence' ) );
				}

				$kaart[ $oud ]   = $nieuw;
				$bezet[ $nieuw ] = array( 'gereserveerd' );
			}
		}

		$kopie = Kadence_MCP_Inventory::hernoem_unique_ids( array( $bron_blok ), $kaart );
		$markup = serialize_blocks( $kopie );

		$positie = isset( $input['position'] ) ? (string) $input['position'] : 'append';
		$binnen  = isset( $input['target_unique_id'] ) ? trim( (string) $input['target_unique_id'] ) : '';

		if ( 'inside' === $positie ) {
			if ( '' === $binnen ) {
				return new WP_Error(
					'kadence_mcp_duplicate_missing_target',
					__( 'Bij position inside hoort target_unique_id: de container in de doelpost waar de kopie in moet.', 'mcp-abilities-kadence' )
				);
			}

			$gelukt      = false;
			$nieuwe_boom = Kadence_MCP_Inventory::voeg_binnen_in( $doel_boom, $binnen, $kopie, $gelukt );

			if ( ! $gelukt ) {
				return new WP_Error(
					'kadence_mcp_duplicate_target_not_found',
					sprintf(
						/* translators: 1: uniqueID, 2: post ID. */
						__( 'Container "%1$s" staat niet in post %2$d, of het is een blok zonder binnenkant.', 'mcp-abilities-kadence' ),
						$binnen,
						$doel_post->ID
					)
				);
			}
		} else {
			$nieuwe_boom = 'prepend' === $positie
				? array_merge( $kopie, $doel_boom )
				: array_merge( $doel_boom, $kopie );
		}

		$content = Kadence_MCP_Inventory::serialiseer( $nieuwe_boom );
		$token_grondslag = array( 'copy' => $bron_id, 'map' => $kaart, 'pos' => $positie );
		$token = Kadence_MCP_Inventory::schrijf_token( $doel_post, 'duplicate:' . $bron_id, $token_grondslag );

		if ( $droog ) {
			return array(
				'source'   => array( 'post_id' => $bron_post->ID, 'unique_id' => $bron_id, 'block' => isset( $bron_blok['blockName'] ) ? $bron_blok['blockName'] : '' ),
				'target'   => array( 'post_id' => $doel_post->ID, 'title' => get_the_title( $doel_post ), 'existing_blocks' => count( $doel_boom ) ),
				'id_map'   => $kaart,
				'markup'   => strlen( $markup ) > 4000 ? substr( $markup, 0, 4000 ) . '…' : $markup,
				'token'    => $token,
				'status'   => __( 'DROOGLOOP — er is niets geschreven. Dit is de markup die ingevoegd zou worden, met verse uniqueID\'s. Kijk hem na en roep opnieuw aan met dry_run false en dit token om hem echt te plaatsen.', 'mcp-abilities-kadence' ),
			);
		}

		// Vanaf hier wordt er geschreven. Dezelfde drie grendels als set-attributes.
		if ( ! Kadence_MCP_Capabilities::current_user_can( Kadence_MCP_Capabilities::WRITE ) ) {
			return new WP_Error( 'kadence_mcp_no_write_capability', __( 'Dit account heeft de capability kadence_mcp_write niet.', 'mcp-abilities-kadence' ) );
		}

		if ( ! current_user_can( 'edit_post', $doel_post->ID ) ) {
			return new WP_Error(
				'kadence_mcp_cannot_edit',
				sprintf(
					/* translators: %d: post ID. */
					__( 'Geen bewerkrecht op post %d volgens WordPress zelf.', 'mcp-abilities-kadence' ),
					$doel_post->ID
				)
			);
		}

		$meegegeven = isset( $input['token'] ) ? trim( (string) $input['token'] ) : '';

		if ( ! hash_equals( $token, $meegegeven ) ) {
			return new WP_Error(
				'kadence_mcp_invalid_token',
				Kadence_MCP_Inventory::token_reden( $meegegeven, $token, $doel_post )
			);
		}

		$resultaat = wp_update_post(
			array(
				'ID'           => $doel_post->ID,
				'post_content' => wp_slash( $content ),
			),
			true
		);

		if ( is_wp_error( $resultaat ) ) {
			return $resultaat;
		}

		clean_post_cache( $doel_post->ID );

		$na      = get_post( $doel_post->ID );
		$na_boom = $na ? parse_blocks( $na->post_content ) : array();
		$gelukt  = array();
		$mislukt = array();

		foreach ( $kaart as $oud => $nieuw ) {
			if ( null !== Kadence_MCP_Inventory::zoek_op_unique_id( $na_boom, $nieuw ) ) {
				$gelukt[] = $nieuw;
			} else {
				$mislukt[] = $nieuw;
			}
		}

		// Aanwezigheid is niet genoeg. Bij position inside hoort de kopie ONDER
		// de opgegeven container te staan; stond hij ergens anders in de post,
		// dan meldde deze controle tot 1.7.3 gewoon succes.
		if ( 'inside' === $positie && '' !== $binnen && empty( $mislukt ) ) {
			$kop   = isset( $kaart[ $bron_id ] ) ? (string) $kaart[ $bron_id ] : '';
			$ouder = '' === $kop ? '' : Kadence_MCP_Inventory::ouder_van( $na_boom, $kop );

			if ( $ouder !== $binnen ) {
				return new WP_Error(
					'kadence_mcp_duplicate_wrong_parent',
					sprintf(
						/* translators: 1: intended container, 2: actual parent. */
						__( 'Er is geschreven, maar de kopie staat niet onder "%1$s" — de ouder is nu "%2$s". Controleer de post en draai zo nodig de revisie terug.', 'mcp-abilities-kadence' ),
						$binnen,
						'' === $ouder ? __( 'het hoogste niveau', 'mcp-abilities-kadence' ) : $ouder
					)
				);
			}
		}

		return array(
			'source'   => array( 'post_id' => $bron_post->ID, 'unique_id' => $bron_id ),
			'target'   => array( 'post_id' => $doel_post->ID, 'blocks_now' => count( $na_boom ) ),
			'id_map'   => $kaart,
			'verified' => $gelukt,
			'missing'  => $mislukt,
			'revision' => __( 'Er is een revisie gemaakt; terugdraaien kan via het revisieoverzicht.', 'mcp-abilities-kadence' ),
			'status'   => empty( $mislukt )
				? sprintf(
					/* translators: %d: number of blocks. */
					__( 'geplaatst en teruggelezen: alle %d blokken staan er met hun nieuwe uniqueID.', 'mcp-abilities-kadence' ),
					count( $gelukt )
				)
				: __( 'LET OP: na het opslaan ontbreken er blokken die er hadden moeten staan. Kadence herschrijft post_content bij het opslaan, of er is iets anders tussengekomen. Kijk in missing welke.', 'mcp-abilities-kadence' ),
		);
	}

	/**
	 * Vervang de attributen van één blok in een boom.
	 *
	 * @param array  $blokken   De boom.
	 * @param string $unique_id Het doelblok.
	 * @param array  $attrs     De nieuwe attributen.
	 *
	 * @return array
	 */
	private static function vervang_attrs( $blokken, $unique_id, $attrs ) {
		foreach ( $blokken as $i => $blok ) {
			$huidig = isset( $blok['attrs']['uniqueID'] ) ? (string) $blok['attrs']['uniqueID'] : '';

			if ( $huidig === (string) $unique_id ) {
				$blokken[ $i ]['attrs'] = $attrs;
				continue;
			}

			if ( ! empty( $blok['innerBlocks'] ) ) {
				$blokken[ $i ]['innerBlocks'] = self::vervang_attrs( $blok['innerBlocks'], $unique_id, $attrs );
			}
		}

		return $blokken;
	}

	/**
	 * Alleen de openingscomment van een geserialiseerd blok.
	 *
	 * Daar staan de attributen in, en dat is precies wat een attribuutwijziging
	 * raakt. De inhoud eronder blijft ongemoeid.
	 *
	 * @param string $markup De geserialiseerde markup.
	 *
	 * @return string
	 */
	private static function eerste_regel( $markup ) {
		$einde = strpos( $markup, '-->' );

		return false === $einde ? $markup : substr( $markup, 0, $einde + 3 );
	}

	/**
	 * Toets een voorgenomen wijziging. Schrijft niets.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function validate_write( $input = array() ) {
		$post = self::haal_post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$unique_id = isset( $input['unique_id'] ) ? trim( (string) $input['unique_id'] ) : '';
		$voorstel  = isset( $input['attributes'] ) ? (array) $input['attributes'] : array();

		if ( '' === $unique_id ) {
			return new WP_Error( 'kadence_mcp_missing_unique_id', __( 'Geef de uniqueID op van het blok waarop je wil schrijven.', 'mcp-abilities-kadence' ) );
		}

		if ( empty( $voorstel ) ) {
			return new WP_Error( 'kadence_mcp_no_attributes', __( 'Geef minstens één attribuut met de voorgenomen waarde.', 'mcp-abilities-kadence' ) );
		}

		$boom = parse_blocks( $post->post_content );
		$blok = Kadence_MCP_Inventory::zoek_op_unique_id( $boom, $unique_id );

		if ( null === $blok ) {
			return new WP_Error(
				'kadence_mcp_block_not_in_post',
				sprintf(
					/* translators: 1: uniqueID, 2: post ID. */
					__( 'Blok met uniqueID "%1$s" staat niet in post %2$d.', 'mcp-abilities-kadence' ),
					$unique_id,
					$post->ID
				)
			);
		}

		$bloknaam  = isset( $blok['blockName'] ) ? (string) $blok['blockName'] : '';
		$huidig    = isset( $blok['attrs'] ) && is_array( $blok['attrs'] ) ? $blok['attrs'] : array();
		$controles = array();
		$blokkeer  = false;
		$waarschuw = false;

		foreach ( $voorstel as $attr => $nieuw ) {
			$attr      = (string) $attr;
			$definitie = Kadence_MCP_Inventory::attribuut_definitie( $bloknaam, $attr );
			$regel     = array( 'attribute' => $attr, 'level' => 'ok', 'notes' => array() );

			// metadata en lock horen bij WordPress zelf en staan in geen enkele
			// block.json. Geen definitie betekent hier dus niet "bestaat niet".
			if ( in_array( $attr, Kadence_MCP_Inventory::CORE_ATTRIBUTEN, true ) ) {
				if ( ! is_array( $nieuw ) && ! is_object( $nieuw ) ) {
					$regel['level']   = 'blokkeer';
					$regel['notes'][] = sprintf(
						/* translators: %s: attribute name. */
						__( '"%s" is een object, geen losse waarde. metadata verwacht bijvoorbeeld {"name":"Hero"}.', 'mcp-abilities-kadence' ),
						$attr
					);
					$blokkeer    = true;
					$controles[] = $regel;
					continue;
				}

				// Bewust 'ok' en niet 'riskant'. Riskant levert geen token op,
				// en dan is het attribuut in het geheel niet te schrijven —
				// waardoor een bloknaam die door een eerdere versie is
				// weggevallen niet te herstellen zou zijn. Er valt hier niets
				// te toetsen, maar er valt ook niets kapot te maken: het is
				// geen Kadence-attribuut, dus geen render hangt ervan af.
				$regel['notes'][] = sprintf(
					/* translators: %s: attribute name. */
					__( '"%s" is een attribuut van WordPress zelf, niet van Kadence. Er is geen schema om de inhoud tegen te toetsen, dus de waarde wordt overgenomen zoals hij is. Controleer hem dus zelf: metadata verwacht bijvoorbeeld {"name":"Hero"}, en een verkeerde sleutel valt nergens op.', 'mcp-abilities-kadence' ),
					$attr
				);
				$controles[] = $regel;
				continue;
			}

			if ( null === $definitie ) {
				$regel['level']   = 'blokkeer';
				$regel['notes'][] = sprintf(
					/* translators: 1: attribute, 2: block name. */
					__( 'het attribuut "%1$s" bestaat niet op %2$s. Controleer de spelling met describe-block; namen zijn hoofdlettergevoelig.', 'mcp-abilities-kadence' ),
					$attr,
					$bloknaam
				);
				$blokkeer    = true;
				$controles[] = $regel;
				continue;
			}

			$fout = Kadence_MCP_Inventory::toets_waarde( $definitie, $nieuw );

			if ( '' !== $fout ) {
				$regel['level']   = 'blokkeer';
				$regel['notes'][] = $fout;
				$blokkeer         = true;
			}

			// Een attribuut met een source wordt uit de markup geparsed. Wat je
			// in het blokcommentaar zet wordt bij het inlezen overschreven.
			if ( isset( $definitie['source'] ) ) {
				$regel['level']   = 'blokkeer';
				$regel['notes'][] = sprintf(
					/* translators: %s: the source type. */
					__( 'dit attribuut wordt uit de markup geparsed (source: %s). Het in het blokcommentaar schrijven doet niets.', 'mcp-abilities-kadence' ),
					is_string( $definitie['source'] ) ? $definitie['source'] : 'onbekend'
				);
				$blokkeer = true;
			}

			$gebonden = Kadence_MCP_Inventory::markup_gebonden( $blok, $attr, isset( $huidig[ $attr ] ) ? $huidig[ $attr ] : null );

			if ( $gebonden['gebonden'] ) {
				$regel['level']   = 'blokkeer' === $regel['level'] ? 'blokkeer' : 'riskant';
				$regel['notes'][] = $gebonden['reden'];
				$waarschuw        = true;
			}

			$regel = self::bijzondere_attributen( $regel, $attr, $nieuw, $bloknaam, $boom, $blok, $voorstel );

			if ( 'blokkeer' === $regel['level'] ) {
				$blokkeer = true;
			} elseif ( 'riskant' === $regel['level'] ) {
				$waarschuw = true;
			}

			if ( empty( $regel['notes'] ) ) {
				$regel['notes'][] = __( 'geen bezwaar: het attribuut bestaat, de waarde past, en hij zit niet vast in de markup.', 'mcp-abilities-kadence' );
			}

			$controles[] = $regel;
		}

		$oordeel = $blokkeer ? 'blokkeer' : ( $waarschuw ? 'riskant' : 'veilig' );

		// De statusregel bij riskant noemde tot 1.3.0 altijd dezelfde reden:
		// "minstens één waarde zit vast in de opgeslagen HTML". Dat was een
		// aanname, en hij stond soms pal naast een antwoord dat zelf
		// self_closing: true meldde — een blok zonder eigen markup dus, waar
		// niets in vast kan zitten. Nu worden de betrokken attributen genoemd
		// en blijft het aan de lezer om de notitie erbij te lezen.
		$riskante = array();

		foreach ( $controles as $controle ) {
			if ( isset( $controle['level'] ) && 'riskant' === $controle['level'] ) {
				$riskante[] = $controle['attribute'];
			}
		}

		$status = array(
			'blokkeer' => __( 'NIET schrijven. Minstens één bezwaar maakt de wijziging zinloos of schadelijk.', 'mcp-abilities-kadence' ),
			'riskant'  => sprintf(
				/* translators: %s: comma separated attribute names. */
				__( 'Er komt geen token: bij %s is er een bezwaar dat een mens moet wegen, niet een hash. Lees de notitie bij dat attribuut — daar staat waaraan het ligt.', 'mcp-abilities-kadence' ),
				implode( ', ', $riskante )
			),
			'veilig'   => __( 'Geen bezwaar gevonden. Dit toetst de voorgenomen wijziging, niet of het resultaat er goed uitziet.', 'mcp-abilities-kadence' ),
		);

		$resultaat = array(
			'block'   => array(
				'post_id'      => $post->ID,
				'unique_id'    => $unique_id,
				'block'        => $bloknaam,
				'self_closing' => '' === trim( isset( $blok['innerHTML'] ) ? (string) $blok['innerHTML'] : '' ),
			),
			'checks'  => $controles,
			'verdict' => $oordeel,
			'status'  => $status[ $oordeel ],
		);

		// Alleen een schone toetsing levert een token op. Bij 'riskant' hoort
		// een mens te beslissen, niet een hash; bij 'blokkeer' is er niets te
		// beslissen.
		if ( 'veilig' === $oordeel ) {
			$resultaat['token'] = Kadence_MCP_Inventory::schrijf_token( $post, $unique_id, $voorstel );
			$resultaat['token_note'] = __( 'Geef dit token mee aan een toekomstige schrijfactie. Het is gebonden aan deze post, dit blok, deze exacte attributen én de huidige wijzigingsdatum van de post — verandert er iets, dan is het ongeldig en moet er opnieuw getoetst worden.', 'mcp-abilities-kadence' );
		}

		return $resultaat;
	}

	/**
	 * De drie attributen met een eigen, stil faalpad.
	 *
	 * @param array  $regel    De controleregel tot nu toe.
	 * @param string $attr     Attribuutnaam.
	 * @param mixed  $nieuw    De voorgenomen waarde.
	 * @param string $bloknaam De bloknaam.
	 * @param array  $boom     De hele blokkenboom van de post.
	 * @param array  $blok     Het blok zelf.
	 *
	 * @return array
	 */
	private static function bijzondere_attributen( $regel, $attr, $nieuw, $bloknaam, $boom, $blok, $voorstel = array() ) {
		// Twee dingen die de gewone toets niet kan zien, allebei uit het
		// blokprofiel. Vroeger stonden ze los van de generator, en juist daar
		// ging het twee keer mis: het attribuut werd netjes opgeslagen en de
		// pagina veranderde niet.
		$huidige = isset( $blok['attrs'] ) && is_array( $blok['attrs'] ) ? $blok['attrs'] : array();

		// Het HELE voorstel meenemen, niet alleen wat er nu op het blok staat.
		// Sommige waarden zijn alleen te toetsen tegen een ander attribuut —
		// colLayout tegen columns — en dat andere attribuut kan in dezelfde
		// aanroep meeveranderen. Tot 1.12.1 werd er tegen de OUDE stand
		// getoetst: wie een rij van één naar drie kolommen bracht en meteen de
		// juiste colLayout meegaf, kreeg "Kadence kent center-exwide niet bij 1
		// kolommen" en werd geblokkeerd op een wijziging die juist klopte.
		if ( ! empty( $voorstel ) && is_array( $voorstel ) ) {
			$huidige = array_merge( $huidige, $voorstel );
		}

		// 1. Een waarde die Kadence niet kent. Attributen zonder enum in
		//    block.json laten élke tekst door.
		$bezwaar = Kadence_MCP_Profielen::toets_waarde( $bloknaam, $attr, $nieuw, $huidige );

		if ( '' !== $bezwaar ) {
			$regel['level']   = 'blokkeer';
			$regel['notes'][] = $bezwaar;
		}

		// 2. Een attribuut waar markup uit wordt afgeleid. set-attributes en
		//    style-blocks raken de markup niet aan, dus de klasse blijft op de
		//    oude waarde staan. Of dat erg is verschilt per geval — bij
		//    colorClass wel (de kleur komt uit de klasse), bij direction niet
		//    (gemeten: het attribuut wint) — dus dit is een waarschuwing en
		//    geen blokkade.
		if ( Kadence_MCP_Profielen::raakt_markup( $bloknaam, $attr ) ) {
			$regel['notes'][] = sprintf(
				/* translators: %s: attribute name. */
				__( 'uit "%s" worden klassen in de markup afgeleid, en die worden hier NIET bijgewerkt — alleen het attribuut. Controleer na het schrijven of het resultaat klopt; bij twijfel bouwt replace-block het blok opnieuw op.', 'mcp-abilities-kadence' ),
				$attr
			);
		}

		if ( 'uniqueID' === $attr ) {
			$schoon = preg_replace( '/[^A-Za-z0-9_-]/', '', str_replace( '/', '-', (string) $nieuw ) );

			if ( $schoon !== (string) $nieuw ) {
				$regel['level']   = 'blokkeer';
				$regel['notes'][] = sprintf(
					/* translators: %s: sanitised value. */
					__( 'Kadence saneert deze waarde server-side tot "%s". Wat je schrijft is niet wat er gebruikt wordt.', 'mcp-abilities-kadence' ),
					$schoon
				);
			}

			if ( '' === trim( (string) $nieuw ) ) {
				$regel['level']   = 'blokkeer';
				$regel['notes'][] = __( 'een lege uniqueID laat Kadence de hele wrapper en de CSS overslaan, zonder foutmelding.', 'mcp-abilities-kadence' );
			}

			$bestaand = Kadence_MCP_Inventory::verzamel_unique_ids( $boom );

			if ( isset( $bestaand[ (string) $nieuw ] ) ) {
				$regel['level']   = 'blokkeer';
				$regel['notes'][] = sprintf(
					/* translators: %s: block names. */
					__( 'deze uniqueID is in dezelfde post al in gebruik door %s. Twee blokken met dezelfde uniqueID delen hun CSS; het tweede krijgt er stil geen eigen.', 'mcp-abilities-kadence' ),
					implode( ', ', $bestaand[ (string) $nieuw ] )
				);
			}

			$regel['notes'][] = __( 'Kadence controleert uniciteit alleen in de editor en alleen binnen de post die openstaat. Over posts heen controleert niemand iets.', 'mcp-abilities-kadence' );
		}

		if ( 'kbVersion' === $attr ) {
			$oud = isset( $huidige['kbVersion'] ) ? (int) $huidige['kbVersion'] : 0;

			// Omhoog of van ontbrekend naar 2 is juist de REPARATIE, geen
			// risico. Een blok zonder kbVersion krijgt van Kadence geen
			// wrapper, en dan valt de hele kolomindeling weg terwijl elke
			// controle groen blijft: de markup is geldig, de blokken staan er,
			// de CSS wordt gegenereerd — alleen het element waar die CSS op
			// hangt bestaat niet. Tot 1.14.0 blokkeerde deze toets die
			// reparatie, want hij keek alleen naar de naam van het attribuut
			// en niet naar de richting van de wijziging.
			if ( (int) $nieuw >= 2 && (int) $nieuw >= $oud ) {
				$regel['notes'][] = ( 0 === $oud )
					? __( 'kbVersion ontbrak; op 2 zetten is de reparatie die de wrapper terugbrengt.', 'mcp-abilities-kadence' )
					: __( 'kbVersion blijft gelijk of gaat omhoog; dat is veilig.', 'mcp-abilities-kadence' );
			} else {
				$regel['level']   = 'blokkeer' === $regel['level'] ? 'blokkeer' : 'riskant';
				$regel['notes'][] = __( 'kbVersion bepaalt welke rendertak Kadence neemt. Op 1 of leeg zetten laat de wrapper vervallen en schakelt de CSS om naar andere selectors.', 'mcp-abilities-kadence' );
			}
		}

		if ( 'columns' === $attr && 'kadence/rowlayout' === $bloknaam ) {
			$kinderen = isset( $blok['innerBlocks'] ) ? count( $blok['innerBlocks'] ) : 0;

			if ( is_numeric( $nieuw ) && (int) $nieuw !== $kinderen ) {
				$regel['level']   = 'blokkeer' === $regel['level'] ? 'blokkeer' : 'riskant';
				$regel['notes'][] = sprintf(
					/* translators: 1: proposed number, 2: actual children. */
					__( 'je zet columns op %1$d terwijl dit blok %2$d kindblokken heeft. De layoutklasse klopt dan niet met de inhoud.', 'mcp-abilities-kadence' ),
					(int) $nieuw,
					$kinderen
				);
			}
		}

		// maxWidth op een Sectie doet iets anders dan de naam belooft zodra de
		// Sectie in een rij staat. Kadence schrijft er namelijk auto-marges bij:
		//
		//   .kadence-column{ID} { max-width: 88px; margin-left: auto; margin-right: auto; }
		//
		// Een rij is een CSS-grid, en auto-marges op een grid-item zetten dat
		// item op fit-content. Het blok krimpt dus naar de breedte van zijn
		// tekst in plaats van naar de opgegeven maat. Gemeten op 14-09-2026 bij
		// het datumvierkant van de agendakaart: rastersleuf 88,78px, maxWidth
		// 88px, resultaat 24px — de breedte van het woord SEP.
		//
		// Bewust 'ok' en geen 'riskant'. Buiten een raster doet maxWidth precies
		// wat je verwacht, en riskant zou het attribuut in het geheel
		// onbruikbaar maken. Wat hier ontbrak was niet een verbod maar een
		// waarschuwing: de meting kostte drie schrijfrondes voordat duidelijk
		// was waar de 24px vandaan kwam.
		if ( 'maxWidth' === $attr && 'kadence/column' === $bloknaam ) {
			$leeg = ! is_array( $nieuw ) || '' === implode( '', array_map( 'strval', $nieuw ) );

			if ( ! $leeg ) {
				$regel['notes'][] = __( 'let op: Kadence schrijft bij maxWidth ook margin-left/right: auto weg. Staat deze Sectie als kolom in een rij, dan is hij een grid-item, en auto-marges krimpen een grid-item naar de breedte van zijn inhoud in plaats van naar de opgegeven maat. Voor een vaste kolombreedte in een rij gebruik je firstColumnWidth en de bijbehorende Tablet/Mobile-varianten op de rij zelf; maxWidth werkt wel zoals verwacht bij een Sectie die niet in een rij staat.', 'mcp-abilities-kadence' );
			}
		}

		return $regel;
	}

	/**
	 * Vergelijk twee blokken.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function diff_blocks( $input = array() ) {
		$post_a = self::haal_post( isset( $input['post_id_a'] ) ? $input['post_id_a'] : 0 );

		if ( is_wp_error( $post_a ) ) {
			return $post_a;
		}

		$post_b = isset( $input['post_id_b'] ) && (int) $input['post_id_b'] > 0
			? self::haal_post( $input['post_id_b'] )
			: $post_a;

		if ( is_wp_error( $post_b ) ) {
			return $post_b;
		}

		$id_a = isset( $input['unique_id_a'] ) ? trim( (string) $input['unique_id_a'] ) : '';
		$id_b = isset( $input['unique_id_b'] ) ? trim( (string) $input['unique_id_b'] ) : '';

		if ( '' === $id_a || '' === $id_b ) {
			return new WP_Error(
				'kadence_mcp_missing_unique_id',
				__( 'Geef zowel unique_id_a als unique_id_b op. Die vind je met inspect-post.', 'mcp-abilities-kadence' )
			);
		}

		$blok_a = Kadence_MCP_Inventory::zoek_op_unique_id( parse_blocks( $post_a->post_content ), $id_a );
		$blok_b = Kadence_MCP_Inventory::zoek_op_unique_id( parse_blocks( $post_b->post_content ), $id_b );

		foreach ( array( 'a' => $blok_a, 'b' => $blok_b ) as $letter => $blok ) {
			if ( null === $blok ) {
				return new WP_Error(
					'kadence_mcp_block_not_in_post',
					sprintf(
						/* translators: 1: A or B, 2: uniqueID, 3: post ID. */
						__( 'Blok %1$s met uniqueID "%2$s" staat niet in post %3$d.', 'mcp-abilities-kadence' ),
						strtoupper( $letter ),
						'a' === $letter ? $id_a : $id_b,
						'a' === $letter ? $post_a->ID : $post_b->ID
					)
				);
			}
		}

		$attrs_a = isset( $blok_a['attrs'] ) && is_array( $blok_a['attrs'] ) ? $blok_a['attrs'] : array();
		$attrs_b = isset( $blok_b['attrs'] ) && is_array( $blok_b['attrs'] ) ? $blok_b['attrs'] : array();

		$sleutels = array_unique( array_merge( array_keys( $attrs_a ), array_keys( $attrs_b ) ) );
		sort( $sleutels );

		$verschillen = array();
		$gelijk      = 0;

		foreach ( $sleutels as $sleutel ) {
			$in_a = array_key_exists( $sleutel, $attrs_a );
			$in_b = array_key_exists( $sleutel, $attrs_b );

			$waarde_a = $in_a ? $attrs_a[ $sleutel ] : null;
			$waarde_b = $in_b ? $attrs_b[ $sleutel ] : null;

			// Vergelijk op de gecodeerde vorm: dan tellen geneste arrays mee
			// zonder dat de volgorde van sleutels roet in het eten gooit.
			if ( $in_a === $in_b && wp_json_encode( $waarde_a ) === wp_json_encode( $waarde_b ) ) {
				++$gelijk;
				continue;
			}

			$verschillen[] = array(
				'attribute' => $sleutel,
				'group'     => Kadence_MCP_Inventory::groep_van_attribuut( $sleutel ),
				'a'         => $in_a ? $waarde_a : null,
				'b'         => $in_b ? $waarde_b : null,
				'only_in'   => $in_a && $in_b ? '' : ( $in_a ? 'a' : 'b' ),
			);
		}

		// Op groep sorteren, want dat is hoe iemand ernaar kijkt: eerst alle
		// achtergrondverschillen bij elkaar, dan alle spacing.
		usort(
			$verschillen,
			static function ( $x, $y ) {
				$vergelijk = strcmp( $x['group'], $y['group'] );

				return 0 !== $vergelijk ? $vergelijk : strcmp( $x['attribute'], $y['attribute'] );
			}
		);

		$resultaat = array(
			'a' => array(
				'post_id'   => $post_a->ID,
				'unique_id' => $id_a,
				'block'     => isset( $blok_a['blockName'] ) ? $blok_a['blockName'] : '',
				'children'  => isset( $blok_a['innerBlocks'] ) ? count( $blok_a['innerBlocks'] ) : 0,
			),
			'b' => array(
				'post_id'   => $post_b->ID,
				'unique_id' => $id_b,
				'block'     => isset( $blok_b['blockName'] ) ? $blok_b['blockName'] : '',
				'children'  => isset( $blok_b['innerBlocks'] ) ? count( $blok_b['innerBlocks'] ) : 0,
			),
			'differences' => $verschillen,
			'identical'   => $gelijk,
		);

		if ( ! empty( $input['include_children'] ) ) {
			$resultaat['structure'] = array(
				'a' => self::tel_structuur( isset( $blok_a['innerBlocks'] ) ? $blok_a['innerBlocks'] : array() ),
				'b' => self::tel_structuur( isset( $blok_b['innerBlocks'] ) ? $blok_b['innerBlocks'] : array() ),
			);
		}

		$resultaat['status'] = self::diff_status( $blok_a, $blok_b, $verschillen );

		return $resultaat;
	}

	/**
	 * Verklaar de uitkomst van diff-blocks.
	 *
	 * @param array $blok_a      Blok A.
	 * @param array $blok_b      Blok B.
	 * @param array $verschillen De gevonden verschillen.
	 *
	 * @return string
	 */
	private static function diff_status( $blok_a, $blok_b, $verschillen ) {
		$naam_a = isset( $blok_a['blockName'] ) ? $blok_a['blockName'] : '';
		$naam_b = isset( $blok_b['blockName'] ) ? $blok_b['blockName'] : '';

		if ( $naam_a !== $naam_b ) {
			return sprintf(
				/* translators: 1: block name A, 2: block name B. */
				__( 'let op — dit zijn verschillende bloktypes (%1$s tegenover %2$s), dus de attributen zijn niet één op één vergelijkbaar.', 'mcp-abilities-kadence' ),
				$naam_a,
				$naam_b
			);
		}

		if ( empty( $verschillen ) ) {
			return __( 'geen verschillen — beide blokken hebben exact dezelfde opgeslagen attributen. Zit het verschil in de weergave, kijk dan naar de kindblokken of naar het bovenliggende blok.', 'mcp-abilities-kadence' );
		}

		return sprintf(
			/* translators: %d: number of differences. */
			__( '%d verschillen gevonden. Een waarde null betekent "niet ingesteld", dus daar geldt de standaardwaarde van het blok.', 'mcp-abilities-kadence' ),
			count( $verschillen )
		);
	}

	/**
	 * Tel bloknamen per niveau onder een blok.
	 *
	 * @param array $blokken De kindblokken.
	 * @param int   $diepte  Interne teller.
	 * @param array $telling Interne verzamelaar.
	 *
	 * @return array
	 */
	private static function tel_structuur( $blokken, $diepte = 0, &$telling = array() ) {
		foreach ( $blokken as $blok ) {
			$naam = isset( $blok['blockName'] ) ? (string) $blok['blockName'] : '';

			if ( '' === $naam ) {
				continue;
			}

			$sleutel = $diepte . ':' . $naam;

			$telling[ $sleutel ] = isset( $telling[ $sleutel ] ) ? $telling[ $sleutel ] + 1 : 1;

			if ( ! empty( $blok['innerBlocks'] ) ) {
				self::tel_structuur( $blok['innerBlocks'], $diepte + 1, $telling );
			}
		}

		return $telling;
	}


	/**
	 * Splits de innerHTML van een tekstblok in omhulsel en inhoud.
	 *
	 * Dit is de kern van de veiligheid van set-text. De innerHTML van een
	 * tekstblok ziet er zo uit:
	 *
	 *     <h1 class="kt-adv-heading1471_12622c-65 ..." data-kb-block="...">Tekst</h1>
	 *
	 * Die klassen en dat data-attribuut worden door save() opnieuw opgebouwd uit
	 * de attributen. Raak je ze aan, dan wijkt de opgeslagen markup af van wat
	 * save() zou produceren en meldt Gutenberg bij het openen "deze blokinhoud
	 * is onverwacht gewijzigd". Daarom wordt hier uitsluitend het deel tussen de
	 * buitenste tags vervangen en blijft de tag zelf letterlijk staan.
	 *
	 * Weigert bij alles wat niet precies één omhullend element is: twee
	 * elementen naast elkaar, kale tekst zonder tag, of een leeg blok.
	 *
	 * @param string $inner De innerHTML.
	 *
	 * @return array{open:string,inhoud:string,sluit:string,tag:string}|WP_Error
	 */
	private static function splits_omhulsel( $inner ) {
		$inner = (string) $inner;

		if ( '' === trim( $inner ) ) {
			return new WP_Error(
				'kadence_mcp_text_empty_block',
				__( 'Dit blok heeft geen innerHTML. Er is dus geen tekst om te vervangen — waarschijnlijk staat de tekst in een attribuut en hoort set-attributes gebruikt te worden.', 'mcp-abilities-kadence' )
			);
		}

		if ( ! preg_match( '#^(\s*<([a-zA-Z][a-zA-Z0-9]*)\b[^>]*>)(.*)(</\2>\s*)$#s', $inner, $m ) ) {
			return new WP_Error(
				'kadence_mcp_text_not_single_element',
				__( 'De innerHTML van dit blok is niet één omhullend element. set-text vervangt alleen de inhoud binnen de buitenste tag en laat die tag ongemoeid; bij een andere vorm zou dat de blokvalidatie van Gutenberg breken. Bekijk de markup met get-raw-markup en pas het blok handmatig aan.', 'mcp-abilities-kadence' )
			);
		}

		$inhoud = $m[3];
		$tag    = strtolower( $m[2] );

		// De inhoud mag geen tag van dezelfde soort meer bevatten, en er zijn
		// twee manieren waarop dat misgaat. Ze vragen een ander antwoord, dus
		// worden ze uit elkaar gehouden in plaats van allebei "op hetzelfde
		// niveau" genoemd — wat bij een kolom aantoonbaar onjuist was.
		//
		// Loop door de tags en tel mee. Zakt de teller onder nul, dan sloot er
		// een tag die hierbinnen nooit geopend is: de hebzuchtige .* hierboven
		// heeft dan over een zusterelement heen gegrepen en $inhoud klopt niet.
		// Blijft de teller op nul eindigen zonder ooit negatief te worden, dan
		// is het een net geneste structuur — bij Kadence meestal een kolom,
		// met zijn kt-inside-inner-col erin.
		if ( preg_match_all( '#<(/?)' . preg_quote( $tag, '#' ) . '\b[^>]*>#i', $inhoud, $treffers, PREG_SET_ORDER ) ) {
			$diepte   = 0;
			$zusters  = false;

			foreach ( $treffers as $treffer ) {
				$diepte += ( '/' === $treffer[1] ) ? -1 : 1;

				if ( $diepte < 0 ) {
					$zusters = true;
					break;
				}
			}

			if ( $zusters ) {
				return new WP_Error(
					'kadence_mcp_text_sibling_elements',
					sprintf(
						/* translators: %s: HTML tag name. */
						__( 'De innerHTML bevat meerdere <%s>-elementen naast elkaar. Welke daarvan de tekst draagt is niet vast te stellen zonder te raden, en dat doet deze plug-in niet.', 'mcp-abilities-kadence' ),
						$tag
					)
				);
			}

			return new WP_Error(
				'kadence_mcp_text_nested_elements',
				sprintf(
					/* translators: %s: HTML tag name. */
					__( 'De innerHTML bevat een genest <%1$s>-element. Dit is een container, geen tekstblok — de tekst hoort in het kindblok dat hem draagt. Bij een Kadence-kolom is dat de binnenste laag (kt-inside-inner-col), en die zou je hier met tekst overschrijven.', 'mcp-abilities-kadence' ),
					$tag
				)
			);
		}

		return array(
			'open'   => $m[1],
			'inhoud' => $inhoud,
			'sluit'  => $m[4],
			'tag'    => $tag,
		);
	}

	/**
	 * Vervang de innerHTML van één blok in de boom.
	 *
	 * @param array  $blokken   De blokken.
	 * @param string $unique_id De uniqueID.
	 * @param string $inner     De nieuwe innerHTML.
	 *
	 * @return array
	 */
	private static function vervang_inner( $blokken, $unique_id, $inner ) {
		foreach ( $blokken as $i => $blok ) {
			$huidig = isset( $blok['attrs']['uniqueID'] ) ? (string) $blok['attrs']['uniqueID'] : '';

			if ( $huidig === (string) $unique_id ) {
				$blokken[ $i ]['innerHTML'] = $inner;

				// innerContent draagt dezelfde string een tweede keer, en
				// serialize_block() gebruikt uitsluitend die. Alleen innerHTML
				// bijwerken levert een schrijfactie op die niets doet.
				$blokken[ $i ]['innerContent'] = array( $inner );
				continue;
			}

			if ( ! empty( $blok['innerBlocks'] ) ) {
				$blokken[ $i ]['innerBlocks'] = self::vervang_inner( $blok['innerBlocks'], $unique_id, $inner );
			}
		}

		return $blokken;
	}

	/**
	 * Alle bladblokken waarvan de tekst letterlijk gelijk is aan de zoektekst.
	 *
	 * Bestaat omdat niet elk tekstblok een uniqueID heeft. Kadence deelt die uit,
	 * WordPress niet: een core/list-item in een accordeon draagt er geen, en was
	 * daarmee tot 1.17.0 met geen enkele schrijf-ability te bereiken. Concreet: een em dash die in een
	 * accordeon bleef staan terwijl acht andere wel opgeruimd konden worden.
	 *
	 * Er wordt bewust op de VOLLEDIGE tekst gematcht en niet op een deel ervan.
	 * Een deelmatch nodigt uit tot zoeken-en-vervangen over een hele pagina, en
	 * dan bepaalt de toevallige volgorde van de boom wat er geraakt wordt. Met
	 * een volledige match is het adres net zo precies als een uniqueID, en bij
	 * twee gelijke teksten weigert set_text in plaats van te kiezen.
	 *
	 * @param array $blokken De boom.
	 * @param string $zoek   De gezochte tekst.
	 * @param array  $pad    Het pad tot hier, als lijst van kindindexen.
	 *
	 * @return array Lijst van array( 'pad' => int[], 'blok' => array ).
	 */
	private static function zoek_op_tekst( $blokken, $zoek, $pad = array() ) {
		$treffers = array();

		foreach ( $blokken as $i => $blok ) {
			$hier = array_merge( $pad, array( (int) $i ) );

			if ( empty( $blok['innerBlocks'] ) ) {
				$inhoud = Kadence_MCP_Inventory::binnenhtml_uit_blok( $blok );

				if ( '' !== $inhoud && trim( $inhoud ) === trim( (string) $zoek ) ) {
					$treffers[] = array( 'pad' => $hier, 'blok' => $blok );
				}

				continue;
			}

			$treffers = array_merge( $treffers, self::zoek_op_tekst( $blok['innerBlocks'], $zoek, $hier ) );
		}

		return $treffers;
	}

	/**
	 * Het blok op een pad van kindindexen.
	 *
	 * @param array $blokken De boom.
	 * @param array $pad     De indexen.
	 *
	 * @return array|null
	 */
	private static function blok_op_pad( $blokken, $pad ) {
		$huidig = $blokken;
		$blok   = null;

		foreach ( $pad as $index ) {
			if ( ! isset( $huidig[ $index ] ) ) {
				return null;
			}

			$blok   = $huidig[ $index ];
			$huidig = isset( $blok['innerBlocks'] ) ? $blok['innerBlocks'] : array();
		}

		return $blok;
	}

	/**
	 * Vervang de innerHTML van het blok op een pad.
	 *
	 * Zelfde vorm als vervang_inner(), maar geadresseerd op positie in plaats van
	 * op uniqueID. innerContent gaat mee, want serialize_block() leest uitsluitend
	 * die: alleen innerHTML bijwerken levert een schrijfactie op die niets doet.
	 *
	 * @param array $blokken De boom.
	 * @param array $pad     De indexen.
	 * @param string $inner  De nieuwe innerHTML.
	 *
	 * @return array
	 */
	private static function vervang_inner_op_pad( $blokken, $pad, $inner ) {
		if ( empty( $pad ) ) {
			return $blokken;
		}

		$index = (int) array_shift( $pad );

		if ( ! isset( $blokken[ $index ] ) ) {
			return $blokken;
		}

		if ( empty( $pad ) ) {
			$blokken[ $index ]['innerHTML']    = $inner;
			$blokken[ $index ]['innerContent'] = array( $inner );

			return $blokken;
		}

		$kinderen = isset( $blokken[ $index ]['innerBlocks'] ) ? $blokken[ $index ]['innerBlocks'] : array();

		$blokken[ $index ]['innerBlocks'] = self::vervang_inner_op_pad( $kinderen, $pad, $inner );

		return $blokken;
	}

	/**
	 * Schrijf de tekst van een blok.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function set_text( $input = array() ) {
		$post = self::haal_post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$unique_id = isset( $input['unique_id'] ) ? trim( (string) $input['unique_id'] ) : '';
		$zoek      = isset( $input['match_text'] ) ? (string) $input['match_text'] : '';
		$tekst     = isset( $input['text'] ) ? (string) $input['text'] : '';
		$token     = isset( $input['token'] ) ? (string) $input['token'] : '';

		if ( '' === $unique_id && '' === trim( $zoek ) ) {
			return new WP_Error(
				'kadence_mcp_missing_unique_id',
				__( 'Geef aan welk blok het is: unique_id voor een Kadence-blok, of match_text met de huidige tekst voor een core-blok dat geen uniqueID heeft.', 'mcp-abilities-kadence' )
			);
		}

		if ( '' !== $unique_id && '' !== trim( $zoek ) ) {
			return new WP_Error(
				'kadence_mcp_two_addresses',
				__( 'Geef unique_id of match_text, niet allebei. Wijzen ze naar verschillende blokken, dan valt dat hier niet op te lossen, en stilzwijgend een van beide laten winnen is precies het gokwerk dat hier niet hoort.', 'mcp-abilities-kadence' )
			);
		}

		if ( '' === trim( $tekst ) ) {
			return new WP_Error(
				'kadence_mcp_text_empty',
				__( 'Geef de nieuwe tekst op. Een blok leegmaken kan hier bewust niet: een leeg tekstblok is in de editor niet meer aan te klikken en dus lastig te herstellen. Verwijder het blok in dat geval in de editor.', 'mcp-abilities-kadence' )
			);
		}

		$boom = parse_blocks( $post->post_content );
		$pad  = array();

		if ( '' !== $unique_id ) {
			$blok = Kadence_MCP_Inventory::zoek_op_unique_id( $boom, $unique_id );

			if ( null === $blok ) {
				return new WP_Error(
					'kadence_mcp_block_not_in_post',
					sprintf(
						/* translators: 1: uniqueID, 2: post ID. */
						__( 'Blok met uniqueID "%1$s" staat niet in post %2$d.', 'mcp-abilities-kadence' ),
						$unique_id,
						$post->ID
					)
				);
			}
		} else {
			$treffers = self::zoek_op_tekst( $boom, $zoek );

			if ( empty( $treffers ) ) {
				return new WP_Error(
					'kadence_mcp_text_not_found',
					sprintf(
						/* translators: 1: the searched text, 2: post ID. */
						__( 'Geen blok in post %2$d draagt precies de tekst "%1$s". match_text moet de HELE tekst van het blok zijn, niet een deel ervan; haal hem letterlijk op met get-raw-markup of inspect-post.', 'mcp-abilities-kadence' ),
						$zoek,
						$post->ID
					)
				);
			}

			if ( count( $treffers ) > 1 ) {
				return new WP_Error(
					'kadence_mcp_text_ambiguous',
					sprintf(
						/* translators: 1: number of matches, 2: the searched text. */
						__( '%1$d blokken dragen de tekst "%2$s". Er is niets geschreven: welke bedoeld is valt hier niet uit op te maken. Zoek het blok op met inspect-post en adresseer het op uniqueID.', 'mcp-abilities-kadence' ),
						count( $treffers ),
						$zoek
					)
				);
			}

			$pad  = $treffers[0]['pad'];
			$blok = $treffers[0]['blok'];
		}

		if ( ! empty( $blok['innerBlocks'] ) ) {
			return new WP_Error(
				'kadence_mcp_text_has_children',
				__( 'Dit blok bevat andere blokken. Schrijf de tekst naar het kindblok dat hem draagt, niet naar de container — anders zou de hele inhoud vervangen worden.', 'mcp-abilities-kadence' )
			);
		}

		$delen = self::splits_omhulsel( isset( $blok['innerHTML'] ) ? $blok['innerHTML'] : '' );

		if ( is_wp_error( $delen ) ) {
			return $delen;
		}

		$schoon = Kadence_MCP_Inventory::schoon_tekst( $tekst );
		$nieuw  = $delen['open'] . $schoon . $delen['sluit'];

		$verwijderd = array();

		if ( $schoon !== $tekst ) {
			$verwijderd[] = __( 'Er is HTML uit de tekst verwijderd die hier niet is toegestaan. Toegestaan zijn alleen opmaaktags als strong, em, a, br, span en mark.', 'mcp-abilities-kadence' );
		}

		// Het adres is of een uniqueID of een pad van kindindexen. Beide gaan in
		// het token: een goedkeuring voor het ene blok mag niet gelden voor het
		// andere, ook niet als de nieuwe tekst toevallig dezelfde is.
		$adres = '' !== $unique_id ? $unique_id : '__pad__:' . implode( '.', $pad );

		$grondslag = Kadence_MCP_Inventory::schrijf_token( $post, $adres, array( '__set_text__' => $nieuw ) );

		// Zelfde afweging als bij style-blocks: expect_modified vervangt de
		// tussenstap zonder de bescherming op te geven. Een tekstwijziging
		// verandert niets aan de structuur — de omhullende tag blijft letterlijk
		// staan — dus wat het token hier beschermt is uitsluitend "schrijf niet
		// op een versie die er niet meer is", en dat doet expect_modified ook.
		$verwacht_gewijzigd = isset( $input['expect_modified'] ) ? trim( (string) $input['expect_modified'] ) : '';

		if ( '' === $token && '' !== $verwacht_gewijzigd ) {
			if ( $verwacht_gewijzigd !== (string) $post->post_modified_gmt ) {
				return new WP_Error(
					'kadence_mcp_stale_write',
					sprintf(
						/* translators: 1: expected timestamp, 2: actual timestamp. */
						__( 'De post is gewijzigd sinds jij hem las. Jij verwachtte versie %1$s, er staat %2$s. Er is niets geschreven: lees de tekst opnieuw en bepaal of je voorstel nog klopt.', 'mcp-abilities-kadence' ),
						$verwacht_gewijzigd,
						(string) $post->post_modified_gmt
					)
				);
			}

			$token = $grondslag;
		}

		// Zonder token is dit een voorstel: laten zien wat het wordt, niets
		// opslaan, en het token meegeven waarmee het wel mag. Bij tekst vallen
		// toetsen en tonen samen — er is geen schema om een tekst tegen te
		// houden, dus de enige zinvolle controle is de markup zelf lezen.
		if ( '' === $token ) {
			return array(
				'block'   => array(
					'post_id'   => $post->ID,
					'unique_id' => '' !== $unique_id ? $unique_id : $adres,
					'block'     => isset( $blok['blockName'] ) ? $blok['blockName'] : '',
					'tag'       => $delen['tag'],
				),
				'before'  => $delen['inhoud'],
				'after'   => $schoon,
				'markup'  => $nieuw,
				'notes'   => $verwijderd,
				'written' => false,
				'token'   => $grondslag,
				'modified' => (string) $post->post_modified_gmt,
				'status'  => __( 'Voorstel, er is NIETS opgeslagen. Lees before en after na en roep deze ability daarna opnieuw aan met het token erbij om het echt te schrijven. Het token vervalt zodra de post wijzigt of de tekst anders is. In één aanroep kan ook: geef expect_modified mee met de waarde uit het veld modified.', 'mcp-abilities-kadence' ),
			);
		}

		if ( ! Kadence_MCP_Capabilities::current_user_can( Kadence_MCP_Capabilities::WRITE ) ) {
			return new WP_Error(
				'kadence_mcp_write_denied',
				__( 'Je hebt de capability kadence_mcp_write niet. Die wordt bij installatie aan niemand gegeven en moet bewust worden toegekend.', 'mcp-abilities-kadence' ),
				array( 'status' => 403 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error(
				'kadence_mcp_edit_denied',
				__( 'Je mag deze post volgens WordPress zelf niet bewerken.', 'mcp-abilities-kadence' ),
				array( 'status' => 403 )
			);
		}

		if ( ! hash_equals( $grondslag, $token ) ) {
			return new WP_Error(
				'kadence_mcp_invalid_token',
				Kadence_MCP_Inventory::token_reden( $token, $grondslag, $post )
			);
		}

		$gewijzigd = '' !== $unique_id
			? self::vervang_inner( $boom, $unique_id, $nieuw )
			: self::vervang_inner_op_pad( $boom, $pad, $nieuw );

		$content = Kadence_MCP_Inventory::serialiseer( $gewijzigd );

		$resultaat = wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_content' => wp_slash( $content ),
			),
			true
		);

		if ( is_wp_error( $resultaat ) ) {
			return $resultaat;
		}

		clean_post_cache( $post->ID );

		$na      = get_post( $post->ID );
		$na_boom = $na ? parse_blocks( $na->post_content ) : array();

		$na_blok = '' !== $unique_id
			? Kadence_MCP_Inventory::zoek_op_unique_id( $na_boom, $unique_id )
			: self::blok_op_pad( $na_boom, $pad );

		$na_inner = $na_blok && isset( $na_blok['innerHTML'] ) ? (string) $na_blok['innerHTML'] : '';

		$klopt = ( $na_inner === $nieuw );

		return array(
			'block'   => array(
				'post_id'   => $post->ID,
				'unique_id' => '' !== $unique_id ? $unique_id : $adres,
				'block'     => isset( $blok['blockName'] ) ? $blok['blockName'] : '',
				'tag'       => $delen['tag'],
			),
			'before'  => $delen['inhoud'],
			'after'   => $schoon,
			'markup'  => $na_inner,
			'notes'   => $verwijderd,
			'written' => true,
			'token'   => '',
			'status'  => $klopt
				? __( 'geschreven en teruggelezen: wat er staat komt overeen met wat er bedoeld was. Er is een revisie gemaakt, dus terugdraaien kan.', 'mcp-abilities-kadence' )
				: __( 'LET OP: er is geschreven, maar bij het teruglezen wijkt de innerHTML af van wat er verstuurd is. Kadence herschrijft post_content op wp_insert_post_data, dus mogelijk heeft een filter ingegrepen. Controleer de markup in het veld markup.', 'mcp-abilities-kadence' ),
		);
	}

	/**
	 * Meerdere blokken in één keer opmaken.
	 *
	 * Bestaat omdat set-attributes één blok per aanroep doet, en dat werkt niet
	 * voor de opdracht waar dit voor bedoeld is. Een hero opmaken raakt zes
	 * blokken: de rij, de kolom, twee tekstblokken, de knoppenrij en de knop.
	 * Dat is met validate-write plus set-attributes veertien aanroepen, en bij
	 * elke tussenstap verandert post_modified — waardoor elk volgend token
	 * vervalt en je opnieuw moet toetsen. Niet onhandig, maar onwerkbaar.
	 *
	 * De toetsing is niet opnieuw geschreven: per blok wordt letterlijk
	 * validate_write() aangeroepen. Een tweede kopie van die logica zou na de
	 * eerste reparatie uit elkaar lopen met het origineel, en dan keurt de ene
	 * goed wat de andere tegenhoudt.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function style_blocks( $input = array() ) {
		$post = self::haal_post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$blokken = isset( $input['blocks'] ) && is_array( $input['blocks'] ) ? $input['blocks'] : array();
		$token   = isset( $input['token'] ) ? (string) $input['token'] : '';

		if ( empty( $blokken ) ) {
			return new WP_Error(
				'kadence_mcp_style_no_blocks',
				__( 'Geef minstens één blok op, als een lijst van objecten met unique_id en attributes.', 'mcp-abilities-kadence' )
			);
		}

		// Normaliseren naar uniqueID => attributen, en meteen dubbele vermeldingen
		// eruit. Twee keer hetzelfde blok in één verzoek betekent dat de tweede
		// de eerste stil overschrijft, en dan is niet te zien welke won.
		$voorstellen = array();

		foreach ( $blokken as $regel ) {
			if ( ! is_array( $regel ) ) {
				continue;
			}

			$id    = isset( $regel['unique_id'] ) ? trim( (string) $regel['unique_id'] ) : '';
			$attrs = isset( $regel['attributes'] ) && is_array( $regel['attributes'] ) ? $regel['attributes'] : array();

			if ( '' === $id || empty( $attrs ) ) {
				return new WP_Error(
					'kadence_mcp_style_incomplete',
					__( 'Elk item heeft een unique_id en een niet-lege attributes nodig.', 'mcp-abilities-kadence' )
				);
			}

			if ( isset( $voorstellen[ $id ] ) ) {
				return new WP_Error(
					'kadence_mcp_style_duplicate_block',
					sprintf(
						/* translators: %s: uniqueID. */
						__( 'Blok "%s" staat twee keer in de lijst. Voeg de attributen samen tot één item; anders is niet te zien welke van de twee wint.', 'mcp-abilities-kadence' ),
						$id
					)
				);
			}

			$voorstellen[ $id ] = $attrs;
		}

		// Toetsen via validate_write, blok voor blok. Die parst de post per
		// aanroep opnieuw; dat is verspilling maar het is één request, en het
		// is de prijs voor maar één plek met toetslogica.
		$rapport  = array();
		$blokkeer = false;
		$riskant  = false;

		foreach ( $voorstellen as $id => $attrs ) {
			$uitslag = self::validate_write(
				array(
					'post_id'    => $post->ID,
					'unique_id'  => $id,
					'attributes' => $attrs,
				)
			);

			if ( is_wp_error( $uitslag ) ) {
				return $uitslag;
			}

			$rapport[] = array(
				'unique_id' => $id,
				'block'     => isset( $uitslag['block']['block'] ) ? $uitslag['block']['block'] : '',
				'verdict'   => $uitslag['verdict'],
				'checks'    => $uitslag['checks'],
			);

			if ( 'blokkeer' === $uitslag['verdict'] ) {
				$blokkeer = true;
			} elseif ( 'riskant' === $uitslag['verdict'] ) {
				$riskant = true;
			}
		}

		// Alles of niets. Eén blok dat niet deugt maakt de hele set verdacht:
		// half doorvoeren laat de pagina in een staat achter die niemand heeft
		// bedoeld en die niet uit één revisie terug te draaien is.
		if ( $blokkeer || $riskant ) {
			return array(
				'post'    => array( 'id' => $post->ID, 'title' => get_the_title( $post ) ),
				'results' => $rapport,
				'verdict' => $blokkeer ? 'blokkeer' : 'riskant',
				'written' => false,
				'token'   => '',
				'status'  => $blokkeer
					? __( 'NIET schrijven, en er is geen token. Minstens één blok heeft een bezwaar dat de wijziging zinloos of schadelijk maakt. Er wordt niets half doorgevoerd: repareer dat blok of laat het uit de lijst.', 'mcp-abilities-kadence' )
					: __( 'Geen token. Minstens één blok is riskant, en dat hoort een mens te wegen. Haal dat blok uit de lijst en doe het apart met set-attributes, of pas het voorstel aan.', 'mcp-abilities-kadence' ),
			);
		}

		$grondslag = Kadence_MCP_Inventory::schrijf_token( $post, '__style__', $voorstellen );

		// De tweede weg naar binnen: expect_modified in plaats van een token.
		//
		// Het token bestaat om twee dingen te doen. Het eerste is een MENS de
		// wijziging laten zien voordat hij gebeurt. Het tweede is voorkomen dat
		// je schrijft op een versie die er niet meer is. Bij een opmaakwijziging
		// die al door validate_write is gekomen is dat eerste doel zwak — er is
		// een schema, elke waarde is getoetst, en de uitkomst staat hierboven in
		// results — terwijl de kosten hoog zijn: elke wijziging is twee
		// aanroepen, en op 14-09-2026 kostte het gelijktrekken van 31 hero's
		// daardoor 62 aanroepen in plaats van 31.
		//
		// expect_modified houdt het tweede doel volledig overeind. De aanroeper
		// zegt op welke versie hij denkt te schrijven; klopt die niet meer, dan
		// wordt er niets geschreven. Dat is dezelfde bescherming, in één
		// aanroep.
		//
		// Bewust ALLEEN hier en bij set-text: dit zijn de twee schrijvers die
		// niets aan de STRUCTUUR veranderen. remove-blocks, replace-block,
		// insert-blocks en create-page houden hun verplichte token, want daar
		// gaat het eerste doel wél op — je kunt niet uit een schema aflezen of
		// het de bedoeling was dat die drie blokken verdwijnen.
		$verwacht_gewijzigd = isset( $input['expect_modified'] ) ? trim( (string) $input['expect_modified'] ) : '';

		if ( '' === $token && '' !== $verwacht_gewijzigd ) {
			if ( $verwacht_gewijzigd !== (string) $post->post_modified_gmt ) {
				return new WP_Error(
					'kadence_mcp_stale_write',
					sprintf(
						/* translators: 1: expected timestamp, 2: actual timestamp. */
						__( 'De post is gewijzigd sinds jij hem las. Jij verwachtte versie %1$s, er staat %2$s. Er is niets geschreven: lees de post opnieuw en bepaal of je voorstel nog klopt.', 'mcp-abilities-kadence' ),
						$verwacht_gewijzigd,
						(string) $post->post_modified_gmt
					)
				);
			}

			$token = $grondslag;
		}

		if ( '' === $token ) {
			return array(
				'post'    => array( 'id' => $post->ID, 'title' => get_the_title( $post ) ),
				'results' => $rapport,
				'verdict' => 'veilig',
				'written' => false,
				'token'   => $grondslag,
				'modified' => (string) $post->post_modified_gmt,
				'status'  => sprintf(
					/* translators: %d: number of blocks. */
					__( 'Alle %d blokken zijn veilig, er is NIETS opgeslagen. Roep opnieuw aan met het token om ze in één keer te schrijven — dat wordt één opslag en dus één revisie. Ken je de post al, dan kan het ook in één aanroep: geef expect_modified mee met de waarde uit dit veld modified.', 'mcp-abilities-kadence' ),
					count( $voorstellen )
				),
			);
		}

		if ( ! Kadence_MCP_Capabilities::current_user_can( Kadence_MCP_Capabilities::WRITE ) ) {
			return new WP_Error(
				'kadence_mcp_write_denied',
				__( 'Je hebt de capability kadence_mcp_write niet. Die wordt bij installatie aan niemand gegeven en moet bewust worden toegekend.', 'mcp-abilities-kadence' ),
				array( 'status' => 403 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error(
				'kadence_mcp_edit_denied',
				__( 'Je mag deze post volgens WordPress zelf niet bewerken.', 'mcp-abilities-kadence' ),
				array( 'status' => 403 )
			);
		}

		if ( ! hash_equals( $grondslag, $token ) ) {
			return new WP_Error(
				'kadence_mcp_invalid_token',
				Kadence_MCP_Inventory::token_reden( $token, $grondslag, $post )
			);
		}

		$boom       = parse_blocks( $post->post_content );
		$weggelaten = array();
		$standaard  = array();

		foreach ( $voorstellen as $id => $attrs ) {
			$blok = Kadence_MCP_Inventory::zoek_op_unique_id( $boom, $id );

			if ( null === $blok ) {
				return new WP_Error(
					'kadence_mcp_block_not_in_post',
					sprintf(
						/* translators: 1: uniqueID, 2: post ID. */
						__( 'Blok "%1$s" staat niet in post %2$d.', 'mcp-abilities-kadence' ),
						$id,
						$post->ID
					)
				);
			}

			$bloknaam = isset( $blok['blockName'] ) ? (string) $blok['blockName'] : '';
			$samen    = array_merge(
				isset( $blok['attrs'] ) && is_array( $blok['attrs'] ) ? $blok['attrs'] : array(),
				$attrs
			);

			$genormaliseerd = Kadence_MCP_Inventory::normaliseer_attributen( $bloknaam, $samen );

			if ( ! empty( $genormaliseerd['dropped_unknown'] ) ) {
				$weggelaten[ $id ] = $genormaliseerd['dropped_unknown'];
			}

			// Ook onthouden wat er wegviel omdat het gelijk was aan de
			// standaardwaarde. Dat is geen afwijking maar normalisatie — precies
			// wat de editor ook doet — en het mag dus niet als mismatch worden
			// gemeld. Tot 1.9.1 gebeurde dat wel: maxWidth op ["","",""] zetten
			// leverde 'er is geschreven, maar bij het teruglezen wijkt er iets af'
			// terwijl het weglaten juist de bedoeling was.
			if ( ! empty( $genormaliseerd['dropped_default'] ) ) {
				$standaard[ $id ] = $genormaliseerd['dropped_default'];
			}

			$boom = self::vervang_attrs( $boom, $id, $genormaliseerd['attrs'] );
		}

		// Eén serialisatie, één opslag, één revisie.
		$content = Kadence_MCP_Inventory::serialiseer( $boom );

		$resultaat = wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_content' => wp_slash( $content ),
			),
			true
		);

		if ( is_wp_error( $resultaat ) ) {
			return $resultaat;
		}

		clean_post_cache( $post->ID );

		$na      = get_post( $post->ID );
		$na_boom = $na ? parse_blocks( $na->post_content ) : array();
		$afwijkend = array();

		foreach ( $voorstellen as $id => $attrs ) {
			$na_blok  = Kadence_MCP_Inventory::zoek_op_unique_id( $na_boom, $id );
			$na_attrs = $na_blok && isset( $na_blok['attrs'] ) ? $na_blok['attrs'] : array();

			foreach ( $attrs as $sleutel => $waarde ) {
				// Wat gelijk is aan de standaardwaarde hoort er niet te staan;
				// dat is geen afwijking maar normalisatie.
				$is_weggelaten = ( isset( $weggelaten[ $id ] ) && in_array( $sleutel, $weggelaten[ $id ], true ) )
					|| ( isset( $standaard[ $id ] ) && in_array( $sleutel, $standaard[ $id ], true ) );

				if ( $is_weggelaten ) {
					continue;
				}

				$staat_er = array_key_exists( $sleutel, $na_attrs ) ? $na_attrs[ $sleutel ] : null;

				// Hier stond tot 1.7.3 een 'continue' die precies het geval oversloeg
				// dat ertoe doet: een attribuut dat na het opslaan HELEMAAL WEG is,
				// omdat een filter van Kadence of de normalisatie het heeft laten
				// vallen. Dat is de bug 'metadata werd weggegooid' in zijn zuiverste
				// vorm, en de melding luidde 'geschreven en teruggelezen'. Een
				// ontbrekend attribuut is dus gewoon een mismatch, net als bij
				// set-attributes.

				if ( wp_json_encode( $staat_er ) !== wp_json_encode( $waarde ) ) {
					$afwijkend[] = array(
						'unique_id' => $id,
						'attribute' => $sleutel,
						'intended'  => $waarde,
						'stored'    => $staat_er,
					);
				}
			}
		}

		// Klassen die uit attributen volgen lopen nu mogelijk achter. Meteen
		// melden in plaats van wachten tot iemand verify-markup draait.
		$klassen = Kadence_MCP_Abilities_Build::klassen_controle( $na_boom, array_keys( $voorstellen ) );

		$klassenmelding = '';

		if ( ! empty( $klassen ) ) {
			$ids = array();

			foreach ( $klassen as $bevinding ) {
				$ids[] = (string) $bevinding['unique_id'];
			}

			$klassenmelding = ' ' . sprintf(
				/* translators: %s: comma separated uniqueIDs. */
				__( 'LET OP: bij %s klopt de markup niet meer met de attributen — er is een attribuut gewijzigd waar klassen uit volgen, en die worden hier niet bijgewerkt. Op de voorkant zie je er niets van, in de editor heet dit "ongeldige inhoud". Herbouw die blokken met replace-block.', 'mcp-abilities-kadence' ),
				implode( ', ', $ids )
			);
		}

		return array(
			'post'        => array( 'id' => $post->ID, 'title' => get_the_title( $post ) ),
			'results'     => $rapport,
			'verdict'     => 'veilig',
			'written'     => true,
			'token'       => '',
			'mismatch'    => $afwijkend,
			'class_drift' => $klassen,
			'dropped'     => (object) $weggelaten,
			'defaults'    => (object) $standaard,
			'revision'    => __( 'Er is één revisie gemaakt voor de hele set; terugdraaien zet ze allemaal tegelijk terug.', 'mcp-abilities-kadence' ),
			'status'      => empty( $afwijkend )
				? sprintf(
					/* translators: %d: number of blocks. */
					__( 'geschreven en teruggelezen: %d blokken opgemaakt in één opslag.', 'mcp-abilities-kadence' ),
					count( $voorstellen )
				) . $klassenmelding . Kadence_MCP_Query::facetwaarschuwing( get_post( $post->ID ) )
				: __( 'LET OP: er is geschreven, maar bij het teruglezen wijkt er iets af. Zie mismatch.', 'mcp-abilities-kadence' ) . $klassenmelding,
		);
	}
}
