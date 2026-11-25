<?php
/**
 * Canada Post API client: OAuth token and Get Rates.
 *
 * @package WPRuby\CanadaPostShipping
 */

namespace WPRuby\CanadaPostShipping\Api;

use WPRuby\CanadaPostShipping\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Talks to Canada Post's Developer Portal gateway (OAuth 2.0 client credentials, JSON).
 * The same URL serves Test and Production apps: the app that owns the key decides.
 */
class Client {

	const BASE_URL   = 'https://api.canadapost-postescanada.ca/prod/devportal-portaildesdeveloppeurs';
	const TOKEN_PATH = '/cpc-api-native-oauth-provider/oauth2/token';
	const RATES_PATH = '/rating/v1/prices';

	/** Seconds before a token's expiry when it's treated as expired. */
	const TOKEN_MARGIN = 120;

	/** Seconds to wait after Canada Post limits requests. */
	const THROTTLE_SECONDS = 60;

	const TIMEOUT = 12;

	/**
	 * @var Settings
	 */
	private $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Get Rates for one parcel.
	 *
	 * @param array $body Request body (see RateRequest).
	 * @return array List of quotes as Canada Post returned them.
	 * @throws ApiException When the request fails.
	 */
	public function get_rates( array $body ): array {
		$response = $this->send( self::RATES_PATH, $body );
		if ( isset( $response['serviceCode'] ) ) {
			return array( $response ); // A single quote instead of a list.
		}
		return array_values( array_filter( $response, 'is_array' ) );
	}

	/**
	 * Get a token, for the "Test connection" button.
	 *
	 * @throws ApiException When Canada Post rejects the credentials.
	 */
	public function check_credentials(): void {
		$this->forget_token();
		$this->token();
	}

	/**
	 * POST a JSON body, with one retry: a new token after a 401, or the same request
	 * after a timeout or a 5xx. Get Rates only reads, so retrying it is safe.
	 *
	 * @throws ApiException When the request fails.
	 */
	private function send( string $path, array $body ): array {
		if ( get_transient( $this->throttle_key() ) ) {
			throw new ApiException( esc_html__( 'Canada Post is limiting requests from this store.', 'wpruby-canada-post-shipping-for-woocommerce' ), 'THROTTLED', 429 );
		}

		$attempt = 0;
		while ( true ) {
			$attempt++;
			$response = wp_remote_post(
				self::BASE_URL . $path,
				array(
					'timeout'     => self::TIMEOUT,
					'redirection' => 0,
					'headers'     => array(
						'Authorization'   => 'Bearer ' . $this->token(),
						'Accept'          => 'application/json',
						'Content-Type'    => 'application/json',
						'Accept-Language' => $this->language(),
					),
					'body'        => self::encode( $body ),
					'user-agent'  => 'WPRuby-CanadaPost-Free/' . ( defined( 'WPRUBY_CPS_VERSION' ) ? WPRUBY_CPS_VERSION : 'dev' ),
				)
			);

			if ( is_wp_error( $response ) ) {
				if ( 1 === $attempt ) {
					continue;
				}
				/* translators: %s: error message from WordPress */
				throw new ApiException( esc_html( sprintf( __( 'Could not reach Canada Post: %s', 'wpruby-canada-post-shipping-for-woocommerce' ), $response->get_error_message() ) ), 'NETWORK', 0 );
			}

			$status  = (int) wp_remote_retrieve_response_code( $response );
			$raw     = (string) wp_remote_retrieve_body( $response );
			$decoded = json_decode( $raw, true );

			if ( $status >= 200 && $status < 300 ) {
				if ( ! is_array( $decoded ) ) {
					throw new ApiException( esc_html__( 'Canada Post sent a response that is not JSON.', 'wpruby-canada-post-shipping-for-woocommerce' ), 'BAD_RESPONSE', (int) $status );
				}
				return $decoded;
			}

			if ( 401 === $status && 1 === $attempt ) {
				$this->forget_token();
				continue;
			}
			if ( 429 === $status || false !== strpos( $raw, 'Rejected by SLM Monitor' ) ) {
				set_transient( $this->throttle_key(), 1, self::THROTTLE_SECONDS );
				throw new ApiException( esc_html( self::error_message( $decoded, $raw, $status ) ), 'THROTTLED', 429 );
			}
			if ( in_array( $status, array( 500, 502, 503, 504 ), true ) && 1 === $attempt ) {
				continue;
			}

			throw new ApiException( esc_html( self::error_message( $decoded, $raw, $status ) ), esc_html( self::error_code( $decoded ) ), (int) $status );
		}
	}

