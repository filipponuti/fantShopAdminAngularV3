<?php

defined( 'ABSPATH' ) || exit;

/**
 * Stores AI provider settings without exposing API keys through REST responses.
 */
final class Fant_Admin_API_V4_AI_Settings {
	private const OPTION_NAME = 'faa_ai_settings';
	private const PROVIDERS   = array( 'gemini', 'openai', 'claude' );

	public static function get_public(): array {
		$settings = self::stored_settings();
		$result   = array();

		foreach ( self::PROVIDERS as $provider ) {
			$result[ $provider ]                     = $settings[ $provider ];
			$result[ $provider ]['apiKeyConfigured'] = '' !== (string) $settings[ $provider ]['apiKeyEncrypted'];
			unset( $result[ $provider ]['apiKeyEncrypted'] );
		}

		return $result;
	}

	/**
	 * Returns a provider configuration including its decrypted key for future
	 * server-side API calls. This method must never be used as a REST response.
	 */
	public static function credentials( string $provider ) {
		$provider = sanitize_key( $provider );
		if ( ! in_array( $provider, self::PROVIDERS, true ) ) {
			return new WP_Error( 'unknown_ai_provider', 'Provider AI non valido.' );
		}

		$settings = self::stored_settings();
		$config   = $settings[ $provider ];
		$secret   = self::decrypt_secret( (string) $config['apiKeyEncrypted'] );
		if ( is_wp_error( $secret ) ) {
			return $secret;
		}

		$config['apiKey'] = $secret;
		unset( $config['apiKeyEncrypted'] );
		return $config;
	}

	public static function update( array $payload ) {
		$current = self::stored_settings();

		foreach ( self::PROVIDERS as $provider ) {
			if ( ! isset( $payload[ $provider ] ) || ! is_array( $payload[ $provider ] ) ) {
				continue;
			}

			$incoming = $payload[ $provider ];
			$config   = $current[ $provider ];
			$enabled  = array_key_exists( 'enabled', $incoming )
				? rest_sanitize_boolean( $incoming['enabled'] )
				: (bool) $config['enabled'];

			$model = array_key_exists( 'model', $incoming )
				? self::limited_text( $incoming['model'], 200 )
				: (string) $config['model'];

			$endpoint = array_key_exists( 'endpoint', $incoming )
				? untrailingslashit( esc_url_raw( trim( (string) $incoming['endpoint'] ) ) )
				: (string) $config['endpoint'];

			$timeout = array_key_exists( 'timeoutSeconds', $incoming )
				? min( 300, max( 5, (int) $incoming['timeoutSeconds'] ) )
				: (int) $config['timeoutSeconds'];

			if ( '' === $endpoint || ! str_starts_with( strtolower( $endpoint ), 'https://' ) || ! wp_http_validate_url( $endpoint ) ) {
				return new WP_Error(
					'invalid_ai_endpoint',
					sprintf( 'L’endpoint HTTPS configurato per %s non è valido.', $provider ),
					array( 'status' => 422 )
				);
			}

			$encrypted_key = (string) $config['apiKeyEncrypted'];
			if ( ! empty( $incoming['clearApiKey'] ) ) {
				$encrypted_key = '';
			}
			if ( array_key_exists( 'apiKey', $incoming ) && '' !== trim( (string) $incoming['apiKey'] ) ) {
				$api_key = trim( (string) $incoming['apiKey'] );
				if ( strlen( $api_key ) > 4096 ) {
					return new WP_Error( 'invalid_ai_key', 'La chiave API è troppo lunga.', array( 'status' => 422 ) );
				}
				$encrypted_key = self::encrypt_secret( $api_key );
				if ( is_wp_error( $encrypted_key ) ) {
					return $encrypted_key;
				}
			}

			if ( $enabled && '' === $model ) {
				return new WP_Error(
					'ai_model_required',
					sprintf( 'Il modello è obbligatorio quando %s è attivo.', $provider ),
					array( 'status' => 422 )
				);
			}
			if ( $enabled && '' === $encrypted_key ) {
				return new WP_Error(
					'ai_key_required',
					sprintf( 'La chiave API è obbligatoria quando %s è attivo.', $provider ),
					array( 'status' => 422 )
				);
			}

			$config['enabled']         = $enabled;
			$config['model']           = $model;
			$config['endpoint']        = $endpoint;
			$config['timeoutSeconds']  = $timeout;
			$config['apiKeyEncrypted'] = $encrypted_key;

			if ( 'openai' === $provider ) {
				$config['organization'] = array_key_exists( 'organization', $incoming )
					? self::limited_text( $incoming['organization'], 200 )
					: (string) $config['organization'];
				$config['project']      = array_key_exists( 'project', $incoming )
					? self::limited_text( $incoming['project'], 200 )
					: (string) $config['project'];
			}

			if ( 'claude' === $provider ) {
				$api_version = array_key_exists( 'apiVersion', $incoming )
					? self::limited_text( $incoming['apiVersion'], 20 )
					: (string) $config['apiVersion'];
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $api_version ) ) {
					return new WP_Error( 'invalid_claude_version', 'La versione API Claude deve avere formato AAAA-MM-GG.', array( 'status' => 422 ) );
				}
				$config['apiVersion'] = $api_version;
			}

