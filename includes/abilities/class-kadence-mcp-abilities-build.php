<?php
/**
 * Abilities voor het opbouwen van nieuwe secties.
 *
 * @package Kadence_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Secties genereren en invoegen.
 */
class Kadence_MCP_Abilities_Build {

	/**
	 * De definities.
	 *
	 * @return array
	 */
	public static function get_definitions() {
		return array(
			array(
				'name' => 'kadence/list-recipes',
				'args' => array(
					'label'       => __( 'Beschikbare sectiesjablonen', 'mcp-abilities-kadence' ),
					'summary'     => __( 'Welke secties generate-section kan bouwen.', 'mcp-abilities-kadence' ),
					'description' => __( 'Geeft de sjablonen die generate-section kent, met per sjabloon de blokken die het gebruikt en de slots die je kunt vullen. Vraag dit op voordat je een sectie laat bouwen, zodat je de juiste slug en de juiste velden meegeeft. De sjablonen leveren bewust alleen de STRUCTUUR: de juiste blokken, goed genest, met werkende uniqueIDs en verder niets. Er worden geen kleuren, marges of lettergroottes ingevuld — die komen uit de globale stijlen, en een verzonnen waarde is ruis die iemand later moet opsporen. Opmaak doe je erna met set-attributes.', 'mcp-abilities-kadence' ),
					'readonly'    => true,
					'idempotent'  => true,
					'input_schema' => array(
						'type'                 => 'object',
						'properties'           => array(),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'recipes' => array( 'type' => 'array' ),
							'status'  => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'list_recipes' ),
				),
			),
			array(
				'name' => 'kadence/generate-section',
				'args' => array(
					'label'       => __( 'Een sectie opbouwen', 'mcp-abilities-kadence' ),
					'summary'     => __( 'Bouwt geldige Kadence-markup uit een sjabloon. Slaat niets op.', 'mcp-abilities-kadence' ),
					'description' => __( 'Bouwt een complete sectie — een Row Layout met daarin Secties en tekstblokken — uit een van de sjablonen van list-recipes, en geeft de markup terug zonder iets op te slaan. Gebruik dit in plaats van zelf blokmarkup te verzinnen: Kadence bakt elk uniqueID in de klassenamen van het blok en codeert de JSON in het blokcommentaar op een eigen manier, en met de hand geschreven markup wijkt daar gegarandeerd van af. post_id is verplicht omdat de uniqueIDs uniek moeten zijn binnen de post waar de sectie in komt; twee blokken met hetzelfde ID delen hun CSS en veranderen samen. De gebouwde markup wordt geparsed en opnieuw geserialiseerd voordat hij wordt teruggegeven, en komt daar niet identiek uit, dan krijg je een fout in plaats van markup. Past het ontwerp in geen van de sjablonen — een hero met een label boven de kop, een sectie met gekleurde balken, kolommen met elk een eigen achtergrond — gebruik dan recipe custom en geef de boom mee via tree; bouw wat bij elkaar hoort in één keer. Iets toevoegen aan een container die er al staat kan met insert-blocks position inside. Schrijven doe je daarna met insert-blocks en het token dat hier terugkomt.', 'mcp-abilities-kadence' ),
					'readonly'    => true,
					'idempotent'  => false,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'        => 'integer',
								'minimum'     => 1,
								'description' => __( 'De post waar de sectie in terecht gaat komen. Bepaalt de uniqueIDs.', 'mcp-abilities-kadence' ),
							),
							'recipe' => array(
								'type'        => 'string',
								'description' => __( 'De slug van het sjabloon, uit list-recipes.', 'mcp-abilities-kadence' ),
							),
							'title' => array(
								'type'        => 'string',
								'description' => __( 'De kop. Verplicht bij elk sjabloon.', 'mcp-abilities-kadence' ),
							),
							'body' => array(
								'type'        => 'string',
								'description' => __( 'De alinea eronder. Laat weg als je er geen wil; er wordt dan geen leeg blok gemaakt.', 'mcp-abilities-kadence' ),
							),
							'items' => array(
								'type'        => 'array',
								'description' => __( 'Alleen bij het sjabloon kolommen: twee tot vier items, elk met title en optioneel body. Een kale string mag ook en wordt dan de kop.', 'mcp-abilities-kadence' ),
								'items'       => array( 'type' => array( 'object', 'string' ) ),
							),
							'tree' => array(
								'type'        => 'array',
								'description' => __( 'Alleen bij recipe custom: de complete boom. Elke knoop is een object met block (bijvoorbeeld kadence/rowlayout), optioneel attrs, en dan òf text met een tag òf children met meer knopen. Geef geen uniqueID mee — die worden hier uitgedeeld.', 'mcp-abilities-kadence' ),
								'items'       => array( 'type' => 'object' ),
							),
							'buttons' => array(
								'type'        => 'array',
								'description' => __( 'Knoppen met text en link. Eén knop levert al twee blokken op: advancedbtn is de rij, singlebtn de knop erin.', 'mcp-abilities-kadence' ),
								'items'       => array( 'type' => array( 'object', 'string' ) ),
							),
						),
						'required'             => array( 'post_id', 'recipe' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'recipe'     => array( 'type' => 'string' ),
							'markup'     => array( 'type' => 'string' ),
							'unique_ids' => array( 'type' => 'array' ),
							'blocks'     => array( 'type' => 'object' ),
							'token'      => array( 'type' => 'string' ),
							'status'     => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'generate_section' ),
				),
			),
			array(
				'name' => 'kadence/insert-blocks',
				'args' => array(
					'label'       => __( 'Blokken invoegen in een post', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Voegt gebouwde markup toe aan een post.', 'mcp-abilities-kadence' ),
					'description' => __( 'Voegt blokmarkup toe aan een bestaande post, op een plek die je zelf kiest. Vereist de capability kadence_mcp_write, bewerkrecht op de post, en het token uit generate-section of prepare-import — dat token is gebonden aan deze post, deze exacte markup en de wijzigingsdatum van de post, dus markup die je zelf hebt aangepast komt er niet in. Voor het opslaan wordt gecontroleerd dat elk uniqueID dat al in de post stond er daarna nog steeds is en dat er geen dubbele uniqueIDs ontstaan; klopt dat niet, dan wordt er niets geschreven. Na het opslaan wordt de post teruggelezen. Er wordt een revisie gemaakt, dus terugdraaien kan.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'idempotent'  => false,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
							'markup'  => array(
								'type'        => 'string',
								'description' => __( 'De markup uit generate-section of prepare-import, letterlijk.', 'mcp-abilities-kadence' ),
							),
							'position' => array(
								'type'        => 'string',
								'enum'        => array( 'append', 'prepend', 'after', 'before', 'inside' ),
								'default'     => 'append',
								'description' => __( 'Waar het heen moet. Bij after, before en inside is relative_to verplicht. inside plaatst de blokken ALS KIND van die container — zo krijg je iets in een bestaande kolom, binnen de achtergrond en de contentbreedte van die rij.', 'mcp-abilities-kadence' ),
							),
							'relative_to' => array(
								'type'        => 'string',
								'description' => __( 'De uniqueID waar het omheen moet, op elke diepte. Bij after en before komen de blokken als BUUR van dat blok te staan, dus binnen dezelfde ouder; bij inside komen ze er als KIND in, achteraan.', 'mcp-abilities-kadence' ),
							),
							'token' => array(
								'type'        => 'string',
								'description' => __( 'Het token uit generate-section of prepare-import.', 'mcp-abilities-kadence' ),
							),
						),
						'required'             => array( 'post_id', 'markup', 'token' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post'       => array( 'type' => 'object' ),
							'inserted'   => array( 'type' => 'array' ),
							'position'   => array( 'type' => 'string' ),
							'block_count' => array( 'type' => 'integer' ),
							'revision'   => array( 'type' => 'string' ),
							'status'     => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'insert_blocks' ),
				),
			),
			array(
				'name' => 'kadence/remove-blocks',
				'args' => array(
					'label'       => __( 'Blokken verwijderen', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Haalt blokken uit een post, met alles eronder.', 'mcp-abilities-kadence' ),
					'description' => __( 'Verwijdert een of meer blokken uit een post, op elke diepte — ook een enkele knop uit een knoppenrij. Let op dat een container alles meeneemt wat eronder staat: een rij weghalen verwijdert zijn kolommen en hun inhoud. Werkt daarom in twee stappen: roep hem eerst zonder token aan en je krijgt te zien welke blokken precies zouden verdwijnen en welke tekst daarin staat, zonder dat er iets gebeurt; roep hem daarna opnieuw aan met dat token om het echt te doen. Staat een van de opgegeven uniqueIDs niet in de post, dan wordt er niets verwijderd — een verzoek dat half klopt wordt niet half uitgevoerd. Vereist de capability kadence_mcp_write en bewerkrecht op de post. Er wordt een revisie gemaakt, dus terugdraaien kan.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id'    => array( 'type' => 'integer', 'minimum' => 1 ),
							'unique_ids' => array(
								'type'        => 'array',
								'description' => __( 'De uniqueIDs die weg moeten. Alles wat onder zo een blok staat gaat mee.', 'mcp-abilities-kadence' ),
								'items'       => array( 'type' => 'string' ),
							),
							'token' => array(
								'type'        => 'string',
								'description' => __( 'Laat leeg voor een voorstel zonder iets te verwijderen. Vul het token in dat je dan terugkrijgt om het echt te doen.', 'mcp-abilities-kadence' ),
							),
						),
						'required'             => array( 'post_id', 'unique_ids' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post'      => array( 'type' => 'object' ),
							'removing'  => array( 'type' => 'array' ),
							'blocks'    => array( 'type' => 'object' ),
							'text'      => array( 'type' => 'array' ),
							'remaining' => array( 'type' => 'integer' ),
							'removed'   => array( 'type' => 'boolean' ),
							'token'     => array( 'type' => 'string' ),
							'revision'  => array( 'type' => 'string' ),
							'status'    => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'remove_blocks' ),
				),
			),
			array(
				'name' => 'kadence/replace-block',
				'args' => array(
					'label'       => __( 'Een blok opnieuw opbouwen met behoud van zijn uniqueID', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Wisselt het bloktype of bouwt de markup opnieuw, zonder dat het uniqueID verandert.', 'mcp-abilities-kadence' ),
					'description' => __( 'Bouwt één bestaand blok opnieuw op, op zijn eigen plek, met hetzelfde uniqueID. Gebruik dit voor twee dingen die set-attributes en style-blocks niet kunnen. EEN: van bloktype wisselen, bijvoorbeeld kadence/query-filter naar kadence/query-filter-buttons. Verwijderen en opnieuw invoegen kan dat ook, maar levert een nieuw uniqueID op, en daar hangen verwijzingen aan die niemand meevolgt — de facetten van een Query Loop staan in post meta en wijzen met een uniqueID naar het filterblok. Verandert dat ID, dan werkt het filter niet meer en staat er nergens een foutmelding. TWEE: een attribuut wijzigen waar markup uit volgt, zoals direction op een kolom of colorClass op een kop. set-attributes raakt alleen het blokcommentaar aan, dus een afgeleide klasse blijft dan op de oude waarde staan; hier wordt de markup opnieuw opgebouwd uit het blokprofiel en komen die klassen dus mee. Werkt alleen op bloktypes waarvan de markup bekend is; describe-block noemt ze niet, list-recipes wel. De kinderen blijven standaard staan en verhuizen mee. Blijft het bloktype gelijk, dan worden de bestaande attributen samengevoegd met wat je opgeeft; wissel je van type, dan gaan ze NIET mee, want een attribuut van het oude blok hoeft op het nieuwe niet te bestaan — geef dan expliciet op wat het nieuwe blok moet krijgen. Twee stappen: eerst zonder token voor een voorstel met de oude en de nieuwe eerste regel naast elkaar, daarna opnieuw met dat token om te schrijven. Voor het schrijven wordt gecontroleerd dat er geen enkel ander blok uit de post verdwijnt. Er wordt een revisie gemaakt.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id'   => array( 'type' => 'integer', 'minimum' => 1 ),
							'unique_id' => array(
								'type'        => 'string',
								'description' => __( 'Het blok dat opnieuw opgebouwd moet worden. Dit ID blijft staan — dat is het punt van deze ability.', 'mcp-abilities-kadence' ),
							),
							'block' => array(
								'type'        => 'string',
								'description' => __( 'Het nieuwe bloktype, bijvoorbeeld kadence/query-filter-buttons. Weglaten houdt het type gelijk en bouwt alleen de markup opnieuw op.', 'mcp-abilities-kadence' ),
							),
							'attributes' => array(
								'type'                 => 'object',
								'description'          => __( 'De attributen voor het nieuwe blok. Geef hier GEEN uniqueID: die blijft vanzelf staan.', 'mcp-abilities-kadence' ),
								'additionalProperties' => true,
							),
							'merge' => array(
								'type'        => 'boolean',
								'description' => __( 'Bestaande attributen samenvoegen met wat je opgeeft. Standaard true als het bloktype gelijk blijft, false als je van type wisselt.', 'mcp-abilities-kadence' ),
							),
							'keep_children' => array(
								'type'        => 'boolean',
								'default'     => true,
								'description' => __( 'De kindblokken behouden. Staat dit aan en kan het nieuwe bloktype geen kinderen dragen, dan weigert hij in plaats van ze weg te gooien.', 'mcp-abilities-kadence' ),
							),
							'text' => array(
								'type'        => 'string',
								'description' => __( 'Nieuwe zichtbare tekst, voor blokken die hun tekst in de markup bewaren. Weglaten behoudt de bestaande tekst.', 'mcp-abilities-kadence' ),
							),
							'tag' => array(
								'type'        => 'string',
								'description' => __( 'De HTML-tag, alleen voor kadence/advancedheading. Weglaten neemt de bestaande tag over.', 'mcp-abilities-kadence' ),
							),
							'token' => array(
								'type'        => 'string',
								'description' => __( 'Laat leeg voor een voorstel zonder te schrijven. Vul het token in dat je dan terugkrijgt om het echt te doen.', 'mcp-abilities-kadence' ),
							),
						),
						'required'             => array( 'post_id', 'unique_id' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post'      => array( 'type' => 'object' ),
							'unique_id' => array( 'type' => 'string' ),
							'van'       => array( 'type' => 'string' ),
							'naar'      => array( 'type' => 'string' ),
							'kinderen'  => array( 'type' => 'integer' ),
							'before'    => array( 'type' => 'string' ),
							'after'     => array( 'type' => 'string' ),
							'markup'    => array( 'type' => 'string' ),
							'written'   => array( 'type' => 'boolean' ),
							'token'     => array( 'type' => 'string' ),
							'revision'  => array( 'type' => 'string' ),
							'status'    => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'replace_block' ),
				),
			),
			array(
				'name' => 'kadence/verify-markup',
				'args' => array(
					'label'       => __( 'Controleren of de markup nog bij de attributen past', 'mcp-abilities-kadence' ),
					'summary'     => __( 'Vindt blokken die in de editor "ongeldige inhoud" zouden heten, terwijl de voorkant er goed uitziet.', 'mcp-abilities-kadence' ),
					'description' => __( 'Loopt een post na en meldt elk blok waarvan de klassen in de markup niet meer kloppen met zijn attributen. Doe dit na elke reeks schrijfacties, en zeker na het wijzigen van een attribuut waar markup uit volgt. Waarom dit nodig is: Kadence leidt klassen af uit attributen, maar set-attributes en style-blocks raken alleen het blokcommentaar aan. Na zo een wijziging staat er dus een klasse die niet meer bij het attribuut hoort. Op de VOORKANT valt dat niet op, want daar stuurt het attribuut de weergave en doet de oude klasse niets. In de EDITOR wel: die vergelijkt de opgeslagen markup met wat het blok zelf zou schrijven, en noemt het verschil onverwachte of ongeldige inhoud. Laat de gebruiker dan blokherstel klikken, dan hernummert Kadence alle uniqueIDs en raken verwijzingen van buitenaf los — bij een Query Loop breekt daarmee de koppeling met de facetten. Deze ability repareert niets; hij geeft de uniqueIDs terug die je met replace-block kunt herbouwen. Alleen bloktypes waarvan het profiel bekend is worden getoetst, en alleen op klassen; checked zegt hoeveel blokken er werkelijk zijn nagekeken.', 'mcp-abilities-kadence' ),
					'readonly'    => true,
					'idempotent'  => true,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'        => 'integer',
								'minimum'     => 1,
								'description' => __( 'De post die nagelopen moet worden. Werkt ook op een kadence_query of een ander Kadence-posttype.', 'mcp-abilities-kadence' ),
							),
						),
						'required'             => array( 'post_id' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post'     => array( 'type' => 'object' ),
							'checked'  => array( 'type' => 'integer' ),
							'findings' => array( 'type' => 'array' ),
							'repair'   => array( 'type' => 'array' ),
							'status'   => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'verify_markup' ),
				),
			),
			array(
				'name' => 'kadence/prepare-import',
				'args' => array(
					'label'       => __( 'Markup van elders controleren voor invoegen', 'mcp-abilities-kadence' ),
					'summary'     => __( 'Controleert blokmarkup die niet uit deze plug-in komt — uit de editor of van een andere site — en geeft een token voor insert-blocks. Schrijft zelf niets.', 'mcp-abilities-kadence' ),
					'description' => __( 'Neemt blokmarkup aan die NIET door generate-section is gebouwd — geserialiseerd in de editor, uitgelezen met get-raw-markup op een andere site, of uit een patroon — en maakt hem klaar om met insert-blocks in deze post te zetten. Schrijft zelf niets. Wat er gebeurt, in volgorde: (1) alleen blokken, geen losse HTML, en elk bloktype moet op deze site bestaan; (2) de markup moet een parse- en serialiseerronde overleven; (3) elke uniqueID krijgt een nieuwe waarde met het postprefix van DEZE post, in het attribuut en in de klassen, en de kaart staat in id_map; (4) de klassen in de markup moeten kloppen met de attributen, voor elk bloktype waarvan het profiel bekend is — dezelfde toets als verify-markup; (5) elk attribuut gaat door de schematoets en de waardenlijsten van validate-write; (6) tellers als slideCount en tabCount moeten gelijk zijn aan het aantal kindblokken; (7) alles wat naar buiten wijst wordt gemeld: links naar een ander domein, afbeeldingen en media-ID\'s die hier niet bestaan, custom SVG-iconen (kb-custom-N), termen, verwijzingen naar andere posts (navigaties, headers, queries, query cards, vectoren, formulieren, menu-items naar een post) die hier niet bestaan of een ander type zijn, paletkleuren met hun waarde op DEZE site, en de eigen CSS-klassen die de markup gebruikt. Omzetten gebeurt alleen expliciet: replace voor letterlijke tekst (een ander domein, een ander icoon-ID), media_map, term_map en post_map voor ID\'s; er wordt niets geraden. Oordeel veilig geeft een token voor insert-blocks. Bij blokkeer komt er geen token. Bij riskant — een verwijzing die op deze site niet bestaat — alleen met accept_warnings true, en dat hoort een mens te beslissen. Wat deze toets NIET kan: bewijzen dat de editor het blok straks geldig vindt, want die toets bestaat alleen in de JavaScript van het blok. Open de post daarom na het invoegen één keer in de editor.', 'mcp-abilities-kadence' ),
					'readonly'    => true,
					'idempotent'  => false,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'        => 'integer',
								'minimum'     => 1,
								'description' => __( 'De post waar de markup in moet komen. Bepaalt het prefix van de nieuwe uniqueIDs.', 'mcp-abilities-kadence' ),
							),
							'markup' => array(
								'type'        => 'string',
								'description' => __( 'De blokmarkup zoals de editor of get-raw-markup hem geeft.', 'mcp-abilities-kadence' ),
							),
							'replace' => array(
								'type'        => 'array',
								'description' => __( 'Letterlijke vervangingen, in volgorde toegepast op alle tekst in attributen en markup. Voorbeeld: [{"from":"https://oud.example","to":"https://nieuw.example"},{"from":"kb-custom-112","to":"kb-custom-87"}]. Vervang geen getallen los — "112" komt ook in afmetingen voor.', 'mcp-abilities-kadence' ),
								'items'       => array(
									'type'       => 'object',
									'properties' => array(
										'from' => array( 'type' => 'string' ),
										'to'   => array( 'type' => 'string' ),
									),
									'required'   => array( 'from', 'to' ),
								),
							),
							'media_map' => array(
								'type'                 => 'object',
								'description'          => __( 'Media-ID\'s omzetten: {"111": 87}. Geldt voor elk beeld in de attributen dat een id met een url ernaast heeft (backgroundImg, image). De url wordt het bestand van het nieuwe ID. Media-ID\'s zijn getallen, dus replace kan ze niet omzetten.', 'mcp-abilities-kadence' ),
								'additionalProperties' => array( 'type' => 'integer' ),
							),
							'term_map' => array(
								'type'                 => 'object',
								'description'          => __( 'Term-ID\'s omzetten: {"3": 12}. Geldt voor gekozen termen in de vorm [{value, label}], zoals het filter van een Post Grid; het label wordt de naam van de nieuwe term.', 'mcp-abilities-kadence' ),
								'additionalProperties' => array( 'type' => 'integer' ),
							),
							'post_map' => array(
								'type'                 => 'object',
								'description'          => __( 'Post-ID\'s omzetten: {"183": 412}. Geldt voor blokken die naar een andere post verwijzen: kadence/navigation, kadence/header, kadence/query, kadence/query-card, kadence/vector en kadence/advanced-form (hun id), en een kadence/navigation-link met kind post-type (id, en de url wordt de permalink van de nieuwe post). Post-ID\'s verschillen per site; replace kan ze niet omzetten, want het zijn getallen.', 'mcp-abilities-kadence' ),
								'additionalProperties' => array( 'type' => 'integer' ),
							),
							'accept_warnings' => array(
								'type'        => 'boolean',
								'default'     => false,
								'description' => __( 'Geef toch een token als er verwijzingen zijn die op deze site niet bestaan. Alleen na akkoord van een mens.', 'mcp-abilities-kadence' ),
							),
						),
						'required'             => array( 'post_id', 'markup' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post'        => array( 'type' => 'object' ),
							'verdict'     => array( 'type' => 'string' ),
							'blocks'      => array( 'type' => 'object' ),
							'problems'    => array( 'type' => 'array' ),
							'warnings'    => array( 'type' => 'array' ),
							'references'  => array( 'type' => 'object' ),
							'not_checked' => array( 'type' => 'object' ),
							'replaced'    => array( 'type' => 'array' ),
							'id_map'      => array( 'type' => 'object' ),
							'markup'      => array( 'type' => 'string' ),
							'token'       => array( 'type' => 'string' ),
							'status'      => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'prepare_import' ),
				),
			),
			array(
				'name' => 'kadence/create-page',
				'args' => array(
					'label'       => __( 'Een nieuwe pagina aanmaken', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Maakt een pagina aan, standaard als concept, eventueel onder een bestaande pagina.', 'mcp-abilities-kadence' ),
					'description' => __( 'Maakt een nieuwe pagina met blokinhoud. Standaard als CONCEPT: wat een agent schrijft is opzet, en een pagina die meteen live staat is met één aanroep zichtbaar voor iedereen. Publiceren is een aparte, bewuste stap — zet status op publish alleen als dat uitdrukkelijk de bedoeling is, en dan nog weigert hij zonder de capability publish_pages. Met parent_id komt de pagina onder een bestaande pagina te hangen, wat de URL bepaalt: onder een pagina met slug evenementen wordt dat /evenementen/<slug>/. De markup wordt op dezelfde manier gecontroleerd als bij insert-blocks: eerst parsen, dan opnieuw serialiseren, en vergelijken. Komt daar iets anders uit, dan zou WordPress de markup bij het opslaan herschrijven en klopt wat je ziet niet met wat er staat; dan wordt er niets aangemaakt. Bouw die markup niet met de hand maar met generate-section, want Kadence bakt elk uniqueID in de klassenamen. Twee stappen: eerst zonder token voor een voorstel, daarna met token.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'title' => array(
								'type'        => 'string',
								'description' => __( 'De titel van de pagina. Bepaalt ook de slug, tenzij WordPress er een botsing in ziet.', 'mcp-abilities-kadence' ),
							),
							'parent_id' => array(
								'type'        => 'integer',
								'minimum'     => 1,
								'description' => __( 'De bovenliggende pagina. Weglaten zet hem op het hoogste niveau.', 'mcp-abilities-kadence' ),
							),
							'markup' => array(
								'type'        => 'string',
								'description' => __( 'De blokmarkup, bij voorkeur uit generate-section. Weglaten geeft een lege pagina.', 'mcp-abilities-kadence' ),
							),
							'status' => array(
								'type'        => 'string',
								'enum'        => array( 'draft', 'publish' ),
								'default'     => 'draft',
								'description' => __( 'Standaard draft. Alleen op publish zetten als de pagina echt meteen live mag.', 'mcp-abilities-kadence' ),
							),
							'token' => array( 'type' => 'string' ),
						),
						'required'             => array( 'title' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'title'       => array( 'type' => 'string' ),
							// Nullable: een pagina op het hoogste niveau heeft geen ouder.
							// Stond dit op alleen 'object', dan wees de validator elke
							// aanroep ZONDER parent_id af — en dat is het normale geval.
							'parent'      => array( 'type' => array( 'object', 'null' ) ),
							'status'      => array( 'type' => 'string' ),
							'block_count' => array( 'type' => 'integer' ),
							'new_id'      => array( 'type' => 'integer' ),
							'url'         => array( 'type' => 'string' ),
							'created'     => array( 'type' => 'boolean' ),
							'token'       => array( 'type' => 'string' ),
							'note'        => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'create_page' ),
				),
			),
			array(
				'name' => 'kadence/set-page-status',
				'args' => array(
					'label'       => __( 'De status van een pagina wijzigen', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Zet een pagina van concept naar gepubliceerd, of terug.', 'mcp-abilities-kadence' ),
					'description' => __( 'Wijzigt de post_status van een pagina. Bestaat omdat create-page alleen bij het AANMAKEN een status kon meegeven: een pagina die als concept is opgebouwd was daarna niet meer te publiceren zonder de editor, en dat betekende dat hij ook niet op de voorkant te controleren viel. Publiceren vraagt de capability publish_pages; die heeft niet iedereen, en dat is expres. Let op wat publiceren betekent: de pagina is daarna voor iedereen zichtbaar en krijgt pas op dat moment zijn definitieve slug uit de titel. Staat er al een pagina met diezelfde slug, dan hangt WordPress er een volgnummer achter en wijkt de URL dus af van wat je verwachtte — de teruggelezen url in het antwoord is de waarheid, niet je aanname. Twee stappen: eerst zonder token voor een voorstel, daarna met token.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
							'status'  => array(
								'type'        => 'string',
								'enum'        => array( 'publish', 'draft', 'private' ),
								'description' => __( 'De nieuwe status. publish maakt hem openbaar, draft haalt hem weer offline, private laat hem alleen aan ingelogde beheerders zien.', 'mcp-abilities-kadence' ),
							),
							'token'   => array( 'type' => 'string' ),
						),
						'required'             => array( 'post_id', 'status' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post'     => array( 'type' => 'object' ),
							'from'     => array( 'type' => 'string' ),
							'to'       => array( 'type' => 'string' ),
							'slug'     => array( 'type' => 'string' ),
							'url'      => array( 'type' => 'string' ),
							'changed'  => array( 'type' => 'boolean' ),
							'token'    => array( 'type' => 'string' ),
							'status'   => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'set_page_status' ),
				),
			),
		);
	}

	/**
	 * De sjablonen.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array
	 */
	public static function list_recipes( $input = array() ) {
		$recepten = array();

		foreach ( Kadence_MCP_Sjablonen::recepten() as $recept ) {
			$recepten[] = array(
				'slug'        => $recept['slug'],
				'label'       => $recept['label'],
				'description' => $recept['beschrijving'],
				'blocks'      => $recept['blokken'],
				'slots'       => $recept['slots'],
			);
		}

		return array(
			'recipes' => $recepten,
			'status'  => sprintf(
				/* translators: %d: number of recipes. */
				__( '%d sjablonen. Ze leveren structuur, geen opmaak: kleuren, marges en lettergroottes zet je erna met set-attributes.', 'mcp-abilities-kadence' ),
				count( $recepten )
			),
		);
	}

	/**
	 * Haal een post op voor het bouwen.
	 *
	 * @param int $post_id De post.
	 *
	 * @return WP_Post|WP_Error
	 */
	private static function post( $post_id ) {
		$post = get_post( (int) $post_id );

		if ( ! $post ) {
			return new WP_Error(
				'kadence_mcp_post_not_found',
				sprintf(
					/* translators: %d: post ID. */
					__( 'Post %d bestaat niet.', 'mcp-abilities-kadence' ),
					(int) $post_id
				)
			);
		}

		return $post;
	}

	/**
	 * Bouw een sectie.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function generate_section( $input = array() ) {
		$post = self::post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$recept = isset( $input['recipe'] ) ? sanitize_key( (string) $input['recipe'] ) : '';

		// De uniqueIDs van de doelpost, zodat de nieuwe er niet mee botsen.
		$bezet = Kadence_MCP_Inventory::verzamel_unique_ids( parse_blocks( $post->post_content ) );

		$gebouwd = Kadence_MCP_Sjablonen::bouw( $recept, $input, $post->ID, $bezet );

		if ( is_wp_error( $gebouwd ) ) {
			return $gebouwd;
		}

		$token = Kadence_MCP_Inventory::schrijf_token( $post, '__insert__', array( 'markup' => Kadence_MCP_Inventory::token_markup( $gebouwd['markup'] ) ) );

		return array(
			'recipe'     => $recept,
			'markup'     => $gebouwd['markup'],
			'unique_ids' => $gebouwd['unique_ids'],
			'blocks'     => (object) $gebouwd['blokken'],
			'token'      => $token,
			'status'     => __( 'Gebouwd en gecontroleerd, er is NIETS opgeslagen. De markup overleeft een parse- en serialiseerronde ongewijzigd. Lees hem na en geef hem met het token door aan insert-blocks om hem te plaatsen. Pas je de markup zelf aan, dan vervalt het token.', 'mcp-abilities-kadence' ),
		);
	}

	/**
	 * Voeg blokken toe aan een post.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function insert_blocks( $input = array() ) {
		$post = self::post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );

		if ( is_wp_error( $post ) ) {
			return $post;
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

		$markup = isset( $input['markup'] ) ? (string) $input['markup'] : '';
		$token  = isset( $input['token'] ) ? (string) $input['token'] : '';

		$verwacht = Kadence_MCP_Inventory::schrijf_token( $post, '__insert__', array( 'markup' => Kadence_MCP_Inventory::token_markup( $markup ) ) );

		if ( ! hash_equals( $verwacht, $token ) ) {
			return new WP_Error(
				'kadence_mcp_invalid_token',
				Kadence_MCP_Inventory::token_reden( $token, $verwacht, $post )
			);
		}

		$nieuw = Kadence_MCP_Inventory::schoon_blokken( parse_blocks( $markup ) );

		if ( empty( $nieuw ) ) {
			return new WP_Error(
				'kadence_mcp_insert_no_blocks',
				__( 'De aangeleverde markup levert geen blokken op bij het parsen. Er valt dus niets in te voegen.', 'mcp-abilities-kadence' )
			);
		}

		// Bewust NIET schoon_blokken op de bestaande inhoud: staat er nog
		// klassieke inhoud in deze post, dan is dat één knoop zonder blockName
		// met de hele pagina erin. Die moet blijven staan. schoon_blokken laat
		// hem ook staan, maar hier is het helderder om hem niet aan te raken.
		$bestaand = parse_blocks( $post->post_content );
		$positie  = isset( $input['position'] ) ? (string) $input['position'] : 'append';
		$relatief = isset( $input['relative_to'] ) ? trim( (string) $input['relative_to'] ) : '';

		$voor_ids = Kadence_MCP_Inventory::verzamel_unique_ids( $bestaand );

		$samengevoegd = self::voeg_samen( $bestaand, $nieuw, $positie, $relatief );

		if ( is_wp_error( $samengevoegd ) ) {
			return $samengevoegd;
		}

		// De Kadence-variant van een designmarkup-controle: niet kijken of er
		// markup verdween, maar of er een uniqueID verdween. Dat is hier het
		// betekenisvolle verlies — aan een ID hangt de CSS van een blok, dus
		// een ID dat weg is betekent een blok dat weg is.
		$na_ids  = Kadence_MCP_Inventory::verzamel_unique_ids( $samengevoegd );
		$verloren = array_diff( array_keys( $voor_ids ), array_keys( $na_ids ) );

		if ( ! empty( $verloren ) ) {
			return new WP_Error(
				'kadence_mcp_insert_would_lose_blocks',
				sprintf(
					/* translators: %s: comma separated uniqueIDs. */
					__( 'Geweigerd: door deze invoeging zouden bestaande blokken verdwijnen (%s). Er is niets geschreven.', 'mcp-abilities-kadence' ),
					implode( ', ', array_slice( $verloren, 0, 5 ) )
				)
			);
		}

		$botsingen = array_intersect( array_keys( $voor_ids ), self::ids_van( $nieuw ) );

		if ( ! empty( $botsingen ) ) {
			return new WP_Error(
				'kadence_mcp_insert_duplicate_ids',
				sprintf(
					/* translators: %s: comma separated uniqueIDs. */
					__( 'Geweigerd: de in te voegen blokken gebruiken uniqueIDs die al in deze post voorkomen (%s). Twee blokken met hetzelfde ID delen hun CSS. Laat de sectie opnieuw bouwen met generate-section.', 'mcp-abilities-kadence' ),
					implode( ', ', array_slice( $botsingen, 0, 5 ) )
				)
			);
		}

		$content = Kadence_MCP_Inventory::serialiseer( $samengevoegd );

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
		$staat   = Kadence_MCP_Inventory::verzamel_unique_ids( $na_boom );

		$geplaatst = self::ids_van( $nieuw );
		$ontbreekt = array_diff( $geplaatst, array_keys( $staat ) );

		// Aanwezigheid is niet genoeg: bij position inside hoort het blok ONDER
		// de opgegeven container te staan, niet ergens anders in de post.
		if ( 'inside' === $positie && '' !== $relatief && empty( $ontbreekt ) ) {
			$eerste = isset( $nieuw[0]['attrs']['uniqueID'] ) ? (string) $nieuw[0]['attrs']['uniqueID'] : '';
			$ouder  = '' === $eerste ? '' : Kadence_MCP_Inventory::ouder_van( $na_boom, $eerste );

			if ( $ouder !== $relatief ) {
				return new WP_Error(
					'kadence_mcp_insert_wrong_parent',
					sprintf(
						/* translators: 1: intended container, 2: actual parent or empty. */
						__( 'Er is geschreven, maar het blok staat niet onder "%1$s" — de ouder is nu "%2$s". Controleer de post en draai zo nodig de revisie terug.', 'mcp-abilities-kadence' ),
						$relatief,
						'' === $ouder ? __( 'het hoogste niveau', 'mcp-abilities-kadence' ) : $ouder
					)
				);
			}
		}

		return array(
			'post'        => array(
				'id'       => $post->ID,
				'title'    => get_the_title( $post ),
				'type'     => $post->post_type,
				'status'   => $post->post_status,
			),
			'inserted'    => $geplaatst,
			'position'    => $positie,
			'block_count' => count( $nieuw ),
			'revision'    => __( 'Er is een revisie gemaakt; terugdraaien kan via het revisieoverzicht van de post.', 'mcp-abilities-kadence' ),
			'status'      => empty( $ontbreekt )
				? __( 'geschreven en teruggelezen: alle ingevoegde blokken staan in de post.', 'mcp-abilities-kadence' ) . Kadence_MCP_Query::facetwaarschuwing( get_post( $post->ID ) )
				: sprintf(
					/* translators: %s: comma separated uniqueIDs. */
					__( 'LET OP: er is geschreven, maar bij het teruglezen ontbreken deze blokken: %s. Kadence herschrijft post_content op wp_insert_post_data, dus mogelijk heeft een filter ingegrepen.', 'mcp-abilities-kadence' ),
					implode( ', ', $ontbreekt )
				),
		);
	}

	/**
	 * De uniqueIDs van een blokkenlijst, alleen het hoogste niveau en dieper.
	 *
	 * @param array $blokken De blokken.
	 *
	 * @return array
	 */
	private static function ids_van( $blokken ) {
		return array_keys( Kadence_MCP_Inventory::verzamel_unique_ids( $blokken ) );
	}

	/**
	 * Voeg de nieuwe blokken op de gevraagde plek toe.
	 *
	 * append en prepend werken op het hoogste niveau: een sectie zomaar in een
	 * bestaande kolom laten landen zou betekenen dat er geraden moet worden
	 * welke kolom. before, after en inside werken op elke diepte, want daar
	 * wijst het opgegeven blok zijn eigen ouder aan.
	 *
	 * @param array  $bestaand De bestaande blokken.
	 * @param array  $nieuw    De nieuwe blokken.
	 * @param string $positie  append, prepend, after of before.
	 * @param string $relatief De uniqueID waar het omheen moet.
	 *
	 * @return array|WP_Error
	 */
	private static function voeg_samen( $bestaand, $nieuw, $positie, $relatief ) {
		if ( 'prepend' === $positie ) {
			return array_merge( $nieuw, $bestaand );
		}

		if ( 'append' === $positie ) {
			return array_merge( $bestaand, $nieuw );
		}

		if ( 'inside' === $positie ) {
			if ( '' === $relatief ) {
				return new WP_Error(
					'kadence_mcp_insert_missing_anchor',
					__( 'Bij position inside hoort relative_to: de uniqueID van de container waar het in moet.', 'mcp-abilities-kadence' )
				);
			}

			$gelukt = false;
			$uit    = Kadence_MCP_Inventory::voeg_binnen_in( $bestaand, $relatief, $nieuw, $gelukt );

			if ( ! $gelukt ) {
				return new WP_Error(
					'kadence_mcp_insert_container_not_found',
					sprintf(
						/* translators: %s: uniqueID. */
						__( 'Container "%s" is niet gevonden, of het is een blok zonder binnenkant. Een zelfsluitend blok — een knop bijvoorbeeld — heeft geen plek voor kindblokken.', 'mcp-abilities-kadence' ),
						$relatief
					)
				);
			}

			return $uit;
		}

		if ( '' === $relatief ) {
			return new WP_Error(
				'kadence_mcp_insert_missing_anchor',
				__( 'Bij position after of before hoort relative_to: de uniqueID van het blok waar het voor of na moet.', 'mcp-abilities-kadence' )
			);
		}

		$gemikt = false;
		$uit    = self::plaats_naast( $bestaand, $nieuw, $positie, $relatief, $gemikt );

		if ( ! $gemikt ) {
			return new WP_Error(
				'kadence_mcp_insert_anchor_not_found',
				sprintf(
					/* translators: %s: uniqueID. */
					__( 'Blok "%s" staat niet in deze post. Controleer het uniqueID met inspect-post.', 'mcp-abilities-kadence' ),
					$relatief
				)
			);
		}

		return $uit;
	}

	/**
	 * Zet nieuwe blokken voor of na een bestaand blok, op elke diepte.
	 *
	 * Tot 1.14.0 kon dit alleen op het hoogste niveau. De reden daarvoor was dat
	 * er anders geraden zou moeten worden in welke kolom iets landt — maar die
	 * redenering klopt voor append en prepend, niet hier: het ankerblok wijst
	 * zijn eigen ouder aan, dus er valt niets te raden. Het gevolg van die
	 * beperking was dat een blok tussenvoegen in een kolom alleen kon door het
	 * blok eronder eerst te VERWIJDEREN en opnieuw op te bouwen, met een nieuw
	 * uniqueID tot gevolg.
	 *
	 * Let op innerContent: dat is de lijst waarin Gutenberg per kindblok een
	 * null als plaatshouder zet, afgewisseld met de stukken wrapper-HTML.
	 * Blokken toevoegen aan innerBlocks zonder daar een null bij te zetten
	 * levert markup op waarin de nieuwe blokken gewoon ONTBREKEN — en de
	 * terugleescontrole ziet dat niet, want de uniqueIDs staan wel in de boom.
	 *
	 * @param array  $blokken  De blokken van dit niveau.
	 * @param array  $nieuw    Wat erbij moet.
	 * @param string $positie  before of after.
	 * @param string $relatief De uniqueID van het anker.
	 * @param bool   $gemikt   Wordt true zodra het anker gevonden is.
	 *
	 * @return array
	 */
	private static function plaats_naast( $blokken, $nieuw, $positie, $relatief, &$gemikt ) {
		// Staat het anker op DIT niveau? Dan is het een kwestie van splicen; de
		// innerContent van de ouder wordt een niveau hoger bijgewerkt.
		foreach ( $blokken as $i => $blok ) {
			$id = isset( $blok['attrs']['uniqueID'] ) ? (string) $blok['attrs']['uniqueID'] : '';

			if ( $id === (string) $relatief ) {
				$gemikt = true;

				array_splice( $blokken, ( 'before' === $positie ) ? $i : $i + 1, 0, $nieuw );

				return $blokken;
			}
		}

		// Niet hier: dieper zoeken.
		foreach ( $blokken as $i => $blok ) {
			if ( empty( $blok['innerBlocks'] ) || ! is_array( $blok['innerBlocks'] ) ) {
				continue;
			}

			$raak     = false;
			$kinderen = self::plaats_naast( $blok['innerBlocks'], $nieuw, $positie, $relatief, $raak );

			if ( ! $raak ) {
				continue;
			}

			$erbij = count( $kinderen ) - count( $blok['innerBlocks'] );

			if ( $erbij > 0 ) {
				// Het anker was een direct kind van DIT blok, dus hier hoort de
				// plaatshouder erbij.
				$anker = -1;

				foreach ( $blok['innerBlocks'] as $k => $kind ) {
					$kid = isset( $kind['attrs']['uniqueID'] ) ? (string) $kind['attrs']['uniqueID'] : '';

					if ( $kid === (string) $relatief ) {
						$anker = $k;
						break;
					}
				}

				if ( $anker >= 0 ) {
					$blok['innerContent'] = self::plaats_plaatshouders(
						isset( $blok['innerContent'] ) ? $blok['innerContent'] : array(),
						$anker,
						$erbij,
						$positie
					);
				}
			}

			$blok['innerBlocks'] = $kinderen;
			$blokken[ $i ]       = $blok;
			$gemikt              = true;

			return $blokken;
		}

		return $blokken;
	}

	/**
	 * Zet extra nulls in innerContent, naast de plaatshouder van het anker.
	 *
	 * @param array  $inhoud       De innerContent van de ouder.
	 * @param int    $anker_index  Het hoeveelste kind het anker is.
	 * @param int    $aantal       Hoeveel blokken erbij komen.
	 * @param string $positie      before of after.
	 *
	 * @return array
	 */
	private static function plaats_plaatshouders( $inhoud, $anker_index, $aantal, $positie ) {
		if ( ! is_array( $inhoud ) || empty( $inhoud ) || $aantal < 1 ) {
			return $inhoud;
		}

		$gezien = 0;
		$doel   = -1;

		foreach ( $inhoud as $index => $stuk ) {
			if ( null !== $stuk ) {
				continue;
			}

			if ( $gezien === (int) $anker_index ) {
				$doel = $index;
				break;
			}

			$gezien++;
		}

		if ( $doel < 0 ) {
			// Geen plaatshouder gevonden waar er een hoort te zijn. Niets doen is
			// hier beter dan gokken: de aanroeper krijgt dan markup terug waarin
			// de blokken ontbreken, en de controle in insert-blocks slaat alarm.
			return $inhoud;
		}

		array_splice( $inhoud, ( 'before' === $positie ) ? $doel : $doel + 1, 0, array_fill( 0, (int) $aantal, null ) );

		return $inhoud;
	}

	/**
	 * Verwijder blokken uit een post.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function remove_blocks( $input = array() ) {
		$post = self::post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$ids   = isset( $input['unique_ids'] ) && is_array( $input['unique_ids'] ) ? array_map( 'strval', $input['unique_ids'] ) : array();
		$ids   = array_values( array_unique( array_filter( array_map( 'trim', $ids ) ) ) );
		$token = isset( $input['token'] ) ? (string) $input['token'] : '';

		if ( empty( $ids ) ) {
			return new WP_Error( 'kadence_mcp_remove_no_ids', __( 'Geef minstens één uniqueID op van een blok dat weg moet.', 'mcp-abilities-kadence' ) );
		}

		$boom    = parse_blocks( $post->post_content );
		$aanwezig = Kadence_MCP_Inventory::verzamel_unique_ids( $boom );

		$onbekend = array_values( array_diff( $ids, array_keys( $aanwezig ) ) );

		if ( ! empty( $onbekend ) ) {
			return new WP_Error(
				'kadence_mcp_remove_unknown_ids',
				sprintf(
					/* translators: 1: comma separated uniqueIDs, 2: post ID. */
					__( 'Deze blokken staan niet in post %2$d: %1$s. Er is niets verwijderd — een verzoek dat half klopt wordt niet half uitgevoerd.', 'mcp-abilities-kadence' ),
					implode( ', ', $onbekend ),
					$post->ID
				)
			);
		}

		// Wat er precies verdwijnt, inclusief alles eronder. Dit is de kern van
		// het voorstel: een rij weghalen neemt zijn kolommen en hun inhoud mee,
		// en dat hoort iemand te zien voordat hij ja zegt.
		$weg      = array_fill_keys( $ids, true );
		$treft    = array();
		$teksten  = array();

		foreach ( $ids as $id ) {
			$blok = Kadence_MCP_Inventory::zoek_op_unique_id( $boom, $id );

			if ( null === $blok ) {
				continue;
			}

			$onder = Kadence_MCP_Inventory::verzamel_unique_ids( array( $blok ) );

			foreach ( $onder as $onder_id => $namen ) {
				$treft[ $onder_id ] = isset( $namen[0] ) ? $namen[0] : '?';
			}

			// Door de hele subboom: bij een container zit de tekst in de
			// kleinkinderen, niet in het blok zelf.
			foreach ( Kadence_MCP_Inventory::verzamel_teksten( array( $blok ) ) as $regel ) {
				$teksten[] = mb_substr( trim( (string) $regel ), 0, 160 );
			}
		}

		$geteld    = 0;
		$overblijft = Kadence_MCP_Inventory::verwijder_blokken( $boom, $weg, $geteld );
		$content    = Kadence_MCP_Inventory::serialiseer( $overblijft );

		$grondslag = Kadence_MCP_Inventory::schrijf_token( $post, '__remove__', array( 'ids' => $ids ) );

		$leeg = '' === trim( wp_strip_all_tags( $content ) ) && empty( Kadence_MCP_Inventory::schoon_blokken( parse_blocks( $content ) ) );

		$basis = array(
			'post'      => array( 'id' => $post->ID, 'title' => get_the_title( $post ) ),
			'removing'  => array_keys( $treft ),
			'blocks'    => (object) array_count_values( array_values( $treft ) ),
			'text'      => $teksten,
			'remaining' => count( Kadence_MCP_Inventory::schoon_blokken( parse_blocks( $content ) ) ),
		);

		if ( '' === $token ) {
			$waarschuwing = $leeg
				? __( ' LET OP: hierna is de pagina leeg.', 'mcp-abilities-kadence' )
				: '';

			return array_merge(
				$basis,
				array(
					'removed' => false,
					'token'   => $grondslag,
					'status'  => sprintf(
						/* translators: 1: number of blocks, 2: extra warning. */
						__( 'Voorstel, er is NIETS verwijderd. Er zouden %1$d blokken verdwijnen, inclusief alles wat eronder staat — lees removing en text na voordat je doorgaat.%2$s Roep deze ability opnieuw aan met het token om het echt te doen.', 'mcp-abilities-kadence' ),
						count( $treft ),
						$waarschuwing
					),
				)
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

		$na       = get_post( $post->ID );
		$na_ids   = $na ? Kadence_MCP_Inventory::verzamel_unique_ids( parse_blocks( $na->post_content ) ) : array();
		$hardnekkig = array_intersect( array_keys( $treft ), array_keys( $na_ids ) );

		return array_merge(
			$basis,
			array(
				'removed'  => true,
				'token'    => '',
				'revision' => __( 'Er is een revisie gemaakt; terugdraaien kan via het revisieoverzicht van de post.', 'mcp-abilities-kadence' ),
				'status'   => Kadence_MCP_Query::facetwaarschuwing( get_post( $post->ID ) ) . ( empty( $hardnekkig )
					? sprintf(
						/* translators: %d: number of blocks. */
						__( 'verwijderd en teruggelezen: %d blokken zijn weg.', 'mcp-abilities-kadence' ),
						count( $treft )
					)
					: sprintf(
						/* translators: %s: comma separated uniqueIDs. */
						__( 'LET OP: er is geschreven, maar bij het teruglezen staan deze blokken er nog: %s.', 'mcp-abilities-kadence' ),
						implode( ', ', $hardnekkig )
					) ),
			)
		);
	}

	/**
	 * Bouw één blok opnieuw op, op zijn eigen plek.
	 *
	 * Nodig voor twee dingen die set-attributes niet kan.
	 *
	 * Een BLOKTYPE wisselen — een query-filter naar een query-filter-buttons —
	 * kan niet met attributen, en remove plus insert levert een nieuw uniqueID
	 * op. Dat is geen detail: aan dat ID hangen verwijzingen van buitenaf. De
	 * facetten van een Query Loop staan in post meta en wijzen met een uniqueID
	 * naar het filterblok; verandert dat ID, dan werkt het filter niet meer en
	 * staat er nergens een foutmelding.
	 *
	 * En een attribuut wijzigen waar MARKUP uit volgt. set-attributes raakt
	 * alleen het blokcommentaar aan, dus een klasse die uit een attribuut is
	 * afgeleid blijft op de oude waarde staan. Hier wordt de markup opnieuw
	 * opgebouwd uit het blokprofiel, dus die klassen komen mee.
	 *
	 * Wat blijft: het uniqueID, de plek in de boom, en standaard ook de
	 * kinderen. Wat gaat: de oude attributen, tenzij je merge gebruikt.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function replace_block( $input = array() ) {
		$post = self::post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$unique_id = isset( $input['unique_id'] ) ? trim( (string) $input['unique_id'] ) : '';
		$token     = isset( $input['token'] ) ? (string) $input['token'] : '';

		if ( '' === $unique_id ) {
			return new WP_Error( 'kadence_mcp_missing_unique_id', __( 'Geef de uniqueID op van het blok dat opnieuw opgebouwd moet worden.', 'mcp-abilities-kadence' ) );
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

		$oud_type = isset( $blok['blockName'] ) ? (string) $blok['blockName'] : '';
		$nieuw_type = isset( $input['block'] ) && '' !== trim( (string) $input['block'] )
			? trim( (string) $input['block'] )
			: $oud_type;

		if ( ! Kadence_MCP_Profielen::bekend( $nieuw_type ) ) {
			return new WP_Error(
				'kadence_mcp_replace_unknown_block',
				sprintf(
					/* translators: 1: block name, 2: comma separated block names. */
					__( 'Van "%1$s" is niet bekend hoe hij zijn markup wegschrijft, dus hij kan niet opgebouwd worden. Bekend zijn: %2$s.', 'mcp-abilities-kadence' ),
					$nieuw_type,
					implode( ', ', Kadence_MCP_Profielen::bloknamen() )
				)
			);
		}

		$oud_attrs = isset( $blok['attrs'] ) && is_array( $blok['attrs'] ) ? $blok['attrs'] : array();
		$voorstel  = isset( $input['attributes'] ) && is_array( $input['attributes'] ) ? $input['attributes'] : array();

		if ( isset( $voorstel['uniqueID'] ) ) {
			return new WP_Error(
				'kadence_mcp_replace_own_id',
				__( 'Geef geen uniqueID mee. Het bestaande ID blijft juist behouden — dat is de reden om deze ability te gebruiken in plaats van verwijderen en opnieuw invoegen.', 'mcp-abilities-kadence' )
			);
		}

		// Bij een typewissel zijn de oude attributen niet zomaar geldig op het
		// nieuwe blok, dus die gaan standaard niet mee. Blijft het type gelijk,
		// dan is samenvoegen het verwachte gedrag.
		$samenvoegen = isset( $input['merge'] ) ? (bool) $input['merge'] : ( $nieuw_type === $oud_type );
		$attrs       = $samenvoegen ? array_merge( $oud_attrs, $voorstel ) : $voorstel;

		$attrs['uniqueID'] = $unique_id;

		// Elk attribuut toetsen zoals validate-write dat doet. Bij een
		// typewissel is dat extra nodig: een attribuut dat op het oude blok
		// bestond hoeft op het nieuwe niet te bestaan.
		foreach ( $attrs as $naam => $waarde ) {
			if ( 'uniqueID' === $naam || in_array( $naam, Kadence_MCP_Inventory::CORE_ATTRIBUTEN, true ) ) {
				continue;
			}

			$definitie = Kadence_MCP_Inventory::attribuut_definitie( $nieuw_type, $naam );

			if ( null === $definitie ) {
				return new WP_Error(
					'kadence_mcp_replace_unknown_attribute',
					sprintf(
						/* translators: 1: attribute, 2: block name. */
						__( 'Het attribuut "%1$s" bestaat niet op %2$s. Bij een wissel van bloktype nemen de oude attributen niet vanzelf mee; geef expliciet op wat het nieuwe blok moet krijgen, of zet merge op false.', 'mcp-abilities-kadence' ),
						$naam,
						$nieuw_type
					)
				);
			}

			$fout = Kadence_MCP_Inventory::toets_waarde( $definitie, $waarde );

			if ( '' !== $fout ) {
				return new WP_Error(
					'kadence_mcp_replace_bad_value',
					sprintf(
						/* translators: 1: attribute, 2: block name, 3: reason. */
						__( '"%1$s" op %2$s: %3$s', 'mcp-abilities-kadence' ),
						$naam,
						$nieuw_type,
						$fout
					)
				);
			}

			$bezwaar = Kadence_MCP_Profielen::toets_waarde( $nieuw_type, $naam, $waarde, $attrs );

			if ( '' !== $bezwaar ) {
				return new WP_Error( 'kadence_mcp_replace_bad_value', $bezwaar );
			}
		}

		// De kinderen blijven standaard staan. Bij een typewissel naar een blok
		// dat geen kinderen kan dragen is dat onmogelijk; dat wordt hieronder
		// geweigerd in plaats van ze stil weg te gooien.
		$kinderen_behouden = isset( $input['keep_children'] ) ? (bool) $input['keep_children'] : true;
		$kinderen          = ( $kinderen_behouden && ! empty( $blok['innerBlocks'] ) ) ? $blok['innerBlocks'] : array();
		$profiel           = Kadence_MCP_Profielen::van( $nieuw_type );

		if ( ! empty( $kinderen ) && ! empty( $profiel['zelfsluitend'] ) ) {
			return new WP_Error(
				'kadence_mcp_replace_children_lost',
				sprintf(
					/* translators: 1: block name, 2: number of children. */
					__( '%1$s kan geen kindblokken dragen, en dit blok heeft er %2$d. Er wordt niets geschreven. Wil je ze echt kwijt, zet dan keep_children op false — maar verplaats ze liever eerst.', 'mcp-abilities-kadence' ),
					$nieuw_type,
					count( $kinderen )
				)
			);
		}

		// Opnieuw opbouwen mag alleen als élke afgeleide klasse bekend is.
		// Mist er een, dan schrijven we markup die Kadence zelf anders zou
		// schrijven — en dan is het blok in de editor ongeldig. Precies de
		// fout die deze ability hoort te repareren.
		$ongedekt = Kadence_MCP_Profielen::ongedekt( $nieuw_type, $attrs );

		if ( ! empty( $ongedekt ) ) {
			return new WP_Error(
				'kadence_mcp_replace_uncovered_markup',
				sprintf(
					/* translators: 1: attributes, 2: block name. */
					__( 'Dit blok heeft attributen waarvan de afgeleide klasse niet in het blokprofiel staat: %1$s. Opnieuw opbouwen zou die klasse weglaten en %2$s in de editor ongeldig maken. Er wordt niets geschreven. Lees de klasse af van het echte blok met get-raw-markup en vul het profiel aan voordat je dit blok herbouwt.', 'mcp-abilities-kadence' ),
					implode( ', ', $ongedekt ),
					$nieuw_type
				)
			);
		}

		$tekst = isset( $input['text'] ) ? (string) $input['text'] : '';
		$tag   = isset( $input['tag'] ) ? strtolower( (string) $input['tag'] ) : '';

		if ( '' === $tag ) {
			// De tag van het bestaande blok overnemen als hij er een heeft.
			$tag = 'p';

			if ( preg_match( '#^\s*<([a-zA-Z][a-zA-Z0-9]*)#', isset( $blok['innerHTML'] ) ? $blok['innerHTML'] : '', $m ) ) {
				$gevonden = strtolower( $m[1] );

				if ( in_array( $gevonden, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span' ), true ) ) {
					$tag = $gevonden;
				}
			}
		}

		// Tekst behouden als er niets nieuws is opgegeven en het blok er een had.
		if ( '' === $tekst && empty( $kinderen ) ) {
			$bestaand = Kadence_MCP_Inventory::binnenhtml_uit_blok( $blok );

			if ( '' !== trim( (string) $bestaand ) ) {
				$tekst = (string) $bestaand;
			}
		}

		$gebouwd = Kadence_MCP_Sjablonen::bouw_een_blok( $nieuw_type, $attrs, $kinderen, $tekst, $tag );

		if ( is_wp_error( $gebouwd ) ) {
			return $gebouwd;
		}

		$grondslag = Kadence_MCP_Inventory::schrijf_token(
			$post,
			'__replace__' . $unique_id,
			array( 'block' => $nieuw_type, 'attrs' => $attrs, 'markup' => $gebouwd['markup'] )
		);

		$rapport = array(
			'post'       => array( 'id' => $post->ID, 'title' => get_the_title( $post ) ),
			'unique_id'  => $unique_id,
			'van'        => $oud_type,
			'naar'       => $nieuw_type,
			'kinderen'   => count( $kinderen ),
			'before'     => self::eerste_regel( serialize_block( $blok ) ),
			'after'      => self::eerste_regel( $gebouwd['markup'] ),
			'markup'     => $gebouwd['markup'],
		);

		if ( '' === $token ) {
			return array_merge(
				$rapport,
				array(
					'written' => false,
					'token'   => $grondslag,
					'status'  => __( 'Voorstel, er is NIETS opgeslagen. Het uniqueID en de plek blijven zoals ze zijn; vergelijk before en after en roep opnieuw aan met het token om te schrijven.', 'mcp-abilities-kadence' ),
				)
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

		$nieuw_blok = Kadence_MCP_Inventory::schoon_blokken( parse_blocks( $gebouwd['markup'] ) );

		if ( empty( $nieuw_blok ) ) {
			return new WP_Error( 'kadence_mcp_replace_unparsable', __( 'De opnieuw opgebouwde markup levert geen blok op bij het parsen. Er wordt niets geschreven.', 'mcp-abilities-kadence' ) );
		}

		$gelukt   = false;
		$gewijzigd = Kadence_MCP_Inventory::vervang_blok( $boom, $unique_id, $nieuw_blok[0], $gelukt );

		if ( ! $gelukt ) {
			return new WP_Error( 'kadence_mcp_replace_failed', __( 'Het blok kon niet vervangen worden in de boom. Er is niets geschreven.', 'mcp-abilities-kadence' ) );
		}

		$content = Kadence_MCP_Inventory::serialiseer( $gewijzigd );

		// Niets anders mag verdwijnen. Een typewissel raakt één blok; blijkt er
		// meer weg, dan is er iets anders aan de hand.
		$voor_ids = Kadence_MCP_Inventory::verzamel_unique_ids( $boom );
		$na_ids   = Kadence_MCP_Inventory::verzamel_unique_ids( parse_blocks( $content ) );
		$verloren = array_diff( array_keys( $voor_ids ), array_keys( $na_ids ) );

		if ( ! empty( $verloren ) ) {
			return new WP_Error(
				'kadence_mcp_replace_would_lose_blocks',
				sprintf(
					/* translators: %s: comma separated uniqueIDs. */
					__( 'Geweigerd: door deze vervanging zouden blokken verdwijnen (%s). Er is niets geschreven.', 'mcp-abilities-kadence' ),
					implode( ', ', array_slice( $verloren, 0, 5 ) )
				)
			);
		}

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

		$na       = get_post( $post->ID );
		$na_boom  = $na ? parse_blocks( $na->post_content ) : array();
		$na_blok  = Kadence_MCP_Inventory::zoek_op_unique_id( $na_boom, $unique_id );
		$klopt    = $na_blok && isset( $na_blok['blockName'] ) && $na_blok['blockName'] === $nieuw_type;

		return array_merge(
			$rapport,
			array(
				'written'  => true,
				'token'    => '',
				'revision' => __( 'Er is een revisie gemaakt; terugdraaien kan via het revisieoverzicht van de post.', 'mcp-abilities-kadence' ),
				'status'   => ( $klopt
					? __( 'vervangen en teruggelezen: het blok staat er met hetzelfde uniqueID en het nieuwe type.', 'mcp-abilities-kadence' )
					: __( 'LET OP: er is geschreven, maar bij het teruglezen klopt het bloktype niet. Controleer de post.', 'mcp-abilities-kadence' ) )
					. Kadence_MCP_Query::facetwaarschuwing( $na ),
			)
		);
	}

	/**
	 * De eerste regel van een stuk markup, voor een leesbare vergelijking.
	 *
	 * @param string $markup De markup.
	 *
	 * @return string
	 */
	private static function eerste_regel( $markup ) {
		$regels = preg_split( '/\r\n|\n|\r/', trim( (string) $markup ) );
		$eerste = isset( $regels[0] ) ? $regels[0] : '';

		return ( strlen( $eerste ) > 300 ) ? substr( $eerste, 0, 300 ) . '…' : $eerste;
	}

	/**
	 * Loop een post na op markup die niet klopt met de attributen.
	 *
	 * Dit is de controle die ontbrak, en het ontbreken ervan heeft geld gekost.
	 *
	 * Kadence leidt klassen af uit attributen. set-attributes en style-blocks
	 * raken alleen het blokcommentaar aan, dus na zo een wijziging staat er een
	 * klasse die niet meer bij het attribuut hoort. Op de VOORKANT valt dat niet
	 * op — het attribuut stuurt de weergave en de oude klasse doet niets. In de
	 * EDITOR wel: die vergelijkt de opgeslagen markup met wat save() zou
	 * schrijven, en noemt het verschil "dit blok bevat onverwachte of ongeldige
	 * inhoud".
	 *
	 * Ik had dat gemeten op de voorkant en geconcludeerd dat de klasse niets
	 * doet. Eén laag gemeten, over twee lagen geconcludeerd. Deze ability meet
	 * de laag die ik oversloeg.
	 *
	 * Let op wat er NIET gebeurt: er wordt niets gerepareerd. De uitkomst is een
	 * lijst met uniqueIDs die je met replace-block kunt herbouwen — per blok,
	 * met een droogloop ertussen.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function verify_markup( $input = array() ) {
		$post = self::post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$bevindingen = array();
		$gekeurd     = 0;

		self::keur_blokken( parse_blocks( $post->post_content ), $bevindingen, $gekeurd );

		$herbouwbaar = array();

		foreach ( $bevindingen as $b ) {
			if ( empty( $b['blocked'] ) ) {
				$herbouwbaar[] = $b['unique_id'];
			}
		}

		return array(
			'post'      => array( 'id' => $post->ID, 'title' => get_the_title( $post ), 'type' => $post->post_type ),
			'checked'   => $gekeurd,
			'findings'  => $bevindingen,
			'repair'    => $herbouwbaar,
			'status'    => empty( $bevindingen )
				? sprintf(
					/* translators: %d: number of blocks. */
					__( '%d blokken nagelopen, de markup klopt overal met de attributen. Let op dat alleen de KLASSEN getoetst zijn, en alleen op bloktypes waarvan het profiel bekend is.', 'mcp-abilities-kadence' ),
					$gekeurd
				)
				: sprintf(
					/* translators: 1: number of findings, 2: number checked, 3: comma separated uniqueIDs. */
					__( '%1$d van de %2$d nagelopen blokken hebben markup die niet bij hun attributen past. In de editor heten die "ongeldige inhoud"; op de voorkant zie je er niets van. Herbouwen kan met replace-block, per blok: %3$s', 'mcp-abilities-kadence' ),
					count( $bevindingen ),
					$gekeurd,
					implode( ', ', $herbouwbaar )
				),
		);
	}

	/**
	 * De klassencontrole, bruikbaar vanuit een andere ability.
	 *
	 * Bestaat omdat style-blocks en set-attributes alleen het blokcommentaar
	 * aanraken: een attribuut waar klassen uit volgen — colorClass, direction —
	 * laat de markup op de oude waarde staan. Op de voorkant valt dat niet op,
	 * in de editor wel, en dan heet het "ongeldige inhoud". Door dit direct na
	 * het schrijven mee te geven hoeft er geen aparte verify-markup-ronde meer
	 * overheen om erachter te komen.
	 *
	 * @param array $boom De blokkenboom.
	 * @param array $ids  Beperk tot deze uniqueIDs; leeg betekent alles.
	 *
	 * @return array
	 */
	public static function klassen_controle( $boom, $ids = array() ) {
		$bevindingen = array();
		$gekeurd     = 0;

		self::keur_blokken( $boom, $bevindingen, $gekeurd );

		if ( empty( $ids ) ) {
			return $bevindingen;
		}

		$ids = array_map( 'strval', $ids );
		$uit = array();

		foreach ( $bevindingen as $bevinding ) {
			if ( in_array( (string) $bevinding['unique_id'], $ids, true ) ) {
				$uit[] = $bevinding;
			}
		}

		return $uit;
	}

	/**
	 * Vergelijk per blok de klassen in de markup met de afgeleide klassen.
	 *
	 * @param array $blokken     De boom.
	 * @param array $bevindingen De verzamelde bevindingen.
	 * @param int   $gekeurd     Teller.
	 *
	 * @return void
	 */
	private static function keur_blokken( $blokken, &$bevindingen, &$gekeurd ) {
		foreach ( $blokken as $blok ) {
			$naam = isset( $blok['blockName'] ) ? (string) $blok['blockName'] : '';

			if ( '' !== $naam ) {
				$profiel = Kadence_MCP_Profielen::van( $naam );
				$attrs   = isset( $blok['attrs'] ) && is_array( $blok['attrs'] ) ? $blok['attrs'] : array();
				$html    = isset( $blok['innerHTML'] ) ? (string) $blok['innerHTML'] : '';

				// Alleen blokken met een eigen wrapper zijn te toetsen. Een
				// zelfsluitend blok heeft geen markup om mee te vergelijken.
				if ( null !== $profiel && '' !== (string) $profiel['open'] && '' !== trim( $html ) ) {
					$gekeurd++;

					$bevinding = self::keur_klassen( $naam, $attrs, $html, $profiel );

					if ( null !== $bevinding ) {
						$bevinding['unique_id'] = isset( $attrs['uniqueID'] ) ? (string) $attrs['uniqueID'] : '';
						$bevinding['block']     = $naam;
						$bevindingen[]          = $bevinding;
					}
				}
			}

			if ( ! empty( $blok['innerBlocks'] ) ) {
				self::keur_blokken( $blok['innerBlocks'], $bevindingen, $gekeurd );
			}
		}
	}

	/**
	 * De klassen van één blok vergelijken.
	 *
	 * @param string $naam    De bloknaam.
	 * @param array  $attrs   De attributen.
	 * @param string $html    De innerHTML.
	 * @param array  $profiel Het blokprofiel.
	 *
	 * @return array|null Null als alles klopt.
	 */
	private static function keur_klassen( $naam, $attrs, $html, $profiel ) {
		if ( ! preg_match( '/class="([^"]*)"/', $html, $m ) ) {
			return null;
		}

		$buitenste = array_values( array_filter( explode( ' ', trim( $m[1] ) ) ) );
		$afgeleid  = Kadence_MCP_Profielen::klassen( $naam, $attrs );

		// Alle klassen van het blok zelf, ook die op dieper gelegen elementen.
		// innerHTML bevat alleen de eigen markup, niet die van de kinderen, dus
		// dit blijft bij dit ene blok. Regels met 'overal' kijken hier.
		preg_match_all( '/class="([^"]*)"/', $html, $alle_m );
		$alle = array();

		foreach ( $alle_m[1] as $lijst ) {
			$alle = array_merge( $alle, array_values( array_filter( explode( ' ', trim( $lijst ) ) ) ) );
		}

		$mist     = array();
		$overbodig = array();

		foreach ( $afgeleid as $plaatshouder => $tekst ) {
			$regel    = isset( Kadence_MCP_Profielen::KLASSENREGELS[ $plaatshouder ] ) ? Kadence_MCP_Profielen::KLASSENREGELS[ $plaatshouder ] : array();
			$aanwezig = ! empty( $regel['overal'] ) ? $alle : $buitenste;

			// Een element: zijn klasse moet er zijn als het sjabloon het
			// voorschrijft, en weg als het dat niet doet.
			if ( isset( $regel['soort'] ) && 'element' === $regel['soort'] ) {
				$staat = in_array( $regel['klasse'], $aanwezig, true );

				if ( '' !== (string) $tekst && ! $staat ) {
					$mist[] = $regel['klasse'];
				}

				if ( '' === (string) $tekst && $staat ) {
					$overbodig[] = $regel['klasse'];
				}

				continue;
			}

			$verwacht = array_values( array_filter( explode( ' ', trim( (string) $tekst ) ) ) );

			// Wat het attribuut voorschrijft maar niet in de markup staat.
			foreach ( $verwacht as $klasse ) {
				if ( ! in_array( $klasse, $aanwezig, true ) ) {
					$mist[] = $klasse;
				}
			}

			// En andersom: een klasse van dezelfde soort die er wél staat maar
			// niet meer voorgeschreven wordt. Dat is de achterblijver na een
			// wijziging met set-attributes.
			$voorvoegsels = array();

			if ( isset( $regel['soort'] ) && 'richting' === $regel['soort'] ) {
				$voorvoegsels = $regel['voorvoegsels'];
			}

			if ( isset( $regel['soort'] ) && 'reeks' === $regel['soort'] ) {
				foreach ( $regel['reeks'] as $stap ) {
					$voorvoegsels[] = $stap[2];
				}
			}

			foreach ( $aanwezig as $klasse ) {
				foreach ( $voorvoegsels as $voorvoegsel ) {
					// kb-slide-align- is ook het begin van niets anders, maar
					// kb-slide-tab-align- begint NIET met kb-slide-align-. Toch
					// het langste voorvoegsel eerst nemen is niet nodig: een
					// klasse telt als achterblijver zodra hij bij een
					// voorvoegsel hoort en niet verwacht wordt.
					if ( 0 === strpos( $klasse, $voorvoegsel ) && ! in_array( $klasse, $verwacht, true ) ) {
						$overbodig[] = $klasse;
					}
				}
			}
		}

		if ( empty( $mist ) && empty( $overbodig ) ) {
			return null;
		}

		$ongedekt = Kadence_MCP_Profielen::ongedekt( $naam, $attrs );

		return array(
			'missing'   => array_values( array_unique( $mist ) ),
			'stale'     => array_values( array_unique( $overbodig ) ),
			'blocked'   => ! empty( $ongedekt ),
			'note'      => empty( $ongedekt )
				? __( 'Te herstellen met replace-block: die bouwt de markup opnieuw op uit de attributen, met behoud van uniqueID, plek en kinderen.', 'mcp-abilities-kadence' )
				: sprintf(
					/* translators: %s: comma separated attributes. */
					__( 'NIET automatisch te herstellen: dit blok heeft attributen waarvan de klasse-afleiding niet bekend is (%s). Lees de juiste klasse af van een blok dat de editor zelf heeft geschreven en vul het blokprofiel aan.', 'mcp-abilities-kadence' ),
					implode( ', ', $ongedekt )
				),
		);
	}

	/**
	 * Markup van elders klaarmaken voor insert-blocks.
	 *
	 * insert-blocks neemt alleen markup met een token, en tot 1.21.0 kwam dat
	 * token alleen uit generate-section. Markup die de editor zelf schreef —
	 * tabs, een slider, alles wat de generator niet kent — of markup van een
	 * andere site kon er dus niet in, terwijl juist die markup het meest te
	 * vertrouwen is: Kadence heeft hem zelf geschreven.
	 *
	 * Deze ability sluit dat gat zonder een tweede schrijfroute te openen. Hij
	 * schrijft niets; hij controleert, zet uniqueIDs om, meldt wat per site
	 * verschilt, en geeft hetzelfde soort token uit als generate-section. Het
	 * schrijven blijft bij insert-blocks, met al zijn controles.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function prepare_import( $input = array() ) {
		$post = self::post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error(
				'kadence_mcp_edit_denied',
				__( 'Je mag deze post volgens WordPress zelf niet bewerken, dus er valt niets in te voegen.', 'mcp-abilities-kadence' ),
				array( 'status' => 403 )
			);
		}

		$invoer = isset( $input['markup'] ) ? (string) $input['markup'] : '';

		if ( '' === trim( $invoer ) ) {
			return new WP_Error( 'kadence_mcp_import_empty', __( 'Er is geen markup meegegeven.', 'mcp-abilities-kadence' ) );
		}

		if ( strlen( $invoer ) > self::IMPORT_MAX_TEKENS ) {
			return new WP_Error(
				'kadence_mcp_import_too_large',
				sprintf(
					/* translators: %d: maximum number of characters. */
					__( 'De markup is groter dan %d tekens. Voer hem in delen in, per sectie.', 'mcp-abilities-kadence' ),
					self::IMPORT_MAX_TEKENS
				)
			);
		}

		$problemen    = array();
		$waarschuwingen = array();

		// 1. Alleen blokken. Losse HTML op het hoogste niveau wordt door de
		//    editor een Klassiek blok, en hoort niet ongemerkt mee te komen.
		$geparsed = parse_blocks( $invoer );

		foreach ( $geparsed as $knoop ) {
			if ( empty( $knoop['blockName'] ) && '' !== trim( isset( $knoop['innerHTML'] ) ? (string) $knoop['innerHTML'] : '' ) ) {
				$problemen[] = array(
					'check'  => 'freeform',
					'detail' => __( 'Er staat HTML buiten een blok. In de editor wordt dat een Klassiek blok. Geef alleen blokmarkup mee.', 'mcp-abilities-kadence' ),
					'sample' => self::kort_fragment( (string) $knoop['innerHTML'] ),
				);
			}
		}

		$boom = Kadence_MCP_Inventory::schoon_blokken( $geparsed );

		if ( empty( $boom ) ) {
			return new WP_Error( 'kadence_mcp_import_no_blocks', __( 'De markup levert bij het parsen geen blokken op.', 'mcp-abilities-kadence' ) );
		}

		// 2. De rondgang. Wijkt de markup af van wat WordPress ervan maakt,
		//    dan wordt hij bij het opslaan herschreven. Dat is geen blokkade —
		//    we geven de herschreven vorm terug en daar gaat het token over —
		//    maar het hoort gemeld: wat je zag is niet precies wat er komt.
		$canoniek = Kadence_MCP_Inventory::serialiseer( $boom );

		if ( trim( self::zonder_witregels( $canoniek ) ) !== trim( self::zonder_witregels( $invoer ) ) ) {
			$waarschuwingen[] = array(
				'check'  => 'roundtrip',
				'detail' => __( 'WordPress schrijft deze markup iets anders weg dan hij binnenkwam (meestal alleen de codering van het blokcommentaar). Wat terugkomt in markup is de vorm die opgeslagen wordt.', 'mcp-abilities-kadence' ),
			);
		}

		// 3. Vervangingen, in volgorde, op alle tekst. Letterlijk: er wordt
		//    niets geraden.
		$vervangingen = array();
		$gemeld       = array();

		if ( ! empty( $input['replace'] ) && is_array( $input['replace'] ) ) {
			foreach ( $input['replace'] as $paar ) {
				if ( ! is_array( $paar ) || ! isset( $paar['from'] ) || '' === (string) $paar['from'] ) {
					continue;
				}

				$vervangingen[] = array( (string) $paar['from'], isset( $paar['to'] ) ? (string) $paar['to'] : '' );
			}
		}

		foreach ( $vervangingen as $i => $paar ) {
			$aantal = 0;
			$boom   = self::vervang_in_boom( $boom, $paar[0], $paar[1], $aantal );

			$gemeld[] = array( 'from' => $paar[0], 'to' => $paar[1], 'count' => $aantal );

			if ( 0 === $aantal ) {
				$waarschuwingen[] = array(
					'check'  => 'replace_unused',
					'detail' => sprintf(
						/* translators: %s: search string. */
						__( 'De vervanging van "%s" kwam nergens voor.', 'mcp-abilities-kadence' ),
						$paar[0]
					),
				);
			}
		}

		// 3b. Media- en term-ID's, alleen volgens een expliciete kaart.
		$media_kaart = self::id_kaart( isset( $input['media_map'] ) ? $input['media_map'] : array() );
		$term_kaart  = self::id_kaart( isset( $input['term_map'] ) ? $input['term_map'] : array() );
		$post_kaart  = self::id_kaart( isset( $input['post_map'] ) ? $input['post_map'] : array() );

		if ( ! empty( $media_kaart ) || ! empty( $term_kaart ) || ! empty( $post_kaart ) ) {
			$omgezet = array( 'media' => 0, 'terms' => 0, 'posts' => 0 );
			$boom    = self::zet_ids_om( $boom, $media_kaart, $term_kaart, $omgezet, $post_kaart );

			$gemeld[] = array( 'from' => 'media_map', 'to' => '', 'count' => $omgezet['media'] );
			$gemeld[] = array( 'from' => 'term_map', 'to' => '', 'count' => $omgezet['terms'] );
			$gemeld[] = array( 'from' => 'post_map', 'to' => '', 'count' => $omgezet['posts'] );
		}

		// 4. Nieuwe uniqueIDs met het prefix van DEZE post. Een ID dat in de
		//    invoer twee keer voorkomt kan niet eenduidig worden omgezet: twee
		//    blokken zouden weer hetzelfde ID krijgen en hun CSS delen.
		$in_invoer = Kadence_MCP_Inventory::verzamel_unique_ids( $boom );

		foreach ( $in_invoer as $id => $namen ) {
			if ( count( $namen ) > 1 ) {
				$problemen[] = array(
					'check'  => 'duplicate_unique_id',
					'detail' => sprintf(
						/* translators: 1: uniqueID, 2: number of blocks. */
						__( 'De uniqueID %1$s komt %2$d keer voor in de invoer. Twee blokken met hetzelfde ID delen hun CSS. Laat de editor het ene blok een nieuw ID geven en exporteer opnieuw.', 'mcp-abilities-kadence' ),
						$id,
						count( $namen )
					),
				);
			}
		}

		$bezet = Kadence_MCP_Inventory::verzamel_unique_ids( parse_blocks( $post->post_content ) );
		$kaart = array();

		foreach ( array_keys( $in_invoer ) as $oud ) {
			$nieuw = Kadence_MCP_Inventory::nieuwe_unique_id( $post->ID, $bezet );

			if ( '' === $nieuw ) {
				return new WP_Error( 'kadence_mcp_id_exhausted', __( 'Kon geen vrije uniqueID genereren.', 'mcp-abilities-kadence' ) );
			}

			$kaart[ $oud ]   = $nieuw;
			$bezet[ $nieuw ] = array( 'gereserveerd' );
		}

		$boom = Kadence_MCP_Inventory::hernoem_unique_ids( $boom, $kaart );

		// 5–7. De controles per blok.
		$tellers     = array();
		$ongetoetst  = array();
		$verwijzingen = array(
			'external_links' => array(),
			'media'          => array(),
			'icons'          => array(),
			'terms'          => array(),
			'posts'          => array(),
			'palette'        => array(),
			'css_classes'    => array(),
		);

		self::keur_import( $boom, $tellers, $ongetoetst, $problemen, $waarschuwingen, $verwijzingen );

		foreach ( Kadence_MCP_Abilities_Build::klassen_controle( $boom ) as $bevinding ) {
			$problemen[] = array(
				'check'     => 'classes',
				'block'     => $bevinding['block'],
				'unique_id' => $bevinding['unique_id'],
				'missing'   => $bevinding['missing'],
				'stale'     => $bevinding['stale'],
				'detail'    => __( 'De klassen in de markup passen niet bij de attributen. In de editor heet dat "ongeldige inhoud". Exporteer het blok opnieuw uit de editor.', 'mcp-abilities-kadence' ),
			);
		}

		$verwijzingen = self::beoordeel_verwijzingen( $verwijzingen, $waarschuwingen );

		$markup = Kadence_MCP_Inventory::serialiseer( $boom );

		$heeft_probleem = ! empty( $problemen );
		$riskant        = false;

		foreach ( $waarschuwingen as $waarschuwing ) {
			if ( ! empty( $waarschuwing['unresolved'] ) ) {
				$riskant = true;
			}
		}

		$accepteer = ! empty( $input['accept_warnings'] );

		if ( $heeft_probleem ) {
			$oordeel = 'blokkeer';
		} elseif ( $riskant && ! $accepteer ) {
			$oordeel = 'riskant';
		} else {
			$oordeel = 'veilig';
		}

		$token = 'veilig' === $oordeel
			? Kadence_MCP_Inventory::schrijf_token( $post, '__insert__', array( 'markup' => Kadence_MCP_Inventory::token_markup( $markup ) ) )
			: '';

		if ( 'blokkeer' === $oordeel ) {
			$status = sprintf(
				/* translators: %d: number of problems. */
				__( 'NIET invoegen: %d probleem/problemen, zie problems. Er is geen token.', 'mcp-abilities-kadence' ),
				count( $problemen )
			);
		} elseif ( 'riskant' === $oordeel ) {
			$status = __( 'Geen token: er zijn verwijzingen die op deze site niet bestaan, zie warnings met unresolved. Zet ze om met replace, of laat een mens beslissen en roep opnieuw aan met accept_warnings true.', 'mcp-abilities-kadence' );
		} else {
			$status = __( 'Gecontroleerd, er is NIETS opgeslagen. Geef markup en token letterlijk door aan insert-blocks (met position en relative_to naar keuze). Pas je de markup aan, dan vervalt het token. Open de post daarna één keer in de editor: of Kadence de blokken geldig vindt, kan alleen de editor zeggen.', 'mcp-abilities-kadence' );

			if ( $riskant ) {
				$status .= ' ' . __( 'Let op: er zijn onopgeloste verwijzingen, en die zijn met accept_warnings geaccepteerd.', 'mcp-abilities-kadence' );
			}
		}

		return array(
			'post'        => array( 'id' => $post->ID, 'title' => get_the_title( $post ), 'type' => $post->post_type ),
			'verdict'     => $oordeel,
			'blocks'      => (object) $tellers,
			'problems'    => $problemen,
			'warnings'    => $waarschuwingen,
			'references'  => (object) $verwijzingen,
			'not_checked' => (object) $ongetoetst,
			'replaced'    => $gemeld,
			'id_map'      => (object) $kaart,
			'markup'      => $markup,
			'token'       => $token,
			'status'      => $status,
		);
	}

	/**
	 * De grootste markup die prepare-import aanneemt.
	 *
	 * Een homepage met twaalf secties is een paar honderdduizend tekens. Groter
	 * is geen import meer maar een migratie, en daar hoort een ander gereedschap
	 * bij.
	 */
	const IMPORT_MAX_TEKENS = 600000;

	/**
	 * Loop de boom na: bestaat elk blok, kloppen de waarden, kloppen de tellers,
	 * en wat wijst er naar buiten.
	 *
	 * @param array $blokken        De boom.
	 * @param array $tellers        Aantal per bloktype.
	 * @param array $ongetoetst     Bloktypes zonder profiel, met aantal.
	 * @param array $problemen      Verzameling.
	 * @param array $waarschuwingen Verzameling.
	 * @param array $verwijzingen   Verzameling.
	 *
	 * @return void
	 */
	private static function keur_import( $blokken, &$tellers, &$ongetoetst, &$problemen, &$waarschuwingen, &$verwijzingen ) {
		foreach ( $blokken as $blok ) {
			$naam  = isset( $blok['blockName'] ) ? (string) $blok['blockName'] : '';
			$attrs = isset( $blok['attrs'] ) && is_array( $blok['attrs'] ) ? $blok['attrs'] : array();
			$id    = isset( $attrs['uniqueID'] ) ? (string) $attrs['uniqueID'] : '';

			if ( '' === $naam ) {
				continue;
			}

			$tellers[ $naam ] = isset( $tellers[ $naam ] ) ? $tellers[ $naam ] + 1 : 1;

			// Bestaat het bloktype hier? Kadence registreert een paar
			// kindblokken alleen in JavaScript; get_block kent die via hun
			// block.json, dus die tellen als bestaand.
			$geregistreerd = class_exists( 'WP_Block_Type_Registry' ) && WP_Block_Type_Registry::get_instance()->is_registered( $naam );

			if ( ! $geregistreerd && is_wp_error( Kadence_MCP_Inventory::get_block( $naam ) ) ) {
				$problemen[] = array(
					'check'     => 'unknown_block',
					'block'     => $naam,
					'unique_id' => $id,
					'detail'    => __( 'Dit bloktype bestaat op deze site niet — ontbreekt er een plug-in, of een andere versie ervan? In de editor wordt het een blok dat niet ondersteund wordt.', 'mcp-abilities-kadence' ),
				);
			}

			$profiel = Kadence_MCP_Profielen::van( $naam );

			if ( null === $profiel && 0 === strpos( $naam, 'kadence/' ) ) {
				$ongetoetst[ $naam ] = isset( $ongetoetst[ $naam ] ) ? $ongetoetst[ $naam ] + 1 : 1;
			}

			// 5. Elk attribuut: schema en waardenlijst.
			foreach ( $attrs as $sleutel => $waarde ) {
				$definitie = Kadence_MCP_Inventory::attribuut_definitie( $naam, $sleutel );

				if ( null !== $definitie ) {
					$reden = Kadence_MCP_Inventory::toets_waarde( $definitie, $waarde );

					if ( '' !== $reden ) {
						$problemen[] = array(
							'check'     => 'attribute_type',
							'block'     => $naam,
							'unique_id' => $id,
							'attribute' => $sleutel,
							'detail'    => $reden,
						);
					}
				}

				$bezwaar = Kadence_MCP_Profielen::toets_waarde( $naam, $sleutel, $waarde, $attrs );

				if ( '' !== $bezwaar ) {
					$problemen[] = array(
						'check'     => 'attribute_value',
						'block'     => $naam,
						'unique_id' => $id,
						'attribute' => $sleutel,
						'detail'    => $bezwaar,
					);
				}

				$genegeerd = Kadence_MCP_Profielen::genegeerd( $naam, $sleutel );

				if ( '' !== $genegeerd ) {
					$waarschuwingen[] = array(
						'check'     => 'ignored_attribute',
						'block'     => $naam,
						'unique_id' => $id,
						'attribute' => $sleutel,
						'detail'    => $genegeerd,
					);
				}
			}

			// 6. Tellers en toegestane kinderen.
			$kinderen = isset( $blok['innerBlocks'] ) && is_array( $blok['innerBlocks'] ) ? $blok['innerBlocks'] : array();

			if ( null !== $profiel && '' !== (string) $profiel['aantal_kinderen'] ) {
				$sleutel = $profiel['aantal_kinderen'];

				if ( array_key_exists( $sleutel, $attrs ) ) {
					$opgegeven = $attrs[ $sleutel ];
				} else {
					$definitie = Kadence_MCP_Inventory::attribuut_definitie( $naam, $sleutel );
					$opgegeven = ( is_array( $definitie ) && array_key_exists( 'default', $definitie ) ) ? $definitie['default'] : null;
				}

				if ( null !== $opgegeven && (int) $opgegeven !== count( $kinderen ) ) {
					$problemen[] = array(
						'check'     => 'child_count',
						'block'     => $naam,
						'unique_id' => $id,
						'attribute' => $sleutel,
						'detail'    => sprintf(
							/* translators: 1: attribute, 2: value, 3: number of child blocks. */
							__( '%1$s staat op %2$d, maar er zijn %3$d kindblokken. Kadence rekent met het attribuut, dus er verschijnen lege of ontbrekende onderdelen.', 'mcp-abilities-kadence' ),
							$sleutel,
							(int) $opgegeven,
							count( $kinderen )
						),
					);
				}
			}

			if ( null !== $profiel && ! empty( $profiel['kinderen'] ) ) {
				foreach ( $kinderen as $kind ) {
					$kindnaam = isset( $kind['blockName'] ) ? (string) $kind['blockName'] : '';

					if ( '' !== $kindnaam && ! in_array( $kindnaam, $profiel['kinderen'], true ) ) {
						$problemen[] = array(
							'check'     => 'child_type',
							'block'     => $naam,
							'unique_id' => $id,
							'detail'    => sprintf(
								/* translators: 1: child block, 2: allowed blocks. */
								__( 'Kindblok %1$s hoort hier niet; toegestaan is %2$s.', 'mcp-abilities-kadence' ),
								$kindnaam,
								implode( ', ', $profiel['kinderen'] )
							),
						);
					}
				}
			}

			// 7. Wat naar buiten wijst.
			self::verzamel_verwijzingen( $attrs, isset( $blok['innerHTML'] ) ? (string) $blok['innerHTML'] : '', $naam, $id, $verwijzingen );

			if ( ! empty( $kinderen ) ) {
				self::keur_import( $kinderen, $tellers, $ongetoetst, $problemen, $waarschuwingen, $verwijzingen );
			}
		}
	}

	/**
	 * Verzamel links, media, iconen, termen, paletkleuren en CSS-klassen.
	 *
	 * @param array  $attrs        De attributen.
	 * @param string $html         De eigen innerHTML van het blok.
	 * @param string $naam         De bloknaam.
	 * @param string $id           De uniqueID.
	 * @param array  $verwijzingen Verzameling.
	 *
	 * @return void
	 */
	private static function verzamel_verwijzingen( $attrs, $html, $naam, $id, &$verwijzingen ) {
		$teksten = array( $html );

		array_walk_recursive(
			$attrs,
			static function ( $waarde ) use ( &$teksten ) {
				if ( is_string( $waarde ) ) {
					$teksten[] = $waarde;
				}
			}
		);

		foreach ( $teksten as $tekst ) {
			if ( preg_match_all( '#https?://[^\s"\'<>()\\\\]+#i', $tekst, $m ) ) {
				foreach ( $m[0] as $url ) {
					$verwijzingen['external_links'][ rtrim( $url, '.,;' ) ] = true;
				}
			}

			if ( preg_match_all( '/\bkb-custom-(\d+)\b/', $tekst, $m ) ) {
				foreach ( $m[1] as $nummer ) {
					$verwijzingen['icons'][ (int) $nummer ] = true;
				}
			}

			if ( preg_match( '/^palette(\d+)$/', $tekst ) ) {
				$verwijzingen['palette'][ $tekst ] = true;
			}
		}

		// Media: een array met een id en een url-achtig veld ernaast is in
		// Kadence vrijwel altijd een bijlage (backgroundImg, image, mediaUrl).
		// Bij een blok dat met zijn id naar een post verwijst (een menu-item met
		// id en url) is dat paar op het bovenste niveau geen bijlage.
		$is_post = null !== self::post_verwijzing( $naam, $attrs );

		$zoek_media = static function ( $waarde, $bovenste = false ) use ( &$zoek_media, &$verwijzingen, $naam, $id, $is_post ) {
			if ( ! is_array( $waarde ) ) {
				return;
			}

			$url = '';

			foreach ( array( 'img', 'url', 'src', 'mediaUrl' ) as $veld ) {
				if ( isset( $waarde[ $veld ] ) && is_string( $waarde[ $veld ] ) && '' !== $waarde[ $veld ] ) {
					$url = $waarde[ $veld ];
				}
			}

			if ( '' !== $url && isset( $waarde['id'] ) && is_numeric( $waarde['id'] ) && (int) $waarde['id'] > 0 && ! ( $bovenste && $is_post ) ) {
				$verwijzingen['media'][] = array( 'id' => (int) $waarde['id'], 'url' => $url, 'block' => $naam, 'unique_id' => $id );
			}

			// Achtergronden bewaart Kadence als paar: bgImg met bgImgID ernaast
			// (Row Layout op het bovenste niveau, Sectie in backgroundImg), en
			// zo ook overlayBgImg/overlayBgImgID.
			foreach ( self::media_paren( $waarde ) as $paar ) {
				$verwijzingen['media'][] = array( 'id' => (int) $waarde[ $paar[1] ], 'url' => $waarde[ $paar[0] ], 'block' => $naam, 'unique_id' => $id );
			}

			foreach ( $waarde as $kind ) {
				$zoek_media( $kind );
			}
		};

		$zoek_media( $attrs, true );

		// Termen: Kadence bewaart gekozen termen als [{value, label}].
		foreach ( array( 'categories', 'tags' ) as $veld ) {
			if ( empty( $attrs[ $veld ] ) || ! is_array( $attrs[ $veld ] ) ) {
				continue;
			}

			foreach ( $attrs[ $veld ] as $term ) {
				if ( is_array( $term ) && isset( $term['value'] ) ) {
					$verwijzingen['terms'][] = array(
						'id'        => (int) $term['value'],
						'label'     => isset( $term['label'] ) ? (string) $term['label'] : '',
						'taxonomy'  => isset( $attrs['taxType'] ) ? (string) $attrs['taxType'] : '',
						'block'     => $naam,
						'unique_id' => $id,
					);
				}
			}
		}

		// Posts: blokken die met hun id naar een andere post verwijzen.
		$verwacht = self::post_verwijzing( $naam, $attrs );

		if ( null !== $verwacht && isset( $attrs['id'] ) && is_numeric( $attrs['id'] ) && (int) $attrs['id'] > 0 ) {
			$verwijzingen['posts'][] = array(
				'id'        => (int) $attrs['id'],
				'post_type' => $verwacht,
				'label'     => isset( $attrs['label'] ) && is_string( $attrs['label'] ) ? $attrs['label'] : '',
				'block'     => $naam,
				'unique_id' => $id,
			);
		}

		if ( ! empty( $attrs['className'] ) && is_string( $attrs['className'] ) ) {
			foreach ( preg_split( '/\s+/', trim( $attrs['className'] ) ) as $klasse ) {
				if ( '' !== $klasse ) {
					$verwijzingen['css_classes'][ $klasse ] = true;
				}
			}
		}
	}

	/**
	 * Zoek elke verwijzing op op DEZE site.
	 *
	 * Wat niet bestaat wordt een waarschuwing met unresolved, en daarmee geen
	 * token zonder accept_warnings. Wat wel bestaat komt als informatie in het
	 * rapport, zodat zichtbaar is wat er meegekomen is.
	 *
	 * @param array $verwijzingen   De verzamelde verwijzingen.
	 * @param array $waarschuwingen Verzameling.
	 *
	 * @return array Het rapport per soort.
	 */
	private static function beoordeel_verwijzingen( $verwijzingen, &$waarschuwingen ) {
		$eigen_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$rapport    = array();

		// Links.
		$extern = array();
		$intern_ontbreekt = array();

		foreach ( array_keys( $verwijzingen['external_links'] ) as $url ) {
			$host = wp_parse_url( $url, PHP_URL_HOST );

			if ( $host && $host !== $eigen_host ) {
				$extern[] = $url;
				continue;
			}

			// Op deze site: een upload moet een bijlage zijn, een pagina een post.
			if ( false !== strpos( $url, '/wp-content/uploads/' ) ) {
				if ( 0 === self::bijlage_voor_url( $url ) ) {
					$intern_ontbreekt[] = $url;
				}
			} elseif ( 0 === url_to_postid( $url ) && untrailingslashit( $url ) !== untrailingslashit( home_url() ) ) {
				$intern_ontbreekt[] = $url;
			}
		}

		$rapport['external_links'] = array_slice( $extern, 0, 50 );

		if ( ! empty( $extern ) ) {
			$hosts = array();

			foreach ( $extern as $url ) {
				$hosts[ (string) wp_parse_url( $url, PHP_URL_HOST ) ] = true;
			}

			$waarschuwingen[] = array(
				'check'      => 'external_links',
				'unresolved' => true,
				'detail'     => sprintf(
					/* translators: 1: number of links, 2: hosts. */
					__( '%1$d link(s) naar een ander domein (%2$s). Komt de markup van een andere omgeving, zet het domein dan om met replace. Is het bewust een externe link, accepteer dan.', 'mcp-abilities-kadence' ),
					count( $extern ),
					implode( ', ', array_keys( $hosts ) )
				),
			);
		}

		$rapport['missing_on_this_site'] = array_slice( $intern_ontbreekt, 0, 50 );

		if ( ! empty( $intern_ontbreekt ) ) {
			$waarschuwingen[] = array(
				'check'      => 'missing_links',
				'unresolved' => true,
				'detail'     => sprintf(
					/* translators: %d: number of links. */
					__( '%d link(s) naar deze site wijzen naar een pagina of bestand dat hier niet bestaat.', 'mcp-abilities-kadence' ),
					count( $intern_ontbreekt )
				),
			);
		}

		// Media.
		$media = array();

		foreach ( $verwijzingen['media'] as $item ) {
			$bijlage   = get_post( $item['id'] );
			$bestaat   = $bijlage && 'attachment' === $bijlage->post_type;
			$zelfde    = $bestaat && self::zelfde_bestand( wp_get_attachment_url( $item['id'] ), $item['url'] );
			$item['status'] = ! $bestaat ? 'missing' : ( $zelfde ? 'ok' : 'different_file' );
			$media[]  = $item;

			if ( 'ok' !== $item['status'] ) {
				$waarschuwingen[] = array(
					'check'      => 'media',
					'unresolved' => true,
					'block'      => $item['block'],
					'unique_id'  => $item['unique_id'],
					'detail'     => 'missing' === $item['status']
						? sprintf(
							/* translators: %d: attachment ID. */
							__( 'Media-ID %d bestaat hier niet. Upload het bestand en zet het ID om met media_map, of kies het beeld daarna in de editor opnieuw.', 'mcp-abilities-kadence' ),
							$item['id']
						)
						: sprintf(
							/* translators: %d: attachment ID. */
							__( 'Media-ID %d bestaat hier, maar is een ander bestand dan de URL ernaast. Op een andere site verwijst hetzelfde nummer meestal naar iets anders; zet het om met media_map.', 'mcp-abilities-kadence' ),
							$item['id']
						),
				);
			}
		}

		$rapport['media'] = $media;

		// Custom SVG-iconen.
		$iconen = array();

		foreach ( array_keys( $verwijzingen['icons'] ) as $nummer ) {
			$icoon   = get_post( $nummer );
			$bestaat = $icoon && 'kadence_custom_svg' === $icoon->post_type;

			$iconen[] = array(
				'icon'   => 'kb-custom-' . $nummer,
				'status' => $bestaat ? 'ok' : 'missing',
				'title'  => $bestaat ? get_the_title( $icoon ) : '',
			);

			if ( ! $bestaat ) {
				$waarschuwingen[] = array(
					'check'      => 'icon',
					'unresolved' => true,
					'detail'     => sprintf(
						/* translators: %d: post ID. */
						__( 'Custom SVG kb-custom-%d bestaat hier niet. Maak het icoon aan onder Kadence → Custom SVGs en zet het nummer om met replace.', 'mcp-abilities-kadence' ),
						$nummer
					),
				);
			}
		}

		$rapport['icons'] = $iconen;

		// Termen.
		$termen = array();

		foreach ( $verwijzingen['terms'] as $item ) {
			$term    = get_term( $item['id'] );
			$bestaat = $term && ! is_wp_error( $term );
			$item['status'] = ! $bestaat ? 'missing' : ( ( '' === $item['label'] || $term->name === $item['label'] ) ? 'ok' : 'different_term' );
			$termen[] = $item;

			if ( 'ok' !== $item['status'] ) {
				$waarschuwingen[] = array(
					'check'      => 'term',
					'unresolved' => true,
					'block'      => $item['block'],
					'unique_id'  => $item['unique_id'],
					'detail'     => sprintf(
						/* translators: 1: term ID, 2: label. */
						__( 'Term %1$d ("%2$s") bestaat hier niet of heet anders. Term-ID\'s verschillen per site; zet het om met term_map of kies het filter in de editor opnieuw.', 'mcp-abilities-kadence' ),
						$item['id'],
						$item['label']
					),
				);
			}
		}

		$rapport['terms'] = $termen;

		// Posts: bestaat het ID hier, en is het van het verwachte type? Een
		// navigatie-ID dat op deze site bij een pagina hoort, is erger dan een
		// ontbrekend: het blok toont dan stil niets of iets anders.
		$posts = array();

		foreach ( $verwijzingen['posts'] as $item ) {
			$doel           = get_post( $item['id'] );
			$item['status'] = ! $doel ? 'missing' : ( $doel->post_type === $item['post_type'] ? 'ok' : 'wrong_type' );
			$item['found']  = $doel ? array( 'post_type' => $doel->post_type, 'title' => get_the_title( $doel ) ) : null;
			$posts[]        = $item;

			if ( 'ok' !== $item['status'] ) {
				$waarschuwingen[] = array(
					'check'      => 'post',
					'unresolved' => true,
					'block'      => $item['block'],
					'unique_id'  => $item['unique_id'],
					'detail'     => 'missing' === $item['status']
						? sprintf(
							/* translators: 1: post ID, 2: post type. */
							__( 'Post %1$d (%2$s) bestaat hier niet. Post-ID\'s verschillen per site: maak hem hier aan en zet het ID om met post_map.', 'mcp-abilities-kadence' ),
							$item['id'],
							$item['post_type']
						)
						: sprintf(
							/* translators: 1: post ID, 2: expected post type, 3: found post type. */
							__( 'Post %1$d is hier een %3$s, geen %2$s. Hetzelfde nummer wijst op een andere site naar iets anders; zet het om met post_map.', 'mcp-abilities-kadence' ),
							$item['id'],
							$item['post_type'],
							$item['found']['post_type']
						),
				);
			}
		}

		$rapport['posts'] = $posts;

		// Paletkleuren: geen fout, wel van belang. palette6 is op elke site
		// iets anders.
		$stijlen = Kadence_MCP_Inventory::get_global_styles();
		$palet   = isset( $stijlen['palette']['value'] ) && is_array( $stijlen['palette']['value'] ) ? $stijlen['palette']['value'] : array();
		$actief  = isset( $palet['active'] ) ? (string) $palet['active'] : 'palette';
		$kleuren = array();

		if ( isset( $palet[ $actief ] ) && is_array( $palet[ $actief ] ) ) {
			foreach ( $palet[ $actief ] as $kleur ) {
				if ( isset( $kleur['slug'], $kleur['color'] ) ) {
					$kleuren[ (string) $kleur['slug'] ] = (string) $kleur['color'];
				}
			}
		}

		$paletrapport = array();

		foreach ( array_keys( $verwijzingen['palette'] ) as $slug ) {
			$paletrapport[ $slug ] = isset( $kleuren[ $slug ] ) ? $kleuren[ $slug ] : null;
		}

		ksort( $paletrapport, SORT_NATURAL );
		$rapport['palette_on_this_site'] = (object) $paletrapport;

		// Eigen klassen: die horen bij CSS van het thema of de site, en die
		// komt niet mee met de markup.
		$rapport['css_classes'] = array_keys( $verwijzingen['css_classes'] );

		if ( ! empty( $rapport['css_classes'] ) ) {
			$waarschuwingen[] = array(
				'check'  => 'css_classes',
				'detail' => sprintf(
					/* translators: %s: class names. */
					__( 'De markup gebruikt eigen CSS-klassen (%s). De CSS daarvoor komt niet mee; controleer dat die op deze site staat.', 'mcp-abilities-kadence' ),
					implode( ', ', $rapport['css_classes'] )
				),
			);
		}

		return $rapport;
	}

	/**
	 * Het bijlage-ID bij een upload-URL, ook voor een verkleinde versie.
	 *
	 * attachment_url_to_postid() kent alleen het origineel. Kadence en de
	 * editor bewaren vaak de URL van een formaat, zoals foto-1024x683.jpg.
	 *
	 * @param string $url De URL.
	 *
	 * @return int
	 */
	private static function bijlage_voor_url( $url ) {
		$id = attachment_url_to_postid( $url );

		if ( 0 === $id ) {
			$origineel = preg_replace( '/-\d+x\d+(\.[a-z0-9]+)$/i', '$1', $url );
			$id        = $origineel !== $url ? attachment_url_to_postid( $origineel ) : 0;
		}

		if ( 0 === $id ) {
			$geschaald = preg_replace( '/(\.[a-z0-9]+)$/i', '-scaled$1', preg_replace( '/-\d+x\d+(\.[a-z0-9]+)$/i', '$1', $url ) );
			$id        = attachment_url_to_postid( $geschaald );
		}

		return (int) $id;
	}

	/**
	 * Wijzen twee URL's naar hetzelfde bestand, formaat buiten beschouwing?
	 *
	 * @param string $a Eerste URL.
	 * @param string $b Tweede URL.
	 *
	 * @return bool
	 */
	private static function zelfde_bestand( $a, $b ) {
		$kaal = static function ( $url ) {
			$pad = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
			$pad = preg_replace( '/-\d+x\d+(\.[a-z0-9]+)$/i', '$1', $pad );
			$pad = preg_replace( '/-scaled(\.[a-z0-9]+)$/i', '$1', $pad );

			return basename( $pad );
		};

		return '' !== $kaal( $a ) && $kaal( $a ) === $kaal( $b );
	}

	/**
	 * Letterlijke vervanging in attributen en markup van een hele boom.
	 *
	 * @param array  $blokken De boom.
	 * @param string $van     Zoektekst.
	 * @param string $naar    Vervanging.
	 * @param int    $aantal  Teller.
	 *
	 * @return array
	 */
	private static function vervang_in_boom( $blokken, $van, $naar, &$aantal ) {
		foreach ( $blokken as $i => $blok ) {
			if ( isset( $blok['attrs'] ) && is_array( $blok['attrs'] ) ) {
				array_walk_recursive(
					$blokken[ $i ]['attrs'],
					static function ( &$waarde ) use ( $van, $naar, &$aantal ) {
						if ( is_string( $waarde ) && false !== strpos( $waarde, $van ) ) {
							$aantal += substr_count( $waarde, $van );
							$waarde  = str_replace( $van, $naar, $waarde );
						}
					}
				);
			}

			if ( isset( $blok['innerHTML'] ) && is_string( $blok['innerHTML'] ) ) {
				$blokken[ $i ]['innerHTML'] = str_replace( $van, $naar, $blok['innerHTML'] );
			}

			if ( isset( $blok['innerContent'] ) && is_array( $blok['innerContent'] ) ) {
				foreach ( $blok['innerContent'] as $j => $stuk ) {
					if ( is_string( $stuk ) ) {
						$aantal += substr_count( $stuk, $van );
						$blokken[ $i ]['innerContent'][ $j ] = str_replace( $van, $naar, $stuk );
					}
				}
			}

			if ( ! empty( $blok['innerBlocks'] ) ) {
				$blokken[ $i ]['innerBlocks'] = self::vervang_in_boom( $blok['innerBlocks'], $van, $naar, $aantal );
			}
		}

		return $blokken;
	}

	/**
	 * Maak van een aangeleverde kaart oud => nieuw een schone lijst gehele getallen.
	 *
	 * @param mixed $ruw De invoer.
	 *
	 * @return array<int,int>
	 */
	private static function id_kaart( $ruw ) {
		$uit = array();

		if ( ! is_array( $ruw ) && ! is_object( $ruw ) ) {
			return $uit;
		}

		foreach ( (array) $ruw as $oud => $nieuw ) {
			if ( is_numeric( $oud ) && is_numeric( $nieuw ) && (int) $oud > 0 && (int) $nieuw > 0 ) {
				$uit[ (int) $oud ] = (int) $nieuw;
			}
		}

		return $uit;
	}

	/**
	 * Zet media- en term-ID's om volgens de kaarten.
	 *
	 * Media: elke array met een id en een url-veld ernaast. De url wordt die
	 * van het nieuwe bestand, zodat id en url niet uit elkaar lopen.
	 * Termen: elementen van categories en tags in de vorm {value, label}.
	 *
	 * @param array $blokken     De boom.
	 * @param array $media_kaart oud => nieuw.
	 * @param array $term_kaart  oud => nieuw.
	 * @param array $omgezet     Tellers.
	 *
	 * @return array
	 */
	private static function zet_ids_om( $blokken, $media_kaart, $term_kaart, &$omgezet, $post_kaart = array() ) {
		$media = static function ( $waarde, $overslaan = false ) use ( &$media, $media_kaart, &$omgezet ) {
			if ( ! is_array( $waarde ) ) {
				return $waarde;
			}

			$url_veld = '';

			foreach ( array( 'img', 'url', 'src', 'mediaUrl' ) as $veld ) {
				if ( isset( $waarde[ $veld ] ) && is_string( $waarde[ $veld ] ) ) {
					$url_veld = $veld;
				}
			}

			if ( ! $overslaan && '' !== $url_veld && isset( $waarde['id'] ) && is_numeric( $waarde['id'] ) && isset( $media_kaart[ (int) $waarde['id'] ] ) ) {
				$nieuw         = $media_kaart[ (int) $waarde['id'] ];
				$waarde['id']  = $nieuw;
				$bestand       = wp_get_attachment_url( $nieuw );

				if ( $bestand ) {
					$waarde[ $url_veld ] = $bestand;
				}

				$omgezet['media']++;
			}

			foreach ( self::media_paren( $waarde ) as $paar ) {
				if ( isset( $media_kaart[ (int) $waarde[ $paar[1] ] ] ) ) {
					$nieuw               = $media_kaart[ (int) $waarde[ $paar[1] ] ];
					$waarde[ $paar[1] ]  = $nieuw;
					$bestand             = wp_get_attachment_url( $nieuw );

					if ( $bestand ) {
						$waarde[ $paar[0] ] = $bestand;
					}

					$omgezet['media']++;
				}
			}

			foreach ( $waarde as $sleutel => $kind ) {
				if ( is_array( $kind ) ) {
					$waarde[ $sleutel ] = $media( $kind );
				}
			}

			return $waarde;
		};

		foreach ( $blokken as $i => $blok ) {
			if ( isset( $blok['attrs'] ) && is_array( $blok['attrs'] ) ) {
				if ( ! empty( $media_kaart ) ) {
					// Het id op het bovenste niveau van een blok dat naar een post
					// verwijst is geen bijlage; wat dieper ligt (mediaImage) wel.
					$is_post                = null !== self::post_verwijzing( isset( $blok['blockName'] ) ? (string) $blok['blockName'] : '', $blok['attrs'] );
					$blokken[ $i ]['attrs'] = $media( $blok['attrs'], $is_post );
				}

				foreach ( array( 'categories', 'tags' ) as $veld ) {
					if ( empty( $term_kaart ) || empty( $blokken[ $i ]['attrs'][ $veld ] ) || ! is_array( $blokken[ $i ]['attrs'][ $veld ] ) ) {
						continue;
					}

					foreach ( $blokken[ $i ]['attrs'][ $veld ] as $j => $term ) {
						if ( is_array( $term ) && isset( $term['value'] ) && isset( $term_kaart[ (int) $term['value'] ] ) ) {
							$nieuw = $term_kaart[ (int) $term['value'] ];
							$obj   = get_term( $nieuw );

							$blokken[ $i ]['attrs'][ $veld ][ $j ]['value'] = $nieuw;

							if ( $obj && ! is_wp_error( $obj ) ) {
								$blokken[ $i ]['attrs'][ $veld ][ $j ]['label'] = $obj->name;
							}

							$omgezet['terms']++;
						}
					}
				}
			}

			if ( ! empty( $post_kaart ) && isset( $blok['attrs']['id'] ) && is_numeric( $blok['attrs']['id'] ) && isset( $post_kaart[ (int) $blok['attrs']['id'] ] ) ) {
				$naam = isset( $blok['blockName'] ) ? (string) $blok['blockName'] : '';

				if ( null !== self::post_verwijzing( $naam, $blok['attrs'] ) ) {
					$nieuw = $post_kaart[ (int) $blok['attrs']['id'] ];

					$blokken[ $i ]['attrs']['id'] = $nieuw;

					// Een menu-item naar een post draagt ook de url; die hoort
					// bij de nieuwe post, anders wijst de link naar de oude site.
					if ( 'kadence/navigation-link' === $naam && isset( $blok['attrs']['url'] ) ) {
						$link = get_permalink( $nieuw );

						if ( $link ) {
							$blokken[ $i ]['attrs']['url'] = $link;
						}
					}

					$omgezet['posts']++;
				}
			}

			if ( ! empty( $blok['innerBlocks'] ) ) {
				$blokken[ $i ]['innerBlocks'] = self::zet_ids_om( $blok['innerBlocks'], $media_kaart, $term_kaart, $omgezet, $post_kaart );
			}
		}

		return $blokken;
	}

	/**
	 * De paren url + ID in één array, zoals Kadence ze voor achtergronden
	 * bewaart: bgImg met bgImgID, overlayBgImg met overlayBgImgID. Een sleutel
	 * op ID met een getal, en dezelfde sleutel zonder ID met een tekst ernaast.
	 *
	 * @param array $waarde De array.
	 *
	 * @return array<int,array{0:string,1:string}> Lijst van [url-sleutel, id-sleutel].
	 */
	private static function media_paren( $waarde ) {
		$paren = array();

		foreach ( $waarde as $sleutel => $inhoud ) {
			if ( ! is_string( $sleutel ) || 'ID' !== substr( $sleutel, -2 ) || strlen( $sleutel ) < 3 ) {
				continue;
			}

			$url_sleutel = substr( $sleutel, 0, -2 );

			if ( is_numeric( $inhoud ) && (int) $inhoud > 0 && isset( $waarde[ $url_sleutel ] ) && is_string( $waarde[ $url_sleutel ] ) && '' !== $waarde[ $url_sleutel ] ) {
				$paren[] = array( $url_sleutel, $sleutel );
			}
		}

		return $paren;
	}

	/**
	 * Naar welk posttype verwijst het id van dit blok, of null als het id
	 * geen post is.
	 *
	 * Afgelezen uit de render van Kadence, die bij elk van deze blokken het
	 * posttype controleert. Andere blokken met een id gebruiken het voor iets
	 * anders: een bijlage (kadence/image), een volgnummer (kadence/tab,
	 * kadence/slide, kadence/column). Die horen hier dus niet bij.
	 *
	 * @param string $naam  De bloknaam.
	 * @param array  $attrs De attributen.
	 *
	 * @return string|null
	 */
	private static function post_verwijzing( $naam, $attrs ) {
		$vast = array(
			'kadence/navigation'    => 'kadence_navigation',
			'kadence/header'        => 'kadence_header',
			'kadence/query'         => 'kadence_query',
			'kadence/query-card'    => 'kadence_query_card',
			'kadence/vector'        => 'kadence_vector',
			'kadence/advanced-form' => 'kadence_form',
		);

		if ( isset( $vast[ $naam ] ) ) {
			return $vast[ $naam ];
		}

		// Een menu-item naar een post of pagina (de Navigation Builder zet die
		// zo neer): kind post-type, met het posttype in type.
		if ( 'kadence/navigation-link' === $naam && isset( $attrs['kind'], $attrs['type'] ) && 'post-type' === $attrs['kind'] && is_string( $attrs['type'] ) && '' !== $attrs['type'] ) {
			return $attrs['type'];
		}

		return null;
	}

	/**
	 * Markup zonder lege regels, voor een vergelijking die alleen inhoud telt.
	 *
	 * @param string $markup De markup.
	 *
	 * @return string
	 */
	private static function zonder_witregels( $markup ) {
		return preg_replace( "/\n\s*\n/", "\n", str_replace( "\r\n", "\n", (string) $markup ) );
	}

	/**
	 * Een kort stuk tekst voor in een melding.
	 *
	 * @param string $tekst De tekst.
	 *
	 * @return string
	 */
	private static function kort_fragment( $tekst ) {
		$tekst = trim( wp_strip_all_tags( $tekst ) );

		return strlen( $tekst ) > 80 ? substr( $tekst, 0, 80 ) . '…' : $tekst;
	}

	/**
	 * Maak een nieuwe pagina, standaard als CONCEPT.
	 *
	 * Waarom concept: wat hier uit komt is opzet, geen gepubliceerde tekst. Een
	 * pagina die meteen live staat is met één aanroep zichtbaar voor iedereen,
	 * en terugdraaien kan dan niet meer ongezien. Publiceren is een aparte,
	 * bewuste stap.
	 *
	 * De inhoud komt als blokmarkup binnen, en die wordt op dezelfde manier
	 * gecontroleerd als bij insert-blocks: parsen, serialiseren, en kijken of je
	 * hetzelfde terugkrijgt. Levert dat iets anders op, dan zou WordPress de
	 * markup bij het opslaan herschrijven en klopt wat je ziet niet met wat er
	 * staat.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function create_page( $input = array() ) {
		$titel = isset( $input['title'] ) ? trim( wp_strip_all_tags( (string) $input['title'] ) ) : '';

		if ( '' === $titel ) {
			return new WP_Error( 'kadence_mcp_no_title', __( 'Geef de pagina een titel.', 'mcp-abilities-kadence' ) );
		}

		$ouder = isset( $input['parent_id'] ) ? (int) $input['parent_id'] : 0;

		if ( $ouder > 0 ) {
			$ouder_post = get_post( $ouder );

			if ( ! $ouder_post || 'page' !== $ouder_post->post_type ) {
				return new WP_Error(
					'kadence_mcp_bad_parent',
					sprintf(
						/* translators: %d: post ID. */
						__( 'Post %d bestaat niet of is geen pagina, dus hij kan geen bovenliggende pagina zijn.', 'mcp-abilities-kadence' ),
						$ouder
					)
				);
			}
		}

		$markup = isset( $input['markup'] ) ? (string) $input['markup'] : '';
		$blokken = array();

		if ( '' !== trim( $markup ) ) {
			$blokken = Kadence_MCP_Inventory::schoon_blokken( parse_blocks( $markup ) );

			if ( empty( $blokken ) ) {
				return new WP_Error( 'kadence_mcp_page_unparsable', __( 'De aangeleverde markup levert geen blokken op bij het parsen.', 'mcp-abilities-kadence' ) );
			}

			$schoon  = Kadence_MCP_Inventory::serialiseer( $blokken );
			$opnieuw = Kadence_MCP_Inventory::serialiseer( parse_blocks( $schoon ) );

			if ( $opnieuw !== $schoon ) {
				return new WP_Error( 'kadence_mcp_page_unstable', __( 'De markup overleeft een parse- en serialiseerronde niet ongewijzigd; WordPress zou hem bij het opslaan herschrijven. Er wordt niets aangemaakt.', 'mcp-abilities-kadence' ) );
			}

			$markup = $schoon;
		}

		$status = isset( $input['status'] ) && 'publish' === $input['status'] ? 'publish' : 'draft';
		$token  = isset( $input['token'] ) ? (string) $input['token'] : '';

		$grondslag = 'kmcp1_' . substr(
			wp_hash( (string) wp_json_encode( array( 'titel' => $titel, 'ouder' => $ouder, 'markup' => $markup, 'status' => $status ) ) ),
			0,
			32
		);

		$rapport = array(
			'title'     => $titel,
			'parent'    => $ouder > 0 ? array( 'id' => $ouder, 'title' => get_the_title( $ouder ) ) : null,
			'status'    => $status,
			'block_count' => count( $blokken ),
		);

		if ( '' === $token ) {
			return array_merge(
				$rapport,
				array(
					'new_id'  => 0,
					'url'     => '',
					'created' => false,
					'token'   => $grondslag,
					'note'    => sprintf(
						/* translators: 1: title, 2: number of blocks. */
						__( 'Voorstel, er is NIETS aangemaakt. Er zou een pagina "%1$s" komen met %2$d blokken. Roep opnieuw aan met het token om hem te maken.', 'mcp-abilities-kadence' ),
						$titel,
						count( $blokken )
					),
				)
			);
		}

		if ( ! Kadence_MCP_Capabilities::current_user_can( Kadence_MCP_Capabilities::WRITE ) ) {
			return new WP_Error(
				'kadence_mcp_write_denied',
				__( 'Je hebt de capability kadence_mcp_write niet.', 'mcp-abilities-kadence' ),
				array( 'status' => 403 )
			);
		}

		$type_object = get_post_type_object( 'page' );
		$mag_maken   = ( $type_object && isset( $type_object->cap->create_posts ) ) ? (string) $type_object->cap->create_posts : 'edit_pages';

		if ( ! current_user_can( $mag_maken ) ) {
			return new WP_Error(
				'kadence_mcp_create_denied',
				sprintf(
					/* translators: %s: capability. */
					__( 'Je mist de capability "%s", die nodig is om een pagina aan te maken.', 'mcp-abilities-kadence' ),
					$mag_maken
				),
				array( 'status' => 403 )
			);
		}

		// Publiceren vraagt meer dan aanmaken. Wie alleen mag schrijven krijgt
		// een concept, ook als hij publish vroeg — en hoort dat te horen.
		if ( 'publish' === $status && ! current_user_can( 'publish_pages' ) ) {
			return new WP_Error(
				'kadence_mcp_publish_denied',
				__( 'Je mag geen pagina\'s publiceren. Laat status weg voor een concept.', 'mcp-abilities-kadence' )
			);
		}

		if ( ! hash_equals( $grondslag, $token ) ) {
			return new WP_Error( 'kadence_mcp_invalid_token', __( 'Het token hoort niet bij deze invoer. Roep opnieuw zonder token aan en gebruik het token dat je dan terugkrijgt.', 'mcp-abilities-kadence' ) );
		}

		$nieuw_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => $status,
				'post_title'   => $titel,
				'post_parent'  => $ouder,
				'post_content' => wp_slash( $markup ),
			),
			true
		);

		if ( is_wp_error( $nieuw_id ) ) {
			return $nieuw_id;
		}

		clean_post_cache( $nieuw_id );

		$controle = get_post( $nieuw_id );
		$staat    = $controle && count( Kadence_MCP_Inventory::schoon_blokken( parse_blocks( $controle->post_content ) ) ) === count( $blokken );

		return array_merge(
			$rapport,
			array(
				'new_id'  => (int) $nieuw_id,
				'url'     => (string) get_permalink( $nieuw_id ),
				'created' => true,
				'token'   => '',
				'note'    => $staat
					? sprintf(
						/* translators: 1: post ID, 2: status. */
						__( 'Aangemaakt als pagina %1$d met status %2$s. Teruggelezen: alle blokken staan erin.', 'mcp-abilities-kadence' ),
						(int) $nieuw_id,
						$status
					)
					: __( 'LET OP: de pagina is aangemaakt maar bij het teruglezen klopt het aantal blokken niet. Controleer hem.', 'mcp-abilities-kadence' ),
			)
		);
	}

	/**
	 * Zet de status van een pagina.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function set_page_status( $input = array() ) {
		$post = self::post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );

		if ( is_wp_error( $post ) ) {
			return $post;
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

		$naar = isset( $input['status'] ) ? (string) $input['status'] : '';

		if ( ! in_array( $naar, array( 'publish', 'draft', 'private' ), true ) ) {
			return new WP_Error( 'kadence_mcp_bad_status', __( 'Status moet publish, draft of private zijn.', 'mcp-abilities-kadence' ) );
		}

		// Publiceren is een eigen recht, los van bewerken. Zonder deze controle
		// zou wp_update_post de status stilzwijgend op pending zetten en zou het
		// antwoord "gepubliceerd" melden terwijl er niets openbaar is.
		if ( 'publish' === $naar ) {
			$type = get_post_type_object( $post->post_type );

			if ( ! $type || ! current_user_can( $type->cap->publish_posts ) ) {
				return new WP_Error(
					'kadence_mcp_publish_denied',
					__( 'Je mag dit posttype niet publiceren. Dat is een apart recht; bewerken volstaat hier niet.', 'mcp-abilities-kadence' ),
					array( 'status' => 403 )
				);
			}
		}

		$van = (string) $post->post_status;

		$rapport = array(
			'post' => array(
				'id'    => (int) $post->ID,
				'title' => (string) $post->post_title,
				'type'  => (string) $post->post_type,
			),
			'from' => $van,
			'to'   => $naar,
		);

		$verwacht = Kadence_MCP_Inventory::schrijf_token( $post, 'status', array( 'status' => $naar ) );
		$token    = isset( $input['token'] ) ? (string) $input['token'] : '';

		if ( '' === $token ) {
			return array_merge(
				$rapport,
				array(
					'slug'    => (string) $post->post_name,
					'url'     => (string) get_permalink( $post ),
					'changed' => false,
					'token'   => $verwacht,
					'status'  => ( $van === $naar )
						? __( 'Voorstel, er is NIETS gewijzigd — de pagina heeft deze status al.', 'mcp-abilities-kadence' )
						: sprintf(
							/* translators: 1: old status, 2: new status. */
							__( 'Voorstel, er is NIETS gewijzigd. De status zou van %1$s naar %2$s gaan. Roep opnieuw aan met het token om het echt te doen.', 'mcp-abilities-kadence' ),
							$van,
							$naar
						),
				)
			);
		}

		if ( ! hash_equals( $verwacht, $token ) ) {
			return new WP_Error(
				'kadence_mcp_bad_token',
				Kadence_MCP_Inventory::token_reden( $token, $verwacht, $post )
			);
		}

		$resultaat = wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => $naar,
			),
			true
		);

		if ( is_wp_error( $resultaat ) ) {
			return $resultaat;
		}

		clean_post_cache( $post->ID );

		$na = get_post( $post->ID );

		return array_merge(
			$rapport,
			array(
				'slug'    => $na ? (string) $na->post_name : '',
				'url'     => $na ? (string) get_permalink( $na ) : '',
				'changed' => $na && (string) $na->post_status === $naar,
				'token'   => '',
				'status'  => ( $na && (string) $na->post_status === $naar )
					? sprintf(
						/* translators: 1: status, 2: url. */
						__( 'Geschreven en teruggelezen: de status staat op %1$s en de pagina staat op %2$s. Controleer die URL — bij een botsende slug hangt WordPress er een volgnummer achter.', 'mcp-abilities-kadence' ),
						$naar,
						$na ? (string) get_permalink( $na ) : ''
					)
					: sprintf(
						/* translators: %s: actual status. */
						__( 'LET OP: er is geschreven, maar bij het teruglezen staat de status op %s. Controleer de pagina.', 'mcp-abilities-kadence' ),
						$na ? (string) $na->post_status : 'onbekend'
					),
			)
		);
	}
}
