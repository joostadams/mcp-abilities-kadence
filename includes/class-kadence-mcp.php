<?php
/**
 * De loader.
 *
 * Hangt alles op en doet verder niets. Bij het inladen van de plugin gebeurt
 * er bewust geen werk: pas op de juiste hook wordt er iets gelezen of
 * geregistreerd.
 *
 * @package Kadence_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Start de plugin.
 */
class Kadence_MCP {

	/**
	 * Boot.
	 *
	 * @return void
	 */
	public static function boot() {
		require_once KADENCE_MCP_PATH . 'includes/abilities/class-kadence-mcp-abilities-blocks.php';
		require_once KADENCE_MCP_PATH . 'includes/abilities/class-kadence-mcp-abilities-build.php';
		require_once KADENCE_MCP_PATH . 'includes/abilities/class-kadence-mcp-abilities-content.php';
		require_once KADENCE_MCP_PATH . 'includes/abilities/class-kadence-mcp-abilities-query.php';
		require_once KADENCE_MCP_PATH . 'includes/abilities/class-kadence-mcp-abilities-site.php';

		// Het instellingenscherm laadt ook zonder Abilities API en MCP Adapter.
		// Dan valt er niets aan te zetten, maar het scherm legt wel uit wat er
		// ontbreekt — beter dan een menu-item dat er ineens niet is.
		if ( is_admin() ) {
			Kadence_MCP_Settings::init();
			Kadence_MCP_Skill::init();
		}

		Kadence_MCP_Capabilities::init();

		add_action( 'wp_abilities_api_categories_init', array( 'Kadence_MCP_Registry', 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( 'Kadence_MCP_Registry', 'register_abilities' ) );

		// De MCP Adapter hangt zijn REST-routes en de hook mcp_adapter_init
		// pas op zodra zijn singleton bestaat (McpAdapter::instance()). Draait
		// de adapter alleen als gevendorde kopie in een andere plugin — zoals
		// in Gravity Forms — dan bestaat de klasse wel maar is er niets
		// geïnitialiseerd, en geeft onze endpoint-URL een 404 terwijl het
		// scherm meldt dat alles aanwezig is. Daarom hier zelf starten.
		//
		// Alleen achter onze eigen hoofdschakelaar: init() zet via
		// maybe_create_default_server() ook de site-brede standaardserver neer,
		// en die hoort niet te verschijnen op een site waar niemand daarom
		// heeft gevraagd. instance() en init() zijn allebei idempotent, dus
		// samenloop met een andere plugin is ongevaarlijk.
		if ( Kadence_MCP_Settings::is_enabled() && class_exists( '\\WP\\MCP\\Core\\McpAdapter' ) ) {
			\WP\MCP\Core\McpAdapter::instance();
		}

		Kadence_MCP_Server::init();
	}
}
