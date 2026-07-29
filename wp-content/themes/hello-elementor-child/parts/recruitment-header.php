<?php
/**
 * Shared chrome (head + header + left g_menu) for the Recruitment index and
 * job-detail views. Renders the verbatim ARKRAY markup so it matches the rest
 * of the site.
 *
 * Caller may set before include:
 *   $arkray_page_title    string  <title> text (default "Recruitment")
 *   $arkray_current_job_id int     ID of the job to mark active in the nav (0 = none)
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$custom_logo_id = get_theme_mod( 'custom_logo' );
$logo_src       = $custom_logo_id
	? wp_get_attachment_image_url( $custom_logo_id, 'full' )
	: get_stylesheet_directory_uri() . '/img/logo.jpg';

$news_page_url      = esc_url( arkray_pll_permalink( get_page_by_path( 'news' ) ) ?: arkray_home_url( '/news/' ) );
$products_page_url  = esc_url( arkray_pll_permalink( get_page_by_path( 'products' ) ) ?: arkray_home_url( '/products/' ) );
$events_page_url    = esc_url( arkray_get_events_gallery_page_url() );
$about_page_url     = esc_url( arkray_get_about_page_url( 'about-us' ) );
$contact_page_url   = esc_url( arkray_get_contact_page_url() );
$sustainability_url = esc_url( arkray_get_sustainability_page_url() );
$history_url        = esc_url( arkray_get_history_page_url() );
$recruitment_url    = esc_url( arkray_get_recruitment_page_url() );

$rec_page_title    = isset( $arkray_page_title ) ? $arkray_page_title : 'Recruitment';
$rec_current_job   = isset( $arkray_current_job_id ) ? (int) $arkray_current_job_id : 0;

$rec_nav_jobs = get_posts( array(
	'post_type'      => 'recruitment',
	'posts_per_page' => -1,
	'post_status'    => 'publish',
	'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
	'lang'           => '',
) );

// Render the Recruitment job sub-menu (shared by g_menu and sp_menu).
$rec_render_job_submenu = static function () use ( $rec_nav_jobs, $rec_current_job ) {
	if ( empty( $rec_nav_jobs ) ) {
		return;
	}
	echo '<ul style="display: block;">';
	foreach ( $rec_nav_jobs as $rec_job ) {
		$cls = ( $rec_job->ID === $rec_current_job ) ? ' class="ac"' : '';
		printf(
			'<li><a href="%s"%s>%s</a></li>',
			esc_url( get_permalink( $rec_job ) ),
			$cls,
			esc_html( get_the_title( $rec_job ) )
		);
	}
	echo '</ul>';
};
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="format-detection" content="telephone=no">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title><?php echo esc_html( $rec_page_title ); ?> | <?php bloginfo( 'name' ); ?></title>
<meta name="description" content="<?php bloginfo( 'description' ); ?>">
<?php wp_head(); ?>
</head>
<body class="arkray-inner arkray-recruitment-page">
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
		<div id="sp_menu"><ul></ul></div>
	</div>
</div>

<div id="content_wrapper" class="cf">
	<ul id="g_menu">
		<li><a href="<?php echo $news_page_url; ?>"><?php echo esc_html( arkray_t( 'News & Topics' ) ); ?></a></li>
		<li><a href="<?php echo $products_page_url; ?>"><?php echo esc_html( arkray_t( 'Products' ) ); ?></a></li>
		<li><a href="<?php echo $history_url; ?>"><?php echo esc_html( arkray_t( 'History of Pioneers' ) ); ?></a></li>
		<li><a href="<?php echo $events_page_url; ?>"><?php echo esc_html( arkray_t( 'Events & Gallery' ) ); ?></a></li>
		<li><a href="<?php echo $about_page_url; ?>"><?php echo esc_html( arkray_t( 'About Us' ) ); ?></a></li>
		<li><a href="<?php echo $sustainability_url; ?>"><?php echo esc_html( arkray_t( 'Sustainability' ) ); ?></a></li>
		<li><a href="<?php echo $recruitment_url; ?>" class="ac"><?php echo esc_html( arkray_t( 'Recruitment' ) ); ?></a>
			<?php $rec_render_job_submenu(); ?>
		</li>
	</ul>
