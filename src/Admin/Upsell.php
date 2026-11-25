<?php
/**
 * What Canada Post Pro adds, shown on the plugin's own settings screens.
 *
 * @package WPRuby\CanadaPostShipping
 */

namespace WPRuby\CanadaPostShipping\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * One card, no nagging notices.
 */
class Upsell {

	/**
	 * Link to the Pro page, tagged with where the click came from.
	 *
	 * @param string $medium Placement, e.g. "zone-settings".
	 */
	public static function url( string $medium ): string {
		return add_query_arg(
			array(
				'utm_source'   => 'canadapost-lite',
				'utm_medium'   => $medium,
				'utm_campaign' => 'freetopro',
			),
			WPRUBY_CPS_PRO_URL
		);
	}

	/**
	 * @return string[]
	 */
	public static function features(): array {
		return array(
			__( 'Print Canada Post labels from the order screen, one order or in bulk', 'wpruby-canada-post-shipping-for-woocommerce' ),
			__( 'End-of-day manifests, pickups and return labels', 'wpruby-canada-post-shipping-for-woocommerce' ),
			__( 'Tracking numbers and status updates in order emails and My Account', 'wpruby-canada-post-shipping-for-woocommerce' ),
			__( 'Delivery to a post office chosen at checkout, and AddressComplete', 'wpruby-canada-post-shipping-for-woocommerce' ),
			__( 'Your own boxes with 3D packing, free shipping thresholds and per-service price adjustments', 'wpruby-canada-post-shipping-for-woocommerce' ),
		);
	}

	/**
	 * @param string $medium Placement, for the link.
	 */
	public static function card( string $medium ): void {
		?>
		<div class="wpruby-cps-upsell">
			<h3><?php esc_html_e( 'More with Canada Post Pro', 'wpruby-canada-post-shipping-for-woocommerce' ); ?></h3>
			<ul>
				<?php foreach ( self::features() as $feature ) : ?>
					<li><?php echo esc_html( $feature ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( self::url( $medium ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See Canada Post Pro', 'wpruby-canada-post-shipping-for-woocommerce' ); ?></a>
				<span class="description"><?php esc_html_e( 'Your shipping zones keep their settings when you upgrade.', 'wpruby-canada-post-shipping-for-woocommerce' ); ?></span>
			</p>
		</div>
		<?php
	}
}
