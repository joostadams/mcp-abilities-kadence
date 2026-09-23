<?php
/**
 * Sjablonen: secties opbouwen uit Kadence-blokken.
 *
 * @package Kadence_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bouwt geldige Kadence-markup uit een recept.
 *
 * Het idee komt uit mcp-abilities-block-editor van bjornfix: leg een handvol
 * secties vast als recept en laat de agent er inhoud in gieten, in plaats van
 * hem markup te laten verzinnen. De uitwerking moest wel anders.
 *
 * Bjornfix plakt strings aan elkaar:
 *
 *     '<!-- wp:columns --><div class="wp-block-columns">' . $kolommen . '</div><!-- /wp:columns -->'
 *
 * Dat werkt bij core-blokken, want die hebben weinig attributen en een vaste
 * wrapper. Bij Kadence gaat het mis op twee punten. Elk blok draagt een
 * uniqueID die in zijn eigen klassenaam gebakken zit, en de JSON in het
 * blokcommentaar wordt door WordPress met eigen vlaggen gecodeerd — een
 * dubbele min wordt --, want anders zou hij het HTML-commentaar
 * afsluiten. Met de hand gebouwde JSON wijkt daar gegarandeerd van af.
 *
 * Daarom bouwt deze klasse een boom van arrays en laat hij serialize_blocks()
 * de markup maken. Die functie is dezelfde die WordPress zelf gebruikt bij het
 * opslaan, dus de codering klopt per definitie. Wat hier wél met de hand
 * gebeurt is de innerHTML per bloktype, en dat is precies de kennis die in
 * de blokprofielen staat (Kadence_MCP_Profielen).
 */
class Kadence_MCP_Sjablonen {

	/**
	 * Hoe elk bloktype zijn eigen markup wegschrijft.
	 *
	 * Afgelezen van markup die Kadence zelf heeft geschreven op een
	 * productiesite, niet uit de documentatie. Drie dingen
	 * vallen daarbij op en alle drie zitten hieronder verwerkt:
	 *
	 * 1. rowlayout slaat NIETS op. Geen div, geen klasse. Zijn kinderen staan
	 *    rechtstreeks tussen de twee commentaren. Daarom is zijn open en sluit
	 *    leeg — en daarom is elk attribuut erop vrij te wijzigen.
	 * 2. column heeft een dubbele div, en de buitenste draagt de uniqueID.
	 * 3. advancedheading zet de uniqueID twee keer neer: in de klasse en in
	 *    data-kb-block. Vergeet je er één, dan verliest het blok zijn CSS.
	 *
	 * {ID} wordt vervangen door de uniqueID, {TAG} door de HTML-tag.
	 */
	// De vormen stonden hier tot 1.10.0 als eigen tabel. Ze zitten nu in
	// Kadence_MCP_Profielen, samen met de klassen die uit attributen volgen en
	// de waarden die Kadence kent. Dat is één plek in plaats van vier, want
	// twee keer is er eentje vergeten en beide keren gaf de toets groen licht
	// terwijl het resultaat fout was.

	/**
	 * De beschikbare recepten.
	 *
	 * Bewust kaal. Er worden geen kleuren, marges of lettergroottes ingevuld
	 * die niemand gevraagd heeft: die komen uit de globale stijlen, en een
	 * verzonnen waarde is ruis die iemand later moet opsporen. Wat je hier
	 * krijgt is de STRUCTUUR — de juiste blokken, goed genest, met werkende
	 * uniqueIDs. De opmaak doe je daarna met set-attributes.
	 *
	 * @return array
	 */
	public static function recepten() {
		return array(
			array(
				'slug'        => 'hero',
				'label'       => __( 'Hero', 'mcp-abilities-kadence' ),
				'beschrijving' => __( 'Rij met twee kolommen: tekst links, tweede kolom leeg voor een achtergrondafbeelding of visual. Kop op h1, alinea, en een knoppenrij.', 'mcp-abilities-kadence' ),
				'blokken'     => array( 'kadence/rowlayout', 'kadence/column', 'kadence/advancedheading', 'kadence/advancedbtn', 'kadence/singlebtn' ),
				'slots'       => array( 'title', 'body', 'buttons' ),
			),
			array(
				'slug'        => 'tekst',
				'label'       => __( 'Tekstblok', 'mcp-abilities-kadence' ),
				'beschrijving' => __( 'Rij met één kolom: kop op h2 en een alinea. De eenvoudigste bouwsteen.', 'mcp-abilities-kadence' ),
				'blokken'     => array( 'kadence/rowlayout', 'kadence/column', 'kadence/advancedheading' ),
				'slots'       => array( 'title', 'body' ),
			),
			array(
				'slug'        => 'kolommen',
				'label'       => __( 'Kolommen', 'mcp-abilities-kadence' ),
				'beschrijving' => __( 'Rij met twee tot vier gelijke kolommen, elk met een kop op h3 en een alinea. Geef de inhoud mee via items.', 'mcp-abilities-kadence' ),
				'blokken'     => array( 'kadence/rowlayout', 'kadence/column', 'kadence/advancedheading' ),
				'slots'       => array( 'title', 'items' ),
			),
			array(
				'slug'        => 'custom',
				'label'       => __( 'Vrije opbouw', 'mcp-abilities-kadence' ),
				'beschrijving' => __( 'Geen sjabloon maar een eigen boom, meegegeven via tree. Gebruik dit zodra een ontwerp niet in een van de vaste vormen past — een hero met een label boven de kop, een sectie met gekleurde balken, kolommen met elk een andere achtergrond. Elke knoop is {block, attrs, text of children, tag}. Alleen blokken waarvan bekend is hoe ze hun markup wegschrijven; elk attribuut wordt getoetst zoals validate-write dat doet.', 'mcp-abilities-kadence' ),
				// Uit de profielen, niet met de hand: deze lijst liep achter en
				// noemde vijf blokken terwijl er veel meer te bouwen zijn, zodat
				// wie hem las zelfsluitende blokken als postgrid via een omweg
				// invoegde.
				'blokken'     => Kadence_MCP_Profielen::bloknamen(),
				'slots'       => array( 'tree' ),
			),
			array(
				'slug'        => 'cta',
				'label'       => __( 'Oproep tot actie', 'mcp-abilities-kadence' ),
				'beschrijving' => __( 'Rij met één kolom: kop op h2, korte alinea en een knoppenrij. Bedoeld als afsluiting van een pagina.', 'mcp-abilities-kadence' ),
				'blokken'     => array( 'kadence/rowlayout', 'kadence/column', 'kadence/advancedheading', 'kadence/advancedbtn', 'kadence/singlebtn' ),
				'slots'       => array( 'title', 'body', 'buttons' ),
			),
		);
	}

