<?php
/**
 * Autoload minimo per Dompdf e le librerie usate dal PDF catalogo.
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( string $class ): void {
		$roots = array(
			'Dompdf\\'         => __DIR__ . '/dompdf/src/',
			'FontLib\\'        => __DIR__ . '/php-font-lib/src/FontLib/',
			'Svg\\'            => __DIR__ . '/php-svg-lib/src/Svg/',
			'Masterminds\\'    => __DIR__ . '/html5/src/',
			'Sabberworm\\CSS\\' => __DIR__ . '/php-css-parser/src/',
		);

		if ( 'Dompdf\\Cpdf' === $class ) {
			require_once __DIR__ . '/dompdf/lib/Cpdf.php';
			return;
		}

		foreach ( $roots as $prefix => $root ) {
			if ( ! str_starts_with( $class, $prefix ) ) {
				continue;
			}
			$relative = substr( $class, strlen( $prefix ) );
			$file     = $root . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_file( $file ) ) {
				require_once $file;
			}
			return;
		}
	}
);
