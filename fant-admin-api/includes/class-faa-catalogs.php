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

		$data = self::read( $path );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$data = self::with_anteprime( $path, $data );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return self::with_pdfs( $path, $data );
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
			'filesAggiuntivi' => array(
				'copertina' => '',
				'retro'     => '',
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

		$files     = is_array( $settings['filesAggiuntivi'] ?? null ) ? $settings['filesAggiuntivi'] : array();
		$copertina = self::valid_optional_cover_code( (string) ( $files['copertina'] ?? '' ) );
		if ( is_wp_error( $copertina ) ) {
			return $copertina;
		}
		$retro = self::valid_optional_cover_code( (string) ( $files['retro'] ?? '' ) );
		if ( is_wp_error( $retro ) ) {
			return $retro;
		}

		return array(
			'sezione' => array(
				'backgroundColor' => strtoupper( $background ),
				'textColor'       => strtoupper( $text ),
				'fontSize'        => $size,
			),
			'filesAggiuntivi' => array(
				'copertina' => $copertina,
				'retro'     => $retro,
			),
		);
	}

	/**
	 * @return string|WP_Error
	 */
	private static function valid_optional_cover_code( string $code ) {
		$code = strtolower( trim( $code ) );
		if ( '' === $code ) {
			return '';
		}
		if ( ! preg_match( '/^[a-z0-9][a-z0-9_-]{0,79}$/', $code ) ) {
			return self::error( 'catalog_settings_invalid', 'Il file selezionato non è una copertina valida.', 422 );
		}
		return $code;
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

		$layout = trim( sanitize_text_field( (string) ( $articolo['layout'] ?? '' ) ) );
		if ( '' !== $layout ) {
			$allowed_layouts = array(
				'articolo-semplice',
				'articolo-variazioni-no-img',
				'articolo-variazioni-img',
				'nutrizione-sfuso',
				'nutrizione-bancali',
			);
			if ( ! in_array( $layout, $allowed_layouts, true ) ) {
				return self::error( 'catalog_contenuto_invalid', 'Layout articolo non valido.', 400 );
			}
			$normalized['layout'] = $layout;
		}

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
		$existing = self::read( $path );
		if ( ! unlink( $path ) ) {
			return self::error( 'catalog_delete_failed', 'Impossibile eliminare il file del catalogo.', 500 );
		}
		if ( is_array( $existing ) ) {
			self::unlink_catalog_files( $code, $existing );
		}

		self::rebuild_index();
		return true;
	}

	/**
	 * @param array<int, mixed> $sezioni
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create_anteprima_record( string $code, string $nome, array $sezioni ) {
		return self::save_anteprima_record( $code, '', $nome, $sezioni );
	}

	/**
	 * @param array<int, mixed> $sezioni
	 * @return array<string, mixed>|WP_Error
	 */
	public static function update_anteprima_record( string $code, string $id, string $nome, array $sezioni ) {
		return self::save_anteprima_record( $code, $id, $nome, $sezioni );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public static function delete_anteprima_record( string $code, string $id ) {
		$loaded = self::catalog_for_write( $code );
		if ( is_wp_error( $loaded ) ) {
			return $loaded;
		}
		$path = $loaded['path'];
		$data = $loaded['data'];
		$index = self::anteprima_index( $data, $id );
		if ( $index < 0 ) {
			return self::error( 'catalog_anteprima_missing', 'Anteprima non trovata.', 404 );
		}
		foreach ( $data['pdfs'] ?? array() as $pdf ) {
			if ( is_array( $pdf ) && (string) ( $pdf['anteprimaId'] ?? '' ) === $id ) {
				return self::error( 'catalog_anteprima_in_use', 'Questa anteprima è usata da un PDF. Elimina prima quel PDF.', 409 );
			}
		}
		$item = $data['anteprime'][ $index ];
		self::unlink_anteprima_files( $code, $item );
		array_splice( $data['anteprime'], $index, 1 );
		if ( (string) ( $data['anteprimaCorrente'] ?? '' ) === $id ) {
			$data['anteprimaCorrente'] = '';
		}
		$written = self::write( $path, $data );
		if ( is_wp_error( $written ) ) {
			return $written;
		}
		return self::anteprima_state( $data );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public static function load_anteprima_record( string $code, string $id ) {
		$loaded = self::catalog_for_write( $code );
		if ( is_wp_error( $loaded ) ) {
			return $loaded;
		}
		$data = $loaded['data'];
		if ( self::anteprima_index( $data, $id ) < 0 ) {
			return self::error( 'catalog_anteprima_missing', 'Anteprima non trovata.', 404 );
		}
		$data['anteprimaCorrente'] = $id;
		$written = self::write( $loaded['path'], $data );
		if ( is_wp_error( $written ) ) {
			return $written;
		}
		return self::anteprima_state( $data );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create_pdf_record( string $code, string $nome, string $anteprima_id ) {
		return self::save_pdf_record( $code, '', $nome, $anteprima_id );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public static function update_pdf_record( string $code, string $id, string $nome, string $anteprima_id ) {
		return self::save_pdf_record( $code, $id, $nome, $anteprima_id );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public static function delete_pdf_record( string $code, string $id ) {
		$loaded = self::catalog_for_write( $code );
		if ( is_wp_error( $loaded ) ) {
			return $loaded;
		}
		$data  = $loaded['data'];
		$index = self::pdf_index( $data, $id );
		if ( $index < 0 ) {
			return self::error( 'catalog_pdf_missing', 'PDF non trovato.', 404 );
		}
		$item = $data['pdfs'][ $index ];
		$code = (string) ( $data['testata']['codice'] ?? $code );
		$path = self::resolve_pdf_path( $code, is_array( $item ) ? $item : array() );
		if ( ! is_wp_error( $path ) && is_file( $path ) && ! unlink( $path ) ) {
			return self::error( 'catalog_pdf_failed', 'Impossibile eliminare il file PDF.', 500 );
		}
		array_splice( $data['pdfs'], $index, 1 );
		if ( (string) ( $data['pdfCorrente'] ?? '' ) === $id ) {
			$data['pdfCorrente'] = '';
		}
		$written = self::write( $loaded['path'], $data );
		if ( is_wp_error( $written ) ) {
			return $written;
		}
		return self::pdf_state( $data );
	}

	/**
	 * @return array{pdfs: array<int, array<string, mixed>>, pdfCorrente: string}|WP_Error
	 */
	public static function update_pdf_uso( string $code, string $id, bool $usa_copertina, bool $usa_retro ) {
		$loaded = self::catalog_for_write( $code );
		if ( is_wp_error( $loaded ) ) {
			return $loaded;
		}
		$data  = $loaded['data'];
		$index = self::pdf_index( $data, $id );
		if ( $index < 0 ) {
			return self::error( 'catalog_pdf_missing', 'PDF non trovato.', 404 );
		}
		$data['pdfs'][ $index ]['usaCopertina'] = $usa_copertina;
		$data['pdfs'][ $index ]['usaRetro']     = $usa_retro;
		$written = self::write( $loaded['path'], $data );
		if ( is_wp_error( $written ) ) {
			return $written;
		}
		return self::pdf_state( $data );
	}

	public static function load_pdf_record( string $code, string $id ) {
		$loaded = self::catalog_for_write( $code );
		if ( is_wp_error( $loaded ) ) {
			return $loaded;
		}
		$data = $loaded['data'];
		if ( self::pdf_index( $data, $id ) < 0 ) {
			return self::error( 'catalog_pdf_missing', 'PDF non trovato.', 404 );
		}
		$data['pdfCorrente'] = $id;
		$written = self::write( $loaded['path'], $data );
		if ( is_wp_error( $written ) ) {
			return $written;
		}
		return self::pdf_state( $data );
	}

	/**
	 * Legge il file HTML dell'anteprima corrente o di quella indicata.
	 *
	 * @return array<string, string>|WP_Error
	 */
	public static function read_anteprima( string $code, string $id = '' ) {
		$data = self::find( $code );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$code = (string) ( $data['testata']['codice'] ?? $code );
		$id   = $id !== '' ? $id : (string) ( $data['anteprimaCorrente'] ?? '' );
		$item = self::anteprima_by_id( $data, $id );
		if ( ! is_array( $item ) ) {
			return self::error( 'catalog_anteprima_missing', 'Nessuna anteprima caricata.', 404 );
		}
		$path = self::resolve_html_path( $code, $item );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( ! is_file( $path ) ) {
			return self::error( 'catalog_anteprima_missing', 'Anteprima non ancora generata.', 404 );
		}
		$html = file_get_contents( $path );
		if ( ! is_string( $html ) || '' === $html ) {
			return self::error( 'catalog_anteprima_missing', 'Il file di anteprima è vuoto.', 404 );
		}
		$payload         = self::anteprima_payload( $path, $html );
		$payload['id']   = $id;
		return $payload;
	}

	/**
	 * Salva il file HTML dell'anteprima indicata, o di quella corrente.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function save_anteprima( string $code, string $html, string $id = '', bool $make_current = true ) {
		$loaded = self::catalog_for_write( $code );
		if ( is_wp_error( $loaded ) ) {
			return $loaded;
		}
		$data = $loaded['data'];
		$code = (string) ( $data['testata']['codice'] ?? $code );
		$id   = $id !== '' ? $id : (string) ( $data['anteprimaCorrente'] ?? '' );
		$index = self::anteprima_index( $data, $id );
		if ( $index < 0 ) {
			return self::error( 'catalog_anteprima_missing', 'Nessuna anteprima caricata.', 404 );
		}
		$html = self::accepted_anteprima_html( $html );
		if ( is_wp_error( $html ) ) {
			return $html;
		}
		$path = self::anteprima_file_path( $code, self::anteprima_storage_id( $id ) );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( false === file_put_contents( $path, $html, LOCK_EX ) ) {
			return self::error( 'catalog_anteprima_failed', 'Impossibile salvare il file di anteprima.', 500 );
		}
		$data['anteprime'][ $index ]['generato'] = true;
		$data['anteprime'][ $index ]['fileHtml'] = basename( $path );
		$data['anteprime'][ $index ]['pathHtml'] = self::display_path( $path );
		if ( $make_current ) {
			$data['anteprimaCorrente'] = $id;
		}
		$written = self::write( $loaded['path'], $data );
		if ( is_wp_error( $written ) ) {
			return $written;
		}
		$payload = self::anteprima_payload( $path, $html );
		$payload['id'] = $id;
		$payload['anteprime'] = $data['anteprime'];
		$payload['anteprimaCorrente'] = (string) ( $data['anteprimaCorrente'] ?? '' );
		return $payload;
	}

	/**
	 * Elimina il file HTML dell'anteprima corrente e azzera il flag Generato.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function delete_anteprima( string $code, string $id = '' ) {
		$loaded = self::catalog_for_write( $code );
		if ( is_wp_error( $loaded ) ) {
			return $loaded;
		}
		$data  = $loaded['data'];
		$code  = (string) ( $data['testata']['codice'] ?? $code );
		$id    = $id !== '' ? $id : (string) ( $data['anteprimaCorrente'] ?? '' );
		$index = self::anteprima_index( $data, $id );
		if ( $index < 0 ) {
			return self::error( 'catalog_anteprima_missing', 'Nessuna anteprima caricata.', 404 );
		}
		$path = self::resolve_html_path( $code, $data['anteprime'][ $index ] );
		if ( ! is_wp_error( $path ) && is_file( $path ) && ! unlink( $path ) ) {
			return self::error( 'catalog_anteprima_failed', 'Impossibile eliminare il file di anteprima.', 500 );
		}
		$data['anteprime'][ $index ]['generato'] = false;
		$data['anteprime'][ $index ]['fileHtml'] = '';
		$data['anteprime'][ $index ]['pathHtml'] = '';
		$written = self::write( $loaded['path'], $data );
		if ( is_wp_error( $written ) ) {
			return $written;
		}
		$state = self::anteprima_state( $data );
		$state['deleted'] = true;
		return $state;
	}

	/**
	 * Genera il PDF dal file HTML di anteprima e lo restituisce in base64.
	 *
	 * @param array<string, mixed> $options
	 * @return array<string, string>|WP_Error
	 */
	public static function pdf( string $code, array $options = array() ) {
		$data = self::find( $code );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$pdf_id = (string) ( $data['pdfCorrente'] ?? '' );
		$record = self::pdf_by_id( $data, $pdf_id );
		if ( ! is_array( $record ) ) {
			return self::error( 'catalog_pdf_missing', 'Nessun PDF caricato.', 404 );
		}
		$usa_copertina = array_key_exists( 'usaCopertina', $options ) ? (bool) $options['usaCopertina'] : ! empty( $record['usaCopertina'] );
		$usa_retro     = array_key_exists( 'usaRetro', $options ) ? (bool) $options['usaRetro'] : ! empty( $record['usaRetro'] );
		if ( array_key_exists( 'usaCopertina', $options ) || array_key_exists( 'usaRetro', $options ) ) {
			$saved = self::update_pdf_uso( $code, $pdf_id, $usa_copertina, $usa_retro );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}
		$id = (string) ( $record['anteprimaId'] ?? '' );
		$saved = self::read_anteprima( $code, $id );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$html = self::prepare_pdf_html( (string) $saved['html'] );
		if ( is_wp_error( $html ) ) {
			return $html;
		}

		$binary = self::render_html_pdf( $html );
		if ( is_wp_error( $binary ) ) {
			return $binary;
		}
		$binary = self::with_extra_files( $data, $usa_copertina, $usa_retro, $binary );
		if ( is_wp_error( $binary ) ) {
			return $binary;
		}

		$code = (string) ( $data['testata']['codice'] ?? $code );
		$path = self::resolve_pdf_path( $code, $record );
		if ( is_wp_error( $path ) || '' === (string) ( $record['filePdf'] ?? '' ) ) {
			$path = self::pdf_file_path( $code, self::anteprima_storage_id( $pdf_id ) );
		}
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( false === file_put_contents( $path, $binary, LOCK_EX ) ) {
			return self::error( 'catalog_pdf_failed', 'Impossibile salvare il PDF del catalogo.', 500 );
		}
		$stored = self::store_pdf_reference( $code, $pdf_id, $path );
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}

		return self::pdf_payload( $path, $binary );
	}

	/**
	 * Antepone il PDF di copertina e accoda il retro, se gli interruttori del PDF caricato sono accesi.
	 *
	 * @param array<string, mixed> $data
	 * @return string|WP_Error
	 */
	private static function with_extra_files( array $data, bool $usa_copertina, bool $usa_retro, string $binary ) {
		$files = is_array( $data['settings']['filesAggiuntivi'] ?? null ) ? $data['settings']['filesAggiuntivi'] : array();
		$parts = array();
		if ( $usa_copertina ) {
			$cover = self::extra_pdf_binary( (string) ( $files['copertina'] ?? '' ), 'Il PDF della copertina selezionata non è disponibile.' );
			if ( is_wp_error( $cover ) ) {
				return $cover;
			}
			if ( is_string( $cover ) ) {
				$parts[] = $cover;
			}
		}
		$parts[] = $binary;
		if ( $usa_retro ) {
			$back = self::extra_pdf_binary( (string) ( $files['retro'] ?? '' ), 'Il PDF del retro selezionato non è disponibile.' );
			if ( is_wp_error( $back ) ) {
				return $back;
			}
			if ( is_string( $back ) ) {
				$parts[] = $back;
			}
		}
		if ( 1 === count( $parts ) ) {
			return $binary;
		}
		return Fant_Admin_API_V4_Pdf_Merge::concat( $parts );
	}

	/**
	 * @return string|null|WP_Error null se non c'è un file selezionato
	 */
	private static function extra_pdf_binary( string $cover_code, string $missing_message ) {
		$cover_code = strtolower( trim( $cover_code ) );
		if ( '' === $cover_code ) {
			return null;
		}
		$path = Fant_Admin_API_V4_Covers::pdf_absolute_path( $cover_code );
		if ( is_wp_error( $path ) ) {
			return new WP_Error( 'catalog_extra_pdf_missing', $missing_message, array( 'status' => 404 ) );
		}
		$binary = file_get_contents( $path );
		if ( ! is_string( $binary ) || '' === $binary ) {
			return new WP_Error( 'catalog_extra_pdf_missing', $missing_message, array( 'status' => 404 ) );
		}
		return $binary;
	}

	/**
	 * Restituisce il PDF già salvato, senza rigenerarlo.
	 *
	 * @return array<string, string>|WP_Error
	 */
	public static function read_pdf( string $code ) {
		$data = self::find( $code );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$id   = (string) ( $data['pdfCorrente'] ?? '' );
		$item = self::pdf_by_id( $data, $id );
		if ( ! is_array( $item ) || '' === (string) ( $item['filePdf'] ?? '' ) ) {
			return self::error( 'catalog_pdf_missing', 'PDF non ancora generato.', 404 );
		}
		$code = (string) ( $data['testata']['codice'] ?? $code );
		$path = self::resolve_pdf_path( $code, $item );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( ! is_file( $path ) ) {
			return self::error( 'catalog_pdf_missing', 'Il file PDF non è più presente.', 404 );
		}
		$binary = file_get_contents( $path );
		if ( ! is_string( $binary ) || '' === $binary ) {
			return self::error( 'catalog_pdf_missing', 'Il file PDF è vuoto.', 404 );
		}
		return self::pdf_payload( $path, $binary );
	}

	/**
	 * @return string|WP_Error
	 */
	private static function accepted_anteprima_html( string $html ) {
		$html = wp_unslash( $html );
		if ( strlen( $html ) < 20 || strlen( $html ) > 8000000 || false === stripos( $html, '<html' ) ) {
			return self::error( 'catalog_anteprima_invalid_html', 'Manca l\'anteprima HTML da salvare.', 422 );
		}
		return preg_replace( '#<\s*script\b[^>]*>.*?<\s*/\s*script\s*>#is', '', $html ) ?? $html;
	}

	/**
	 * @param string $path
	 * @return array{filename: string, path: string, html: string}
	 */
	private static function anteprima_payload( string $path, string $html ): array {
		return array(
			'filename' => basename( $path ),
			'path'     => self::display_path( $path ),
			'html'     => $html,
		);
	}

	/**
	 * @return array{filename: string, path: string, mimeType: string, contentBase64: string}
	 */
	private static function pdf_payload( string $path, string $binary ): array {
		return array(
			'filename'      => basename( $path ),
			'path'          => self::display_path( $path ),
			'mimeType'      => 'application/pdf',
			'contentBase64' => base64_encode( $binary ),
		);
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>|WP_Error
	 */
	private static function with_pdf_reference( string $catalog_path, array $data ) {
		$code = (string) ( $data['testata']['codice'] ?? '' );
		$file = self::pdf_file_path( $code );
		$exists = ! is_wp_error( $file ) && is_file( $file );
		if ( ! $exists ) {
			if ( isset( $data['pdf'] ) ) {
				unset( $data['pdf'] );
				$written = self::write( $catalog_path, $data );
				if ( is_wp_error( $written ) ) {
					return $written;
				}
			}
			return $data;
		}
		$pdf = array(
			'filename' => basename( $file ),
			'path'     => self::display_path( $file ),
		);
		if ( ( $data['pdf'] ?? null ) !== $pdf ) {
			$data['pdf'] = $pdf;
			$written     = self::write( $catalog_path, $data );
			if ( is_wp_error( $written ) ) {
				return $written;
			}
		}
		$data['pdf'] = $pdf;
		return $data;
	}

	/**
	 * @return true|WP_Error
	 */
	private static function store_pdf_reference( string $code, string $id, string $file ) {
		$catalog_path = self::catalog_path( $code );
		if ( is_wp_error( $catalog_path ) ) {
			return $catalog_path;
		}
		$catalog = self::read( $catalog_path );
		if ( is_wp_error( $catalog ) ) {
			return $catalog;
		}
		$index = self::pdf_index( $catalog, $id );
		if ( $index < 0 ) {
			return self::error( 'catalog_pdf_missing', 'Nessun PDF caricato.', 404 );
		}
		$catalog['pdfs'][ $index ]['generato'] = true;
		$catalog['pdfs'][ $index ]['filePdf']  = basename( $file );
		$catalog['pdfs'][ $index ]['pathPdf']  = self::display_path( $file );
		$catalog['pdf'] = array(
			'filename' => basename( $file ),
			'path'     => self::display_path( $file ),
		);
		return self::write( $catalog_path, $catalog );
	}

	private static function display_path( string $path ): string {
		$path = wp_normalize_path( $path );
		$root = trailingslashit( wp_normalize_path( ABSPATH ) );
		if ( '' !== $root && str_starts_with( $path, $root ) ) {
			return ltrim( substr( $path, strlen( $root ) ), '/' );
		}
		return $path;
	}

	private static function prepare_pdf_html( string $html ) {
		$html = self::accepted_anteprima_html( $html );
		if ( is_wp_error( $html ) ) {
			return $html;
		}
		$compat = '<style>'
			. '.art-semplice-intestazione,.art-semplice-riga,.art-semplice-specchio-titoli,.art-semplice-specchio-riga{display:table;width:100%;border-collapse:collapse;}'
			. '.art-semplice-intestazione>span,.art-semplice-riga>div,.art-semplice-specchio-titoli>span,.art-semplice-specchio-riga>span{display:table-cell;vertical-align:middle;}'
			. '</style>';
		if ( false !== stripos( $html, '</head>' ) ) {
			return preg_replace( '#</head>#i', $compat . '</head>', $html, 1 ) ?? $html;
		}
		return $compat . $html;
	}

	/**
	 * @return string|WP_Error
	 */
	private static function render_html_pdf( string $html ) {
		$autoload = FANT_ADMIN_API_V4_PATH . 'lib/pdf/autoload.php';
		if ( ! is_file( $autoload ) ) {
			return self::error( 'catalog_pdf_failed', 'Il motore PDF non è installato nel plugin.', 500 );
		}
		require_once $autoload;

		$directory = self::directory();
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		$font_cache = trailingslashit( dirname( $directory ) ) . 'cataloghi_pdf/fonts';
		if ( ! is_dir( $font_cache ) && ! wp_mkdir_p( $font_cache ) ) {
			return self::error( 'catalog_storage_unavailable', 'Impossibile creare la cache dei font PDF.', 500 );
		}

		try {
			$options = new Dompdf\Options();
			$options->setIsRemoteEnabled( true );
			$options->setAllowedRemoteHosts( self::pdf_image_hosts() );
			$options->setIsHtml5ParserEnabled( true );
			$options->setDefaultMediaType( 'print' );
			$options->setChroot( FANT_ADMIN_API_V4_PATH );
			$options->setFontDir( FANT_ADMIN_API_V4_PATH . 'lib/pdf/dompdf/lib/fonts' );
			$options->setFontCache( $font_cache );
			$dompdf = new Dompdf\Dompdf( $options );
			$dompdf->loadHtml( $html, 'UTF-8' );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();
			$binary = $dompdf->output();
		} catch ( Throwable $error ) {
			return self::error( 'catalog_pdf_failed', 'Impossibile generare il PDF del catalogo.', 500 );
		}

		if ( ! is_string( $binary ) || '' === $binary ) {
			return self::error( 'catalog_pdf_failed', 'Il PDF generato è vuoto.', 500 );
		}
		return $binary;
	}

	/**
	 * Solo gli host del negozio: le foto prodotto sono allegati WooCommerce.
	 *
	 * @return string[]
	 */
	private static function pdf_image_hosts(): array {
		$hosts = array();
		$uploads = wp_upload_dir();
		$baseurl = is_array( $uploads ) ? (string) ( $uploads['baseurl'] ?? '' ) : '';
		foreach ( array( home_url(), site_url(), $baseurl ) as $url ) {
			$host = wp_parse_url( (string) $url, PHP_URL_HOST );
			if ( is_string( $host ) && $host !== '' ) {
				$hosts[] = strtolower( $host );
			}
		}
		return array_values( array_unique( $hosts ) );
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>|WP_Error
	 */
	private static function with_anteprime( string $catalog_path, array $data ) {
		if ( isset( $data['anteprime'] ) && is_array( $data['anteprime'] ) ) {
			$data['anteprime']          = self::normalize_anteprime_list( $data['anteprime'] );
			$data['anteprimaCorrente']  = self::valid_current_id( $data );
			return $data;
		}
		$code = (string) ( $data['testata']['codice'] ?? '' );
		$data['anteprime']         = array();
		$data['anteprimaCorrente'] = '';
		$legacy = self::anteprima_file_path( $code );
		if ( ! is_wp_error( $legacy ) && is_file( $legacy ) ) {
			$pdf_file = self::pdf_file_path( $code );
			$has_pdf  = ! is_wp_error( $pdf_file ) && is_file( $pdf_file );
			$data['anteprime'][] = array(
				'id'        => 'legacy',
				'nome'      => 'Completa',
				'sezioni'   => self::sections_from_catalog( $data ),
				'generato'  => true,
				'fileHtml'  => basename( $legacy ),
				'pathHtml'  => self::display_path( $legacy ),
				'filePdf'   => $has_pdf ? basename( $pdf_file ) : '',
				'pathPdf'   => $has_pdf ? self::display_path( $pdf_file ) : '',
			);
			$data['anteprimaCorrente'] = 'legacy';
		}
		$written = self::write( $catalog_path, $data );
		if ( is_wp_error( $written ) ) {
			return $written;
		}
		return $data;
	}

	/**
	 * @param array<int, mixed> $sezioni
	 * @return array<string, mixed>|WP_Error
	 */
	private static function save_anteprima_record( string $code, string $id, string $nome, array $sezioni ) {
		$loaded = self::catalog_for_write( $code );
		if ( is_wp_error( $loaded ) ) {
			return $loaded;
		}
		$data    = $loaded['data'];
		$nome    = self::valid_anteprima_nome( $nome );
		if ( is_wp_error( $nome ) ) {
			return $nome;
		}
		$sezioni = self::valid_anteprima_sezioni( $sezioni );
		if ( is_wp_error( $sezioni ) ) {
			return $sezioni;
		}
		if ( self::anteprima_nome_taken( $data, $nome, $id ) ) {
			return self::error( 'catalog_anteprima_exists', 'Esiste già un\'anteprima con questo nome.', 409 );
		}
		if ( '' === $id ) {
			$data['anteprime'][] = array(
				'id'       => self::new_anteprima_id( $data ),
				'nome'     => $nome,
				'sezioni'  => $sezioni,
				'generato' => false,
				'fileHtml' => '',
				'pathHtml' => '',
				'filePdf'  => '',
				'pathPdf'  => '',
			);
		} else {
			$index = self::anteprima_index( $data, $id );
			if ( $index < 0 ) {
				return self::error( 'catalog_anteprima_missing', 'Anteprima non trovata.', 404 );
			}
			$data['anteprime'][ $index ]['nome']    = $nome;
			$data['anteprime'][ $index ]['sezioni'] = $sezioni;
		}
		$written = self::write( $loaded['path'], $data );
		if ( is_wp_error( $written ) ) {
			return $written;
		}
		return self::anteprima_state( $data );
	}

	/**
	 * @return array{path: string, data: array<string, mixed>}|WP_Error
	 */
	private static function catalog_for_write( string $code ) {
		$path = self::catalog_path( $code );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( ! is_file( $path ) ) {
			return self::error( 'catalog_not_found', 'Catalogo non trovato.', 404 );
		}
		$data = self::read( $path );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$data = self::with_anteprime( $path, $data );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$data = self::with_pdfs( $path, $data );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return array(
			'path' => $path,
			'data' => $data,
		);
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array{anteprime: array<int, array<string, mixed>>, anteprimaCorrente: string}
	 */
	private static function anteprima_state( array $data ): array {
		return array(
			'anteprime'          => array_values( $data['anteprime'] ?? array() ),
			'anteprimaCorrente'  => (string) ( $data['anteprimaCorrente'] ?? '' ),
		);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function anteprima_index( array $data, string $id ): int {
		foreach ( $data['anteprime'] ?? array() as $index => $item ) {
			if ( is_array( $item ) && (string) ( $item['id'] ?? '' ) === $id ) {
				return (int) $index;
			}
		}
		return -1;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>|null
	 */
	private static function anteprima_by_id( array $data, string $id ): ?array {
		$index = self::anteprima_index( $data, $id );
		if ( $index < 0 ) {
			return null;
		}
		$item = $data['anteprime'][ $index ];
		return is_array( $item ) ? $item : null;
	}

	/**
	 * @param array<int, mixed> $list
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalize_anteprime_list( array $list ): array {
		$items = array();
		foreach ( $list as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$id = (string) ( $item['id'] ?? '' );
			if ( ! preg_match( '/^[a-zA-Z0-9_-]{1,40}$/', $id ) ) {
				continue;
			}
			$items[] = array(
				'id'       => $id,
				'nome'     => (string) ( $item['nome'] ?? '' ),
				'sezioni'  => self::normalize_section_refs( $item['sezioni'] ?? array() ),
				'generato' => ! empty( $item['generato'] ),
				'fileHtml' => (string) ( $item['fileHtml'] ?? '' ),
				'pathHtml' => (string) ( $item['pathHtml'] ?? '' ),
				'filePdf'  => (string) ( $item['filePdf'] ?? '' ),
				'pathPdf'  => (string) ( $item['pathPdf'] ?? '' ),
			);
		}
		return $items;
	}

	/**
	 * @param mixed $sezioni
	 * @return array<int, array{nome: string, posizione: int}>
	 */
	private static function normalize_section_refs( $sezioni ): array {
		if ( ! is_array( $sezioni ) ) {
			return array();
		}
		$refs = array();
		foreach ( $sezioni as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$refs[] = array(
				'nome'      => (string) ( $item['nome'] ?? '' ),
				'posizione' => (int) ( $item['posizione'] ?? 0 ),
			);
		}
		return $refs;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function valid_current_id( array $data ): string {
		$id = (string) ( $data['anteprimaCorrente'] ?? '' );
		return self::anteprima_index( $data, $id ) >= 0 ? $id : '';
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<int, array{nome: string, posizione: int}>
	 */
	private static function sections_from_catalog( array $data ): array {
		$refs = array();
		foreach ( $data['prodotti'] ?? array() as $index => $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			$refs[] = array(
				'nome'      => (string) ( $section['nome'] ?? '' ),
				'posizione' => (int) $index,
			);
		}
		return $refs;
	}

	/**
	 * @return string|WP_Error
	 */
	private static function valid_anteprima_nome( string $nome ) {
		$nome = trim( sanitize_text_field( $nome ) );
		if ( '' === $nome ) {
			return self::error( 'catalog_anteprima_invalid', 'Il nome dell\'anteprima è obbligatorio.', 422 );
		}
		if ( strlen( $nome ) > 80 ) {
			return self::error( 'catalog_anteprima_invalid', 'Il nome dell\'anteprima può avere al massimo 80 caratteri.', 422 );
		}
		return $nome;
	}

	/**
	 * @param array<int, mixed> $sezioni
	 * @return array<int, array{nome: string, posizione: int}>|WP_Error
	 */
	private static function valid_anteprima_sezioni( array $sezioni ) {
		$refs = self::normalize_section_refs( $sezioni );
		$refs = array_values( array_filter( $refs, static fn( array $item ): bool => '' !== $item['nome'] ) );
		if ( ! $refs ) {
			return self::error( 'catalog_anteprima_invalid', 'Seleziona almeno una sezione.', 422 );
		}
		return $refs;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function anteprima_nome_taken( array $data, string $nome, string $except_id ): bool {
		$wanted = mb_strtolower( $nome );
		foreach ( $data['anteprime'] ?? array() as $item ) {
			if ( ! is_array( $item ) || (string) ( $item['id'] ?? '' ) === $except_id ) {
				continue;
			}
			if ( mb_strtolower( (string) ( $item['nome'] ?? '' ) ) === $wanted ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function new_anteprima_id( array $data ): string {
		do {
			$id = substr( bin2hex( random_bytes( 4 ) ), 0, 8 );
		} while ( self::anteprima_index( $data, $id ) >= 0 );
		return $id;
	}

	/**
	 * @param array<string, mixed> $item
	 * @return string|WP_Error
	 */
	private static function resolve_html_path( string $code, array $item ) {
		$file = (string) ( $item['fileHtml'] ?? '' );
		if ( '' !== $file && preg_match( '/^[a-zA-Z0-9_.-]+$/', $file ) ) {
			$base = self::anteprima_file_path( $code );
			if ( is_wp_error( $base ) ) {
				return $base;
			}
			return trailingslashit( dirname( $base ) ) . $file;
		}
		return self::anteprima_file_path( $code, (string) ( $item['id'] ?? '' ) );
	}

	/**
	 * @param array<string, mixed> $item
	 * @return string|WP_Error
	 */
	private static function resolve_pdf_path( string $code, array $item ) {
		$file = (string) ( $item['filePdf'] ?? '' );
		if ( '' !== $file && preg_match( '/^[a-zA-Z0-9_.-]+$/', $file ) ) {
			$base = self::pdf_file_path( $code );
			if ( is_wp_error( $base ) ) {
				return $base;
			}
			return trailingslashit( dirname( $base ) ) . $file;
		}
		return self::pdf_file_path( $code, (string) ( $item['id'] ?? '' ) );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function unlink_catalog_files( string $code, array $data ): void {
		foreach ( $data['anteprime'] ?? array() as $item ) {
			if ( is_array( $item ) ) {
				self::unlink_anteprima_files( $code, $item );
			}
		}
		foreach ( $data['pdfs'] ?? array() as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$path = self::resolve_pdf_path( $code, $item );
			if ( ! is_wp_error( $path ) && is_file( $path ) ) {
				unlink( $path );
			}
		}
		foreach ( array( self::anteprima_file_path( $code ), self::pdf_file_path( $code ) ) as $path ) {
			if ( ! is_wp_error( $path ) && is_file( $path ) ) {
				unlink( $path );
			}
		}
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function unlink_anteprima_files( string $code, array $item ): void {
		$path = self::resolve_html_path( $code, $item );
		if ( ! is_wp_error( $path ) && is_file( $path ) ) {
			unlink( $path );
		}
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>|WP_Error
	 */
	private static function with_pdfs( string $catalog_path, array $data ) {
		if ( isset( $data['pdfs'] ) && is_array( $data['pdfs'] ) ) {
			$data['pdfs']        = self::normalize_pdf_list( $data['pdfs'] );
			$data['pdfCorrente'] = self::valid_pdf_current_id( $data );
			return $data;
		}
		$data['pdfs']        = array();
		$data['pdfCorrente'] = '';
		$seen                = array();
		foreach ( $data['anteprime'] ?? array() as $item ) {
			if ( ! is_array( $item ) || '' === (string) ( $item['filePdf'] ?? '' ) ) {
				continue;
			}
			$anteprima_id = (string) ( $item['id'] ?? '' );
			$nome         = (string) ( $item['nome'] ?? 'PDF' );
			$base         = $nome;
			$suffix       = 2;
			while ( isset( $seen[ mb_strtolower( $nome ) ] ) ) {
				$nome = $base . ' ' . $suffix;
				$suffix++;
			}
			$seen[ mb_strtolower( $nome ) ] = true;
			$data['pdfs'][] = array(
				'id'            => '' !== $anteprima_id ? $anteprima_id : self::new_pdf_id( $data ),
				'nome'          => $nome,
				'anteprimaId'   => $anteprima_id,
				'generato'      => true,
				'filePdf'       => (string) $item['filePdf'],
				'pathPdf'       => (string) ( $item['pathPdf'] ?? '' ),
				'usaCopertina'  => false,
				'usaRetro'      => false,
			);
		}
		$current = (string) ( $data['anteprimaCorrente'] ?? '' );
		foreach ( $data['pdfs'] as $pdf ) {
			if ( (string) ( $pdf['anteprimaId'] ?? '' ) === $current ) {
				$data['pdfCorrente'] = (string) $pdf['id'];
				break;
			}
		}
		if ( '' === $data['pdfCorrente'] && $data['pdfs'] ) {
			$data['pdfCorrente'] = (string) $data['pdfs'][0]['id'];
		}
		$written = self::write( $catalog_path, $data );
		if ( is_wp_error( $written ) ) {
			return $written;
		}
		return $data;
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function save_pdf_record( string $code, string $id, string $nome, string $anteprima_id ) {
		$loaded = self::catalog_for_write( $code );
		if ( is_wp_error( $loaded ) ) {
			return $loaded;
		}
		$data = $loaded['data'];
		$nome = self::valid_pdf_nome( $nome );
		if ( is_wp_error( $nome ) ) {
			return $nome;
		}
		$anteprima_id = trim( $anteprima_id );
		if ( self::anteprima_index( $data, $anteprima_id ) < 0 ) {
			return self::error( 'catalog_pdf_invalid', 'Seleziona un\'anteprima del catalogo.', 422 );
		}
		if ( self::pdf_nome_taken( $data, $nome, $id ) ) {
			return self::error( 'catalog_pdf_exists', 'Esiste già un PDF con questo nome.', 409 );
		}
		if ( '' === $id ) {
			$data['pdfs'][] = array(
				'id'           => self::new_pdf_id( $data ),
				'nome'         => $nome,
				'anteprimaId'  => $anteprima_id,
				'generato'     => false,
				'filePdf'      => '',
				'pathPdf'      => '',
				'usaCopertina' => false,
				'usaRetro'     => false,
			);
		} else {
			$index = self::pdf_index( $data, $id );
			if ( $index < 0 ) {
				return self::error( 'catalog_pdf_missing', 'PDF non trovato.', 404 );
			}
			$data['pdfs'][ $index ]['nome']        = $nome;
			$data['pdfs'][ $index ]['anteprimaId'] = $anteprima_id;
		}
		$written = self::write( $loaded['path'], $data );
		if ( is_wp_error( $written ) ) {
			return $written;
		}
		return self::pdf_state( $data );
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array{pdfs: array<int, array<string, mixed>>, pdfCorrente: string}
	 */
	private static function pdf_state( array $data ): array {
		return array(
			'pdfs'        => array_values( $data['pdfs'] ?? array() ),
			'pdfCorrente' => (string) ( $data['pdfCorrente'] ?? '' ),
		);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function pdf_index( array $data, string $id ): int {
		foreach ( $data['pdfs'] ?? array() as $index => $item ) {
			if ( is_array( $item ) && (string) ( $item['id'] ?? '' ) === $id ) {
				return (int) $index;
			}
		}
		return -1;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>|null
	 */
	private static function pdf_by_id( array $data, string $id ): ?array {
		$index = self::pdf_index( $data, $id );
		if ( $index < 0 ) {
			return null;
		}
		$item = $data['pdfs'][ $index ];
		return is_array( $item ) ? $item : null;
	}

	/**
	 * @param array<int, mixed> $list
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalize_pdf_list( array $list ): array {
		$items = array();
		foreach ( $list as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$id = (string) ( $item['id'] ?? '' );
			if ( ! preg_match( '/^[a-zA-Z0-9_-]{1,40}$/', $id ) ) {
				continue;
			}
			$items[] = array(
				'id'           => $id,
				'nome'         => (string) ( $item['nome'] ?? '' ),
				'anteprimaId'  => (string) ( $item['anteprimaId'] ?? '' ),
				'generato'     => ! empty( $item['generato'] ),
				'filePdf'      => (string) ( $item['filePdf'] ?? '' ),
				'pathPdf'      => (string) ( $item['pathPdf'] ?? '' ),
				'usaCopertina' => ! empty( $item['usaCopertina'] ),
				'usaRetro'     => ! empty( $item['usaRetro'] ),
			);
		}
		return $items;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function valid_pdf_current_id( array $data ): string {
		$id = (string) ( $data['pdfCorrente'] ?? '' );
		return self::pdf_index( $data, $id ) >= 0 ? $id : '';
	}

	/**
	 * @return string|WP_Error
	 */
	private static function valid_pdf_nome( string $nome ) {
		$nome = trim( sanitize_text_field( $nome ) );
		if ( '' === $nome ) {
			return self::error( 'catalog_pdf_invalid', 'Il nome del PDF è obbligatorio.', 422 );
		}
		if ( strlen( $nome ) > 80 ) {
			return self::error( 'catalog_pdf_invalid', 'Il nome del PDF può avere al massimo 80 caratteri.', 422 );
		}
		return $nome;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function pdf_nome_taken( array $data, string $nome, string $except_id ): bool {
		$wanted = mb_strtolower( $nome );
		foreach ( $data['pdfs'] ?? array() as $item ) {
			if ( ! is_array( $item ) || (string) ( $item['id'] ?? '' ) === $except_id ) {
				continue;
			}
			if ( mb_strtolower( (string) ( $item['nome'] ?? '' ) ) === $wanted ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function new_pdf_id( array $data ): string {
		do {
			$id = substr( bin2hex( random_bytes( 4 ) ), 0, 8 );
		} while ( self::pdf_index( $data, $id ) >= 0 );
		return $id;
	}

	private static function anteprima_storage_id( string $id ): string {
		return 'legacy' === $id ? '' : $id;
	}

	private static function anteprima_file_path( string $code, string $id = '' ) {
		$code = self::valid_code( $code );
		if ( is_wp_error( $code ) ) {
			return $code;
		}
		$directory = self::directory();
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		$html_dir = trailingslashit( dirname( $directory ) ) . 'cataloghi_html';
		if ( ! is_dir( $html_dir ) && ! wp_mkdir_p( $html_dir ) ) {
			return self::error( 'catalog_storage_unavailable', 'Impossibile creare la cartella delle anteprime.', 500 );
		}
		self::protect_directory( $html_dir );
		$filename = '' === $id ? $code . '.html' : $code . '--' . $id . '.html';
		return trailingslashit( $html_dir ) . $filename;
	}

	private static function pdf_file_path( string $code, string $id = '' ) {
		$code = self::valid_code( $code );
		if ( is_wp_error( $code ) ) {
			return $code;
		}
		$directory = self::directory();
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		$pdf_dir = trailingslashit( dirname( $directory ) ) . 'cataloghi_pdf';
		if ( ! is_dir( $pdf_dir ) && ! wp_mkdir_p( $pdf_dir ) ) {
			return self::error( 'catalog_storage_unavailable', 'Impossibile creare la cartella dei PDF.', 500 );
		}
		self::protect_directory( $pdf_dir );
		$filename = '' === $id ? $code . '.pdf' : $code . '--' . $id . '.pdf';
		return trailingslashit( $pdf_dir ) . $filename;
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
