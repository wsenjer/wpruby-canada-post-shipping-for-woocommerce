<?php
/**
 * Uninstall: removes the store-wide settings and cached tokens and rates.
 *
 * Zone settings (woocommerce_canada_post_{n}_settings) are left to WooCommerce, which
 * deletes them with the zone.
 *
 * @package WPRuby\CanadaPostShipping
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'woocommerce_canada_post_settings_settings' );

global $wpdb;
foreach ( array( '_transient_wpruby_cps_', '_transient_timeout_wpruby_cps_' ) as $wpruby_cps_prefix ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $wpruby_cps_prefix ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
