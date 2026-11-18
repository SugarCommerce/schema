<?php
/**
 * Working out what a column holds.
 *
 * @package SugarCommerce\Schema
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema\Tests;

use SugarCommerce\Schema\GeneratorException;
use SugarCommerce\Schema\Kinds;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Kinds from rules, and the callback each becomes.
 */
final class KindsTest extends TestCase {

	/**
	 * Kinds for the default rules.
	 *
	 * @param array<string, mixed> $settings More settings.
	 *
	 * @return Kinds
	 */
	private function kinds( array $settings = [] ): Kinds {
		return new Kinds(
			self::config(
				$this->folder(),
				$settings + [
					'kinds' => [
						'id'       => 'acme_id',
						'count'    => 'acme_count',
						'amount'   => 'acme_amount',
						'flag'     => 'acme_flag',
						'datetime' => 'acme_datetime',
						'rate'     => 'acme_rate',
						'currency' => 'acme_currency',
						'country'  => 'acme_country',
						'email'    => 'acme_email',
						'ip'       => 'acme_ip',
						'key'      => [ 'acme_key', true ],
						'text'     => [
							'function' => 'acme_text',
							'length'   => true,
						],
						'textarea' => [ 'acme_textarea', true ],
						'html'     => 'acme_html',
					],
				]
			)
		);
	}

	/**
	 * Columns and the kind the default rules give each.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: string|null}>
	 */
	public static function columns(): array {
		return [
			'datetime'           => [ [ 'name' => 'date_paid', 'type' => 'datetime' ], 'datetime' ],
			'tinyint(1) is flag' => [ [ 'name' => 'featured', 'type' => 'tinyint', 'length' => 1 ], 'flag' ],
			'tinyint(4) counts'  => [ [ 'name' => 'level', 'type' => 'tinyint', 'length' => 4 ], 'count' ],
			'flag before id'     => [ [ 'name' => 'is_id', 'type' => 'tinyint', 'length' => 1 ], 'flag' ],
			'*_id'               => [ [ 'name' => 'customer_id', 'type' => 'bigint' ], 'id' ],
			'amount'             => [ [ 'name' => 'amount', 'type' => 'bigint' ], 'amount' ],
			'amount_*'           => [ [ 'name' => 'amount_tax', 'type' => 'bigint' ], 'amount' ],
			'*_fee'              => [ [ 'name' => 'stripe_fee', 'type' => 'int' ], 'amount' ],
			'other integer'      => [ [ 'name' => 'sort_order', 'type' => 'int' ], 'count' ],
			'*_rate'             => [ [ 'name' => 'tax_rate', 'type' => 'decimal' ], 'rate' ],
			'other decimal'      => [ [ 'name' => 'weight', 'type' => 'decimal' ], null ],
			'text'               => [ [ 'name' => 'email', 'type' => 'text' ], 'textarea' ],
			'currency'           => [ [ 'name' => 'currency', 'type' => 'char' ], 'currency' ],
			'*_currency'         => [ [ 'name' => 'payout_currency', 'type' => 'char' ], 'currency' ],
			'country'            => [ [ 'name' => 'country', 'type' => 'char' ], 'country' ],
			'email'              => [ [ 'name' => 'email', 'type' => 'varchar' ], 'email' ],
			'ip'                 => [ [ 'name' => 'ip', 'type' => 'varchar' ], 'ip' ],
			'status'             => [ [ 'name' => 'status', 'type' => 'varchar' ], 'key' ],
			'*_type'             => [ [ 'name' => 'object_type', 'type' => 'varchar' ], 'key' ],
			'anything else'      => [ [ 'name' => 'title', 'type' => 'varchar' ], 'text' ],
			'a date'             => [ [ 'name' => 'day', 'type' => 'date' ], 'text' ],
			'stamped created'    => [ [ 'name' => 'date_created', 'type' => 'datetime', 'created' => true ], null ],
			'stamped modified'   => [ [ 'name' => 'date_modified', 'type' => 'datetime', 'modified' => true ], null ],
			'uuid'               => [ [ 'name' => 'uuid', 'type' => 'varchar', 'uuid' => true ], null ],
			'auto increment'     => [ [ 'name' => 'id', 'type' => 'bigint', 'extra' => 'auto_increment' ], null ],
			'said out loud'      => [ [ 'name' => 'customer_id', 'type' => 'bigint', 'kind' => 'html' ], 'html' ],
		];
	}

	/**
	 * Each column gets its kind.
	 *
	 * @param array<string, mixed> $column The column.
	 * @param string|null          $kind   The kind it should get.
	 *
	 * @return void
	 */
	#[DataProvider( 'columns' )]
	public function test_the_kind( array $column, ?string $kind ): void {
		$this->assertSame( $kind, $this->kinds()->of( $column ) );
	}

