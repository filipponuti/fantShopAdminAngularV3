<?php

defined( 'ABSPATH' ) || exit;

final class Fant_Admin_API_V4_Layouts {
	private const SCHEMA_VERSION = 1;

	/** @return array<int, array<string, mixed>> */
	private static function seed_definitions(): array {
		return array(
			array(
				'codice'               => 'articolo-semplice',
				'nome'                 => 'Articolo Semplice',
				'family'               => 'semplice',
				'showVariationImages'  => false,
				'bancali'              => false,
			),
			array(
				'codice'               => 'articolo-variazioni-no-img',
				'nome'                 => 'Articolo Con Variazioni Senza immagini',
				'family'               => 'variazioni',
				'showVariationImages'  => false,
				'bancali'              => false,
			),
			array(
				'codice'               => 'articolo-variazioni-img',
				'nome'                 => 'Articolo Con Variazioni Con immagini',
				'family'               => 'variazioni',
				'showVariationImages'  => true,
				'bancali'              => false,
			),
			array(
				'codice'               => 'nutrizione-sfuso',
				'nome'                 => 'Nutrizione Sfuso',
				'family'               => 'nutrizione',
				'showVariationImages'  => false,
				'bancali'              => false,
			),
			array(
				'codice'               => 'nutrizione-bancali',
				'nome'                 => 'Nutrizione con bancali',
				'family'               => 'nutrizione',
				'showVariationImages'  => false,
				'bancali'              => true,
			),
		);
	}

	public static function all() {
		$seeded = self::ensure_seeded();
		if ( is_wp_error( $seeded ) ) {
			return $seeded;
		}

		$directory = self::directory();
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}

		$layouts = array();
		foreach ( self::seed_definitions() as $definition ) {
			$path = trailingslashit( $directory ) . $definition['codice'] . '.json';
			$data = self::read( $path );
			if ( is_wp_error( $data ) ) {
				return $data;
			}
			$layouts[] = self::summary( $data );
		}

