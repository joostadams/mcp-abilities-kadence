<?php
/**
 * De ability-klasse met de grendels erin.
 *
 * Dit bestand wordt bewust pas ingeladen wanneer WP_Ability bestaat — het
 * breidt die klasse uit, dus eerder inladen geeft een fatale fout.
 *
 * @package Kadence_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Voegt twee dingen toe aan een gewone ability: de per-tool-schakelaar en een
 * schone foutafhandeling.
 */
class Kadence_MCP_Ability extends WP_Ability {

	/**
	 * Controleer of dit mag.
	 *
	 * De schakelaar wordt hier gecontroleerd — bij uitvoering, niet bij
	 * registratie. Een vlag die bij registratie wordt gezet ligt vast zolang
	 * het request duurt; deze controle stelt de vraag opnieuw bij elke
	 * aanroep, op elk oppervlak.
	 *
	 * @param array $input De invoer.
	 *
	 * @return bool|WP_Error
	 */
	public function check_permissions( $input = array() ) {
		if ( $this->is_tool_disabled() ) {
			return new WP_Error(
				'kadence_mcp_tool_disabled',
				sprintf(
					/* translators: %s: ability name. */
					__( 'De tool "%s" staat uit in Kadence → MCP.', 'mcp-abilities-kadence' ),
					$this->get_name()
				)
			);
		}

		return parent::check_permissions( $input );
	}

	/**
	 * Staat deze tool uit?
	 *
	 * Faalt dicht: is de instellingenklasse er niet, dan geldt de tool als uit.
	 * Een halve bootstrap mag de poort nooit openzetten.
	 *
	 * @return bool
	 */
	protected function is_tool_disabled() {
		if ( ! class_exists( 'Kadence_MCP_Settings' ) ) {
			return true;
		}

		return ! Kadence_MCP_Settings::is_tool_enabled( $this->get_name() );
	}

	/**
	 * Voer uit, en laat geen serverpaden weglekken.
	 *
	 * Een exception uit een callback wordt door de MCP Adapter met het ruwe
	 * bericht doorgegeven aan de client (McpTool.php:271) — inclusief absolute
	 * serverpaden. Daarom hier zelf vangen: het echte bericht gaat naar het
	 * logboek, de client krijgt een algemeen bericht. Een WP_Error die een
	 * handler bewust teruggeeft is een echte melding en gaat ongemoeid door.
	 *
	 * @param array $input De invoer.
	 *
	 * @return mixed
	 */
	public function do_execute( $input = array() ) {
		// Een aanroep zonder argumenten levert de standaard uit het schema op,
		// en dat is een stdClass terwijl de handlers een array verwachten.
		if ( $input instanceof stdClass ) {
			$input = (array) $input;
		}

		try {
			return parent::do_execute( $input );
		} catch ( \Throwable $e ) {
			error_log( sprintf(
				'Kadence MCP: ability "%s" gaf een exception — %s in %s:%d',
				$this->get_name(),
				$e->getMessage(),
				$e->getFile(),
				$e->getLine()
			) );

			return new WP_Error(
				'kadence_mcp_execution_failed',
				sprintf(
					/* translators: %s: ability name. */
					__( 'Ability "%s" is onverwacht gestopt. Kijk in het foutenlogboek van de site.', 'mcp-abilities-kadence' ),
					$this->get_name()
				)
			);
		}
	}
}