	/**
	 * Een recept opzoeken.
	 *
	 * @param string $slug De slug.
	 *
	 * @return array|null
	 */
	public static function recept( $slug ) {
		foreach ( self::recepten() as $recept ) {
			if ( $recept['slug'] === $slug ) {
				return $recept;
			}
		}

		return null;
	}

	/**
	 * Maak één knoop in de boom.
	 *
	 * @param string $bloknaam De bloknaam.
	 * @param array  $attrs    De attributen, zonder uniqueID.
	 * @param array  $kinderen De kindknopen.
	 * @param string $tekst    De tekst, alleen voor tekstblokken.
	 * @param string $tag      De HTML-tag, alleen voor advancedheading.
	 *
	 * @return array
	 */
	private static function knoop( $bloknaam, $attrs = array(), $kinderen = array(), $tekst = '', $tag = 'p' ) {
		return array(
			'blok'     => $bloknaam,
			'attrs'    => $attrs,
			'kinderen' => $kinderen,
			'tekst'    => $tekst,
			'tag'      => $tag,
		);
	}

	/**
	 * Bouw de boom voor een recept.
	 *
	 * @param string $slug  De receptslug.
	 * @param array  $input De inhoud.
	 *
	 * @return array|WP_Error
	 */
	public static function boom( $slug, $input ) {
		$recept = self::recept( $slug );

		if ( null === $recept ) {
			return new WP_Error(
				'kadence_mcp_unknown_recipe',
				sprintf(
					/* translators: 1: requested slug, 2: available slugs. */
					__( 'Onbekend recept "%1$s". Beschikbaar zijn: %2$s. Vraag list-recipes voor de beschrijvingen.', 'mcp-abilities-kadence' ),
					$slug,
					implode( ', ', wp_list_pluck( self::recepten(), 'slug' ) )
				)
			);
		}

		$titel   = isset( $input['title'] ) ? (string) $input['title'] : '';
		$tekst   = isset( $input['body'] ) ? (string) $input['body'] : '';
		$items   = isset( $input['items'] ) && is_array( $input['items'] ) ? $input['items'] : array();
		$knoppen = isset( $input['buttons'] ) && is_array( $input['buttons'] ) ? $input['buttons'] : array();

		if ( '' === trim( $titel ) ) {
			return new WP_Error(
				'kadence_mcp_recipe_no_title',
				__( 'Geef een title mee. Een sectie zonder kop levert een blok op dat in de editor niet te onderscheiden is van de rest.', 'mcp-abilities-kadence' )
			);
		}

		switch ( $slug ) {
			case 'hero':
				return array(
					self::knoop(
						'kadence/rowlayout',
						array( 'colLayout' => 'equal', 'kbVersion' => 2, 'metadata' => array( 'name' => 'Hero' ) ),
						array(
							self::knoop( 'kadence/column', array( 'kbVersion' => 2 ), array_merge(
								array( self::knoop( 'kadence/advancedheading', array( 'level' => 1 ), array(), $titel, 'h1' ) ),
								'' === trim( $tekst ) ? array() : array( self::knoop( 'kadence/advancedheading', array( 'htmlTag' => 'p' ), array(), $tekst, 'p' ) ),
								self::knoppenrij( $knoppen )
							) ),
							// Tweede kolom leeg: daar hoort de visual of de
							// achtergrond. Een kolom met een placeholder erin
							// moet iemand later weghalen.
							self::knoop( 'kadence/column', array( 'id' => 2, 'kbVersion' => 2 ) ),
						)
					),
				);

			case 'tekst':
				return array(
					self::knoop(
						'kadence/rowlayout',
						array( 'kbVersion' => 2 ),
						array(
							self::knoop( 'kadence/column', array( 'kbVersion' => 2 ), array_merge(
								array( self::knoop( 'kadence/advancedheading', array( 'level' => 2 ), array(), $titel, 'h2' ) ),
								'' === trim( $tekst ) ? array() : array( self::knoop( 'kadence/advancedheading', array( 'htmlTag' => 'p' ), array(), $tekst, 'p' ) )
							) ),
						)
					),
				);

			case 'kolommen':
				$aantal = count( $items );

				if ( $aantal < 2 || $aantal > 4 ) {
					return new WP_Error(
						'kadence_mcp_recipe_column_count',
						sprintf(
							/* translators: %d: number of items given. */
							__( 'Het recept kolommen verwacht 2 tot 4 items; er zijn er %d gegeven. Elk item wordt één kolom met een kop en een alinea.', 'mcp-abilities-kadence' ),
							$aantal
						)
					);
				}

				$kolommen = array();
				$nummer   = 0;

				foreach ( $items as $item ) {
					$nummer++;
					$kop  = is_array( $item ) && isset( $item['title'] ) ? (string) $item['title'] : (string) $item;
					$body = is_array( $item ) && isset( $item['body'] ) ? (string) $item['body'] : '';

					$inhoud = array( self::knoop( 'kadence/advancedheading', array( 'level' => 3 ), array(), $kop, 'h3' ) );

					if ( '' !== trim( $body ) ) {
						$inhoud[] = self::knoop( 'kadence/advancedheading', array( 'htmlTag' => 'p' ), array(), $body, 'p' );
					}

					// Kolom 1 krijgt geen id: dat is de standaardwaarde en
					// Kadence laat hem dan ook weg.
					$attrs = 1 === $nummer ? array( 'kbVersion' => 2 ) : array( 'id' => $nummer, 'kbVersion' => 2 );

					$kolommen[] = self::knoop( 'kadence/column', $attrs, $inhoud );
				}

				// colLayout moet kloppen met het aantal kinderen, anders staat
				// de layoutklasse los van wat er werkelijk in staat.
				// 'equal' voor elk aantal. 'thirds' en 'fourths' bestaan NIET; die
				// stonden hier tot 1.7.2 en leverden een rij op die op de voorkant
				// goed stond maar in de editor als losse blokken onder elkaar.
				$layout = array( 2 => 'equal', 3 => 'equal', 4 => 'equal' );

				$rij = self::knoop(
					'kadence/rowlayout',
					array( 'columns' => $aantal, 'colLayout' => $layout[ $aantal ], 'kbVersion' => 2 ),
					$kolommen
				);

				if ( '' === trim( $titel ) ) {
					return array( $rij );
				}

				// De kop komt in een eigen rij erboven, niet in kolom 1 — daar
				// zou hij scheef staan ten opzichte van de andere kolommen.
				return array(
					self::knoop(
						'kadence/rowlayout',
						array( 'kbVersion' => 2 ),
						array(
							self::knoop( 'kadence/column', array( 'kbVersion' => 2 ), array(
								self::knoop( 'kadence/advancedheading', array( 'level' => 2 ), array(), $titel, 'h2' ),
							) ),
						)
					),
					$rij,
				);

			case 'cta':
				return array(
					self::knoop(
						'kadence/rowlayout',
						array( 'kbVersion' => 2, 'metadata' => array( 'name' => 'CTA' ) ),
						array(
							self::knoop( 'kadence/column', array( 'kbVersion' => 2 ), array_merge(
								array( self::knoop( 'kadence/advancedheading', array( 'level' => 2 ), array(), $titel, 'h2' ) ),
								'' === trim( $tekst ) ? array() : array( self::knoop( 'kadence/advancedheading', array( 'htmlTag' => 'p' ), array(), $tekst, 'p' ) ),
								self::knoppenrij( $knoppen )
							) ),
						)
					),
				);
		}

		return new WP_Error( 'kadence_mcp_unknown_recipe', __( 'Onbekend recept.', 'mcp-abilities-kadence' ) );
	}

