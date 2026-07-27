<?php
/**
 * Template Name: Sustainability
 *
 * Verbatim port of arkray.co.jp/english/sustainability/*.html — uses the
 * original IDs (#header, #content_wrapper, #g_menu, #content_area,
 * #editor_area, #footer) and original classes (.h1_about, .about_index,
 * .cf, .column) so the verbatim CSS in arkray-content.css matches without
 * drift. Body content for landing + 4 sub-pages is stored as postmeta on
 * the Sustainability page (ID 10). SDGs Initiatives uses the External Content
 * plugin; see tools/migrate-sdgs-initiatives-page.php.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Page URLs ─────────────────────────────────────────────────────────────
$news_page_url      = esc_url( arkray_pll_permalink( get_page_by_path( 'news' ) ) ?: arkray_home_url( '/news/' ) );
$products_page_url  = esc_url( arkray_pll_permalink( get_page_by_path( 'products' ) ) ?: arkray_home_url( '/products/' ) );
$events_page_url    = esc_url( arkray_get_events_gallery_page_url() );
$about_page_url     = esc_url( arkray_get_about_page_url( 'about-us' ) );
$contact_page_url   = esc_url( arkray_get_contact_page_url() );
$sustainability_url = esc_url( arkray_get_sustainability_page_url() );
$history_url        = esc_url( arkray_get_history_page_url() );

// Sustainability sub-page URLs (4 sub-routes)
$sus_commitment_url  = esc_url( arkray_get_sustainability_page_url( 'top-commitment' ) );
$sus_policy_url      = esc_url( arkray_get_sustainability_page_url( 'sdgs-basic-policy' ) );
$sus_materiality_url = esc_url( arkray_get_sustainability_page_url( 'arkrays-materiality' ) );
$sus_action_url      = esc_url( arkray_get_sustainability_page_url( 'sdgs-initiatives' ) );

// ── Current sub-page slug routing ─────────────────────────────────────────
$route_slug = function_exists( 'arkray_get_sustainability_route_key_from_request' )
	? arkray_get_sustainability_route_key_from_request()
	: '';
$sus_slug = '' !== $route_slug ? $route_slug : get_post_field( 'post_name', get_the_ID() );
if ( function_exists( 'arkray_get_sustainability_page_key_from_segment' ) ) {
	$mapped_slug = arkray_get_sustainability_page_key_from_segment( $sus_slug );
	if ( '' !== $mapped_slug ) {
		$sus_slug = $mapped_slug;
	}
}
$valid_slugs = array(
	'sustainability', 'top-commitment', 'sdgs-basic-policy',
	'arkrays-materiality', 'sdgs-initiatives',
);
if ( ! in_array( $sus_slug, $valid_slugs, true ) ) {
	$sus_slug = 'sustainability';
}

// Sub-page slug → ( h1 title, postmeta key on page 10 holding the body )
$sus_subpage_map = array(
	'top-commitment'      => array( 'Top Commitment',         'sus_commitment_body'  ),
	'sdgs-basic-policy'   => array( 'SDGs Basic Policy',      'sus_policy_body'      ),
	'arkrays-materiality' => array( 'ARKRAY’s Materiality',   'sus_materiality_body' ),
	'sdgs-initiatives'    => array( 'SDGs Initiatives',       'sus_action_body'      ),
);

$sus_h1_title = 'Sustainability';
if ( isset( $sus_subpage_map[ $sus_slug ] ) ) {
	$sus_h1_title = $sus_subpage_map[ $sus_slug ][0];
}

// Resolve the page that holds this route's per-page ACF fields. The landing
// lives on the `sustainability` page; each sub-route has a matching page slug
// (top-commitment, sdgs-basic-policy, arkrays-materiality, sdgs-initiatives).
$sus_page_id = get_queried_object_id();
$slug_page   = null;
if ( function_exists( 'arkray_get_sustainability_subpage_id' ) ) {
	$subpage_id = arkray_get_sustainability_subpage_id( $sus_slug );
	if ( $subpage_id ) {
		$slug_page = get_post( $subpage_id );
	}
}
if ( ! ( $slug_page instanceof WP_Post ) ) {
	$path_slugs = array( 'sustainability' === $sus_slug ? 'sustainability' : $sus_slug );
	if ( function_exists( 'arkray_get_sustainability_public_page_map' ) ) {
		$map = arkray_get_sustainability_public_page_map();
		if ( isset( $map[ $sus_slug ] ) ) {
			$path_slugs = array_merge( $path_slugs, $map[ $sus_slug ]['wp_slugs'] );
			if ( '' !== $map[ $sus_slug ]['legacy_slug'] ) {
				$path_slugs[] = 'sustainability/' . $map[ $sus_slug ]['legacy_slug'];
			}
		}
	}
	foreach ( array_unique( $path_slugs ) as $path_slug ) {
		$slug_page = get_page_by_path( $path_slug );
		if ( $slug_page instanceof WP_Post ) {
			break;
		}
	}
}
if ( $slug_page instanceof WP_Post ) {
	$sus_page_id = (int) $slug_page->ID;
}

// Resolve body HTML: the page editor and route-specific ACF fields override imported
// fallbacks. When an admin clears the editor and updates the page, render blank
// instead of falling back to cached external content or legacy postmeta.
$sus_body_html       = '';
$uses_editor_content = false;
$sus_page            = $sus_page_id ? get_post( $sus_page_id ) : null;

if ( $sus_page instanceof WP_Post ) {
	$editor_html = (string) $sus_page->post_content;
	if ( '' !== trim( $editor_html ) ) {
		// Imported editor_area markup is already complete HTML. Running wpautop
		// here would make the public body differ from what the admin edits.
		$sus_body_html       = do_shortcode( $editor_html );
		$uses_editor_content = true;
	} elseif ( strtotime( $sus_page->post_modified_gmt ) > strtotime( $sus_page->post_date_gmt ) + 60 ) {
		// Page was updated after its initial publish with an empty editor.
		// Only treat that as an intentional blank override when no import source
		// is configured; saving ACF fields alone must not block external content.
		$has_import_source = false;
		if ( function_exists( 'get_field' ) ) {
			$has_import_source = '' !== trim( (string) get_field( 'external_content_url', $sus_page_id ) );
			if ( ! $has_import_source && 'sdgs-initiatives' === $sus_slug ) {
				$has_import_source = '' !== trim( (string) get_field( 'sus_initiatives_content', $sus_page_id ) );
			}
		}
		if ( ! $has_import_source ) {
			$uses_editor_content = true;
		}
	}
}

if ( ! $uses_editor_content && $sus_page_id && function_exists( 'get_field' ) && 'sdgs-initiatives' === $sus_slug ) {
	$initiatives_html = trim( (string) get_field( 'sus_initiatives_content', $sus_page_id ) );
	if ( '' !== $initiatives_html ) {
		$sus_body_html       = $initiatives_html;
		$uses_editor_content = true;
	}
}

$external_url = ( $sus_page_id && function_exists( 'get_field' ) )
	? (string) get_field( 'external_content_url', $sus_page_id )
	: '';
if ( ! $uses_editor_content && '' !== $external_url && function_exists( 'arkray_get_external_content_best' ) ) {
	$external_base = (string) get_field( 'external_content_base_url', $sus_page_id );
	$external_ttl  = (int) get_field( 'external_content_cache_hours', $sus_page_id );
	$fetch_args    = array(
		'base_url'  => '' !== $external_base ? $external_base : 'https://www.arkray.global',
		'cache_ttl' => $external_ttl > 0 ? $external_ttl * HOUR_IN_SECONDS : DAY_IN_SECONDS,
	);
	if ( 'sdgs-initiatives' === $sus_slug ) {
		$fetch_args['preserve_source_navigation_urls'] = true;
	}
	$sus_body_html = arkray_get_external_content_best( $external_url, $fetch_args );
} elseif ( ! $uses_editor_content && '' !== $external_url && function_exists( 'arkray_get_external_content' ) ) {
	$external_base = (string) get_field( 'external_content_base_url', $sus_page_id );
	$external_ttl  = (int) get_field( 'external_content_cache_hours', $sus_page_id );
	$fetch_args    = array(
		'base_url'  => '' !== $external_base ? $external_base : 'https://www.arkray.global',
		'cache_ttl' => $external_ttl > 0 ? $external_ttl * HOUR_IN_SECONDS : DAY_IN_SECONDS,
	);
	if ( 'sdgs-initiatives' === $sus_slug ) {
		$fetch_args['preserve_source_navigation_urls'] = true;
	}
	$sus_body_html = arkray_get_external_content( $external_url, $fetch_args );
}
if ( 'sustainability' === $sus_slug && '' !== $sus_body_html ) {
	// Older external-content cache entries mapped action.html to the former
	// /sdgs-initiatives/ route. Keep the landing tile canonical even before
	// that remote-content transient expires.
	$sus_body_html = (string) preg_replace(
		'#href=(["\'])[^"\']*/sustainability/sdgs-initiatives/?\1#iu',
		'href=$1' . $sus_action_url . '$1',
		$sus_body_html
	);
}
if ( ! $uses_editor_content && '' === $sus_body_html && isset( $sus_subpage_map[ $sus_slug ] ) ) {
	$sus_body_html = (string) get_post_meta( 10, $sus_subpage_map[ $sus_slug ][1], true );
}

