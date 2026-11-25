<?php
/**
 * WooCommerce → Settings → Shipping → Canada Post: the store-wide settings.
 *
 * @package WPRuby\CanadaPostShipping
 */

namespace WPRuby\CanadaPostShipping\Shipping;

use WC_Shipping_Method;
use WPRuby\CanadaPostShipping\Admin\Upsell;
use WPRuby\CanadaPostShipping\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Not a method customers can see: it only owns the store-wide settings section
 * (account, origin, packing, debug). Rates come from the Canada Post method in each zone.
 */
class SettingsSection extends WC_Shipping_Method {

	const ID = 'canada_post_settings';

	public function __construct() {
		$this->id                 = self::ID;
		$this->method_title       = __( 'Canada Post', 'wpruby-canada-post-shipping-for-woocommerce' );
		$this->method_description = __( 'Connect your Canada Post account and set where parcels ship from. Then add the Canada Post method to your shipping zones.', 'wpruby-canada-post-shipping-for-woocommerce' );
		$this->supports           = array( 'settings' );
		$this->enabled            = 'no';

		$this->init_form_fields();
		$this->init_settings();

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Never offered at checkout.
	 *
	 * @param array $package Package.
	 */
	public function is_available( $package ) {
		return false;
	}

	public function init_form_fields() {
		$weight_unit    = (string) get_option( 'woocommerce_weight_unit', 'kg' );
		$dimension_unit = (string) get_option( 'woocommerce_dimension_unit', 'cm' );
		$from_config    = defined( 'WPRUBY_CPS_API_KEY' ) && defined( 'WPRUBY_CPS_API_SECRET' );

		$this->form_fields = array(
			'account_title'    => array(
				'title'       => __( 'Canada Post account', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'type'        => 'title',
				'description' => wp_kses_post(
					sprintf(
						/* translators: %s: link to the Canada Post Developer Portal */
						__( 'Create an app on the %s, add the Rating API product to it, and copy its API key and secret here.', 'wpruby-canada-post-shipping-for-woocommerce' ),
						'<a href="https://developer-developpeur.canadapost-postescanada.ca/devportal-portaildesdeveloppeurs/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Canada Post Developer Portal', 'wpruby-canada-post-shipping-for-woocommerce' ) . '</a>'
					)
				),
			),
			'app_type'         => array(
				'title'       => __( 'App type', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'type'        => 'select',
				'default'     => Settings::APP_PRODUCTION,
				'options'     => array(
					Settings::APP_PRODUCTION => __( 'Production app (real rates)', 'wpruby-canada-post-shipping-for-woocommerce' ),
					Settings::APP_TEST       => __( 'Test app (sample rates, shown to shop managers only)', 'wpruby-canada-post-shipping-for-woocommerce' ),
				),
				'description' => __( 'Canada Post decides the type when you create the app. A Test app returns sample prices that are not your real rates.', 'wpruby-canada-post-shipping-for-woocommerce' ),
			),
			'api_key'          => array(
				'title'       => __( 'API key', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'disabled'    => $from_config,
				'description' => $from_config ? __( 'Set in wp-config.php (WPRUBY_CPS_API_KEY).', 'wpruby-canada-post-shipping-for-woocommerce' ) : '',
			),
			'api_secret'       => array(
				'title'       => __( 'API secret', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'type'        => 'wpruby_cps_secret',
				'default'     => '',
				'disabled'    => $from_config,
				'description' => $from_config ? __( 'Set in wp-config.php (WPRUBY_CPS_API_SECRET).', 'wpruby-canada-post-shipping-for-woocommerce' ) : __( 'Stored in your database. Leave empty to keep the saved secret.', 'wpruby-canada-post-shipping-for-woocommerce' ),
			),
			'customer_number'  => array(
				'title'       => __( 'Customer number', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'Your Canada Post customer number (up to 10 digits), shown in your Canada Post business account.', 'wpruby-canada-post-shipping-for-woocommerce' ),
			),
			'account_type'     => array(
				'title'   => __( 'Account type', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'type'    => 'select',
				'default' => 'small_business',
				'options' => array(
					'small_business' => __( 'Solutions for Small Business (retail rates)', 'wpruby-canada-post-shipping-for-woocommerce' ),
					'commercial'     => __( 'Commercial contract (business rates)', 'wpruby-canada-post-shipping-for-woocommerce' ),
				),
			),
			'contract_number'  => array(
				'title'       => __( 'Contract number', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'Commercial accounts only. Leave empty for Solutions for Small Business.', 'wpruby-canada-post-shipping-for-woocommerce' ),
			),
			'origin_postcode'  => array(
				'title'       => __( 'Ship-from postal code', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'placeholder' => 'K1A 0B1',
				'description' => __( 'Where your parcels leave from. Leave empty to use the store address when it is in Canada.', 'wpruby-canada-post-shipping-for-woocommerce' ),
			),

			'packing_title'    => array(
				'title' => __( 'Parcels', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'type'  => 'title',
			),
			'packing'          => array(
				'title'   => __( 'Packing', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'type'    => 'select',
				'default' => 'single',
				'options' => array(
					'single'     => __( 'Pack items together (split into more parcels by weight)', 'wpruby-canada-post-shipping-for-woocommerce' ),
					'individual' => __( 'Ship each item in its own parcel', 'wpruby-canada-post-shipping-for-woocommerce' ),
				),
			),
			'max_weight'       => array(
				'title'       => __( 'Maximum parcel weight (kg)', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'type'        => 'decimal',
				'default'     => '30',
				'description' => __( 'When packing items together, a new parcel starts above this weight. Canada Post\'s limit is 30 kg.', 'wpruby-canada-post-shipping-for-woocommerce' ),
			),
			'default_weight'   => array(
				/* translators: %s: weight unit */
				'title'       => sprintf( __( 'Default weight (%s)', 'wpruby-canada-post-shipping-for-woocommerce' ), $weight_unit ),
				'type'        => 'decimal',
				'default'     => '0.5',
				'description' => __( 'Used for products with no weight.', 'wpruby-canada-post-shipping-for-woocommerce' ),
			),
			'default_length'   => array(
				/* translators: %s: dimension unit */
				'title'   => sprintf( __( 'Default length (%s)', 'wpruby-canada-post-shipping-for-woocommerce' ), $dimension_unit ),
				'type'    => 'decimal',
				'default' => '',
			),
			'default_width'    => array(
				/* translators: %s: dimension unit */
				'title'   => sprintf( __( 'Default width (%s)', 'wpruby-canada-post-shipping-for-woocommerce' ), $dimension_unit ),
				'type'    => 'decimal',
				'default' => '',
			),
			'default_height'   => array(
				/* translators: %s: dimension unit */
				'title'       => sprintf( __( 'Default height (%s)', 'wpruby-canada-post-shipping-for-woocommerce' ), $dimension_unit ),
				'type'        => 'decimal',
				'default'     => '',
				'description' => __( 'Used for products with no size. Leave empty to quote those products by weight only.', 'wpruby-canada-post-shipping-for-woocommerce' ),
			),
			'packaging_weight' => array(
				/* translators: %s: weight unit */
				'title'       => sprintf( __( 'Packaging weight (%s)', 'wpruby-canada-post-shipping-for-woocommerce' ), $weight_unit ),
				'type'        => 'decimal',
				'default'     => '',
				'description' => __( 'Added to every parcel for the box and padding.', 'wpruby-canada-post-shipping-for-woocommerce' ),
			),

			'other_title'      => array(
				'title' => __( 'Other', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'type'  => 'title',
			),
			'exchange_rate'    => array(
				'title'       => __( 'CAD exchange rate', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'type'        => 'decimal',
				'default'     => '1',
				/* translators: %s: store currency code */
				'description' => sprintf( __( 'Canada Post quotes in CAD. Your store uses %s, so rates are multiplied by this number. Ignored when the store uses CAD.', 'wpruby-canada-post-shipping-for-woocommerce' ), get_woocommerce_currency() ),
			),
			'debug'            => array(
				'title'       => __( 'Debug mode', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Show Canada Post errors on the cart and checkout to shop managers, and log them', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'default'     => 'no',
				'description' => __( 'Logs are in WooCommerce → Status → Logs (source: wpruby-canada-post). Customers never see these messages.', 'wpruby-canada-post-shipping-for-woocommerce' ),
			),
		);
	}

	/**
	 * A password field that never prints the saved secret.
	 *
	 * @param string $key  Field key.
	 * @param array  $data Field definition.
	 */
	public function generate_wpruby_cps_secret_html( $key, $data ): string {
		$field = $this->get_field_key( $key );
		$saved = '' !== (string) $this->get_option( $key );
		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><label for="<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $data['title'] ); ?></label></th>
			<td class="forminp">
				<input type="password" class="input-text regular-input" id="<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $saved ? __( 'Saved (hidden)', 'wpruby-canada-post-shipping-for-woocommerce' ) : '' ); ?>" <?php disabled( ! empty( $data['disabled'] ) ); ?>>
				<?php if ( ! empty( $data['description'] ) ) : ?>
					<p class="description"><?php echo esc_html( $data['description'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * An empty secret keeps the saved one.
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Posted value.
	 */
	public function validate_wpruby_cps_secret_field( $key, $value ): string {
		$value = trim( sanitize_text_field( wp_unslash( (string) $value ) ) );
		return '' === $value ? (string) $this->get_option( $key ) : $value;
	}

	/**
	 * A postal code Canada Post accepts, or an error and the previous value.
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Posted value.
	 * @throws \Exception For a postal code that isn't Canadian.
	 */
	public function validate_origin_postcode_field( $key, $value ): string {
		$value = strtoupper( (string) preg_replace( '/\s+/', '', sanitize_text_field( wp_unslash( (string) $value ) ) ) );
		if ( '' !== $value && ! preg_match( '/^[A-Z]\d[A-Z]\d[A-Z]\d$/', $value ) ) {
			throw new \Exception( esc_html__( 'The ship-from postal code must be a Canadian postal code, like K1A 0B1. The previous value was kept.', 'wpruby-canada-post-shipping-for-woocommerce' ) );
		}
		return '' === $value ? '' : substr( $value, 0, 3 ) . ' ' . substr( $value, 3 );
	}

	/**
	 * Digits only.
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Posted value.
	 * @throws \Exception For more than 10 digits.
	 */
	public function validate_customer_number_field( $key, $value ): string {
		$digits = (string) preg_replace( '/\D/', '', (string) $value );
		if ( strlen( $digits ) > 10 ) {
			throw new \Exception( esc_html__( 'The customer number has at most 10 digits. The previous value was kept.', 'wpruby-canada-post-shipping-for-woocommerce' ) );
		}
		return $digits;
	}

	/**
	 * Digits only.
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Posted value.
	 */
	public function validate_contract_number_field( $key, $value ): string {
		return (string) preg_replace( '/\D/', '', (string) $value );
	}

	/**
	 * The settings, a "Test connection" button and what Pro adds.
	 */
	public function admin_options() {
		parent::admin_options();
		?>
		<h2><?php esc_html_e( 'Check the connection', 'wpruby-canada-post-shipping-for-woocommerce' ); ?></h2>
		<p><?php esc_html_e( 'Save your settings first. The check asks Canada Post for a Regular Parcel rate from your postal code to itself.', 'wpruby-canada-post-shipping-for-woocommerce' ); ?></p>
		<p>
			<button type="button" class="button" id="wpruby-cps-test-connection"><?php esc_html_e( 'Test connection', 'wpruby-canada-post-shipping-for-woocommerce' ); ?></button>
			<span id="wpruby-cps-test-result" role="status" aria-live="polite"></span>
		</p>
		<?php
		Upsell::card( 'settings' );
	}
}
