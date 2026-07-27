<?php
/**
 * Plugin Name: ARKRAY External Content
 * Description: Imports the inner content of a remote page's #content_area into ARKRAY static pages. Relative asset paths are rewritten to absolute URLs and results are cached in transients. Source URLs are assigned per page via an ACF field or via the [arkray_external_content] shortcode, so new pages can be added without editing theme code.
 * Version: 1.0.0
 * Author: ARKRAY
 * Text Domain: arkray-external-content
 *
 * @package ArkrayExternalContent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ARKRAY_EXT_CONTENT_VERSION', '1.0.0' );
define( 'ARKRAY_EXT_PARSER_VERSION', '1.5' );
define( 'ARKRAY_EXT_CONTENT_DIR', plugin_dir_path( __FILE__ ) );

require_once ARKRAY_EXT_CONTENT_DIR . 'includes/class-content-parser.php';
require_once ARKRAY_EXT_CONTENT_DIR . 'includes/class-content-fetcher.php';
require_once ARKRAY_EXT_CONTENT_DIR . 'includes/acf-fields.php';

/**
 * Default base URL used when rewriting root-relative asset/link paths.
 */
function arkray_ext_default_base_url() {
	/**
	 * Filter the default base URL for relative-to-absolute path conversion.
	 *
	 * @param string $base_url Default base URL.
	 */
	return apply_filters( 'arkray_ext_default_base_url', 'https://www.arkray.global' );
}

/**
 * Hosts allowed as content sources. Requests to other hosts are rejected.
 */
function arkray_ext_allowed_hosts() {
	/**
	 * Filter the list of allowed source hosts.
	 *
	 * @param string[] $hosts Allowed host names.
	 */
	return apply_filters(
		'arkray_ext_allowed_hosts',
		array(
			'www.arkray.co.jp',
			'arkray.co.jp',
			'www.arkray.global',
			'arkray.global',
		)
	);
}

/**
 * Rewrite a navigation URL from imported external content.
 *
 * Resolves relative values against the source page URL, maps source-site
 * internal links onto the current WordPress site, and preserves external
 * destinations as absolute URLs.
 *
 * @param string $url        Raw href/data-href value.
 * @param string $source_url Fetched source page URL.
 * @return string|null Rewritten URL, or null when the value should be left untouched.
 */
function arkray_rewrite_external_content_url( $url, $source_url ) {
	$parser = new Arkray_Ext_Content_Parser();
	return $parser->rewrite_navigation_url( $url, $source_url );
}

/**
 * Fetch, parse, and return the external content for a URL.
 *
 * On any failure an empty string is returned so callers can fall back to
 * locally stored content.
 *
 * @param string $url  Source page URL.
 * @param array  $args {
 *     Optional. Override defaults.
 *
	 *     @type string $selector                        CSS-style id/class selector for the wrapper. Default '#content_area'.
	 *     @type string $base_url                        Base URL for root-relative paths. Default arkray_ext_default_base_url().
	 *     @type int    $cache_ttl                       Transient lifetime in seconds. Default DAY_IN_SECONDS.
	 *     @type bool   $force_refresh                   Bypass and refresh the cache. Default false.
	 *     @type bool   $preserve_source_navigation_urls Keep same-host href/data-href values as absolute source-site URLs instead of mapping them onto this WordPress site. Default false.
	 * }
 * @return string Sanitized inner HTML, or '' on failure.
 */
function arkray_get_external_content( $url, $args = array() ) {
	$defaults = array(
		'selector'                        => '#content_area',
		'base_url'                        => arkray_ext_default_base_url(),
		'cache_ttl'                       => DAY_IN_SECONDS,
		'force_refresh'                   => false,
		'preserve_source_navigation_urls' => false,
	);
	$args = wp_parse_args( $args, $defaults );

	if ( empty( $args['base_url'] ) ) {
		$args['base_url'] = arkray_ext_default_base_url();
	}
	if ( (int) $args['cache_ttl'] <= 0 ) {
		$args['cache_ttl'] = DAY_IN_SECONDS;
	}

	$fetcher = new Arkray_Ext_Content_Fetcher();
	return $fetcher->get( $url, $args );
}

/**
 * Fetch external content using the most complete known wrapper on the source page.
 *
 * Some ARKRAY pages place the primary body in #content_area (e.g. products/index.html)
 * while others nest everything inside #editor_area (e.g. sustainability/action.html).
 * When no explicit selector is passed, try both and keep the largest non-empty result.
 *
 * @param string $url  Source page URL.
 * @param array  $args Optional args for arkray_get_external_content().
 * @return string Sanitized inner HTML, or '' on failure.
 */
function arkray_get_external_content_best( $url, $args = array() ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}

	$args = wp_parse_args( $args, array( 'selector' => '' ) );

	if ( '' !== trim( (string) $args['selector'] ) ) {
		return arkray_unwrap_external_editor_area(
			arkray_get_external_content( $url, $args )
		);
	}

	$selectors = array( '#content_area', '#editor_area' );
	$best      = '';

	foreach ( $selectors as $selector ) {
		$chunk = arkray_get_external_content(
			$url,
			array_merge(
				$args,
				array( 'selector' => $selector )
			)
		);
		if ( strlen( $chunk ) > strlen( $best ) ) {
			$best = $chunk;
		}
	}

	return arkray_unwrap_external_editor_area( $best );
}

/**
 * Remove a single outer #editor_area wrapper when it is the only root element.
 *
 * @param string $html Imported HTML.
 * @return string
 */
function arkray_unwrap_external_editor_area( $html ) {
	$html = trim( (string) $html );
	if ( '' === $html ) {
		return $html;
	}

	if ( preg_match(
		'#^\s*<div\b[^>]*\bid=(["\'])editor_area\1[^>]*>(.*)</div>\s*$#is',
		$html,
		$matches
	) ) {
		return trim( $matches[2] );
	}

	return $html;
}

add_shortcode( 'arkray_external_content', 'arkray_ext_content_shortcode' );

/**
 * Shortcode handler: [arkray_external_content url="..." selector="#content_area" base="..." cache="24"].
 *
 * @param array $atts Shortcode attributes.
 * @return string Imported HTML or empty string.
 */
function arkray_ext_content_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'url'      => '',
			'selector' => '#content_area',
			'base'     => '',
			'cache'    => '',
		),
		$atts,
		'arkray_external_content'
	);

	if ( '' === trim( $atts['url'] ) ) {
		return '';
	}

	$args = array(
		'selector' => $atts['selector'],
	);
	if ( '' !== trim( $atts['base'] ) ) {
		$args['base_url'] = $atts['base'];
	}
	if ( '' !== trim( $atts['cache'] ) ) {
		$args['cache_ttl'] = max( 1, (int) $atts['cache'] ) * HOUR_IN_SECONDS;
	}

	return arkray_get_external_content( $atts['url'], $args );
}
