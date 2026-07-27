<?php
/**
 * One-off migration: create the Site Map page (slug: site-map)
 * assigned to template-site-map.php, and link it to both Polylang
 * languages so footer links resolve from English and Vietnamese.
 *
 * Run once from the project root:
 *   php wp-content/themes/hello-elementor-child/tools/migrate-site-map-page.php
 */

$root = dirname( __FILE__, 5 );
require_once $root . '/wp-load.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit( "WP not loaded\n" );
}

// ── Guard: skip if the page already exists (published or draft) ────────────
$existing = get_page_by_path( 'site-map', OBJECT, 'page' );
if ( ! ( $existing instanceof WP_Post ) ) {
	// get_page_by_path misses drafts; fall back to a direct slug lookup.
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'page' LIMIT 1",
		'site-map'
	) );
	if ( $row ) {
		$existing = get_post( (int) $row->ID );
	}
}
if ( $existing instanceof WP_Post ) {
	echo "Site Map page already exists (ID {$existing->ID}). Nothing to do.\n";

	// Ensure the page template is set correctly even on an existing page.
	$current_template = get_post_meta( $existing->ID, '_wp_page_template', true );
	if ( 'template-site-map.php' !== $current_template ) {
		update_post_meta( $existing->ID, '_wp_page_template', 'template-site-map.php' );
		echo "Updated page template to template-site-map.php.\n";
	}
} else {
	// ── Create the page ────────────────────────────────────────────────────
	// Also check for a draft page with this slug before creating a new one.
	global $wpdb;
	$draft_row = $wpdb->get_row( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'page' LIMIT 1",
		'site-map'
	) );

	if ( $draft_row ) {
		$page_id = wp_update_post( array(
			'ID'          => (int) $draft_row->ID,
			'post_status' => 'publish',
		), true );
	} else {
		$page_id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Site Map',
			'post_name'    => 'site-map',
			'post_content' => '',
		), true );
	}

	if ( is_wp_error( $page_id ) ) {
		exit( 'Failed to create page: ' . $page_id->get_error_message() . "\n" );
	}

	update_post_meta( $page_id, '_wp_page_template', 'template-site-map.php' );
	echo "Created Site Map page (ID {$page_id}).\n";
	$existing = get_post( $page_id );
}

$page_id = (int) $existing->ID;

// ── Polylang: set language to English and link Vietnamese to the same page ──
if ( function_exists( 'pll_set_post_language' ) && function_exists( 'pll_save_post_translations' ) ) {
	// Determine the English Polylang slug (usually "english" or "en").
	$en_slug = 'english';
	$vi_slug = 'vietnamese';

	if ( function_exists( 'pll_languages_list' ) ) {
		$langs = pll_languages_list( array( 'fields' => 'slug' ) );
		foreach ( (array) $langs as $slug ) {
			// Detect likely English slug by locale or slug containing "en".
			if ( false !== strpos( strtolower( $slug ), 'en' ) ) {
				$en_slug = $slug;
			}
			if ( false !== strpos( strtolower( $slug ), 'vi' ) ) {
				$vi_slug = $slug;
			}
		}
	}

	pll_set_post_language( $page_id, $en_slug );
	echo "Set page language to '{$en_slug}'.\n";

	// Link the same English page as the Vietnamese translation so
	// arkray_pll_permalink() resolves correctly from both language footers.
	pll_save_post_translations( array(
		$en_slug => $page_id,
		$vi_slug => $page_id,
	) );
	echo "Linked '{$vi_slug}' translation to the same page (ID {$page_id}).\n";
} else {
	echo "Polylang not active — skipping language assignment.\n";
}

echo "Done.\n";
