<?php
/**
 * One-off migration: import #editor_area markup from arkray.co.jp group pages
 * into WordPress page editors (admin-editable, no External Content plugin).
 *
 * Defaults to page 855 (ARKRAY Group / World). Pass a page ID as the first CLI
 * argument to import a regional page (group02–group05 sources).
 *
 * Run from the project root:
 *   php wp-content/themes/hello-elementor-child/tools/migrate-arkray-group-page.php
 *   php wp-content/themes/hello-elementor-child/tools/migrate-arkray-group-page.php 855
 *
 * @package HelloElementorChild
 */

$root = dirname( __FILE__, 5 );
require_once $root . '/wp-load.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit( "WP not loaded\n" );
}

$sources = array(
	855 => array(
		'source' => 'https://www.arkray.co.jp/english/about/group.html',
		'file'   => 'group.html',
	),
);

$regional = array(
	'arkray-group-2' => array( 'source' => 'https://www.arkray.co.jp/english/about/group02.html', 'file' => 'group02.html' ),
	'arkray-group-3' => array( 'source' => 'https://www.arkray.co.jp/english/about/group03.html', 'file' => 'group03.html' ),
	'arkray-group-4' => array( 'source' => 'https://www.arkray.co.jp/english/about/group04.html', 'file' => 'group04.html' ),
	'arkray-group-5' => array( 'source' => 'https://www.arkray.co.jp/english/about/group05.html', 'file' => 'group05.html' ),
);

foreach ( $regional as $slug => $config ) {
	$page = get_page_by_path( $slug );
	if ( $page instanceof WP_Post ) {
		$sources[ (int) $page->ID ] = $config;
	}
}

$target_id = isset( $argv[1] ) ? (int) $argv[1] : 855;
if ( ! isset( $sources[ $target_id ] ) ) {
	exit( "Unknown or unmapped page ID {$target_id}.\n" );
}

$page = get_post( $target_id );
if ( ! ( $page instanceof WP_Post ) || 'page' !== $page->post_type ) {
	exit( "Page {$target_id} not found.\n" );
}

$config     = $sources[ $target_id ];
$source_url = $config['source'];

$response = wp_remote_get(
	$source_url,
	array(
		'timeout'   => 30,
		'sslverify' => true,
		'headers'   => array(
			'User-Agent' => 'ARKRAY-WP-Migration/1.0',
		),
	)
);

if ( is_wp_error( $response ) ) {
	exit( 'Fetch failed: ' . $response->get_error_message() . "\n" );
}

$status = (int) wp_remote_retrieve_response_code( $response );
$html   = (string) wp_remote_retrieve_body( $response );
if ( 200 !== $status || '' === trim( $html ) ) {
	exit( "Fetch failed: HTTP {$status}\n" );
}

$body_html = arkray_migrate_extract_editor_area_html( $html );
if ( '' === $body_html ) {
	exit( "Could not find #editor_area in {$source_url}\n" );
}

$body_html = arkray_migrate_rewrite_group_body_links( $body_html );
$body_html = arkray_migrate_wrap_group_html_block( $body_html );

$result = wp_update_post(
	array(
		'ID'           => $target_id,
		'post_content' => $body_html,
	),
	true
);

if ( is_wp_error( $result ) ) {
	exit( 'Update failed: ' . $result->get_error_message() . "\n" );
}

// Ensure the About template is assigned and external import is cleared.
update_post_meta( $target_id, '_wp_page_template', 'template-about-us.php' );
if ( function_exists( 'update_field' ) ) {
	update_field( 'external_content_url', '', $target_id );
	update_field( 'external_content_base_url', '', $target_id );
	update_field( 'external_content_cache_hours', '', $target_id );
}

echo "Updated page {$target_id} ({$page->post_title}) from {$source_url}\n";
echo 'Content length: ' . strlen( $body_html ) . " bytes\n";

/**
 * Extract inner HTML of #editor_area, without <script> tags.
 *
 * @param string $html Full source document.
 * @return string
 */
