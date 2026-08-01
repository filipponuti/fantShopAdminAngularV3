<?php
/**
 * Plugin Name: fantAdminApi
 * Description: API REST sicure per la dashboard amministrativa WooCommerce fantShopAdmin.
 * Version: 0.5.2
 * Requires at least: 6.9
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Text Domain: fant-admin-api
 */

defined( 'ABSPATH' ) || exit;

define( 'FANT_ADMIN_API_V4_VERSION', '0.5.2' );
define( 'FANT_ADMIN_API_V4_FILE', __FILE__ );
define( 'FANT_ADMIN_API_V4_PATH', plugin_dir_path( __FILE__ ) );

require_once FANT_ADMIN_API_V4_PATH . 'includes/class-faa-install.php';
require_once FANT_ADMIN_API_V4_PATH . 'includes/class-faa-auth.php';
require_once FANT_ADMIN_API_V4_PATH . 'includes/class-faa-catalogs.php';
require_once FANT_ADMIN_API_V4_PATH . 'includes/class-faa-covers.php';
require_once FANT_ADMIN_API_V4_PATH . 'includes/class-faa-ai-settings.php';
require_once FANT_ADMIN_API_V4_PATH . 'includes/class-faa-api.php';

/**
 * Rimuove esclusivamente le vecchie cartelle fant-admin-api lasciate da
 * precedenti pacchetti, mantenendo intatta la directory della versione attiva.
 */
function fant_admin_api_v4_remove_legacy_copies(): void {
	$current = realpath( FANT_ADMIN_API_V4_PATH );
	$root    = realpath( WP_PLUGIN_DIR );
	if ( false === $current || false === $root ) {
		return;
	}

	foreach ( glob( trailingslashit( $root ) . 'fant-admin-api*', GLOB_ONLYDIR ) ?: array() as $directory ) {
		$target = realpath( $directory );
		if ( false === $target || $target === $current || ! str_starts_with( $target, $root . DIRECTORY_SEPARATOR ) ) {
			continue;
		}
		if ( ! preg_match( '/^fant-admin-api(?:-|$)/', basename( $target ) ) ) {
			continue;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $target, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			if ( $item->isDir() && ! $item->isLink() ) {
				@rmdir( $item->getPathname() );
			} else {
				@unlink( $item->getPathname() );
			}
		}
		@rmdir( $target );
	}
}

register_activation_hook( FANT_ADMIN_API_V4_FILE, 'fant_admin_api_v4_remove_legacy_copies' );

$faa_diagnostic_file = WP_CONTENT_DIR . '/uploads/faa-activation-error.json';
if ( is_file( $faa_diagnostic_file ) ) {
	@unlink( $faa_diagnostic_file );
}

add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				FANT_ADMIN_API_V4_FILE,
				true
			);
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		Fant_Admin_API_V4_Auth::init();
		Fant_Admin_API_V4_REST::init();
	}
);
