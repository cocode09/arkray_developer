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
 * The CSV uses exactly four columns (case-insensitive, order-independent):
 *
 * - id                  ID of the original (English) post. Preferred identifier.
 * - slug                Slug of the original post; used when id is empty.
 *                       Pages accept a full path like 'about/philosophy'.
 * - english_content     The original content, exported for the translator's
 *                       reference. Ignored on import.
 * - vietnamese_content  The translated content (HTML allowed). Written to the
 *                       translation's body. Rows with an empty cell are skipped.
 *
 * A few header spellings are accepted for convenience (e.g. 'english content',
 * 'en_content', 'vi_content', 'vietnam content').
 */
class Arkray_TI_Importer {

	const COLUMN_ALIASES = array(
		'id'                 => 'id',
		'en_id'              => 'id',
		'post_id'            => 'id',
		'slug'               => 'slug',
		'en_slug'            => 'slug',
		'post_slug'          => 'slug',
		'english_content'    => 'english_content',
		'english'            => 'english_content',
		'en_content'         => 'english_content',
		'content_en'         => 'english_content',
		'vietnamese_content' => 'vietnamese_content',
		'vietnam_content'    => 'vietnamese_content',
		'vietnamese'         => 'vietnamese_content',
		'vietnam'            => 'vietnamese_content',
		'vi_content'         => 'vietnamese_content',
		'content_vi'         => 'vietnamese_content',
		'translated_content' => 'vietnamese_content',
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
	 * Per-row results.
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
	 * Map a raw CSV header to its canonical column name.
	 *
	 * @param string $header Normalized (lower-case, trimmed) header.
	 * @return string Canonical name or '' when unrecognized.
	 */
	public static function canonical_column( $header ) {
		$header = str_replace( array( ' ', '-' ), '_', strtolower( trim( (string) $header ) ) );
		return isset( self::COLUMN_ALIASES[ $header ] ) ? self::COLUMN_ALIASES[ $header ] : '';
	}

	/**
	 * Column names in $headers that the importer will ignore.
	 *
	 * @param string[] $headers Normalized CSV headers.
	 * @return string[]
	 */
	public static function ignored_columns( array $headers ) {
		$ignored = array();
		foreach ( $headers as $header ) {
			if ( '' === self::canonical_column( $header ) ) {
				$ignored[] = $header;
			}
		}
		return $ignored;
	}

	/**
	 * Process all rows.
	 *
	 * @param array[] $rows    Associative rows from Arkray_TI_Csv::read().
	 * @param bool    $dry_run When true nothing is written; actions are only reported.
	 * @return array {
	 *     @type array[] $rows    Per-row results: line, post_type, source, action, message, edit_link.
	 *     @type array   $summary Counts keyed by action.
	 * }
	 */
	public function run( array $rows, $dry_run ) {
		$this->results   = array();
		$this->processed = array();

		foreach ( $rows as $index => $row ) {
			$this->process_row( $index + 2, $this->normalize_row( $row ), (bool) $dry_run ); // +2: 1-based plus header row.
		}

		if ( ! $dry_run ) {
			$this->fix_parents();
		}

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
		);
	}

	/**
	 * Re-key a raw CSV row to the canonical column names.
	 *
	 * @param array $row Row keyed by raw headers.
	 * @return array Row keyed by canonical names (id, slug, english_content, vietnamese_content).
	 */
	private function normalize_row( array $row ) {
		$normalized = array(
			'id'                 => '',
			'slug'               => '',
			'english_content'    => '',
			'vietnamese_content' => '',
		);

		foreach ( $row as $header => $value ) {
			$canonical = self::canonical_column( $header );
			if ( '' !== $canonical && '' === $normalized[ $canonical ] ) {
				$normalized[ $canonical ] = trim( (string) $value );
			}
		}

		return $normalized;
	}

