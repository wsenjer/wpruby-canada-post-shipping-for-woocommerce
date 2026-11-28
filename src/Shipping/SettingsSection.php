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
		$this->method_title       = __( 'Canada Post', 'wpruby-shipping-rates-canada-post' );
		$this->method_description = __( 'Connect your Canada Post account and set where parcels ship from. Then add the Canada Post method to your shipping zones.', 'wpruby-shipping-rates-canada-post' );
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
				'title'       => __( 'Canada Post account', 'wpruby-shipping-rates-canada-post' ),
				'type'        => 'title',
				'description' => wp_kses_post(
					sprintf(
						/* translators: %s: link to the Canada Post Developer Portal */
						__( 'Create an app on the %s, add the Rating API product to it, and copy its API key and secret here.', 'wpruby-shipping-rates-canada-post' ),
						'<a href="https://developer-developpeur.canadapost-postescanada.ca/devportal-portaildesdeveloppeurs/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Canada Post Developer Portal', 'wpruby-shipping-rates-canada-post' ) . '</a>'
					)
				),
			),
			'app_type'         => array(
				'title'       => __( 'App type', 'wpruby-shipping-rates-canada-post' ),
				'type'        => 'select',
				'default'     => Settings::APP_PRODUCTION,
				'options'     => array(
					Settings::APP_PRODUCTION => __( 'Production app (real rates)', 'wpruby-shipping-rates-canada-post' ),
					Settings::APP_TEST       => __( 'Test app (sample rates, shown to shop managers only)', 'wpruby-shipping-rates-canada-post' ),
				),
				'description' => __( 'Canada Post decides the type when you create the app. A Test app returns sample prices that are not your real rates.', 'wpruby-shipping-rates-canada-post' ),
			),
			'api_key'          => array(
				'title'       => __( 'API key', 'wpruby-shipping-rates-canada-post' ),
				'type'        => 'text',
				'default'     => '',
				'disabled'    => $from_config,
				'description' => $from_config ? __( 'Set in wp-config.php (WPRUBY_CPS_API_KEY).', 'wpruby-shipping-rates-canada-post' ) : '',
			),
			'api_secret'       => array(
				'title'       => __( 'API secret', 'wpruby-shipping-rates-canada-post' ),
				'type'        => 'wpruby_cps_secret',
				'default'     => '',
				'disabled'    => $from_config,
				'description' => $from_config ? __( 'Set in wp-config.php (WPRUBY_CPS_API_SECRET).', 'wpruby-shipping-rates-canada-post' ) : __( 'Stored in your database. Leave empty to keep the saved secret.', 'wpruby-shipping-rates-canada-post' ),
			),
			'customer_number'  => array(
				'title'       => __( 'Customer number', 'wpruby-shipping-rates-canada-post' ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'Your Canada Post customer number (up to 10 digits), shown in your Canada Post business account.', 'wpruby-shipping-rates-canada-post' ),
			),
			'account_type'     => array(
				'title'   => __( 'Account type', 'wpruby-shipping-rates-canada-post' ),
				'type'    => 'select',
				'default' => 'small_business',
				'options' => array(
					'small_business' => __( 'Solutions for Small Business (retail rates)', 'wpruby-shipping-rates-canada-post' ),
					'commercial'     => __( 'Commercial contract (business rates)', 'wpruby-shipping-rates-canada-post' ),
				),
			),
			'contract_number'  => array(
				'title'       => __( 'Contract number', 'wpruby-shipping-rates-canada-post' ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'Commercial accounts only. Leave empty for Solutions for Small Business.', 'wpruby-shipping-rates-canada-post' ),
			),
			'origin_postcode'  => array(
				'title'       => __( 'Ship-from postal code', 'wpruby-shipping-rates-canada-post' ),
				'type'        => 'text',
				'default'     => '',
				'placeholder' => 'K1A 0B1',
				'description' => __( 'Where your parcels leave from. Leave empty to use the store address when it is in Canada.', 'wpruby-shipping-rates-canada-post' ),
			),

			'packing_title'    => array(
				'title'       => __( 'Parcels', 'wpruby-shipping-rates-canada-post' ),
				'type'        => 'title',
				'description' => __( 'How cart items are packed into parcels, and the weight and size used for products that have none.', 'wpruby-shipping-rates-canada-post' ),
			),
			'packing'          => array(
				'title'   => __( 'Packing', 'wpruby-shipping-rates-canada-post' ),
				'type'    => 'select',
				'default' => 'single',
				'options' => array(
					'single'     => __( 'Pack items together (split into more parcels by weight)', 'wpruby-shipping-rates-canada-post' ),
					'individual' => __( 'Ship each item in its own parcel', 'wpruby-shipping-rates-canada-post' ),
				),
			),
			'max_weight'       => array(
				'title'       => __( 'Maximum parcel weight (kg)', 'wpruby-shipping-rates-canada-post' ),
				'type'        => 'decimal',
				'default'     => '30',
				'description' => __( 'When packing items together, a new parcel starts above this weight. Canada Post\'s limit is 30 kg.', 'wpruby-shipping-rates-canada-post' ),
			),
			'default_weight'   => array(
				/* translators: %s: weight unit */
				'title'       => sprintf( __( 'Default weight (%s)', 'wpruby-shipping-rates-canada-post' ), $weight_unit ),
				'type'        => 'decimal',
				'default'     => '0.5',
				'description' => __( 'Used for products with no weight.', 'wpruby-shipping-rates-canada-post' ),
			),
			'default_length'   => array(
				/* translators: %s: dimension unit */
				'title'   => sprintf( __( 'Default length (%s)', 'wpruby-shipping-rates-canada-post' ), $dimension_unit ),
				'type'    => 'decimal',
				'default' => '',
			),
			'default_width'    => array(
				/* translators: %s: dimension unit */
				'title'   => sprintf( __( 'Default width (%s)', 'wpruby-shipping-rates-canada-post' ), $dimension_unit ),
				'type'    => 'decimal',
				'default' => '',
			),
			'default_height'   => array(
				/* translators: %s: dimension unit */
				'title'       => sprintf( __( 'Default height (%s)', 'wpruby-shipping-rates-canada-post' ), $dimension_unit ),
				'type'        => 'decimal',
				'default'     => '',
				'description' => __( 'Used for products with no size. Leave empty to quote those products by weight only.', 'wpruby-shipping-rates-canada-post' ),
			),
			'packaging_weight' => array(
				/* translators: %s: weight unit */
				'title'       => sprintf( __( 'Packaging weight (%s)', 'wpruby-shipping-rates-canada-post' ), $weight_unit ),
				'type'        => 'decimal',
				'default'     => '',
				'description' => __( 'Added to every parcel for the box and padding.', 'wpruby-shipping-rates-canada-post' ),
			),

			'other_title'      => array(
				'title'       => __( 'Currency and troubleshooting', 'wpruby-shipping-rates-canada-post' ),
				'type'        => 'title',
				'description' => __( 'Currency conversion for stores not in CAD, and logging for when rates don\'t show up.', 'wpruby-shipping-rates-canada-post' ),
			),
			'exchange_rate'    => array(
				'title'       => __( 'CAD exchange rate', 'wpruby-shipping-rates-canada-post' ),
				'type'        => 'decimal',
				'default'     => '1',
				/* translators: %s: store currency code */
				'description' => sprintf( __( 'Canada Post quotes in CAD. Your store uses %s, so rates are multiplied by this number. Ignored when the store uses CAD.', 'wpruby-shipping-rates-canada-post' ), get_woocommerce_currency() ),
			),
			'debug'            => array(
				'title'       => __( 'Debug mode', 'wpruby-shipping-rates-canada-post' ),
				'type'        => 'checkbox',
				'label'       => __( 'Show Canada Post errors on the cart and checkout to shop managers, and log them', 'wpruby-shipping-rates-canada-post' ),
				'default'     => 'no',
				'description' => __( 'Logs are in WooCommerce → Status → Logs (source: wpruby-canada-post). Customers never see these messages.', 'wpruby-shipping-rates-canada-post' ),
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
				<input type="password" class="input-text regular-input" id="<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $saved ? __( 'Saved (hidden)', 'wpruby-shipping-rates-canada-post' ) : '' ); ?>" <?php disabled( ! empty( $data['disabled'] ) ); ?>>
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
			throw new \Exception( esc_html__( 'The ship-from postal code must be a Canadian postal code, like K1A 0B1. The previous value was kept.', 'wpruby-shipping-rates-canada-post' ) );
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
			throw new \Exception( esc_html__( 'The customer number has at most 10 digits. The previous value was kept.', 'wpruby-shipping-rates-canada-post' ) );
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
	 * The settings page: a header with the connection status, a setup checklist, the
	 * settings in cards, and a sidebar with the connection test and help links.
	 */
	public function admin_options() {
		$settings  = new Settings();
		$connected = $settings->has_credentials();
		$postcode  = $settings->origin_postcode();
		$zones     = self::zones_using_method();
		$steps     = array(
			array(
				'done'  => $connected,
				'title' => __( 'Connect your Canada Post account', 'wpruby-shipping-rates-canada-post' ),
				'text'  => __( 'API key, secret and customer number.', 'wpruby-shipping-rates-canada-post' ),
				'link'  => '#' . $this->get_field_key( 'account_title' ),
			),
			array(
				'done'  => '' !== $postcode,
				'title' => __( 'Set your ship-from postal code', 'wpruby-shipping-rates-canada-post' ),
				'text'  => '' !== $postcode
					/* translators: %s: postal code */
					? sprintf( __( 'Parcels ship from %s.', 'wpruby-shipping-rates-canada-post' ), $postcode )
					: __( 'Where your parcels leave from.', 'wpruby-shipping-rates-canada-post' ),
				'link'  => '#' . $this->get_field_key( 'origin_postcode' ),
			),
			array(
				'done'  => $zones > 0,
				'title' => __( 'Add Canada Post to a shipping zone', 'wpruby-shipping-rates-canada-post' ),
				'text'  => $zones > 0
					/* translators: %d: number of shipping zones */
					? sprintf( _n( 'Offered in %d zone.', 'Offered in %d zones.', $zones, 'wpruby-shipping-rates-canada-post' ), $zones )
					: __( 'Then pick the services customers can choose.', 'wpruby-shipping-rates-canada-post' ),
				'link'  => admin_url( 'admin.php?page=wc-settings&tab=shipping' ),
			),
		);
		$done = count( array_filter( wp_list_pluck( $steps, 'done' ) ) );

		if ( ! $connected ) {
			$status = array( 'none', __( 'Not connected', 'wpruby-shipping-rates-canada-post' ) );
		} elseif ( $settings->is_test_app() ) {
			$status = array( 'test', __( 'Test app', 'wpruby-shipping-rates-canada-post' ) );
		} else {
			$status = array( 'ready', __( 'Production app', 'wpruby-shipping-rates-canada-post' ) );
		}
		?>
		<div class="wpruby-cps-admin">
			<div class="wpruby-cps-header">
				<span class="wpruby-cps-header__icon dashicons dashicons-airplane" aria-hidden="true"></span>
				<div class="wpruby-cps-header__text">
					<h2><?php echo esc_html( $this->get_method_title() ); ?></h2>
					<p><?php echo esc_html( $this->get_method_description() ); ?></p>
				</div>
				<span class="wpruby-cps-pill wpruby-cps-pill--<?php echo esc_attr( $status[0] ); ?>" id="wpruby-cps-status"><?php echo esc_html( $status[1] ); ?></span>
			</div>

			<?php if ( $done < count( $steps ) ) : ?>
				<div class="wpruby-cps-card wpruby-cps-steps">
					<div class="wpruby-cps-card__head">
						<h3><?php esc_html_e( 'Get started', 'wpruby-shipping-rates-canada-post' ); ?></h3>
						<span class="wpruby-cps-steps__count">
							<?php
							/* translators: 1: steps done, 2: total steps */
							echo esc_html( sprintf( __( '%1$d of %2$d done', 'wpruby-shipping-rates-canada-post' ), $done, count( $steps ) ) );
							?>
						</span>
					</div>
					<ol>
						<?php foreach ( $steps as $i => $step ) : ?>
							<li class="<?php echo $step['done'] ? 'is-done' : ''; ?>">
								<span class="wpruby-cps-steps__mark" aria-hidden="true"><?php echo $step['done'] ? '<span class="dashicons dashicons-yes"></span>' : esc_html( (string) ( $i + 1 ) ); ?></span>
								<span class="wpruby-cps-steps__body">
									<a href="<?php echo esc_url( $step['link'] ); ?>"><?php echo esc_html( $step['title'] ); ?></a>
									<span><?php echo esc_html( $step['text'] ); ?></span>
								</span>
								<span class="screen-reader-text"><?php echo $step['done'] ? esc_html__( 'Done', 'wpruby-shipping-rates-canada-post' ) : esc_html__( 'To do', 'wpruby-shipping-rates-canada-post' ); ?></span>
							</li>
						<?php endforeach; ?>
					</ol>
				</div>
			<?php endif; ?>

			<div class="wpruby-cps-layout">
				<div class="wpruby-cps-main">
					<?php foreach ( $this->field_groups() as $group ) : ?>
						<section class="wpruby-cps-card" id="<?php echo esc_attr( $this->get_field_key( $group['key'] ) ); ?>">
							<div class="wpruby-cps-card__head">
								<h3><span class="dashicons <?php echo esc_attr( $group['icon'] ); ?>" aria-hidden="true"></span><?php echo esc_html( $group['title'] ); ?></h3>
							</div>
							<?php if ( '' !== $group['description'] ) : ?>
								<p class="wpruby-cps-card__intro"><?php echo wp_kses_post( $group['description'] ); ?></p>
							<?php endif; ?>
							<table class="form-table">
								<?php echo $this->generate_settings_html( $group['fields'], false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce's escaped field markup. ?>
							</table>
						</section>
					<?php endforeach; ?>
				</div>

				<aside class="wpruby-cps-sidebar">
					<div class="wpruby-cps-card">
						<div class="wpruby-cps-card__head">
							<h3><span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span><?php esc_html_e( 'Connection test', 'wpruby-shipping-rates-canada-post' ); ?></h3>
						</div>
						<p><?php esc_html_e( 'Asks Canada Post for a 1 kg Regular Parcel rate from your postal code to itself. Save your changes first.', 'wpruby-shipping-rates-canada-post' ); ?></p>
						<p>
							<button type="button" class="button button-secondary wpruby-cps-test" id="wpruby-cps-test-connection" <?php disabled( ! $connected ); ?>>
								<span class="dashicons dashicons-update" aria-hidden="true"></span>
								<?php esc_html_e( 'Test connection', 'wpruby-shipping-rates-canada-post' ); ?>
							</button>
						</p>
						<div id="wpruby-cps-test-result" class="wpruby-cps-result" role="status" aria-live="polite"<?php echo $connected ? ' hidden' : ''; ?>>
							<?php
							if ( ! $connected ) {
								esc_html_e( 'Enter and save your API key, secret and customer number to run the test.', 'wpruby-shipping-rates-canada-post' );
							}
							?>
						</div>
					</div>

					<?php Upsell::card( 'settings' ); ?>

					<div class="wpruby-cps-card">
						<div class="wpruby-cps-card__head">
							<h3><span class="dashicons dashicons-sos" aria-hidden="true"></span><?php esc_html_e( 'Help', 'wpruby-shipping-rates-canada-post' ); ?></h3>
						</div>
						<ul class="wpruby-cps-links">
							<li><a href="https://wpruby.com/canada-post-shipping-rates-woocommerce/" target="_blank" rel="noopener"><span class="dashicons dashicons-book" aria-hidden="true"></span><?php esc_html_e( 'Setup guide', 'wpruby-shipping-rates-canada-post' ); ?></a></li>
							<li><a href="https://developer-developpeur.canadapost-postescanada.ca/devportal-portaildesdeveloppeurs/" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-admin-network" aria-hidden="true"></span><?php esc_html_e( 'Canada Post Developer Portal', 'wpruby-shipping-rates-canada-post' ); ?></a></li>
							<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&source=wpruby-canada-post' ) ); ?>"><span class="dashicons dashicons-media-text" aria-hidden="true"></span><?php esc_html_e( 'Debug logs', 'wpruby-shipping-rates-canada-post' ); ?></a></li>
							<li><a href="https://wordpress.org/support/plugin/wpruby-shipping-rates-canada-post/" target="_blank" rel="noopener"><span class="dashicons dashicons-format-chat" aria-hidden="true"></span><?php esc_html_e( 'Support forum', 'wpruby-shipping-rates-canada-post' ); ?></a></li>
							<li><a href="https://wordpress.org/support/plugin/wpruby-shipping-rates-canada-post/reviews/#new-post" target="_blank" rel="noopener"><span class="dashicons dashicons-star-filled" aria-hidden="true"></span><?php esc_html_e( 'Rate the plugin', 'wpruby-shipping-rates-canada-post' ); ?></a></li>
						</ul>
					</div>
				</aside>
			</div>
		</div>
		<?php
	}

	/**
	 * The form fields split at each title field, for one card per section.
	 *
	 * @return array[] key, title, description, icon, fields.
	 */
	private function field_groups(): array {
		$icons  = array(
			'account_title' => 'dashicons-admin-network',
			'packing_title' => 'dashicons-archive',
			'other_title'   => 'dashicons-admin-tools',
		);
		$groups = array();
		foreach ( $this->get_form_fields() as $key => $field ) {
			if ( 'title' === ( $field['type'] ?? '' ) ) {
				$groups[] = array(
					'key'         => $key,
					'title'       => (string) $field['title'],
					'description' => (string) ( $field['description'] ?? '' ),
					'icon'        => $icons[ $key ] ?? 'dashicons-admin-generic',
					'fields'      => array(),
				);
				continue;
			}
			if ( ! $groups ) {
				$groups[] = array(
					'key'         => 'general',
					'title'       => '',
					'description' => '',
					'icon'        => 'dashicons-admin-generic',
					'fields'      => array(),
				);
			}
			$groups[ count( $groups ) - 1 ]['fields'][ $key ] = $field;
		}
		return $groups;
	}

	/**
	 * How many shipping zones (including "Locations not covered") offer Canada Post.
	 */
	public static function zones_using_method(): int {
		if ( ! class_exists( \WC_Shipping_Zones::class ) ) {
			return 0;
		}
		$zones   = array_column( \WC_Shipping_Zones::get_zones(), 'shipping_methods' );
		$zones[] = ( new \WC_Shipping_Zone( 0 ) )->get_shipping_methods();
		$count   = 0;
		foreach ( $zones as $methods ) {
			foreach ( (array) $methods as $method ) {
				if ( Method::ID === $method->id && 'yes' === $method->enabled ) {
					++$count;
					break;
				}
			}
		}
		return $count;
	}
}
