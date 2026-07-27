<?php
/**
 * Hello Elementor Child Theme Functions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// ACF fallbacks — keeps all templates working when ACF plugin is not active.
if ( ! function_exists( 'get_field' ) ) {
	function get_field( $key, $post_id = false ) { return null; }
}
if ( ! function_exists( 'get_fields' ) ) {
	function get_fields( $post_id = false ) { return array(); }
}

// Disable WordPress smart-quote/em-dash conversion on titles so they render
// exactly as stored (reference arkray.co.jp uses straight quotes and hyphens).
remove_filter( 'the_title', 'wptexturize' );
remove_filter( 'the_content', 'wptexturize' );
remove_filter( 'the_excerpt', 'wptexturize' );

// Hide WordPress version from the HTML generator meta tag.
remove_action( 'wp_head', 'wp_generator' );

/**
 * Restrict the REST API to logged-in editors.
 *
 * Custom post types are registered with show_in_rest, which otherwise exposes
 * post-type names, product counts (X-WP-Total), and content to anonymous
 * requests. wp-admin and Elementor keep working for users who can edit content.
 *
 * @param WP_Error|true|null $result Existing authentication error, if any.
 * @return WP_Error|true|null
 */
function arkray_restrict_rest_api_access( $result ) {
	if ( ! empty( $result ) ) {
		return $result;
	}

	if ( ! is_user_logged_in() ) {
		return new WP_Error(
			'rest_not_logged_in',
			__( 'Only authenticated users can access the REST API.', 'arkray' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_pages' ) ) {
		return new WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to access the REST API.', 'arkray' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	return $result;
}
add_filter( 'rest_authentication_errors', 'arkray_restrict_rest_api_access' );

/**
 * Translate shared theme chrome strings through Polylang when available.
 *
 * @param string $text Source string registered in Polylang > Languages > Strings.
 * @return string
 */
function arkray_t( $text ) {
	return function_exists( 'pll__' ) ? pll__( $text ) : $text;
}

/**
 * Resolve the post/page ID of the translation for the current language.
 *
 * Falls back to the original ID when Polylang is inactive or no translation
 * is linked, so the theme keeps working without Polylang.
 *
 * @param int|WP_Post|null $post Post ID or object.
 * @return int Translated post ID, or 0 when the input is empty.
 */
function arkray_pll_post_id( $post ) {
	$post_id = ( $post instanceof WP_Post ) ? $post->ID : (int) $post;
	if ( $post_id <= 0 ) {
		return 0;
	}

	if ( function_exists( 'pll_get_post' ) && function_exists( 'pll_current_language' ) ) {
		$lang = pll_current_language();
		if ( $lang ) {
			$translated = pll_get_post( $post_id, $lang );
			if ( $translated ) {
				return (int) $translated;
			}
		}
	}

	return $post_id;
}

/**
 * Permalink of the translated counterpart for the current language.
 *
 * @param int|WP_Post|null $post Post ID or object.
 * @return string Permalink, or '' when the page cannot be resolved.
 */
function arkray_pll_permalink( $post ) {
	$post_id = arkray_pll_post_id( $post );
	return $post_id ? (string) get_permalink( $post_id ) : '';
}

/**
 * Language-aware home URL.
 *
 * Uses pll_home_url() so the homepage and virtual routes follow the current
 * language. Falls back to home_url() when Polylang is inactive.
 *
 * @param string $path Optional path appended to the language home URL.
 * @return string
 */
function arkray_home_url( $path = '/' ) {
	if ( function_exists( 'pll_home_url' ) ) {
		$home = pll_home_url();
		if ( $home ) {
			if ( '' === $path || '/' === $path ) {
				return $home;
			}
			return trailingslashit( $home ) . ltrim( $path, '/' );
		}
	}

	return home_url( $path );
}

/**
 * Language-aware canonical Privacy Policy URL.
 *
 * @return string
 */
function arkray_get_privacy_policy_url() {
	return arkray_home_url( '/policy/' );
}

/**
 * Language-aware canonical Website Terms of Use URL.
 *
 * @return string
 */
function arkray_get_terms_of_use_url() {
	return arkray_home_url( '/use/' );
}

/**
 * Language-aware canonical Site Map URL.
 *
 * @return string
 */
function arkray_get_site_map_url() {
	return arkray_home_url( '/sitemap/' );
}

/**
 * Register header, footer, and sidebar labels for Polylang string translation.
 */
function arkray_register_polylang_strings() {
	if ( ! function_exists( 'pll_register_string' ) ) {
		return;
	}

	$strings = array(
		// Header / global navigation.
		'Select',
		'Vietnam site',
		// Global gateway modal.
		'Please select your Region.',
		'Close',
		'News & Topics',
		'Products',
		'History of Pioneers',
		'Events & Gallery',
		'About Us',
		'Sustainability',
		'Recruitment',
		// Footer.
		'Privacy Policy',
		'Website Terms of Use',
		'Site Map',
		'Contact Us',
		'Copyright© %s ARKRAY, Inc. All Rights Reserved.',
		// Shared content labels.
		'Media Gallery',
		'more',
		'Line up',
		'Download for more information',
		// 404 page.
		'Page not found',
		'We could not find the page you were looking for.',
		'The address may be incorrect, or the page may have been moved. Use one of the options below to continue.',
		'Back to home',
		'View site map',
		'Search ARKRAY',
		'Search',
		'Popular destinations',
		'Requested page',
		'Footer navigation',
		// About sidebar sub-items.
		'ARKRAY Philosophy',
		'Message from ARKRAY',
		'Brand Concept',
		'Contact',
		'Corporate Outline',
		'History',
		'ARKRAY Group',
		'Access',
		'ARKRAY Action Guidelines',
		'Download Company Profile [PDF]',
		'Download Company Profile',
		// Sustainability sidebar sub-items.
		'Top Commitment',
		'SDGs Basic Policy',
		'ARKRAY’s Materiality',
		'SDGs Initiatives',
		// Events & Gallery sidebar / content labels.
		'Events',
		'All',
		'Local',
		'Back to Events',
		'Related Products',
	);

	foreach ( $strings as $string ) {
		pll_register_string( 'arkray-' . sanitize_title( $string ), $string, 'ARKRAY Theme' );
	}
}
add_action( 'init', 'arkray_register_polylang_strings' );

/**
 * Render the language switcher shown in the header.
 */
function arkray_render_language_switcher() {
	if ( function_exists( 'pll_the_languages' ) ) {
		$languages = pll_the_languages(
			array(
				'raw'              => 1,
				'hide_if_empty'    => 0,
				'display_names_as' => 'name',
				'hide_current'     => 0,
				'force_home'       => 0,
			)
		);

		if ( ! empty( $languages ) && is_array( $languages ) ) {
			echo '<ul class="language">';
			foreach ( $languages as $language ) {
				$is_current = ! empty( $language['current_lang'] );
				$name       = isset( $language['name'] ) ? $language['name'] : '';
				$url        = isset( $language['url'] ) ? $language['url'] : '';

				if ( $is_current ) {
					echo '<li class="ac">' . esc_html( $name ) . '</li>';
				} else {
					echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a></li>';
				}
			}
			echo '</ul>';
			return;
		}
	}

	echo '<ul class="language">';
	echo '<li class="ac">English</li>';
	echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">Tiếng Việt</a></li>';
	echo '</ul>';
}

/**
 * Use full-word language codes in URLs and prefix the default language too.
 *
 * Renames the Polylang URL slugs so the site serves:
 *   - English    at /english/...    (default language is also prefixed)
 *   - Tiếng Việt at /vietnamese/...
 *
 * The change is applied through Polylang's own model so existing posts, terms,
 * menus and the default-language option stay in sync, the language cache is
 * cleaned, and rewrite rules are flushed. It runs in wp-admin and is guarded by
 * an option flag so it only executes once. To re-run it (e.g. after changing the
 * desired slugs), delete the `arkray_pll_language_slugs_v1` option.
 */
function arkray_set_polylang_language_slugs() {
	if ( ! is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}

	if ( ! function_exists( 'PLL' ) ) {
		return; // Polylang inactive.
	}

	$flag = 'arkray_pll_language_slugs_v1';
	if ( get_option( $flag ) ) {
		return; // Already applied.
	}

	$pll = PLL();
	if ( empty( $pll ) || empty( $pll->model ) ) {
		return;
	}

	$model = $pll->model;
	if ( ! method_exists( $model, 'get_languages_list' ) || ! method_exists( $model, 'update_language' ) ) {
		return; // Unexpected Polylang version.
	}

	$languages = $model->get_languages_list();
	if ( empty( $languages ) || ! is_array( $languages ) ) {
		return; // Languages not configured yet — retry on a later load.
	}

	// Current short slug / locale prefix => desired full-word URL slug.
	$slug_map = array(
		'en' => 'english',
		'vi' => 'vietnamese',
	);

	foreach ( $languages as $language ) {
		if ( empty( $language->term_id ) ) {
			continue;
		}

		$current       = (string) $language->slug;
		$locale_prefix = strtolower( substr( (string) $language->locale, 0, 2 ) );

		$target = '';
		if ( isset( $slug_map[ $current ] ) ) {
			$target = $slug_map[ $current ];
		} elseif ( isset( $slug_map[ $locale_prefix ] ) ) {
			$target = $slug_map[ $locale_prefix ];
		}

		if ( '' === $target || $current === $target ) {
			continue; // Nothing to do (or already renamed).
		}

		// update_language() backfills name/locale/rtl/flag from the existing
		// language and also updates translations, menu locations and the
		// default-language option, cleans the cache and flushes rewrite rules.
		$model->update_language(
			array(
				'lang_id' => (int) $language->term_id,
				'slug'    => $target,
			)
		);
	}

	// Show the language code in the URL for every language, including the
	// default one, using the directory URL mode (/english/..., /vietnamese/...).
	if ( isset( $model->options ) && is_object( $model->options ) && method_exists( $model->options, 'set' ) ) {
		$model->options->set( 'force_lang', 1 );
		$model->options->set( 'hide_default', 0 );
		if ( method_exists( $model->options, 'save' ) ) {
			$model->options->save();
		}
	}

	flush_rewrite_rules();

	update_option( $flag, time() );
}
add_action( 'admin_init', 'arkray_set_polylang_language_slugs', 20 );

add_action( 'wp_enqueue_scripts', 'hello_elementor_child_enqueue_styles' );

// Suppress Hello Elementor parent styles via filters (fires before enqueue)
add_filter( 'hello_elementor_enqueue_style',       '__return_false' );
add_filter( 'hello_elementor_enqueue_theme_style', '__return_false' );

/**
 * Site-wide font stack when Google Fonts are not loaded.
 */
define( 'ARKRAY_FONT_STACK', '"Linotype Univers", Helvetica, Arial, sans-serif' );

/**
 * Disable Elementor Google Fonts (Settings → Google Fonts → Disable).
 *
 * Fonts fall back to Linotype Univers → Helvetica → Arial.
 */
function arkray_disable_elementor_google_fonts() {
	if ( '0' !== get_option( 'elementor_google_font' ) ) {
		update_option( 'elementor_google_font', '0' );
	}
}
add_action( 'init', 'arkray_disable_elementor_google_fonts', 1 );

/**
 * Override Elementor kit typography variables so widgets use the local stack.
 */
function arkray_elementor_font_fallback_css() {
	$stack = ARKRAY_FONT_STACK;
	$css   = ':root,.elementor-kit{'
		. '--e-global-typography-primary-font-family:' . $stack . ';'
		. '--e-global-typography-secondary-font-family:' . $stack . ';'
		. '--e-global-typography-text-font-family:' . $stack . ';'
		. '--e-global-typography-accent-font-family:' . $stack . ';'
		. '}';
	wp_register_style( 'arkray-elementor-font-fallback', false );
	wp_enqueue_style( 'arkray-elementor-font-fallback' );
	wp_add_inline_style( 'arkray-elementor-font-fallback', $css );
}
add_action( 'wp_enqueue_scripts', 'arkray_elementor_font_fallback_css', 5 );

/**
 * Detect whether the current request uses an arkray template (homepage or any
 * of the cloned ARKRAY page templates). We treat all of them the same way:
 * strip every Elementor / Hello Elementor / WP block style so the only CSS
 * applied is the verbatim arkray.co.jp stack.
 */
function arkray_is_arkray_page() {
	if ( is_admin() ) { return false; }
	if ( is_404() ) { return true; }
	if ( is_front_page() ) { return true; }
	// Match any page using a template-*.php file from this child theme.
	if ( is_page_template() ) {
		$tpl = get_page_template_slug();
		if ( $tpl && strpos( $tpl, 'template-' ) === 0 ) { return true; }
	}
	// CPT single/archive views (product, news, event, recruitment).
	if ( is_singular( array( 'product', 'news', 'event', 'recruitment', 'gallery' ) ) ) { return true; }
	if ( is_post_type_archive( array( 'product', 'news', 'event', 'recruitment', 'gallery' ) ) ) { return true; }
	// Virtual routes (about, sustainability, history, events_gallery, etc.) use
	// the helper functions registered above to route to a template-*.php.
	$virtual_checkers = array(
		'arkray_get_about_route_key_from_request',
		'arkray_get_sustainability_route_key_from_request',
		'arkray_get_history_route_key_from_request',
		'arkray_get_events_gallery_route_key_from_request',
		'arkray_get_recruitment_route_key_from_request',
	);
	foreach ( $virtual_checkers as $fn ) {
		if ( function_exists( $fn ) && '' !== $fn() ) { return true; }
	}
	return false;
}

// Dequeue all Hello Elementor + Elementor plugin styles on every arkray page
add_action( 'wp_enqueue_scripts', function() {
	if ( ! arkray_is_arkray_page() ) { return; }
	wp_dequeue_style( 'hello-elementor' );
	wp_dequeue_style( 'hello-elementor-theme-style' );
	wp_dequeue_style( 'hello-elementor-header-footer' );
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'global-styles' );
	wp_dequeue_style( 'classic-theme-styles' );
	// Elementor global kit CSS (post-N.css) and frontend.css
	wp_dequeue_style( 'elementor-frontend' );
	wp_dequeue_style( 'elementor-icons' );
	wp_dequeue_style( 'elementor-wp-admin-bar' );
	// Sweep ANY handle that starts with elementor-, e-, hello-, or any Google
	// font handle injected by Elementor (elementor-gf-*). The original
	// arkray.co.jp uses Arial/Hiragino fallback ONLY — no Google Fonts.
	global $wp_styles;
	if ( isset( $wp_styles->registered ) ) {
		foreach ( $wp_styles->registered as $handle => $style ) {
			if (
				strpos( $handle, 'elementor' ) !== false
				|| strpos( $handle, 'e-' ) === 0
				|| strpos( $handle, 'hello-' ) === 0
				|| strpos( $handle, '-gf-' ) !== false // elementor-gf-roboto, elementor-gf-robotoslab
			) {
				wp_dequeue_style( $handle );
				wp_deregister_style( $handle );
			}
		}
	}
	// Also strip the wp-emoji styles + admin bar inline styles that leak
	// font-family declarations onto <body>.
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
}, 999 );

function hello_elementor_child_enqueue_styles() {
	$ver = wp_get_theme()->get( 'Version' );
	$dir = get_stylesheet_directory_uri();
	$arkray_common_path = get_stylesheet_directory() . '/assets/arkray-common.css';
	$arkray_common_ver  = file_exists( $arkray_common_path ) ? (string) filemtime( $arkray_common_path ) : $ver;
	$arkray_content_path = get_stylesheet_directory() . '/assets/arkray-content.css';
	$arkray_content_ver  = file_exists( $arkray_content_path ) ? (string) filemtime( $arkray_content_path ) : $ver;
	$arkray_js_path = get_stylesheet_directory() . '/assets/arkray.js';
	$arkray_js_ver  = file_exists( $arkray_js_path ) ? (string) filemtime( $arkray_js_path ) : $ver;
	$arkray_404_path = get_stylesheet_directory() . '/assets/arkray-404.css';
	$arkray_404_ver  = file_exists( $arkray_404_path ) ? (string) filemtime( $arkray_404_path ) : $ver;

	// Original arkray.co.jp stylesheets — pixel-perfect source of truth
	wp_enqueue_style( 'arkray-bxslider',      $dir . '/assets/jquery.bxslider.css', array(), $ver );
	wp_enqueue_style( 'arkray-common',        $dir . '/assets/arkray-common.css',   array( 'arkray-bxslider' ), $arkray_common_ver );
	wp_enqueue_style( 'arkray-content',       $dir . '/assets/arkray-content.css',  array( 'arkray-common' ), $arkray_content_ver );
	wp_enqueue_style( 'arkray-home',          $dir . '/assets/arkray-home.css',     array( 'arkray-content' ), $ver );
	if ( is_404() ) {
		wp_enqueue_style( 'arkray-404', $dir . '/assets/arkray-404.css', array( 'arkray-content' ), $arkray_404_ver );
	}

	// jQuery (WP ships it; bxSlider needs it)
	wp_enqueue_script( 'jquery' );

	// bxSlider
	wp_enqueue_script(
		'arkray-bxslider-js',
		$dir . '/assets/arkray-bxslider.min.js',
		array( 'jquery' ),
		$ver,
		true
	);

	// Site-wide behaviour (page-top button, mobile menu, bxSlider init)
	wp_enqueue_script(
		'arkray-js',
		$dir . '/assets/arkray.js',
		array( 'jquery', 'arkray-bxslider-js' ),
		$arkray_js_ver,
		true
	);
	wp_localize_script( 'arkray-js', 'arkrayVars', array(
		'homeUrl' => home_url( '/' ),
	) );
}

/**
 * Apply live-site responsive classes on <html> before paint (skel parity).
 *
 * Breakpoints: pc ≤1199px, tablet ≤990px, sp ≤767px.
 */
function arkray_responsive_class_inline() {
	if ( ! arkray_is_arkray_page() ) {
		return;
	}
	?>
<script>
(function(){
	var root = document.documentElement;
	var mq = {
		sp: window.matchMedia('(max-width: 767px)'),
		tablet: window.matchMedia('(max-width: 990px)'),
		pc: window.matchMedia('(max-width: 1199px)')
	};
	function arkrayUpdateViewportClasses(){
		root.classList.toggle('sp', mq.sp.matches);
		root.classList.toggle('tablet', mq.tablet.matches);
		root.classList.toggle('pc', mq.pc.matches);
	}
	function arkrayBindMq(mql){
		if ( mql.addEventListener ) {
			mql.addEventListener('change', arkrayUpdateViewportClasses);
		} else if ( mql.addListener ) {
			mql.addListener(arkrayUpdateViewportClasses);
		}
	}
	arkrayUpdateViewportClasses();
	arkrayBindMq(mq.sp);
	arkrayBindMq(mq.tablet);
	arkrayBindMq(mq.pc);
	window.addEventListener('resize', arkrayUpdateViewportClasses);
	window.addEventListener('orientationchange', arkrayUpdateViewportClasses);
	if ( window.visualViewport ) {
		window.visualViewport.addEventListener('resize', arkrayUpdateViewportClasses);
	}
})();
</script>
	<?php
}
add_action( 'wp_head', 'arkray_responsive_class_inline', 1 );

/**
 * Normalize news titles for reliable fallback matching.
 *
 * @param string $title Raw news title.
 * @return string
 */
function arkray_normalize_news_title( $title ) {
	$title = wp_strip_all_tags( (string) $title );
	$title = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$title = strtolower( trim( preg_replace( '/\s+/', ' ', $title ) ) );
	return $title;
}

/**
 * Build a map of fallback image URLs from scraped reference news pages.
 *
 * Returns:
 * - by_date_title: keyed as "M d, Y|normalized title"
 * - by_title: keyed as "normalized title"
 *
 * @return array{by_date_title: array<string, string>, by_title: array<string, string>}
 */
function arkray_get_reference_news_image_map() {
	static $map = null;

	if ( null !== $map ) {
		return $map;
	}

	$map = array(
		'by_date_title' => array(),
		'by_title'      => array(),
		'by_date'       => array(), // last-resort: date-only lookup (first image per date)
	);

	$pages_dir = ABSPATH . '_reference/arkray-live/scraped/pages/';
	if ( ! is_dir( $pages_dir ) ) {
		return $map;
	}

	$files = glob( $pages_dir . 'news__*__index.html' );
	if ( file_exists( $pages_dir . 'news__index.html' ) ) {
		$files[] = $pages_dir . 'news__index.html';
	}

	if ( empty( $files ) ) {
		return $map;
	}

	$files = array_values( array_unique( $files ) );

	$resolve_image_url = static function( $raw_src ) {
		$raw_src = trim( (string) $raw_src );
		if ( '' === $raw_src ) {
			return '';
		}

		$path_part = parse_url( $raw_src, PHP_URL_PATH );
		$basename_raw = $path_part ? wp_basename( $path_part ) : wp_basename( $raw_src );
		if ( '' === $basename_raw ) {
			return '';
		}

		$basename_decoded = rawurldecode( $basename_raw );
		$candidates       = array_unique( array( $basename_raw, $basename_decoded ) );
		foreach ( $candidates as $candidate ) {
			if ( '' === $candidate ) {
				continue;
			}
			$local_rel = 'wp-content/uploads/arkray-assets/' . $candidate;
			$local_abs = ABSPATH . $local_rel;
			if ( file_exists( $local_abs ) ) {
				return home_url( '/wp-content/uploads/arkray-assets/' . rawurlencode( $candidate ) );
			}
		}

		return $raw_src;
	};

	libxml_use_internal_errors( true );
	foreach ( $files as $file ) {
		if ( ! is_readable( $file ) ) {
			continue;
		}

		$html = file_get_contents( $file );
		if ( false === $html || '' === $html ) {
			continue;
		}

		$dom = new DOMDocument();
		if ( ! @$dom->loadHTML( $html ) ) {
			continue;
		}

		$xpath = new DOMXPath( $dom );
		$boxes = $xpath->query( "//div[contains(concat(' ', normalize-space(@class), ' '), ' box ')]" );
		if ( ! $boxes || 0 === $boxes->length ) {
			continue;
		}

		foreach ( $boxes as $box ) {
			$date_node = $xpath->query( ".//p[contains(concat(' ', normalize-space(@class), ' '), ' date ')]", $box )->item( 0 );
			$title_node = $xpath->query(
				".//p[contains(concat(' ', normalize-space(@class), ' '), ' tx ') or contains(concat(' ', normalize-space(@class), ' '), ' tx_long ')]//a",
				$box
			)->item( 0 );
			$img_node = $xpath->query( ".//p[contains(concat(' ', normalize-space(@class), ' '), ' img ')]//img", $box )->item( 0 );

			if ( ! $title_node || ! $img_node ) {
				continue;
			}

			$title_key = arkray_normalize_news_title( $title_node->textContent );
			if ( '' === $title_key ) {
				continue;
			}

			$image_url = $resolve_image_url( $img_node->getAttribute( 'src' ) );
			if ( '' === $image_url ) {
				continue;
			}

			if ( ! isset( $map['by_title'][ $title_key ] ) ) {
				$map['by_title'][ $title_key ] = $image_url;
			}

			$date_key = $date_node ? trim( preg_replace( '/\s+/', ' ', $date_node->textContent ) ) : '';
			if ( '' !== $date_key ) {
				$combo_key = $date_key . '|' . $title_key;
				if ( ! isset( $map['by_date_title'][ $combo_key ] ) ) {
					$map['by_date_title'][ $combo_key ] = $image_url;
				}
				// by_date: first image seen for each date (last-resort fallback)
				if ( ! isset( $map['by_date'][ $date_key ] ) ) {
					$map['by_date'][ $date_key ] = $image_url;
				}
			}
		}
	}
	libxml_clear_errors();

	return $map;
}

/**
 * Resolve a fallback image URL for a news item by date+title, then by title.
 *
 * @param string $title News title.
 * @param string $date  Optional date label in "M d, Y" format.
 * @return string
 */
function arkray_get_news_fallback_image_url( $title, $date = '' ) {
	$title_key = arkray_normalize_news_title( $title );
	if ( '' === $title_key ) {
		return '';
	}

	$map = arkray_get_reference_news_image_map();
	if ( ! empty( $date ) ) {
		$combo_key = trim( (string) $date ) . '|' . $title_key;
		if ( isset( $map['by_date_title'][ $combo_key ] ) ) {
			return (string) $map['by_date_title'][ $combo_key ];
		}
	}

	if ( isset( $map['by_title'][ $title_key ] ) ) {
		return (string) $map['by_title'][ $title_key ];
	}

	// Last resort: match by date alone (first image recorded for that date).
	if ( ! empty( $date ) ) {
		$date_key = trim( (string) $date );
		if ( isset( $map['by_date'][ $date_key ] ) ) {
			return (string) $map['by_date'][ $date_key ];
		}
	}

	return '';
}

/**
 * HTTP GET with transient cache for origin page fetches.
 *
 * @param string $url       Remote URL.
 * @param string $cache_key Transient key.
 * @param int    $ttl       Cache lifetime in seconds.
 * @return string
 */
function arkray_http_get_cached( $url, $cache_key, $ttl = DAY_IN_SECONDS ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}

	$cached = get_transient( $cache_key );
	if ( is_string( $cached ) && '' !== $cached ) {
		return $cached;
	}

	$response = wp_remote_get(
		$url,
		array(
			'timeout'    => 25,
			'user-agent' => 'Mozilla/5.0 (compatible; ARKRAY-WP/1.0)',
		)
	);
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return '';
	}

	$body = (string) wp_remote_retrieve_body( $response );
	if ( '' === $body ) {
		return '';
	}

	set_transient( $cache_key, $body, $ttl );
	return $body;
}

/**
 * Parse news index rows (date, title, href) from an index HTML document.
 *
 * @param string $html Raw HTML.
 * @return array<int, array{date:string,title:string,href:string}>
 */