	/**
	 * Bouw de knoppenrij.
	 *
	 * advancedbtn is de container en singlebtn het knopje erin. Die scheiding
	 * bestaat omdat er meerdere knoppen naast elkaar kunnen staan; één knop is
	 * dus nog steeds twee blokken.
	 *
	 * @param array $knoppen Lijst van knoppen met text en link.
	 *
	 * @return array
	 */
	private static function knoppenrij( $knoppen ) {
		$knoppen = array_values( array_filter( (array) $knoppen ) );

		if ( empty( $knoppen ) ) {
			return array();
		}

		$kinderen = array();

		foreach ( $knoppen as $knop ) {
			$tekst = is_array( $knop ) && isset( $knop['text'] ) ? (string) $knop['text'] : (string) $knop;
			$link  = is_array( $knop ) && isset( $knop['link'] ) ? (string) $knop['link'] : '';

			$attrs = array( 'text' => $tekst );

			if ( '' !== $link ) {
				$attrs['link'] = esc_url_raw( $link );
			}

			$kinderen[] = self::knoop( 'kadence/singlebtn', $attrs );
		}

		return array( self::knoop( 'kadence/advancedbtn', array(), $kinderen ) );
	}

	/**
	 * Zet de boom om in markup.
	 *
	 * Hier worden de uniqueIDs uitgedeeld. Ze moeten uniek zijn binnen de post
	 * waar de sectie in terechtkomt, want Kadence hangt er CSS aan op: twee
	 * blokken met hetzelfde ID delen hun opmaak en veranderen samen zodra je er
	 * één aanpast. Daarom wordt de lijst met bezette IDs meegedragen en
	 * bijgewerkt terwijl de boom wordt doorlopen.
	 *
	 * @param array $knopen  De boom.
	 * @param int   $post_id De post waar het in komt.
	 * @param array $bezet   De al bezette uniqueIDs, wordt aangevuld.
	 *
	 * @return string
	 */
	public static function markup( $knopen, $post_id, &$bezet ) {
		$uit = '';

		foreach ( $knopen as $knoop ) {
			$bloknaam = $knoop['blok'];
			$vorm = Kadence_MCP_Profielen::van( $bloknaam );

			if ( null === $vorm ) {
				return '';
			}

			$unique_id = Kadence_MCP_Inventory::nieuwe_unique_id( $post_id, $bezet );

			if ( '' === $unique_id ) {
				return '';
			}

			// nieuwe_unique_id toetst met isset() op de SLEUTEL. Zet je het ID
			// als waarde weg, dan botst er nooit iets en deelt hij vrolijk
			// dubbele IDs uit — waarna twee blokken hun CSS delen.
			$bezet[ $unique_id ] = true;

			$attrs = array_merge( array( 'uniqueID' => $unique_id ), $knoop['attrs'] );

			// columns moet kloppen met het werkelijke aantal kinderen. De
			// standaardwaarde in block.json is 2, en Kadence rekent daarmee: een rij
			// met één kolom en geen columns krijgt repeat(2, 1fr) en zet de inhoud op
			// halve breedte, met een lege sleuf ernaast. Geen foutmelding — de markup
			// is syntactisch in orde, dus ook de rondgang laat hem door.
			if ( 'kadence/rowlayout' === $bloknaam ) {
				$attrs['columns'] = count( $knoop['kinderen'] );

				if ( empty( $attrs['colLayout'] ) ) {
					$attrs['colLayout'] = 'equal';
				}
			}

			// Hetzelfde voor elk ander blok dat zijn kinderen telt, zoals
			// slideCount op een slider: een verkeerd getal geeft lege of
			// ontbrekende slides, zonder melding.
			if ( '' !== (string) $vorm['aantal_kinderen'] ) {
				$attrs[ $vorm['aantal_kinderen'] ] = count( $knoop['kinderen'] );
			}

			$attrs = self::vul_kbversion_aan( $bloknaam, $attrs );

			if ( 'kadence/column' === $bloknaam ) {
				$attrs = self::zet_ruimte_goed( $attrs );
			}

			$genormaliseerd = Kadence_MCP_Inventory::normaliseer_attributen( $bloknaam, $attrs );
			$json           = wp_json_encode( (object) $genormaliseerd['attrs'] );

			// Een blok zonder inhoud EN zonder eigen wrapper is zelfsluitend. Dat
			// is geen uitzondering maar de regel die WordPress zelf hanteert:
			// get_comment_delimited_block_content() schrijft /--> zodra er niets
			// tussen de tags staat.
			//
			// kadence/query laat zien waarom dat nodig is. In een PAGINA verwijst
			// hij alleen naar een query-post en is hij leeg, dus zelfsluitend. In
			// die query-post zélf draagt hij de hele layout en heeft hij een open-
			// en sluittag. Eén vorm, twee gedaanten, bepaald door de inhoud.
			$leeg_en_zonder_wrapper = empty( $knoop['kinderen'] )
				&& '' === trim( (string) $knoop['tekst'] )
				&& '' === (string) $vorm['open']
				&& '' === (string) $vorm['sluit'];

			if ( ! empty( $vorm['zelfsluitend'] ) || $leeg_en_zonder_wrapper ) {
				$uit .= '<!-- wp:' . $bloknaam . ' ' . $json . ' /-->' . "\n\n";
				continue;
			}

			$klassen = Kadence_MCP_Profielen::klassen( $bloknaam, $genormaliseerd['attrs'] );
			$zoek    = array_merge( array( '{ID}', '{TAG}' ), array_keys( $klassen ) );
			$vervang = array_merge( array( $unique_id, $knoop['tag'] ), array_values( $klassen ) );

			$open  = self::vul_attributen( str_replace( $zoek, $vervang, $vorm['open'] ), $bloknaam, $genormaliseerd['attrs'] );
			$sluit = self::vul_attributen( str_replace( $zoek, $vervang, $vorm['sluit'] ), $bloknaam, $genormaliseerd['attrs'] );

			$binnenin = '';

			if ( ! empty( $knoop['kinderen'] ) ) {
				$binnenin = "\n" . self::markup( $knoop['kinderen'], $post_id, $bezet );
			} elseif ( '' !== $knoop['tekst'] ) {
				$binnenin = Kadence_MCP_Inventory::schoon_tekst( $knoop['tekst'] );
			}

			$uit .= '<!-- wp:' . $bloknaam . ' ' . $json . ' -->' . "\n";
			$uit .= $open . $binnenin . $sluit . "\n";
			$uit .= '<!-- /wp:' . $bloknaam . ' -->' . "\n\n";
		}

		return $uit;
	}