// When this page imports external content (including on the landing route),
// render that body directly and bypass the static landing tile grid.
$has_external = ( '' !== $external_url && '' !== $sus_body_html );

// Active-class helper for sub-menu
$sus_ac = static function ( $slug ) use ( $sus_slug ) {
	return ( $sus_slug === $slug ) ? ' class="ac"' : '';
};

// ── Logo ──────────────────────────────────────────────────────────────────
$custom_logo_id = get_theme_mod( 'custom_logo' );
$logo_src       = $custom_logo_id
	? wp_get_attachment_image_url( $custom_logo_id, 'full' )
	: get_stylesheet_directory_uri() . '/img/logo.jpg';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="format-detection" content="telephone=no">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<meta name="description" content="<?php bloginfo( 'description' ); ?>">
<?php
add_filter( 'document_title_parts', static function ( $parts ) use ( $sus_h1_title ) {
	$parts['title'] = $sus_h1_title;
	return $parts;
} );
wp_head();
?>
</head>
<body class="arkray-inner arkray-sustainability-page">
<?php wp_body_open(); ?>

<div id="header" class="cf">
	<div class="header_left">
		<p class="logo">
			<a href="<?php echo esc_url( arkray_home_url( '/' ) ); ?>">
				<img src="<?php echo esc_url( $logo_src ); ?>" alt="arkray">
			</a>
		</p>
	</div>
	<div class="header_right">
		<?php arkray_render_google_search(); ?>
		<div class="select">
			<select onchange="location.href=value">
				<option value=""><?php echo esc_html( arkray_t( 'Select' ) ); ?></option>
				<option value="https://www.arkray.asia/english/index.html?ct=Asia" title="Asia">Asia Pacific</option>
				<option value="https://www.arkray.cn/?ct=China" title="China">&nbsp;&nbsp;&nbsp;China</option>
				<option value="https://www.arkray.co.in/?ct=India" title="India">&nbsp;&nbsp;&nbsp;India</option>
				<option value="https://www.arkray.id/english/index.html?ct=Indonesia" title="Indonesia">&nbsp;&nbsp;&nbsp;Indonesia</option>
				<option value="https://www.arkray.co.jp/japanese/?ct=Japan" title="Japan">&nbsp;&nbsp;&nbsp;Japan</option>
				<option value="https://www.arkray.co.kr/?ct=Korea" title="Korea">&nbsp;&nbsp;&nbsp;Korea</option>
				<option value="https://www.arkray.ph/english/index.html?ct=Philippines" title="Philippines">&nbsp;&nbsp;&nbsp;Philippines</option>
				<option value="<?php echo esc_url( home_url( '/?ct=Vietnam' ) ); ?>" title="Vietnam" selected="selected">&nbsp;&nbsp;&nbsp;Vietnam</option>
				<option value="https://www.arkray.asia/english/index.html?ct=Asia">&nbsp;&nbsp;&nbsp;-Others</option>
				<option value="https://www.arkray.eu/english/index.html?ct=Europe" title="Europe">Europe</option>
				<option value="https://arkrayusa.com">North America</option>
				<option value="https://www.arkraylatam.com/spanish/?ct=Latin" title="Latin">Latin America</option>
				<option value="https://www.arkray.co.jp/english/?ct=Japan">Middle East</option>
				<option value="https://www.arkray.co.jp/english/?ct=Japan">Africa</option>
			</select>
		</div>
		<p class="base"><span class="poland_disp">Color Trading Sp. z o. o. </span><span class="other_disp"><?php echo esc_html( arkray_t( 'Vietnam site' ) ); ?></span></p>
		<?php arkray_render_language_switcher(); ?>
		<div id="sp_menubtn"><span></span><span></span><span></span></div>
		<div id="sp_menu">
			<ul>
				<li><a href="<?php echo $news_page_url; ?>"><?php echo esc_html( arkray_t( 'News & Topics' ) ); ?></a></li>
				<li><a href="<?php echo $products_page_url; ?>"><?php echo esc_html( arkray_t( 'Products' ) ); ?></a></li>
				<li><a href="<?php echo $history_url; ?>"><?php echo esc_html( arkray_t( 'History of Pioneers' ) ); ?></a></li>
				<li><a href="<?php echo $events_page_url; ?>"><?php echo esc_html( arkray_t( 'Events & Gallery' ) ); ?></a></li>
				<li><a href="<?php echo $about_page_url; ?>"><?php echo esc_html( arkray_t( 'About Us' ) ); ?></a></li>
				<li><a href="<?php echo $sustainability_url; ?>" class="ac"><?php echo esc_html( arkray_t( 'Sustainability' ) ); ?></a>
					<ul style="display: block;">
						<li><a href="<?php echo $sus_commitment_url;  ?>"<?php echo $sus_ac( 'top-commitment' );      ?>><?php echo esc_html( arkray_t( 'Top Commitment' ) ); ?></a></li>
						<li><a href="<?php echo $sus_policy_url;      ?>"<?php echo $sus_ac( 'sdgs-basic-policy' );   ?>><?php echo esc_html( arkray_t( 'SDGs Basic Policy' ) ); ?></a></li>
						<li><a href="<?php echo $sus_materiality_url; ?>"<?php echo $sus_ac( 'arkrays-materiality' ); ?>><?php echo esc_html( arkray_t( 'ARKRAY’s Materiality' ) ); ?></a></li>
						<li><a href="<?php echo $sus_action_url;      ?>"<?php echo $sus_ac( 'sdgs-initiatives' );    ?>><?php echo esc_html( arkray_t( 'SDGs Initiatives' ) ); ?></a></li>
					</ul>
				</li>
				<li><a href="<?php echo esc_url( arkray_get_recruitment_page_url() ); ?>"><?php echo esc_html( arkray_t( 'Recruitment' ) ); ?></a></li>
			</ul>
		</div>
	</div>