function arkray_parse_news_index_rows_from_html( $html ) {
	$rows = array();
	$html = (string) $html;
	if ( '' === $html ) {
		return $rows;
	}

	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	if ( ! @$dom->loadHTML( $html ) ) {
		libxml_clear_errors();
		return $rows;
	}

	$xp    = new DOMXPath( $dom );
	$panel = $xp->query( "(//div[contains(concat(' ', normalize-space(@class), ' '), ' tab_index_area ')])[1]" )->item( 0 );
	if ( ! $panel ) {
		libxml_clear_errors();
		return $rows;
	}

	$boxes = $xp->query( ".//div[contains(concat(' ', normalize-space(@class), ' '), ' box ')]", $panel );
	if ( ! $boxes ) {
		libxml_clear_errors();
		return $rows;
	}

	foreach ( $boxes as $box ) {
		$date_node = $xp->query( ".//p[contains(concat(' ', normalize-space(@class), ' '), ' date ')]", $box )->item( 0 );
		$link_node = $xp->query(
			".//p[contains(concat(' ', normalize-space(@class), ' '), ' tx ') or contains(concat(' ', normalize-space(@class), ' '), ' tx_long ')]//a",
			$box
		)->item( 0 );
		if ( ! $date_node || ! $link_node ) {
			continue;
		}

		$date  = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( $date_node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		$title = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( $link_node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		$href  = trim( (string) $link_node->getAttribute( 'href' ) );

		if ( '' === $date || '' === $title || '' === $href ) {
			continue;
		}

		$key = $date . '|' . $title;
		if ( ! isset( $rows[ $key ] ) ) {
			$rows[ $key ] = array(
				'date'  => $date,
				'title' => $title,
				'href'  => $href,
			);
		}
	}

	libxml_clear_errors();
	return array_values( $rows );
}

/**
 * Aggressive title normalization for origin news index matching.
 *
 * @param string $title Raw title.
 * @return string
 */
function arkray_normalize_origin_news_title( $title ) {
	$title = html_entity_decode( wp_strip_all_tags( (string) $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$title = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $title );
	return strtolower( trim( preg_replace( '/\s+/u', ' ', $title ) ) );
}

/**
 * Score title similarity for origin news matching.
 *
 * @param string $ref_title Origin title.
 * @param string $wp_title  Local title.
 * @return int
 */
function arkray_score_origin_news_title_match( $ref_title, $wp_title ) {
	$ref_norm = arkray_normalize_origin_news_title( $ref_title );
	$wp_norm  = arkray_normalize_origin_news_title( $wp_title );

	if ( $ref_norm === $wp_norm ) {
		return 1000;
	}
	if ( false !== strpos( $ref_norm, $wp_norm ) || false !== strpos( $wp_norm, $ref_norm ) ) {
		return 900;
	}

	similar_text( $ref_norm, $wp_norm, $pct );
	return (int) round( $pct );
}

/**
 * Resolve the best-matching origin news detail URL for a local post.
 *
 * @param string $date_fmt Date label in "M d, Y" format.
 * @param string $title    News title.
 * @param int    $post_id  Optional news post ID.
 * @return string
 */
function arkray_resolve_origin_news_detail_url( $date_fmt, $title, $post_id = 0 ) {
	$post_id = (int) $post_id;
	if ( $post_id > 0 ) {
		$external = trim( (string) get_field( 'news_external_url', $post_id ) );
		if ( '' !== $external ) {
			return $external;
		}
	}

	$year_hint = '';
	if ( preg_match( '/\b(\d{4})\b/', (string) $date_fmt, $ym ) ) {
		$year_hint = $ym[1];
	}

	$index_urls = array();
	if ( $year_hint ) {
		$index_urls[] = 'https://www.arkray.co.jp/english/news/' . $year_hint . '/index.html';
	}
	$index_urls[] = 'https://www.arkray.co.jp/english/news/index.html';

	$pages_dir = ABSPATH . '_reference/arkray-live/scraped/pages/';
	if ( is_dir( $pages_dir ) ) {
		if ( $year_hint && file_exists( $pages_dir . 'news__' . $year_hint . '__index.html' ) ) {
			array_unshift( $index_urls, $pages_dir . 'news__' . $year_hint . '__index.html' );
		}
		if ( file_exists( $pages_dir . 'news__index.html' ) ) {
			$index_urls[] = $pages_dir . 'news__index.html';
		}
	}

	$index_urls = array_values( array_unique( $index_urls ) );
	$candidates = array();

	foreach ( $index_urls as $source ) {
		if ( 0 === strpos( $source, 'http' ) ) {
			$html = arkray_http_get_cached( $source, 'arkray_news_idx_' . md5( $source ) );
		} else {
			$html = is_readable( $source ) ? (string) file_get_contents( $source ) : '';
		}

		foreach ( arkray_parse_news_index_rows_from_html( $html ) as $row ) {
			if ( $row['date'] !== $date_fmt ) {
				continue;
			}
			$candidates[] = array(
				'score' => arkray_score_origin_news_title_match( $row['title'], $title ),
				'href'  => $row['href'],
			);
		}
	}

	if ( empty( $candidates ) ) {
		foreach ( $index_urls as $source ) {
			if ( 0 === strpos( $source, 'http' ) ) {
				$html = arkray_http_get_cached( $source, 'arkray_news_idx_' . md5( $source ) );
			} else {
				$html = is_readable( $source ) ? (string) file_get_contents( $source ) : '';
			}

			foreach ( arkray_parse_news_index_rows_from_html( $html ) as $row ) {
				$score = arkray_score_origin_news_title_match( $row['title'], $title );
				if ( $year_hint && preg_match( '#/english/news/' . preg_quote( $year_hint, '#' ) . '/#', $row['href'] ) ) {
					$score += 20;
				}
				$candidates[] = array(
					'score' => $score,
					'href'  => $row['href'],
				);
			}
		}
	}

	if ( empty( $candidates ) ) {
		return '';
	}

	usort(
		$candidates,
		function( $a, $b ) {
			return $b['score'] <=> $a['score'];
		}
	);

	return $candidates[0]['score'] >= 80 ? (string) $candidates[0]['href'] : '';
}

/**
 * Extract innerHTML from a DOMElement.
 *
 * @param DOMElement|null $element DOM node.
 * @return string
 */
function arkray_dom_inner_html( $element ) {
	$html = '';
	if ( ! $element || ! $element->hasChildNodes() ) {
		return $html;
	}
	foreach ( $element->childNodes as $child ) {
		$html .= $element->ownerDocument->saveHTML( $child );
	}
	return $html;
}

/**
 * Sanitize origin news HTML while preserving layout hooks and inline styles.
 *
 * @param string $html Raw HTML.
 * @return string
 */
function arkray_sanitize_origin_news_html( $html ) {
	$html = (string) $html;

	$html = preg_replace(
		'#<(script|iframe|object|embed|form|input|button|select|option|textarea)[^>]*>.*?</\1>#is',
		'',
		$html
	);
	$html = preg_replace(
		'#<(script|iframe|object|embed|form|input|button|select|option|textarea)[^>]*/?\s*>#is',
		'',
		$html
	);
	$html = preg_replace( '/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $html );
	$html = preg_replace( '/\b(href|src|action)\s*=\s*(["\']?)\s*javascript:[^\s"\'>{]*/i', '$1=$2#', $html );

	return $html;
}

/**
 * Extract and sanitize the origin news article block from a detail page.
 *
 * @param string $html Full detail page HTML.
 * @return string
 */
function arkray_extract_origin_news_editor_html( $html ) {
	$html = (string) $html;
	if ( '' === $html ) {
		return '';
	}

	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	if ( ! @$dom->loadHTML( $html ) ) {
		libxml_clear_errors();
		return '';
	}

	$xp     = new DOMXPath( $dom );
	$editor = $xp->query( "//div[@id='editor_area' and contains(concat(' ', normalize-space(@class), ' '), ' news_area ')]" )->item( 0 );
	libxml_clear_errors();

	if ( ! $editor ) {
		return '';
	}

	return arkray_sanitize_origin_news_html( arkray_dom_inner_html( $editor ) );
}

/**
 * Resolve a local scraped news detail file from an origin detail URL.
 *
 * @param string $detail_url Origin detail URL.
 * @return string Absolute file path or empty string.
 */
function arkray_origin_news_detail_url_to_local_file( $detail_url ) {
	$path = parse_url( (string) $detail_url, PHP_URL_PATH );
	if ( ! $path || ! preg_match( '#/english/news/(\d{4})/([^/]+)\.html$#', $path, $m ) ) {
		return '';
	}

	$file = ABSPATH . '_reference/arkray-live/scraped/pages/news__' . $m[1] . '__' . $m[2] . '.html';
	return is_readable( $file ) ? $file : '';
}

/**
 * Rewrite origin upload images to local copies when available.
 *
 * @param string $html Article HTML.
 * @return string
 */
function arkray_localize_origin_news_html( $html ) {
	$html = (string) $html;
	if ( '' === $html ) {
		return '';
	}

	return preg_replace_callback(
		'#(<img[^>]*\ssrc=")([^"]*upload/img/([^"/]+))(")#i',
		function( $m ) {
			$basename_raw     = wp_basename( $m[3] );
			$basename_decoded = rawurldecode( $basename_raw );
			foreach ( array_unique( array( $basename_raw, $basename_decoded ) ) as $candidate ) {
				if ( '' === $candidate ) {
					continue;
				}
				$local_abs = ABSPATH . 'wp-content/uploads/arkray-assets/' . $candidate;
				if ( file_exists( $local_abs ) ) {
					return $m[1] . home_url( '/wp-content/uploads/arkray-assets/' . rawurlencode( $candidate ) ) . $m[4];
				}
			}
			return $m[1] . $m[2] . $m[4];
		},
		$html
	);
}

/**
 * Whether post content already uses origin news detail markup.
 *
 * @param string $html Post content HTML.
 * @return bool
 */
function arkray_news_content_has_origin_markup( $html ) {
	return (bool) preg_match( '/class\s*=\s*["\'][^"\']*\b(pressrelease|press_box|bgf|col_1)\b/i', (string) $html );
}

/**
 * Fetch origin news article HTML for a news post (local scrape, DB, or live site).
 *
 * @param int $post_id News post ID.
 * @return string Sanitized editor_area HTML or empty string.
 */
function arkray_get_origin_news_article_html( $post_id ) {
	static $cache = array();
	$post_id = (int) $post_id;
	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}

	$title         = get_the_title( $post_id );
	$news_date_raw = (string) get_field( 'news_date', $post_id );
	$date_fmt      = $news_date_raw ? date_i18n( 'M d, Y', strtotime( $news_date_raw ) ) : get_the_date( 'M d, Y', $post_id );

	$detail_url = arkray_resolve_origin_news_detail_url( $date_fmt, $title, $post_id );
	$html       = '';

	if ( '' !== $detail_url ) {
		$local_file = arkray_origin_news_detail_url_to_local_file( $detail_url );
		if ( $local_file ) {
			$raw = @file_get_contents( $local_file );
			if ( false !== $raw && '' !== $raw ) {
				$html = arkray_extract_origin_news_editor_html( $raw );
			}
		}

		if ( '' === $html ) {
			$raw = arkray_http_get_cached( $detail_url, 'arkray_news_art_' . md5( $detail_url ) );
			$html = arkray_extract_origin_news_editor_html( $raw );
		}
	}

	if ( '' === $html ) {
		$body = (string) get_post_field( 'post_content', $post_id );
		if ( arkray_news_content_has_origin_markup( $body ) ) {
			if ( ! preg_match( '/<p\s+class=["\']date["\']/i', $body ) ) {
				$body = '<p class="date">' . esc_html( $date_fmt ) . '</p>' . $body;
			}
			$html = arkray_sanitize_origin_news_html( $body );
		}
	}

	if ( '' !== $html ) {
		$html = arkray_localize_origin_news_html( $html );
	}

	$cache[ $post_id ] = $html;
	return $html;
}

/**
 * Build rendered news detail HTML matching arkray.co.jp editor_area markup.
 *
 * @param int $post_id News post ID.
 * @return string
 */
function arkray_render_news_article_html( $post_id ) {
	$origin_html = arkray_get_origin_news_article_html( $post_id );
	if ( '' !== $origin_html ) {
		return $origin_html;
	}

	$post_id       = (int) $post_id;
	$title         = get_the_title( $post_id );
	$news_date_raw = (string) get_field( 'news_date', $post_id );
	$date_fmt      = $news_date_raw ? date_i18n( 'M d, Y', strtotime( $news_date_raw ) ) : get_the_date( 'M d, Y', $post_id );
	$body          = trim( (string) get_post_field( 'post_content', $post_id ) );

	ob_start();
	?>
<p class="date"><?php echo esc_html( $date_fmt ); ?></p>
<div class="bgf">
	<div class="pressrelease">
		<h2><?php echo esc_html( $title ); ?></h2>
		<div class="press_box cl">
			<?php
			if ( '' !== $body ) {
				echo apply_filters( 'the_content', $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo '<p><em>Article body not available.</em></p>';
			}
			?>
		</div>
	</div>
</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Years that have at least one published news post, newest first.
 *
 * @return string[] Four-digit year strings.
 */
function arkray_get_published_news_years() {
	static $years = null;

	if ( null !== $years ) {
		return $years;
	}

	$years = array();
	$query = new WP_Query(
		array(
			'post_type'              => 'news',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'meta_key'               => 'news_date',
			'orderby'                => 'meta_value',
			'order'                  => 'DESC',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	if ( $query->have_posts() ) {
		foreach ( $query->posts as $post_id ) {
			$date = get_post_meta( $post_id, 'news_date', true );
			if ( ! $date ) {
				continue;
			}
			$year = substr( (string) $date, 0, 4 );
			if ( preg_match( '/^\d{4}$/', $year ) ) {
				$years[] = $year;
			}
		}
	}
	wp_reset_postdata();

	$years = array_values( array_unique( $years ) );
	rsort( $years, SORT_STRING );

	return $years;
}

/**
 * Render the News & Topics nav item with year sub-links.
 *
 * @param bool $active Whether the parent item is the current section.
 */
function arkray_render_news_topics_menu_item( $active = false ) {
	$news_page_url = esc_url( arkray_pll_permalink( get_page_by_path( 'news' ) ) ?: arkray_home_url( '/news/' ) );
	$years         = arkray_get_published_news_years();
	$active_attr   = $active ? ' class="ac"' : '';

	echo '<li><a href="' . $news_page_url . '"' . $active_attr . '>' . esc_html( arkray_t( 'News & Topics' ) ) . '</a>';
	if ( ! empty( $years ) ) {
		echo '<ul style="display: block;">';
		foreach ( $years as $year ) {
			echo '<li><a href="' . esc_url( $news_page_url . '#year-' . esc_attr( $year ) ) . '">' . esc_html( $year ) . '</a></li>';
		}
		echo '</ul>';
	}
	echo '</li>';
}

add_action( 'wp_head', 'arkray_favicon' );
function arkray_favicon() {
	$favicon_url = get_stylesheet_directory_uri() . '/img/favicon.ico';
	echo '<link rel="icon" type="image/x-icon" href="' . esc_url( $favicon_url ) . '">' . "\n";
	echo '<link rel="shortcut icon" type="image/x-icon" href="' . esc_url( $favicon_url ) . '">' . "\n";
}

/**
 * Render the global region-gateway modal (verbatim port of arkray.com's
 * #modal-content / #modal-content_sp). Output runs on wp_body_open so every
 * ARKRAY template renders it without per-template edits.
 *
 * Behavior is wired up in assets/arkray.js: on first visit (no dismissal
 * cookie) the modal is shown, and it is hidden once the visitor closes it or
 * selects a region. The "current" link (this Vietnam site) closes the modal
 * without navigating.
 */
function arkray_render_gateway_modal() {
	if ( ! arkray_is_arkray_page() ) {
		return;
	}

	$lead       = esc_html( arkray_t( 'Please select your Region.' ) );
	$close      = esc_attr( arkray_t( 'Close' ) );
	$vietnam_vi_url = esc_url( home_url( '/vietnamese/?ct=Vietnam' ) );
	$vietnam_en_url = esc_url( home_url( '/english/?ct=Vietnam' ) );

	$current_lang = 'english';
	if ( function_exists( 'pll_current_language' ) ) {
		$lang_slug = pll_current_language( 'slug' );
		if ( $lang_slug ) {
			$current_lang = $lang_slug;
		}
	}
	$is_vi_current = ( 'vietnamese' === $current_lang );
	$vi_current_attr = $is_vi_current ? ' class="current"' : '';
	$en_current_attr = $is_vi_current ? '' : ' class="current"';
	$vietnam_main_url = $is_vi_current ? $vietnam_vi_url : $vietnam_en_url;
	$vi_option_current = $is_vi_current ? ' current="on"' : '';
	$en_option_current = $is_vi_current ? '' : ' current="on"';
	?>
<div id="modal-content">
	<a href="#" id="modal-close" class="modal-close" role="button" aria-label="<?php echo $close; ?>">&times;</a>
	<p class="lead"><?php echo $lead; ?></p>
	<div>
		<ul>
			<li class="title"><a href="https://www.arkray.eu/english/index.html?ct=Europe">Europe</a></li>
			<li class="Benelux"><a href="https://www.arkray.eu/ben/english/index.html?ct=Benelux">Benelux</a> [<a href="https://www.arkray.eu/ben/dutch/index.html?ct=Benelux">Dutch</a> / <a href="https://www.arkray.eu/ben/french/index.html?ct=Benelux">French</a> / <a href="https://www.arkray.eu/ben/english/index.html?ct=Benelux">English</a>]</li>
			<li class="Italy"><a href="https://www.arkray.eu/it/italian/index.html?ct=Italy">Italy</a> [<a href="https://www.arkray.eu/it/italian/index.html?ct=Italy">Italian</a> / <a href="https://www.arkray.eu/it/english/index.html?ct=Italy">English</a>]</li>
			<li class="Portugal"><a href="https://www.arkray.eu/pt/portuguese/index.html?ct=Portugal">Portugal</a> [<a href="https://www.arkray.eu/pt/portuguese/index.html?ct=Portugal">Portuguese</a> / <a href="https://www.arkray.eu/pt/english/index.html?ct=Portugal">English</a>]<br></li>
			<li class="Spain"><a href="https://www.arkray.eu/es/spanish/index.html?ct=Spain">Spain</a> [<a href="https://www.arkray.eu/es/spanish/index.html?ct=Spain">Spanish</a> / <a href="https://www.arkray.eu/es/english/index.html?ct=Spain">English</a>]</li>
			<li class="UnitedKingdomofGreatBritainandNorthernIreland"><a href="https://www.arkray.eu/uk/english/index.html?ct=UnitedKingdom">United Kingdom</a> [<a href="https://www.arkray.eu/uk/english/index.html?ct=UnitedKingdom">English</a>]</li>
			<li class="others"><a href="https://www.arkray.eu/english/index.html?ct=Europe">-Others</a> [<a href="https://www.arkray.eu/english/index.html?ct=Europe">English</a>]</li>
		</ul>
		<ul>
			<li class="title"><a href="https://www.arkray.co.jp/english/?ct=Japan">Middle East</a></li>
		</ul>
		<ul>
			<li class="title"><a href="https://www.arkray.co.jp/english/?ct=Japan">Africa</a></li>
		</ul>
	</div>
	<div>
		<ul>
			<li class="title"><a href="https://www.arkray.asia/?ct=Asia">Asia Pacific</a></li>
			<li class="China"><a href="https://www.arkray.cn/chinese/index.html?ct=China">China</a> [<a href="https://www.arkray.cn/chinese/index.html?ct=China">&#20013;&#25991;&#65288;&#31777;&#20307;&#65289;</a>/<a href="https://www.arkray.cn/english/index.html?ct=China">English</a>]</li>
			<li class="India"><a href="https://www.arkray.co.in/english/index.html?ct=India">India</a> [<a href="https://www.arkray.co.in/english/index.html?ct=India">English</a>]</li>
			<li class="Indonesia"><a href="https://www.arkray.id/english/index.html?ct=Indonesia">Indonesia</a> [<a href="https://www.arkray.id/english/index.html?ct=Indonesia">English</a>]</li>
			<li class="Japan"><a href="https://www.arkray.co.jp/japanese/?ct=Japan">Japan</a> [<a href="https://www.arkray.co.jp/japanese/?ct=Japan">&#26085;&#26412;&#35486;</a>/<a href="https://www.arkray.co.jp/english/?ct=Japan">English</a>]</li>
			<li class="Korea"><a href="https://www.arkray.co.kr/korean/index.html?ct=Korea">Korea</a> [<a href="https://www.arkray.co.kr/korean/index.html?ct=Korea">&#54620;&#44397;&#50612;</a>/<a href="https://www.arkray.co.kr/english/index.html?ct=Korea">English</a>]</li>
			<li class="Philippines"><a href="https://www.arkray.ph/english/index.html?ct=Philippines">Philippines</a> [<a href="https://www.arkray.ph/english/index.html?ct=Philippines">English</a>]</li>
			<li class="Thailand"><a href="https://www.arkray.co.th/english/?ct=Thailand">Thailand</a> [<a href="https://www.arkray.co.th/english/?ct=Thailand">English</a>]</li>
			<li class="Vietnam"><a class="current" href="<?php echo $vietnam_main_url; ?>">Vietnam</a> [<a<?php echo $vi_current_attr; ?> href="<?php echo $vietnam_vi_url; ?>">Tiếng Việt</a>/<a<?php echo $en_current_attr; ?> href="<?php echo $vietnam_en_url; ?>">English</a>]</li>
			<li class="others"><a href="https://www.arkray.asia/?ct=Asia">-Others</a> [<a href="https://www.arkray.asia/?ct=Asia">English</a>]</li>
		</ul>
	</div>
	<div>
		<ul>
			<li class="title"><a href="https://arkrayusa.com/">North America</a></li>
			<li class="USA"><a href="https://arkrayusa.com/">United States of America</a> [<a href="https://arkrayusa.com/">English</a>]</li>
		</ul>
		<ul>
			<li class="title"><a href="https://www.arkraylatam.com/spanish/?ct=Latin">Latin America</a></li>
			<li class="latin">[<a href="https://www.arkraylatam.com/spanish/?ct=Latin">Spanish</a>/<a href="https://www.arkraylatam.com/english/?ct=Latin">English</a>]</li>
		</ul>
	</div>
</div>
<div id="modal-content_sp">
	<a href="#" id="modal-close-sp" class="modal-close" role="button" aria-label="<?php echo $close; ?>">&times;</a>
	<p class="lead"><?php echo $lead; ?></p>
	<select id="sp_region">
		<option value="<?php echo $vietnam_vi_url; ?>"<?php echo $vi_option_current; ?>>&nbsp;&nbsp;&nbsp;Vietnam - Tiếng Việt</option>
		<option value="<?php echo $vietnam_en_url; ?>"<?php echo $en_option_current; ?>>&nbsp;&nbsp;&nbsp;Vietnam - English</option>
		<option value="https://www.arkray.asia/english/index.html?ct=Asia">Asia Pacific</option>
		<option value="https://www.arkray.cn/?ct=China">&nbsp;&nbsp;&nbsp;China - Chinese</option>
		<option value="https://www.arkray.cn/english/?ct=China">&nbsp;&nbsp;&nbsp;China - English</option>
		<option value="https://www.arkray.co.in/?ct=India">&nbsp;&nbsp;&nbsp;India</option>
		<option value="https://www.arkray.id/?ct=Indonesia">&nbsp;&nbsp;&nbsp;Indonesia</option>
		<option value="https://www.arkray.co.jp/japanese/?ct=Japan">&nbsp;&nbsp;&nbsp;Japan - Japanese</option>
		<option value="https://www.arkray.co.jp/english/?ct=Japan">&nbsp;&nbsp;&nbsp;Japan - English</option>
		<option value="https://www.arkray.co.kr/?ct=Korea">&nbsp;&nbsp;&nbsp;Korea - Korean</option>
		<option value="https://www.arkray.co.kr/english/?ct=Korea">&nbsp;&nbsp;&nbsp;Korea - English</option>
		<option value="https://www.arkray.ph/english/index.html?ct=Philippines">&nbsp;&nbsp;&nbsp;Philippines</option>
		<option value="https://www.arkray.co.th/english/?ct=Thailand">&nbsp;&nbsp;&nbsp;Thailand</option>
		<option value="https://www.arkray.asia/english/index.html?ct=Asia">&nbsp;&nbsp;&nbsp;-Others</option>
		<option value="https://www.arkray.eu/english/index.html?ct=Europe">Europe</option>
		<option value="https://www.arkray.eu/ben/dutch/index.html?ct=Benelux">&nbsp;&nbsp;&nbsp;Benelux - Dutch</option>
		<option value="https://www.arkray.eu/ben/french/index.html?ct=Benelux">&nbsp;&nbsp;&nbsp;Benelux - French</option>
		<option value="https://www.arkray.eu/ben/english/index.html?ct=Benelux">&nbsp;&nbsp;&nbsp;Benelux - English</option>
		<option value="https://www.arkray.eu/it/italian/index.html?ct=Italy">&nbsp;&nbsp;&nbsp;Italy - Italian</option>
		<option value="https://www.arkray.eu/it/english/index.html?ct=Italy">&nbsp;&nbsp;&nbsp;Italy - English</option>
		<option value="https://www.arkray.eu/pt/portuguese/index.html?ct=Portugal">&nbsp;&nbsp;&nbsp;Portugal - Portuguese</option>
		<option value="https://www.arkray.eu/pt/english/index.html?ct=Portugal">&nbsp;&nbsp;&nbsp;Portugal - English</option>
		<option value="https://www.arkray.eu/es/spanish/index.html?ct=Spain">&nbsp;&nbsp;&nbsp;Spain - Spanish</option>
		<option value="https://www.arkray.eu/es/english/index.html?ct=Spain">&nbsp;&nbsp;&nbsp;Spain - English</option>
		<option value="https://www.arkray.eu/uk/english/index.html?ct=UnitedKingdom">&nbsp;&nbsp;&nbsp;United Kingdom - English</option>
		<option value="https://www.arkray.eu/english/index.html?ct=Europe">&nbsp;&nbsp;&nbsp;-Others</option>
		<option value="https://arkrayusa.com">North America</option>
		<option value="https://arkrayusa.com">&nbsp;&nbsp;&nbsp;United States of America</option>
		<option value="https://www.arkraylatam.com/spanish/?ct=Latin">Latin America - Spanish</option>
		<option value="https://www.arkraylatam.com/english/?ct=Latin">Latin America - English</option>
		<option value="https://www.arkray.co.jp/english/?ct=Japan">Middle East</option>
		<option value="https://www.arkray.co.jp/english/?ct=Japan">Africa</option>
	</select>
</div>
	<?php
}
add_action( 'wp_body_open', 'arkray_render_gateway_modal' );

/**
 * Render the header Google CSE search box (overlay modal results).
 *
 * Uses gcse:search (not searchbox-only) so results open in an overlay on the
 * current page, matching https://www.arkray.co.jp.
 *
 * @param bool $trailing_br Output a line break after the search div.
 */
function arkray_render_google_search( $trailing_br = true ) {
	?>
	<div class="search">
		<gcse:search></gcse:search>
	</div><?php
	if ( $trailing_br ) {
		echo '<br>';
	}
}

/**
 * Google Custom Search Engine — load bootstrap from Google (matches arkray.co.jp).
 *
 * The local google-cse.js snapshot embeds an expired cse_token, which causes
 * "Unauthorized access to internal API". Loading cse.js from Google serves a
 * fresh token and configuration for the search engine.
 */
function arkray_enqueue_google_cse() {
	if ( ! arkray_is_arkray_page() ) {
		return;
	}

	$cx = '000856785468872874231:4egatqikpkm';

	wp_enqueue_script(
		'arkray-google-cse',
		'https://cse.google.com/cse.js?cx=' . rawurlencode( $cx ),
		array(),
		null,
		false
	);
	wp_script_add_data( 'arkray-google-cse', 'async', true );
}
add_action( 'wp_enqueue_scripts', 'arkray_enqueue_google_cse', 20 );

/**
 * About > ARKRAY Group page keys that use the interactive map.
 *
 * @return string[]
 */
function arkray_get_group_about_page_keys() {
	return array(
		'arkray-group',
		'arkray-group-2',
		'arkray-group-3',
		'arkray-group-4',
		'arkray-group-5',
	);
}

/**
 * gmap.js "area" value for each regional Group page.
 *
 * @param string $about_slug About route slug.
 * @return string Empty when the slug is not a Group page.
 */
function arkray_get_group_map_area_for_slug( $about_slug ) {
	$map = array(
		'arkray-group'   => 'World',
		'arkray-group-2' => 'Japan',
		'arkray-group-3' => 'Asia',
		'arkray-group-4' => 'Europe',
		'arkray-group-5' => 'US',
	);
	$about_slug = sanitize_title( (string) $about_slug );

	return isset( $map[ $about_slug ] ) ? $map[ $about_slug ] : '';
}

/**
 * Canonical URLs for ARKRAY Group region tabs (World → group, etc.).
 *
 * @return array<string, string> Region label => page URL.
 */
function arkray_get_group_region_tab_urls() {
	return array(
		'World'    => arkray_get_about_page_url( 'arkray-group' ),
		'Japan'    => arkray_get_about_page_url( 'arkray-group-2' ),
		'Asia'     => arkray_get_about_page_url( 'arkray-group-3' ),
		'Europe'   => arkray_get_about_page_url( 'arkray-group-4' ),
		'Americas' => arkray_get_about_page_url( 'arkray-group-5' ),
	);
}

/**
 * Render the ARKRAY Group region tab bar (World / Japan / Asia / Europe / Americas).
 *
 * @param string $active_region Active tab label.
 * @param string $wrapper_class Wrapper element class list.
 * @param string $wrapper_id    Optional wrapper id attribute.
 * @return void
 */
function arkray_render_group_region_tabs( $active_region = '', $wrapper_class = 'common_tabarea mb20 pt20', $wrapper_id = 'tab' ) {
	$tabs = arkray_get_group_region_tab_urls();
	$id_attr = '' !== (string) $wrapper_id ? ' id="' . esc_attr( $wrapper_id ) . '"' : '';
	?>
	<div class="<?php echo esc_attr( $wrapper_class ); ?>"<?php echo $id_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="common_tab">
			<?php foreach ( $tabs as $label => $url ) : ?>
				<p><a href="<?php echo esc_url( $url ); ?>"<?php echo ( $label === $active_region ) ? ' class="ac"' : ''; ?>><?php echo esc_html( $label ); ?></a></p>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

/**
 * Normalize imported/stored ARKRAY Group markup: ensure region tabs use local URLs.
 *
 * @param string $html          Raw or imported Group page HTML.
 * @param string $active_region Active region label (World, Japan, Asia, Europe, Americas).
 * @return string
 */
function arkray_prepare_group_body_markup( $html, $active_region = '' ) {
	$html = trim( (string) $html );
	if ( '' === $html ) {
		return '';
	}

	$tab_urls = arkray_get_group_region_tab_urls();

	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	$loaded = $dom->loadHTML(
		'<?xml encoding="UTF-8"><div id="arkray-group-root">' . $html . '</div>',
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
	);
	if ( ! $loaded ) {
		libxml_clear_errors();
		return $html;
	}

	$xpath   = new DOMXPath( $dom );
	$tabarea = $xpath->query( "//*[contains(concat(' ',normalize-space(@class),' '),' common_tabarea ')]" )->item( 0 );

	if ( $tabarea instanceof DOMElement ) {
		$links = $xpath->query( './/a', $tabarea );
		if ( $links ) {
			foreach ( $links as $link ) {
				if ( ! ( $link instanceof DOMElement ) ) {
					continue;
				}
				$label = trim( $link->textContent );
				if ( ! isset( $tab_urls[ $label ] ) ) {
					continue;
				}
				$link->setAttribute( 'href', untrailingslashit( $tab_urls[ $label ] ) );
				if ( $label === $active_region ) {
					$link->setAttribute( 'class', 'ac' );
				} else {
					$link->removeAttribute( 'class' );
				}
			}
		}
	} else {
		$tabs_html = arkray_capture_group_region_tabs_html( $active_region );
		$temp_dom  = new DOMDocument();
		$temp_loaded = $temp_dom->loadHTML(
			'<?xml encoding="UTF-8"><div id="arkray-group-tabs-wrap">' . $tabs_html . '</div>',
			LIBXML_NOERROR | LIBXML_NOWARNING
		);
		if ( $temp_loaded ) {
			$wrap = $temp_dom->getElementById( 'arkray-group-tabs-wrap' );
			if ( $wrap ) {
				$inject_after = $xpath->query( "//*[contains(concat(' ',normalize-space(@class),' '),' gmap_lead ')]" )->item( 0 );
				if ( ! ( $inject_after instanceof DOMNode ) ) {
					$inject_after = $xpath->query( "//h1[contains(concat(' ',normalize-space(@class),' '),' h1_index ')]" )->item( 0 );
				}
				$parent = ( $inject_after instanceof DOMNode && $inject_after->parentNode )
					? $inject_after->parentNode
					: $dom->getElementById( 'arkray-group-root' );
				$before = ( $inject_after instanceof DOMNode && $inject_after->parentNode )
					? $inject_after->nextSibling
					: null;
				if ( $parent ) {
					foreach ( $wrap->childNodes as $child ) {
						$imported = $dom->importNode( $child, true );
						if ( $before ) {
							$parent->insertBefore( $imported, $before );
						} else {
							$parent->appendChild( $imported );
						}
					}
				}
			}
		}
	}

	$out  = '';
	$root = $dom->getElementById( 'arkray-group-root' );
	if ( $root ) {
		foreach ( $root->childNodes as $child ) {
			$out .= $dom->saveHTML( $child );
		}
	}
	libxml_clear_errors();

	return trim( $out );
}

/**
 * Return region tab markup as an HTML string (for DOM injection).
 *
 * @param string $active_region Active region label.
 * @return string
 */
function arkray_capture_group_region_tabs_html( $active_region = '' ) {
	ob_start();
	arkray_render_group_region_tabs( $active_region );
	return (string) ob_get_clean();
}

/**
 * Enqueue Google Maps + gmap.js for admin-editable Group page content.
 *
 * @param string $map_area gmap.js region (World, Japan, Asia, Europe, US).
 * @return void
 */
function arkray_enqueue_group_map_assets( $map_area = 'World' ) {
	$theme_uri = get_stylesheet_directory_uri();
	$theme_ver = wp_get_theme()->get( 'Version' );

	wp_enqueue_script(
		'arkray-gmap-jquery',
		$theme_uri . '/js/jquery-1.11.1.min.js',
		array(),
		'1.11.1',
		true
	);

	wp_enqueue_script(
		'arkray-google-maps',
		'https://maps.google.com/maps/api/js?sensor=false&language=en&key=AIzaSyB_y8CFki59MD3dXOcPiqPM5RZGjtQfKaM',
		array(),
		null,
		true
	);

	wp_add_inline_script(
		'arkray-google-maps',
		'window.ARKRAY_JQ = window.jQuery;'
			. 'window.ARKRAY_MAP_ICON_BASE = ' . wp_json_encode( $theme_uri . '/img/' ) . ';'
			. 'var area = ' . wp_json_encode( (string) $map_area ) . ';',
		'after'
	);

	wp_enqueue_script(
		'arkray-gmap',
		$theme_uri . '/js/gmap.js',
		array( 'arkray-gmap-jquery', 'arkray-google-maps' ),
		$theme_ver,
		true
	);

	wp_enqueue_script(
		'arkray-group-accordion',
		$theme_uri . '/js/group-accordion.js',
		array( 'arkray-gmap-jquery', 'arkray-google-maps' ),
		$theme_ver,
		true
	);
}

/**
 * Load map assets when a Group About page renders editor content.
 *
 * @return void
 */
function arkray_maybe_enqueue_group_map_assets() {
	if ( ! function_exists( 'arkray_get_about_route_key_from_request' ) ) {
		return;
	}

	$about_slug = arkray_get_about_route_key_from_request();
	if ( '' === $about_slug ) {
		return;
	}

	$map_area = arkray_get_group_map_area_for_slug( $about_slug );
	if ( '' === $map_area ) {
		return;
	}

	$content_id = function_exists( 'arkray_get_about_subpage_id' )
		? arkray_get_about_subpage_id( $about_slug )
		: 0;
	if ( ! $content_id ) {
		return;
	}

	$page_post = get_post( $content_id );
	if ( ! ( $page_post instanceof WP_Post ) || '' === trim( $page_post->post_content ) ) {
		return;
	}

	arkray_enqueue_group_map_assets( $map_area );
}
add_action( 'wp_enqueue_scripts', 'arkray_maybe_enqueue_group_map_assets', 25 );

/**
 * Normalize About page keys and legacy aliases to canonical keys.
 *
 * @param string $page_key Logical About page key or legacy slug.
 * @return string
 */
function arkray_normalize_about_page_key( $page_key ) {
	$page_key = sanitize_title( (string) $page_key );

	$aliases = array(
		'about'                         => 'about-us',
		'message-from-our-management' => 'message-from-arkray',
		'contact'                       => 'about-contact',
		'profile'                       => 'about-contact',
	);

	return isset( $aliases[ $page_key ] ) ? $aliases[ $page_key ] : $page_key;
}

/**
 * About section pages and their canonical public paths.
 *
 * Paths match the legacy source shape (e.g. /english/about/philosophy/).
 *
 * @return array<string, array{public_path:string, legacy_slug:string, wp_slugs:string[]}>
 */
function arkray_get_about_public_page_map() {
	return array(
		'about-us'                          => array(
			'public_path'  => '/about/',
			'legacy_slug'  => '',
			'wp_slugs'     => array( 'about-us', 'about' ),
		),
		'arkray-philosophy'                 => array(
			'public_path'  => '/about/philosophy/',
			'legacy_slug'  => 'philosophy',
			'wp_slugs'     => array( 'arkray-philosophy', 'philosophy' ),
		),
		'arkray-action-guidelines'          => array(
			'public_path'  => '/about/action_guidelines/',
			'legacy_slug'  => 'action_guidelines',
			'wp_slugs'     => array( 'arkray-action-guidelines', 'action_guidelines', 'action-guidelines' ),
		),
		'message-from-arkray'               => array(
			'public_path'  => '/about/message/',
			'legacy_slug'  => 'message',
			'wp_slugs'     => array( 'message-from-arkray', 'message' ),
		),
		'brand-concept'                     => array(
			'public_path'  => '/about/concept/',
			'legacy_slug'  => 'concept',
			'wp_slugs'     => array( 'brand-concept', 'concept' ),
		),
		'about-contact'                     => array(
			'public_path'  => '/about/profile/',
			'legacy_slug'  => 'profile',
			'wp_slugs'     => array( 'profile', 'about-contact', 'contact' ),
		),
		'corporate-outline'                 => array(
			'public_path'  => '/about/business/',
			'legacy_slug'  => 'business',
			'wp_slugs'     => array( 'corporate-outline', 'business' ),
		),
		'history'                           => array(
			'public_path'  => '/about/history1960/',
			'legacy_slug'  => 'history',
			'wp_slugs'     => array( 'history' ),
		),
		'arkray-group'                      => array(
			'public_path'  => '/about/group/',
			'legacy_slug'  => 'group',
			'wp_slugs'     => array( 'arkray-group', 'group' ),
		),
		'arkray-group-2'                    => array(
			'public_path'  => '/about/group02/',
			'legacy_slug'  => 'group02',
			'wp_slugs'     => array( 'arkray-group-2', 'group02' ),
		),
		'arkray-group-3'                    => array(
			'public_path'  => '/about/group03/',
			'legacy_slug'  => 'group03',
			'wp_slugs'     => array( 'arkray-group-3', 'group03' ),
		),
		'arkray-group-4'                    => array(
			'public_path'  => '/about/group04/',
			'legacy_slug'  => 'group04',
			'wp_slugs'     => array( 'arkray-group-4', 'group04' ),
		),
		'arkray-group-5'                    => array(
			'public_path'  => '/about/group05/',
			'legacy_slug'  => 'group05',
			'wp_slugs'     => array( 'arkray-group-5', 'group05' ),
		),
		'access'                            => array(
			'public_path'  => '/about/access/',
			'legacy_slug'  => 'access',
			'wp_slugs'     => array( 'access' ),
		),
		'sustainable-procurement-standards' => array(
			'public_path'  => '/about/action_guidelines/',
			'legacy_slug'  => 'sustainable-procurement-standards',
			'wp_slugs'     => array( 'sustainable-procurement-standards' ),
		),
	);
}

/**
 * Canonical public path for an About page key.
 *
 * @param string $page_key Logical About page key.
 * @return string Path relative to the language home, with leading slash.
 */
function arkray_get_about_public_path( $page_key = 'about-us' ) {
	$page_key = arkray_normalize_about_page_key( $page_key );

	if ( 'history' === $page_key ) {
		return '/about/history' . arkray_get_default_history_decade() . '/';
	}

	$map = arkray_get_about_public_page_map();

	if ( isset( $map[ $page_key ] ) ) {
		return $map[ $page_key ]['public_path'];
	}

	return '/about/' . trim( $page_key, '/' ) . '/';
}

/**
 * URL path segments after the WP install path and optional language prefix.
 *
 * @return string[]
 */
function arkray_get_about_request_segments() {
	$rel_path = arkray_get_request_relative_path();
	if ( '' === $rel_path ) {
		return array();
	}

	$segments = array_values( array_filter( explode( '/', $rel_path ), 'strlen' ) );
	if ( function_exists( 'pll_languages_list' ) ) {
		$langs = pll_languages_list( array( 'fields' => 'slug' ) );
		if ( ! empty( $segments ) && in_array( $segments[0], $langs, true ) ) {
			array_shift( $segments );
		}
	}

	return $segments;
}

/**
 * Whether the current request targets About > Company History.
 *
 * Distinguishes /about/history{decade}/ from Product History of Pioneers routes.
 *
 * @return bool
 */
function arkray_is_about_history_request() {
	$segments = arkray_get_about_request_segments();
	$last     = arkray_get_request_last_segment();

	if ( '' !== arkray_get_history_decade_from_segment( $last ) ) {
		return true;
	}

	if ( count( $segments ) < 2 ) {
		return false;
	}

	$slug_before_last = $segments[ count( $segments ) - 2 ];

	return 'about' === $slug_before_last && 'history' === $last;
}

/**
 * Resolve an About page key from a URL path segment.
 *
 * @param string $segment Sanitized URL segment.
 * @return string Empty string when the segment is not an About route.
 */
function arkray_get_about_page_key_from_segment( $segment ) {
	$segment = sanitize_title( (string) $segment );
	if ( '' === $segment ) {
		return '';
	}

	if ( '' !== arkray_get_history_decade_from_segment( $segment ) ) {
		return 'history';
	}

	$segment = arkray_normalize_about_page_key( $segment );

	if ( 'history' === $segment && ! arkray_is_about_history_request() ) {
		return '';
	}

	$map = arkray_get_about_public_page_map();

	if ( isset( $map[ $segment ] ) ) {
		return $segment;
	}

	foreach ( $map as $page_key => $config ) {
		if ( $config['legacy_slug'] === $segment || in_array( $segment, $config['wp_slugs'], true ) ) {
			return $page_key;
		}
	}

	return '';
}

/**
 * Resolve About section URLs using canonical /about/{slug}/ paths.
 *
 * @param string $page_key Logical About page key.
 * @return string
 */
function arkray_get_about_page_url( $page_key = 'about-us' ) {
	return arkray_home_url( arkray_get_about_public_path( $page_key ) );
}

/**
 * Resolve the WP page ID for an About sub-page (e.g. history, arkray-philosophy).
 *
 * @param string $page_key Logical About page key.
 * @return int Page ID, or 0 when not found.
 */
function arkray_get_about_subpage_id( $page_key = 'about-us' ) {
	$page_key = arkray_normalize_about_page_key( $page_key );

	$map = arkray_get_about_public_page_map();
	if ( isset( $map[ $page_key ] ) ) {
		$config      = $map[ $page_key ];
		$paths       = array();
		$legacy_slug = $config['legacy_slug'];
		if ( '' !== $legacy_slug ) {
			$paths[] = 'about/' . $legacy_slug;
			$paths[] = 'about-us/' . $page_key;
			$paths[] = 'english/about/' . $legacy_slug;
		} else {
			$paths[] = 'about';
			$paths[] = 'english/about';
		}
		foreach ( $config['wp_slugs'] as $slug ) {
			$paths[] = $slug;
		}
	} else {
		$paths = array( $page_key, 'about-us/' . $page_key, 'about/' . $page_key );
	}

	foreach ( $paths as $path ) {
		$page = get_page_by_path( $path );
		if ( $page instanceof WP_Post ) {
			return arkray_pll_post_id( $page->ID );
		}
	}

	return 0;
}

/**
 * Build a decade-specific Company History source URL from a configured base.
 *
 * Accepts URLs such as .../history1960.html or .../about/history1960.html and
 * swaps the year for the requested decade tab.
 *
 * @param string $base_url Configured external content URL.
 * @param string $decade   Decade label (e.g. "1960").
 * @return string
 */
function arkray_build_history_decade_source_url( $base_url, $decade ) {
	$base_url = trim( (string) $base_url );
	$decade   = sanitize_text_field( (string) $decade );

	if ( '' === $base_url || '' === $decade ) {
		return '';
	}

	// Strip query/fragment so decade substitution targets the path only.
	$parts    = wp_parse_url( $base_url );
	$path     = isset( $parts['path'] ) ? untrailingslashit( (string) $parts['path'] ) : '';
	$scheme   = ! empty( $parts['scheme'] ) ? $parts['scheme'] . '://' : '';
	$host     = ! empty( $parts['host'] ) ? $parts['host'] : '';
	$port     = ! empty( $parts['port'] ) ? ':' . $parts['port'] : '';
	$origin   = $scheme . $host . $port;
	$new_path = $path;

	if ( preg_match( '#/about/history/index\.html$#i', $path ) ) {
		$new_path = preg_replace( '#/about/history/index\.html$#i', '/about/history' . $decade . '.html', $path );
	} elseif ( preg_match( '#/history/index\.html$#i', $path ) ) {
		$new_path = preg_replace( '#/history/index\.html$#i', '/about/history' . $decade . '.html', $path );
	} elseif ( preg_match( '#/history\d{4}\.html$#i', $path ) ) {
		$new_path = preg_replace( '#/history\d{4}\.html$#i', '/history' . $decade . '.html', $path );
	} elseif ( preg_match( '#/history\.html$#i', $path ) ) {
		$new_path = preg_replace( '#/history\.html$#i', '/history' . $decade . '.html', $path );
	} elseif ( preg_match( '#/about/history/?$#i', $path ) ) {
		$new_path = preg_replace( '#/about/history/?$#i', '/about/history' . $decade . '.html', $path );
	} elseif ( preg_match( '#/history\d{4}$#i', $path ) ) {
		$new_path = preg_replace( '#/history\d{4}$#i', '/history' . $decade . '.html', $path );
	} else {
		$dir      = preg_replace( '#/[^/]*$#', '/', $path );
		$new_path = $dir . 'history' . $decade . '.html';
	}

	if ( '' === $new_path ) {
		return '';
	}

	return $origin . $new_path;
}

/**
 * Infer the language segment used by legacy ARKRAY history source pages.
 *
 * @param string $path Optional URL path hint.
 * @return string "english" or "vietnamese".
 */
function arkray_get_history_source_language( $path = '' ) {
	$path = (string) $path;

	if ( preg_match( '#/(vietnamese)(?:/|$)#i', $path ) ) {
		return 'vietnamese';
	}

	if ( function_exists( 'pll_current_language' ) ) {
		$current = pll_current_language( 'slug' );
		if ( in_array( $current, array( 'vi', 'vietnamese' ), true ) ) {
			return 'vietnamese';
		}
	}

	return 'english';
}

/**
 * Default remote host for About > History decade imports.
 *
 * @param int $page_id History page ID.
 * @return string Origin such as https://www.arkray.co.jp.
 */
function arkray_get_history_source_origin( $page_id = 0 ) {
	$origin = 'https://www.arkray.co.jp';

	if ( $page_id && function_exists( 'get_field' ) ) {
		$external_base = trim( (string) get_field( 'external_content_base_url', $page_id ) );
		if ( '' !== $external_base && preg_match( '#^(https?://[^/]+)#i', $external_base, $matches ) ) {
			$origin = $matches[1];
		}
	}

	/**
	 * Filter the default origin used for About > History decade imports.
	 *
	 * @param string $origin  Default origin URL.
	 * @param int    $page_id History page ID.
	 */
	return apply_filters( 'arkray_history_source_origin', $origin, $page_id );
}

/**
 * Build the canonical remote URL for a History decade page.
 *
 * @param string $decade  Decade label.
 * @param int    $page_id History page ID.
 * @return string
 */
function arkray_get_default_history_decade_source_url( $decade, $page_id = 0 ) {
	$decade = sanitize_text_field( (string) $decade );
	if ( '' === $decade ) {
		return '';
	}

	$lang   = arkray_get_history_source_language();
	$origin = untrailingslashit( arkray_get_history_source_origin( $page_id ) );

	return $origin . '/' . $lang . '/about/history' . $decade . '.html';
}

/**
 * Resolve a decade-specific remote source URL from a configured base URL.
 *
 * Falls back to the canonical arkray.co.jp history path when the configured
 * value is missing, local, or otherwise not on an allowed import host.
 *
 * @param string $decade       Decade label.
 * @param int    $page_id      History page ID.
 * @param string $external_url Optional configured external content URL.
 * @return string
 */
function arkray_resolve_history_decade_source_url( $decade, $page_id = 0, $external_url = '' ) {
	$decade = sanitize_text_field( (string) $decade );
	if ( '' === $decade ) {
		return '';
	}

	if ( '' === trim( (string) $external_url ) && $page_id && function_exists( 'get_field' ) ) {
		$external_url = trim( (string) get_field( 'external_content_url', $page_id ) );
	}

	$decade_url = '' !== $external_url
		? arkray_build_history_decade_source_url( $external_url, $decade )
		: '';

	$parts  = is_string( $decade_url ) ? wp_parse_url( $decade_url ) : array();
	$host   = ! empty( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
	$allowed = function_exists( 'arkray_ext_allowed_hosts' ) ? arkray_ext_allowed_hosts() : array();

	if ( '' === $decade_url || '' === $host || ! in_array( $host, $allowed, true ) ) {
		$hint_path  = ! empty( $parts['path'] ) ? (string) $parts['path'] : (string) $external_url;
		$lang       = arkray_get_history_source_language( $hint_path );
		$origin     = untrailingslashit( arkray_get_history_source_origin( $page_id ) );
		$decade_url = $origin . '/' . $lang . '/about/history' . $decade . '.html';
	}

	/**
	 * Filter the resolved source URL for a History decade tab.
	 *
	 * @param string $decade_url   Decade-specific source URL.
	 * @param string $decade       Requested decade label.
	 * @param string $external_url Configured external content URL.
	 * @param int    $page_id      History page ID.
	 */
	return (string) apply_filters( 'arkray_history_decade_source_url', $decade_url, $decade, $external_url, $page_id );
}

/**
 * Default decade tab for the About > History landing page.
 *
 * @return string
 */
function arkray_get_default_history_decade() {
	return '1960';
}

/**
 * Valid decade labels for the About > History page tabs.
 *
 * @return string[]
 */
function arkray_get_history_decades() {
	return array( '1960', '1970', '1980', '1990', '2000', '2010', '2020' );
}

/**
 * Build a path-preserved About > History decade URL on the current site.
 *
 * Matches the legacy source shape (e.g. /english/about/history1970) so imported
 * navigation links and template tabs stay consistent with other external pages.
 *
 * @param string $decade Decade label (e.g. "1960").
 * @return string
 */
function arkray_get_history_decade_url( $decade ) {
	$decade = sanitize_text_field( (string) $decade );
	if ( '' === $decade ) {
		$decade = arkray_get_default_history_decade();
	}

	return arkray_home_url( '/about/history' . $decade );
}

/**
 * Extract a History decade label from a request path segment, if present.
 *
 * @param string $segment Sanitized URL segment.
 * @return string Decade label, or '' when the segment is not a decade page.
 */
function arkray_get_history_decade_from_segment( $segment ) {
	$segment = sanitize_title( (string) $segment );
	if ( ! preg_match( '#^history(\d{4})$#i', $segment, $matches ) ) {
		return '';
	}

	$decade = $matches[1];
	return in_array( $decade, arkray_get_history_decades(), true ) ? $decade : '';
}

/**
 * Return the active Company History decade from the request.
 *
 * @return string One of 1960–2020; defaults to 1960.
 */
function arkray_get_active_history_decade() {
	$valid_decades = arkray_get_history_decades();
	$requested     = get_query_var( 'decade' );

	if ( ( '' === $requested || ! is_string( $requested ) ) && isset( $_GET['decade'] ) ) {
		$requested = wp_unslash( $_GET['decade'] );
	}

	if ( '' === $requested || ! is_string( $requested ) ) {
		$requested = arkray_get_history_decade_from_segment( arkray_get_request_last_segment() );
	}

	$requested = sanitize_text_field( (string) $requested );

	return in_array( $requested, $valid_decades, true ) ? $requested : '1960';
}

/**
 * Register the decade query var used by About > History tab links.
 *
 * @param string[] $vars Public query vars.
 * @return string[]
 */
function arkray_register_history_query_var( $vars ) {
	$vars[] = 'decade';
	return $vars;
}
add_filter( 'query_vars', 'arkray_register_history_query_var' );

/**
 * Keep path-preserved and ?decade= History URLs when WordPress canonicalizes.
 *
 * @param string|false $redirect_url  Canonical redirect target.
 * @param string       $requested_url Requested URL.
 * @return string|false
 */
function arkray_preserve_history_decade_canonical( $redirect_url, $requested_url ) {
	if ( ! is_string( $requested_url ) || false === $redirect_url ) {
		return $redirect_url;
	}

	if ( function_exists( 'arkray_is_about_history_request' ) && arkray_is_about_history_request() ) {
		return false;
	}

	$path = (string) wp_parse_url( $requested_url, PHP_URL_PATH );
	if ( ! preg_match( '#/history#i', $path ) ) {
		return $redirect_url;
	}

	$decade = arkray_get_history_decade_from_segment( arkray_get_request_last_segment() );
	if ( '' === $decade ) {
		if ( isset( $_GET['decade'] ) ) {
			$decade = sanitize_text_field( wp_unslash( $_GET['decade'] ) );
		} elseif ( function_exists( 'get_query_var' ) ) {
			$decade = (string) get_query_var( 'decade' );
		}
		$decade = sanitize_text_field( (string) $decade );
	}

	if ( '' !== $decade && in_array( $decade, arkray_get_history_decades(), true ) ) {
		return false;
	}

	return $redirect_url;
}
add_filter( 'redirect_canonical', 'arkray_preserve_history_decade_canonical', 10, 2 );

/**
 * Extract the history timeline columns from a full history page HTML fragment.
 *
 * @param string $html Raw HTML (reference scrape or imported #content_area).
 * @return string Sanitized column markup, or '' when not found.
 */
function arkray_extract_history_column_area( $html ) {
	$html = (string) $html;
	if ( '' === $html ) {
		return '';
	}

	libxml_use_internal_errors( true );
	$hdoc = new DOMDocument();
	$loaded = $hdoc->loadHTML(
		'<?xml encoding="UTF-8">' . $html,
		LIBXML_NOERROR | LIBXML_NOWARNING
	);
	if ( ! $loaded ) {
		libxml_clear_errors();
		return '';
	}

	$hxpath   = new DOMXPath( $hdoc );
	$col_node = $hxpath->query( "//div[contains(concat(' ',normalize-space(@class),' '),' history_column_area ')]" )->item( 0 );
	if ( ! $col_node ) {
		libxml_clear_errors();
		return '';
	}

	$col_html = $hdoc->saveHTML( $col_node );
	libxml_clear_errors();

	return arkray_rewrite_history_column_image_urls( $col_html );
}

/**
 * Normalize imported/stored History markup for display under the template tabs.
 *
 * The About > History template renders its own <h1> and decade tabs. Imported
 * #content_area / #editor_area bodies therefore must be reduced to the timeline
 * columns only so headings and tabs are not duplicated.
 *
 * @param string $html Raw or imported History HTML.
 * @return string Column markup only, or '' when nothing remains.
 */
function arkray_prepare_history_body_markup( $html ) {
	$html = trim( (string) $html );
	if ( '' === $html ) {
		return '';
	}

	$columns = arkray_extract_history_column_area( $html );
	if ( '' !== $columns ) {
		return $columns;
	}

	// The external-content parser returns only the inner column markup for
	// .history_column_area; wrap it without running it through DOMDocument.
	if ( false !== stripos( $html, 'class="column"' ) ) {
		if ( false === stripos( $html, 'history_column_area' ) ) {
			$html = '<div class="history_column_area cf">' . $html . '</div>';
		}
		return arkray_rewrite_history_column_image_urls( $html );
	}

	libxml_use_internal_errors( true );
	$hdoc = new DOMDocument();
	$loaded = $hdoc->loadHTML(
		'<?xml encoding="UTF-8">' . $html,
		LIBXML_NOERROR | LIBXML_NOWARNING
	);
	if ( ! $loaded ) {
		libxml_clear_errors();
		return $html;
	}

	$hxpath = new DOMXPath( $hdoc );
	$remove = $hxpath->query(
		"//h1[contains(concat(' ',normalize-space(@class),' '),' h1_index ')]"
		. " | //div[contains(concat(' ',normalize-space(@class),' '),' common_tabarea ')]"
	);
	if ( $remove ) {
		$nodes = array();
		foreach ( $remove as $node ) {
			$nodes[] = $node;
		}
		foreach ( $nodes as $node ) {
			if ( $node->parentNode ) {
				$node->parentNode->removeChild( $node );
			}
		}
	}

	$out  = '';
	$body = $hdoc->getElementsByTagName( 'body' )->item( 0 );
	if ( $body && $body->hasChildNodes() ) {
		foreach ( $body->childNodes as $child ) {
			$out .= $hdoc->saveHTML( $child );
		}
	}
	libxml_clear_errors();

	$out = trim( $out );
	if ( '' === $out ) {
		return '';
	}

	if ( false === stripos( $out, 'history_column_area' ) && false !== stripos( $out, 'class="column"' ) ) {
		$out = '<div class="history_column_area cf">' . $out . '</div>';
	}

	return arkray_rewrite_history_column_image_urls( $out );
}

/**
 * Render a History decade from the About timeline ACF repeater.
 *
 * @param string $decade  Decade label.
 * @param int    $page_id Optional page ID that stores about_timeline rows.
 * @return string
 */
function arkray_render_history_decade_from_acf( $decade, $page_id = 0 ) {
	if ( ! function_exists( 'get_field' ) ) {
		return '';
	}

	$decade = sanitize_text_field( (string) $decade );
	if ( '' === $decade ) {
		return '';
	}

	$page_ids = array();
	if ( $page_id ) {
		$page_ids[] = (int) $page_id;
	}
	$history_id = arkray_get_about_subpage_id( 'history' );
	if ( $history_id && ! in_array( $history_id, $page_ids, true ) ) {
		$page_ids[] = $history_id;
	}
	$about_id = arkray_get_about_subpage_id( 'about-us' );
	if ( $about_id && ! in_array( $about_id, $page_ids, true ) ) {
		$page_ids[] = $about_id;
	}

	$row = null;
	foreach ( $page_ids as $candidate_id ) {
		$rows = get_field( 'about_timeline', $candidate_id );
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			continue;
		}
		foreach ( $rows as $timeline_row ) {
			$label = isset( $timeline_row['decade'] ) ? trim( (string) $timeline_row['decade'] ) : '';
			if ( $label === $decade ) {
				$row = $timeline_row;
				break 2;
			}
		}
	}

	if ( ! is_array( $row ) ) {
		return '';
	}

	$render_entries = static function ( $entries ) {
		if ( ! is_array( $entries ) || empty( $entries ) ) {
			return '';
		}

		$html = '<dl>';
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$date = isset( $entry['entry_date'] ) ? trim( (string) $entry['entry_date'] ) : '';
			$text = isset( $entry['entry_text'] ) ? (string) $entry['entry_text'] : '';
			if ( '' === $date && '' === trim( wp_strip_all_tags( $text ) ) ) {
				continue;
			}
			$html .= '<dt>' . esc_html( $date ) . '</dt>';
			$html .= '<dd>' . wp_kses_post( $text ) . '</dd>';
		}
		$html .= '</dl>';

		return $html;
	};

	$company_html = $render_entries( isset( $row['company_entries'] ) ? $row['company_entries'] : array() );
	$product_html = $render_entries( isset( $row['product_entries'] ) ? $row['product_entries'] : array() );
	$image_url    = isset( $row['decade_image'] ) ? trim( (string) $row['decade_image'] ) : '';

	if ( '' === $company_html && '' === $product_html ) {
		return '';
	}

	$html  = '<div class="history_column_area cf">';
	$html .= '<div class="column">';
	$html .= '<h2>Company History/' . esc_html( $decade ) . '</h2>';
	$html .= $company_html;
	if ( '' !== $image_url ) {
		$html .= '<img src="' . esc_url( $image_url ) . '" alt="" />';
	}
	$html .= '</div>';
	$html .= '<div class="column">';
	$html .= '<h2>Product History/' . esc_html( $decade ) . '</h2>';
	$html .= $product_html;
	$html .= '</div>';
	$html .= '</div>';

	return $html;
}

/**
 * Local arkray-assets URL for a basename when the file exists.
 *
 * @param string $basename File basename (e.g. "pu-4010.jpg").
 * @return string Public URL or '' when missing.
 */
function arkray_get_arkray_asset_url( $basename ) {
	$basename = wp_basename( (string) $basename );
	if ( '' === $basename ) {
		return '';
	}

	$local_abs = ABSPATH . 'wp-content/uploads/arkray-assets/' . $basename;
	if ( ! file_exists( $local_abs ) ) {
		return '';
	}

	return home_url( '/wp-content/uploads/arkray-assets/' . rawurlencode( $basename ) );
}

/**
 * Resolve a product/upload image URL to a local arkray-assets copy when available.
 *
 * @param string $url Remote or local image URL.
 * @return string
 */
function arkray_resolve_product_image_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}

	if ( preg_match( '#(?:upload/img/)([^"?]+)#i', $url, $m ) ) {
		$local = arkray_get_arkray_asset_url( rawurldecode( $m[1] ) );
		if ( $local ) {
			return $local;
		}
	}

	return $url;
}

/**
 * Main product detail image URL (featured thumbnail or slug-based arkray-assets fallback).
 *
 * @param int $post_id Product post ID.
 * @return string
 */
function arkray_get_product_detail_main_image_url( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return '';
	}

	$thumb = get_the_post_thumbnail_url( $post_id, 'large' );
	if ( $thumb ) {
		$resolved = arkray_resolve_product_image_url( $thumb );
		if ( $resolved ) {
			return $resolved;
		}
	}

	$slug = (string) get_post_field( 'post_name', $post_id );
	if ( '' !== $slug ) {
		return arkray_get_arkray_asset_url( $slug . '.jpg' );
	}

	return '';
}

/**
 * Rewrite remote arkray.global history images to local uploads when available.
 *
 * @param string $col_html History column HTML.
 * @return string
 */
function arkray_rewrite_history_column_image_urls( $col_html ) {
	$col_html = (string) preg_replace_callback(
		'/src="https?:\/\/www\.arkray\.global\/english\/about\/img\/([^"]+)"/',
		static function ( $m ) {
			$fname = $m[1];
			$local = ABSPATH . 'wp-content/uploads/arkray-assets/' . $fname;
			if ( file_exists( $local ) ) {
				return 'src="' . esc_url( home_url( '/wp-content/uploads/arkray-assets/' . $fname ) ) . '"';
			}
			return $m[0];
		},
		(string) $col_html
	);

	return (string) preg_replace_callback(
		'/src="https?:\/\/www\.arkray\.co\.jp\/english\/about\/img\/([^"]+)"/',
		static function ( $m ) {
			$fname = $m[1];
			$local = ABSPATH . 'wp-content/uploads/arkray-assets/' . $fname;
			if ( file_exists( $local ) ) {
				return 'src="' . esc_url( home_url( '/wp-content/uploads/arkray-assets/' . $fname ) ) . '"';
			}
			return $m[0];
		},
		$col_html
	);
}

/**
 * Load Company History decade content for the About > History page.
 *
 * Priority: external content plugin (per-decade URL) → local reference scrape
 * → empty string so the template can fall back to stored postmeta.
 *
 * @param string $decade   Active decade tab (e.g. "1960").
 * @param int    $page_id  Optional History page ID; resolved automatically when 0.
 * @return string
 */
function arkray_get_history_decade_content( $decade, $page_id = 0 ) {
	$valid_decades = arkray_get_history_decades();
	if ( ! in_array( $decade, $valid_decades, true ) ) {
		$decade = '1960';
	}

	if ( ! $page_id ) {
		$page_id = arkray_get_about_subpage_id( 'history' );
	}
	if ( ! $page_id ) {
		$page_id = (int) get_queried_object_id();
	}

	if ( $page_id && function_exists( 'arkray_get_external_content' ) ) {
		$external_url = function_exists( 'get_field' )
			? trim( (string) get_field( 'external_content_url', $page_id ) )
			: '';
		$decade_url   = arkray_resolve_history_decade_source_url( $decade, $page_id, $external_url );

		if ( '' !== $decade_url ) {
			$external_base = function_exists( 'get_field' )
				? (string) get_field( 'external_content_base_url', $page_id )
				: '';
			$external_ttl  = function_exists( 'get_field' )
				? (int) get_field( 'external_content_cache_hours', $page_id )
				: 0;
			$fetch_args    = array(
				'base_url'  => '' !== $external_base ? $external_base : 'https://www.arkray.global',
				'cache_ttl' => $external_ttl > 0 ? $external_ttl * HOUR_IN_SECONDS : DAY_IN_SECONDS,
			);

			$selectors = array( '.history_column_area', '#editor_area', '#content_area' );
			foreach ( $selectors as $selector ) {
				$chunk = arkray_get_external_content(
					$decade_url,
					array_merge(
						$fetch_args,
						array(
							'selector'    => $selector,
							'cache_extra' => $decade,
						)
					)
				);
				if ( '' === $chunk ) {
					continue;
				}

				$content = arkray_prepare_history_body_markup( $chunk );
				if ( '' !== $content ) {
					return $content;
				}
			}
		}
	}

	$acf_html = arkray_render_history_decade_from_acf( $decade, $page_id );
	if ( '' !== $acf_html ) {
		return $acf_html;
	}

	$ref_file = ABSPATH . '_reference/arkray-live/scraped/pages/about__history' . $decade . '.html';
	if ( is_readable( $ref_file ) ) {
		$ref_html = file_get_contents( $ref_file );
		if ( false !== $ref_html && '' !== $ref_html ) {
			$content = arkray_extract_history_column_area( $ref_html );
			if ( '' !== $content ) {
				return $content;
			}
		}
	}

	return '';
}

/**
 * Global contact form URL for footer "Contact Us" links.
 *
 * Footer links open the shared ARKRAY inquiry form in a new tab, matching the
 * reference site. Language follows the current Polylang slug when available.
 *
 * @return string
 */
function arkray_get_contact_page_url() {
	$lang = 'english';
	if ( function_exists( 'pll_current_language' ) ) {
		$current = pll_current_language( 'slug' );
		if ( $current ) {
			$lang = $current;
		}
	}

	return 'https://www.arkray.global/contact/' . $lang . '/index.html';
}

/**
 * Redirect legacy About URLs to canonical /about/{slug}/ paths.
 */
function arkray_redirect_legacy_about_urls() {
	if ( is_admin() || empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}

	$rel_path = arkray_get_request_relative_path();
	if ( '' === $rel_path ) {
		return;
	}

	$segments = array_values( array_filter( explode( '/', $rel_path ), 'strlen' ) );
	if ( function_exists( 'pll_languages_list' ) ) {
		$langs = pll_languages_list( array( 'fields' => 'slug' ) );
		if ( ! empty( $segments ) && in_array( $segments[0], $langs, true ) ) {
			array_shift( $segments );
		}
	}

	if ( empty( $segments ) ) {
		return;
	}

	$last_segment = sanitize_title( end( $segments ) );
	$decade       = arkray_get_history_decade_from_segment( $last_segment );
	if ( '' !== $decade ) {
		$canonical_path = 'about/history' . $decade;
		$current_path   = implode( '/', $segments );

		if ( untrailingslashit( $current_path ) !== untrailingslashit( $canonical_path ) ) {
			wp_safe_redirect( arkray_get_history_decade_url( $decade ), 301 );
			exit;
		}

		return;
	}

	$page_key = arkray_get_about_page_key_from_segment( $last_segment );
	if ( '' === $page_key ) {
		return;
	}

	$canonical_path = trim( arkray_get_about_public_path( $page_key ), '/' );
	$current_path   = implode( '/', $segments );

	if ( untrailingslashit( $current_path ) !== untrailingslashit( $canonical_path ) ) {
		wp_safe_redirect( arkray_get_about_page_url( $page_key ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'arkray_redirect_legacy_about_urls' );

/**
 * Return the request path relative to the WP install directory.
 *
 * @return string
 */
function arkray_get_request_relative_path() {
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return '';
	}

	$request_path = parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
	$request_path = trim( (string) $request_path, '/' );

	$home_path = trim( (string) parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	if ( '' !== $home_path ) {
		if ( 0 === strpos( $request_path, $home_path . '/' ) ) {
			$request_path = substr( $request_path, strlen( $home_path ) + 1 );
		} elseif ( $request_path === $home_path ) {
			$request_path = '';
		}
	}

	return $request_path;
}

/**
 * Return the last sanitized URL segment from REQUEST_URI, after stripping
 * the WP install path. Returns '' when the path is empty.
 *
 * @return string
 */
function arkray_get_request_last_segment() {
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return '';
	}

	$request_path = parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
	$request_path = trim( (string) $request_path, '/' );

	$home_path = trim( (string) parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	if ( '' !== $home_path && 0 === strpos( $request_path, $home_path . '/' ) ) {
		$request_path = substr( $request_path, strlen( $home_path ) + 1 );
	}

	$segments = array_values( array_filter( explode( '/', $request_path ), 'strlen' ) );
	if ( empty( $segments ) ) {
		return '';
	}

	return sanitize_title( end( $segments ) );
}

/**
 * Preferred page slugs for About virtual route context.
 *
 * @param string $about_key Canonical About page key.
 * @return string[]
 */
function arkray_get_about_virtual_preferred_slugs( $about_key = 'about-us' ) {
	$about_key = arkray_normalize_about_page_key( $about_key );
	$preferred = array();

	$subpage_id = arkray_get_about_subpage_id( $about_key );
	if ( $subpage_id ) {
		$post = get_post( $subpage_id );
		if ( $post instanceof WP_Post ) {
			$preferred[] = $post->post_name;
		}
	}

	return array_merge( $preferred, array( 'about-us', 'about' ) );
}

/**
 * Whether a page belongs to the About section.
 *
 * @param int|WP_Post|null $post Post ID or object.
 * @return bool
 */
function arkray_is_about_page( $post ) {
	$post = get_post( $post );
	if ( ! ( $post instanceof WP_Post ) || 'page' !== $post->post_type ) {
		return false;
	}

	$template = get_page_template_slug( $post->ID );
	if ( 'template-history-of-pioneers.php' === $template ) {
		return false;
	}

	if ( 'template-about-us.php' === $template ) {
		return true;
	}

	foreach ( arkray_get_about_public_page_map() as $config ) {
		if ( ! in_array( $post->post_name, $config['wp_slugs'], true ) ) {
			continue;
		}

		// Slug "history" is shared with History of Pioneers — require About template.
		if ( 'history' === $post->post_name ) {
			continue;
		}

		return true;
	}

	return false;
}

/**
 * Apply virtual page context for an About route.
 *
 * @param WP_Query $wp_query  Main query.
 * @param string   $about_key Canonical About page key.
 * @return void
 */
function arkray_apply_about_virtual_page_context( $wp_query, $about_key ) {
	if ( function_exists( 'arkray_apply_virtual_page_context' ) ) {
		arkray_apply_virtual_page_context( $wp_query, arkray_get_about_virtual_preferred_slugs( $about_key ) );
		return;
	}

	$wp_query->is_page     = true;
	$wp_query->is_singular = true;
}

/**
 * Return About route key from current request path.
 *
 * @return string Empty string when request is not an About route.
 */
function arkray_get_about_route_key_from_request() {
	$request_path = arkray_get_request_last_segment();
	if ( '' === $request_path ) {
		return '';
	}

	return arkray_get_about_page_key_from_segment( $request_path );
}

/**
 * Prevent default 404 for known About routes when no page record exists.
 *
 * @param bool     $preempt  Whether to short-circuit 404 handling.
 * @param WP_Query $wp_query Main query instance.
 * @return bool
 */
function arkray_prevent_about_virtual_404( $preempt, $wp_query ) {
	if ( is_admin() || ! $wp_query->is_main_query() ) {
		return $preempt;
	}

	$about_key = arkray_get_about_route_key_from_request();
	if ( '' === $about_key ) {
		return $preempt;
	}

	$wp_query->is_404 = false;

	$expected_id = arkray_get_about_subpage_id( $about_key );
	$resolved_id = ! empty( $wp_query->posts ) ? (int) $wp_query->queried_object_id : 0;

	if ( $expected_id && $resolved_id === $expected_id ) {
		return true;
	}

	if ( $resolved_id && arkray_is_about_page( $resolved_id ) ) {
		return true;
	}

	arkray_apply_about_virtual_page_context( $wp_query, $about_key );

	return true;
}
add_filter( 'pre_handle_404', 'arkray_prevent_about_virtual_404', 10, 2 );

/**
 * Route virtual About requests to the About template.
 *
 * @param string $template Resolved template path.
 * @return string
 */
function arkray_route_virtual_about_template( $template ) {
	if ( is_admin() ) {
		return $template;
	}

	if ( '' === arkray_get_about_route_key_from_request() ) {
		return $template;
	}

	$about_template = get_stylesheet_directory() . '/template-about-us.php';
	if ( file_exists( $about_template ) ) {
		return $about_template;
	}

	return $template;
}
add_filter( 'template_include', 'arkray_route_virtual_about_template', 100 );

/**
 * Emit canonical /about/{slug}/ permalinks for About template pages.
 *
 * @param string $permalink Page permalink.
 * @param int    $post_id   Page ID.
 * @return string
 */
function arkray_about_page_link( $permalink, $post_id ) {
	$post = get_post( $post_id );
	if ( ! ( $post instanceof WP_Post ) || 'page' !== $post->post_type ) {
		return $permalink;
	}

	$template = get_page_template_slug( $post_id );
	if ( 'template-history-of-pioneers.php' === $template ) {
		return $permalink;
	}

	$map = arkray_get_about_public_page_map();

	foreach ( $map as $page_key => $config ) {
		if ( in_array( $post->post_name, $config['wp_slugs'], true ) ) {
			return arkray_home_url( $config['public_path'] );
		}
	}

	if ( 'template-about-us.php' === $template ) {
		return arkray_home_url( arkray_get_about_public_path( $post->post_name ) );
	}

	return $permalink;
}
add_filter( 'page_link', 'arkray_about_page_link', 20, 2 );

/**
 * Register a single About sub-page rewrite for a URL slug segment.
 *
 * @param string $slug    Path segment under /about/ (e.g. group02, arkray-group-2).
 * @param string $base    Rewrite query (index.php?page_id=…).
 * @param string $lang_re Polylang language slug regex alternation.
 * @return void
 */
function arkray_add_about_slug_rewrite( $slug, $base, $lang_re ) {
	$slug = sanitize_title( (string) $slug );
	if ( '' === $slug ) {
		return;
	}

	$quoted = preg_quote( $slug, '#' );
	add_rewrite_rule( '^about/' . $quoted . '/?$', $base, 'top' );
	add_rewrite_rule(
		'^(' . $lang_re . ')/about/' . $quoted . '/?$',
		$base . '&lang=$matches[1]',
		'top'
	);
}

/**
 * Register rewrite rules so /about/{slug}/ URLs resolve to matching About pages.
 */
function arkray_add_about_rewrites() {
	$lang_re = arkray_language_slugs_regex();
	$map     = arkray_get_about_public_page_map();

	foreach ( $map as $page_key => $config ) {
		$page_id = arkray_get_about_subpage_id( $page_key );
		if ( ! $page_id || ! arkray_is_about_page( $page_id ) ) {
			continue;
		}

		$base = 'index.php?page_id=' . (int) $page_id;

		if ( 'about-us' === $page_key ) {
			add_rewrite_rule( '^about/?$', $base, 'top' );
			add_rewrite_rule( '^(' . $lang_re . ')/about/?$', $base . '&lang=$matches[1]', 'top' );
			continue;
		}

		$legacy_slug = $config['legacy_slug'];
		if ( '' === $legacy_slug ) {
			continue;
		}

		if ( 'history' === $page_key ) {
			add_rewrite_rule( '^about/history([0-9]{4})/?$', $base, 'top' );
			add_rewrite_rule(
				'^(' . $lang_re . ')/about/history([0-9]{4})/?$',
				$base . '&lang=$matches[1]',
				'top'
			);
			continue;
		}

		$rewrite_slugs = array( $legacy_slug );
		foreach ( $config['wp_slugs'] as $slug ) {
			if ( ! in_array( $slug, $rewrite_slugs, true ) ) {
				$rewrite_slugs[] = $slug;
			}
		}

		foreach ( $rewrite_slugs as $slug ) {
			arkray_add_about_slug_rewrite( $slug, $base, $lang_re );
		}
	}
}
add_action( 'init', 'arkray_add_about_rewrites', 21 );

/**
 * Flush rewrite rules once so /about/{slug}/ and /about/history{decade}/ resolve.
 * Re-run by deleting the `arkray_about_public_rewrites_v6` option.
 */
function arkray_flush_about_rewrites_once() {
	if ( get_option( 'arkray_about_public_rewrites_v6' ) ) {
		return;
	}
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	flush_rewrite_rules();
	update_option( 'arkray_about_public_rewrites_v6', 1 );
}
add_action( 'admin_init', 'arkray_flush_about_rewrites_once' );

/**
 * Canonical public paths for Sustainability pages.
 *
 * Paths match the legacy source shape (e.g. /english/sustainability/commitment/).
 *
 * @return array<string, array{public_path:string, legacy_slug:string, wp_slugs:string[]}>
 */
function arkray_get_sustainability_public_page_map() {
	return array(
		'sustainability'      => array(
			'public_path' => '/sustainability/',
			'legacy_slug' => '',
			'wp_slugs'    => array( 'sustainability', 'sustainable' ),
		),
		'top-commitment'      => array(
			'public_path' => '/sustainability/commitment/',
			'legacy_slug' => 'commitment',
			'wp_slugs'    => array( 'top-commitment' ),
		),
		'sdgs-basic-policy'   => array(
			'public_path' => '/sustainability/policy/',
			'legacy_slug' => 'policy',
			'wp_slugs'    => array( 'sdgs-basic-policy' ),
		),
		'arkrays-materiality' => array(
			'public_path' => '/sustainability/materiality/',
			'legacy_slug' => 'materiality',
			'wp_slugs'    => array( 'arkrays-materiality', 'arkray-s-materiality', 'materiality' ),
		),
		'sdgs-initiatives'    => array(
			'public_path' => '/sustainability/action/',
			'legacy_slug' => 'sdgs-initiatives',
			'wp_slugs'    => array( 'action' ),
		),
	);
}

/**
 * Canonical public path for a Sustainability page key.
 *
 * @param string $page_key Logical Sustainability page key.
 * @return string Path relative to the language home, with leading slash.
 */
function arkray_get_sustainability_public_path( $page_key = 'sustainability' ) {
	$page_key = sanitize_title( $page_key );

	$aliases = array(
		'sustainable'          => 'sustainability',
		'commitment'           => 'top-commitment',
		'policy'               => 'sdgs-basic-policy',
		'materiality'          => 'arkrays-materiality',
		'arkray-s-materiality' => 'arkrays-materiality',
	);

	if ( isset( $aliases[ $page_key ] ) ) {
		$page_key = $aliases[ $page_key ];
	}

	$map = arkray_get_sustainability_public_page_map();

	if ( isset( $map[ $page_key ] ) ) {
		return $map[ $page_key ]['public_path'];
	}

	return '/sustainability/' . trim( $page_key, '/' ) . '/';
}

/**
 * Resolve a Sustainability page key from a URL path segment.
 *
 * @param string $segment Sanitized URL segment.
 * @return string Empty string when the segment is not a Sustainability route.
 */
function arkray_get_sustainability_page_key_from_segment( $segment ) {
	$segment = sanitize_title( (string) $segment );
	if ( '' === $segment ) {
		return '';
	}

	$map = arkray_get_sustainability_public_page_map();

	if ( isset( $map[ $segment ] ) ) {
		return $segment;
	}

	foreach ( $map as $page_key => $config ) {
		if ( $config['legacy_slug'] === $segment || in_array( $segment, $config['wp_slugs'], true ) ) {
			return $page_key;
		}
	}

	return '';
}

/**
 * Resolve the WP page ID for a Sustainability sub-page.
 *
 * @param string $page_key Logical Sustainability page key.
 * @return int Page ID, or 0 when not found.
 */
function arkray_get_sustainability_subpage_id( $page_key = 'sustainability' ) {
	$page_key = sanitize_title( $page_key );

	$aliases = array(
		'sustainable'          => 'sustainability',
		'commitment'           => 'top-commitment',
		'policy'               => 'sdgs-basic-policy',
		'materiality'          => 'arkrays-materiality',
		'arkray-s-materiality' => 'arkrays-materiality',
	);

	if ( isset( $aliases[ $page_key ] ) ) {
		$page_key = $aliases[ $page_key ];
	}

	$map   = arkray_get_sustainability_public_page_map();
	$paths = array();

	if ( isset( $map[ $page_key ] ) ) {
		$config      = $map[ $page_key ];
		$legacy_slug = $config['legacy_slug'];
		if ( '' !== $legacy_slug ) {
			$paths[] = 'sustainability/' . $legacy_slug;
		}
		foreach ( $config['wp_slugs'] as $slug ) {
			$paths[] = $slug;
		}
	} else {
		$paths = array( $page_key, 'sustainability/' . $page_key );
	}

	foreach ( $paths as $path ) {
		$page = get_page_by_path( $path );
		if ( $page instanceof WP_Post ) {
			return arkray_pll_post_id( $page->ID );
		}
	}

	$sustainability_query = new WP_Query(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_wp_page_template',
			'meta_value'     => 'template-sustainability.php',
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		)
	);

	if ( empty( $sustainability_query->posts ) ) {
		return 0;
	}

	if ( 'sustainability' === $page_key ) {
		return arkray_pll_post_id( $sustainability_query->posts[0] );
	}

	$title_map = array(
		'top-commitment'      => 'top commitment',
		'sdgs-basic-policy'   => 'sdgs basic policy',
		'arkrays-materiality' => "arkray's materiality",
		'sdgs-initiatives'    => 'sdgs initiatives',
	);

	if ( ! isset( $title_map[ $page_key ] ) ) {
		return 0;
	}

	$target_title = $title_map[ $page_key ];
	foreach ( $sustainability_query->posts as $page_id ) {
		if ( strtolower( get_the_title( $page_id ) ) === $target_title ) {
			return arkray_pll_post_id( $page_id );
		}
	}

	return 0;
}

/**
 * Resolve Sustainability section URLs using canonical /sustainability/{slug}/ paths.
 *
 * @param string $page_key Logical Sustainability page key.
 * @return string
 */
function arkray_get_sustainability_page_url( $page_key = 'sustainability' ) {
	return arkray_home_url( arkray_get_sustainability_public_path( $page_key ) );
}

/**
 * Return sustainability route key from current request.
 *
 * @return string Empty string when request is not sustainability route.
 */
function arkray_get_sustainability_route_key_from_request() {
	$rel_path = arkray_get_request_relative_path();
	if ( '' === $rel_path ) {
		return '';
	}

	$segments = array_values( array_filter( explode( '/', $rel_path ), 'strlen' ) );
	if ( function_exists( 'pll_languages_list' ) ) {
		$langs = pll_languages_list( array( 'fields' => 'slug' ) );
		if ( ! empty( $segments ) && in_array( $segments[0], $langs, true ) ) {
			array_shift( $segments );
		}
	}

	if ( empty( $segments ) ) {
		return '';
	}

	$request_segment = sanitize_title( end( $segments ) );

	// /policy/ is the standalone Privacy Policy page. The Sustainability
	// policy route is valid only when it is under /sustainability/.
	if ( 'policy' === $request_segment && ( count( $segments ) < 2 || 'sustainability' !== $segments[ count( $segments ) - 2 ] ) ) {
		return '';
	}

	return arkray_get_sustainability_page_key_from_segment( $request_segment );
}

/**
 * Emit canonical /sustainability/{slug}/ permalinks for Sustainability template pages.
 *
 * @param string $permalink Page permalink.
 * @param int    $post_id   Page ID.
 * @return string
 */
function arkray_sustainability_page_link( $permalink, $post_id ) {
	$post = get_post( $post_id );
	if ( ! ( $post instanceof WP_Post ) || 'page' !== $post->post_type ) {
		return $permalink;
	}

	$map = arkray_get_sustainability_public_page_map();

	foreach ( $map as $page_key => $config ) {
		if ( in_array( $post->post_name, $config['wp_slugs'], true ) ) {
			return arkray_home_url( $config['public_path'] );
		}
	}

	if ( 'template-sustainability.php' === get_page_template_slug( $post_id ) ) {
		return arkray_home_url( arkray_get_sustainability_public_path( $post->post_name ) );
	}

	return $permalink;
}
add_filter( 'page_link', 'arkray_sustainability_page_link', 20, 2 );

/**
 * Register a single Sustainability sub-page rewrite for a URL slug segment.
 *
 * @param string $slug    Path segment under /sustainability/ (e.g. commitment, policy).
 * @param string $base    Rewrite query (index.php?page_id=…).
 * @param string $lang_re Polylang language slug regex alternation.
 * @return void
 */
function arkray_add_sustainability_slug_rewrite( $slug, $base, $lang_re ) {
	$slug = sanitize_title( (string) $slug );
	if ( '' === $slug ) {
		return;
	}

	$quoted = preg_quote( $slug, '#' );
	add_rewrite_rule( '^sustainability/' . $quoted . '/?$', $base, 'top' );
	add_rewrite_rule(
		'^(' . $lang_re . ')/sustainability/' . $quoted . '/?$',
		$base . '&lang=$matches[1]',
		'top'
	);
}

/**
 * Register rewrite rules so /sustainability/{slug}/ URLs resolve to matching pages.
 */
function arkray_add_sustainability_rewrites() {
	$lang_re = arkray_language_slugs_regex();
	$map     = arkray_get_sustainability_public_page_map();

	foreach ( $map as $page_key => $config ) {
		$page_id = arkray_get_sustainability_subpage_id( $page_key );
		if ( ! $page_id ) {
			continue;
		}

		$base = 'index.php?page_id=' . (int) $page_id;

		if ( 'sustainability' === $page_key ) {
			add_rewrite_rule( '^sustainability/?$', $base, 'top' );
			add_rewrite_rule( '^(' . $lang_re . ')/sustainability/?$', $base . '&lang=$matches[1]', 'top' );
			continue;
		}

		$legacy_slug = $config['legacy_slug'];
		if ( '' === $legacy_slug ) {
			continue;
		}

		$rewrite_slugs = array( $legacy_slug );
		foreach ( $config['wp_slugs'] as $slug ) {
			if ( ! in_array( $slug, $rewrite_slugs, true ) ) {
				$rewrite_slugs[] = $slug;
			}
		}

		foreach ( $rewrite_slugs as $slug ) {
			arkray_add_sustainability_slug_rewrite( $slug, $base, $lang_re );
		}
	}
}
add_action( 'init', 'arkray_add_sustainability_rewrites', 21 );

/**
 * Flush rewrite rules once so /sustainability/{slug}/ URLs resolve.
 * Re-run by deleting the `arkray_sustainability_public_rewrites_v2` option.
 */
function arkray_flush_sustainability_rewrites_once() {
	if ( get_option( 'arkray_sustainability_public_rewrites_v2' ) ) {
		return;
	}
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	flush_rewrite_rules();
	update_option( 'arkray_sustainability_public_rewrites_v2', 1 );
}
add_action( 'admin_init', 'arkray_flush_sustainability_rewrites_once' );

/**
 * Redirect legacy Sustainability URLs to canonical /sustainability/{slug}/ paths.
 */
function arkray_redirect_legacy_sustainability_urls() {
	if ( is_admin() || empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}

	$rel_path = arkray_get_request_relative_path();
	if ( '' === $rel_path ) {
		return;
	}

	$segments = array_values( array_filter( explode( '/', $rel_path ), 'strlen' ) );
	if ( function_exists( 'pll_languages_list' ) ) {
		$langs = pll_languages_list( array( 'fields' => 'slug' ) );
		if ( ! empty( $segments ) && in_array( $segments[0], $langs, true ) ) {
			array_shift( $segments );
		}
	}

	if ( empty( $segments ) ) {
		return;
	}

	$request_segment = sanitize_title( end( $segments ) );

	// Do not claim the standalone /policy/ Privacy Policy route. Only
	// /sustainability/policy/ belongs to the Sustainability URL family.
	if ( 'policy' === $request_segment && ( count( $segments ) < 2 || 'sustainability' !== $segments[ count( $segments ) - 2 ] ) ) {
		return;
	}

	$page_key = arkray_get_sustainability_page_key_from_segment( $request_segment );
	if ( '' === $page_key ) {
		return;
	}

	$canonical_path = trim( arkray_get_sustainability_public_path( $page_key ), '/' );
	$current_path   = implode( '/', $segments );

	if ( untrailingslashit( $current_path ) !== untrailingslashit( $canonical_path ) ) {
		wp_safe_redirect( arkray_get_sustainability_page_url( $page_key ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'arkray_redirect_legacy_sustainability_urls' );

/**
 * Prevent default 404 for virtual sustainability routes.
 *
 * @param bool     $preempt  Whether to short-circuit 404 handling.
 * @param WP_Query $wp_query Main query instance.
 * @return bool
 */
function arkray_prevent_sustainability_virtual_404( $preempt, $wp_query ) {
	if ( is_admin() || ! $wp_query->is_main_query() ) {
		return $preempt;
	}

	if ( '' === arkray_get_sustainability_route_key_from_request() ) {
		return $preempt;
	}

	// When WordPress already resolved a real page for this request (e.g. the
	// top-commitment/sdgs-basic-policy/arkrays-materiality/sdgs-initiatives
	// child pages), keep its natural query context so per-page data (ACF
	// fields, page ID, title) continues to work. Only synthesize a virtual
	// context for routes that have no underlying page record.
	if ( ! empty( $wp_query->posts ) ) {
		$wp_query->is_404 = false;
		return true;
	}

	$wp_query->is_404 = false;
	if ( function_exists( 'arkray_apply_virtual_page_context' ) ) {
		arkray_apply_virtual_page_context( $wp_query, array( 'sustainability', 'about-us' ) );
	} else {
		$wp_query->is_page     = true;
		$wp_query->is_singular = true;
	}

	return true;
}
add_filter( 'pre_handle_404', 'arkray_prevent_sustainability_virtual_404', 11, 2 );

/**
 * Route virtual sustainability requests to sustainability template.
 *
 * @param string $template Resolved template path.
 * @return string
 */
function arkray_route_virtual_sustainability_template( $template ) {
	if ( is_admin() || '' === arkray_get_sustainability_route_key_from_request() ) {
		return $template;
	}

	if ( function_exists( 'arkray_get_about_route_key_from_request' ) && '' !== arkray_get_about_route_key_from_request() ) {
		return $template;
	}

	$sustainability_template = get_stylesheet_directory() . '/template-sustainability.php';
	if ( file_exists( $sustainability_template ) ) {
		return $sustainability_template;
	}

	return $template;
}
add_filter( 'template_include', 'arkray_route_virtual_sustainability_template', 98 );

/**
 * Extract History of Pioneers route key from request URI.
 *
 * @return string
 */
function arkray_get_history_route_key_from_request() {
	if ( function_exists( 'arkray_is_about_history_request' ) && arkray_is_about_history_request() ) {
		return '';
	}

	// Product category URLs such as /products/urinalysis/ share a trailing
	// segment with History routes — never treat them as History pages.
	if ( function_exists( 'arkray_is_product_category_request_path' ) && arkray_is_product_category_request_path() ) {
		return '';
	}

	$request_path = arkray_get_request_last_segment();
	if ( '' === $request_path ) {
		return '';
	}

	$aliases = array(
		'history'            => 'history-of-pioneers',
		'history-pioneer'    => 'history-of-pioneers',
		'dry-chemistry'      => 'dry-chemistry-testing',
		// Canonical public slug (sanitize_title of diabetes_testing01).
		'diabetes-testing01' => 'diabetes-testing',
		'diabetes-testing-1' => 'diabetes-testing',
		'diabetes-testing02' => 'diabetes-testing-2',
		'diabetes-testing-2' => 'diabetes-testing-2',
		'poc-testing01'      => 'dry-chemistry-testing',
		'poc_testing01'      => 'dry-chemistry-testing',
		'poc-testing02'      => 'poc-testing-02',
		'poc_testing02'      => 'poc-testing-02',
		'smbg'               => 'bgm',
	);

	if ( isset( $aliases[ $request_path ] ) ) {
		$request_path = $aliases[ $request_path ];
	}

	$valid_keys = array(
		'history-of-pioneers',
		'diabetes-testing',
		'diabetes-testing-2',
		'urinalysis',
		'dry-chemistry-testing',
		'poc-testing-02',
		'bgm',
	);

	return in_array( $request_path, $valid_keys, true ) ? $request_path : '';
}

/**
 * Map History of Pioneers route keys to WP page slugs that hold content.
 *
 * Diabetes testing content lives on the `diabetes-testing-1` page (External
 * Content URL configured in WP admin).
 *
 * @return array<string, string> Route key => content page slug.
 */
function arkray_get_history_content_page_map() {
	return array(
		'history-of-pioneers'   => 'diabetes-testing-1',
		'diabetes-testing'      => 'diabetes-testing-1',
		'diabetes-testing-2'    => 'diabetes-testing-2',
		'urinalysis'            => 'test-2',
		'dry-chemistry-testing' => 'test-3',
		'poc-testing-02'        => 'poc_testing02',
		'bgm'                   => 'test-4',
	);
}

/**
 * Map history route keys to WP page slugs that hold external/ACF content.
 *
 * @param string $page_key History route key.
 * @return string Page slug, or '' when no mapping exists.
 */
function arkray_get_history_content_page_slug( $page_key ) {
	$map      = arkray_get_history_content_page_map();
	$page_key = sanitize_title( $page_key );

	return isset( $map[ $page_key ] ) ? $map[ $page_key ] : '';
}

/**
 * Reverse-map a content page slug to its History route key.
 *
 * @param string $page_slug WP page slug (e.g. diabetes-testing-1, test-2).
 * @return string Route key, or '' when not a mapped content page.
 */
function arkray_get_history_route_key_from_content_page( $page_slug ) {
	$reverse = array(
		'diabetes-testing-1' => 'diabetes-testing',
		'diabetes-testing01' => 'diabetes-testing',
		'diabetes-testing-2' => 'diabetes-testing-2',
		'diabetes-testing02' => 'diabetes-testing-2',
		'test-2'             => 'urinalysis',
		'test-3'             => 'dry-chemistry-testing',
		'poc_testing02'      => 'poc-testing-02',
		'poc-testing02'      => 'poc-testing-02',
		'test-4'             => 'bgm',
	);

	$page_slug = sanitize_title( $page_slug );

	return isset( $reverse[ $page_slug ] ) ? $reverse[ $page_slug ] : '';
}

/**
 * Diabetes history content pages and their canonical public paths.
 *
 * @return array<string, array{public_path:string, rewrite_slug:string, legacy_slug:string}>
 */
function arkray_get_diabetes_history_page_map() {
	return array(
		'diabetes-testing-1' => array(
			'public_path'  => '/products/history/diabetes_testing01/',
			'rewrite_slug' => 'diabetes_testing01',
			'legacy_slug'  => 'diabetes-testing-1',
		),
		'diabetes-testing-2' => array(
			'public_path'  => '/products/history/diabetes_testing02/',
			'rewrite_slug' => 'diabetes_testing02',
			'legacy_slug'  => 'diabetes-testing-2',
		),
	);
}

/**
 * History of Pioneers content pages and their canonical public paths.
 *
 * @return array<string, array{public_path:string, rewrite_slug:string, legacy_slug:string}>
 */
function arkray_get_history_public_page_map() {
	return array_merge(
		arkray_get_diabetes_history_page_map(),
		array(
			'test-2' => array(
				'public_path'  => '/products/history/urinalysis/',
				'rewrite_slug' => 'urinalysis',
				'legacy_slug'  => 'test-2',
			),
			'test-3' => array(
				'public_path'  => '/products/history/poc_testing01/',
				'rewrite_slug' => 'poc_testing01',
				'legacy_slug'  => 'test-3',
			),
			'poc_testing02' => array(
				'public_path'  => '/products/history/poc_testing02/',
				'rewrite_slug' => 'poc_testing02',
				'legacy_slug'  => 'poc_testing02',
			),
			'test-4' => array(
				'public_path'  => '/products/history/smbg/',
				'rewrite_slug' => 'smbg',
				'legacy_slug'  => 'test-4',
			),
		)
	);
}

/**
 * Canonical public path for the Urinalysis History page
 * (matches arkray.co.jp/english/products/history/urinalysis.html).
 *
 * @return string Path relative to the language home, with leading slash.
 */
function arkray_get_urinalysis_public_path() {
	return '/products/history/urinalysis/';
}

/**
 * WP page slug that holds Urinalysis external/ACF content.
 *
 * @return string
 */
function arkray_get_urinalysis_content_slug() {
	return 'test-2';
}

/**
 * Canonical public path for the Dry Chemistry Testing History page
 * (matches arkray.co.jp/english/products/history/poc_testing01.html).
 *
 * @return string Path relative to the language home, with leading slash.
 */
function arkray_get_dry_chemistry_public_path() {
	return '/products/history/poc_testing01/';
}

/**
 * WP page slug that holds Dry Chemistry Testing external/ACF content.
 *
 * @return string
 */
function arkray_get_dry_chemistry_content_slug() {
	return 'test-3';
}

/**
 * Canonical public path for the Dry Chemistry tab 2 (D-Concept) page
 * (matches arkray.co.jp/english/products/history/poc_testing02.html).
 *
 * @return string Path relative to the language home, with leading slash.
 */
function arkray_get_poc_testing02_public_path() {
	return '/products/history/poc_testing02/';
}

/**
 * WP page slug that holds Dry Chemistry tab 2 external/ACF content.
 *
 * @return string
 */
function arkray_get_poc_testing02_content_slug() {
	return 'poc_testing02';
}

/**
 * Canonical public path for the BGM History page
 * (matches arkray.co.jp/english/products/history/smbg.html).
 *
 * @return string Path relative to the language home, with leading slash.
 */
function arkray_get_bgm_public_path() {
	return '/products/history/smbg/';
}

/**
 * WP page slug that holds BGM external/ACF content.
 *
 * @return string
 */
function arkray_get_bgm_content_slug() {
	return 'test-4';
}

/**
 * Canonical public path for the Diabetes testing tab 1 page
 * (matches arkray.co.jp/english/products/history/diabetes_testing01.html).
 *
 * @return string Path relative to the language home, with leading slash.
 */
function arkray_get_diabetes_testing_public_path() {
	return arkray_get_diabetes_history_page_map()['diabetes-testing-1']['public_path'];
}

/**
 * Canonical public path for the Diabetes testing tab 2 page
 * (matches arkray.co.jp/english/products/history/diabetes_testing02.html).
 *
 * @return string Path relative to the language home, with leading slash.
 */
function arkray_get_diabetes_testing2_public_path() {
	return arkray_get_diabetes_history_page_map()['diabetes-testing-2']['public_path'];
}

/**
 * WP page slug that holds Diabetes testing tab 1 external/ACF content.
 *
 * @return string
 */
function arkray_get_diabetes_testing_content_slug() {
	return 'diabetes-testing-1';
}

/**
 * WP page slug that holds Diabetes testing tab 2 external/ACF content.
 *
 * @return string
 */
function arkray_get_diabetes_testing2_content_slug() {
	return 'diabetes-testing-2';
}

/**
 * Resolve the WP page ID whose external/ACF content should render for a route.
 *
 * @param string $page_slug  Current page slug from the request or queried object.
 * @param string $route_slug Active history route key.
 * @param string $type       Section type (unused; kept for template compatibility).
 * @return int
 */
function arkray_resolve_history_content_page_id( $page_slug, $route_slug, $type = '' ) {
	unset( $type );

	$page_slug  = sanitize_title( $page_slug );
	$route_slug = sanitize_title( $route_slug );

	// When WordPress resolved a real content-holder page, use it directly.
	$content_page_slugs = array_unique( array_values( arkray_get_history_content_page_map() ) );
	if ( '' !== $page_slug && in_array( $page_slug, $content_page_slugs, true ) ) {
		$page = get_page_by_path( $page_slug, OBJECT, 'page' );
		if ( $page instanceof WP_Post ) {
			return arkray_pll_post_id( $page->ID );
		}
	}

	$content_slug = arkray_get_history_content_page_slug( $route_slug );
	if ( '' === $content_slug ) {
		$mapped_route = arkray_get_history_route_key_from_content_page( $page_slug );
		if ( '' !== $mapped_route ) {
			$content_slug = arkray_get_history_content_page_slug( $mapped_route );
		}
	}
	if ( '' === $content_slug ) {
		$content_slug = 'diabetes-testing-1';
	}

	$page = get_page_by_path( $content_slug, OBJECT, 'page' );
	if ( $page instanceof WP_Post ) {
		return arkray_pll_post_id( $page->ID );
	}

	return (int) get_queried_object_id();
}

/**
 * Resolve History of Pioneers page URL.
 *
 * @param string $page_key History route key.
 * @param string $tab      Optional content tab key.
 * @return string
 */
function arkray_get_history_page_url( $page_key = 'history-of-pioneers', $tab = '' ) {
	$page_key = sanitize_title( $page_key );

	// History of Pioneers and diabetes testing tab 1 share the canonical landing URL.
	if ( in_array( $page_key, array( 'history-of-pioneers', 'diabetes-testing' ), true ) ) {
		$url = arkray_home_url( arkray_get_diabetes_testing_public_path() );
		if ( '' !== $tab ) {
			$url = add_query_arg( 'tab', sanitize_key( $tab ), $url );
		}
		return esc_url( $url );
	}

	// Diabetes testing tab 2 uses the canonical /products/history/diabetes_testing02/ URL.
	if ( 'diabetes-testing-2' === $page_key ) {
		$url = arkray_home_url( arkray_get_diabetes_testing2_public_path() );
		if ( '' !== $tab ) {
			$url = add_query_arg( 'tab', sanitize_key( $tab ), $url );
		}
		return esc_url( $url );
	}

	// Urinalysis uses the canonical /products/history/urinalysis/ URL.
	if ( 'urinalysis' === $page_key ) {
		$url = arkray_home_url( arkray_get_urinalysis_public_path() );
		if ( '' !== $tab ) {
			$url = add_query_arg( 'tab', sanitize_key( $tab ), $url );
		}
		return esc_url( $url );
	}

	// Dry Chemistry Testing tab 1 uses the canonical /products/history/poc_testing01/ URL.
	if ( 'dry-chemistry-testing' === $page_key ) {
		$url = arkray_home_url( arkray_get_dry_chemistry_public_path() );
		if ( '' !== $tab ) {
			$url = add_query_arg( 'tab', sanitize_key( $tab ), $url );
		}
		return esc_url( $url );
	}

	// Dry Chemistry Testing tab 2 (D-Concept) uses /products/history/poc_testing02/.
	if ( 'poc-testing-02' === $page_key ) {
		$url = arkray_home_url( arkray_get_poc_testing02_public_path() );
		if ( '' !== $tab ) {
			$url = add_query_arg( 'tab', sanitize_key( $tab ), $url );
		}
		return esc_url( $url );
	}

	// BGM uses the canonical /products/history/smbg/ URL.
	if ( 'bgm' === $page_key ) {
		$url = arkray_home_url( arkray_get_bgm_public_path() );
		if ( '' !== $tab ) {
			$url = add_query_arg( 'tab', sanitize_key( $tab ), $url );
		}
		return esc_url( $url );
	}

	$candidates = array(
		'history-of-pioneers'   => array( 'history-of-pioneers', 'history-pioneers', 'history' ),
	);

	if ( ! isset( $candidates[ $page_key ] ) ) {
		$page_key = 'history-of-pioneers';
	}

	$candidate_slugs = $candidates[ $page_key ];
	$url             = '';

	foreach ( $candidate_slugs as $slug ) {
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
			$url = arkray_pll_permalink( $page->ID );
			break;
		}
	}

	if ( '' === $url ) {
		$url = arkray_home_url( '/' . $page_key . '/' );
	}

	if ( '' !== $tab ) {
		$url = add_query_arg( 'tab', sanitize_key( $tab ), $url );
	}

	return esc_url( $url );
}

/**
 * Prevent 404 for virtual history routes.
 *
 * @param bool     $preempt  Whether to short-circuit 404 handling.
 * @param WP_Query $wp_query Main query instance.
 * @return bool
 */
function arkray_prevent_history_virtual_404( $preempt, $wp_query ) {
	if ( is_admin() || ! $wp_query->is_main_query() ) {
		return $preempt;
	}

	if ( function_exists( 'arkray_is_about_history_request' ) && arkray_is_about_history_request() ) {
		return $preempt;
	}

	if ( function_exists( 'arkray_is_product_category_request_path' ) && arkray_is_product_category_request_path() ) {
		return $preempt;
	}

	if ( '' === arkray_get_history_route_key_from_request() ) {
		return $preempt;
	}

	$wp_query->is_404 = false;
	arkray_apply_virtual_page_context( $wp_query, array( 'history-of-pioneers', 'about-us', 'sustainability' ) );

	return true;
}
add_filter( 'pre_handle_404', 'arkray_prevent_history_virtual_404', 10, 2 );

/**
 * Route virtual history requests to history template.
 *
 * @param string $template Resolved template path.
 * @return string
 */
function arkray_route_virtual_history_template( $template ) {
	if ( is_admin() ) {
		return $template;
	}

	if ( function_exists( 'arkray_is_about_history_request' ) && arkray_is_about_history_request() ) {
		return $template;
	}

	if ( function_exists( 'arkray_is_product_category_request_path' ) && arkray_is_product_category_request_path() ) {
		return $template;
	}

	if ( '' === arkray_get_history_route_key_from_request() ) {
		return $template;
	}

	$history_template = get_stylesheet_directory() . '/template-history-of-pioneers.php';
	if ( file_exists( $history_template ) ) {
		return $history_template;
	}

	return $template;
}
add_filter( 'template_include', 'arkray_route_virtual_history_template', 97 );

/**
 * Rewrite /products/history/{slug}(/|.html) to matching History content pages.
 * Registered after product-detail rewrites so these more-specific rules win.
 */
function arkray_add_diabetes_testing_history_rewrites() {
	$lang_re = arkray_language_slugs_regex();

	foreach ( arkray_get_history_public_page_map() as $wp_slug => $config ) {
		$page = get_page_by_path( $wp_slug, OBJECT, 'page' );
		if ( ! ( $page instanceof WP_Post ) ) {
			continue;
		}

		$pid     = (int) $page->ID;
		$slug_re = $config['rewrite_slug'];
		$base    = 'index.php?page_id=' . $pid;

		add_rewrite_rule( '^products/history/' . $slug_re . '\.html$', $base, 'top' );
		add_rewrite_rule( '^products/history/' . $slug_re . '/?$', $base, 'top' );
		add_rewrite_rule(
			'^(' . $lang_re . ')/products/history/' . $slug_re . '\.html$',
			$base . '&lang=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^(' . $lang_re . ')/products/history/' . $slug_re . '/?$',
			$base . '&lang=$matches[1]',
			'top'
		);
	}
}
add_action( 'init', 'arkray_add_diabetes_testing_history_rewrites', 21 );

/**
 * Emit canonical /products/history/diabetes_testing0{1,2}/ permalinks for the
 * diabetes-testing content pages.
 *
 * @param string $permalink Page permalink.
 * @param int    $post_id   Page ID.
 * @return string
 */
function arkray_diabetes_testing_page_link( $permalink, $post_id ) {
	$post = get_post( $post_id );
	if ( ! ( $post instanceof WP_Post ) || 'page' !== $post->post_type ) {
		return $permalink;
	}

	if ( 'history-of-pioneers' === $post->post_name ) {
		return arkray_home_url( arkray_get_diabetes_testing_public_path() );
	}

	$map = arkray_get_history_public_page_map();
	if ( ! isset( $map[ $post->post_name ] ) ) {
		return $permalink;
	}

	return arkray_home_url( $map[ $post->post_name ]['public_path'] );
}
add_filter( 'page_link', 'arkray_diabetes_testing_page_link', 20, 2 );

/**
 * 301-redirect legacy /diabetes-testing-1/, /diabetes-testing-2/, and
 * /diabetes-testing/ to their canonical /products/history/... URLs.
 */
function arkray_redirect_legacy_diabetes_testing_url() {
	if ( is_admin() || empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}

	$request_path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );

	// Legacy /history-of-pioneers/ → canonical diabetes tab 1 landing URL.
	if ( preg_match( '#/(history-of-pioneers|history-pioneers)(/|$)#', $request_path ) ) {
		if ( ! preg_match( '#/products/history/diabetes_testing01(/|\.html|$)#', $request_path ) ) {
			$target = arkray_home_url( arkray_get_diabetes_testing_public_path() );
			$query  = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_QUERY );
			if ( '' !== $query ) {
				$target .= ( false === strpos( $target, '?' ) ? '?' : '&' ) . $query;
			}

			wp_safe_redirect( $target, 301 );
			exit;
		}
	}

	$map = arkray_get_history_public_page_map();

	foreach ( $map as $config ) {
		$legacy_slug  = $config['legacy_slug'];
		$public_path  = $config['public_path'];
		$rewrite_slug = $config['rewrite_slug'];

		if ( preg_match( '#/' . preg_quote( $legacy_slug, '#' ) . '(/|$)#', $request_path ) ) {
			if ( preg_match( '#/products/history/' . preg_quote( $rewrite_slug, '#' ) . '(/|\.html|$)#', $request_path ) ) {
				return;
			}

			if ( function_exists( 'arkray_is_product_category_request_path' ) && arkray_is_product_category_request_path( $request_path ) ) {
				return;
			}

			$target = arkray_home_url( $public_path );
			$query  = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_QUERY );
			if ( '' !== $query ) {
				$target .= ( false === strpos( $target, '?' ) ? '?' : '&' ) . $query;
			}

			wp_safe_redirect( $target, 301 );
			exit;
		}
	}

	// Bare /diabetes-testing/ (no tab suffix) → tab 1.
	if ( preg_match( '#/(diabetes-testing)(/|$)#', $request_path )
		&& ! preg_match( '#/(diabetes-testing-[12])(/|$)#', $request_path ) ) {
		if ( preg_match( '#/products/history/diabetes_testing01(/|\.html|$)#', $request_path ) ) {
			return;
		}

		$target = arkray_home_url( arkray_get_diabetes_testing_public_path() );
		$query  = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_QUERY );
		if ( '' !== $query ) {
			$target .= ( false === strpos( $target, '?' ) ? '?' : '&' ) . $query;
		}

		wp_safe_redirect( $target, 301 );
		exit;
	}

	// Legacy bare /urinalysis/ → canonical /products/history/urinalysis/.
	if ( preg_match( '#/(urinalysis)(/|$)#', $request_path )
		&& ! preg_match( '#/products/history/urinalysis(/|\.html|$)#', $request_path )
		&& ! preg_match( '#/products/urinalysis(/|$)#', $request_path ) ) {
		$target = arkray_home_url( arkray_get_urinalysis_public_path() );
		$query  = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_QUERY );
		if ( '' !== $query ) {
			$target .= ( false === strpos( $target, '?' ) ? '?' : '&' ) . $query;
		}

		wp_safe_redirect( $target, 301 );
		exit;
	}

	// Legacy bare /dry-chemistry-testing/ and /dry-chemistry/ → poc_testing01.
	if ( preg_match( '#/(dry-chemistry-testing|dry-chemistry)(/|$)#', $request_path )
		&& ! preg_match( '#/products/history/poc_testing01(/|\.html|$)#', $request_path ) ) {
		$target = arkray_home_url( arkray_get_dry_chemistry_public_path() );
		$query  = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_QUERY );
		if ( '' !== $query ) {
			$target .= ( false === strpos( $target, '?' ) ? '?' : '&' ) . $query;
		}

		wp_safe_redirect( $target, 301 );
		exit;
	}

	// Legacy bare /bgm/ → canonical /products/history/smbg/ (not product category paths).
	if ( preg_match( '#/(bgm)(/|$)#', $request_path )
		&& ! preg_match( '#/products/#', $request_path )
		&& ! preg_match( '#/products/history/smbg(/|\.html|$)#', $request_path ) ) {
		$target = arkray_home_url( arkray_get_bgm_public_path() );
		$query  = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_QUERY );
		if ( '' !== $query ) {
			$target .= ( false === strpos( $target, '?' ) ? '?' : '&' ) . $query;
		}

		wp_safe_redirect( $target, 301 );
		exit;
	}
}
add_action( 'template_redirect', 'arkray_redirect_legacy_diabetes_testing_url', -11 );

/**
 * Flush rewrite rules once so /products/history/... canonical paths resolve.
 * Re-run by deleting the `arkray_history_public_rewrites_v4` option.
 */
function arkray_flush_diabetes_testing_history_rewrites_once() {
	if ( get_option( 'arkray_history_public_rewrites_v4' ) ) {
		return;
	}
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	flush_rewrite_rules();
	update_option( 'arkray_history_public_rewrites_v4', 1 );
}
add_action( 'admin_init', 'arkray_flush_diabetes_testing_history_rewrites_once' );

/**
 * Extract Events & Gallery route key from request URI.
 *
 * @return string
 */
function arkray_get_events_gallery_route_key_from_request() {
	$request_relative_path = trim( arkray_get_request_relative_path(), '/' );
	if ( preg_match( '#(?:^|/)events_gallery/events(?:/[^/]+)?/?$#', $request_relative_path ) ) {
		return 'events_gallery';
	}

	$request_path = arkray_get_request_last_segment();
	if ( '' === $request_path ) {
		return '';
	}

	$aliases = array(
		'events'         => 'events_gallery',
		'gallery'        => 'events_gallery',
		'media-gallery'  => 'events_gallery',
		'events_gallery' => 'events_gallery',
	);

	if ( isset( $aliases[ $request_path ] ) ) {
		$request_path = $aliases[ $request_path ];
	}

	$valid_keys = array(
		'events_gallery',
	);

	return in_array( $request_path, $valid_keys, true ) ? $request_path : '';
}

/**
 * Resolve Events & Gallery page URL.
 *
 * @param string $tab Optional tab key.
 * @return string
 */
function arkray_get_events_gallery_page_url( $tab = '' ) {
	$tab = sanitize_key( $tab );

	if ( '' === $tab || 'events' === $tab ) {
		return esc_url( arkray_home_url( '/events_gallery/events' ) );
	}

	if ( 'gallery' === $tab || 'media-gallery' === $tab ) {
		return esc_url( arkray_home_url( '/events_gallery/gallery' ) );
	}

	return esc_url( arkray_home_url( '/events_gallery/' ) );
}

/**
 * Redirect the legacy Media Gallery query-string URL to its clean route.
 */
function arkray_redirect_legacy_media_gallery_tab_url() {
	if ( is_admin() || ! isset( $_GET['tab'] ) ) {
		return;
	}

	$tab = sanitize_key( wp_unslash( $_GET['tab'] ) );
	if ( 'gallery' !== $tab && 'media-gallery' !== $tab ) {
		return;
	}

	$request_relative_path = trim( arkray_get_request_relative_path(), '/' );
	if ( ! preg_match( '#(?:^|/)events[-_]gallery/?$#', $request_relative_path ) ) {
		return;
	}

	wp_safe_redirect( arkray_get_events_gallery_page_url( 'gallery' ), 301 );
	exit;
}
add_action( 'template_redirect', 'arkray_redirect_legacy_media_gallery_tab_url', -13 );

/**
 * Redirect the former hyphenated Events & Gallery segment to events_gallery.
 */
function arkray_redirect_legacy_events_gallery_segment() {
	if ( is_admin() ) {
		return;
	}

	$request_relative_path = trim( arkray_get_request_relative_path(), '/' );
	if ( ! preg_match( '#(?:^|/)events-gallery(?:/|$)#', $request_relative_path ) ) {
		return;
	}

	$target_path = preg_replace( '#(^|/)events-gallery(?=/|$)#', '$1events_gallery', $request_relative_path, 1 );
	$target_url  = arkray_home_url( '/' . ltrim( $target_path, '/' ) );
	$query       = (string) parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_QUERY );

	if ( '' !== $query ) {
		$query_args = array();
		wp_parse_str( $query, $query_args );
		$target_url = add_query_arg( $query_args, $target_url );
	}

	wp_safe_redirect( $target_url, 301 );
	exit;
}
add_action( 'template_redirect', 'arkray_redirect_legacy_events_gallery_segment', -11 );

/**
 * Redirect former top-level Gallery detail permalinks to their nested route.
 */
function arkray_redirect_legacy_gallery_detail_permalink() {
	if ( is_admin() ) {
		return;
	}

	$request_relative_path = trim( arkray_get_request_relative_path(), '/' );
	if ( ! preg_match( '#^(?:english/|vietnamese/)?gallary/([^/]+)/?$#', $request_relative_path, $matches ) ) {
		return;
	}

	$gallery_slug = sanitize_title( rawurldecode( $matches[1] ) );
	$gallery      = get_posts(
		array(
			'name'           => $gallery_slug,
			'post_type'      => array( 'gallery', 'gallary' ),
			'post_status'    => 'publish',
			'posts_per_page' => 1,
		)
	);
	$gallery      = $gallery ? reset( $gallery ) : null;
	if ( ! $gallery instanceof WP_Post || 'publish' !== $gallery->post_status ) {
		return;
	}

	$target_url = arkray_home_url( '/events_gallery/gallery/' . rawurlencode( $gallery_slug ) . '/' );
	$query      = (string) parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_QUERY );
	if ( '' !== $query ) {
		$query_args = array();
		wp_parse_str( $query, $query_args );
		$target_url = add_query_arg( $query_args, $target_url );
	}

	wp_safe_redirect( $target_url, 301 );
	exit;
}
add_action( 'template_redirect', 'arkray_redirect_legacy_gallery_detail_permalink', -14 );

/**
 * Redirect the former misspelled /events_gallery/gallary/ segment to /gallery/.
 */
function arkray_redirect_legacy_gallery_misspelled_segment() {
	if ( is_admin() ) {
		return;
	}

	$request_relative_path = trim( arkray_get_request_relative_path(), '/' );
	if ( ! preg_match( '#^(?:(english|vietnamese)/)?events_gallery/gallary/([^/]+)/?$#', $request_relative_path, $matches ) ) {
		return;
	}

	$gallery_slug = sanitize_title( rawurldecode( $matches[2] ) );
	$gallery      = get_posts(
		array(
			'name'           => $gallery_slug,
			'post_type'      => array( 'gallery', 'gallary' ),
			'post_status'    => 'publish',
			'posts_per_page' => 1,
		)
	);
	$gallery = $gallery ? reset( $gallery ) : null;
	if ( ! ( $gallery instanceof WP_Post ) ) {
		return;
	}

	$target_url = get_permalink( $gallery );
	if ( ! $target_url ) {
		return;
	}

	$query = (string) parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_QUERY );
	if ( '' !== $query ) {
		$query_args = array();
		wp_parse_str( $query, $query_args );
		$target_url = add_query_arg( $query_args, $target_url );
	}

	wp_safe_redirect( $target_url, 301 );
	exit;
}
add_action( 'template_redirect', 'arkray_redirect_legacy_gallery_misspelled_segment', -15 );

/**
 * Extract an event slug from a nested Events & Gallery detail URL.
 *
 * @return string
 */
function arkray_get_events_gallery_event_key_from_request() {
	$request_relative_path = trim( arkray_get_request_relative_path(), '/' );
	if ( ! preg_match( '#(?:^|/)events_gallery/events/([^/]+)/?$#', $request_relative_path, $matches ) ) {
		return '';
	}

	return sanitize_title( rawurldecode( $matches[1] ) );
}

/**
 * Resolve an Events & Gallery detail URL.
 *
 * @param string $event_key Event post slug.
 * @return string
 */
function arkray_get_events_gallery_event_url( $event_key ) {
	$event_key = sanitize_title( $event_key );
	if ( '' === $event_key ) {
		return arkray_get_events_gallery_page_url( 'events' );
	}

	return esc_url( arkray_home_url( '/events_gallery/events/' . rawurlencode( $event_key ) ) );
}

/**
 * Redirect legacy query-string event details to their nested clean URLs.
 */
function arkray_redirect_legacy_events_gallery_detail_url() {
	if ( is_admin() || ! isset( $_GET['event'] ) ) {
		return;
	}

	$request_relative_path = trim( arkray_get_request_relative_path(), '/' );
	if ( ! preg_match( '#(?:^|/)events[-_]gallery/events/?$#', $request_relative_path ) ) {
		return;
	}

	$event_key = sanitize_title( wp_unslash( $_GET['event'] ) );
	if ( '' === $event_key ) {
		return;
	}

	wp_safe_redirect( arkray_get_events_gallery_event_url( $event_key ), 301 );
	exit;
}
add_action( 'template_redirect', 'arkray_redirect_legacy_events_gallery_detail_url', -12 );

/**
 * Redirect legacy Event CPT detail URLs to nested Events & Gallery routes.
 */
function arkray_redirect_legacy_event_detail_url() {
	if ( is_admin() || ! is_singular( 'event' ) ) {
		return;
	}

	$request_relative_path = trim( arkray_get_request_relative_path(), '/' );
	if ( preg_match( '#events_gallery/events/#', $request_relative_path ) ) {
		return;
	}

	$post = get_queried_object();
	if ( ! ( $post instanceof WP_Post ) || '' === $post->post_name ) {
		return;
	}

	wp_safe_redirect( arkray_get_events_gallery_event_url( $post->post_name ), 301 );
	exit;
}
add_action( 'template_redirect', 'arkray_redirect_legacy_event_detail_url', -12 );

/**
 * Prevent 404 for virtual Events & Gallery routes.
 *
 * @param bool     $preempt  Whether to short-circuit 404 handling.
 * @param WP_Query $wp_query Main query instance.
 * @return bool
 */
function arkray_prevent_events_gallery_virtual_404( $preempt, $wp_query ) {
	if ( is_admin() || ! $wp_query->is_main_query() ) {
		return $preempt;
	}

	if ( '' === arkray_get_events_gallery_route_key_from_request() ) {
		return $preempt;
	}

	$wp_query->is_404              = false;
	$wp_query->is_single           = false;
	$wp_query->is_archive          = false;
	$wp_query->is_post_type_archive = false;
	arkray_apply_virtual_page_context( $wp_query, array( 'events_gallery', 'events-gallery', 'about-us', 'sustainability' ) );

	return true;
}
add_filter( 'pre_handle_404', 'arkray_prevent_events_gallery_virtual_404', 10, 2 );

/**
 * Route virtual Events & Gallery requests to template.
 *
 * @param string $template Resolved template path.
 * @return string
 */
function arkray_route_virtual_events_gallery_template( $template ) {
	if ( is_admin() ) {
		return $template;
	}

	if ( '' === arkray_get_events_gallery_route_key_from_request() ) {
		return $template;
	}

	$events_template = get_stylesheet_directory() . '/template-events-gallery.php';
	if ( file_exists( $events_template ) ) {
		return $events_template;
	}

	return $template;
}
add_filter( 'template_include', 'arkray_route_virtual_events_gallery_template', 97 );

/**
 * Keep nested Events & Gallery routes from being canonicalized to Event CPT URLs.
 *
 * Event detail slugs can also match native Event CPT requests. Keep nested
 * `/events_gallery/events/{slug}` requests on the virtual template instead of
 * redirecting them to the native `/events/{slug}/` permalink.
 *
 * @param string|false $redirect_url  Canonical redirect target.
 * @param string       $requested_url Requested URL.
 * @return string|false
 */
function arkray_preserve_events_gallery_virtual_canonical( $redirect_url, $requested_url ) {
	if ( '' !== arkray_get_events_gallery_route_key_from_request() ) {
		return false;
	}

	return $redirect_url;
}
add_filter( 'redirect_canonical', 'arkray_preserve_events_gallery_virtual_canonical', 10, 2 );

/**
 * Extract recruitment route key from request URI.
 *
 * @return string
 */
function arkray_get_recruitment_route_key_from_request() {
	$rel_path = arkray_get_request_relative_path();
	if ( '' === $rel_path ) {
		return '';
	}

	$segments = array_values( array_filter( explode( '/', $rel_path ), 'strlen' ) );
	if ( function_exists( 'pll_languages_list' ) ) {
		$langs = pll_languages_list( array( 'fields' => 'slug' ) );
		if ( ! empty( $segments ) && in_array( $segments[0], $langs, true ) ) {
			array_shift( $segments );
		}
	}

	if ( empty( $segments ) ) {
		return '';
	}

	$request_segment = sanitize_title( end( $segments ) );
	if ( in_array( $request_segment, array( 'index', 'index-html' ), true ) && count( $segments ) >= 2 ) {
		$request_segment = sanitize_title( $segments[ count( $segments ) - 2 ] );
	}

	$valid_keys = array(
		'recruitment',
		'open-positions',
		'application-process',
		'company-culture',
		'benefits',
	);

	return in_array( $request_segment, $valid_keys, true ) ? $request_segment : '';
}

/**
 * Resolve Recruitment page URL.
 *
 * @param string $page_key Recruitment route key.
 * @return string
 */
function arkray_get_recruitment_page_url( $page_key = 'recruitment' ) {
	$page_key = sanitize_title( $page_key );

	$candidates = array(
		'recruitment'           => array( 'recruitment', 'careers' ),
		'open-positions'        => array( 'open-positions', 'recruitment/open-positions', 'jobs' ),
		'application-process'   => array( 'application-process', 'recruitment/application-process', 'apply' ),
		'company-culture'       => array( 'company-culture', 'recruitment/company-culture', 'culture' ),
		'benefits'              => array( 'benefits', 'recruitment/benefits', 'our-benefits' ),
	);

	if ( ! isset( $candidates[ $page_key ] ) ) {
		$page_key = 'recruitment';
	}

	$candidate_slugs = $candidates[ $page_key ];

	foreach ( $candidate_slugs as $slug ) {
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $page ) {
			return esc_url( arkray_pll_permalink( $page->ID ) );
		}
	}

	return esc_url( arkray_home_url( '/' . sanitize_title( $page_key ) . '/' ) );
}

