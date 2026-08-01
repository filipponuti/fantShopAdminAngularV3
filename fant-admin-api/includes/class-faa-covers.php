<?php

defined( 'ABSPATH' ) || exit;

final class Fant_Admin_API_V4_Covers {
	private const SCHEMA_VERSION = 1;
	private const MAX_FILE_SIZE = 20971520;

	public static function all() {
		$directory = self::directory( 'copertine' );
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		$covers = array();
		foreach ( glob( trailingslashit( $directory ) . '*.json' ) ?: array() as $file ) {
			$cover = self::read( $file );
			if ( is_wp_error( $cover ) ) {
				return $cover;
			}
			$covers[] = self::summary( $cover );
		}
		usort( $covers, static fn( array $a, array $b ): int => strcasecmp( $a['nome'], $b['nome'] ) );
		return $covers;
	}

	public static function find( string $code ) {
		$path = self::json_path( $code );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		return is_file( $path ) ? self::read( $path ) : self::error( 'cover_not_found', 'Copertina non trovata.', 404 );
	}

	public static function create( string $code, string $name, string $pdf_name = '' ) {
		$code = self::valid_code( $code );
		$name = self::valid_name( $name );
		if ( is_wp_error( $code ) || is_wp_error( $name ) ) {
			return is_wp_error( $code ) ? $code : $name;
		}
		$path = self::json_path( $code );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( is_file( $path ) ) {
			return self::error( 'cover_code_exists', 'Esiste già una copertina con questo codice.', 409 );
		}
		$pdf_name = self::valid_pdf_name( '' !== trim( $pdf_name ) ? $pdf_name : $code . '.pdf' );
		if ( is_wp_error( $pdf_name ) ) {
			return $pdf_name;
		}
		if ( self::pdf_name_exists( $pdf_name ) ) {
			return self::error( 'cover_pdf_name_exists', 'Esiste già una copertina con questo nome file PDF.', 409 );
		}
		$now = gmdate( 'c' );
		$data = array(
			'schemaVersion' => self::SCHEMA_VERSION,
			'testata' => array( 'codice' => $code, 'nome' => $name, 'createdAt' => $now, 'updatedAt' => $now ),
			'pdf' => array(),
			'allegati' => array(),
		);
		$pdf = self::generate_pdf( $code, $name, $pdf_name );
		if ( is_wp_error( $pdf ) ) {
			return $pdf;
		}
		$data['pdf'] = $pdf;
		$result = self::write( $path, $data );
		return is_wp_error( $result ) ? $result : self::summary( $data );
	}

