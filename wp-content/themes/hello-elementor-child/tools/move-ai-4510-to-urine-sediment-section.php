<?php
/**
 * One-off migration: move AI-4510 from "Urine Chemistry" to "Urine Sediment".
 *
 * Run: php wp-content/themes/hello-elementor-child/tools/move-ai-4510-to-urine-sediment-section.php
 */

$root = dirname( __FILE__, 5 );
require_once $root . '/wp-load.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit( "WP not loaded\n" );
}

global $wpdb;

$product_id = 579;
$from       = 'Urine Chemistry';
$to         = 'Urine Sediment';

$updated = $wpdb->update(
	$wpdb->postmeta,
	array( 'meta_value' => $to ),
	array(
		'post_id'    => $product_id,
		'meta_key'   => 'product_section_title',
		'meta_value' => $from,
	),
	array( '%s' ),
	array( '%d', '%s', '%s' )
);

if ( false === $updated ) {
	exit( "Update failed: {$wpdb->last_error}\n" );
}

echo "Updated {$updated} product_section_title row(s) for post {$product_id} from \"{$from}\" to \"{$to}\".\n";
