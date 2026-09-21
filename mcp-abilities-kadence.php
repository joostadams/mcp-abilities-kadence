<?php
/**
 * Plugin Name:       MCP Abilities — Kadence
 * Plugin URI:        https://github.com/joostadams/mcp-abilities-kadence
 * Description:       Geeft een MCP-agent toegang tot Kadence Blocks, Kadence Blocks Pro, Kadence Pro en het Kadence-thema, via de WordPress Abilities API. Achttien leestools en zeventien schrijftools; alles staat standaard uit, en schrijven vraagt bovendien een eigen capability die niemand automatisch krijgt.
 * Version:           1.20.0
 * Requires at least: 6.8
 * Requires PHP:      7.4
 * Author:            Joost Adams
 * Author URI:        https://github.com/joostadams
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mcp-abilities-kadence
 *
 * @package Kadence_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KADENCE_MCP_VERSION', '1.20.0' );
define( 'KADENCE_MCP_FILE', __FILE__ );
define( 'KADENCE_MCP_PATH', plugin_dir_path( __FILE__ ) );
define( 'KADENCE_MCP_BASENAME', plugin_basename( __FILE__ ) );

/**
 * De namespace waaronder alle abilities van deze plugin vallen.
 *
 * Staat als constante vast: de dedicated server filtert hierop, de
 * instellingen groeperen hierop, en de capability-controle leest hem.
 */
define( 'KADENCE_MCP_NAMESPACE', 'kadence' );

require_once KADENCE_MCP_PATH . 'includes/class-kadence-mcp-capabilities.php';
require_once KADENCE_MCP_PATH . 'includes/class-kadence-mcp-settings.php';
require_once KADENCE_MCP_PATH . 'includes/class-kadence-mcp-skill.php';
require_once KADENCE_MCP_PATH . 'includes/class-kadence-mcp-inventory.php';
require_once KADENCE_MCP_PATH . 'includes/class-kadence-mcp-profielen.php';
require_once KADENCE_MCP_PATH . 'includes/class-kadence-mcp-sjablonen.php';
require_once KADENCE_MCP_PATH . 'includes/class-kadence-mcp-query.php';
require_once KADENCE_MCP_PATH . 'includes/class-kadence-mcp-registry.php';
require_once KADENCE_MCP_PATH . 'includes/class-kadence-mcp-server.php';
require_once KADENCE_MCP_PATH . 'includes/class-kadence-mcp.php';

/**
 * Activering.
 *
 * Bewust op topniveau geregistreerd — binnen een hook vuurt hij niet.
 *
 * Er is GEEN deactivation hook. Deactiveren hoort de rollen ongemoeid te
 * laten; het opruimen gebeurt in uninstall.php. Zie de toelichting bij
 * Kadence_MCP_Capabilities::on_uninstall().
 */
register_activation_hook( __FILE__, array( 'Kadence_MCP_Capabilities', 'on_activate' ) );

/**
 * Updates vanaf GitHub, via plugin-update-checker.
 *
 * De repo is publiek, dus er is geen token nodig: geen setAuthentication(), geen
 * constante in wp-config.php. Zou de repo ooit priv\u00e9 worden, dan is dat het
 * moment om die er alsnog bij te zetten — zonder token geeft GitHub dan een 404
 * en verschijnt er stil nooit meer een update.
 *
 * enableReleaseAssets() laat PUC de ZIP pakken die de release-workflow bouwt, in
 * plaats van de automatisch gegenereerde zipball van de tag. Die zipball is de
 * hele repo, inclusief .github/, en dat hoort niet op een klantserver.
 *
 * setBranch('main') schakelt release-detectie NIET uit: PUC behandelt main en
 * master als "eerst de laatste release, dan de hoogste versietag, en pas als die
 * er geen van beide zijn de branch zelf".
 */
function kadence_mcp_register_updater() {
	$puc = KADENCE_MCP_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';

	if ( ! is_readable( $puc ) ) {
		return;
	}

	require_once $puc;

	$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/joostadams/mcp-abilities-kadence/',
		KADENCE_MCP_FILE,
		'mcp-abilities-kadence'
	);

	$checker->setBranch( 'main' );
	$checker->getVcsApi()->enableReleaseAssets( '/^mcp-abilities-kadence\.zip$/' );
}
add_action( 'plugins_loaded', 'kadence_mcp_register_updater', 0 );

/**
 * Start de plugin.
 *
 * Op 'plugins_loaded' zodat Kadence, de Abilities API en de MCP Adapter
 * geladen zijn voordat er iets wordt bekeken of geregistreerd. Er gebeurt
 * bij het inladen van dit bestand bewust niets anders dan definiëren.
 */
add_action( 'plugins_loaded', array( 'Kadence_MCP', 'boot' ) );
