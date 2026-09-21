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
					'description' => __( 'Voegt blokmarkup toe aan een bestaande post, op een plek die je zelf kiest. Vereist de capability kadence_mcp_write, bewerkrecht op de post, en het token uit generate-section — dat token is gebonden aan deze post, deze exacte markup en de wijzigingsdatum van de post, dus markup die je zelf hebt aangepast komt er niet in. Voor het opslaan wordt gecontroleerd dat elk uniqueID dat al in de post stond er daarna nog steeds is en dat er geen dubbele uniqueIDs ontstaan; klopt dat niet, dan wordt er niets geschreven. Na het opslaan wordt de post teruggelezen. Er wordt een revisie gemaakt, dus terugdraaien kan.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'idempotent'  => false,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
							'markup'  => array(
								'type'        => 'string',
								'description' => __( 'De markup uit generate-section, letterlijk.', 'mcp-abilities-kadence' ),
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
								'description' => __( 'Het token uit generate-section.', 'mcp-abilities-kadence' ),
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

		$aanwezig = array_values( array_filter( explode( ' ', trim( $m[1] ) ) ) );
		$afgeleid = Kadence_MCP_Profielen::klassen( $naam, $attrs );

		$mist     = array();
		$overbodig = array();

		foreach ( $afgeleid as $plaatshouder => $tekst ) {
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
			$regel = isset( Kadence_MCP_Profielen::KLASSENREGELS[ $plaatshouder ] ) ? Kadence_MCP_Profielen::KLASSENREGELS[ $plaatshouder ] : array();

			if ( isset( $regel['soort'] ) && 'richting' === $regel['soort'] ) {
				foreach ( $aanwezig as $klasse ) {
					foreach ( $regel['voorvoegsels'] as $voorvoegsel ) {
						if ( 0 === strpos( $klasse, $voorvoegsel ) && ! in_array( $klasse, $verwacht, true ) ) {
							$overbodig[] = $klasse;
						}
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