	public static function update( string $code, string $name, string $pdf_name = '' ) {
		$data = self::find( $code );
		$name = self::valid_name( $name );
		if ( is_wp_error( $data ) || is_wp_error( $name ) ) {
			return is_wp_error( $data ) ? $data : $name;
		}
		$old_pdf_name = (string) ( $data['pdf']['nome'] ?? $code . '.pdf' );
		$pdf_name = self::valid_pdf_name( '' !== trim( $pdf_name ) ? $pdf_name : $old_pdf_name );
		if ( is_wp_error( $pdf_name ) ) {
			return $pdf_name;
		}
		if ( self::pdf_name_exists( $pdf_name, $code ) ) {
			return self::error( 'cover_pdf_name_exists', 'Esiste già una copertina con questo nome file PDF.', 409 );
		}
		$data['testata']['nome'] = $name;
		$data['testata']['updatedAt'] = gmdate( 'c' );
		$pdf = self::generate_pdf( $code, $name, $pdf_name );
		if ( is_wp_error( $pdf ) ) {
			return $pdf;
		}
		$data['pdf'] = $pdf;
		$path = self::json_path( $code );
		$result = is_wp_error( $path ) ? $path : self::write( $path, $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( 0 !== strcasecmp( $old_pdf_name, $pdf_name ) ) {
			$old_pdf_path = self::pdf_file_path( $old_pdf_name );
			if ( ! is_wp_error( $old_pdf_path ) && is_file( $old_pdf_path ) ) {
				@unlink( $old_pdf_path );
			}
		}
		return self::summary( $data );
	}

	public static function upload_attachments( string $code, array $files ) {
		$data = self::find( $code );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$files = self::normalize_files( $files );
		if ( ! $files ) {
			return self::error( 'cover_files_required', 'Seleziona almeno un file da caricare.', 422 );
		}
		$directory = self::attachment_directory( $code );
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		foreach ( $files as $file ) {
			if ( UPLOAD_ERR_OK !== (int) $file['error'] || (int) $file['size'] > self::MAX_FILE_SIZE ) {
				return self::error( 'cover_file_invalid', 'Uno degli allegati non è valido o supera 20 MB.', 422 );
			}
			$filetype = wp_check_filetype( sanitize_file_name( $file['name'] ), get_allowed_mime_types() );
			if ( empty( $filetype['type'] ) ) {
				return self::error( 'cover_file_type_not_allowed', 'Il tipo di uno degli allegati non è consentito da WordPress.', 422 );
			}
			$name = wp_unique_filename( $directory, sanitize_file_name( $file['name'] ) );
			if ( '' === $name || ! is_uploaded_file( $file['tmp_name'] ) || ! move_uploaded_file( $file['tmp_name'], trailingslashit( $directory ) . $name ) ) {
				return self::error( 'cover_upload_failed', 'Impossibile salvare uno degli allegati.', 500 );
			}
			$data['allegati'][] = array(
				'nome' => $name,
				'tipo' => sanitize_mime_type( (string) $filetype['type'] ),
				'dimensione' => (int) $file['size'],
				'uploadedAt' => gmdate( 'c' ),
			);
		}
		$data['testata']['updatedAt'] = gmdate( 'c' );
		$path = self::json_path( $code );
		$result = is_wp_error( $path ) ? $path : self::write( $path, $data );
		return is_wp_error( $result ) ? $result : self::summary( $data );
	}

	public static function pdf( string $code ) {
		$data = self::find( $code );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$path = self::pdf_file_path( (string) ( $data['pdf']['nome'] ?? $code . '.pdf' ) );
		if ( is_wp_error( $path ) || ! is_file( $path ) ) {
			return is_wp_error( $path ) ? $path : self::error( 'cover_pdf_not_found', 'PDF della copertina non trovato.', 404 );
		}
		return array(
			'filename' => basename( $path ),
			'mimeType' => 'application/pdf',
			'contentBase64' => base64_encode( (string) file_get_contents( $path ) ),
		);
	}

	public static function delete( string $code ) {
		$data = self::find( $code );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$json = self::json_path( $code );
		$pdf = self::pdf_file_path( (string) ( $data['pdf']['nome'] ?? $code . '.pdf' ) );
		if ( ! is_wp_error( $json ) && is_file( $json ) && ! unlink( $json ) ) {
			return self::error( 'cover_delete_failed', 'Impossibile eliminare la copertina.', 500 );
		}
		if ( ! is_wp_error( $pdf ) && is_file( $pdf ) ) {
			@unlink( $pdf );
		}
		$attachments = self::attachment_directory( $code, false );
		if ( ! is_wp_error( $attachments ) && is_dir( $attachments ) ) {
			foreach ( glob( trailingslashit( $attachments ) . '*' ) ?: array() as $file ) {
				is_file( $file ) && @unlink( $file );
			}
			foreach ( array( '.htaccess' ) as $hidden_file ) {
				$hidden_path = trailingslashit( $attachments ) . $hidden_file;
				is_file( $hidden_path ) && @unlink( $hidden_path );
			}
			@rmdir( $attachments );
		}
		return true;
	}

	private static function generate_pdf( string $code, string $name, string $pdf_name ) {
		$path = self::pdf_file_path( $pdf_name );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		$title = self::pdf_text( $name );
		$subtitle = self::pdf_text( 'Codice: ' . $code );
		$stream = "BT /F1 28 Tf 72 760 Td (" . $title . ") Tj 0 -42 Td /F1 12 Tf (" . $subtitle . ") Tj ET";
		$objects = array(
			'<< /Type /Catalog /Pages 2 0 R >>',
			'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
			'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
			'<< /Length ' . strlen( $stream ) . " >>\nstream\n" . $stream . "\nendstream",
			'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
		);
		$pdf = "%PDF-1.4\n";
		$offsets = array( 0 );
		foreach ( $objects as $index => $object ) {
			$offsets[] = strlen( $pdf );
			$pdf .= ( $index + 1 ) . " 0 obj\n" . $object . "\nendobj\n";
		}
		$xref = strlen( $pdf );
		$pdf .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";
		foreach ( array_slice( $offsets, 1 ) as $offset ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offset );
		}
		$pdf .= "trailer << /Size " . ( count( $objects ) + 1 ) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
		if ( false === file_put_contents( $path, $pdf, LOCK_EX ) ) {
			return self::error( 'cover_pdf_failed', 'Impossibile generare il PDF della copertina.', 500 );
		}
		return array( 'nome' => basename( $path ), 'generatedAt' => gmdate( 'c' ) );
	}

