<?php

defined( 'ABSPATH' ) || exit;

final class AMA_Install {
    public static function activate(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $sessions = $wpdb->prefix . 'ama_sessions';
        $idempotency = $wpdb->prefix . 'ama_idempotency';

        dbDelta( "CREATE TABLE {$sessions} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint unsigned NOT NULL,
            device_id varchar(64) NOT NULL,
            device_name varchar(191) NOT NULL DEFAULT '',
            access_hash char(64) NOT NULL,
            refresh_hash char(64) NOT NULL,
            access_expires_at datetime NOT NULL,
            refresh_expires_at datetime NOT NULL,
            revoked_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY access_hash (access_hash),
            UNIQUE KEY refresh_hash (refresh_hash),
            KEY user_id (user_id),
            KEY device_id (device_id)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$idempotency} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint unsigned NOT NULL,
            idempotency_key char(36) NOT NULL,
            request_hash char(64) NOT NULL,
            resource_type varchar(32) NOT NULL,
            resource_id bigint unsigned NULL,
            response_json longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_key (user_id, idempotency_key),
            KEY resource (resource_type, resource_id)
        ) {$charset};" );

        add_option( 'ama_disable_emails', 'yes' );
        add_option( 'ama_access_token_ttl', 900 );
        add_option( 'ama_refresh_token_ttl', 2592000 );
        add_option( 'ama_allow_manual_price', 'yes' );
        add_option( 'ama_allow_discounts', 'yes' );
        update_option( 'ama_version', AMA_VERSION );
    }
}
