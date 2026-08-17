<?php

defined( 'ABSPATH' ) || exit;

final class Fant_Admin_API_V4_Catalogs {
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
			'settings'      => self::default_settings(),
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

	public static function update_contenuto( string $code, $prodotti ) {
		$catalog = self::find( $code );
		if ( is_wp_error( $catalog ) ) {
			return $catalog;
		}
		$normalized = self::normalize_prodotti( $prodotti );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$catalog['prodotti']             = $normalized;
		$catalog['schemaVersion']        = 2;
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

	public static function update_settings( string $code, $settings ) {
		$catalog = self::find( $code );
		if ( is_wp_error( $catalog ) ) {
			return $catalog;
		}
		$normalized = self::normalize_settings( $settings );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$catalog['settings']             = $normalized;
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

	/** @return array<string, mixed> */
	public static function default_settings(): array {
		return array(
			'sezione' => array(
				'backgroundColor' => '#C6B2B3',
				'textColor'       => '#333333',
				'fontSize'        => 15,
			),
		);
	}

	/**
	 * @param mixed $settings
	 * @return array<string, mixed>|WP_Error
	 */
	public static function normalize_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return self::error( 'catalog_settings_invalid', 'settings deve essere un oggetto.', 400 );
		}

		$defaults = self::default_settings();
		$sezione  = is_array( $settings['sezione'] ?? null ) ? $settings['sezione'] : array();

		$background = self::valid_hex_color( (string) ( $sezione['backgroundColor'] ?? $defaults['sezione']['backgroundColor'] ) );
		if ( is_wp_error( $background ) ) {
			return $background;
		}
		$text = self::valid_hex_color( (string) ( $sezione['textColor'] ?? $defaults['sezione']['textColor'] ) );
		if ( is_wp_error( $text ) ) {
			return $text;
		}
		$size = (int) ( $sezione['fontSize'] ?? $defaults['sezione']['fontSize'] );
		if ( $size < 8 || $size > 48 ) {
			return self::error( 'catalog_settings_invalid', 'La dimensione testo deve essere tra 8 e 48.', 422 );
		}

		return array(
			'sezione' => array(
				'backgroundColor' => strtoupper( $background ),
				'textColor'       => strtoupper( $text ),
				'fontSize'        => $size,
			),
		);
	}