/**
 * Get a real page object to use as context for virtual routes.
 *
 * @param array<int, string> $preferred_slugs Preferred page slugs.
 * @return WP_Post|null
 */
function arkray_get_virtual_page_context( $preferred_slugs = array() ) {
	foreach ( $preferred_slugs as $slug ) {
		$page = get_page_by_path( sanitize_title( $slug ), OBJECT, 'page' );
		if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
			return $page;
		}
	}

	$front_page_id = (int) get_option( 'page_on_front' );
	if ( $front_page_id > 0 ) {
		$front_page = get_post( $front_page_id );
		if ( $front_page instanceof WP_Post && 'page' === $front_page->post_type ) {
			return $front_page;
		}
	}

	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'numberposts'    => 1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'suppress_filters' => true,
		)
	);

	if ( ! empty( $pages ) && $pages[0] instanceof WP_Post ) {
		return $pages[0];
	}

	return null;
}

/**
 * Apply a valid page context for virtual routes.
 *
 * @param WP_Query          $wp_query Main query.
 * @param array<int, string> $preferred_slugs Preferred page slugs for context.
 * @return void
 */
function arkray_apply_virtual_page_context( $wp_query, $preferred_slugs = array() ) {
	$virtual_page = arkray_get_virtual_page_context( $preferred_slugs );

	if ( ! $virtual_page instanceof WP_Post ) {
		return;
	}

	$wp_query->is_page          = true;
	$wp_query->is_singular      = true;
	$wp_query->queried_object   = $virtual_page;
	$wp_query->queried_object_id = (int) $virtual_page->ID;
	$wp_query->post             = $virtual_page;
	$wp_query->posts            = array( $virtual_page );
	$wp_query->post_count       = 1;
	$wp_query->found_posts      = 1;
	$wp_query->max_num_pages    = 1;
	$wp_query->set( 'page_id', (int) $virtual_page->ID );

	global $post;
	$post = $virtual_page;
}

