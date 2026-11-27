<?php
/**
 * Runs the unit tests without WordPress: just enough WordPress and WooCommerce
 * stand-ins for the plugin's classes, with HTTP responses queued by the tests.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPRUBY_CPS_VERSION', 'test' );
define( 'WPRUBY_CPS_FILE', dirname( __DIR__ ) . '/wpruby-shipping-rates-canada-post.php' );
define( 'WPRUBY_CPS_DIR', dirname( __DIR__ ) . '/' );
define( 'WPRUBY_CPS_URL', 'https://example.test/wp-content/plugins/wpruby-shipping-rates-canada-post/' );

class TestState {
	public static $options;
	public static $transients;
	public static $http;
	public static $requests;
	public static $can;
	public static $currency;
	public static $locale;
	public static $base;
	public static $notices;
	public static $logs;

	public static function reset(): void {
		self::$options    = array(
			'woocommerce_weight_unit'    => 'kg',
			'woocommerce_dimension_unit' => 'cm',
		);
		self::$transients = array();
		self::$http       = array();
		self::$requests   = array();
		self::$can        = false;
		self::$currency   = 'CAD';
		self::$locale     = 'en_CA';
		self::$base       = array( 'country' => 'CA', 'postcode' => 'K1A 0B1' );
		self::$notices    = array();
		self::$logs       = array();
	}

	/**
	 * Queue an HTTP response. $body is JSON-encoded unless it's a string.
	 *
	 * @param int          $status HTTP status.
	 * @param array|string $body   Body.
	 */
	public static function respond( int $status, $body ): void {
		self::$http[] = array(
			'response' => array( 'code' => $status ),
			'body'     => is_string( $body ) ? $body : json_encode( $body ),
		);
	}

	public static function fail( string $message ): void {
		self::$http[] = new WP_Error( 'http_request_failed', $message );
	}

	/**
	 * Requests to a URL path ending, e.g. "/prices".
	 */
	public static function requests_to( string $suffix ): array {
		return array_values(
			array_filter(
				self::$requests,
				static function ( $request ) use ( $suffix ) {
					return substr( $request['url'], -strlen( $suffix ) ) === $suffix;
				}
			)
		);
	}
}

class WP_Error {
	private $message;
	public function __construct( $code = '', $message = '' ) {
		$this->message = $message;
	}
	public function get_error_message() {
		return $this->message;
	}
}

// ---- WordPress stand-ins -----------------------------------------------------

