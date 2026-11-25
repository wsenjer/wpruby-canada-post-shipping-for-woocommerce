<?php

use WPRuby\CanadaPostShipping\Services;
use WPRuby\CanadaPostShipping\Settings;
use WPRuby\CanadaPostShipping\Shipping\Method;

final class MethodTest extends CpsTestCase {

	private function method( array $zone = array(), array $store = array() ): Method {
		$method                    = new Method( 7 );
		$method->instance_settings = $zone;
		$method->tax_status        = $zone['tax_status'] ?? 'taxable';
		$method->settings_object   = $this->settings( $store );
		return $method;
	}

	private function package( string $country = 'CA', string $postcode = 'V6B 1A1' ): array {
		return array(
			'contents'    => array( array( 'data' => new WC_Product( array( 'weight' => 1 ) ), 'quantity' => 1 ) ),
			'destination' => array( 'country' => $country, 'postcode' => $postcode ),
		);
	}

	public function test_checkout_shows_the_quoted_services_cheapest_first_before_tax(): void {
		$this->token();
		TestState::respond(
			200,
			array(
				$this->quote( 'DOM.XP', 28.25, 3.25, 1 ),
				$this->quote( 'DOM.RP', 15.42, 1.77, 4 ),
				$this->quote( 'DOM.EP', 18.08, 2.08, 1 ),
			)
		);

		$method = $this->method();
		$method->calculate_shipping( $this->package() );

		$this->assertSame( array( 'canada_post:7:DOM.RP', 'canada_post:7:DOM.EP', 'canada_post:7:DOM.XP' ), array_keys( $method->rates ) );
		$this->assertSame( 13.65, $method->rates['canada_post:7:DOM.RP']['cost'] );
		$this->assertSame( 'Regular Parcel (est. 4 business days)', $method->rates['canada_post:7:DOM.RP']['label'] );
		$this->assertSame( 'Expedited Parcel (est. 1 business day)', $method->rates['canada_post:7:DOM.EP']['label'] );
		$this->assertSame( 'DOM.RP', $method->rates['canada_post:7:DOM.RP']['meta_data']['_canada_post_service'] );

		$body = $this->rate_body();
		$this->assertSame( array( 'DOM.RP', 'DOM.EP', 'DOM.XP', 'DOM.PC' ), $body['services'] );
		$this->assertSame( 'counter', $body['quoteType'] );
	}

	public function test_untaxed_shipping_charges_canada_posts_price_with_tax(): void {
		$this->token();
		TestState::respond( 200, array( $this->quote( 'DOM.RP', 15.42, 1.77 ) ) );

		$method = $this->method( array( 'tax_status' => 'none', 'show_transit' => 'no' ) );
		$method->calculate_shipping( $this->package() );

		$this->assertSame( 15.42, $method->rates['canada_post:7:DOM.RP']['cost'] );
		$this->assertSame( 'Regular Parcel', $method->rates['canada_post:7:DOM.RP']['label'] );
	}

	public function test_handling_fees_and_renamed_services(): void {
		$this->token();
		TestState::respond( 200, array( $this->quote( 'DOM.EP', 20.00, 0 ) ) );

		$services                     = Services::defaults();
		$services['DOM.EP']['name']   = 'Fast shipping';
		$method = $this->method( array( 'services' => $services, 'handling_fee' => '2', 'handling_fee_percent' => '10', 'show_transit' => 'no' ) );
		$method->calculate_shipping( $this->package() );

		$this->assertSame( 24.2, $method->rates['canada_post:7:DOM.EP']['cost'] );
		$this->assertSame( 'Fast shipping', $method->rates['canada_post:7:DOM.EP']['label'] );
	}

	public function test_only_ticked_services_for_the_destination_are_requested(): void {
		$this->token();
		TestState::respond( 200, array( $this->quote( 'USA.EP', 30.00 ) ) );

		$services                       = Services::defaults();
		$services['USA.XP']['enabled']  = false;
		$method = $this->method( array( 'services' => $services ) );
		$method->calculate_shipping( $this->package( 'US', '10001' ) );

		$this->assertSame( array( 'USA.EP', 'USA.TP', 'USA.SP.AIR' ), $this->rate_body()['services'] );
		$this->assertSame( array( 'unitedStates' => array( 'zipCode' => '10001' ) ), $this->rate_body()['destination'] );
	}

