<?php
/**
 * An error from the Canada Post API, or a request that couldn't be sent.
 *
 * @package WPRuby\CanadaPostShipping
 */

namespace WPRuby\CanadaPostShipping\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Carries the HTTP status and Canada Post's error code, and a hint for setup problems.
 */
class ApiException extends \Exception {

	/**
	 * Canada Post's error code, or one of ours (AUTH, NETWORK, THROTTLED, VALIDATION).
	 *
	 * @var string
	 */
	private $error_code;

	/**
	 * HTTP status, 0 when no response was received.
	 *
	 * @var int
	 */
	private $status;

	public function __construct( string $message, string $error_code = '', int $status = 0 ) {
		parent::__construct( $message, $status );
		$this->error_code = $error_code;
		$this->status     = $status;
	}

	public function error_code(): string {
		return $this->error_code;
	}

	public function status(): int {
		return $this->status;
	}

	/**
	 * Whether the problem is on Canada Post's side or the network, rather than the request or setup.
	 */
	public function is_temporary(): bool {
		return 0 === $this->status || 429 === $this->status || $this->status >= 500;
	}

	/**
	 * A next step for the merchant, for errors caused by the setup.
	 */
	public function hint(): string {
		if ( 'AUTH' === $this->error_code || 401 === $this->status ) {
			return __( 'Check the API key and secret on WooCommerce → Settings → Shipping → Canada Post. They come from your app on the Canada Post Developer Portal.', 'wpruby-shipping-rates-canada-post' );
		}
		if ( 403 === $this->status ) {
			return __( 'Add the Rating API product to your app on the Canada Post Developer Portal, and check the customer number.', 'wpruby-shipping-rates-canada-post' );
		}
		if ( 'THROTTLED' === $this->error_code || 429 === $this->status ) {
			return __( 'Canada Post is limiting requests. Rates will be requested again in a minute.', 'wpruby-shipping-rates-canada-post' );
		}
		return '';
	}
}
