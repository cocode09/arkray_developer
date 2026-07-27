<?php
/**
 * Single News Article Template (verbatim structure).
 *
 * Matches arkray.co.jp/english/news/{year}/{slug}.html — same IDs, classes,
 * and #editor_area.news_area markup as the origin detail pages.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

the_post();

// ── Page URLs ──────────────────────────────────────────────────────────────
$news_page_url      = esc_url( arkray_pll_permalink( get_page_by_path( 'news' ) ) ?: arkray_home_url( '/news/' ) );
$products_page_url  = esc_url( arkray_pll_permalink( get_page_by_path( 'products' ) ) ?: arkray_home_url( '/products/' ) );
$events_page_url    = esc_url( arkray_get_events_gallery_page_url() );
$about_page_url     = esc_url( arkray_get_about_page_url( 'about-us' ) );
$contact_page_url   = esc_url( arkray_get_contact_page_url() );
$sustainability_url = esc_url( arkray_get_sustainability_page_url() );
$history_url        = esc_url( arkray_get_history_page_url() );

// ── Logo ──────────────────────────────────────────────────────────────────
$custom_logo_id = get_theme_mod( 'custom_logo' );
$logo_src       = $custom_logo_id
	? wp_get_attachment_image_url( $custom_logo_id, 'full' )
	: get_stylesheet_directory_uri() . '/img/logo.jpg';

$news_title = get_the_title();
$article_html = arkray_render_news_article_html( get_the_ID() );

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="format-detection" content="telephone=no">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title><?php echo esc_html( $news_title ); ?> | <?php bloginfo( 'name' ); ?></title>
<?php wp_head(); ?>
</head>
<body class="arkray-inner arkray-news-page">
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
				<?php arkray_render_news_topics_menu_item( true ); ?>
				<li><a href="<?php echo $products_page_url; ?>"><?php echo esc_html( arkray_t( 'Products' ) ); ?></a></li>
				<li><a href="<?php echo $history_url; ?>"><?php echo esc_html( arkray_t( 'History of Pioneers' ) ); ?></a></li>
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
		<?php arkray_render_news_topics_menu_item( true ); ?>
		<li><a href="<?php echo $products_page_url; ?>"><?php echo esc_html( arkray_t( 'Products' ) ); ?></a></li>
		<li><a href="<?php echo $history_url; ?>"><?php echo esc_html( arkray_t( 'History of Pioneers' ) ); ?></a></li>
		<li><a href="<?php echo $events_page_url; ?>"><?php echo esc_html( arkray_t( 'Events & Gallery' ) ); ?></a></li>
		<li><a href="<?php echo $about_page_url; ?>"><?php echo esc_html( arkray_t( 'About Us' ) ); ?></a></li>
		<li><a href="<?php echo $sustainability_url; ?>"><?php echo esc_html( arkray_t( 'Sustainability' ) ); ?></a></li>
		<li><a href="<?php echo esc_url( arkray_get_recruitment_page_url() ); ?>"><?php echo esc_html( arkray_t( 'Recruitment' ) ); ?></a></li>
	</ul>

	<div id="content_area">
		<h1 class="h1_index"><?php echo esc_html( arkray_t( 'News & Topics' ) ); ?></h1>
		<div id="editor_area" class="news_area">
			<?php echo $article_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
