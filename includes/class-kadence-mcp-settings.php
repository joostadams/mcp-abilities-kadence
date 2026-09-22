<?php
/**
 * Instellingenscherm, onder Kadence Blocks → MCP.
 *
 * Twee lagen, en ze doen verschillende dingen:
 *
 *   de toggle      bepaalt of een ability überhaupt wordt aangeboden
 *   de capability  bepaalt of dit account hem mag uitvoeren
 *
 * De toggle is oppervlakteverkleining. De capability is de grendel. Een
 * uitgezette toggle die je weer aanzet geeft meteen toegang; een ontbrekende
 * capability niet.
 *
 * @package Kadence_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Beheert de opties en het scherm.
 */
class Kadence_MCP_Settings {

	const OPTION_ENABLED       = 'kadence_mcp_enabled';
	const OPTION_ENABLED_TOOLS = 'kadence_mcp_enabled_tools';
	const OPTION_ENDPOINT_MODE = 'kadence_mcp_endpoint_mode';

	const OPTION_GROUP = 'kadence_mcp_settings';
	const MENU_SLUG    = 'kadence-mcp';

	/**
	 * Eigen MCP-server: elke ingeschakelde ability is een eigen tool.
	 */
	const ENDPOINT_DEDICATED = 'dedicated';

	/**
	 * De gedeelde WordPress-server: abilities zijn alleen bereikbaar via de
	 * generieke discovery-tools van de adapter.
	 */
	const ENDPOINT_SITE = 'site';

	/**
	 * REST-namespace waaronder MCP-servers hangen.
	 *
	 * Bewust 'mcp' en niet 'kadence': dat is de namespace waar de MCP Adapter
	 * zijn eigen standaardserver neerzet en waar Gravity Forms zijn server
	 * onderhangt. Alle MCP-endpoints van een site staan zo bij elkaar, en wie
	 * op /wp-json/mcp/ kijkt ziet ze alle drie.
	 */
	const ROUTE_NAMESPACE = 'mcp';

	/**
	 * Route van onze eigen server, binnen die namespace.
	 */
	const SERVER_SLUG = 'kadence';

	/**
	 * Route van de gedeelde standaardserver van de MCP Adapter.
	 *
	 * De adapter legt deze waarde als kale string vast in zijn
	 * DefaultServerFactory en biedt er geen constante of getter voor. Hier
	 * overgenomen om hem te kunnen tonen. Verandert de adapter dit, dan is het
	 * filter mcp_adapter_default_server_config het signaal.
	 */
	const DEFAULT_SERVER_ROUTE = 'mcp/mcp-adapter-default-server';

	/**
	 * Hang het scherm en de opties op.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 99 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

		// options.php eist standaard manage_options. Zonder dit filter kan een
		// account dat alleen kadence_mcp_manage heeft het scherm wel zien maar
		// niet opslaan.
		add_filter( 'option_page_capability_' . self::OPTION_GROUP, array( __CLASS__, 'option_page_capability' ) );
	}

	/**
	 * De capability die het opslaan van dit optiescherm bewaakt.
	 *
	 * @return string
	 */
	public static function option_page_capability() {
		return Kadence_MCP_Capabilities::MANAGE;
	}

	/**
	 * Staat de hoofdschakelaar aan?
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) get_option( self::OPTION_ENABLED, false );
	}

	/**
	 * De namen van de abilities die de beheerder heeft aangezet.
	 *
	 * @return string[]
	 */
	public static function get_enabled_tools() {
		$tools = get_option( self::OPTION_ENABLED_TOOLS, array() );

		return is_array( $tools ) ? array_values( array_filter( array_map( 'strval', $tools ) ) ) : array();
	}

	/**
	 * Is deze ability aangezet?
	 *
	 * De enige bron van waarheid voor elke poort — registratie, REST en MCP.
	 * Staat de hoofdschakelaar uit, dan staat alles uit, ongeacht de lijst.
	 *
	 * @param string $ability_name Volledige naam, bijvoorbeeld 'kadence/list-blocks'.
	 *
	 * @return bool
	 */
	public static function is_tool_enabled( $ability_name ) {
		if ( ! self::is_enabled() ) {
			return false;
		}

		return in_array( $ability_name, self::get_enabled_tools(), true );
	}

