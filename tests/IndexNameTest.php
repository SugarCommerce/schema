<?php
/**
 * What an index is called.
 *
 * @package SugarCommerce\Schema
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema\Tests;

use PHPUnit\Framework\TestCase;
use SugarCommerce\Schema\Render\Columns;

/**
 * An index is named after its columns, and never past MySQL's 64
 * characters: a longer name is refused by CREATE TABLE and ALTER TABLE.
 */
final class IndexNameTest extends TestCase {

	/**
	 * A short name is the columns joined, as it always was.
	 */
	public function test_a_short_name_is_the_columns_joined(): void {
		$this->assertSame( 'is_test_status_date_created', Columns::index_name( [ 'is_test', 'status', 'date_created' ] ) );

		$exactly = [ str_repeat( 'a', 30 ), str_repeat( 'b', 33 ) ];

		$this->assertSame( 64, strlen( implode( '_', $exactly ) ) );
		$this->assertSame( implode( '_', $exactly ), Columns::index_name( $exactly ) );
	}

	/**
	 * A long one is cut to 64 with a hash of the whole, stable and
	 * different for different columns.
	 */
	public function test_a_long_name_is_cut_and_stays_unique(): void {
		$covering = [ 'is_test', 'currency', 'date_ordered', 'prev_order', 'next_order', 'first_order', 'seq', 'net' ];
		$name     = Columns::index_name( $covering );

		$this->assertSame( 64, strlen( $name ) );
		$this->assertStringStartsWith( substr( implode( '_', $covering ), 0, 55 ) . '_', $name );
		$this->assertSame( $name, Columns::index_name( $covering ) );
		$this->assertNotSame( $name, Columns::index_name( array_merge( $covering, [ 'cum_net' ] ) ) );
	}

	/**
	 * The table's index list uses it, for plain and unique indexes alike.
	 */
	public function test_indexes_use_it(): void {
		$long    = [ 'is_test', 'currency', 'product_id', 'other_id', 'other_key', 'buyers', 'units' ];
		$indexes = Columns::indexes(
			[
				'columns' => [],
				'unique'  => [ $long ],
				'indexes' => [ array_reverse( $long ) ],
			]
		);

		foreach ( $indexes as $index ) {
			$this->assertLessThanOrEqual( 64, strlen( $index['name'] ), $index['name'] );
		}

		$this->assertSame( Columns::index_name( $long ), $indexes[1]['name'] );
		$this->assertSame( Columns::index_name( array_reverse( $long ) ), $indexes[2]['name'] );
	}
}
