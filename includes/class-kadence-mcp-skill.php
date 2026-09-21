<?php
/**
 * De agent skill, en het downloaden ervan.
 *
 * De skill staat als gewone bestanden in de plugin onder skill/. Deze klasse
 * verpakt die map desgevraagd tot een zip en stuurt hem naar de browser, zodat
 * je hem niet via FTP hoeft op te halen.
 *
 * Dit is de enige plek in de plugin die naar schijf schrijft: één tijdelijk
 * zipbestand, dat direct na verzending weer weg is. Aan WordPress-gegevens —
 * posts, opties, meta — wordt nergens iets veranderd.
 *
 * @package Kadence_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Levert de skill als download.
 */
class Kadence_MCP_Skill {

	/**
	 * De actienaam waar admin-post.php op luistert.
	 */
	const ACTION = 'kadence_mcp_download_skill';

	/**
	 * De map binnen de plugin waar de skill staat.
	 */
	const MAP = 'skill';

	/**
	 * Hang de downloadafhandeling op.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'download' ) );
	}

	/**
	 * Het volledige pad naar de skillmap, of '' als hij ontbreekt.
	 *
	 * @return string
	 */
	public static function pad() {
		$pad = KADENCE_MCP_PATH . self::MAP;

		return is_dir( $pad ) ? $pad : '';
	}

	/**
	 * De bestanden in de skillmap, relatief aan die map.
	 *
	 * @return string[]
	 */
	public static function bestanden() {
		$pad = self::pad();

		if ( '' === $pad ) {
			return array();
		}

		$lijst    = array();
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $pad, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $bestand ) {
			if ( ! $bestand->isFile() ) {
				continue;
			}

			$relatief = ltrim( str_replace( $pad, '', $bestand->getPathname() ), '/\\' );

			// Alleen bestanden binnen een skillmap. De README in de wortel van
			// skill/ legt uit hoe je installeert en hoort in de repo thuis, niet
			// in de zip — die zou anders los in de skills-map van de gebruiker
			// belanden naast de skillmap zelf.
			if ( false === strpos( str_replace( '\\', '/', $relatief ), '/' ) ) {
				continue;
			}

			$lijst[] = $relatief;
		}

		sort( $lijst );