	/**
	 * Bouw, controleer en geef de markup terug.
	 *
	 * De controle is een rondgang: parse de gebouwde markup, serialiseer hem
	 * opnieuw, en kijk of je hetzelfde terugkrijgt. Dat is de truc die bjornfix
	 * in validate-content gebruikt, en hij vangt precies wat je met de hand
	 * gebouwde markup kunt aandoen — een commentaar dat niet sluit, een div te
	 * veel, een blok dat de parser niet herkent. Slaagt de rondgang niet, dan
	 * komt er geen markup uit maar een fout.
	 *
	 * @param string $slug    Het recept.
	 * @param array  $input   De inhoud.
	 * @param int    $post_id De doelpost.
	 * @param array  $bezet   De bezette uniqueIDs, als sleutels van de array.
	 *
	 * @return array|WP_Error
	 */
	public static function bouw( $slug, $input, $post_id, $bezet = array() ) {
		self::$notities = array();

		$boom = ( 'custom' === $slug )
			? self::boom_uit_invoer( isset( $input['tree'] ) ? $input['tree'] : array() )
			: self::boom( $slug, $input );

		if ( is_wp_error( $boom ) ) {
			return $boom;
		}

		$gebruikt = $bezet;
		$ruw      = self::markup( $boom, $post_id, $gebruikt );

		$geparsed = Kadence_MCP_Inventory::schoon_blokken( parse_blocks( $ruw ) );

		if ( empty( $geparsed ) ) {
			return new WP_Error(
				'kadence_mcp_build_unparsable',
				__( 'De gebouwde markup levert geen blokken op bij het parsen. Dat is een fout in de plug-in, niet in je invoer.', 'mcp-abilities-kadence' )
			);
		}

		$markup = Kadence_MCP_Inventory::serialiseer( $geparsed );

		// De rondgang: opnieuw parsen en opnieuw serialiseren moet exact
		// hetzelfde opleveren. Doet het dat niet, dan is de markup niet stabiel
		// en zou hij bij de eerstvolgende opslag door WordPress veranderen.
		$opnieuw = Kadence_MCP_Inventory::serialiseer( parse_blocks( $markup ) );

		if ( $opnieuw !== $markup ) {
			return new WP_Error(
				'kadence_mcp_build_unstable',
				__( 'De gebouwde markup overleeft een parse- en serialiseerronde niet ongewijzigd. Dat betekent dat WordPress hem bij het opslaan zou herschrijven, en dan klopt wat je ziet niet met wat er staat. Er wordt niets teruggegeven.', 'mcp-abilities-kadence' )
			);
		}

		$nieuwe = array_values( array_diff( array_keys( $gebruikt ), array_keys( $bezet ) ) );

		return array(
			'markup'     => $markup,
			'unique_ids' => $nieuwe,
			'blokken'    => self::tel_blokken( $geparsed ),
			'notities'   => self::$notities,
		);
	}

