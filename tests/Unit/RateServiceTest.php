<?php

use WPRuby\CanadaPostShipping\Api\ApiException;
use WPRuby\CanadaPostShipping\Api\Client;
use WPRuby\CanadaPostShipping\Rates\RateService;

final class RateServiceTest extends CpsTestCase {

	private function service(): RateService {
		$settings = $this->settings();
		return new RateService( $settings, new Client( $settings ) );
	}

	public function test_a_quote_keeps_the_due_price_taxes_and_estimate(): void {
		$quote = RateService::normalize(
			array(
				'serviceCode'     => 'DOM.EP',
				'serviceName'     => 'Expedited Parcel',
				'priceDetails'    => array(
					'due'   => '18.08',
					'taxes' => array( 'gst' => array( 'amt' => 0.5 ), 'pst' => '0.40', 'hst' => array( 'amt' => 0 ) ),
				),
				'serviceStandard' => array( 'expectedTransitTime' => '3', 'expectedDeliveryDate' => '2026-11-30' ),
			)
		);

		$this->assertSame( array( 'code' => 'DOM.EP', 'name' => 'Expedited Parcel', 'due' => 18.08, 'taxes' => 0.9, 'transit' => 3, 'delivery_date' => '2026-11-30' ), $quote );
		$this->assertNull( RateService::normalize( array( 'serviceCode' => 'DOM.RP' ) ) );
		$this->assertNull( RateService::normalize( array( 'priceDetails' => array( 'due' => 5 ) ) ) );
	}

	public function test_several_parcels_add_up_and_only_services_quoted_for_all_are_kept(): void {
		$this->token();
		TestState::respond( 200, array( $this->quote( 'DOM.RP', 15.42, 1.77, 3 ), $this->quote( 'DOM.XP', 30.00, 3.45, 1 ) ) );
		TestState::respond( 200, array( $this->quote( 'DOM.RP', 20.10, 2.31, 4 ) ) );

		$quotes = $this->service()->quote( array( array( 'weight' => 20 ), array( 'weight' => 12 ) ), 'CA', 'V6B 1A1', array( 'DOM.RP', 'DOM.XP' ), 'counter' );

		$this->assertSame( array( 'DOM.RP' ), array_keys( $quotes ) );
		$this->assertSame( 35.52, $quotes['DOM.RP']['due'] );
		$this->assertSame( 4.08, $quotes['DOM.RP']['taxes'] );
		$this->assertSame( 4, $quotes['DOM.RP']['transit'] );
		$this->assertSame( 20, $this->rate_body( 0 )['parcelCharacteristics']['weight'] );
		$this->assertSame( 12, $this->rate_body( 1 )['parcelCharacteristics']['weight'] );
	}

	public function test_a_parcel_without_an_estimate_leaves_the_order_without_one(): void {
		$this->token();
		TestState::respond( 200, array( $this->quote( 'DOM.RP', 15.42, 0, 3 ) ) );
		TestState::respond( 200, array( $this->quote( 'DOM.RP', 15.42, 0, null ) ) );

		$quotes = $this->service()->quote( array( array( 'weight' => 1 ), array( 'weight' => 2 ) ), 'CA', 'V6B1A1', array( 'DOM.RP' ), 'counter' );

		$this->assertNull( $quotes['DOM.RP']['transit'] );
	}

	public function test_the_same_request_is_answered_from_the_cache(): void {
		$this->token();
		TestState::respond( 200, array( $this->quote( 'DOM.RP', 15.42 ) ) );

		$first  = $this->service()->quote( array( array( 'weight' => 1 ) ), 'CA', 'V6B1A1', array( 'DOM.RP' ), 'counter' );
		$second = $this->service()->quote( array( array( 'weight' => 1 ) ), 'CA', 'V6B1A1', array( 'DOM.RP' ), 'counter' );

		$this->assertSame( $first, $second );
		$this->assertCount( 1, TestState::requests_to( '/prices' ) );
	}

	public function test_a_failed_request_is_not_cached(): void {
		$this->token();
		TestState::respond( 400, array( 'errors' => array( array( 'errorCode' => 'E1', 'message' => 'Bad' ) ) ) );
		TestState::respond( 200, array( $this->quote( 'DOM.RP', 15.42 ) ) );

		try {
			$this->service()->quote( array( array( 'weight' => 1 ) ), 'CA', 'V6B1A1', array( 'DOM.RP' ), 'counter' );
			$this->fail( 'No exception' );
		} catch ( ApiException $e ) {
			$this->assertSame( 'E1', $e->error_code() );
		}

		$quotes = $this->service()->quote( array( array( 'weight' => 1 ) ), 'CA', 'V6B1A1', array( 'DOM.RP' ), 'counter' );
		$this->assertArrayHasKey( 'DOM.RP', $quotes );
	}
}
