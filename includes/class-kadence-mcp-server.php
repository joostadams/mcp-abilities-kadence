<?php
/**
 * De eigen MCP-server.
 *
 * Waarom een eigen server en niet de gedeelde: op de gedeelde staat elke
 * ability achter de generieke discovery-tools van de adapter. Een assistent
 * moet dan eerst vragen wát er is en daarna via een omweg uitvoeren. Op een
 * eigen server staat elke ingeschakelde ability als losse tool in de lijst.
 *
 * @package Kadence_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registreert de server bij de MCP Adapter.
 */
class Kadence_MCP_Server {

	/**
	 * Hang de registratie op.
	 *
	 * @return void
	 */
	public static function init() {
		// Prioriteit 20: de adapter zet zijn eigen standaardserver op 10 neer,
		// en onze abilities moeten geregistreerd zijn voordat we ernaar wijzen.
		add_action( 'mcp_adapter_init', array( __CLASS__, 'register_server' ), 20 );
	}

	/**
	 * De beschrijving die MCP-clients als serverinstructies inladen.
	 *
	 * Dit is de goedkoopste plek voor kennis die een agent nodig heeft: hij
	 * wordt bij elke sessie automatisch geladen en kost geen toolcall. Alleen
	 * dingen die STABIEL zijn en niet uit een schema af te leiden. Wat per
	 * blok verschilt hoort in describe-block, niet hier.
	 *
	 * @return string
	 */
	public static function get_server_description() {
		return implode( "\n", array(
			__( 'Toegang tot Kadence Blocks, Kadence Blocks Pro, Kadence Pro en het Kadence-thema. Vijfendertig abilities: achttien lezen, zeventien schrijven. SCHRIJVEN IS DUS GEEN UITZONDERING — lees voor elke tool die je aanroept in zijn beschrijving of hij schrijft. Elke schrijfability vraagt de capability kadence_mcp_write (die na installatie aan niemand is toegekend), bewerkrecht op de post volgens WordPress zelf, en een token uit een voorafgaande controlestap; na afloop wordt er teruggelezen en vergeleken. Wat ze raken verschilt: de meeste schrijven post_content, maar set-entity-meta en set-card-layout schrijven post meta (geen revisies) en set-global-typography en set-site-css raken de hele site. Maak je meer dan één blok tegelijk op, gebruik dan style-blocks: set-attributes doet één blok per aanroep en elk token vervalt zodra de post wijzigt. Wil je een nieuwe sectie bouwen, verzin dan geen markup maar vraag list-recipes en generate-section: Kadence bakt elk uniqueID in de klassenamen van het blok, en met de hand geschreven markup klopt gegarandeerd niet.', 'mcp-abilities-kadence' ),
			'',
			__( 'HOE KADENCE WAARDEN OPSLAAT — lees dit voordat je attributen interpreteert.', 'mcp-abilities-kadence' ),
			'',
			__( '1. Responsive waarden staan op TWEE manieren in de data, en dat verschilt per blok:', 'mcp-abilities-kadence' ),
			__( '   - als array van 3: [desktop, tablet, mobiel]. Voorbeeld: direction op kadence/column, fontSize op kadence/advancedheading, tabWidth op kadence/tabs.', 'mcp-abilities-kadence' ),
			__( '   - als losse attributen met een prefix of suffix: mobileLayout en tabletLayout op kadence/tabs, mobilePadding op kadence/rowlayout, bottomPaddingM op kadence/column.', 'mcp-abilities-kadence' ),
			__( '   Ga nooit uit van een van beide. Vraag describe-block om de volledige lijst.', 'mcp-abilities-kadence' ),
			'',
			__( '2. Zoeken op attribuutnaam mist responsive waarden. Het attribuut dat de mobiele richting van een Sectie bepaalt heet "direction", niet "mobileDirection". Zoek je op "mobile", dan vind je hem niet. Haal bij twijfel alles op met een hoge per_page.', 'mcp-abilities-kadence' ),
			'',
			__( '3. Arrays van 4 zijn spacing in de volgorde [boven, rechts, onder, links]. Een begeleidend attribuut op -Unit of -Type geeft de eenheid; ontbreekt dat, dan geldt de standaard (meestal px). Waarden als "sm" en "md" zijn themavoorinstellingen, geen pixels.', 'mcp-abilities-kadence' ),
			'',
			__( '4. Kleuren verwijzen naar het globale palet met palette1 tot en met palette15. Vraag get-global-styles om de werkelijke hexwaarden. Let op dat achtergrondkleur op drie plekken kan staan: bgColor op kadence/rowlayout, background op kadence/column, en soms op het bovenliggende blok zelf.', 'mcp-abilities-kadence' ),
			'',
			__( '5. Een verborgen blok is niet aan zijn attributen te zien. Dat staat in metadata als blockVisibility: false, en dat is de native verbergoptie van WordPress zelf — hij geldt dus voor elk blok, niet alleen voor Kadence-blokken. inspect-post geeft metadata daarom standaard mee.', 'mcp-abilities-kadence' ),
			'',
			__( '6. Row Layout (kadence/rowlayout) en Sectie (kadence/column) zijn allebei containers maar regelen andere dingen. Row Layout heeft kolomaantallen en collapse-gedrag; Sectie is een flex-container met direction, justifyContent, gutter en flexBasis per breakpoint. Een pagina kan met beide gebouwd zijn, ook door elkaar heen.', 'mcp-abilities-kadence' ),
			'',
			__( '7. Vergelijk je twee blokken die er anders uitzien, gebruik dan diff-blocks. Zelf een selectie attributen ophalen en die vergelijken levert gemiste verschillen op.', 'mcp-abilities-kadence' ),
			'',
			__( '8. Niet alle configuratie staat in blokattributen. Een Query Loop is daar het duidelijkste voorbeeld van: het blok kadence/query heeft zes attributen en geen daarvan gaat over de query — posttype, sortering en meta_key staan in post meta op de kadence_query-post, onder _kad_query_query. Gebruik get-post-meta zodra je wil weten HOE iets is ingesteld in plaats van alleen dat het er staat.', 'mcp-abilities-kadence' ),
			'',
			__( '9. Queries, navigaties, cards en headers zijn losse posts die via een id-attribuut in een pagina worden opgenomen. Wil je weten waar zo een object gebruikt wordt voordat je het aanpast, gebruik find-usages.', 'mcp-abilities-kadence' ),
		) );
	}

