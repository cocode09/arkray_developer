<?php
/**
 * Writes and reads the Excel workbook used for translations.
 *
 * @package ArkrayTranslationImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal .xlsx support, without any external library.
 *
 * The export is a workbook instead of a CSV because a CSV has no cell types:
 * Excel guesses one per column, so a cell like "- To manage the relationship"
 * or "+84-24-73033886" is read as a formula and shows #NAME?, and saving the
 * file then writes that error back over the text. Here every cell is written as
 * a string with the "Text" number format, so what a cell holds is what it shows.
 *
 * Only the parts of the format this plugin needs are implemented: inline
 * strings, a handful of background fills for the colour coding, column widths
 * and a frozen header row.
 */
class Arkray_TI_Xlsx {

	/**
	 * Row styles, by name, as indexes into the cellXfs list of styles.xml.
	 */
	const STYLES = array(
		'default'   => 0,
		'header'    => 1,
		'separator' => 2,
		'image'     => 3,
		'table'     => 4,
		'highlight' => 5,
		'external'  => 6,
	);

	const CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

	/**
	 * Send a workbook as a download and stop.
	 *
	 * @param string  $filename Download file name.
	 * @param array[] $sheets   Sheet definitions, see build().
	 * @return void
	 */
	public static function stream( $filename, array $sheets ) {
		$workbook = self::build( $sheets );

		nocache_headers();
		header( 'Content-Type: ' . self::CONTENT_TYPE );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . strlen( $workbook ) );

