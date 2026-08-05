<?php
/**
 * Splits post content into individually addressable blocks and writes
 * translations back into the original markup.
 *
 * @package ArkrayTranslationImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block view of an HTML document.
 *
 * Every paragraph, heading, list item, table cell and image of a page is a
 * block with its own key, so the CSV can carry one row per block and contain
 * plain text instead of markup. {@see apply()} walks the same document again and
 * puts the translations back between the original tags, which keeps the stored
 * content valid HTML.
 *
 * Keys describe where a block sits, not how far into the document it is:
 *
 *   - `title`            the post title (handled by the importer, not here)
 *   - `p01`, `p02`       paragraphs and other text containers
 *   - `h01`, `h02`       headings
 *   - `li01`, `li02`     list items
 *   - `cap01`            figure captions
 *   - `tbl1-r2-c1`       table 1, row 2, column 1
 *   - `tbl1-caption`     the caption element of table 1
 *   - `img01`, `img02`   images
 *
 * When a container holds text on both sides of an inline tag, the later pieces
 * get `-2`, `-3` … appended (`p03-2`), so a sentence broken by a link stays
 * recognisable as one paragraph.
 *
 * The document is walked with the WordPress HTML API, so tags, attributes,
 * comments and shortcodes are left exactly as they were and text is read and
 * written with the correct HTML encoding.
 */
class Arkray_TI_Blocks {

	/**
	 * Elements that flow inside a line of text and therefore never start a block
	 * of their own.
	 */
	const INLINE_ELEMENTS = array(
		'A',
		'ABBR',
		'ACRONYM',
		'B',
		'BDI',
		'BDO',
		'BIG',
		'BR',
		'BUTTON',
		'CITE',
		'CODE',
		'DATA',
		'DEL',
		'DFN',
		'EM',
		'FONT',
		'I',
		'IMG',
		'INS',
		'KBD',
		'LABEL',
		'MARK',
		'NOBR',
		'Q',
		'RP',
		'RT',
		'RUBY',
		'S',
		'SAMP',
		'SMALL',
		'SPAN',
		'STRIKE',
		'STRONG',
		'SUB',
		'SUP',
		'TIME',
		'TT',
		'U',
		'VAR',
		'WBR',
	);

	/**
	 * Elements that never have a closing tag.
	 */
	const VOID_ELEMENTS = array(
		'AREA',
		'BASE',
		'BR',
		'COL',
		'EMBED',
		'HR',
		'IMG',
		'INPUT',
		'LINK',
		'META',
		'PARAM',
		'SOURCE',
		'TRACK',
		'WBR',
	);

	/**
	 * Elements whose text is preformatted or not prose, and whose contents are
	 * therefore never offered for translation.
	 *
	 * Text inside SCRIPT, STYLE, TEXTAREA and friends never appears as a text
	 * node in the HTML API, so those elements need no entry here.
	 */
	const OPAQUE_ELEMENTS = array( 'PRE', 'LISTING', 'SVG', 'MATH' );

	/**
	 * Elements that close an open sibling of the same kind, so content stays in
	 * the right cell or list item even when the markup omits closing tags.
	 */
	const IMPLICIT_CLOSERS = array(
		'P'      => array( 'P' ),
		'LI'     => array( 'LI' ),
		'TD'     => array( 'TD', 'TH' ),
		'TH'     => array( 'TD', 'TH' ),
		'TR'     => array( 'TD', 'TH', 'TR' ),
		'DT'     => array( 'DT', 'DD' ),
		'DD'     => array( 'DT', 'DD' ),
		'OPTION' => array( 'OPTION' ),
	);

	/**
	 * Whether the WordPress HTML API supports reading and rewriting content.
	 *
	 * @return bool
	 */
	public static function is_supported() {
		return class_exists( 'WP_HTML_Tag_Processor' )
			&& method_exists( 'WP_HTML_Tag_Processor', 'next_token' )
			&& method_exists( 'WP_HTML_Tag_Processor', 'set_modifiable_text' );
	}

