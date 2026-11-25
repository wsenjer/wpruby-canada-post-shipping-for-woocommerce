<?php
/**
 * Quotes every parcel and combines the results per service.
 *
 * @package WPRuby\CanadaPostShipping
 */

namespace WPRuby\CanadaPostShipping\Rates;

use WPRuby\CanadaPostShipping\Api\ApiException;
use WPRuby\CanadaPostShipping\Api\Client;
use WPRuby\CanadaPostShipping\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * One Get Rates request per parcel (cached for 10 minutes). A service is offered only
 * when Canada Post quoted it for every parcel; its price is the sum over the parcels.
 */
class RateService {

	const CACHE_SECONDS = 600;

	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @var Client
	 */
	private $client;

	public function __construct( Settings $settings, Client $client ) {
		$this->settings = $settings;
		$this->client   = $client;
	}

	/**
	 * @param array[]  $parcels    From Packer::pack().
	 * @param string   $country    Destination country.
	 * @param string   $postcode   Destination postal code.
	 * @param string[] $services   Service codes to quote.
	 * @param string   $quote_type "commercial" or "counter".
	 * @return array<string,array> Service code => name, due, taxes, transit (days or null), delivery_date.
	 * @throws ApiException When a parcel can't be quoted.
	 */
	public function quote( array $parcels, string $country, string $postcode, array $services, string $quote_type ): array {
		$combined = null;
		foreach ( $parcels as $parcel ) {
			$quotes = $this->quote_parcel( RateRequest::build( $this->settings, $parcel, $country, $postcode, $services, $quote_type ) );

			if ( null === $combined ) {
				$combined = $quotes;
				continue;
			}
			foreach ( $combined as $code => $total ) {
				if ( ! isset( $quotes[ $code ] ) ) {
					unset( $combined[ $code ] );
					continue;
				}
				$combined[ $code ]['due']           += $quotes[ $code ]['due'];
				$combined[ $code ]['taxes']         += $quotes[ $code ]['taxes'];
				$combined[ $code ]['transit']        = self::max_or_null( $total['transit'], $quotes[ $code ]['transit'] );
				$combined[ $code ]['delivery_date']  = max( $total['delivery_date'], $quotes[ $code ]['delivery_date'] );
			}
		}

		$combined = (array) $combined;
		foreach ( $combined as $code => $quote ) {
			$combined[ $code ]['due']   = round( $quote['due'], 2 );
			$combined[ $code ]['taxes'] = round( $quote['taxes'], 2 );
		}
		return $combined;
	}

	/**
	 * Quotes for one parcel, from the cache when the same request was made in the last 10 minutes.
	 *
	 * @throws ApiException When the request fails.
	 */
	private function quote_parcel( array $body ): array {
		$cache_key = 'wpruby_cps_rates_' . md5( $this->settings->fingerprint() . Client::encode( $body ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$quotes = array();
		foreach ( $this->client->get_rates( $body ) as $raw ) {
			$quote = self::normalize( $raw );
			if ( null !== $quote ) {
				$quotes[ $quote['code'] ] = $quote;
			}
		}

		set_transient( $cache_key, $quotes, self::CACHE_SECONDS );
		return $quotes;
	}

	/**
	 * The parts of a quote the checkout needs. Quotes without a service code or a due
	 * amount are skipped rather than guessed.
	 *
	 * @param array $raw One quote from Canada Post.
	 */
	public static function normalize( array $raw ): ?array {
		$code = (string) ( $raw['serviceCode'] ?? '' );
		if ( '' === $code || ! isset( $raw['priceDetails']['due'] ) || ! is_numeric( $raw['priceDetails']['due'] ) ) {
			return null;
		}

		$taxes = 0.0;
		foreach ( array( 'gst', 'pst', 'hst' ) as $tax ) {
			$amount = $raw['priceDetails']['taxes'][ $tax ] ?? 0;
			if ( is_array( $amount ) ) {
				$amount = $amount['amt'] ?? ( $amount['amount'] ?? 0 );
			}
			$taxes += is_numeric( $amount ) ? (float) $amount : 0.0;
		}

		$transit = $raw['serviceStandard']['expectedTransitTime'] ?? null;

		return array(
			'code'          => $code,
			'name'          => (string) ( $raw['serviceName'] ?? '' ),
			'due'           => (float) $raw['priceDetails']['due'],
			'taxes'         => $taxes,
			'transit'       => is_numeric( $transit ) ? (int) $transit : null,
			'delivery_date' => (string) ( $raw['serviceStandard']['expectedDeliveryDate'] ?? '' ),
		);
	}

	/**
	 * @param int|null $a Days.
	 * @param int|null $b Days.
	 */
	private static function max_or_null( $a, $b ): ?int {
		// When any parcel has no estimate, the order has none either.
		return null === $a || null === $b ? null : max( $a, $b );
	}
}