function __( $text, $domain = null ) { return $text; }
function _x( $text, $context, $domain = null ) { return $text; }
function _n( $single, $plural, $number, $domain = null ) { return 1 === (int) $number ? $single : $plural; }
function esc_html__( $text, $domain = null ) { return $text; }
function esc_html_e( $text, $domain = null ) { echo $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_url( $url ) { return (string) $url; }
function wp_kses_post( $html ) { return (string) $html; }
function checked( $checked, $current = true, $echo = true ) { $out = $checked == $current ? ' checked="checked"' : ''; if ( $echo ) { echo $out; } return $out; }
function disabled( $disabled, $current = true, $echo = true ) { $out = $disabled == $current ? ' disabled="disabled"' : ''; if ( $echo ) { echo $out; } return $out; }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function wp_unslash( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
function wp_specialchars_decode( $text, $quotes = ENT_NOQUOTES ) { return htmlspecialchars_decode( (string) $text, ENT_QUOTES ); }
function wp_json_encode( $data ) { return json_encode( $data ); }
function number_format_i18n( $number, $decimals = 0 ) { return number_format( (float) $number, $decimals ); }
function get_locale() { return TestState::$locale; }
function add_action( ...$args ) { return true; }
function add_filter( ...$args ) { return true; }
function add_query_arg( array $args, string $url ) { return $url . '?' . http_build_query( $args ); }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function current_user_can( $capability ) { return TestState::$can; }
function is_multisite() { return false; }
function get_site_option( $name, $default = false ) { return $default; }
function get_option( $name, $default = false ) { return TestState::$options[ $name ] ?? $default; }
function update_option( $name, $value, $autoload = null ) { TestState::$options[ $name ] = $value; return true; }
function get_transient( $key ) { return TestState::$transients[ $key ] ?? false; }
function set_transient( $key, $value, $ttl = 0 ) { TestState::$transients[ $key ] = $value; return true; }
function delete_transient( $key ) { unset( TestState::$transients[ $key ] ); return true; }
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

function wp_remote_post( $url, $args = array() ) {
	TestState::$requests[] = array( 'url' => $url, 'args' => $args );
	$response = array_shift( TestState::$http );
	if ( null === $response ) {
		throw new RuntimeException( 'No queued HTTP response for ' . $url );
	}
	return $response;
}
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }

// ---- WooCommerce stand-ins ---------------------------------------------------

class FakeCountries {
	public function get_base_country() { return TestState::$base['country']; }
	public function get_base_postcode() { return TestState::$base['postcode']; }
}
class FakeLogger {
	public function log( $level, $message, $context = array() ) { TestState::$logs[] = array( $level, $message ); }
}
class FakeWooCommerce {
	public $countries;
	public $session = true;
	public function __construct() { $this->countries = new FakeCountries(); }
}
function WC() {
	static $wc;
	return $wc ?: $wc = new FakeWooCommerce();
}
function wc_get_logger() { return new FakeLogger(); }
function wc_add_notice( $message, $type = 'notice' ) { TestState::$notices[] = array( $type, $message ); }
function wc_has_notice( $message, $type = 'notice' ) { return in_array( array( $type, $message ), TestState::$notices, true ); }
function get_woocommerce_currency() { return TestState::$currency; }
function wc_get_price_decimals() { return 2; }
function wc_get_weight( $weight, $to_unit, $from_unit = '' ) {
	$grams = array( 'kg' => 1000, 'g' => 1, 'lbs' => 453.59237, 'oz' => 28.349523125 );
	$from  = $from_unit ?: get_option( 'woocommerce_weight_unit' );
	return (float) $weight * $grams[ $from ] / $grams[ $to_unit ];
}
function wc_get_dimension( $dimension, $to_unit, $from_unit = '' ) {
	$mm   = array( 'm' => 1000, 'cm' => 10, 'mm' => 1, 'in' => 25.4, 'yd' => 914.4 );
	$from = $from_unit ?: get_option( 'woocommerce_dimension_unit' );
	return (float) $dimension * $mm[ $from ] / $mm[ $to_unit ];
}

class WC_Product {
	private $props;
	public function __construct( array $props = array() ) {
		$this->props = $props + array( 'weight' => '', 'length' => '', 'width' => '', 'height' => '', 'virtual' => false );
	}
	public function get_weight() { return $this->props['weight']; }
	public function get_length() { return $this->props['length']; }
	public function get_width() { return $this->props['width']; }
	public function get_height() { return $this->props['height']; }
	public function needs_shipping() { return ! $this->props['virtual']; }
}

/** Just enough of WooCommerce's shipping method base class. */
abstract class WC_Shipping_Method {
	public $id;
	public $instance_id = 0;
	public $method_title;
	public $method_description;
	public $title;
	public $enabled = 'yes';
	public $tax_status = 'taxable';
	public $supports = array();
	public $rates = array();
	public $form_fields = array();
	public $instance_form_fields = array();
	public $instance_settings = array();
	public $settings = array();
	public function init_settings() {}
	public function get_instance_form_fields() { return $this->instance_form_fields; }
	public function get_option( $key, $empty_value = null ) {
		$fields = $this->instance_id ? $this->instance_form_fields : $this->form_fields;
		$values = $this->instance_id ? $this->instance_settings : $this->settings;
		return $values[ $key ] ?? ( $fields[ $key ]['default'] ?? $empty_value );
	}
	public function get_field_key( $key ) { return 'woocommerce_' . $this->id . '_' . $key; }
	public function get_rate_id( $suffix = '' ) { return implode( ':', array_filter( array( $this->id, $this->instance_id, $suffix ) ) ); }
	public function add_rate( $args = array() ) { $this->rates[ $args['id'] ] = $args; }
	public function admin_options() {}
}

TestState::reset();

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'WPRuby\\CanadaPostShipping\\';
		if ( 0 === strpos( $class, $prefix ) ) {
			require dirname( __DIR__ ) . '/src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		}
	}
);

require __DIR__ . '/TestCase.php';
