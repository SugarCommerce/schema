<?php
/**
 * Table versions and the lock file.
 *
 * @package SugarCommerce\Schema
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema\Tests;

use SugarCommerce\Schema\Support\Json;
use SugarCommerce\Schema\Render\Versions;

/**
 * A version moves when, and only when, the table's shape does.
 */
final class VersionsTest extends TestCase {

	/**
	 * A table definition, as decoded objects.
	 *
	 * @param array<string, mixed> $table The table.
	 *
	 * @return \stdClass
	 */
	private static function table( array $table ): \stdClass {
		return json_decode( (string) json_encode( $table ) );
	}

	/**
	 * A small table.
	 *
	 * @param array<string, mixed> $column_extra Keys to add to its one column.
	 *
	 * @return \stdClass
	 */
	private static function orders( array $column_extra = [] ): \stdClass {
		return self::table(
			[
				'singular' => 'order',
				'uuid'     => false,
				'columns'  => [
					[
						'name'   => 'status',
						'type'   => 'varchar',
						'length' => 20,
					] + $column_extra,
				],
			]
		);
	}

	/**
	 * The fingerprint is the first generation's: a known table, a known hash.
	 *
	 * @return void
	 */
	public function test_the_fingerprint_is_stable(): void {
		$lock   = json_decode( (string) file_get_contents( self::fixture( 'acme/expected/bin/schema.lock.json' ) ), true );
		$schema = json_decode( (string) file_get_contents( self::fixture( 'acme/input/bin/schema.json' ) ) );

		foreach ( $lock as $plural => $entry ) {
			$this->assertSame( $entry['shape'], Versions::fingerprint( $schema->{$plural} ), $plural );
		}
	}

	/**
	 * How a value is shaped is not part of the table.
	 *
	 * @return void
	 */
	public function test_shaping_keys_do_not_move_the_fingerprint(): void {
		$plain = Versions::fingerprint( self::orders() );

		foreach ( [
			[ 'kind' => 'key' ],
			[ 'sanitize' => 'my_clean' ],
			[ 'default_function' => 'my_default' ],
			[ 'transition' => false ],
		] as $extra ) {
			$this->assertSame( $plain, Versions::fingerprint( self::orders( $extra ) ), (string) json_encode( $extra ) );
		}
	}

	/**
	 * Anything MySQL hears of does move it.
	 *
	 * @return void
	 */
	public function test_ddl_keys_move_the_fingerprint(): void {
		$plain = Versions::fingerprint( self::orders() );

		foreach ( [
			[ 'default' => 'x' ],
			[ 'index' => true ],
			[ 'allow_null' => true ],
			[ 'unique' => true ],
		] as $extra ) {
			$this->assertNotSame( $plain, Versions::fingerprint( self::orders( $extra ) ), (string) json_encode( $extra ) );
		}

		$this->assertNotSame( $plain, Versions::fingerprint( self::table( [ 'uuid' => true ] + (array) json_decode( (string) json_encode( self::orders() ), true ) ) ) );
	}

	/**
	 * A new table gets the initial version; an unchanged one keeps its own.
	 *
	 * @return void
	 */
	public function test_new_and_unchanged_tables(): void {
		$versions = new Versions( '202601010', static fn(): string => '20261117' );
		$mark     = Versions::fingerprint( self::orders() );

		$this->assertSame( [ 'orders' => [ 'shape' => $mark, 'version' => 202601010 ] ], $versions->stamp( [ 'orders' => self::orders() ], [] ) );
		$this->assertSame(
			[ 'orders' => [ 'shape' => $mark, 'version' => 202603041 ] ],
			$versions->stamp( [ 'orders' => self::orders() ], [ 'orders' => [ 'shape' => $mark, 'version' => 202603041 ] ] )
		);
	}

	/**
	 * A changed table moves to today's date and a nought.
	 *
	 * @return void
	 */
	public function test_a_changed_table_moves_to_today(): void {
		$versions = new Versions( '1', static fn(): string => '20261117' );
		$stamped  = $versions->stamp( [ 'orders' => self::orders( [ 'default' => 'new' ] ) ], [ 'orders' => [ 'shape' => 'old', 'version' => 202603041 ] ] );

		$this->assertSame( 202611170, $stamped['orders']['version'] );
	}

	/**
	 * Changed twice in one day, it moves by one: never backwards, never equal.
	 *
	 * @return void
	 */
	public function test_a_second_change_today_counts_up(): void {
		$versions = new Versions( '1', static fn(): string => '20261117' );
		$stamped  = $versions->stamp( [ 'orders' => self::orders( [ 'default' => 'new' ] ) ], [ 'orders' => [ 'shape' => 'old', 'version' => 202611170 ] ] );

		$this->assertSame( 202611171, $stamped['orders']['version'] );
	}

	/**
	 * A table gone from the schema is gone from the lock.
	 *
	 * @return void
	 */
	public function test_a_removed_table_leaves_the_lock(): void {
		$versions = new Versions( '1', static fn(): string => '20261117' );
		$stamped  = $versions->stamp( [ 'orders' => self::orders() ], [ 'gone' => [ 'shape' => 'x', 'version' => 5 ] ] );

		$this->assertSame( [ 'orders' ], array_keys( $stamped ) );
	}

	/**
	 * The lock file's format: two-space indent, a newline at the end.
	 *
	 * @return void
	 */
	public function test_the_lock_file_format(): void {
		$this->assertSame(
			"{\n  \"orders\": {\n    \"shape\": \"abc\",\n    \"version\": 3\n  }\n}\n",
			Versions::lock_file( [ 'orders' => [ 'shape' => 'abc', 'version' => 3 ] ] )
		);
		$this->assertSame( "{}\n", Versions::lock_file( [] ) );
	}

	/**
	 * The encoder the fingerprint depends on.
	 *
	 * @return void
	 */
	public function test_the_encoder(): void {
		$value = json_decode( '{"b":[1,true,null,"x/y"],"a":{"é":"☃ 😀","q":"\"\\\\\n\t"}}' );

		$this->assertSame(
			'{"a": {"q": "\\"\\\\\\n\\t", "\\u00e9": "\\u2603 \\ud83d\\ude00"}, "b": [1, true, null, "x/y"]}',
			Json::encode( $value, true )
		);
		$this->assertSame( '{"b": [1, true, null, "x/y"], "a": {"\\u00e9": "\\u2603 \\ud83d\\ude00", "q": "\\"\\\\\\n\\t"}}', Json::encode( $value ) );
		$this->assertSame( '[1.5, 2.0, "\\u007f"]', Json::encode( [ 1.5, 2.0, "\x7f" ] ) );
	}
}