	/**
	 * Tel de blokken per type.
	 *
	 * @param array $blokken De blokken.
	 * @param array $telling De telling.
	 *
	 * @return array
	 */
	private static function tel_blokken( $blokken, $telling = array() ) {
		foreach ( $blokken as $blok ) {
			if ( empty( $blok['blockName'] ) ) {
				continue;
			}

			$naam             = $blok['blockName'];
			$telling[ $naam ] = isset( $telling[ $naam ] ) ? $telling[ $naam ] + 1 : 1;

			if ( ! empty( $blok['innerBlocks'] ) ) {
				$telling = self::tel_blokken( $blok['innerBlocks'], $telling );
			}
		}

		return $telling;
	}

	/**
	 * Zet een aangeleverde boom om in interne knopen.
	 *
	 * De vier recepten dekken vier vormen. Een echt ontwerp heeft er meer: een
	 * hero met een label boven de kop, een sectie met vijf gekleurde balken,
	 * drie kolommen waarvan er één een afwijkende achtergrond heeft. Die kun je
	 * niet met een vast recept bouwen. Sinds 1.7.0 kan insert-blocks met
	 * position inside ook iets in een bestaande container plaatsen, maar wat
	 * bij elkaar hoort bouw je in één keer.
	 *
	 * Wat hier niet verandert is de rest van de keten: de uniqueIDs worden nog
	 * steeds hier uitgedeeld, de markup komt nog steeds uit de blokprofielen, en de
	 * rondgang door parse_blocks() en serialize_blocks() blijft de eindcontrole.
	 *
	 * Elk attribuut wordt getoetst zoals validate-write dat doet. Een naam die
	 * niet bestaat of een waarde die niet bij het type past wordt geweigerd, en
	 * niet stil weggeschreven.
	 *
	 * @param array $knopen De aangeleverde boom.
	 * @param int   $diepte Beveiliging tegen te diepe nesting.
	 *
	 * @return array|WP_Error
	 */
	public static function boom_uit_invoer( $knopen, $diepte = 0 ) {
		if ( $diepte > 6 ) {
			return new WP_Error(
				'kadence_mcp_tree_too_deep',
				__( 'De boom is meer dan zes niveaus diep. Dat is bijna zeker niet de bedoeling; een sectie is doorgaans rij, kolom, inhoud.', 'mcp-abilities-kadence' )
			);
		}

		if ( ! is_array( $knopen ) ) {
			return new WP_Error( 'kadence_mcp_tree_invalid', __( 'De boom moet een lijst van knopen zijn.', 'mcp-abilities-kadence' ) );
		}

		$uit = array();

		foreach ( $knopen as $knoop ) {
			if ( ! is_array( $knoop ) || empty( $knoop['block'] ) ) {
				return new WP_Error(
					'kadence_mcp_tree_no_block',
					__( 'Elke knoop heeft een block nodig, bijvoorbeeld kadence/rowlayout.', 'mcp-abilities-kadence' )
				);
			}

			$bloknaam = (string) $knoop['block'];

			if ( ! Kadence_MCP_Profielen::bekend( $bloknaam ) ) {
				return new WP_Error(
					'kadence_mcp_tree_unknown_block',
					sprintf(
						/* translators: 1: block name, 2: comma separated block names. */
						__( 'Van "%1$s" is niet bekend hoe hij zijn markup wegschrijft, dus hij kan niet gebouwd worden. Bekend zijn: %2$s. Voor andere blokken is duplicate-blocks de weg — dan komt de markup uit de editor en wordt er niets verzonnen.', 'mcp-abilities-kadence' ),
						$bloknaam,
						implode( ', ', Kadence_MCP_Profielen::bloknamen() )
					)
				);
			}

			$attrs = isset( $knoop['attrs'] ) && is_array( $knoop['attrs'] ) ? $knoop['attrs'] : array();

			foreach ( $attrs as $naam => $waarde ) {
				if ( 'uniqueID' === $naam ) {
					return new WP_Error(
						'kadence_mcp_tree_own_id',
						__( 'Geef geen uniqueID mee. Die worden hier uitgedeeld op grond van wat er al in de doelpost staat; een eigen ID zou kunnen botsen.', 'mcp-abilities-kadence' )
					);
				}

				if ( in_array( $naam, Kadence_MCP_Inventory::CORE_ATTRIBUTEN, true ) ) {
					continue;
				}

				$definitie = Kadence_MCP_Inventory::attribuut_definitie( $bloknaam, $naam );

				if ( null === $definitie ) {
					return new WP_Error(
						'kadence_mcp_tree_unknown_attribute',
						sprintf(
							/* translators: 1: attribute, 2: block name. */
							__( 'Het attribuut "%1$s" bestaat niet op %2$s. Controleer de spelling met describe-block; namen zijn hoofdlettergevoelig.', 'mcp-abilities-kadence' ),
							$naam,
							$bloknaam
						)
					);
				}

				$fout = Kadence_MCP_Inventory::toets_waarde( $definitie, $waarde );

				if ( '' !== $fout ) {
					return new WP_Error(
						'kadence_mcp_tree_bad_value',
						sprintf(
							/* translators: 1: attribute, 2: block name, 3: reason. */
							__( '"%1$s" op %2$s: %3$s', 'mcp-abilities-kadence' ),
							$naam,
							$bloknaam,
							$fout
						)
					);
				}
			}

			$kinderen = array();

			if ( ! empty( $knoop['children'] ) ) {
				$kinderen = self::boom_uit_invoer( $knoop['children'], $diepte + 1 );

				if ( is_wp_error( $kinderen ) ) {
					return $kinderen;
				}
			}

			$tekst = isset( $knoop['text'] ) ? (string) $knoop['text'] : '';
			$tag   = isset( $knoop['tag'] ) ? strtolower( (string) $knoop['tag'] ) : 'p';

			if ( ! in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span' ), true ) ) {
				return new WP_Error(
					'kadence_mcp_tree_bad_tag',
					sprintf(
						/* translators: %s: the given tag. */
						__( 'Tag "%s" is niet toegestaan. Kies h1 tot en met h6, p, div of span.', 'mcp-abilities-kadence' ),
						$tag
					)
				);
			}

			if ( ! empty( $kinderen ) && '' !== trim( $tekst ) ) {
				return new WP_Error(
					'kadence_mcp_tree_text_and_children',
					sprintf(
						/* translators: %s: block name. */
						__( 'Knoop "%s" heeft zowel tekst als kindblokken. Dat kan niet: de tekst zou de kinderen overschrijven.', 'mcp-abilities-kadence' ),
						$bloknaam
					)
				);
			}

			// De tag moet meelopen met level, anders rendert Kadence iets anders
			// dan er in de markup staat. Zie de opmerking bij htmlTag hieronder.
			if ( 'kadence/advancedheading' === $bloknaam ) {
				$attrs = self::stem_tag_af( $attrs, $tag );
			}

			$uit[] = self::knoop( $bloknaam, $attrs, $kinderen, $tekst, $tag );
		}

		return $uit;
	}

