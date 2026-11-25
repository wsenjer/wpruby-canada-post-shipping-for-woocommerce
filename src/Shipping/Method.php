<?php
/**
 * The Canada Post method in a shipping zone.
 *
 * @package WPRuby\CanadaPostShipping
 */

namespace WPRuby\CanadaPostShipping\Shipping;

use WC_Shipping_Method;
use WPRuby\CanadaPostShipping\Api\ApiException;
use WPRuby\CanadaPostShipping\Api\Client;
use WPRuby\CanadaPostShipping\Rates\Packer;
use WPRuby\CanadaPostShipping\Rates\RateRequest;
use WPRuby\CanadaPostShipping\Rates\RateService;
use WPRuby\CanadaPostShipping\Services;
use WPRuby\CanadaPostShipping\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Live Canada Post rates for the zone. The method ID and the setting keys match
 * Canada Post Pro, so zones keep their settings after an upgrade.
 */
class Method extends WC_Shipping_Method {

	const ID = 'canada_post';

	/**
	 * Store settings; replaceable for tests.
	 *
	 * @var Settings|null
	 */
	public $settings_object;

	/**
	 * Rate service; replaceable for tests.
	 *
	 * @var RateService|null
	 */
	public $rate_service;

	/**
	 * @param int $instance_id Zone method instance.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = self::ID;
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Canada Post', 'wpruby-canada-post-shipping-for-woocommerce' );
		$this->method_description = __( 'Live Canada Post rates from your Canada Post account.', 'wpruby-canada-post-shipping-for-woocommerce' );
		$this->supports           = array( 'shipping-zones', 'instance-settings' );

		$this->init_instance_form_fields();
		$this->init_settings();

		$this->title      = $this->get_option( 'title', __( 'Canada Post', 'wpruby-canada-post-shipping-for-woocommerce' ) );
		$this->tax_status = $this->get_option( 'tax_status', 'taxable' );

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	private function init_instance_form_fields(): void {
		$this->instance_form_fields = array(
			'title'                => array(
				'title'       => __( 'Method title', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'type'        => 'text',
				'default'     => __( 'Canada Post', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'desc_tip'    => true,
				'description' => __( 'Names this method in the zone list. Customers see the service names, like Expedited Parcel.', 'wpruby-canada-post-shipping-for-woocommerce' ),
			),
			'tax_status'           => array(
				'title'       => __( 'Tax status', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'default'     => 'taxable',
				'options'     => array(
					'taxable' => __( 'Taxable', 'wpruby-canada-post-shipping-for-woocommerce' ),
					'none'    => _x( 'None', 'Tax status', 'wpruby-canada-post-shipping-for-woocommerce' ),
				),
				'description' => __( 'Canada Post prices include GST/HST/PST. When shipping is taxable, the rate is shown before tax and WooCommerce adds your tax. When it is not, the rate includes Canada Post\'s tax.', 'wpruby-canada-post-shipping-for-woocommerce' ),
			),
			'services'             => array(
				'title'   => __( 'Services', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'type'    => 'wpruby_cps_services',
				'default' => '',
			),
			'quote_type'           => array(
				'title'       => __( 'Rate type', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'default'     => 'account',
				'options'     => array(
					'account'    => __( 'From the account type', 'wpruby-canada-post-shipping-for-woocommerce' ),
					'commercial' => __( 'Business rates (your contract or Solutions for Small Business prices)', 'wpruby-canada-post-shipping-for-woocommerce' ),
					'counter'    => __( 'Retail rates (post office counter prices)', 'wpruby-canada-post-shipping-for-woocommerce' ),
				),
				'desc_tip'    => true,
				'description' => __( 'Business rates are what you pay. Retail rates are what a customer would pay at the counter.', 'wpruby-canada-post-shipping-for-woocommerce' ),
			),
			'handling_fee'         => array(
				'title'       => __( 'Handling fee', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'type'        => 'price',
				'default'     => '',
				'placeholder' => '0.00',
				'desc_tip'    => true,
				'description' => __( 'A fixed amount added to every rate. Use a negative amount for a discount.', 'wpruby-canada-post-shipping-for-woocommerce' ),
			),
			'handling_fee_percent' => array(
				'title'       => __( 'Handling fee (%)', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'type'        => 'decimal',
				'default'     => '',
				'placeholder' => '0',
				'desc_tip'    => true,
				'description' => __( 'A percentage of the rate, added after the fixed fee. Use a negative value for a discount.', 'wpruby-canada-post-shipping-for-woocommerce' ),
			),
			'show_transit'         => array(
				'title'   => __( 'Delivery estimate', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Show the expected transit time next to each service', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'default' => 'yes',
			),
		);
	}

	/**
	 * The zone's service settings, with every known service present.
	 *
	 * @return array<string,array>
	 */
	public function services_config(): array {
		$saved    = $this->get_option( 'services', array() );
		$services = array_replace_recursive( Services::defaults(), is_array( $saved ) ? $saved : array() );
		uasort(
			$services,
			static function ( $a, $b ) {
				return (int) $a['order'] <=> (int) $b['order'];
			}
		);
		return array_intersect_key( $services, Services::REGIONS );
	}

