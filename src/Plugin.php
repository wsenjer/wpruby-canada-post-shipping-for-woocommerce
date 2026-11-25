<?php
/**
 * Wires the plugin into WordPress and WooCommerce.
 *
 * @package WPRuby\CanadaPostShipping
 */

namespace WPRuby\CanadaPostShipping;

use WPRuby\CanadaPostShipping\Admin\ConnectionCheck;
use WPRuby\CanadaPostShipping\Admin\Upsell;
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
	 * Settings first, then Pro and support.
	 *
	 * @param array $links Plugin row links.
	 */
	public function action_links( $links ): array {
		$links = (array) $links;
		array_unshift( $links, '<a href="' . esc_url( self::settings_url() ) . '">' . esc_html__( 'Settings', 'wpruby-canada-post-shipping-for-woocommerce' ) . '</a>' );
		$links[] = '<a href="' . esc_url( Upsell::url( 'plugins' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Get the Pro version', 'wpruby-canada-post-shipping-for-woocommerce' ) . '</a>';
		$links[] = '<a href="https://wordpress.org/support/plugin/wpruby-canada-post-shipping-for-woocommerce/" target="_blank" rel="noopener">' . esc_html__( 'Support', 'wpruby-canada-post-shipping-for-woocommerce' ) . '</a>';
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
		wp_enqueue_style( 'wpruby-cps-admin', WPRUBY_CPS_URL . 'assets/css/admin.css', array(), WPRUBY_CPS_VERSION );
		wp_enqueue_script( 'wpruby-cps-admin', WPRUBY_CPS_URL . 'assets/js/admin.js', array(), WPRUBY_CPS_VERSION, true );
		wp_localize_script(
			'wpruby-cps-admin',
			'wprubyCps',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'action'   => ConnectionCheck::ACTION,
				'nonce'    => wp_create_nonce( ConnectionCheck::ACTION ),
				'checking' => __( 'Checking…', 'wpruby-canada-post-shipping-for-woocommerce' ),
				'failed'   => __( 'The check could not run. Reload the page and try again.', 'wpruby-canada-post-shipping-for-woocommerce' ),
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
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Canada Post needs a ship-from postal code in Canada. Enter it in the Canada Post settings, or set a Canadian store address.', 'wpruby-canada-post-shipping-for-woocommerce' ) . '</p></div>';
		}
		if ( 'CAD' !== get_woocommerce_currency() && 1.0 === $settings->exchange_rate() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html(
				sprintf(
					/* translators: %s: store currency code */
					__( 'Canada Post quotes in CAD, but your store uses %s. Set the CAD exchange rate in the Canada Post settings so customers pay the right amount.', 'wpruby-canada-post-shipping-for-woocommerce' ),
					get_woocommerce_currency()
				)
			) . '</p></div>';
		}
		if ( $settings->is_test_app() ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'Canada Post is connected with a Test app: rates are samples and only shop managers see them. Switch to a Production app before customers order.', 'wpruby-canada-post-shipping-for-woocommerce' ) . '</p></div>';
		}
	}
}
