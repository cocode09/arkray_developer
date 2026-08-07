<?php
/**
 * Theme UI strings (header, navigation, footer) via Polylang string translations.
 *
 * These labels live outside post content — they are rendered through arkray_t()
 * from the child theme — so the page export alone never picks them up.
 *
 * @package ArkrayTranslationImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Export and import shared theme chrome strings registered with Polylang.
 */
class Arkray_TI_Strings {

	/**
	 * ID / note path prefix for every theme-UI row.
	 */
	const PREFIX = 'theme-ui';

	/**
	 * Polylang string group used by the child theme.
	 */
	const CONTEXT = 'ARKRAY Theme';

	/**
	 * Homepage chrome strings only (header → nav → sidebars → footer → copyright).
	 *
	 * Ordered to mirror the site's own navigation, with each sidebar sub-item
	 * listed right under the nav entry it belongs to (e.g. Products, then its
	 * three category names, then History of Pioneers, then its four items),
	 * so the exported sheet reads top-to-bottom the same way the menu does.
	 *
	 * 404, search and other Polylang theme strings are intentionally left out
	 * of the Excel export.
	 *
	 * @return string[]
	 */
	public static function priority_strings() {
		return array(
			// Header.
			'Vietnam site',
			'Select',

			// Main navigation, each entry followed by its own sidebar sub-items.
			'News & Topics',
			'Products',
			'Diabetes Testing',
			'Urinalysis / Urine Testing',
			'Osmolality',
			'History of Pioneers',
			'Diabetes testing',
			'Urinalysis',
			'Dry Chemistry Testing',
			'BGM',
			'Events & Gallery',
			'Events',
			'Local',
			'Media Gallery',
			'About Us',
			'ARKRAY Philosophy',
			'Message from ARKRAY',
			'Brand Concept',
			'Contact',
			'Corporate Outline',
			'History',
			'ARKRAY Group',
			'Download Company Profile [PDF]',
			'Sustainability',
			'Top Commitment',
			'SDGs Basic Policy',
			'ARKRAY’s Materiality',
			'SDGs Initiatives',
			'Recruitment',

			// Shared content labels used across pages.
			'more',
			'Line up',
			'Download for more information',

			// Footer.
			'Privacy Policy',
			'Website Terms of Use',
			'Site Map',
			'Contact Us',
			'Copyright© %s ARKRAY, Inc. All Rights Reserved.',
		);
	}

	/**
	 * Whether a normalised CSV row belongs to the theme-UI section.
	 *
	 * @param array $row Canonical row from Arkray_TI_Importer::normalize_row().
	 * @return bool
	 */
	public static function is_theme_row( array $row ) {
		$id = isset( $row['block_id'] ) ? strtolower( (string) $row['block_id'] ) : '';
		if ( 0 === strpos( $id, self::PREFIX . '-' ) ) {
			return true;
		}

		$type = isset( $row['type'] ) ? strtolower( (string) $row['type'] ) : '';
		return 'string' === $type;
	}

	/**
	 * Whether the row is the section title (not a string to translate).
	 *
	 * @param array $row Canonical row.
	 * @return bool
	 */
	public static function is_section_title( array $row ) {
		$id = isset( $row['block_id'] ) ? strtolower( (string) $row['block_id'] ) : '';
		return self::PREFIX . '-title' === $id;
	}

	/**
	 * Stable slug for a source string, unique within the catalog.
	 *
	 * @param string   $string Source English string.
	 * @param string[] $used   Slugs already handed out.
	 * @return string
	 */
	public static function string_slug( $string, array &$used ) {
		$slug = sanitize_title( (string) $string );
		if ( '' === $slug ) {
			$slug = 'str-' . substr( md5( (string) $string ), 0, 10 );
		}
		if ( isset( $used[ $slug ] ) ) {
			$slug .= '-' . substr( md5( (string) $string ), 0, 6 );
		}
		$used[ $slug ] = true;

		return $slug;
	}

