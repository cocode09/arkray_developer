<?php
/**
 * Core translation importer: creates or updates translated posts from CSV rows
 * and links them to their originals through Polylang.
 *
 * @package ArkrayTranslationImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Processes parsed CSV rows.
 *
 * The exported CSV carries one row per block of a page — the title, each
 * paragraph, heading, list item, table cell and image — and holds plain text
 * instead of HTML:
 *
 * - ID                  Unique key of the block, e.g. `policy-h01` or
 *                       `ha-8190v-tbl1-r2-c1`. It says which page and which part
 *                       of it the text belongs to, so it must not be edited.
 * - Parent ID           The block this one sits under, for reading the file as a
 *                       tree. Only used to find the page of a row whose ID
 *                       follows none of the known patterns.
 * - 種別                Post type of the page (reference only).
 * - タイトル/箇所        What the block is (reference only).
 * - Global/Local        Term of news_category / event_type.
 * - NEW                 New-arrival flag of a news post or event.
 * - 国コード             Country of an event, for its flag.
 * - English             The original text (reference only, ignored on import).
 * - Vietnamese          The translated text. Written into the block.
 * - English img         File name of the original image (reference only).
 * - Vietnamese img      File name to use in the translation instead.
 * - English caption     Alt text of the original image (reference only).
 * - Vietnamese caption  Alt text to use in the translation.
 * - 箇所・メモ           Where the block is on the page, written as
 *                       "page path: position". The part in front of the colon is
 *                       how a row says which page it belongs to.
 *
 * The page of a row is looked up in this order: the path in the note column, the
 * part of the ID in front of the position (the slug of the page), and finally the
 * page of its parent or of the row above it. Files that still carry the earlier
 * `page_id` and `page_slug` columns are read from those instead.
 *
 * Two earlier layouts are still read as well: a file with a `content_id` column
 * instead of block IDs, and the original whole-page file where
 * `vietnamese_content` holds the complete HTML of the translation.
 */
class Arkray_TI_Importer {

	/**
	 * One row per block, addressed by ID.
	 */
	const FORMAT_BLOCK = 'block';

	/**
	 * One row per piece of text, addressed by position.
	 */
	const FORMAT_SEGMENT = 'segment';

	/**
	 * One row per page, holding the complete HTML.
	 */
	const FORMAT_PAGE = 'page';

	/**
	 * Canonical column name for every accepted header spelling.
	 *
	 * Headers are compared after {@see normalize_header()}, which lower-cases
	 * them, drops any parenthesised gloss and turns separators into underscores.
	 * The `id` header is resolved by {@see canonical_column()} instead, because
	 * it means the block in the current format and the post in the older ones.
	 */
	const COLUMN_ALIASES = array(
		// Which post the row belongs to.
		'page_id'            => 'id',
		'post_id'            => 'id',
		'en_id'              => 'id',
		'page_slug'          => 'slug',
		'slug'               => 'slug',
		'post_slug'          => 'slug',
		'en_slug'            => 'slug',

		// Which block of that post.
		'block_id'           => 'block_id',
		'parent_id'          => 'parent_id',
		'親id'               => 'parent_id',
		'content_id'         => 'content_id',
		'contentid'          => 'content_id',
		'content_no'         => 'content_id',
		'segment_id'         => 'content_id',
		'segment'            => 'content_id',
		'text_id'            => 'content_id',

		// Text.
		'english'            => 'english_content',
		'english_content'    => 'english_content',
		'english_text'       => 'english_content',
		'en_content'         => 'english_content',
		'content_en'         => 'english_content',
		'英語テキスト'        => 'english_content',
		'vietnamese'         => 'vietnamese_content',
		'vietnamese_content' => 'vietnamese_content',
		'vietnamese_text'    => 'vietnamese_content',
		'vietnam_content'    => 'vietnamese_content',
		'vietnam'            => 'vietnamese_content',
		'vi_content'         => 'vietnamese_content',
		'content_vi'         => 'vietnamese_content',
		'translated_content' => 'vietnamese_content',
		'ベトナム語テキスト'   => 'vietnamese_content',

		// Images.
		'english_img'        => 'english_img',
		'english_image'      => 'english_img',
		'en_img'             => 'english_img',
		'vietnamese_img'     => 'vietnamese_img',
		'vietnamese_image'   => 'vietnamese_img',
		'vietnam_img'        => 'vietnamese_img',
		'vi_img'             => 'vietnamese_img',
		'english_caption'    => 'english_caption',
		'en_caption'         => 'english_caption',
		'vietnamese_caption' => 'vietnamese_caption',
		'vietnam_caption'    => 'vietnamese_caption',
		'vi_caption'         => 'vietnamese_caption',

		// Page level fields and notes.
		'種別'                => 'type',
		'type'               => 'type',
		'post_type'          => 'type',
		'タイトル_箇所'        => 'label',
		'label'              => 'label',
		'title_location'     => 'label',
		'global_local'       => 'scope',
		'scope'              => 'scope',
		'new'                => 'is_new',
		'is_new'             => 'is_new',
		'国コード'            => 'country',
		'country'            => 'country',
		'country_code'       => 'country',
		'箇所_メモ'           => 'note',
		'note'               => 'note',
		'memo'               => 'note',
		'remarks'            => 'note',
	);

	/**
	 * Headers that only the block format has, used to recognise it.
	 */
	const BLOCK_ONLY_COLUMNS = array(
		'block_id',
		'parent_id',
		'english_img',
		'vietnamese_img',
		'english_caption',
		'vietnamese_caption',
		'label',
		'note',
		'type',
		'scope',
		'is_new',
		'country',
	);

	const ELEMENTOR_META_KEYS = array(
		'_elementor_data',
		'_elementor_edit_mode',
		'_elementor_template_type',
		'_elementor_version',
		'_elementor_page_settings',
	);

	/**
	 * Target language slug (e.g. 'vietnamese').
	 *
	 * @var string
	 */
	private $target_lang;

	/**
	 * Source language slug (e.g. 'english').
	 *
	 * @var string
	 */
	private $source_lang;

	/**
	 * Behaviour options.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Layout of the CSV being processed.
	 *
	 * @var string
	 */
	private $format = self::FORMAT_PAGE;

	/**
	 * Per-page results.
	 *
	 * @var array[]
	 */
	private $results = array();

	/**
	 * Successfully processed pairs used for the parent-fix pass.
	 *
	 * @var array[]
	 */
	private $processed = array();

	/**
	 * Media library lookups already done in this run, file name => image data.
	 *
	 * @var array
	 */
	private $media_cache = array();

