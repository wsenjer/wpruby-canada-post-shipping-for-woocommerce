<?php
/**
 * Wires the plugin into WordPress and WooCommerce.
 *
 * @package WPRuby\CanadaPostShipping
 */

namespace WPRuby\CanadaPostShipping;

use WPRuby\CanadaPostShipping\Admin\ConnectionCheck;
use WPRuby\CanadaPostShipping\Shipping\Method;
use WPRuby\CanadaPostShipping\Shipping\SettingsSection;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks only; the work happens in the shipping method and the services it uses.
 */
class Plugin {

	/**
	 * @var Plugin|null
	 */
	private static $instance;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		add_filter( 'woocommerce_shipping_methods', array( $this, 'register_methods' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WPRUBY_CPS_FILE ), array( $this, 'action_links' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		ConnectionCheck::register();
	}

	/**
	 * @param array $methods Shipping method ID => class.
	 */
	public function register_methods( $methods ): array {
		$methods                       = (array) $methods;
		$methods[ Method::ID ]          = Method::class;
		$methods[ SettingsSection::ID ] = SettingsSection::class;
		return $methods;
	}

	public static function settings_url(): string {
		return admin_url( 'admin.php?page=wc-settings&tab=shipping&section=' . SettingsSection::ID );
	}

	/**
	 * Settings first, then support.
	 *
	 * @param array $links Plugin row links.
	 */
	public function action_links( $links ): array {
		$links = (array) $links;
		array_unshift( $links, '<a href="' . esc_url( self::settings_url() ) . '">' . esc_html__( 'Settings', 'wpruby-shipping-rates-canada-post' ) . '</a>' );
		$links[] = '<a href="https://wordpress.org/support/plugin/wpruby-shipping-rates-canada-post/" target="_blank" rel="noopener">' . esc_html__( 'Support', 'wpruby-shipping-rates-canada-post' ) . '</a>';
		return $links;
	}

	/**
	 * Whether the current screen is one of this plugin's settings screens.
	 */
	private function is_own_screen(): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only picks the screen.
		if ( 'wc-settings' !== sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ) || 'shipping' !== sanitize_key( wp_unslash( $_GET['tab'] ?? '' ) ) ) {
			return false;
		}
		if ( SettingsSection::ID === sanitize_key( wp_unslash( $_GET['section'] ?? '' ) ) ) {
			return true;
		}
		// The zone screen, where the method's settings open in a modal.
		if ( isset( $_GET['zone_id'] ) ) {
			return true;
		}
		$instance_id = absint( $_GET['instance_id'] ?? 0 );
		// phpcs:enable
		if ( ! $instance_id || ! class_exists( \WC_Shipping_Zones::class ) ) {
			return false;
		}
		$method = \WC_Shipping_Zones::get_shipping_method( $instance_id );
		return $method && Method::ID === $method->id;
	}

	public function admin_assets(): void {
		if ( ! $this->is_own_screen() ) {
			return;
		}
		wp_enqueue_style( 'wpruby-cps-admin', WPRUBY_CPS_URL . 'assets/css/admin.css', array( 'dashicons' ), WPRUBY_CPS_VERSION );
		wp_enqueue_script( 'wpruby-cps-admin', WPRUBY_CPS_URL . 'assets/js/admin.js', array(), WPRUBY_CPS_VERSION, true );
		wp_localize_script(
			'wpruby-cps-admin',
			'wprubyCps',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'action'    => ConnectionCheck::ACTION,
				'nonce'     => wp_create_nonce( ConnectionCheck::ACTION ),
				'checking'  => __( 'Checking…', 'wpruby-shipping-rates-canada-post' ),
				'failed'    => __( 'The check could not run. Reload the page and try again.', 'wpruby-shipping-rates-canada-post' ),
				'connected' => __( 'Connected', 'wpruby-shipping-rates-canada-post' ),
				/* translators: 1: services turned on, 2: services in the group */
				'countOn'   => __( '%1$d of %2$d on', 'wpruby-shipping-rates-canada-post' ),
			)
		);
	}

	/**
	 * Setup problems, on this plugin's own screens only.
	 */
	public function admin_notices(): void {
		if ( ! $this->is_own_screen() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$settings = new Settings();
		if ( $settings->has_credentials() && '' === $settings->origin_postcode() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Canada Post needs a ship-from postal code in Canada. Enter it in the Canada Post settings, or set a Canadian store address.', 'wpruby-shipping-rates-canada-post' ) . '</p></div>';
		}
		if ( 'CAD' !== get_woocommerce_currency() && 1.0 === $settings->exchange_rate() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html(
				sprintf(
					/* translators: %s: store currency code */
					__( 'Canada Post quotes in CAD, but your store uses %s. Set the CAD exchange rate in the Canada Post settings so customers pay the right amount.', 'wpruby-shipping-rates-canada-post' ),
					get_woocommerce_currency()
				)
			) . '</p></div>';
		}
		if ( $settings->is_test_app() ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'Canada Post is connected with a Test app: rates are samples and only shop managers see them. Switch to a Production app before customers order.', 'wpruby-shipping-rates-canada-post' ) . '</p></div>';
		}
	}
}
