<?php
/**
 * The Canada Post services this plugin can quote.
 *
 * @package WPRuby\CanadaPostShipping
 */

namespace WPRuby\CanadaPostShipping;

defined( 'ABSPATH' ) || exit;

/**
 * Service codes, Canada Post's names for them and the destinations they serve.
 * The codes and the order match Canada Post Pro, so zone settings carry over.
 */
class Services {

	const REGION_DOMESTIC      = 'domestic';
	const REGION_USA           = 'usa';
	const REGION_INTERNATIONAL = 'international';

	/**
	 * Service code => region.
	 */
	const REGIONS = array(
		'DOM.RP'      => self::REGION_DOMESTIC,
		'DOM.EP'      => self::REGION_DOMESTIC,
		'DOM.XP'      => self::REGION_DOMESTIC,
		'DOM.PC'      => self::REGION_DOMESTIC,
		'DOM.LIB'     => self::REGION_DOMESTIC,
		'USA.EP'      => self::REGION_USA,
		'USA.XP'      => self::REGION_USA,
		'USA.TP'      => self::REGION_USA,
		'USA.SP.AIR'  => self::REGION_USA,
		'INT.XP'      => self::REGION_INTERNATIONAL,
		'INT.TP'      => self::REGION_INTERNATIONAL,
		'INT.SP.AIR'  => self::REGION_INTERNATIONAL,
		'INT.SP.SURF' => self::REGION_INTERNATIONAL,
		'INT.IP.AIR'  => self::REGION_INTERNATIONAL,
		'INT.IP.SURF' => self::REGION_INTERNATIONAL,
	);

	/**
	 * Services that are off until the merchant turns them on.
	 */
	const OFF_BY_DEFAULT = array( 'DOM.LIB' );

	/**
	 * Canada Post's names for the services, translatable.
	 *
	 * @return array<string,string>
	 */
	public static function names(): array {
		return array(
			'DOM.RP'      => __( 'Regular Parcel', 'wpruby-canada-post-shipping-for-woocommerce' ),
			'DOM.EP'      => __( 'Expedited Parcel', 'wpruby-canada-post-shipping-for-woocommerce' ),
			'DOM.XP'      => __( 'Xpresspost', 'wpruby-canada-post-shipping-for-woocommerce' ),
			'DOM.PC'      => __( 'Priority', 'wpruby-canada-post-shipping-for-woocommerce' ),
			'DOM.LIB'     => __( 'Library Books', 'wpruby-canada-post-shipping-for-woocommerce' ),
			'USA.EP'      => __( 'Expedited Parcel USA', 'wpruby-canada-post-shipping-for-woocommerce' ),
			'USA.XP'      => __( 'Xpresspost USA', 'wpruby-canada-post-shipping-for-woocommerce' ),
			'USA.TP'      => __( 'Tracked Packet – USA', 'wpruby-canada-post-shipping-for-woocommerce' ),
			'USA.SP.AIR'  => __( 'Small Packet USA Air', 'wpruby-canada-post-shipping-for-woocommerce' ),
			'INT.XP'      => __( 'Xpresspost International', 'wpruby-canada-post-shipping-for-woocommerce' ),
			'INT.TP'      => __( 'Tracked Packet – International', 'wpruby-canada-post-shipping-for-woocommerce' ),
			'INT.SP.AIR'  => __( 'Small Packet International Air', 'wpruby-canada-post-shipping-for-woocommerce' ),
			'INT.SP.SURF' => __( 'Small Packet International Surface', 'wpruby-canada-post-shipping-for-woocommerce' ),
			'INT.IP.AIR'  => __( 'International Parcel Air', 'wpruby-canada-post-shipping-for-woocommerce' ),
			'INT.IP.SURF' => __( 'International Parcel Surface', 'wpruby-canada-post-shipping-for-woocommerce' ),
		);
	}

	/**
	 * The name of one service, falling back to the name Canada Post sent, then the code.
	 */
	public static function name( string $code, string $fallback = '' ): string {
		$names = self::names();
		if ( isset( $names[ $code ] ) ) {
			return $names[ $code ];
		}
		return '' !== $fallback ? $fallback : $code;
	}

	/**
	 * The region a destination country belongs to.
	 */
	public static function region_for_country( string $country ): string {
		$country = strtoupper( $country );
		if ( 'CA' === $country ) {
			return self::REGION_DOMESTIC;
		}
		return 'US' === $country ? self::REGION_USA : self::REGION_INTERNATIONAL;
	}

	/**
	 * Default per-zone service settings, in the format Canada Post Pro uses.
	 *
	 * @return array<string,array>
	 */
	public static function defaults(): array {
		$out   = array();
		$order = 0;
		foreach ( array_keys( self::REGIONS ) as $code ) {
			$out[ $code ] = array(
				'enabled'         => ! in_array( $code, self::OFF_BY_DEFAULT, true ),
				'name'            => '',
				'adjustment'      => '',
				'adjustment_type' => 'fixed',
				'order'           => $order++,
			);
		}
		return $out;
	}
}
