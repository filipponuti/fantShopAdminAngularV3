<?php

defined( 'ABSPATH' ) || exit;

/**
 * Unisce più PDF in uno solo, nell'ordine ricevuto.
 */
final class Fant_Admin_API_V4_Pdf_Merge {
	/**
	 * @param array<int, string> $binaries
	 * @return string|WP_Error
	 */
	public static function concat( array $binaries ) {
		$parts = array();
		foreach ( $binaries as $binary ) {
			if ( is_string( $binary ) && '' !== $binary ) {
				$parts[] = $binary;
			}
		}
		if ( ! $parts ) {
			return new WP_Error( 'catalog_pdf_failed', 'Nessun PDF da unire.', array( 'status' => 500 ) );
		}
		if ( 1 === count( $parts ) ) {
			return $parts[0];
		}

		$fpdf     = FANT_ADMIN_API_V4_PATH . 'lib/fpdf/fpdf.php';
		$autoload = FANT_ADMIN_API_V4_PATH . 'lib/fpdi/src/autoload.php';
		if ( ! is_file( $fpdf ) || ! is_file( $autoload ) ) {
			return new WP_Error( 'catalog_pdf_failed', 'Il motore per unire i PDF non è installato nel plugin.', array( 'status' => 500 ) );
		}
		if ( ! class_exists( 'FPDF', false ) ) {
			require_once $fpdf;
		}
		require_once $autoload;

		try {
			$pdf = new setasign\Fpdi\Fpdi();
			$pdf->SetAutoPageBreak( false );
			foreach ( $parts as $binary ) {
				$count = $pdf->setSourceFile( setasign\Fpdi\PdfParser\StreamReader::createByString( $binary ) );
				for ( $page = 1; $page <= $count; $page++ ) {
					$template = $pdf->importPage( $page );
					$size     = $pdf->getTemplateSize( $template );
					if ( ! is_array( $size ) ) {
						return new WP_Error( 'catalog_pdf_failed', 'Impossibile leggere una pagina del PDF da unire.', array( 'status' => 500 ) );
					}
					$pdf->AddPage( $size['orientation'], array( $size['width'], $size['height'] ) );
					$pdf->useTemplate( $template );
				}
			}
			$merged = $pdf->Output( 'S' );
			if ( ! is_string( $merged ) || '' === $merged ) {
				return new WP_Error( 'catalog_pdf_failed', 'Impossibile unire copertina o retro al PDF.', array( 'status' => 500 ) );
			}
			return $merged;
		} catch ( Throwable $exception ) {
			return new WP_Error( 'catalog_pdf_failed', 'Impossibile unire copertina o retro al PDF.', array( 'status' => 500 ) );
		}
	}
}