	/**
	 * List the translatable blocks of a document, in document order.
	 *
	 * @param string $content Post content (HTML).
	 * @return array[] {
	 *     @type string $key     Block key, e.g. 'p01' or 'tbl1-r1-c2'.
	 *     @type string $kind    'text' or 'image'.
	 *     @type string $label   Human readable description of the position.
	 *     @type string $text    Plain text (text blocks).
	 *     @type string $file    Image file name (image blocks).
	 *     @type string $src     Image source as written in the markup (image blocks).
	 *     @type string $caption Image alt text (image blocks).
	 * }
	 */
	public static function extract( $content ) {
		$content = (string) $content;
		if ( '' === trim( $content ) || ! self::is_supported() ) {
			return array();
		}

		$blocks = array();

		self::walk(
			$content,
			static function ( $block, $processor ) use ( &$blocks ) {
				if ( 'image' === $block['kind'] ) {
					$src              = $processor->get_attribute( 'src' );
					$alt              = $processor->get_attribute( 'alt' );
					$block['src']     = is_string( $src ) ? $src : '';
					$block['file']    = self::file_name( $block['src'] );
					$block['caption'] = is_string( $alt ) ? self::to_plain_text( $alt ) : '';
				}
				$blocks[] = $block;
			}
		);

		return $blocks;
	}

	/**
	 * The keys of a block list, for comparing two versions of a document.
	 *
	 * @param array[] $blocks Blocks from extract().
	 * @return string[]
	 */
	public static function keys( array $blocks ) {
		return wp_list_pluck( $blocks, 'key' );
	}

	/**
	 * Index a block list by key.
	 *
	 * @param array[] $blocks Blocks from extract().
	 * @param string  $kind   Optional kind filter ('text' or 'image').
	 * @return array[] Blocks keyed by block key.
	 */
	public static function by_key( array $blocks, $kind = '' ) {
		$indexed = array();
		foreach ( $blocks as $block ) {
			if ( '' === $kind || $kind === $block['kind'] ) {
				$indexed[ $block['key'] ] = $block;
			}
		}
		return $indexed;
	}

	/**
	 * Rebuild a document with translated text and swapped images.
	 *
	 * Blocks without a replacement keep what they had, so the result is
	 * identical to the input when nothing is passed in.
	 *
	 * @param string $content Post content (HTML) providing the markup.
	 * @param array  $texts   Plain text keyed by block key.
	 * @param array  $images  Image changes keyed by block key: src, alt, width,
	 *                        height, attachment_id (all optional).
	 * @return string
	 */
	public static function apply( $content, array $texts, array $images = array() ) {
		$content = (string) $content;
		if ( '' === trim( $content ) || ( empty( $texts ) && empty( $images ) ) || ! self::is_supported() ) {
			return $content;
		}

		$processor = self::walk(
			$content,
			static function ( $block, $processor ) use ( $texts, $images ) {
				$key = $block['key'];

				if ( 'image' === $block['kind'] ) {
					if ( isset( $images[ $key ] ) ) {
						self::apply_image( $processor, (array) $images[ $key ] );
					}
					return;
				}

				if ( isset( $texts[ $key ] ) && '' !== trim( (string) $texts[ $key ] ) ) {
					$processor->set_modifiable_text(
						self::pad_like( $processor->get_modifiable_text(), (string) $texts[ $key ] )
					);
				}
			}
		);

		return $processor->get_updated_html();
	}

