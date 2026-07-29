<?php
/**
 * Admin UI: Tools → Translation Import/Export (CSV).
 *
 * @package ArkrayTranslationImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the tools page and handles the export download and CSV upload.
 */
class Arkray_TI_Admin {

	const CAPABILITY     = 'manage_options';
	const NONCE_ACTION   = 'arkray_ti_nonce_action';
	const MAX_UPLOAD_MB  = 32;

	/**
	 * Hook everything up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_post_arkray_ti_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_arkray_ti_import', array( __CLASS__, 'handle_import' ) );
	}

	/**
	 * Register the page under Tools.
	 *
	 * @return void
	 */
	public static function register_page() {
		add_management_page(
			__( 'Translation Import/Export (CSV)', 'arkray-translation-importer' ),
			__( 'Translation CSV', 'arkray-translation-importer' ),
			self::CAPABILITY,
			ARKRAY_TI_PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Languages configured in Polylang as slug => name.
	 *
	 * @return array
	 */
	private static function languages() {
		$slugs = (array) pll_languages_list( array( 'fields' => 'slug' ) );
		$names = (array) pll_languages_list( array( 'fields' => 'name' ) );
		if ( count( $slugs ) !== count( $names ) ) {
			$names = $slugs;
		}
		return array_combine( $slugs, $names );
	}

	/**
	 * Default target language: the first non-default language.
	 *
	 * @return string
	 */
	private static function default_target() {
		$default = (string) pll_default_language();
		foreach ( array_keys( self::languages() ) as $slug ) {
			if ( $slug !== $default ) {
				return $slug;
			}
		}
		return $default;
	}

	/**
	 * Post types manageable by this tool.
	 *
	 * @return WP_Post_Type[] Keyed by post type name.
	 */
	private static function translated_post_types() {
		$types  = array();
		$objects = get_post_types( array( 'show_ui' => true ), 'objects' );
		foreach ( $objects as $object ) {
			if ( 'attachment' === $object->name ) {
				continue;
			}
			if ( pll_is_translated_post_type( $object->name ) ) {
				$types[ $object->name ] = $object;
			}
		}
		return $types;
	}

	/**
	 * Validate a language slug from the request.
	 *
	 * @param string $value Raw value.
	 * @return string Valid slug or ''.
	 */
	private static function sanitize_language( $value ) {
		$value = sanitize_title( wp_unslash( (string) $value ) );
		return array_key_exists( $value, self::languages() ) ? $value : '';
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Export
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Stream the ready-to-translate CSV.
	 *
	 * @return void
	 */
	public static function handle_export() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'arkray-translation-importer' ) );
		}
		check_admin_referer( self::NONCE_ACTION );

		if ( ! arkray_ti_polylang_ready() ) {
			wp_die( esc_html__( 'Polylang is not active.', 'arkray-translation-importer' ) );
		}

		$source = self::sanitize_language( isset( $_POST['source_lang'] ) ? $_POST['source_lang'] : '' );
		$target = self::sanitize_language( isset( $_POST['target_lang'] ) ? $_POST['target_lang'] : '' );
		if ( '' === $source ) {
			$source = (string) pll_default_language();
		}
		if ( '' === $target || $target === $source ) {
			$target = self::default_target();
		}

		$requested_types = isset( $_POST['post_types'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['post_types'] ) ) : array();
		$available_types = array_keys( self::translated_post_types() );
		$post_types      = array_values( array_intersect( $requested_types, $available_types ) );
		if ( empty( $post_types ) ) {
			$post_types = array( 'page' );
		}

		@set_time_limit( 300 );
		wp_raise_memory_limit( 'admin' );

		$headers = array(
			'id',
			'slug',
			'english_content',
			'vietnamese_content',
		);

		$rows = array();

		foreach ( $post_types as $post_type ) {
			$posts = get_posts(
				array(
					'post_type'        => $post_type,
					'post_status'      => array( 'publish', 'draft', 'private', 'pending' ),
					'numberposts'      => -1,
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'lang'             => $source,
					'suppress_filters' => false,
				)
			);

			foreach ( $posts as $post ) {
				$translation_id = (int) pll_get_post( $post->ID, $target );
				$translation    = $translation_id ? get_post( $translation_id ) : null;

				$rows[] = array(
					$post->ID,
					is_post_type_hierarchical( $post_type ) ? get_page_uri( $post ) : $post->post_name,
					$post->post_content,
					$translation instanceof WP_Post ? $translation->post_content : '',
				);
			}
		}

