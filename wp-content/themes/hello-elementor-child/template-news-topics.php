<?php
/**
 * Template Name: News & Topics
 *
 * Verbatim port of arkray.co.jp/english/news/index.html — uses the original
 * IDs (#header, #content_wrapper, #g_menu, #content_area, #footer) and
 * original classes (.h1_news, .common_tabarea, .common_tab, .tab_index_area,
 * .content_newsarea, .box, .tag, .date, .tx, .tx_long, .img, .new) so the
 * verbatim CSS in arkray-content.css matches without drift.
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

// ── Logo ──────────────────────────────────────────────────────────────────
$custom_logo_id = get_theme_mod( 'custom_logo' );
$logo_src       = $custom_logo_id
	? wp_get_attachment_image_url( $custom_logo_id, 'full' )
	: get_stylesheet_directory_uri() . '/img/logo.jpg';

// ── News data — pulled from the `news` CPT, ordered by ACF `news_date` desc.
// Cap to 20 items per tab to match the verbatim reference (original site only
// lists ~20 most-recent items on the index page; older items live under
// year-specific archive pages linked from the left nav).
$news_items      = array();
$news_max_items  = 20;

// Parse origin top-list rows from local reference scrape so the rendered
// "All" tab can match origin article text + thumbnail presence/filename.
$origin_all_rows = array();
$origin_index    = ABSPATH . '_reference/arkray-live/scraped/pages/news__index.html';
if ( is_readable( $origin_index ) ) {
	$origin_html = file_get_contents( $origin_index );
	if ( false !== $origin_html && '' !== $origin_html ) {
		libxml_use_internal_errors( true );
		$origin_dom = new DOMDocument();
		if ( @$origin_dom->loadHTML( $origin_html ) ) {
			$origin_xpath = new DOMXPath( $origin_dom );
			$origin_panel = $origin_xpath->query( "(//div[contains(concat(' ', normalize-space(@class), ' '), ' tab_index_area ')])[1]" )->item( 0 );
			if ( $origin_panel ) {
				$origin_boxes = $origin_xpath->query( ".//div[contains(concat(' ', normalize-space(@class), ' '), ' box ')]", $origin_panel );
				if ( $origin_boxes ) {
					foreach ( $origin_boxes as $obox ) {
						$date_node = $origin_xpath->query( ".//p[contains(concat(' ', normalize-space(@class), ' '), ' date ')]", $obox )->item( 0 );
						$title_node = $origin_xpath->query(
							".//p[contains(concat(' ', normalize-space(@class), ' '), ' tx ') or contains(concat(' ', normalize-space(@class), ' '), ' tx_long ')]//a",
							$obox
						)->item( 0 );
						$img_node = $origin_xpath->query( ".//p[contains(concat(' ', normalize-space(@class), ' '), ' img ')]//img", $obox )->item( 0 );

						if ( ! $date_node || ! $title_node ) {
							continue;
						}

						$date_txt = trim( preg_replace( '/\s+/', ' ', html_entity_decode( $date_node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
						$title_txt = trim( preg_replace( '/\s+/', ' ', html_entity_decode( $title_node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
						$img_src = $img_node ? trim( (string) $img_node->getAttribute( 'src' ) ) : '';

						if ( '' === $date_txt || '' === $title_txt ) {
							continue;
						}

						$origin_all_rows[] = array(
							'date'  => $date_txt,
							'title' => $title_txt,
							'img'   => $img_src,
						);
					}
				}
			}
		}
		libxml_clear_errors();
	}
}

$news_query = new WP_Query( array(
	'post_type'      => 'news',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'meta_key'       => 'news_date',
	'orderby'        => 'meta_value',
	'order'          => 'DESC',
) );

if ( $news_query->have_posts() ) {
	while ( $news_query->have_posts() ) {
		$news_query->the_post();

		$news_date_raw = get_field( 'news_date' );
		$news_date_ts  = $news_date_raw ? strtotime( $news_date_raw ) : 0;
		$news_terms    = get_the_terms( get_the_ID(), 'news_category' );

		$tag_names = array();
		$type_slug = 'local';
		if ( ! empty( $news_terms ) && ! is_wp_error( $news_terms ) ) {
			foreach ( $news_terms as $term ) {
				$tag_names[] = $term->name;
				$type_slug   = strtolower( $term->slug );
			}
		}
		if ( empty( $tag_names ) ) {
			$tag_names[] = 'Local';
		}

		$thumb_url = get_the_post_thumbnail_url( null, 'thumbnail' ) ?: '';
		if ( '' === $thumb_url ) {
			$thumb_url = arkray_get_news_fallback_image_url(
				get_the_title(),
				$news_date_ts ? date( 'M d, Y', $news_date_ts ) : ''
			);
		}
		$year      = $news_date_ts ? date( 'Y', $news_date_ts ) : '';

		// NEW badge: ACF field `news_is_new` wins; otherwise default to false
		// (we mark only the topmost item as NEW below the loop, matching the
		// verbatim reference where exactly one NEW badge appears).
		$ev_is_new_meta = get_field( 'news_is_new' );
		$news_items[] = array(
			'title'     => get_the_title(),
			'link'      => get_permalink(),
			'date_ts'   => $news_date_ts,
			'date_fmt'  => $news_date_ts ? date( 'M d, Y', $news_date_ts ) : '',
			'tags'      => $tag_names,
			'type'      => $type_slug,
			'is_new'    => ( null !== $ev_is_new_meta && '' !== $ev_is_new_meta )
				? (bool) $ev_is_new_meta
				: false,
			'thumb_url' => $thumb_url,
			'year'      => $year,
		);
	}
	wp_reset_postdata();
}

// If no item has an explicit `news_is_new` ACF flag, fall back to marking
// the top-most item as NEW (mirrors verbatim reference behaviour).
if ( ! empty( $news_items ) ) {
	$has_explicit_new = false;
	foreach ( $news_items as $it ) { if ( $it['is_new'] ) { $has_explicit_new = true; break; } }
	if ( ! $has_explicit_new ) {
		$news_items[0]['is_new'] = true;
	}
}

// Use all items so year-filter JS can show any year's content.
$display_items = $news_items;

// If we have origin rows from the reference scrape, align top "All" display
// text/image with origin by index (keeps links and tags from local WP posts).
if ( ! empty( $origin_all_rows ) ) {
	for ( $i = 0; $i < count( $display_items ) && $i < count( $origin_all_rows ); $i++ ) {
		if ( $display_items[ $i ]['date_fmt'] !== $origin_all_rows[ $i ]['date'] ) {
			continue;
		}

		$display_items[ $i ]['title_render'] = $origin_all_rows[ $i ]['title'];

		$origin_img_src = (string) $origin_all_rows[ $i ]['img'];
		if ( '' === $origin_img_src ) {
			$display_items[ $i ]['thumb_url_render'] = '';
			continue;
		}

		$path_part        = parse_url( $origin_img_src, PHP_URL_PATH );
		$basename_raw     = $path_part ? wp_basename( $path_part ) : wp_basename( $origin_img_src );
		$basename_decoded = rawurldecode( $basename_raw );
		$resolved_thumb   = '';

		foreach ( array_unique( array( $basename_raw, $basename_decoded ) ) as $candidate ) {
			if ( '' === $candidate ) {
				continue;
			}
			$local_abs = ABSPATH . 'wp-content/uploads/arkray-assets/' . $candidate;
			if ( file_exists( $local_abs ) ) {
				$resolved_thumb = home_url( '/wp-content/uploads/arkray-assets/' . rawurlencode( $candidate ) );
				break;
			}
		}

		if ( '' === $resolved_thumb ) {
			$resolved_thumb = $origin_img_src;
		}

		$display_items[ $i ]['thumb_url_render'] = $resolved_thumb;
	}
}

// ── Local-only items (for second tab) ─────────────────────────────────────
$local_items = array_values( array_filter( $news_items, function( $item ) {
	return in_array( 'local', array_map( 'strtolower', $item['tags'] ), true );
} ) );
// No cap — all local items needed for year-filter to work.
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="format-detection" content="telephone=no">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>News &amp; Topics | <?php bloginfo( 'name' ); ?></title>
<meta name="description" content="<?php bloginfo( 'description' ); ?>">
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
		<h1 class="h1_news"><?php echo esc_html( arkray_t( 'News & Topics' ) ); ?></h1>
		<div id="tab_change" class="common_tabarea">
			<div class="common_tab">
				<p><a href="javascript:void(0)" class="ac"><?php echo esc_html( arkray_t( 'All' ) ); ?></a></p>
				<p><a href="javascript:void(0)"><?php echo esc_html( arkray_t( 'Local' ) ); ?></a></p>
			</div>
		</div>

		<?php
		// Helper: render a list of news items as .box entries.
		$render_news_list = function( $items ) {
			foreach ( $items as $item ) :
				$item_title = isset( $item['title_render'] ) && '' !== $item['title_render']
					? $item['title_render']
					: $item['title'];
				$item_thumb = isset( $item['thumb_url_render'] )
					? $item['thumb_url_render']
					: $item['thumb_url'];
				$has_thumb  = ! empty( $item_thumb );
				$tx_class   = $has_thumb ? 'tx' : 'tx_long';
				?>
				<div class="box" data-year="<?php echo esc_attr( $item['year'] ); ?>">
					<p class="tag">
						<?php if ( $item['is_new'] ) : ?><span class="new">NEW</span><?php endif; ?>
						<?php foreach ( $item['tags'] as $tag ) : ?><span><?php echo esc_html( $tag ); ?></span><?php endforeach; ?>
					</p>
					<p class="date"><?php echo esc_html( $item['date_fmt'] ); ?></p>
					<p class="<?php echo esc_attr( $tx_class ); ?>"><a href="<?php echo esc_url( $item['link'] ); ?>"><?php echo esc_html( $item_title ); ?></a></p>
					<?php if ( $has_thumb ) : ?>
						<p class="img"><img src="<?php echo esc_url( $item_thumb ); ?>" alt=""></p>
					<?php endif; ?>
				</div>
			<?php
			endforeach;
		};
		?>

		<div class="tab_index_area">
			<div class="content_newsarea">
				<?php $render_news_list( $display_items ); ?>
			</div>
		</div>
		<div class="tab_index_area hide">
			<div class="content_newsarea">
				<?php $render_news_list( $local_items ); ?>
			</div>
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

<script>
// All / Local tab toggle — matches the original site's behaviour where the
// `javascript:void(0)` anchors swap which .tab_index_area carries the `hide`
// class. Defensive against missing nodes (page can render without JS).
(function () {
	var tabs   = document.querySelectorAll('#tab_change .common_tab p a');
	var panels = document.querySelectorAll('#content_area .tab_index_area');
	if ( tabs.length < 2 || panels.length < 2 ) { return; }
	tabs.forEach(function ( link, idx ) {
		link.addEventListener('click', function ( e ) {
			e.preventDefault();
			tabs.forEach(function ( a, i ) { a.classList.toggle('ac', i === idx); });
			panels.forEach(function ( p, i ) { p.classList.toggle('hide', i !== idx); });
		});
	});
})();

// Year filter — sidebar year links filter visible .box items by data-year.
// Max 20 items shown at a time (default = 20 most recent; per year = up to 20).
(function () {
	var MAX = 20;
	var yearLinks = document.querySelectorAll('#g_menu a[href*="#year-"], #sp_menu a[href*="#year-"]');
	var heading   = document.querySelector('#content_area h1.h1_news');
	var baseTitle = heading ? heading.textContent : '';

	function applyFilter( year ) {
		// Operate on each tab panel independently so both "All" and "Local" tabs respect the limit.
		var panels = document.querySelectorAll('#content_area .tab_index_area');
		panels.forEach(function ( panel ) {
			var boxes = panel.querySelectorAll('.box');
			var shown = 0;
			boxes.forEach(function ( box ) {
				var matches = ! year || box.getAttribute('data-year') === year;
				if ( matches && shown < MAX ) {
					box.style.display = '';
					shown++;
				} else {
					box.style.display = 'none';
				}
			});
		});

		// Reflect the selected year in the heading: "News & Topics: 2025".
		if ( heading ) {
			heading.textContent = year ? baseTitle + ': ' + year : baseTitle;
		}

		// Mark active year link.
		yearLinks.forEach(function ( a ) {
			var linkYear = (a.getAttribute('href') || '').replace(/.*#year-/, '');
			a.classList.toggle('ac', !! year && linkYear === year);
		});
	}

	// Default on load: show 20 most recent (no year filter).
	applyFilter('');

	yearLinks.forEach(function ( link ) {
		link.addEventListener('click', function ( e ) {
			e.preventDefault();
			var year = (link.getAttribute('href') || '').replace(/.*#year-/, '');
			applyFilter( year );
		});
	});

	// On page load, apply year from URL hash if present, then strip the hash
	// from the address bar so it isn't left dangling (e.g. when arriving here
	// from a news detail page year link).
	if ( window.location.hash && /^#year-\d{4}$/.test(window.location.hash) ) {
		applyFilter( window.location.hash.replace('#year-', '') );
		if ( window.history && window.history.replaceState ) {
			window.history.replaceState( null, '', window.location.pathname + window.location.search );
		}
	}
})();
</script>

<?php wp_footer(); ?>
</body>
</html>
