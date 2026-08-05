<?php
/**
 * CSV reading/writing with encoding normalization.
 *
 * @package ArkrayTranslationImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads uploaded CSV files (UTF-8 / UTF-16, comma / semicolon / tab delimited).
 *
 * The export is an Excel workbook, see Arkray_TI_Xlsx, but a CSV is still
 * accepted on upload.
 */
class Arkray_TI_Csv {

	/**
	 * Parse a CSV file into headers plus associative rows.
	 *
	 * @param string $file_path Absolute path to the CSV file.
	 * @return array|WP_Error { 'headers' => string[], 'rows' => array[] } keyed by header.
	 */
	public static function read( $file_path ) {
		$raw = file_get_contents( $file_path );
		if ( false === $raw || '' === trim( $raw ) ) {
			return new WP_Error( 'arkray_ti_empty', __( 'The uploaded file is empty or could not be read.', 'arkray-translation-importer' ) );
		}

		$raw = self::normalize_encoding( $raw );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$delimiter = self::detect_delimiter( $raw );

		$handle = fopen( 'php://temp', 'r+' );
		fwrite( $handle, $raw );
		rewind( $handle );

		$header_row = fgetcsv( $handle, 0, $delimiter, '"', '\\' );
		if ( ! is_array( $header_row ) ) {
			fclose( $handle );
			return new WP_Error( 'arkray_ti_no_header', __( 'Could not read a header row from the CSV file.', 'arkray-translation-importer' ) );
		}

		$data_rows = array();
		while ( ( $data = fgetcsv( $handle, 0, $delimiter, '"', '\\' ) ) !== false ) {
			if ( is_array( $data ) ) {
				$data_rows[] = $data;
			}
		}

		fclose( $handle );

		return self::structure( $header_row, $data_rows );
	}

	/**
	 * Turn a header row plus data rows into the shape the importer expects.
	 *
	 * Headers are lower-cased and trimmed, empty trailing columns are dropped and
	 * repeated headers are numbered so no column can quietly swallow another.
	 * Rows shorter than the header are padded, longer rows are truncated, and
	 * fully empty rows are skipped.
	 *
	 * Shared by the CSV reader and by Arkray_TI_Xlsx.
	 *
	 * @param array   $header_row First row of the file.
	 * @param array[] $data_rows  The rows below it.
	 * @return array|WP_Error { 'headers' => string[], 'rows' => array[] } keyed by header.
	 */
	public static function structure( array $header_row, array $data_rows ) {
		$headers = array_map(
			static function ( $header ) {
				return strtolower( trim( (string) $header ) );
			},
			array_values( $header_row )
		);

		while ( ! empty( $headers ) && '' === end( $headers ) ) {
			array_pop( $headers );
		}

		if ( empty( $headers ) ) {
			return new WP_Error( 'arkray_ti_no_header', __( 'The first row of the file holds no column names.', 'arkray-translation-importer' ) );
		}

		$seen = array();
		foreach ( $headers as $index => $header ) {
			if ( '' === $header ) {
				$header = 'column_' . ( $index + 1 );
			}
			if ( isset( $seen[ $header ] ) ) {
				++$seen[ $header ];
				$header .= '_' . $seen[ $header ];
			} else {
				$seen[ $header ] = 1;
			}
			$headers[ $index ] = $header;
		}

		$count = count( $headers );
		$rows  = array();

		foreach ( $data_rows as $data ) {
			$data = array_map(
				static function ( $value ) {
					return null === $value ? '' : trim( (string) $value );
				},
				array_values( (array) $data )
			);

			if ( 0 === count( array_filter( $data, 'strlen' ) ) ) {
				continue;
			}

			$rows[] = array_combine( $headers, array_pad( array_slice( $data, 0, $count ), $count, '' ) );
		}

		if ( empty( $rows ) ) {
			return new WP_Error( 'arkray_ti_no_rows', __( 'The file contains a header row but no data rows.', 'arkray-translation-importer' ) );
		}

		return array(
			'headers' => $headers,
			'rows'    => $rows,
		);
	}

	/**
	 * Convert the raw file contents to BOM-free UTF-8.
	 *
	 * @param string $raw Raw file contents.
	 * @return string|WP_Error
	 */
	private static function normalize_encoding( $raw ) {
		if ( "\xEF\xBB\xBF" === substr( $raw, 0, 3 ) ) {
			$raw = substr( $raw, 3 );
		} elseif ( "\xFF\xFE" === substr( $raw, 0, 2 ) ) {
			$raw = mb_convert_encoding( substr( $raw, 2 ), 'UTF-8', 'UTF-16LE' );
		} elseif ( "\xFE\xFF" === substr( $raw, 0, 2 ) ) {
			$raw = mb_convert_encoding( substr( $raw, 2 ), 'UTF-8', 'UTF-16BE' );
		}

		if ( ! mb_check_encoding( $raw, 'UTF-8' ) ) {
			return new WP_Error(
				'arkray_ti_encoding',
				__( 'The file is not valid UTF-8. Please save the CSV as "CSV UTF-8" (in Excel: File → Save As → CSV UTF-8) and upload it again. This is required for Vietnamese characters.', 'arkray-translation-importer' )
			);
		}

		return $raw;
	}

	/**
	 * Guess the delimiter from the header line.
	 *
	 * @param string $raw UTF-8 file contents.
	 * @return string One of ',', ';', "\t".
	 */
	private static function detect_delimiter( $raw ) {
		$first_line = strtok( $raw, "\r\n" );
		$candidates = array( ',', ';', "\t" );
		$best       = ',';
		$best_count = -1;

		foreach ( $candidates as $candidate ) {
			$count = substr_count( (string) $first_line, $candidate );
			if ( $count > $best_count ) {
				$best_count = $count;
				$best       = $candidate;
			}
		}

		return $best;
	}

}