	/**
	 * Process one CSV row.
	 *
	 * @param int   $line    CSV line number (for reporting).
	 * @param array $row     Canonicalized row.
	 * @param bool  $dry_run Dry-run flag.
	 * @return void
	 */
	private function process_row( $line, array $row, $dry_run ) {
		$source_ref = '' !== $row['id'] ? '#' . $row['id'] : $row['slug'];

		$source = $this->find_source_post( $row );
		if ( is_wp_error( $source ) ) {
			$this->add_result( $line, '', $source_ref, 'error', $source->get_error_message() );
			return;
		}

		if ( '' === $row['vietnamese_content'] ) {
			$this->add_result(
				$line,
				$source->post_type,
				$source_ref,
				'skipped',
				__( 'The translated content cell is empty.', 'arkray-translation-importer' )
			);
			return;
		}

		$target_id = (int) pll_get_post( $source->ID, $this->target_lang );
		$messages  = array();

		if ( $target_id ) {
			$result = $this->update_translation( $target_id, $source, $row, $dry_run, $messages );
		} else {
			$result = $this->create_translation( $source, $row, $dry_run, $messages );
		}

		if ( is_wp_error( $result ) ) {
			$this->add_result( $line, $source->post_type, $source_ref, 'error', $result->get_error_message() );
			return;
		}

		list( $action, $post_id ) = $result;

		if ( $post_id && ! $dry_run ) {
			$this->processed[] = array(
				'source_id' => (int) $source->ID,
				'target_id' => (int) $post_id,
				'post_type' => $source->post_type,
			);
		}

		$edit_link = ( $post_id && ! $dry_run ) ? get_edit_post_link( $post_id, 'raw' ) : '';
		$this->add_result( $line, $source->post_type, $source_ref, $action, implode( ' ', $messages ), $edit_link );
	}

	/**
	 * Locate the original (source-language) post for a row by id or slug.
	 *
	 * @param array $row Canonicalized row.
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
				return new WP_Error(
					'arkray_ti_not_found',
					sprintf( __( 'No post found with slug "%s".', 'arkray-translation-importer' ), $slug )
				);
			}
		} else {
			return new WP_Error( 'arkray_ti_no_identifier', __( 'The row has neither an id nor a slug, so the original post cannot be identified.', 'arkray-translation-importer' ) );
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
	 * The title, excerpt, date, menu order and (optionally) template, featured
	 * image, Elementor layout and terms are copied from the original so the new
	 * page is complete; the body comes from the CSV.
	 *
	 * @param WP_Post  $source   Original post.
	 * @param array    $row      Canonicalized row.
	 * @param bool     $dry_run  Dry-run flag.
	 * @param string[] $messages Accumulates informational messages.
	 * @return array|WP_Error array( 'created', $post_id ). $post_id is 0 in a dry run.
	 */
	private function create_translation( WP_Post $source, array $row, $dry_run, array &$messages ) {
		if ( $dry_run ) {
			return array( 'created', 0 );
		}

		$postarr = array(
			'post_type'      => $source->post_type,
			'post_status'    => 'publish',
			'post_title'     => $source->post_title,
			'post_content'   => $row['vietnamese_content'],
			'post_excerpt'   => $source->post_excerpt,
			'post_date'      => $source->post_date,
			'menu_order'     => (int) $source->menu_order,
			'comment_status' => $source->comment_status,
			'ping_status'    => $source->ping_status,
		);

		$messages[] = __( 'Title copied from the original (this format carries content only).', 'arkray-translation-importer' );

		$post_id = wp_insert_post( wp_slash( $postarr ), true );
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

		return array( 'created', $post_id );
	}

	/**
	 * Update an existing translation's content.
	 *
	 * @param int      $target_id Existing translation post ID.
	 * @param WP_Post  $source    Original post.
	 * @param array    $row       Canonicalized row.
	 * @param bool     $dry_run   Dry-run flag.
	 * @param string[] $messages  Accumulates informational messages.
	 * @return array|WP_Error array( 'updated', $post_id ).
	 */
	private function update_translation( $target_id, WP_Post $source, array $row, $dry_run, array &$messages ) {
		if ( $dry_run ) {
			return array( 'updated', 0 );
		}

		$updated = wp_update_post(
			wp_slash(
				array(
					'ID'           => $target_id,
					'post_content' => $row['vietnamese_content'],
				)
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
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

			$parent_translation = (int) pll_get_post( $source->post_parent, $this->target_lang );
			if ( $parent_translation && (int) $target->post_parent !== $parent_translation ) {
				wp_update_post(
					array(
						'ID'          => $target->ID,
						'post_parent' => $parent_translation,
					)
				);
			}
		}
	}

	/**
	 * Record a per-row result.
	 *
	 * @param int    $line      CSV line number.
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