	private static function pdf_text( string $value ): string {
		$value = function_exists( 'iconv' ) ? ( iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $value ) ?: $value ) : $value;
		return str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $value );
	}

	private static function directory( string $name ) {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return self::error( 'cover_storage_unavailable', (string) $uploads['error'], 500 );
		}
		$path = trailingslashit( $uploads['basedir'] ) . 'fant-admin-api/' . $name;
		if ( ! is_dir( $path ) && ! wp_mkdir_p( $path ) ) {
			return self::error( 'cover_storage_unavailable', 'Impossibile creare la cartella ' . $name . '.', 500 );
		}
		self::protect_directory( $path );
		return $path;
	}

	private static function protect_directory( string $directory ): void {
		$files = array(
			'index.php' => "<?php\n// Silence is golden.\n",
			'.htaccess' => "Require all denied\nDeny from all\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\"/><add accessType=\"Deny\" users=\"*\"/></authorization></security></system.webServer></configuration>\n",
		);
		foreach ( $files as $name => $content ) {
			$path = trailingslashit( $directory ) . $name;
			if ( ! file_exists( $path ) ) {
				@file_put_contents( $path, $content, LOCK_EX );
			}
		}
	}

	private static function json_path( string $code ) {
		$code = self::valid_code( $code );
		$directory = self::directory( 'copertine' );
		return is_wp_error( $code ) || is_wp_error( $directory ) ? ( is_wp_error( $code ) ? $code : $directory ) : trailingslashit( $directory ) . $code . '.json';
	}

	private static function pdf_file_path( string $name ) {
		$name = self::valid_pdf_name( $name );
		$directory = self::directory( 'copertine_pdf' );
		return is_wp_error( $name ) || is_wp_error( $directory ) ? ( is_wp_error( $name ) ? $name : $directory ) : trailingslashit( $directory ) . $name;
	}

	private static function attachment_directory( string $code, bool $create = true ) {
		$code = self::valid_code( $code );
		$root = self::directory( 'copertine_allegati' );
		if ( is_wp_error( $code ) || is_wp_error( $root ) ) {
			return is_wp_error( $code ) ? $code : $root;
		}
		$path = trailingslashit( $root ) . $code;
		if ( $create && ! is_dir( $path ) && ! wp_mkdir_p( $path ) ) {
			return self::error( 'cover_storage_unavailable', 'Impossibile creare la cartella degli allegati.', 500 );
		}
		if ( $create ) {
			self::protect_directory( $path );
		}
		return $path;
	}

	private static function valid_code( string $code ) {
		$code = strtolower( trim( $code ) );
		return preg_match( '/^[a-z0-9][a-z0-9_-]{0,79}$/', $code ) ? $code : self::error( 'invalid_cover_code', 'Il codice può contenere lettere minuscole, numeri, trattini e underscore.', 422 );
	}

	private static function valid_name( string $name ) {
		$name = trim( sanitize_text_field( $name ) );
		return '' !== $name ? $name : self::error( 'cover_name_required', 'Il nome della copertina è obbligatorio.', 422 );
	}

	private static function valid_pdf_name( string $name ) {
		$name = trim( sanitize_file_name( $name ) );
		if ( '' === $name || str_contains( $name, '..' ) || ! preg_match( '/^[a-z0-9][a-z0-9._-]*\.pdf$/i', $name ) ) {
			return self::error( 'invalid_cover_pdf_name', 'Il nome file PDF deve terminare con .pdf e può contenere lettere, numeri, punti, trattini e underscore.', 422 );
		}
		return $name;
	}

	private static function pdf_name_exists( string $pdf_name, string $exclude_code = '' ): bool {
		$directory = self::directory( 'copertine' );
		if ( is_wp_error( $directory ) ) {
			return false;
		}
		foreach ( glob( trailingslashit( $directory ) . '*.json' ) ?: array() as $file ) {
			$data = self::read( $file );
			if ( is_wp_error( $data ) || (string) ( $data['testata']['codice'] ?? '' ) === $exclude_code ) {
				continue;
			}
			if ( 0 === strcasecmp( (string) ( $data['pdf']['nome'] ?? '' ), $pdf_name ) ) {
				return true;
			}
		}
		return false;
	}

	private static function normalize_files( array $files ): array {
		$result = array();
		foreach ( $files as $file ) {
			if ( is_array( $file['name'] ?? null ) ) {
				foreach ( $file['name'] as $i => $name ) {
					$result[] = array( 'name' => $name, 'type' => $file['type'][ $i ], 'tmp_name' => $file['tmp_name'][ $i ], 'error' => $file['error'][ $i ], 'size' => $file['size'][ $i ] );
				}
			} elseif ( isset( $file['name'] ) ) {
				$result[] = $file;
			}
		}
		return $result;
	}

	private static function read( string $path ) {
		$data = json_decode( (string) file_get_contents( $path ), true );
		return is_array( $data ) && isset( $data['testata'], $data['pdf'], $data['allegati'] ) ? $data : self::error( 'cover_json_invalid', 'Il file della copertina non è valido.', 500 );
	}

	private static function write( string $path, array $data ) {
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return false !== $json && false !== file_put_contents( $path, $json . "\n", LOCK_EX ) ? true : self::error( 'cover_write_failed', 'Impossibile salvare la copertina.', 500 );
	}

	private static function summary( array $data ): array {
		return array(
			'codice' => (string) ( $data['testata']['codice'] ?? '' ),
			'nome' => (string) ( $data['testata']['nome'] ?? '' ),
			'numeroAllegati' => count( $data['allegati'] ?? array() ),
			'pdfNome' => (string) ( $data['pdf']['nome'] ?? '' ),
			'createdAt' => (string) ( $data['testata']['createdAt'] ?? '' ),
			'updatedAt' => (string) ( $data['testata']['updatedAt'] ?? '' ),
		);
	}

	private static function error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
