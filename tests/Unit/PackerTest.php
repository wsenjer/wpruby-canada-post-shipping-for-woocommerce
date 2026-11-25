<?php

use WPRuby\CanadaPostShipping\Rates\Packer;

final class PackerTest extends CpsTestCase {

	private function line( array $props, int $quantity = 1 ): array {
		return array( 'data' => new WC_Product( $props ), 'quantity' => $quantity );
	}

	public function test_items_go_together_stacked_in_one_parcel(): void {
		$parcels = ( new Packer( $this->settings() ) )->pack(
			array(
				$this->line( array( 'weight' => 1.2, 'length' => 30, 'width' => 20, 'height' => 5 ), 2 ),
				$this->line( array( 'weight' => 0.3, 'length' => 10, 'width' => 25, 'height' => 4 ) ),
			)
		);

		$this->assertSame( array( array( 'weight' => 2.7, 'length' => 30.0, 'width' => 20.0, 'height' => 14.0 ) ), $parcels );
	}

	public function test_a_new_parcel_starts_above_the_weight_limit(): void {
		$parcels = ( new Packer( $this->settings( array( 'max_weight' => '10' ) ) ) )->pack( array( $this->line( array( 'weight' => 4 ), 5 ) ) );

		$this->assertSame( array( 8.0, 8.0, 4.0 ), array_column( $parcels, 'weight' ) );
		$this->assertArrayNotHasKey( 'length', $parcels[0] );
	}

	public function test_each_item_can_ship_on_its_own(): void {
		$parcels = ( new Packer( $this->settings( array( 'packing' => 'individual', 'packaging_weight' => '0.2' ) ) ) )->pack( array( $this->line( array( 'weight' => 1 ), 3 ) ) );

		$this->assertSame( array( 1.2, 1.2, 1.2 ), array_column( $parcels, 'weight' ) );
	}

	public function test_store_units_are_converted_to_kg_and_cm(): void {
		TestState::$options['woocommerce_weight_unit']    = 'g';
		TestState::$options['woocommerce_dimension_unit'] = 'mm';

		$parcels = ( new Packer( $this->settings() ) )->pack( array( $this->line( array( 'weight' => 750, 'length' => 300, 'width' => 200, 'height' => 100 ) ) ) );

		$this->assertSame( array( array( 'weight' => 0.75, 'length' => 30.0, 'width' => 20.0, 'height' => 10.0 ) ), $parcels );
	}

	public function test_products_without_weight_or_size_use_the_defaults(): void {
		$settings = $this->settings( array( 'default_weight' => '0.8', 'default_length' => '20', 'default_width' => '15', 'default_height' => '10' ) );

		$parcels = ( new Packer( $settings ) )->pack( array( $this->line( array() ) ) );

		$this->assertSame( array( array( 'weight' => 0.8, 'length' => 20.0, 'width' => 15.0, 'height' => 10.0 ) ), $parcels );
	}

	public function test_virtual_products_are_skipped(): void {
		$this->assertSame( array(), ( new Packer( $this->settings() ) )->pack( array( $this->line( array( 'weight' => 1, 'virtual' => true ) ) ) ) );
	}
}
