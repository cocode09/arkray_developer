<?php
/**
 * Professional ARKRAY 404 page.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$home_url           = arkray_home_url( '/' );
$products_url       = arkray_pll_permalink( get_page_by_path( 'products' ) ) ?: arkray_home_url( '/products/' );
$news_url           = arkray_pll_permalink( get_page_by_path( 'news' ) ) ?: arkray_home_url( '/news/' );
$about_url          = arkray_get_about_page_url( 'about-us' );
$site_map_url       = arkray_get_site_map_url();
$contact_url        = arkray_get_contact_page_url();
$custom_logo_id     = get_theme_mod( 'custom_logo' );
$logo_src           = $custom_logo_id
	? wp_get_attachment_image_url( $custom_logo_id, 'full' )
	: get_stylesheet_directory_uri() . '/img/logo.jpg';
$requested_path     = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
$displayed_path     = $requested_path ? strtok( $requested_path, '?' ) : '';

add_filter(
	'document_title_parts',
	static function ( $parts ) {
		$parts['title'] = arkray_t( 'Page not found' );
		return $parts;
	}
);
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, follow">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'arkray-error-page' ); ?>>
<?php wp_body_open(); ?>

<a class="error-skip-link" href="#main-content"><?php echo esc_html__( 'Skip to content', 'hello-elementor' ); ?></a>

<div class="error-page-shell">
	<header class="error-header">
		<div class="error-header__inner">
			<a class="error-logo" href="<?php echo esc_url( $home_url ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<img src="<?php echo esc_url( $logo_src ); ?>" alt="ARKRAY">
			</a>
			<a class="error-header__home" href="<?php echo esc_url( $home_url ); ?>">
				<svg viewBox="0 0 24 24" aria-hidden="true">
					<path d="M3 11.5 12 4l9 7.5M5.5 10v9.5h13V10M9.5 19.5v-6h5v6"/>
				</svg>
				<span><?php echo esc_html( arkray_t( 'Back to home' ) ); ?></span>
			</a>
		</div>
	</header>

	<main id="main-content" class="error-main">
		<section class="error-hero" aria-labelledby="error-title">
			<div class="error-hero__content">
				<p class="error-code" aria-hidden="true">404</p>
				<p class="error-eyebrow"><?php echo esc_html( arkray_t( 'Page not found' ) ); ?></p>
				<h1 id="error-title"><?php echo esc_html( arkray_t( 'We could not find the page you were looking for.' ) ); ?></h1>
				<p class="error-intro"><?php echo esc_html( arkray_t( 'The address may be incorrect, or the page may have been moved. Use one of the options below to continue.' ) ); ?></p>

				<?php if ( $displayed_path ) : ?>
					<p class="error-path">
						<span><?php echo esc_html( arkray_t( 'Requested page' ) ); ?></span>
						<code><?php echo esc_html( $displayed_path ); ?></code>
					</p>
				<?php endif; ?>

				<div class="error-actions">
					<a class="error-button error-button--primary" href="<?php echo esc_url( $home_url ); ?>">
						<?php echo esc_html( arkray_t( 'Back to home' ) ); ?>
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
					</a>
					<a class="error-button error-button--secondary" href="<?php echo esc_url( $site_map_url ); ?>">
						<?php echo esc_html( arkray_t( 'View site map' ) ); ?>
					</a>
				</div>
			</div>

			<div class="error-hero__visual" aria-hidden="true">
				<div class="error-illustration">
					<div class="error-illustration__orbit error-illustration__orbit--one"></div>
					<div class="error-illustration__orbit error-illustration__orbit--two"></div>
					<svg viewBox="0 0 420 340">
						<path class="illustration-line" d="M81 231c47-51 68-78 95-123 12-20 26-42 51-42 30 0 38 32 53 55 18 27 37 50 68 82"/>
						<path class="illustration-line illustration-line--soft" d="M67 251h283"/>
						<circle class="illustration-dot" cx="87" cy="225" r="7"/>
						<circle class="illustration-dot illustration-dot--yellow" cx="348" cy="203" r="9"/>
						<rect class="illustration-card" x="142" y="106" width="140" height="112" rx="18"/>
						<path class="illustration-card-line" d="M175 146h74M175 166h50"/>
						<circle class="illustration-card-icon" cx="212" cy="196" r="8"/>
					</svg>
					<span class="error-illustration__label">404</span>
				</div>
			</div>
		</section>

		<section class="error-recovery" aria-label="<?php echo esc_attr( arkray_t( 'Popular destinations' ) ); ?>">
			<nav class="error-links" aria-labelledby="popular-destinations">
				<p id="popular-destinations"><?php echo esc_html( arkray_t( 'Popular destinations' ) ); ?></p>
				<ul>
					<li><a href="<?php echo esc_url( $products_url ); ?>"><?php echo esc_html( arkray_t( 'Products' ) ); ?><span aria-hidden="true">↗</span></a></li>
					<li><a href="<?php echo esc_url( $news_url ); ?>"><?php echo esc_html( arkray_t( 'News & Topics' ) ); ?><span aria-hidden="true">↗</span></a></li>
					<li><a href="<?php echo esc_url( $about_url ); ?>"><?php echo esc_html( arkray_t( 'About Us' ) ); ?><span aria-hidden="true">↗</span></a></li>
				</ul>
			</nav>
		</section>
	</main>

	<footer class="error-footer">
		<div class="error-footer__inner">
			<nav aria-label="<?php echo esc_attr( arkray_t( 'Footer navigation' ) ); ?>">
				<a href="<?php echo esc_url( arkray_get_privacy_policy_url() ); ?>"><?php echo esc_html( arkray_t( 'Privacy Policy' ) ); ?></a>
				<a href="<?php echo esc_url( arkray_get_terms_of_use_url() ); ?>"><?php echo esc_html( arkray_t( 'Website Terms of Use' ) ); ?></a>
				<a href="<?php echo esc_url( $contact_url ); ?>"><?php echo esc_html( arkray_t( 'Contact Us' ) ); ?></a>
			</nav>
			<p><?php printf( esc_html( arkray_t( 'Copyright© %s ARKRAY, Inc. All Rights Reserved.' ) ), esc_html( wp_date( 'Y' ) ) ); ?></p>
		</div>
	</footer>
</div>

<?php wp_footer(); ?>
</body>
</html>