	/**
	 * @param string $color
	 * @return string|WP_Error
	 */
	private static function valid_hex_color( string $color ) {
		$color = trim( $color );
		if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color ) ) {
			if ( 4 === strlen( $color ) ) {
				return '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
			}
			return $color;
		}

		return self::error( 'catalog_settings_invalid', 'Colore non valido: usare formato #RGB o #RRGGBB.', 422 );
	}

	public static function normalize_prodotti( $prodotti ) {
		if ( ! is_array( $prodotti ) ) {
			return self::error( 'catalog_contenuto_invalid', 'prodotti deve essere un array.', 400 );
		}

		$sections = array();
		foreach ( $prodotti as $section ) {
			if ( ! is_array( $section ) ) {
				return self::error( 'catalog_contenuto_invalid', 'Ogni sezione deve essere un oggetto.', 400 );
			}

			$category_id = (int) ( $section['categoryId'] ?? 0 );
			$nome        = trim( sanitize_text_field( (string) ( $section['nome'] ?? '' ) ) );
			if ( $category_id < 0 || '' === $nome ) {
				return self::error( 'catalog_contenuto_invalid', 'Nome sezione obbligatorio; categoryId non valido.', 400 );
			}

			$articoli_in = $section['articoli'] ?? array();
			if ( ! is_array( $articoli_in ) ) {
				return self::error( 'catalog_contenuto_invalid', 'articoli deve essere un array.', 400 );
			}

			$articoli = array();
			$seen_sku = array();
			foreach ( $articoli_in as $articolo ) {
				if ( ! is_array( $articolo ) ) {
					return self::error( 'catalog_contenuto_invalid', 'Ogni articolo deve essere un oggetto.', 400 );
				}

				$normalized = self::normalize_articolo( $articolo, $seen_sku, true );
				if ( is_wp_error( $normalized ) ) {
					return $normalized;
				}
				if ( null === $normalized ) {
					continue;
				}
				$articoli[] = $normalized;
			}

			$sections[] = array(
				'categoryId'  => $category_id,
				'nome'        => $nome,
				'saltoPagina' => ! empty( $section['saltoPagina'] ),
				'articoli'    => $articoli,
			);
		}

		return $sections;
	}

	/**
	 * @param array $articolo
	 * @param array $seen_sku
	 * @param bool  $allow_variations
	 * @return array|null|WP_Error
	 */
	private static function normalize_articolo( array $articolo, array &$seen_sku, bool $allow_variations ) {
		$product_id = (int) ( $articolo['productId'] ?? 0 );
		$sku        = trim( sanitize_text_field( (string) ( $articolo['sku'] ?? '' ) ) );
		$anome      = trim( sanitize_text_field( (string) ( $articolo['nome'] ?? '' ) ) );
		if ( $product_id <= 0 || '' === $sku || '' === $anome ) {
			return self::error( 'catalog_contenuto_invalid', 'productId, sku e nome articolo sono obbligatori.', 400 );
		}

		$key = strtolower( $sku );
		if ( isset( $seen_sku[ $key ] ) ) {
			return null;
		}
		$seen_sku[ $key ] = true;

		$normalized = array(
			'productId'   => $product_id,
			'sku'         => $sku,
			'nome'        => $anome,
			'saltoPagina' => ! empty( $articolo['saltoPagina'] ),
		);

		if ( $allow_variations ) {
			$variazioni_in = $articolo['variazioni'] ?? array();
			if ( ! is_array( $variazioni_in ) ) {
				return self::error( 'catalog_contenuto_invalid', 'variazioni deve essere un array.', 400 );
			}
			$variazioni = array();
			foreach ( $variazioni_in as $variazione ) {
				if ( ! is_array( $variazione ) ) {
					return self::error( 'catalog_contenuto_invalid', 'Ogni variazione deve essere un oggetto.', 400 );
				}
				$child = self::normalize_articolo( $variazione, $seen_sku, false );
				if ( is_wp_error( $child ) ) {
					return $child;
				}
				if ( null !== $child ) {
					$variazioni[] = $child;
				}
			}
			$normalized['variazioni'] = $variazioni;
		}

		return $normalized;
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

		$normalized_settings = self::normalize_settings( $data['settings'] ?? self::default_settings() );
		$data['settings']    = is_wp_error( $normalized_settings )
			? self::default_settings()
			: $normalized_settings;

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
		$products = is_array( $catalog['prodotti'] ?? null ) ? $catalog['prodotti'] : array();
		$count    = 0;
		foreach ( $products as $section ) {
			if ( is_array( $section ) && isset( $section['articoli'] ) && is_array( $section['articoli'] ) ) {
				foreach ( $section['articoli'] as $articolo ) {
					if ( ! is_array( $articolo ) ) {
						continue;
					}
					++$count;
					if ( isset( $articolo['variazioni'] ) && is_array( $articolo['variazioni'] ) ) {
						$count += count( $articolo['variazioni'] );
					}
				}
			}
		}
		if ( 0 === $count && $products && ! isset( $products[0]['articoli'] ) && ! isset( $products[0]['categoryId'] ) ) {
			$count = count( $products );
		}

		return array(
			'codice'         => (string) ( $header['codice'] ?? '' ),
			'nome'           => (string) ( $header['nome'] ?? '' ),
			'numeroProdotti' => $count,
			'createdAt'      => (string) ( $header['createdAt'] ?? '' ),
			'updatedAt'      => (string) ( $header['updatedAt'] ?? '' ),
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
