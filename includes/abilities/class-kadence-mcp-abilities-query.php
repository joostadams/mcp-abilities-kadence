<?php
/**
 * Abilities voor de Query Loop.
 *
 * @package Kadence_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query Loops lezen, wijzigen en aanmaken.
 */
class Kadence_MCP_Abilities_Query {

	/**
	 * De definities.
	 *
	 * @return array
	 */
	public static function get_definitions() {
		return array(
			array(
				'name' => 'kadence/describe-query',
				'args' => array(
					'label'       => __( 'Een Query Loop doorlichten', 'mcp-abilities-kadence' ),
					'summary'     => __( 'Wat de query ophaalt, welke filters erin staan, of de facetten kloppen en waar hij gebruikt wordt.', 'mcp-abilities-kadence' ),
					'description' => __( 'Leest een kadence_query volledig uit en zet de drie lagen naast elkaar. EEN: wat de query ophaalt, uit de post meta _kad_query_query — posttype, aantal per pagina, sortering. TWEE: welke filterblokken er in de layout staan. DRIE: de facetten in _kad_query_facets, de afgeleide beschrijving waaruit de voorkant leest welke taxonomie een filter toont. Die derde laag is de valkuil van dit blok: hij staat NIET in de markup en wordt door de editor bij elke wijziging opnieuw berekend uit de blokken. Loopt hij uit de pas, dan staat er een filter op de pagina dat niets doet, zonder enige foutmelding. Deze ability rekent de facetten daarom na op dezelfde manier als de editor en meldt het verschil. Er wordt ook een proefdraai gedaan: de query wordt echt uitgevoerd zodat je ziet hoeveel berichten hij oplevert — de meta heeft geen schema, dus opgeslagen betekent daar niet geldig, en een posttype dat niet bestaat levert simpelweg een lege pagina op.', 'mcp-abilities-kadence' ),
					'readonly'    => true,
					'idempotent'  => true,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'        => 'integer',
								'minimum'     => 1,
								'description' => __( 'Het ID van de kadence_query. Vind je met find-post of in het query-blok op de pagina, waar hij als id in het attribuut staat.', 'mcp-abilities-kadence' ),
							),
							'include_usage' => array(
								'type'        => 'boolean',
								'default'     => false,
								'description' => __( 'Ook opzoeken op welke pagina\'s deze query wordt aangeroepen. Kost een extra zoekopdracht over alle posts.', 'mcp-abilities-kadence' ),
							),
						),
						'required'             => array( 'post_id' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post'     => array( 'type' => 'object' ),
							'query'    => array( 'type' => 'object' ),
							'test_run' => array( 'type' => 'object' ),
							'filters'  => array( 'type' => 'array' ),
							'facets'   => array( 'type' => 'object' ),
							'used_on'  => array( 'type' => 'array' ),
							'status'   => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'describe_query' ),
				),
			),
			array(
				'name' => 'kadence/sync-query-facets',
				'args' => array(
					'label'       => __( 'De facetten van een Query Loop bijwerken', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Rekent de facetten opnieuw uit de blokken en schrijft ze weg.', 'mcp-abilities-kadence' ),
					'description' => __( 'Werkt de post meta _kad_query_facets bij zodat hij weer klopt met de filterblokken in de layout. Doe dit na ELKE wijziging aan de blokken van een kadence_query waarbij een filterblok betrokken is: toevoegen, verwijderen, van type wisselen, of een andere taxonomie kiezen. De editor doet dit vanzelf; schrijf je de blokken via deze MCP, dan gebeurt het niet vanzelf en blijft er een filter achter dat niets doet. De berekening is dezelfde als die van de editor, inclusief de hashfunctie, en de facetten die eruit komen zijn volledig afgeleid — er valt hier niets te kiezen. Kadence pikt de wijziging zelf op: zodra deze meta verandert werkt de indexer de index bij, en dat gebeurt op de achtergrond, dus een filter kan een moment leeg zijn. Twee stappen: eerst zonder token om te zien wat er zou veranderen, daarna met token om te schrijven.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
							'token'   => array(
								'type'        => 'string',
								'description' => __( 'Laat leeg voor een voorstel. Vul het token in dat je terugkrijgt om te schrijven.', 'mcp-abilities-kadence' ),
							),
						),
						'required'             => array( 'post_id' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post'     => array( 'type' => 'object' ),
							'in_sync'  => array( 'type' => 'boolean' ),
							'stored'   => array( 'type' => 'array' ),
							'computed' => array( 'type' => 'array' ),
							'added'    => array( 'type' => 'array' ),
							'removed'  => array( 'type' => 'array' ),
							'written'  => array( 'type' => 'boolean' ),
							'token'    => array( 'type' => 'string' ),
							'status'   => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'sync_query_facets' ),
				),
			),
			array(
				'name' => 'kadence/set-query',
				'args' => array(
					'label'       => __( 'Instellen wat een Query Loop ophaalt', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Wijzigt _kad_query_query: posttype, aantal, sortering, taxonomie.', 'mcp-abilities-kadence' ),
					'description' => __( 'Wijzigt de instellingen van een Query Loop: welk posttype hij ophaalt, hoeveel per pagina, in welke volgorde. Dit staat niet in de blokmarkup maar in de post meta _kad_query_query, en die meta heeft GEEN schema — een verzonnen sleutel wordt even hard opgeslagen als een geldige en daarna genegeerd, en een posttype dat niet bestaat levert een lege pagina op zonder foutmelding. Daarom gebeuren hier twee dingen die elders niet nodig zijn: onbekende sleutels worden geweigerd in plaats van doorgelaten, en er wordt PROEFGEDRAAID — de query wordt echt uitgevoerd, voor en na, zodat je ziet hoeveel berichten hij oplevert. Levert de nieuwe instelling nul berichten op terwijl de oude er wel had, dan wordt dat expliciet gemeld; het wordt niet tegengehouden, want nul kan de bedoeling zijn. De opgegeven sleutels worden samengevoegd met de bestaande instellingen, dus je hoeft alleen te sturen wat verandert. Twee stappen: eerst zonder token, dan met.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id'  => array( 'type' => 'integer', 'minimum' => 1 ),
							'settings' => array(
								'type'                 => 'object',
								'description'          => __( 'De instellingen die moeten veranderen. Bekende sleutels: postType (array van posttypes), perPage, orderBy, order, offset, pages, taxonomy, taxQuery, exclude, parents, search, inherit, infiniteScroll, comparisonLogic. Vraag describe-query op om de huidige waarden te zien.', 'mcp-abilities-kadence' ),
								'additionalProperties' => true,
							),
							'token' => array( 'type' => 'string' ),
						),
						'required'             => array( 'post_id', 'settings' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post'     => array( 'type' => 'object' ),
							'before'   => array( 'type' => 'object' ),
							'after'    => array( 'type' => 'object' ),
							'changed'  => array( 'type' => 'array' ),
							'test_run' => array( 'type' => 'object' ),
							'written'  => array( 'type' => 'boolean' ),
							'token'    => array( 'type' => 'string' ),
							'status'   => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'set_query' ),
				),
			),
			array(
				'name' => 'kadence/create-query',
				'args' => array(
					'label'       => __( 'Een nieuwe Query Loop aanmaken', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Maakt een kadence_query door een bestaande te kopiëren, met verse uniqueIDs en kloppende facetten.', 'mcp-abilities-kadence' ),
					'description' => __( 'Maakt een nieuwe Query Loop door een bestaande te kopiëren. Kopiëren en niet vanaf nul opbouwen, om dezelfde reden als bij duplicate-blocks: de layout komt uit de editor en is dus geldig, en een Query Loop hangt aan een query-card die op zijn beurt een eigen post is — die verwijzing blijft bij een kopie gewoon staan. Wat er gebeurt: de blokken worden overgenomen met VERSE uniqueIDs geprefixt met de nieuwe post, alle _kad_query-instellingen worden meegenomen, en daarna worden de facetten opnieuw berekend. Dat laatste is niet optioneel — de facetten wijzen met een uniqueID naar de filterblokken, en die zijn net allemaal veranderd; zou je ze laten staan, dan wijst elk filter in de nieuwe query naar een blok dat er niet is. Met settings kun je meteen instellen wat de nieuwe query ophaalt. Plaatsen op een pagina doe je daarna zelf: bouw met generate-section een custom boom met één blok kadence/query en het id van de nieuwe query, en voeg dat in met insert-blocks. Twee stappen: eerst zonder token voor een voorstel, daarna met token om echt aan te maken.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'source_id' => array(
								'type'        => 'integer',
								'minimum'     => 1,
								'description' => __( 'De bestaande kadence_query die als basis dient. Vraag list-entities op om te zien welke er zijn.', 'mcp-abilities-kadence' ),
							),
							'title' => array(
								'type'        => 'string',
								'description' => __( 'De titel van de nieuwe query. Dit is de naam waarmee hij in de editor te kiezen is, dus maak hem herkenbaar.', 'mcp-abilities-kadence' ),
							),
							'settings' => array(
								'type'                 => 'object',
								'description'          => __( 'Instellingen die afwijken van de bron, zoals postType of perPage. Wordt samengevoegd met de instellingen van de bron.', 'mcp-abilities-kadence' ),
								'additionalProperties' => true,
							),
							'token' => array( 'type' => 'string' ),
						),
						'required'             => array( 'source_id', 'title' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'source'   => array( 'type' => 'object' ),
							'new_id'   => array( 'type' => 'integer' ),
							'id_map'   => array( 'type' => 'object' ),
							'settings' => array( 'type' => 'object' ),
							'facets'   => array( 'type' => 'array' ),
							'test_run' => array( 'type' => 'object' ),
							'created'  => array( 'type' => 'boolean' ),
							'token'    => array( 'type' => 'string' ),
							'next'     => array( 'type' => 'string' ),
							'status'   => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'create_query' ),
				),
			),
			array(
				'name' => 'kadence/describe-post-type',
				'args' => array(
					'label'       => __( 'Een posttype doorlichten voor een Query Loop', 'mcp-abilities-kadence' ),
					'summary'     => __( 'Taxonomieën, meta-velden en de dynamische velden die je in een Query Card kunt tonen.', 'mcp-abilities-kadence' ),
					'description' => __( 'Geeft alles wat je van een posttype moet weten om er een Query Loop en een Query Card op te bouwen: hoeveel gepubliceerde posts er zijn, welke taxonomieën eraan hangen en hoeveel termen die hebben, welke meta-velden een echte post van dit type draagt, en de vaste velden die Kadence zelf aanbiedt in dynamische blokken. Vraag dit op VOORDAT je een kaart inricht. Een Query Card toont velden met kadence/dynamichtml, en die heeft de meta-sleutel nodig; een verkeerde sleutel levert een leeg blok op zonder enige foutmelding. Let op het verschil met get-post-meta: die geeft bewust alleen sleutels van Kadence terug, wat juist is voor blokwerk maar onbruikbaar zodra je een Loop over een ander posttype bouwt — de velden die je dan nodig hebt komen van een heel andere plug-in. De waarden hier zijn afgekapt: dit is bedoeld om te zien WELK veld je nodig hebt, niet om inhoud uit te lezen. Bij source acf hoort het veld bij Advanced Custom Fields; in beide gevallen spreek je het aan met field post|post_custom_field, para kb_custom_input en custom gelijk aan de sleutel.', 'mcp-abilities-kadence' ),
					'readonly'    => true,
					'idempotent'  => true,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_type' => array(
								'type'        => 'string',
								'description' => __( 'De naam van het posttype, bijvoorbeeld post of een eigen type. Noem je er een die niet bestaat, dan krijg je de lijst met bestaande terug.', 'mcp-abilities-kadence' ),
							),
							'sample_id' => array(
								'type'        => 'integer',
								'minimum'     => 1,
								'description' => __( 'Lees de velden van deze post in plaats van de nieuwste. Handig als niet elke post alle velden gevuld heeft.', 'mcp-abilities-kadence' ),
							),
						),
						'required'             => array( 'post_type' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_type'   => array( 'type' => 'object' ),
							'taxonomies'  => array( 'type' => 'array' ),
							'sample'      => array( 'type' => 'object' ),
							'meta_fields' => array( 'type' => 'array' ),
							'dynamic'     => array( 'type' => 'object' ),
							'status'      => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'describe_post_type' ),
				),
			),
			array(
				'name' => 'kadence/create-query-card',
				'args' => array(
					'label'       => __( 'Een nieuwe Query Card aanmaken', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Kopieert een bestaande kaart met verse uniqueIDs, klaar om aan te passen.', 'mcp-abilities-kadence' ),
					'description' => __( 'Maakt een nieuwe Query Card door een bestaande te kopiëren. Een Query Card is de opmaak van ÉÉN resultaat in een Query Loop en is een eigen post van het type kadence_query_card; de Loop verwijst ernaar met een id in het blok kadence/query-card. Wil je een Loop over een ander posttype, dan heb je bijna altijd ook een eigen kaart nodig, want de velden verschillen. Kopiëren en niet vanaf nul opbouwen: een kaart hangt vol dynamische blokken — kadence/dynamichtml voor een veld, kadence/dynamiclist voor taxonomietermen, een knop met een kb-dynamic-shortcode als link — en die verhoudingen klop je makkelijker bij dan dat je ze verzint. Geef for_post_type mee zodat de kaart in de editor een voorbeeld van het juiste soort post toont; staat dat verkeerd, dan lijkt elk dynamisch veld leeg terwijl er niets mis is. Wat je erna aanpast doe je met set-attributes en replace-block, en describe-post-type laat zien welke velden er te tonen zijn. Twee stappen: eerst zonder token voor een voorstel, daarna met token.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'source_id' => array(
								'type'        => 'integer',
								'minimum'     => 1,
								'description' => __( 'De bestaande kaart die als basis dient. Kies er een die qua opbouw het dichtst bij je doel ligt.', 'mcp-abilities-kadence' ),
							),
							'title' => array(
								'type'        => 'string',
								'description' => __( 'De naam van de nieuwe kaart, zoals je hem in de editor wil terugvinden.', 'mcp-abilities-kadence' ),
							),
							'for_post_type' => array(
								'type'        => 'string',
								'description' => __( 'Het posttype waarvoor deze kaart bedoeld is. Zet het voorbeeld in de editor goed; weglaten neemt dat van de bron over.', 'mcp-abilities-kadence' ),
							),
							'token' => array( 'type' => 'string' ),
						),
						'required'             => array( 'source_id', 'title' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'source'  => array( 'type' => 'object' ),
							'title'   => array( 'type' => 'string' ),
							'new_id'  => array( 'type' => 'integer' ),
							'id_map'  => array( 'type' => 'object' ),
							'created' => array( 'type' => 'boolean' ),
							'token'   => array( 'type' => 'string' ),
							'next'    => array( 'type' => 'string' ),
							'status'  => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'create_query_card' ),
				),
			),
			array(
				'name' => 'kadence/set-card-layout',
				'args' => array(
					'label'       => __( 'De opmaak van een Query Card wijzigen', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Zet het aantal kolommen en de tussenruimte van een Query Card.', 'mcp-abilities-kadence' ),
					'description' => __( 'Wijzigt de RASTERINSTELLINGEN van een Query Card. Die staan niet in de blokmarkup maar in post meta op de kaart zelf, en daar komt geen enkele andere ability bij: set-attributes en style-blocks raken alleen blokken aan. Zonder deze ability staat een gekopieerde kaart dus vast op het aantal kolommen van zijn bron, en dat is precies waar een Query Loop over een ander posttype op stukloopt — de kaart werkt, maar alles staat onder elkaar. Let op het verschil met de kaartinhoud: hoe een los resultaat eruitziet regel je met set-attributes en replace-block op de blokken IN de kaart; hoeveel resultaten er naast elkaar staan regel je hier. Twee stappen: eerst zonder token voor een voorstel met de oude en nieuwe waarden naast elkaar, daarna opnieuw met dat token om te schrijven. Er wordt teruggelezen na het schrijven.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'        => 'integer',
								'minimum'     => 1,
								'description' => __( 'Het ID van de kadence_query_card. Dat is het id-attribuut van het blok kadence/query-card in de Query Loop, niet de pagina waar de Loop op staat.', 'mcp-abilities-kadence' ),
							),
							'layout' => array(
								'type'                 => 'object',
								'additionalProperties' => true,
								'description'          => __( 'Wat er moet veranderen. Bekende sleutels: columns, columnGap, rowGap, maxWidth en preview_post_type. Alle vier de eerste zijn responsive en verwachten een array van drie: [desktop, tablet, mobiel]; een lege string betekent "neem over van de grotere weergave".', 'mcp-abilities-kadence' ),
							),
							'token' => array(
								'type'        => 'string',
								'description' => __( 'Laat leeg voor een voorstel zonder te schrijven. Vul het token in dat je dan terugkrijgt om het echt te doen.', 'mcp-abilities-kadence' ),
							),
						),
						'required'             => array( 'post_id', 'layout' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post'      => array( 'type' => 'object' ),
							'before'    => array( 'type' => 'object' ),
							'after'     => array( 'type' => 'object' ),
							'changed'   => array( 'type' => 'array' ),
							'used_by'   => array( 'type' => 'array' ),
							'written'   => array( 'type' => 'boolean' ),
							'token'     => array( 'type' => 'string' ),
							'status'    => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'set_card_layout' ),
				),
			),
			array(
				'name' => 'kadence/set-entity-meta',
				'args' => array(
					'label'       => __( 'Een instelling van een Kadence-entiteit wijzigen', 'mcp-abilities-kadence' ),
					'summary'     => __( 'SCHRIJFACTIE. Schrijft een _kad_-instelling op een Kadence-post.', 'mcp-abilities-kadence' ),
					'description' => __( 'Wijzigt de INSTELLINGEN van een Kadence-entiteit: een navigatie, een element, een header, een query of een query card. Die staan niet in de blokmarkup maar in post meta met de prefix _kad_, en geen enkele blok-ability komt daarbij. Dat is het verschil tussen "wat er in het menu staat" (blokken, dus set-attributes) en "hoe het menu eruitziet" (meta, dus hier): de linkkleur van een navigatie, de breedte van een dropdown, en bij een element de plaatsing — welke hook, op welke pagina\'s wel en op welke niet. Lees eerst get-post-meta: een sleutel die daar niet in staat wordt geweigerd, want Kadence schrijft bij het opslaan zijn hele set weg, en een sleutel die ontbreekt is dus een typefout of een instelling die deze entiteit niet kent. Zo een sleutel zou stil opgeslagen worden en daarna genegeerd, en dat ziet eruit alsof het gelukt is. LET OP: post meta kent geen revisies. Terugdraaien kan alleen met de waarden uit het veld before van het antwoord, dus bewaar die. Twee stappen: eerst zonder token voor een voorstel met oud en nieuw naast elkaar, daarna opnieuw met dat token om te schrijven. Er wordt teruggelezen.', 'mcp-abilities-kadence' ),
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post_id' => array(
								'type'        => 'integer',
								'minimum'     => 1,
								'description' => __( 'Het ID van de Kadence-post: de kadence_navigation, kadence_element, kadence_header, kadence_query of kadence_query_card zelf, niet de pagina waar je hem ziet.', 'mcp-abilities-kadence' ),
							),
							'meta' => array(
								'type'                 => 'object',
								'additionalProperties' => true,
								'description'          => __( 'De sleutels die moeten wijzigen, met hun nieuwe waarde. Sleutels zonder de prefix _kad_ worden geweigerd, en sleutels die nog niet op deze post bestaan ook. Neem de vorm van de waarde letterlijk over van get-post-meta: een kleur is "palette9" of een rgba-string, een spacing is een array van vier, en een responsive waarde een array van drie.', 'mcp-abilities-kadence' ),
							),
							'token' => array(
								'type'        => 'string',
								'description' => __( 'Laat leeg voor een voorstel zonder te schrijven. Vul het token in dat je dan terugkrijgt om het echt te doen.', 'mcp-abilities-kadence' ),
							),
						),
						'required'             => array( 'post_id', 'meta' ),
						'additionalProperties' => false,
					),
					'output_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'post'    => array( 'type' => 'object' ),
							'before'  => array( 'type' => 'object' ),
							'after'   => array( 'type' => 'object' ),
							'changed' => array( 'type' => 'array' ),
							'notes'   => array( 'type' => 'array' ),
							'written' => array( 'type' => 'boolean' ),
							'token'   => array( 'type' => 'string' ),
							'status'  => array( 'type' => 'string' ),
						),
					),
					'execute_callback' => array( __CLASS__, 'set_entity_meta' ),
				),
			),
		);
	}

	/**
	 * Haal een kadence_query op.
	 *
	 * @param int $post_id Het ID.
	 *
	 * @return WP_Post|WP_Error
	 */
	private static function query_post( $post_id ) {
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

		if ( 'kadence_query' !== $post->post_type ) {
			return new WP_Error(
				'kadence_mcp_not_a_query',
				sprintf(
					/* translators: 1: post ID, 2: post type. */
					__( 'Post %1$d is een %2$s, geen kadence_query. De instellingen van een Query Loop staan niet op de pagina waar je hem ziet maar in de query-post zelf; die vind je in het attribuut id van het blok kadence/query.', 'mcp-abilities-kadence' ),
					$post->ID,
					$post->post_type
				)
			);
		}

		return $post;
	}

	/**
	 * De schrijfgrendels, in één keer.
	 *
	 * @param WP_Post $post De post.
	 *
	 * @return true|WP_Error
	 */
	private static function mag_schrijven( $post ) {
		if ( ! Kadence_MCP_Capabilities::current_user_can( Kadence_MCP_Capabilities::WRITE ) ) {
			return new WP_Error(
				'kadence_mcp_write_denied',
				__( 'Je hebt de capability kadence_mcp_write niet. Die wordt bij installatie aan niemand gegeven en moet bewust worden toegekend.', 'mcp-abilities-kadence' ),
				array( 'status' => 403 )
			);
		}

		if ( $post && ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error(
				'kadence_mcp_edit_denied',
				__( 'Je mag deze post volgens WordPress zelf niet bewerken.', 'mcp-abilities-kadence' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Schrijf instellingen naar een Kadence-entiteit.
	 *
	 * De tegenhanger van style-blocks voor alles wat NIET in de blokmarkup zit.
	 * De opmaak van een navigatie staat in post meta op de navigatie-post, niet
	 * op de blokken erin; hetzelfde geldt voor de plaatsing van een element. Tot
	 * 1.17.0 was dat alleen in de editor te doen, en dat betekende dat een menu
	 * dat op een zwarte achtergrond terechtkwam zwart op zwart bleef staan tot
	 * iemand het met de hand kwam repareren.
	 *
	 * Twee poorten, allebei bewust streng:
	 *
	 * 1. Alleen posttypes van Kadence. Deze ability is geen algemene
	 *    metaschrijver; met een willekeurige post erbij zou hij dat wel zijn.
	 * 2. Alleen sleutels met de prefix _kad_ die AL op deze post staan. Kadence
	 *    schrijft bij elke opslag zijn volledige set weg, dus een ontbrekende
	 *    sleutel is een typefout of een instelling die dit posttype niet kent.
	 *    Zonder deze controle zou zo een sleutel gewoon opgeslagen worden en
	 *    daarna genegeerd — het ziet er dan uit alsof het gelukt is, en dat is
	 *    de vervelendste soort fout.
	 *
	 * Er is geen revisie: WordPress bewaart post meta niet in revisies. Daarom
	 * staat de oude waarde in het antwoord en zegt de status dat expliciet.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function set_entity_meta( $input = array() ) {
		$post = get_post( isset( $input['post_id'] ) ? (int) $input['post_id'] : 0 );

		if ( ! $post ) {
			return new WP_Error(
				'kadence_mcp_post_not_found',
				sprintf(
					/* translators: %d: post ID. */
					__( 'Post %d bestaat niet.', 'mcp-abilities-kadence' ),
					isset( $input['post_id'] ) ? (int) $input['post_id'] : 0
				)
			);
		}

		if ( 0 !== strpos( (string) $post->post_type, 'kadence_' ) ) {
			return new WP_Error(
				'kadence_mcp_not_a_kadence_entity',
				sprintf(
					/* translators: 1: post ID, 2: post type. */
					__( 'Post %1$d is een %2$s. Deze ability schrijft alleen op posttypes van Kadence; hij is bewust geen algemene metaschrijver.', 'mcp-abilities-kadence' ),
					$post->ID,
					$post->post_type
				)
			);
		}

		$grendel = self::mag_schrijven( $post );

		if ( is_wp_error( $grendel ) ) {
			return $grendel;
		}

		$voorstel = isset( $input['meta'] ) && is_array( $input['meta'] ) ? $input['meta'] : array();

		if ( empty( $voorstel ) ) {
			return new WP_Error( 'kadence_mcp_no_meta', __( 'Geef in meta op welke instellingen moeten wijzigen.', 'mcp-abilities-kadence' ) );
		}

		$fout = array();

		foreach ( array_keys( $voorstel ) as $sleutel ) {
			if ( 0 !== strpos( (string) $sleutel, '_kad_' ) ) {
				$fout[] = sprintf(
					/* translators: %s: meta key. */
					__( '"%s" heeft niet de prefix _kad_ en is dus geen instelling van Kadence.', 'mcp-abilities-kadence' ),
					$sleutel
				);
				continue;
			}

			if ( ! metadata_exists( 'post', $post->ID, $sleutel ) ) {
				$fout[] = sprintf(
					/* translators: 1: meta key, 2: post ID. */
					__( '"%1$s" staat nog niet op post %2$d. Kadence schrijft bij het opslaan zijn hele set weg, dus een sleutel die ontbreekt kent dit posttype niet — hij zou opgeslagen worden en daarna genegeerd. Controleer de schrijfwijze met get-post-meta.', 'mcp-abilities-kadence' ),
					$sleutel,
					$post->ID
				);
			}
		}

		if ( ! empty( $fout ) ) {
			return new WP_Error( 'kadence_mcp_bad_meta_key', implode( ' ', $fout ) );
		}

		$voor      = array();
		$na        = array();
		$gewijzigd = array();

		foreach ( $voorstel as $sleutel => $waarde ) {
			$oud             = get_post_meta( $post->ID, $sleutel, true );
			$voor[ $sleutel ] = $oud;
			$na[ $sleutel ]   = $waarde;

			if ( wp_json_encode( $oud ) === wp_json_encode( $waarde ) ) {
				continue;
			}

			$gewijzigd[] = array(
				'key'  => $sleutel,
				'from' => $oud,
				'to'   => $waarde,
			);
		}

		$rapport = array(
			'post'    => array(
				'id'    => (int) $post->ID,
				'title' => (string) $post->post_title,
				'type'  => (string) $post->post_type,
			),
			'before'  => (object) $voor,
			'after'   => (object) $na,
			'changed' => $gewijzigd,
			'notes'   => self::entiteitmeta_notities( $post, $voorstel ),
		);

		$verwacht = Kadence_MCP_Inventory::schrijf_token( $post, 'entiteitmeta', $voorstel );
		$token    = isset( $input['token'] ) ? (string) $input['token'] : '';

		if ( '' === $token ) {
			return array_merge(
				$rapport,
				array(
					'written' => false,
					'token'   => $verwacht,
					'status'  => empty( $gewijzigd )
						? __( 'Voorstel, er is NIETS opgeslagen — en er zou ook niets veranderen: deze waarden staan er al.', 'mcp-abilities-kadence' )
						: sprintf(
							/* translators: %d: number of changes. */
							__( 'Voorstel, er is NIETS opgeslagen. Er zouden %d instellingen wijzigen. Post meta kent geen revisies, dus bewaar het veld before voordat je doorgaat. Roep opnieuw aan met het token om te schrijven.', 'mcp-abilities-kadence' ),
							count( $gewijzigd )
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

		foreach ( $voorstel as $sleutel => $waarde ) {
			update_post_meta( $post->ID, $sleutel, $waarde );
		}

		clean_post_cache( $post->ID );

		// Teruglezen: opgeslagen is niet hetzelfde als opgeslagen zoals bedoeld.
		$controle  = array();
		$afwijking = array();

		foreach ( $voorstel as $sleutel => $waarde ) {
			$controle[ $sleutel ] = get_post_meta( $post->ID, $sleutel, true );

			if ( wp_json_encode( $controle[ $sleutel ] ) !== wp_json_encode( $waarde ) ) {
				$afwijking[] = $sleutel;
			}
		}

		return array_merge(
			$rapport,
			array(
				'after'   => (object) $controle,
				'written' => empty( $afwijking ),
				'token'   => '',
				'status'  => empty( $afwijking )
					? __( 'geschreven en teruggelezen: wat er staat komt overeen met wat er bedoeld was. Er is GEEN revisie — post meta kent die niet. Terugdraaien kan alleen met de waarden uit before.', 'mcp-abilities-kadence' )
					: sprintf(
						/* translators: %s: the keys that differ. */
						__( 'LET OP: er is geschreven, maar bij het teruglezen wijken deze sleutels af van wat er verstuurd is: %s. Waarschijnlijk heeft een filter of een sanitizer ingegrepen. Controleer het veld after.', 'mcp-abilities-kadence' ),
						implode( ', ', $afwijking )
					),
			)
		);
	}

	/**
	 * Wat een meta-waarde anders doet dan hij lijkt.
	 *
	 * Een navigatie rekent spacing standaard in em (_kad_navigation_spacingUnit
	 * heeft als default "em"). Wie 20 bedoelt als pixels krijgt bij een
	 * lettergrootte van 17 px 340 px tussen de items. Gemeten op 23-09-2026.
	 *
	 * @param WP_Post $post     De entiteit.
	 * @param array   $voorstel De voorgenomen meta.
	 *
	 * @return array
	 */
	private static function entiteitmeta_notities( $post, $voorstel ) {
		$notities = array();

		if ( 'kadence_navigation' !== $post->post_type ) {
			return $notities;
		}

		$eenheid = array_key_exists( '_kad_navigation_spacingUnit', $voorstel )
			? (string) $voorstel['_kad_navigation_spacingUnit']
			: (string) get_post_meta( $post->ID, '_kad_navigation_spacingUnit', true );

		if ( '' === $eenheid ) {
			$eenheid = 'em';
		}

		foreach ( array( '_kad_navigation_spacing', '_kad_navigation_spacingTablet', '_kad_navigation_spacingMobile' ) as $sleutel ) {
			if ( ! isset( $voorstel[ $sleutel ] ) || ! is_array( $voorstel[ $sleutel ] ) ) {
				continue;
			}

			$getallen = array_filter( $voorstel[ $sleutel ], 'is_numeric' );

			if ( ! empty( $getallen ) && 'em' === $eenheid ) {
				$notities[] = sprintf(
					/* translators: %s: meta key. */
					__( '%s wordt gerekend in em, want _kad_navigation_spacingUnit staat op em (de standaard van Kadence). 20 wordt dan 20em, bij 17 px letters 340 px. Bedoel je pixels, zet dan _kad_navigation_spacingUnit mee op "px". De volgorde is [rij-gap, kolom-gap, …].', 'mcp-abilities-kadence' ),
					$sleutel
				);
			}
		}

		return $notities;
	}

	/**
	 * Doorlicht een Query Loop.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function describe_query( $input = array() ) {
		$post = self::query_post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$instellingen = Kadence_MCP_Query::instellingen( $post->ID );
		$stand        = Kadence_MCP_Query::facetstand( $post->ID );

		if ( is_wp_error( $stand ) ) {
			return $stand;
		}

		// De filterblokken zelf, zodat je ziet welk uniqueID bij welk filter
		// hoort zonder de hele boom op te vragen.
		$filters = self::filterblokken( parse_blocks( $post->post_content ) );

		$proef = Kadence_MCP_Query::proefdraai( $instellingen );

		$meldingen = array();
		$bezwaren  = Kadence_MCP_Query::facetcontrole( $stand['computed'], $instellingen );

		foreach ( $bezwaren as $bezwaar ) {
			$meldingen[] = $bezwaar['uitleg'];
		}

		if ( ! $stand['in_sync'] ) {
			$meldingen[] = __( 'DE FACETTEN LOPEN UIT DE PAS met de filterblokken. Er staat dus minstens één filter op de pagina dat niets doet. Herstellen met sync-query-facets.', 'mcp-abilities-kadence' );
		}

		if ( ! empty( $proef['unknown_post_types'] ) ) {
			$meldingen[] = sprintf(
				/* translators: %s: comma separated post types. */
				__( 'Deze query vraagt om posttypes die niet bestaan: %s. Dat levert een lege lijst op.', 'mcp-abilities-kadence' ),
				implode( ', ', $proef['unknown_post_types'] )
			);
		}

		if ( 0 === $proef['found'] ) {
			$meldingen[] = __( 'De query levert nul berichten op.', 'mcp-abilities-kadence' );
		}

		return array(
			'post'     => array( 'id' => $post->ID, 'title' => get_the_title( $post ), 'type' => $post->post_type ),
			'query'    => (object) $instellingen,
			'test_run' => (object) $proef,
			'filters'  => $filters,
			'facets'   => (object) array(
				'in_sync'  => $stand['in_sync'],
				'stored'   => $stand['stored'],
				'computed' => $stand['computed'],
				// Kloppen met de blokken is niet hetzelfde als iets kunnen
				// tonen. Een filter kan volstrekt geldig zijn en leeg blijven.
				'problems' => $bezwaren,
			),
			'used_on'  => empty( $input['include_usage'] ) ? array() : Kadence_MCP_Query::gebruikt_in( $post->ID ),
			'status'   => empty( $meldingen )
				? sprintf(
					/* translators: 1: number of filters, 2: number of posts. */
					__( 'De drie lagen kloppen met elkaar. %1$d filterblokken, %2$d berichten in de proefdraai.', 'mcp-abilities-kadence' ),
					count( $filters ),
					(int) $proef['found']
				)
				: implode( ' ', $meldingen ),
		);
	}

	/**
	 * Werk de facetten bij.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function sync_query_facets( $input = array() ) {
		$post = self::query_post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$stand = Kadence_MCP_Query::facetstand( $post->ID );

		if ( is_wp_error( $stand ) ) {
			return $stand;
		}

		$rapport = array(
			'post'     => array( 'id' => $post->ID, 'title' => get_the_title( $post ) ),
			'in_sync'  => $stand['in_sync'],
			'stored'   => $stand['stored'],
			'computed' => $stand['computed'],
			'added'    => $stand['toegevoegd'],
			'removed'  => $stand['verdwenen'],
		);

		if ( $stand['in_sync'] ) {
			return array_merge(
				$rapport,
				array(
					'written' => false,
					'token'   => '',
					'status'  => __( 'De facetten kloppen al met de blokken. Er valt niets bij te werken.', 'mcp-abilities-kadence' ),
				)
			);
		}

		$grondslag = Kadence_MCP_Inventory::schrijf_token( $post, '__facets__', array( 'computed' => $stand['computed'] ) );
		$token     = isset( $input['token'] ) ? (string) $input['token'] : '';

		if ( '' === $token ) {
			return array_merge(
				$rapport,
				array(
					'written' => false,
					'token'   => $grondslag,
					'status'  => __( 'Voorstel, er is NIETS opgeslagen. Vergelijk stored met computed en roep opnieuw aan met het token om te schrijven.', 'mcp-abilities-kadence' ),
				)
			);
		}

		$mag = self::mag_schrijven( $post );

		if ( is_wp_error( $mag ) ) {
			return $mag;
		}

		if ( ! hash_equals( $grondslag, $token ) ) {
			return new WP_Error( 'kadence_mcp_invalid_token', Kadence_MCP_Inventory::token_reden( $token, $grondslag, $post ) );
		}

		$resultaat = Kadence_MCP_Query::schrijf_facetten( $post->ID );

		if ( is_wp_error( $resultaat ) ) {
			return $resultaat;
		}

		return array_merge(
			$rapport,
			array(
				// De stand NA het schrijven. Stond hier de stand van ervoor,
				// dan meldde het veld in_sync false terwijl de status zei dat
				// alles klopte — een rapport dat zichzelf tegenspreekt.
				'in_sync'  => ! empty( $resultaat['in_sync'] ),
				'computed' => $resultaat['computed'],
				'stored'   => $resultaat['stored'],
				'written'  => true,
				'token'    => '',
				'status'   => $resultaat['in_sync']
					? __( 'Bijgewerkt en teruggelezen: de facetten kloppen nu met de blokken. Kadence vult de index op de achtergrond bij, dus een filter kan nog even leeg zijn.', 'mcp-abilities-kadence' )
					: __( 'LET OP: er is geschreven, maar bij het teruglezen komen de facetten nog steeds niet overeen. Controleer de query.', 'mcp-abilities-kadence' ),
			)
		);
	}

	/**
	 * Wijzig wat de query ophaalt.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function set_query( $input = array() ) {
		$post = self::query_post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$voorstel = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();

		if ( empty( $voorstel ) ) {
			return new WP_Error( 'kadence_mcp_no_settings', __( 'Geef op wat er moet veranderen in settings.', 'mcp-abilities-kadence' ) );
		}

		// Deze meta heeft geen schema, dus niemand houdt een onbekende sleutel
		// tegen. Hier wel: opgeslagen en genegeerd is de slechtste uitkomst.
		$onbekend = array_diff( array_keys( $voorstel ), array_keys( Kadence_MCP_Query::QUERY_SLEUTELS ) );

		if ( ! empty( $onbekend ) ) {
			return new WP_Error(
				'kadence_mcp_unknown_query_key',
				sprintf(
					/* translators: 1: unknown keys, 2: known keys. */
					__( 'Onbekende sleutels: %1$s. Deze meta heeft geen schema, dus zo een sleutel zou gewoon opgeslagen worden en daarna genegeerd. Bekend zijn: %2$s.', 'mcp-abilities-kadence' ),
					implode( ', ', $onbekend ),
					implode( ', ', array_keys( Kadence_MCP_Query::QUERY_SLEUTELS ) )
				)
			);
		}

		$voor = Kadence_MCP_Query::instellingen( $post->ID );
		$na   = array_merge( $voor, $voorstel );

		$gewijzigd = array();

		foreach ( $voorstel as $sleutel => $waarde ) {
			$oud = isset( $voor[ $sleutel ] ) ? $voor[ $sleutel ] : null;

			if ( wp_json_encode( $oud ) === wp_json_encode( $waarde ) ) {
				continue;
			}

			$gewijzigd[] = array(
				'key'  => $sleutel,
				'from' => $oud,
				'to'   => $waarde,
			);
		}

		$proef_voor = Kadence_MCP_Query::proefdraai( $voor );
		$proef_na   = Kadence_MCP_Query::proefdraai( $na );

		$rapport = array(
			'post'    => array( 'id' => $post->ID, 'title' => get_the_title( $post ) ),
			'before'  => (object) $voor,
			'after'   => (object) $na,
			'changed' => $gewijzigd,
			'test_run' => (object) array(
				'before' => $proef_voor,
				'after'  => $proef_na,
			),
		);

		$waarschuwing = '';

		if ( ! empty( $proef_na['unknown_post_types'] ) ) {
			$waarschuwing = sprintf(
				/* translators: %s: comma separated post types. */
				__( ' LET OP: deze posttypes bestaan niet: %s.', 'mcp-abilities-kadence' ),
				implode( ', ', $proef_na['unknown_post_types'] )
			);
		} elseif ( 0 === $proef_na['found'] && $proef_voor['found'] > 0 ) {
			$waarschuwing = sprintf(
				/* translators: %d: number of posts before. */
				__( ' LET OP: na deze wijziging levert de query nul berichten op, waar hij er nu %d had. Dat kan de bedoeling zijn, maar het wordt niet tegengehouden.', 'mcp-abilities-kadence' ),
				(int) $proef_voor['found']
			);
		}

		if ( empty( $gewijzigd ) ) {
			return array_merge(
				$rapport,
				array(
					'written' => false,
					'token'   => '',
					'status'  => __( 'Deze instellingen staan al zo. Er valt niets te wijzigen.', 'mcp-abilities-kadence' ),
				)
			);
		}

		$grondslag = Kadence_MCP_Inventory::schrijf_token( $post, '__query__', $na );
		$token     = isset( $input['token'] ) ? (string) $input['token'] : '';

		if ( '' === $token ) {
			return array_merge(
				$rapport,
				array(
					'written' => false,
					'token'   => $grondslag,
					'status'  => __( 'Voorstel, er is NIETS opgeslagen. De proefdraai laat zien wat de query nu en straks oplevert; roep opnieuw aan met het token om te schrijven.', 'mcp-abilities-kadence' ) . $waarschuwing,
				)
			);
		}

		$mag = self::mag_schrijven( $post );

		if ( is_wp_error( $mag ) ) {
			return $mag;
		}

		if ( ! hash_equals( $grondslag, $token ) ) {
			return new WP_Error( 'kadence_mcp_invalid_token', Kadence_MCP_Inventory::token_reden( $token, $grondslag, $post ) );
		}

		update_post_meta( $post->ID, '_kad_query_query', $na );

		$terug = Kadence_MCP_Query::instellingen( $post->ID );
		$klopt = wp_json_encode( $terug ) === wp_json_encode( $na );

		return array_merge(
			$rapport,
			array(
				'after'   => (object) $terug,
				'written' => true,
				'token'   => '',
				'status'  => ( $klopt
					? __( 'Geschreven en teruggelezen: de instellingen staan zoals bedoeld.', 'mcp-abilities-kadence' )
					: __( 'LET OP: er is geschreven maar het teruglezen wijkt af. Controleer de query.', 'mcp-abilities-kadence' ) ) . $waarschuwing,
			)
		);
	}

	/**
	 * Maak een nieuwe Query Loop.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function create_query( $input = array() ) {
		$bron = self::query_post( isset( $input['source_id'] ) ? $input['source_id'] : 0 );

		if ( is_wp_error( $bron ) ) {
			return $bron;
		}

		$titel = isset( $input['title'] ) ? trim( wp_strip_all_tags( (string) $input['title'] ) ) : '';

		if ( '' === $titel ) {
			return new WP_Error( 'kadence_mcp_no_title', __( 'Geef de nieuwe query een titel. Dat is de naam waarmee hij in de editor te kiezen is.', 'mcp-abilities-kadence' ) );
		}

		$voorstel = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();
		$onbekend = array_diff( array_keys( $voorstel ), array_keys( Kadence_MCP_Query::QUERY_SLEUTELS ) );

		if ( ! empty( $onbekend ) ) {
			return new WP_Error(
				'kadence_mcp_unknown_query_key',
				sprintf(
					/* translators: 1: unknown keys, 2: known keys. */
					__( 'Onbekende sleutels in settings: %1$s. Bekend zijn: %2$s.', 'mcp-abilities-kadence' ),
					implode( ', ', $onbekend ),
					implode( ', ', array_keys( Kadence_MCP_Query::QUERY_SLEUTELS ) )
				)
			);
		}

		$instellingen = array_merge( Kadence_MCP_Query::instellingen( $bron->ID ), $voorstel );
		$proef        = Kadence_MCP_Query::proefdraai( $instellingen );
		$token        = isset( $input['token'] ) ? (string) $input['token'] : '';

		$rapport = array(
			'source'   => array( 'id' => $bron->ID, 'title' => get_the_title( $bron ) ),
			'settings' => (object) $instellingen,
			'test_run' => (object) $proef,
		);

		// Het token dekt de bron, de titel en de instellingen. De uniqueIDs
		// worden pas bij het schrijven uitgedeeld en kunnen dus niet mee — dat
		// hoeft ook niet: ze bestaan nog nergens en er kan niets naar verwijzen.
		$grondslag = Kadence_MCP_Inventory::schrijf_token( $bron, '__create__', array( 'title' => $titel, 'settings' => $instellingen ) );

		if ( '' === $token ) {
			return array_merge(
				$rapport,
				array(
					'new_id'  => 0,
					'id_map'  => (object) array(),
					'facets'  => array(),
					'created' => false,
					'token'   => $grondslag,
					'next'    => '',
					'status'  => sprintf(
						/* translators: 1: title, 2: source title, 3: number of posts. */
						__( 'Voorstel, er is NIETS aangemaakt. Er zou een query "%1$s" komen met de layout van "%2$s"; de proefdraai levert %3$d berichten op. Roep opnieuw aan met het token om hem echt te maken.', 'mcp-abilities-kadence' ),
						$titel,
						get_the_title( $bron ),
						(int) $proef['found']
					),
				)
			);
		}

		$mag = self::mag_schrijven( null );

		if ( is_wp_error( $mag ) ) {
			return $mag;
		}

		// NIET edit_posts. Een kadence_query heeft eigen capabilities
		// (capability_type array('kadence_query','kadence_queries') met
		// map_meta_cap, query-cpt.php:149), en Kadence spiegelt
		// edit_kadence_queries op edit_others_pages. Iemand die pagina's mag
		// bewerken maar geen berichten heeft dus wél het recht om een query te
		// maken, en zou op edit_posts onterecht zijn afgewezen. Het posttype
		// zelf vragen in plaats van een capability te raden.
		$type_object = get_post_type_object( 'kadence_query' );
		$mag_maken   = ( $type_object && isset( $type_object->cap->create_posts ) )
			? (string) $type_object->cap->create_posts
			: 'edit_posts';

		if ( ! current_user_can( $mag_maken ) ) {
			return new WP_Error(
				'kadence_mcp_create_denied',
				sprintf(
					/* translators: %s: capability name. */
					__( 'Je mist de capability "%s", die nodig is om een Query Loop aan te maken.', 'mcp-abilities-kadence' ),
					$mag_maken
				),
				array( 'status' => 403 )
			);
		}

		if ( ! hash_equals( $grondslag, $token ) ) {
			return new WP_Error( 'kadence_mcp_invalid_token', Kadence_MCP_Inventory::token_reden( $token, $grondslag, $bron ) );
		}

		// Eerst leeg aanmaken, want de nieuwe uniqueIDs worden geprefixt met
		// het post-ID en dat bestaat pas na het invoegen.
		$nieuw_id = wp_insert_post(
			array(
				'post_type'    => 'kadence_query',
				'post_status'  => 'publish',
				'post_title'   => $titel,
				'post_content' => '',
			),
			true
		);

		if ( is_wp_error( $nieuw_id ) ) {
			return $nieuw_id;
		}

		$boom  = Kadence_MCP_Inventory::schoon_blokken( parse_blocks( $bron->post_content ) );
		$oude  = Kadence_MCP_Inventory::verzamel_unique_ids( $boom );
		$kaart = array();
		$bezet = array();

		foreach ( array_keys( $oude ) as $oud_id ) {
			$vers = Kadence_MCP_Inventory::nieuwe_unique_id( $nieuw_id, $bezet );

			if ( '' === $vers ) {
				wp_delete_post( $nieuw_id, true );

				return new WP_Error( 'kadence_mcp_no_unique_id', __( 'Er kon geen vrij uniqueID gemaakt worden. De nieuwe query is weer verwijderd.', 'mcp-abilities-kadence' ) );
			}

			$bezet[ $vers ]   = true;
			$kaart[ $oud_id ] = $vers;
		}

		$nieuwe_boom = Kadence_MCP_Inventory::hernoem_unique_ids( $boom, $kaart );
		$inhoud      = Kadence_MCP_Inventory::serialiseer( $nieuwe_boom );

		// Geen enkel oud ID mag zijn blijven staan. Eén achterblijver betekent
		// twee posts die dezelfde CSS-klasse dragen.
		foreach ( array_keys( $kaart ) as $oud_id ) {
			if ( false !== strpos( $inhoud, $oud_id ) ) {
				wp_delete_post( $nieuw_id, true );

				return new WP_Error(
					'kadence_mcp_id_leftover',
					sprintf(
						/* translators: %s: uniqueID. */
						__( 'Het oude uniqueID "%s" staat nog in de gekopieerde inhoud. De nieuwe query is weer verwijderd.', 'mcp-abilities-kadence' ),
						$oud_id
					)
				);
			}
		}

		wp_update_post(
			array(
				'ID'           => $nieuw_id,
				'post_content' => wp_slash( $inhoud ),
			),
			true
		);

		// Alle _kad_query-instellingen mee, behalve de facetten: die wijzen met
		// een uniqueID naar filterblokken die zojuist allemaal een ander ID
		// hebben gekregen, en worden daarom opnieuw berekend.
		foreach ( get_post_meta( $bron->ID ) as $sleutel => $waarden ) {
			if ( 0 !== strpos( (string) $sleutel, '_kad_' ) || '_kad_query_facets' === $sleutel ) {
				continue;
			}

			update_post_meta( $nieuw_id, $sleutel, maybe_unserialize( $waarden[0] ) );
		}

		update_post_meta( $nieuw_id, '_kad_query_query', $instellingen );

		$facetten = Kadence_MCP_Query::schrijf_facetten( $nieuw_id );
		$stand    = is_wp_error( $facetten ) ? array( 'computed' => array(), 'in_sync' => false ) : $facetten;

		clean_post_cache( $nieuw_id );

		$controle = get_post( $nieuw_id );
		$klopt    = $controle && '' !== trim( $controle->post_content );

		return array_merge(
			$rapport,
			array(
				'new_id'   => (int) $nieuw_id,
				'id_map'   => (object) $kaart,
				'facets'   => isset( $stand['computed'] ) ? $stand['computed'] : array(),
				'created'  => true,
				'token'    => '',
				'next'     => sprintf(
					/* translators: %d: new query ID. */
					__( 'Plaatsen doe je zo: generate-section met recipe custom en als boom één blok kadence/query met attrs {"id":%d}, en dat daarna invoegen met insert-blocks.', 'mcp-abilities-kadence' ),
					(int) $nieuw_id
				),
				'status'   => $klopt
					? sprintf(
						/* translators: 1: new ID, 2: number of ids, 3: number of facets. */
						__( 'Aangemaakt als post %1$d. %2$d uniqueIDs vernieuwd, %3$d facetten opnieuw berekend. Hij staat nog op geen enkele pagina.', 'mcp-abilities-kadence' ),
						(int) $nieuw_id,
						count( $kaart ),
						count( isset( $stand['computed'] ) ? $stand['computed'] : array() )
					)
					: __( 'LET OP: de query is aangemaakt maar heeft geen inhoud bij het teruglezen. Controleer hem.', 'mcp-abilities-kadence' ),
			)
		);
	}

	/**
	 * De filterblokken uit een boom, plat.
	 *
	 * @param array $blokken De boom.
	 * @param array $uit     De verzameling.
	 *
	 * @return array
	 */
	private static function filterblokken( $blokken, $uit = array() ) {
		foreach ( $blokken as $blok ) {
			$naam = isset( $blok['blockName'] ) ? (string) $blok['blockName'] : '';

			if ( 0 === strpos( $naam, 'kadence/query-filter' ) ) {
				$attrs = isset( $blok['attrs'] ) && is_array( $blok['attrs'] ) ? $blok['attrs'] : array();

				$uit[] = array(
					'block'     => $naam,
					'unique_id' => isset( $attrs['uniqueID'] ) ? (string) $attrs['uniqueID'] : '',
					'label'     => isset( $attrs['label'] ) ? (string) $attrs['label'] : '',
					// query-filter-search en query-filter-reset zien eruit als
					// filters maar leveren geen facet op: de een zoekt in de
					// tekst, de ander wist alleen de keuzes.
					'is_facet'  => in_array( $naam, Kadence_MCP_Query::FACETBLOKKEN, true ),
				);
			}

			if ( ! empty( $blok['innerBlocks'] ) ) {
				$uit = self::filterblokken( $blok['innerBlocks'], $uit );
			}
		}

		return $uit;
	}

	/**
	 * Alles wat je van een posttype moet weten om er een query op te bouwen.
	 *
	 * Waarom dit bestaat: get-post-meta geeft bewust alleen sleutels van Kadence
	 * terug. Dat is juist voor blokwerk, maar onbruikbaar zodra je een Query
	 * Loop over een ANDER posttype bouwt. De kaart moet dan velden tonen die van
	 * een heel andere plug-in komen — een evenementdatum, een locatie — en die
	 * spreek je aan met de meta-sleutel. Zonder die sleutel te kunnen zien is de
	 * enige uitweg gokken, en een verkeerde sleutel levert een leeg blok op
	 * zonder foutmelding.
	 *
	 * Waarden worden afgekapt. Het doel is uitzoeken WELK veld je nodig hebt,
	 * niet de inhoud uitlezen.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function describe_post_type( $input = array() ) {
		$naam = isset( $input['post_type'] ) ? sanitize_key( (string) $input['post_type'] ) : '';
		$type = $naam ? get_post_type_object( $naam ) : null;

		if ( ! $type ) {
			$bekend = get_post_types( array( 'show_ui' => true ), 'names' );

			return new WP_Error(
				'kadence_mcp_unknown_post_type',
				sprintf(
					/* translators: 1: post type, 2: comma separated post types. */
					__( 'Het posttype "%1$s" bestaat niet. Bekend zijn: %2$s.', 'mcp-abilities-kadence' ),
					$naam,
					implode( ', ', $bekend )
				)
			);
		}

		// De taxonomieën. Dit bepaalt of een taxonomiefilter überhaupt kan
		// werken, en welke naam dynamiclist nodig heeft.
		$taxonomieen = array();

		foreach ( get_object_taxonomies( $naam, 'objects' ) as $tax ) {
			$termen = get_terms( array( 'taxonomy' => $tax->name, 'hide_empty' => false, 'fields' => 'ids', 'number' => 0 ) );

			$taxonomieen[] = array(
				'name'   => $tax->name,
				'label'  => $tax->label,
				'public' => (bool) $tax->public,
				'terms'  => is_wp_error( $termen ) ? 0 : count( $termen ),
			);
		}

		$voorbeeld = self::voorbeeldpost( $naam, isset( $input['sample_id'] ) ? (int) $input['sample_id'] : 0 );
		$velden    = array();

		if ( $voorbeeld ) {
			foreach ( get_post_meta( $voorbeeld->ID ) as $sleutel => $waarden ) {
				$waarde = maybe_unserialize( isset( $waarden[0] ) ? $waarden[0] : '' );

				if ( is_array( $waarde ) || is_object( $waarde ) ) {
					$kort = sprintf( '(%s met %d elementen)', is_object( $waarde ) ? 'object' : 'array', count( (array) $waarde ) );
				} else {
					$kort = (string) $waarde;
					$kort = strlen( $kort ) > 80 ? substr( $kort, 0, 80 ) . '…' : $kort;
				}

				// ACF zet naast elk veld een verwijzing met dezelfde naam met
				// een underscore ervoor, met de veldsleutel als waarde. Dat
				// verraadt dat het veld van ACF is, en dat bepaalt hoe je het
				// in een blok aanspreekt.
				$is_acf = isset( get_post_meta( $voorbeeld->ID )[ '_' . $sleutel ] );

				$velden[] = array(
					'key'    => (string) $sleutel,
					'value'  => $kort,
					'empty'  => ( '' === trim( (string) $kort ) ),
					'source' => $is_acf ? 'acf' : ( 0 === strpos( (string) $sleutel, '_' ) ? 'verborgen' : 'meta' ),
				);
			}
		}

		$telling = wp_count_posts( $naam );

		return array(
			'post_type'   => array(
				'name'         => $naam,
				'label'        => $type->label,
				'public'       => (bool) $type->public,
				'published'    => isset( $telling->publish ) ? (int) $telling->publish : 0,
				'rest_base'    => isset( $type->rest_base ) && $type->rest_base ? $type->rest_base : $naam,
				'capabilities' => array(
					'create' => isset( $type->cap->create_posts ) ? (string) $type->cap->create_posts : '',
					'edit'   => isset( $type->cap->edit_posts ) ? (string) $type->cap->edit_posts : '',
				),
			),
			'taxonomies'  => $taxonomieen,
			'sample'      => $voorbeeld ? array( 'id' => $voorbeeld->ID, 'title' => get_the_title( $voorbeeld ) ) : null,
			'meta_fields' => $velden,
			'dynamic'     => self::DYNAMISCHE_VELDEN,
			'status'      => $voorbeeld
				? sprintf(
					/* translators: 1: number of meta keys, 2: sample title, 3: number of taxonomies. */
					__( '%1$d meta-sleutels gelezen van "%2$s", %3$d taxonomieën. Een veld met source acf of meta spreek je in een blok aan met field post|post_custom_field, para kb_custom_input en custom gelijk aan de sleutel. Waarden zijn afgekapt: dit is bedoeld om te zien WELK veld je nodig hebt.', 'mcp-abilities-kadence' ),
					count( $velden ),
					get_the_title( $voorbeeld ),
					count( $taxonomieen )
				)
				: __( 'Er is geen gepubliceerde post van dit type om velden van af te lezen. Maak er eerst een aan, of geef sample_id op.', 'mcp-abilities-kadence' ),
		);
	}

	/**
	 * De vaste velden die Kadence zelf aanbiedt in dynamische blokken.
	 *
	 * Afgelezen uit dist/blocks-query.js en de afhandeling in
	 * class-kadence-blocks-pro-dynamic-content.php, 13-09-2026.
	 */
	const DYNAMISCHE_VELDEN = array(
		'post|post_title'              => 'de titel',
		'post|post_excerpt'            => 'de samenvatting',
		'post|post_date'               => 'de publicatiedatum, in het datumformaat van de site — NIET zelf op te maken in het blok',
		'post|post_date_modified'      => 'de wijzigingsdatum',
		'post|post_url'                => 'de permalink, gebruik dit voor de link van een knop',
		'post|post_featured_image'     => 'de uitgelichte afbeelding',
		'post|post_featured_image_url' => 'de URL van de uitgelichte afbeelding',
		'post|post_custom_field'       => 'een eigen veld; zet para op kb_custom_input en custom op de meta-sleutel',
	);

	/**
	 * Een gepubliceerde post om velden van af te lezen.
	 *
	 * @param string $post_type Het posttype.
	 * @param int    $gekozen   Een specifieke post, of 0.
	 *
	 * @return WP_Post|null
	 */
	private static function voorbeeldpost( $post_type, $gekozen = 0 ) {
		if ( $gekozen > 0 ) {
			$post = get_post( $gekozen );

			return ( $post && $post->post_type === $post_type ) ? $post : null;
		}

		$zoek = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => 'publish',
				'numberposts'      => 1,
				'suppress_filters' => false,
			)
		);

		return empty( $zoek ) ? null : $zoek[0];
	}

	/**
	 * Maak een nieuwe Query Card door een bestaande te kopiëren.
	 *
	 * Een Query Card is de opmaak van één resultaat in een Query Loop, en hij is
	 * een eigen post van het type kadence_query_card. De Loop verwijst ernaar
	 * met een id in het blok kadence/query-card.
	 *
	 * Kopiëren en niet vanaf nul bouwen, om dezelfde reden als bij een query:
	 * de kaart hangt vol dynamische blokken — dynamichtml voor een veld,
	 * dynamiclist voor taxonomietermen, een knop met een kb-dynamic-shortcode
	 * als link — en die verhoudingen klop je makkelijker bij dan dat je ze
	 * verzint. Wat je erna aanpast doe je met set-attributes en replace-block.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function create_query_card( $input = array() ) {
		$bron = get_post( isset( $input['source_id'] ) ? (int) $input['source_id'] : 0 );

		if ( ! $bron || 'kadence_query_card' !== $bron->post_type ) {
			return new WP_Error(
				'kadence_mcp_not_a_card',
				__( 'Geef het ID op van een bestaande kadence_query_card. Vind ze met list-entities; het ID staat ook in het attribuut id van het blok kadence/query-card in een Query Loop.', 'mcp-abilities-kadence' )
			);
		}

		$titel = isset( $input['title'] ) ? trim( wp_strip_all_tags( (string) $input['title'] ) ) : '';

		if ( '' === $titel ) {
			return new WP_Error( 'kadence_mcp_no_title', __( 'Geef de nieuwe kaart een titel; dat is de naam waarmee je hem in de editor terugvindt.', 'mcp-abilities-kadence' ) );
		}

		$post_type = isset( $input['for_post_type'] ) ? sanitize_key( (string) $input['for_post_type'] ) : '';

		if ( '' !== $post_type && ! post_type_exists( $post_type ) ) {
			return new WP_Error(
				'kadence_mcp_unknown_post_type',
				sprintf(
					/* translators: %s: post type. */
					__( 'Het posttype "%s" bestaat niet. Vraag describe-post-type op om te zien welke er zijn.', 'mcp-abilities-kadence' ),
					$post_type
				)
			);
		}

		$token     = isset( $input['token'] ) ? (string) $input['token'] : '';
		$grondslag = Kadence_MCP_Inventory::schrijf_token( $bron, '__createcard__', array( 'title' => $titel, 'for' => $post_type ) );

		$rapport = array(
			'source' => array( 'id' => $bron->ID, 'title' => get_the_title( $bron ) ),
			'title'  => $titel,
		);

		if ( '' === $token ) {
			return array_merge(
				$rapport,
				array(
					'new_id'  => 0,
					'id_map'  => (object) array(),
					'created' => false,
					'token'   => $grondslag,
					'next'    => '',
					'status'  => sprintf(
						/* translators: 1: new title, 2: source title. */
						__( 'Voorstel, er is NIETS aangemaakt. Er zou een kaart "%1$s" komen met de opmaak van "%2$s". Roep opnieuw aan met het token om hem echt te maken.', 'mcp-abilities-kadence' ),
						$titel,
						get_the_title( $bron )
					),
				)
			);
		}

		$mag = self::mag_schrijven( null );

		if ( is_wp_error( $mag ) ) {
			return $mag;
		}

		$type_object = get_post_type_object( 'kadence_query_card' );
		$mag_maken   = ( $type_object && isset( $type_object->cap->create_posts ) ) ? (string) $type_object->cap->create_posts : 'edit_posts';

		if ( ! current_user_can( $mag_maken ) ) {
			return new WP_Error(
				'kadence_mcp_create_denied',
				sprintf(
					/* translators: %s: capability. */
					__( 'Je mist de capability "%s", die nodig is om een Query Card aan te maken.', 'mcp-abilities-kadence' ),
					$mag_maken
				),
				array( 'status' => 403 )
			);
		}

		if ( ! hash_equals( $grondslag, $token ) ) {
			return new WP_Error( 'kadence_mcp_invalid_token', Kadence_MCP_Inventory::token_reden( $token, $grondslag, $bron ) );
		}

		$nieuw_id = wp_insert_post(
			array(
				'post_type'    => 'kadence_query_card',
				'post_status'  => 'publish',
				'post_title'   => $titel,
				'post_content' => '',
			),
			true
		);

		if ( is_wp_error( $nieuw_id ) ) {
			return $nieuw_id;
		}

		$boom  = Kadence_MCP_Inventory::schoon_blokken( parse_blocks( $bron->post_content ) );
		$kaart = array();
		$bezet = array();

		foreach ( array_keys( Kadence_MCP_Inventory::verzamel_unique_ids( $boom ) ) as $oud_id ) {
			$vers = Kadence_MCP_Inventory::nieuwe_unique_id( $nieuw_id, $bezet );

			if ( '' === $vers ) {
				wp_delete_post( $nieuw_id, true );

				return new WP_Error( 'kadence_mcp_no_unique_id', __( 'Er kon geen vrij uniqueID gemaakt worden. De nieuwe kaart is weer verwijderd.', 'mcp-abilities-kadence' ) );
			}

			$bezet[ $vers ]   = true;
			$kaart[ $oud_id ] = $vers;
		}

		$inhoud = Kadence_MCP_Inventory::serialiseer( Kadence_MCP_Inventory::hernoem_unique_ids( $boom, $kaart ) );

		foreach ( array_keys( $kaart ) as $oud_id ) {
			if ( false !== strpos( $inhoud, $oud_id ) ) {
				wp_delete_post( $nieuw_id, true );

				return new WP_Error(
					'kadence_mcp_id_leftover',
					sprintf(
						/* translators: %s: uniqueID. */
						__( 'Het oude uniqueID "%s" staat nog in de gekopieerde inhoud. De nieuwe kaart is weer verwijderd.', 'mcp-abilities-kadence' ),
						$oud_id
					)
				);
			}
		}

		wp_update_post( array( 'ID' => $nieuw_id, 'post_content' => wp_slash( $inhoud ) ), true );

		foreach ( get_post_meta( $bron->ID ) as $sleutel => $waarden ) {
			if ( 0 !== strpos( (string) $sleutel, '_kad_' ) ) {
				continue;
			}

			update_post_meta( $nieuw_id, $sleutel, maybe_unserialize( $waarden[0] ) );
		}

		// De kaart weet zelf voor welk posttype hij een voorbeeld toont. Staat
		// dat verkeerd, dan toont de editor een voorbeeld van het verkeerde
		// soort post en lijkt elk dynamisch veld leeg.
		if ( '' !== $post_type ) {
			update_post_meta( $nieuw_id, '_kad_query_card_postType', $post_type );
			update_post_meta( $nieuw_id, '_kad_query_card_preview_post_type', $post_type );
		}

		clean_post_cache( $nieuw_id );

		$controle = get_post( $nieuw_id );

		return array_merge(
			$rapport,
			array(
				'new_id'  => (int) $nieuw_id,
				'id_map'  => (object) $kaart,
				'created' => true,
				'token'   => '',
				'next'    => sprintf(
					/* translators: %d: new card ID. */
					__( 'Koppelen doe je door in de Query Loop het blok kadence/query-card op id %d te zetten met set-attributes. Daarna pas je de kaart aan met set-attributes en replace-block; describe-post-type laat zien welke velden je kunt tonen.', 'mcp-abilities-kadence' ),
					(int) $nieuw_id
				),
				'status'  => ( $controle && '' !== trim( $controle->post_content ) )
					? sprintf(
						/* translators: 1: new ID, 2: number of ids. */
						__( 'Aangemaakt als post %1$d, %2$d uniqueIDs vernieuwd. Hij is nog aan geen enkele Query Loop gekoppeld.', 'mcp-abilities-kadence' ),
						(int) $nieuw_id,
						count( $kaart )
					)
					: __( 'LET OP: de kaart is aangemaakt maar heeft geen inhoud bij het teruglezen. Controleer hem.', 'mcp-abilities-kadence' ),
			)
		);
	}

	/**
	 * De rasterinstellingen van een Query Card, en waar ze in post meta staan.
	 *
	 * Bewust een korte lijst. Deze meta heeft geen schema, dus een sleutel die
	 * hier niet in staat zou gewoon opgeslagen worden en daarna genegeerd — de
	 * slechtste uitkomst, want alles ziet er dan uit alsof het gelukt is.
	 */
	const KAART_SLEUTELS = array(
		'columns'           => '_kad_query_card_columns',
		'columnGap'         => '_kad_query_card_columnGap',
		'rowGap'            => '_kad_query_card_rowGap',
		'maxWidth'          => '_kad_query_card_maxWidth',
		'preview_post_type' => '_kad_query_card_preview_post_type',
	);

	/**
	 * De themavoorinstellingen die Kadence als maat accepteert.
	 */
	const KAART_MATEN = array( 'xxs', 'xs', 'sm', 'md', 'lg', 'xl', 'xxl' );

	/**
	 * Haal een kadence_query_card op.
	 *
	 * @param int $post_id Het ID.
	 *
	 * @return WP_Post|WP_Error
	 */
	private static function card_post( $post_id ) {
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

		if ( 'kadence_query_card' !== $post->post_type ) {
			return new WP_Error(
				'kadence_mcp_not_a_card',
				sprintf(
					/* translators: 1: post ID, 2: post type. */
					__( 'Post %1$d is een %2$s, geen kadence_query_card. De kaart is een eigen post; het blok kadence/query-card in de Query Loop verwijst ernaar met zijn id-attribuut.', 'mcp-abilities-kadence' ),
					$post->ID,
					$post->post_type
				)
			);
		}

		return $post;
	}

	/**
	 * Toets één rasterwaarde en geef hem terug in de vorm die Kadence bewaart.
	 *
	 * @param string $sleutel De sleutel uit KAART_SLEUTELS.
	 * @param mixed  $waarde  Wat de aanroeper opgaf.
	 *
	 * @return array {ok: bool, waarde: mixed, reden: string}
	 */
	private static function toets_kaartwaarde( $sleutel, $waarde ) {
		if ( 'preview_post_type' === $sleutel ) {
			$waarde = (string) $waarde;

			if ( ! post_type_exists( $waarde ) ) {
				return array(
					'ok'     => false,
					'waarde' => null,
					'reden'  => sprintf(
						/* translators: %s: post type. */
						__( 'Het posttype "%s" bestaat niet. Dit bepaalt welke voorbeeldpost de editor in de kaart laat zien; staat het verkeerd, dan lijkt elk dynamisch veld leeg terwijl er niets mis is.', 'mcp-abilities-kadence' ),
						$waarde
					),
				);
			}

			return array( 'ok' => true, 'waarde' => $waarde, 'reden' => '' );
		}

		if ( ! is_array( $waarde ) || 3 !== count( $waarde ) ) {
			return array(
				'ok'     => false,
				'waarde' => null,
				'reden'  => sprintf(
					/* translators: %s: key name. */
					__( '"%s" is responsive en verwacht een array van precies drie: [desktop, tablet, mobiel]. Een lege string betekent "neem over van de grotere weergave".', 'mcp-abilities-kadence' ),
					$sleutel
				),
			);
		}

		$uit = array();

		foreach ( array_values( $waarde ) as $i => $deel ) {
			if ( '' === $deel || null === $deel ) {
				$uit[] = '';
				continue;
			}

			if ( 'columns' === $sleutel ) {
				$getal = (int) $deel;

				if ( (string) $getal !== (string) $deel || $getal < 1 || $getal > 6 ) {
					return array(
						'ok'     => false,
						'waarde' => null,
						'reden'  => sprintf(
							/* translators: 1: value, 2: position. */
							__( 'columns accepteert 1 tot en met 6 of een lege string; "%1$s" op plek %2$d kan niet.', 'mcp-abilities-kadence' ),
							is_scalar( $deel ) ? (string) $deel : gettype( $deel ),
							(int) $i + 1
						),
					);
				}

				// Kadence bewaart dit als tekst, niet als getal.
				$uit[] = (string) $getal;
				continue;
			}

			if ( is_numeric( $deel ) ) {
				$uit[] = (string) $deel;
				continue;
			}

			if ( in_array( (string) $deel, self::KAART_MATEN, true ) ) {
				$uit[] = (string) $deel;
				continue;
			}

			return array(
				'ok'     => false,
				'waarde' => null,
				'reden'  => sprintf(
					/* translators: 1: key, 2: value, 3: known sizes. */
					__( '%1$s accepteert een getal, een lege string of een themavoorinstelling; "%2$s" is geen van drie. Bekende maten: %3$s.', 'mcp-abilities-kadence' ),
					$sleutel,
					is_scalar( $deel ) ? (string) $deel : gettype( $deel ),
					implode( ', ', self::KAART_MATEN )
				),
			);
		}

		return array( 'ok' => true, 'waarde' => $uit, 'reden' => '' );
	}

	/**
	 * De huidige rasterinstellingen van een kaart.
	 *
	 * @param int $post_id Het ID van de kaart.
	 *
	 * @return array
	 */
	private static function kaart_opmaak( $post_id ) {
		$uit = array();

		foreach ( self::KAART_SLEUTELS as $sleutel => $meta ) {
			$waarde = get_post_meta( (int) $post_id, $meta, true );

			$uit[ $sleutel ] = ( '' === $waarde && 'preview_post_type' !== $sleutel ) ? array( '', '', '' ) : $waarde;
		}

		return $uit;
	}

	/**
	 * Welke Query Loops deze kaart gebruiken.
	 *
	 * @param int $card_id Het ID van de kaart.
	 *
	 * @return array
	 */
	private static function kaart_gebruikt_door( $card_id ) {
		$queries = get_posts(
			array(
				'post_type'        => 'kadence_query',
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'suppress_filters' => false,
			)
		);

		$uit = array();

		foreach ( $queries as $query ) {
			if ( false === strpos( (string) $query->post_content, 'kadence/query-card' ) ) {
				continue;
			}

			if ( ! preg_match( '/kadence\/query-card\s+\{[^}]*"id"\s*:\s*' . (int) $card_id . '\b/', (string) $query->post_content ) ) {
				continue;
			}

			$uit[] = array(
				'id'    => (int) $query->ID,
				'title' => (string) $query->post_title,
			);
		}

		return $uit;
	}

	/**
	 * Zet de rasterinstellingen van een Query Card.
	 *
	 * @param array $input De invoer.
	 *
	 * @return array|WP_Error
	 */
	public static function set_card_layout( $input = array() ) {
		$post = self::card_post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$grendel = self::mag_schrijven( $post );

		if ( is_wp_error( $grendel ) ) {
			return $grendel;
		}

		$voorstel = isset( $input['layout'] ) && is_array( $input['layout'] ) ? $input['layout'] : array();

		if ( empty( $voorstel ) ) {
			return new WP_Error( 'kadence_mcp_no_layout', __( 'Geef op wat er moet veranderen in layout.', 'mcp-abilities-kadence' ) );
		}

		$onbekend = array_diff( array_keys( $voorstel ), array_keys( self::KAART_SLEUTELS ) );

		if ( ! empty( $onbekend ) ) {
			return new WP_Error(
				'kadence_mcp_unknown_card_key',
				sprintf(
					/* translators: 1: unknown keys, 2: known keys. */
					__( 'Onbekende sleutels: %1$s. Deze meta heeft geen schema, dus zo een sleutel zou gewoon opgeslagen worden en daarna genegeerd. Bekend zijn: %2$s.', 'mcp-abilities-kadence' ),
					implode( ', ', $onbekend ),
					implode( ', ', array_keys( self::KAART_SLEUTELS ) )
				)
			);
		}

		$voor    = self::kaart_opmaak( $post->ID );
		$schoon  = array();
		$bezwaar = array();

		foreach ( $voorstel as $sleutel => $waarde ) {
			$toets = self::toets_kaartwaarde( $sleutel, $waarde );

			if ( ! $toets['ok'] ) {
				$bezwaar[] = $toets['reden'];
				continue;
			}

			$schoon[ $sleutel ] = $toets['waarde'];
		}

		if ( ! empty( $bezwaar ) ) {
			return new WP_Error( 'kadence_mcp_invalid_card_layout', implode( ' ', $bezwaar ) );
		}

		$na        = array_merge( $voor, $schoon );
		$gewijzigd = array();

		foreach ( $schoon as $sleutel => $waarde ) {
			$oud = isset( $voor[ $sleutel ] ) ? $voor[ $sleutel ] : null;

			if ( wp_json_encode( $oud ) === wp_json_encode( $waarde ) ) {
				continue;
			}

			$gewijzigd[] = array(
				'key'  => $sleutel,
				'from' => $oud,
				'to'   => $waarde,
			);
		}

		$gebruikt = self::kaart_gebruikt_door( $post->ID );

		$rapport = array(
			'post'    => array(
				'id'    => (int) $post->ID,
				'title' => (string) $post->post_title,
			),
			'before'  => (object) $voor,
			'after'   => (object) $na,
			'changed' => $gewijzigd,
			'used_by' => $gebruikt,
		);

		$verwacht = Kadence_MCP_Inventory::schrijf_token( $post, 'kaartopmaak', $schoon );
		$token    = isset( $input['token'] ) ? (string) $input['token'] : '';

		if ( '' === $token ) {
			return array_merge(
				$rapport,
				array(
					'written' => false,
					'token'   => $verwacht,
					'status'  => empty( $gewijzigd )
						? __( 'Voorstel, er is NIETS opgeslagen — en er zou ook niets veranderen: de kaart staat al zo.', 'mcp-abilities-kadence' )
						: sprintf(
							/* translators: 1: number of changes, 2: number of query loops. */
							__( 'Voorstel, er is NIETS opgeslagen. Er zouden %1$d instellingen wijzigen; %2$d Query Loops gebruiken deze kaart en veranderen dus mee. Roep opnieuw aan met het token om te schrijven.', 'mcp-abilities-kadence' ),
							count( $gewijzigd ),
							count( $gebruikt )
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

		foreach ( $schoon as $sleutel => $waarde ) {
			update_post_meta( $post->ID, self::KAART_SLEUTELS[ $sleutel ], $waarde );
		}

		clean_post_cache( $post->ID );

		// Teruglezen: opgeslagen is niet hetzelfde als opgeslagen zoals bedoeld.
		$controle = self::kaart_opmaak( $post->ID );
		$afwijking = array();

		foreach ( $schoon as $sleutel => $waarde ) {
			if ( wp_json_encode( $controle[ $sleutel ] ) !== wp_json_encode( $waarde ) ) {
				$afwijking[] = $sleutel;
			}
		}

		return array_merge(
			$rapport,
			array(
				'after'   => (object) $controle,
				'written' => empty( $afwijking ),
				'token'   => '',
				'status'  => empty( $afwijking )
					? sprintf(
						/* translators: %d: number of query loops. */
						__( 'Geschreven en teruggelezen: de opmaak staat zoals bedoeld. %d Query Loops gebruiken deze kaart.', 'mcp-abilities-kadence' ),
						count( $gebruikt )
					)
					: sprintf(
						/* translators: %s: keys that did not stick. */
						__( 'LET OP: geschreven, maar bij het teruglezen wijken deze sleutels af: %s. Controleer de kaart in de editor.', 'mcp-abilities-kadence' ),
						implode( ', ', $afwijking )
					),
			)
		);
	}
}
