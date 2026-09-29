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
							'notes'      => array( 'type' => 'array' ),
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
				'name' => 'kadence/move-blocks',
				'args' => array(
					'label'       => __( 'Blokken verplaatsen binnen een post', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Verplaatst een of meer blokken naar een andere plek in dezelfde post, met behoud van hun uniqueID.', 'mcp-abilities-kadence' ),
					'description' => __( 'Verplaatst blokken (met alles eronder) naar een nieuwe plek in dezelfde post: voor of na een ander blok, of als eerste of laatste kind binnen een container. De uniqueIDs blijven staan, dus de CSS per blok en verwijzingen ernaar (facetten, ankers) blijven werken; verwijderen en opnieuw invoegen zou nieuwe ID\'s geven. Meerdere blokken komen in de opgegeven volgorde op de nieuwe plek. Weigert als het doel in een van de verplaatste blokken ligt, als een Sectie (kadence/column) uit of in een Row Layout zou gaan (het kolomaantal van de rij klopt dan niet meer), en als een blok volgens zijn parent-regel niet onder de nieuwe ouder mag. Voor het schrijven wordt gecontroleerd dat er geen blok verdwijnt. Twee stappen: eerst zonder token voor een voorstel, daarna met token. Er wordt een revisie gemaakt.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id'    => array( 'type' => 'integer', 'minimum' => 1 ),
							'unique_ids' => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => __( 'De blokken die verhuizen, in de volgorde waarin ze op de nieuwe plek moeten staan.', 'mcp-abilities-kadence' ),
							),
							'target'     => array(
								'type'        => 'string',
								'description' => __( 'Het uniqueID van het blok waar ze naast of in komen.', 'mcp-abilities-kadence' ),
							),
							'position'   => array(
								'type'        => 'string',
								'enum'        => array( 'before', 'after', 'inside_start', 'inside_end' ),
								'description' => __( 'before/after: naast target. inside_start/inside_end: als eerste of laatste kind binnen target.', 'mcp-abilities-kadence' ),
							),
							'token'      => array( 'type' => 'string' ),
						),
						'required'             => array( 'post_id', 'unique_ids', 'target', 'position' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post'     => array( 'type' => 'object' ),
							'moving'   => array( 'type' => 'array' ),
							'from'     => array( 'type' => 'array' ),
							'to'       => array( 'type' => 'object' ),
							'moved'    => array( 'type' => 'boolean' ),
							'token'    => array( 'type' => 'string' ),
							'status'   => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'move_blocks' ),
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
				'name' => 'kadence/create-entity',
				'args' => array(
					'label'       => __( 'Een Kadence-object aanmaken: navigatie, header, element of vector', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Maakt een lege kadence_navigation, kadence_header of kadence_element met de volledige set instellingen van Kadence, of een kadence_vector uit een SVG.', 'mcp-abilities-kadence' ),
					'description' => __( 'Maakt een nieuw Kadence-object aan, zodat je er daarna blokken in kunt zetten (prepare-import of generate-section, dan insert-blocks) en instellingen op kunt schrijven (set-entity-meta). Nodig bij het overzetten naar een andere omgeving: daar bestaan de navigaties, headers en elementen nog niet, en blokken die ernaar verwijzen hebben hun nieuwe ID nodig (post_map in prepare-import). Een navigatie, header of element wordt LEEG aangemaakt, maar met ALLE instellingen die Kadence voor dat posttype registreert, op hun standaardwaarde — anders weigert set-entity-meta ze later, omdat een sleutel die er niet staat een typefout of een onbekende instelling kan zijn. Met meta zet je meteen afwijkende instellingen (alleen sleutels die Kadence voor dit posttype registreert). Een vector wordt gemaakt uit svg, via de eigen route van Kadence (kb-vector/v1/vectors) en dus door de sanitizer van Kadence; die is altijd gepubliceerd. Status is standaard draft: een gepubliceerd element met een hook als replace_footer vervangt meteen de footer van de hele site, ook als het nog leeg is. Twee stappen: eerst zonder token voor een voorstel, daarna met token om aan te maken.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_type' => array(
								'type'        => 'string',
								'enum'        => array( 'kadence_navigation', 'kadence_header', 'kadence_element', 'kadence_vector' ),
								'description' => __( 'Wat er gemaakt wordt. Een Query Loop of Query Card maak je met create-query en create-query-card.', 'mcp-abilities-kadence' ),
							),
							'title' => array(
								'type'        => 'string',
								'description' => __( 'De naam waarmee het object in het beheer en in de editor te kiezen is.', 'mcp-abilities-kadence' ),
							),
							'slug' => array(
								'type'        => 'string',
								'description' => __( 'Optioneel. Handig bij een element dat code op slug opzoekt: de slug is op elke omgeving gelijk, het ID niet.', 'mcp-abilities-kadence' ),
							),
							'status' => array(
								'type'        => 'string',
								'enum'        => array( 'draft', 'publish' ),
								'default'     => 'draft',
								'description' => __( 'draft (standaard) of publish. Niet van toepassing op een vector.', 'mcp-abilities-kadence' ),
							),
							'meta' => array(
								'type'                 => 'object',
								'additionalProperties' => true,
								'description'          => __( 'Instellingen die afwijken van de standaard, bijvoorbeeld {"_kad_navigation_orientation":"vertical"} of {"_kad_element_hook":"replace_footer"}. Alleen sleutels die Kadence voor dit posttype registreert.', 'mcp-abilities-kadence' ),
							),
							'svg' => array(
								'type'        => 'string',
								'description' => __( 'Alleen bij kadence_vector: de SVG-code. Het voorstel rekent uit wat er na de sanitizer van Kadence en (zonder unfiltered_html) kses van WordPress van overblijft, en weigert als er elementen wegvallen.', 'mcp-abilities-kadence' ),
							),
							'accept_loss' => array(
								'type'        => 'boolean',
								'default'     => false,
								'description' => __( 'Alleen bij kadence_vector: toch aanmaken als het voorstel voorspelt dat er SVG-elementen wegvallen.', 'mcp-abilities-kadence' ),
							),
							'token' => array( 'type' => 'string' ),
						),
						'required'             => array( 'post_type', 'title' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_type' => array( 'type' => 'string' ),
							'title'     => array( 'type' => 'string' ),
							'status'    => array( 'type' => 'string' ),
							'meta'      => array( 'type' => 'object' ),
							'new_id'    => array( 'type' => 'integer' ),
							'created'   => array( 'type' => 'boolean' ),
							'token'     => array( 'type' => 'string' ),
							'next'      => array( 'type' => 'string' ),
							'note'      => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'create_entity' ),
				),
			),
			array(
				'name' => 'kadence/update-entity-content',
				'args' => array(
					'label'       => __( 'De inhoud van een vector of custom SVG bijwerken', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Vervangt tekst in de SVG van een kadence_vector of de JSON van een kadence_custom_svg, bijvoorbeeld een kleur.', 'mcp-abilities-kadence' ),
					'description' => __( 'Voor de twee Kadence-objecten waarvan de inhoud geen blokken zijn: een kadence_vector (kale SVG) en een kadence_custom_svg (een icoon als JSON, bijvoorbeeld kb-custom-112). Geef replace als lijst van {from, to, count}: count is het aantal keer dat from er nu moet staan, en klopt dat niet, dan gebeurt er niets — zo vervang je niet per ongeluk iets anders. Bij een vector wordt vooraf uitgerekend of kses (zonder unfiltered_html) elementen zou weghalen; bij een custom SVG moet de JSON geldig blijven. Twee stappen: eerst zonder token voor een voorstel (met de huidige inhoud in before), daarna met token. Er wordt teruggelezen.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
							'replace' => array(
								'type'  => 'array',
								'items' => array(
									'type'       => 'object',
									'properties' => array(
										'from'             => array( 'type' => 'string' ),
										'to'               => array( 'type' => 'string' ),
										'count'            => array( 'type' => 'integer', 'minimum' => 0 ),
										'case_insensitive' => array( 'type' => 'boolean' ),
									),
									'required'   => array( 'from', 'to' ),
								),
							),
							'token' => array( 'type' => 'string' ),
						),
						'required'             => array( 'post_id', 'replace' ),
						'additionalProperties' => false,
					),
					'execute_callback' => array( __CLASS__, 'update_entity_content' ),
				),
			),
			array(
				'name' => 'kadence/trash-post',
				'args' => array(
					'label'       => __( 'Een post in de prullenbak zetten', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Zet een pagina, bericht of Kadence-object in de prullenbak, na een controle waar het nog gebruikt wordt.', 'mcp-abilities-kadence' ),
					'description' => __( 'Zet één post in de prullenbak — nooit definitief verwijderen; dat blijft in het beheer, met een mens erbij. Vooraf wordt gezocht waar het object nog gebruikt wordt: op id-attribuut (zoals find-usages) en bij een custom SVG ook op de iconnaam kb-custom-{ID}. Is er gebruik, dan komt er geen token, want een verwijzing naar een post in de prullenbak laat het blok stil verdwijnen; geef ignore_usages: true als het toch de bedoeling is. Altijd riskant, ook met ignore_usages (zie objections): een actief element op een hook (zoals de footer), de voorpagina, de berichtenpagina, de privacypagina, en een scan die faalde of begrensd was. Handig bij het opruimen van dubbel aangemaakte objecten. Twee stappen: eerst zonder token, daarna met token.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id'       => array( 'type' => 'integer', 'minimum' => 1 ),
							'ignore_usages' => array( 'type' => 'boolean', 'default' => false ),
							'token'         => array( 'type' => 'string' ),
						),
						'required'             => array( 'post_id' ),
						'additionalProperties' => false,
					),
					'execute_callback' => array( __CLASS__, 'trash_post' ),
				),
			),
			array(
				'name' => 'kadence/export-entity',
				'args' => array(
					'label'       => __( 'Een Kadence-object exporteren als pakket', 'mcp-abilities-kadence' ),
					'summary'     => __( 'Inhoud én _kad-instellingen van een navigatie, header, element, query, card of vector, met de ID\'s die op een andere site een kaart nodig hebben.', 'mcp-abilities-kadence' ),
					'description' => __( 'Leest een Kadence-object uit als één pakket: de blokken in post_content en de weergave-instellingen in _kad-meta (schaduwen, kleuren, plaatsing, breedtes) — het deel dat get-raw-markup en prepare-import niet meenemen. Noemt ook de posts en media waarnaar verwezen wordt, met type, titel, slug of bestandsnaam, zodat je hun tegenstuk op de andere site kunt opzoeken. Geef het pakket daarna aan import-entity op de andere site. Schrijft niets.', 'mcp-abilities-kadence' ),
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
						),
						'required'             => array( 'post_id' ),
						'additionalProperties' => false,
					),
					'execute_callback' => array( __CLASS__, 'export_entity' ),
				),
			),
			array(
				'name' => 'kadence/import-entity',
				'args' => array(
					'label'       => __( 'Een Kadence-object uit een pakket neerzetten', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Zet een pakket uit export-entity neer als nieuw object, of over een bestaand, met ID-kaarten en teruglezen.', 'mcp-abilities-kadence' ),
					'description' => __( 'De overzetting van een Kadence-object zonder de schade van Kadence\' eigen export en import (die haalt backslashes uit de inhoud, zodat \\u002d u002d wordt en var(--…) en klassen stil breken). De inhoud gaat door dezelfde omzetting als prepare-import: replace ({from, to}, bijvoorbeeld het domein), post_map, media_map en term_map (oud ID → ID op deze site). De uniqueID\'s blijven gelijk, zodat find-post met unique_id het tegenstuk terugvindt. Met target_id wordt een bestaand object overschreven (inhoud met revisie, meta zonder); zonder target_id komt er een nieuw object, standaard als concept. Alleen meta-sleutels die Kadence hier registreert; ID\'s ín de meta worden niet omgezet en staan in meta_ids_to_check. Na het opslaan wordt byte voor byte teruggelezen. Twee stappen: eerst zonder token, daarna met token.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'package'   => array( 'type' => 'object', 'additionalProperties' => true, 'description' => __( 'Het veld package uit export-entity.', 'mcp-abilities-kadence' ) ),
							'target_id' => array( 'type' => 'integer', 'minimum' => 1, 'description' => __( 'Optioneel: het bestaande object op deze site dat overschreven wordt.', 'mcp-abilities-kadence' ) ),
							'post_map'  => array( 'type' => 'object', 'additionalProperties' => true ),
							'media_map' => array( 'type' => 'object', 'additionalProperties' => true ),
							'term_map'  => array( 'type' => 'object', 'additionalProperties' => true ),
							'replace'   => array(
								'type'  => 'array',
								'items' => array(
									'type'       => 'object',
									'properties' => array(
										'from' => array( 'type' => 'string' ),
										'to'   => array( 'type' => 'string' ),
									),
								),
							),
							'status'    => array( 'type' => 'string', 'enum' => array( 'draft', 'publish' ) ),
							'token'     => array( 'type' => 'string' ),
						),
						'required'             => array( 'package' ),
						'additionalProperties' => false,
					),
					'execute_callback' => array( __CLASS__, 'import_entity' ),
				),
			),
			array(
				'name' => 'kadence/create-post',
				'args' => array(
					'label'       => __( 'Een bericht of een post van een eigen posttype aanmaken', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Maakt een post van elk publiek posttype (een bericht, een dienst, een markt …) met titel, samenvatting, volgorde, uitgelichte afbeelding, termen en ACF-velden; alles wordt tegen het posttype getoetst.', 'mcp-abilities-kadence' ),
					'description' => __( 'Maakt één post aan van een posttype dat op deze site bestaat en in de REST-API staat: post of een eigen posttype. Niet voor pagina\'s (create-page), bijlagen, of de posttypes van Kadence zelf (create-entity, create-query, create-query-card). Eén ability voor alle posttypes, omdat wat per type verschilt in WordPress zelf staat en hier per aanroep wordt uitgelezen en getoetst: excerpt, menu_order en featured_image alleen als het posttype dat ondersteunt; terms alleen voor taxonomieën die aan dit posttype hangen, met termen die bestaan (op slug of ID); acf alleen voor velden uit een ACF-veldgroep die op dit posttype geldt, op veldnaam, en een relatieveld alleen met ID\'s van bestaande posts van een toegestaan type. Wat niet klopt wordt geweigerd in plaats van stil opgeslagen. content is optioneel blokmarkup en gaat door dezelfde parse- en serialiseercontrole als create-page. Standaard een concept. Bij het overzetten naar een andere omgeving: maak eerst de posts waar andere naar verwijzen, en gebruik hun nieuwe ID\'s in post_map van prepare-import. Twee stappen: eerst zonder token voor een voorstel, daarna met token om aan te maken.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_type'      => array( 'type' => 'string', 'description' => __( 'Het posttype, bijvoorbeeld post, service of market.', 'mcp-abilities-kadence' ) ),
							'title'          => array( 'type' => 'string' ),
							'slug'           => array( 'type' => 'string', 'description' => __( 'Optioneel; anders maakt WordPress hem uit de titel.', 'mcp-abilities-kadence' ) ),
							'status'         => array( 'type' => 'string', 'enum' => array( 'draft', 'publish' ), 'default' => 'draft' ),
							'date'           => array( 'type' => 'string', 'description' => __( 'Publicatiedatum, JJJJ-MM-DD of JJJJ-MM-DD UU:MM:SS in de tijdzone van de site. Zonder datum zet WordPress het moment van aanmaken — bij een overzetting meestal niet de bedoeling. Een datum in de toekomst met status publish wordt "gepland".', 'mcp-abilities-kadence' ) ),
							'excerpt'        => array( 'type' => 'string' ),
							'content'        => array( 'type' => 'string', 'description' => __( 'Optioneel: blokmarkup, bij voorkeur uit generate-section of prepare-import.', 'mcp-abilities-kadence' ) ),
							'menu_order'     => array( 'type' => 'integer' ),
							'featured_image' => array( 'type' => 'integer', 'minimum' => 1, 'description' => __( 'Het ID van een bijlage op DEZE site.', 'mcp-abilities-kadence' ) ),
							'terms'          => array(
								'type'                 => 'object',
								'additionalProperties' => array( 'type' => 'array' ),
								'description'          => __( 'Per taxonomie een lijst termen, op slug of ID: {"service_type":["transport-mode"]}. Een lege lijst betekent expliciet geen termen: {"category": []} voorkomt dat WordPress een bericht in de standaardcategorie (Uncategorized) zet. Wat WordPress er toch zelf bij zet, staat na het aanmaken in added_by_wordpress en vooraf in plan.wordpress_adds.', 'mcp-abilities-kadence' ),
							),
							'acf'            => array(
								'type'                 => 'object',
								'additionalProperties' => true,
								'description'          => __( 'ACF-velden op veldnaam: {"intro":"…","key_points":[{"text":"…"}]}. Een repeater is een lijst rijen met de namen van de subvelden; een relatieveld een lijst post-ID\'s op deze site.', 'mcp-abilities-kadence' ),
							),
							'token'          => array( 'type' => 'string' ),
						),
						'required'             => array( 'post_type', 'title' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_type' => array( 'type' => 'string' ),
							'title'     => array( 'type' => 'string' ),
							'status'    => array( 'type' => 'string' ),
							'plan'      => array( 'type' => 'object' ),
							'new_id'    => array( 'type' => 'integer' ),
							'url'       => array( 'type' => 'string' ),
							'created'   => array( 'type' => 'boolean' ),
							'mismatch'  => array( 'type' => 'array' ),
							'token'     => array( 'type' => 'string' ),
							'note'      => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'create_post' ),
				),
			),
			array(
				'name' => 'kadence/update-post',
				'args' => array(
					'label'       => __( 'Een bestaand bericht of een post van een eigen posttype bijwerken', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Werkt titel, slug, samenvatting, volgorde, uitgelichte afbeelding, termen en ACF-velden van een bestaande post bij; alles wordt tegen het posttype getoetst.', 'mcp-abilities-kadence' ),
					'description' => __( 'De tegenhanger van create-post voor een post die al bestaat: dezelfde posttypes, dezelfde toetsen. Alleen wat je meegeeft verandert; de rest blijft staan. terms vervangt per genoemde taxonomie de termen (andere taxonomieën blijven ongemoeid). acf zet per veld de nieuwe waarde op veldsleutel; een repeater wordt in zijn geheel vervangen. De inhoud (post_content) en de status gaan hier niet: gebruik daarvoor de blokabilities en set-page-status. Let op: ACF-velden, termen en de uitgelichte afbeelding zijn post meta of relaties en kennen geen revisies; het voorstel geeft de oude waarden in before, bewaar die. Twee stappen: eerst zonder token voor een voorstel met before en after, daarna met token om te schrijven. Het token vervalt als de post intussen wijzigt.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id'        => array( 'type' => 'integer', 'minimum' => 1 ),
							'title'          => array( 'type' => 'string' ),
							'slug'           => array( 'type' => 'string' ),
							'excerpt'        => array( 'type' => 'string' ),
							'menu_order'     => array( 'type' => 'integer' ),
							'featured_image' => array( 'type' => 'integer', 'minimum' => 1, 'description' => __( 'Het ID van een bijlage op DEZE site.', 'mcp-abilities-kadence' ) ),
							'terms'          => array(
								'type'                 => 'object',
								'additionalProperties' => array( 'type' => 'array' ),
								'description'          => __( 'Per taxonomie de nieuwe lijst termen, op slug of ID. Vervangt wat er in die taxonomie staat.', 'mcp-abilities-kadence' ),
							),
							'acf'            => array(
								'type'                 => 'object',
								'additionalProperties' => true,
								'description'          => __( 'ACF-velden op veldnaam, zoals bij create-post. Een repeater vervangt alle rijen.', 'mcp-abilities-kadence' ),
							),
							'token'          => array( 'type' => 'string' ),
						),
						'required'             => array( 'post_id' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post'     => array( 'type' => 'object' ),
							'before'   => array( 'type' => 'object' ),
							'after'    => array( 'type' => 'object' ),
							'changed'  => array( 'type' => 'array' ),
							'written'  => array( 'type' => 'boolean' ),
							'mismatch' => array( 'type' => 'array' ),
							'token'    => array( 'type' => 'string' ),
							'note'     => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'update_post' ),
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
			'notes'      => isset( $gebouwd['notities'] ) ? $gebouwd['notities'] : array(),
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
	 * De ouder van een blok (op uniqueID) in de boom, of null op het hoogste niveau.
	 *
	 * @param array       $blokken De boom.
	 * @param string      $id      Het uniqueID.
	 * @param array|null  $ouder   De ouder tot hier.
	 * @param bool        $gevonden Wordt true als het blok er is.
	 *
	 * @return array|null
	 */
	private static function ouder_van( $blokken, $id, $ouder, &$gevonden ) {
		foreach ( $blokken as $blok ) {
			if ( isset( $blok['attrs']['uniqueID'] ) && (string) $blok['attrs']['uniqueID'] === (string) $id ) {
				$gevonden = true;
				return $ouder;
			}
			if ( ! empty( $blok['innerBlocks'] ) ) {
				$hier = self::ouder_van( $blok['innerBlocks'], $id, $blok, $gevonden );
				if ( $gevonden ) {
					return $hier;
				}
			}
		}
		return null;
	}

	/**
	 * Zet blokken als eerste of laatste kind in een container, met plaatshouders.
	 *
	 * @param array  $blokken De boom.
	 * @param array  $nieuw   De blokken die erin komen.
	 * @param string $doel_id De container.
	 * @param bool   $vooraan true voor inside_start, false voor inside_end.
	 * @param bool   $gemikt  Wordt true als de container gevonden is.
	 *
	 * @return array
	 */
	private static function plaats_binnen( $blokken, $nieuw, $doel_id, $vooraan, &$gemikt ) {
		foreach ( $blokken as $i => $blok ) {
			if ( isset( $blok['attrs']['uniqueID'] ) && (string) $blok['attrs']['uniqueID'] === (string) $doel_id ) {
				$gemikt   = true;
				$kinderen = isset( $blok['innerBlocks'] ) ? (array) $blok['innerBlocks'] : array();
				$inhoud   = isset( $blok['innerContent'] ) ? (array) $blok['innerContent'] : array();
				$nullen   = array_fill( 0, count( $nieuw ), null );

				// Waar de plaatshouders komen: naast de eerste of laatste bestaande
				// plaatshouder, of, zonder kinderen, tussen de openings- en de
				// sluitstring van de container.
				$posities = array_keys( array_filter( $inhoud, 'is_null' ) );
				if ( ! empty( $posities ) ) {
					$waar = $vooraan ? $posities[0] : end( $posities ) + 1;
				} else {
					$waar = count( $inhoud ) >= 2 ? 1 : count( $inhoud );
				}
				array_splice( $inhoud, $waar, 0, $nullen );

				$blok['innerBlocks']  = $vooraan ? array_merge( $nieuw, $kinderen ) : array_merge( $kinderen, $nieuw );
				$blok['innerContent'] = $inhoud;
				$blokken[ $i ]        = $blok;
				return $blokken;
			}
			if ( ! empty( $blok['innerBlocks'] ) ) {
				$raak = false;
				$blok['innerBlocks'] = self::plaats_binnen( $blok['innerBlocks'], $nieuw, $doel_id, $vooraan, $raak );
				if ( $raak ) {
					$gemikt        = true;
					$blokken[ $i ] = $blok;
					return $blokken;
				}
			}
		}
		return $blokken;
	}

	/**
	 * Verplaats blokken binnen een post, met behoud van uniqueID.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function move_blocks( $input = array() ) {
		$post = self::post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$ids     = isset( $input['unique_ids'] ) && is_array( $input['unique_ids'] ) ? array_values( array_unique( array_filter( array_map( 'trim', array_map( 'strval', $input['unique_ids'] ) ) ) ) ) : array();
		$doel    = isset( $input['target'] ) ? trim( (string) $input['target'] ) : '';
		$positie = isset( $input['position'] ) ? (string) $input['position'] : '';
		$token   = isset( $input['token'] ) ? (string) $input['token'] : '';

		if ( empty( $ids ) || '' === $doel || ! in_array( $positie, array( 'before', 'after', 'inside_start', 'inside_end' ), true ) ) {
			return new WP_Error( 'kadence_mcp_move_input', __( 'Geef unique_ids (minstens één), target en position (before, after, inside_start of inside_end).', 'mcp-abilities-kadence' ) );
		}

		$boom     = parse_blocks( $post->post_content );
		$aanwezig = Kadence_MCP_Inventory::verzamel_unique_ids( $boom );
		$onbekend = array_values( array_diff( array_merge( $ids, array( $doel ) ), array_keys( $aanwezig ) ) );

		if ( ! empty( $onbekend ) ) {
			return new WP_Error(
				'kadence_mcp_move_unknown_ids',
				sprintf(
					/* translators: 1: IDs, 2: post ID. */
					__( 'Deze blokken staan niet in post %2$d: %1$s. Er is niets verplaatst.', 'mcp-abilities-kadence' ),
					implode( ', ', $onbekend ),
					$post->ID
				)
			);
		}

		if ( in_array( $doel, $ids, true ) ) {
			return new WP_Error( 'kadence_mcp_move_target_moving', __( 'Het doel is zelf een van de blokken die verhuizen.', 'mcp-abilities-kadence' ) );
		}

		// De blokken zelf, in de opgegeven volgorde, en of het doel in een van
		// hen ligt (dan zou het meeverhuizen en valt er niets te plaatsen).
		$verhuizers = array();
		$bezwaren   = array();
		$van        = array();

		foreach ( $ids as $id ) {
			$blok = Kadence_MCP_Inventory::zoek_op_unique_id( $boom, $id );
			$onder = Kadence_MCP_Inventory::verzamel_unique_ids( array( $blok ) );
			if ( isset( $onder[ $doel ] ) ) {
				$bezwaren[] = sprintf( __( 'Het doel %1$s ligt in %2$s, dat zelf verhuist.', 'mcp-abilities-kadence' ), $doel, $id );
			}
			foreach ( $ids as $ander ) {
				if ( $ander !== $id && isset( $onder[ $ander ] ) ) {
					$bezwaren[] = sprintf( __( '%1$s ligt in %2$s; geef alleen het buitenste blok op.', 'mcp-abilities-kadence' ), $ander, $id );
				}
			}
			$verhuizers[] = $blok;

			$gevonden = false;
			$ouder    = self::ouder_van( $boom, $id, null, $gevonden );
			$van[]    = array(
				'unique_id' => $id,
				'block'     => (string) $blok['blockName'],
				'parent'    => $ouder ? ( isset( $ouder['attrs']['uniqueID'] ) ? $ouder['attrs']['uniqueID'] : '' ) . ' ' . $ouder['blockName'] : __( '(hoogste niveau)', 'mcp-abilities-kadence' ),
				'parent_block' => $ouder ? (string) $ouder['blockName'] : '',
				'parent_id'    => $ouder && isset( $ouder['attrs']['uniqueID'] ) ? (string) $ouder['attrs']['uniqueID'] : '',
			);
		}

		// De nieuwe ouder.
		$doelblok = Kadence_MCP_Inventory::zoek_op_unique_id( $boom, $doel );
		if ( 0 === strpos( $positie, 'inside' ) ) {
			$nieuwe_ouder = $doelblok;
		} else {
			$gevonden     = false;
			$nieuwe_ouder = self::ouder_van( $boom, $doel, null, $gevonden );
		}
		$nieuwe_ouder_naam = $nieuwe_ouder ? (string) $nieuwe_ouder['blockName'] : '';
		$nieuwe_ouder_id   = $nieuwe_ouder && isset( $nieuwe_ouder['attrs']['uniqueID'] ) ? (string) $nieuwe_ouder['attrs']['uniqueID'] : '';

		foreach ( $verhuizers as $k => $blok ) {
			$naam = (string) $blok['blockName'];

			// Een Sectie in een Row Layout is een kolom; het kolomaantal staat
			// in het attribuut columns van de rij en de layoutklasse hangt eraan.
			// Binnen dezelfde rij van plek wisselen kan; naar een andere rij niet.
			$zelfde_rij = 'kadence/rowlayout' === $nieuwe_ouder_naam
				&& $van[ $k ]['parent_block'] === $nieuwe_ouder_naam
				&& '' !== $nieuwe_ouder_id
				&& $van[ $k ]['parent_id'] === $nieuwe_ouder_id;
			if ( 'kadence/column' === $naam && ( 'kadence/rowlayout' === $van[ $k ]['parent_block'] || 'kadence/rowlayout' === $nieuwe_ouder_naam ) && ! $zelfde_rij ) {
				$bezwaren[] = sprintf( __( '%s is een kolom van een Row Layout (of wordt dat): dan klopt het kolomaantal (columns) van de rij niet meer. Verplaats de inhoud van de kolom, niet de kolom zelf.', 'mcp-abilities-kadence' ), $ids[ $k ] );
			}

			// Een slide of tab telt mee in een attribuut van zijn ouder (slideCount
			// van de slider, de titels van de tabs). Binnen dezelfde ouder van plek
			// wisselen kan; naar een andere ouder niet.
			if ( in_array( $naam, array( 'kadence/slide', 'kadence/tab' ), true ) ) {
				$zelfde = $van[ $k ]['parent_block'] === $nieuwe_ouder_naam
					&& '' !== $nieuwe_ouder_id
					&& $van[ $k ]['parent_id'] === $nieuwe_ouder_id;
				if ( ! $zelfde ) {
					$bezwaren[] = sprintf( __( '%1$s (%2$s) kan alleen binnen zijn eigen slider of tabs van plek wisselen: de ouder telt ze (slideCount, de tabtitels), en die zou niet meer kloppen.', 'mcp-abilities-kadence' ), $ids[ $k ], $naam );
				}
			}

			// Waar een blok direct in mag. Uit de registratie, en voor blokken die
			// dat daar niet zeggen maar in de editor-JS wel vastleggen, een eigen
			// lijst (afgelezen op 28-09-2026: kadence/slide declareert geen parent).
			$vast      = array(
				'kadence/slide'            => array( 'kadence/slider' ),
				'kadence/pane'             => array( 'kadence/accordion' ),
				'kadence/repeatertemplate' => array( 'kadence/repeater' ),
			);
			$definitie = Kadence_MCP_Inventory::get_block( $naam );
			$ouders    = ( ! is_wp_error( $definitie ) && ! empty( $definitie['parent'] ) ) ? (array) $definitie['parent'] : ( isset( $vast[ $naam ] ) ? $vast[ $naam ] : array() );
			if ( ! empty( $ouders ) && ! in_array( $nieuwe_ouder_naam, $ouders, true ) ) {
				$definitie = array( 'parent' => $ouders );
				$bezwaren[] = sprintf(
					/* translators: 1: block, 2: allowed parents, 3: new parent. */
					__( '%1$s mag alleen direct in %2$s staan, niet in %3$s.', 'mcp-abilities-kadence' ),
					$naam,
					implode( ', ', (array) $definitie['parent'] ),
					'' !== $nieuwe_ouder_naam ? $nieuwe_ouder_naam : __( 'het hoogste niveau', 'mcp-abilities-kadence' )
				);
			}
		}

		if ( ! empty( $bezwaren ) ) {
			return new WP_Error( 'kadence_mcp_move_invalid', implode( ' ', array_unique( $bezwaren ) ) );
		}

		// Uit de boom, dan op de nieuwe plek.
		$geteld = 0;
		$zonder = Kadence_MCP_Inventory::verwijder_blokken( $boom, array_fill_keys( $ids, true ), $geteld );
		$gemikt = false;

		if ( 0 === strpos( $positie, 'inside' ) ) {
			$nieuw_boom = self::plaats_binnen( $zonder, $verhuizers, $doel, 'inside_start' === $positie, $gemikt );
		} else {
			$nieuw_boom = self::plaats_naast( $zonder, $verhuizers, $positie, $doel, $gemikt );
		}

		$content = Kadence_MCP_Inventory::serialiseer( $nieuw_boom );
		$na_ids  = Kadence_MCP_Inventory::verzamel_unique_ids( parse_blocks( $content ) );
		$kwijt   = array_values( array_diff( array_keys( $aanwezig ), array_keys( $na_ids ) ) );

		if ( ! $gemikt || ! empty( $kwijt ) ) {
			return new WP_Error(
				'kadence_mcp_move_lost_blocks',
				! $gemikt
					? __( 'Het doel is na het weghalen niet meer gevonden. Er is niets verplaatst.', 'mcp-abilities-kadence' )
					: sprintf( __( 'Na het verplaatsen zouden deze blokken ontbreken: %s. Er is niets verplaatst.', 'mcp-abilities-kadence' ), implode( ', ', $kwijt ) )
			);
		}

		$basis = array(
			'post'   => array( 'id' => $post->ID, 'title' => get_the_title( $post ) ),
			'moving' => $ids,
			'from'   => array_map( static function ( $v ) { unset( $v['parent_block'], $v['parent_id'] ); return $v; }, $van ),
			'to'     => array(
				'target'   => $doel,
				'position' => $positie,
				'parent'   => '' !== $nieuwe_ouder_naam ? ( isset( $nieuwe_ouder['attrs']['uniqueID'] ) ? $nieuwe_ouder['attrs']['uniqueID'] : '' ) . ' ' . $nieuwe_ouder_naam : __( '(hoogste niveau)', 'mcp-abilities-kadence' ),
			),
		);

		$grondslag = Kadence_MCP_Inventory::schrijf_token( $post, '__move__', array( 'ids' => $ids, 'target' => $doel, 'position' => $positie ) );

		if ( '' === $token ) {
			return array_merge(
				$basis,
				array(
					'moved'  => false,
					'token'  => $grondslag,
					'status' => sprintf(
						/* translators: %d: number of blocks. */
						__( 'Voorstel, er is NIETS verplaatst. %d blokken zouden verhuizen (met alles eronder), met behoud van uniqueID; geen enkel blok verdwijnt. Roep opnieuw aan met het token om het te doen.', 'mcp-abilities-kadence' ),
						count( $ids )
					),
				)
			);
		}

		if ( ! Kadence_MCP_Capabilities::current_user_can( Kadence_MCP_Capabilities::WRITE ) ) {
			return new WP_Error( 'kadence_mcp_write_denied', __( 'Je hebt de capability kadence_mcp_write niet.', 'mcp-abilities-kadence' ), array( 'status' => 403 ) );
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'kadence_mcp_edit_denied', __( 'Je mag deze post volgens WordPress zelf niet bewerken.', 'mcp-abilities-kadence' ), array( 'status' => 403 ) );
		}

		if ( ! hash_equals( $grondslag, $token ) ) {
			return new WP_Error( 'kadence_mcp_invalid_token', Kadence_MCP_Inventory::token_reden( $token, $grondslag, $post ) );
		}

		$resultaat = wp_update_post( array( 'ID' => $post->ID, 'post_content' => wp_slash( $content ) ), true );

		if ( is_wp_error( $resultaat ) ) {
			return $resultaat;
		}

		clean_post_cache( $post->ID );

		// Teruglezen: staat elk verplaatst blok onder de verwachte ouder, en is
		// er niets verdwenen?
		$terug      = parse_blocks( get_post( $post->ID )->post_content );
		$terug_ids  = Kadence_MCP_Inventory::verzamel_unique_ids( $terug );
		$verkeerd   = array();

		foreach ( $ids as $id ) {
			$gevonden = false;
			$ouder    = self::ouder_van( $terug, $id, null, $gevonden );
			$naam     = $ouder ? (string) $ouder['blockName'] : '';
			if ( ! $gevonden || $naam !== $nieuwe_ouder_naam ) {
				$verkeerd[] = $id;
			}
		}

		$weg = array_values( array_diff( array_keys( $aanwezig ), array_keys( $terug_ids ) ) );

		return array_merge(
			$basis,
			array(
				'moved'  => empty( $verkeerd ) && empty( $weg ),
				'token'  => '',
				'status' => ( empty( $verkeerd ) && empty( $weg ) )
					? __( 'Verplaatst en teruggelezen: de blokken staan op de nieuwe plek, alle uniqueIDs zijn er nog. Er is een revisie gemaakt.', 'mcp-abilities-kadence' )
					: sprintf( __( 'LET OP: geschreven, maar bij het teruglezen klopt dit niet: %s. Draai terug via de revisie.', 'mcp-abilities-kadence' ), implode( ', ', array_merge( $verkeerd, $weg ) ) ),
			)
		);
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

		$boom = parse_blocks( $post->post_content );

		// Twee stille fouten die niets met klassen te maken hebben, maar die je
		// alleen ziet als je de hele boom afloopt.
		$paletnamen   = Kadence_MCP_Inventory::zoek_paletnamen_buiten_kadence( $boom );
		$zonder_id    = self::zonder_unique_id( $boom, $post->ID );
		$extra_status = '';

		if ( ! empty( $paletnamen ) ) {
			$extra_status .= ' ' . sprintf(
				/* translators: %d: number of blocks. */
				__( '%d niet-Kadence-blokken hebben een Kadence-paletnaam in een kleurattribuut (palette_names); core kent die niet, dus de kleur wordt stil genegeerd. Te herstellen met set-attributes op het pad.', 'mcp-abilities-kadence' ),
				count( $paletnamen )
			);
		}

		if ( ! empty( $zonder_id ) ) {
			$extra_status .= ' ' . sprintf(
				/* translators: %d: number of blocks. */
				__( '%d Kadence-blokken hebben geen uniqueID (missing_unique_id): Kadence schrijft dan geen eigen CSS en geen wrapperklasse voor dat blok. Elke vondst heeft een voorstel in Kadence-vorm, uniek in deze post; te zetten met set-attributes op het pad.', 'mcp-abilities-kadence' ),
				count( $zonder_id )
			);
		}

		return array(
			'post'      => array( 'id' => $post->ID, 'title' => get_the_title( $post ), 'type' => $post->post_type ),
			'checked'   => $gekeurd,
			'findings'  => $bevindingen,
			'repair'    => $herbouwbaar,
			'palette_names'     => $paletnamen,
			'missing_unique_id' => $zonder_id,
			'status'    => ( empty( $bevindingen )
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
				) ) . $extra_status,
		);
	}

	/**
	 * Kadence-blokken zonder uniqueID, met een voorstel.
	 *
	 * Kadence deelt een uniqueID uit in de editor. Een blok dat buiten de
	 * editor ontstaat (een mega menu dat via code is opgebouwd, een dicht
	 * submenu) mist hem, en krijgt dan geen eigen CSS en geen wrapperklasse —
	 * zonder foutmelding. Alleen blokken waarvan het schema een uniqueID kent.
	 *
	 * @param array $boom    De boom.
	 * @param int   $post_id De post; Kadence zet dat ID vóór de hash.
	 *
	 * @return array[]
	 */
	private static function zonder_unique_id( $boom, $post_id ) {
		$bestaand = Kadence_MCP_Inventory::verzamel_unique_ids( $boom );
		$treffers = array();
		$loop     = static function ( $blokken, $pad ) use ( &$loop, &$treffers, &$bestaand, $post_id ) {
			foreach ( $blokken as $i => $blok ) {
				$hier = array_merge( $pad, array( (int) $i ) );
				$naam = isset( $blok['blockName'] ) ? (string) $blok['blockName'] : '';

				if ( 0 === strpos( $naam, Kadence_MCP_Inventory::BLOCK_PREFIX )
					&& null !== Kadence_MCP_Inventory::attribuut_definitie( $naam, 'uniqueID' )
					&& ( ! isset( $blok['attrs']['uniqueID'] ) || '' === trim( (string) $blok['attrs']['uniqueID'] ) ) ) {
					// Kadence-vorm: {postID}_{6 hex}-{2 hex}, uniek in deze post.
					do {
						$hash    = substr( md5( $post_id . '|' . implode( '.', $hier ) . '|' . wp_rand() ), 0, 8 );
						$voorstel = (int) $post_id . '_' . substr( $hash, 0, 6 ) . '-' . substr( $hash, 6, 2 );
					} while ( isset( $bestaand[ $voorstel ] ) );

					$bestaand[ $voorstel ] = array( $naam );
					$treffers[]            = array(
						'block'     => $naam,
						'path'      => Kadence_MCP_Inventory::PAD_PREFIX . implode( '.', $hier ),
						'suggested' => $voorstel,
					);
				}

				if ( ! empty( $blok['innerBlocks'] ) ) {
					$loop( $blok['innerBlocks'], $hier );
				}
			}
		};
		$loop( $boom, array() );

		return $treffers;
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

		$al_gebruikt = Kadence_MCP_Inventory::token_al_gebruikt( $token );

		if ( is_wp_error( $al_gebruikt ) ) {
			return $al_gebruikt;
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

		Kadence_MCP_Inventory::onthoud_token( $token, $nieuw_id );

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
	 * Maak een Kadence-object aan.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function create_entity( $input = array() ) {
		$type  = isset( $input['post_type'] ) ? (string) $input['post_type'] : '';
		$titel = isset( $input['title'] ) ? trim( wp_strip_all_tags( (string) $input['title'] ) ) : '';
		$slug  = isset( $input['slug'] ) ? sanitize_title( (string) $input['slug'] ) : '';
		$meta  = isset( $input['meta'] ) && is_array( $input['meta'] ) ? $input['meta'] : array();
		$svg   = isset( $input['svg'] ) ? (string) $input['svg'] : '';

		if ( ! in_array( $type, array( 'kadence_navigation', 'kadence_header', 'kadence_element', 'kadence_vector' ), true ) ) {
			return new WP_Error( 'kadence_mcp_bad_entity_type', __( 'post_type moet kadence_navigation, kadence_header, kadence_element of kadence_vector zijn.', 'mcp-abilities-kadence' ) );
		}

		if ( ! post_type_exists( $type ) ) {
			return new WP_Error(
				'kadence_mcp_entity_type_missing',
				sprintf(
					/* translators: %s: post type. */
					__( 'Het posttype %s bestaat op deze site niet. Elementen komen uit Kadence Pro, de rest uit Kadence Blocks.', 'mcp-abilities-kadence' ),
					$type
				)
			);
		}

		if ( '' === $titel ) {
			return new WP_Error( 'kadence_mcp_no_title', __( 'Geef het object een titel.', 'mcp-abilities-kadence' ) );
		}

		$status = ( isset( $input['status'] ) && 'publish' === $input['status'] ) ? 'publish' : 'draft';

		// De instellingen die Kadence voor dit posttype registreert, met hun
		// standaard. Een Element registreert een deel zonder standaard; die
		// krijgen de lege waarde van hun type, zodat ze er wel staan.
		$standaard = array();

		foreach ( get_registered_meta_keys( 'post', $type ) as $sleutel => $args ) {
			if ( 0 !== strpos( (string) $sleutel, '_kad_' ) ) {
				continue;
			}

			if ( array_key_exists( 'default', $args ) ) {
				$standaard[ $sleutel ] = $args['default'];
				continue;
			}

			$soort = isset( $args['type'] ) ? (string) $args['type'] : 'string';
			$leeg  = array(
				'string'  => '',
				'integer' => 0,
				'number'  => 0,
				'boolean' => false,
				'array'   => array(),
				'object'  => array(),
			);
			$standaard[ $sleutel ] = isset( $leeg[ $soort ] ) ? $leeg[ $soort ] : '';
		}

		$onbekend = array_diff( array_keys( $meta ), array_keys( $standaard ) );

		if ( ! empty( $onbekend ) ) {
			return new WP_Error(
				'kadence_mcp_bad_meta_key',
				sprintf(
					/* translators: 1: keys, 2: post type. */
					__( 'Deze sleutels registreert Kadence niet voor %2$s: %1$s. Ze zouden opgeslagen worden en daarna genegeerd. Controleer de schrijfwijze met get-post-meta op een bestaand object.', 'mcp-abilities-kadence' ),
					implode( ', ', $onbekend ),
					$type
				)
			);
		}

		if ( 'kadence_vector' === $type ) {
			if ( '' === trim( $svg ) || false === stripos( $svg, '<svg' ) ) {
				return new WP_Error( 'kadence_mcp_no_svg', __( 'Een vector heeft svg nodig: de SVG-code.', 'mcp-abilities-kadence' ) );
			}

			if ( ! empty( $meta ) ) {
				return new WP_Error( 'kadence_mcp_vector_meta', __( 'Een vector heeft geen instellingen; laat meta weg.', 'mcp-abilities-kadence' ) );
			}

			$status = 'publish';

			// Vooraf uitrekenen wat er van de SVG overblijft. Tot 1.26.0 ging dat
			// stil mis: op staging bleef van 2679 tekens er 275 over, omdat het
			// account geen unfiltered_html had en kses de paden weghaalde.
			$voorspelling = self::voorspel_svg_opslag( $svg );

			if ( $voorspelling['blocking'] && empty( $input['accept_loss'] ) ) {
				return new WP_Error(
					'kadence_mcp_svg_loss',
					$voorspelling['note'],
					array( 'prediction' => $voorspelling )
				);
			}
		}

		$token     = isset( $input['token'] ) ? (string) $input['token'] : '';
		$grondslag = 'kmcp1_' . substr(
			wp_hash( (string) wp_json_encode( array( 'type' => $type, 'titel' => $titel, 'slug' => $slug, 'status' => $status, 'meta' => $meta, 'svg' => md5( $svg ) ) ) ),
			0,
			32
		);

		$rapport = array(
			'post_type' => $type,
			'title'     => $titel,
			'status'    => $status,
			'meta'      => (object) $meta,
		);

		if ( '' === $token ) {
			return array_merge(
				$rapport,
				'kadence_vector' === $type ? array( 'svg_prediction' => $voorspelling ) : array(),
				array(
					'new_id'  => 0,
					'created' => false,
					'token'   => $grondslag,
					'next'    => '',
					'note'    => 'kadence_vector' === $type
						? sprintf(
							/* translators: %s: title. */
							__( 'Voorstel, er is NIETS aangemaakt. Er zou een vector "%s" komen, via de route en de sanitizer van Kadence. Roep opnieuw aan met het token om hem te maken.', 'mcp-abilities-kadence' ),
							$titel
						)
						: sprintf(
							/* translators: 1: post type, 2: title, 3: number of settings, 4: number of overrides, 5: status. */
							__( 'Voorstel, er is NIETS aangemaakt. Er zou een lege %1$s "%2$s" komen (%5$s), met alle %3$d instellingen van Kadence op hun standaard en %4$d daarvan anders. Roep opnieuw aan met het token om hem te maken.', 'mcp-abilities-kadence' ),
							$type,
							$titel,
							count( $standaard ),
							count( $meta ),
							$status
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

		// De capability van het posttype zelf vragen, niet raden: Kadence geeft
		// sommige van deze types eigen capabilities.
		$type_object = get_post_type_object( $type );
		$mag_maken   = ( $type_object && isset( $type_object->cap->create_posts ) ) ? (string) $type_object->cap->create_posts : 'edit_posts';

		if ( ! current_user_can( $mag_maken ) ) {
			return new WP_Error(
				'kadence_mcp_create_denied',
				sprintf(
					/* translators: 1: capability, 2: post type. */
					__( 'Je mist de capability "%1$s", die nodig is om een %2$s aan te maken.', 'mcp-abilities-kadence' ),
					$mag_maken,
					$type
				),
				array( 'status' => 403 )
			);
		}

		if ( 'publish' === $status && 'kadence_vector' !== $type && $type_object && isset( $type_object->cap->publish_posts ) && ! current_user_can( $type_object->cap->publish_posts ) ) {
			return new WP_Error( 'kadence_mcp_publish_denied', __( 'Je mag dit posttype niet publiceren. Laat status weg voor een concept.', 'mcp-abilities-kadence' ) );
		}

		if ( ! hash_equals( $grondslag, $token ) ) {
			return new WP_Error( 'kadence_mcp_invalid_token', __( 'Het token hoort niet bij deze invoer. Roep opnieuw zonder token aan en gebruik het token dat je dan terugkrijgt.', 'mcp-abilities-kadence' ) );
		}

		$al_gebruikt = Kadence_MCP_Inventory::token_al_gebruikt( $token );

		if ( is_wp_error( $al_gebruikt ) ) {
			return $al_gebruikt;
		}

		if ( 'kadence_vector' === $type ) {
			// Via de route van Kadence zelf: die saneert de SVG en maakt de post
			// zoals de editor dat doet. Een eigen wp_insert_post zou de
			// sanitizer overslaan.
			$verzoek = new WP_REST_Request( 'POST', '/kb-vector/v1/vectors' );
			$verzoek->set_header( 'content-type', 'application/json' );
			$verzoek->set_body( wp_json_encode( array( 'vectorSVG' => $svg, 'title' => $titel ) ) );

			$antwoord = rest_do_request( $verzoek );
			$data     = $antwoord->get_data();

			if ( $antwoord->is_error() || ! is_array( $data ) || empty( $data['value'] ) ) {
				return new WP_Error(
					'kadence_mcp_vector_failed',
					sprintf(
						/* translators: %s: response. */
						__( 'Kadence heeft de vector niet aangemaakt: %s', 'mcp-abilities-kadence' ),
						wp_json_encode( $data )
					)
				);
			}

			$nieuw_id = (int) $data['value'];

		Kadence_MCP_Inventory::onthoud_token( $token, $nieuw_id );

			if ( '' !== $slug ) {
				wp_update_post( array( 'ID' => $nieuw_id, 'post_name' => $slug ) );
			}

			$controle  = get_post( $nieuw_id );
			$opgeslagen = $controle ? (string) $controle->post_content : '';
			$verlies    = self::svg_verlies( $svg, $opgeslagen );

			return array_merge(
				$rapport,
				array(
					'new_id'  => $nieuw_id,
					'created' => '' !== trim( $opgeslagen ),
					'token'   => '',
					'svg'     => $verlies,
					'next'    => sprintf(
						/* translators: %d: post ID. */
						__( 'Plaats hem met een blok kadence/vector met id %d (generate-section kan dat blok bouwen).', 'mcp-abilities-kadence' ),
						$nieuw_id
					),
					'note'    => $verlies['lost']
						? sprintf(
							/* translators: 1: input length, 2: stored length, 3: lost elements. */
							__( 'LET OP: aangemaakt, maar er is SVG verloren gegaan: %1$d tekens in, %2$d opgeslagen, weggevallen elementen: %3$s. Kijk met check-access of unfiltered_html ontbreekt; zo niet, dan heeft de sanitizer van Kadence ingegrepen.', 'mcp-abilities-kadence' ),
							$verlies['input_length'],
							$verlies['stored_length'],
							implode( ', ', $verlies['lost_elements'] )
						)
						: __( 'Aangemaakt via de route van Kadence en teruggelezen: de SVG is heel opgeslagen (zelfde elementen als de invoer).', 'mcp-abilities-kadence' ),
				)
			);
		}

		$nieuw_id = wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => $status,
				'post_title'   => $titel,
				'post_name'    => $slug,
				'post_content' => '',
			),
			true
		);

		if ( is_wp_error( $nieuw_id ) ) {
			return $nieuw_id;
		}

		Kadence_MCP_Inventory::onthoud_token( $token, $nieuw_id );

		foreach ( array_merge( $standaard, $meta ) as $sleutel => $waarde ) {
			Kadence_MCP_Inventory::schrijf_meta( $nieuw_id, $sleutel, $waarde );
		}

		clean_post_cache( $nieuw_id );

		// Teruglezen: staat de hele set, en de afwijkingen zoals bedoeld?
		$mist = array();

		foreach ( array_merge( $standaard, $meta ) as $sleutel => $waarde ) {
			if ( ! metadata_exists( 'post', $nieuw_id, $sleutel ) ) {
				$mist[] = $sleutel;
			} elseif ( isset( $meta[ $sleutel ] ) && wp_json_encode( get_post_meta( $nieuw_id, $sleutel, true ) ) !== wp_json_encode( $meta[ $sleutel ] ) ) {
				$mist[] = $sleutel;
			}
		}

		$volgende = array(
			'kadence_navigation' => __( 'Zet de menu-items erin met insert-blocks: één kadence/navigation-blok met de kadence/navigation-link-blokken erin (prepare-import met de markup van de bron, of generate-section). Plaats de navigatie daarna met een blok kadence/navigation met dit id.', 'mcp-abilities-kadence' ),
			'kadence_header'     => __( 'Zet de header erin met insert-blocks: één kadence/header-blok met zijn containers (prepare-import met de markup van de bron). Toewijzen gebeurt in de Customizer: Header, Header block.', 'mcp-abilities-kadence' ),
			'kadence_element'    => __( 'Zet de inhoud erin met insert-blocks. De plaatsing (hook, weergaveregels) staat in de meta; wijzigen met set-entity-meta. Publiceer pas als de inhoud er staat.', 'mcp-abilities-kadence' ),
		);

		return array_merge(
			$rapport,
			array(
				'new_id'  => (int) $nieuw_id,
				'created' => empty( $mist ),
				'token'   => '',
				'next'    => $volgende[ $type ],
				'note'    => empty( $mist )
					? sprintf(
						/* translators: 1: post type, 2: ID, 3: number of settings. */
						__( 'Aangemaakt: %1$s %2$d, met %3$d instellingen. Teruggelezen: alles staat er.', 'mcp-abilities-kadence' ),
						$type,
						(int) $nieuw_id,
						count( $standaard )
					)
					: sprintf(
						/* translators: %s: keys. */
						__( 'LET OP: aangemaakt, maar bij het teruglezen ontbreken of wijken af: %s.', 'mcp-abilities-kadence' ),
						implode( ', ', $mist )
					),
			)
		);
	}

	/**
	 * Wat er van een SVG overblijft na de weg naar de database.
	 *
	 * Twee zeven: de SVG-sanitizer van Kadence (bij een nieuwe vector) en kses
	 * van WordPress, die bij opslaan draait als het account geen
	 * unfiltered_html heeft en elementen die hij niet kent (path, g, circle …)
	 * gewoon weghaalt. Allebei zonder foutmelding.
	 *
	 * @param string $svg        De invoer.
	 * @param bool   $sanitizer  Ook de sanitizer van Kadence toepassen.
	 *
	 * @return array
	 */
	private static function voorspel_svg_opslag( $svg, $sanitizer = true ) {
		$stappen = array();
		$uit     = (string) $svg;

		if ( $sanitizer && class_exists( '\\KadenceWP\\KadenceBlocks\\enshrined\\svgSanitize\\Sanitizer' ) && class_exists( 'KadenceBlocksAllowedAttributes' ) ) {
			$schoner = new \KadenceWP\KadenceBlocks\enshrined\svgSanitize\Sanitizer();
			$schoner->removeRemoteReferences( true );
			$schoner->setAllowedAttrs( new KadenceBlocksAllowedAttributes() );
			$geschoond = $schoner->sanitize( $uit );
			$uit       = false === $geschoond ? '' : (string) $geschoond;
			$stappen[] = 'sanitizer van Kadence';
		}

		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$uit       = wp_unslash( wp_filter_post_kses( wp_slash( $uit ) ) );
			$stappen[] = 'kses van WordPress (geen unfiltered_html)';
		}

		$verlies = self::svg_verlies( $svg, $uit );

		$verlies['steps']    = $stappen;
		$verlies['blocking'] = $verlies['lost'];
		$verlies['note']     = $verlies['lost']
			? sprintf(
				/* translators: 1: steps, 2: input length, 3: output length, 4: elements. */
				__( 'Deze SVG komt niet heel in de database (%1$s): %2$d tekens in, %3$d over, en deze elementen vallen weg: %4$s. Er is niets opgeslagen. Geef het account unfiltered_html (zie check-access), of vereenvoudig de SVG; wil je toch doorgaan, geef dan accept_loss: true.', 'mcp-abilities-kadence' ),
				empty( $stappen ) ? __( 'geen zeef', 'mcp-abilities-kadence' ) : implode( ' + ', $stappen ),
				$verlies['input_length'],
				$verlies['stored_length'],
				implode( ', ', $verlies['lost_elements'] )
			)
			: sprintf(
				/* translators: %s: steps. */
				__( 'De SVG komt heel in de database (%s).', 'mcp-abilities-kadence' ),
				empty( $stappen ) ? __( 'geen zeef van toepassing', 'mcp-abilities-kadence' ) : implode( ' + ', $stappen )
			);

		return $verlies;
	}

	/**
	 * Vergelijk twee SVG's op elementen.
	 *
	 * Op elementen en niet op lengte: de sanitizer herschrijft witruimte en
	 * aanhalingstekens, en dat is geen verlies. Een verdwenen path wel.
	 *
	 * @param string $voor De invoer.
	 * @param string $na   Wat er is opgeslagen of zou worden.
	 *
	 * @return array
	 */
	private static function svg_verlies( $voor, $na ) {
		$tel = static function ( $svg ) {
			preg_match_all( '/<([a-zA-Z][a-zA-Z0-9:-]*)\b/', (string) $svg, $m );

			return array_count_values( array_map( 'strtolower', $m[1] ) );
		};

		$a       = $tel( $voor );
		$b       = $tel( $na );
		$weg     = array();

		foreach ( $a as $element => $aantal ) {
			$over = isset( $b[ $element ] ) ? $b[ $element ] : 0;

			if ( $over < $aantal ) {
				$weg[] = $element . ' (' . $aantal . ' → ' . $over . ')';
			}
		}

		return array(
			'input_length'  => strlen( (string) $voor ),
			'stored_length' => strlen( (string) $na ),
			'lost_elements' => $weg,
			'lost'          => ! empty( $weg ) || ( '' !== trim( (string) $voor ) && '' === trim( (string) $na ) ),
		);
	}

	/**
	 * Vervang tekst in de inhoud van een bestaande vector of custom SVG.
	 *
	 * Voor wat de blokabilities niet raken: een kadence_vector bewaart een kale
	 * SVG in post_content, een kadence_custom_svg een JSON-beschrijving. Een
	 * kleur omzetten (#716D55 naar #5E5B4A) ging tot 1.26.0 via de REST API,
	 * zonder toets. Hier met een verwacht aantal per vervanging, de
	 * verliescontrole van create-entity, een token en teruglezen.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function update_entity_content( $input = array() ) {
		$post = get_post( isset( $input['post_id'] ) ? (int) $input['post_id'] : 0 );

		if ( ! $post || ! in_array( $post->post_type, array( 'kadence_vector', 'kadence_custom_svg' ), true ) ) {
			return new WP_Error( 'kadence_mcp_not_a_vector', __( 'Deze ability werkt alleen op een kadence_vector of kadence_custom_svg. Blokinhoud wijzig je met de blokabilities.', 'mcp-abilities-kadence' ) );
		}

		if ( ! current_user_can( 'read_post', $post->ID ) ) {
			return new WP_Error( 'kadence_mcp_cannot_read', __( 'Dit account mag deze post niet lezen (zie check-access).', 'mcp-abilities-kadence' ) );
		}

		$vervangingen = isset( $input['replace'] ) && is_array( $input['replace'] ) ? $input['replace'] : array();

		if ( empty( $vervangingen ) ) {
			return new WP_Error( 'kadence_mcp_no_replace', __( 'Geef replace op: een lijst van {from, to, count}.', 'mcp-abilities-kadence' ) );
		}

		$inhoud = (string) $post->post_content;
		$nieuw  = $inhoud;
		$plan   = array();
		$fouten = array();

		foreach ( $vervangingen as $v ) {
			$van   = isset( $v['from'] ) ? (string) $v['from'] : '';
			$naar  = isset( $v['to'] ) ? (string) $v['to'] : '';
			$hoofd = ! empty( $v['case_insensitive'] );

			if ( '' === $van ) {
				$fouten[] = __( 'Een vervanging zonder from.', 'mcp-abilities-kadence' );
				continue;
			}

			$aantal = $hoofd ? substr_count( strtolower( $nieuw ), strtolower( $van ) ) : substr_count( $nieuw, $van );

			if ( isset( $v['count'] ) && (int) $v['count'] !== $aantal ) {
				$fouten[] = sprintf(
					/* translators: 1: from, 2: found, 3: expected. */
					__( '"%1$s" staat er %2$d keer, verwacht %3$d. Niets gedaan; controleer de inhoud met get-raw-markup.', 'mcp-abilities-kadence' ),
					$van,
					$aantal,
					(int) $v['count']
				);
				continue;
			}

			$nieuw  = $hoofd ? str_ireplace( $van, $naar, $nieuw ) : str_replace( $van, $naar, $nieuw );
			$plan[] = array( 'from' => $van, 'to' => $naar, 'count' => $aantal );
		}

		if ( ! empty( $fouten ) ) {
			return new WP_Error( 'kadence_mcp_replace_mismatch', implode( ' ', $fouten ) );
		}

		if ( $nieuw === $inhoud ) {
			return new WP_Error( 'kadence_mcp_nothing_to_replace', __( 'Er verandert niets: de from-waarden staan niet in de inhoud, of from en to zijn gelijk.', 'mcp-abilities-kadence' ) );
		}

		// De JSON van een custom SVG moet JSON blijven.
		if ( 'kadence_custom_svg' === $post->post_type && null === json_decode( $nieuw ) ) {
			return new WP_Error( 'kadence_mcp_svg_json', __( 'Na de vervanging is de inhoud geen geldige JSON meer; Kadence zou het icoon niet meer kunnen lezen.', 'mcp-abilities-kadence' ) );
		}

		$voorspelling = 'kadence_vector' === $post->post_type
			? self::voorspel_svg_opslag( $nieuw, false )
			: array( 'lost' => false, 'blocking' => false, 'note' => '' );

		if ( $voorspelling['blocking'] ) {
			return new WP_Error( 'kadence_mcp_svg_loss', $voorspelling['note'], array( 'prediction' => $voorspelling ) );
		}

		$verwacht = Kadence_MCP_Inventory::schrijf_token( $post, 'inhoud', $plan );
		$token    = isset( $input['token'] ) ? (string) $input['token'] : '';
		$rapport  = array(
			'post'       => array( 'id' => $post->ID, 'title' => get_the_title( $post ), 'type' => $post->post_type ),
			'replace'    => $plan,
			'length'     => array( 'before' => strlen( $inhoud ), 'after' => strlen( $nieuw ) ),
			'prediction' => $voorspelling,
		);

		if ( '' === $token ) {
			return array_merge(
				$rapport,
				array(
					'written' => false,
					'token'   => $verwacht,
					'status'  => __( 'Voorstel, er is NIETS opgeslagen. Er komt een revisie als de post dat ondersteunt, anders is dit veld before je weg terug. Roep opnieuw aan met het token om te schrijven.', 'mcp-abilities-kadence' ),
					'before'  => $inhoud,
				)
			);
		}

		if ( ! Kadence_MCP_Capabilities::current_user_can( Kadence_MCP_Capabilities::WRITE ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'kadence_mcp_write_denied', __( 'Geen kadence_mcp_write of geen bewerkrecht op deze post.', 'mcp-abilities-kadence' ), array( 'status' => 403 ) );
		}

		if ( ! hash_equals( $verwacht, $token ) ) {
			return new WP_Error( 'kadence_mcp_invalid_token', Kadence_MCP_Inventory::token_reden( $token, $verwacht, $post ) );
		}

		$resultaat = wp_update_post( array( 'ID' => $post->ID, 'post_content' => wp_slash( $nieuw ) ), true );

		if ( is_wp_error( $resultaat ) ) {
			return $resultaat;
		}

		clean_post_cache( $post->ID );
		$terug = (string) get_post_field( 'post_content', $post->ID );

		return array_merge(
			$rapport,
			array(
				'written' => $terug === $nieuw,
				'token'   => '',
				'status'  => $terug === $nieuw
					? __( 'geschreven en teruggelezen: de inhoud staat er precies zo.', 'mcp-abilities-kadence' )
					: __( 'LET OP: geschreven, maar de opgeslagen inhoud wijkt af van wat er verstuurd is — een filter of kses heeft ingegrepen. Vergelijk met get-raw-markup.', 'mcp-abilities-kadence' ),
			)
		);
	}

	/**
	 * Zet een post in de prullenbak, na een controle waar hij nog gebruikt wordt.
	 *
	 * Alleen de prullenbak, nooit definitief verwijderen: daar is WordPress'
	 * eigen scherm voor, met een mens erbij. Wordt het object nog ergens
	 * opgenomen (een navigatie in een header, een vector in een pagina, een
	 * custom SVG als icoon), dan komt er geen token tenzij ignore_usages aan
	 * staat — want een verwijzing naar een post in de prullenbak laat het blok
	 * stil verdwijnen.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function trash_post( $input = array() ) {
		$post = get_post( isset( $input['post_id'] ) ? (int) $input['post_id'] : 0 );

		if ( ! $post ) {
			return new WP_Error( 'kadence_mcp_post_not_found', __( 'Die post bestaat niet.', 'mcp-abilities-kadence' ) );
		}

		if ( in_array( $post->post_type, array( 'attachment', 'revision', 'nav_menu_item', 'customize_changeset' ), true ) || 'trash' === $post->post_status ) {
			return new WP_Error(
				'kadence_mcp_trash_not_here',
				'trash' === $post->post_status
					? __( 'Deze post staat al in de prullenbak.', 'mcp-abilities-kadence' )
					: sprintf( __( 'Een %s zet deze ability niet in de prullenbak; doe dat in het beheer.', 'mcp-abilities-kadence' ), $post->post_type )
			);
		}

		if ( ! current_user_can( 'read_post', $post->ID ) ) {
			return new WP_Error( 'kadence_mcp_cannot_read', __( 'Dit account mag deze post niet lezen (zie check-access).', 'mcp-abilities-kadence' ) );
		}

		// Waar wordt hij nog gebruikt? Op id-attribuut, en bij een custom SVG
		// ook op zijn iconnaam kb-custom-{ID}.
		$gebruik = Kadence_MCP_Abilities_Site::find_usages( array( 'object_id' => $post->ID, 'scan_limit' => 1000 ) );
		$treffers = is_wp_error( $gebruik ) ? array() : $gebruik['usages'];

		// Wat de scan niet ziet maar wel stil breekt. Tot 1.26.1 kreeg de actieve
		// footer (een element op replace_footer) hier "veilig": hij wordt door
		// een hook geladen, niet via een id-attribuut. En een scan die faalde of
		// begrensd was, gold als "niets gevonden".
		$bezwaren = array();

		if ( is_wp_error( $gebruik ) ) {
			$bezwaren[] = sprintf( __( 'find-usages faalde (%s), dus het is onbekend waar dit object nog staat.', 'mcp-abilities-kadence' ), $gebruik->get_error_message() );
		} elseif ( ! empty( $gebruik['truncated'] ) ) {
			$bezwaren[] = sprintf( __( 'De scan is begrensd op %d posts en dekt de site mogelijk niet helemaal.', 'mcp-abilities-kadence' ), (int) $gebruik['scanned'] );
		}

		if ( 'kadence_element' === $post->post_type && 'publish' === $post->post_status && '' !== (string) get_post_meta( $post->ID, '_kad_element_hook', true ) ) {
			$bezwaren[] = sprintf( __( 'Dit element is actief op de hook %s: het wordt door het thema geladen, niet via een blok, dus find-usages ziet het niet. Zet het eerst op concept en controleer de site.', 'mcp-abilities-kadence' ), (string) get_post_meta( $post->ID, '_kad_element_hook', true ) );
		}

		foreach ( array( 'page_on_front' => __( 'de voorpagina', 'mcp-abilities-kadence' ), 'page_for_posts' => __( 'de berichtenpagina', 'mcp-abilities-kadence' ), 'wp_page_for_privacy_policy' => __( 'de privacypagina', 'mcp-abilities-kadence' ) ) as $optie => $rol ) {
			if ( (int) get_option( $optie ) === $post->ID ) {
				$bezwaren[] = sprintf( __( 'Deze pagina is %s van de site (instelling %s).', 'mcp-abilities-kadence' ), $rol, $optie );
			}
		}

		if ( 'kadence_custom_svg' === $post->post_type ) {
			global $wpdb;

			foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_type, post_status FROM {$wpdb->posts} WHERE post_status NOT IN ('trash','inherit','auto-draft') AND post_type <> 'revision' AND post_content LIKE %s LIMIT 100", '%' . $wpdb->esc_like( 'kb-custom-' . $post->ID ) . '%' ) ) as $rij ) {
				$treffers[] = array( 'id' => (int) $rij->ID, 'title' => $rij->post_title, 'post_type' => $rij->post_type, 'status' => $rij->post_status, 'hits' => 1, 'via' => 'kb-custom-' . $post->ID );
			}
		}

		$negeer  = ! empty( $input['ignore_usages'] );
		$rapport = array(
			'post'   => array( 'id' => $post->ID, 'title' => get_the_title( $post ), 'type' => $post->post_type, 'status' => $post->post_status ),
			'usages' => $treffers,
		);

		// Deze bezwaren zijn niet weg te zetten met ignore_usages: daar hoort een
		// mens in het beheer te beslissen.
		if ( ! empty( $bezwaren ) ) {
			return array_merge(
				$rapport,
				array(
					'verdict'    => 'riskant',
					'trashed'    => false,
					'token'      => '',
					'objections' => $bezwaren,
					'status'     => __( 'Geen token: zie objections. Dit gaat niet via deze ability, ook niet met ignore_usages; doe het in het beheer als het echt de bedoeling is.', 'mcp-abilities-kadence' ),
				)
			);
		}

		if ( ! empty( $treffers ) && ! $negeer ) {
			return array_merge(
				$rapport,
				array(
					'verdict' => 'riskant',
					'trashed' => false,
					'token'   => '',
					'status'  => sprintf(
						/* translators: %d: number of posts. */
						__( 'Geen token: %d posts gebruiken dit object nog (usages). Een verwijzing naar een post in de prullenbak laat het blok stil verdwijnen. Haal de verwijzingen eerst weg, of geef ignore_usages: true als dat de bedoeling is.', 'mcp-abilities-kadence' ),
						count( $treffers )
					),
				)
			);
		}

		$verwacht = Kadence_MCP_Inventory::schrijf_token( $post, 'prullenbak', array( 'ignore_usages' => $negeer ) );
		$token    = isset( $input['token'] ) ? (string) $input['token'] : '';

		if ( '' === $token ) {
			return array_merge(
				$rapport,
				array(
					'verdict' => 'veilig',
					'trashed' => false,
					'token'   => $verwacht,
					'status'  => empty( $treffers )
						? __( 'Voorstel, er is NIETS gedaan. Het object wordt nergens gebruikt. Roep opnieuw aan met het token om hem in de prullenbak te zetten (terughalen kan vanuit de prullenbak).', 'mcp-abilities-kadence' )
						: __( 'Voorstel, er is NIETS gedaan. Het object wordt nog gebruikt, maar ignore_usages staat aan. Roep opnieuw aan met het token om hem in de prullenbak te zetten.', 'mcp-abilities-kadence' ),
				)
			);
		}

		if ( ! Kadence_MCP_Capabilities::current_user_can( Kadence_MCP_Capabilities::WRITE ) || ! current_user_can( 'delete_post', $post->ID ) ) {
			return new WP_Error( 'kadence_mcp_write_denied', __( 'Geen kadence_mcp_write of geen recht om deze post te verwijderen.', 'mcp-abilities-kadence' ), array( 'status' => 403 ) );
		}

		if ( ! hash_equals( $verwacht, $token ) ) {
			return new WP_Error( 'kadence_mcp_invalid_token', Kadence_MCP_Inventory::token_reden( $token, $verwacht, $post ) );
		}

		$uit = wp_trash_post( $post->ID );

		return array_merge(
			$rapport,
			array(
				'verdict' => 'veilig',
				'trashed' => (bool) $uit && 'trash' === get_post_status( $post->ID ),
				'token'   => '',
				'status'  => ( $uit && 'trash' === get_post_status( $post->ID ) )
					? __( 'In de prullenbak gezet. Terughalen kan in het beheer, onder Prullenbak van dit posttype.', 'mcp-abilities-kadence' )
					: __( 'LET OP: de post staat niet in de prullenbak; WordPress of een plugin heeft het tegengehouden.', 'mcp-abilities-kadence' ),
			)
		);
	}

	/**
	 * Een Kadence-object als pakket: inhoud, instellingen en wat erin verwijst.
	 *
	 * Een navigatie, header of element bestaat uit twee delen: de blokken in
	 * post_content en de weergave in _kad-meta (schaduwen, kleuren, plaatsing).
	 * get-raw-markup en prepare-import dragen alleen het eerste deel over. Dit
	 * pakket draagt allebei, en noemt de ID's waarvoor op de andere site een
	 * kaart nodig is. Schrijft niets.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function export_entity( $input = array() ) {
		$post = get_post( isset( $input['post_id'] ) ? (int) $input['post_id'] : 0 );

		if ( ! $post || 0 !== strpos( (string) $post->post_type, 'kadence_' ) ) {
			return new WP_Error( 'kadence_mcp_not_a_kadence_entity', __( 'Geef het ID van een Kadence-object (navigatie, header, element, query, card, vector, custom SVG).', 'mcp-abilities-kadence' ) );
		}

		if ( ! current_user_can( 'read_post', $post->ID ) ) {
			return new WP_Error( 'kadence_mcp_cannot_read', __( 'Dit account mag deze post niet lezen (zie check-access).', 'mcp-abilities-kadence' ) );
		}

		$meta = array();

		foreach ( get_post_meta( $post->ID ) as $sleutel => $waarden ) {
			if ( 0 === strpos( (string) $sleutel, '_kad_' ) ) {
				$meta[ $sleutel ] = maybe_unserialize( $waarden[0] );
			}
		}

		ksort( $meta );

		// Welke ID's staan erin? Die bestaan op de andere site onder een ander
		// nummer; de kaarten van import-entity zetten ze om.
		$verwijzingen = array( 'posts' => array(), 'media' => array() );
		$loop         = static function ( $blokken ) use ( &$loop, &$verwijzingen ) {
			foreach ( $blokken as $blok ) {
				$naam  = isset( $blok['blockName'] ) ? (string) $blok['blockName'] : '';
				$attrs = isset( $blok['attrs'] ) && is_array( $blok['attrs'] ) ? $blok['attrs'] : array();

				if ( isset( $attrs['id'] ) && is_numeric( $attrs['id'] ) && (int) $attrs['id'] > 0 && ! in_array( $naam, array( 'kadence/tab', 'kadence/slide', 'kadence/pane' ), true ) ) {
					$doel = get_post( (int) $attrs['id'] );
					$soort = $doel && 'attachment' === $doel->post_type ? 'media' : 'posts';

					$verwijzingen[ $soort ][ (int) $attrs['id'] ] = array(
						'id'    => (int) $attrs['id'],
						'type'  => $doel ? $doel->post_type : '',
						'title' => $doel ? get_the_title( $doel ) : '',
						'slug'  => $doel ? $doel->post_name : '',
						'block' => $naam,
					);
				}

				foreach ( array( 'bgImgID', 'imgID', 'mediaId' ) as $sleutel ) {
					if ( isset( $attrs[ $sleutel ] ) && is_numeric( $attrs[ $sleutel ] ) && (int) $attrs[ $sleutel ] > 0 ) {
						$verwijzingen['media'][ (int) $attrs[ $sleutel ] ] = array(
							'id'    => (int) $attrs[ $sleutel ],
							'file'  => wp_basename( (string) get_attached_file( (int) $attrs[ $sleutel ] ) ),
							'block' => $naam,
						);
					}
				}

				if ( ! empty( $blok['innerBlocks'] ) ) {
					$loop( $blok['innerBlocks'] );
				}
			}
		};
		$loop( parse_blocks( $post->post_content ) );

		return array(
			'package'    => array(
				'format'    => 'kadence-mcp-entity/1',
				'source'    => home_url(),
				'source_id' => $post->ID,
				'post_type' => $post->post_type,
				'title'     => $post->post_title,
				'slug'      => $post->post_name,
				'status'    => $post->post_status,
				'content'   => $post->post_content,
				'meta'      => (object) $meta,
			),
			'references' => array(
				'posts' => array_values( $verwijzingen['posts'] ),
				'media' => array_values( $verwijzingen['media'] ),
			),
			'status'     => sprintf(
				/* translators: 1: meta keys, 2: post refs, 3: media refs. */
				__( 'Pakket met de inhoud en %1$d _kad-instellingen. Er wordt verwezen naar %2$d posts en %3$d media; zoek hun tegenstuk op de andere site (op slug, titel of bestandsnaam) en geef die mee als post_map en media_map aan import-entity. ID\'s in de meta zelf (bijvoorbeeld paginavoorwaarden van een element) zet import-entity niet om; die staan in zijn voorstel onder meta_ids_to_check.', 'mcp-abilities-kadence' ),
				count( $meta ),
				count( $verwijzingen['posts'] ),
				count( $verwijzingen['media'] )
			),
		);
	}

	/**
	 * Zet een pakket uit export-entity neer: nieuw, of over een bestaand object.
	 *
	 * Bedoeld voor wat op 28-09-2026 misging: Kadence' eigen export en import
	 * haalden de backslashes uit de inhoud (\u002d werd u002d) en braken zo
	 * var(--…) en klassen, en daarna moest alles met eigen scripts rechtgezet.
	 * Hier gaat de inhoud door dezelfde omzetting als prepare-import (replace,
	 * post_map, media_map, term_map), wordt de blokmarkup op stabiliteit
	 * getoetst, en na het opslaan byte voor byte teruggelezen.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function import_entity( $input = array() ) {
		$pakket = isset( $input['package'] ) && is_array( $input['package'] ) ? $input['package'] : array();

		if ( empty( $pakket['post_type'] ) || ! isset( $pakket['content'] ) || 'kadence-mcp-entity/1' !== ( isset( $pakket['format'] ) ? $pakket['format'] : '' ) ) {
			return new WP_Error( 'kadence_mcp_bad_package', __( 'Geef package zoals export-entity het teruggeeft (format kadence-mcp-entity/1).', 'mcp-abilities-kadence' ) );
		}

		$type = (string) $pakket['post_type'];

		if ( 0 !== strpos( $type, 'kadence_' ) || ! post_type_exists( $type ) ) {
			return new WP_Error( 'kadence_mcp_entity_type_missing', sprintf( __( 'Het posttype %s bestaat op deze site niet.', 'mcp-abilities-kadence' ), $type ) );
		}

		$doel_id = isset( $input['target_id'] ) ? (int) $input['target_id'] : 0;
		$doel    = $doel_id > 0 ? get_post( $doel_id ) : null;

		if ( $doel_id > 0 && ( ! $doel || $doel->post_type !== $type ) ) {
			return new WP_Error( 'kadence_mcp_bad_target', sprintf( __( 'target_id %1$d is geen %2$s op deze site.', 'mcp-abilities-kadence' ), $doel_id, $type ) );
		}

		// De inhoud: vervangen, ID's omzetten, stabiliteit toetsen.
		$boom    = Kadence_MCP_Inventory::schoon_blokken( parse_blocks( (string) $pakket['content'] ) );
		$gemeld  = array();

		foreach ( ( isset( $input['replace'] ) && is_array( $input['replace'] ) ? $input['replace'] : array() ) as $paar ) {
			if ( ! empty( $paar['from'] ) ) {
				$aantal   = 0;
				$boom     = self::vervang_in_boom( $boom, (string) $paar['from'], isset( $paar['to'] ) ? (string) $paar['to'] : '', $aantal );
				$gemeld[] = array( 'from' => (string) $paar['from'], 'to' => isset( $paar['to'] ) ? (string) $paar['to'] : '', 'count' => $aantal );
			}
		}

		$omgezet = array( 'media' => 0, 'terms' => 0, 'posts' => 0 );
		$boom    = self::zet_ids_om(
			$boom,
			self::id_kaart( isset( $input['media_map'] ) ? $input['media_map'] : array() ),
			self::id_kaart( isset( $input['term_map'] ) ? $input['term_map'] : array() ),
			$omgezet,
			self::id_kaart( isset( $input['post_map'] ) ? $input['post_map'] : array() )
		);
		$inhoud  = '' === trim( (string) $pakket['content'] ) ? '' : Kadence_MCP_Inventory::serialiseer( $boom );

		if ( '' !== $inhoud && Kadence_MCP_Inventory::serialiseer( parse_blocks( $inhoud ) ) !== $inhoud ) {
			return new WP_Error( 'kadence_mcp_unstable_markup', __( 'De inhoud overleeft een parse- en serialiseerronde niet; er is niets gedaan.', 'mcp-abilities-kadence' ) );
		}

		// Vectoren en custom SVG's zijn geen blokmarkup: letterlijk overnemen.
		if ( in_array( $type, array( 'kadence_vector', 'kadence_custom_svg' ), true ) ) {
			$inhoud = (string) $pakket['content'];

			foreach ( $gemeld as $i => $paar ) {
				$gemeld[ $i ]['count'] = substr_count( $inhoud, $paar['from'] );
				$inhoud                = str_replace( $paar['from'], $paar['to'], $inhoud );
			}
		}

		// De meta: alleen sleutels die Kadence voor dit posttype registreert.
		$meta       = isset( $pakket['meta'] ) && ( is_array( $pakket['meta'] ) || is_object( $pakket['meta'] ) ) ? (array) $pakket['meta'] : array();
		$bekend     = array_keys( get_registered_meta_keys( 'post', $type ) );
		$onbekend   = array();
		$ids_meta   = array();

		foreach ( $meta as $sleutel => $waarde ) {
			// Altijd het prefix _kad_: export-entity levert niets anders, en zonder
			// deze eis kwamen via een vector (geen geregistreerde sleutels) of via
			// een sleutel die het doel toevallig al had, meta van andere plug-ins mee.
			if ( 0 !== strpos( (string) $sleutel, '_kad_' ) || ( ! empty( $bekend ) && ! in_array( $sleutel, $bekend, true ) && ! ( $doel && metadata_exists( 'post', $doel->ID, $sleutel ) ) ) ) {
				$onbekend[] = $sleutel;
				continue;
			}

			$tekst = is_scalar( $waarde ) ? (string) $waarde : wp_json_encode( $waarde );

			// Ook "ids":[12,34]: zo bewaart een element de pagina's waarop het
			// verschijnt. Per sleutel de gevonden ID's, zodat je weet wat je moet
			// nakijken.
			if ( preg_match_all( '/"(?:id|ID|ids|post|page|value)":\s*(\[[^\]]*\]|"?\d+)/', (string) $tekst, $m ) || ( preg_match( '/(_id|Id|ID)$/', $sleutel ) && is_numeric( $waarde ) && (int) $waarde > 0 ) ) {
				$gevonden_ids = array();

				foreach ( ! empty( $m[1] ) ? $m[1] : array( (string) $waarde ) as $stuk ) {
					if ( preg_match_all( '/\d+/', $stuk, $n ) ) {
						$gevonden_ids = array_merge( $gevonden_ids, array_map( 'intval', $n[0] ) );
					}
				}

				$gevonden_ids = array_values( array_unique( array_filter( $gevonden_ids ) ) );

				if ( ! empty( $gevonden_ids ) ) {
					$ids_meta[] = array( 'key' => $sleutel, 'ids' => $gevonden_ids );
				}
			}

			$m = array();
		}

		if ( ! empty( $onbekend ) ) {
			return new WP_Error(
				'kadence_mcp_bad_meta_key',
				sprintf(
					/* translators: 1: keys, 2: post type. */
					__( 'Deze sleutels neemt import-entity niet over voor %2$s: %1$s. Alleen sleutels met het prefix _kad_ die Kadence hier registreert (of die het doel al heeft) komen mee. Staat er op deze site een andere Kadence-versie? Haal ze uit het pakket of werk Kadence bij.', 'mcp-abilities-kadence' ),
					implode( ', ', $onbekend ),
					$type
				)
			);
		}

		$voorstel = array(
			'post_type'          => $type,
			'title'              => (string) $pakket['title'],
			'target'             => $doel ? array( 'id' => $doel->ID, 'title' => get_the_title( $doel ) ) : null,
			'replace'            => $gemeld,
			'ids_converted'      => $omgezet,
			'meta_keys'          => count( $meta ),
			'meta_ids_to_check'  => $ids_meta,
			'content_length'     => strlen( $inhoud ),
			'backslashes'        => substr_count( $inhoud, '\\' ),
			'warnings'           => current_user_can( 'unfiltered_html' ) ? array() : array( __( 'Geen unfiltered_html: WordPress haalt bij het opslaan SVG en inline-HTML door kses. Zie check-access.', 'mcp-abilities-kadence' ) ),
		);

		// Status en slug horen bij wat getoetst is: tot 1.26.1 kon een token voor
		// een concept ook een gepubliceerd object opleveren.
		$status = isset( $input['status'] ) && 'publish' === $input['status'] ? 'publish' : ( $doel ? $doel->post_status : 'draft' );
		$slug   = ! $doel && isset( $pakket['slug'] ) ? sanitize_title( (string) $pakket['slug'] ) : '';

		$voorstel['post_status'] = $status;
		$voorstel['slug']        = $doel ? $doel->post_name : $slug;

		// Een aanmaaktoken is anders herbruikbaar: dezelfde aanroep twee keer gaf
		// twee objecten. Bestaat er al een met deze titel, dan beslist een mens.
		if ( ! $doel ) {
			$dubbel = Kadence_MCP_Inventory::bestaand_object( $type, (string) $pakket['title'] );

			if ( $dubbel ) {
				return array_merge(
					$voorstel,
					array(
						'verdict'  => 'riskant',
						'written'  => false,
						'token'    => '',
						'existing' => array( 'id' => $dubbel->ID, 'status' => $dubbel->post_status ),
						'status'   => sprintf( __( 'Geen token: er bestaat al een %1$s "%2$s" (ID %3$d, %4$s). Is dit een tweede aanroep met hetzelfde token, dan is hij al aangemaakt. Wil je die overschrijven, geef dan target_id: %3$d; anders eerst een andere titel.', 'mcp-abilities-kadence' ), $type, (string) $pakket['title'], $dubbel->ID, $dubbel->post_status ),
					)
				);
			}
		}

		$token    = isset( $input['token'] ) ? (string) $input['token'] : '';
		$verwacht = 'kmcp1_' . substr( wp_hash( (string) wp_json_encode( array( $type, $doel ? $doel->ID . ':' . $doel->post_modified_gmt : 'nieuw', md5( $inhoud ), md5( wp_json_encode( $meta ) ), $pakket['title'], $status, $slug ) ) ), 0, 32 );

		if ( '' === $token ) {
			return array_merge(
				$voorstel,
				array(
					'written' => false,
					'token'   => $verwacht,
					'status'  => $doel
						? sprintf( __( 'Voorstel, er is NIETS opgeslagen. De inhoud en %1$d instellingen van %2$d worden overschreven (de inhoud krijgt een revisie, de meta niet). Kijk naar meta_ids_to_check: ID\'s daarin zijn niet omgezet. Roep opnieuw aan met het token.', 'mcp-abilities-kadence' ), count( $meta ), $doel->ID )
						: sprintf( __( 'Voorstel, er is NIETS opgeslagen. Er komt een nieuwe %1$s "%2$s" met %3$d instellingen, als concept tenzij status publish is. Kijk naar meta_ids_to_check. Roep opnieuw aan met het token.', 'mcp-abilities-kadence' ), $type, (string) $pakket['title'], count( $meta ) ),
				)
			);
		}

		if ( ! Kadence_MCP_Capabilities::current_user_can( Kadence_MCP_Capabilities::WRITE ) ) {
			return new WP_Error( 'kadence_mcp_write_denied', __( 'Je hebt de capability kadence_mcp_write niet.', 'mcp-abilities-kadence' ), array( 'status' => 403 ) );
		}

		$type_object = get_post_type_object( $type );

		if ( $doel ? ! current_user_can( 'edit_post', $doel->ID ) : ! current_user_can( $type_object->cap->create_posts ) ) {
			return new WP_Error( 'kadence_mcp_edit_denied', __( 'Geen recht om dit object aan te maken of te bewerken (zie check-access).', 'mcp-abilities-kadence' ), array( 'status' => 403 ) );
		}

		if ( 'publish' === $status && ( ! $doel || 'publish' !== $doel->post_status ) && ! current_user_can( $type_object->cap->publish_posts ) ) {
			return new WP_Error( 'kadence_mcp_publish_denied', __( 'Geen recht om dit posttype te publiceren (publish_posts); laat status weg voor een concept.', 'mcp-abilities-kadence' ), array( 'status' => 403 ) );
		}

		if ( ! hash_equals( $verwacht, $token ) ) {
			return new WP_Error( 'kadence_mcp_invalid_token', __( 'Het token hoort niet bij deze invoer, of het doel is intussen gewijzigd. Vraag opnieuw een voorstel.', 'mcp-abilities-kadence' ) );
		}

		$velden = array(
			'post_type'    => $type,
			'post_title'   => (string) $pakket['title'],
			'post_content' => wp_slash( $inhoud ),
			'post_status'  => $status,
		);

		if ( $doel ) {
			$velden['ID'] = $doel->ID;
			$id           = wp_update_post( $velden, true );
		} else {
			$velden['post_name'] = $slug;
			$id                  = wp_insert_post( $velden, true );
		}

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		foreach ( $meta as $sleutel => $waarde ) {
			Kadence_MCP_Inventory::schrijf_meta( (int) $id, $sleutel, $waarde );
		}

		clean_post_cache( (int) $id );

		$afwijking = array();

		if ( (string) get_post_field( 'post_content', (int) $id ) !== $inhoud ) {
			$afwijking[] = 'content';
		}

		foreach ( $meta as $sleutel => $waarde ) {
			if ( ! Kadence_MCP_Inventory::meta_gelijk( get_post_meta( (int) $id, $sleutel, true ), $waarde ) ) {
				$afwijking[] = $sleutel;
			}
		}

		return array_merge(
			$voorstel,
			array(
				'id'       => (int) $id,
				'written'  => empty( $afwijking ),
				'mismatch' => $afwijking,
				'token'    => '',
				'status'   => empty( $afwijking )
					? sprintf( __( 'Geschreven en teruggelezen: object %d, inhoud byte voor byte gelijk (ook de backslashes) en alle instellingen zoals in het pakket.', 'mcp-abilities-kadence' ), (int) $id )
					: sprintf( __( 'LET OP: geschreven, maar dit wijkt af na het teruglezen: %s. Een filter of sanitizer heeft ingegrepen.', 'mcp-abilities-kadence' ), implode( ', ', $afwijking ) ),
			)
		);
	}

	/**
	 * Maak een post van een publiek posttype.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function create_post( $input = array() ) {
		$type  = isset( $input['post_type'] ) ? sanitize_key( (string) $input['post_type'] ) : '';
		$titel = isset( $input['title'] ) ? trim( wp_strip_all_tags( (string) $input['title'] ) ) : '';

		$object = $type ? get_post_type_object( $type ) : null;

		if ( ! $object ) {
			return new WP_Error(
				'kadence_mcp_unknown_post_type',
				sprintf(
					/* translators: %s: post type. */
					__( 'Het posttype "%s" bestaat op deze site niet.', 'mcp-abilities-kadence' ),
					$type
				)
			);
		}

		// Wat hier niet hoort heeft een eigen ability, met eigen controles.
		$elders = array(
			'page'               => 'create-page',
			'attachment'         => __( 'de mediabibliotheek', 'mcp-abilities-kadence' ),
			'kadence_navigation' => 'create-entity',
			'kadence_header'     => 'create-entity',
			'kadence_element'    => 'create-entity',
			'kadence_vector'     => 'create-entity',
			'kadence_query'      => 'create-query',
			'kadence_query_card' => 'create-query-card',
		);

		if ( isset( $elders[ $type ] ) || 0 === strpos( $type, 'wp_' ) || 0 === strpos( $type, 'kadence_' ) || ! $object->show_in_rest ) {
			return new WP_Error(
				'kadence_mcp_post_type_elsewhere',
				isset( $elders[ $type ] )
					? sprintf(
						/* translators: 1: post type, 2: where to go instead. */
						__( '%1$s maak je niet hier maar met %2$s.', 'mcp-abilities-kadence' ),
						$type,
						$elders[ $type ]
					)
					: sprintf(
						/* translators: %s: post type. */
						__( '%s is een intern posttype of staat niet in de REST-API; dat maakt deze ability niet aan.', 'mcp-abilities-kadence' ),
						$type
					)
			);
		}

		if ( '' === $titel ) {
			return new WP_Error( 'kadence_mcp_no_title', __( 'Geef de post een titel.', 'mcp-abilities-kadence' ) );
		}

		$status = ( isset( $input['status'] ) && 'publish' === $input['status'] ) ? 'publish' : 'draft';

		list( $bezwaren, $plan, $termen, $acf_velden ) = self::toets_postvelden( $type, $object, $input );

		// De publicatiedatum. Zonder datum zet WordPress het moment van
		// aanmaken; bij een overzetting is dat zelden de bedoeling.
		$datum = isset( $input['date'] ) ? trim( (string) $input['date'] ) : '';

		if ( '' !== $datum ) {
			$tijd = strtotime( $datum );

			if ( false === $tijd || ! preg_match( '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $datum ) ) {
				$bezwaren[] = sprintf( __( 'De datum "%s" is niet te lezen. Gebruik JJJJ-MM-DD of JJJJ-MM-DD UU:MM:SS, in de tijdzone van de site.', 'mcp-abilities-kadence' ), $datum );
			} else {
				$plan['date'] = gmdate( 'Y-m-d H:i:s', $tijd );

				if ( 'publish' === $status && $tijd > current_time( 'timestamp' ) ) {
					$plan['date_note'] = __( 'de datum ligt in de toekomst: WordPress zet de post dan op "gepland" (future) in plaats van gepubliceerd.', 'mcp-abilities-kadence' );
				}
			}
		}

		// Wat WordPress er zelf bij zou zetten, vooraf gemeld.
		if ( in_array( 'category', get_object_taxonomies( $type ), true ) && ! isset( $termen['category'] ) ) {
			$standaard = get_term( (int) get_option( 'default_category' ), 'category' );
			$plan['wordpress_adds'][] = sprintf(
				/* translators: %s: category name. */
				__( 'categorie "%s" (de standaardcategorie; geef terms {"category": []} voor geen categorie)', 'mcp-abilities-kadence' ),
				$standaard && ! is_wp_error( $standaard ) ? $standaard->name : 'Uncategorized'
			);
		}

		if ( ! isset( $plan['date'] ) ) {
			$plan['wordpress_adds'][] = __( 'de datum van nu als publicatiedatum (geef date mee om dat te voorkomen)', 'mcp-abilities-kadence' );
		}

		// Inhoud: dezelfde rondgang als create-page.
		$markup = isset( $input['content'] ) ? (string) $input['content'] : '';

		if ( '' !== trim( $markup ) ) {
			$blokken = Kadence_MCP_Inventory::schoon_blokken( parse_blocks( $markup ) );
			$schoon  = Kadence_MCP_Inventory::serialiseer( $blokken );

			if ( empty( $blokken ) || Kadence_MCP_Inventory::serialiseer( parse_blocks( $schoon ) ) !== $schoon ) {
				$bezwaren[] = __( 'De inhoud is geen stabiele blokmarkup: hij levert geen blokken op of overleeft een parse- en serialiseerronde niet.', 'mcp-abilities-kadence' );
			} else {
				$markup = $schoon;
				$plan['content_blocks'] = count( $blokken );
			}
		}

		if ( ! empty( $bezwaren ) ) {
			return new WP_Error( 'kadence_mcp_create_post_invalid', implode( ' ', $bezwaren ) );
		}

		$slug      = isset( $input['slug'] ) ? sanitize_title( (string) $input['slug'] ) : '';
		$token     = isset( $input['token'] ) ? (string) $input['token'] : '';
		$grondslag = 'kmcp1_' . substr(
			wp_hash( (string) wp_json_encode( array( $type, $titel, $slug, $status, $markup, $plan, isset( $input['acf'] ) ? $input['acf'] : array() ) ) ),
			0,
			32
		);

		$rapport = array(
			'post_type' => $type,
			'title'     => $titel,
			'status'    => $status,
			'plan'      => (object) $plan,
		);

		if ( '' === $token ) {
			return array_merge(
				$rapport,
				array(
					'new_id'   => 0,
					'url'      => '',
					'created'  => false,
					'mismatch' => array(),
					'token'    => $grondslag,
					'note'     => sprintf(
						/* translators: 1: post type, 2: title, 3: status. */
						__( 'Voorstel, er is NIETS aangemaakt. Er zou een %1$s "%2$s" komen (%3$s), met wat onder plan staat; alles is tegen dit posttype getoetst. Roep opnieuw aan met het token om hem te maken.', 'mcp-abilities-kadence' ),
						$type,
						$titel,
						$status
					),
				)
			);
		}

		if ( ! Kadence_MCP_Capabilities::current_user_can( Kadence_MCP_Capabilities::WRITE ) ) {
			return new WP_Error( 'kadence_mcp_write_denied', __( 'Je hebt de capability kadence_mcp_write niet.', 'mcp-abilities-kadence' ), array( 'status' => 403 ) );
		}

		$mag_maken = isset( $object->cap->create_posts ) ? (string) $object->cap->create_posts : 'edit_posts';

		if ( ! current_user_can( $mag_maken ) ) {
			return new WP_Error(
				'kadence_mcp_create_denied',
				sprintf( __( 'Je mist de capability "%1$s", die nodig is om een %2$s aan te maken.', 'mcp-abilities-kadence' ), $mag_maken, $type ),
				array( 'status' => 403 )
			);
		}

		if ( 'publish' === $status && isset( $object->cap->publish_posts ) && ! current_user_can( $object->cap->publish_posts ) ) {
			return new WP_Error( 'kadence_mcp_publish_denied', __( 'Je mag dit posttype niet publiceren. Laat status weg voor een concept.', 'mcp-abilities-kadence' ) );
		}

		if ( ! hash_equals( $grondslag, $token ) ) {
			return new WP_Error( 'kadence_mcp_invalid_token', __( 'Het token hoort niet bij deze invoer. Roep opnieuw zonder token aan en gebruik het token dat je dan terugkrijgt.', 'mcp-abilities-kadence' ) );
		}

		$al_gebruikt = Kadence_MCP_Inventory::token_al_gebruikt( $token );

		if ( is_wp_error( $al_gebruikt ) ) {
			return $al_gebruikt;
		}

		$velden = array(
			'post_type'    => $type,
			'post_status'  => $status,
			'post_title'   => $titel,
			'post_name'    => $slug,
			'post_excerpt' => isset( $plan['excerpt'] ) ? $plan['excerpt'] : '',
			'menu_order'   => isset( $plan['menu_order'] ) ? $plan['menu_order'] : 0,
			'post_content' => wp_slash( $markup ),
		);

		if ( isset( $plan['date'] ) ) {
			$velden['post_date']     = $plan['date'];
			$velden['post_date_gmt'] = get_gmt_from_date( $plan['date'] );
		}

		$nieuw_id = wp_insert_post( $velden, true );

		if ( is_wp_error( $nieuw_id ) ) {
			return $nieuw_id;
		}

		Kadence_MCP_Inventory::onthoud_token( $token, $nieuw_id );

		if ( isset( $plan['featured_image'] ) ) {
			set_post_thumbnail( $nieuw_id, $plan['featured_image']['id'] );
		}

		foreach ( $termen as $taxonomie => $ids ) {
			wp_set_object_terms( $nieuw_id, $ids, $taxonomie );
		}

		// Wat WordPress er uit zichzelf bij heeft gezet: termen in een
		// taxonomie waar niets voor gevraagd was.
		$erbij = array();

		foreach ( get_object_taxonomies( $type ) as $taxonomie ) {
			if ( isset( $termen[ $taxonomie ] ) ) {
				continue;
			}

			$staat = wp_get_object_terms( $nieuw_id, $taxonomie, array( 'fields' => 'slugs' ) );

			if ( ! is_wp_error( $staat ) && ! empty( $staat ) ) {
				$erbij[ $taxonomie ] = $staat;
			}
		}

		// Op veldsleutel en niet op naam: bij een nieuwe post bestaat er nog
		// geen verwijzing van naam naar sleutel, en dan slaat ACF op naam een
		// waarde op die zijn eigen velden daarna niet herkennen.
		foreach ( $acf_velden as $naam => $veld ) {
			update_field( $veld['key'], $veld['value'], $nieuw_id );
		}

		clean_post_cache( $nieuw_id );

		// Teruglezen.
		$afwijking = array();
		$controle  = get_post( $nieuw_id );

		if ( ! $controle || $controle->post_title !== $titel ) {
			$afwijking[] = 'title';
		}

		if ( isset( $plan['featured_image'] ) && (int) get_post_thumbnail_id( $nieuw_id ) !== $plan['featured_image']['id'] ) {
			$afwijking[] = 'featured_image';
		}

		foreach ( $termen as $taxonomie => $ids ) {
			$staat = wp_get_object_terms( $nieuw_id, $taxonomie, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $staat ) || array_diff( $ids, array_map( 'intval', $staat ) ) ) {
				$afwijking[] = 'terms:' . $taxonomie;
			}
		}

		foreach ( $acf_velden as $naam => $veld ) {
			$terug = get_field( $veld['key'], $nieuw_id, false );

			if ( empty( $terug ) && ! empty( $veld['value'] ) ) {
				$afwijking[] = 'acf:' . $naam;
			}
		}

		return array_merge(
			$rapport,
			array(
				'new_id'   => (int) $nieuw_id,
				'url'      => (string) get_permalink( $nieuw_id ),
				'created'  => true,
				'mismatch' => $afwijking,
				'date'     => $controle ? $controle->post_date : '',
				'added_by_wordpress' => (object) $erbij,
				'token'    => '',
				'note'     => empty( $afwijking )
					? sprintf(
						/* translators: 1: post type, 2: ID, 3: status. */
						__( 'Aangemaakt: %1$s %2$d (%3$s). Teruggelezen: titel, afbeelding, termen en ACF-velden staan zoals bedoeld.', 'mcp-abilities-kadence' ),
						$type,
						(int) $nieuw_id,
						$status
					)
					: sprintf(
						/* translators: %s: fields. */
						__( 'LET OP: aangemaakt, maar bij het teruglezen wijkt dit af: %s.', 'mcp-abilities-kadence' ),
						implode( ', ', $afwijking )
					),
			)
		);
	}

	/**
	 * Werk een bestaande post van een publiek posttype bij.
	 *
	 * Zelfde toetsen als create_post (toets_postvelden). Alleen wat in de
	 * invoer staat verandert. Het voorstel geeft per onderdeel de huidige en de
	 * nieuwe waarde; het token is gebonden aan de post, zijn wijzigingsdatum en
	 * de hele invoer.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function update_post( $input = array() ) {
		$post = get_post( isset( $input['post_id'] ) ? (int) $input['post_id'] : 0 );

		if ( ! $post ) {
			return new WP_Error( 'kadence_mcp_post_not_found', __( 'Die post bestaat niet.', 'mcp-abilities-kadence' ) );
		}

		$type   = (string) $post->post_type;
		$object = get_post_type_object( $type );

		if ( ! $object || 'page' === $type || 'attachment' === $type || 0 === strpos( $type, 'wp_' ) || 0 === strpos( $type, 'kadence_' ) || ! $object->show_in_rest ) {
			return new WP_Error(
				'kadence_mcp_post_type_elsewhere',
				sprintf(
					/* translators: %s: post type. */
					__( '%s werkt deze ability niet bij. Een pagina of een Kadence-object heeft zijn eigen abilities (blokabilities, set-page-status, set-entity-meta).', 'mcp-abilities-kadence' ),
					$type
				)
			);
		}

		$wijzigbaar = array( 'title', 'slug', 'excerpt', 'menu_order', 'featured_image', 'terms', 'acf' );
		if ( ! array_intersect( $wijzigbaar, array_keys( $input ) ) ) {
			return new WP_Error( 'kadence_mcp_nothing_to_update', __( 'Geef ten minste één van title, slug, excerpt, menu_order, featured_image, terms of acf mee.', 'mcp-abilities-kadence' ) );
		}

		list( $bezwaren, $plan, $termen, $acf_velden ) = self::toets_postvelden( $type, $object, $input );

		$titel = isset( $input['title'] ) ? trim( wp_strip_all_tags( (string) $input['title'] ) ) : null;
		if ( null !== $titel && '' === $titel ) {
			$bezwaren[] = __( 'Een lege titel kan niet.', 'mcp-abilities-kadence' );
		}
		$slug = isset( $input['slug'] ) ? sanitize_title( (string) $input['slug'] ) : null;

		if ( ! empty( $bezwaren ) ) {
			return new WP_Error( 'kadence_mcp_update_post_invalid', implode( ' ', $bezwaren ) );
		}

		// Voor en na, per onderdeel dat genoemd is.
		$voor = array();
		$na   = array();

		if ( null !== $titel ) {
			$voor['title'] = $post->post_title;
			$na['title']   = $titel;
		}
		if ( null !== $slug ) {
			$voor['slug'] = $post->post_name;
			$na['slug']   = $slug;
		}
		if ( isset( $plan['excerpt'] ) ) {
			$voor['excerpt'] = $post->post_excerpt;
			$na['excerpt']   = $plan['excerpt'];
		}
		if ( isset( $plan['menu_order'] ) ) {
			$voor['menu_order'] = (int) $post->menu_order;
			$na['menu_order']   = $plan['menu_order'];
		}
		if ( isset( $plan['featured_image'] ) ) {
			$voor['featured_image'] = (int) get_post_thumbnail_id( $post->ID );
			$na['featured_image']   = $plan['featured_image']['id'];
		}
		foreach ( $termen as $taxonomie => $ids ) {
			$huidig = wp_get_object_terms( $post->ID, $taxonomie, array( 'fields' => 'slugs' ) );
			$voor[ 'terms:' . $taxonomie ] = is_wp_error( $huidig ) ? array() : $huidig;
			$na[ 'terms:' . $taxonomie ]   = $plan['terms'][ $taxonomie ];
		}
		foreach ( $acf_velden as $naam => $veld ) {
			$voor[ 'acf:' . $naam ] = get_field( $veld['key'], $post->ID, false );
			$na[ 'acf:' . $naam ]   = $veld['value'];
		}

		$gewijzigd = array();
		foreach ( $na as $sleutel => $waarde ) {
			if ( ! Kadence_MCP_Inventory::meta_gelijk( isset( $voor[ $sleutel ] ) ? $voor[ $sleutel ] : null, $waarde ) ) {
				$gewijzigd[] = $sleutel;
			}
		}

		$rapport = array(
			'post'    => array(
				'id'    => (int) $post->ID,
				'type'  => $type,
				'title' => (string) $post->post_title,
			),
			'before'  => (object) $voor,
			'after'   => (object) $na,
			'changed' => $gewijzigd,
		);

		// Het token dekt de post, zijn wijzigingsdatum en de hele invoer zonder
		// het token zelf: een goedkeuring voor deze wijziging op deze versie.
		$token   = isset( $input['token'] ) ? (string) $input['token'] : '';
		$invoer  = $input;
		unset( $invoer['token'] );
		$grondslag = Kadence_MCP_Inventory::schrijf_token( $post, '__update_post__', $invoer );

		if ( '' === $token ) {
			return array_merge(
				$rapport,
				array(
					'written'  => false,
					'mismatch' => array(),
					'token'    => $grondslag,
					'note'     => empty( $gewijzigd )
						? __( 'Voorstel, er is NIETS opgeslagen — en er zou ook niets veranderen: deze waarden staan er al.', 'mcp-abilities-kadence' )
						: sprintf(
							/* translators: %d: number of changes. */
							__( 'Voorstel, er is NIETS opgeslagen. Er zouden %d onderdelen wijzigen (changed). ACF-velden, termen en de uitgelichte afbeelding kennen geen revisies: bewaar before. Roep opnieuw aan met het token om te schrijven.', 'mcp-abilities-kadence' ),
							count( $gewijzigd )
						),
				)
			);
		}

		if ( ! Kadence_MCP_Capabilities::current_user_can( Kadence_MCP_Capabilities::WRITE ) ) {
			return new WP_Error( 'kadence_mcp_write_denied', __( 'Je hebt de capability kadence_mcp_write niet.', 'mcp-abilities-kadence' ), array( 'status' => 403 ) );
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'kadence_mcp_edit_denied', __( 'Je mag deze post niet bewerken.', 'mcp-abilities-kadence' ), array( 'status' => 403 ) );
		}

		if ( ! hash_equals( $grondslag, $token ) ) {
			return new WP_Error( 'kadence_mcp_invalid_token', Kadence_MCP_Inventory::token_reden( $token, $grondslag, $post ) );
		}

		$velden = array( 'ID' => $post->ID );
		if ( null !== $titel ) {
			$velden['post_title'] = $titel;
		}
		if ( null !== $slug ) {
			$velden['post_name'] = $slug;
		}
		if ( isset( $plan['excerpt'] ) ) {
			$velden['post_excerpt'] = $plan['excerpt'];
		}
		if ( isset( $plan['menu_order'] ) ) {
			$velden['menu_order'] = $plan['menu_order'];
		}
		if ( count( $velden ) > 1 ) {
			$uitkomst = wp_update_post( wp_slash( $velden ), true );
			if ( is_wp_error( $uitkomst ) ) {
				return $uitkomst;
			}
		}

		if ( isset( $plan['featured_image'] ) ) {
			set_post_thumbnail( $post->ID, $plan['featured_image']['id'] );
		}

		foreach ( $termen as $taxonomie => $ids ) {
			wp_set_object_terms( $post->ID, $ids, $taxonomie );
		}

		// Op veldsleutel, zoals bij create-post: bij botsende veldnamen (een
		// subveld met dezelfde naam als een veld op het hoogste niveau) kiest
		// ACF op naam anders het verkeerde veld.
		foreach ( $acf_velden as $naam => $veld ) {
			update_field( $veld['key'], $veld['value'], $post->ID );
		}

		clean_post_cache( $post->ID );

		// Teruglezen.
		$controle  = get_post( $post->ID );
		$afwijking = array();
		$terug     = array();

		foreach ( $na as $sleutel => $waarde ) {
			if ( 'title' === $sleutel ) {
				$terug[ $sleutel ] = $controle->post_title;
			} elseif ( 'slug' === $sleutel ) {
				$terug[ $sleutel ] = $controle->post_name;
			} elseif ( 'excerpt' === $sleutel ) {
				$terug[ $sleutel ] = $controle->post_excerpt;
			} elseif ( 'menu_order' === $sleutel ) {
				$terug[ $sleutel ] = (int) $controle->menu_order;
			} elseif ( 'featured_image' === $sleutel ) {
				$terug[ $sleutel ] = (int) get_post_thumbnail_id( $post->ID );
			} elseif ( 0 === strpos( $sleutel, 'terms:' ) ) {
				$staat             = wp_get_object_terms( $post->ID, substr( $sleutel, 6 ), array( 'fields' => 'slugs' ) );
				$terug[ $sleutel ] = is_wp_error( $staat ) ? array() : $staat;
				sort( $terug[ $sleutel ] );
				$verwacht = $waarde;
				sort( $verwacht );
				if ( $terug[ $sleutel ] !== $verwacht ) {
					$afwijking[] = $sleutel;
				}
				continue;
			} elseif ( 0 === strpos( $sleutel, 'acf:' ) ) {
				$veld              = $acf_velden[ substr( $sleutel, 4 ) ];
				$terug[ $sleutel ] = get_field( $veld['key'], $post->ID, false );
				// Een repeater komt terug met veldsleutels in plaats van
				// subveldnamen; vergelijk dan alleen of er iets staat.
				if ( 'repeater' === $veld['type'] || 'group' === $veld['type'] || 'flexible_content' === $veld['type'] ) {
					if ( empty( $terug[ $sleutel ] ) !== empty( $waarde ) || ( is_array( $waarde ) && count( (array) $terug[ $sleutel ] ) !== count( $waarde ) ) ) {
						$afwijking[] = $sleutel;
					}
					continue;
				}
			}

			if ( ! Kadence_MCP_Inventory::meta_gelijk( $terug[ $sleutel ], $waarde ) ) {
				$afwijking[] = $sleutel;
			}
		}

		return array_merge(
			$rapport,
			array(
				'after'    => (object) $terug,
				'written'  => true,
				'mismatch' => $afwijking,
				'token'    => '',
				'note'     => empty( $afwijking )
					? sprintf(
						/* translators: %d: number of changes. */
						__( 'Bijgewerkt en teruggelezen: %d onderdelen staan zoals bedoeld. ACF-velden, termen en de afbeelding hebben geen revisie; terugdraaien kan met before.', 'mcp-abilities-kadence' ),
						count( $gewijzigd )
					)
					: sprintf(
						/* translators: %s: keys. */
						__( 'LET OP: geschreven, maar bij het teruglezen wijkt dit af: %s. Controleer after.', 'mcp-abilities-kadence' ),
						implode( ', ', $afwijking )
					),
			)
		);
	}

	/**
	 * Toetst de velden die create-post en update-post delen tegen het posttype.
	 *
	 * Samenvatting, volgorde en uitgelichte afbeelding alleen als het type ze
	 * ondersteunt; termen alleen in taxonomieën die aan het type hangen en die
	 * bestaan; ACF-velden alleen uit een veldgroep die op het type geldt, met
	 * een waarde die bij het veldtype past.
	 *
	 * @param string       $type   Het posttype.
	 * @param WP_Post_Type $object Het posttype-object.
	 * @param array        $input  De invoer.
	 *
	 * @return array array( bezwaren, plan, termen, acf_velden ).
	 */
	private static function toets_postvelden( $type, $object, $input ) {
		$bezwaren = array();
		$plan     = array();

		// Samenvatting, volgorde, uitgelichte afbeelding: alleen als het type
		// het ondersteunt. Anders slaat WordPress het op en toont het nergens.
		if ( isset( $input['excerpt'] ) ) {
			if ( ! post_type_supports( $type, 'excerpt' ) ) {
				$bezwaren[] = sprintf( __( '%s ondersteunt geen samenvatting (excerpt).', 'mcp-abilities-kadence' ), $type );
			}
			$plan['excerpt'] = (string) $input['excerpt'];
		}

		if ( isset( $input['menu_order'] ) ) {
			if ( ! post_type_supports( $type, 'page-attributes' ) && ! $object->hierarchical ) {
				$plan['menu_order_note'] = __( 'dit posttype heeft geen veld Volgorde in de editor; menu_order wordt wel opgeslagen en werkt in een query op menu_order.', 'mcp-abilities-kadence' );
			}
			$plan['menu_order'] = (int) $input['menu_order'];
		}

		if ( isset( $input['featured_image'] ) ) {
			$bijlage = get_post( (int) $input['featured_image'] );

			if ( ! post_type_supports( $type, 'thumbnail' ) ) {
				$bezwaren[] = sprintf( __( '%s ondersteunt geen uitgelichte afbeelding.', 'mcp-abilities-kadence' ), $type );
			} elseif ( ! $bijlage || 'attachment' !== $bijlage->post_type || ! wp_attachment_is_image( $bijlage ) ) {
				$bezwaren[] = sprintf( __( 'Bijlage %d bestaat hier niet of is geen afbeelding. Media-ID\'s verschillen per site: upload het bestand hier en gebruik dat ID.', 'mcp-abilities-kadence' ), (int) $input['featured_image'] );
			} else {
				$plan['featured_image'] = array( 'id' => (int) $bijlage->ID, 'file' => basename( (string) get_attached_file( $bijlage->ID ) ) );
			}
		}

		// Termen: taxonomie moet aan dit type hangen, term moet bestaan.
		$termen = array();

		if ( ! empty( $input['terms'] ) && is_array( $input['terms'] ) ) {
			$eigen = get_object_taxonomies( $type );

			foreach ( $input['terms'] as $taxonomie => $lijst ) {
				if ( ! in_array( $taxonomie, $eigen, true ) ) {
					$bezwaren[] = sprintf(
						/* translators: 1: taxonomy, 2: post type, 3: list. */
						__( 'De taxonomie %1$s hangt niet aan %2$s (wel: %3$s). Een term daarin toont op deze post nergens.', 'mcp-abilities-kadence' ),
						$taxonomie,
						$type,
						$eigen ? implode( ', ', $eigen ) : __( 'geen', 'mcp-abilities-kadence' )
					);
					continue;
				}

				foreach ( (array) $lijst as $term ) {
					$gevonden = is_numeric( $term ) ? get_term( (int) $term, $taxonomie ) : get_term_by( 'slug', (string) $term, $taxonomie );

					if ( ! $gevonden || is_wp_error( $gevonden ) ) {
						$bezwaren[] = sprintf(
							/* translators: 1: term, 2: taxonomy. */
							__( 'Term "%1$s" bestaat niet in %2$s. Term-ID\'s verschillen per site; een slug is hier betrouwbaarder.', 'mcp-abilities-kadence' ),
							(string) $term,
							$taxonomie
						);
						continue;
					}

					$termen[ $taxonomie ][] = (int) $gevonden->term_id;
					$plan['terms'][ $taxonomie ][] = $gevonden->slug;
				}

				// Een expliciet lege lijst betekent "geen termen in deze
				// taxonomie" — ook niet de standaardcategorie die WordPress bij
				// een bericht zelf toekent (Uncategorized).
				if ( array() === (array) $lijst && in_array( $taxonomie, $eigen, true ) ) {
					$termen[ $taxonomie ]         = array();
					$plan['terms'][ $taxonomie ] = array();
				}
			}
		}

		// ACF: alleen velden uit een veldgroep die op dit posttype geldt.
		$acf_velden = array();

		if ( ! empty( $input['acf'] ) && is_array( $input['acf'] ) ) {
			if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'update_field' ) ) {
				$bezwaren[] = __( 'ACF is op deze site niet actief, dus acf kan niet worden opgeslagen.', 'mcp-abilities-kadence' );
			} else {
				$bekend = array();

				foreach ( acf_get_field_groups( array( 'post_type' => $type ) ) as $groep ) {
					foreach ( (array) acf_get_fields( $groep ) as $veld ) {
						if ( ! empty( $veld['name'] ) ) {
							$bekend[ $veld['name'] ] = $veld;
						}
					}
				}

				foreach ( $input['acf'] as $naam => $waarde ) {
					if ( ! isset( $bekend[ $naam ] ) ) {
						$bezwaren[] = sprintf(
							/* translators: 1: field, 2: post type, 3: list. */
							__( 'Het ACF-veld "%1$s" hoort bij geen veldgroep op %2$s. Bekend: %3$s.', 'mcp-abilities-kadence' ),
							$naam,
							$type,
							$bekend ? implode( ', ', array_keys( $bekend ) ) : __( 'geen', 'mcp-abilities-kadence' )
						);
						continue;
					}

					$veld  = $bekend[ $naam ];
					$fout  = self::toets_acf_waarde( $veld, $waarde );

					if ( '' !== $fout ) {
						$bezwaren[] = $fout;
						continue;
					}

					$acf_velden[ $naam ] = array( 'key' => $veld['key'], 'value' => $waarde, 'type' => $veld['type'] );
					$plan['acf'][ $naam ] = $veld['type'];
				}
			}
		}

		return array( $bezwaren, $plan, $termen, $acf_velden );
	}

	/**
	 * Past een waarde bij het type ACF-veld? Alleen wat eenduidig te toetsen
	 * is; de rest laat ACF zelf afhandelen.
	 *
	 * @param array $veld   Het veld.
	 * @param mixed $waarde De waarde.
	 *
	 * @return string Leeg als het past, anders de reden.
	 */
	private static function toets_acf_waarde( $veld, $waarde ) {
		$naam = (string) $veld['name'];

		switch ( $veld['type'] ) {
			case 'text':
			case 'textarea':
			case 'wysiwyg':
			case 'email':
			case 'url':
				return is_scalar( $waarde ) ? '' : sprintf( __( 'ACF-veld "%s" verwacht tekst.', 'mcp-abilities-kadence' ), $naam );

			case 'number':
				return is_numeric( $waarde ) ? '' : sprintf( __( 'ACF-veld "%s" verwacht een getal.', 'mcp-abilities-kadence' ), $naam );

			case 'repeater':
				if ( ! is_array( $waarde ) ) {
					return sprintf( __( 'ACF-repeater "%s" verwacht een lijst rijen.', 'mcp-abilities-kadence' ), $naam );
				}

				$subs = wp_list_pluck( isset( $veld['sub_fields'] ) ? (array) $veld['sub_fields'] : array(), 'name' );

				foreach ( $waarde as $rij ) {
					if ( ! is_array( $rij ) ) {
						return sprintf( __( 'ACF-repeater "%s": elke rij is een object met de namen van de subvelden.', 'mcp-abilities-kadence' ), $naam );
					}

					$vreemd = array_diff( array_keys( $rij ), $subs );

					if ( $vreemd ) {
						return sprintf(
							/* translators: 1: field, 2: unknown, 3: known. */
							__( 'ACF-repeater "%1$s" kent de subvelden %2$s niet (wel: %3$s).', 'mcp-abilities-kadence' ),
							$naam,
							implode( ', ', $vreemd ),
							implode( ', ', $subs )
						);
					}
				}

				return '';

			case 'relationship':
			case 'post_object':
				$ids       = is_array( $waarde ) ? $waarde : array( $waarde );
				$toegestaan = isset( $veld['post_type'] ) ? array_filter( (array) $veld['post_type'] ) : array();

				foreach ( $ids as $id ) {
					$doel = is_numeric( $id ) ? get_post( (int) $id ) : null;

					if ( ! $doel ) {
						return sprintf(
							/* translators: 1: field, 2: ID. */
							__( 'ACF-relatieveld "%1$s": post %2$s bestaat hier niet. Post-ID\'s verschillen per site; maak die post eerst aan en gebruik het nieuwe ID.', 'mcp-abilities-kadence' ),
							$naam,
							(string) $id
						);
					}

					if ( $toegestaan && ! in_array( $doel->post_type, $toegestaan, true ) ) {
						return sprintf(
							/* translators: 1: field, 2: ID, 3: type, 4: allowed. */
							__( 'ACF-relatieveld "%1$s": post %2$d is een %3$s, en het veld staat alleen %4$s toe.', 'mcp-abilities-kadence' ),
							$naam,
							(int) $id,
							$doel->post_type,
							implode( ', ', $toegestaan )
						);
					}
				}

				return '';
		}

		return '';
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