/**
 * Prevent 404 for virtual recruitment routes.
 *
 * @param bool     $preempt WP_Query.
 * @param WP_Query $wp_query WP_Query object.
 * @return bool
 */
function arkray_prevent_recruitment_virtual_404( $preempt, $wp_query ) {
	if ( is_admin() || ! $wp_query->is_main_query() ) {
		return $preempt;
	}

	// Yield to the recruitment CPT archive when WP has already resolved it.
	if ( ! empty( $wp_query->query_vars['post_type'] ) && 'recruitment' === $wp_query->query_vars['post_type'] ) {
		return $preempt;
	}

	if ( '' === arkray_get_recruitment_route_key_from_request() ) {
		return $preempt;
	}

	$wp_query->is_404 = false;
	arkray_apply_virtual_page_context( $wp_query, array( 'recruitment', 'about-us', 'sustainability' ) );

	return true;
}
add_filter( 'pre_handle_404', 'arkray_prevent_recruitment_virtual_404', 10, 2 );

/**
 * Route virtual recruitment requests to recruitment template.
 *
 * @param string $template Resolved template path.
 * @return string
 */
function arkray_route_virtual_recruitment_template( $template ) {
	if ( is_admin() || is_post_type_archive( 'recruitment' ) || is_singular( 'recruitment' ) ) {
		return $template;
	}

	if ( '' === arkray_get_recruitment_route_key_from_request() ) {
		return $template;
	}

	$recruitment_template = get_stylesheet_directory() . '/template-recruitment.php';
	if ( file_exists( $recruitment_template ) ) {
		return $recruitment_template;
	}

	return $template;
}
add_filter( 'template_include', 'arkray_route_virtual_recruitment_template', 97 );


