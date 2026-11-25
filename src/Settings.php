<?php
/**
 * Store-wide settings: Canada Post account, origin, packing and debug.
 *
 * @package WPRuby\CanadaPostShipping
 */

namespace WPRuby\CanadaPostShipping;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only view of the settings saved on WooCommerce → Settings → Shipping → Canada Post.
 */
class Settings {

	/** Option written by the WooCommerce settings API for the settings section. */
	const OPTION = 'woocommerce_canada_post_settings_settings';

	const APP_PRODUCTION = 'production';
	const APP_TEST       = 'test';

	/**
	 * Saved values.
	 *
	 * @var array
	 */
	private $values;

	/**
	 * @param array|null $values Saved values; read from the database when null.
	 */
	public function __construct( ?array $values = null ) {
		if ( null === $values ) {
			$saved  = get_option( self::OPTION, array() );
			$values = is_array( $saved ) ? $saved : array();
		}
		$this->values = $values;
	}

	public function get( string $key, string $default = '' ): string {
		$value = $this->values[ $key ] ?? $default;
		return is_scalar( $value ) ? trim( (string) $value ) : $default;
	}

	/**
	 * The API key, from wp-config.php when WPRUBY_CPS_API_KEY is defined.
	 */
	public function api_key(): string {
		return defined( 'WPRUBY_CPS_API_KEY' ) ? trim( (string) WPRUBY_CPS_API_KEY ) : $this->get( 'api_key' );
	}

	/**
	 * The API secret, from wp-config.php when WPRUBY_CPS_API_SECRET is defined.
	 */
	public function api_secret(): string {
		return defined( 'WPRUBY_CPS_API_SECRET' ) ? trim( (string) WPRUBY_CPS_API_SECRET ) : $this->get( 'api_secret' );
	}

	public function has_credentials(): bool {
		return '' !== $this->api_key() && '' !== $this->api_secret() && '' !== $this->customer_number();
	}

	/**
	 * A Test app returns sample rates, so they're shown only to shop managers.
	 */
	public function is_test_app(): bool {
		return self::APP_TEST === $this->get( 'app_type', self::APP_PRODUCTION );
	}

	public function customer_number(): string {
		return (string) preg_replace( '/\D/', '', $this->get( 'customer_number' ) );
	}

	public function contract_number(): string {
		return (string) preg_replace( '/\D/', '', $this->get( 'contract_number' ) );
	}

	/**
	 * Commercial (contract) accounts get business rates by default; Solutions for
	 * Small Business accounts get retail (counter) rates.
	 */
	public function is_commercial_account(): bool {
		return 'commercial' === $this->get( 'account_type', 'small_business' );
	}

	/**
	 * Origin postal code without spaces, upper case. Falls back to the store address in Canada.
	 */
	public function origin_postcode(): string {
		$postcode = $this->get( 'origin_postcode' );
		if ( '' === $postcode && function_exists( 'WC' ) && WC()->countries && 'CA' === WC()->countries->get_base_country() ) {
			$postcode = (string) WC()->countries->get_base_postcode();
		}
		return strtoupper( (string) preg_replace( '/\s+/', '', $postcode ) );
	}

	/**
	 * "single": everything in one parcel, split by weight; "individual": one parcel per item.
	 */
	public function packing(): string {
		return 'individual' === $this->get( 'packing', 'single' ) ? 'individual' : 'single';
	}

	/**
	 * Heaviest parcel in kg when packing everything together (Canada Post's limit is 30 kg).
	 */
	public function max_weight_kg(): float {
		$value = (float) $this->get( 'max_weight', '30' );
		return $value > 0 ? min( $value, 30.0 ) : 30.0;
	}

	/**
	 * Weight for products without one, in the store's weight unit.
	 */
	public function default_weight(): float {
		return max( 0.0, (float) $this->get( 'default_weight', '0.5' ) );
	}

	/**
	 * Size for products without one, in the store's dimension unit: [length, width, height].
	 *
	 * @return float[]
	 */
	public function default_dimensions(): array {
		return array(
			max( 0.0, (float) $this->get( 'default_length', '0' ) ),
			max( 0.0, (float) $this->get( 'default_width', '0' ) ),
			max( 0.0, (float) $this->get( 'default_height', '0' ) ),
		);
	}

	/**
	 * Weight added to every parcel for the box and padding, in the store's weight unit.
	 */
	public function packaging_weight(): float {
		return max( 0.0, (float) $this->get( 'packaging_weight', '0' ) );
	}

	/**
	 * Canada Post quotes in CAD. For a store in another currency, rates are multiplied by this.
	 */
	public function exchange_rate(): float {
		$rate = (float) $this->get( 'exchange_rate', '1' );
		return $rate > 0 ? $rate : 1.0;
	}

	public function is_debug(): bool {
		return 'yes' === $this->get( 'debug', 'no' );
	}

	/**
	 * Identifies the credentials in cache keys without storing them.
	 */
	public function fingerprint(): string {
		return substr( hash( 'sha256', $this->api_key() . '|' . $this->api_secret() ), 0, 16 );
	}
}