</div>

<div id="content_wrapper" class="cf">
	<ul id="g_menu">
		<li><a href="<?php echo $news_page_url; ?>"><?php echo esc_html( arkray_t( 'News & Topics' ) ); ?></a></li>
		<li><a href="<?php echo $products_page_url; ?>"><?php echo esc_html( arkray_t( 'Products' ) ); ?></a></li>
		<li><a href="<?php echo $history_url; ?>"><?php echo esc_html( arkray_t( 'History of Pioneers' ) ); ?></a></li>
		<li><a href="<?php echo $events_page_url; ?>"><?php echo esc_html( arkray_t( 'Events & Gallery' ) ); ?></a></li>
		<li><a href="<?php echo $about_page_url; ?>"><?php echo esc_html( arkray_t( 'About Us' ) ); ?></a></li>
		<li><a href="<?php echo $sustainability_url; ?>" class="ac"><?php echo esc_html( arkray_t( 'Sustainability' ) ); ?></a>
			<ul style="display: block;">
				<li><a href="<?php echo $sus_commitment_url;  ?>"<?php echo $sus_ac( 'top-commitment' );      ?>><?php echo esc_html( arkray_t( 'Top Commitment' ) ); ?></a></li>
				<li><a href="<?php echo $sus_policy_url;      ?>"<?php echo $sus_ac( 'sdgs-basic-policy' );   ?>><?php echo esc_html( arkray_t( 'SDGs Basic Policy' ) ); ?></a></li>
				<li><a href="<?php echo $sus_materiality_url; ?>"<?php echo $sus_ac( 'arkrays-materiality' ); ?>><?php echo esc_html( arkray_t( 'ARKRAY’s Materiality' ) ); ?></a></li>
				<li><a href="<?php echo $sus_action_url;      ?>"<?php echo $sus_ac( 'sdgs-initiatives' );    ?>><?php echo esc_html( arkray_t( 'SDGs Initiatives' ) ); ?></a></li>
			</ul>
		</li>
		<li><a href="<?php echo esc_url( arkray_get_recruitment_page_url() ); ?>"><?php echo esc_html( arkray_t( 'Recruitment' ) ); ?></a></li>
	</ul>

	<div id="content_area">
		<div id="editor_area">
			<?php
			// Inject template h1 unless the body already has one.
			$body_has_h1 = ( '' !== $sus_body_html ) && ( false !== stripos( $sus_body_html, '<h1' ) );
			?>
			<?php if ( $has_external ) : ?>
				<?php if ( ! $body_has_h1 ) : ?>
					<h1 class="h1_index"><?php echo esc_html( $sus_h1_title ); ?></h1>
				<?php endif; ?>
				<?php echo $sus_body_html; // Imported #content_area from the External Content plugin. ?>
			<?php else : ?>
				<?php if ( 'sustainability' === $sus_slug || ! $body_has_h1 ) : ?>
					<h1 class="h1_about"><?php echo esc_html( $sus_h1_title ); ?></h1>
				<?php endif; ?>

				<?php if ( 'sustainability' === $sus_slug ) : ?>
					<?php // ── Landing: 2x2 about_index grid of sub-section tiles ──── ?>
					<div class="about_index cf">
						<div class="column">
							<p><a href="<?php echo $sus_commitment_url; ?>"><img src="https://www.arkray.co.jp/english/sustainability/img/index01.png" alt="Top Commitment" width="350" height="80"></a></p>
						</div>
						<div class="column">
							<p><a href="<?php echo $sus_policy_url; ?>"><img src="https://www.arkray.co.jp/english/sustainability/img/index02.png" alt="SDGs Basic Policy" width="350" height="80"></a></p>
						</div>
					</div>
					<div class="about_index cf">
						<div class="column">
							<p><a href="<?php echo $sus_materiality_url; ?>"><img src="https://www.arkray.co.jp/english/sustainability/img/index03.png" alt="ARKRAY's Materiality" width="350" height="80"></a></p>
						</div>
						<div class="column">
							<p><a href="<?php echo $sus_action_url; ?>"><img src="https://www.arkray.co.jp/english/sustainability/img/index04.png" alt="SDGs Initiatives" width="350" height="80"></a></p>
						</div>
					</div>
				<?php else : ?>
					<?php echo $sus_body_html; // Verbatim editor_area from reference scrape. ?>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