	/**
	 * Zorg dat de gerenderde tag overeenkomt met de tag in de markup.
	 *
	 * advancedheading bepaalt zijn tag uit twee attributen. htmlTag staat
	 * standaard op "heading", en dan valt hij terug op level (standaard 2).
	 * Staat htmlTag op iets anders — p, div, span — dan wint dat.
	 *
	 * Schrijf je in de markup een h3 maar laat je level op 2 staan, dan rendert
	 * Kadence een h2 terwijl de opgeslagen HTML een h3 bevat. Dat is precies de
	 * stille mismatch die de blokvalidatie van Gutenberg later opmerkt.
	 *
	 * @param array  $attrs De attributen.
	 * @param string $tag   De gevraagde tag.
	 *
	 * @return array
	 */
	private static function stem_tag_af( $attrs, $tag ) {
		if ( in_array( $tag, array( 'p', 'div', 'span' ), true ) ) {
			$attrs['htmlTag'] = $tag;
			unset( $attrs['level'] );

			return $attrs;
		}

		$attrs['level'] = (int) substr( $tag, 1 );
		unset( $attrs['htmlTag'] );

		return $attrs;
	}

	/**
	 * Bouw de markup van EEN blok, met een uniqueID dat je zelf meegeeft.
	 *
	 * markup() deelt altijd een nieuw uniqueID uit — dat klopt voor nieuwe
	 * secties, maar niet voor een blok dat al bestaat en dat alleen opnieuw
	 * opgebouwd moet worden. Daar is het behouden van het ID juist het punt:
	 * er hangen verwijzingen aan vanuit post meta die geen enkele controle
	 * meevolgt.
	 *
	 * Kinderen komen hier binnen als GEPARSEERDE blokken, niet als knopen —
	 * ze bestaan al en worden ongewijzigd doorgegeven.
	 *
	 * @param string $bloknaam De bloknaam.
	 * @param array  $attrs    De attributen, inclusief uniqueID.
	 * @param array  $kinderen Geparseerde kindblokken.
	 * @param string $tekst    De tekst, voor tekstblokken.
	 * @param string $tag      De HTML-tag.
	 *
	 * @return array|WP_Error
	 */
	public static function bouw_een_blok( $bloknaam, $attrs, $kinderen = array(), $tekst = '', $tag = 'p' ) {
		$vorm = Kadence_MCP_Profielen::van( $bloknaam );

		if ( null === $vorm ) {
			return new WP_Error(
				'kadence_mcp_build_unknown_block',
				sprintf(
					/* translators: %s: block name. */
					__( 'Van "%s" is niet bekend hoe hij zijn markup wegschrijft.', 'mcp-abilities-kadence' ),
					$bloknaam
				)
			);
		}

		$unique_id = isset( $attrs['uniqueID'] ) ? (string) $attrs['uniqueID'] : '';

		if ( '' === $unique_id ) {
			return new WP_Error( 'kadence_mcp_build_no_id', __( 'Er is geen uniqueID meegegeven om in de markup te zetten.', 'mcp-abilities-kadence' ) );
		}

		// Zelfde reden als in markup(): de standaard is 2, dus een rij zonder
		// columns rekent met twee sleuven en zet de inhoud op halve breedte.
		if ( 'kadence/rowlayout' === $bloknaam ) {
			$attrs['columns'] = count( $kinderen );

			if ( empty( $attrs['colLayout'] ) ) {
				$attrs['colLayout'] = 'equal';
			}
		}

		if ( '' !== (string) $vorm['aantal_kinderen'] ) {
			$attrs[ $vorm['aantal_kinderen'] ] = count( $kinderen );
		}

		$attrs = self::vul_kbversion_aan( $bloknaam, $attrs );

		$genormaliseerd = Kadence_MCP_Inventory::normaliseer_attributen( $bloknaam, $attrs );
		$json           = wp_json_encode( (object) $genormaliseerd['attrs'] );

		$leeg_en_zonder_wrapper = empty( $kinderen )
			&& '' === trim( (string) $tekst )
			&& '' === (string) $vorm['open']
			&& '' === (string) $vorm['sluit'];

		if ( ! empty( $vorm['zelfsluitend'] ) || $leeg_en_zonder_wrapper ) {
			$ruw = '<!-- wp:' . $bloknaam . ' ' . $json . ' /-->';
		} else {
			$klassen = Kadence_MCP_Profielen::klassen( $bloknaam, $genormaliseerd['attrs'] );
			$zoek    = array_merge( array( '{ID}', '{TAG}' ), array_keys( $klassen ) );
			$vervang = array_merge( array( $unique_id, $tag ), array_values( $klassen ) );

			$open  = self::vul_attributen( str_replace( $zoek, $vervang, $vorm['open'] ), $bloknaam, $genormaliseerd['attrs'] );
			$sluit = self::vul_attributen( str_replace( $zoek, $vervang, $vorm['sluit'] ), $bloknaam, $genormaliseerd['attrs'] );

			$binnenin = '';

			if ( ! empty( $kinderen ) ) {
				$binnenin = "\n" . Kadence_MCP_Inventory::serialiseer( $kinderen ) . "\n";
			} elseif ( '' !== $tekst ) {
				$binnenin = Kadence_MCP_Inventory::schoon_tekst( $tekst );
			}

			$ruw  = '<!-- wp:' . $bloknaam . ' ' . $json . ' -->' . "\n";
			$ruw .= $open . $binnenin . $sluit . "\n";
			$ruw .= '<!-- /wp:' . $bloknaam . ' -->';
		}

		$geparsed = Kadence_MCP_Inventory::schoon_blokken( parse_blocks( $ruw ) );

		if ( empty( $geparsed ) ) {
			return new WP_Error( 'kadence_mcp_build_unparsable', __( 'De gebouwde markup levert geen blok op bij het parsen.', 'mcp-abilities-kadence' ) );
		}

		$markup  = Kadence_MCP_Inventory::serialiseer( $geparsed );
		$opnieuw = Kadence_MCP_Inventory::serialiseer( parse_blocks( $markup ) );

		// Dezelfde rondgang als bouw(): overleeft de markup een parse- en
		// serialiseerronde niet ongewijzigd, dan zou WordPress hem bij het
		// opslaan herschrijven en klopt wat je ziet niet met wat er staat.
		if ( $opnieuw !== $markup ) {
			return new WP_Error( 'kadence_mcp_build_unstable', __( 'De opnieuw opgebouwde markup overleeft een parse- en serialiseerronde niet ongewijzigd. Er wordt niets teruggegeven.', 'mcp-abilities-kadence' ) );
		}

		// Het uniqueID moet er na het parsen nog staan. Valt hij weg door de
		// normalisatie, dan zou de vervanging het blok losknippen van alles wat
		// ernaar verwijst.
		if ( ! isset( $geparsed[0]['attrs']['uniqueID'] ) || $geparsed[0]['attrs']['uniqueID'] !== $unique_id ) {
			return new WP_Error(
				'kadence_mcp_build_id_lost',
				sprintf(
					/* translators: %s: uniqueID. */
					__( 'Het uniqueID "%s" staat na het opbouwen niet meer in het blok. Er wordt niets teruggegeven.', 'mcp-abilities-kadence' ),
					$unique_id
				)
			);
		}

		return array(
			'markup' => $markup,
			'blok'   => $geparsed[0],
		);
	}

