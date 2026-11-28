<?php
/**
 * Plugin Name:          WPRuby Shipping Rates for Canada Post and WooCommerce
 * Plugin URI:           https://wordpress.org/plugins/wpruby-shipping-rates-canada-post/
 * Description:          Live Canada Post shipping rates at WooCommerce checkout: Regular Parcel, Expedited Parcel, Xpresspost, Priority, and US and international services, from your own Canada Post account.
 * Version:              1.0.0
 * Author:               WPRuby
 * Author URI:           https://wpruby.com
 * Text Domain:          wpruby-shipping-rates-canada-post
 * Domain Path:          /languages
 * Requires at least:    6.2
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * WC requires at least: 8.2
 * WC tested up to:      11.3
 * License:              GPL-2.0+
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WPRuby\CanadaPostShipping
 */

defined( 'ABSPATH' ) || exit;

/**
 * WPRuby's other Canada Post plugin registers the same shipping method ID, so this
 * plugin stays out of the way while that one is active.
 */
function wpruby_cps_conflicting_plugin_is_active(): bool {
	$plugin  = 'woocommerce-canada-post-extension-pro/woocommerce-canada-post-extension-pro.php';
	$active  = (array) get_option( 'active_plugins', array() );
	$network = is_multisite() ? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array();

	return in_array( $plugin, $active, true ) || in_array( $plugin, $network, true );
}

if ( wpruby_cps_conflicting_plugin_is_active() ) {
	// Said once, in this plugin's own row on the Plugins screen: no admin notices.
	add_filter(
		'plugin_row_meta',
		static function ( $meta, $file ) {
			if ( plugin_basename( __FILE__ ) === $file ) {
				$meta[] = '<strong>' . esc_html__( 'Paused: WPRuby\'s Canada Post Pro plugin is active and handles Canada Post shipping. You can deactivate this plugin.', 'wpruby-shipping-rates-canada-post' ) . '</strong>';
			}
			return $meta;
		},
		10,
		2
	);
	return;
}

define( 'WPRUBY_CPS_VERSION', '1.0.0' );
define( 'WPRUBY_CPS_FILE', __FILE__ );
define( 'WPRUBY_CPS_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPRUBY_CPS_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'WPRuby\\CanadaPostShipping\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$file = WPRUBY_CPS_DIR . 'src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

\WPRuby\CanadaPostShipping\Plugin::instance()->boot();