		echo $workbook; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary file.
		exit;
	}

	/**
	 * Build a workbook.
	 *
	 * @param array[] $sheets {
	 *     One entry per sheet.
	 *
	 *     @type string  $name   Sheet name.
	 *     @type array[] $rows   Rows. A row is either a list of cell values or
	 *                           array( 'cells' => list, 'style' => style name ).
	 *     @type int[]   $widths Column widths in characters.
	 *     @type bool    $freeze Whether to freeze the first row.
	 * }
	 * @return string Binary .xlsx file.
	 */
	public static function build( array $sheets ) {
		$sheets = array_values( $sheets );
		$parts  = array(
			'[Content_Types].xml'      => self::content_types_xml( count( $sheets ) ),
			'_rels/.rels'              => self::root_rels_xml(),
			'xl/workbook.xml'          => self::workbook_xml( $sheets ),
			'xl/_rels/workbook.xml.rels' => self::workbook_rels_xml( count( $sheets ) ),
			'xl/styles.xml'            => self::styles_xml(),
		);

		foreach ( $sheets as $index => $sheet ) {
			$parts[ 'xl/worksheets/sheet' . ( $index + 1 ) . '.xml' ] = self::sheet_xml( $sheet );
		}

		return self::zip( $parts );
	}

	/**
	 * Read the first sheet of a workbook into headers plus associative rows.
	 *
	 * @param string $file_path Absolute path to the .xlsx file.
	 * @return array|WP_Error { 'headers' => string[], 'rows' => array[] }
	 */
	public static function read( $file_path ) {
		$files = self::unzip( $file_path );
		if ( is_wp_error( $files ) ) {
			return $files;
		}

		$sheet_path = self::first_sheet_path( $files );
		if ( ! isset( $files[ $sheet_path ] ) ) {
			return new WP_Error( 'arkray_ti_xlsx_no_sheet', __( 'The Excel file has no readable worksheet.', 'arkray-translation-importer' ) );
		}

		$sheet = self::parse_xml( $files[ $sheet_path ] );
		if ( is_wp_error( $sheet ) ) {
			return $sheet;
		}

		$shared = array();
		if ( isset( $files['xl/sharedStrings.xml'] ) ) {
			$strings = self::parse_xml( $files['xl/sharedStrings.xml'] );
			if ( ! is_wp_error( $strings ) ) {
				foreach ( $strings->si as $item ) {
					$shared[] = self::text_of( $item );
				}
			}
		}

		$rows = array();
		if ( isset( $sheet->sheetData ) ) {
			foreach ( $sheet->sheetData->row as $row ) {
				$cells = array();
				foreach ( $row->c as $cell ) {
					$index = self::column_index( (string) $cell['r'] );
					if ( $index >= 0 ) {
						$cells[ $index ] = self::cell_value( $cell, $shared );
					}
				}
				if ( empty( $cells ) ) {
					$rows[] = array();
					continue;
				}
				ksort( $cells );
				$rows[] = array_replace( array_fill( 0, max( array_keys( $cells ) ) + 1, '' ), $cells );
			}
		}

		if ( empty( $rows ) ) {
			return new WP_Error( 'arkray_ti_xlsx_empty', __( 'The first worksheet of the Excel file is empty.', 'arkray-translation-importer' ) );
		}

		return Arkray_TI_Csv::structure( array_shift( $rows ), $rows );
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Writing
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * The [Content_Types].xml part.
	 *
	 * @param int $sheet_count Number of sheets.
	 * @return string
	 */
	private static function content_types_xml( $sheet_count ) {
		$overrides = '';
		for ( $index = 1; $index <= $sheet_count; $index++ ) {
			$overrides .= '<Override PartName="/xl/worksheets/sheet' . $index . '.xml"'
				. ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
		}

		return self::declaration()
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. $overrides
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. '</Types>';
	}

	/**
	 * The package relationships part.
	 *
	 * @return string
	 */
	private static function root_rels_xml() {
		return self::declaration()
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>';
	}

	/**
	 * The workbook part.
	 *
	 * @param array[] $sheets Sheet definitions.
	 * @return string
	 */
	private static function workbook_xml( array $sheets ) {
		$entries = '';
		foreach ( $sheets as $index => $sheet ) {
			$name     = isset( $sheet['name'] ) ? $sheet['name'] : sprintf( 'Sheet%d', $index + 1 );
			$entries .= sprintf(
				'<sheet name="%s" sheetId="%d" r:id="rId%d"/>',
				self::escape( self::sheet_name( $name ) ),
				$index + 1,
				$index + 1
			);
		}

		return self::declaration()
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
			. ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets>' . $entries . '</sheets>'
			. '</workbook>';
	}

	/**
	 * The workbook relationships part.
	 *
	 * @param int $sheet_count Number of sheets.
	 * @return string
	 */
	private static function workbook_rels_xml( $sheet_count ) {
		$entries = '';
		for ( $index = 1; $index <= $sheet_count; $index++ ) {
			$entries .= '<Relationship Id="rId' . $index . '"'
				. ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
				. ' Target="worksheets/sheet' . $index . '.xml"/>';
		}

		return self::declaration()
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. $entries
			. '<Relationship Id="rId' . ( $sheet_count + 1 ) . '"'
			. ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"'
			. ' Target="styles.xml"/>'
			. '</Relationships>';
	}

	/**
	 * The styles part: one cell format per row style, all of them "Text".
	 *
	 * Number format 49 is the built-in "@", which keeps Excel from turning a
	 * value into a number, a date or a formula.
	 *
	 * @return string
	 */
	private static function styles_xml() {
		$fills = array(
			'FF1F3864', // Header, dark navy.
			'FFD9D9D9', // Section separator, grey.
			'FFDDEBF7', // Image and caption rows, light blue.
			'FFFFF2CC', // Table cell rows, light yellow.
			'FFFCE4D6', // News and events rows, light orange.
			'FFE2EFDA', // Pages whose content comes from outside, light green.
		);

		$fill_xml = '<fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>';
		foreach ( $fills as $colour ) {
			$fill_xml .= '<fill><patternFill patternType="solid"><fgColor rgb="' . $colour . '"/><bgColor indexed="64"/></patternFill></fill>';
		}

		// Style order must match self::STYLES.
		$formats = array(
			array( 0, 0 ), // default
			array( 1, 2 ), // header
			array( 2, 3 ), // separator
			array( 0, 4 ), // image
			array( 0, 5 ), // table
			array( 0, 6 ), // highlight
			array( 0, 7 ), // external
		);

		$format_xml = '';
		foreach ( $formats as $format ) {
			$format_xml .= sprintf(
				'<xf numFmtId="49" fontId="%d" fillId="%d" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyAlignment="1">'
					. '<alignment vertical="top" wrapText="1"/></xf>',
				$format[0],
				$format[1]
			);
		}

		return self::declaration()
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="3">'
			. '<font><sz val="11"/><name val="Calibri"/></font>'
			. '<font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font>'
			. '<font><b/><sz val="11"/><name val="Calibri"/></font>'
			. '</fonts>'
			. '<fills count="' . ( count( $fills ) + 2 ) . '">' . $fill_xml . '</fills>'
			. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="' . count( $formats ) . '">' . $format_xml . '</cellXfs>'
			. '</styleSheet>';
	}

	/**
	 * One worksheet part.
	 *
	 * Empty cells are written too, so a row's colour runs across the sheet and
	 * so anything typed into them stays text.
	 *
	 * @param array $sheet Sheet definition.
	 * @return string
	 */
	private static function sheet_xml( array $sheet ) {
		$rows    = isset( $sheet['rows'] ) ? (array) $sheet['rows'] : array();
		$widths  = isset( $sheet['widths'] ) ? (array) $sheet['widths'] : array();
		$columns = count( $widths );

		foreach ( $rows as $row ) {
			$cells   = isset( $row['cells'] ) ? (array) $row['cells'] : (array) $row;
			$columns = max( $columns, count( $cells ) );
		}

		$cols_xml = '';
		if ( $columns > 0 ) {
			for ( $index = 0; $index < $columns; $index++ ) {
				$width     = isset( $widths[ $index ] ) ? (float) $widths[ $index ] : 18;
				$cols_xml .= sprintf(
					'<col min="%1$d" max="%1$d" width="%2$s" style="0" customWidth="1"/>',
					$index + 1,
					number_format( $width, 2, '.', '' )
				);
			}
			$cols_xml = '<cols>' . $cols_xml . '</cols>';
		}

		$rows_xml = '';
		foreach ( $rows as $number => $row ) {
			$cells = isset( $row['cells'] ) ? (array) $row['cells'] : (array) $row;
			$style = isset( $row['style'] ) && isset( self::STYLES[ $row['style'] ] )
				? self::STYLES[ $row['style'] ]
				: self::STYLES['default'];

			$cells_xml = '';
			for ( $index = 0; $index < $columns; $index++ ) {
				$reference = self::column_letter( $index ) . ( $number + 1 );
				$value     = isset( $cells[ $index ] ) ? self::plain_text( $cells[ $index ] ) : '';

				if ( '' === $value ) {
					$cells_xml .= sprintf( '<c r="%s" s="%d"/>', $reference, $style );
					continue;
				}

				$cells_xml .= sprintf(
					'<c r="%s" s="%d" t="inlineStr"><is><t xml:space="preserve">%s</t></is></c>',
					$reference,
					$style,
					self::escape( $value )
				);
			}

			$rows_xml .= '<row r="' . ( $number + 1 ) . '">' . $cells_xml . '</row>';
		}

		$view = empty( $sheet['freeze'] )
			? '<sheetView workbookViewId="0"/>'
			: '<sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView>';

		return self::declaration()
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<sheetViews>' . $view . '</sheetViews>'
			. '<sheetFormatPr defaultRowHeight="15"/>'
			. $cols_xml
			. '<sheetData>' . $rows_xml . '</sheetData>'
			. '</worksheet>';
	}

	/**
	 * Pack the parts into a zip container.
	 *
	 * Written by hand because PclZip, the zip library WordPress ships, can only
	 * add files that are already on disk.
	 *
	 * @param array $parts Contents keyed by path inside the archive.
	 * @return string
	 */
	private static function zip( array $parts ) {
		$stamp   = self::dos_datetime( time() );
		$local   = '';
		$central = '';
		$count   = 0;

		foreach ( $parts as $name => $content ) {
			$content    = (string) $content;
			$size       = strlen( $content );
			$deflated   = gzdeflate( $content, 6 );
			$method     = 8;
			if ( false === $deflated || strlen( $deflated ) >= $size ) {
				$deflated = $content;
				$method   = 0;
			}
			$compressed = strlen( $deflated );

			$header = pack( 'vvvvv', 20, 0, $method, $stamp['time'], $stamp['date'] )
				. pack( 'VVV', crc32( $content ), $compressed, $size )
				. pack( 'vv', strlen( $name ), 0 );

			$central .= "PK\x01\x02" . pack( 'v', 20 ) . $header
				. pack( 'vvv', 0, 0, 0 )
				. pack( 'VV', 32, strlen( $local ) )
				. $name;

			$local .= "PK\x03\x04" . $header . $name . $deflated;
			++$count;
		}

		return $local . $central . "PK\x05\x06"
			. pack( 'vvvv', 0, 0, $count, $count )
			. pack( 'VV', strlen( $central ), strlen( $local ) )
			. pack( 'v', 0 );
	}

	/**
	 * MS-DOS date and time fields for the zip headers.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return array time, date.
	 */
	private static function dos_datetime( $timestamp ) {
		$parts = getdate( (int) $timestamp );
		$year  = max( 1980, (int) $parts['year'] );

		return array(
			'time' => ( $parts['hours'] << 11 ) | ( $parts['minutes'] << 5 ) | (int) ( $parts['seconds'] / 2 ),
			'date' => ( ( $year - 1980 ) << 9 ) | ( $parts['mon'] << 5 ) | $parts['mday'],
		);
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Reading
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * Read every part of a workbook into memory.
	 *
	 * @param string $file_path Absolute path to the .xlsx file.
	 * @return array|WP_Error Contents keyed by path inside the archive.
	 */
	private static function unzip( $file_path ) {
		if ( ! class_exists( 'PclZip' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
		}

		$archive = new PclZip( $file_path );
		$entries = $archive->extract( PCLZIP_OPT_EXTRACT_AS_STRING );

		if ( ! is_array( $entries ) || empty( $entries ) ) {
			return new WP_Error(
				'arkray_ti_xlsx_unreadable',
				__( 'The Excel file could not be opened. Please upload the .xlsx file as it was downloaded, or save it again from Excel.', 'arkray-translation-importer' )
			);
		}

		$files = array();
		foreach ( $entries as $entry ) {
			if ( empty( $entry['folder'] ) && isset( $entry['stored_filename'] ) ) {
				$files[ $entry['stored_filename'] ] = isset( $entry['content'] ) ? $entry['content'] : '';
			}
		}

		return $files;
	}

	/**
	 * Path of the worksheet the workbook lists first.
	 *
	 * @param array $files Archive contents.
	 * @return string
	 */
	private static function first_sheet_path( array $files ) {
		$fallback = 'xl/worksheets/sheet1.xml';

		if ( ! isset( $files['xl/workbook.xml'], $files['xl/_rels/workbook.xml.rels'] ) ) {
			return $fallback;
		}

		$workbook = self::parse_xml( $files['xl/workbook.xml'] );
		$rels     = self::parse_xml( $files['xl/_rels/workbook.xml.rels'] );
		if ( is_wp_error( $workbook ) || is_wp_error( $rels ) || ! isset( $workbook->sheets->sheet[0] ) ) {
			return $fallback;
		}

		$relationship = $workbook->sheets->sheet[0]->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' );
		$id           = isset( $relationship['id'] ) ? (string) $relationship['id'] : '';

		foreach ( $rels->Relationship as $rel ) {
			if ( (string) $rel['Id'] !== $id ) {
				continue;
			}
			$target = ltrim( (string) $rel['Target'], '/' );
			if ( 0 !== strpos( $target, 'xl/' ) ) {
				$target = 'xl/' . $target;
			}
			return $target;
		}

		return $fallback;
	}

	/**
	 * Parse one XML part.
	 *
	 * @param string $xml XML source.
	 * @return SimpleXMLElement|WP_Error
	 */
	private static function parse_xml( $xml ) {
		$previous = libxml_use_internal_errors( true );
		$parsed   = simplexml_load_string( (string) $xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $parsed instanceof SimpleXMLElement ) {
			return new WP_Error( 'arkray_ti_xlsx_broken', __( 'The Excel file is damaged and could not be read.', 'arkray-translation-importer' ) );
		}

		return $parsed;
	}

	/**
	 * The text a cell holds, whatever way it stores it.
	 *
	 * @param SimpleXMLElement $cell   A `c` element.
	 * @param string[]         $shared Shared strings of the workbook.
	 * @return string
	 */
	private static function cell_value( SimpleXMLElement $cell, array $shared ) {
		$type = (string) $cell['t'];

		if ( 'inlineStr' === $type ) {
			return isset( $cell->is ) ? self::text_of( $cell->is ) : '';
		}

		if ( 's' === $type ) {
			$index = isset( $cell->v ) ? (int) $cell->v : -1;
			return isset( $shared[ $index ] ) ? $shared[ $index ] : '';
		}

		// A cell holding a formula error such as #NAME? carries no usable text.
		if ( 'e' === $type ) {
			return '';
		}

		if ( 'b' === $type ) {
			return ( isset( $cell->v ) && '1' === (string) $cell->v ) ? '1' : '0';
		}

		return isset( $cell->v ) ? (string) $cell->v : '';
	}

	/**
	 * Join the text of an element that may be split into formatting runs.
	 *
	 * @param SimpleXMLElement $element An `is` or `si` element.
	 * @return string
	 */
	private static function text_of( SimpleXMLElement $element ) {
		$text = isset( $element->t ) ? (string) $element->t : '';

		foreach ( $element->r as $run ) {
			if ( isset( $run->t ) ) {
				$text .= (string) $run->t;
			}
		}

		return $text;
	}

	// ─────────────────────────────────────────────────────────────────────────
	// Helpers
	// ─────────────────────────────────────────────────────────────────────────

	/**
	 * The XML prolog every part starts with.
	 *
	 * @return string
	 */
	private static function declaration() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
	}

	/**
	 * Normalise a value for a cell.
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	private static function plain_text( $value ) {
		$value = str_replace( array( "\r\n", "\r" ), "\n", (string) $value );

		return (string) preg_replace( '#[\x00-\x08\x0B\x0C\x0E-\x1F]#', '', $value );
	}

	/**
	 * Escape text for XML content.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	private static function escape( $value ) {
		return str_replace(
			array( '&', '<', '>', '"' ),
			array( '&amp;', '&lt;', '&gt;', '&quot;' ),
			(string) $value
		);
	}

	/**
	 * Trim a sheet name to what Excel accepts.
	 *
	 * @param string $name Wanted name.
	 * @return string
	 */
	private static function sheet_name( $name ) {
		$name = str_replace( array( '[', ']', ':', '*', '?', '/', '\\' ), ' ', (string) $name );
		$name = trim( $name, " '" );

		if ( function_exists( 'mb_substr' ) ) {
			$name = mb_substr( $name, 0, 31 );
		}

		return '' === $name ? 'Sheet' : $name;
	}

	/**
	 * Column letter for a zero-based column index: 0 is A, 26 is AA.
	 *
	 * @param int $index Zero-based index.
	 * @return string
	 */
	private static function column_letter( $index ) {
		$index  = max( 0, (int) $index );
		$letter = '';

		do {
			$letter = chr( 65 + ( $index % 26 ) ) . $letter;
			$index  = (int) ( $index / 26 ) - 1;
		} while ( $index >= 0 );

		return $letter;
	}

	/**
	 * Zero-based column index of a cell reference such as "AB12".
	 *
	 * @param string $reference Cell reference.
	 * @return int -1 when the reference cannot be read.
	 */
	private static function column_index( $reference ) {
		if ( ! preg_match( '#^([A-Z]+)#i', trim( (string) $reference ), $matches ) ) {
			return -1;
		}

		$letters = strtoupper( $matches[1] );
		$index   = 0;
		$length  = strlen( $letters );

		for ( $position = 0; $position < $length; $position++ ) {
			$index = $index * 26 + ( ord( $letters[ $position ] ) - 64 );
		}

		return $index - 1;
	}
}