	/**
	 * Walk a document once, reporting every block to a callback.
	 *
	 * Both extract() and apply() go through here, so a block always gets the
	 * same key whether it is being read or written.
	 *
	 * @param string   $content  Post content (HTML).
	 * @param callable $on_block Receives ( array $block, WP_HTML_Tag_Processor $processor ).
	 * @return WP_HTML_Tag_Processor The processor, after the walk.
	 */
	private static function walk( $content, callable $on_block ) {
		$processor = new WP_HTML_Tag_Processor( (string) $content );

		// The first entry stands for the document itself and is never popped.
		$stack = array(
			array(
				'tag'    => '#root',
				'key'    => '',
				'label'  => '',
				'parts'  => 0,
				'coords' => null,
			),
		);

		$tables   = array();
		$counters = array(
			'table' => 0,
			'p'     => 0,
			'h'     => 0,
			'li'    => 0,
			'cap'   => 0,
			'img'   => 0,
		);

		while ( $processor->next_token() ) {
			$token_type = $processor->get_token_type();

			if ( '#tag' === $token_type ) {
				self::handle_tag( $processor, $on_block, $stack, $tables, $counters );
				continue;
			}

			if ( '#text' !== $token_type || self::in_opaque( $stack ) ) {
				continue;
			}

			$text = self::to_plain_text( $processor->get_modifiable_text() );
			if ( ! self::is_translatable( $text ) ) {
				continue;
			}

			$container = self::container_index( $stack );
			if ( '' === $stack[ $container ]['key'] ) {
				list( $key, $label )          = self::make_key( $stack[ $container ], $counters );
				$stack[ $container ]['key']   = $key;
				$stack[ $container ]['label'] = $label;
			}

			++$stack[ $container ]['parts'];
			$part  = $stack[ $container ]['parts'];
			$key   = $stack[ $container ]['key'];
			$label = $stack[ $container ]['label'];

			if ( $part > 1 ) {
				$key  .= '-' . $part;
				/* translators: %d: number of the text piece inside one paragraph or cell */
				$label .= ' ' . sprintf( __( '(part %d)', 'arkray-translation-importer' ), $part );
			}

			call_user_func(
				$on_block,
				array(
					'key'   => $key,
					'kind'  => 'text',
					'label' => $label,
					'text'  => $text,
				),
				$processor
			);
		}

		return $processor;
	}

	/**
	 * Track the element stack and report images.
	 *
	 * @param WP_HTML_Tag_Processor $processor Processor positioned on a tag.
	 * @param callable              $on_block  Block callback.
	 * @param array                 $stack     Open elements, by reference.
	 * @param array                 $tables    Open tables, by reference.
	 * @param array                 $counters  Per-kind counters, by reference.
	 * @return void
	 */
	private static function handle_tag( WP_HTML_Tag_Processor $processor, callable $on_block, array &$stack, array &$tables, array &$counters ) {
		$tag = (string) $processor->get_tag();

		if ( $processor->is_tag_closer() ) {
			self::pop_to( $tag, $stack, $tables );
			return;
		}

		if ( 'IMG' === $tag && ! self::in_opaque( $stack ) ) {
			++$counters['img'];
			call_user_func(
				$on_block,
				array(
					'key'     => 'img' . self::pad( $counters['img'] ),
					'kind'    => 'image',
					/* translators: %d: image number on the page */
					'label'   => sprintf( __( 'Image %d', 'arkray-translation-importer' ), $counters['img'] ),
					'text'    => '',
					'src'     => '',
					'file'    => '',
					'caption' => '',
				),
				$processor
			);
		}

		self::close_implicitly( $tag, $stack, $tables );

		if ( 'TABLE' === $tag ) {
			++$counters['table'];
			$tables[] = array(
				'index' => $counters['table'],
				'row'   => 0,
				'cell'  => 0,
			);
		} elseif ( ! empty( $tables ) ) {
			$current = count( $tables ) - 1;
			if ( 'TR' === $tag ) {
				++$tables[ $current ]['row'];
				$tables[ $current ]['cell'] = 0;
			} elseif ( 'TD' === $tag || 'TH' === $tag ) {
				++$tables[ $current ]['cell'];
				if ( 0 === $tables[ $current ]['row'] ) {
					$tables[ $current ]['row'] = 1;
				}
			}
		}

		if ( in_array( $tag, self::VOID_ELEMENTS, true ) || $processor->has_self_closing_flag() ) {
			return;
		}

		$stack[] = array(
			'tag'    => $tag,
			'key'    => '',
			'label'  => '',
			'parts'  => 0,
			'coords' => empty( $tables ) ? null : $tables[ count( $tables ) - 1 ],
		);
	}

	/**
	 * Index of the element a piece of text belongs to: the innermost open
	 * element that is not an inline one.
	 *
	 * @param array[] $stack Open elements.
	 * @return int
	 */
	private static function container_index( array $stack ) {
		for ( $index = count( $stack ) - 1; $index > 0; $index-- ) {
			if ( ! in_array( $stack[ $index ]['tag'], self::INLINE_ELEMENTS, true ) ) {
				return $index;
			}
		}
		return 0;
	}

