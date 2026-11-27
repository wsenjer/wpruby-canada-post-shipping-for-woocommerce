<?php
/**
 * The "Test connection" button.
 *
 * @package WPRuby\CanadaPostShipping
 */

namespace WPRuby\CanadaPostShipping\Admin;

use WPRuby\CanadaPostShipping\Api\ApiException;
use WPRuby\CanadaPostShipping\Api\Client;
use WPRuby\CanadaPostShipping\Rates\RateRequest;
use WPRuby\CanadaPostShipping\Rates\RateService;
use WPRuby\CanadaPostShipping\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Gets a token, then a Regular Parcel rate for 1 kg from the origin to itself.
 */
class ConnectionCheck {

	const ACTION = 'wpruby_cps_test_connection';

	public static function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	public static function handle(): void {
		check_ajax_referer( self::ACTION );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'wpruby-shipping-rates-canada-post' ) ), 403 );
		}

		$result = self::run( new Settings() );
		if ( $result['ok'] ) {
			wp_send_json_success( $result );
		}
		wp_send_json_error( $result );
	}

	/**
	 * @return array ok, message.
	 */
	public static function run( Settings $settings, ?Client $client = null ): array {
		if ( ! $settings->has_credentials() ) {
			return array(
				'ok'      => false,
				'message' => __( 'Enter the API key, API secret and customer number, then save.', 'wpruby-shipping-rates-canada-post' ),
			);
		}

		$client = $client ?? new Client( $settings );
		try {
			$client->check_credentials();
			$quote_type = $settings->is_commercial_account() ? RateRequest::QUOTE_COMMERCIAL : RateRequest::QUOTE_COUNTER;
			$body       = RateRequest::build( $settings, array( 'weight' => 1.0 ), 'CA', $settings->origin_postcode(), array( 'DOM.RP' ), $quote_type );
			$quotes     = array_filter( array_map( array( RateService::class, 'normalize' ), $client->get_rates( $body ) ) );
		} catch ( ApiException $e ) {
			return array(
				'ok'      => false,
				'message' => trim( wp_specialchars_decode( $e->getMessage(), ENT_QUOTES ) . ' ' . $e->hint() ),
			);
		}

		if ( ! $quotes ) {
			return array(
				'ok'      => false,
				'message' => __( 'Connected, but Canada Post returned no Regular Parcel rate. Check the customer number and the ship-from postal code.', 'wpruby-shipping-rates-canada-post' ),
			);
		}

		$quote   = reset( $quotes );
		$message = sprintf(
			/* translators: %s: price in CAD */
			__( 'Connected. Regular Parcel, 1 kg, to your own postal code: %s CAD including tax.', 'wpruby-shipping-rates-canada-post' ),
			number_format_i18n( $quote['due'], 2 )
		);
		if ( $settings->is_test_app() ) {
			$message .= ' ' . __( 'This is a Test app, so the price is a sample.', 'wpruby-shipping-rates-canada-post' );
		}
		return array(
			'ok'      => true,
			'message' => $message,
		);
	}
}