	/**
	 * Theme UI strings included in the Excel export / accepted on import.
	 *
	 * Limited to {@see priority_strings()} — not every Polylang ARKRAY Theme
	 * string (404 page, search UI, sidebar extras, etc. stay out).
	 *
	 * @return string[] Unique source strings.
	 */
	public static function catalog() {
		return array_values( array_unique( self::priority_strings() ) );
	}

	/**
	 * Existing translation for a source string in $target, or '' when unset / identical.
	 *
	 * @param string $string Source English string.
	 * @param string $target Target language slug.
	 * @return string
	 */
	public static function existing_translation( $string, $target ) {
		// Polylang registers PLL_MO with its Composer autoloader, so it isn't
		// necessarily loaded yet here — allow autoloading (no `false` third
		// state) instead of requiring it to already be defined.
		if ( ! function_exists( 'pll_translate_string' ) || ! class_exists( 'PLL_MO' ) ) {
			return '';
		}

		$lang = function_exists( 'PLL' ) ? PLL()->model->get_language( $target ) : false;
		if ( empty( $lang ) ) {
			return '';
		}

		$mo = new PLL_MO();
		$mo->import_from_db( $lang );
		$translated = $mo->translate_if_any( (string) $string );

		return (string) $translated;
	}

	/**
	 * Excel rows for the theme-UI section: separator, title, then one row per string.
	 *
	 * @param string $target Target language slug.
	 * @return array[] Rows shaped like Arkray_TI_Admin::export_rows_for_post().
	 */
	public static function export_rows( $target ) {
		$catalog = self::catalog();
		if ( empty( $catalog ) ) {
			return array();
		}

		$fields = array(
			'scope'   => '',
			'is_new'  => '',
			'country' => '',
		);
		$used  = array();
		$rows  = array();

		$separator    = array_fill( 0, count( Arkray_TI_Admin::COLUMNS ), '' );
		$separator[3] = '【' . __( 'Theme UI (header / navigation / footer)', 'arkray-translation-importer' ) . '】';
		$rows[]       = array(
			'cells' => $separator,
			'style' => 'separator',
		);

		foreach ( $catalog as $string ) {
			$slug = self::string_slug( $string, $used );
			$rows[] = array(
				'cells' => Arkray_TI_Admin::export_row_public(
					array(
						'id'         => self::PREFIX . '-' . $slug,
						'type'       => 'string',
						'label'      => __( 'Theme UI string', 'arkray-translation-importer' ),
						'english'    => $string,
						'vietnamese' => self::existing_translation( $string, $target ),
						'note'       => self::PREFIX . ': ' . $string,
					),
					$fields
				),
				'style' => 'highlight',
			);
		}

		return $rows;
	}