function arkray_migrate_extract_editor_area_html( $html ) {
	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	$loaded = $dom->loadHTML(
		'<?xml encoding="UTF-8">' . $html,
		LIBXML_NOERROR | LIBXML_NOWARNING
	);
	if ( ! $loaded ) {
		libxml_clear_errors();
		return '';
	}

	$xpath = new DOMXPath( $dom );
	$nodes = $xpath->query( '//*[@id="editor_area"]' );
	if ( ! $nodes || ! $nodes->length ) {
		libxml_clear_errors();
		return '';
	}

	$editor = $nodes->item( 0 );
	$inner  = '';
	foreach ( $editor->childNodes as $child ) {
		if ( $child instanceof DOMElement && 'script' === strtolower( $child->tagName ) ) {
			continue;
		}
		$inner .= $dom->saveHTML( $child );
	}

	libxml_clear_errors();

	// Drop any Google Maps render artifacts if the source was saved from a browser.
	$inner = arkray_migrate_clean_gmap_placeholder( $inner );

	return trim( $inner );
}

/**
 * Ensure #gmap is an empty placeholder without stripping surrounding markup.
 *
 * @param string $html Editor-area HTML.
 * @return string
 */
function arkray_migrate_clean_gmap_placeholder( $html ) {
	if ( '' === trim( (string) $html ) || false === stripos( $html, 'id="gmap"' ) ) {
		return (string) $html;
	}

	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	$loaded = $dom->loadHTML(
		'<?xml encoding="UTF-8"><div id="arkray-migrate-root">' . $html . '</div>',
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
	);
	if ( ! $loaded ) {
		libxml_clear_errors();
		return (string) $html;
	}

	$xpath = new DOMXPath( $dom );
	$nodes = $xpath->query( '//*[@id="gmap"]' );
	if ( $nodes && $nodes->length ) {
		$gmap = $nodes->item( 0 );
		while ( $gmap->hasChildNodes() ) {
			$gmap->removeChild( $gmap->firstChild );
		}
		if ( $gmap instanceof DOMElement && $gmap->hasAttribute( 'style' ) ) {
			$gmap->removeAttribute( 'style' );
		}
	}

	$root = $dom->getElementById( 'arkray-migrate-root' );
	$clean = '';
	if ( $root ) {
		foreach ( $root->childNodes as $child ) {
			$clean .= $dom->saveHTML( $child );
		}
	}

	libxml_clear_errors();

	return '' !== $clean ? $clean : (string) $html;
}

/**
 * Rewrite legacy group tab links to this WordPress site.
 *
 * @param string $html Editor-area HTML.
 * @return string
 */
function arkray_migrate_rewrite_group_body_links( $html ) {
	$tab_urls = array(
		'group.html'  => function_exists( 'arkray_get_about_page_url' ) ? arkray_get_about_page_url( 'arkray-group' ) : home_url( '/about/group/' ),
		'group01.html' => function_exists( 'arkray_get_about_page_url' ) ? arkray_get_about_page_url( 'arkray-group' ) : home_url( '/about/group/' ),
		'group02.html' => function_exists( 'arkray_get_about_page_url' ) ? arkray_get_about_page_url( 'arkray-group-2' ) : home_url( '/about/group02/' ),
		'group03.html' => function_exists( 'arkray_get_about_page_url' ) ? arkray_get_about_page_url( 'arkray-group-3' ) : home_url( '/about/group03/' ),
		'group04.html' => function_exists( 'arkray_get_about_page_url' ) ? arkray_get_about_page_url( 'arkray-group-4' ) : home_url( '/about/group04/' ),
		'group05.html' => function_exists( 'arkray_get_about_page_url' ) ? arkray_get_about_page_url( 'arkray-group-5' ) : home_url( '/about/group05/' ),
	);

	foreach ( $tab_urls as $legacy_file => $site_url ) {
		$site_url = untrailingslashit( $site_url );
		$html     = str_replace( 'href="' . $legacy_file, 'href="' . $site_url, $html );
		$html     = str_replace( "href='" . $legacy_file, "href='" . $site_url, $html );
	}

	return $html;
}

/**
 * Wrap verbatim HTML in a Gutenberg Custom HTML block for safe admin editing.
 *
 * @param string $html Inner editor markup.
 * @return string
 */
function arkray_migrate_wrap_group_html_block( $html ) {
	$html = trim( (string) $html );
	if ( '' === $html ) {
		return '';
	}

	if ( false !== strpos( $html, '<!-- wp:html -->' ) ) {
		return $html;
	}

	return "<!-- wp:html -->\n" . $html . "\n<!-- /wp:html -->";
}