	/**
	 * Constructor.
	 *
	 * @param string $target_lang Target language slug.
	 * @param string $source_lang Source language slug.
	 * @param array  $options {
	 *     Optional behaviour flags, all default true. Applied when creating translations.
	 *
	 *     @type bool $copy_thumbnail Copy the featured image from the original.
	 *     @type bool $copy_template  Copy the page template from the original.
	 *     @type bool $copy_elementor Copy Elementor layout meta from the original.
	 *     @type bool $copy_terms     Assign the target-language translations of the original's terms.
	 * }
	 */
	public function __construct( $target_lang, $source_lang, array $options = array() ) {
		$this->target_lang = (string) $target_lang;
		$this->source_lang = (string) $source_lang;
		$this->options     = wp_parse_args(
			$options,
			array(
				'copy_thumbnail' => true,
				'copy_template'  => true,
				'copy_elementor' => true,
				'copy_terms'     => true,
			)
		);
	}

	/**
	 * Where the page level fields of the CSV are stored, per post type.
	 *
	 * @return array[] Post type => taxonomy, new_meta, country_meta.
	 */
	public static function post_type_fields() {
		return (array) apply_filters(
			'arkray_ti_post_type_fields',
			array(
				'news'  => array(
					'taxonomy'     => 'news_category',
					'new_meta'     => 'news_is_new',
					'country_meta' => '',
				),
				'event' => array(
					'taxonomy'     => 'event_type',
					'new_meta'     => 'event_is_new',
					'country_meta' => 'event_location',
				),
			)
		);
	}

	/**
	 * Reduce a raw CSV header to a comparable key.
	 *
	 * "English（英語テキスト）" and "english" both become `english`, and
	 * "Parent ID" becomes `parent_id`.
	 *
	 * @param string $header Raw header.
	 * @return string
	 */
	public static function normalize_header( $header ) {
		$header = trim( (string) $header );
		$header = preg_replace( '#（[^）]*）#u', '', $header );
		$header = preg_replace( '#\([^)]*\)#', '', $header );
		$header = strtolower( trim( (string) $header ) );
		$header = preg_replace( '#[\s\-/_・:：]+#u', '_', $header );

		return trim( (string) $header, '_' );
	}

	/**
	 * Map a raw CSV header to its canonical column name.
	 *
	 * @param string $header Raw header.
	 * @param string $format Layout the file is being read as.
	 * @return string Canonical name or '' when unrecognized.
	 */
	public static function canonical_column( $header, $format = self::FORMAT_PAGE ) {
		$key = self::normalize_header( $header );

		if ( 'id' === $key ) {
			return self::FORMAT_BLOCK === $format ? 'block_id' : 'id';
		}

		return isset( self::COLUMN_ALIASES[ $key ] ) ? self::COLUMN_ALIASES[ $key ] : '';
	}

	/**
	 * Recognise which layout a file uses from its headers.
	 *
	 * @param string[] $headers CSV headers.
	 * @return string One of the FORMAT_* constants.
	 */
	public static function detect_format( array $headers ) {
		// The `id` header is left out on purpose: it means the block in the
		// current layout and the post in the older ones, so it says nothing
		// about which layout this is.
		$canonical = array();
		foreach ( $headers as $header ) {
			$key = self::normalize_header( $header );
			if ( isset( self::COLUMN_ALIASES[ $key ] ) ) {
				$canonical[] = self::COLUMN_ALIASES[ $key ];
			}
		}

		if ( array_intersect( $canonical, self::BLOCK_ONLY_COLUMNS ) ) {
			return self::FORMAT_BLOCK;
		}

		return in_array( 'content_id', $canonical, true ) ? self::FORMAT_SEGMENT : self::FORMAT_PAGE;
	}

	/**
	 * Column names in $headers that the importer will ignore.
	 *
	 * @param string[] $headers CSV headers.
	 * @param string   $format  Layout the file is read as; detected when omitted.
	 * @return string[]
	 */
	public static function ignored_columns( array $headers, $format = '' ) {
		if ( '' === $format ) {
			$format = self::detect_format( $headers );
		}

		$ignored = array();
		foreach ( $headers as $header ) {
			if ( '' === self::canonical_column( $header, $format ) ) {
				$ignored[] = $header;
			}
		}
		return $ignored;
	}

	/**
	 * Process all rows.
	 *
	 * @param array[]  $rows    Associative rows from Arkray_TI_Csv::read().
	 * @param bool     $dry_run When true nothing is written; actions are only reported.
	 * @param string[] $headers CSV headers; derived from the rows when omitted.
	 * @return array {
	 *     @type array[] $rows    Per-page results: line, post_type, source, action, message, edit_link.
	 *     @type array   $summary Counts keyed by action.
	 *     @type string  $format  Layout the file was read as.
	 * }
	 */
	public function run( array $rows, $dry_run, array $headers = array() ) {
		$this->results   = array();
		$this->processed = array();

		if ( empty( $headers ) && ! empty( $rows ) ) {
			$headers = array_keys( (array) reset( $rows ) );
		}
		$this->format = self::detect_format( $headers );

		if ( self::FORMAT_BLOCK === $this->format ) {
			foreach ( Arkray_TI_Strings::import( $this->theme_string_items( $rows ), $this->target_lang, (bool) $dry_run ) as $result ) {
				$this->results[] = $result;
			}
		}

		foreach ( $this->group_rows( $rows ) as $group ) {
			$this->process_group( $group, (bool) $dry_run );
		}

		if ( ! $dry_run ) {
			$this->fix_parents();
		}

		usort(
			$this->results,
			static function ( $a, $b ) {
				return $a['line'] - $b['line'];
			}
		);

		$summary = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'error'   => 0,
		);
		foreach ( $this->results as $result ) {
			if ( isset( $summary[ $result['action'] ] ) ) {
				$summary[ $result['action'] ]++;
			}
		}