		return $lijst;
	}

	/**
	 * De URL van de downloadknop, met nonce.
	 *
	 * @return string
	 */
	public static function url() {
		return wp_nonce_url(
			add_query_arg( 'action', self::ACTION, admin_url( 'admin-post.php' ) ),
			self::ACTION
		);
	}

	/**
	 * Stuur de skill als zip naar de browser.
	 *
	 * @return void
	 */
	public static function download() {
		// Zelfde poort als het instellingenscherm: wie de instellingen niet mag
		// beheren, hoeft de skill ook niet op te halen.
		if ( ! Kadence_MCP_Capabilities::current_user_can( Kadence_MCP_Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Je hebt geen toegang tot deze download.', 'mcp-abilities-kadence' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ACTION );

		$pad = self::pad();

		if ( '' === $pad ) {
			wp_die( esc_html__( 'De skillmap ontbreekt in deze plugin.', 'mcp-abilities-kadence' ) );
		}

		$bestanden = self::bestanden();

		if ( empty( $bestanden ) ) {
			wp_die( esc_html__( 'De skillmap is leeg.', 'mcp-abilities-kadence' ) );
		}

		// Zonder de zip-extensie het losse SKILL.md serveren. Dat is minder
		// comfortabel maar wel bruikbaar, en beter dan een knop die niets doet.
		if ( ! class_exists( 'ZipArchive' ) ) {
			self::stuur_markdown( $pad );
			return;
		}

		$tijdelijk = wp_tempnam( 'kadence-mcp-skill.zip' );
		$zip       = new ZipArchive();

		if ( true !== $zip->open( $tijdelijk, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			@unlink( $tijdelijk );
			wp_die( esc_html__( 'Het zipbestand kon niet worden aangemaakt.', 'mcp-abilities-kadence' ) );
		}

		foreach ( $bestanden as $relatief ) {
			$zip->addFile( trailingslashit( $pad ) . $relatief, $relatief );
		}

		$zip->close();

		$naam = sprintf( 'kadence-mcp-skill-%s.zip', KADENCE_MCP_VERSION );

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $naam . '"' );
		header( 'Content-Length: ' . filesize( $tijdelijk ) );
		header( 'X-Content-Type-Options: nosniff' );

		readfile( $tijdelijk );

		// Het tijdelijke bestand hoort niet te blijven staan.
		@unlink( $tijdelijk );

		exit;
	}

	/**
	 * Terugval zonder ZipArchive: het losse SKILL.md.
	 *
	 * @param string $pad Pad naar de skillmap.
	 *
	 * @return void
	 */
	private static function stuur_markdown( $pad ) {
		$bestand = trailingslashit( $pad ) . 'kadence-blocks/SKILL.md';

		if ( ! is_readable( $bestand ) ) {
			wp_die( esc_html__( 'SKILL.md is niet gevonden.', 'mcp-abilities-kadence' ) );
		}

		nocache_headers();
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="SKILL.md"' );
		header( 'X-Content-Type-Options: nosniff' );

		readfile( $bestand );

		exit;
	}

	/**
	 * Het blok op het instellingenscherm.
	 *
	 * @return void
	 */
	public static function render_sectie() {
		$bestanden = self::bestanden();
		?>
		<hr>
		<h2><?php esc_html_e( 'Agent skill', 'mcp-abilities-kadence' ); ?></h2>
		<p style="max-width:46em">
			<?php esc_html_e( 'De serverbeschrijving draagt de conventies al en wordt automatisch geladen. Deze skill is het niveau daarboven: werkwijzen, valkuilen en nuances die niet in een toolschema passen — hoe dynamische inhoud gekoppeld wordt, waarom een Query Loop een gedeeld object is, en welke aanroep bij welke vraag hoort.', 'mcp-abilities-kadence' ); ?>
		</p>

		<?php if ( empty( $bestanden ) ) : ?>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'De skillmap ontbreekt in deze installatie van de plugin.', 'mcp-abilities-kadence' ); ?></p>
			</div>
			<?php return; ?>
		<?php endif; ?>

		<p>
			<a class="button button-secondary" href="<?php echo esc_url( self::url() ); ?>">
				<?php esc_html_e( 'Skill downloaden', 'mcp-abilities-kadence' ); ?>
			</a>
			<span class="description" style="margin-left:.6em">
				<?php
				printf(
					/* translators: 1: number of files, 2: plugin version. */
					esc_html__( '%1$d bestanden, versie %2$s', 'mcp-abilities-kadence' ),
					count( $bestanden ),
					esc_html( KADENCE_MCP_VERSION )
				);
				?>
			</span>
		</p>

		<p class="description" style="max-width:46em">
			<?php esc_html_e( 'Pak de zip uit in de skills-map van je agent, zodat de map kadence-blocks daar rechtstreeks in staat:', 'mcp-abilities-kadence' ); ?>
		</p>
		<p class="description">
			<code>~/.claude/skills/kadence-blocks/</code><?php esc_html_e( ' voor al je projecten', 'mcp-abilities-kadence' ); ?><br>
			<code>&lt;project&gt;/.claude/skills/kadence-blocks/</code><?php esc_html_e( ' voor één project', 'mcp-abilities-kadence' ); ?>
		</p>
		<p class="description" style="max-width:46em">
			<?php esc_html_e( 'Claude laadt hem daarna zelf wanneer een vraag erover gaat; je hoeft er niet naar te verwijzen. Werk je de plugin bij, haal de skill dan opnieuw op — hij wordt samen met de plugin onderhouden.', 'mcp-abilities-kadence' ); ?>
		</p>
		<?php
	}
}
