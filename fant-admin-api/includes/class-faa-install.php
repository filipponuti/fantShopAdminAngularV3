<?php

defined( 'ABSPATH' ) || exit;

final class Fant_Admin_API_V4_Install {
	public static function ensure_installed(): void {
		if ( FANT_ADMIN_API_V4_VERSION !== get_option( 'faa_version' ) ) {
			self::activate();
		}
	}

	public static function activate(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name = $wpdb->prefix . 'faa_sessions';
		$charset    = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table_name} (
				id bigint unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint unsigned NOT NULL,
				device_id varchar(64) NOT NULL DEFAULT '',
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
			) {$charset};"
		);

		add_option( 'faa_access_token_ttl', 900 );
		add_option( 'faa_refresh_token_ttl', 2592000 );
		add_option( 'faa_disable_emails', 'yes' );
		update_option( 'faa_version', FANT_ADMIN_API_V4_VERSION );
	}
}