	/**
	 * De gekozen endpointstand.
	 *
	 * @return string
	 */
	public static function get_endpoint_mode() {
		$mode = get_option( self::OPTION_ENDPOINT_MODE, self::ENDPOINT_DEDICATED );

		return in_array( $mode, array( self::ENDPOINT_SITE, self::ENDPOINT_DEDICATED ), true )
			? $mode
			: self::ENDPOINT_DEDICATED;
	}

	/**
	 * Draait de plugin op zijn eigen server?
	 *
	 * @return bool
	 */
	public static function is_dedicated_endpoint() {
		return self::ENDPOINT_DEDICATED === self::get_endpoint_mode();
	}

	/**
	 * De volledige URL van de gekozen endpoint.
	 *
	 * @return string
	 */
	public static function get_endpoint_url() {
		$route = self::is_dedicated_endpoint()
			? self::ROUTE_NAMESPACE . '/' . self::SERVER_SLUG
			: self::DEFAULT_SERVER_ROUTE;

		return rest_url( $route );
	}

	/**
	 * Zijn de Abilities API en de MCP Adapter aanwezig?
	 *
	 * @return bool
	 */
	public static function is_mcp_available() {
		return function_exists( 'wp_register_ability' ) && class_exists( '\WP\MCP\Core\McpAdapter' );
	}

