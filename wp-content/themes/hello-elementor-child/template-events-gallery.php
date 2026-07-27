<?php
/**
 * Template Name: Events & Gallery
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


$uploads_url     = content_url( '/uploads/2026/05' );
$img             = $uploads_url;
$custom_logo_id  = get_theme_mod( 'custom_logo' );
$logo_url        = $custom_logo_id ? wp_get_attachment_image_url( $custom_logo_id, 'full' ) : '';
$logo_src        = $logo_url ?: $img . '/logo.jpg';

$events_gallery_pages = array(
	'events_gallery' => array(
		'title' => 'Events & Gallery',
		'type'  => 'events_gallery',
	),
);

$slug = get_post_field( 'post_name', get_the_ID() );

if ( empty( $slug ) ) {
	$slug = sanitize_title( get_the_title() );
}

if ( empty( $slug ) && function_exists( 'arkray_get_events_gallery_route_key_from_request' ) ) {
	$slug = arkray_get_events_gallery_route_key_from_request();
}

if ( ! isset( $events_gallery_pages[ $slug ] ) ) {
	$slug = 'events_gallery';
}

$current_page = $events_gallery_pages[ $slug ];
$current_tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'events';
$events_gallery_request_path = trim( arkray_get_request_relative_path(), '/' );
if ( preg_match( '#(?:^|/)events_gallery/(?:gallery|media-gallery)/?$#', $events_gallery_request_path ) ) {
	$current_tab = 'gallery';
}
if ( ! in_array( $current_tab, array( 'events', 'gallery' ), true ) ) {
	$current_tab = 'events';
}

$current_event_key = function_exists( 'arkray_get_events_gallery_event_key_from_request' )
	? arkray_get_events_gallery_event_key_from_request()
	: '';
if ( '' === $current_event_key && isset( $_GET['event'] ) ) {
	$current_event_key = sanitize_key( wp_unslash( $_GET['event'] ) );
}

$current_scope = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : 'all';
if ( ! in_array( $current_scope, array( 'all', 'local' ), true ) ) {
	$current_scope = 'all';
}

$current_gallery = null;
if ( is_singular( 'gallery' ) ) {
	$queried_gallery = get_queried_object();
	if ( $queried_gallery instanceof WP_Post && 'gallery' === $queried_gallery->post_type ) {
		$current_gallery = $queried_gallery;
		$current_tab     = 'gallery';
	}
}

// Events list â€” pulled from the `event` CPT.
$event_items = array();
$events_list_query = new WP_Query( array(
	'post_type'      => 'event',
	'posts_per_page' => -1,
	'meta_key'       => 'event_date',
	'orderby'        => 'meta_value',
	'order'          => 'DESC',
) );

if ( $events_list_query->have_posts() ) {
	$event_first = true;
	while ( $events_list_query->have_posts() ) {
		$events_list_query->the_post();

		$ev_date_raw = get_field( 'event_date' );
		$ev_date_ts  = $ev_date_raw ? strtotime( $ev_date_raw ) : 0;
		$ev_terms    = get_the_terms( get_the_ID(), 'event_type' );
		$ev_scope    = 'global';
		if ( ! empty( $ev_terms ) && ! is_wp_error( $ev_terms ) ) {
			$ev_scope = $ev_terms[0]->slug;
		}

		// NEW badge: explicit ACF field `event_is_new` wins; otherwise fall back
		// to "the most recent event in the list" so newly-published events
		// automatically get badged without manual config.
		$ev_is_new_meta = get_field( 'event_is_new' );
		$ev_is_new      = ( null !== $ev_is_new_meta && '' !== $ev_is_new_meta )
			? (bool) $ev_is_new_meta
			: $event_first;

		$event_items[] = array(
			'date'       => $ev_date_ts ? date_i18n( 'M d, Y', $ev_date_ts ) : '',
			'year'       => $ev_date_ts ? (string) date( 'Y', $ev_date_ts ) : '',
			'label'      => (string) get_field( 'event_type_label' ),
			'title'      => get_the_title(),
			'event_key'  => get_post_field( 'post_name' ),
			'scope'      => $ev_scope,
			'flag'       => '',
			'is_new'     => $ev_is_new,
			'flag_emoji' => (string) get_field( 'event_location' ),
		);
		$event_first = false;
	}
	wp_reset_postdata();
}

// Fallback static seed list to keep filter/year UI populated before content migration.
if ( empty( $event_items ) ) :
$event_items = array(
	array(
		'date'       => 'Feb 04, 2025',
		'year'       => '2025',
		'label'      => 'EUROMEDLAB Brussels 2025',
		'title'      => 'JOIN US AT EUROMEDLAB BRUSSELS 2025',
		'event_key'  => 'euromedlab-brussels-2025',
		'scope'      => 'local',
		'flag'       => 'BE',
		'is_new'     => true,
		'flag_emoji' => 'ðŸ‡§ðŸ‡ª',
	),
	array(
		'date'       => 'Oct 09, 2024',
		'year'       => '2024',
		'label'      => 'Webinar',
		'title'      => 'Upcoming Webinar: The updated EFLM European Urinalysis guideline: particles in the picture!',
		'scope'      => 'global',
		'flag'       => '',
		'is_new'     => false,
		'flag_emoji' => '',
	),
	array(
		'date'       => 'Aug 30, 2024',
		'year'       => '2024',
		'label'      => 'Conference',
		'title'      => 'Medicina di Laboratorio: rete integrata tra programmazione ed urgenze',
		'scope'      => 'local',
		'flag'       => 'IT',
		'is_new'     => false,
		'flag_emoji' => 'ðŸ‡®ðŸ‡¹',
	),
	array(
		'date'       => 'Aug 07, 2024',
		'year'       => '2024',
		'label'      => 'Conference',
		'title'      => '9Â° Congresso Nazionale SIPMeL',
		'scope'      => 'local',
		'flag'       => 'IT',
		'is_new'     => false,
		'flag_emoji' => 'ðŸ‡®ðŸ‡¹',
	),
	array(
		'date'       => 'Aug 07, 2024',
		'year'       => '2024',
		'label'      => 'Conference',
		'title'      => '56Â° Congresso Nazionale SIBioC - MEDICINA DI LABORATORIO',
		'scope'      => 'local',
		'flag'       => 'IT',
		'is_new'     => false,
		'flag_emoji' => 'ðŸ‡®ðŸ‡¹',
	),
	array(
		'date'       => 'Feb 27, 2023',
		'year'       => '2023',
		'label'      => 'Scientific Event',
		'title'      => 'HbA1c for monitoring glycemic control in 2023 and in the future',
		'scope'      => 'global',
		'flag'       => 'IT',
		'is_new'     => false,
		'flag_emoji' => 'ðŸ‡®ðŸ‡¹',
	),
	array(
		'date'       => 'Feb 22, 2023',
		'year'       => '2023',
		'label'      => 'Exhibition',
		'title'      => 'ARKRAY\'s Booth at "WORLDLAB - EUROMEDLAB ROMA 2023"',
		'scope'      => 'global',
		'flag'       => 'IT',
		'is_new'     => false,
		'flag_emoji' => 'ðŸ‡®ðŸ‡¹',
	),
	array(
		'date'       => 'Aug 04, 2017',
		'year'       => '2017',
		'label'      => 'Conference',
		'title'      => 'National Scientific Conference, Exhibition & 27th AGM by MSAVA',
		'scope'      => 'global',
		'flag'       => 'MY',
		'is_new'     => false,
		'flag_emoji' => 'ðŸ‡²ðŸ‡¾',
	),
	array(
		'date'       => 'Aug 04, 2017',
		'year'       => '2017',
		'label'      => 'Conference',
		'title'      => '4th CAD/CAM & Digital Dentistry International Conference',
		'scope'      => 'global',
		'flag'       => 'SG',
		'is_new'     => false,
		'flag_emoji' => 'ðŸ‡¸ðŸ‡¬',
	),
	array(
		'date'       => 'Aug 01, 2017',
		'year'       => '2017',
		'label'      => 'Annual Meeting',
		'title'      => 'ARKRAY\'s Booth at 2017 AACC Annual Meeting',
		'scope'      => 'global',
		'flag'       => '',
		'is_new'     => false,
		'flag_emoji' => '',
	),
	array(
		'date'       => 'Jun 02, 2017',
		'year'       => '2017',
		'label'      => 'Exhibition',
		'title'      => 'ARKRAY\'s Booth at Africa Health Exhibition & Congress 2017',
		'scope'      => 'local',
		'flag'       => 'ZA',
		'is_new'     => false,
		'flag_emoji' => 'ðŸ‡¿ðŸ‡¦',
	),
	array(
		'date'       => 'Feb 01, 2017',
		'year'       => '2017',
		'label'      => 'Exhibition',
		'title'      => 'MEDLAB, 6-9 February 2017 Dubai, UAE',
		'scope'      => 'local',
		'flag'       => 'AE',
		'is_new'     => false,
		'flag_emoji' => 'ðŸ‡¦ðŸ‡ª',
	),
	array(
		'date'       => 'Jan 15, 2016',
		'year'       => '2016',
		'label'      => 'Exhibition',
		'title'      => 'Arab Health 2016, 25 - 28 January 2016',
		'scope'      => 'global',
		'flag'       => '',
		'is_new'     => false,
		'flag_emoji' => '',
	),
	array(
		'date'       => 'Sep 01, 2015',
		'year'       => '2015',
		'label'      => 'Annual Meeting',
		'title'      => 'ARKRAY\'s Booth at "51st Annual Meeting of the European Association for the Study of Diabetes"',
		'scope'      => 'local',
		'flag'       => 'SE',
		'is_new'     => false,
		'flag_emoji' => 'ðŸ‡¸ðŸ‡ª',
	),
	array(
		'date'       => 'Sep 01, 2014',
		'year'       => '2014',
		'label'      => 'Annual Meeting',
		'title'      => 'ARKRAY\'s Booth at "50th Annual Meeting of the European Association for the Study of Diabetes"',
		'scope'      => 'local',
		'flag'       => 'AT',
		'is_new'     => false,
		'flag_emoji' => 'ðŸ‡¦ðŸ‡¹',
	),
	array(
		'date'       => 'Sep 13, 2013',
		'year'       => '2013',
		'label'      => 'Annual Meeting',
		'title'      => 'ARKRAY\'s Booth at "49th Annual Meeting of the European Association for the Study of Diabetes"',
		'scope'      => 'local',
		'flag'       => 'ES',
		'is_new'     => false,
		'flag_emoji' => 'ðŸ‡ªðŸ‡¸',
	),
);
endif; // empty( $event_items )

// Gallery sections: pulled from ACF options page (Site Settings) when set,
// otherwise the hardcoded fallback below is used.
$gallery_sections_fallback = array(
	array(
		'title' => 'Corporate introduction video',
		'items' => array(
			array(
				'title'   => 'Corporate introduction video',
				'excerpt' => 'We develop instruments, in vitro diagnostic reagents and dat...',
				'image'   => $img . '/gallery_img.png',
				'link'    => 'https://www.arkray.ph/english/events_gallery/corporate_introduction_video.html',
			),
		),
	),
	array(
		'title' => 'Finding ARKRAY in the most surprising places',
		'items' => array(
			array(
				'title'   => 'Drink with ARKRAY Japanese Sake "Kizakura"',
				'excerpt' => 'This is the company that manufactures or sells alcoholic bev...',
				'image'   => $img . '/gallery01.jpg',
				'link'    => 'https://www.arkray.ph/english/events_gallery/gallery/finding_arkray/gellery01.html',
			),
			array(
				'title'   => 'Yummy with ARKRAY "Calbee Potato"',
				'excerpt' => 'This is a the company that takes on a role of purchasing and...',
				'image'   => $img . '/gallery02.jpg',
				'link'    => 'https://www.arkray.ph/english/events_gallery/gallery/finding_arkray/gallery02.html',
			),
			array(
				'title'   => 'Knuckle-down with ARKRAY? Horse-racing "Ritto Training Center"',
				'excerpt' => 'This is one of 2 Training Centers of Horse Racing in Japan',
				'image'   => $img . '/gallery03.jpg',
				'link'    => 'https://www.arkray.ph/english/events_gallery/gallery/finding_arkray/gallery03.html',
			),
			array(
				'title'   => 'Smile with ARKRAY Osaka Aquarium "KAIYUKAN"',
				'excerpt' => 'Aquarium in Osaka, one of the largest aquariums in the world...',
				'image'   => $img . '/gallery04.jpg',
				'link'    => 'https://www.arkray.ph/english/events_gallery/gallery/finding_arkray/gallery04.html',
			),
		),
	),
	array(
		'title' => 'ARKRAY KYOTO LABORATORY',
		'items' => array(
			array(
				'title'   => 'Kyoto Laboratory',
				'excerpt' => 'Kyoto Laboratory has been home to ARKRAY’s Research and Deve...',
				'image'   => $img . '/arkray01.jpg',
				'link'    => 'https://www.arkray.ph/english/events_gallery/gallery/arkray_kyoto_laboratory/kyoto_laboratory.html',
			),
		),
	),
	array(
		'title' => 'YOUSUIEN',
		'items' => array(
			array(
				'title'   => 'YOUSUIEN',
				'excerpt' => 'Kyoto Main Research Center is in YOUSUIEN.<br>YOUSUIEN, Th...',
				'image'   => $img . '/seimon.640_360pxpng.png',
				'link'    => 'https://www.arkray.ph/english/events_gallery/yosuien.html',
			),
		),
	),
);

$acf_gallery_sections = function_exists( 'get_field' ) ? get_field( 'gallery_sections', 'option' ) : null;
if ( ! empty( $acf_gallery_sections ) && is_array( $acf_gallery_sections ) ) {
	$gallery_sections = array();
	foreach ( $acf_gallery_sections as $acf_section ) {
		$items = array();
		if ( ! empty( $acf_section['section_items'] ) && is_array( $acf_section['section_items'] ) ) {
			foreach ( $acf_section['section_items'] as $acf_item ) {
				$items[] = array(
					'title'   => isset( $acf_item['item_title'] ) ? (string) $acf_item['item_title'] : '',
					'excerpt' => isset( $acf_item['item_excerpt'] ) ? (string) $acf_item['item_excerpt'] : '',
					'image'   => isset( $acf_item['item_image'] ) ? (string) $acf_item['item_image'] : '',
					'link'    => isset( $acf_item['item_link'] ) ? (string) $acf_item['item_link'] : '',
				);
			}
		}
		$gallery_sections[] = array(
			'title' => isset( $acf_section['section_title'] ) ? (string) $acf_section['section_title'] : '',
			'items' => $items,
		);
	}
} else {
	$gallery_sections = $gallery_sections_fallback;
}

// Published Gallery records are the canonical source once the detail-content
// migration has run. Group them by the existing ACF taxonomy so the landing
// page and the Gallery admin category column share one content model.
$gallery_posts_query = new WP_Query(
	array(
		'post_type'      => 'gallery',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => array(
			'menu_order' => 'ASC',
			'title'      => 'ASC',
		),
	)
);

if ( $gallery_posts_query->have_posts() ) {
	$db_gallery_sections = array(
		'corporate-introduction-video'                 => array( 'title' => 'Corporate introduction video', 'items' => array() ),
		'finding-arkray-in-the-most-surprising-places' => array( 'title' => 'Finding ARKRAY in the most surprising places', 'items' => array() ),
		'arkray-kyoto-laboratory'                      => array( 'title' => 'ARKRAY KYOTO LABORATORY', 'items' => array() ),
		'yousuien'                                      => array( 'title' => 'YOUSUIEN', 'items' => array() ),
	);

	while ( $gallery_posts_query->have_posts() ) {
		$gallery_posts_query->the_post();
		$post_terms = get_the_terms( get_the_ID(), 'gallery_category' );

		if ( empty( $post_terms ) || is_wp_error( $post_terms ) ) {
			continue;
		}

		$category = reset( $post_terms );
		if ( ! isset( $db_gallery_sections[ $category->slug ] ) ) {
			$db_gallery_sections[ $category->slug ] = array(
				'title' => $category->name,
				'items' => array(),
			);
		}

		$db_gallery_sections[ $category->slug ]['items'][] = array(
			'title'   => get_the_title(),
			'excerpt' => get_the_excerpt(),
			'image'   => get_the_post_thumbnail_url( get_the_ID(), 'full' ) ?: '',
			'link'    => get_permalink(),
		);
	}

	wp_reset_postdata();

	$gallery_sections = array_values(
		array_filter(
			$db_gallery_sections,
			static function ( $section ) {
				return ! empty( $section['items'] );
			}
		)
	);
}

$event_years = array_values( array_unique( wp_list_pluck( $event_items, 'year' ) ) );
rsort( $event_years, SORT_NATURAL );

// Resolve event detail page via CPT lookup by slug.
$current_event = null;
if ( '' !== $current_event_key ) {
	$event_detail_query = new WP_Query( array(
		'post_type'      => 'event',
		'name'           => $current_event_key,
		'posts_per_page' => 1,
	) );

	if ( $event_detail_query->have_posts() ) {
		$event_detail_query->the_post();

		$ev_date_raw  = get_field( 'event_date' );
		$ev_date_ts   = $ev_date_raw ? strtotime( $ev_date_raw ) : 0;
		$hero_thumb   = get_the_post_thumbnail_url( null, 'large' );
		$info_rows_raw = get_field( 'event_info_rows' );
		$information_rows = array();
		if ( is_array( $info_rows_raw ) ) {
			foreach ( $info_rows_raw as $row ) {
				$label = isset( $row['info_label'] ) ? (string) $row['info_label'] : '';
				$value = isset( $row['info_value'] ) ? (string) $row['info_value'] : '';
				$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $value ) ), 'strlen' ) );
				$information_rows[] = array(
					'label' => $label,
					'value' => $lines,
				);
			}
		}

		$related_raw      = (string) get_field( 'event_related_products' );
		$related_products = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $related_raw ) ), 'strlen' ) );

		// Rich body: the original detail pages carry free-form HTML (info tables,
		// register buttons, bold notes) that the structured fields can't reproduce
		// verbatim. When the post has content, render it as-is (raw, no wpautop —
		// the migrated markup is already fully-formed) for pixel-perfect fidelity;
		// otherwise fall back to the structured fields below.
		$content_html = trim( (string) get_the_content() );

		$current_event = array(
			'title'            => get_the_title(),
			'date'             => $ev_date_ts ? date_i18n( 'M d, Y', $ev_date_ts ) : '',
			'year'             => $ev_date_ts ? (string) date( 'Y', $ev_date_ts ) : '',
			'country_emoji'    => (string) get_field( 'event_location' ),
			'lead'             => get_the_excerpt(),
			'hero_image'       => $hero_thumb ?: '',
			'hero_alt'         => get_the_title(),
			'content_html'     => $content_html,
			'information_rows' => $information_rows,
			'related_products' => $related_products,
			'products_link'    => (string) get_field( 'event_external_url' ),
		);

		wp_reset_postdata();
	}
}

$current_year = isset( $_GET['year'] ) ? sanitize_key( wp_unslash( $_GET['year'] ) ) : '';
if ( '' !== $current_year && ! in_array( $current_year, $event_years, true ) ) {
	$current_year = '';
}

if ( $current_event ) {
	$current_tab  = 'events';
	$current_year = $current_event['year'];
}

$filtered_event_items = array_values(
	array_filter(
		$event_items,
		static function ( $event_item ) use ( $current_scope, $current_year ) {
			if ( 'local' === $current_scope && 'local' !== $event_item['scope'] ) {
				return false;
			}

			if ( '' !== $current_year && $current_year !== $event_item['year'] ) {
				return false;
			}

			return true;
		}
	)
);

$resolve_events_gallery_url = static function ( $tab = 'events', $year = '', $scope = 'all', $event = '' ) {
	$base_url   = function_exists( 'arkray_get_events_gallery_page_url' )
		? arkray_get_events_gallery_page_url( $tab )
		: home_url( '/events_gallery/' );
	$query_args = array();

	if ( ! function_exists( 'arkray_get_events_gallery_page_url' ) ) {
		$query_args['tab'] = $tab;
	}

	if ( '' !== $year ) {
		$query_args['year'] = $year;
	}

	if ( 'all' !== $scope ) {
		$query_args['scope'] = $scope;
	}

	if ( '' !== $event ) {
		$base_url = arkray_get_events_gallery_event_url( $event );
	}

	return esc_url( add_query_arg( $query_args, $base_url ) );
};
?>
<?php
// Country flag class resolver. Accepts ISO codes ("IT"), full country names
// ("Italy"), or emoji regional-indicator flags ("🇮🇹") and returns the
// country-name CSS class the original site uses on the .date span.
$flag_iso_to_name = array(
	'IT' => 'Italy', 'SG' => 'Singapore', 'ZA' => 'SAfrica', 'MY' => 'Malaysia',
	'ES' => 'Spain', 'AT' => 'Austria',   'BE' => 'Belgium',  'AE' => 'UAE',
	'SE' => 'Sweden','DE' => 'Germany',   'FR' => 'France',
	'GB' => 'UnitedKingdomofGreatBritainandNorthernIreland',
	'NL' => 'Netherlands', 'CH' => 'Switzerland', 'PT' => 'Portugal',
	'JP' => 'Japan', 'CN' => 'China',     'KR' => 'Korea',    'IN' => 'India',
	'TH' => 'Thailand', 'PH' => 'Philippines', 'ID' => 'Indonesia', 'VN' => 'VietNam',
	'US' => 'USA',
);
$flag_class = static function ( $raw ) use ( $flag_iso_to_name ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) { return 'no_flag'; }

	// Case 1: ISO 2-letter code → name
	$upper = strtoupper( $raw );
	if ( isset( $flag_iso_to_name[ $upper ] ) ) {
		return $flag_iso_to_name[ $upper ];
	}

	// Case 2: Full country name already (e.g. "Italy")
	if ( in_array( $raw, $flag_iso_to_name, true ) ) {
		return $raw;
	}

	// Case 3: Emoji regional-indicator flag (e.g. "🇮🇹") — decode to ISO.
	// Each regional indicator is U+1F1E6..U+1F1FF and represents A..Z.
	$codepoints = unpack( 'N*', mb_convert_encoding( $raw, 'UCS-4BE', 'UTF-8' ) );
	if ( is_array( $codepoints ) && count( $codepoints ) >= 2 ) {
		$cps = array_values( $codepoints );
		$cp1 = (int) $cps[0];
		$cp2 = (int) $cps[1];
		if ( $cp1 >= 0x1F1E6 && $cp1 <= 0x1F1FF && $cp2 >= 0x1F1E6 && $cp2 <= 0x1F1FF ) {
			$iso2 = chr( $cp1 - 0x1F1E6 + ord( 'A' ) ) . chr( $cp2 - 0x1F1E6 + ord( 'A' ) );
			if ( isset( $flag_iso_to_name[ $iso2 ] ) ) {
				return $flag_iso_to_name[ $iso2 ];
			}
		}
	}

	return 'no_flag';
};

// Page URLs for the verbatim header / g_menu / footer ────────────────────
$news_page_url      = esc_url( arkray_pll_permalink( get_page_by_path( 'news' ) ) ?: arkray_home_url( '/news/' ) );
$products_page_url  = esc_url( arkray_pll_permalink( get_page_by_path( 'products' ) ) ?: arkray_home_url( '/products/' ) );
$events_page_url    = esc_url( arkray_get_events_gallery_page_url() );
$about_page_url     = esc_url( arkray_get_about_page_url( 'about-us' ) );
$contact_page_url   = esc_url( arkray_get_contact_page_url() );
$sustainability_url = esc_url( arkray_get_sustainability_page_url() );
$history_url        = esc_url( arkray_get_history_page_url() );

$gallery_tab_url    = esc_url( $resolve_events_gallery_url( 'gallery' ) );
$events_tab_url     = esc_url( $resolve_events_gallery_url( 'events' ) );

$logo_src = $custom_logo_id
	? wp_get_attachment_image_url( $custom_logo_id, 'full' )
	: get_stylesheet_directory_uri() . '/img/logo.jpg';

$page_h1 = ( 'gallery' === $current_tab ) ? arkray_t( 'Media Gallery' ) : arkray_t( 'Events & Gallery' );
$document_title = $current_gallery ? get_the_title( $current_gallery ) : $page_h1;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="format-detection" content="telephone=no">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title><?php echo esc_html( $document_title ); ?> | <?php bloginfo( 'name' ); ?></title>
<meta name="description" content="<?php bloginfo( 'description' ); ?>">
<?php wp_head(); ?>
</head>
<body class="arkray-inner arkray-events-gallery-page arkray-events-gallery-tab--<?php echo esc_attr( $current_tab ); ?>">
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
				<option value="https://arkrayusa.com">North America</option>
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
				<li><a href="<?php echo $events_page_url; ?>" class="ac"><?php echo esc_html( arkray_t( 'Events & Gallery' ) ); ?></a>
					<ul style="display: block;">
						<li><a href="<?php echo $events_tab_url; ?>"<?php echo 'events'  === $current_tab ? ' class="ac"' : ''; ?>><?php echo esc_html( arkray_t( 'Events' ) ); ?></a></li>
						<li><a href="<?php echo $gallery_tab_url; ?>"<?php echo 'gallery' === $current_tab ? ' class="ac"' : ''; ?>><?php echo esc_html( arkray_t( 'Media Gallery' ) ); ?></a></li>
					</ul>
				</li>
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
		<li><a href="<?php echo $products_page_url; ?>"><?php echo esc_html( arkray_t( 'Products' ) ); ?></a></li>
		<li><a href="<?php echo $history_url; ?>"><?php echo esc_html( arkray_t( 'History of Pioneers' ) ); ?></a></li>
		<li><a href="<?php echo $events_page_url; ?>" class="ac"><?php echo esc_html( arkray_t( 'Events & Gallery' ) ); ?></a>
			<ul style="display: block;">
				<li><a href="<?php echo $events_tab_url; ?>"<?php echo 'events'  === $current_tab ? ' class="ac"' : ''; ?>><?php echo esc_html( arkray_t( 'Events' ) ); ?></a>
					<?php if ( 'events' === $current_tab && ! empty( $event_years ) ) : ?>
						<ul style="display: block;">
							<?php foreach ( $event_years as $yr ) : ?>
								<li><a href="<?php echo $events_tab_url; ?>" data-filter-year="<?php echo esc_attr( $yr ); ?>"<?php echo $yr === $current_year ? ' class="ac"' : ''; ?>><?php echo esc_html( $yr ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</li>
				<li><a href="<?php echo $gallery_tab_url; ?>"<?php echo 'gallery' === $current_tab ? ' class="ac"' : ''; ?>><?php echo esc_html( arkray_t( 'Media Gallery' ) ); ?></a></li>
			</ul>
		</li>
		<li><a href="<?php echo $about_page_url; ?>"><?php echo esc_html( arkray_t( 'About Us' ) ); ?></a></li>
		<li><a href="<?php echo $sustainability_url; ?>"><?php echo esc_html( arkray_t( 'Sustainability' ) ); ?></a></li>
				<li><a href="<?php echo esc_url( arkray_get_recruitment_page_url() ); ?>"><?php echo esc_html( arkray_t( 'Recruitment' ) ); ?></a></li>
	</ul>

	<div id="content_area">
		<h1 class="h1_index"><?php echo esc_html( $current_event ? arkray_t( 'Events' ) : $page_h1 ); ?></h1>

		<?php if ( $current_event ) : ?>
			<?php
			$detail_flag_cls = $flag_class( $current_event['country_emoji'] );
			?>
			<div id="editor_area">
				<?php if ( '' !== $current_event['date'] ) : ?>
					<p class="date"><span class="<?php echo esc_attr( $detail_flag_cls ); ?>"><?php echo esc_html( $current_event['date'] ); ?></span></p>
				<?php endif; ?>

				<?php if ( '' !== $current_event['content_html'] ) : ?>
					<?php echo $current_event['content_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted post content rendered through the_content filters. ?>
				<?php else : ?>

				<h2 class="h2_news"><?php echo esc_html( $current_event['title'] ); ?></h2>

				<?php if ( '' !== $current_event['hero_image'] ) : ?>
					<p class="mb15 align_c">
						<img src="<?php echo esc_url( $current_event['hero_image'] ); ?>" alt="<?php echo esc_attr( $current_event['hero_alt'] ); ?>" class="mt-image-none">
					</p>
				<?php endif; ?>

				<?php if ( '' !== $current_event['lead'] ) : ?>
					<p class="mb20"><?php echo esc_html( $current_event['lead'] ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $current_event['information_rows'] ) ) : ?>
					<table class="typeB mb30">
						<tbody>
							<?php foreach ( $current_event['information_rows'] as $info_row ) : ?>
								<tr>
									<th><?php echo esc_html( $info_row['label'] ); ?></th>
									<td>
										<?php
										$row_lines = array_map( 'esc_html', (array) $info_row['value'] );
										echo implode( '<br>', $row_lines );
										?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<?php if ( ! empty( $current_event['related_products'] ) ) : ?>
					<h3><?php echo esc_html( arkray_t( 'Related Products' ) ); ?></h3>
					<ul class="mb20">
						<?php foreach ( $current_event['related_products'] as $product_name ) : ?>
							<li><?php echo esc_html( $product_name ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if ( '' !== $current_event['products_link'] ) : ?>
					<p class="mb20"><a href="<?php echo esc_url( $current_event['products_link'] ); ?>" target="_blank" class="ex"><?php echo esc_html( $current_event['products_link'] ); ?></a></p>
				<?php endif; ?>

				<?php endif; // content_html vs structured ?>

				<p class="mt20"><a href="<?php echo $events_tab_url; ?>">&laquo; <?php echo esc_html( arkray_t( 'Back to Events' ) ); ?></a></p>
			</div>

		<?php elseif ( $current_gallery ) : ?>
			<?php echo arkray_render_gallery_content( $current_gallery ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted migrated Gallery content. ?>

		<?php elseif ( 'gallery' === $current_tab ) : ?>
			<?php foreach ( $gallery_sections as $section ) : ?>
				<h2 class="h2_content_nm"><?php echo esc_html( $section['title'] ); ?></h2>
				<div class="pb20">
					<?php foreach ( array_chunk( $section['items'], 2 ) as $item_row ) : ?>
						<div class="gellery_lineup">
							<?php foreach ( $item_row as $item ) :
								$item_link = ! empty( $item['link'] ) ? $item['link'] : '#';
							?>
								<div class="box">
									<div class="cf">
										<div class="tx">
											<h3><a href="<?php echo esc_url( $item_link ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h3>
											<?php if ( ! empty( $item['excerpt'] ) ) : ?>
												<p><?php echo wp_kses( $item['excerpt'], array( 'br' => array() ) ); ?></p>
											<?php endif; ?>
										</div>
										<?php if ( ! empty( $item['image'] ) ) : ?>
											<p class="img"><a href="<?php echo esc_url( $item_link ); ?>"><img src="<?php echo esc_url( $item['image'] ); ?>" width="120" alt=""></a></p>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>

		<?php else : ?>
			<div id="tab_change" class="common_tabarea">
				<div class="common_tab">
					<p><a href="<?php echo esc_url( $resolve_events_gallery_url( 'events', $current_year, 'all'   ) ); ?>"<?php echo 'all'   === $current_scope ? ' class="ac"' : ''; ?>><?php echo esc_html( arkray_t( 'All' ) ); ?></a></p>
					<p><a href="<?php echo esc_url( $resolve_events_gallery_url( 'events', $current_year, 'local' ) ); ?>"<?php echo 'local' === $current_scope ? ' class="ac"' : ''; ?>><?php echo esc_html( arkray_t( 'Local' ) ); ?></a></p>
				</div>
			</div>

			<?php
			// Helper: render a list of events into .content_eventarea
			$render_events = static function ( $items ) use ( $flag_class ) {
				foreach ( $items as $ev ) :
					$scope_label = ucfirst( $ev['scope'] ); // Global / Local
					// DB events store the country in `flag_emoji` (emoji or full name);
					// static-fallback events store an ISO code in `flag`. Try both.
					$flag_cls    = $flag_class( ! empty( $ev['flag_emoji'] ) ? $ev['flag_emoji'] : $ev['flag'] );
					$detail_url  = ! empty( $ev['event_key'] )
						? arkray_get_events_gallery_event_url( $ev['event_key'] )
						: '#';
				?>
					<div class="box" data-year="<?php echo esc_attr( $ev['year'] ); ?>">
						<p class="tag">
							<?php if ( ! empty( $ev['is_new'] ) ) : ?><span class="new">NEW</span><?php endif; ?>
							<span><?php echo esc_html( $scope_label ); ?></span>
						</p>
						<p class="date"><span class="<?php echo esc_attr( $flag_cls ); ?>"><?php echo esc_html( $ev['date'] ); ?></span></p>
						<p class="tx_long"><a href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html( $ev['title'] ); ?></a></p>
					</div>
				<?php
				endforeach;
			};

			// Apply the active year filter server-side so legacy deep links like
			// /events_gallery/events?year=2024 render correctly
			// without relying on JS.
			$year_filter = static function ( $items ) use ( $current_year ) {
				if ( '' === $current_year ) {
					return $items;
				}
				return array_values( array_filter( $items, static function ( $e ) use ( $current_year ) {
					return $current_year === $e['year'];
				} ) );
			};

			$all_items   = $year_filter( $event_items );
			$local_items = $year_filter( array_values( array_filter( $event_items, static function ( $e ) {
				return 'local' === $e['scope'];
			} ) ) );
			?>

			<?php
			// Swap which tab_index_area carries the .hide class based on the URL's
			// scope param so server-side rendering already reflects the active tab.
			// Original site uses JS to toggle; we mirror the same DOM but pre-hide
			// the inactive panel so the page works without JS.
			$all_class   = ( 'local' === $current_scope ) ? 'tab_index_area hide' : 'tab_index_area';
			$local_class = ( 'local' === $current_scope ) ? 'tab_index_area'      : 'tab_index_area hide';
			?>
			<div class="<?php echo esc_attr( $all_class ); ?>">
				<div class="content_eventarea">
					<?php $render_events( $all_items ); ?>
				</div>
			</div>
			<div class="<?php echo esc_attr( $local_class ); ?>">
				<div class="content_eventarea">
					<?php $render_events( $local_items ); ?>
				</div>
			</div>
		<?php endif; ?>
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