	/**
	 * Wat de generator bij het bouwen heeft rechtgezet, voor het antwoord.
	 *
	 * @var array
	 */
	public static $notities = array();

	/**
	 * Zet de ruimte tussen de kinderen van een Sectie zo, dat Kadence hem ook
	 * gebruikt.
	 *
	 * Kadence leest gutter en rowGap alleen als gutterVariable en
	 * rowGapVariable op dezelfde plek "custom" zijn; anders geldt een preset en
	 * wordt het getal stil genegeerd (Kadence_Blocks_CSS::render_row_gap).
	 * Daarnaast is gutter de ruimte NAAST elkaar: in een verticale Sectie doet
	 * hij niets zichtbaars, en daar is rowGap wat bedoeld wordt.
	 *
	 * Rechtgezet wordt alleen wat eenduidig is: een getal zonder zijn
	 * *Variable krijgt "custom", en gutter in een verticale Sectie zonder
	 * rowGap wordt rowGap. Wat de gebruiker zelf al expliciet zette blijft
	 * staan. Elke correctie komt terug in notes.
	 *
	 * @param array $attrs De attributen van de Sectie.
	 *
	 * @return array
	 */
	private static function zet_ruimte_goed( $attrs ) {
		$richting = isset( $attrs['direction'][0] ) && '' !== $attrs['direction'][0] ? (string) $attrs['direction'][0] : 'vertical';
		$naam     = isset( $attrs['metadata']['name'] ) ? (string) $attrs['metadata']['name'] : 'Sectie';
		$heeft    = static function ( $waarde ) {
			return is_array( $waarde ) && array_filter( $waarde, 'is_numeric' );
		};

		if ( 0 === strpos( $richting, 'vertical' ) && $heeft( isset( $attrs['gutter'] ) ? $attrs['gutter'] : null ) && ! $heeft( isset( $attrs['rowGap'] ) ? $attrs['rowGap'] : null ) ) {
			$attrs['rowGap'] = $attrs['gutter'];
			unset( $attrs['gutter'] );

			if ( isset( $attrs['gutterVariable'] ) ) {
				unset( $attrs['gutterVariable'] );
			}

			self::$notities[] = sprintf(
				/* translators: %s: section name. */
				__( '%s is verticaal: gutter is daar de ruimte naast elkaar en doet niets zichtbaars. Omgezet naar rowGap.', 'mcp-abilities-kadence' ),
				$naam
			);
		}

		foreach ( array( 'gutter' => 'gutterVariable', 'rowGap' => 'rowGapVariable' ) as $getal => $variabel ) {
			if ( ! $heeft( isset( $attrs[ $getal ] ) ? $attrs[ $getal ] : null ) ) {
				continue;
			}

			$stand     = isset( $attrs[ $variabel ] ) && is_array( $attrs[ $variabel ] ) ? $attrs[ $variabel ] : array( '', '', '' );
			$aangevuld = false;

			foreach ( array( 0, 1, 2 ) as $i ) {
				if ( isset( $attrs[ $getal ][ $i ] ) && is_numeric( $attrs[ $getal ][ $i ] ) && ( ! isset( $stand[ $i ] ) || '' === $stand[ $i ] ) ) {
					$stand[ $i ] = 'custom';
					$aangevuld   = true;
				}
			}

			if ( $aangevuld ) {
				$attrs[ $variabel ] = array_values( array_replace( array( '', '', '' ), $stand ) );

				self::$notities[] = sprintf(
					/* translators: 1: section name, 2: attribute, 3: companion attribute. */
					__( '%1$s: %3$s op "custom" gezet waar %2$s een getal heeft; zonder dat gebruikt Kadence een preset en negeert het getal.', 'mcp-abilities-kadence' ),
					$naam,
					$getal,
					$variabel
				);
			}
		}

		return $attrs;
	}