		Arkray_TI_Csv::stream(
			sprintf( 'arkray-translations-%s-%s.csv', $target, gmdate( 'Ymd-His' ) ),
			$headers,
			$rows
		);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Import
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Handle the CSV upload, run the importer, redirect back with results.
	 *
	 * @return void
	 */
	public static function handle_import() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'arkray-translation-importer' ) );
		}
		check_admin_referer( self::NONCE_ACTION );

		if ( ! arkray_ti_polylang_ready() ) {
			wp_die( esc_html__( 'Polylang is not active.', 'arkray-translation-importer' ) );
		}

		$mode    = isset( $_POST['import_mode'] ) ? sanitize_key( $_POST['import_mode'] ) : 'dry_run';
		$dry_run = 'import' !== $mode;

		$error = self::validate_upload();
		if ( is_wp_error( $error ) ) {
			self::redirect_with_results( array( 'fatal' => $error->get_error_message() ) );
		}

		$parsed = Arkray_TI_Csv::read( $_FILES['csv_file']['tmp_name'] );
		if ( is_wp_error( $parsed ) ) {
			self::redirect_with_results( array( 'fatal' => $parsed->get_error_message() ) );
		}

		$target = self::sanitize_language( isset( $_POST['target_lang'] ) ? $_POST['target_lang'] : '' );
		if ( '' === $target ) {
			$target = self::default_target();
		}
		$source = (string) pll_default_language();
		if ( $target === $source ) {
			self::redirect_with_results(
				array( 'fatal' => __( 'The target language equals the default (source) language. Choose the language you are importing translations for.', 'arkray-translation-importer' ) )
			);
		}

		$options = array(
			'copy_thumbnail' => ! empty( $_POST['copy_thumbnail'] ),
			'copy_template'  => ! empty( $_POST['copy_template'] ),
			'copy_elementor' => ! empty( $_POST['copy_elementor'] ),
			'copy_terms'     => ! empty( $_POST['copy_terms'] ),
		);

		@set_time_limit( 600 );
		wp_raise_memory_limit( 'admin' );

		$importer = new Arkray_TI_Importer( $target, $source, $options );
		$report   = $importer->run( $parsed['rows'], $dry_run );

		$report['dry_run']         = $dry_run;
		$report['target_lang']     = $target;
		$report['ignored_columns'] = Arkray_TI_Importer::ignored_columns( $parsed['headers'] );

		self::redirect_with_results( $report );
	}

	/**
	 * Validate the uploaded file.
	 *
	 * @return true|WP_Error
	 */
	private static function validate_upload() {
		if ( empty( $_FILES['csv_file'] ) || ! isset( $_FILES['csv_file']['error'] ) ) {
			return new WP_Error( 'arkray_ti_no_file', __( 'No file was uploaded.', 'arkray-translation-importer' ) );
		}
		if ( UPLOAD_ERR_OK !== (int) $_FILES['csv_file']['error'] ) {
			return new WP_Error( 'arkray_ti_upload_error', __( 'The upload failed. The file may exceed the server upload limit.', 'arkray-translation-importer' ) );
		}
		if ( ! is_uploaded_file( $_FILES['csv_file']['tmp_name'] ) ) {
			return new WP_Error( 'arkray_ti_upload_error', __( 'Invalid upload.', 'arkray-translation-importer' ) );
		}
		if ( (int) $_FILES['csv_file']['size'] > self::MAX_UPLOAD_MB * 1024 * 1024 ) {
			return new WP_Error(
				'arkray_ti_too_large',
				sprintf( __( 'The file exceeds %d MB.', 'arkray-translation-importer' ), self::MAX_UPLOAD_MB )
			);
		}

		$name = isset( $_FILES['csv_file']['name'] ) ? (string) $_FILES['csv_file']['name'] : '';
		$ext  = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'csv', 'txt' ), true ) ) {
			return new WP_Error( 'arkray_ti_wrong_type', __( 'Please upload a .csv file.', 'arkray-translation-importer' ) );
		}

		return true;
	}

	/**
	 * Store the report for the current user and redirect back to the page.
	 *
	 * @param array $report Report data.
	 * @return void
	 */
	private static function redirect_with_results( array $report ) {
		set_transient( 'arkray_ti_results_' . get_current_user_id(), $report, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'tools.php?page=' . ARKRAY_TI_PAGE_SLUG . '&arkray_ti_done=1' ) );
		exit;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Page rendering
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Render the tools page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Translation Import/Export (CSV)', 'arkray-translation-importer' ) . '</h1>';

		if ( ! arkray_ti_polylang_ready() ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Polylang must be active to use this tool.', 'arkray-translation-importer' ) . '</p></div></div>';
			return;
		}

		$languages = self::languages();
		if ( count( $languages ) < 2 ) {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'Polylang has fewer than two languages configured. Add the target language (e.g. Vietnamese) under Languages before importing.', 'arkray-translation-importer' )
				. '</p></div></div>';
			return;
		}

		self::render_results();
		self::render_workflow_help();
		self::render_export_form( $languages );
		self::render_import_form( $languages );
		self::render_column_reference();

		echo '</div>';
	}

	/**
	 * Render the report from the last run, if any.
	 *
	 * @return void
	 */
	private static function render_results() {
		if ( empty( $_GET['arkray_ti_done'] ) ) {
			return;
		}

		$key    = 'arkray_ti_results_' . get_current_user_id();
		$report = get_transient( $key );
		delete_transient( $key );

		if ( ! is_array( $report ) ) {
			return;
		}

		if ( ! empty( $report['fatal'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $report['fatal'] ) . '</p></div>';
			return;
		}

		$summary = isset( $report['summary'] ) ? $report['summary'] : array();
		$dry_run = ! empty( $report['dry_run'] );

		$notice_class = ( empty( $summary['error'] ) ) ? 'notice-success' : 'notice-warning';
		echo '<div class="notice ' . esc_attr( $notice_class ) . '"><p><strong>';
		if ( $dry_run ) {
			esc_html_e( 'Dry run (nothing was saved):', 'arkray-translation-importer' );
		} else {
			esc_html_e( 'Import finished:', 'arkray-translation-importer' );
		}
		echo '</strong> ';
		echo esc_html(
			sprintf(
				/* translators: 1-4: counts */
				__( '%1$d to create, %2$d to update, %3$d skipped, %4$d errors.', 'arkray-translation-importer' ),
				isset( $summary['created'] ) ? $summary['created'] : 0,
				isset( $summary['updated'] ) ? $summary['updated'] : 0,
				isset( $summary['skipped'] ) ? $summary['skipped'] : 0,
				isset( $summary['error'] ) ? $summary['error'] : 0
			)
		);
		echo '</p></div>';

		if ( ! empty( $report['ignored_columns'] ) ) {
			echo '<div class="notice notice-info"><p>'
				. esc_html__( 'Ignored columns (reference only):', 'arkray-translation-importer' ) . ' <code>'
				. implode( '</code>, <code>', array_map( 'esc_html', $report['ignored_columns'] ) )
				. '</code></p></div>';
		}

		if ( empty( $report['rows'] ) ) {
			return;
		}

		$action_labels = array(
			'created' => $dry_run ? __( 'Would create', 'arkray-translation-importer' ) : __( 'Created', 'arkray-translation-importer' ),
			'updated' => $dry_run ? __( 'Would update', 'arkray-translation-importer' ) : __( 'Updated', 'arkray-translation-importer' ),
			'skipped' => __( 'Skipped', 'arkray-translation-importer' ),
			'error'   => __( 'Error', 'arkray-translation-importer' ),
		);
		$action_colors = array(
			'created' => '#00a32a',
			'updated' => '#2271b1',
			'skipped' => '#787c82',
			'error'   => '#d63638',
		);

		echo '<table class="widefat striped" style="max-width:1100px;margin-bottom:24px;">';
		echo '<thead><tr>';
		echo '<th style="width:70px;">' . esc_html__( 'CSV line', 'arkray-translation-importer' ) . '</th>';
		echo '<th style="width:110px;">' . esc_html__( 'Post type', 'arkray-translation-importer' ) . '</th>';
		echo '<th>' . esc_html__( 'Original', 'arkray-translation-importer' ) . '</th>';
		echo '<th style="width:130px;">' . esc_html__( 'Action', 'arkray-translation-importer' ) . '</th>';
		echo '<th>' . esc_html__( 'Details', 'arkray-translation-importer' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $report['rows'] as $row ) {
			$action = isset( $row['action'] ) ? $row['action'] : 'error';
			$label  = isset( $action_labels[ $action ] ) ? $action_labels[ $action ] : $action;
			$color  = isset( $action_colors[ $action ] ) ? $action_colors[ $action ] : '#000';

			echo '<tr>';
			echo '<td>' . esc_html( $row['line'] ) . '</td>';
			echo '<td>' . esc_html( $row['post_type'] ) . '</td>';
			echo '<td><code>' . esc_html( $row['source'] ) . '</code></td>';
			echo '<td><strong style="color:' . esc_attr( $color ) . ';">' . esc_html( $label ) . '</strong></td>';
			echo '<td>' . esc_html( $row['message'] );
			if ( ! empty( $row['edit_link'] ) ) {
				echo ' <a href="' . esc_url( $row['edit_link'] ) . '">' . esc_html__( 'Edit', 'arkray-translation-importer' ) . '</a>';
			}
			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Render the how-it-works box.
	 *
	 * @return void
	 */
	private static function render_workflow_help() {
		echo '<div class="card" style="max-width:1100px;">';
		echo '<h2>' . esc_html__( 'Workflow', 'arkray-translation-importer' ) . '</h2>';
		echo '<ol>';
		echo '<li>' . esc_html__( 'Export a CSV below. It has four columns: id, slug, english_content and vietnamese_content (prefilled when a translation already exists).', 'arkray-translation-importer' ) . '</li>';
		echo '<li>' . esc_html__( 'Fill the "vietnamese_content" column with the translation of "english_content". Keep the HTML tags intact — translate only the visible text. Do not change the id or slug columns.', 'arkray-translation-importer' ) . '</li>';
		echo '<li>' . esc_html__( 'Save the file as CSV UTF-8 (required for Vietnamese characters) and upload it below with "Dry run" first to preview, then "Import".', 'arkray-translation-importer' ) . '</li>';
		echo '</ol>';
		echo '<p>' . esc_html__( 'The import is safe to repeat: existing translations are updated, missing ones are created, and rows with an empty vietnamese_content cell are skipped without touching anything.', 'arkray-translation-importer' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render the export form.
	 *
	 * @param array $languages slug => name.
	 * @return void
	 */
	private static function render_export_form( array $languages ) {
		$default_lang   = (string) pll_default_language();
		$default_target = self::default_target();
		$post_types     = self::translated_post_types();

		echo '<div class="card" style="max-width:1100px;">';
		echo '<h2>' . esc_html__( '1. Export CSV for translation', 'arkray-translation-importer' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="arkray_ti_export" />';
		wp_nonce_field( self::NONCE_ACTION );

		echo '<table class="form-table"><tbody>';

		echo '<tr><th scope="row">' . esc_html__( 'Post types', 'arkray-translation-importer' ) . '</th><td>';
		if ( empty( $post_types ) ) {
			echo '<em>' . esc_html__( 'No post types are managed by Polylang. Enable them under Languages → Settings.', 'arkray-translation-importer' ) . '</em>';
		}
		foreach ( $post_types as $name => $object ) {
			echo '<label style="margin-right:16px;display:inline-block;">';
			echo '<input type="checkbox" name="post_types[]" value="' . esc_attr( $name ) . '"' . checked( 'page', $name, false ) . ' /> ';
			echo esc_html( $object->labels->name ) . ' <code>' . esc_html( $name ) . '</code>';
			echo '</label>';
		}
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="arkray-ti-export-source">' . esc_html__( 'Source language', 'arkray-translation-importer' ) . '</label></th><td>';
		echo '<select id="arkray-ti-export-source" name="source_lang">';
		foreach ( $languages as $slug => $label ) {
			echo '<option value="' . esc_attr( $slug ) . '"' . selected( $slug, $default_lang, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="arkray-ti-export-target">' . esc_html__( 'Target language', 'arkray-translation-importer' ) . '</label></th><td>';
		echo '<select id="arkray-ti-export-target" name="target_lang">';
		foreach ( $languages as $slug => $label ) {
			echo '<option value="' . esc_attr( $slug ) . '"' . selected( $slug, $default_target, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'When a translation already exists, its current content is prefilled in the vietnamese_content column so you can review and correct it.', 'arkray-translation-importer' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';
		submit_button( __( 'Download CSV', 'arkray-translation-importer' ), 'secondary' );
		echo '</form></div>';
	}

	/**
	 * Render the import form.
	 *
	 * @param array $languages slug => name.
	 * @return void
	 */
	private static function render_import_form( array $languages ) {
		$default_target = self::default_target();

		echo '<div class="card" style="max-width:1100px;">';
		echo '<h2>' . esc_html__( '2. Upload translated CSV', 'arkray-translation-importer' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
		echo '<input type="hidden" name="action" value="arkray_ti_import" />';
		wp_nonce_field( self::NONCE_ACTION );

		echo '<table class="form-table"><tbody>';

		echo '<tr><th scope="row"><label for="arkray-ti-file">' . esc_html__( 'CSV file', 'arkray-translation-importer' ) . '</label></th><td>';
		echo '<input type="file" id="arkray-ti-file" name="csv_file" accept=".csv,.txt,text/csv" required />';
		echo '<p class="description">' . esc_html__( 'UTF-8 encoded (Excel: File → Save As → "CSV UTF-8"). Comma, semicolon and tab delimiters are detected automatically.', 'arkray-translation-importer' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="arkray-ti-import-target">' . esc_html__( 'Target language', 'arkray-translation-importer' ) . '</label></th><td>';
		echo '<select id="arkray-ti-import-target" name="target_lang">';
		foreach ( $languages as $slug => $label ) {
			echo '<option value="' . esc_attr( $slug ) . '"' . selected( $slug, $default_target, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'When creating translations', 'arkray-translation-importer' ) . '</th><td>';
		echo '<label style="display:block;"><input type="checkbox" name="copy_template" value="1" checked /> ' . esc_html__( 'Copy the page template from the original', 'arkray-translation-importer' ) . '</label>';
		echo '<label style="display:block;"><input type="checkbox" name="copy_thumbnail" value="1" checked /> ' . esc_html__( 'Copy the featured image from the original', 'arkray-translation-importer' ) . '</label>';
		echo '<label style="display:block;"><input type="checkbox" name="copy_elementor" value="1" checked /> ' . esc_html__( 'Copy the Elementor layout from the original (if it uses Elementor)', 'arkray-translation-importer' ) . '</label>';
		echo '<label style="display:block;"><input type="checkbox" name="copy_terms" value="1" checked /> ' . esc_html__( 'Assign translated categories/terms matching the original (when no tax: column is given)', 'arkray-translation-importer' ) . '</label>';
		echo '</td></tr>';

		echo '</tbody></table>';

		echo '<p>';
		echo '<button type="submit" name="import_mode" value="dry_run" class="button button-secondary">' . esc_html__( 'Dry run (preview only)', 'arkray-translation-importer' ) . '</button> ';
		echo '<button type="submit" name="import_mode" value="import" class="button button-primary" onclick="return confirm(\'' . esc_js( __( 'Run the import and write changes to the database?', 'arkray-translation-importer' ) ) . '\');">' . esc_html__( 'Import', 'arkray-translation-importer' ) . '</button>';
		echo '</p>';

		echo '</form></div>';
	}

	/**
	 * Render the CSV column reference.
	 *
	 * @return void
	 */
	private static function render_column_reference() {
		$columns = array(
			'id'                 => __( 'ID of the original (English) post. The most reliable identifier — keep this column from the export.', 'arkray-translation-importer' ),
			'slug'               => __( 'Slug of the original post, used to find it when id is empty. Pages accept a full path such as "about/philosophy". Never modified by the import.', 'arkray-translation-importer' ),
			'english_content'    => __( 'The original content, for the translator\'s reference. Ignored on import.', 'arkray-translation-importer' ),
			'vietnamese_content' => __( 'The translated body (HTML allowed — translate only the visible text between tags). Written to the translation; rows with an empty cell are skipped.', 'arkray-translation-importer' ),
		);

		echo '<div class="card" style="max-width:1100px;">';
		echo '<h2>' . esc_html__( 'CSV column reference', 'arkray-translation-importer' ) . '</h2>';
		echo '<p>' . esc_html__( 'Column order does not matter and headers are case-insensitive (common variants such as "vietnam content" or "vi_content" are also accepted). When a translation is created, its title, date, template, featured image, Elementor layout and categories are copied from the original according to the options above.', 'arkray-translation-importer' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th style="width:220px;">' . esc_html__( 'Column', 'arkray-translation-importer' ) . '</th>';
		echo '<th>' . esc_html__( 'Meaning', 'arkray-translation-importer' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $columns as $column => $description ) {
			echo '<tr><td><code>' . esc_html( $column ) . '</code></td><td>' . esc_html( $description ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '</div>';
	}
}