/**
 * Place ACF-managed Gallery detail posts beneath Events & Gallery.
 *
 * ACF registers the post type as `gallery`. Public detail URLs use
 * /events_gallery/gallery/{slug}/ beneath the Media Gallery tab.
 *
 * @param array<string, mixed> $args      Post type registration arguments.
 * @param string               $post_type Post type key.
 * @return array<string, mixed>
 */
function arkray_gallery_post_type_permalink_args( $args, $post_type ) {
	if ( 'gallery' !== $post_type ) {
		return $args;
	}

	$args['rewrite'] = array(
		'slug'       => 'events_gallery/gallery',
		'with_front' => false,
		'feeds'      => false,
		'pages'      => true,
	);

	return $args;
}
add_filter( 'register_post_type_args', 'arkray_gallery_post_type_permalink_args', 20, 2 );

/**
 * Public URL for a Gallery detail post (language-prefixed).
 *
 * @param string  $permalink Default permalink.
 * @param WP_Post $post      Gallery post.
 * @return string
 */
function arkray_gallery_permalink( $permalink, $post ) {
	if ( ! ( $post instanceof WP_Post ) || 'gallery' !== $post->post_type ) {
		return $permalink;
	}

	if ( '' === $post->post_name ) {
		return $permalink;
	}

	return arkray_home_url( '/events_gallery/gallery/' . $post->post_name . '/' );
}
add_filter( 'post_type_link', 'arkray_gallery_permalink', 20, 2 );

