<?php
/**
 * Groups cart items into Canada Post parcels.
 *
 * @package WPRuby\CanadaPostShipping
 */

namespace WPRuby\CanadaPostShipping\Rates;

use WPRuby\CanadaPostShipping\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Two simple modes:
 * - single: items go together, stacked, in as few parcels as the weight limit allows;
 * - individual: every item ships in its own parcel.
 * Weights are converted to kg and sizes to cm.
 */
class Packer {

	/**
	 * @var Settings
	 */
	private $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @param array $contents WooCommerce package contents (each with 'data' and 'quantity').
	 * @return array[] Parcels: weight (kg) and, when known, length, width, height (cm).
	 */
	public function pack( array $contents ): array {
		$items = array();
		foreach ( $contents as $line ) {
			$product = $line['data'] ?? null;
			if ( ! is_object( $product ) || ( method_exists( $product, 'needs_shipping' ) && ! $product->needs_shipping() ) ) {
				continue;
			}
			$item = $this->item( $product );
			for ( $i = 0; $i < (int) ( $line['quantity'] ?? 1 ); $i++ ) {
				$items[] = $item;
			}
		}
		if ( ! $items ) {
			return array();
		}

		$packaging = $this->settings->packaging_weight() > 0 ? (float) wc_get_weight( $this->settings->packaging_weight(), 'kg' ) : 0.0;

		if ( 'individual' === $this->settings->packing() ) {
			return array_map(
				function ( $item ) use ( $packaging ) {
					return $this->parcel( array( $item ), $packaging );
				},
				$items
			);
		}

		// First fit, heaviest first, within the weight limit (less the packaging).
		$limit = max( 0.001, $this->settings->max_weight_kg() - $packaging );
		usort(
			$items,
			static function ( $a, $b ) {
				return $b['weight'] <=> $a['weight'];
			}
		);
		$groups = array();
		$loads  = array();
		foreach ( $items as $item ) {
			$placed = false;
			foreach ( $groups as $index => $group ) {
				if ( $loads[ $index ] + $item['weight'] <= $limit + 1e-9 ) {
					$groups[ $index ][] = $item;
					$loads[ $index ]   += $item['weight'];
					$placed             = true;
					break;
				}
			}
			if ( ! $placed ) {
				$groups[] = array( $item );
				$loads[]  = $item['weight'];
			}
		}

		return array_map(
			function ( $group ) use ( $packaging ) {
				return $this->parcel( $group, $packaging );
			},
			$groups
		);
	}

	/**
	 * One item's weight (kg) and size (cm), using the defaults when the product has none.
	 *
	 * @param object $product WC_Product.
	 */
	private function item( $product ): array {
		$weight = (float) $product->get_weight();
		if ( $weight <= 0 ) {
			$weight = $this->settings->default_weight();
		}

		$sides = array( (float) $product->get_length(), (float) $product->get_width(), (float) $product->get_height() );
		if ( min( $sides ) <= 0 ) {
			$sides = $this->settings->default_dimensions();
		}
		$dimensions = null;
		if ( min( $sides ) > 0 ) {
			$dimensions = array_map(
				static function ( $side ) {
					return (float) wc_get_dimension( $side, 'cm' );
				},
				$sides
			);
			rsort( $dimensions );
		}

		return array(
			'weight'     => $weight > 0 ? (float) wc_get_weight( $weight, 'kg' ) : 0.0,
			'dimensions' => $dimensions,
		);
	}

	/**
	 * A parcel for a group of items: total weight plus packaging, and the items stacked
	 * (longest and widest item, heights added) when every item has a size.
	 *
	 * @param array[] $items Items from item().
	 */
	private function parcel( array $items, float $packaging ): array {
		$weight = $packaging;
		$length = 0.0;
		$width  = 0.0;
		$height = 0.0;
		$sized  = true;
		foreach ( $items as $item ) {
			$weight += $item['weight'];
			if ( null === $item['dimensions'] ) {
				$sized = false;
				continue;
			}
			$length  = max( $length, $item['dimensions'][0] );
			$width   = max( $width, $item['dimensions'][1] );
			$height += $item['dimensions'][2];
		}

		$parcel = array( 'weight' => max( 0.001, round( $weight, 3 ) ) );
		if ( $sized ) {
			$sides = array( $length, $width, $height );
			rsort( $sides );
			$parcel['length'] = round( $sides[0], 1 );
			$parcel['width']  = round( $sides[1], 1 );
			$parcel['height'] = round( $sides[2], 1 );
		}
		return $parcel;
	}
}
