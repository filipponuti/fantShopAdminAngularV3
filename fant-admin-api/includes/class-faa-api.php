<?php

defined( 'ABSPATH' ) || exit;

final class Fant_Admin_API_REST {
	private const API_NAMESPACE = 'fant-admin/v1';
	private const MAX_LOGIN_ATTEMPTS = 5;
	private const LOGIN_WINDOW = 900;

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes(): void {
		self::route( '/status', WP_REST_Server::READABLE, 'status', '__return_true' );
		self::route( '/auth/login', WP_REST_Server::CREATABLE, 'login', '__return_true' );
		self::route( '/auth/refresh', WP_REST_Server::CREATABLE, 'refresh', '__return_true' );
		self::route( '/auth/logout', WP_REST_Server::CREATABLE, 'logout', '__return_true' );
		self::route( '/me', WP_REST_Server::READABLE, 'me' );

		register_rest_route(
			self::API_NAMESPACE,
			'/categories',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'categories' ),
					'permission_callback' => array( __CLASS__, 'authorized' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_category' ),
					'permission_callback' => array( __CLASS__, 'authorized' ),
				),
			)
		);
		self::route( '/categories/reorder', 'PUT', 'reorder_categories' );
		self::route( '/categories/(?P<categoryId>\d+)', 'PUT', 'update_category' );
		self::route( '/categories/(?P<categoryId>\d+)', WP_REST_Server::DELETABLE, 'delete_category' );

		register_rest_route(
			self::API_NAMESPACE,
			'/catalogs',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'catalogs' ),
					'permission_callback' => array( __CLASS__, 'authorized' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_catalog' ),
					'permission_callback' => array( __CLASS__, 'authorized' ),
				),
			)
		);
		self::route( '/catalogs/(?P<catalogCode>[a-zA-Z0-9_-]+)', WP_REST_Server::READABLE, 'catalog' );
		self::route( '/catalogs/(?P<catalogCode>[a-zA-Z0-9_-]+)', 'PUT', 'update_catalog' );
		self::route( '/catalogs/(?P<catalogCode>[a-zA-Z0-9_-]+)', WP_REST_Server::DELETABLE, 'delete_catalog' );
	}

	private static function route( string $path, string $methods, string $callback, $permission = null ): void {
		register_rest_route(
			self::API_NAMESPACE,
			$path,
			array(
				'methods'             => $methods,
				'callback'            => array( __CLASS__, $callback ),
				'permission_callback' => $permission ?: array( __CLASS__, 'authorized' ),
			)
		);
	}

	public static function status(): WP_REST_Response {
		$hpos_enabled = false;
		if ( class_exists( Automattic\WooCommerce\Utilities\OrderUtil::class ) ) {
			$hpos_enabled = Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}

		return new WP_REST_Response(
			array(
				'name'        => 'fantAdminApi',
				'version'     => FANT_ADMIN_API_VERSION,
				'woocommerce' => defined( 'WC_VERSION' ) ? WC_VERSION : null,
				'hposEnabled' => $hpos_enabled,
			),
			200
		);
	}

	public static function authorized() {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return self::error( 'unauthorized', 'Token assente, non valido o scaduto.', 401 );
		}

		if ( ! Fant_Admin_API_Auth::is_administrator( $user ) ) {
			return self::error( 'administrator_required', 'Accesso riservato agli amministratori.', 403 );
		}

		return true;
	}

	public static function login( WP_REST_Request $request ) {
		Fant_Admin_API_Install::ensure_installed();

		$login       = trim( sanitize_text_field( (string) $request->get_param( 'login' ) ) );
		$password    = (string) $request->get_param( 'password' );
		$device_id   = sanitize_text_field( (string) $request->get_param( 'deviceId' ) );
		$device_name = sanitize_text_field( (string) $request->get_param( 'deviceName' ) );

		if ( '' === $login || '' === $password ) {
			return self::error( 'invalid_login_request', 'Username/email e password sono obbligatori.', 422 );
		}

		$rate_key = self::rate_limit_key( $login );
		$attempts = (int) get_transient( $rate_key );
		if ( $attempts >= self::MAX_LOGIN_ATTEMPTS ) {
			return self::error( 'too_many_attempts', 'Troppi tentativi. Riprova tra alcuni minuti.', 429 );
		}

		$user = wp_authenticate( $login, $password );
		if ( is_wp_error( $user ) ) {
			set_transient( $rate_key, $attempts + 1, self::LOGIN_WINDOW );
			return self::error( 'invalid_credentials', 'Credenziali non valide.', 401 );
		}

		if ( ! Fant_Admin_API_Auth::is_administrator( $user ) ) {
			set_transient( $rate_key, $attempts + 1, self::LOGIN_WINDOW );
			return self::error( 'administrator_required', 'Accesso riservato agli amministratori.', 403 );
		}

		delete_transient( $rate_key );

		try {
			$tokens = Fant_Admin_API_Auth::issue( (int) $user->ID, $device_id, $device_name );
		} catch ( Throwable $exception ) {
			return self::error( 'session_error', 'Impossibile creare la sessione.', 500 );
		}

		return rest_ensure_response( array_merge( $tokens, array( 'user' => self::user_data( $user ) ) ) );
	}

	public static function refresh( WP_REST_Request $request ) {
		$refresh_token = (string) $request->get_param( 'refreshToken' );
		$user          = '' !== $refresh_token ? Fant_Admin_API_Auth::user_for_refresh_token( $refresh_token ) : null;

		if ( ! $user ) {
			return self::error( 'invalid_refresh_token', 'Refresh token non valido o scaduto.', 401 );
		}

		if ( ! Fant_Admin_API_Auth::is_administrator( $user ) ) {
			return self::error( 'administrator_required', 'Accesso riservato agli amministratori.', 403 );
		}

		try {
			$tokens = Fant_Admin_API_Auth::rotate( $refresh_token );
		} catch ( Throwable $exception ) {
			$tokens = null;
		}

		if ( ! $tokens ) {
			return self::error( 'invalid_refresh_token', 'Refresh token non valido o scaduto.', 401 );
		}

		return rest_ensure_response( array_merge( $tokens, array( 'user' => self::user_data( $user ) ) ) );
	}

	public static function logout( WP_REST_Request $request ): WP_REST_Response {
		$refresh_token = (string) $request->get_param( 'refreshToken' );
		if ( '' !== $refresh_token ) {
			Fant_Admin_API_Auth::revoke( $refresh_token );
		}

		return new WP_REST_Response( null, 204 );
	}

	public static function me(): WP_REST_Response {
		return new WP_REST_Response( self::user_data( wp_get_current_user() ), 200 );
	}

	public static function categories() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return self::error( 'categories_error', 'Impossibile caricare le categorie.', 500 );
		}

		$data = array_map( array( __CLASS__, 'category_data' ), $terms );
		usort(
			$data,
			static function ( array $left, array $right ): int {
				$order = $left['menuOrder'] <=> $right['menuOrder'];
				return 0 !== $order ? $order : strcasecmp( $left['name'], $right['name'] );
			}
		);

		return rest_ensure_response( $data );
	}

	public static function create_category( WP_REST_Request $request ) {
		$name = sanitize_text_field( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) {
			return self::error( 'category_name_required', 'Il nome della categoria è obbligatorio.', 422 );
		}

		$parent = max( 0, (int) $request->get_param( 'parent' ) );
		if ( $parent && ! term_exists( $parent, 'product_cat' ) ) {
			return self::error( 'invalid_category_parent', 'La categoria padre non esiste.', 422 );
		}

		$args = array(
			'parent'      => $parent,
			'description' => sanitize_textarea_field( (string) $request->get_param( 'description' ) ),
		);
		$slug = sanitize_title( (string) $request->get_param( 'slug' ) );
		if ( '' !== $slug ) {
			$args['slug'] = $slug;
		}

		$result = wp_insert_term( $name, 'product_cat', $args );
		if ( is_wp_error( $result ) ) {
			return self::term_error( $result );
		}

		$term_id = (int) $result['term_id'];
		update_term_meta( $term_id, 'display_type', self::display_type( $request->get_param( 'display' ) ) );
		update_term_meta( $term_id, 'order', self::next_category_order( $parent ) );
		self::clear_category_cache( $term_id );

		return new WP_REST_Response( self::category_data( get_term( $term_id, 'product_cat' ) ), 201 );
	}

	public static function update_category( WP_REST_Request $request ) {
		$term_id = (int) $request['categoryId'];
		$term    = get_term( $term_id, 'product_cat' );
		if ( ! $term instanceof WP_Term ) {
			return self::error( 'category_not_found', 'Categoria non trovata.', 404 );
		}

		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();
		$args   = array();

		if ( array_key_exists( 'name', $params ) ) {
			$name = sanitize_text_field( (string) $params['name'] );
			if ( '' === $name ) {
				return self::error( 'category_name_required', 'Il nome della categoria è obbligatorio.', 422 );
			}
			$args['name'] = $name;
		}
		if ( array_key_exists( 'slug', $params ) ) {
			$args['slug'] = sanitize_title( (string) $params['slug'] );
		}
		if ( array_key_exists( 'description', $params ) ) {
			$args['description'] = sanitize_textarea_field( (string) $params['description'] );
		}
		if ( array_key_exists( 'parent', $params ) ) {
			$parent = max( 0, (int) $params['parent'] );
			if ( $parent === $term_id || ( $parent && ! term_exists( $parent, 'product_cat' ) ) ) {
				return self::error( 'invalid_category_parent', 'Categoria padre non valida.', 422 );
			}
			if ( self::is_category_descendant( $parent, $term_id ) ) {
				return self::error( 'category_cycle', 'Non è possibile creare una gerarchia circolare.', 422 );
			}
			$args['parent'] = $parent;
		}

		if ( $args ) {
			$result = wp_update_term( $term_id, 'product_cat', $args );
			if ( is_wp_error( $result ) ) {
				return self::term_error( $result );
			}
		}
		if ( array_key_exists( 'display', $params ) ) {
			update_term_meta( $term_id, 'display_type', self::display_type( $params['display'] ) );
		}

		self::clear_category_cache( $term_id );
		return rest_ensure_response( self::category_data( get_term( $term_id, 'product_cat' ) ) );
	}

	public static function delete_category( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$term_id = (int) $request['categoryId'];
		if ( (int) get_option( 'default_product_cat' ) === $term_id ) {
			return self::error( 'default_category', 'La categoria predefinita di WooCommerce non può essere eliminata.', 409 );
		}
		if ( ! term_exists( $term_id, 'product_cat' ) ) {
			return self::error( 'category_not_found', 'Categoria non trovata.', 404 );
		}

		$result = wp_delete_term( $term_id, 'product_cat' );
		if ( is_wp_error( $result ) || false === $result ) {
			return self::error( 'category_delete_failed', 'Impossibile eliminare la categoria.', 500 );
		}
		self::clear_category_cache( $term_id );

		return new WP_REST_Response( null, 204 );
	}

	public static function reorder_categories( WP_REST_Request $request ) {
		$items = $request->get_param( 'items' );
		if ( ! is_array( $items ) || ! $items ) {
			return self::error( 'invalid_category_order', 'L’ordinamento delle categorie non è valido.', 422 );
		}

		$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) ) {
			return self::error( 'categories_error', 'Impossibile verificare le categorie.', 500 );
		}

		$parents = array();
		foreach ( $terms as $term ) {
			$parents[ (int) $term->term_id ] = (int) $term->parent;
		}
		$normalized = array();
		foreach ( $items as $item ) {
			$id     = isset( $item['id'] ) ? (int) $item['id'] : 0;
			$parent = isset( $item['parent'] ) ? max( 0, (int) $item['parent'] ) : 0;
			if ( ! isset( $parents[ $id ] ) || ( $parent && ! isset( $parents[ $parent ] ) ) || $id === $parent ) {
				return self::error( 'invalid_category_order', 'L’ordinamento contiene categorie non valide.', 422 );
			}
			$parents[ $id ] = $parent;
			$normalized[ $id ] = array(
				'id'        => $id,
				'parent'    => $parent,
				'menuOrder' => isset( $item['menuOrder'] ) ? max( 0, (int) $item['menuOrder'] ) : 0,
			);
		}

		foreach ( array_keys( $parents ) as $id ) {
			$seen   = array();
			$cursor = $id;
			while ( $cursor && isset( $parents[ $cursor ] ) ) {
				if ( isset( $seen[ $cursor ] ) ) {
					return self::error( 'category_cycle', 'Non è possibile creare una gerarchia circolare.', 422 );
				}
				$seen[ $cursor ] = true;
				$cursor = $parents[ $cursor ];
			}
		}

		$depth = static function ( int $id ) use ( $parents ): int {
			$level = 0;
			while ( isset( $parents[ $id ] ) && $parents[ $id ] ) {
				++$level;
				$id = $parents[ $id ];
			}
			return $level;
		};
		uasort( $normalized, static fn( array $a, array $b ): int => $depth( $a['id'] ) <=> $depth( $b['id'] ) );

		foreach ( $normalized as $item ) {
			$result = wp_update_term( $item['id'], 'product_cat', array( 'parent' => $item['parent'] ) );
			if ( is_wp_error( $result ) ) {
				return self::term_error( $result );
			}
			update_term_meta( $item['id'], 'order', $item['menuOrder'] );
		}

		self::clear_category_cache();
		return self::categories();
	}

	public static function catalogs() {
		return rest_ensure_response( Fant_Admin_API_Catalogs::all() );
	}

	public static function catalog( WP_REST_Request $request ) {
		return rest_ensure_response( Fant_Admin_API_Catalogs::find( (string) $request['catalogCode'] ) );
	}

	public static function create_catalog( WP_REST_Request $request ) {
		$result = Fant_Admin_API_Catalogs::create(
			(string) $request->get_param( 'codice' ),
			(string) $request->get_param( 'nome' )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 201 );
	}

	public static function update_catalog( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();
		$code   = strtolower( (string) $request['catalogCode'] );
		if ( isset( $params['codice'] ) && strtolower( trim( (string) $params['codice'] ) ) !== $code ) {
			return self::error( 'catalog_code_immutable', 'Il codice del catalogo non può essere modificato.', 409 );
		}

		return rest_ensure_response(
			Fant_Admin_API_Catalogs::update( $code, (string) ( $params['nome'] ?? '' ) )
		);
	}

	public static function delete_catalog( WP_REST_Request $request ) {
		$result = Fant_Admin_API_Catalogs::delete( (string) $request['catalogCode'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( null, 204 );
	}

	private static function user_data( WP_User $user ): array {
		return array(
			'id'          => (int) $user->ID,
			'username'    => $user->user_login,
			'email'       => $user->user_email,
			'displayName' => $user->display_name,
			'firstName'   => $user->first_name,
			'lastName'    => $user->last_name,
			'roles'       => array_values( (array) $user->roles ),
			'avatarUrl'   => get_avatar_url( $user->ID, array( 'size' => 96 ) ),
		);
	}

	private static function category_data( WP_Term $term ): array {
		$image_id  = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
		$image_src = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : false;

		return array(
			'id'          => (int) $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'parent'      => (int) $term->parent,
			'display'     => (string) get_term_meta( $term->term_id, 'display_type', true ),
			'image'       => $image_src ? array(
				'id'  => $image_id,
				'src' => $image_src,
				'alt' => (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
			) : null,
			'count'       => (int) $term->count,
			'menuOrder'   => (int) get_term_meta( $term->term_id, 'order', true ),
		);
	}

	private static function next_category_order( int $parent ): int {
		$children = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'parent'     => $parent,
				'fields'     => 'ids',
			)
		);
		if ( is_wp_error( $children ) || ! $children ) {
			return 0;
		}

		$orders = array_map( static fn( $id ): int => (int) get_term_meta( $id, 'order', true ), $children );
		return max( $orders ) + 1;
	}

	private static function display_type( $value ): string {
		$value = sanitize_key( (string) $value );
		return in_array( $value, array( '', 'products', 'subcategories', 'both' ), true ) ? $value : '';
	}

	private static function is_category_descendant( int $candidate, int $ancestor ): bool {
		while ( $candidate ) {
			if ( $candidate === $ancestor ) {
				return true;
			}
			$term = get_term( $candidate, 'product_cat' );
			if ( ! $term instanceof WP_Term ) {
				return false;
			}
			$candidate = (int) $term->parent;
		}
		return false;
	}

	private static function clear_category_cache( int $term_id = 0 ): void {
		if ( $term_id ) {
			clean_term_cache( $term_id, 'product_cat' );
		} else {
			clean_taxonomy_cache( 'product_cat' );
		}
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients();
		}
	}

	private static function term_error( WP_Error $error ): WP_Error {
		$status = 'term_exists' === $error->get_error_code() ? 409 : 422;
		return self::error( $error->get_error_code(), $error->get_error_message(), $status );
	}

	private static function rate_limit_key( string $login ): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		return 'faa_login_' . md5( strtolower( $login ) . '|' . $ip );
	}

	private static function error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
