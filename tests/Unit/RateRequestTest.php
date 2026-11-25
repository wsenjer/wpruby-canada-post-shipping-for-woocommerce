<?php

use WPRuby\CanadaPostShipping\Api\ApiException;
use WPRuby\CanadaPostShipping\Rates\RateRequest;

final class RateRequestTest extends CpsTestCase {

	public function test_a_domestic_request_has_everything_canada_post_needs(): void {
		$body = RateRequest::build(
			$this->settings(),
			array( 'weight' => 1.2345, 'length' => 30.04, 'width' => 20, 'height' => 10 ),
			'CA',
			'v6b 1a1',
			array( 'DOM.RP', 'DOM.EP', 'DOM.RP' ),
			'counter'
		);

		$this->assertSame(
			array(
				'customerNumber'        => '0001234567',
				'quoteType'             => 'counter',
				'originPostalCode'      => 'K1A0B1',
				'destination'           => array( 'domestic' => array( 'postalCode' => 'V6B1A1' ) ),
				'parcelCharacteristics' => array(
					'weight'     => 1.235,
					'dimensions' => array( 'length' => 30.0, 'width' => 20.0, 'height' => 10.0 ),
				),
				'services'              => array( 'DOM.RP', 'DOM.EP' ),
			),
			$body
		);
	}

	public function test_the_contract_is_sent_only_for_business_rates(): void {
		$settings = $this->settings( array( 'account_type' => 'commercial', 'contract_number' => '0040-012345' ) );

		$business = RateRequest::build( $settings, array( 'weight' => 1 ), 'CA', 'V6B1A1', array(), 'commercial' );
		$retail   = RateRequest::build( $settings, array( 'weight' => 1 ), 'CA', 'V6B1A1', array(), 'counter' );

		$this->assertSame( '0040012345', $business['contractId'] );
		$this->assertArrayNotHasKey( 'contractId', $retail );
		$this->assertArrayNotHasKey( 'services', $retail );
	}

	public function test_us_and_international_destinations(): void {
		$this->assertSame( array( 'unitedStates' => array( 'zipCode' => '10001-1234' ) ), RateRequest::destination( 'US', ' 10001-1234 ' ) );
		$this->assertSame( array( 'international' => array( 'countryCode' => 'GB', 'postalCode' => 'SW1A 1AA' ) ), RateRequest::destination( 'gb', 'SW1A 1AA' ) );
		$this->assertSame( array( 'international' => array( 'countryCode' => 'AE' ) ), RateRequest::destination( 'AE', '' ) );
	}

	public function test_bad_input_is_caught_before_anything_is_sent(): void {
		$cases = array(
			array( array(), array( 'weight' => 1 ), 'CA', '12345', 'Canadian postal code' ),
			array( array(), array( 'weight' => 1 ), 'US', 'ABC', 'ZIP code' ),
			array( array(), array( 'weight' => 0 ), 'CA', 'V6B1A1', 'weight' ),
			array( array(), array( 'weight' => 120 ), 'CA', 'V6B1A1', 'weight' ),
			array( array( 'origin_postcode' => '90210' ), array( 'weight' => 1 ), 'CA', 'V6B1A1', 'origin postal code' ),
			array( array( 'customer_number' => '' ), array( 'weight' => 1 ), 'CA', 'V6B1A1', 'customer number' ),
		);
		foreach ( $cases as list( $overrides, $parcel, $country, $postcode, $expected ) ) {
			try {
				RateRequest::build( $this->settings( $overrides ), $parcel, $country, $postcode, array(), 'counter' );
				$this->fail( "Accepted $country $postcode" );
			} catch ( ApiException $e ) {
				$this->assertSame( 'VALIDATION', $e->error_code() );
				$this->assertStringContainsString( $expected, $e->getMessage() );
			}
		}
		$this->assertSame( array(), TestState::$requests );
	}

	public function test_the_origin_falls_back_to_a_canadian_store_address(): void {
		$this->assertSame( 'K1A0B1', $this->settings( array( 'origin_postcode' => '' ) )->origin_postcode() );

		TestState::$base = array( 'country' => 'US', 'postcode' => '10001' );
		$this->assertSame( '', $this->settings( array( 'origin_postcode' => '' ) )->origin_postcode() );
	}
}
