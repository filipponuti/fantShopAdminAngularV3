<?php
/*
Plugin Name: Fantini Cataloghi
Description: Fantini Cataloghi Plugin 
Author: Fantini 
Version: 2.0.0
*/

// If this file is calles directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/** Ajax route. */
add_action( 'wp_ajax_cataloghi_save_cfg', 'cataloghi_salva_cfg' );
add_action( 'wp_ajax_nopriv_cataloghi_save_cfg', 'cataloghi_salva_cfg' );

/** Ajax callback. */
function cataloghi_salva_cfg() {
  /** Dump $_REQUEST as JSON. */
  require_once __DIR__ . '/adminfe/setting.php';
  rest_salva_ini($_REQUEST );
  //echo json_encode( $_REQUEST );
  wp_die();
}


function fantini_load_catalogo( $atts = [], $content = null, $tag = '' ) {
    ob_start();
    global $pagenow;
    //tipo user
    $tipo = '';
    if (array_key_exists('tipo', $atts)) {	
        $tipo = trim('' . $atts['tipo']);
    }
    if ($tipo == '' ) $tipo = 'Guest';
    //Admin
    if ($tipo == 'Admin') {
        require_once __DIR__ . '/adminfe/setting.php';
        require_once __DIR__ . '/adminfe/adminfe.php';
        renderPageAdmin($atts);
    }
    //Guest
    if ($tipo == 'Guest') {
        require_once __DIR__ . '/adminfe/setting.php';
        require_once __DIR__ . '/cataloghi/catalogo.php';
        renderPage($atts);
    }
    return ob_get_clean();
}

/*
 * Central location to create all shortcodes.
 */
add_action( 'init', 'fantini_cataloghi_init' );
function fantini_cataloghi_init() {
    add_shortcode( 'fantcatalogo', 'fantini_load_catalogo' );
}

//ANTEPRIMA
function fantini_load_catalogo_anteprima( $atts = [], $content = null, $tag = '' ) {
    ob_start();
    global $pagenow;
    //tipo user
    require_once __DIR__ . '/adminfe/setting.php';
    require_once __DIR__ . '/cataloghi/catalogo-anteprima.php';
    renderPageAnteprima($atts);
    return ob_get_clean();
}

/*
 * Central location to create all shortcodes.
 */
add_action( 'init', 'fantini_cataloghi_anteprima_init' );
function fantini_cataloghi_anteprima_init() {
    add_shortcode( 'fantcatalogoanteprima', 'fantini_load_catalogo_anteprima' );
}