</div>

<div id="footer">
	<div class="footer_link">
		<ul>
			<?php
			$privacy_page = get_page_by_path( 'privacy-policy' );
			$terms_page   = get_page_by_path( 'website-terms-of-use' );
			if ( ! $terms_page ) { $terms_page = get_page_by_path( 'terms-of-use' ); }
			$sitemap_page = get_page_by_path( 'site-map' );
			?>
			<li><a href="<?php echo esc_url( arkray_get_privacy_policy_url() ); ?>"><?php echo esc_html( arkray_t( 'Privacy Policy' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( arkray_get_terms_of_use_url() ); ?>"><?php echo esc_html( arkray_t( 'Website Terms of Use' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( arkray_get_site_map_url() ); ?>"><?php echo esc_html( arkray_t( 'Site Map' ) ); ?></a></li>
			<li><a href="<?php echo $contact_page_url; ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( arkray_t( 'Contact Us' ) ); ?></a></li>
		</ul>
	</div>
	<div class="copyright">
		<p><?php printf( esc_html( arkray_t( 'Copyright© %s ARKRAY, Inc. All Rights Reserved.' ) ), esc_html( date( 'Y' ) ) ); ?></p>
	</div>
</div>

<p id="pagetop" style="display:none;">
	<img src="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/img/pagetop.jpg" alt="" width="35" height="35">
</p>

<?php wp_footer(); ?>
</body>
</html>