	/**
	 * The services table: one row per service, grouped by destination, with an on/off
	 * switch and an optional checkout name.
	 *
	 * @param string $key  Field key.
	 * @param array  $data Field definition.
	 */
	public function generate_wpruby_cps_services_html( $key, $data ): string {
		$field   = $this->get_field_key( $key );
		$rows    = $this->services_config();
		$names   = Services::names();
		$regions = array(
			Services::REGION_DOMESTIC      => __( 'Canada', 'wpruby-canada-post-shipping-for-woocommerce' ),
			Services::REGION_USA           => __( 'United States', 'wpruby-canada-post-shipping-for-woocommerce' ),
			Services::REGION_INTERNATIONAL => __( 'International', 'wpruby-canada-post-shipping-for-woocommerce' ),
		);

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php echo esc_html( $data['title'] ); ?></th>
			<td class="forminp">
				<table class="widefat striped wpruby-cps-services">
					<thead>
						<tr>
							<th class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Offer', 'wpruby-canada-post-shipping-for-woocommerce' ); ?></span></th>
							<th><?php esc_html_e( 'Service', 'wpruby-canada-post-shipping-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Name at checkout', 'wpruby-canada-post-shipping-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<?php foreach ( $regions as $region => $region_name ) : ?>
						<tbody>
							<tr class="wpruby-cps-services__region"><th colspan="3"><?php echo esc_html( $region_name ); ?></th></tr>
							<?php
							foreach ( $rows as $code => $row ) :
								if ( Services::REGIONS[ $code ] !== $region ) {
									continue;
								}
								$id = $field . '_' . str_replace( '.', '_', $code );
								?>
								<tr>
									<td class="check-column">
										<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( "{$field}[{$code}][enabled]" ); ?>" value="1" <?php checked( ! empty( $row['enabled'] ) ); ?>>
									</td>
									<td>
										<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $names[ $code ] ); ?></label>
										<code><?php echo esc_html( $code ); ?></code>
									</td>
									<td>
										<input type="text" class="regular-text" name="<?php echo esc_attr( "{$field}[{$code}][name]" ); ?>" value="<?php echo esc_attr( (string) $row['name'] ); ?>" placeholder="<?php echo esc_attr( $names[ $code ] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: service name */ __( 'Name at checkout for %s', 'wpruby-canada-post-shipping-for-woocommerce' ), $names[ $code ] ) ); ?>">
										<input type="hidden" name="<?php echo esc_attr( "{$field}[{$code}][adjustment]" ); ?>" value="<?php echo esc_attr( (string) $row['adjustment'] ); ?>">
										<input type="hidden" name="<?php echo esc_attr( "{$field}[{$code}][adjustment_type]" ); ?>" value="<?php echo esc_attr( (string) $row['adjustment_type'] ); ?>">
										<input type="hidden" name="<?php echo esc_attr( "{$field}[{$code}][order]" ); ?>" value="<?php echo esc_attr( (string) $row['order'] ); ?>">
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					<?php endforeach; ?>
				</table>
				<p class="description"><?php esc_html_e( 'Customers see only the ticked services that Canada Post can deliver to their address. Leave the name empty to use Canada Post\'s name.', 'wpruby-canada-post-shipping-for-woocommerce' ); ?></p>
			</td>
		</tr>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Keep known services only. Price adjustments and the order (set in Canada Post Pro)
	 * are passed through unchanged.
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Posted value.
	 */
	public function validate_wpruby_cps_services_field( $key, $value ): array {
		$value = is_array( $value ) ? $value : array();
		$out   = array();
		foreach ( Services::defaults() as $code => $default ) {
			$row          = is_array( $value[ $code ] ?? null ) ? $value[ $code ] : array();
			$adjustment   = (string) ( $row['adjustment'] ?? '' );
			$out[ $code ] = array(
				'enabled'         => ! empty( $row['enabled'] ),
				'name'            => sanitize_text_field( wp_unslash( (string) ( $row['name'] ?? '' ) ) ),
				'adjustment'      => '' === $adjustment || ! is_numeric( $adjustment ) ? '' : (string) (float) $adjustment,
				'adjustment_type' => 'percent' === ( $row['adjustment_type'] ?? '' ) ? 'percent' : 'fixed',
				'order'           => isset( $row['order'] ) ? (int) $row['order'] : $default['order'],
			);
		}
		return $out;
	}

	/**
	 * The zone settings, then what Canada Post Pro adds.
	 */
	public function admin_options() {
		parent::admin_options();
		$settings = $this->settings();
		if ( ! $settings->has_credentials() ) {
			echo '<div class="notice notice-warning inline"><p>' . wp_kses_post(
				sprintf(
					/* translators: %s: link to the Canada Post settings */
					__( 'Customers see no Canada Post rates until you connect your Canada Post account in %s.', 'wpruby-canada-post-shipping-for-woocommerce' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=' . SettingsSection::ID ) ) . '">' . esc_html__( 'the Canada Post settings', 'wpruby-canada-post-shipping-for-woocommerce' ) . '</a>'
				)
			) . '</p></div>';
		}
		\WPRuby\CanadaPostShipping\Admin\Upsell::card( 'zone-settings' );
	}

	/**
	 * Rates for the cart.
	 *
	 * @param array $package WooCommerce package.
	 */
	public function calculate_shipping( $package = array() ) {
		$settings = $this->settings();
		if ( ! $settings->has_credentials() ) {
			return;
		}
		// A Test app answers with sample prices, so only shop managers see them.
		if ( $settings->is_test_app() && ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$country  = strtoupper( (string) ( $package['destination']['country'] ?? '' ) );
		$postcode = (string) ( $package['destination']['postcode'] ?? '' );
		if ( '' === $country || ( in_array( $country, array( 'CA', 'US' ), true ) && '' === trim( $postcode ) ) ) {
			return;
		}

		$region   = Services::region_for_country( $country );
		$services = array_filter(
			$this->services_config(),
			static function ( $row, $code ) use ( $region ) {
				return ! empty( $row['enabled'] ) && Services::REGIONS[ $code ] === $region;
			},
			ARRAY_FILTER_USE_BOTH
		);
		if ( ! $services ) {
			return;
		}

		$parcels = ( new Packer( $settings ) )->pack( (array) ( $package['contents'] ?? array() ) );
		if ( ! $parcels ) {
			return;
		}

		try {
			$quotes = $this->rate_service( $settings )->quote( $parcels, $country, $postcode, array_keys( $services ), $this->quote_type( $settings ) );
		} catch ( ApiException $e ) {
			$this->debug( $settings, $e->getMessage() . ( '' !== $e->hint() ? ' ' . $e->hint() : '' ), 'error' );
			return;
		}

		$this->debug(
			$settings,
			sprintf(
				/* translators: 1: number of parcels, 2: quoted services */
				__( 'Canada Post quoted %1$d parcel(s): %2$s', 'wpruby-canada-post-shipping-for-woocommerce' ),
				count( $parcels ),
				$quotes ? implode( ', ', array_keys( $quotes ) ) : __( 'no services', 'wpruby-canada-post-shipping-for-woocommerce' )
			),
			'notice'
		);

		$rates = array();
		foreach ( $services as $code => $row ) {
			if ( ! isset( $quotes[ $code ] ) ) {
				continue;
			}
			$quote = $quotes[ $code ];
			$label = '' !== (string) $row['name'] ? (string) $row['name'] : Services::name( $code, $quote['name'] );
			if ( 'yes' === $this->get_option( 'show_transit', 'yes' ) && null !== $quote['transit'] ) {
				/* translators: %d: business days in transit */
				$label .= ' ' . sprintf( _n( '(est. %d business day)', '(est. %d business days)', $quote['transit'], 'wpruby-canada-post-shipping-for-woocommerce' ), $quote['transit'] );
			}
			$rates[] = array(
				'id'        => $this->get_rate_id( $code ),
				'label'     => $label,
				'cost'      => $this->cost( $quote, $settings ),
				'package'   => $package,
				'meta_data' => array(
					'_canada_post_service' => $code,
					'_canada_post_parcels' => count( $parcels ),
				),
			);
		}

		usort(
			$rates,
			static function ( $a, $b ) {
				return $a['cost'] <=> $b['cost'];
			}
		);
		foreach ( $rates as $rate ) {
			$this->add_rate( $rate );
		}
	}

	/**
	 * The price for the customer: before Canada Post's tax when WooCommerce taxes
	 * shipping (so tax isn't charged twice), converted to the store currency, plus fees.
	 *
	 * @param array $quote Combined quote.
	 */
	public function cost( array $quote, Settings $settings ): float {
		$cost = 'taxable' === $this->tax_status ? $quote['due'] - $quote['taxes'] : $quote['due'];
		if ( 'CAD' !== get_woocommerce_currency() ) {
			$cost *= $settings->exchange_rate();
		}
		$cost += (float) $this->get_option( 'handling_fee', '' );
		$cost += $cost * (float) $this->get_option( 'handling_fee_percent', '' ) / 100;

		return max( 0.0, round( $cost, wc_get_price_decimals() ) );
	}

	private function quote_type( Settings $settings ): string {
		$type = $this->get_option( 'quote_type', 'account' );
		if ( in_array( $type, array( RateRequest::QUOTE_COMMERCIAL, RateRequest::QUOTE_COUNTER ), true ) ) {
			return $type;
		}
		return $settings->is_commercial_account() ? RateRequest::QUOTE_COMMERCIAL : RateRequest::QUOTE_COUNTER;
	}

	private function settings(): Settings {
		if ( null === $this->settings_object ) {
			$this->settings_object = new Settings();
		}
		return $this->settings_object;
	}

	private function rate_service( Settings $settings ): RateService {
		if ( null === $this->rate_service ) {
			$this->rate_service = new RateService( $settings, new Client( $settings ) );
		}
		return $this->rate_service;
	}

	/**
	 * With debug mode on: a notice on the cart and checkout for shop managers, and a line
	 * in the WooCommerce log (WooCommerce → Status → Logs, source wpruby-canada-post).
	 */
	private function debug( Settings $settings, string $message, string $type ): void {
		if ( ! $settings->is_debug() ) {
			return;
		}
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( 'error' === $type ? 'error' : 'debug', wp_specialchars_decode( $message, ENT_QUOTES ), array( 'source' => 'wpruby-canada-post' ) );
		}
		if ( current_user_can( 'manage_woocommerce' ) && function_exists( 'wc_add_notice' ) && function_exists( 'WC' ) && WC()->session && ! wc_has_notice( 'Canada Post: ' . $message, $type ) ) {
			wc_add_notice( 'Canada Post: ' . $message, $type );
		}
	}
}
