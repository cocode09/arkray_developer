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
 * Reads uploaded CSV files (UTF-8 / UTF-16, comma / semicolon / tab delimited)
 * and streams CSV downloads that open cleanly in Excel.
 */
class Arkray_TI_Csv {

	/**
	 * Parse a CSV file into headers plus associative rows.
	 *
	 * Headers are lower-cased and trimmed. Rows shorter than the header are
	 * padded with empty strings; longer rows are truncated. Fully empty rows
	 * are skipped.
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

		$headers = fgetcsv( $handle, 0, $delimiter, '"', '\\' );
		if ( ! is_array( $headers ) || 0 === count( array_filter( $headers, 'strlen' ) ) ) {
			fclose( $handle );
			return new WP_Error( 'arkray_ti_no_header', __( 'Could not read a header row from the CSV file.', 'arkray-translation-importer' ) );
		}

		$headers = array_map(
			static function ( $header ) {
				return strtolower( trim( (string) $header ) );
			},
			$headers
		);

		$count = count( $headers );
		$rows  = array();

		while ( ( $data = fgetcsv( $handle, 0, $delimiter, '"', '\\' ) ) !== false ) {
			if ( ! is_array( $data ) ) {
				continue;
			}
			$data = array_map(
				static function ( $value ) {
					return null === $value ? '' : trim( (string) $value );
				},
				$data
			);
			if ( 0 === count( array_filter( $data, 'strlen' ) ) ) {
				continue;
			}
			$data = array_pad( array_slice( $data, 0, $count ), $count, '' );
			$rows[] = array_combine( $headers, $data );
		}

		fclose( $handle );

		if ( empty( $rows ) ) {
			return new WP_Error( 'arkray_ti_no_rows', __( 'The CSV file contains a header row but no data rows.', 'arkray-translation-importer' ) );
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

	/**
	 * Stream a CSV download (UTF-8 with BOM so Excel detects the encoding) and exit.
	 *
	 * @param string   $filename Download file name.
	 * @param string[] $headers  Header row.
	 * @param array[]  $rows     Rows of plain arrays matching the header order.
	 * @return void
	 */
	public static function stream( $filename, array $headers, array $rows ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );

		echo "\xEF\xBB\xBF";

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, $headers, ',', '"', '\\' );
		foreach ( $rows as $row ) {
			fputcsv( $out, $row, ',', '"', '\\' );
		}
		fclose( $out );
		exit;
	}
}
