<?php

defined( 'ABSPATH' ) || exit;

final class AMA_Auth {
    private const TOKEN_BYTES = 32;

    public static function init(): void {
        add_filter( 'determine_current_user', array( __CLASS__, 'determine_current_user' ), 30 );
    }

    public static function determine_current_user( $user_id ) {
        if ( $user_id || ! self::is_api_request() ) {
            return $user_id;
        }

        $token = self::bearer_token();
        if ( ! $token ) {
            return $user_id;
        }

        $session = self::find_session( 'access_hash', $token );
        if ( ! $session || strtotime( $session->access_expires_at . ' UTC' ) <= time() ) {
            return $user_id;
        }

        return (int) $session->user_id;
    }

    public static function issue( int $user_id, string $device_id, string $device_name = '' ): array {
        global $wpdb;

        $access = self::random_token();
        $refresh = self::random_token();
        $now = current_time( 'mysql', true );
        $access_ttl = max( 60, (int) get_option( 'ama_access_token_ttl', 900 ) );
        $refresh_ttl = max( 3600, (int) get_option( 'ama_refresh_token_ttl', 2592000 ) );

        $wpdb->insert(
            $wpdb->prefix . 'ama_sessions',
            array(
                'user_id'            => $user_id,
                'device_id'          => sanitize_text_field( $device_id ),
                'device_name'        => sanitize_text_field( $device_name ),
                'access_hash'        => self::hash( $access ),
                'refresh_hash'       => self::hash( $refresh ),
                'access_expires_at'  => gmdate( 'Y-m-d H:i:s', time() + $access_ttl ),
                'refresh_expires_at' => gmdate( 'Y-m-d H:i:s', time() + $refresh_ttl ),
                'created_at'         => $now,
                'updated_at'         => $now,
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( ! $wpdb->insert_id ) {
            throw new RuntimeException( 'Impossibile creare la sessione.' );
        }

        return array(
            'accessToken'  => $access,
            'refreshToken' => $refresh,
            'tokenType'    => 'Bearer',
            'expiresIn'    => $access_ttl,
        );
    }

    public static function rotate( string $refresh_token ): ?array {
        global $wpdb;

        $session = self::find_session( 'refresh_hash', $refresh_token );
        if ( ! $session || strtotime( $session->refresh_expires_at . ' UTC' ) <= time() ) {
            return null;
        }

        $wpdb->update(
            $wpdb->prefix . 'ama_sessions',
            array( 'revoked_at' => current_time( 'mysql', true ) ),
            array( 'id' => (int) $session->id ),
            array( '%s' ),
            array( '%d' )
        );

        return self::issue( (int) $session->user_id, $session->device_id, $session->device_name );
    }

    public static function revoke( string $refresh_token ): bool {
        global $wpdb;

        return false !== $wpdb->update(
            $wpdb->prefix . 'ama_sessions',
            array( 'revoked_at' => current_time( 'mysql', true ) ),
            array( 'refresh_hash' => self::hash( $refresh_token ) ),
            array( '%s' ),
            array( '%s' )
        );
    }

    public static function is_sales_agent( int $user_id ): bool {
        if ( function_exists( 'wcb2bsa_has_role' ) ) {
            return wcb2bsa_has_role( $user_id, 'sales_agent' );
        }

        $user = get_userdata( $user_id );
        $agent_roles = get_option( 'wcb2bsa_has_role_sales_agent', array( 'sales_agent' ) );
        if ( ! is_array( $agent_roles ) ) {
            $agent_roles = array( 'sales_agent' );
        }

        return $user && ! empty( array_intersect( $agent_roles, (array) $user->roles ) );
    }

    private static function find_session( string $column, string $token ): ?object {
        global $wpdb;

        if ( ! in_array( $column, array( 'access_hash', 'refresh_hash' ), true ) ) {
            return null;
        }

        $table = $wpdb->prefix . 'ama_sessions';
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$column} = %s AND revoked_at IS NULL LIMIT 1",
            self::hash( $token )
        );
        $session = $wpdb->get_row( $sql );
        return $session ?: null;
    }

    private static function bearer_token(): string {
        $header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? trim( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ) : '';
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

    private static function is_api_request(): bool {
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        return false !== strpos( $uri, '/wp-json/agent-mobile/v1/' );
    }
}