		return array(
			'rows'    => $this->results,
			'summary' => $summary,
			'format'  => $this->format,
		);
	}

	/**
	 * Theme-UI string rows from a block-format file, with spreadsheet line numbers.
	 *
	 * @param array[] $rows Associative rows from the spreadsheet.
	 * @return array[] Each item: array( 'line' => int, 'row' => canonical row ).
	 */
	private function theme_string_items( array $rows ) {
		$items = array();

		foreach ( $rows as $index => $raw ) {
			$row = $this->normalize_row( (array) $raw );
			if ( '' === $row['block_id'] || ! Arkray_TI_Strings::is_theme_row( $row ) ) {
				continue;
			}
			$items[] = array(
				'line' => $index + 2, // +2: 1-based plus header row.
				'row'  => $row,
			);
		}

		return $items;
	}

	/**
	 * Re-key a raw CSV row to the canonical column names.
	 *
	 * @param array $row Row keyed by raw headers.
	 * @return array Row keyed by canonical names.
	 */
	private function normalize_row( array $row ) {
		$normalized = array(
			'id'                 => '',
			'slug'               => '',
			'block_id'           => '',
			'parent_id'          => '',
			'content_id'         => '',
			'english_content'    => '',
			'vietnamese_content' => '',
			'english_img'        => '',
			'vietnamese_img'     => '',
			'english_caption'    => '',
			'vietnamese_caption' => '',
			'type'               => '',
			'label'              => '',
			'scope'              => '',
			'is_new'             => '',
			'country'            => '',
			'note'               => '',
		);

		foreach ( $row as $header => $value ) {
			$canonical = self::canonical_column( $header, $this->format );
			if ( '' !== $canonical && '' === $normalized[ $canonical ] ) {
				$normalized[ $canonical ] = trim( (string) $value );
			}
		}

		return $normalized;
	}

	/**
	 * Collect the rows belonging to each page.
	 *
	 * Rows of the same page are recognised by the part of their ID in front of
	 * the position, which is the slug of the page. Which post that is comes from
	 * the note column first, because it holds the full path of the page.
	 *
	 * @param array[] $rows Associative rows from Arkray_TI_Csv::read().
	 * @return array[] Groups keyed by page, in the order they appear.
	 */
	private function group_rows( array $rows ) {
		$groups     = array();
		$page_of    = array(); // Block ID => group key, so a child can find its page.
		$last_group = null;

		foreach ( $rows as $index => $raw ) {
			$line = $index + 2; // +2: 1-based plus header row.
			$row  = $this->normalize_row( (array) $raw );

			if ( self::FORMAT_BLOCK === $this->format && '' === $row['block_id'] ) {
				continue; // Section heading or spacer row.
			}

			// Shared theme labels are imported separately into Polylang strings.
			if ( self::FORMAT_BLOCK === $this->format && Arkray_TI_Strings::is_theme_row( $row ) ) {
				continue;
			}

			list( $prefix, $block ) = self::split_block_id( $row['block_id'] );

			$key = $this->page_key( $row, $prefix, $page_of, $last_group );
			if ( '' === $key ) {
				$this->add_result(
					$line,
					'',
					$row['block_id'],
					'error',
					__( 'This row does not say which page it belongs to, and no row above it does either.', 'arkray-translation-importer' )
				);
				continue;
			}

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'line'           => $line,
					'prefix'         => $prefix,
					'id'             => $row['id'],
					'slug'           => '' !== $row['slug'] ? $row['slug'] : self::path_from_note( $row['note'] ),
					'title'          => '',
					'texts'          => array(),
					'segments'       => array(),
					'images'         => array(),
					'scope'          => '',
					'is_new'         => '',
					'country'        => '',
					'unknown'        => array(),
					'warnings'       => array(),
					'legacy_content' => '',
				);
			} elseif ( '' === $groups[ $key ]['slug'] ) {
				$groups[ $key ]['slug'] = '' !== $row['slug'] ? $row['slug'] : self::path_from_note( $row['note'] );
			}

			if ( '' !== $row['block_id'] ) {
				$page_of[ $row['block_id'] ] = $key;
			}
			$last_group = $key;

			$this->collect_row( $groups[ $key ], $row, $block );
		}

		return $groups;
	}

	/**
	 * Work out which page a row belongs to.
	 *
	 * @param array       $row        Canonicalized row.
	 * @param string      $prefix     Page part of the row's ID.
	 * @param array       $page_of    Group key of every block ID seen so far.
	 * @param string|null $last_group Group key of the row above.
	 * @return string Group key, or '' when the page cannot be told.
	 */
	private function page_key( array $row, $prefix, array $page_of, $last_group ) {
		if ( '' !== $row['id'] ) {
			return 'id:' . $row['id'];
		}
		if ( '' !== $row['slug'] ) {
			return 'slug:' . strtolower( $row['slug'] );
		}
		if ( '' !== $prefix ) {
			return 'prefix:' . $prefix;
		}

		$path = self::path_from_note( $row['note'] );
		if ( '' !== $path ) {
			return 'slug:' . strtolower( $path );
		}

		if ( '' !== $row['parent_id'] && isset( $page_of[ $row['parent_id'] ] ) ) {
			return $page_of[ $row['parent_id'] ];
		}

		return null === $last_group ? '' : $last_group;
	}

	/**
	 * Fall back to the ID prefix when no column and no note named the page.
	 *
	 * The export builds the prefix from the slug of the page, and from its post
	 * ID when the slug holds no plain letters or digits.
	 *
	 * @param array $group Grouped rows.
	 * @return array
	 */
	private static function resolve_page( array $group ) {
		if ( '' !== $group['id'] || '' !== $group['slug'] ) {
			return $group;
		}

		if ( preg_match( '#^post-([0-9]+)$#', $group['prefix'], $matches ) ) {
			$group['id'] = $matches[1];
		} else {
			$group['slug'] = $group['prefix'];
		}

		return $group;
	}

	/**
	 * The page path a note column starts with, as in "about/profile: row 1".
	 *
	 * @param string $note Note cell.
	 * @return string
	 */
	private static function path_from_note( $note ) {
		$note = trim( (string) $note );
		if ( '' === $note ) {
			return '';
		}

		if ( ! preg_match( '#^([a-z0-9][a-z0-9_./-]*)\s*[:：]#i', $note, $matches ) ) {
			return '';
		}

		return trim( $matches[1], '/' );
	}

	/**
	 * Add one CSV row to its page group.
	 *
	 * @param array  $group Group to extend, by reference.
	 * @param array  $row   Canonicalized row.
	 * @param string $block Position part of the row's ID.
	 * @return void
	 */
	private function collect_row( array &$group, array $row, $block ) {
		if ( self::FORMAT_PAGE === $this->format ) {
			if ( '' === $group['legacy_content'] ) {
				$group['legacy_content'] = $row['vietnamese_content'];
			}
			return;
		}

		if ( self::FORMAT_SEGMENT === $this->format ) {
			if ( '' === $row['content_id'] || '' === $row['vietnamese_content'] ) {
				return;
			}
			if ( ! preg_match( '#^[0-9]+$#', $row['content_id'] ) || 0 === (int) $row['content_id'] ) {
				$group['unknown'][] = $row['content_id'];
				return;
			}
			$group['segments'][ (int) $row['content_id'] ] = $row['vietnamese_content'];
			return;
		}

		foreach ( array( 'scope', 'is_new', 'country' ) as $field ) {
			if ( '' === $group[ $field ] && '' !== $row[ $field ] ) {
				$group[ $field ] = $row[ $field ];
			}
		}

		if ( '' === $block ) {
			$group['unknown'][] = $row['block_id'];
			return;
		}

		if ( 'title' === $block ) {
			if ( '' !== $row['vietnamese_content'] ) {
				$group['title'] = $row['vietnamese_content'];
			}
			return;
		}

		if ( 0 === strpos( $block, 'img' ) ) {
			if ( '' !== $row['vietnamese_img'] || '' !== $row['vietnamese_caption'] ) {
				$group['images'][ $block ] = array(
					'file'    => $row['vietnamese_img'],
					'caption' => $row['vietnamese_caption'],
				);
			}
			if ( '' !== $row['vietnamese_content'] ) {
				$group['warnings'][] = sprintf(
					/* translators: %s: block ID */
					__( 'Text given for image row %s was ignored; images only carry a file name and a caption.', 'arkray-translation-importer' ),
					$row['block_id']
				);
			}
			return;
		}

		if ( '' !== $row['vietnamese_content'] ) {
			$group['texts'][ $block ] = $row['vietnamese_content'];
		}
	}

	/**
	 * Split a CSV ID into the page it belongs to and the position on that page.
	 *
	 * `ha-8190v-tbl1-r2-c1` becomes `ha-8190v` and `tbl1-r2-c1`; `policy-title`
	 * becomes `policy` and `title`. An ID that follows none of the known
	 * patterns yields two empty strings.
	 *
	 * @param string $block_id Value of the ID column.
	 * @return array array( string $prefix, string $block )
	 */
	private static function split_block_id( $block_id ) {
		$block_id = strtolower( trim( (string) $block_id ) );
		if ( '' === $block_id ) {
			return array( '', '' );
		}

		$pattern = '#(?:^|-)('
			. 'title'
			. '|p[0-9]+|h[0-9]+|li[0-9]+|cap[0-9]+|img[0-9]+'
			. '|tbl[0-9]+-r[0-9]+-c[0-9]+'
			. '|tbl[0-9]+-caption'
			. ')(-[0-9]+)?$#';

		if ( ! preg_match( $pattern, $block_id, $matches, PREG_OFFSET_CAPTURE ) ) {
			return array( '', '' );
		}

		$block = $matches[1][0];
		if ( isset( $matches[2] ) && '' !== $matches[2][0] ) {
			$block .= $matches[2][0];
		}

		return array( rtrim( substr( $block_id, 0, $matches[1][1] ), '-' ), $block );
	}

	/**
	 * Process the rows of one page.
	 *
	 * @param array $group   Grouped rows.
	 * @param bool  $dry_run Dry-run flag.
	 * @return void
	 */
	private function process_group( array $group, $dry_run ) {
		$group      = self::resolve_page( $group );
		$line       = $group['line'];
		$source_ref = '' !== $group['id'] ? '#' . $group['id'] : $group['slug'];

		$source = $this->find_source_post( $group );
		if ( is_wp_error( $source ) ) {
			// A page that has been thrown away is a normal state of affairs, not
			// a fault in the file, so it is reported as skipped.
			$action = 'arkray_ti_in_trash' === $source->get_error_code() ? 'skipped' : 'error';
			$this->add_result( $line, '', $source_ref, $action, $source->get_error_message() );
			return;
		}

		$messages = array();
		$title    = '';
		$content  = null;

		if ( self::FORMAT_PAGE === $this->format ) {
			if ( '' === $group['legacy_content'] ) {
				$this->add_result( $line, $source->post_type, $source_ref, 'skipped', __( 'The translated content cell is empty.', 'arkray-translation-importer' ) );
				return;
			}
			$content = $group['legacy_content'];
		} else {
			$title  = $group['title'];
			$result = $this->build_translated_content( $source, $group, $messages );

			if ( is_wp_error( $result ) ) {
				$this->add_result( $line, $source->post_type, $source_ref, 'error', $result->get_error_message() );
				return;
			}

			$content = $result;

			// The page level fields ride along with a translation; on their own
			// they must not turn an untranslated page into a translated one.
			if ( '' === $title && null === $content ) {
				$this->add_result(
					$line,
					$source->post_type,
					$source_ref,
					'skipped',
					trim( __( 'Nothing was translated for this page.', 'arkray-translation-importer' ) . ' ' . implode( ' ', $messages ) )
				);
				return;
			}
		}

		$trashed   = false;
		$target_id = $this->translation_id( $source, $trashed );

		if ( $trashed ) {
			$messages[] = __( 'The translation that existed is in the trash; it was left there and a new one was made.', 'arkray-translation-importer' );
		}

		if ( $target_id ) {
			$result = $this->update_translation( $target_id, $source, $content, $title, $dry_run, $messages );
		} else {
			$result = $this->create_translation( $source, $content, $title, $dry_run, $messages );
		}

		if ( is_wp_error( $result ) ) {
			$this->add_result( $line, $source->post_type, $source_ref, 'error', $result->get_error_message() );
			return;
		}

		list( $action, $post_id ) = $result;

		if ( $post_id && ! $dry_run ) {
			$this->apply_page_fields( $post_id, $source, $group, $messages );

			$this->processed[] = array(
				'source_id' => (int) $source->ID,
				'target_id' => (int) $post_id,
				'post_type' => $source->post_type,
			);
		}

		$messages = array_merge( $messages, $group['warnings'] );

		$edit_link = ( $post_id && ! $dry_run ) ? get_edit_post_link( $post_id, 'raw' ) : '';
		$this->add_result( $line, $source->post_type, $source_ref, $action, implode( ' ', $messages ), $edit_link );
	}

	/**
	 * Put the translated text and images of a group back into the original markup.
	 *
	 * The markup always comes from the original page, so the translation stays
	 * structurally identical to it. Blocks left blank in the CSV keep what the
	 * existing translation has, as long as it still has the same blocks;
	 * otherwise they keep the original text.
	 *
	 * @param WP_Post  $source   Original post.
	 * @param array    $group    Grouped rows.
	 * @param string[] $messages Accumulates informational messages.
	 * @return string|null|WP_Error Content for the translation, or null when the
	 *                              page content needs no change.
	 */
	private function build_translated_content( WP_Post $source, array $group, array &$messages ) {
		$wanted_texts  = $group['texts'];
		$wanted_images = $group['images'];
		$unknown       = $group['unknown'];

		$blocks = Arkray_TI_Blocks::extract( $source->post_content );

		if ( self::FORMAT_SEGMENT === $this->format ) {
			$wanted_texts = self::texts_by_position( $blocks, $group['segments'], $unknown );
		}

		if ( empty( $wanted_texts ) && empty( $wanted_images ) ) {
			if ( ! empty( $unknown ) ) {
				$messages[] = $this->unknown_message( $unknown );
			}
			return null;
		}

		if ( empty( $blocks ) ) {
			return new WP_Error(
				'arkray_ti_no_blocks',
				__( 'The content of the original page holds no text or images, so there is nothing to translate in it. Text kept outside the content, for example in a page builder layout, cannot be imported.', 'arkray-translation-importer' )
			);
		}

		$texts_by_key  = Arkray_TI_Blocks::by_key( $blocks, 'text' );
		$images_by_key = Arkray_TI_Blocks::by_key( $blocks, 'image' );

		$texts  = array();
		$images = array();

		foreach ( $wanted_texts as $key => $text ) {
			if ( isset( $texts_by_key[ $key ] ) ) {
				$texts[ $key ] = $text;
			} else {
				$unknown[] = $key;
			}
		}

		foreach ( $wanted_images as $key => $wanted ) {
			if ( ! isset( $images_by_key[ $key ] ) ) {
				$unknown[] = $key;
				continue;
			}
			$image = $this->resolve_image( $wanted, $images_by_key[ $key ], $messages );
			if ( ! empty( $image ) ) {
				$images[ $key ] = $image;
			}
		}

		$translated_count = count( $texts );
		$swapped_count    = count( $images );

		$this->keep_existing_translation( $source, $blocks, $texts, $images );

		if ( $translated_count ) {
			$messages[] = sprintf(
				/* translators: 1: number of translated blocks, 2: number of text blocks on the page */
				__( '%1$d of %2$d texts taken from the file.', 'arkray-translation-importer' ),
				$translated_count,
				count( $texts_by_key )
			);
		}
		if ( $swapped_count ) {
			$messages[] = sprintf(
				/* translators: %d: number of images changed */
				_n( '%d image updated.', '%d images updated.', $swapped_count, 'arkray-translation-importer' ),
				$swapped_count
			);
		}
		if ( ! empty( $unknown ) ) {
			$messages[] = $this->unknown_message( $unknown );
		}

		return Arkray_TI_Blocks::apply( $source->post_content, $texts, $images );
	}

	/**
	 * Turn the position based cells of the older segment format into block keys.
	 *
	 * @param array[] $blocks    Blocks of the original.
	 * @param array   $segments  Text keyed by 1-based position.
	 * @param array   $unknown   Collects positions that do not exist, by reference.
	 * @return array Text keyed by block key.
	 */
	private static function texts_by_position( array $blocks, array $segments, array &$unknown ) {
		$texts    = array();
		$position = 0;

		foreach ( $blocks as $block ) {
			if ( 'text' !== $block['kind'] ) {
				continue;
			}
			++$position;
			if ( isset( $segments[ $position ] ) ) {
				$texts[ $block['key'] ] = $segments[ $position ];
				unset( $segments[ $position ] );
			}
		}

		foreach ( array_keys( $segments ) as $position ) {
			$unknown[] = (string) $position;
		}

		return $texts;
	}

	/**
	 * Carry over what the current translation already says for the blocks the
	 * CSV does not mention, so a partial file does not undo earlier work.
	 *
	 * @param WP_Post $source Original post.
	 * @param array[] $blocks Blocks of the original.
	 * @param array   $texts  Text replacements, by reference.
	 * @param array   $images Image replacements, by reference.
	 * @return void
	 */
	private function keep_existing_translation( WP_Post $source, array $blocks, array &$texts, array &$images ) {
		$target_id = $this->translation_id( $source );
		if ( ! $target_id ) {
			return;
		}

		$target = get_post( $target_id );
		if ( ! $target instanceof WP_Post ) {
			return;
		}

		$current = Arkray_TI_Blocks::extract( $target->post_content );

		// Only comparable while both versions are built the same way.
		if ( Arkray_TI_Blocks::keys( $current ) !== Arkray_TI_Blocks::keys( $blocks ) ) {
			return;
		}

		$originals = Arkray_TI_Blocks::by_key( $blocks );

		foreach ( $current as $block ) {
			$key = $block['key'];

			if ( 'text' === $block['kind'] ) {
				if ( '' !== $block['text'] && ! isset( $texts[ $key ] ) ) {
					$texts[ $key ] = $block['text'];
				}
				continue;
			}

			if ( isset( $images[ $key ] ) || ! isset( $originals[ $key ] ) ) {
				continue;
			}

			$image = array();
			if ( '' !== $block['src'] && $block['file'] !== $originals[ $key ]['file'] ) {
				$image['src'] = $block['src'];
			}
			if ( '' !== $block['caption'] && $block['caption'] !== $originals[ $key ]['caption'] ) {
				$image['alt'] = $block['caption'];
			}
			if ( ! empty( $image ) ) {
				$images[ $key ] = $image;
			}
		}
	}

	/**
	 * Work out what to write into an image block.
	 *
	 * A file name is looked up in the media library first; when it is not there,
	 * a file of that name sitting next to the original image is accepted.
	 *
	 * @param array    $wanted   File name and caption from the CSV.
	 * @param array    $block    Image block of the original.
	 * @param string[] $messages Accumulates informational messages.
	 * @return array src, alt, width, height, attachment_id — only what changes.
	 */
	private function resolve_image( array $wanted, array $block, array &$messages ) {
		$image = array();

		if ( '' !== $wanted['caption'] && $wanted['caption'] !== $block['caption'] ) {
			$image['alt'] = $wanted['caption'];
		}

		$file = $wanted['file'];
		if ( '' === $file || $file === $block['file'] ) {
			return $image;
		}

		$found = $this->find_media( $file );
		if ( ! empty( $found ) ) {
			return array_merge( $image, $found );
		}

		$sibling = self::sibling_url( $block['src'], $file );
		if ( '' !== $sibling ) {
			$image['src'] = $sibling;
			return $image;
		}

		$messages[] = sprintf(
			/* translators: 1: image file name, 2: block ID */
			__( 'The image "%1$s" is not in the media library, so %2$s keeps the original image.', 'arkray-translation-importer' ),
			$file,
			$block['key']
		);

		return $image;
	}

	/**
	 * Find an attachment by file name.
	 *
	 * @param string $file File name without a path.
	 * @return array src, width, height, attachment_id; empty when not found.
	 */
	private function find_media( $file ) {
		if ( isset( $this->media_cache[ $file ] ) ) {
			return $this->media_cache[ $file ];
		}

		global $wpdb;

		$attachment_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = '_wp_attached_file' AND ( meta_value = %s OR meta_value LIKE %s )
				ORDER BY post_id DESC LIMIT 1",
				$file,
				'%/' . $wpdb->esc_like( $file )
			)
		);

		$image = array();
		if ( $attachment_id ) {
			$source = wp_get_attachment_image_src( $attachment_id, 'full' );
			if ( is_array( $source ) && ! empty( $source[0] ) ) {
				$image = array(
					'src'           => $source[0],
					'width'         => isset( $source[1] ) ? (int) $source[1] : 0,
					'height'        => isset( $source[2] ) ? (int) $source[2] : 0,
					'attachment_id' => $attachment_id,
				);
			}
		}

		$this->media_cache[ $file ] = $image;

		return $image;
	}

	/**
	 * URL of a file that sits in the same folder as the original image.
	 *
	 * @param string $src  Source of the original image.
	 * @param string $file File name to put there instead.
	 * @return string Empty when there is no such file.
	 */
	private static function sibling_url( $src, $file ) {
		$src = (string) $src;
		if ( '' === $src ) {
			return '';
		}

		$parts = wp_parse_url( $src );
		$path  = isset( $parts['path'] ) ? $parts['path'] : '';
		if ( '' === $path ) {
			return '';
		}

		$path = '/' === substr( $path, -1 ) ? $path . $file : preg_replace( '#[^/]+$#', $file, $path );

		$uploads = wp_get_upload_dir();
		$base    = (string) wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );
		if ( '' === $base || 0 !== strpos( $path, $base ) ) {
			return '';
		}

		if ( ! file_exists( $uploads['basedir'] . substr( $path, strlen( $base ) ) ) ) {
			return '';
		}

		$prefix = '';
		if ( ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) ) {
			$prefix = $parts['scheme'] . '://' . $parts['host'];
			if ( ! empty( $parts['port'] ) ) {
				$prefix .= ':' . $parts['port'];
			}
		}

		return $prefix . $path;
	}

	/**
	 * Message listing IDs that could not be placed.
	 *
	 * @param string[] $unknown IDs or positions.
	 * @return string
	 */
	private function unknown_message( array $unknown ) {
		return sprintf(
			/* translators: %s: comma separated list of IDs */
			__( 'Ignored rows whose ID is not on this page: %s.', 'arkray-translation-importer' ),
			implode( ', ', array_slice( array_unique( $unknown ), 0, 20 ) )
		);
	}

	/**
	 * Write the page level fields of the CSV to the translation.
	 *
	 * @param int      $post_id  Translation post ID.
	 * @param WP_Post  $source   Original post.
	 * @param array    $group    Grouped rows.
	 * @param string[] $messages Accumulates informational messages.
	 * @return void
	 */
	private function apply_page_fields( $post_id, WP_Post $source, array $group, array &$messages ) {
		$map = self::post_type_fields();
		if ( ! isset( $map[ $source->post_type ] ) ) {
			return;
		}

		$fields = wp_parse_args(
			$map[ $source->post_type ],
			array(
				'taxonomy'     => '',
				'new_meta'     => '',
				'country_meta' => '',
			)
		);

		if ( '' !== $group['scope'] && '' !== $fields['taxonomy'] ) {
			$this->assign_scope_term( $post_id, $fields['taxonomy'], $group['scope'], $messages );
		}

		if ( '' !== $group['is_new'] && '' !== $fields['new_meta'] ) {
			update_post_meta( $post_id, $fields['new_meta'], self::is_truthy( $group['is_new'] ) ? 1 : 0 );
		}

		if ( '' !== $group['country'] && '' !== $fields['country_meta'] ) {
			update_post_meta( $post_id, $fields['country_meta'], $group['country'] );
		}
	}

	/**
	 * Give the translation the Global/Local term named in the CSV.
	 *
	 * @param int      $post_id  Translation post ID.
	 * @param string   $taxonomy Taxonomy name.
	 * @param string   $scope    Term slug or name from the CSV.
	 * @param string[] $messages Accumulates informational messages.
	 * @return void
	 */
	private function assign_scope_term( $post_id, $taxonomy, $scope, array &$messages ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$term = get_term_by( 'slug', sanitize_title( $scope ), $taxonomy );
		if ( ! $term instanceof WP_Term ) {
			$term = get_term_by( 'name', $scope, $taxonomy );
		}

		if ( ! $term instanceof WP_Term ) {
			$messages[] = sprintf(
				/* translators: 1: value from the CSV, 2: taxonomy name */
				__( 'The Global/Local value "%1$s" is not a term of %2$s and was ignored.', 'arkray-translation-importer' ),
				$scope,
				$taxonomy
			);
			return;
		}

		$term_id = (int) $term->term_id;

		if ( function_exists( 'pll_is_translated_taxonomy' ) && function_exists( 'pll_get_term' ) && pll_is_translated_taxonomy( $taxonomy ) ) {
			$translated = (int) pll_get_term( $term_id, $this->target_lang );
			if ( $translated ) {
				$term_id = $translated;
			}
		}

		wp_set_object_terms( $post_id, array( $term_id ), $taxonomy );
	}

	/**
	 * Read a flag cell such as the NEW column.
	 *
	 * @param string $value Cell value.
	 * @return bool
	 */
	private static function is_truthy( $value ) {
		$value = strtolower( trim( (string) $value ) );

		return ! in_array( $value, array( '', '0', 'no', 'false', '-', '×', 'なし' ), true );
	}

	/**
	 * The translation of a post in the target language.
	 *
	 * A translation sitting in the trash counts as no translation: it is left
	 * where it is instead of being quietly revived and written to.
	 *
	 * @param WP_Post $source    Original post.
	 * @param bool    $in_trash  Set to true when a translation exists but is in
	 *                           the trash, by reference.
	 * @return int Post ID, or 0.
	 */
	private function translation_id( WP_Post $source, &$in_trash = false ) {
		$in_trash  = false;
		$target_id = (int) pll_get_post( $source->ID, $this->target_lang );
		if ( ! $target_id ) {
			return 0;
		}

		$target = get_post( $target_id );
		if ( ! $target instanceof WP_Post ) {
			return 0;
		}

		if ( 'trash' === $target->post_status ) {
			$in_trash = true;
			return 0;
		}

		return $target_id;
	}

	/**
	 * Look for a post with this slug in the trash.
	 *
	 * Used to tell "the page was thrown away" apart from "the file names a page
	 * that never existed". WordPress may add "__trashed" to the slug when a post
	 * is trashed, so both spellings are tried.
	 *
	 * @param string $slug Slug or page path from the file.
	 * @return WP_Post|null
	 */
	private static function find_trashed_post( $slug ) {
		$name = sanitize_title( basename( $slug ) );

		foreach ( array( $name, $name . '__trashed' ) as $candidate ) {
			$found = get_posts(
				array(
					'post_type'        => 'any',
					'post_status'      => 'trash',
					'name'             => $candidate,
					'numberposts'      => 1,
					'lang'             => '',
					'suppress_filters' => false,
				)
			);

			if ( ! empty( $found ) ) {
				return $found[0];
			}
		}

		return null;
	}

	/**
	 * Locate the original (source-language) post for a group by id or slug.
	 *
	 * @param array $row Group or canonicalized row carrying id and slug.
	 * @return WP_Post|WP_Error
	 */
	private function find_source_post( array $row ) {
		$post = null;

		if ( '' !== $row['id'] ) {
			$post = get_post( (int) $row['id'] );
			if ( ! $post instanceof WP_Post ) {
				return new WP_Error(
					'arkray_ti_not_found',
					sprintf( __( 'No post found with ID %s.', 'arkray-translation-importer' ), $row['id'] )
				);
			}
		} elseif ( '' !== $row['slug'] ) {
			$slug = trim( $row['slug'], "/ \t" );

			// Pages first: a full path like 'about/philosophy' resolves exactly.
			$post = get_page_by_path( $slug, OBJECT, 'page' );

			if ( ! $post instanceof WP_Post ) {
				$name       = sanitize_title( basename( $slug ) );
				$candidates = get_posts(
					array(
						'post_type'        => 'any',
						'name'             => $name,
						'post_status'      => array( 'publish', 'draft', 'private', 'pending', 'future' ),
						'numberposts'      => 10,
						'lang'             => '', // All languages; filtered below.
						'suppress_filters' => false,
					)
				);
				foreach ( $candidates as $candidate ) {
					if ( pll_get_post_language( $candidate->ID ) === $this->source_lang ) {
						$post = $candidate;
						break;
					}
				}
				if ( ! $post instanceof WP_Post && ! empty( $candidates ) ) {
					$post = $candidates[0];
				}
			}

			if ( ! $post instanceof WP_Post ) {
				$trashed = self::find_trashed_post( $slug );
				if ( $trashed instanceof WP_Post ) {
					return new WP_Error(
						'arkray_ti_in_trash',
						sprintf(
							/* translators: 1: post title, 2: post ID */
							__( 'The original "%1$s" (#%2$d) is in the trash, so it was left alone. Restore it and import again if it should be translated.', 'arkray-translation-importer' ),
							$trashed->post_title,
							$trashed->ID
						)
					);
				}

				return new WP_Error(
					'arkray_ti_not_found',
					sprintf( __( 'No post found with slug "%s".', 'arkray-translation-importer' ), $slug )
				);
			}
		} else {
			return new WP_Error( 'arkray_ti_no_identifier', __( 'The row has neither an id nor a slug, so the original post cannot be identified.', 'arkray-translation-importer' ) );
		}

		// Checked before anything else about the post: once it is in the trash,
		// nothing else about it is worth reporting.
		if ( 'trash' === $post->post_status ) {
			return new WP_Error(
				'arkray_ti_in_trash',
				sprintf(
					/* translators: 1: post title, 2: post ID */
					__( 'The original "%1$s" (#%2$d) is in the trash, so it was left alone. Restore it and import again if it should be translated.', 'arkray-translation-importer' ),
					$post->post_title,
					$post->ID
				)
			);
		}

		if ( ! pll_is_translated_post_type( $post->post_type ) ) {
			return new WP_Error(
				'arkray_ti_untranslated_type',
				sprintf( __( 'Post type "%s" is not managed by Polylang. Enable it under Languages → Settings → Custom post types and taxonomies.', 'arkray-translation-importer' ), $post->post_type )
			);
		}

		// If the located post is not in the source language, hop to its source-language translation.
		$post_lang = pll_get_post_language( $post->ID );
		if ( $post_lang && $post_lang !== $this->source_lang ) {
			$mapped = pll_get_post( $post->ID, $this->source_lang );
			if ( $mapped ) {
				$post = get_post( $mapped );
			}
		}

		if ( pll_get_post_language( $post->ID ) === $this->target_lang ) {
			return new WP_Error(
				'arkray_ti_wrong_language',
				__( 'The identified post is already in the target language; it cannot be used as the original.', 'arkray-translation-importer' )
			);
		}

		return $post;
	}

	/**
	 * Create a new translation carrying the translated content.
	 *
	 * The excerpt, date, menu order and (optionally) template, featured image,
	 * Elementor layout and terms are copied from the original so the new page is
	 * complete; the title and body come from the CSV where it has them.
	 *
	 * @param WP_Post     $source   Original post.
	 * @param string|null $content  Content for the translation, or null to copy the original.
	 * @param string      $title    Translated title, or '' to copy the original.
	 * @param bool        $dry_run  Dry-run flag.
	 * @param string[]    $messages Accumulates informational messages.
	 * @return array|WP_Error array( 'created', $post_id ). $post_id is 0 in a dry run.
	 */
	private function create_translation( WP_Post $source, $content, $title, $dry_run, array &$messages ) {
		if ( '' === $title ) {
			$messages[] = __( 'Title copied from the original.', 'arkray-translation-importer' );
		}

		if ( $dry_run ) {
			return array( 'created', 0 );
		}

		$postarr = array(
			'post_type'      => $source->post_type,
			'post_status'    => 'publish',
			'post_title'     => '' !== $title ? $title : $source->post_title,
			'post_name'      => $source->post_name,
			'post_content'   => null === $content ? $source->post_content : $content,
			'post_excerpt'   => $source->post_excerpt,
			'post_date'      => $source->post_date,
			'menu_order'     => (int) $source->menu_order,
			'comment_status' => $source->comment_status,
			'ping_status'    => $source->ping_status,
		);

		$post_id = $this->insert_keeping_slug( $postarr );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		pll_set_post_language( $post_id, $this->target_lang );

		$translations                       = (array) pll_get_post_translations( $source->ID );
		$translations[ $this->source_lang ] = (int) $source->ID;
		$translations[ $this->target_lang ] = (int) $post_id;
		pll_save_post_translations( $translations );

		if ( $this->options['copy_template'] && 'page' === $source->post_type ) {
			$template = get_post_meta( $source->ID, '_wp_page_template', true );
			if ( $template && 'default' !== $template ) {
				update_post_meta( $post_id, '_wp_page_template', $template );
			}
		}

		if ( $this->options['copy_thumbnail'] ) {
			$thumbnail_id = (int) get_post_meta( $source->ID, '_thumbnail_id', true );
			if ( $thumbnail_id ) {
				update_post_meta( $post_id, '_thumbnail_id', $thumbnail_id );
			}
		}

		if ( $this->options['copy_elementor'] && get_post_meta( $source->ID, '_elementor_data', true ) ) {
			foreach ( self::ELEMENTOR_META_KEYS as $meta_key ) {
				$meta_value = get_post_meta( $source->ID, $meta_key, true );
				if ( '' !== $meta_value && null !== $meta_value ) {
					update_post_meta( $post_id, $meta_key, $meta_value );
				}
			}
			$messages[] = __( 'Elementor layout copied from the original.', 'arkray-translation-importer' );
		}

		if ( $this->options['copy_terms'] ) {
			$this->copy_translated_terms( $post_id, $source );
		}

		$this->copy_source_meta( $post_id, $source );

		return array( 'created', $post_id );
	}

	/**
	 * Update an existing translation.
	 *
	 * @param int         $target_id Existing translation post ID.
	 * @param WP_Post     $source    Original post.
	 * @param string|null $content   Content for the translation, or null to leave it alone.
	 * @param string      $title     Translated title, or '' to leave it alone.
	 * @param bool        $dry_run   Dry-run flag.
	 * @param string[]    $messages  Accumulates informational messages.
	 * @return array|WP_Error array( 'updated', $post_id ).
	 */
	private function update_translation( $target_id, WP_Post $source, $content, $title, $dry_run, array &$messages ) {
		if ( $dry_run ) {
			return array( 'updated', 0 );
		}

		$postarr = array( 'ID' => $target_id );
		if ( null !== $content ) {
			$postarr['post_content'] = $content;
		}
		if ( '' !== $title ) {
			$postarr['post_title'] = $title;
		}

		// Keep the URL of the translation in step with the original.
		$target = get_post( $target_id );
		if ( $target instanceof WP_Post && '' !== $source->post_name && $target->post_name !== $source->post_name ) {
			$postarr['post_name'] = $source->post_name;
			$messages[]           = sprintf(
				/* translators: 1: new slug, 2: previous slug */
				__( 'Slug changed to "%1$s" to match the original (was "%2$s").', 'arkray-translation-importer' ),
				$source->post_name,
				$target->post_name
			);
		}

		if ( count( $postarr ) > 1 ) {
			$updated = $this->insert_keeping_slug( $postarr );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}

		// Ensure the language and the link to the original are intact.
		if ( pll_get_post_language( $target_id ) !== $this->target_lang ) {
			pll_set_post_language( $target_id, $this->target_lang );
		}
		$translations                       = (array) pll_get_post_translations( $source->ID );
		$translations[ $this->source_lang ] = (int) $source->ID;
		$translations[ $this->target_lang ] = (int) $target_id;
		pll_save_post_translations( $translations );

		return array( 'updated', $target_id );
	}

	/**
	 * Save a post keeping the slug it asks for.
	 *
	 * A translation is given the slug of its original, so that the two share a
	 * URL path under their own language directory. WordPress would otherwise add
	 * a counter to it, since the original already holds that slug.
	 *
	 * @param array $postarr Post data, with an ID to update an existing post.
	 * @return int|WP_Error Post ID.
	 */
	private function insert_keeping_slug( array $postarr ) {
		add_filter( 'wp_unique_post_slug', array( $this, 'keep_requested_slug' ), 10, 6 );

		$result = empty( $postarr['ID'] )
			? wp_insert_post( wp_slash( $postarr ), true )
			: wp_update_post( wp_slash( $postarr ), true );

		remove_filter( 'wp_unique_post_slug', array( $this, 'keep_requested_slug' ), 10 );

		return $result;
	}

	/**
	 * Hand back the slug that was asked for, counter and all.
	 *
	 * Only hooked while this class saves a post, see insert_keeping_slug().
	 *
	 * @param string $slug          Slug WordPress settled on.
	 * @param int    $post_id       Post ID.
	 * @param string $post_status   Post status.
	 * @param string $post_type     Post type.
	 * @param int    $post_parent   Parent post ID.
	 * @param string $original_slug Slug that was asked for.
	 * @return string
	 */
	public function keep_requested_slug( $slug, $post_id, $post_status, $post_type, $post_parent, $original_slug ) {
		return '' !== (string) $original_slug ? (string) $original_slug : $slug;
	}

	/**
	 * Copy the meta a listing template needs to show the translation at all,
	 * such as the date a news post or event is sorted by.
	 *
	 * @param int     $post_id Translation post ID.
	 * @param WP_Post $source  Original post.
	 * @return void
	 */
	private function copy_source_meta( $post_id, WP_Post $source ) {
		$keys = (array) apply_filters(
			'arkray_ti_copied_meta_keys',
			array(
				'news'  => array( 'news_date', 'news_external_url' ),
				'event' => array( 'event_date', 'event_location', 'event_external_url' ),
			),
			$source
		);

		if ( ! isset( $keys[ $source->post_type ] ) ) {
			return;
		}

		foreach ( (array) $keys[ $source->post_type ] as $meta_key ) {
			$value = get_post_meta( $source->ID, $meta_key, true );
			if ( '' !== $value && null !== $value && array() !== $value ) {
				update_post_meta( $post_id, $meta_key, $value );
			}
		}
	}

	/**
	 * Assign the target-language translations of the original's terms.
	 * Terms without a translation are skipped.
	 *
	 * @param int     $post_id Translation post ID.
	 * @param WP_Post $source  Original post.
	 * @return void
	 */
	private function copy_translated_terms( $post_id, WP_Post $source ) {
		if ( ! function_exists( 'pll_get_term' ) || ! function_exists( 'pll_is_translated_taxonomy' ) ) {
			return;
		}

		foreach ( get_object_taxonomies( $source->post_type ) as $taxonomy ) {
			if ( ! pll_is_translated_taxonomy( $taxonomy ) ) {
				continue;
			}

			$source_terms = wp_get_object_terms( $source->ID, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $source_terms ) || empty( $source_terms ) ) {
				continue;
			}

			$mapped = array();
			foreach ( $source_terms as $term_id ) {
				$translated = (int) pll_get_term( $term_id, $this->target_lang );
				if ( $translated ) {
					$mapped[] = $translated;
				}
			}

			if ( ! empty( $mapped ) ) {
				wp_set_object_terms( $post_id, $mapped, $taxonomy );
			}
		}
	}

	/**
	 * Second pass: point translated pages at the translations of their originals' parents.
	 *
	 * Runs after all rows so a child can find its parent's translation even when
	 * the parent appears later in the CSV.
	 *
	 * @return void
	 */
	private function fix_parents() {
		foreach ( $this->processed as $pair ) {
			if ( ! is_post_type_hierarchical( $pair['post_type'] ) ) {
				continue;
			}

			$source = get_post( $pair['source_id'] );
			$target = get_post( $pair['target_id'] );
			if ( ! $source instanceof WP_Post || ! $target instanceof WP_Post || ! $source->post_parent ) {
				continue;
			}

			$source_parent = get_post( $source->post_parent );
			if ( ! $source_parent instanceof WP_Post ) {
				continue;
			}

			// A parent in the trash would drag the child's URL down with it.
			$parent_translation = $this->translation_id( $source_parent );
			if ( $parent_translation && (int) $target->post_parent !== $parent_translation ) {
				$this->insert_keeping_slug(
					array(
						'ID'          => $target->ID,
						'post_parent' => $parent_translation,
					)
				);
			}
		}
	}

	/**
	 * Record a per-page result.
	 *
	 * @param int    $line      CSV line number where the page starts.
	 * @param string $post_type Post type.
	 * @param string $source    Source identifier as given in the CSV.
	 * @param string $action    created|updated|skipped|error.
	 * @param string $message   Details.
	 * @param string $edit_link Edit link for the affected post.
	 * @return void
	 */
	private function add_result( $line, $post_type, $source, $action, $message = '', $edit_link = '' ) {
		$this->results[] = array(
			'line'      => (int) $line,
			'post_type' => $post_type,
			'source'    => $source,
			'action'    => $action,
			'message'   => $message,
			'edit_link' => $edit_link,
		);
	}
}