/**
 * Public URL for an Event detail post (language-prefixed).
 *
 * Event details live under Events & Gallery at
 * /events_gallery/events/{slug}/ rather than the native /events/{slug}/ CPT path.
 *
 * @param string  $permalink Default permalink.
 * @param WP_Post $post      Event post.
 * @return string
 */
function arkray_event_permalink( $permalink, $post ) {
	if ( ! ( $post instanceof WP_Post ) || 'event' !== $post->post_type ) {
		return $permalink;
	}

	if ( '' === $post->post_name ) {
		return $permalink;
	}

	return arkray_home_url( '/events_gallery/events/' . $post->post_name . '/' );
}
add_filter( 'post_type_link', 'arkray_event_permalink', 26, 2 );

/**
 * Register language-prefixed Gallery detail rewrite rules.
 */
function arkray_add_gallery_detail_rewrites() {
	$lang_re = arkray_language_slugs_regex();
	add_rewrite_rule(
		'^(' . $lang_re . ')/events_gallery/gallery/([^/]+)/?$',
		'index.php?gallery=$matches[2]&lang=$matches[1]',
		'top'
	);
}
add_action( 'init', 'arkray_add_gallery_detail_rewrites', 20 );

/**
 * Resolve language-prefixed Gallery detail requests even when rewrite cache is stale.
 *
 * @param WP $wp Current WordPress request.
 */
function arkray_parse_gallery_detail_request( $wp ) {
	if ( is_admin() || empty( $wp->request ) ) {
		return;
	}

	$lang_re = arkray_language_slugs_regex();
	$request = trim( $wp->request, '/' );

	if ( ! preg_match( '#^(' . $lang_re . ')/events_gallery/gallery/([^/]+)/?$#i', $request, $matches ) ) {
		return;
	}

	$post_slug = sanitize_title_for_query( $matches[2] );
	$post      = get_posts(
		array(
			'name'           => $post_slug,
			'post_type'      => 'gallery',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
		)
	);
	$post = $post ? reset( $post ) : null;
	if ( ! ( $post instanceof WP_Post ) ) {
		return;
	}

	$wp->query_vars['gallery'] = $post->post_name;
	$wp->query_vars['post_type'] = 'gallery';
	$wp->query_vars['name']      = $post->post_name;
	$wp->query_vars['lang']      = sanitize_title( $matches[1] );
	unset( $wp->query_vars['error'] );
	$wp->matched_rule  = 'arkray-gallery-detail';
	$wp->matched_query = 'gallery=' . $post->post_name;
}
add_action( 'parse_request', 'arkray_parse_gallery_detail_request', 15 );

/**
 * Whether the current request targets a Gallery detail page.
 *
 * @return string Gallery post slug, or '' when not a detail request.
 */
function arkray_get_gallery_detail_slug_from_request() {
	$request_relative_path = trim( arkray_get_request_relative_path(), '/' );
	if ( preg_match( '#^(?:english/|vietnamese/)?events_gallery/gallery/([^/]+)/?$#', $request_relative_path, $matches ) ) {
		return sanitize_title( rawurldecode( $matches[1] ) );
	}

	return '';
}

/**
 * Keep Gallery detail URLs from being canonicalized to the unprefixed CPT form.
 *
 * Without this, redirect_canonical strips /english/ from
 * /english/events_gallery/gallery/{slug}/ while our unprefixed redirect sends
 * the browser back, causing ERR_TOO_MANY_REDIRECTS.
 *
 * @param string|false $redirect_url  Canonical redirect target.
 * @param string       $requested_url Requested URL.
 * @return string|false
 */
function arkray_preserve_gallery_detail_canonical( $redirect_url, $requested_url ) {
	if ( '' !== arkray_get_gallery_detail_slug_from_request() || is_singular( 'gallery' ) ) {
		return false;
	}

	return $redirect_url;
}
add_filter( 'redirect_canonical', 'arkray_preserve_gallery_detail_canonical', 10, 2 );

/**
 * Prevent Polylang from rewriting Gallery detail URLs during canonical checks.
 *
 * @param string|false $redirect_url Redirect target.
 * @return string|false
 */
function arkray_preserve_gallery_detail_polylang_canonical( $redirect_url ) {
	if ( '' !== arkray_get_gallery_detail_slug_from_request() || is_singular( 'gallery' ) ) {
		return false;
	}

	return $redirect_url;
}
add_filter( 'pll_check_canonical_url', 'arkray_preserve_gallery_detail_polylang_canonical', 10, 1 );

/**
 * Canonical iframe markup for Gallery detail pages whose imported content lost
 * its embed during sanitization (empty <div class="youtube"></div>).
 *
 * @return array<string,string> Gallery post slug => iframe HTML.
 */
function arkray_gallery_video_iframe_map() {
	return array(
		'corporate_introduction_video' => '<iframe width="650" height="366" class="tx" src="https://www.youtube.com/embed/g3PIKLR-Zog?rel=0&amp;autoplay=1" frameborder="0" allowfullscreen></iframe>',
		'kizakura'                     => '<iframe width="650" height="366" class="tx" src="https://www.youtube.com/embed/tpI_PqjPWa0?rel=0&amp;autoplay=1" frameborder="0" allowfullscreen></iframe>',
		'calbee'                       => '<iframe width="650" height="366" class="tx" src="https://www.youtube.com/embed/ywDDFBufF0g?rel=0&amp;autoplay=1" frameborder="0" allowfullscreen></iframe>',
		'ritto_training_center'        => '<iframe width="650" height="366" class="tx" src="https://www.youtube.com/embed/lg72Vv3s9Kk?rel=0&amp;autoplay=1" frameborder="0" allowfullscreen></iframe>',
		'kaiyukan'                     => '<iframe width="650" height="366" class="tx" src="https://www.youtube.com/embed/LFfWHvBAuCc?rel=0&amp;autoplay=1" frameborder="0" allowfullscreen></iframe>',
		'kyoto_laboratory'             => '<iframe width="650" height="366" class="tx" src="https://www.youtube.com/embed/AimotUBS15Q?rel=0&amp;autoplay=1" frameborder="0" allowfullscreen></iframe>',
		'yousuien'                     => '<iframe width="650" height="366" class="tx" src="https://www.youtube.com/embed/U7ypvBeEyeY?rel=0&amp;autoplay=1" frameborder="0" allowfullscreen></iframe>',
	);
}

/**
 * Render Gallery detail post content, restoring video embeds when missing.
 *
 * @param WP_Post $gallery Gallery post object.
 * @return string
 */
function arkray_render_gallery_content( $gallery ) {
	if ( ! ( $gallery instanceof WP_Post ) || 'gallery' !== $gallery->post_type ) {
		return '';
	}

	$html = (string) $gallery->post_content;
	$map  = arkray_gallery_video_iframe_map();
	$slug = $gallery->post_name;

	if ( empty( $map[ $slug ] ) ) {
		return $html;
	}

	if ( preg_match( '#<div class="youtube">\s*(?:<iframe\b|<object\b|<embed\b)#i', $html ) ) {
		return $html;
	}

	if ( preg_match( '#<div class="youtube"[^>]*>\s*</div>#i', $html ) ) {
		$iframe = $map[ $slug ];
		$html   = preg_replace(
			'#<div class="youtube"[^>]*>\s*</div>#i',
			'<div class="youtube">' . $iframe . '</div>',
			$html,
			1
		);
	}

	return $html;
}

/**
 * Flush Gallery rewrite rules once after introducing language-prefixed detail URLs.
 */
function arkray_flush_gallery_detail_rewrites_once() {
	if ( get_option( 'arkray_gallery_detail_rewrites_v4' ) ) {
		return;
	}
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	flush_rewrite_rules();
	update_option( 'arkray_gallery_detail_rewrites_v4', 1 );
}
add_action( 'admin_init', 'arkray_flush_gallery_detail_rewrites_once' );


function arkray_register_post_types() {
	register_post_type(
		'product',
		array(
			'labels'       => array(
				'name'          => __( 'Products', 'arkray' ),
				'singular_name' => __( 'Product', 'arkray' ),
				'add_new_item'  => __( 'Add New Product', 'arkray' ),
				'edit_item'     => __( 'Edit Product', 'arkray' ),
				'menu_name'     => __( 'Products', 'arkray' ),
			),
			'public'       => true,
			'has_archive'  => false,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-products',
			'supports'     => array( 'title', 'thumbnail', 'editor' ),
			'rewrite'      => array( 'slug' => 'products', 'with_front' => false ),
		)
	);

	register_post_type(
		'news',
		array(
			'labels'       => array(
				'name'          => __( 'News', 'arkray' ),
				'singular_name' => __( 'News Item', 'arkray' ),
				'add_new_item'  => __( 'Add News Item', 'arkray' ),
				'edit_item'     => __( 'Edit News Item', 'arkray' ),
				'menu_name'     => __( 'News', 'arkray' ),
			),
			'public'       => true,
			'has_archive'  => false,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-megaphone',
			'supports'     => array( 'title', 'thumbnail', 'editor', 'excerpt' ),
			'rewrite'      => array( 'slug' => 'news', 'with_front' => false ),
		)
	);

	register_post_type(
		'event',
		array(
			'labels'       => array(
				'name'          => __( 'Events', 'arkray' ),
				'singular_name' => __( 'Event', 'arkray' ),
				'add_new_item'  => __( 'Add New Event', 'arkray' ),
				'edit_item'     => __( 'Edit Event', 'arkray' ),
				'menu_name'     => __( 'Events', 'arkray' ),
			),
			'public'       => true,
			'has_archive'  => false,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-calendar-alt',
			'supports'     => array( 'title', 'thumbnail', 'editor', 'excerpt' ),
			'rewrite'      => array( 'slug' => 'events', 'with_front' => false ),
		)
	);

	register_post_type(
		'recruitment',
		array(
			'labels'       => array(
				'name'          => __( 'Recruitment', 'arkray' ),
				'singular_name' => __( 'Job Posting', 'arkray' ),
				'add_new_item'  => __( 'Add New Job', 'arkray' ),
				'edit_item'     => __( 'Edit Job', 'arkray' ),
				'menu_name'     => __( 'Recruitment', 'arkray' ),
			),
			'public'       => true,
			'has_archive'  => false,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-businessperson',
			'supports'     => array( 'title', 'editor' ),
			'rewrite'      => array( 'slug' => 'recruitment', 'with_front' => false ),
		)
	);
}
add_action( 'init', 'arkray_register_post_types' );

/**
 * Normalize an initiative title for matching imported SDGs index links.
 *
 * @param string $title Initiative title.
 * @return string
 */
function arkray_normalize_sdg_initiative_title( $title ) {
	$title = wp_strip_all_tags( (string) $title );
	for ( $i = 0; $i < 3; $i++ ) {
		$decoded = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( $decoded === $title ) {
			break;
		}
		$title = $decoded;
	}

	return strtolower( trim( (string) preg_replace( '/\s+/u', ' ', $title ) ) );
}

/**
 * Point selected SDGs index links at their canonical local pages.
 *
 * @param string $html Imported SDGs index HTML.
 * @return string
 */
function arkray_localize_sdg_initiative_links( $html ) {
	$title_overrides = array(
		arkray_normalize_sdg_initiative_title( 'Accelerating the introduction of the remote monitoring service' ) => arkray_home_url( '/news/2019_0708/' ),
		arkray_normalize_sdg_initiative_title( 'Promoting the internal initiatives to accommodate sexual minorities' ) => arkray_home_url( '/news/2026_0106/' ),
		arkray_normalize_sdg_initiative_title( 'Developing compliance guidelines' ) => arkray_home_url( '/about/action_guidelines/' ),
	);

	return (string) preg_replace_callback(
		'#<a\b([^>]*?)href=(["\'])(.*?)\2([^>]*)>(.*?)</a>#isu',
		static function ( $matches ) use ( $title_overrides ) {
			$title = arkray_normalize_sdg_initiative_title( $matches[5] );
			if ( ! isset( $title_overrides[ $title ] ) ) {
				return $matches[0];
			}

			return '<a' . $matches[1] . 'href=' . $matches[2] . esc_url( $title_overrides[ $title ] ) . $matches[2] . $matches[4] . '>' . $matches[5] . '</a>';
		},
		(string) $html
	);
}

/**
 * Build standard editor content for a recruitment post.
 *
 * @param string $intro    Job description summary.
 * @param array  $sections Recruitment detail sections.
 * @return string
 */
function arkray_build_recruitment_editor_content( $intro, $sections ) {
	$content = array();
	$intro   = trim( (string) $intro );

	if ( '' !== $intro ) {
		$content[] = '<h3>Job Description:</h3>';
		$content[] = '<p>' . esc_html( $intro ) . '</p>';
	}

	if ( is_array( $sections ) ) {
		foreach ( $sections as $section ) {
			$title = isset( $section['section_title'] ) ? trim( (string) $section['section_title'] ) : '';
			$body  = isset( $section['section_content'] ) ? trim( (string) $section['section_content'] ) : '';

			if ( '' === $title && '' === $body ) {
				continue;
			}
			if ( '' !== $title ) {
				$content[] = '<h3>' . esc_html( $title ) . '</h3>';
			}
			if ( '' !== $body ) {
				$content[] = wp_kses_post( $body );
			}
		}
	}

	return implode( "\n\n", $content );
}

/**
 * Copy legacy ACF recruitment data into an empty WordPress editor.
 * Existing editor content is never overwritten.
 */
