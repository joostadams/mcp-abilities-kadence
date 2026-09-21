<?php
/**
 * Opruimen bij verwijderen.
 *
 * Dit is de enige plek waar de capabilities weggehaald worden. Bij deactivering
 * gebeurt dat bewust niet: dan zou een rol die je zelf hebt ingericht stil
 * leeggehaald worden.
 *
 * WordPress laadt bij uninstall uitsluitend dit bestand, dus de requires uit de
 * hoofdplugin hebben niet gedraaid — de capability-klasse wordt hier zelf
 * ingeladen.
 *
 * Verder is er niets op te ruimen: deze plugin schrijft geen posts, geen post
 * meta en geen eigen tabellen.
 *
 * @package Kadence_MCP
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'kadence_mcp_enabled' );
delete_option( 'kadence_mcp_enabled_tools' );
delete_option( 'kadence_mcp_endpoint_mode' );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-kadence-mcp-capabilities.php';

if ( class_exists( 'Kadence_MCP_Capabilities' ) ) {
	Kadence_MCP_Capabilities::on_uninstall();
}