	/**
	 * Vul kbVersion aan als het blok dat attribuut kent en het ontbreekt.
	 *
	 * Dit is geen verfraaiing maar een noodzaak. kbVersion bepaalt welke
	 * rendertak Kadence neemt; ontbreekt hij op een rowlayout, dan schrijft
	 * Kadence de wrapper .kb-row-layout-wrap NIET en valt de hele kolomindeling
	 * weg. Wat je dan ziet is een pagina waarop alles onder elkaar staat over
	 * de volle breedte, terwijl de blokken er gewoon zijn en elke controle
	 * groen gaat: de markup is geldig, de blokken staan in de post, de CSS
	 * wordt zelfs gegenereerd — alleen de wrapper waar die CSS op hangt
	 * bestaat niet.
	 *
	 * De ingebouwde recepten zetten kbVersion al mee. De vrije boom van
	 * generate-section deed dat niet, en dat was een valkuil voor wie een eigen
	 * structuur opgeeft: het verschil is onzichtbaar tot je de pagina opent.
	 *
	 * @param string $bloknaam De bloknaam.
	 * @param array  $attrs    De attributen.
	 *
	 * @return array
	 */
	private static function vul_kbversion_aan( $bloknaam, $attrs ) {
		if ( array_key_exists( 'kbVersion', $attrs ) ) {
			return $attrs;
		}

		// Alleen als het blok dit attribuut echt kent. Zo hoeft er nergens een
		// lijst bijgehouden te worden die kan verouderen.
		if ( null === Kadence_MCP_Inventory::attribuut_definitie( $bloknaam, 'kbVersion' ) ) {
			return $attrs;
		}

		// Wat de editor bij dit blok schrijft. Meestal 2, maar niet altijd: de
		// Advanced Slider krijgt 3. Staat het in het profiel, dan geldt dat.
		$profiel            = Kadence_MCP_Profielen::van( $bloknaam );
		$attrs['kbVersion'] = ( null !== $profiel && ! empty( $profiel['kbversion'] ) ) ? (int) $profiel['kbversion'] : 2;

		return $attrs;
	}
	/**
	 * Vul {ATTR:naam}-plaatshouders uit de attributen van het blok.
	 *
	 * Sommige blokken zetten een attribuutwaarde LETTERLIJK in hun opgeslagen
	 * markup, niet alleen in het blokcommentaar. kadence/single-icon is daar het
	 * duidelijkste voorbeeld: de naam van het pictogram staat als data-name op
	 * een lege span, en zonder die span rendert Kadence niets. Met alleen {ID}
	 * en {TAG} was zo'n blok niet uit te drukken, en dus ook niet te bouwen.
	 *
	 * De waarde gaat door esc_attr(): hij belandt in een HTML-attribuut, en een
	 * aanhalingsteken erin zou de markup openbreken. Ontbreekt het attribuut,
	 * dan valt hij terug op de standaardwaarde uit block.json — precies wat
	 * Kadence zelf ook zou opslaan.
	 *
	 * @param string $markup   De open- of sluittag uit het profiel.
	 * @param string $bloknaam De bloknaam, voor de standaardwaarden.
	 * @param array  $attrs    De genormaliseerde attributen.
	 *
	 * @return string
	 */
	private static function vul_attributen( $markup, $bloknaam, $attrs ) {
		if ( false === strpos( $markup, '{ATTR:' ) ) {
			return $markup;
		}

		return preg_replace_callback(
			'/\{ATTR:([A-Za-z0-9_]+)\}/',
			static function ( $treffer ) use ( $bloknaam, $attrs ) {
				$naam = $treffer[1];

				if ( array_key_exists( $naam, $attrs ) ) {
					return esc_attr( is_scalar( $attrs[ $naam ] ) ? (string) $attrs[ $naam ] : '' );
				}

				$definitie = Kadence_MCP_Inventory::attribuut_definitie( $bloknaam, $naam );

				return ( is_array( $definitie ) && isset( $definitie['default'] ) && is_scalar( $definitie['default'] ) )
					? esc_attr( (string) $definitie['default'] )
					: '';
			},
			$markup
		);
	}


}
