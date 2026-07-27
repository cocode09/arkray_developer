<?php
/**
 * Shared chrome footer for the Recruitment views. Closes #content_wrapper and
 * renders the verbatim ARKRAY footer.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$contact_page_url = esc_url( arkray_get_contact_page_url() );
$privacy_page     = get_page_by_path( 'privacy-policy' );
$terms_page       = get_page_by_path( 'website-terms-of-use' );
if ( ! $terms_page ) {
	$terms_page = get_page_by_path( 'terms-of-use' );
}
$sitemap_page = get_page_by_path( 'site-map' );
?>
</div>

<div id="footer">
	<div class="footer_link">
		<ul>
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