function arkray_migrate_recruitment_content_to_editor() {
	if ( get_option( 'arkray_recruitment_editor_migrated_v1' ) ) {
		return;
	}

	$job_ids = get_posts(
		array(
			'post_type'      => 'recruitment',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	foreach ( $job_ids as $job_id ) {
		if ( '' !== trim( (string) get_post_field( 'post_content', $job_id, 'raw' ) ) ) {
			continue;
		}

		$content = arkray_build_recruitment_editor_content(
			get_post_meta( $job_id, 'job_intro', true ),
			get_post_meta( $job_id, 'job_sections', true )
		);

		if ( '' !== $content ) {
			wp_update_post(
				wp_slash(
					array(
						'ID'           => $job_id,
						'post_content' => $content,
					)
				)
			);
		}
	}

	update_option( 'arkray_recruitment_editor_migrated_v1', 1, false );
}
add_action( 'init', 'arkray_migrate_recruitment_content_to_editor', 20 );

/**
 * Canonical listing-page intros scraped from arkray.ph.
 *
 * @return array<string, string> Slug => intro sentence.
 */
function arkray_get_recruitment_intro_defaults() {
	return array(
		'finance-and-admin-head-1'        => '* Graduate of Bachelor of Science in Accountancy. Licensed CPA (Certified Public Accountant); With at least 3-4 years experience in managerial level gained in a manufacturing set-up.',
		'general-accounting-supervisor-1' => '* Graduate of Bachelor of Science in Accountancy; With at least 3 years experience in supervisory level gained in a manufacturing set-up.',
		'warehouse-team-leader-1'         => '* Graduate of Bachelor’s Degree of any Four-year Course; With at least 2 years experience in gained in a manufacturing set-up.',
	);
}

/**
 * Ensure known recruitment posts carry their listing intro text.
 */
function arkray_seed_recruitment_intros() {
	if ( get_option( 'arkray_recruitment_intros_seeded_v1' ) ) {
		return;
	}

	foreach ( arkray_get_recruitment_intro_defaults() as $slug => $intro ) {
		$post = get_page_by_path( $slug, OBJECT, 'recruitment' );
		if ( ! $post instanceof WP_Post ) {
			continue;
		}

		$existing = trim( (string) get_post_meta( $post->ID, 'job_intro', true ) );
		if ( '' !== $existing ) {
			continue;
		}

		update_post_meta( $post->ID, 'job_intro', $intro );
		if ( function_exists( 'update_field' ) ) {
			update_field( 'job_intro', $intro, $post->ID );
		}
	}

	update_option( 'arkray_recruitment_intros_seeded_v1', 1, false );
}
add_action( 'init', 'arkray_seed_recruitment_intros', 25 );

/**
 * Get the Job Description paragraph used on the recruitment listing.
 *
 * @param int $post_id Recruitment post ID.
 * @return string
 */
function arkray_get_recruitment_intro( $post_id ) {
	if ( function_exists( 'get_field' ) ) {
		$acf_intro = trim( (string) get_field( 'job_intro', $post_id ) );
		if ( '' !== $acf_intro ) {
			return $acf_intro;
		}
	}

	$meta_intro = trim( (string) get_post_meta( $post_id, 'job_intro', true ) );
	if ( '' !== $meta_intro ) {
		return $meta_intro;
	}

	$content = trim( (string) get_post_field( 'post_content', $post_id, 'raw' ) );
	if ( '' !== $content ) {
		if ( preg_match( '#<h[1-6][^>]*>\s*Job Description:?\s*</h[1-6]>\s*<p[^>]*>(.*?)</p>#is', $content, $match ) ) {
			$intro = trim( wp_strip_all_tags( $match[1] ) );
			if ( '' !== $intro ) {
				return $intro;
			}
		}
		if ( preg_match( '#<p[^>]*class=(["\'])bold\1[^>]*>\s*Job Description:?\s*</p>\s*(.*?)\s*<p#is', $content, $match ) ) {
			$intro = trim( wp_strip_all_tags( $match[2] ) );
			if ( '' !== $intro ) {
				return $intro;
			}
		}
		if ( preg_match( '#<p[^>]*>(.*?)</p>#is', $content, $match ) ) {
			$intro = trim( wp_strip_all_tags( $match[1] ) );
			if ( '' !== $intro && 'Job Description:' !== $intro ) {
				return $intro;
			}
		}
	}

	$slug = get_post_field( 'post_name', $post_id );
	$defaults = arkray_get_recruitment_intro_defaults();
	if ( isset( $defaults[ $slug ] ) ) {
		return $defaults[ $slug ];
	}

	return trim( (string) get_post_field( 'post_excerpt', $post_id, 'raw' ) );
}

/**
 * Register Taxonomies.
 */
function arkray_register_taxonomies() {
	register_taxonomy(
		'product_category',
		array( 'product' ),
		array(
			'labels'            => array(
				'name'          => __( 'Product Categories', 'arkray' ),
				'singular_name' => __( 'Product Category', 'arkray' ),
				'menu_name'     => __( 'Categories', 'arkray' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'product-category' ),
		)
	);

	register_taxonomy(
		'news_category',
		array( 'news' ),
		array(
			'labels'            => array(
				'name'          => __( 'News Categories', 'arkray' ),
				'singular_name' => __( 'News Category', 'arkray' ),
				'menu_name'     => __( 'Categories', 'arkray' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'news-category' ),
		)
	);

	register_taxonomy(
		'event_type',
		array( 'event' ),
		array(
			'labels'            => array(
				'name'          => __( 'Event Types', 'arkray' ),
				'singular_name' => __( 'Event Type', 'arkray' ),
				'menu_name'     => __( 'Types', 'arkray' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'event-type' ),
		)
	);
}
add_action( 'init', 'arkray_register_taxonomies' );

/**
 * Register Nav Menu locations.
 */
function arkray_register_nav_menus() {
	register_nav_menus(
		array(
			'primary_menu'  => __( 'Primary Navigation', 'arkray' ),
			'footer_menu'   => __( 'Footer Navigation', 'arkray' ),
			'products_menu' => __( 'Products Sidebar Menu', 'arkray' ),
		)
	);
}
add_action( 'after_setup_theme', 'arkray_register_nav_menus' );

// Register ?pcat= so WP doesn't strip it as an unrecognised query var.
add_filter( 'query_vars', function( $vars ) {
	$vars[] = 'pcat';
	return $vars;
} );

/**
 * On theme switch: flush rewrite rules so newly registered CPT/taxonomy
 * permalinks resolve. Does NOT touch site-wide permalink_structure — that
 * remains an explicit admin choice under Settings → Permalinks.
 */
function arkray_after_switch_theme() {
	arkray_register_post_types();
	arkray_register_taxonomies();
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'arkray_after_switch_theme' );

/**
 * Register `pcat` as a public query var so rewrite rules can populate it
 * and templates can read it via get_query_var() or $_GET.
 */
function arkray_register_pcat_query_var( $vars ) {
	$vars[] = 'pcat';
	return $vars;
}
add_filter( 'query_vars', 'arkray_register_pcat_query_var' );

/**
 * Map origin category paths to our category query var.
 *   /products/diabetes/                    -> ?page_id=products&pcat=laboratory-testing
 *   /products/diabetes/index.html          -> same
 *   /products/urinalysis/                  -> ?pcat=urinalysis
 *   /products/urinalysis_urine_testing/    -> ?pcat=urinalysis (legacy alias)
 *   /products/osmolality/                  -> ?pcat=clinical-chemistry-reagents
 *   etc.
 * Each entry: { origin_slug => wp_term_slug }
 */
function arkray_product_category_wp_slug_aliases() {
	return array(
		'near-patient-testing' => 'urinalysis',
	);
}

/**
 * Normalize legacy product_category term slugs to the canonical WP slug.
 *
 * @param string $wp_slug Raw taxonomy slug.
 * @return string
 */
function arkray_normalize_product_category_wp_slug( $wp_slug ) {
	$aliases = arkray_product_category_wp_slug_aliases();
	return isset( $aliases[ $wp_slug ] ) ? $aliases[ $wp_slug ] : $wp_slug;
}

/**
 * Legacy origin directory paths that resolve to the same category as the
 * canonical origin slug (e.g. /products/urinalysis_urine_testing/).
 *
 * @return array<string,string> origin_slug => wp_term_slug
 */
function arkray_origin_category_path_aliases() {
	return array(
		'urinalysis_urine_testing' => 'urinalysis',
	);
}

/**
 * Resolve a WP product_category slug to its canonical origin directory.
 *
 * @param string $wp_slug Taxonomy slug (aliases accepted).
 * @return string Origin slug or '' when unmapped.
 */
function arkray_wp_slug_to_origin_slug( $wp_slug ) {
	$wp_slug = arkray_normalize_product_category_wp_slug( $wp_slug );
	$map     = array_flip( arkray_origin_product_slug_map() );
	return isset( $map[ $wp_slug ] ) ? $map[ $wp_slug ] : '';
}

/**
 * Origin directory paths and their WP product_category term slugs for rewrites.
 *
 * @return array<string,string> origin_slug => wp_term_slug
 */
function arkray_origin_category_rewrite_routes() {
	$routes = arkray_origin_product_slug_map();
	foreach ( arkray_origin_category_path_aliases() as $origin => $term_slug ) {
		if ( ! isset( $routes[ $origin ] ) ) {
			$routes[ $origin ] = $term_slug;
		}
	}
	return $routes;
}

/**
 * Polylang language slug from the current request path, if present.
 *
 * @return string e.g. "english", or '' when not language-prefixed.
 */
function arkray_get_language_slug_from_request_path() {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
	if ( '' === $path ) {
		return '';
	}

	$lang_re = arkray_language_slugs_regex();
	if ( preg_match( '#/(?:^|/)(' . $lang_re . ')(/|$)#', $path, $matches ) ) {
		return sanitize_title( $matches[1] );
	}

	return '';
}

/**
 * Resolve the Products page ID for the current (or supplied) language.
 *
 * @param string $lang Optional Polylang slug.
 * @return int Products page ID, or 0 when unavailable.
 */
function arkray_get_products_page_id_for_request( $lang = '' ) {
	$products_page = get_page_by_path( 'products' );
	if ( ! $products_page ) {
		return 0;
	}

	if ( '' === $lang ) {
		if ( ! empty( $_REQUEST['lang'] ) ) {
			$lang = sanitize_title( wp_unslash( $_REQUEST['lang'] ) );
		} elseif ( function_exists( 'pll_current_language' ) ) {
			$lang = (string) pll_current_language( 'slug' );
		}
		if ( '' === $lang ) {
			$lang = arkray_get_language_slug_from_request_path();
		}
	}

	$page_id = (int) $products_page->ID;
	if ( '' !== $lang && function_exists( 'pll_get_post' ) ) {
		$translated = pll_get_post( $page_id, $lang );
		if ( $translated ) {
			return (int) $translated;
		}
	}

	return (int) arkray_pll_post_id( $products_page );
}

/**
 * Apply Products page + pcat context to a main query.
 *
 * @param WP_Query $wp_query  Main query.
 * @param string   $pcat_slug Product category slug.
 * @return bool True when context was applied.
 */
function arkray_apply_product_category_query_context( $wp_query, $pcat_slug ) {
	$page_id = arkray_get_products_page_id_for_request();
	if ( $page_id <= 0 || '' === $pcat_slug ) {
		return false;
	}

	$page = get_post( $page_id );
	if ( ! $page instanceof WP_Post ) {
		return false;
	}

	$wp_query->is_404            = false;
	$wp_query->is_page           = true;
	$wp_query->is_singular       = true;
	$wp_query->is_single         = false;
	$wp_query->is_archive        = false;
	$wp_query->queried_object    = $page;
	$wp_query->queried_object_id = $page_id;
	$wp_query->post              = $page;
	$wp_query->posts             = array( $page );
	$wp_query->post_count        = 1;
	$wp_query->found_posts       = 1;
	$wp_query->max_num_pages     = 1;
	$wp_query->set( 'page_id', $page_id );
	$wp_query->set( 'pcat', $pcat_slug );

	$lang = arkray_get_language_slug_from_request_path();
	if ( '' !== $lang ) {
		$wp_query->set( 'lang', $lang );
	}

	global $post;
	$post = $page;

	return true;
}

/**
 * Regex that matches only a product category index path for a given origin slug.
 *
 * Matches /products/{origin}/ and /products/{origin}/index.html but not nested
 * product detail URLs such as /products/{origin}/{product}/.
 *
 * @param string $origin Origin directory slug.
 * @return string PCRE pattern including delimiters.
 */
function arkray_product_category_index_path_pattern( $origin ) {
	return '#/products/' . preg_quote( $origin, '#' ) . '(?:/index\.html)?/?$#';
}

/**
 * Whether the current request targets a Products category index path only.
 *
 * @param string $path Optional path; defaults to the current request URI path.
 * @return bool
 */
function arkray_is_product_category_request_path( $path = '' ) {
	if ( '' === $path ) {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
	}

	if ( '' === $path || preg_match( '#/products/history/#', $path ) ) {
		return false;
	}

	foreach ( array_keys( arkray_origin_category_rewrite_routes() ) as $origin ) {
		if ( preg_match( arkray_product_category_index_path_pattern( $origin ), $path ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Whether the request path is a nested product detail URL under a category origin.
 *
 * @param string $path Optional path; defaults to the current request URI path.
 * @return bool
 */
function arkray_is_product_detail_request_path( $path = '' ) {
	if ( '' === $path ) {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
	}

	if ( '' === $path || preg_match( '#/products/history/#', $path ) ) {
		return false;
	}

	foreach ( array_keys( arkray_origin_category_rewrite_routes() ) as $origin ) {
		$origin_q = preg_quote( $origin, '#' );
		if ( preg_match( '#/products/' . $origin_q . '/([^/]+)(?:/|$)#', $path, $matches ) ) {
			if ( 'index.html' !== $matches[1] ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Product slug from a nested /products/... detail request path.
 *
 * @param string $path Optional path; defaults to the current request URI path.
 * @return string Product post_name or '' when not a detail URL.
 */
function arkray_get_product_slug_from_detail_request_path( $path = '' ) {
	if ( '' === $path ) {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
	}

	if ( '' === $path || preg_match( '#/products/history/#', $path ) ) {
		return '';
	}

	foreach ( array_keys( arkray_origin_category_rewrite_routes() ) as $origin ) {
		$origin_q = preg_quote( $origin, '#' );
		if ( preg_match( '#/products/' . $origin_q . '/(?:[^/]+/)?([^/]+)/?$#', $path, $matches ) ) {
			$slug = sanitize_title( $matches[1] );
			if ( 'index.html' !== $slug ) {
				return $slug;
			}
		}
	}

	if ( preg_match( '#/products/([^/]+)/?$#', $path, $matches ) ) {
		$slug = sanitize_title( $matches[1] );
		if ( isset( arkray_origin_category_rewrite_routes()[ $slug ] ) ) {
			return '';
		}
		return $slug;
	}

	return '';
}

/**
 * @param string $slug Product post_name.
 * @return WP_Post|null
 */
function arkray_get_product_post_by_slug( $slug ) {
	$slug = sanitize_title( $slug );
	if ( '' === $slug ) {
		return null;
	}

	$posts = get_posts(
		array(
			'post_type'              => 'product',
			'name'                   => $slug,
			'posts_per_page'         => 1,
			'post_status'            => 'publish',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	return ( ! empty( $posts[0] ) && $posts[0] instanceof WP_Post ) ? $posts[0] : null;
}

/**
 * Apply a single product query context for nested product detail URLs.
 *
 * @param WP_Query $wp_query     Main query.
 * @param string   $product_slug Product post_name.
 * @return bool
 */
function arkray_apply_product_detail_query_context( $wp_query, $product_slug ) {
	$product = arkray_get_product_post_by_slug( $product_slug );
	if ( ! $product ) {
		return false;
	}

	$wp_query->is_404             = false;
	$wp_query->is_single          = true;
	$wp_query->is_singular        = true;
	$wp_query->is_page            = false;
	$wp_query->is_archive         = false;
	$wp_query->queried_object     = $product;
	$wp_query->queried_object_id  = (int) $product->ID;
	$wp_query->post               = $product;
	$wp_query->posts              = array( $product );
	$wp_query->post_count         = 1;
	$wp_query->found_posts        = 1;
	$wp_query->max_num_pages      = 1;
	$wp_query->set( 'product', $product_slug );
	$wp_query->set( 'post_type', 'product' );
	$wp_query->set( 'name', $product_slug );

	$lang = arkray_get_language_slug_from_request_path();
	if ( '' !== $lang ) {
		$wp_query->set( 'lang', $lang );
	}

	global $post;
	$post = $product;

	return true;
}

/**
 * Detect a product category slug from the current request path.
 *
 * @return string Canonical WP term slug or '' when not a category URL.
 */
function arkray_get_product_category_slug_from_request_path() {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
	if ( '' === $path || preg_match( '#/products/history/#', $path ) ) {
		return '';
	}

	foreach ( arkray_origin_category_rewrite_routes() as $origin => $term_slug ) {
		if ( preg_match( arkray_product_category_index_path_pattern( $origin ), $path ) ) {
			return arkray_normalize_product_category_wp_slug( $term_slug );
		}
	}

	return '';
}

/**
 * Active product_category slug for the current request.
 *
 * @return string Canonical WP term slug or '' when viewing the products index.
 */
function arkray_get_request_product_category_slug() {
	$pcat = get_query_var( 'pcat' );
	if ( '' === $pcat && isset( $_GET['pcat'] ) ) {
		$pcat = sanitize_title( wp_unslash( $_GET['pcat'] ) );
	}
	if ( '' !== $pcat ) {
		return arkray_normalize_product_category_wp_slug( $pcat );
	}

	return arkray_get_product_category_slug_from_request_path();
}

/**
 * Ensure nested /products/{origin}/{slug}/ URLs resolve to the product CPT.
 *
 * @param WP $wp Current WordPress environment instance.
 */
function arkray_parse_product_detail_request( $wp ) {
	if ( ! ( $wp instanceof WP ) ) {
		return;
	}

	if ( ! empty( $wp->query_vars['pcat'] ) || arkray_is_product_category_request_path() ) {
		return;
	}

	$routes       = arkray_origin_category_rewrite_routes();
	$product_slug = isset( $wp->query_vars['product'] ) ? sanitize_title( (string) $wp->query_vars['product'] ) : '';

	if ( '' !== $product_slug && isset( $routes[ $product_slug ] ) && ! arkray_is_product_detail_request_path() ) {
		return;
	}

	if ( '' === $product_slug ) {
		$product_slug = arkray_get_product_slug_from_detail_request_path();
	}

	if ( '' === $product_slug || ! arkray_get_product_post_by_slug( $product_slug ) ) {
		return;
	}

	$lang = ! empty( $wp->query_vars['lang'] ) ? $wp->query_vars['lang'] : arkray_get_language_slug_from_request_path();

	$wp->query_vars['product']   = $product_slug;
	$wp->query_vars['post_type'] = 'product';
	$wp->query_vars['name']      = $product_slug;
	if ( '' !== $lang ) {
		$wp->query_vars['lang'] = $lang;
	}
	unset(
		$wp->query_vars['page_id'],
		$wp->query_vars['pagename'],
		$wp->query_vars['pcat'],
		$wp->query_vars['error']
	);
}
add_action( 'parse_request', 'arkray_parse_product_detail_request', 98 );

/**
 * Ensure /products/{origin}/ URLs resolve to the products page with ?pcat= even
 * when the generic language-prefixed product-detail rewrite matched first.
 *
 * @param WP $wp Current WordPress environment instance.
 */
function arkray_parse_product_category_request( $wp ) {
	if ( ! ( $wp instanceof WP ) ) {
		return;
	}

	if ( ! empty( $wp->query_vars['pcat'] ) ) {
		return;
	}

	// Nested product detail URLs must not be rewritten to the category index.
	if ( arkray_is_product_detail_request_path() ) {
		return;
	}

	$routes       = arkray_origin_category_rewrite_routes();
	$product_slug = isset( $wp->query_vars['product'] ) ? sanitize_title( (string) $wp->query_vars['product'] ) : '';
	$path_slug    = arkray_get_product_category_slug_from_request_path();

	if ( '' === $path_slug && ( '' === $product_slug || ! isset( $routes[ $product_slug ] ) ) ) {
		return;
	}

	$term_slug = '' !== $path_slug ? $path_slug : arkray_normalize_product_category_wp_slug( $routes[ $product_slug ] );
	$page_id   = arkray_get_products_page_id_for_request();
	if ( $page_id <= 0 ) {
		return;
	}

	$lang = ! empty( $wp->query_vars['lang'] ) ? $wp->query_vars['lang'] : arkray_get_language_slug_from_request_path();

	$wp->query_vars['page_id'] = $page_id;
	$wp->query_vars['pcat']    = $term_slug;
	if ( '' !== $lang ) {
		$wp->query_vars['lang'] = $lang;
	}
	unset(
		$wp->query_vars['product'],
		$wp->query_vars['name'],
		$wp->query_vars['error'],
		$wp->query_vars['pagename'],
		$wp->query_vars['attachment']
	);
}
add_action( 'parse_request', 'arkray_parse_product_category_request', 99 );

/**
 * Prevent 404 for product category origin URLs such as /products/urinalysis/.
 *
 * @param bool     $preempt  Whether to short-circuit 404 handling.
 * @param WP_Query $wp_query Main query instance.
 * @return bool
 */
function arkray_prevent_product_category_virtual_404( $preempt, $wp_query ) {
	if ( is_admin() || ! $wp_query->is_main_query() ) {
		return $preempt;
	}

	if ( arkray_is_product_detail_request_path() ) {
		return $preempt;
	}

	if ( ! empty( $wp_query->query_vars['product'] ) && ! arkray_is_product_category_request_path() ) {
		return $preempt;
	}

	if ( ! arkray_is_product_category_request_path() ) {
		return $preempt;
	}

	$pcat_slug = arkray_get_product_category_slug_from_request_path();
	if ( '' === $pcat_slug ) {
		return $preempt;
	}

	if ( arkray_apply_product_category_query_context( $wp_query, $pcat_slug ) ) {
		return true;
	}

	return $preempt;
}
add_filter( 'pre_handle_404', 'arkray_prevent_product_category_virtual_404', 12, 2 );

/**
 * Prevent 404 for nested product detail URLs under /products/{origin}/.
 *
 * @param bool     $preempt  Whether to short-circuit 404 handling.
 * @param WP_Query $wp_query Main query instance.
 * @return bool
 */
function arkray_prevent_product_detail_virtual_404( $preempt, $wp_query ) {
	if ( is_admin() || ! $wp_query->is_main_query() ) {
		return $preempt;
	}

	if ( arkray_is_product_category_request_path() ) {
		return $preempt;
	}

	$routes       = arkray_origin_category_rewrite_routes();
	$product_slug = ! empty( $wp_query->query_vars['product'] )
		? sanitize_title( (string) $wp_query->query_vars['product'] )
		: '';

	if ( '' !== $product_slug && isset( $routes[ $product_slug ] ) && ! arkray_is_product_detail_request_path() ) {
		return $preempt;
	}

	if ( '' === $product_slug ) {
		$product_slug = arkray_get_product_slug_from_detail_request_path();
	}

	if ( '' === $product_slug ) {
		return $preempt;
	}

	if ( arkray_apply_product_detail_query_context( $wp_query, $product_slug ) ) {
		return true;
	}

	return $preempt;
}
add_filter( 'pre_handle_404', 'arkray_prevent_product_detail_virtual_404', 13, 2 );

/**
 * Route product category origin URLs to the Products template.
 *
 * @param string $template Resolved template path.
 * @return string
 */
function arkray_route_virtual_product_category_template( $template ) {
	if ( is_admin() || is_singular( 'product' ) || arkray_is_product_detail_request_path() ) {
		return $template;
	}

	if ( ! arkray_is_product_category_request_path() ) {
		return $template;
	}

	$products_template = get_stylesheet_directory() . '/template-products.php';
	if ( file_exists( $products_template ) ) {
		return $products_template;
	}

	return $template;
}
add_filter( 'template_include', 'arkray_route_virtual_product_category_template', 96 );

/**
 * Route nested product detail URLs to single-product.php.
 *
 * @param string $template Resolved template path.
 * @return string
 */
function arkray_route_product_detail_template( $template ) {
	if ( is_admin() || is_singular( 'product' ) ) {
		return $template;
	}

	if ( ! arkray_is_product_detail_request_path() ) {
		return $template;
	}

	$single_template = get_stylesheet_directory() . '/single-product.php';
	if ( file_exists( $single_template ) ) {
		return $single_template;
	}

	return $template;
}
add_filter( 'template_include', 'arkray_route_product_detail_template', 95 );

function arkray_origin_product_slug_map() {
	return array(
		'diabetes'                     => 'laboratory-testing',
		'urinalysis'                   => 'urinalysis',
		'blood'                        => 'bgm',
		'others'                       => 'veterinary-others',
		'immunodiagnostic_products'    => 'immunodiagnostic-products',
		'osmolality'                   => 'clinical-chemistry-reagents',
		'primary_healthcare'           => 'primary-health-care',
	);
}

/**
 * Add rewrite rules so /products/{origin_slug}/(index.html)? maps to the
 * products page with the appropriate pcat query var.
 *
 * Both a bare form (/products/{origin}/) and a language-prefixed form
 * (/english/products/{origin}/, /vietnamese/products/{origin}/) are
 * registered. The language-prefixed variants are registered at priority 22 —
 * after arkray_add_language_cpt_rewrites() at 20 — so they take precedence over
 * the generic product-detail rule (^(english|vietnamese)/products/([^/]+)/?$)
 * and resolve to the category view instead of a product lookup.
 */
function arkray_add_origin_category_rewrites() {
	$products_page = get_page_by_path( 'products' );
	if ( ! $products_page ) {
		return;
	}
	$pid     = $products_page->ID;
	$lang_re = arkray_language_slugs_regex();

	foreach ( arkray_origin_category_rewrite_routes() as $origin => $term_slug ) {
		$origin_q = preg_quote( $origin, '#' );
		$pcat     = rawurlencode( $term_slug );
		$base     = 'index.php?page_id=' . $pid . '&pcat=' . $pcat;

		// Bare form (no language directory).
		add_rewrite_rule( '^products/' . $origin_q . '/index\.html$', $base, 'top' );
		add_rewrite_rule( '^products/' . $origin_q . '/?$',           $base, 'top' );

		// Language-prefixed form, e.g. /english/products/diabetes/.
		add_rewrite_rule( '^(' . $lang_re . ')/products/' . $origin_q . '/index\.html$', $base . '&lang=$matches[1]', 'top' );
		add_rewrite_rule( '^(' . $lang_re . ')/products/' . $origin_q . '/?$',           $base . '&lang=$matches[1]', 'top' );
	}
}
add_action( 'init', 'arkray_add_origin_category_rewrites', 22 );

/**
 * Resolve a product's origin category directory slug (e.g. "diabetes" for a
 * product in the "laboratory-testing" WP category). Returns the first category
 * that has an entry in arkray_origin_product_slug_map(); empty string when the
 * product has no mapped category.
 *
 * @param int $post_id Product post ID.
 * @return string Origin slug (e.g. "diabetes") or '' when none.
 */
function arkray_get_product_origin_slug( $post_id ) {
	$terms = get_the_terms( $post_id, 'product_category' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}
	foreach ( $terms as $term ) {
		$origin = arkray_wp_slug_to_origin_slug( $term->slug );
		if ( '' !== $origin ) {
			return $origin;
		}
	}
	return '';
}

/**
 * Add rewrite rules so product detail URLs nest under their category's origin
 * directory, e.g. /products/diabetes/ha-8190v/ and the language-prefixed
 * /english/products/diabetes/ha-8190v/.
 *
 * Registered AFTER arkray_add_origin_category_rewrites() (so the bare/index.html
 * category rules sit above these two-segment rules) and BEFORE
 * arkray_add_language_cpt_rewrites() (so these specific two-segment rules take
 * precedence over the generic single-segment product-detail rule). Within the
 * `extra_rules_top` group, precedence follows registration order.
 */
function arkray_add_product_category_detail_rewrites() {
	$lang_re = arkray_language_slugs_regex();

	foreach ( arkray_origin_product_slug_map() as $origin => $term_slug ) {
		$origin_q = preg_quote( $origin, '#' );

		// Bare form (no language directory): /products/{origin}/{product}/.
		add_rewrite_rule( '^products/' . $origin_q . '/([^/]+)/?$', 'index.php?product=$matches[1]', 'top' );

		// Language-prefixed form: /english/products/{origin}/{product}/.
		add_rewrite_rule( '^(' . $lang_re . ')/products/' . $origin_q . '/([^/]+)/?$', 'index.php?product=$matches[2]&lang=$matches[1]', 'top' );
	}
}
add_action( 'init', 'arkray_add_product_category_detail_rewrites', 20 );

/**
 * Products that live one directory level deeper than their category origin,
 * under an extra subsection segment. e.g. HA-8190V and HA-8380V are HbA1c
 * analyzers published at /products/diabetes/hba1c/{slug}/ instead of the flat
 * /products/diabetes/{slug}/.
 *
 * Keyed by product slug (post_name) => subsection segment. Pairs with
 * arkray_product_subdir_routes() which lists the {origin}/{subdir} routes for
 * rewrite-rule registration; keep the two in sync.
 *
 * @return array<string,string>
 */
function arkray_product_subdir_map() {
	return array(
		'ha-8190v'              => 'hba1c',
		'ha-8380v'              => 'hba1c',
		'glucocard_s'           => 'bgm',
		'pu-4010'               => 'urine_chemistry',
		'ax-4060'               => 'urine_chemistry',
		'ae-4070'               => 'urine_chemistry',
		'aution_eye_ai-4510'    => 'urine_sediment',
	);
}

/**
 * Category origin directory => list of subsection segments that nest product
 * detail pages one level deeper (see arkray_product_subdir_map()). Used to
 * register the /products/{origin}/{subdir}/{slug}/ rewrite rules.
 *
 * @return array<string, string[]>
 */
function arkray_product_subdir_routes() {
	return array(
		'diabetes'   => array( 'hba1c', 'bgm' ),
		'urinalysis' => array( 'urine_chemistry', 'urine_sediment' ),
	);
}

/**
 * Register rewrite rules so deeper product detail URLs nest under a subsection
 * directory, e.g. /products/diabetes/hba1c/ha-8190v/ and the language-prefixed
 * /english/products/diabetes/hba1c/ha-8190v/.
 *
 * These three-segment rules cannot be shadowed by the two-segment detail rules
 * in arkray_add_product_category_detail_rewrites() (the [^/]+ slug token never
 * matches the extra "/" segment), so registration order between them is not
 * significant. Registered at priority 20 alongside the other product rules.
 */
function arkray_add_product_subdir_detail_rewrites() {
	$lang_re = arkray_language_slugs_regex();

	foreach ( arkray_product_subdir_routes() as $origin => $subdirs ) {
		$origin_q = preg_quote( $origin, '#' );
		foreach ( (array) $subdirs as $subdir ) {
			$subdir_q = preg_quote( $subdir, '#' );

			// Bare form: /products/{origin}/{subdir}/{product}/.
			add_rewrite_rule(
				'^products/' . $origin_q . '/' . $subdir_q . '/([^/]+)/?$',
				'index.php?product=$matches[1]',
				'top'
			);

			// Language-prefixed form: /english/products/{origin}/{subdir}/{product}/.
			add_rewrite_rule(
				'^(' . $lang_re . ')/products/' . $origin_q . '/' . $subdir_q . '/([^/]+)/?$',
				'index.php?product=$matches[2]&lang=$matches[1]',
				'top'
			);
		}
	}
}
add_action( 'init', 'arkray_add_product_subdir_detail_rewrites', 20 );

/**
 * Map of CPT rewrite slug (URL base) => post type query var.
 *
 * These post types are language-neutral (shared across English/Vietnamese and
 * not registered as Polylang-translated), so their permalinks are normally
 * emitted WITHOUT a language directory. The whole rest of the site, however,
 * lives under /english/ or /vietnamese/, so a language-prefixed detail URL
 * such as /english/products/{slug}/ would otherwise fall through to Polylang's
 * generic page rule and 404. The helpers below make those prefixed URLs both
 * resolvable and the canonical, language-aware permalink.
 *
 * @return array<string,string>
 */
function arkray_language_cpt_slug_map() {
	return array(
		'products'    => 'product',
		'news'        => 'news',
		'events'      => 'event',
		'recruitment' => 'recruitment',
	);
}

/**
 * Polylang language slugs (e.g. "english", "vietnamese") as a regex alternation.
 *
 * @return string
 */
function arkray_language_slugs_regex() {
	$slugs = array();
	if ( function_exists( 'pll_languages_list' ) ) {
		$slugs = (array) pll_languages_list( array( 'fields' => 'slug' ) );
	}
	if ( empty( $slugs ) ) {
		$slugs = array( 'english', 'vietnamese' );
	}
	return implode( '|', array_map( function ( $slug ) {
		return preg_quote( (string) $slug, '#' );
	}, $slugs ) );
}

/**
 * Register rewrite rules so language-prefixed CPT detail URLs resolve to the
 * shared (language-neutral) post. Without these, /english/products/{slug}/ and
 * /vietnamese/products/{slug}/ 404 because the product/news/event/recruitment
 * post types have no Polylang language-prefixed rewrite rules of their own.
 *
 * The `lang` query var is set from the URL so the page chrome renders in the
 * matching language (Polylang string translations), even though the post
 * itself is not filtered by language.
 */
function arkray_add_language_cpt_rewrites() {
	$lang_re = arkray_language_slugs_regex();
	foreach ( arkray_language_cpt_slug_map() as $url_slug => $post_type ) {
		add_rewrite_rule(
			'^(' . $lang_re . ')/' . preg_quote( $url_slug, '#' ) . '/([^/]+)/?$',
			'index.php?' . $post_type . '=$matches[2]&lang=$matches[1]',
			'top'
		);
	}
}
add_action( 'init', 'arkray_add_language_cpt_rewrites', 20 );

/**
 * Prefix language-neutral CPT permalinks with the current language directory so
 * the site emits /english/products/{slug}/ or /vietnamese/products/{slug}/
 * instead of the bare /products/{slug}/. This keeps CPT detail links consistent
 * with the rest of the language-prefixed site and pairs with the rewrite rules
 * registered in arkray_add_language_cpt_rewrites().
 *
 * @param string  $permalink The post's permalink.
 * @param WP_Post $post      The post object.
 * @return string
 */
function arkray_language_prefix_cpt_permalink( $permalink, $post ) {
	if ( ! function_exists( 'PLL' ) ) {
		return $permalink;
	}
	if ( ! ( $post instanceof WP_Post ) || ! array_key_exists( $post->post_type, array_flip( arkray_language_cpt_slug_map() ) ) ) {
		return $permalink;
	}

	$pll = PLL();
	if ( empty( $pll ) || empty( $pll->curlang ) || empty( $pll->links_model ) ) {
		return $permalink;
	}
	if ( ! method_exists( $pll->links_model, 'add_language_to_link' ) ) {
		return $permalink;
	}

	// Avoid double-prefixing if a language directory is already present.
	$lang_re = arkray_language_slugs_regex();
	$path    = (string) wp_parse_url( $permalink, PHP_URL_PATH );
	$home    = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	$rel     = ltrim( preg_replace( '#^/' . preg_quote( $home, '#' ) . '#', '', $path ), '/' );
	if ( preg_match( '#^(' . $lang_re . ')/#', $rel ) ) {
		return $permalink;
	}

	return $pll->links_model->add_language_to_link( $permalink, $pll->curlang );
}
add_filter( 'post_type_link', 'arkray_language_prefix_cpt_permalink', 20, 2 );

/**
 * Nest product detail permalinks under their category's origin directory so the
 * emitted URL is /products/{origin}/{slug}/ (e.g. /english/products/diabetes/
 * ha-8190v/) instead of the flat /products/{slug}/. Pairs with the rewrite
 * rules registered in arkray_add_product_category_detail_rewrites().
 *
 * Runs at priority 25 — after arkray_language_prefix_cpt_permalink() (20) has
 * already added the language directory — and edits only the `/products/{slug}`
 * path segment, so it works whether or not a language prefix is present.
 *
 * @param string  $permalink The product permalink.
 * @param WP_Post $post      The post object.
 * @return string
 */
function arkray_product_category_permalink( $permalink, $post ) {
	if ( ! ( $post instanceof WP_Post ) || 'product' !== $post->post_type ) {
		return $permalink;
	}

	$origin = arkray_get_product_origin_slug( $post->ID );
	if ( '' === $origin ) {
		return $permalink;
	}

	// Already nested under an origin directory — nothing to do.
	if ( preg_match( '#/products/' . preg_quote( $origin, '#' ) . '/#', $permalink ) ) {
		return $permalink;
	}

	$slug = $post->post_name;
	if ( '' === $slug ) {
		return $permalink;
	}

	// Optional subsection directory (e.g. "hba1c") nests the product one level
	// deeper: /products/{origin}/{subdir}/{slug}/.
	$subdir_map = arkray_product_subdir_map();
	$subdir     = isset( $subdir_map[ $slug ] ) ? trim( (string) $subdir_map[ $slug ], '/' ) : '';
	$nested     = '/products/' . $origin . '/' . ( '' !== $subdir ? $subdir . '/' : '' ) . $slug . '/';

	// Insert the origin (and optional subsection) right after "products/", once.
	return preg_replace(
		'#/products/' . preg_quote( $slug, '#' ) . '(/|$)#',
		$nested,
		$permalink,
		1
	);
}
add_filter( 'post_type_link', 'arkray_product_category_permalink', 25, 2 );

/**
 * 301-redirect legacy flat product detail URLs to their nested subsection URL,
 * e.g. /products/diabetes/ha-8190v/ -> /products/diabetes/hba1c/ha-8190v/.
 *
 * The two-segment rewrite rule still resolves the legacy URL to the product, so
 * without this redirect both the old and new URLs would render the same page.
 * Redirecting keeps the nested URL canonical.
 */
function arkray_redirect_legacy_product_subdir() {
	if ( is_admin() || ! is_singular( 'product' ) || empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}

	$post = get_queried_object();
	if ( ! ( $post instanceof WP_Post ) ) {
		return;
	}

	$subdir_map = arkray_product_subdir_map();
	if ( empty( $subdir_map[ $post->post_name ] ) ) {
		return;
	}

	$subdir = trim( (string) $subdir_map[ $post->post_name ], '/' );
	if ( '' === $subdir ) {
		return;
	}

	$request_path = '/' . trim( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ), '/' ) . '/';

	// Already nested under the subsection segment — nothing to do.
	if ( false !== strpos( $request_path, '/' . $subdir . '/' . $post->post_name . '/' ) ) {
		return;
	}

	$target = get_permalink( $post );
	if ( $target ) {
		wp_safe_redirect( $target, 301 );
		exit;
	}
}
add_action( 'template_redirect', 'arkray_redirect_legacy_product_subdir' );

/**
 * Flush rewrite rules once so the language-prefixed CPT rules above take effect
 * without requiring a manual Settings → Permalinks save. Re-run by deleting the
 * `arkray_language_cpt_rewrites_v1` option.
 */
function arkray_flush_language_cpt_rewrites_once() {
	if ( get_option( 'arkray_language_cpt_rewrites_v1' ) ) {
		return;
	}
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	flush_rewrite_rules();
	update_option( 'arkray_language_cpt_rewrites_v1', 1 );
}
add_action( 'admin_init', 'arkray_flush_language_cpt_rewrites_once' );

/**
 * Resolve a product_category WP slug (e.g. "urinalysis") to the
 * verbatim-original URL on our site (e.g. "/products/urinalysis/").
 * Falls back to the products page when no mapping exists (e.g. for "oral-care"
 * which is external — caller should override before falling back).
 */
function arkray_get_product_category_url( $wp_slug ) {
	$products_page_url = arkray_pll_permalink( get_page_by_path( 'products' ) ) ?: arkray_home_url( '/products/' );
	$origin            = arkray_wp_slug_to_origin_slug( $wp_slug );
	if ( '' !== $origin ) {
		// Language-aware pretty directory, e.g. /english/products/diabetes/.
		// Built off the (language-prefixed) products page permalink so the
		// current language directory is preserved and the URL resolves via
		// the rewrite rules in arkray_add_origin_category_rewrites().
		return trailingslashit( $products_page_url ) . $origin . '/';
	}
	return $products_page_url;
}

/**
 * Redirect legacy product category origin paths to their canonical URLs.
 */
function arkray_redirect_legacy_product_category_origin_paths() {
	if ( is_admin() || empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}

	$legacy_origins = array(
		'urinalysis_urine_testing' => 'urinalysis',
	);

	$request_path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
	foreach ( $legacy_origins as $legacy => $canonical ) {
		if ( ! preg_match( '#/products/' . preg_quote( $legacy, '#' ) . '(/|$)#', $request_path ) ) {
			continue;
		}

		$new_path = preg_replace(
			'#/(products/)' . preg_quote( $legacy, '#' ) . '#',
			'/$1' . $canonical,
			$request_path,
			1
		);
		$dest = home_url( $new_path );
		$query = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_QUERY );
		if ( '' !== $query ) {
			$dest .= ( false === strpos( $dest, '?' ) ? '?' : '&' ) . $query;
		}

		wp_safe_redirect( $dest, 301 );
		exit;
	}
}
add_action( 'template_redirect', 'arkray_redirect_legacy_product_category_origin_paths', 4 );

/**
 * Redirect legacy ?pcat= query-string category URLs to their pretty origin path.
 */
function arkray_redirect_pcat_query_to_origin_url() {
	$pcat = get_query_var( 'pcat' );
	if ( '' === $pcat && isset( $_GET['pcat'] ) ) {
		$pcat = sanitize_title( wp_unslash( $_GET['pcat'] ) );
	}
	if ( '' === $pcat ) {
		return;
	}

	$products_page = get_page_by_path( 'products' );
	if ( ! $products_page || ! is_page( $products_page->ID ) ) {
		return;
	}

	$products_page_url = arkray_pll_permalink( $products_page ) ?: arkray_home_url( '/products/' );
	$dest              = arkray_get_product_category_url( $pcat );
	if ( untrailingslashit( $dest ) === untrailingslashit( $products_page_url ) ) {
		return;
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$dest_path   = (string) wp_parse_url( $dest, PHP_URL_PATH );
	if ( '' !== $dest_path && false !== strpos( $request_uri, untrailingslashit( $dest_path ) ) ) {
		return;
	}

	wp_safe_redirect( $dest, 301 );
	exit;
}
add_action( 'template_redirect', 'arkray_redirect_pcat_query_to_origin_url', 5 );

/**
 * Redirect the default WP taxonomy archive (/product-category/{slug}/) to the
 * verbatim-original origin URL. Without this redirect, those archive URLs
 * render via the theme's archive.php which has no styling for product
 * categories.
 */
function arkray_redirect_product_category_archive() {
	if ( ! is_tax( 'product_category' ) ) {
		return;
	}
	$term = get_queried_object();
	if ( ! $term || empty( $term->slug ) ) {
		return;
	}
	$external_cat_links = array(
		'oral-care' => 'http://arkraydental.com/',
	);
	$dest = isset( $external_cat_links[ $term->slug ] )
		? $external_cat_links[ $term->slug ]
		: arkray_get_product_category_url( $term->slug );
	if ( $dest ) {
		// wp_redirect (not wp_safe_redirect) so external destinations like
		// http://arkraydental.com/ for oral-care aren't blocked.
		wp_redirect( $dest, 301 );
		exit;
	}
}
add_action( 'template_redirect', 'arkray_redirect_product_category_archive' );

/**
 * Redirect news_category and event_type taxonomy archives to their parent
 * landing pages — those WP taxonomy archives have no styled template and
 * would otherwise leak raw markup.
 */
function arkray_redirect_other_taxonomy_archives() {
	if ( is_tax( 'news_category' ) ) {
		$news_url = arkray_pll_permalink( get_page_by_path( 'news' ) ) ?: arkray_home_url( '/news/' );
		wp_redirect( $news_url, 301 );
		exit;
	}
	if ( is_tax( 'event_type' ) ) {
		$events_url = arkray_get_events_gallery_page_url();
		wp_redirect( $events_url, 301 );
		exit;
	}
}
add_action( 'template_redirect', 'arkray_redirect_other_taxonomy_archives' );

/**
 * 301-redirect the legacy /news-topics/ slug to the renamed /news/ landing page
 * so old links (and any language-prefixed variants) keep resolving.
 */
function arkray_redirect_legacy_news_topics() {
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	if ( '' === $path ) {
		return;
	}
	$segments = explode( '/', $path );
	if ( 'news-topics' !== end( $segments ) ) {
		return;
	}
	$news_url = arkray_pll_permalink( get_page_by_path( 'news' ) ) ?: arkray_home_url( '/news/' );
	wp_redirect( $news_url, 301 );
	exit;
}
add_action( 'template_redirect', 'arkray_redirect_legacy_news_topics' );

/**
 * Redirect unresolved front-end requests to the language-aware landing page.
 *
 * Run after the more specific legacy redirects above so known moved URLs keep
 * their canonical destinations. A temporary redirect prevents browsers and
 * search engines from permanently caching a mistyped URL.
 */
function arkray_redirect_404_to_landing_page() {
	if ( ! is_404() ) {
		return;
	}

	wp_safe_redirect( arkray_home_url( '/' ), 302 );
	exit;
}
add_action( 'template_redirect', 'arkray_redirect_404_to_landing_page', 100 );

/**
 * Ensure the rewrite rules above survive across deploys by flushing once
 * after this set of rules is registered.
 */
function arkray_flush_origin_rewrites_once() {
	if ( get_option( 'arkray_origin_rewrites_v5' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	flush_rewrite_rules();
	update_option( 'arkray_origin_rewrites_v5', 1 );
}
add_action( 'admin_init', 'arkray_flush_origin_rewrites_once' );

/**
 * Prevent WordPress from redirecting our origin-style /products/{slug}/index.html
 * URLs to a trailing-slash variant. WP's redirect_canonical adds a slash to any
 * non-trailing-slash URL, which breaks the .html extension we want to preserve.
 */
function arkray_disable_canonical_for_origin_urls( $redirect_url, $requested_url ) {
	if ( preg_match( '#/products/([a-z_]+)/index\.html$#', $requested_url ) ) {
		return false;
	}

	$path = (string) wp_parse_url( $requested_url, PHP_URL_PATH );
	$origins = array_merge(
		array_keys( arkray_origin_product_slug_map() ),
		array_keys( arkray_origin_category_path_aliases() )
	);
	foreach ( $origins as $origin ) {
		if ( preg_match( '#/products/' . preg_quote( $origin, '#' ) . '/?$#', $path ) ) {
			return false;
		}
	}

	return $redirect_url;
}
add_filter( 'redirect_canonical', 'arkray_disable_canonical_for_origin_urls', 10, 2 );

/**
 * Flush rewrite rules once so the nested product-detail rules
 * (/products/{origin}/{slug}/) registered in
 * arkray_add_product_category_detail_rewrites() take effect after deploy.
 * Re-run by deleting the `arkray_product_category_detail_rewrites_v1` option.
 */
function arkray_flush_product_category_detail_rewrites_once() {
	if ( get_option( 'arkray_product_category_detail_rewrites_v1' ) ) {
		return;
	}
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	flush_rewrite_rules();
	update_option( 'arkray_product_category_detail_rewrites_v1', 1 );
}
add_action( 'admin_init', 'arkray_flush_product_category_detail_rewrites_once' );

/**
 * Flush rewrite rules once so the nested subsection product-detail rules
 * (/products/{origin}/{subdir}/{slug}/) registered in
 * arkray_add_product_subdir_detail_rewrites() take effect after deploy.
 * Re-run by deleting the `arkray_product_subdir_rewrites_v2` option.
 */
function arkray_flush_product_subdir_rewrites_once() {
	if ( get_option( 'arkray_product_subdir_rewrites_v2' ) ) {
		return;
	}
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	flush_rewrite_rules();
	update_option( 'arkray_product_subdir_rewrites_v2', 1 );
}
add_action( 'admin_init', 'arkray_flush_product_subdir_rewrites_once' );

/**
 * Admin-only one-time flush guard. Runs once on the next admin request after
 * deploy (when the theme was already active and after_switch_theme had
 * already fired) so CPT archives resolve without requiring a manual visit to
 * Settings → Permalinks. Never runs on frontend; requires a capable user.
 */
function arkray_phase1_one_time_flush() {
	if ( get_option( 'arkray_phase1_flushed' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	flush_rewrite_rules();
	update_option( 'arkray_phase1_flushed', 1 );
}
add_action( 'admin_init', 'arkray_phase1_one_time_flush' );

/**
 * One-time activation of bundled ACF plugin if present and not yet active.
 * Admin-only, capability-gated, and logs activation failures.
 */
function arkray_maybe_activate_acf() {
	if ( ! is_admin() || class_exists( 'ACF' ) ) {
		return;
	}

	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$acf_plugin  = 'advanced-custom-fields/acf.php';
	$plugin_file = WP_PLUGIN_DIR . '/' . $acf_plugin;

	if ( ! file_exists( $plugin_file ) ) {
		return;
	}

	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( is_plugin_active( $acf_plugin ) ) {
		return;
	}

	$result = activate_plugin( $acf_plugin );
	if ( is_wp_error( $result ) ) {
		error_log( '[arkray] ACF activation failed: ' . $result->get_error_message() );
	}
}
add_action( 'admin_init', 'arkray_maybe_activate_acf' );

/**
 * Register ACF Field Groups via code (DB-independent).
 */
function arkray_register_acf_field_groups() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	// Product Fields.
	acf_add_local_field_group(
		array(
			'key'      => 'group_product_fields',
			'title'    => 'Product Fields',
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'product',
					),
				),
			),
			'fields'   => array(
				array(
					'key'           => 'field_product_category',
					'label'         => 'Product Category',
					'name'          => 'product_category',
					'type'          => 'taxonomy',
					'taxonomy'      => 'product_category',
					'field_type'    => 'select',
					'add_term'      => 1,
					'save_terms'    => 1,
					'load_terms'    => 1,
					'return_format' => 'id',
				),
				array(
					'key'           => 'field_product_image_gallery',
					'label'         => 'Image Gallery',
					'name'          => 'product_image_gallery',
					'type'          => 'gallery',
					'return_format' => 'array',
				),
				array(
					'key'   => 'field_product_subtitle',
					'label' => 'Subtitle',
					'name'  => 'product_subtitle',
					'type'  => 'text',
				),
				array(
					'key'          => 'field_product_specs',
					'label'        => 'Specifications',
					'name'         => 'product_specs',
					'type'         => 'repeater',
					'button_label' => 'Add Specification',
					'sub_fields'   => array(
						array(
							'key'   => 'field_product_spec_label',
							'label' => 'Spec Label',
							'name'  => 'spec_label',
							'type'  => 'text',
						),
						array(
							'key'   => 'field_product_spec_value',
							'label' => 'Spec Value',
							'name'  => 'spec_value',
							'type'  => 'text',
						),
					),
				),
				array(
					'key'   => 'field_product_features',
					'label' => 'Features',
					'name'  => 'product_features',
					'type'  => 'textarea',
				),
				array(
					'key'   => 'field_product_brochure_url',
					'label' => 'Brochure URL',
					'name'  => 'product_brochure_url',
					'type'  => 'url',
				),
				array(
					'key'          => 'field_product_section_title',
					'label'        => 'Section Title',
					'name'         => 'product_section_title',
					'type'         => 'text',
					'instructions' => 'Groups products within a category, e.g. "HbA1c Aid to Diagnosis&Monitoring".',
				),
			),
		)
	);

	// Product Category (taxonomy) Fields.
	acf_add_local_field_group(
		array(
			'key'      => 'group_product_category_fields',
			'title'    => 'Product Category Fields',
			'location' => array(
				array(
					array(
						'param'    => 'taxonomy',
						'operator' => '==',
						'value'    => 'product_category',
					),
				),
			),
			'fields'   => array(
				array(
					'key'           => 'field_product_cat_image',
					'label'         => 'Category Image',
					'name'          => 'category_image',
					'type'          => 'image',
					'return_format' => 'url',
				),
			),
		)
	);

	// News Fields.
	acf_add_local_field_group(
		array(
			'key'      => 'group_news_fields',
			'title'    => 'News Fields',
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'news',
					),
				),
			),
			'fields'   => array(
				array(
					'key'            => 'field_news_date',
					'label'          => 'News Date',
					'name'           => 'news_date',
					'type'           => 'date_picker',
					'display_format' => 'Y-m-d',
					'return_format'  => 'Y-m-d',
				),
				array(
					'key'   => 'field_news_category_label',
					'label' => 'Category Label (display override)',
					'name'  => 'news_category_label',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_news_external_url',
					'label' => 'External URL (optional)',
					'name'  => 'news_external_url',
					'type'  => 'url',
				),
			),
		)
	);

	// Event Fields.
	acf_add_local_field_group(
		array(
			'key'      => 'group_event_fields',
			'title'    => 'Event Fields',
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'event',
					),
				),
			),
			'fields'   => array(
				array(
					'key'            => 'field_event_date',
					'label'          => 'Event Date',
					'name'           => 'event_date',
					'type'           => 'date_picker',
					'display_format' => 'Y-m-d',
					'return_format'  => 'Y-m-d',
				),
				array(
					'key'   => 'field_event_location',
					'label' => 'Location',
					'name'  => 'event_location',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_event_type_label',
					'label' => 'Event Type Label',
					'name'  => 'event_type_label',
					'type'  => 'text',
				),
				array(
					'key'           => 'field_event_image_gallery',
					'label'         => 'Image Gallery',
					'name'          => 'event_image_gallery',
					'type'          => 'gallery',
					'return_format' => 'array',
				),
				array(
					'key'   => 'field_event_external_url',
					'label' => 'External URL',
					'name'  => 'event_external_url',
					'type'  => 'url',
				),
				array(
					'key'          => 'field_event_info_rows',
					'label'        => 'Information Rows',
					'name'         => 'event_info_rows',
					'type'         => 'repeater',
					'button_label' => 'Add Row',
					'instructions' => 'Each row becomes a line in the detail-page Information table. Put one value per line in the value field.',
					'sub_fields'   => array(
						array(
							'key'   => 'field_event_info_label',
							'label' => 'Label',
							'name'  => 'info_label',
							'type'  => 'text',
						),
						array(
							'key'   => 'field_event_info_value',
							'label' => 'Value (one per line)',
							'name'  => 'info_value',
							'type'  => 'textarea',
						),
					),
				),
				array(
					'key'          => 'field_event_related_products',
					'label'        => 'Related Products',
					'name'         => 'event_related_products',
					'type'         => 'textarea',
					'instructions' => 'One product name per line.',
				),
			),
		)
	);

	// About page Fields (editorial text overrides — applied to pages using
	// the About template).
	acf_add_local_field_group(
		array(
			'key'      => 'group_about_page_fields',
			'title'    => 'About Page Fields',
			'location' => array(
				array(
					array(
						'param'    => 'page_template',
						'operator' => '==',
						'value'    => 'template-about-us.php',
					),
				),
			),
			'fields'   => array(
				array(
					'key'           => 'field_about_hero_image',
					'label'         => 'Hero / Banner Image',
					'name'          => 'about_hero_image',
					'type'          => 'image',
					'return_format' => 'url',
				),
				array(
					'key'   => 'field_about_intro_text',
					'label' => 'Intro Text',
					'name'  => 'about_intro_text',
					'type'  => 'wysiwyg',
				),
				array(
					'key'   => 'field_about_secondary_text',
					'label' => 'Secondary Text',
					'name'  => 'about_secondary_text',
					'type'  => 'wysiwyg',
				),
				array(
					'key'   => 'field_about_manager_name',
					'label' => 'Manager Name (Message page)',
					'name'  => 'about_manager_name',
					'type'  => 'text',
				),
				array(
					'key'           => 'field_about_manager_photo',
					'label'         => 'Manager Photo',
					'name'          => 'about_manager_photo',
					'type'          => 'image',
					'return_format' => 'url',
				),
				array(
					'key'           => 'field_about_pdf_url',
					'label'         => 'Company Profile PDF',
					'name'          => 'about_pdf_url',
					'type'          => 'file',
					'return_format' => 'url',
				),
				array(
					'key'          => 'field_about_landing_cards',
					'label'        => 'Landing Cards (About index)',
					'name'         => 'about_landing_cards',
					'type'         => 'repeater',
					'button_label' => 'Add Card',
					'instructions' => 'Override the About landing-page card images. Card Key must match the routing slug (e.g. "arkray-philosophy", "message-from-arkray", "brand-concept", "about-contact", "corporate-outline", "history", "arkray-group").',
					'sub_fields'   => array(
						array(
							'key'   => 'field_about_card_key',
							'label' => 'Card Key (routing slug)',
							'name'  => 'card_key',
							'type'  => 'text',
						),
						array(
							'key'           => 'field_about_card_image',
							'label'         => 'Card Image',
							'name'          => 'card_image',
							'type'          => 'image',
							'return_format' => 'url',
						),
					),
				),
			),
		)
	);

	// Site Settings (ACF options page) — site-wide gallery sections, etc.
	acf_add_local_field_group(
		array(
			'key'      => 'group_site_settings',
			'title'    => 'Site Settings',
			'location' => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => 'arkray-site-settings',
					),
				),
			),
			'fields'   => array(
				array(
					'key'          => 'field_site_gallery_sections',
					'label'        => 'Events & Gallery — Sections',
					'name'         => 'gallery_sections',
					'type'         => 'repeater',
					'button_label' => 'Add Section',
					'instructions' => 'Each row is a gallery section on the Events & Gallery page (Media Gallery tab).',
					'sub_fields'   => array(
						array(
							'key'   => 'field_site_gallery_section_title',
							'label' => 'Section title',
							'name'  => 'section_title',
							'type'  => 'text',
						),
						array(
							'key'          => 'field_site_gallery_section_items',
							'label'        => 'Items',
							'name'         => 'section_items',
							'type'         => 'repeater',
							'button_label' => 'Add Item',
							'sub_fields'   => array(
								array(
									'key'   => 'field_site_gallery_item_title',
									'label' => 'Item title',
									'name'  => 'item_title',
									'type'  => 'text',
								),
								array(
									'key'   => 'field_site_gallery_item_excerpt',
									'label' => 'Item excerpt',
									'name'  => 'item_excerpt',
									'type'  => 'textarea',
								),
								array(
									'key'           => 'field_site_gallery_item_image',
									'label'         => 'Item image',
									'name'          => 'item_image',
									'type'          => 'image',
									'return_format' => 'url',
								),
								array(
									'key'   => 'field_site_gallery_item_link',
									'label' => 'Item link',
									'name'  => 'item_link',
									'type'  => 'url',
								),
							),
						),
					),
				),
			),
		)
	);

	// About — Company History timeline (separate group so editors can find
	// timeline data quickly, still scoped to template-about-us.php pages).
	acf_add_local_field_group(
		array(
			'key'      => 'group_about_history_fields',
			'title'    => 'About Page — Company History Timeline',
			'location' => array(
				array(
					array(
						'param'    => 'page_template',
						'operator' => '==',
						'value'    => 'template-about-us.php',
					),
				),
			),
			'fields'   => array(
				array(
					'key'          => 'field_about_timeline',
					'label'        => 'Decades',
					'name'         => 'about_timeline',
					'type'         => 'repeater',
					'button_label' => 'Add Decade',
					'instructions' => 'Each row is a decade tab on the History page.',
					'sub_fields'   => array(
						array(
							'key'   => 'field_about_decade',
							'label' => 'Decade label',
							'name'  => 'decade',
							'type'  => 'text',
							'instructions' => 'e.g. "1960", "1970"',
						),
						array(
							'key'          => 'field_about_company_entries',
							'label'        => 'Company entries',
							'name'         => 'company_entries',
							'type'         => 'repeater',
							'button_label' => 'Add Entry',
							'sub_fields'   => array(
								array(
									'key'   => 'field_about_company_date',
									'label' => 'Date',
									'name'  => 'entry_date',
									'type'  => 'text',
								),
								array(
									'key'   => 'field_about_company_text',
									'label' => 'Text',
									'name'  => 'entry_text',
									'type'  => 'textarea',
								),
							),
						),
						array(
							'key'          => 'field_about_product_entries',
							'label'        => 'Product entries',
							'name'         => 'product_entries',
							'type'         => 'repeater',
							'button_label' => 'Add Entry',
							'sub_fields'   => array(
								array(
									'key'   => 'field_about_product_date',
									'label' => 'Date',
									'name'  => 'entry_date',
									'type'  => 'text',
								),
								array(
									'key'   => 'field_about_product_text',
									'label' => 'Text',
									'name'  => 'entry_text',
									'type'  => 'textarea',
								),
							),
						),
						array(
							'key'           => 'field_about_decade_image',
							'label'         => 'Decade image',
							'name'          => 'decade_image',
							'type'          => 'image',
							'return_format' => 'url',
						),
					),
				),
			),
		)
	);

	// Sustainability page Fields.
	acf_add_local_field_group(
		array(
			'key'      => 'group_sustainability_page_fields',
			'title'    => 'Sustainability Page Fields',
			'location' => array(
				array(
					array(
						'param'    => 'page_template',
						'operator' => '==',
						'value'    => 'template-sustainability.php',
					),
				),
			),
			'fields'   => array(
				array(
					'key'           => 'field_sus_hero_image',
					'label'         => 'Hero Image',
					'name'          => 'sus_hero_image',
					'type'          => 'image',
					'return_format' => 'url',
				),
				array(
					'key'   => 'field_sus_intro_text',
					'label' => 'Intro Text',
					'name'  => 'sus_intro_text',
					'type'  => 'wysiwyg',
				),
				array(
					'key'   => 'field_sus_body_text',
					'label' => 'Body Text',
					'name'  => 'sus_body_text',
					'type'  => 'wysiwyg',
				),
				array(
					'key'   => 'field_sus_signatory',
					'label' => 'Signatory (Top Commitment page)',
					'name'  => 'sus_signatory',
					'type'  => 'text',
				),
				array(
					'key'          => 'field_sus_sdgs_policies',
					'label'        => 'SDGs Activity Policies',
					'name'         => 'sus_sdgs_policies',
					'type'         => 'textarea',
					'instructions' => 'One bullet point per line. Used on the SDGs Basic Policy page.',
				),
				array(
					'key'          => 'field_sus_initiatives_content',
					'label'        => 'SDGs Initiatives Content',
					'name'         => 'sus_initiatives_content',
					'type'         => 'wysiwyg',
					'instructions' => 'Full HTML block for the SDGs Initiatives page. Replaces the hardcoded initiative blocks when set.',
				),
			),
		)
	);

	// History of Pioneers page Fields.
	acf_add_local_field_group(
		array(
			'key'      => 'group_history_page_fields',
			'title'    => 'History of Pioneers Page Fields',
			'location' => array(
				array(
					array(
						'param'    => 'page_template',
						'operator' => '==',
						'value'    => 'template-history-of-pioneers.php',
					),
				),
			),
			'fields'   => array(
				array(
					'key'           => 'field_hop_hero_image',
					'label'         => 'Hero Image',
					'name'          => 'hop_hero_image',
					'type'          => 'image',
					'return_format' => 'url',
				),
				array(
					'key'   => 'field_hop_intro_text',
					'label' => 'Intro Text',
					'name'  => 'hop_intro_text',
					'type'  => 'wysiwyg',
				),
				array(
					'key'          => 'field_hop_diabetes_tab1',
					'label'        => 'Diabetes Testing — Tab 1',
					'name'         => 'hop_diabetes_tab1',
					'type'         => 'wysiwyg',
					'instructions' => 'Renders on /history-of-pioneers/diabetes-testing/?tab=1',
				),
				array(
					'key'          => 'field_hop_diabetes_tab2',
					'label'        => 'Diabetes Testing — Tab 2',
					'name'         => 'hop_diabetes_tab2',
					'type'         => 'wysiwyg',
					'instructions' => 'Renders on /history-of-pioneers/diabetes-testing/?tab=2',
				),
				array(
					'key'          => 'field_hop_urinalysis_body',
					'label'        => 'Urinalysis — Full body',
					'name'         => 'hop_urinalysis_body',
					'type'         => 'wysiwyg',
					'instructions' => 'Renders on /history-of-pioneers/urinalysis/',
				),
				array(
					'key'          => 'field_hop_dry_ez_tab',
					'label'        => 'Dry Chemistry — SPOTCHEM EZ tab',
					'name'         => 'hop_dry_ez_tab',
					'type'         => 'wysiwyg',
					'instructions' => 'Renders on /history-of-pioneers/dry-chemistry-testing/?tab=ez',
				),
				array(
					'key'          => 'field_hop_dry_dconcept_tab',
					'label'        => 'Dry Chemistry — D-Concept tab',
					'name'         => 'hop_dry_dconcept_tab',
					'type'         => 'wysiwyg',
					'instructions' => 'Renders on /history-of-pioneers/dry-chemistry-testing/?tab=d-concept',
				),
				array(
					'key'          => 'field_hop_bgm_body',
					'label'        => 'BGM — Full body',
					'name'         => 'hop_bgm_body',
					'type'         => 'wysiwyg',
					'instructions' => 'Renders on /history-of-pioneers/bgm/',
				),
			),
		)
	);

}
add_action( 'acf/init', 'arkray_register_acf_field_groups' );

/**
 * Register an ACF options page for site-wide settings (gallery sections, etc.).
 */
function arkray_register_acf_options_page() {
	if ( function_exists( 'acf_add_options_page' ) ) {
		acf_add_options_page( array(
			'page_title' => 'Site Settings',
			'menu_title' => 'Site Settings',
			'menu_slug'  => 'arkray-site-settings',
			'capability' => 'manage_options',
			'redirect'   => false,
		) );
	}
}
add_action( 'acf/init', 'arkray_register_acf_options_page' );

/**
 * Sanitize a comma/whitespace-separated list of attachment IDs into an int array.
 */
function arkray_sanitize_attachment_id_list( $value ) {
	if ( is_array( $value ) ) {
		return array_values( array_filter( array_map( 'absint', $value ) ) );
	}
	$value = (string) $value;
	if ( '' === trim( $value ) ) {
		return array();
	}
	$ids = preg_split( '/[\s,]+/', $value );
	return array_values( array_filter( array_map( 'absint', $ids ) ) );
}

/**
 * Register Customizer controls so editors can manage hero slider + sidebar
 * widget images without touching theme code.
 */
function arkray_customize_register( $wp_customize ) {
	$wp_customize->add_section( 'arkray_homepage', array(
		'title'       => __( 'Homepage Media', 'arkray' ),
		'priority'    => 35,
		'description' => __( 'Hero slider images and right-sidebar widget banners.', 'arkray' ),
	) );

	// Hero slider images (multiple attachment IDs, stored as int array).
	$wp_customize->add_setting( 'arkray_slider_images', array(
		'default'           => array(),
		'type'              => 'theme_mod',
		'capability'        => 'edit_theme_options',
		'transport'         => 'refresh',
		'sanitize_callback' => 'arkray_sanitize_attachment_id_list',
	) );

	$wp_customize->add_control( 'arkray_slider_images', array(
		'label'       => __( 'Hero slider — attachment IDs', 'arkray' ),
		'description' => __( 'Comma-separated Media Library attachment IDs (e.g. 12,15,21). Hover an image in Media Library to see its ID.', 'arkray' ),
		'section'     => 'arkray_homepage',
		'type'        => 'textarea',
		'settings'    => 'arkray_slider_images',
	) );

	// Sidebar widget images (single attachment per widget).
	$sidebar_image_controls = array(
		'arkray_sidebar_gallery_image' => __( 'Media Gallery widget image', 'arkray' ),
		'arkray_sidebar_global_image'  => __( 'Global Leader widget image', 'arkray' ),
		'arkray_sidebar_health_image'  => __( 'Health Ingredients widget image', 'arkray' ),
	);

	foreach ( $sidebar_image_controls as $setting_key => $label ) {
		$wp_customize->add_setting( $setting_key, array(
			'default'           => 0,
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'transport'         => 'refresh',
			'sanitize_callback' => 'absint',
		) );

		$wp_customize->add_control( new WP_Customize_Media_Control(
			$wp_customize,
			$setting_key,
			array(
				'label'     => $label,
				'section'   => 'arkray_homepage',
				'mime_type' => 'image',
				'settings'  => $setting_key,
			)
		) );
	}

	// Health Ingredients widget click-through URL.
	$wp_customize->add_setting( 'arkray_sidebar_health_url', array(
		'default'           => '',
		'type'              => 'theme_mod',
		'capability'        => 'edit_theme_options',
		'transport'         => 'refresh',
		'sanitize_callback' => 'esc_url_raw',
	) );

	$wp_customize->add_control( 'arkray_sidebar_health_url', array(
		'label'    => __( 'Health Ingredients widget URL', 'arkray' ),
		'section'  => 'arkray_homepage',
		'type'     => 'url',
		'settings' => 'arkray_sidebar_health_url',
	) );
}
add_action( 'customize_register', 'arkray_customize_register' );
