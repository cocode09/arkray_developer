<?php
/**
 * Single Product Template
 *
 * Verbatim port of arkray.co.jp/english/products/.../[product].html — uses the
 * original IDs (#header, #content_wrapper, #g_menu, #content_area,
 * #content_product, #content_product_left, #content_product_right,
 * #editor_area, #footer) and original classes (.h1_content, .h2_content,
 * .tbl01, .product_item, .contact_area, .youtube, .bold, .mb0, .align_r) so
 * the verbatim CSS in arkray-content.css matches without drift.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

the_post();

// ── Page URLs ─────────────────────────────────────────────────────────────
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

// ── Current product data ─────────────────────────────────────────────────
$product_id       = get_the_ID();
$product_title    = get_the_title();
// Read post_excerpt directly to avoid WP auto-generating from post_content
// when the field is empty (which would dump the whole body text into the h1).
$product_excerpt  = (string) get_post_field( 'post_excerpt', $product_id );
$product_features       = (string) get_field( 'product_features' );
$product_features_after = (string) get_field( 'product_features_after' );
$product_section  = (string) get_field( 'product_section_title' );
$product_speccode  = (string) get_field( 'product_spec_code' );
$product_footnotes = (string) get_field( 'product_spec_footnotes' );

// Fallback: extract regulatory/spec code from the first PDF name when ACF
// `product_spec_code` is empty. Original site stores it as the text inside
// Japanese full-width parens (e.g. "ADAMS A1c HA-8190V（AG220425-HA8190V-EN-A）")
// or ASCII parens. This avoids a manual ACF field per product.
if ( '' === $product_speccode ) {
	$first_pdf_name = '';
	$pdfs_for_code  = (array) get_field( 'product_pdfs' );
	if ( ! empty( $pdfs_for_code[0]['name'] ) ) {
		$first_pdf_name = (string) $pdfs_for_code[0]['name'];
	}
	if ( $first_pdf_name && preg_match( '/[（(]([^（）()]+)[）)]\s*$/u', $first_pdf_name, $m ) ) {
		$candidate = trim( $m[1] );
		// Real regulatory codes look like "AG211206-01A" — alphanumeric with
		// at least one digit AND at least one hyphen. This rejects descriptive
		// suffixes like "(Dipstick)" or "(Slide / Tube)" that happen to live
		// inside the same parentheses pattern.
		if ( preg_match( '/\d/', $candidate ) && preg_match( '/-/', $candidate ) ) {
			$product_speccode = $candidate;
		}
	}
}
$main_thumb_url   = arkray_get_product_detail_main_image_url( $product_id );
$specs            = (array) get_field( 'product_specs' );
$pdfs             = (array) get_field( 'product_pdfs' );
$raw_body         = get_post_field( 'post_content', $product_id );

// Long display name (e.g. "ADAMS A1c HA-8190V"). Stored in post_excerpt when
// the brand prefix differs from post_title. Falls back to post_title.
$product_long_name = '' !== trim( (string) $product_excerpt ) ? trim( (string) $product_excerpt ) : $product_title;

// Current category
$cat_terms   = get_the_terms( $product_id, 'product_category' );
$current_cat = ( $cat_terms && ! is_wp_error( $cat_terms ) ) ? $cat_terms[0] : null;

// ── External overrides (matches template-products.php) ───────────────────
$external_cat_links = array(
	'oral-care' => 'http://arkraydental.com/',
);

$_theme_img = get_stylesheet_directory_uri() . '/img';
$category_images = array(
	'laboratory-testing'          => $_theme_img . '/8190v_w140.jpg',
	'near-patient-testing'        => $_theme_img . '/se_1520.jpg',
	'urinalysis'                  => $_theme_img . '/ha-8180v_w140.png',
	'urinalysis_urine_testing'    => $_theme_img . '/ha-8180v_w140.png',
	'bgm'                         => $_theme_img . '/GLUCOCARD-S_140.jpg',
	'oral-care'                   => $_theme_img . '/st4910_140_e.jpg',
	'veterinary-others'           => $_theme_img . '/RT-4010_130px.jpg',
	'immunodiagnostic-products'   => $_theme_img . '/Crystal_HBsAg1_w140.jpg',
	'clinical-chemistry-reagents' => $_theme_img . '/om-6070_w140.png',
	'primary-health-care'         => $_theme_img . '/thelab004_130.jpg',
);

// ── Build full product catalog (same as template-products.php) ───────────
$product_catalog   = array();
$product_cat_terms = get_terms( array(
	'taxonomy'   => 'product_category',
	'hide_empty' => false,
	'orderby'    => 'term_order',
	'order'      => 'ASC',
) );

if ( ! is_wp_error( $product_cat_terms ) && ! empty( $product_cat_terms ) ) {
	foreach ( $product_cat_terms as $product_term ) {
		$products_in_cat = new WP_Query( array(
			'post_type'      => 'product',
			'posts_per_page' => -1,
			'tax_query'      => array(
				array(
					'taxonomy' => 'product_category',
					'field'    => 'term_id',
					'terms'    => $product_term->term_id,
				),
			),
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		) );

		$sections        = array();
		$section_idx_map = array();

		if ( $products_in_cat->have_posts() ) {
			while ( $products_in_cat->have_posts() ) {
				$products_in_cat->the_post();
				$section_title = (string) get_field( 'product_section_title' );
				if ( '' === $section_title ) {
					$section_title = $product_term->name;
				}
				if ( ! isset( $section_idx_map[ $section_title ] ) ) {
					$section_idx_map[ $section_title ] = count( $sections );
					$sections[] = array( 'title' => $section_title, 'items' => array() );
				}
				$sections[ $section_idx_map[ $section_title ] ]['items'][] = array(
					'name' => get_the_title(),
					'link' => get_permalink(),
					'id'   => get_the_ID(),
				);
			}
			wp_reset_postdata();
		}

		// Match the main page: link category cards to the language-aware
		// pretty category directory (e.g. /english/products/diabetes/) via
		// arkray_get_product_category_url(). Categories without an origin
		// mapping fall back to the ?pcat= form on the products page.
		$cat_link = isset( $external_cat_links[ $product_term->slug ] )
			? $external_cat_links[ $product_term->slug ]
			: arkray_get_product_category_url( $product_term->slug );
		if ( $cat_link === $products_page_url ) {
			$cat_link = add_query_arg( 'pcat', $product_term->slug, $products_page_url );
		}

		$product_catalog[] = array(
			'slug'     => $product_term->slug,
			'title'    => $product_term->name,
			'link'     => $cat_link,
			'sections' => $sections,
			'image'    => isset( $category_images[ $product_term->slug ] ) ? $category_images[ $product_term->slug ] : '',
		);
	}
}

// ── Normalize post_content classes to verbatim-original markup ────────────
// post_content sometimes uses helper classes (product-youtube, product-subhead)
// instead of the verbatim originals (youtube, bold mb0). Map them inline so
// the rendered DOM matches without requiring DB migrations.
$body_html = (string) $raw_body;
$body_html = preg_replace( '/<div class="product-youtube"([^>]*)>/i', '<div class="youtube"$1>', $body_html );
$body_html = preg_replace( '/<h4 class="product-subhead"([^>]*)>/i', '<p class="bold mb0"$1>', $body_html );
$body_html = preg_replace( '/<\/h4>/i', '</p>', $body_html );
// Original site wraps the intro paragraph in <p class="bold mb10"> — the
// `bold` class makes the surrounding text bold so combined with the inner
// <strong> the whole block renders with the verbatim heavy weight.
$body_html = preg_replace( '/<p class="product-intro"([^>]*)>/i', '<p class="bold mb10"$1>', $body_html );

// IMPORTANT: post_content for products already contains hand-authored <p>
// tags matching the verbatim original markup. WordPress's wpautop filter
// would otherwise convert every \n into a <br>, breaking lines mid-paragraph
// vs the original where newlines collapse as whitespace. We run all the
// usual `the_content` filters EXCEPT wpautop.
remove_filter( 'the_content', 'wpautop' );
$body_html = apply_filters( 'the_content', $body_html );
add_filter( 'the_content', 'wpautop' ); // restore for other parts of the page
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="format-detection" content="telephone=no">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title><?php echo esc_html( $product_long_name ); ?> | <?php bloginfo( 'name' ); ?></title>
<meta name="description" content="<?php bloginfo( 'description' ); ?>">
<?php wp_head(); ?>
</head>
<body class="arkray-inner arkray-products-page arkray-products-single-page">
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
				<?php arkray_render_news_topics_menu_item(); ?>
				<li><a href="<?php echo $products_page_url; ?>" class="ac"><?php echo esc_html( arkray_t( 'Products' ) ); ?></a>
					<ul style="display: block;">
						<?php foreach ( $product_catalog as $pcat ) :
							$_sp_url    = $pcat['link'] ?: $products_page_url;
							$_sp_is_ext = $_sp_url && 0 !== strpos( $_sp_url, home_url() ) && 0 === strpos( $_sp_url, 'http' );
						?>
							<li><a href="<?php echo esc_url( $_sp_url ); ?>"<?php echo $_sp_is_ext ? ' target="_blank" class="product_link"' : ''; ?>><?php echo esc_html( $pcat['title'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</li>
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
		<li><a href="<?php echo $news_page_url; ?>"><?php echo esc_html( arkray_t( 'News & Topics' ) ); ?></a></li>
		<li><a href="<?php echo $products_page_url; ?>" class="ac"><?php echo esc_html( arkray_t( 'Products' ) ); ?></a>
			<ul style="display: block;">
				<?php foreach ( $product_catalog as $pcat ) :
					$pcat_url     = $pcat['link'] ?: $products_page_url;
					$pcat_is_ext  = $pcat_url && 0 !== strpos( $pcat_url, home_url() ) && 0 === strpos( $pcat_url, 'http' );
					$pcat_active  = $current_cat && $current_cat->slug === $pcat['slug'];
				?>
					<li>
						<a href="<?php echo esc_url( $pcat_url ); ?>"<?php echo $pcat_active ? ' class="ac"' : ( $pcat_is_ext ? ' target="_blank" class="product_link"' : '' ); ?>><?php echo esc_html( $pcat['title'] ); ?></a>
						<?php if ( ! empty( $pcat['sections'] ) ) : ?>
							<ul<?php echo $pcat_active ? ' style="display: block;"' : ''; ?>>
								<?php foreach ( $pcat['sections'] as $section ) : ?>
									<?php foreach ( $section['items'] as $item ) :
										$item_active = ( (int) $item['id'] === (int) $product_id );
									?>
										<li>
											<?php if ( ! empty( $item['link'] ) ) : ?>
												<a href="<?php echo esc_url( $item['link'] ); ?>"<?php echo $item_active ? ' class="ac"' : ''; ?>><?php echo esc_html( $item['name'] ); ?></a>
											<?php else : ?>
												<?php echo esc_html( $item['name'] ); ?>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</li>
		<li><a href="<?php echo $history_url; ?>"><?php echo esc_html( arkray_t( 'History of Pioneers' ) ); ?></a></li>
		<li><a href="<?php echo $events_page_url; ?>"><?php echo esc_html( arkray_t( 'Events & Gallery' ) ); ?></a></li>
		<li><a href="<?php echo $about_page_url; ?>"><?php echo esc_html( arkray_t( 'About Us' ) ); ?></a></li>
		<li><a href="<?php echo $sustainability_url; ?>"><?php echo esc_html( arkray_t( 'Sustainability' ) ); ?></a></li>
				<li><a href="<?php echo esc_url( arkray_get_recruitment_page_url() ); ?>"><?php echo esc_html( arkray_t( 'Recruitment' ) ); ?></a></li>
	</ul>

	<div id="content_area">
		<h1 class="h1_content"><?php
			// Verbatim original structure:
			//   {features_prefix_line}<br>...
			//   {model_long_name}<br>
			//   {features_after_line}<br>... (when present)
			$h1_lines_before = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $product_features ) ) );
			$h1_lines_after  = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $product_features_after ) ) );
			foreach ( $h1_lines_before as $line ) {
				echo esc_html( $line ) . '<br>';
			}
			echo esc_html( $product_long_name ) . '<br>';
			foreach ( $h1_lines_after as $line ) {
				echo esc_html( $line ) . '<br>';
			}
		?></h1>
		<div id="content_product" class="cf">
			<div id="content_product_right">
				<?php if ( $main_thumb_url ) : ?>
					<p class="tx"><img src="<?php echo esc_url( $main_thumb_url ); ?>" alt="" width="163" height="163"></p>
				<?php endif; ?>
				<?php
				// Additional right-column images extracted from reference scrape
				// via migrate-product-detail-images.php — newline-separated URLs.
				$extra_imgs_blob = (string) get_field( 'product_detail_extra_images' );
				if ( '' !== $extra_imgs_blob ) {
					$extra_imgs = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $extra_imgs_blob ) ), 'strlen' ) );
					foreach ( $extra_imgs as $extra_url ) {
						$extra_url = arkray_resolve_product_image_url( $extra_url );
						if ( '' === $extra_url ) {
							continue;
						}
						echo '<p class="tx"><img src="' . esc_url( $extra_url ) . '" alt="" width="163" height="163"></p>';
					}
				}
				?>
			</div>
			<div id="content_product_left">
				<div id="editor_area">
					<?php echo $body_html; // Already filtered via the_content + class normalization. ?>

					<?php if ( ! empty( $specs ) ) : ?>
						<h3>Specifications</h3>
						<table class="tbl01">
							<tbody>
								<?php foreach ( $specs as $spec ) :
									$label = isset( $spec['label'] ) ? (string) $spec['label'] : '';
									$value = isset( $spec['value'] ) ? (string) $spec['value'] : '';
									if ( '' === $label && '' === $value ) { continue; }
								?>
									<tr>
										<th><?php echo nl2br( esc_html( $label ) ); ?></th>
										<td><?php echo nl2br( esc_html( $value ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<div class="mb20"></div>
					<?php endif; ?>

					<?php if ( '' !== $product_footnotes ) : ?>
						<?php echo $product_footnotes; // Verbatim <p class="txtIn2">…</p> blocks from reference. ?>
					<?php endif; ?>

					<?php if ( '' !== $product_speccode ) : ?>
						<p style="font-size: 10px;" class="align_r"><?php echo esc_html( $product_speccode ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<?php if ( ! empty( $pdfs ) ) : ?>
			<h2 class="h2_content"><?php echo esc_html( arkray_t( 'Download for more information' ) ); ?></h2>
			<?php foreach ( $pdfs as $pdf ) :
				$pdf_name = isset( $pdf['name'] ) ? (string) $pdf['name'] : '';
				$pdf_url  = '';
				if ( ! empty( $pdf['attachment_id'] ) ) {
					$pdf_url = wp_get_attachment_url( (int) $pdf['attachment_id'] );
				} elseif ( ! empty( $pdf['url'] ) ) {
					$pdf_url = (string) $pdf['url'];
				}
				if ( '' === $pdf_url || '' === $pdf_name ) { continue; }
			?>
				<p class="product_item"><a href="<?php echo esc_url( $pdf_url ); ?>" target="_blank"><?php echo esc_html( $pdf_name ); ?></a></p>
			<?php endforeach; ?>
		<?php endif; ?>

		<div class="contact_area">
			<p><a href="<?php echo $contact_page_url; ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( arkray_t( 'Contact Us' ) ); ?></a></p>
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
