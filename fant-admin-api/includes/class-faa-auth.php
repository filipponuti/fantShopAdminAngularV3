<?php

defined( 'ABSPATH' ) || exit;

final class Fant_Admin_API_V4_Auth {
	private const TOKEN_BYTES = 32;

	public static function init(): void {
		add_filter( 'determine_current_user', array( __CLASS__, 'determine_current_user' ), 30 );
	}

	public static function determine_current_user( $user_id ) {
		if ( $user_id || ! self::is_api_request() ) {
			return $user_id;
		}

		$token = self::bearer_token();
		if ( '' === $token ) {
			return $user_id;
		}

		$session = self::find_session( 'access_hash', $token );
		if ( ! $session || self::is_expired( $session->access_expires_at ) ) {
			return $user_id;
		}

		return (int) $session->user_id;
	}

	public static function issue( int $user_id, string $device_id = '', string $device_name = '' ): array {
		global $wpdb;

		$access     = self::random_token();
		$refresh    = self::random_token();
		$now        = current_time( 'mysql', true );
		$access_ttl = max( 60, (int) get_option( 'faa_access_token_ttl', 900 ) );
		$refresh_ttl = max( 3600, (int) get_option( 'faa_refresh_token_ttl', 2592000 ) );

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'faa_sessions',
			array(
				'user_id'            => $user_id,
				'device_id'          => substr( sanitize_text_field( $device_id ), 0, 64 ),
				'device_name'        => substr( sanitize_text_field( $device_name ), 0, 191 ),
				'access_hash'        => self::hash( $access ),
				'refresh_hash'       => self::hash( $refresh ),
				'access_expires_at'  => gmdate( 'Y-m-d H:i:s', time() + $access_ttl ),
				'refresh_expires_at' => gmdate( 'Y-m-d H:i:s', time() + $refresh_ttl ),
				'created_at'         => $now,
				'updated_at'         => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			throw new RuntimeException( 'Impossibile creare la sessione.' );
		}

		return array(
			'accessToken'  => $access,
			'refreshToken' => $refresh,
			'tokenType'    => 'Bearer',
			'expiresIn'    => $access_ttl,
		);
	}

	public static function user_for_refresh_token( string $refresh_token ): ?WP_User {
		$session = self::find_session( 'refresh_hash', $refresh_token );
		if ( ! $session || self::is_expired( $session->refresh_expires_at ) ) {
			return null;
		}

		$user = get_userdata( (int) $session->user_id );
		return $user instanceof WP_User ? $user : null;
	}

	public static function rotate( string $refresh_token ): ?array {
		global $wpdb;

		$session = self::find_session( 'refresh_hash', $refresh_token );
		if ( ! $session || self::is_expired( $session->refresh_expires_at ) ) {
			return null;
		}

		$updated = $wpdb->update(
			$wpdb->prefix . 'faa_sessions',
			array(
				'revoked_at' => current_time( 'mysql', true ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array(
				'id'         => (int) $session->id,
				'revoked_at' => null,
			),
			array( '%s', '%s' ),
			array( '%d', null )
		);

		if ( false === $updated ) {
			return null;
		}

		return self::issue( (int) $session->user_id, (string) $session->device_id, (string) $session->device_name );
	}

	public static function revoke( string $refresh_token ): bool {
		global $wpdb;

		return false !== $wpdb->update(
			$wpdb->prefix . 'faa_sessions',
			array(
				'revoked_at' => current_time( 'mysql', true ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'refresh_hash' => self::hash( $refresh_token ) ),
			array( '%s', '%s' ),
			array( '%s' )
		);
	}

	public static function is_administrator( WP_User $user ): bool {
		return in_array( 'administrator', (array) $user->roles, true );
	}

	private static function find_session( string $column, string $token ): ?object {
		global $wpdb;

		if ( ! in_array( $column, array( 'access_hash', 'refresh_hash' ), true ) || '' === $token ) {
			return null;
		}

		$table_name = $wpdb->prefix . 'faa_sessions';
		$session    = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE {$column} = %s AND revoked_at IS NULL LIMIT 1",
				self::hash( $token )
			)
		);

		return $session ?: null;
	}

	private static function bearer_token(): string {
		$header = '';
		if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$header = trim( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		} elseif ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$header = trim( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
		}

		if ( preg_match( '/^Bearer\s+(.+)$/i', $header, $matches ) ) {
			return trim( $matches[1] );
		}

		return '';
	}

	private static function random_token(): string {
		return rtrim( strtr( base64_encode( random_bytes( self::TOKEN_BYTES ) ), '+/', '-_' ), '=' );
	}

	private static function hash( string $token ): string {
		return hash( 'sha256', $token );
	}

	private static function is_expired( string $mysql_utc ): bool {
		return strtotime( $mysql_utc . ' UTC' ) <= time();
	}

	private static function is_api_request(): bool {
		$uri        = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$rest_route = isset( $_GET['rest_route'] ) ? wp_unslash( $_GET['rest_route'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return false !== strpos( $uri, '/wp-json/fant-admin/v1/' )
			|| 0 === strpos( $rest_route, '/fant-admin/v1/' );
	}
}
