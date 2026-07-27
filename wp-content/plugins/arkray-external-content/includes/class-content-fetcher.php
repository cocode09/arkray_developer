<?php
/**
 * Remote content fetcher with transient caching.
 *
 * @package ArkrayExternalContent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arkray_Ext_Content_Fetcher {

	const CACHE_PREFIX = 'arkray_ext_';

	/**
	 * Resolve content for a URL, using the cache when available.
	 *
	 * @param string $url  Source URL.
	 * @param array  $args Resolved args from arkray_get_external_content().
	 * @return string Sanitized inner HTML, or '' on failure.
	 */
	public function get( $url, $args ) {
		$url = trim( (string) $url );
		if ( ! $this->is_allowed_url( $url ) ) {
			return '';
		}

		$nav_mode = ! empty( $args['preserve_source_navigation_urls'] ) ? 'origin' : 'local';
		$cache_key = self::CACHE_PREFIX . md5(
			$url . '|' . $args['selector'] . '|' . $args['base_url'] . '|' . ( ! empty( $args['cache_extra'] ) ? (string) $args['cache_extra'] : '' ) . '|' . $nav_mode . '|' . ARKRAY_EXT_PARSER_VERSION
		);

		if ( empty( $args['force_refresh'] ) ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return (string) $cached;
			}
		}

		$html = $this->request( $url );
		if ( '' === $html ) {
			return '';
		}

		$parser  = new Arkray_Ext_Content_Parser();
		$content = $parser->extract(
			$html,
			$args['selector'],
			$url,
			$args['base_url'],
			array(
				'preserve_source_navigation_urls' => ! empty( $args['preserve_source_navigation_urls'] ),
			)
		);
		if ( '' === $content ) {
			return '';
		}

		set_transient( $cache_key, $content, (int) $args['cache_ttl'] );

		return $content;
	}

	/**
	 * Validate the URL scheme and host against the allow-list.
	 *
	 * @param string $url URL to check.
	 * @return bool
	 */
	private function is_allowed_url( $url ) {
		if ( '' === $url ) {
			return false;
		}

		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}
		if ( ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return false;
		}

		return in_array( strtolower( $parts['host'] ), arkray_ext_allowed_hosts(), true );
	}

	/**
	 * Perform the HTTP GET request.
	 *
	 * @param string $url Source URL.
	 * @return string Response body, or '' on failure.
	 */
	private function request( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'user-agent' => 'ARKRAY-WP-Importer/' . ARKRAY_EXT_CONTENT_VERSION,
				'headers'    => array(
					'Accept'          => 'text/html,application/xhtml+xml',
					'Accept-Language' => 'en',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		return (string) wp_remote_retrieve_body( $response );
	}
}
