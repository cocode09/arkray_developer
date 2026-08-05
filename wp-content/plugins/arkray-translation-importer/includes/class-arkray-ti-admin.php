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
	 * Header row of the export.
	 *
	 * The page a row belongs to is carried by the ID and by the path in front of
	 * the colon in the note column, so no separate post column is needed.
	 */
	const COLUMNS = array(
		'ID',
		'Parent ID',
		'種別',
		'タイトル/箇所',
		'Global/Local',
		'NEW',
		'国コード',
		'English（英語テキスト）',
		'Vietnamese（ベトナム語テキスト）',
		'English img（英語画像ファイル名）',
		'Vietnamese img（ベトナム語画像ファイル名）',
		'English caption（英語キャプション）',
		'Vietnamese caption（ベトナム語キャプション）',
		'箇所・メモ',
	);

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
			__( 'Translation Import/Export (Excel)', 'arkray-translation-importer' ),
			__( 'Translations', 'arkray-translation-importer' ),
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

		if ( ! Arkray_TI_Blocks::is_supported() ) {
			wp_die( esc_html__( 'This tool needs WordPress 6.7 or newer.', 'arkray-translation-importer' ) );
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

		$rows     = array();
		$prefixes = array();

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
				$slug = is_post_type_hierarchical( $post_type ) ? get_page_uri( $post ) : $post->post_name;
				foreach ( self::export_rows_for_post( $post, $slug, $target, $prefixes ) as $row ) {
					$rows[] = $row;
				}
			}
		}

		array_unshift(
			$rows,
			array(
				'cells' => self::COLUMNS,
				'style' => 'header',
			)
		);

		Arkray_TI_Xlsx::stream(
			sprintf( 'arkray-translations-%s-%s.xlsx', $target, gmdate( 'Ymd-His' ) ),
			array(
				array(
					'name'   => 'CSV_MAIN',
					'freeze' => true,
					'widths' => array( 24, 22, 10, 28, 12, 7, 10, 50, 50, 26, 26, 26, 26, 34 ),
					'rows'   => $rows,
				),
				self::legend_sheet(),
			)
		);
	}

	/**
	 * Build the CSV rows for one post: a separator, the title, then one row per
	 * paragraph, heading, list item, table cell and image of its content.
	 *
	 * The Vietnamese columns are prefilled from the existing translation while it
	 * still has the same blocks as the original, so the text can be reviewed.
	 *
	 * @param WP_Post $post     Original post.
	 * @param string  $slug     Slug or page path of the original.
	 * @param string  $target   Target language slug.
	 * @param array   $prefixes ID prefixes already handed out, by reference.
	 * @return array[] Rows as array( 'cells' => values matching self::COLUMNS, 'style' => name ).
	 */
	private static function export_rows_for_post( WP_Post $post, $slug, $target, array &$prefixes ) {
		$prefix = self::block_prefix( $slug, $post->ID, $prefixes );
		$blocks = Arkray_TI_Blocks::extract( $post->post_content );
		$fields = self::page_fields( $post );
		$base   = self::row_style( $post );

		$translation_id = (int) pll_get_post( $post->ID, $target );
		$translation    = $translation_id ? get_post( $translation_id ) : null;
		$current        = $translation instanceof WP_Post
			? Arkray_TI_Blocks::extract( $translation->post_content )
			: array();

		// The two versions only line up while they are built the same way.
		if ( Arkray_TI_Blocks::keys( $current ) !== Arkray_TI_Blocks::keys( $blocks ) ) {
			$current = array();
		}
		$current = Arkray_TI_Blocks::by_key( $current );

		$title_id = $prefix . '-title';
		$rows     = array();

		// A separator row, so a page is easy to find when scrolling the file.
		$separator    = array_fill( 0, count( self::COLUMNS ), '' );
		$separator[3] = '【' . $post->post_title . '】';
		$rows[]       = array(
			'cells' => $separator,
			'style' => 'separator',
		);

		$rows[] = array(
			'cells' => self::export_row(
				array(
					'id'         => $title_id,
					'type'       => $post->post_type,
					'label'      => 'page' === $post->post_type
						? __( 'Page title', 'arkray-translation-importer' )
						: __( 'Post title', 'arkray-translation-importer' ),
					'english'    => $post->post_title,
					'vietnamese' => $translation instanceof WP_Post ? $translation->post_title : '',
					'note'       => $slug . ': ' . __( 'title', 'arkray-translation-importer' ),
				),
				$fields
			),
			'style' => $base,
		);

		foreach ( $blocks as $block ) {
			$existing = isset( $current[ $block['key'] ] ) ? $current[ $block['key'] ] : null;

			$row = array(
				'id'        => $prefix . '-' . $block['key'],
				'parent_id' => $title_id,
				'type'      => $post->post_type,
				'label'     => $block['label'],
				'note'      => $slug . ': ' . $block['label'],
			);

			if ( 'image' === $block['kind'] ) {
				$row['english_img']        = $block['file'];
				$row['vietnamese_img']     = $existing ? $existing['file'] : '';
				$row['english_caption']    = $block['caption'];
				$row['vietnamese_caption'] = $existing ? $existing['caption'] : '';
			} else {
				$row['english']    = $block['text'];
				$row['vietnamese'] = $existing ? $existing['text'] : '';
			}

			$rows[] = array(
				'cells' => self::export_row( $row, $fields ),
				'style' => self::block_style( $block, $base ),
			);
		}

		return $rows;
	}

	/**
	 * Background colour for every row of a post, see the legend sheet.
	 *
	 * @param WP_Post $post Original post.
	 * @return string Style name of Arkray_TI_Xlsx.
	 */
	private static function row_style( WP_Post $post ) {
		if ( '' !== (string) get_post_meta( $post->ID, 'external_content_url', true ) ) {
			return 'external';
		}

		$map = Arkray_TI_Importer::post_type_fields();

		return isset( $map[ $post->post_type ] ) ? 'highlight' : 'default';
	}

	/**
	 * Background colour for one block row: images and table cells get their own.
	 *
	 * @param array  $block Block from Arkray_TI_Blocks::extract().
	 * @param string $base  Style of the post the block belongs to.
	 * @return string Style name of Arkray_TI_Xlsx.
	 */
	private static function block_style( array $block, $base ) {
		if ( 'image' === $block['kind'] || 0 === strpos( $block['key'], 'cap' ) ) {
			return 'image';
		}
		if ( 0 === strpos( $block['key'], 'tbl' ) ) {
			return 'table';
		}

		return $base;
	}

	/**
	 * Lay one row out in the column order of the export.
	 *
	 * @param array $values Row values keyed by role.
	 * @param array $fields Page level fields: scope, is_new, country.
	 * @return array
	 */
	private static function export_row( array $values, array $fields ) {
		$values = wp_parse_args(
			$values,
			array(
				'id'                 => '',
				'parent_id'          => '',
				'type'               => '',
				'label'              => '',
				'english'            => '',
				'vietnamese'         => '',
				'english_img'        => '',
				'vietnamese_img'     => '',
				'english_caption'    => '',
				'vietnamese_caption' => '',
				'note'               => '',
			)
		);

		return array(
			$values['id'],
			$values['parent_id'],
			$values['type'],
			$values['label'],
			$fields['scope'],
			$fields['is_new'],
			$fields['country'],
			$values['english'],
			$values['vietnamese'],
			$values['english_img'],
			$values['vietnamese_img'],
			$values['english_caption'],
			$values['vietnamese_caption'],
			$values['note'],
		);
	}

	/**
	 * The second sheet of the workbook: what each column and colour means.
	 *
	 * Kept in Japanese, like the sample sheet this format was agreed on, because
	 * it is read by the people who hand the file to the translators.
	 *
	 * @return array Sheet definition for Arkray_TI_Xlsx.
	 */
	private static function legend_sheet() {
		$rows = array(
			array(
				'cells' => array( 'arkray.vn 多言語ファイル 凡例・ルール' ),
				'style' => 'header',
			),
			array(),
			array(
				'cells' => array( '【列の説明】' ),
				'style' => 'separator',
			),
			array(
				'cells' => array( '列名', '内容', '記入例', 'ルール' ),
				'style' => 'header',
			),
			array( 'ID', 'ブロックごとの一意のID（ページ＋位置）', 'ha-8190v-tbl1-r2-c1', '書き換え不可。インポート時の位置の判別に使用します。' ),
			array( 'Parent ID', '親ブロックのID（階層構造）', 'ha-8190v-title', '同じページのタイトル行を親として出力します。' ),
			array( '種別', 'WordPressの投稿タイプ', 'page / news / event / product', '参照用。' ),
			array( 'タイトル/箇所', 'そのブロックがページ内のどこかを示すメモ', 'Table 1, row 2, column 1', '参照用。翻訳対象ではありません。' ),
			array( 'Global/Local', 'News・Eventsの分類', 'global / local', 'News・Eventsのみ。空欄の場合は現状を維持します。' ),
			array( 'NEW', '新着フラグ', 'new', '「new」で表示、「0」で非表示、空欄は現状維持。' ),
			array( '国コード', 'Events開催国（国旗表示用）', 'VN / JP / IT', 'Eventsのみ。空欄は現状維持。' ),
			array( 'English（英語テキスト）', '英語の本文（HTMLタグなし）', 'Trade Name', '参照用。インポート時は使用しません。' ),
			array( 'Vietnamese（ベトナム語テキスト）', 'ベトナム語の翻訳', 'Tên giao dịch', '翻訳者が記入。空欄の場合は現在の翻訳を維持します。' ),
			array( 'English img（英語画像ファイル名）', '英語版の画像ファイル名', 'ha-8190v.jpg', '参照用。' ),
			array( 'Vietnamese img（ベトナム語画像ファイル名）', 'ベトナム語版の画像ファイル名', 'ha-8190v-vn.jpg', 'ファイル名を書き換えると画像を差し替えます。事前にメディアライブラリへ登録してください。' ),
			array( 'English caption（英語キャプション）', '英語のキャプション・代替テキスト', 'HA-8190V', '参照用。' ),
			array( 'Vietnamese caption（ベトナム語キャプション）', 'ベトナム語のキャプション・代替テキスト', 'Máy phân tích HA-8190V', '翻訳者が記入。alt属性として保存します。' ),
			array( '箇所・メモ', 'ページのパスとブロックの位置', 'about/profile: Table 1, row 1, column 1', 'コロンより前をページの判別に使用します。書き換え不可。' ),
			array(),
			array(
				'cells' => array( '【背景色の意味】' ),
				'style' => 'separator',
			),
			array(
				'cells' => array( '濃紺背景', 'ヘッダー行' ),
				'style' => 'header',
			),
			array(
				'cells' => array( '濃灰背景', 'ページの区切り行（IDが空欄。インポート時は無視されます）' ),
				'style' => 'separator',
			),
			array(
				'cells' => array( '薄青背景', '画像・キャプションの行' ),
				'style' => 'image',
			),
			array(
				'cells' => array( '薄黄背景', 'テーブルのセルの行（1セル＝1ID）' ),
				'style' => 'table',
			),
			array(
				'cells' => array( '薄オレンジ背景', 'News・Eventsの行' ),
				'style' => 'highlight',
			),
			array(
				'cells' => array( '薄緑背景', '本文を外部から取得しているページの行（API連携）' ),
				'style' => 'external',
			),
			array(),
			array(
				'cells' => array( '【ルール】' ),
				'style' => 'separator',
			),
			array( '①', '1ページ内の段落・見出し・リスト・テーブルのセル・画像は、すべて別のIDで別の行に出力されます。' ),
			array( '②', '行の追加・削除・並べ替え、およびIDの書き換えは行わないでください。' ),
			array( '③', 'テーブルは1セルが1行（1ID）です。IDにテーブル番号・行番号・列番号が入ります。' ),
			array( '④', '訳文はHTMLタグなしで記入してください。タグは元のページのものをそのまま使用します。' ),
			array( '⑤', 'Vietnamese imgのファイル名を書き換えると、その画像を差し替えます。' ),
			array( '⑥', 'すべてのセルは書式「文字列」で出力しています。先頭が「-」「+」「=」の文字列もそのまま扱われます。' ),
			array( '⑦', '英語版のページを更新した場合は、IDが変わることがあるため再度エクスポートしてください。' ),
			array( '⑧', 'このファイルは.xlsxのままアップロードできます。CSVに変換する必要はありません。' ),
		);

		return array(
			'name'   => '凡例・ルール',
			'widths' => array( 34, 58, 38, 52 ),
			'rows'   => $rows,
		);
	}

	/**
	 * The part every block ID of a post starts with, taken from its slug.
	 *
	 * @param string $slug     Slug or page path.
	 * @param int    $post_id  Post ID, used to keep the prefix unique.
	 * @param array  $prefixes Prefixes already handed out, by reference.
	 * @return string
	 */
	private static function block_prefix( $slug, $post_id, array &$prefixes ) {
		$base = '' !== (string) $slug ? basename( (string) $slug ) : '';
		$base = strtolower( remove_accents( $base ) );
		$base = preg_replace( '#[^a-z0-9]+#', '-', $base );
		$base = trim( (string) $base, '-' );

		if ( '' === $base ) {
			$base = 'post-' . (int) $post_id;
		}
		if ( isset( $prefixes[ $base ] ) ) {
			$base .= '-' . (int) $post_id;
		}

		$prefixes[ $base ] = true;

		return $base;
	}

	/**
	 * Read the Global/Local, NEW and country columns off a post.
	 *
	 * @param WP_Post $post Original post.
	 * @return array scope, is_new, country.
	 */
	private static function page_fields( WP_Post $post ) {
		$values = array(
			'scope'   => '',
			'is_new'  => '',
			'country' => '',
		);

		$map = Arkray_TI_Importer::post_type_fields();
		if ( ! isset( $map[ $post->post_type ] ) ) {
			return $values;
		}

		$fields = wp_parse_args(
			$map[ $post->post_type ],
			array(
				'taxonomy'     => '',
				'new_meta'     => '',
				'country_meta' => '',
			)
		);

		if ( '' !== $fields['taxonomy'] ) {
			$terms = get_the_terms( $post->ID, $fields['taxonomy'] );
			if ( is_array( $terms ) && ! empty( $terms ) ) {
				$values['scope'] = $terms[0]->slug;
			}
		}

		if ( '' !== $fields['new_meta'] ) {
			$is_new = get_post_meta( $post->ID, $fields['new_meta'], true );
			if ( '' !== $is_new && null !== $is_new ) {
				$values['is_new'] = $is_new ? 'new' : '';
			}
		}

		if ( '' !== $fields['country_meta'] ) {
			$values['country'] = (string) get_post_meta( $post->ID, $fields['country_meta'], true );
		}

		return $values;
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

		if ( ! Arkray_TI_Blocks::is_supported() ) {
			wp_die( esc_html__( 'This tool needs WordPress 6.7 or newer.', 'arkray-translation-importer' ) );
		}

		$mode    = isset( $_POST['import_mode'] ) ? sanitize_key( $_POST['import_mode'] ) : 'dry_run';
		$dry_run = 'import' !== $mode;

		$extension = self::validate_upload();
		if ( is_wp_error( $extension ) ) {
			self::redirect_with_results( array( 'fatal' => $extension->get_error_message() ) );
		}

		$parsed = 'xlsx' === $extension
			? Arkray_TI_Xlsx::read( $_FILES['translation_file']['tmp_name'] )
			: Arkray_TI_Csv::read( $_FILES['translation_file']['tmp_name'] );
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
		$report   = $importer->run( $parsed['rows'], $dry_run, $parsed['headers'] );

		$report['dry_run']         = $dry_run;
		$report['target_lang']     = $target;
		$report['ignored_columns'] = Arkray_TI_Importer::ignored_columns( $parsed['headers'], $report['format'] );

		self::redirect_with_results( $report );
	}

	/**
	 * Validate the uploaded file.
	 *
	 * @return string|WP_Error The file extension, lower-cased.
	 */
	private static function validate_upload() {
		if ( empty( $_FILES['translation_file'] ) || ! isset( $_FILES['translation_file']['error'] ) ) {
			return new WP_Error( 'arkray_ti_no_file', __( 'No file was uploaded.', 'arkray-translation-importer' ) );
		}
		if ( UPLOAD_ERR_OK !== (int) $_FILES['translation_file']['error'] ) {
			return new WP_Error( 'arkray_ti_upload_error', __( 'The upload failed. The file may exceed the server upload limit.', 'arkray-translation-importer' ) );
		}
		if ( ! is_uploaded_file( $_FILES['translation_file']['tmp_name'] ) ) {
			return new WP_Error( 'arkray_ti_upload_error', __( 'Invalid upload.', 'arkray-translation-importer' ) );
		}
		if ( (int) $_FILES['translation_file']['size'] > self::MAX_UPLOAD_MB * 1024 * 1024 ) {
			return new WP_Error(
				'arkray_ti_too_large',
				sprintf( __( 'The file exceeds %d MB.', 'arkray-translation-importer' ), self::MAX_UPLOAD_MB )
			);
		}

		$name      = isset( $_FILES['translation_file']['name'] ) ? (string) $_FILES['translation_file']['name'] : '';
		$extension = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );

		if ( in_array( $extension, array( 'xls', 'xlsm' ), true ) ) {
			return new WP_Error(
				'arkray_ti_wrong_type',
				__( 'Excel 97-2003 (.xls) and macro (.xlsm) files cannot be read. Open the file in Excel and use “Save as” to store it as .xlsx.', 'arkray-translation-importer' )
			);
		}
		if ( ! in_array( $extension, array( 'xlsx', 'csv', 'txt' ), true ) ) {
			return new WP_Error( 'arkray_ti_wrong_type', __( 'Please upload the .xlsx file you downloaded, or a .csv file.', 'arkray-translation-importer' ) );
		}

		return $extension;
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
		echo '<h1>' . esc_html__( 'Translation Import/Export (Excel)', 'arkray-translation-importer' ) . '</h1>';

		if ( ! arkray_ti_polylang_ready() ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Polylang must be active to use this tool.', 'arkray-translation-importer' ) . '</p></div></div>';
			return;
		}

		if ( ! Arkray_TI_Blocks::is_supported() ) {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'This tool needs WordPress 6.7 or newer to read the text out of page content and put translations back into it.', 'arkray-translation-importer' )
				. '</p></div></div>';
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

		if ( ! empty( $report['format'] ) && Arkray_TI_Importer::FORMAT_BLOCK !== $report['format'] ) {
			$notice = Arkray_TI_Importer::FORMAT_SEGMENT === $report['format']
				? __( 'The file has no ID column, so it was read as the older layout that addresses text by content_id.', 'arkray-translation-importer' )
				: __( 'The file has no ID and no content_id column, so it was read as the oldest layout, where the Vietnamese cell holds the whole HTML of a page.', 'arkray-translation-importer' );
			echo '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>';
		}

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
		echo '<th style="width:70px;">' . esc_html__( 'Row', 'arkray-translation-importer' ) . '</th>';
		echo '<th style="width:110px;">' . esc_html__( 'Post type', 'arkray-translation-importer' ) . '</th>';
		echo '<th style="width:90px;">' . esc_html__( 'Original', 'arkray-translation-importer' ) . '</th>';
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
		echo '<li>' . esc_html__( 'Export the Excel file below. It contains no HTML: the title, every paragraph, heading, list item, table cell and image of a page is one row with its own ID, and a separator row marks where each page starts. A second sheet explains the columns and the row colours.', 'arkray-translation-importer' ) . '</li>';
		echo '<li>' . esc_html__( 'Translators fill the Vietnamese column next to each English cell, and the Vietnamese caption column for images. Do not add, delete, sort or renumber rows, and leave the ID, Parent ID and 箇所・メモ columns as they are: they say where the text goes.', 'arkray-translation-importer' ) . '</li>';
		echo '<li>' . esc_html__( 'To show a different image in Vietnamese, put its file name in the "Vietnamese img" column. The file must already be in the media library.', 'arkray-translation-importer' ) . '</li>';
		echo '<li>' . esc_html__( 'Upload the .xlsx file as it is, with "Dry run" first to preview, then "Import". There is no need to convert it to CSV.', 'arkray-translation-importer' ) . '</li>';
		echo '</ol>';
		echo '<p>' . esc_html__( 'Every cell of the export is formatted as text, so Excel leaves the content alone: a paragraph starting with "-" or "+" stays text instead of turning into a #NAME? formula error, and phone numbers and codes keep their leading characters.', 'arkray-translation-importer' ) . '</p>';
		echo '<p>' . esc_html__( 'On import the HTML tags of the original page are put back around each translated text, so the translation is stored as complete HTML exactly like the original. Rows left blank keep what the current translation says, or the original text when there is no translation yet.', 'arkray-translation-importer' ) . '</p>';
		echo '<p>' . esc_html__( 'The import is safe to repeat: existing translations are updated, missing ones are created, and pages without any translated text are skipped without touching anything.', 'arkray-translation-importer' ) . '</p>';
		echo '<p>' . esc_html__( 'Export again whenever the English content changed: an ID says which paragraph, cell or image it belongs to, so an outdated file can leave text in the wrong place or be reported as unknown.', 'arkray-translation-importer' ) . '</p>';
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
		echo '<h2>' . esc_html__( '1. Export the Excel file for translation', 'arkray-translation-importer' ) . '</h2>';
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
		echo '<p class="description">' . esc_html__( 'When a translation already exists, its text, image file names and captions are prefilled in the Vietnamese columns so they can be reviewed and corrected.', 'arkray-translation-importer' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';
		submit_button( __( 'Download Excel file (.xlsx)', 'arkray-translation-importer' ), 'secondary' );
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
		echo '<h2>' . esc_html__( '2. Upload the translated file', 'arkray-translation-importer' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
		echo '<input type="hidden" name="action" value="arkray_ti_import" />';
		wp_nonce_field( self::NONCE_ACTION );

		echo '<table class="form-table"><tbody>';

		echo '<tr><th scope="row"><label for="arkray-ti-file">' . esc_html__( 'Translated file', 'arkray-translation-importer' ) . '</label></th><td>';
		echo '<input type="file" id="arkray-ti-file" name="translation_file" accept=".xlsx,.csv,.txt,' . esc_attr( Arkray_TI_Xlsx::CONTENT_TYPE ) . ',text/csv" required />';
		echo '<p class="description">' . esc_html__( 'The .xlsx file from the export, with the translations filled in; only its first sheet is read. A CSV is still accepted, in which case it has to be UTF-8 encoded (Excel: File → Save As → "CSV UTF-8"); comma, semicolon and tab delimiters are detected automatically.', 'arkray-translation-importer' ) . '</p>';
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
		echo '<label style="display:block;"><input type="checkbox" name="copy_terms" value="1" checked /> ' . esc_html__( 'Assign the translated categories and terms of the original; the Global/Local column still wins over them', 'arkray-translation-importer' ) . '</label>';
		echo '</td></tr>';

		echo '</tbody></table>';

		echo '<p>';
		echo '<button type="submit" name="import_mode" value="dry_run" class="button button-secondary">' . esc_html__( 'Dry run (preview only)', 'arkray-translation-importer' ) . '</button> ';
		echo '<button type="submit" name="import_mode" value="import" class="button button-primary" onclick="return confirm(\'' . esc_js( __( 'Run the import and write changes to the database?', 'arkray-translation-importer' ) ) . '\');">' . esc_html__( 'Import', 'arkray-translation-importer' ) . '</button>';
		echo '</p>';

		echo '</form></div>';
	}

	/**
	 * Render the column reference.
	 *
	 * @return void
	 */
	private static function render_column_reference() {
		$columns = array(
			'ID'                 => __( 'Unique key of the block, made of the page slug and the position, e.g. "ha-8190v-tbl1-r2-c1". It tells the import which page and which part of it the text belongs to, so leave it exactly as exported.', 'arkray-translation-importer' ),
			'Parent ID'          => __( 'The block this row sits under: every row of a page points at the title row. Used to read the file as a tree.', 'arkray-translation-importer' ),
			'種別'                => __( 'Post type of the page (page, post, news, event, product…). Reference only.', 'arkray-translation-importer' ),
			'タイトル/箇所'        => __( 'What the block is, e.g. "Heading 2 (H3)" or "Table 1, row 2, column 1". Reference only.', 'arkray-translation-importer' ),
			'Global/Local'       => __( 'Term of news_category or event_type. Written to the translation together with the translated text; blank leaves it as it is.', 'arkray-translation-importer' ),
			'NEW'                => __( 'New-arrival flag of a news post or event. "new" switches the badge on, "0" switches it off, blank leaves it as it is.', 'arkray-translation-importer' ),
			'国コード'            => __( 'Country of an event, used for its flag (ISO code, country name or flag emoji). Blank leaves it as it is.', 'arkray-translation-importer' ),
			'English'            => __( 'The original text of that block, without HTML tags. For the translator\'s reference; ignored on import.', 'arkray-translation-importer' ),
			'Vietnamese'         => __( 'The translated text, without HTML tags. Blank cells keep the text that is there today. Tags typed into this cell are stored as visible text, not as markup.', 'arkray-translation-importer' ),
			'English img'        => __( 'File name of the image in the original page. Reference only.', 'arkray-translation-importer' ),
			'Vietnamese img'     => __( 'File name to show in the translation instead. The file must be in the media library; blank keeps the original image.', 'arkray-translation-importer' ),
			'English caption'    => __( 'Alt text of the original image. Reference only.', 'arkray-translation-importer' ),
			'Vietnamese caption' => __( 'Alt text for the translated image. Blank keeps the alt text that is there today.', 'arkray-translation-importer' ),
			'箇所・メモ'          => __( 'Where the block sits, written as "page path: position". The path in front of the colon is how the import finds the page, so keep it as exported. Pages use a full path such as "about/philosophy".', 'arkray-translation-importer' ),
		);

		echo '<div class="card" style="max-width:1100px;">';
		echo '<h2>' . esc_html__( 'Column reference', 'arkray-translation-importer' ) . '</h2>';
		echo '<p>' . esc_html__( 'The same columns are read from an .xlsx and from a CSV. Column order does not matter and headers are case-insensitive; the English name alone is enough ("Vietnamese" as well as "Vietnamese（ベトナム語テキスト）"). Rows without an ID, such as the separator rows, are skipped. When a translation is created, its date, template, featured image, Elementor layout and categories are copied from the original according to the options above.', 'arkray-translation-importer' ) . '</p>';
		echo '<p>' . esc_html__( 'A file may also carry page_id and page_slug columns naming the original post; the import then uses those instead of the ID and the note.', 'arkray-translation-importer' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th style="width:220px;">' . esc_html__( 'Column', 'arkray-translation-importer' ) . '</th>';
		echo '<th>' . esc_html__( 'Meaning', 'arkray-translation-importer' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $columns as $column => $description ) {
			echo '<tr><td><code>' . esc_html( $column ) . '</code></td><td>' . esc_html( $description ) . '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h3>' . esc_html__( 'How an ID is built', 'arkray-translation-importer' ) . '</h3>';
		echo '<p>' . esc_html__( 'After the page slug comes the position of the block:', 'arkray-translation-importer' ) . '</p>';
		echo '<ul style="list-style:disc;margin-left:20px;">';
		echo '<li><code>title</code> — ' . esc_html__( 'the page or post title', 'arkray-translation-importer' ) . '</li>';
		echo '<li><code>p01</code>, <code>h01</code>, <code>li01</code>, <code>cap01</code> — ' . esc_html__( 'paragraph, heading, list item, figure caption', 'arkray-translation-importer' ) . '</li>';
		echo '<li><code>tbl1-r2-c1</code>, <code>tbl1-caption</code> — ' . esc_html__( 'one table cell (table, row, column) and the caption of a table', 'arkray-translation-importer' ) . '</li>';
		echo '<li><code>img01</code> — ' . esc_html__( 'an image; its row carries the file name and caption instead of text', 'arkray-translation-importer' ) . '</li>';
		echo '<li><code>p03-2</code> — ' . esc_html__( 'the second piece of paragraph 3, which happens when a link or a bold word breaks the sentence', 'arkray-translation-importer' ) . '</li>';
		echo '</ul>';

		echo '<p class="description">' . esc_html__( 'Text that lives outside the post content (page builder layouts, widgets, menus, ACF fields) and the contents of preformatted blocks are not part of this file. Files exported by earlier versions of this plugin are still read: a file with a content_id column addresses text by position, and a file with only id, slug and content columns replaces the whole HTML of a page.', 'arkray-translation-importer' ) . '</p>';
		echo '</div>';
	}
}