	/**
	 * Voeg het submenu toe.
	 *
	 * Op prioriteit 99 zodat Kadence Blocks zijn eigen menu al heeft
	 * geregistreerd. Staat dat menu er niet, dan valt het scherm terug naar
	 * Instellingen — zichtbaar op een andere plek is beter dan onzichtbaar.
	 *
	 * @return void
	 */
	public static function add_menu() {
		$parent = self::heeft_kadence_menu() ? 'kadence-blocks' : 'options-general.php';

		add_submenu_page(
			$parent,
			__( 'Kadence MCP', 'mcp-abilities-kadence' ),
			__( 'MCP', 'mcp-abilities-kadence' ),
			Kadence_MCP_Capabilities::MANAGE,
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Bestaat het menu van Kadence Blocks?
	 *
	 * @return bool
	 */
	private static function heeft_kadence_menu() {
		global $admin_page_hooks;

		return is_array( $admin_page_hooks ) && isset( $admin_page_hooks['kadence-blocks'] );
	}

	/**
	 * Registreer de drie opties met hun sanitizers.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_ENABLED,
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_enabled' ),
			)
		);

		register_setting(
			self::OPTION_GROUP,
			self::OPTION_ENDPOINT_MODE,
			array(
				'type'              => 'string',
				'default'           => self::ENDPOINT_DEDICATED,
				'sanitize_callback' => static function ( $value ) {
					$geldig = array( self::ENDPOINT_SITE, self::ENDPOINT_DEDICATED );

					return in_array( $value, $geldig, true ) ? $value : self::ENDPOINT_DEDICATED;
				},
			)
		);

		register_setting(
			self::OPTION_GROUP,
			self::OPTION_ENABLED_TOOLS,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_tools' ),
			)
		);
	}

	/**
	 * Bewaar de hoofdschakelaar.
	 *
	 * Twee dingen tegelijk. Het verborgen veld stuurt de string '0' mee wanneer
	 * de checkbox uit staat — die is niet leeg maar wel onwaar. En wanneer de
	 * Abilities API of de adapter ontbreekt staat de checkbox op disabled en
	 * post de browser hem niet; WordPress schrijft dan alsnog élke
	 * geregistreerde optie weg, met null als er niets gepost is. Zonder deze
	 * poort zou één keer opslaan op zo'n site de instelling stil uitzetten.
	 *
	 * @param mixed $value De geposte waarde.
	 *
	 * @return bool
	 */
	public static function sanitize_enabled( $value ) {
		if ( ! self::is_mcp_available() ) {
			return self::is_enabled();
		}

		return ! in_array( $value, array( '0', 0, '', false, null ), true );
	}

	/**
	 * Laat alleen namen door die deze plugin ook echt kent.
	 *
	 * Een geposte naam die niet in de registry staat wordt weggegooid, niet
	 * bewaard. Anders kan een handmatig gepost veld een naam in de optie
	 * zetten die later door een andere plugin geregistreerd wordt en dan
	 * ineens aan blijkt te staan.
	 *
	 * @param mixed $value De geposte waarde.
	 *
	 * @return string[]
	 */
	public static function sanitize_tools( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$bekend = wp_list_pluck( Kadence_MCP_Registry::get_definitions(), 'name' );

		return array_values( array_intersect( array_map( 'strval', $value ), $bekend ) );
	}

	/**
	 * Teken het scherm.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! Kadence_MCP_Capabilities::current_user_can( Kadence_MCP_Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'Je hebt geen toegang tot deze pagina.', 'mcp-abilities-kadence' ) );
		}

		$beschikbaar = self::is_mcp_available();
		$aan         = self::is_enabled();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Kadence MCP', 'mcp-abilities-kadence' ); ?></h1>

			<p style="max-width:46em">
				<?php esc_html_e( 'Geeft een AI-assistent toegang tot Kadence via MCP: achttien leestools en zeventien schrijftools. Alles staat standaard uit; je zet per tool aan wat je wil aanbieden. Schrijven vraagt daarnaast de capability kadence_mcp_write, die bij installatie aan niemand wordt toegekend.', 'mcp-abilities-kadence' ); ?>
			</p>

			<?php if ( ! $beschikbaar ) : ?>
				<div class="notice notice-error inline">
					<p>
						<strong><?php esc_html_e( 'Nog niet bruikbaar.', 'mcp-abilities-kadence' ); ?></strong>
						<?php esc_html_e( 'Deze plugin heeft zowel de WordPress Abilities API als de MCP Adapter nodig. Zolang die ontbreken wordt er niets geregistreerd en is er geen endpoint.', 'mcp-abilities-kadence' ); ?>
					</p>
					<p>
						<?php
						printf(
							/* translators: 1: Abilities API status, 2: MCP Adapter status. */
							esc_html__( 'Abilities API: %1$s — MCP Adapter: %2$s', 'mcp-abilities-kadence' ),
							function_exists( 'wp_register_ability' ) ? esc_html__( 'aanwezig', 'mcp-abilities-kadence' ) : '<strong>' . esc_html__( 'ontbreekt', 'mcp-abilities-kadence' ) . '</strong>',
							class_exists( '\WP\MCP\Core\McpAdapter' ) ? esc_html__( 'aanwezig', 'mcp-abilities-kadence' ) : '<strong>' . esc_html__( 'ontbreekt', 'mcp-abilities-kadence' ) . '</strong>'
						);
						?>
					</p>

					<?php
					// "Ontbreekt" zonder vervolg is een doodlopend spoor. De
					// adapter staat niet in de plugin-directory, dus zoeken
					// onder Plugins > Nieuwe plugin levert niets op, en van de
					// twee downloads op GitHub werkt er maar een: de broncode
					// mist vendor/ en dus de autoloader.
					if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) :
						?>
						<p>
							<?php
							printf(
								/* translators: %s: link to the MCP Adapter release asset. */
								esc_html__( 'De MCP Adapter staat niet in de plugin-directory; zoeken levert niets op. Haal %s van de releasepagina en installeer die via Plugins → Nieuwe plugin → Plugin uploaden. Neem het bestand onder Assets, niet de broncode — die mist zijn dependencies.', 'mcp-abilities-kadence' ),
								'<a href="https://github.com/WordPress/mcp-adapter/releases/latest" target="_blank" rel="noopener noreferrer"><code>mcp-adapter.zip</code></a>'
							);
							?>
						</p>
					<?php endif; ?>

