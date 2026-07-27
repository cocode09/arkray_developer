<?php
/**
 * Template Name: About Pages
 *
 * Verbatim port of arkray.co.jp/english/about/index.html — uses the original
 * IDs (#header, #content_wrapper, #g_menu, #content_area, #editor_area,
 * #footer) and original classes (.h1_about, .about_index, .cf, .column,
 * .catalog_area, .font_size01) so the verbatim CSS in arkray-content.css
 * matches without drift.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Page URLs ──────────────────────────────────────────────────────────────
$news_page_url      = esc_url( arkray_pll_permalink( get_page_by_path( 'news' ) ) ?: arkray_home_url( '/news/' ) );
$products_page_url  = esc_url( arkray_pll_permalink( get_page_by_path( 'products' ) ) ?: arkray_home_url( '/products/' ) );
$events_page_url    = esc_url( arkray_get_events_gallery_page_url() );
$about_page_url     = esc_url( arkray_get_about_page_url( 'about-us' ) );
$contact_page_url   = esc_url( arkray_get_contact_page_url() );
$sustainability_url = esc_url( arkray_get_sustainability_page_url() );
$history_url        = esc_url( arkray_get_history_page_url() );

// About sub-page URLs
$philosophy_url     = esc_url( arkray_get_about_page_url( 'arkray-philosophy' ) );
$message_url        = esc_url( arkray_get_about_page_url( 'message-from-arkray' ) );
$concept_url        = esc_url( arkray_get_about_page_url( 'brand-concept' ) );
$profile_url        = esc_url( arkray_get_about_page_url( 'about-contact' ) );
$business_url       = esc_url( arkray_get_about_page_url( 'corporate-outline' ) );
$history_about_url     = esc_url( arkray_get_about_page_url( 'history' ) );
$history_about_url_raw = arkray_get_about_page_url( 'history' );
$group_url          = esc_url( arkray_get_about_page_url( 'arkray-group' ) );
$group2_url         = esc_url( arkray_get_about_page_url( 'arkray-group-2' ) );
$group3_url         = esc_url( arkray_get_about_page_url( 'arkray-group-3' ) );
$group4_url         = esc_url( arkray_get_about_page_url( 'arkray-group-4' ) );
$group5_url         = esc_url( arkray_get_about_page_url( 'arkray-group-5' ) );
$action_guide_url   = esc_url( arkray_get_about_page_url( 'arkray-action-guidelines' ) );
$company_profile_pdf = esc_url( 'https://www.arkray.co.jp/english/corpo/profile_e.pdf' );

// ── Current sub-page slug routing ──────────────────────────────────────────
// Detect slug from URL via the routing helper, falling back to the WP page
// slug. The about-us template handles 11 routes — landing + 10 sub-pages.
$route_slug = function_exists( 'arkray_get_about_route_key_from_request' )
	? arkray_get_about_route_key_from_request()
	: '';
$about_slug = '' !== $route_slug ? $route_slug : get_post_field( 'post_name', get_the_ID() );
$about_slug_aliases = array(
	'contact' => 'about-contact',
	'profile' => 'about-contact',
);
if ( isset( $about_slug_aliases[ $about_slug ] ) ) {
	$about_slug = $about_slug_aliases[ $about_slug ];
}
$valid_slugs = array(
	'about-us', 'arkray-philosophy', 'arkray-action-guidelines',
	'message-from-arkray', 'brand-concept', 'about-contact',
	'corporate-outline', 'history', 'arkray-group',
	'arkray-group-2', 'arkray-group-3', 'arkray-group-4', 'arkray-group-5',
);
if ( ! in_array( $about_slug, $valid_slugs, true ) ) {
	$about_slug = 'about-us';
}

// Sub-page slug → ( h1 title, postmeta key on page ID 9 holding the body )
$about_subpage_map = array(
	'arkray-philosophy'                 => array( 'ARKRAY Philosophy',                   'about_philosophy_body' ),
	'arkray-action-guidelines'          => array( 'ARKRAY Action Guidelines',            'about_action_guidelines_body' ),
	'message-from-arkray'               => array( 'Message from ARKRAY',                 'about_message_body' ),
	'brand-concept'                     => array( 'Brand Concept',                       'about_concept_body' ),
	'about-contact'                     => array( 'Contact',                             'about_contact_body' ),
	'corporate-outline'                 => array( 'Corporate Outline',                   'about_corporate_outline_body' ),
	'history'                           => array( 'History',                             'about_history_body' ),
	'arkray-group'                      => array( 'ARKRAY Group',                        'about_group_body' ),
	'arkray-group-2'                    => array( 'ARKRAY Group — Japan',               'about_group2_body' ),
	'arkray-group-3'                    => array( 'ARKRAY Group — Asia',                'about_group3_body' ),
	'arkray-group-4'                    => array( 'ARKRAY Group — Europe',              'about_group4_body' ),
	'arkray-group-5'                    => array( 'ARKRAY Group — Americas',            'about_group5_body' ),
);

$about_h1_title = 'About Us';
if ( isset( $about_subpage_map[ $about_slug ] ) ) {
	$about_h1_title = $about_subpage_map[ $about_slug ][0];
}

// Resolve body HTML: the page editor overrides imported fallbacks. When an admin
// clears the External Content URL and updates the page, render blank instead of
// falling back to cached external content or legacy postmeta on page 9.
$queried_id          = get_queried_object_id();
$content_id          = $queried_id;
$about_body_html     = '';
$uses_editor_content = false;

if ( function_exists( 'arkray_get_about_subpage_id' ) ) {
	$subpage_content_id = arkray_get_about_subpage_id( $about_slug );
	if ( $subpage_content_id ) {
		$content_id = $subpage_content_id;
	} elseif ( 'about-us' !== $about_slug ) {
		$landing_id = arkray_get_about_subpage_id( 'about-us' );
		if ( $landing_id ) {
			$content_id = $landing_id;
		}
	}
}

$content_page = $content_id ? get_post( $content_id ) : null;
$editor_html  = ( $content_page instanceof WP_Post ) ? (string) $content_page->post_content : '';

$external_url = ( $content_id && function_exists( 'get_field' ) )
	? trim( (string) get_field( 'external_content_url', $content_id ) )
	: '';
$has_import_source     = '' !== $external_url;
$import_settings_saved = (bool) get_post_meta( $content_id, '_arkray_external_content_configured', true );
$config_at             = (int) get_post_meta( $content_id, '_arkray_external_content_configured_at', true );
$modified_at           = ( $content_page instanceof WP_Post ) ? strtotime( $content_page->post_modified_gmt ) : 0;
$editor_saved_after_import_settings = $config_at > 0 && $modified_at > ( $config_at + 5 );

if ( ! $has_import_source && $import_settings_saved && ! $editor_saved_after_import_settings ) {
	// Cleared import URL — ignore stale migration placeholders until the page editor
	// is saved again after the External Content settings were updated.
	$uses_editor_content = true;
	$about_body_html     = '';
} elseif ( '' !== trim( $editor_html ) ) {
	// Imported editor_area markup is already complete HTML. Running wpautop
	// here would make the public body differ from what the admin edits.
	$about_body_html     = do_shortcode( $editor_html );
	$uses_editor_content = true;
} elseif ( $content_page instanceof WP_Post && strtotime( $content_page->post_modified_gmt ) > strtotime( $content_page->post_date_gmt ) + 60 ) {
	// Page was updated after its initial publish with an empty editor.
	// Only treat that as an intentional blank override when no import source
	// is configured; saving ACF fields alone must not block external content.
	if ( ! $has_import_source ) {
		$uses_editor_content = true;
	}
}

$group_page_keys = function_exists( 'arkray_get_group_about_page_keys' )
	? arkray_get_group_about_page_keys()
	: array( 'arkray-group', 'arkray-group-2', 'arkray-group-3', 'arkray-group-4', 'arkray-group-5' );
if ( ! $uses_editor_content && '' !== $external_url && function_exists( 'arkray_get_external_content' ) && 'history' !== $about_slug && ! in_array( $about_slug, $group_page_keys, true ) ) {
	$external_base = (string) get_field( 'external_content_base_url', $content_id );
	$external_ttl  = (int) get_field( 'external_content_cache_hours', $content_id );
	$about_body_html = arkray_get_external_content(
		$external_url,
		array(
			'base_url'  => '' !== $external_base ? $external_base : 'https://www.arkray.global',
			'cache_ttl' => $external_ttl > 0 ? $external_ttl * HOUR_IN_SECONDS : DAY_IN_SECONDS,
		)
	);
}
if ( ! $uses_editor_content && '' === $about_body_html && isset( $about_subpage_map[ $about_slug ] ) && ! in_array( $about_slug, $group_page_keys, true ) ) {
	$about_body_html = (string) get_post_meta( 9, $about_subpage_map[ $about_slug ][1], true );
}

// When this page imports external content (e.g. a standalone page that is not
// one of the built-in About routes), render that body directly and bypass the
// landing grid, history tabs, and group map layouts.
// Company History uses decade tabs and per-decade external imports; do not
// treat it as a single-page external body.
$has_external = ( '' !== $external_url && '' !== $about_body_html && 'history' !== $about_slug );

// Sub-menu active-class helper — emits ' class="ac"' when slug matches current.
// ARKRAY Group regional tabs (Japan, Asia, etc.) share the same sidebar item.
$sub_ac = static function ( $slug ) use ( $about_slug, $group_page_keys ) {
	if ( 'arkray-group' === $slug && in_array( $about_slug, $group_page_keys, true ) ) {
		return ' class="ac"';
	}
	return ( $about_slug === $slug ) ? ' class="ac"' : '';
};

// ── Logo ──────────────────────────────────────────────────────────────────
$theme_img_uri  = get_stylesheet_directory_uri() . '/img';
$custom_logo_id = get_theme_mod( 'custom_logo' );
$logo_src       = $custom_logo_id
	? wp_get_attachment_image_url( $custom_logo_id, 'full' )
	: $theme_img_uri . '/logo.jpg';
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
// Override WP's auto-generated <title> with the sub-page title.
add_filter( 'document_title_parts', static function ( $parts ) use ( $about_h1_title ) {
	$parts['title'] = $about_h1_title;
	return $parts;
} );
wp_head();
?>
</head>
<body class="arkray-inner arkray-about-page">
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
				<li><a href="<?php echo $history_url; ?>"><?php echo esc_html( arkray_t( 'History of Pioneers' ) ); ?></a></li>
				<li><a href="<?php echo $events_page_url; ?>"><?php echo esc_html( arkray_t( 'Events & Gallery' ) ); ?></a></li>
				<li><a href="<?php echo $about_page_url; ?>" class="ac"><?php echo esc_html( arkray_t( 'About Us' ) ); ?></a>
					<ul style="display: block;">
						<li><a href="<?php echo $philosophy_url; ?>"<?php echo $sub_ac("arkray-philosophy"); ?>><?php echo esc_html( arkray_t( 'ARKRAY Philosophy' ) ); ?></a></li>
						<li><a href="<?php echo $message_url; ?>"<?php echo $sub_ac("message-from-arkray"); ?>><?php echo esc_html( arkray_t( 'Message from ARKRAY' ) ); ?></a></li>
						<li><a href="<?php echo $concept_url; ?>"<?php echo $sub_ac("brand-concept"); ?>><?php echo esc_html( arkray_t( 'Brand Concept' ) ); ?></a></li>
						<li><a href="<?php echo $profile_url; ?>"<?php echo $sub_ac("about-contact"); ?>><?php echo esc_html( arkray_t( 'Contact' ) ); ?></a></li>
						<li><a href="<?php echo $business_url; ?>"<?php echo $sub_ac("corporate-outline"); ?>><?php echo esc_html( arkray_t( 'Corporate Outline' ) ); ?></a></li>
						<li><a href="<?php echo $history_about_url; ?>"<?php echo $sub_ac("history"); ?>><?php echo esc_html( arkray_t( 'History' ) ); ?></a></li>
						<li><a href="<?php echo $group_url; ?>"<?php echo $sub_ac("arkray-group"); ?>><?php echo esc_html( arkray_t( 'ARKRAY Group' ) ); ?></a></li>
						<li><a href="<?php echo $company_profile_pdf; ?>" target="_blank"><?php echo esc_html( arkray_t( 'Download Company Profile [PDF]' ) ); ?></a></li>
					</ul>
				</li>
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
		<li><a href="<?php echo $history_url; ?>"><?php echo esc_html( arkray_t( 'History of Pioneers' ) ); ?></a></li>
		<li><a href="<?php echo $events_page_url; ?>"><?php echo esc_html( arkray_t( 'Events & Gallery' ) ); ?></a></li>
		<li><a href="<?php echo $about_page_url; ?>" class="ac"><?php echo esc_html( arkray_t( 'About Us' ) ); ?></a>
			<ul style="display: block;">
				<li><a href="<?php echo $philosophy_url; ?>"<?php echo $sub_ac("arkray-philosophy"); ?>><?php echo esc_html( arkray_t( 'ARKRAY Philosophy' ) ); ?></a></li>
				<li><a href="<?php echo $message_url; ?>"<?php echo $sub_ac("message-from-arkray"); ?>><?php echo esc_html( arkray_t( 'Message from ARKRAY' ) ); ?></a></li>
				<li><a href="<?php echo $concept_url; ?>"<?php echo $sub_ac("brand-concept"); ?>><?php echo esc_html( arkray_t( 'Brand Concept' ) ); ?></a></li>
				<li><a href="<?php echo $profile_url; ?>"<?php echo $sub_ac("about-contact"); ?>><?php echo esc_html( arkray_t( 'Contact' ) ); ?></a></li>
				<li><a href="<?php echo $business_url; ?>"<?php echo $sub_ac("corporate-outline"); ?>><?php echo esc_html( arkray_t( 'Corporate Outline' ) ); ?></a></li>
				<li><a href="<?php echo $history_about_url; ?>"<?php echo $sub_ac("history"); ?>><?php echo esc_html( arkray_t( 'History' ) ); ?></a></li>
				<li><a href="<?php echo $group_url; ?>"<?php echo $sub_ac("arkray-group"); ?>><?php echo esc_html( arkray_t( 'ARKRAY Group' ) ); ?></a></li>
				<li><a href="<?php echo $company_profile_pdf; ?>" target="_blank"><?php echo esc_html( arkray_t( 'Download Company Profile [PDF]' ) ); ?></a></li>
			</ul>
		</li>
		<li><a href="<?php echo $sustainability_url; ?>"><?php echo esc_html( arkray_t( 'Sustainability' ) ); ?></a></li>
				<li><a href="<?php echo esc_url( arkray_get_recruitment_page_url() ); ?>"><?php echo esc_html( arkray_t( 'Recruitment' ) ); ?></a></li>
	</ul>

	<div id="content_area">
		<div id="editor_area">
		<style type="text/css">
.font_size01 {font-size: 18px;}
</style>

		<?php
		// For the about-us landing we render our own h1.h1_about banner.
		// For sub-pages, the verbatim body usually includes its own <h1> from
		// the reference (e.g. "Message from our management" — h1_index class).
		// When the body has NO h1 (e.g. ARKRAY Action Guidelines),
		// inject the template's h1 so the page isn't left without a heading.
		// ARKRAY Group pages fall back to the interactive Google Map (gmap.js) only
		// when no body content is available from the page editor, external import,
		// or page-9 postmeta fallback.
		$group_area_map = array();
		foreach ( $group_page_keys as $group_key ) {
			$map_area = function_exists( 'arkray_get_group_map_area_for_slug' )
				? arkray_get_group_map_area_for_slug( $group_key )
				: '';
			if ( '' === $map_area ) {
				continue;
			}
			$region_label = ( 'US' === $map_area ) ? 'Americas' : $map_area;
			$group_area_map[ $group_key ] = array(
				'area'   => $map_area,
				'region' => $region_label,
			);
		}
		$is_group_page   = isset( $group_area_map[ $about_slug ] );
		$map_area        = $is_group_page ? $group_area_map[ $about_slug ]['area'] : '';
		$active_region   = $is_group_page ? $group_area_map[ $about_slug ]['region'] : '';
		$show_group_map  = $is_group_page && '' === trim( $about_body_html );

		$body_has_h1 = ( '' !== $about_body_html )
			&& ( false !== stripos( $about_body_html, '<h1' ) );
		?>
		<?php if ( $has_external ) : ?>
			<?php if ( ! $body_has_h1 ) : ?>
				<h1 class="h1_index"><?php echo esc_html( get_the_title( $queried_id ) ); ?></h1>

			<?php endif; ?>
		<?php elseif ( ( 'about-us' === $about_slug || ! $body_has_h1 ) && 'history' !== $about_slug && ! $show_group_map ) : ?>
			<h1 class="h1_about"><?php echo esc_html( $about_h1_title ); ?></h1>

		<?php endif; ?>

		<?php if ( ! $has_external && 'about-us' === $about_slug && $uses_editor_content && '' !== $about_body_html ) : ?>
			<?php echo $about_body_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- admin-authored page content. ?>
		<?php elseif ( ! $has_external && 'about-us' === $about_slug && ! $uses_editor_content ) : ?>
			<?php // ── Landing fallback grid when the page editor is empty ──────── ?>
			<div class="about_index cf">
				<div class="column">
					<h2><a href="<?php echo $philosophy_url; ?>"<?php echo $sub_ac("arkray-philosophy"); ?>><?php echo esc_html( arkray_t( 'ARKRAY Philosophy' ) ); ?></a></h2>
					<p><a href="<?php echo $philosophy_url; ?>"<?php echo $sub_ac("arkray-philosophy"); ?>><img src="<?php echo esc_url( $theme_img_uri . '/about10.jpg' ); ?>" alt="ARKRAY Philosophy" width="350" height="80"></a></p>
				</div>
			</div>

			<div class="about_index cf">
				<div class="column">
					<h2><a href="<?php echo $message_url; ?>"<?php echo $sub_ac("message-from-arkray"); ?>><?php echo esc_html( arkray_t( 'Message from ARKRAY' ) ); ?></a></h2>
					<p><a href="<?php echo $message_url; ?>"<?php echo $sub_ac("message-from-arkray"); ?>><img src="<?php echo esc_url( $theme_img_uri . '/about01.jpg' ); ?>" alt="Message from ARKRAY" width="350" height="80"></a></p>
				</div>
				<div class="column">
					<h2><a href="<?php echo $concept_url; ?>"<?php echo $sub_ac("brand-concept"); ?>><?php echo esc_html( arkray_t( 'Brand Concept' ) ); ?></a></h2>
					<p><a href="<?php echo $concept_url; ?>"<?php echo $sub_ac("brand-concept"); ?>><img src="<?php echo esc_url( $theme_img_uri . '/about03.jpg' ); ?>" alt="Brand Concept" width="350" height="80"></a></p>
				</div>
			</div>

			<div class="about_index cf">
				<div class="column">
					<h2><a href="<?php echo $profile_url; ?>"<?php echo $sub_ac("about-contact"); ?>><?php echo esc_html( arkray_t( 'Contact' ) ); ?></a></h2>
					<p><a href="<?php echo $profile_url; ?>"<?php echo $sub_ac("about-contact"); ?>><img src="<?php echo esc_url( $theme_img_uri . '/about04.jpg' ); ?>" alt="Corporate Profile" width="350" height="80"></a></p>
				</div>
				<div class="column">
					<h2><a href="<?php echo $business_url; ?>"<?php echo $sub_ac("corporate-outline"); ?>><?php echo esc_html( arkray_t( 'Corporate Outline' ) ); ?></a></h2>
					<p><a href="<?php echo $business_url; ?>"<?php echo $sub_ac("corporate-outline"); ?>><img alt="English_bnr-100.jpg" src="<?php echo esc_url( $theme_img_uri . '/English_bnr-100.jpg' ); ?>" width="350" height="80"></a></p>
				</div>
			</div>

			<div class="about_index cf">
				<div class="column">
					<h2><a href="<?php echo $history_about_url; ?>"<?php echo $sub_ac("history"); ?>><?php echo esc_html( arkray_t( 'History' ) ); ?></a></h2>
					<p><a href="<?php echo $history_about_url; ?>"<?php echo $sub_ac("history"); ?>><img src="<?php echo esc_url( $theme_img_uri . '/about05.jpg' ); ?>" alt="History" width="350" height="80"></a></p>
				</div>
				<div class="column">
					<h2><a href="<?php echo $group_url; ?>"<?php echo $sub_ac("arkray-group"); ?>><?php echo esc_html( arkray_t( 'ARKRAY Group' ) ); ?></a></h2>
					<p><a href="<?php echo $group_url; ?>"<?php echo $sub_ac("arkray-group"); ?>><img src="<?php echo esc_url( $theme_img_uri . '/about06.jpg' ); ?>" alt="ARKRAY Group" width="350" height="80"></a></p>
				</div>
			</div>

			<div class="catalog_area">
				<p><a href="<?php echo $company_profile_pdf; ?>" target="_blank"><?php echo esc_html( arkray_t( 'Download Company Profile' ) ); ?></a></p>
			</div>

		<?php elseif ( ! $has_external && 'history' === $about_slug ) : ?>
			<?php
			// ── Company/About History — decade tabs from reference scrape ─────────
			// Tabs link to path-preserved decade URLs (e.g. /english/about/history1970).
			// Company History and Product History columns always match the reference.
			$valid_decades  = function_exists( 'arkray_get_history_decades' )
				? arkray_get_history_decades()
				: array( '1960', '1970', '1980', '1990', '2000', '2010', '2020' );
			$active_decade  = function_exists( 'arkray_get_active_history_decade' )
				? arkray_get_active_history_decade()
				: '1960';
			if ( ! in_array( $active_decade, $valid_decades, true ) ) {
				$active_decade = '1960';
			}
			?>
			<h1 class="h1_index">History</h1>
			<div class="common_tabarea">
				<div class="common_tab">
					<?php foreach ( $valid_decades as $decade ) : ?>
						<p><a href="<?php echo esc_url( function_exists( 'arkray_get_history_decade_url' ) ? arkray_get_history_decade_url( $decade ) : add_query_arg( 'decade', $decade, $history_about_url_raw ) ); ?>"<?php echo $decade === $active_decade ? ' class="ac"' : ''; ?>><?php echo esc_html( $decade ); ?></a></p>
					<?php endforeach; ?>
				</div>
			</div>
			<?php
			$history_page_id = function_exists( 'arkray_get_about_subpage_id' )
				? arkray_get_about_subpage_id( 'history' )
				: (int) $queried_id;
			$history_decade_html = function_exists( 'arkray_get_history_decade_content' )
				? arkray_get_history_decade_content( $active_decade, $history_page_id )
				: '';
			// Never reuse the static 1960 postmeta body when another decade tab is active.
			$history_body_html = $history_decade_html;
			if ( '' === $history_body_html && '1960' === $active_decade ) {
				$history_body_html = $about_body_html;
			}
			if ( '' !== $history_body_html && function_exists( 'arkray_prepare_history_body_markup' ) ) {
				$history_body_html = arkray_prepare_history_body_markup( $history_body_html );
			}
			if ( '' !== $history_body_html ) {
				echo $history_body_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized by external content plugin.
			}
			?>
		<?php else : ?>
			<?php // ── Sub-page: render verbatim body extracted from reference HTML ──
			// Migrate-about-content.php populates `about_*_body` postmeta on page 9.
			if ( $show_group_map ) {
					include locate_template( 'parts/arkray-group-map.php' );
				} elseif ( '' !== $about_body_html ) {
					if ( $is_group_page && function_exists( 'arkray_prepare_group_body_markup' ) ) {
						$about_body_html = arkray_prepare_group_body_markup( $about_body_html, $active_region );
					}
					echo $about_body_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized by the_content or external content plugin.
				}
			?>
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
