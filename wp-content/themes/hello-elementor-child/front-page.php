<?php
/**
 * Front Page Template — pixel-perfect mirror of arkray.co.jp/english/
 * HTML structure and class names match the original exactly.
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
$gallery_page_url   = esc_url( arkray_get_events_gallery_page_url( 'gallery' ) );
$about_page_url     = esc_url( arkray_get_about_page_url( 'about-us' ) );
$contact_page_url   = esc_url( arkray_get_contact_page_url() );
$sustainability_url = esc_url( arkray_get_sustainability_page_url() );
$history_url        = esc_url( arkray_get_history_page_url() );

// ── Logo ──────────────────────────────────────────────────────────────────
$custom_logo_id = get_theme_mod( 'custom_logo' );
$logo_src       = $custom_logo_id
	? wp_get_attachment_image_url( $custom_logo_id, 'full' )
	: get_stylesheet_directory_uri() . '/img/logo.jpg';

// ── Slider images ─────────────────────────────────────────────────────────
$uploads_dir  = WP_CONTENT_DIR . '/uploads/TOPページimg';
$uploads_url  = content_url( '/uploads/TOPページimg' );
$slide_defs   = array(
	array( 'file' => 'kyoto_lab.jpg',          'alt' => 'ARKRAY Kyoto Laboratory',              'url' => $about_page_url ),
	array( 'file' => 'laboratory.jpg',         'alt' => 'Main Research Center, Kyoto Japan',    'url' => esc_url( arkray_home_url( '/events_gallery/gallery/yousuien/' ) ) ),
	array( 'file' => 'ha-8190v.jpg',           'alt' => 'HA-8190V',                             'url' => ( $p = arkray_get_product_post_by_slug( 'ha-8190v' ) ) ? esc_url( get_permalink( $p->ID ) ) : $products_page_url ),
	array( 'file' => 'aiI-4510-ax-4060.png',   'alt' => 'Aution MAX AX-4060',                   'url' => ( $p = arkray_get_product_post_by_slug( 'ai-4510' ) ) ? esc_url( get_permalink( $p->ID ) ) : $products_page_url ),
	array( 'file' => 'ae-4070.png',            'alt' => 'Aution Eleven AE-4070',                'url' => ( $p = arkray_get_product_post_by_slug( 'ae-4070' ) ) ? esc_url( get_permalink( $p->ID ) ) : $products_page_url ),
	array( 'file' => 'om-6070.jpg',            'alt' => 'OSMO STATION OM-6070',                 'url' => ( $p = arkray_get_product_post_by_slug( 'om-6070' ) ) ? esc_url( get_permalink( $p->ID ) ) : $products_page_url ),
	array( 'file' => 'ha-8380v.jpg',           'alt' => 'HA-8380V',                             'url' => ( $p = arkray_get_product_post_by_slug( 'ha-8380v' ) ) ? esc_url( get_permalink( $p->ID ) ) : $products_page_url ),
);
$slides = array();
foreach ( $slide_defs as $slide_def ) {
	if ( file_exists( $uploads_dir . '/' . $slide_def['file'] ) ) {
		$slides[] = array(
			'src' => $uploads_url . '/' . rawurlencode( $slide_def['file'] ),
			'alt' => $slide_def['alt'],
			'url' => $slide_def['url'],
		);
	}
}
if ( empty( $slides ) ) {
	$slider_images = get_theme_mod( 'arkray_slider_images', array() );
	foreach ( $slider_images as $attachment_id ) {
		$src = wp_get_attachment_image_url( (int) $attachment_id, 'large' );
		if ( $src ) {
			$slides[] = array(
				'src' => $src,
				'alt' => get_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', true ) ?: '',
				'url' => $about_page_url,
			);
		}
	}
}

// ── Sidebar banners ───────────────────────────────────────────────────────
$theme_dir_uri = get_stylesheet_directory_uri();
$bnr01_src     = get_theme_mod( 'arkray_sidebar_health_image', 0 )
	? wp_get_attachment_image_url( get_theme_mod( 'arkray_sidebar_health_image', 0 ), 'medium' )
	: $theme_dir_uri . '/img/bnr01.jpg';
$bnr02_src     = $theme_dir_uri . '/img/bn_05.jpg';
$bnr03_src     = $theme_dir_uri . '/img/arkray4u_banner.jpg';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="format-detection" content="telephone=no">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title><?php bloginfo( 'name' ); ?></title>
<meta name="description" content="<?php bloginfo( 'description' ); ?>">
<?php wp_head(); ?>
</head>
<body>
<?php wp_body_open(); ?>

<div id="header" class="cf">
	<div class="header_left">
		<h1 class="logo">
			<a href="<?php echo esc_url( arkray_home_url( '/' ) ); ?>">
				<img src="<?php echo esc_url( $logo_src ); ?>" alt="arkray">
			</a>
		</h1>
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
				<option value="https://www.arkray.co.th/english/?ct=Thailand" title="Thailand">&nbsp;&nbsp;&nbsp;Thailand</option>
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
				<?php arkray_render_news_topics_menu_item(); ?>
				<li><a href="<?php echo $products_page_url; ?>"><?php echo esc_html( arkray_t( 'Products' ) ); ?></a></li>
				<li><a href="<?php echo $history_url; ?>"><?php echo esc_html( arkray_t( 'History of Pioneers' ) ); ?></a></li>
				<li><a href="<?php echo $events_page_url; ?>"><?php echo esc_html( arkray_t( 'Events & Gallery' ) ); ?></a></li>
				<li><a href="<?php echo $about_page_url; ?>"><?php echo esc_html( arkray_t( 'About Us' ) ); ?></a></li>
				<li><a href="<?php echo $sustainability_url; ?>"><?php echo esc_html( arkray_t( 'Sustainability' ) ); ?></a></li>
				<li><a href="<?php echo esc_url( arkray_get_recruitment_page_url() ); ?>"><?php echo esc_html( arkray_t( 'Recruitment' ) ); ?></a></li>
			</ul>
			<?php arkray_render_google_search( false ); ?>
			<div class="select">
				<select onchange="location.href=value">
					<option value="<?php echo esc_url( home_url( '/?ct=Vietnam' ) ); ?>" selected="selected">Vietnam</option>
					<option value="https://www.arkray.asia/english/index.html?ct=Asia">Asia Pacific</option>
					<option value="https://arkrayusa.com">North America</option>
					<option value="https://www.arkray.eu/english/index.html?ct=Europe">Europe</option>
				</select>
			</div>
		</div>
	</div>
</div>

<div id="mainvisual_content" class="cf">
	<div id="mainvisual">
		<ul>
			<?php foreach ( $slides as $slide ) : ?>
			<li>
				<a href="<?php echo esc_url( $slide['url'] ); ?>">
					<img src="<?php echo esc_url( $slide['src'] ); ?>" alt="<?php echo esc_attr( $slide['alt'] ); ?>">
				</a>
			</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<ul id="g_menu">
		<li><a href="<?php echo $news_page_url; ?>"><?php echo esc_html( arkray_t( 'News & Topics' ) ); ?></a></li>
		<li><a href="<?php echo $products_page_url; ?>"><?php echo esc_html( arkray_t( 'Products' ) ); ?></a></li>
		<li><a href="<?php echo $history_url; ?>"><?php echo esc_html( arkray_t( 'History of Pioneers' ) ); ?></a></li>
		<li><a href="<?php echo $events_page_url; ?>"><?php echo esc_html( arkray_t( 'Events & Gallery' ) ); ?></a></li>
		<li><a href="<?php echo $about_page_url; ?>"><?php echo esc_html( arkray_t( 'About Us' ) ); ?></a></li>
		<li><a href="<?php echo $sustainability_url; ?>"><?php echo esc_html( arkray_t( 'Sustainability' ) ); ?></a></li>
				<li><a href="<?php echo esc_url( arkray_get_recruitment_page_url() ); ?>"><?php echo esc_html( arkray_t( 'Recruitment' ) ); ?></a></li>
	</ul>
</div>

<div id="top_content" class="cf">
	<div class="top_left">

		<!-- NEWS & TOPICS -->
		<h2 class="news"><?php echo esc_html( arkray_t( 'News & Topics' ) ); ?></h2>
		<div class="top_newsarea">
			<?php
			$home_news = new WP_Query( array(
				'post_type'      => 'news',
				'posts_per_page' => 5,
				'meta_key'       => 'news_date',
				'orderby'        => 'meta_value',
				'order'          => 'DESC',
			) );
			$news_index = 0;
			if ( $home_news->have_posts() ) :
				while ( $home_news->have_posts() ) :
					$home_news->the_post();
					$news_terms    = get_the_terms( get_the_ID(), 'news_category' );
					$news_date_raw = get_field( 'news_date' );
					$news_date     = $news_date_raw ? date_i18n( 'M d, Y', strtotime( $news_date_raw ) ) : get_the_date( 'M d, Y' );
					$news_ext_url  = get_field( 'news_external_url' );
					$news_link     = $news_ext_url ? $news_ext_url : get_permalink();
					$news_thumb    = get_the_post_thumbnail_url( get_the_ID(), 'thumbnail' );
					?>
					<div class="box">
						<p class="tag">
							<?php if ( 0 === $news_index ) : ?>
								<span class="new">NEW</span>
							<?php endif; ?>
							<?php if ( ! empty( $news_terms ) && ! is_wp_error( $news_terms ) ) : ?>
								<?php foreach ( $news_terms as $term ) : ?>
									<span><?php echo esc_html( $term->name ); ?></span>
								<?php endforeach; ?>
							<?php else : ?>
								<span><?php echo esc_html( arkray_t( 'Local' ) ); ?></span>
							<?php endif; ?>
						</p>
						<p class="date"><?php echo esc_html( $news_date ); ?></p>
						<?php if ( $news_thumb ) : ?>
							<p class="tx"><a href="<?php echo esc_url( $news_link ); ?>"><?php echo esc_html( get_the_title() ); ?></a></p>
							<p class="img"><img src="<?php echo esc_url( $news_thumb ); ?>" alt=""></p>
						<?php else : ?>
							<p class="tx_long"><a href="<?php echo esc_url( $news_link ); ?>"><?php echo esc_html( get_the_title() ); ?></a></p>
						<?php endif; ?>
					</div>
					<?php
					$news_index++;
				endwhile;
				wp_reset_postdata();
			endif;
			?>
		</div>
		<p class="right_more"><a href="<?php echo $news_page_url; ?>"><?php echo esc_html( arkray_t( 'more' ) ); ?></a></p>

		<!-- PRODUCTS -->
		<div class="top_productarea">
			<h2><?php echo esc_html( arkray_t( 'Products' ) ); ?></h2>
			<div class="line">
				<?php
				$product_categories = get_terms( array(
					'taxonomy'   => 'product_category',
					'hide_empty' => false,
					'number'     => 4,
					'orderby'    => 'term_order',
					'order'      => 'ASC',
				) );

				// Fallback: hardcoded original 4 categories if no WP terms
				if ( empty( $product_categories ) || is_wp_error( $product_categories ) ) {
					$product_categories = array(
						(object) array(
							'name'    => 'Laboratory Testing',
							'slug'    => 'laboratory-testing',
							'term_id' => 0,
							'_img'    => $theme_dir_uri . '/img/ha8190v-65.png',
							'_url'    => $products_page_url,
						),
						(object) array(
							'name'    => 'Near Patient Testing',
							'slug'    => 'near-patient-testing',
							'term_id' => 0,
							'_img'    => $theme_dir_uri . '/img/se_1520_s.jpg',
							'_url'    => $products_page_url,
						),
						(object) array(
							'name'    => 'BGM',
							'slug'    => 'bgm',
							'term_id' => 0,
							'_img'    => $theme_dir_uri . '/img/GLUCOCARD-S_65.jpg',
							'_url'    => $products_page_url,
						),
						(object) array(
							'name'    => 'Oral care',
							'slug'    => 'oral-care',
							'term_id' => 0,
							'_img'    => $theme_dir_uri . '/img/st4910_140_e.jpg',
							'_url'    => 'http://arkraydental.com/',
						),
					);
				}

				$is_first = true;
				foreach ( $product_categories as $pcat ) :
					if ( ! $is_first ) : ?>
						<a class="space">&nbsp;</a>
					<?php endif;
					$is_first = false;

					// Image: ACF field or first product thumbnail
					if ( isset( $pcat->_img ) ) {
						$cat_img = $pcat->_img;
						$cat_url = $pcat->_url;
					} else {
						$cat_img = get_term_meta( $pcat->term_id, 'category_image', true );
						if ( ! $cat_img ) {
							$cat_img = get_field( 'category_image', 'product_category_' . $pcat->term_id );
						}
						if ( ! $cat_img ) {
							$first_p = get_posts( array(
								'post_type'      => 'product',
								'posts_per_page' => 1,
								'fields'         => 'ids',
								'tax_query'      => array( array(
									'taxonomy' => 'product_category',
									'field'    => 'term_id',
									'terms'    => $pcat->term_id,
								) ),
							) );
							$cat_img = ! empty( $first_p ) ? get_the_post_thumbnail_url( $first_p[0], 'thumbnail' ) : '';
						}
						// Language-aware pretty category directory
						// (e.g. /english/products/diabetes/); fall back to the
						// ?pcat= form for categories without an origin mapping.
						$cat_url = arkray_get_product_category_url( $pcat->slug );
						if ( $cat_url === $products_page_url ) {
							$cat_url = add_query_arg( 'pcat', $pcat->slug, $products_page_url );
						}
						$cat_url = esc_url( $cat_url );
					}
					?>
					<a href="<?php echo esc_url( $cat_url ); ?>" class="product_link">
						<div class="black_highlight">&nbsp;</div>
						<div class="box">
							<p class="img">
								<?php if ( $cat_img ) : ?>
									<img src="<?php echo esc_url( $cat_img ); ?>" alt="<?php echo esc_attr( $pcat->name ); ?>" width="65">
								<?php endif; ?>
							</p>
							<p class="tx"><?php echo esc_html( $pcat->name ); ?></p>
						</div>
					</a>
				<?php endforeach; ?>
			</div>
			<p class="right_more"><a href="<?php echo $products_page_url; ?>"><?php echo esc_html( arkray_t( 'more' ) ); ?></a></p>
		</div>

		<!-- EVENTS -->
		<div class="top_column cf">
			<div class="top_eventarea_wide">
				<h2><?php echo esc_html( arkray_t( 'Events' ) ); ?></h2>
				<div class="top_eventbox">
					<?php
					$home_events = new WP_Query( array(
						'post_type'      => 'event',
						'posts_per_page' => 2,
						'meta_key'       => 'event_date',
						'orderby'        => 'meta_value',
						'order'          => 'DESC',
					) );
					if ( $home_events->have_posts() ) :
						while ( $home_events->have_posts() ) :
							$home_events->the_post();
							$event_date_raw = get_field( 'event_date' );
							$event_date     = $event_date_raw ? date_i18n( 'M d, Y', strtotime( $event_date_raw ) ) : get_the_date( 'M d, Y' );
							$event_location = get_field( 'event_location' );
							$event_ext_url  = get_field( 'event_external_url' );
							$event_link     = $event_ext_url ? $event_ext_url : get_permalink();
							$location_class = $event_location ? sanitize_html_class( $event_location ) : 'no_flag';
							?>
							<div class="box">
								<p class="date">
									<span class="<?php echo esc_attr( $location_class ); ?>">
										<?php echo esc_html( $event_date ); ?>
									</span>
								</p>
								<p class="tx">
									<a href="<?php echo esc_url( $event_link ); ?>"><?php echo esc_html( get_the_title() ); ?></a>
								</p>
							</div>
						<?php
						endwhile;
						wp_reset_postdata();
					endif;
					?>
				</div>
				<p class="right_more"><a href="<?php echo $events_page_url; ?>"><?php echo esc_html( arkray_t( 'more' ) ); ?></a></p>
			</div>
		</div>

	</div><!-- .top_left -->

	<div class="top_right">

		<!-- MEDIA GALLERY VIDEO -->
		<div class="top_movarea">
			<div class="mov">
				<iframe id="player" frameborder="0" allowfullscreen
					allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
					title="arkray kyoto laboratory" width="240" height="135"
					src="https://www.youtube.com/embed/AimotUBS15Q?loop=1&playlist=AimotUBS15Q&mute=1&autoplay=1">
				</iframe>
			</div>
			<p class="media_link"><a href="<?php echo $gallery_page_url; ?>"><?php echo esc_html( arkray_t( 'Media Gallery' ) ); ?></a></p>
		</div>

		<!-- BANNERS -->
		<div class="top_bnrarea">
			<p><a href="https://ebn2.arkray.co.jp/english/" target="_blank">
				<img src="<?php echo esc_url( $bnr01_src ); ?>" alt="Health バナー">
			</a></p>
			<p><a href="https://www.arkray.co.jp/yousuien_english/index.html" target="_blank">
				<img src="<?php echo esc_url( $bnr02_src ); ?>" alt="YOUSUIEN">
			</a></p>
			<p><a href="https://www.arkray4u.com/cvc/" target="_blank">
				<img src="<?php echo esc_url( $bnr03_src ); ?>" alt="ARKRAY 4U">
			</a></p>
		</div>

	</div><!-- .top_right -->
</div><!-- #top_content -->

<div id="footer">
	<div class="footer_link">
		<ul>
			<?php
			$privacy_page  = get_page_by_path( 'privacy-policy' );
			$terms_page    = get_page_by_path( 'terms-of-use' );
			$sitemap_page  = get_page_by_path( 'site-map' );
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
	<img src="<?php echo esc_url( $theme_dir_uri ); ?>/img/pagetop.jpg" alt="" width="35" height="35">
</p>

<?php wp_footer(); ?>
</body>
</html>
