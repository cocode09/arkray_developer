<?php
/**
 * Template Name: Products
 *
 * Verbatim port of arkray.co.jp/english/products/index.html — uses the
 * original IDs (#header, #content_wrapper, #g_menu, #content_area, #footer)
 * and original classes (.product_lineup, .lineup_list, .list, .box, .img,
 * .h1_product, .h2_content_nm, .contact_area, .footer_link, .copyright)
 * so the verbatim CSS in arkray-content.css matches.
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

// ── Known category slug → external URL overrides ─────────────────────────
// Some categories link to external sites (arkraydental.com). These override
// the WP term link so the rendered anchor matches the original exactly.
$external_cat_links = array(
	'oral-care' => 'http://arkraydental.com/',
);

// ── Authoritative category images (verbatim from original site) ───────────
// These override whatever the WP ACF field or product thumbnail provides.
// Images are already copied into wp-content/themes/hello-elementor-child/img/
$_theme_img = get_stylesheet_directory_uri() . '/img';
$category_images = array(
	'laboratory-testing'        => $_theme_img . '/8190v_w140.jpg',
	'near-patient-testing'        => $_theme_img . '/se_1520.jpg',
	'urinalysis'                  => $_theme_img . '/se_1520.jpg',
	'urinalysis_urine_testing'    => $_theme_img . '/se_1520.jpg',
	'bgm'                       => $_theme_img . '/GLUCOCARD-S_140.jpg',
	'oral-care'                 => $_theme_img . '/st4910_140_e.jpg',
	'veterinary-others'         => $_theme_img . '/RT-4010_130px.jpg',
	'immunodiagnostic-products' => $_theme_img . '/Crystal_HBsAg1_w140.jpg',
	'clinical-chemistry-reagents' => $_theme_img . '/Coagulation-Reagents-New_140.jpg',
	'primary-health-care'       => $_theme_img . '/thelab004_130.jpg',
);

// ── Build product catalog from product_category taxonomy + product CPT ────
// Each category becomes a .product_lineup > .box rendered with the original
// lineup_list markup. Products grouped by their `product_section_title` ACF
// field become <h4> groups inside .list.
$product_catalog = array();

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
		$first_thumb_url = '';

		if ( $products_in_cat->have_posts() ) {
			while ( $products_in_cat->have_posts() ) {
				$products_in_cat->the_post();

				$section_title = (string) get_field( 'product_section_title' );
				if ( '' === $section_title ) {
					$section_title = $product_term->name;
				}

				if ( ! isset( $section_idx_map[ $section_title ] ) ) {
					$section_idx_map[ $section_title ] = count( $sections );
					$sections[] = array(
						'title' => $section_title,
						'items' => array(),
					);
				}

				if ( '' === $first_thumb_url ) {
					$thumb_url = get_the_post_thumbnail_url( null, 'medium' );
					if ( $thumb_url ) {
						$first_thumb_url = $thumb_url;
					}
				}

				$sections[ $section_idx_map[ $section_title ] ]['items'][] = array(
					'name' => get_the_title(),
					'link' => get_permalink(),
					'id'   => get_the_ID(),
				);
			}
			wp_reset_postdata();
		}

		// Authoritative image overrides always win; fall back to ACF then first product thumbnail.
		if ( isset( $category_images[ $product_term->slug ] ) ) {
			$category_image_url = $category_images[ $product_term->slug ];
		} else {
			$category_image_url = (string) get_field( 'category_image', 'product_category_' . $product_term->term_id );
			if ( '' === $category_image_url && '' !== $first_thumb_url ) {
				$category_image_url = $first_thumb_url;
			}
		}

		// Use the language-aware pretty category directory
		// (e.g. /english/products/diabetes/) so each category links to its
		// own directory. arkray_get_product_category_url() returns a
		// language-prefixed path that resolves via the rewrite rules in
		// arkray_add_origin_category_rewrites() and is detected as internal.
		// Categories without an origin mapping fall back to the ?pcat= form.
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
			'image'    => $category_image_url,
		);
	}
}

// ── Patch known DB gaps to match original structure exactly ──────────────────
// The original products/index.html has specific h4 sections that may be absent
// from the WP DB if no product posts exist for that section title yet.
// We inject them here so the rendered markup stays pixel-perfect regardless of
// DB population state.
if ( ! empty( $product_catalog ) ) {
	// Patch helper: prepend/append a section to a category by slug.
	$patch_section = function( $slug, $position, $title, $items = array() ) use ( &$product_catalog ) {
		foreach ( $product_catalog as &$pcat ) {
			if ( $pcat['slug'] !== $slug ) { continue; }
			// Skip if already present.
			foreach ( $pcat['sections'] as $sec ) {
				if ( $sec['title'] === $title ) { return; }
			}
			$new_sec = array( 'title' => $title, 'items' => $items );
			if ( $position === 'prepend' ) {
				array_unshift( $pcat['sections'], $new_sec );
			} else {
				$pcat['sections'][] = $new_sec;
			}
		}
		unset( $pcat );
	};

	// Blood Glucose Monitoring: original has "Test Strip" with one product.
	$patch_section( 'bgm', 'append', 'Test Strip', array(
		array(
			'name' => 'GLUCOCARD S test strip',
			'link' => 'http://www.arkray.co.jp/smbg/test_strip/',
		),
	) );
}

// Fallback static catalog (only used while WP content is empty) ───────────
// Mirrors the 8 categories / h4 sections / products from the original
// arkray.co.jp/english/products/index.html. Product detail pages aren't
// modelled in WP yet, so individual product `<li>` items render without
// links. Category cards link to the products page filtered by ?pcat=<slug>,
// matching the rewrite rules registered in functions.php
// (arkray_origin_product_slug_map / arkray_add_origin_category_rewrites).
if ( empty( $product_catalog ) ) {
	$theme_img_url    = get_stylesheet_directory_uri() . '/img';
	$pcat_url = function( $origin_slug ) {
		return home_url( '/products/' . $origin_slug . '/index.html' );
	};
	// One image still lives only in scraped assets because its filename
	// contains a URL-encoded space (Crystal%20HBsAg1_w140.jpg) and could
	// not be copied into the theme img dir from the sandbox. Serve it
	// from the scraped asset path which Apache exposes under the project
	// root. When the corresponding product category gets a featured
	// image via WP-admin it will override this path automatically.
	$crystal_hbs_url = $theme_img_url . '/Crystal_HBsAg1_w140.jpg';

	$product_catalog = array(
		array(
			'slug'  => 'laboratory-testing',
			'title' => 'Laboratory Testing',
			'link'  => $pcat_url( 'diabetes' ),
			'image' => $theme_img_url . '/8190v_w140.jpg',
			'sections' => array(
				array( 'title' => 'HbA1c', 'items' => array(
					array( 'name' => 'HA-8190V',                'link' => '' ),
					array( 'name' => 'HA-8180T',                'link' => '' ),
					array( 'name' => 'HA-8380V',                'link' => '' ),
					array( 'name' => 'PocketChem A1c Advanced', 'link' => '' ),
					array( 'name' => 'PocketChem A1c',          'link' => '' ),
				) ),
				array( 'title' => 'Urine Chemistry', 'items' => array(
					array( 'name' => 'AX-4060', 'link' => '' ),
					array( 'name' => 'AX-4030', 'link' => '' ),
					array( 'name' => 'AE-4070', 'link' => '' ),
					array( 'name' => 'AE-4020', 'link' => '' ),
				) ),
				array( 'title' => 'Urine Sediment', 'items' => array(
					array( 'name' => 'AI-4510', 'link' => '' ),
				) ),
				array( 'title' => 'Urinalysis (Test Strips)', 'items' => array(
					array( 'name' => 'Urine test strips series', 'link' => '' ),
				) ),
				array( 'title' => 'Osmolality', 'items' => array(
					array( 'name' => 'OM-6070', 'link' => '' ),
					array( 'name' => 'OM-6060', 'link' => '' ),
				) ),
			),
		),
		array(
			'slug'  => 'urinalysis',
			'title' => 'Near Patient Testing',
			'link'  => $pcat_url( 'urinalysis' ),
			'image' => $theme_img_url . '/se_1520.jpg',
			'sections' => array(
				array( 'title' => 'HbA1c',  'items' => array() ),
				array( 'title' => 'Clinical Chemistry', 'items' => array(
					array( 'name' => 'D-Concept 2c SD-4830', 'link' => '' ),
					array( 'name' => 'D-Concept 2e SD-4840', 'link' => '' ),
					array( 'name' => 'D-Concept',            'link' => '' ),
					array( 'name' => 'SP-4430',              'link' => '' ),
				) ),
				array( 'title' => 'Electrolyte', 'items' => array(
					array( 'name' => 'SE-1520', 'link' => '' ),
				) ),
				array( 'title' => 'Blood Ammonia', 'items' => array(
					array( 'name' => 'PA-4140', 'link' => '' ),
				) ),
				array( 'title' => 'Blood Glucose', 'items' => array(
					array( 'name' => 'PG-7320', 'link' => '' ),
				) ),
				array( 'title' => 'Urine Chemistry', 'items' => array(
					array( 'name' => 'PU-4010', 'link' => '' ),
					array( 'name' => 'AX-4060', 'link' => '' ),
					array( 'name' => 'AE-4070', 'link' => '' ),
				) ),
				array( 'title' => 'Urine Sediment', 'items' => array(
					array( 'name' => 'AI-4510', 'link' => '' ),
				) ),
				array( 'title' => 'Urinalysis (Test Strips)', 'items' => array(
					array( 'name' => 'Urine test strips series', 'link' => '' ),
				) ),
			),
		),
		array(
			'slug'  => 'bgm',
			'title' => 'BGM',
			'link'  => $pcat_url( 'blood' ),
			'image' => $theme_img_url . '/GLUCOCARD-S_140.jpg',
			'sections' => array(
				array( 'title' => 'BGM', 'items' => array(
					array( 'name' => 'S',            'link' => '' ),
					array( 'name' => 'W',            'link' => '' ),
					array( 'name' => '01-mini',      'link' => '' ),
					array( 'name' => '01-mini plus', 'link' => '' ),
					array( 'name' => 'X-mini plus',  'link' => '' ),
					array( 'name' => 'Σ',            'link' => '' ),
					array( 'name' => 'Σ-mini',       'link' => '' ),
				) ),
				array( 'title' => 'Test Strip', 'items' => array(
					array( 'name' => 'GLUCOCARD S test strip', 'link' => 'http://www.arkray.co.jp/smbg/test_strip/' ),
				) ),
			),
		),
		array(
			'slug'  => 'oral-care',
			'title' => 'Oral care',
			'link'  => 'http://arkraydental.com/',
			'image' => $theme_img_url . '/st4910_140_e.jpg',
			'sections' => array(
				array( 'title' => 'Salivary testing', 'items' => array(
					array( 'name' => 'SillHa LH-4912', 'link' => 'http://arkraydental.com/' ),
				) ),
			),
		),
		array(
			'slug'  => 'veterinary-others',
			'title' => 'Veterinary & Others',
			'link'  => $pcat_url( 'others' ),
			'image' => $theme_img_url . '/RT-4010_130px.jpg',
			'sections' => array(
				array( 'title' => 'Veterinary', 'items' => array(
					array( 'name' => 'BS-7110',    'link' => '' ),
					array( 'name' => 'RT-4010',    'link' => '' ),
					array( 'name' => 'CHW',        'link' => '' ),
					array( 'name' => 'FIV / FeLV', 'link' => '' ),
				) ),
				array( 'title' => 'Blood Lactate', 'items' => array(
					array( 'name' => 'LT-1730', 'link' => '' ),
				) ),
				array( 'title' => 'Osmotic Pressure', 'items' => array(
					array( 'name' => 'OM-6060', 'link' => '' ),
				) ),
			),
		),
		array(
			'slug'  => 'immunodiagnostic-products',
			'title' => 'Immunodiagnostic Products',
			'link'  => $pcat_url( 'immunodiagnostic_products' ),
			'image' => $crystal_hbs_url,
			'sections' => array(
				array( 'title' => 'Rapid Tests', 'items' => array(
					array( 'name' => 'Rapid test for Filaria - Signal MF -',                  'link' => '' ),
					array( 'name' => 'Rapid test for HCV - Signal HCV Ver 3.0 - ',            'link' => '' ),
					array( 'name' => 'Rapid test for HIV - Signal HIV & Signal HIV 3D - ',    'link' => '' ),
					array( 'name' => 'Rapid test for Malaria - Parahit f/Parahit Total -',    'link' => '' ),
					array( 'name' => 'Rapid test for Cholera - Crystal - VC (Dipstick) -',    'link' => '' ),
					array( 'name' => 'Rapid test for Syphilis - Crystal Tp+ (Dipstick) - ',   'link' => '' ),
					array( 'name' => 'Rapid test for HBsAg - Crystal HBsAg (Device/Dipstick) - ', 'link' => '' ),
				) ),
				array( 'title' => 'Serology', 'items' => array(
					array( 'name' => 'WIDAL Antigen Set (Slide / Tube)', 'link' => '' ),
					array( 'name' => 'Tuberculin PPD',                   'link' => '' ),
					array( 'name' => 'Stained Brucella Suspensions',     'link' => '' ),
					array( 'name' => 'RPR (Rapid Plasma Reagin)',        'link' => '' ),
					array( 'name' => 'RA/ASO/CRP Latex',                 'link' => '' ),
				) ),
				array( 'title' => 'Blood Grouping and Typing AntiSera', 'items' => array(
					array( 'name' => 'Blood Grouping & Typing AntiSera', 'link' => '' ),
				) ),
			),
		),
		array(
			'slug'  => 'clinical-chemistry-reagents',
			'title' => 'Clinical Chemistry Reagents',
			'link'  => $pcat_url( 'osmolality' ),
			'image' => $theme_img_url . '/Coagulation-Reagents-New_140.jpg',
			'sections' => array(
				// Original has a bare <ul> with no <h4> — model as a section
				// whose title matches the category title so the renderer
				// suppresses the <h4> (see the `$section['title'] !== $pcat['title']` guard).
				array( 'title' => 'Clinical Chemistry Reagents', 'items' => array(
					array( 'name' => 'Biochemistry Reagents',         'link' => '' ),
					array( 'name' => 'Immunoturbidimetry Reagents',   'link' => '' ),
					array( 'name' => 'Coagulation Reagents',          'link' => '' ),
				) ),
			),
		),
		array(
			'slug'  => 'primary-health-care',
			'title' => 'Primary Health Care',
			'link'  => $pcat_url( 'primary_healthcare' ),
			'image' => $theme_img_url . '/thelab004_130.jpg',
			'sections' => array(
				array( 'title' => 'Clinical Chemistry', 'items' => array(
					array( 'name' => 'The Lab 004', 'link' => '' ),
				) ),
				array( 'title' => 'Urine Chemistry', 'items' => array(
					array( 'name' => 'PU-4010', 'link' => '' ),
				) ),
				array( 'title' => 'HbA1c', 'items' => array(
					array( 'name' => 'PocketChem A1c Advanced', 'link' => '' ),
				) ),
				array( 'title' => 'Electrolyte', 'items' => array(
					array( 'name' => 'SE-1520', 'link' => '' ),
				) ),
			),
		),
	);
}

// Helper: is current product key active? (Detail pages can be added later.)
$current_product_slug = '';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="format-detection" content="telephone=no">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>Product | <?php bloginfo( 'name' ); ?></title>
<meta name="description" content="<?php bloginfo( 'description' ); ?>">
<?php wp_head(); ?>
</head>
<body class="arkray-inner arkray-products-page">
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

<?php
// Active category, if any. Set by rewrite rules for URLs like
// /products/patient_testing/index.html — see arkray_add_origin_category_rewrites().
// Detected BEFORE g_menu so we can mark the active row with class="ac" and
// expand its sub-list with style="display: block;" matching the original.
$active_pcat_slug = arkray_get_request_product_category_slug();
$active_pcat      = null;
if ( $active_pcat_slug ) {
	foreach ( $product_catalog as $pcat ) {
		if ( arkray_normalize_product_category_wp_slug( $pcat['slug'] ) === $active_pcat_slug ) {
			$active_pcat = $pcat;
			break;
		}
	}
}
?>

<div id="content_wrapper" class="cf">
	<ul id="g_menu">
		<li><a href="<?php echo $news_page_url; ?>"><?php echo esc_html( arkray_t( 'News & Topics' ) ); ?></a></li>
		<li><a href="<?php echo $products_page_url; ?>" class="ac"><?php echo esc_html( arkray_t( 'Products' ) ); ?></a>
			<ul style="display: block;">
				<?php foreach ( $product_catalog as $pcat ) :
					$_pcat_menu_url = $pcat['link'] ?: $products_page_url;
					$_pcat_is_ext   = $_pcat_menu_url && 0 !== strpos( $_pcat_menu_url, home_url() ) && 0 === strpos( $_pcat_menu_url, 'http' );
					$_pcat_active   = $active_pcat && arkray_normalize_product_category_wp_slug( $active_pcat['slug'] ) === arkray_normalize_product_category_wp_slug( $pcat['slug'] );

					// Build the <a> classes: external links get .product_link;
					// the active category gets .ac. Both can apply together.
					$_pcat_classes = array();
					if ( $_pcat_is_ext ) { $_pcat_classes[] = 'product_link'; }
					if ( $_pcat_active )  { $_pcat_classes[] = 'ac'; }
					$_pcat_class_attr = $_pcat_classes ? ' class="' . esc_attr( implode( ' ', $_pcat_classes ) ) . '"' : '';
					$_pcat_target     = $_pcat_is_ext ? ' target="_blank"' : '';
				?>
					<li>
						<a href="<?php echo esc_url( $_pcat_menu_url ); ?>"<?php echo $_pcat_target . $_pcat_class_attr; ?>><?php echo esc_html( $pcat['title'] ); ?></a>
						<?php if ( ! empty( $pcat['sections'] ) ) : ?>
							<ul<?php echo $_pcat_active ? ' style="display: block;"' : ''; ?>>
								<?php foreach ( $pcat['sections'] as $section ) : ?>
									<?php
									// "Flat" sections duplicate the category title. In the verbatim
									// original these render an empty <ul> sub-list (e.g. Clinical
									// Chemistry Reagents, whose entries aren't real product pages).
									// But when a flat section contains real linked products — e.g.
									// the Osmolality category's OM-6070 — still list them as menu
									// sub-items so they aren't hidden from the navigation.
									if ( ! empty( $section['title'] ) && $section['title'] === $pcat['title'] ) {
										$_flat_has_link = false;
										foreach ( $section['items'] as $_flat_item ) {
											if ( ! empty( $_flat_item['link'] ) ) { $_flat_has_link = true; break; }
										}
										if ( ! $_flat_has_link ) { continue; }
									}
									?>
									<?php foreach ( $section['items'] as $item ) : ?>
										<li>
											<?php if ( ! empty( $item['link'] ) ) : ?>
												<a href="<?php echo esc_url( $item['link'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
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

	<?php if ( $active_pcat ) : ?>
		<div id="content_area">
			<h1 class="h1_index"><?php echo esc_html( $active_pcat['title'] ); ?></h1>

			<?php
			// Optional category-level intro HTML stored on the term description
			// (e.g. Primary Health Care has 3 paragraphs of preamble between
			// <h1> and the first <h2>). Verbatim from reference scrape.
			$_pcat_term = get_term_by( 'slug', $active_pcat['slug'], 'product_category' );
			if ( $_pcat_term && ! empty( $_pcat_term->description ) ) {
				// Description stored as raw HTML — print as-is (WP already kses'd on save).
				echo $_pcat_term->description;
			}
			?>

			<?php foreach ( $active_pcat['sections'] as $section ) : ?>
				<?php // Suppress h2 when section title equals the category title — matches the
				// original "flat" categories (e.g. Clinical Chemistry Reagents) where
				// products list directly under the page banner with no sub-heading. ?>
				<?php if ( ! empty( $section['title'] ) && $section['title'] !== $active_pcat['title'] ) : ?>
					<h2 class="h2_content mt50"><?php echo esc_html( $section['title'] ); ?></h2>
				<?php endif; ?>

				<?php
				// Build enriched item list with subtitle, long name and 60px thumb
				// for the category index "product_index_line" rows.
				$enriched_items = array();
				foreach ( $section['items'] as $item ) {
					$pid          = isset( $item['id'] ) ? (int) $item['id'] : 0;
					$item_label    = '';
					$item_subtitle = '';
					$item_longname = isset( $item['name'] ) ? $item['name'] : '';
					$item_thumb    = '';
					if ( $pid ) {
						// Verbatim per-category label from migrate-product-index-labels.php.
						// Overrides subtitle+name construction below when present.
						$item_label    = (string) get_field( 'product_index_label', $pid );
						$item_subtitle = (string) get_field( 'product_features', $pid );
						$excerpt       = (string) get_post_field( 'post_excerpt', $pid );
						if ( '' !== trim( $excerpt ) ) {
							$item_longname = trim( $excerpt );
						}
						// Prefer the verbatim category-index image URL extracted from the
						// reference scrape — matches the original site exactly even when
						// the WP _thumbnail_id points to a different product photo.
						$index_img = (string) get_field( 'product_index_image', $pid );
						if ( '' !== $index_img ) {
							$item_thumb = $index_img;
						} else {
							$thumb = get_the_post_thumbnail_url( $pid, 'thumbnail' );
							if ( $thumb ) {
								$item_thumb = $thumb;
							}
						}
					}
					$enriched_items[] = array(
						'link'      => isset( $item['link'] ) ? $item['link'] : '',
						'label'     => $item_label,
						'subtitle'  => $item_subtitle,
						'longname'  => $item_longname,
						// Short name (post_title) for the image alt attribute —
						// matches the verbatim original which uses e.g. "PU-4010",
						// not the long "PocketChem UA PU-4010".
						'shortname' => isset( $item['name'] ) ? $item['name'] : '',
						'thumb'     => $item_thumb,
					);
				}

				// Original wraps items into rows of 3 with border-bottom on all
				// rows except the last in this section.
				$rows = array_chunk( $enriched_items, 3 );
				$last_row_idx = count( $rows ) - 1;
				foreach ( $rows as $ri => $row ) :
					if ( empty( $row ) ) { continue; }
					$inline = ( $ri < $last_row_idx ) ? ' style="border-bottom: 1px solid rgb(230, 230, 230);"' : '';
				?>
					<div class="product_index_line"<?php echo $inline; ?>>
						<?php foreach ( $row as $item ) :
							$item_link = $item['link'] ?: '#';
							$item_ext  = $item_link && false === strpos( $item_link, home_url() ) && 0 === strpos( $item_link, 'http' );
						?>
							<div>
								<p class="tx"><a href="<?php echo esc_url( $item_link ); ?>"<?php echo $item_ext ? ' target="_blank"' : ''; ?>><?php
									if ( '' !== $item['label'] ) {
										// Verbatim original label (may contain inline <br>) — wp_kses to allow only <br>.
										echo wp_kses( $item['label'], array( 'br' => array() ) );
									} else {
										if ( '' !== $item['subtitle'] ) {
											echo nl2br( esc_html( $item['subtitle'] ) ) . '<br>';
										}
										echo esc_html( $item['longname'] );
									}
								?></a></p>
								<?php if ( '' !== $item['thumb'] ) : ?>
									<p class="img"><a href="<?php echo esc_url( $item_link ); ?>"<?php echo $item_ext ? ' target="_blank"' : ''; ?>><img src="<?php echo esc_url( $item['thumb'] ); ?>" alt="<?php echo esc_attr( $item['shortname'] ); ?>" width="60"></a></p>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			<?php endforeach; ?>

			<div class="contact_area">
				<p><a href="<?php echo $contact_page_url; ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( arkray_t( 'Contact Us' ) ); ?></a></p>
			</div>
		</div>
	</div>

	<?php // Skip the rest of the file's content area + footer rendering will continue below.
	// We close out the same footer/wp_footer block by including the rest of this template inline.
	?>
	<?php
	// Render footer and exit — we already closed #content_wrapper above.
	?>
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
	</body></html>
	<?php exit; endif; ?>

	<div id="content_area">
		<h1 class="h1_product"><?php echo esc_html( arkray_t( 'Products' ) ); ?></h1>
		<h2 class="h2_content_nm"><?php echo esc_html( arkray_t( 'Line up' ) ); ?></h2>

		<?php
		// Original renders categories in groups of 2 — each <div class="product_lineup">
		// contains up to two <div class="box"> children.
		$chunks = array_chunk( $product_catalog, 2 );
		foreach ( $chunks as $chunk ) : ?>
			<div class="product_lineup">
				<?php foreach ( $chunk as $pcat ) :
					$pcat_link    = $pcat['link'] ?: $products_page_url;
					$pcat_image   = $pcat['image'];
					$pcat_is_ext  = $pcat_link && 0 !== strpos( $pcat_link, home_url() ) && 0 === strpos( $pcat_link, 'http' );
				?>
					<div class="box">
						<h3><a href="<?php echo esc_url( $pcat_link ); ?>"<?php echo $pcat_is_ext ? ' target="_blank"' : ''; ?>><?php echo esc_html( $pcat['title'] ); ?></a></h3>
						<div class="lineup_list">
							<div class="list">
								<?php foreach ( $pcat['sections'] as $section ) : ?>
									<?php if ( ! empty( $section['title'] ) && $section['title'] !== $pcat['title'] ) : ?>
										<h4><?php echo esc_html( $section['title'] ); ?></h4>
									<?php endif; ?>
									<ul>
										<?php foreach ( $section['items'] as $item ) :
											$item_link = $item['link'];
											$is_ext    = $item_link && false === strpos( $item_link, home_url() );
										?>
											<li>
												<?php if ( $item_link ) : ?>
													<a href="<?php echo esc_url( $item_link ); ?>"<?php echo $is_ext ? ' target="_blank"' : ''; ?>><?php echo esc_html( $item['name'] ); ?></a>
												<?php else : ?>
													<?php echo esc_html( $item['name'] ); ?>
												<?php endif; ?>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php endforeach; ?>
							</div>
							<div class="img">
								<?php if ( $pcat_image ) : ?>
									<p><a href="<?php echo esc_url( $pcat_link ); ?>"<?php echo $pcat_is_ext ? ' target="_blank"' : ''; ?>><img src="<?php echo esc_url( $pcat_image ); ?>" alt="<?php echo esc_attr( $pcat['title'] ); ?>" width="120"></a></p>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>

		<h2 class="h2_content pt30"><?php echo esc_html( arkray_t( 'History of Pioneers' ) ); ?></h2>
		<div id="editor_area">
			<ul>
				<li><a href="<?php echo esc_url( arkray_get_history_page_url( 'diabetes-testing' ) ); ?>">Diabetes testing</a></li>
				<li><a href="<?php echo esc_url( arkray_get_history_page_url( 'urinalysis' ) ); ?>">Urinalysis</a></li>
				<li><a href="<?php echo esc_url( arkray_get_history_page_url( 'dry-chemistry-testing' ) ); ?>">Dry Chemistry Testing</a></li>
				<li><a href="<?php echo esc_url( arkray_get_history_page_url( 'bgm' ) ); ?>">BGM</a></li>
			</ul>
		</div>

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
