<?php
/**
 * Plugin Name: fantAdminApi
 * Description: API REST sicure per la dashboard amministrativa WooCommerce fantShopAdmin.
 * Version: 0.1.5
 * Requires at least: 6.9
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Text Domain: fant-admin-api
 */

defined( 'ABSPATH' ) || exit;

define( 'FANT_ADMIN_API_VERSION', '0.1.5' );
define( 'FANT_ADMIN_API_FILE', __FILE__ );
define( 'FANT_ADMIN_API_PATH', plugin_dir_path( __FILE__ ) );

require_once FANT_ADMIN_API_PATH . 'includes/class-faa-install.php';
require_once FANT_ADMIN_API_PATH . 'includes/class-faa-auth.php';
require_once FANT_ADMIN_API_PATH . 'includes/class-faa-api.php';

add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				FANT_ADMIN_API_FILE,
				true
			);
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		Fant_Admin_API_Auth::init();
		Fant_Admin_API_REST::init();
	}
);
