<?php

defined( 'ABSPATH' ) || exit;

final class Fant_Admin_API_Catalogs {
	private const SCHEMA_VERSION = 1;
	private const INDEX_FILE = 'cataloghi-index.json';

	public static function all() {
		$directory = self::directory();
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}

		$catalogs = array();
		$files    = glob( trailingslashit( $directory ) . '*.json' );
		foreach ( is_array( $files ) ? $files : array() as $file ) {
			if ( self::INDEX_FILE === basename( $file ) ) {
				continue;
			}

			$catalog = self::read( $file );
			if ( is_wp_error( $catalog ) ) {
				return $catalog;
			}
			$catalogs[] = self::summary( $catalog );
		}

		usort(
			$catalogs,
			static fn( array $left, array $right ): int => strcasecmp( $left['nome'], $right['nome'] )
		);

		return $catalogs;
	}

	public static function find( string $code ) {
		$path = self::catalog_path( $code );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( ! is_file( $path ) ) {
			return self::error( 'catalog_not_found', 'Catalogo non trovato.', 404 );
		}

		return self::read( $path );
	}

	public static function create( string $code, string $name ) {
		$code = self::valid_code( $code );
		if ( is_wp_error( $code ) ) {
			return $code;
		}
		$name = self::valid_name( $name );
		if ( is_wp_error( $name ) ) {
			return $name;
		}

		$path = self::catalog_path( $code );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( file_exists( $path ) ) {
			return self::error( 'catalog_code_exists', 'Esiste già un catalogo con questo codice.', 409 );
		}

		$now     = gmdate( 'c' );
		$catalog = array(
			'schemaVersion' => self::SCHEMA_VERSION,
			'testata'       => array(
				'codice'    => $code,
				'nome'      => $name,
				'createdAt' => $now,
				'updatedAt' => $now,
			),
			'prodotti'      => array(),
		);

		$result = self::write( $path, $catalog );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		self::rebuild_index();

		return self::summary( $catalog );
	}

	public static function update( string $code, string $name ) {
		$catalog = self::find( $code );
		if ( is_wp_error( $catalog ) ) {
			return $catalog;
		}
		$name = self::valid_name( $name );
		if ( is_wp_error( $name ) ) {
			return $name;
		}

		$catalog['testata']['nome']      = $name;
		$catalog['testata']['updatedAt'] = gmdate( 'c' );
		$path = self::catalog_path( $code );
		if ( is_wp_error( $path ) ) {
			return $path;
		}

		$result = self::write( $path, $catalog );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		self::rebuild_index();

		return self::summary( $catalog );
	}

	public static function delete( string $code ) {
		$path = self::catalog_path( $code );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( ! is_file( $path ) ) {
			return self::error( 'catalog_not_found', 'Catalogo non trovato.', 404 );
		}
		if ( ! unlink( $path ) ) {
			return self::error( 'catalog_delete_failed', 'Impossibile eliminare il file del catalogo.', 500 );
		}

		self::rebuild_index();
		return true;
	}

	private static function directory() {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return self::error( 'catalog_storage_unavailable', (string) $uploads['error'], 500 );
		}

		$directory = trailingslashit( $uploads['basedir'] ) . 'fant-admin-api/cataloghi';
		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			return self::error( 'catalog_storage_unavailable', 'Impossibile creare la cartella dei cataloghi.', 500 );
		}

		self::protect_directory( $directory );
		return $directory;
	}

	private static function protect_directory( string $directory ): void {
		$files = array(
			'index.php'  => "<?php\n// Silence is golden.\n",
			'.htaccess'  => "Require all denied\nDeny from all\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\"/><add accessType=\"Deny\" users=\"*\"/></authorization></security></system.webServer></configuration>\n",
		);
		foreach ( $files as $name => $content ) {
			$path = trailingslashit( $directory ) . $name;
			if ( ! file_exists( $path ) ) {
				@file_put_contents( $path, $content, LOCK_EX );
			}
		}
	}

	private static function catalog_path( string $code ) {
		$code = self::valid_code( $code );
		if ( is_wp_error( $code ) ) {
			return $code;
		}
		$directory = self::directory();
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}

		return trailingslashit( $directory ) . $code . '.json';
	}

	private static function valid_code( string $code ) {
		$code = strtolower( trim( $code ) );
		if ( ! preg_match( '/^[a-z0-9][a-z0-9_-]{0,79}$/', $code ) ) {
			return self::error(
				'invalid_catalog_code',
				'Il codice deve iniziare con una lettera o un numero e può contenere solo lettere minuscole, numeri, trattini e underscore.',
				422
			);
		}

		return $code;
	}

	private static function valid_name( string $name ) {
		$name = trim( sanitize_text_field( $name ) );
		if ( '' === $name ) {
			return self::error( 'catalog_name_required', 'Il nome del catalogo è obbligatorio.', 422 );
		}

		return $name;
	}

	private static function read( string $path ) {
		$content = file_get_contents( $path );
		if ( false === $content ) {
			return self::error( 'catalog_read_failed', 'Impossibile leggere il catalogo ' . basename( $path ) . '.', 500 );
		}
		$data = json_decode( $content, true );
		if ( ! is_array( $data ) || ! isset( $data['testata'], $data['prodotti'] ) || ! is_array( $data['testata'] ) || ! is_array( $data['prodotti'] ) ) {
			return self::error( 'catalog_json_invalid', 'Il file ' . basename( $path ) . ' non contiene un catalogo valido.', 500 );
		}

		return $data;
	}

	private static function write( string $path, array $data ) {
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			return self::error( 'catalog_encode_failed', 'Impossibile generare il JSON del catalogo.', 500 );
		}

		$directory = dirname( $path );
		$temp      = tempnam( $directory, '.faa-' );
		if ( false === $temp ) {
			return self::error( 'catalog_write_failed', 'Impossibile creare il file temporaneo del catalogo.', 500 );
		}

		$written = file_put_contents( $temp, $json . "\n", LOCK_EX );
		if ( false === $written || ! rename( $temp, $path ) ) {
			@unlink( $temp );
			return self::error( 'catalog_write_failed', 'Impossibile salvare il catalogo.', 500 );
		}
		@chmod( $path, 0640 );

		return true;
	}

	private static function summary( array $catalog ): array {
		$header   = $catalog['testata'];
		$products = $catalog['prodotti'];
		return array(
			'codice'          => (string) ( $header['codice'] ?? '' ),
			'nome'            => (string) ( $header['nome'] ?? '' ),
			'numeroProdotti'  => count( $products ),
			'createdAt'       => (string) ( $header['createdAt'] ?? '' ),
			'updatedAt'       => (string) ( $header['updatedAt'] ?? '' ),
		);
	}

	private static function rebuild_index(): void {
		$catalogs = self::all();
		$directory = self::directory();
		if ( is_wp_error( $catalogs ) || is_wp_error( $directory ) ) {
			return;
		}

		$result = self::write(
			trailingslashit( $directory ) . self::INDEX_FILE,
			array(
				'schemaVersion' => self::SCHEMA_VERSION,
				'cataloghi'     => $catalogs,
			)
		);
		if ( is_wp_error( $result ) ) {
			error_log( '[fantAdminApi] Impossibile aggiornare l’indice dei cataloghi: ' . $result->get_error_message() );
		}
	}

	private static function error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
