<?php
/**
 * One-off migration: rename product section title
 * "Urinalysis (Analyzers)" → "Urine Chemistry".
 *
 * Run: php wp-content/themes/hello-elementor-child/tools/rename-urinalysis-analyzers-section.php
 */

$root = dirname( __FILE__, 5 );
require_once $root . '/wp-load.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit( "WP not loaded\n" );
}

global $wpdb;

$from = 'Urinalysis (Analyzers)';
$to   = 'Urine Chemistry';

$updated = $wpdb->query(
	$wpdb->prepare(
		"UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_key = 'product_section_title' AND meta_value = %s",
		$to,
		$from
	)
);

if ( false === $updated ) {
	exit( "Update failed: {$wpdb->last_error}\n" );
}

echo "Updated {$updated} product_section_title row(s) from \"{$from}\" to \"{$to}\".\n";
