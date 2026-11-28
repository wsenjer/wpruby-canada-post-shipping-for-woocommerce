<?php
/**
 * What Canada Post Pro adds, shown on the plugin's own settings screens.
 *
 * @package WPRuby\CanadaPostShipping
 */

namespace WPRuby\CanadaPostShipping\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * One card on the plugin's own screens and a link in its plugin row; no admin notices.
 */
class Upsell {

	const PRO_URL = 'https://wpruby.com/plugin/woocommerce-canada-post-extension-pro/';

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
			self::PRO_URL
		);
	}

	/**
	 * @return string[]
	 */
	public static function features(): array {
		return array(
			__( 'Print Canada Post labels from the order screen, one order or in bulk', 'wpruby-shipping-rates-canada-post' ),
			__( 'End-of-day manifests, pickups and return labels', 'wpruby-shipping-rates-canada-post' ),
			__( 'Tracking numbers and status updates in order emails and My Account', 'wpruby-shipping-rates-canada-post' ),
			__( 'Delivery to a post office chosen at checkout, and AddressComplete', 'wpruby-shipping-rates-canada-post' ),
			__( 'Your own boxes with 3D packing, free shipping thresholds and per-service price adjustments', 'wpruby-shipping-rates-canada-post' ),
		);
	}

	/**
	 * The Pro card.
	 *
	 * @param string $medium Placement, for the link.
	 */
	public static function card( string $medium ): void {
		?>
		<div class="wpruby-cps-card wpruby-cps-pro">
			<div class="wpruby-cps-card__head">
				<h3><span class="dashicons dashicons-star-filled" aria-hidden="true"></span><?php esc_html_e( 'Upgrade to Canada Post Pro', 'wpruby-shipping-rates-canada-post' ); ?></h3>
			</div>
			<ul class="wpruby-cps-pro__list">
				<?php foreach ( self::features() as $feature ) : ?>
					<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php echo esc_html( $feature ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p>
				<a class="button button-primary wpruby-cps-pro__button" href="<?php echo esc_url( self::url( $medium ) ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Get Canada Post Pro', 'wpruby-shipping-rates-canada-post' ); ?>
					<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'wpruby-shipping-rates-canada-post' ); ?></span>
				</a>
			</p>
			<p class="wpruby-cps-pro__note"><?php esc_html_e( 'Your shipping zones keep their settings when you upgrade.', 'wpruby-shipping-rates-canada-post' ); ?></p>
		</div>
		<?php
	}
}