	/**
	 * A kind nobody configured is refused, naming the ones there are.
	 *
	 * @return void
	 */
	public function test_an_unknown_kind_is_refused(): void {
		$this->expectException( GeneratorException::class );
		$this->expectExceptionMessage( "sku: there is no kind 'sku'. One of: amount, count, country," );

		$this->kinds()->of(
			[
				'name' => 'sku',
				'type' => 'varchar',
				'kind' => 'sku',
			]
		);
	}

	/**
	 * The first matching rule wins, and a plugin's rules replace the defaults.
	 *
	 * @return void
	 */
	public function test_rules_are_replaceable_and_ordered(): void {
		$kinds = $this->kinds(
			[
				'rules' => [
					[
						'kind'     => 'id',
						'patterns' => [ '/^(?=stripe_).*_id$/' ],
					],
					[
						'kind'  => 'email',
						'names' => [ 'stripe_id' ],
					],
					[ 'kind' => 'text' ],
				],
			]
		);

		$this->assertSame( 'id', $kinds->of( [ 'name' => 'stripe_customer_id', 'type' => 'varchar' ] ) );
		$this->assertSame( 'id', $kinds->of( [ 'name' => 'stripe_id', 'type' => 'varchar' ] ), 'The first rule matches first.' );
		$this->assertSame( 'text', $kinds->of( [ 'name' => 'amount', 'type' => 'bigint' ] ), 'The default rules are gone.' );
	}

	/**
	 * A kind a rule names but nobody configured has no callback.
	 *
	 * @return void
	 */
	public function test_an_unconfigured_kind_has_no_callback(): void {
		$this->assertSame( [ null, null ], ( new Kinds( self::config( $this->folder() ) ) )->shaper( [ 'name' => 'title', 'type' => 'varchar', 'length' => 20 ] ) );
	}

	/**
	 * The callbacks: named, adapted, or none at all.
	 *
	 * @return void
	 */
	public function test_the_callback(): void {
		$kinds = $this->kinds();

		$this->assertSame( [ "'acme_id'", null ], $kinds->shaper( [ 'name' => 'customer_id', 'type' => 'bigint', 'length' => 20 ] ) );
		$this->assertSame(
			[ "array( self::class, 'shape_title' )", [ 'title', 'acme_text', '200' ] ],
			$kinds->shaper( [ 'name' => 'title', 'type' => 'varchar', 'length' => 200 ] )
		);
		$this->assertSame( [ "'acme_text'", null ], $kinds->shaper( [ 'name' => 'title', 'type' => 'varchar' ] ), 'No length, nothing to pass on.' );
		$this->assertSame( [ "'acme_textarea'", null ], $kinds->shaper( [ 'name' => 'body', 'type' => 'text', 'length' => 9 ] ), 'A text type never passes a length.' );
		$this->assertSame( [ "'my_clean'", null ], $kinds->shaper( [ 'name' => 'title', 'type' => 'varchar', 'sanitize' => 'my_clean' ] ) );
		$this->assertSame( [ null, null ], $kinds->shaper( [ 'name' => 'title', 'type' => 'varchar', 'sanitize' => false ] ) );
	}

	/**
	 * A signed number says so, and the Schema class writes it out.
	 *
	 * @return void
	 */
	public function test_unsigned_false_is_written(): void {
		$columns = new \SugarCommerce\Schema\Columns( self::config( $this->folder() ), $this->kinds() );

		$this->assertSame(
			"\t\tarray(\n\t\t\t'name'     => 'balance',\n\t\t\t'type'     => 'bigint',\n\t\t\t'length'   => '20',\n\t\t\t'unsigned' => false,\n\t\t\t'sortable' => true,\n\t\t\t'default'  => '0',\n\t\t\t'validate' => 'acme_amount',\n\t\t),",
			$columns->column_php( [ 'name' => 'balance', 'type' => 'bigint', 'length' => 20, 'unsigned' => false, 'sortable' => true, 'default' => '0', 'kind' => 'amount' ] )
		);
		$this->assertStringNotContainsString( 'unsigned', $columns->column_php( [ 'name' => 'n', 'type' => 'int' ] ), 'Unsaid, BerlinDB decides.' );
	}

	/**
	 * A malformed kind is refused when the settings are read.
	 *
	 * @return void
	 */
	public function test_a_kind_without_a_function_is_refused(): void {
		$this->expectException( GeneratorException::class );
		$this->expectExceptionMessage( 'The kind "id" names no function.' );

		self::config( $this->folder(), [ 'kinds' => [ 'id' => [ 'length' => true ] ] ] );
	}
}