	/**
	 * Maak de server aan.
	 *
	 * @param object $adapter De McpAdapter-instantie.
	 *
	 * @return void
	 */
	public static function register_server( $adapter ) {
		if ( ! Kadence_MCP_Settings::is_enabled() || ! Kadence_MCP_Settings::is_dedicated_endpoint() ) {
			return;
		}

		if ( ! is_object( $adapter ) || ! method_exists( $adapter, 'create_server' ) ) {
			return;
		}

		$tools = Kadence_MCP_Registry::get_registered_names();

		// Geen ingeschakelde tools betekent geen server. Een lege server
		// aanbieden geeft een verbinding die niets kan, en dat is verwarrender
		// dan een verbinding die er nog niet is.
		if ( empty( $tools ) ) {
			return;
		}

		$transport     = 'WP\\MCP\\Transport\\HttpTransport';
		$foutafhandelaar = 'WP\\MCP\\Infrastructure\\ErrorHandling\\ErrorLogMcpErrorHandler';
		$observability = 'WP\\MCP\\Infrastructure\\Observability\\NullMcpObservabilityHandler';

		if ( ! class_exists( $transport ) || ! class_exists( $foutafhandelaar ) || ! class_exists( $observability ) ) {
			error_log( 'Kadence MCP: de MCP Adapter mist een verwachte klasse; de eigen server is niet aangemaakt.' );

			return;
		}

		$adapter->create_server(
			Kadence_MCP_Settings::SERVER_SLUG,
			Kadence_MCP_Settings::ROUTE_NAMESPACE,
			Kadence_MCP_Settings::SERVER_SLUG,
			__( 'Kadence MCP Server', 'mcp-abilities-kadence' ),
			self::get_server_description(),
			KADENCE_MCP_VERSION,
			array( $transport ),
			$foutafhandelaar,
			$observability,
			$tools,
			array(),
			array(),
			// Dertiende argument: de poort op de REST-route zelf
			// (McpAdapter::create_server, $transport_permission_callback).
			// Zonder deze callback valt de HttpTransport terug op 'read', en
			// kan élke ingelogde gebruiker de toolcatalogus opvragen. Uitvoeren
			// bleef dicht — dat regelt de permission_callback per ability —
			// maar de lijst hoort niet open te staan.
			static function () {
				return Kadence_MCP_Capabilities::current_user_can( Kadence_MCP_Capabilities::VIEW );
			}
		);
	}
}