	/**
	 * Give a container its key and label the first time it is found to hold text.
	 *
	 * @param array $entry    Stack entry of the container.
	 * @param array $counters Per-kind counters, by reference.
	 * @return array array( string $key, string $label )
	 */
	private static function make_key( array $entry, array &$counters ) {
		$tag    = $entry['tag'];
		$coords = $entry['coords'];

		if ( ( 'TD' === $tag || 'TH' === $tag ) && is_array( $coords ) ) {
			$row  = max( 1, (int) $coords['row'] );
			$cell = max( 1, (int) $coords['cell'] );
			return array(
				sprintf( 'tbl%d-r%d-c%d', $coords['index'], $row, $cell ),
				/* translators: 1: table number, 2: row number, 3: column number */
				sprintf( __( 'Table %1$d, row %2$d, column %3$d', 'arkray-translation-importer' ), $coords['index'], $row, $cell ),
			);
		}

		if ( 'CAPTION' === $tag && is_array( $coords ) ) {
			return array(
				sprintf( 'tbl%d-caption', $coords['index'] ),
				/* translators: %d: table number */
				sprintf( __( 'Table %d caption', 'arkray-translation-importer' ), $coords['index'] ),
			);
		}

		if ( 'FIGCAPTION' === $tag ) {
			++$counters['cap'];
			return array(
				'cap' . self::pad( $counters['cap'] ),
				/* translators: %d: caption number on the page */
				sprintf( __( 'Figure caption %d', 'arkray-translation-importer' ), $counters['cap'] ),
			);
		}

		if ( preg_match( '#^H[1-6]$#', $tag ) ) {
			++$counters['h'];
			return array(
				'h' . self::pad( $counters['h'] ),
				/* translators: 1: heading number on the page, 2: HTML tag such as H2 */
				sprintf( __( 'Heading %1$d (%2$s)', 'arkray-translation-importer' ), $counters['h'], $tag ),
			);
		}

		if ( 'LI' === $tag ) {
			++$counters['li'];
			return array(
				'li' . self::pad( $counters['li'] ),
				/* translators: %d: list item number on the page */
				sprintf( __( 'List item %d', 'arkray-translation-importer' ), $counters['li'] ),
			);
		}

		++$counters['p'];
		return array(
			'p' . self::pad( $counters['p'] ),
			/* translators: %d: paragraph number on the page */
			sprintf( __( 'Paragraph %d', 'arkray-translation-importer' ), $counters['p'] ),
		);
	}

	/**
	 * Close the elements a newly opened tag implies the end of.
	 *
	 * @param string  $tag    Tag being opened.
	 * @param array[] $stack  Open elements, by reference.
	 * @param array[] $tables Open tables, by reference.
	 * @return void
	 */
	private static function close_implicitly( $tag, array &$stack, array &$tables ) {
		if ( ! isset( self::IMPLICIT_CLOSERS[ $tag ] ) ) {
			return;
		}

		$closes = self::IMPLICIT_CLOSERS[ $tag ];

		while ( count( $stack ) > 1 && in_array( $stack[ count( $stack ) - 1 ]['tag'], $closes, true ) ) {
			self::pop_one( $stack, $tables );
		}
	}

	/**
	 * Close an element and everything still open inside it.
	 *
	 * A closing tag for an element that was never opened is ignored.
	 *
	 * @param string  $tag    Tag being closed.
	 * @param array[] $stack  Open elements, by reference.
	 * @param array[] $tables Open tables, by reference.
	 * @return void
	 */
	private static function pop_to( $tag, array &$stack, array &$tables ) {
		$found = 0;
		for ( $index = count( $stack ) - 1; $index > 0; $index-- ) {
			if ( $stack[ $index ]['tag'] === $tag ) {
				$found = $index;
				break;
			}
		}

		if ( 0 === $found ) {
			return;
		}

		while ( count( $stack ) > $found ) {
			self::pop_one( $stack, $tables );
		}
	}

	/**
	 * Remove the innermost open element.
	 *
	 * @param array[] $stack  Open elements, by reference.
	 * @param array[] $tables Open tables, by reference.
	 * @return void
	 */
	private static function pop_one( array &$stack, array &$tables ) {
		$entry = array_pop( $stack );
		if ( is_array( $entry ) && 'TABLE' === $entry['tag'] ) {
			array_pop( $tables );
		}
	}

