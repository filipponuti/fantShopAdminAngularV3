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

	private static function rate_limit_key( string $login ): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		return 'faa_login_' . md5( strtolower( $login ) . '|' . $ip );
	}

	private static function error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