	/**
	 * A cached access token, or a new one.
	 *
	 * @throws ApiException When the credentials are missing or rejected.
	 */
	private function token(): string {
		$key    = $this->settings->api_key();
		$secret = $this->settings->api_secret();
		if ( '' === $key || '' === $secret ) {
			throw new ApiException( esc_html__( 'The Canada Post API key and secret are not set.', 'wpruby-canada-post-shipping-for-woocommerce' ), 'AUTH', 0 );
		}

		$cached = get_transient( $this->token_key() );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$response = wp_remote_post(
			self::BASE_URL . self::TOKEN_PATH,
			array(
				'timeout'     => self::TIMEOUT,
				'redirection' => 0,
				'headers'     => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'        => array(
					'grant_type'    => 'client_credentials',
					'scope'         => 'merchant',
					'client_id'     => $key,
					'client_secret' => $secret,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			/* translators: %s: error message from WordPress */
			throw new ApiException( esc_html( sprintf( __( 'Could not reach Canada Post: %s', 'wpruby-canada-post-shipping-for-woocommerce' ), $response->get_error_message() ) ), 'NETWORK', 0 );
		}

		$status  = (int) wp_remote_retrieve_response_code( $response );
		$raw     = (string) wp_remote_retrieve_body( $response );
		$decoded = json_decode( $raw, true );
		$token   = is_array( $decoded ) ? (string) ( $decoded['access_token'] ?? '' ) : '';

		if ( 200 !== $status || '' === $token ) {
			if ( $status >= 500 ) {
				throw new ApiException( esc_html( self::error_message( $decoded, $raw, $status ) ), 'NETWORK', (int) $status );
			}
			/* translators: %s: Canada Post's error message */
			throw new ApiException( esc_html( sprintf( __( 'Canada Post rejected the API key and secret: %s', 'wpruby-canada-post-shipping-for-woocommerce' ), self::error_message( $decoded, $raw, $status ) ) ), 'AUTH', 401 );
		}

		$ttl = (int) ( $decoded['expires_in'] ?? 3600 ) - self::TOKEN_MARGIN;
		set_transient( $this->token_key(), $token, max( 60, $ttl ) );

		return $token;
	}

	private function forget_token(): void {
		delete_transient( $this->token_key() );
	}

	private function token_key(): string {
		return 'wpruby_cps_token_' . $this->settings->fingerprint();
	}

	private function throttle_key(): string {
		return 'wpruby_cps_throttled_' . $this->settings->fingerprint();
	}

	private function language(): string {
		return 0 === strpos( (string) get_locale(), 'fr' ) ? 'fr-CA' : 'en-CA';
	}

	/**
	 * JSON with the shortest exact floats. With PHP's default precision, 4.4 can be sent
	 * as 4.4000000000000004, which Canada Post rejects as a schema error.
	 */
	public static function encode( array $body ): string {
		$precision = ini_get( 'serialize_precision' );
		ini_set( 'serialize_precision', '-1' ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.PHP.IniSet.Risky -- Restored right after encoding.
		$json = wp_json_encode( $body );
		ini_set( 'serialize_precision', (string) $precision ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.PHP.IniSet.Risky -- Restores the setting.
		return (string) $json;
	}

	/**
	 * Canada Post's error message, from any of the error formats its gateway uses.
	 *
	 * @param mixed $decoded Decoded body.
	 */
	public static function error_message( $decoded, string $raw, int $status ): string {
		$messages = array();
		$bodies   = is_array( $decoded ) && isset( $decoded[0] ) && is_array( $decoded[0] ) ? $decoded : array( $decoded );
		foreach ( $bodies as $body ) {
			if ( ! is_array( $body ) ) {
				continue;
			}
			foreach ( (array) ( $body['errors'] ?? array() ) as $error ) {
				$messages[] = trim( ( $error['errorCode'] ?? '' ) . ' ' . ( $error['message'] ?? '' ) );
			}
			foreach ( (array) ( $body['messages'] ?? array() ) as $message ) {
				$messages[] = trim( ( $message['code'] ?? '' ) . ' ' . ( $message['description'] ?? '' ) );
			}
			if ( ! $messages ) {
				foreach ( array( 'detail', 'title', 'errorDescription', 'errorMessage', 'moreInformation', 'httpMessage', 'error_description', 'error' ) as $field ) {
					if ( ! empty( $body[ $field ] ) && is_string( $body[ $field ] ) ) {
						$messages[] = $body[ $field ];
						break;
					}
				}
			}
		}
		$messages = array_values( array_unique( array_filter( $messages ) ) );
		if ( $messages ) {
			return implode( '; ', $messages );
		}
		$raw = trim( wp_strip_all_tags( $raw ) );
		/* translators: %d: HTTP status code */
		return '' !== $raw ? substr( $raw, 0, 200 ) : sprintf( __( 'HTTP %d from Canada Post.', 'wpruby-canada-post-shipping-for-woocommerce' ), $status );
	}

	/**
	 * Canada Post's error code, when the body has one.
	 *
	 * @param mixed $decoded Decoded body.
	 */
	private static function error_code( $decoded ): string {
		$body = is_array( $decoded ) && isset( $decoded[0] ) && is_array( $decoded[0] ) ? $decoded[0] : $decoded;
		if ( ! is_array( $body ) ) {
			return '';
		}
		if ( ! empty( $body['errors'][0]['errorCode'] ) ) {
			return (string) $body['errors'][0]['errorCode'];
		}
		if ( ! empty( $body['messages'][0]['code'] ) ) {
			return (string) $body['messages'][0]['code'];
		}
		return (string) ( $body['errorCode'] ?? '' );
	}
}
