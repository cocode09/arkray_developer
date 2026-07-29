<?php
/**
 * Plugin Name: ARKRAY Translation Importer
 * Description: Bulk create or update translations (e.g. Vietnamese) for pages and custom post types from a single CSV upload. Includes a CSV exporter that produces a ready-to-translate file from the existing English content. Requires Polylang.
 * Version: 1.0.0
 * Author: ARKRAY
 * Text Domain: arkray-translation-importer
 *
 * @package ArkrayTranslationImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARKRAY_TI_VERSION', '1.0.0' );
define( 'ARKRAY_TI_DIR', plugin_dir_path( __FILE__ ) );
define( 'ARKRAY_TI_PAGE_SLUG', 'arkray-translation-importer' );

require_once ARKRAY_TI_DIR . 'includes/class-arkray-ti-csv.php';
require_once ARKRAY_TI_DIR . 'includes/class-arkray-ti-importer.php';
require_once ARKRAY_TI_DIR . 'includes/class-arkray-ti-admin.php';

if ( is_admin() ) {
	Arkray_TI_Admin::init();
}

/**
 * Whether the Polylang API needed by this plugin is available.
 *
 * @return bool
 */
function arkray_ti_polylang_ready() {
	return function_exists( 'pll_languages_list' )
		&& function_exists( 'pll_get_post' )
		&& function_exists( 'pll_set_post_language' )
		&& function_exists( 'pll_save_post_translations' )
		&& function_exists( 'pll_default_language' )
		&& function_exists( 'pll_is_translated_post_type' );
}

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( $links ) {
		$url = admin_url( 'tools.php?page=' . ARKRAY_TI_PAGE_SLUG );
		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Import / Export', 'arkray-translation-importer' ) . '</a>'
		);
		return $links;
	}
);