	/**
	 * Write translated theme UI strings into Polylang's string table.
	 *
	 * @param array[] $items   Each item: array( 'line' => int, 'row' => canonical row ).
	 * @param string  $target  Target language slug.
	 * @param bool    $dry_run When true, only report what would change.
	 * @return array[] Result rows for the admin report (same shape as importer results).
	 */
	public static function import( array $items, $target, $dry_run ) {
		$results = array();

		if ( empty( $items ) ) {
			return $results;
		}

		// Polylang registers PLL_MO with its Composer autoloader, so it isn't
		// necessarily loaded yet here — allow autoloading (no `false` third
		// state) instead of requiring it to already be defined.
		if ( ! class_exists( 'PLL_MO' ) || ! function_exists( 'PLL' ) ) {
			foreach ( $items as $item ) {
				$results[] = self::result(
					$item['line'],
					$item['row']['block_id'],
					'error',
					__( 'Polylang string translations are not available.', 'arkray-translation-importer' )
				);
			}
			return $results;
		}

		$language = PLL()->model->get_language( $target );
		if ( empty( $language ) ) {
			foreach ( $items as $item ) {
				$results[] = self::result(
					$item['line'],
					$item['row']['block_id'],
					'error',
					sprintf(
						/* translators: %s: language slug */
						__( 'Unknown target language "%s".', 'arkray-translation-importer' ),
						$target
					)
				);
			}
			return $results;
		}

		$registered = array();
		foreach ( self::catalog() as $string ) {
			$registered[ $string ] = true;
		}

		$mo = new PLL_MO();
		$mo->import_from_db( $language );
		$changed = 0;

		foreach ( $items as $item ) {
			$line = (int) $item['line'];
			$row  = $item['row'];
			$id   = $row['block_id'];

			if ( self::is_section_title( $row ) ) {
				continue;
			}

			$english = (string) $row['english_content'];
			$viet    = (string) $row['vietnamese_content'];

			if ( '' === $english ) {
				$english = self::english_from_note( $row['note'] );
			}

			if ( '' === $english ) {
				$results[] = self::result(
					$line,
					$id,
					'error',
					__( 'Theme UI row has no English source string.', 'arkray-translation-importer' )
				);
				continue;
			}

			if ( ! isset( $registered[ $english ] ) ) {
				// Accept strings that match a registered entry after trim.
				$match = self::match_registered( $english, array_keys( $registered ) );
				if ( null === $match ) {
					$results[] = self::result(
						$line,
						$id,
						'error',
						sprintf(
							/* translators: %s: English source string */
							__( 'Unknown theme UI string: "%s".', 'arkray-translation-importer' ),
							$english
						)
					);
					continue;
				}
				$english = $match;
			}

			if ( '' === $viet ) {
				$results[] = self::result(
					$line,
					$id,
					'skipped',
					__( 'The translated content cell is empty.', 'arkray-translation-importer' )
				);
				continue;
			}

			$previous = $mo->translate_if_any( $english );
			if ( $previous === $viet ) {
				$results[] = self::result(
					$line,
					$id,
					'skipped',
					__( 'Theme UI string already has this translation.', 'arkray-translation-importer' )
				);
				continue;
			}

			if ( $dry_run ) {
				$results[] = self::result(
					$line,
					$id,
					'' === $previous ? 'created' : 'updated',
					sprintf(
						/* translators: %s: English source string */
						__( 'Would save theme UI translation for "%s".', 'arkray-translation-importer' ),
						$english
					)
				);
				continue;
			}

			$mo->add_entry( $mo->make_entry( $english, $viet ) );
			++$changed;
			$results[] = self::result(
				$line,
				$id,
				'' === $previous ? 'created' : 'updated',
				sprintf(
					/* translators: %s: English source string */
					__( 'Saved theme UI translation for "%s".', 'arkray-translation-importer' ),
					$english
				)
			);
		}

		if ( ! $dry_run && $changed > 0 ) {
			$mo->export_to_db( $language );
			/**
			 * Fires after theme UI string translations are written by this plugin.
			 */
			do_action( 'pll_save_strings_translations' );
		}

		return $results;
	}

	/**
	 * Pull the English source out of a "theme-ui: …" note when the English column is empty.
	 *
	 * @param string $note Note column.
	 * @return string
	 */
	private static function english_from_note( $note ) {
		$note = (string) $note;
		if ( preg_match( '#^' . preg_quote( self::PREFIX, '#' ) . '\s*:\s*(.+)$#u', $note, $matches ) ) {
			$english = trim( $matches[1] );
			if ( 'section' !== strtolower( $english ) ) {
				return $english;
			}
		}
		return '';
	}

	/**
	 * Find a registered string equal to $english, ignoring surrounding whitespace.
	 *
	 * @param string   $english Candidate.
	 * @param string[] $list    Registered source strings.
	 * @return string|null
	 */
	private static function match_registered( $english, array $list ) {
		$needle = trim( $english );
		foreach ( $list as $candidate ) {
			if ( trim( $candidate ) === $needle ) {
				return $candidate;
			}
		}
		return null;
	}

	/**
	 * One admin-report result row.
	 *
	 * @param int    $line    Spreadsheet line.
	 * @param string $source  Row ID.
	 * @param string $action  created|updated|skipped|error.
	 * @param string $message Details.
	 * @return array
	 */
	private static function result( $line, $source, $action, $message ) {
		return array(
			'line'      => (int) $line,
			'post_type' => 'string',
			'source'    => (string) $source,
			'action'    => $action,
			'message'   => $message,
			'edit_link' => '',
		);
	}
}