	public function test_a_service_canada_post_did_not_quote_is_not_offered(): void {
		$this->token();
		TestState::respond( 200, array( $this->quote( 'DOM.LIB', 5.00 ), $this->quote( 'DOM.RP', 15.42 ) ) );

		$method = $this->method();
		$method->calculate_shipping( $this->package() );

		$this->assertSame( array( 'canada_post:7:DOM.RP' ), array_keys( $method->rates ) );
	}

	public function test_test_app_rates_are_for_shop_managers_only(): void {
		$method = $this->method( array(), array( 'app_type' => Settings::APP_TEST ) );
		$method->calculate_shipping( $this->package() );
		$this->assertSame( array(), $method->rates );
		$this->assertSame( array(), TestState::$requests );

		TestState::$can = true;
		$this->token();
		TestState::respond( 200, array( $this->quote( 'DOM.RP', 15.42 ) ) );
		$method->calculate_shipping( $this->package() );
		$this->assertCount( 1, $method->rates );
	}

	public function test_nothing_is_requested_without_an_account_or_an_address(): void {
		$this->method( array(), array( 'api_key' => '' ) )->calculate_shipping( $this->package() );
		$this->method()->calculate_shipping( $this->package( 'CA', '' ) );
		$this->method()->calculate_shipping( $this->package( '', '' ) );

		$this->assertSame( array(), TestState::$requests );
	}

	public function test_errors_reach_shop_managers_only_in_debug_mode(): void {
		TestState::$can = true;
		$this->method()->calculate_shipping( $this->package( 'CA', '12345' ) );
		$this->assertSame( array(), TestState::$notices );

		$this->method( array(), array( 'debug' => 'yes' ) )->calculate_shipping( $this->package( 'CA', '12345' ) );
		$this->assertSame( 'error', TestState::$notices[0][0] );
		$this->assertStringContainsString( 'valid Canadian postal code', TestState::$notices[0][1] );
		$this->assertSame( 'error', TestState::$logs[0][0] );
	}

	public function test_business_rates_follow_the_account_type_unless_the_zone_says_otherwise(): void {
		$this->token();
		TestState::respond( 200, array( $this->quote( 'DOM.RP', 15.42 ) ) );
		TestState::respond( 200, array( $this->quote( 'DOM.RP', 15.42 ) ) );

		$this->method( array(), array( 'account_type' => 'commercial', 'contract_number' => '42' ) )->calculate_shipping( $this->package() );
		$this->method( array( 'quote_type' => 'counter' ), array( 'account_type' => 'commercial', 'contract_number' => '42' ) )->calculate_shipping( $this->package( 'CA', 'H2X 1Y4' ) );

		$this->assertSame( 'commercial', $this->rate_body( 0 )['quoteType'] );
		$this->assertSame( '42', $this->rate_body( 0 )['contractId'] );
		$this->assertSame( 'counter', $this->rate_body( 1 )['quoteType'] );
	}

	public function test_a_store_in_another_currency_converts_with_its_rate(): void {
		TestState::$currency = 'USD';
		$this->token();
		TestState::respond( 200, array( $this->quote( 'DOM.RP', 20.00, 0 ) ) );

		$method = $this->method( array(), array( 'exchange_rate' => '0.72' ) );
		$method->calculate_shipping( $this->package() );

		$this->assertSame( 14.4, $method->rates['canada_post:7:DOM.RP']['cost'] );
	}

	public function test_saved_services_keep_canada_post_pro_fields(): void {
		$saved = ( new Method( 7 ) )->validate_wpruby_cps_services_field(
			'services',
			array(
				'DOM.RP'  => array( 'enabled' => '1', 'name' => ' <b>Standard</b> ', 'adjustment' => '-2.5', 'adjustment_type' => 'percent', 'order' => '3' ),
				'BOGUS'   => array( 'enabled' => '1' ),
			)
		);

		$this->assertSame( array_keys( Services::REGIONS ), array_keys( $saved ) );
		$this->assertSame( array( 'enabled' => true, 'name' => 'Standard', 'adjustment' => '-2.5', 'adjustment_type' => 'percent', 'order' => 3 ), $saved['DOM.RP'] );
		$this->assertFalse( $saved['DOM.EP']['enabled'] );
	}
}
