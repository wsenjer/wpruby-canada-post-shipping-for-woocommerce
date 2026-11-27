<?php
/**
 * Builds and checks a Get Rates request body.
 *
 * @package WPRuby\CanadaPostShipping
 */

namespace WPRuby\CanadaPostShipping\Rates;

use WPRuby\CanadaPostShipping\Api\ApiException;
use WPRuby\CanadaPostShipping\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * The body of POST /rating/v1/prices for one parcel. Invalid input is caught here,
 * before anything is sent to Canada Post.
 */
class RateRequest {

	const QUOTE_COMMERCIAL = 'commercial';
	const QUOTE_COUNTER    = 'counter';

	/** Canada Post accepts at most 20 services per request. */
	const MAX_SERVICES = 20;

	/**
	 * @param Settings $settings   Store settings.
	 * @param array    $parcel     Weight (kg) and optional length, width, height (cm).
	 * @param string   $country    Destination country code.
	 * @param string   $postcode   Destination postal code or ZIP code.
	 * @param string[] $services   Service codes to quote.
	 * @param string   $quote_type "commercial" or "counter".
	 * @return array
	 * @throws ApiException When a value would be rejected by Canada Post.
	 */
	public static function build( Settings $settings, array $parcel, string $country, string $postcode, array $services, string $quote_type ): array {
		$customer = $settings->customer_number();
		if ( '' === $customer || strlen( $customer ) > 10 ) {
			throw self::invalid( esc_html__( 'The Canada Post customer number is missing or too long.', 'wpruby-shipping-rates-canada-post' ) );
		}

		$origin = $settings->origin_postcode();
		if ( ! self::is_canadian_postcode( $origin ) ) {
			throw self::invalid( esc_html__( 'The origin postal code must be a Canadian postal code, like K1A 0B1.', 'wpruby-shipping-rates-canada-post' ) );
		}

		$weight = round( (float) ( $parcel['weight'] ?? 0 ), 3 );
		if ( $weight <= 0 || $weight > 99.999 ) {
			throw self::invalid( esc_html__( 'The parcel weight must be more than 0 and at most 99.999 kg.', 'wpruby-shipping-rates-canada-post' ) );
		}

		$quote_type = self::QUOTE_COUNTER === $quote_type ? self::QUOTE_COUNTER : self::QUOTE_COMMERCIAL;

		$body = array(
			'customerNumber'        => $customer,
			'quoteType'             => $quote_type,
			'originPostalCode'      => $origin,
			'destination'           => self::destination( $country, $postcode ),
			'parcelCharacteristics' => array( 'weight' => $weight ),
		);

		$length = (float) ( $parcel['length'] ?? 0 );
		$width  = (float) ( $parcel['width'] ?? 0 );
		$height = (float) ( $parcel['height'] ?? 0 );
		if ( $length > 0 && $width > 0 && $height > 0 ) {
			$body['parcelCharacteristics']['dimensions'] = array(
				'length' => round( $length, 1 ),
				'width'  => round( $width, 1 ),
				'height' => round( $height, 1 ),
			);
		}

		if ( self::QUOTE_COMMERCIAL === $quote_type && '' !== $settings->contract_number() ) {
			$body['contractId'] = $settings->contract_number();
		}

		$services = array_values( array_unique( array_filter( array_map( 'strval', $services ) ) ) );
		if ( $services ) {
			$body['services'] = array_slice( $services, 0, self::MAX_SERVICES );
		}

		return $body;
	}

	/**
	 * The destination block for a country and postal code.
	 *
	 * @throws ApiException For a Canadian postal code or US ZIP code Canada Post would reject.
	 */
	public static function destination( string $country, string $postcode ): array {
		$country = strtoupper( trim( $country ) );
		if ( 'CA' === $country ) {
			$postcode = strtoupper( (string) preg_replace( '/\s+/', '', $postcode ) );
			if ( ! self::is_canadian_postcode( $postcode ) ) {
				throw self::invalid( esc_html__( 'Enter a valid Canadian postal code to see Canada Post rates.', 'wpruby-shipping-rates-canada-post' ) );
			}
			return array( 'domestic' => array( 'postalCode' => $postcode ) );
		}
		if ( 'US' === $country ) {
			$zip = trim( $postcode );
			if ( ! preg_match( '/^\d{5}(-\d{4})?$/', $zip ) ) {
				throw self::invalid( esc_html__( 'Enter a valid US ZIP code to see Canada Post rates.', 'wpruby-shipping-rates-canada-post' ) );
			}
			return array( 'unitedStates' => array( 'zipCode' => $zip ) );
		}
		if ( ! preg_match( '/^[A-Z]{2}$/', $country ) ) {
			throw self::invalid( esc_html__( 'The destination country is missing.', 'wpruby-shipping-rates-canada-post' ) );
		}
		$international = array( 'countryCode' => $country );
		$postcode      = substr( trim( $postcode ), 0, 10 );
		if ( '' !== $postcode ) {
			$international['postalCode'] = $postcode;
		}
		return array( 'international' => $international );
	}

	public static function is_canadian_postcode( string $postcode ): bool {
		return 1 === preg_match( '/^[A-Z]\d[A-Z]\d[A-Z]\d$/', $postcode );
	}

	private static function invalid( string $message ): ApiException {
		return new ApiException( $message, 'VALIDATION', 0 );
	}
}