		return $layouts;
	}

	public static function find( string $code ) {
		$code = self::valid_code( $code );
		if ( is_wp_error( $code ) ) {
			return $code;
		}

		$seeded = self::ensure_seeded();
		if ( is_wp_error( $seeded ) ) {
			return $seeded;
		}

		$path = self::json_path( $code );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( ! is_file( $path ) ) {
			return self::error( 'layout_not_found', 'Layout articolo non trovato.', 404 );
		}

		return self::read( $path );
	}

	/**
	 * Aggiorna campi editabili del layout (htmlPreview, notes).
	 *
	 * @param string               $code
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>|WP_Error
	 */
	public static function update( string $code, array $params ) {
		$code = self::valid_code( $code );
		if ( is_wp_error( $code ) ) {
			return $code;
		}

		$data = self::find( $code );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$layout = is_array( $data['layout'] ?? null ) ? $data['layout'] : array();

		if ( array_key_exists( 'htmlPreview', $params ) ) {
			$html = $params['htmlPreview'];
			if ( null === $html ) {
				$layout['htmlPreview'] = null;
			} elseif ( is_string( $html ) ) {
				$layout['htmlPreview'] = self::sanitize_layout_html( $html );
			} else {
				return self::error( 'layout_invalid_html', 'htmlPreview deve essere una stringa HTML.', 422 );
			}
		}

		if ( array_key_exists( 'notes', $params ) ) {
			$layout['notes'] = sanitize_textarea_field( (string) $params['notes'] );
		}

		$data['layout']              = $layout;
		$data['testata']['updatedAt'] = gmdate( 'c' );

		$path = self::json_path( $code );
		if ( is_wp_error( $path ) ) {
			return $path;
		}

		$result = self::write( $path, $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $data;
	}

	/** Sanitizza HTML template layout (solo admin): rimuove script/iframe. */
	private static function sanitize_layout_html( string $html ): string {
		$html = wp_unslash( $html );
		$html = preg_replace( '#<\s*(script|iframe|object|embed|form)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html ) ?? $html;
		$html = preg_replace( '#<\s*(script|iframe|object|embed|form)[^>]*/?\s*>#is', '', $html ) ?? $html;
		$html = preg_replace( '#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html ) ?? $html;
		return $html;
	}

	private static function ensure_seeded() {
		$directory = self::directory();
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}

		foreach ( self::seed_definitions() as $definition ) {
			$path = trailingslashit( $directory ) . $definition['codice'] . '.json';
			if ( is_file( $path ) ) {
				continue;
			}
			$now  = gmdate( 'c' );
			$data = array(
				'schemaVersion' => self::SCHEMA_VERSION,
				'testata'       => array(
					'codice'    => $definition['codice'],
					'nome'      => $definition['nome'],
					'createdAt' => $now,
					'updatedAt' => $now,
				),
				'layout'        => array(
					'family'              => $definition['family'],
					'showVariationImages' => ! empty( $definition['showVariationImages'] ),
					'bancali'             => ! empty( $definition['bancali'] ),
					'cssClass'            => '',
					'labels'              => array(
						'confezione' => 'Confezione da {n} pz.',
						'set'        => 'Set da {n} pz.',
					),
					'htmlPreview'         => null,
					'notes'               => '',
				),
			);
			$result = self::write( $path, $data );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	private static function summary( array $data ): array {
		$header = is_array( $data['testata'] ?? null ) ? $data['testata'] : array();
		$layout = is_array( $data['layout'] ?? null ) ? $data['layout'] : array();
		return array(
			'codice'              => (string) ( $header['codice'] ?? '' ),
			'nome'                => (string) ( $header['nome'] ?? '' ),
			'family'              => (string) ( $layout['family'] ?? '' ),
			'showVariationImages' => ! empty( $layout['showVariationImages'] ),
			'bancali'             => ! empty( $layout['bancali'] ),
			'updatedAt'           => (string) ( $header['updatedAt'] ?? '' ),
		);
	}

	private static function directory() {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return self::error( 'layout_storage_unavailable', (string) $uploads['error'], 500 );
		}

		$path = trailingslashit( $uploads['basedir'] ) . 'fant-admin-api/layout-articoli';
		if ( ! is_dir( $path ) && ! wp_mkdir_p( $path ) ) {
			return self::error( 'layout_storage_unavailable', 'Impossibile creare la cartella layout-articoli.', 500 );
		}

		self::protect_directory( $path );
		return $path;
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

	private static function json_path( string $code ) {
		$code      = self::valid_code( $code );
		$directory = self::directory();
		if ( is_wp_error( $code ) || is_wp_error( $directory ) ) {
			return is_wp_error( $code ) ? $code : $directory;
		}
		return trailingslashit( $directory ) . $code . '.json';
	}

	private static function valid_code( string $code ) {
		$code = strtolower( trim( $code ) );
		return preg_match( '/^[a-z0-9][a-z0-9_-]{0,79}$/', $code )
			? $code
			: self::error( 'invalid_layout_code', 'Codice layout non valido.', 422 );
	}

	private static function read( string $path ) {
		$raw = file_get_contents( $path );
		if ( false === $raw ) {
			return self::error( 'layout_read_failed', 'Impossibile leggere il layout.', 500 );
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return self::error( 'layout_invalid_json', 'File layout non valido.', 500 );
		}
		return $data;
	}

	private static function write( string $path, array $data ) {
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return self::error( 'layout_write_failed', 'Impossibile serializzare il layout.', 500 );
		}

		$directory = dirname( $path );
		$temp      = tempnam( $directory, '.faa-' );
		if ( false === $temp ) {
			return self::error( 'layout_write_failed', 'Impossibile creare il file temporaneo del layout.', 500 );
		}

		$written = file_put_contents( $temp, $json . "\n", LOCK_EX );
		if ( false === $written || ! rename( $temp, $path ) ) {
			@unlink( $temp );
			return self::error( 'layout_write_failed', 'Impossibile salvare il layout.', 500 );
		}
		@chmod( $path, 0640 );

		return true;
	}

	private static function error( string $code, string $message, int $status ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
