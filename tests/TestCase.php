<?php

use WPRuby\CanadaPostShipping\Settings;

abstract class CpsTestCase extends PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		TestState::reset();
	}

	protected function settings( array $overrides = array() ): Settings {
		return new Settings(
			array_merge(
				array(
					'app_type'        => 'production',
					'api_key'         => 'key-123',
					'api_secret'      => 'secret-456',
					'customer_number' => '0001234567',
					'account_type'    => 'small_business',
					'origin_postcode' => 'K1A 0B1',
					'packing'         => 'single',
					'default_weight'  => '0.5',
				),
				$overrides
			)
		);
	}

	protected function token(): void {
		TestState::respond( 200, array( 'token_type' => 'Bearer', 'access_token' => 'token-1', 'expires_in' => 3600 ) );
	}

	/**
	 * A quote in the shape Canada Post's Rating API returns.
	 */
	protected function quote( string $code, float $due, float $hst = 0.0, ?int $transit = 2, string $name = '' ): array {
		$quote = array(
			'serviceCode'  => $code,
			'serviceName'  => $name ?: $code,
			'priceDetails' => array(
				'base'  => round( $due - $hst, 2 ),
				'due'   => $due,
				'taxes' => array( 'hst' => array( 'amt' => $hst, 'percent' => 13 ) ),
			),
		);
		if ( null !== $transit ) {
			$quote['serviceStandard'] = array( 'expectedTransitTime' => $transit, 'expectedDeliveryDate' => '2026-11-27' );
		}
		return $quote;
	}

	/** The JSON body of the n-th request to Get Rates. */
	protected function rate_body( int $index = 0 ): array {
		return json_decode( TestState::requests_to( '/prices' )[ $index ]['args']['body'], true );
	}
}