					<?php
					// De Abilities API zit in core vanaf 6.9, en dat is ook de
					// ondergrens van deze plugin. Ontbreekt de functie, dan
					// draait deze site ouder dan dat.
					if ( ! function_exists( 'wp_register_ability' ) ) :
						?>
						<p>
							<?php
							printf(
								/* translators: %s: the current WordPress version. */
								esc_html__( 'De Abilities API zit in WordPress vanaf 6.9. Deze site draait %s; werk WordPress bij, of installeer de Abilities API als losse plugin.', 'mcp-abilities-kadence' ),
								esc_html( get_bloginfo( 'version' ) )
							);
							?>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<h2><?php esc_html_e( 'Hoofdschakelaar', 'mcp-abilities-kadence' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'MCP inschakelen', 'mcp-abilities-kadence' ); ?></th>
						<td>
							<?php // Een uitgevinkte checkbox post niets. Zonder dit veld zou de
								// oude waarde blijven staan en was uitzetten onmogelijk. ?>
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_ENABLED ); ?>" value="0">
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_ENABLED ); ?>" value="1" <?php checked( $aan ); ?> <?php disabled( ! $beschikbaar ); ?>>
								<?php esc_html_e( 'Bied de aangevinkte tools aan via MCP', 'mcp-abilities-kadence' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Staat dit uit, dan is geen enkele tool bereikbaar — ook niet de tools die hieronder aangevinkt staan.', 'mcp-abilities-kadence' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php self::render_tools_section(); ?>
				<?php self::render_endpoint_section(); ?>

				<?php submit_button(); ?>
			</form>

			<?php self::render_status_section(); ?>
			<?php Kadence_MCP_Skill::render_sectie(); ?>
		</div>
		<?php
	}

	/**
	 * De lijst met tools, gegroepeerd op readonly en write.
	 *
	 * De lijst komt uit de registry, niet uit een tweede tabel hier. Voeg je
	 * een ability toe, dan verschijnt hij vanzelf op dit scherm.
	 *
	 * @return void
	 */
	private static function render_tools_section() {
		$ingeschakeld = self::get_enabled_tools();
		$groepen      = array(
			'readonly' => array(
				'titel' => __( 'Alleen lezen', 'mcp-abilities-kadence' ),
				'uitleg' => __( 'Deze tools lezen uit en veranderen niets. Zet aan wat je wil aanbieden.', 'mcp-abilities-kadence' ),
				'items' => array(),
			),
			'write'    => array(
				'titel' => __( 'Schrijven', 'mcp-abilities-kadence' ),
				'uitleg' => __( 'Deze tools veranderen inhoud. Aanzetten is niet genoeg: het account heeft daarnaast de capability kadence_mcp_write nodig, en die wordt bij activering aan niemand toegekend. Zet dit alleen aan wanneer je erbij bent, en daarna weer uit.', 'mcp-abilities-kadence' ),
				'items' => array(),
			),
		);

		foreach ( Kadence_MCP_Registry::get_definitions() as $definitie ) {
			$groep = ! empty( $definitie['meta']['annotations']['readonly'] ) ? 'readonly' : 'write';

			$groepen[ $groep ]['items'][] = $definitie;
		}

		// Zelfde reden als bij de hoofdschakelaar: zonder een altijd meegepost
		// veld blijft de opgeslagen lijst staan zodra je alles uitvinkt. De
		// lege waarde valt in sanitize_tools() vanzelf af.
		printf(
			'<input type="hidden" name="%s[]" value="">',
			esc_attr( self::OPTION_ENABLED_TOOLS )
		);

		foreach ( $groepen as $sleutel => $groep ) {
			if ( empty( $groep['items'] ) ) {
				continue;
			}

			echo '<h2>' . esc_html( $groep['titel'] ) . '</h2>';
			echo '<p class="description" style="max-width:46em">' . esc_html( $groep['uitleg'] ) . '</p>';

			// Alles aanvinken per groep. Bewust per groep en niet één knop voor
			// het hele scherm: lezen en schrijven zijn wezenlijk verschillende
			// beslissingen, en één vinkje dat ze allebei aanzet nodigt uit tot
			// precies de klik die je niet wil.
			//
			// Dit verkleint alleen het oppervlak; de grendel blijft de capability.
			// Alle schrijftools aanzetten doet nog steeds niets voor een account
			// zonder kadence_mcp_write.
			$alles_aan = count( array_intersect( wp_list_pluck( $groep['items'], 'name' ), $ingeschakeld ) ) === count( $groep['items'] );
			?>
			<p>
				<label>
					<input type="checkbox"
						class="kmcp-alles"
						data-groep="<?php echo esc_attr( $sleutel ); ?>"
						<?php checked( $alles_aan ); ?>>
					<strong>
						<?php
						printf(
							/* translators: %d: number of tools in this group. */
							esc_html__( 'Alle %d aanvinken', 'mcp-abilities-kadence' ),
							count( $groep['items'] )
						);
						?>
					</strong>
				</label>
			</p>
			<?php
			echo '<table class="form-table" role="presentation"><tbody>';

			foreach ( $groep['items'] as $definitie ) {
				$naam    = $definitie['name'];
				$samenv  = isset( $definitie['meta']['summary'] ) ? $definitie['meta']['summary'] : '';
				?>
				<tr>
					<th scope="row" style="font-weight:400">
						<label for="<?php echo esc_attr( 'kmcp-' . sanitize_key( $naam ) ); ?>">
							<?php echo esc_html( $definitie['label'] ); ?>
						</label>
					</th>
					<td>
						<label>
							<input
								type="checkbox"
								id="<?php echo esc_attr( 'kmcp-' . sanitize_key( $naam ) ); ?>"
								name="<?php echo esc_attr( self::OPTION_ENABLED_TOOLS ); ?>[]"
								value="<?php echo esc_attr( $naam ); ?>"
								class="kmcp-tool"
								data-groep="<?php echo esc_attr( $sleutel ); ?>"
								<?php checked( in_array( $naam, $ingeschakeld, true ) ); ?>>
							<code><?php echo esc_html( $naam ); ?></code>
							<?php if ( self::is_dedicated_endpoint() ) : ?>
								<span class="description">
									<?php
									printf(
										/* translators: %s: the tool name as the MCP client sees it. */
										esc_html__( '— de assistent ziet dit als %s', 'mcp-abilities-kadence' ),
										'<code>' . esc_html( str_replace( '/', '-', $naam ) ) . '</code>'
									);
									?>
								</span>
							<?php endif; ?>
						</label>
						<?php if ( $samenv ) : ?>
							<p class="description"><?php echo esc_html( $samenv ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<?php
			}

			echo '</tbody></table>';
		}

		self::render_alles_script();
	}

	/**
	 * De schakelaar achter "Alle N aanvinken".
	 *
	 * Inline en zonder afhankelijkheden: het is twintig regels op één scherm, en
	 * een apart bestand plus een enqueue zou meer onderhoud kosten dan het waard
	 * is. Er wordt niets opgeslagen — dit zet alleen vinkjes; opslaan doet het
	 * formulier zoals altijd.
	 *
	 * De groepsvinkjes staan op indeterminate zodra een groep half aanstaat. Dat
	 * is geen verfraaiing: een leeg vakje boven een half aangevinkte lijst leest
	 * als "er staat niets aan", en dan klik je hem aan om te zien wat er gebeurt.
	 *
	 * @return void
	 */
	private static function render_alles_script() {
		?>
		<script>
		( function () {
			var groepen = {};

			document.querySelectorAll( '.kmcp-alles' ).forEach( function ( knop ) {
				groepen[ knop.dataset.groep ] = {
					knop:  knop,
					tools: document.querySelectorAll( '.kmcp-tool[data-groep="' + knop.dataset.groep + '"]' )
				};
			} );

			function ververs( groep ) {
				var aan = 0;

				groep.tools.forEach( function ( tool ) {
					if ( tool.checked ) {
						aan++;
					}
				} );

				groep.knop.checked       = aan === groep.tools.length;
				groep.knop.indeterminate = aan > 0 && aan < groep.tools.length;
			}

			Object.keys( groepen ).forEach( function ( naam ) {
				var groep = groepen[ naam ];

				ververs( groep );

				groep.knop.addEventListener( 'change', function () {
					groep.tools.forEach( function ( tool ) {
						tool.checked = groep.knop.checked;
					} );

					groep.knop.indeterminate = false;
				} );

				groep.tools.forEach( function ( tool ) {
					tool.addEventListener( 'change', function () {
						ververs( groep );
					} );
				} );
			} );
		}() );
		</script>
		<?php
	}

	/**
	 * De endpointkeuze.
	 *
	 * @return void
	 */
	private static function render_endpoint_section() {
		$mode = self::get_endpoint_mode();
		?>
		<h2><?php esc_html_e( 'Endpoint', 'mcp-abilities-kadence' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Hoe verbindt de assistent?', 'mcp-abilities-kadence' ); ?></th>
				<td>
					<fieldset>
						<label style="display:block;margin-bottom:.6em">
							<input type="radio" name="<?php echo esc_attr( self::OPTION_ENDPOINT_MODE ); ?>" value="<?php echo esc_attr( self::ENDPOINT_DEDICATED ); ?>" <?php checked( $mode, self::ENDPOINT_DEDICATED ); ?>>
							<strong><?php esc_html_e( 'Eigen endpoint (aanbevolen)', 'mcp-abilities-kadence' ); ?></strong><br>
							<span class="description" style="margin-left:1.8em;display:block;max-width:44em">
								<?php esc_html_e( 'Een server alleen voor Kadence. Elke ingeschakelde tool staat er als losse tool in, wat een assistent betrouwbaarder vindt en aanroept dan een tool die hij eerst moet opvragen.', 'mcp-abilities-kadence' ); ?>
							</span>
						</label>
						<label style="display:block">
							<input type="radio" name="<?php echo esc_attr( self::OPTION_ENDPOINT_MODE ); ?>" value="<?php echo esc_attr( self::ENDPOINT_SITE ); ?>" <?php checked( $mode, self::ENDPOINT_SITE ); ?>>
							<strong><?php esc_html_e( 'Gedeelde site-endpoint', 'mcp-abilities-kadence' ); ?></strong><br>
							<span class="description" style="margin-left:1.8em;display:block;max-width:44em">
								<?php esc_html_e( 'De endpoint die alle MCP-plugins op deze site delen. Eén verbinding voor de hele site, maar de Kadence-tools zijn alleen bereikbaar via de generieke discovery-tools van WordPress. Kies dit alleen als je bewust één verbinding wil.', 'mcp-abilities-kadence' ); ?>
							</span>
						</label>
					</fieldset>
					<p class="description" style="margin-top:1em">
						<?php esc_html_e( 'Huidige URL:', 'mcp-abilities-kadence' ); ?>
						<code><?php echo esc_html( self::get_endpoint_url() ); ?></code>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Een statusblok onder het formulier: wat staat er nu echt aan.
	 *
	 * @return void
	 */
	private static function render_status_section() {
		$definities   = Kadence_MCP_Registry::get_definitions();
		$ingeschakeld = self::get_enabled_tools();
		$actief       = self::is_enabled() ? count( array_intersect( wp_list_pluck( $definities, 'name' ), $ingeschakeld ) ) : 0;
		?>
		<hr>
		<h2><?php esc_html_e( 'Status', 'mcp-abilities-kadence' ); ?></h2>
		<table class="widefat striped" style="max-width:52em">
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Tools actief', 'mcp-abilities-kadence' ); ?></td>
					<td><?php echo esc_html( sprintf( '%d / %d', $actief, count( $definities ) ) ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Endpoint', 'mcp-abilities-kadence' ); ?></td>
					<td><code><?php echo esc_html( self::get_endpoint_url() ); ?></code></td>
				</tr>
				<?php
				$omgeving = Kadence_MCP_Inventory::get_environment();
				$labels   = array(
					'theme'              => __( 'Thema', 'mcp-abilities-kadence' ),
					'theme_version'      => __( 'Themaversie', 'mcp-abilities-kadence' ),
					'child_theme'        => __( 'Child theme', 'mcp-abilities-kadence' ),
					'kadence_blocks'     => __( 'Kadence Blocks', 'mcp-abilities-kadence' ),
					'kadence_blocks_pro' => __( 'Kadence Blocks Pro', 'mcp-abilities-kadence' ),
					'kadence_pro'        => __( 'Kadence Pro', 'mcp-abilities-kadence' ),
				);
				foreach ( $labels as $sleutel => $label ) :
					$waarde = isset( $omgeving[ $sleutel ] ) ? (string) $omgeving[ $sleutel ] : '';
					?>
					<tr>
						<td><?php echo esc_html( $label ); ?></td>
						<td><?php echo '' !== $waarde ? esc_html( $waarde ) : '<em>' . esc_html__( 'niet aanwezig', 'mcp-abilities-kadence' ) . '</em>'; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Een apart account voor de assistent', 'mcp-abilities-kadence' ); ?></h3>
		<p style="max-width:46em">
			<?php esc_html_e( 'Geef de assistent bij voorkeur een eigen gebruiker met een eigen rol, en verbind met een applicatiewachtwoord van dat account. Wat die rol niet mag, kan de assistent niet — ongeacht welke tools hierboven aanstaan.', 'mcp-abilities-kadence' ); ?>
		</p>
		<p style="max-width:46em">
			<strong><?php esc_html_e( 'Die rol heeft twee capabilities nodig, niet één.', 'mcp-abilities-kadence' ); ?></strong>
			<?php esc_html_e( 'Naast kadence_mcp_view ook read — het gewone abonneerecht. Zonder read komt het account niet langs de REST-poort, en kunnen de tools die posts uitlezen geen enkele post zien. Wil de assistent ook concepten en niet-gepubliceerde headers en elementen kunnen lezen, dan is daarbovenop edit_theme_options nodig; laat dat weg als je dat niet wil.', 'mcp-abilities-kadence' ); ?>
		</p>
		<table class="widefat striped" style="max-width:52em">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Capability', 'mcp-abilities-kadence' ); ?></th>
					<th><?php esc_html_e( 'Geeft recht op', 'mcp-abilities-kadence' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code>read</code></td>
					<td><?php esc_html_e( 'langs de REST-poort komen en posts mogen zien — WordPress-standaard, elke rol vanaf abonnee heeft hem', 'mcp-abilities-kadence' ); ?></td>
				</tr>
				<tr>
					<td><code>kadence_mcp_view</code></td>
					<td><?php esc_html_e( 'de leestools uitvoeren', 'mcp-abilities-kadence' ); ?></td>
				</tr>
				<tr>
					<td><code>kadence_mcp_manage</code></td>
					<td><?php esc_html_e( 'dit scherm zien en opslaan', 'mcp-abilities-kadence' ); ?></td>
				</tr>
				<tr>
					<td><code>kadence_mcp_write</code></td>
					<td><?php esc_html_e( 'blokken wijzigen — krijgt niemand automatisch, ook de beheerder niet. Daarnaast eist WordPress zelf bewerkrecht op de post.', 'mcp-abilities-kadence' ); ?></td>
				</tr>
				<tr>
					<td><code>kadence_mcp_full_access</code></td>
					<td><?php esc_html_e( 'alles hierboven, ook wat er later bij komt — niemand krijgt hem automatisch', 'mcp-abilities-kadence' ); ?></td>
				</tr>
			</tbody>
		</table>
		<?php
	}
}
