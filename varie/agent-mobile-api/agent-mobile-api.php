<?php
/**
 * Plugin Name: Agent Mobile API
 * Description: API REST per l'app mobile degli agenti WooCommerce.
 * Version: 0.1.3
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'AMA_VERSION', '0.1.3' );
define( 'AMA_FILE', __FILE__ );
define( 'AMA_PATH', plugin_dir_path( __FILE__ ) );

require_once AMA_PATH . 'includes/class-ama-install.php';
require_once AMA_PATH . 'includes/class-ama-auth.php';
require_once AMA_PATH . 'includes/class-ama-api.php';

register_activation_hook( AMA_FILE, array( 'AMA_Install', 'activate' ) );

add_action( 'before_woocommerce_init', static function () {
    if ( class_exists( Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            AMA_FILE,
            true
        );
    }
} );

add_action( 'plugins_loaded', static function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    AMA_Auth::init();
    AMA_API::init();
} );
