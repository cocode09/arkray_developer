<?php
/**
 * Site Map — #editor_area content.
 *
 * Verbatim port of the #editor_area block from the ARKRAY Site Map reference
 * page (Sandbox/Site Map _ ARKRAY, Inc..html). Classes sitemap_area,
 * sitemap_left, sitemap_right, sitemap are styled by assets/arkray-content.css.
 *
 * This partial is only included when the WordPress page body is empty, so the
 * admin can override every link via Pages > Site Map > Edit.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$news_page_url      = esc_url( arkray_pll_permalink( get_page_by_path( 'news' ) ) ?: arkray_home_url( '/news/' ) );
$products_page_url  = esc_url( arkray_pll_permalink( get_page_by_path( 'products' ) ) ?: arkray_home_url( '/products/' ) );
$events_page_url    = esc_url( arkray_get_events_gallery_page_url() );
$events_tab_url     = esc_url( arkray_get_events_gallery_page_url( 'events' ) );
$gallery_tab_url    = esc_url( arkray_get_events_gallery_page_url( 'gallery' ) );
$about_page_url     = esc_url( arkray_get_about_page_url( 'about-us' ) );
$history_url        = esc_url( arkray_get_history_page_url() );
$sustainability_url = esc_url( arkray_get_sustainability_page_url() );
$recruitment_url    = esc_url( arkray_get_recruitment_page_url() );
$contact_page_url   = esc_url( arkray_get_contact_page_url() );
$company_profile_pdf = esc_url( 'https://www.arkray.co.jp/english/corpo/profile_e.pdf' );

$privacy_page = get_page_by_path( 'privacy-policy' );
$terms_page   = get_page_by_path( 'website-terms-of-use' );
if ( ! $terms_page ) {
	$terms_page = get_page_by_path( 'terms-of-use' );
}
$privacy_url = esc_url( arkray_get_privacy_policy_url() );
$terms_url   = esc_url( arkray_get_terms_of_use_url() );
?>
<h1 class="h1_index"><?php echo esc_html( arkray_t( 'Site Map' ) ); ?></h1>
<div class="sitemap_area cf">
	<div class="sitemap_left">
		<h2><a href="<?php echo $news_page_url; ?>"><?php echo esc_html( arkray_t( 'News & Topics' ) ); ?></a></h2>
	</div>
</div>
<div class="sitemap_area cf">
	<div class="sitemap_left">
		<div class="line">
			<h2><a href="<?php echo $products_page_url; ?>"><?php echo esc_html( arkray_t( 'Products' ) ); ?></a></h2>
		</div>
	</div>
	<div class="sitemap_right">
		<ul class="sitemap">
			<li><a href="<?php echo esc_url( arkray_get_product_category_url( 'laboratory-testing' ) ); ?>"><?php echo esc_html( arkray_t( 'Laboratory Testing' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( arkray_get_product_category_url( 'near-patient-testing' ) ); ?>"><?php echo esc_html( arkray_t( 'Near Patient Testing' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( arkray_get_product_category_url( 'bgm' ) ); ?>"><?php echo esc_html( arkray_t( 'Blood Glucose Monitoring （BGM）' ) ); ?></a></li>
			<li><a href="http://arkraydental.com/" target="_blank" rel="noopener noreferrer"><?php echo esc_html( arkray_t( 'Oral care' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( arkray_get_product_category_url( 'veterinary-others' ) ); ?>"><?php echo esc_html( arkray_t( 'Veterinary & Others' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( arkray_get_product_category_url( 'immunodiagnostic-products' ) ); ?>"><?php echo esc_html( arkray_t( 'Immunodiagnostic Products' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( arkray_get_product_category_url( 'clinical-chemistry-reagents' ) ); ?>"><?php echo esc_html( arkray_t( 'Clinical Chemistry Reagents' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( arkray_get_product_category_url( 'primary-health-care' ) ); ?>"><?php echo esc_html( arkray_t( 'Primary Health Care' ) ); ?></a></li>
		</ul>
	</div>
</div>
<div class="sitemap_area cf">
	<div class="sitemap_left">
		<div class="line">
			<h2><a href="<?php echo $history_url; ?>"><?php echo esc_html( arkray_t( 'History of Pioneers' ) ); ?></a></h2>
		</div>
	</div>
	<div class="sitemap_right">
		<ul class="sitemap">
			<li><a href="<?php echo esc_url( arkray_get_history_page_url( 'diabetes-testing' ) ); ?>"><?php echo esc_html( arkray_t( 'Diabetes testing' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( arkray_get_history_page_url( 'urinalysis' ) ); ?>"><?php echo esc_html( arkray_t( 'Urinalysis' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( arkray_get_history_page_url( 'dry-chemistry-testing' ) ); ?>"><?php echo esc_html( arkray_t( 'Dry Chemistry Testing' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( arkray_get_history_page_url( 'bgm' ) ); ?>"><?php echo esc_html( arkray_t( 'BGM' ) ); ?></a></li>
		</ul>
	</div>
</div>
<div class="sitemap_area cf">
	<div class="sitemap_left">
		<div class="line">
			<h2><a href="<?php echo $events_page_url; ?>"><?php echo esc_html( arkray_t( 'Events & Gallery' ) ); ?></a></h2>
		</div>
	</div>
	<div class="sitemap_right">
		<ul class="sitemap">
			<li><a href="<?php echo $events_tab_url; ?>"><?php echo esc_html( arkray_t( 'Events' ) ); ?></a></li>
			<li><a href="<?php echo $gallery_tab_url; ?>"><?php echo esc_html( arkray_t( 'Media Gallery' ) ); ?></a></li>
		</ul>
	</div>
</div>
<div class="sitemap_area cf">
	<div class="sitemap_left">
		<div class="line">
			<h2><a href="<?php echo $about_page_url; ?>"><?php echo esc_html( arkray_t( 'About Us' ) ); ?></a></h2>
		</div>
	</div>
	<div class="sitemap_right">
		<ul class="sitemap">
			<li><a href="<?php echo esc_url( arkray_get_about_page_url( 'arkray-philosophy' ) ); ?>"><?php echo esc_html( arkray_t( 'ARKRAY Philosophy' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( arkray_get_about_page_url( 'message-from-arkray' ) ); ?>"><?php echo esc_html( arkray_t( 'Message from ARKRAY' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( arkray_get_about_page_url( 'brand-concept' ) ); ?>"><?php echo esc_html( arkray_t( 'Brand Concept' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( arkray_get_about_page_url( 'about-contact' ) ); ?>"><?php echo esc_html( arkray_t( 'Contact' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( arkray_get_about_page_url( 'corporate-outline' ) ); ?>"><?php echo esc_html( arkray_t( 'Corporate Outline' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( arkray_get_about_page_url( 'history' ) ); ?>"><?php echo esc_html( arkray_t( 'History' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( arkray_get_about_page_url( 'arkray-group' ) ); ?>"><?php echo esc_html( arkray_t( 'ARKRAY Group' ) ); ?></a></li>
			<li><a href="<?php echo $company_profile_pdf; ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( arkray_t( 'Download Company Profile [PDF]' ) ); ?></a></li>
		</ul>
	</div>
</div>
<div class="sitemap_area cf">
	<div class="sitemap_left">
		<div class="line">
			<h2><a href="<?php echo $sustainability_url; ?>"><?php echo esc_html( arkray_t( 'Sustainability' ) ); ?></a></h2>
		</div>
	</div>
	<div class="sitemap_right">
		<ul class="sitemap">
			<li><a href="<?php echo esc_url( arkray_get_sustainability_page_url( 'top-commitment' ) ); ?>"><?php echo esc_html( arkray_t( 'Top Commitment' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( arkray_get_sustainability_page_url( 'sdgs-basic-policy' ) ); ?>"><?php echo esc_html( arkray_t( 'SDGs Basic Policy' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( arkray_get_sustainability_page_url( 'arkrays-materiality' ) ); ?>"><?php echo esc_html( arkray_t( 'ARKRAY’s Materiality' ) ); ?></a></li>
			<li><a href="<?php echo esc_url( arkray_get_sustainability_page_url( 'sdgs-initiatives' ) ); ?>"><?php echo esc_html( arkray_t( 'SDGs Initiatives' ) ); ?></a></li>
		</ul>
	</div>
</div>
<div class="sitemap_area cf">
	<div class="sitemap_left">
		<h2><a href="<?php echo $recruitment_url; ?>"><?php echo esc_html( arkray_t( 'Recruitment' ) ); ?></a></h2>
	</div>
</div>
<div class="sitemap_area cf">
	<div class="sitemap_left">
		<div class="line">
			<h2><?php echo esc_html( arkray_t( 'Others' ) ); ?></h2>
		</div>
	</div>
	<div class="sitemap_right">
		<ul class="sitemap">
			<li><a href="<?php echo $privacy_url; ?>"><?php echo esc_html( arkray_t( 'Privacy Policy' ) ); ?></a></li>
			<li><a href="<?php echo $terms_url; ?>"><?php echo esc_html( arkray_t( 'Website Terms of Use' ) ); ?></a></li>
			<li><a href="<?php echo $contact_page_url; ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( arkray_t( 'Contact Us' ) ); ?></a></li>
		</ul>
	</div>
</div>
