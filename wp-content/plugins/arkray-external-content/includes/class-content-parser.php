<?php
/**
 * Extracts a wrapper element, rewrites navigation links onto the current site,
 * resolves relative asset URLs to absolute, and sanitizes the resulting HTML.
 *
 * @package ArkrayExternalContent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Arkray_Ext_Content_Parser {

	/**
	 * Navigation attributes rewritten onto the current WordPress site when internal.
	 *
	 * @var string[]
	 */
	private $navigation_attributes = array( 'href', 'data-href' );

	/**
	 * Asset attributes converted to absolute source/base URLs only.
	 *
	 * @var string[]
	 */
	private $asset_attributes = array( 'src', 'poster', 'data-src' );

	/**
	 * When true, same-host navigation links stay as absolute source-site URLs.
	 *
	 * @var bool
	 */
	private $preserve_source_navigation_urls = false;

	/**
	 * Extract the inner HTML of the selector, resolve URLs, and sanitize.
	 *
	 * @param string $html     Full source HTML.
	 * @param string $selector Selector for the wrapper (e.g. '#content_area' or '.foo').
	 * @param string $page_url Source page URL (for resolving document-relative paths).
	 * @param string $base_url Base URL for root-relative paths.
	 * @param array  $options  Optional extract options.
	 * @return string Sanitized inner HTML, or '' if the wrapper is missing.
	 */
	public function extract( $html, $selector, $page_url, $base_url, $options = array() ) {
		$html = (string) $html;
		if ( '' === $html ) {
			return '';
		}

		$options = wp_parse_args(
			is_array( $options ) ? $options : array(),
			array(
				'preserve_source_navigation_urls' => false,
			)
		);
		$this->preserve_source_navigation_urls = ! empty( $options['preserve_source_navigation_urls'] );

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
		$node  = $this->find_node( $xpath, $selector );
		if ( ! $node ) {
			libxml_clear_errors();
			return '';
		}

		$this->rewrite_urls( $xpath, $node, $page_url, $base_url );

		$inner = $this->inner_html( $node );
		libxml_clear_errors();

		return $this->sanitize( $inner );
	}

	/**
	 * Locate the wrapper node from an id (#foo) or class (.foo) selector.
	 *
	 * @param DOMXPath $xpath    XPath instance.
	 * @param string   $selector Selector string.
	 * @return DOMNode|null
	 */
	private function find_node( $xpath, $selector ) {
		$selector = trim( (string) $selector );
		if ( '' === $selector ) {
			$selector = '#content_area';
		}

		if ( 0 === strpos( $selector, '.' ) ) {
			$class = substr( $selector, 1 );
			$query = "//*[contains(concat(' ', normalize-space(@class), ' '), ' " . $class . " ')]";
		} else {
			$id    = ( 0 === strpos( $selector, '#' ) ) ? substr( $selector, 1 ) : $selector;
			$query = "//*[@id='" . $id . "']";
		}

		$nodes = $xpath->query( $query );
		return ( $nodes && $nodes->length ) ? $nodes->item( 0 ) : null;
	}

	/**
	 * Rewrite navigation and asset URLs within the node.
	 *
	 * @param DOMXPath $xpath    XPath instance.
	 * @param DOMNode  $node     Wrapper node.
	 * @param string   $page_url Source page URL.
	 * @param string   $base_url Base URL for root-relative asset paths.
	 * @return void
	 */
	private function rewrite_urls( $xpath, $node, $page_url, $base_url ) {
		$elements = $xpath->query( './/*', $node );
		if ( ! $elements ) {
			return;
		}

		foreach ( $elements as $el ) {
			if ( ! ( $el instanceof DOMElement ) ) {
				continue;
			}
			foreach ( $this->navigation_attributes as $attr ) {
				if ( ! $el->hasAttribute( $attr ) ) {
					continue;
				}
				$value = trim( $el->getAttribute( $attr ) );
				// Original-site internal links become current-site internal links;
				// original-site external links remain absolute external URLs.
				$rewritten = $this->rewrite_navigation_url( $value, $page_url );
				if ( null !== $rewritten && $rewritten !== $value ) {
					$el->setAttribute( $attr, $rewritten );
				}
			}
			foreach ( $this->asset_attributes as $attr ) {
				if ( ! $el->hasAttribute( $attr ) ) {
					continue;
				}
				$value = trim( $el->getAttribute( $attr ) );
				$abs   = $this->to_absolute( $value, $page_url, $base_url );
				if ( null !== $abs && $abs !== $value ) {
					$el->setAttribute( $attr, $abs );
				}
			}
			if ( $el->hasAttribute( 'srcset' ) ) {
				$el->setAttribute( 'srcset', $this->rewrite_srcset( $el->getAttribute( 'srcset' ), $page_url, $base_url ) );
			}
		}
	}

	/**
	 * Rewrite a navigation URL from imported external content.
	 *
	 * Resolves relative values against the source page, then maps source-site
	 * internal links onto the current WordPress site while preserving external
	 * destinations as absolute URLs.
	 *
	 * @param string $value    Raw href/data-href value.
	 * @param string $page_url Source page URL.
	 * @return string|null Rewritten URL, or null when the value should be left untouched.
	 */
	public function rewrite_navigation_url( $value, $page_url ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}
		if ( '#' === $value[0] ) {
			return null;
		}
		if ( preg_match( '#^(?:mailto:|tel:|sms:|javascript:|data:)#i', $value ) ) {
			return null;
		}

		$source_parts = wp_parse_url( $page_url );
		if ( empty( $source_parts['host'] ) ) {
			return null;
		}

		$source_host   = strtolower( $source_parts['host'] );
		$source_scheme = ! empty( $source_parts['scheme'] ) ? strtolower( $source_parts['scheme'] ) : 'https';

		$absolute = $this->resolve_navigation_absolute( $value, $page_url, $source_scheme, $source_host );
		if ( null === $absolute ) {
			return null;
		}

		$resolved = wp_parse_url( $absolute );
		if ( empty( $resolved['host'] ) ) {
			return null;
		}

		if ( strtolower( $resolved['host'] ) === $source_host ) {
			if ( $this->preserve_source_navigation_urls ) {
				$sanitized = esc_url_raw( $absolute );
				return ( $sanitized !== $value ) ? $sanitized : null;
			}

			return $this->build_url_with_site_origin( $resolved );
		}

		$sanitized = esc_url_raw( $absolute );
		return ( $sanitized !== $value ) ? $sanitized : null;
	}

	/**
	 * Resolve a navigation URL to an absolute http(s) URL against the source page.
	 *
	 * @param string $value         Raw attribute value.
	 * @param string $page_url      Source page URL.
	 * @param string $source_scheme Source URL scheme.
	 * @param string $source_host   Source URL host.
	 * @return string|null
	 */
	private function resolve_navigation_absolute( $value, $page_url, $source_scheme, $source_host ) {
		if ( 0 === strpos( $value, '//' ) ) {
			return $this->normalize_url( 'https:' . $value );
		}
		if ( preg_match( '#^https?://#i', $value ) ) {
			return $this->normalize_url( $value );
		}
		if ( 0 === strpos( $value, '/' ) ) {
			return $this->normalize_url( $source_scheme . '://' . $source_host . $value );
		}

		$dir = $this->page_dir_url( $page_url );
		if ( '' === $dir ) {
			return $this->normalize_url( $source_scheme . '://' . $source_host . '/' . ltrim( $value, '/' ) );
		}

		return $this->normalize_url( $dir . $value );
	}

	/**
	 * Rebuild a URL using the current WordPress site origin.
	 *
	 * Maps known legacy source paths to theme page URLs when available, then
	 * falls back to home_url() so subdirectory installs keep the correct base.
	 *
	 * @param array $url_parts Parsed URL components to preserve beyond the host.
	 * @return string
	 */
	private function build_url_with_site_origin( $url_parts ) {
		$mapped = $this->map_internal_path_to_site_url( $url_parts );
		if ( null !== $mapped ) {
			return esc_url_raw( $this->strip_html_extension_from_navigation_url( $mapped ) );
		}

		$path = isset( $url_parts['path'] ) ? $url_parts['path'] : '/';
		$relative = $path;
		if ( ! empty( $url_parts['query'] ) ) {
			$relative .= '?' . $url_parts['query'];
		}
		if ( ! empty( $url_parts['fragment'] ) ) {
			$relative .= '#' . $url_parts['fragment'];
		}

		return esc_url_raw(
			$this->strip_html_extension_from_navigation_url( home_url( $relative ) )
		);
	}

	/**
	 * Remove a trailing .html extension from a rewritten navigation URL path.
	 *
	 * Asset URLs (src, srcset, etc.) are not passed through this helper.
	 *
	 * @param string $url Absolute or site-relative navigation URL.
	 * @return string
	 */
	private function strip_html_extension_from_navigation_url( $url ) {
		$url = (string) $url;
		if ( '' === $url || ! preg_match( '/\.html([?#]|$)/i', $url ) ) {
			return $url;
		}

		return preg_replace( '/\.html(?=([?#]|$))/i', '', $url );
	}

	/**
	 * Map a resolved source-site path to a WordPress page URL when possible.
	 *
	 * @param array $url_parts Parsed absolute URL components.
	 * @return string|null Mapped URL, or null to fall back to path-preserving rewrite.
	 */
	private function map_internal_path_to_site_url( $url_parts ) {
		$path = isset( $url_parts['path'] ) ? (string) $url_parts['path'] : '';
		$rel  = $this->site_relative_path( $path );
		if ( '' === $rel ) {
			return null;
		}

		$query    = ! empty( $url_parts['query'] ) ? (string) $url_parts['query'] : '';
		$fragment = ! empty( $url_parts['fragment'] ) ? (string) $url_parts['fragment'] : '';
		$mapped   = $this->resolve_known_legacy_path( $rel, $query, $fragment );

		/**
		 * Filter a mapped internal navigation URL before falling back to home_url().
		 *
		 * @param string $mapped   Mapped URL, or '' when no mapping was found.
		 * @param array  $url_parts Parsed absolute URL components.
		 * @param string $rel      Site-relative path derived from the source URL.
		 */
		$mapped = apply_filters( 'arkray_ext_map_navigation_url', $mapped, $url_parts, $rel );

		return ( is_string( $mapped ) && '' !== $mapped ) ? $mapped : null;
	}

	/**
	 * Strip the WordPress install path prefix from an absolute URL path.
	 *
	 * @param string $path Absolute URL path.
	 * @return string
	 */
	private function site_relative_path( $path ) {
		$path      = ltrim( (string) $path, '/' );
		$home_path = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );

		if ( '' !== $home_path ) {
			if ( $path === $home_path ) {
				return '';
			}
			if ( 0 === strpos( $path, $home_path . '/' ) ) {
				return substr( $path, strlen( $home_path ) + 1 );
			}
		}

		return $path;
	}

	/**
	 * Resolve legacy ARKRAY source HTML paths to theme page URLs.
	 *
	 * @param string $rel      Site-relative path (e.g. english/about/philosophy.html).
	 * @param string $query    Query string from the source URL.
	 * @param string $fragment Fragment from the source URL.
	 * @return string Mapped URL or empty string.
	 */
	private function resolve_known_legacy_path( $rel, $query, $fragment ) {
		$append = static function ( $url ) use ( $query, $fragment ) {
			if ( '' !== $query ) {
				$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $query;
			}
			if ( '' !== $fragment ) {
				$url .= '#' . $fragment;
			}
			return $url;
		};

		if ( preg_match( '#^(?:english|vietnamese)/about/(?P<file>[^/]+)\.html$#i', $rel, $matches ) ) {
			$page_key = $this->legacy_about_file_to_page_key( $matches['file'] );
			if ( null !== $page_key && function_exists( 'arkray_get_about_page_url' ) ) {
				return $append( arkray_get_about_page_url( $page_key ) );
			}
		}

		if ( preg_match( '#^(?:english|vietnamese)/sustainability/(?P<file>[^/]+)\.html$#i', $rel, $matches ) ) {
			$page_key = $this->legacy_sustainability_file_to_page_key( $matches['file'] );
			if ( null !== $page_key && function_exists( 'arkray_get_sustainability_page_url' ) ) {
				return $append( arkray_get_sustainability_page_url( $page_key ) );
			}
		}

		if ( preg_match( '#^(?:english|vietnamese)/sustainability/action/supplychain/post_32\.html$#i', $rel ) ) {
			if ( function_exists( 'arkray_get_about_page_url' ) ) {
				return $append( arkray_get_about_page_url( 'arkray-action-guidelines' ) );
			}
		}

		if ( preg_match( '#^(?:english|vietnamese)/news/index\.html$#i', $rel ) ) {
			if ( function_exists( 'arkray_home_url' ) ) {
				return $append( arkray_home_url( '/news/' ) );
			}
		}

		if ( preg_match( '#^(?:english|vietnamese)/products/index\.html$#i', $rel ) ) {
			if ( function_exists( 'arkray_home_url' ) ) {
				return $append( arkray_home_url( '/products/' ) );
			}
		}

		if ( preg_match( '#^(?:english|vietnamese)/history/index\.html$#i', $rel ) ) {
			if ( function_exists( 'arkray_get_history_page_url' ) ) {
				return $append( arkray_get_history_page_url() );
			}
		}

		if ( preg_match( '#^(?:english|vietnamese)/products/history/urinalysis\.html$#i', $rel ) ) {
			if ( function_exists( 'arkray_get_history_page_url' ) ) {
				return $append( arkray_get_history_page_url( 'urinalysis' ) );
			}
		}

		if ( preg_match( '#^(?:english|vietnamese)/products/history/poc_testing01\.html$#i', $rel ) ) {
			if ( function_exists( 'arkray_get_history_page_url' ) ) {
				return $append( arkray_get_history_page_url( 'dry-chemistry-testing' ) );
			}
		}

		if ( preg_match( '#^(?:english|vietnamese)/products/history/poc_testing02\.html$#i', $rel ) ) {
			if ( function_exists( 'arkray_get_history_page_url' ) ) {
				return $append( arkray_get_history_page_url( 'poc-testing-02' ) );
			}
		}

		if ( preg_match( '#^(?:english|vietnamese)/products/history/smbg\.html$#i', $rel ) ) {
			if ( function_exists( 'arkray_get_history_page_url' ) ) {
				return $append( arkray_get_history_page_url( 'bgm' ) );
			}
		}

		return '';
	}

	/**
	 * Map a legacy About HTML filename to a WordPress page key.
	 *
	 * @param string $file Legacy filename without extension.
	 * @return string|null
	 */
	private function legacy_about_file_to_page_key( $file ) {
		if ( 'index' === $file ) {
			return 'about-us';
		}
		if ( preg_match( '#^history(\d{4})$#i', $file ) ) {
			return null;
		}

		$map = array(
			'philosophy'        => 'arkray-philosophy',
			'action_guidelines' => 'arkray-action-guidelines',
			'message'           => 'message-from-arkray',
			'concept'           => 'brand-concept',
			'profile'           => 'about-contact',
			'business'          => 'corporate-outline',
			'group'             => 'arkray-group',
			'group01'           => 'arkray-group',
			'group02'           => 'arkray-group-2',
			'group03'           => 'arkray-group-3',
			'group04'           => 'arkray-group-4',
			'group05'           => 'arkray-group-5',
			'access'            => 'access',
		);

		return isset( $map[ $file ] ) ? $map[ $file ] : null;
	}

	/**
	 * Map a legacy Sustainability HTML filename to a WordPress page key.
	 *
	 * @param string $file Legacy filename without extension.
	 * @return string|null
	 */
	private function legacy_sustainability_file_to_page_key( $file ) {
		if ( 'index' === $file ) {
			return 'sustainability';
		}

		$map = array(
			'commitment'  => 'top-commitment',
			'policy'      => 'sdgs-basic-policy',
			'materiality' => 'arkrays-materiality',
			'action'      => 'sdgs-initiatives',
		);

		return isset( $map[ $file ] ) ? $map[ $file ] : null;
	}

	/**
	 * Convert a single URL to absolute form.
	 *
	 * @param string $value    Raw attribute value.
	 * @param string $page_url Source page URL.
	 * @param string $base_url Base URL for root-relative paths.
	 * @return string|null Absolute URL, or null when it should be left untouched.
	 */
	private function to_absolute( $value, $page_url, $base_url ) {
		if ( '' === $value ) {
			return null;
		}

		// Non-navigational or already-absolute values: leave alone.
		if ( preg_match( '#^(?:https?:)?//#i', $value ) ) {
			// Protocol-relative -> force https.
			if ( 0 === strpos( $value, '//' ) ) {
				return 'https:' . $value;
			}
			return null;
		}
		if ( preg_match( '#^(?:mailto:|tel:|sms:|javascript:|data:|\#)#i', $value ) ) {
			return null;
		}

		$base_url = untrailingslashit( $base_url );

		// Root-relative: resolve against the configured base URL.
		if ( 0 === strpos( $value, '/' ) ) {
			return $base_url . $value;
		}

		// Document-relative: resolve against the source page's directory.
		$dir = $this->page_dir_url( $page_url );
		if ( '' === $dir ) {
			return $base_url . '/' . ltrim( $value, '/' );
		}

		return $this->normalize_url( $dir . $value );
	}

	/**
	 * Rewrite a srcset attribute (comma-separated "url descriptor" pairs).
	 *
	 * @param string $srcset   Raw srcset value.
	 * @param string $page_url Source page URL.
	 * @param string $base_url Base URL for root-relative paths.
	 * @return string
	 */
	private function rewrite_srcset( $srcset, $page_url, $base_url ) {
		$parts = array_map( 'trim', explode( ',', $srcset ) );
		foreach ( $parts as $i => $part ) {
			if ( '' === $part ) {
				continue;
			}
			$bits = preg_split( '/\s+/', $part, 2 );
			$abs  = $this->to_absolute( $bits[0], $page_url, $base_url );
			if ( null !== $abs ) {
				$bits[0]   = $abs;
				$parts[ $i ] = trim( implode( ' ', $bits ) );
			}
		}
		return implode( ', ', array_filter( $parts ) );
	}

	/**
	 * Directory URL of a source page (scheme://host/path/).
	 *
	 * @param string $page_url Source page URL.
	 * @return string
	 */
	private function page_dir_url( $page_url ) {
		$parts = wp_parse_url( $page_url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		$path = isset( $parts['path'] ) ? $parts['path'] : '/';
		$dir  = ( '/' === substr( $path, -1 ) ) ? $path : dirname( $path );
		$dir  = ( '.' === $dir || '' === $dir ) ? '/' : trailingslashit( $dir );

		return $parts['scheme'] . '://' . $parts['host'] . $dir;
	}

	/**
	 * Collapse ./ and ../ segments in an absolute URL.
	 *
	 * @param string $url URL to normalize.
	 * @return string
	 */
	private function normalize_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) || empty( $parts['path'] ) ) {
			return $url;
		}

		$segments = explode( '/', $parts['path'] );
		$stack    = array();
		foreach ( $segments as $seg ) {
			if ( '..' === $seg ) {
				array_pop( $stack );
			} elseif ( '.' !== $seg && '' !== $seg ) {
				$stack[] = $seg;
			}
		}
		$path = '/' . implode( '/', $stack );
		if ( '/' === substr( $parts['path'], -1 ) && '/' !== substr( $path, -1 ) ) {
			$path .= '/';
		}

		$out = $parts['scheme'] . '://' . $parts['host'] . $path;
		if ( ! empty( $parts['query'] ) ) {
			$out .= '?' . $parts['query'];
		}
		if ( ! empty( $parts['fragment'] ) ) {
			$out .= '#' . $parts['fragment'];
		}
		return $out;
	}

	/**
	 * Serialize the inner HTML of a node (children only).
	 *
	 * @param DOMNode $node Wrapper node.
	 * @return string
	 */
	private function inner_html( $node ) {
		$html = '';
		if ( ! $node || ! $node->hasChildNodes() ) {
			return $html;
		}
		foreach ( $node->childNodes as $child ) {
			$html .= $node->ownerDocument->saveHTML( $child );
		}
		return $html;
	}

	/**
	 * Strip dangerous interactive/executable markup while preserving layout
	 * hooks (classes, inline <style>, tables) used by the verbatim ARKRAY CSS.
	 *
	 * @param string $html Inner HTML.
	 * @return string
	 */
	private function sanitize( $html ) {
		$html = (string) $html;

		// Drop every iframe whose src is not a trusted video host (e.g. YouTube),
		// while keeping the trusted ones so embedded videos still render.
		$html = $this->filter_iframes( $html );

		$html = preg_replace(
			'#<(script|object|embed|form|input|button|select|option|textarea)[^>]*>.*?</\1>#is',
			'',
			$html
		);
		$html = preg_replace(
			'#<(script|object|embed|form|input|button|select|option|textarea)[^>]*/?\s*>#is',
			'',
			$html
		);
		$html = preg_replace( '/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $html );
		$html = preg_replace( '/\b(href|src|action)\s*=\s*(["\']?)\s*javascript:[^\s"\'>{]*/i', '$1=$2#', $html );

		return $html;
	}

	/**
	 * Remove all <iframe> elements except those pointing at a trusted video host.
	 *
	 * @param string $html Inner HTML.
	 * @return string
	 */
	private function filter_iframes( $html ) {
		// Match paired <iframe ...>...</iframe> and any standalone/self-closing <iframe ...>.
		$html = preg_replace_callback(
			'#<iframe\b[^>]*>(?:.*?</iframe>)?#is',
			array( $this, 'iframe_callback' ),
			$html
		);

		return $html;
	}

	/**
	 * Decide whether a matched iframe is kept (trusted host) or removed.
	 *
	 * @param array $matches Regex matches; $matches[0] is the full iframe markup.
	 * @return string Original markup when trusted, empty string otherwise.
	 */
	private function iframe_callback( $matches ) {
		$iframe = $matches[0];

		if ( ! preg_match( '/\bsrc\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $iframe, $m ) ) {
			return '';
		}
		$src = '' !== $m[1] ? $m[1] : ( '' !== $m[2] ? $m[2] : ( isset( $m[3] ) ? $m[3] : '' ) );
		$src = html_entity_decode( trim( $src ), ENT_QUOTES );

		if ( 0 === strpos( $src, '//' ) ) {
			$src = 'https:' . $src;
		}

		$host = strtolower( (string) wp_parse_url( $src, PHP_URL_HOST ) );
		if ( '' === $host ) {
			return '';
		}

		foreach ( $this->allowed_iframe_hosts() as $allowed ) {
			$allowed = strtolower( $allowed );
			if ( $host === $allowed || $this->str_ends_with( $host, '.' . $allowed ) ) {
				return $iframe;
			}
		}

		return '';
	}

	/**
	 * Trusted iframe hosts whose embeds are preserved during sanitization.
	 *
	 * @return string[]
	 */
	private function allowed_iframe_hosts() {
		$hosts = array(
			'youtube.com',
			'youtube-nocookie.com',
			'youtu.be',
			'player.vimeo.com',
			'arkray.co.jp',
		);

		/**
		 * Filter the list of iframe hosts allowed through the sanitizer.
		 *
		 * @param string[] $hosts Allowed iframe host names.
		 */
		return apply_filters( 'arkray_ext_allowed_iframe_hosts', $hosts );
	}

	/**
	 * Polyfill for str_ends_with() on PHP < 8.0.
	 *
	 * @param string $haystack Subject string.
	 * @param string $needle   Suffix to check for.
	 * @return bool
	 */
	private function str_ends_with( $haystack, $needle ) {
		if ( function_exists( 'str_ends_with' ) ) {
			return str_ends_with( $haystack, $needle );
		}
		$len = strlen( $needle );
		if ( 0 === $len ) {
			return true;
		}
		return substr( $haystack, -$len ) === $needle;
	}
}
