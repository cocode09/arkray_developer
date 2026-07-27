<?php
/**
 * ACF field group for assigning a source URL per page (code-registered,
 * DB-independent), matching the theme's existing acf/init pattern.
 *
 * @package ArkrayExternalContent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'acf/init', 'arkray_ext_register_acf_fields' );

/**
 * Register the External Content field group on all Pages.
 *
 * @return void
 */
function arkray_ext_register_acf_fields() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'                   => 'group_arkray_external_content',
			'title'                 => 'External Content Import',
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'page',
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'active'                => true,
			'description'           => 'Import a remote page\'s #content_area into this page. Leave the URL blank to use the page\'s normal content.',
			'fields'                => array(
				array(
					'key'          => 'field_external_content_url',
					'label'        => 'External Content URL',
					'name'         => 'external_content_url',
					'type'         => 'url',
					'instructions' => 'Full URL of the source page (e.g. https://www.arkray.co.jp/english/about/philosophy.html). Only the inner content of #content_area is imported.',
				),
				array(
					'key'          => 'field_external_content_base_url',
					'label'        => 'Asset Base URL (optional)',
					'name'         => 'external_content_base_url',
					'type'         => 'url',
					'instructions' => 'Base used to convert root-relative paths (e.g. /img/...) to absolute URLs. Defaults to https://www.arkray.global.',
				),
				array(
					'key'               => 'field_external_content_cache_hours',
					'label'             => 'Cache Duration (hours, optional)',
					'name'              => 'external_content_cache_hours',
					'type'              => 'number',
					'instructions'      => 'How long to cache the imported content before re-fetching. Defaults to 24 hours.',
					'min'               => 0,
					'placeholder'       => '24',
				),
			),
		)
	);
}
