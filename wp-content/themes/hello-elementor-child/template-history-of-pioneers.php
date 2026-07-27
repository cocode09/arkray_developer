<?php
/**
 * Template Name: History of Pioneers
 *
 * Verbatim port of arkray.co.jp/english/products/history/*.html — uses the
 * original IDs (#header, #content_wrapper, #g_menu, #content_area,
 * #editor_area, #footer) and original classes (.h1_index, .h2_content,
 * .h3_content, .common_tabarea, .common_tab, .cf, .right_img, .tx,
 * .contact_area, .footer_link, .copyright) so the verbatim CSS in
 * arkray-content.css matches without drift.
 *
 * Section bodies are imported via the ARKRAY External Content plugin from
 * mapped pages (diabetes-testing-1, test-2, test-3, test-4). See
 * arkray_get_history_content_page_slug() in functions.php.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Section routing ──────────────────────────────────────────────────────
$history_sections = array(
	'history-of-pioneers'   => array( 'title' => 'History of Pioneers - Diabetes testing', 'type' => 'diabetes-testing' ),
	'diabetes-testing'      => array( 'title' => 'History of Pioneers - Diabetes testing', 'type' => 'diabetes-testing' ),
	'diabetes-testing01'    => array( 'title' => 'History of Pioneers - Diabetes testing', 'type' => 'diabetes-testing' ),
	'diabetes-testing-2'  => array( 'title' => 'History of Pioneers - Diabetes testing', 'type' => 'diabetes-testing' ),
	'diabetes-testing02'  => array( 'title' => 'History of Pioneers - Diabetes testing', 'type' => 'diabetes-testing' ),
	'urinalysis'            => array( 'title' => 'History of Pioneers - Urinalysis',       'type' => 'urinalysis' ),
	'dry-chemistry-testing' => array( 'title' => 'History of Pioneers - Dry Chemistry Testing', 'type' => 'dry-chemistry-testing' ),
	'poc-testing01'         => array( 'title' => 'History of Pioneers - Dry Chemistry Testing', 'type' => 'dry-chemistry-testing' ),
	'poc_testing01'         => array( 'title' => 'History of Pioneers - Dry Chemistry Testing', 'type' => 'dry-chemistry-testing' ),
	'poc-testing-02'        => array( 'title' => 'History of Pioneers - Dry Chemistry Testing', 'type' => 'dry-chemistry-testing' ),
	'poc_testing02'         => array( 'title' => 'History of Pioneers - Dry Chemistry Testing', 'type' => 'dry-chemistry-testing' ),
	'poc-testing02'         => array( 'title' => 'History of Pioneers - Dry Chemistry Testing', 'type' => 'dry-chemistry-testing' ),
	'bgm'                   => array( 'title' => 'History of Pioneers - BGM',              'type' => 'bgm' ),
	'smbg'                  => array( 'title' => 'History of Pioneers - BGM',              'type' => 'bgm' ),
);

$route_slug = function_exists( 'arkray_get_history_route_key_from_request' ) ? arkray_get_history_route_key_from_request() : '';
$page_slug  = '' !== $route_slug ? $route_slug : get_post_field( 'post_name', get_the_ID() );
$slug       = $page_slug;
if ( function_exists( 'arkray_get_history_route_key_from_content_page' ) ) {
	$mapped_route = arkray_get_history_route_key_from_content_page( $page_slug );
	if ( '' !== $mapped_route ) {
		$slug = $mapped_route;
	}
}
if ( empty( $slug ) || ! isset( $history_sections[ $slug ] ) ) {
	$slug = 'history-of-pioneers';
}
$current_page = $history_sections[ $slug ];

$hop_page_id = function_exists( 'arkray_resolve_history_content_page_id' )
	? arkray_resolve_history_content_page_id( $page_slug, $slug, $current_page['type'] )
	: (int) get_queried_object_id();

$hop_body_html = '';
$external_url  = ( $hop_page_id && function_exists( 'get_field' ) )
	? (string) get_field( 'external_content_url', $hop_page_id )
	: '';
if ( '' !== $external_url && function_exists( 'arkray_get_external_content' ) ) {
	$external_base = (string) get_field( 'external_content_base_url', $hop_page_id );
	$external_ttl  = (int) get_field( 'external_content_cache_hours', $hop_page_id );
	$hop_body_html = arkray_get_external_content(
		$external_url,
		array(
			'base_url'  => '' !== $external_base ? $external_base : 'https://www.arkray.global',
			'cache_ttl' => $external_ttl > 0 ? $external_ttl * HOUR_IN_SECONDS : DAY_IN_SECONDS,
		)
	);
}

$hop_h1_title = isset( $history_sections[ $page_slug ] )
	? $current_page['title']
	: get_the_title( $hop_page_id );

// ── Page URLs ────────────────────────────────────────────────────────────
$news_page_url      = esc_url( arkray_pll_permalink( get_page_by_path( 'news' ) ) ?: arkray_home_url( '/news/' ) );
$products_page_url  = esc_url( arkray_pll_permalink( get_page_by_path( 'products' ) ) ?: arkray_home_url( '/products/' ) );
$events_page_url    = esc_url( arkray_get_events_gallery_page_url() );
$about_page_url     = esc_url( arkray_get_about_page_url( 'about-us' ) );
$contact_page_url   = esc_url( arkray_get_contact_page_url() );
$sustainability_url = esc_url( arkray_get_sustainability_page_url() );
$history_url        = esc_url( arkray_get_history_page_url() );

// History sub-page URLs
$hop_diabetes_url   = esc_url( arkray_get_history_page_url( 'diabetes-testing' ) );
$hop_urinalysis_url = esc_url( arkray_get_history_page_url( 'urinalysis' ) );
$hop_dry_url        = esc_url( arkray_get_history_page_url( 'dry-chemistry-testing' ) );
$hop_bgm_url        = esc_url( arkray_get_history_page_url( 'bgm' ) );

// ── Logo ─────────────────────────────────────────────────────────────────
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
<title><?php echo esc_html( $current_page['title'] ); ?> | <?php bloginfo( 'name' ); ?></title>
<meta name="description" content="<?php bloginfo( 'description' ); ?>">
<?php wp_head(); ?>
</head>
<body class="arkray-inner arkray-history-page arkray-history-page--<?php echo esc_attr( $slug ); ?>">
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
				<option value="https://www.arkray.eu/ben/english/index.html?ct=Benelux" title="Benelux">&nbsp;&nbsp;&nbsp;Benelux</option>
				<option value="https://www.arkray.eu/it/italian/index.html?ct=Italy" title="Italy">&nbsp;&nbsp;&nbsp;Italy</option>
				<option value="https://www.arkray.eu/pt/portuguese/index.html?ct=Portugal" title="Portugal">&nbsp;&nbsp;&nbsp;Portugal</option>
				<option value="https://www.arkray.eu/es/spanish/index.html?ct=Spain" title="Spain">&nbsp;&nbsp;&nbsp;Spain</option>
				<option value="https://www.arkray.eu/uk/english/index.html?ct=UnitedKingdom" title="UnitedKingdom">&nbsp;&nbsp;&nbsp;United Kingdom</option>
				<option value="https://www.arkray.eu/english/index.html?ct=Europe">&nbsp;&nbsp;&nbsp;-Others</option>
				<option value="https://arkrayusa.com">North America</option>
				<option value="https://arkrayusa.com" title="USA">&nbsp;&nbsp;&nbsp;United States of America</option>
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
				<li><a href="<?php echo $history_url; ?>" class="ac"><?php echo esc_html( arkray_t( 'History of Pioneers' ) ); ?></a>
					<ul style="display: block;">
						<li><a href="<?php echo $hop_diabetes_url; ?>"<?php echo 'diabetes-testing' === $current_page['type'] ? ' class="ac"' : ''; ?>>Diabetes testing</a></li>
						<li><a href="<?php echo $hop_urinalysis_url; ?>"<?php echo 'urinalysis' === $current_page['type'] ? ' class="ac"' : ''; ?>>Urinalysis</a></li>
						<li><a href="<?php echo $hop_dry_url; ?>"<?php echo 'dry-chemistry-testing' === $current_page['type'] ? ' class="ac"' : ''; ?>>Dry Chemistry Testing</a></li>
						<li><a href="<?php echo $hop_bgm_url; ?>"<?php echo 'bgm' === $current_page['type'] ? ' class="ac"' : ''; ?>>BGM</a></li>
					</ul>
				</li>
				<li><a href="<?php echo $events_page_url; ?>"><?php echo esc_html( arkray_t( 'Events & Gallery' ) ); ?></a></li>
				<li><a href="<?php echo $about_page_url; ?>"><?php echo esc_html( arkray_t( 'About Us' ) ); ?></a></li>
				<li><a href="<?php echo $sustainability_url; ?>"><?php echo esc_html( arkray_t( 'Sustainability' ) ); ?></a></li>
				<li><a href="<?php echo esc_url( arkray_get_recruitment_page_url() ); ?>"><?php echo esc_html( arkray_t( 'Recruitment' ) ); ?></a></li>
			</ul>
		</div>
	</div>
</div>

<div id="content_wrapper" class="cf">
	<ul id="g_menu">
		<li><a href="<?php echo $news_page_url; ?>"><?php echo esc_html( arkray_t( 'News & Topics' ) ); ?></a></li>
		<li><a href="<?php echo $products_page_url; ?>"><?php echo esc_html( arkray_t( 'Products' ) ); ?></a></li>
		<li><a href="<?php echo $history_url; ?>" class="ac"><?php echo esc_html( arkray_t( 'History of Pioneers' ) ); ?></a>
			<ul style="display: block;">
				<li><a href="<?php echo $hop_diabetes_url; ?>"<?php echo 'diabetes-testing' === $current_page['type'] ? ' class="ac"' : ''; ?>>Diabetes testing</a></li>
				<li><a href="<?php echo $hop_urinalysis_url; ?>"<?php echo 'urinalysis' === $current_page['type'] ? ' class="ac"' : ''; ?>>Urinalysis</a></li>
				<li><a href="<?php echo $hop_dry_url; ?>"<?php echo 'dry-chemistry-testing' === $current_page['type'] ? ' class="ac"' : ''; ?>>Dry Chemistry Testing</a></li>
				<li><a href="<?php echo $hop_bgm_url; ?>"<?php echo 'bgm' === $current_page['type'] ? ' class="ac"' : ''; ?>>BGM</a></li>
			</ul>
		</li>
		<li><a href="<?php echo $events_page_url; ?>"><?php echo esc_html( arkray_t( 'Events & Gallery' ) ); ?></a></li>
		<li><a href="<?php echo $about_page_url; ?>"><?php echo esc_html( arkray_t( 'About Us' ) ); ?></a></li>
		<li><a href="<?php echo $sustainability_url; ?>"><?php echo esc_html( arkray_t( 'Sustainability' ) ); ?></a></li>
				<li><a href="<?php echo esc_url( arkray_get_recruitment_page_url() ); ?>"><?php echo esc_html( arkray_t( 'Recruitment' ) ); ?></a></li>
	</ul>

	<div id="content_area">
		<div id="editor_area">
			<?php
			$body_has_h1 = ( '' !== $hop_body_html ) && ( false !== stripos( $hop_body_html, '<h1' ) );
			if ( ! $body_has_h1 ) :
				?>
				<h1 class="h1_index"><?php echo esc_html( $hop_h1_title ); ?></h1>
			<?php endif; ?>
			<?php echo $hop_body_html; // Imported #content_area from the External Content plugin. ?>
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
