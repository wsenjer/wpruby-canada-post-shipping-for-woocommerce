<?php

use WPRuby\CanadaPostShipping\Api\ApiException;
use WPRuby\CanadaPostShipping\Api\Client;

final class ClientTest extends CpsTestCase {

	private function client( array $overrides = array() ): Client {
		return new Client( $this->settings( $overrides ) );
	}

	public function test_the_token_is_fetched_once_and_sent_as_a_bearer(): void {
		$this->token();
		TestState::respond( 200, array( $this->quote( 'DOM.RP', 15.42, 1.77 ) ) );
		TestState::respond( 200, array( $this->quote( 'DOM.EP', 18.00, 2.07 ) ) );

		$client = $this->client();
		$client->get_rates( array( 'weight' => 1 ) );
		$client->get_rates( array( 'weight' => 2 ) );

		$token = TestState::requests_to( '/oauth2/token' );
		$this->assertCount( 1, $token );
		$this->assertSame(
			array( 'grant_type' => 'client_credentials', 'scope' => 'merchant', 'client_id' => 'key-123', 'client_secret' => 'secret-456' ),
			$token[0]['args']['body']
		);

		$rate = TestState::requests_to( '/rating/v1/prices' );
		$this->assertCount( 2, $rate );
		$this->assertSame( 'https://api.canadapost-postescanada.ca/prod/devportal-portaildesdeveloppeurs/rating/v1/prices', $rate[0]['url'] );
		$this->assertSame( 'Bearer token-1', $rate[0]['args']['headers']['Authorization'] );
		$this->assertSame( 'application/json', $rate[0]['args']['headers']['Accept'] );
		$this->assertSame( 'en-CA', $rate[0]['args']['headers']['Accept-Language'] );
		$this->assertSame( 0, $rate[0]['args']['redirection'] );
	}

	public function test_french_sites_ask_for_french(): void {
		TestState::$locale = 'fr_CA';
		$this->token();
		TestState::respond( 200, array() );

		$this->client()->get_rates( array() );

		$this->assertSame( 'fr-CA', TestState::requests_to( '/prices' )[0]['args']['headers']['Accept-Language'] );
	}

	public function test_an_expired_token_is_replaced_once(): void {
		$this->token();
		TestState::respond( 401, array( 'httpCode' => '401', 'httpMessage' => 'Unauthorized' ) );
		TestState::respond( 200, array( 'token_type' => 'Bearer', 'access_token' => 'token-2', 'expires_in' => 3600 ) );
		TestState::respond( 200, array( $this->quote( 'DOM.RP', 15.42 ) ) );

		$quotes = $this->client()->get_rates( array() );

		$this->assertSame( 'DOM.RP', $quotes[0]['serviceCode'] );
		$this->assertSame( 'Bearer token-2', TestState::requests_to( '/prices' )[1]['args']['headers']['Authorization'] );
	}

	public function test_rejected_credentials_explain_what_to_check(): void {
		TestState::respond( 401, array( 'error' => 'invalid_client', 'error_description' => 'Client authentication failed' ) );

		try {
			$this->client()->get_rates( array() );
			$this->fail( 'No exception' );
		} catch ( ApiException $e ) {
			$this->assertSame( 'AUTH', $e->error_code() );
			$this->assertStringContainsString( 'Client authentication failed', $e->getMessage() );
			$this->assertStringContainsString( 'API key and secret', $e->hint() );
			$this->assertFalse( $e->is_temporary() );
		}
	}

	public function test_missing_credentials_send_nothing(): void {
		$this->expectException( ApiException::class );
		try {
			$this->client( array( 'api_secret' => '' ) )->get_rates( array() );
		} finally {
			$this->assertSame( array(), TestState::$requests );
		}
	}

	public function test_a_server_error_or_timeout_is_retried_once(): void {
		$this->token();
		TestState::respond( 503, 'Service Unavailable' );
		TestState::respond( 200, array( $this->quote( 'DOM.RP', 15.42 ) ) );
		$this->assertCount( 1, $this->client()->get_rates( array() ) );

		TestState::reset();
		$this->token();
		TestState::fail( 'cURL error 28: Operation timed out' );
		TestState::fail( 'cURL error 28: Operation timed out' );
		try {
			$this->client()->get_rates( array() );
			$this->fail( 'No exception' );
		} catch ( ApiException $e ) {
			$this->assertTrue( $e->is_temporary() );
			$this->assertCount( 2, TestState::requests_to( '/prices' ) );
		}
	}

	public function test_rate_limiting_pauses_requests_for_a_minute(): void {
		$this->token();
		TestState::respond( 429, array( 'httpCode' => '429', 'moreInformation' => 'Rejected by SLM Monitor' ) );

		$client = $this->client();
		try {
			$client->get_rates( array() );
			$this->fail( 'No exception' );
		} catch ( ApiException $e ) {
			$this->assertSame( 'THROTTLED', $e->error_code() );
		}

		try {
			$client->get_rates( array() );
			$this->fail( 'No exception' );
		} catch ( ApiException $e ) {
			$this->assertSame( 'THROTTLED', $e->error_code() );
		}
		$this->assertCount( 1, TestState::requests_to( '/prices' ) );
	}

	public function test_every_error_format_gives_a_readable_message(): void {
		$this->assertSame( 'E001 Invalid destination', Client::error_message( array( 'title' => 'Bad Request', 'errors' => array( array( 'errorCode' => 'E001', 'message' => 'Invalid destination' ) ) ), '', 400 ) );
		$this->assertSame( 'Rejected by SLM Monitor', Client::error_message( array( 'httpCode' => '429', 'httpMessage' => 'Too Many Requests', 'moreInformation' => 'Rejected by SLM Monitor' ), '', 429 ) );
		$this->assertSame( 'No service', Client::error_message( array( 'errorCode' => '9111', 'errorMessage' => 'No service' ), '', 400 ) );
		$this->assertSame( '9020 No services available', Client::error_message( array( array( 'messages' => array( array( 'code' => '9020', 'description' => 'No services available' ) ) ) ), '', 400 ) );
		$this->assertSame( 'HTTP 502 from Canada Post.', Client::error_message( null, '', 502 ) );
	}

	public function test_floats_are_sent_exactly(): void {
		$this->assertSame( '{"weight":4.4,"length":30.1}', Client::encode( array( 'weight' => 1.1 * 4, 'length' => 30.1 ) ) );
	}
}