	/**
	 * Whether the walk is inside an element whose text is not prose.
	 *
	 * @param array[] $stack Open elements.
	 * @return bool
	 */
	private static function in_opaque( array $stack ) {
		foreach ( $stack as $entry ) {
			if ( in_array( $entry['tag'], self::OPAQUE_ELEMENTS, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Change the source, alt text and size of an image.
	 *
	 * @param WP_HTML_Tag_Processor $processor Processor positioned on an IMG tag.
	 * @param array                 $image     src, alt, width, height, attachment_id.
	 * @return void
	 */
	private static function apply_image( WP_HTML_Tag_Processor $processor, array $image ) {
		if ( ! empty( $image['src'] ) ) {
			$processor->set_attribute( 'src', (string) $image['src'] );

			// The old responsive sources and size would still point at the old file.
			$processor->remove_attribute( 'srcset' );
			$processor->remove_attribute( 'sizes' );

			if ( ! empty( $image['width'] ) ) {
				$processor->set_attribute( 'width', (string) (int) $image['width'] );
			}
			if ( ! empty( $image['height'] ) ) {
				$processor->set_attribute( 'height', (string) (int) $image['height'] );
			}

			if ( ! empty( $image['attachment_id'] ) ) {
				$classes = $processor->get_attribute( 'class' );
				if ( is_string( $classes ) ) {
					foreach ( preg_split( '#\s+#', $classes, -1, PREG_SPLIT_NO_EMPTY ) as $class ) {
						if ( preg_match( '#^wp-image-[0-9]+$#', $class ) ) {
							$processor->remove_class( $class );
						}
					}
				}
				$processor->add_class( 'wp-image-' . (int) $image['attachment_id'] );
			}
		}

		if ( isset( $image['alt'] ) && '' !== $image['alt'] ) {
			$processor->set_attribute( 'alt', (string) $image['alt'] );
		}
	}

	/**
	 * Whether a piece of text is prose worth translating.
	 *
	 * Whitespace, shortcodes, template placeholders and text without any letter
	 * or digit (punctuation, separators) are left alone.
	 *
	 * @param string $text Plain text.
	 * @return bool
	 */
	private static function is_translatable( $text ) {
		if ( '' === $text ) {
			return false;
		}

		if ( preg_match( '#^(?:\s*(?:\[[^\]]*\]|\{\{[^}]*\}\})\s*)+$#u', $text ) ) {
			return false;
		}

		return (bool) preg_match( '#[\p{L}\p{N}]#u', $text );
	}

	/**
	 * Tidy a piece of text for display in the CSV.
	 *
	 * @param string $text Decoded text.
	 * @return string
	 */
	private static function to_plain_text( $text ) {
		$plain = str_replace( "\xc2\xa0", ' ', (string) $text );
		$plain = preg_replace( '#[ \t]+#', ' ', $plain );

		return trim( (string) $plain );
	}

	/**
	 * Keep the whitespace that surrounds a piece of text, so neighbouring inline
	 * elements do not end up glued to the translated words.
	 *
	 * @param string $original    Decoded text of the text node.
	 * @param string $replacement Plain text from the CSV.
	 * @return string
	 */
	private static function pad_like( $original, $replacement ) {
		$leading  = preg_match( '#^\s+#', $original, $matches ) ? $matches[0] : '';
		$trailing = preg_match( '#\s+$#', $original, $matches ) ? $matches[0] : '';

		$replacement = str_replace( array( "\r\n", "\r" ), "\n", $replacement );

		return $leading . trim( $replacement ) . $trailing;
	}

	/**
	 * The file name part of an image source.
	 *
	 * @param string $src Value of a src attribute.
	 * @return string
	 */
	private static function file_name( $src ) {
		$src = trim( (string) $src );
		if ( '' === $src || 0 === stripos( $src, 'data:' ) ) {
			return '';
		}

		$path = (string) wp_parse_url( $src, PHP_URL_PATH );
		if ( '' === $path ) {
			return '';
		}

		return rawurldecode( basename( $path ) );
	}

	/**
	 * Two-digit block numbering, as in `p01`.
	 *
	 * @param int $number Counter value.
	 * @return string
	 */
	private static function pad( $number ) {
		return str_pad( (string) (int) $number, 2, '0', STR_PAD_LEFT );
	}
}