			$current[ $provider ] = $config;
		}

		if ( null === get_option( self::OPTION_NAME, null ) ) {
			add_option( self::OPTION_NAME, $current, '', false );
		} else {
			update_option( self::OPTION_NAME, $current, false );
		}

		return self::get_public();
	}

	private static function defaults(): array {
		$common = array(
			'enabled'         => false,
			'apiKeyEncrypted' => '',
			'model'           => '',
			'timeoutSeconds'  => 60,
		);

		return array(
			'gemini' => array_merge( $common, array(
				'endpoint' => 'https://generativelanguage.googleapis.com/v1beta',
			) ),
			'openai' => array_merge( $common, array(
				'endpoint'     => 'https://api.openai.com/v1',
				'organization' => '',
				'project'      => '',
			) ),
			'claude' => array_merge( $common, array(
				'endpoint'   => 'https://api.anthropic.com/v1',
				'apiVersion' => '2023-06-01',
			) ),
		);
	}

	private static function stored_settings(): array {
		$defaults = self::defaults();
		$stored   = get_option( self::OPTION_NAME, array() );
		$stored   = is_array( $stored ) ? $stored : array();

		foreach ( self::PROVIDERS as $provider ) {
			if ( isset( $stored[ $provider ] ) && is_array( $stored[ $provider ] ) ) {
				$defaults[ $provider ] = array_merge( $defaults[ $provider ], $stored[ $provider ] );
			}
		}

		return $defaults;
	}

	private static function limited_text( $value, int $max_length ): string {
		$value = sanitize_text_field( (string) $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max_length ) : substr( $value, 0, $max_length );
	}

	private static function encryption_key(): string {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}

	private static function encrypt_secret( string $secret ) {
		$key = self::encryption_key();

		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $secret, $nonce, $key );
			return 'sodium:' . base64_encode( $nonce . $cipher );
		}

		if ( function_exists( 'openssl_encrypt' ) ) {
			$nonce  = random_bytes( 12 );
			$tag    = '';
			$cipher = openssl_encrypt( $secret, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag );
			if ( false !== $cipher ) {
				return 'openssl:' . base64_encode( $nonce . $tag . $cipher );
			}
		}

		return new WP_Error( 'ai_encryption_unavailable', 'Impossibile cifrare la chiave API sul server.', array( 'status' => 500 ) );
	}

	private static function decrypt_secret( string $encrypted ) {
		if ( '' === $encrypted ) {
			return '';
		}

		$key = self::encryption_key();
		if ( str_starts_with( $encrypted, 'sodium:' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$payload = base64_decode( substr( $encrypted, 7 ), true );
			if ( false !== $payload && strlen( $payload ) > SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
				$nonce = substr( $payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
				$plain = sodium_crypto_secretbox_open( substr( $payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, $key );
				if ( false !== $plain ) {
					return $plain;
				}
			}
		}

		if ( str_starts_with( $encrypted, 'openssl:' ) && function_exists( 'openssl_decrypt' ) ) {
			$payload = base64_decode( substr( $encrypted, 8 ), true );
			if ( false !== $payload && strlen( $payload ) > 28 ) {
				$nonce = substr( $payload, 0, 12 );
				$tag   = substr( $payload, 12, 16 );
				$plain = openssl_decrypt( substr( $payload, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag );
				if ( false !== $plain ) {
					return $plain;
				}
			}
		}

		return new WP_Error( 'ai_key_decryption_failed', 'Impossibile decifrare la chiave API salvata.' );
	}
}
