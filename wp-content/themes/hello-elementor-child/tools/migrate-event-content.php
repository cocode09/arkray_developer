<?php
/**
 * One-off migration: populate event detail bodies with pixel-perfect HTML
 * extracted verbatim from the original arkray.co.jp pages stored under
 * _reference/, localizing inline images into the uploads dir.
 *
 * Run:  php wp-content/themes/hello-elementor-child/tools/migrate-event-content.php
 */

$root = dirname( __FILE__, 5 );
require_once $root . '/wp-load.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit( "WP not loaded\n" );
}

// Trusted migration: keep the original markup verbatim (no KSES stripping).
kses_remove_filters();

$ref_dir = $root . '/_reference/arkray-live/scraped/pages';
$uploads = wp_upload_dir();
$img_dir = $uploads['basedir'] . '/2026/05';
$img_url = $uploads['baseurl'] . '/2026/05';
if ( ! is_dir( $img_dir ) ) {
	wp_mkdir_p( $img_dir );
}

// slug => reference page basename (without .html)
$map = array(
	'eflm-urinalysis-webinar-2024' => 'events_gallery__events__2024__20241009',
	'euromedlab-roma-2023'         => 'events_gallery__events__2023__eventse_20230521',
	'africa-health-2017'           => 'events_gallery__events__2017__event20170607',
	'aacc-annual-meeting-2017'     => 'events_gallery__events__2017__event20170801',
	'msava-agm-2017'               => 'events_gallery__events__2017__eventse2070819',
	'cadcam-dentistry-2017'        => 'events_gallery__events__2017__eventse2070819_2',
	'easd-annual-meeting-2014'     => 'events_gallery__events__2014__info05_kr',
	'easd-annual-meeting-2013'     => 'events_gallery__events__2013__info03_kr',
);

/**
 * Pull the inner HTML of #editor_area out of a scraped page.
 */
function arkray_extract_editor_area( $html ) {
	$html  = str_replace( "\r\n", "\n", $html );
	$start = strpos( $html, '<div id="editor_area">' );
	if ( false === $start ) {
		return '';
	}
	$start += strlen( '<div id="editor_area">' );
	$rest   = substr( $html, $start );

	// editor_area closes with "\t\t</div>\n\t</div>" (its own close + content_area).
	$end = strpos( $rest, "\t\t</div>\n\t</div>" );
	if ( false === $end ) {
		// Fallback: cut at the footer.
		$end = strpos( $rest, '<div id="footer">' );
	}
	$body = false !== $end ? substr( $rest, 0, $end ) : $rest;

	// Drop the leading date paragraph — the template renders the date itself.
	$body = preg_replace( '#\s*<p class="date">.*?</p>#s', '', $body, 1 );

	// Remove inline <style> blocks (their CSS lives in the theme stylesheet).
	$body = preg_replace( '#<style[^>]*>.*?</style>#is', '', $body );

	return trim( $body );
}

/**
 * Download an upload image referenced in the markup and return its local URL.
 */
function arkray_localize( $remote_basename, $img_dir, $img_url ) {
	$dest = $img_dir . '/' . $remote_basename;
	if ( ! file_exists( $dest ) ) {
		$src  = 'https://www.arkray.co.jp/english/upload/img/' . $remote_basename;
		$body = wp_remote_retrieve_body( wp_remote_get( $src, array( 'timeout' => 30 ) ) );
		if ( '' === $body ) {
			echo "    ! download failed: $remote_basename\n";
			return '';
		}
		file_put_contents( $dest, $body );
		echo "    + downloaded $remote_basename\n";
	}
	return $img_url . '/' . $remote_basename;
}

foreach ( $map as $slug => $basename ) {
	$post = get_page_by_path( $slug, OBJECT, 'event' );
	if ( ! $post ) {
		echo "SKIP (post not found): $slug\n";
		continue;
	}
	$file = $ref_dir . '/' . $basename . '.html';
	if ( ! file_exists( $file ) ) {
		echo "SKIP (ref not found): $basename.html\n";
		continue;
	}

	echo "Event: $slug (ID {$post->ID})\n";
	$body = arkray_extract_editor_area( file_get_contents( $file ) );

	// Localize every upload/img image regardless of the (sometimes mirrored) host.
	$body = preg_replace_callback(
		'#(<img[^>]*\ssrc=")([^"]*upload/img/([^"/]+))(")#i',
		function ( $m ) use ( $img_dir, $img_url ) {
			$local = arkray_localize( $m[3], $img_dir, $img_url );
			return $m[1] . ( $local ?: $m[2] ) . $m[4];
		},
		$body
	);

	// Rewrite root-relative arkray links so they don't resolve against localhost.
	$body = str_replace( array( 'href="/english/', 'src="/english/' ), array( 'href="https://www.arkray.co.jp/english/', 'src="https://www.arkray.co.jp/english/' ), $body );

	$res = wp_update_post(
		array(
			'ID'           => $post->ID,
			'post_content' => $body,
		),
		true
	);
	if ( is_wp_error( $res ) ) {
		echo "  ! update failed: " . $res->get_error_message() . "\n";
	} else {
		echo "  > content updated (" . strlen( $body ) . " bytes)\n";
	}
}

echo "Done.\n";
