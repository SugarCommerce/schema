<?php
/**
 * Reading a plugin's settings.
 *
 * @package SugarCommerce\Schema
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema\Tests;

use SugarCommerce\Schema\Config;
use SugarCommerce\Schema\Generator;
use SugarCommerce\Schema\GeneratorException;

/**
 * Required settings, derived ones, and which source wins.
 */
final class ConfigTest extends TestCase {

	/**
	 * A namespace and a prefix are required.
	 *
	 * @return void
	 */
	public function test_namespace_and_prefix_are_required(): void {
		foreach ( [ 'namespace', 'prefix' ] as $key ) {
			try {
				new Config(
					'/x',
					array_diff_key(
						[
							'namespace' => 'Acme\\Database',
							'prefix'    => 'acme',
						],
						[ $key => true ]
					)
				);
				$this->fail( "No {$key} was accepted." );
			} catch ( GeneratorException $e ) {
				$this->assertStringContainsString( "No \"{$key}\" is set", $e->getMessage() );
			}
		}
	}

	/**
	 * Everything else follows from those two.
	 *
	 * @return void
	 */
	public function test_derived_settings(): void {
		$config = new Config(
			'/x/',
			[
				'namespace' => '\\Acme\\Database\\',
				'prefix'    => 'acme',
				'kern'      => 'Acme\\Vendor\\BerlinDB\\Database\\Kern',
				'schema'    => 'db/tables.json',
			]
		);

		$this->assertSame( '/x', $config->root );
		$this->assertSame( 'Acme\\Database', $config->get( 'namespace' ) );
		$this->assertSame( 'Acme', $config->get( 'package' ) );
		$this->assertSame( 'acme', $config->get( 'function_prefix' ) );
		$this->assertSame( 'acme', $config->get( 'hook_prefix' ) );
		$this->assertSame( 'acme_register_component', $config->get( 'register_function' ) );
		$this->assertSame( 'acme_register_components', $config->get( 'register_action' ) );
		$this->assertSame( 'db/tables.lock.json', $config->get( 'lock' ) );
		$this->assertSame( 'src/Database/Rows/Concerns', $config->get( 'paths' )['concerns'] );
		$this->assertSame(
			[
				'schema' => 'Acme\\Vendor\\BerlinDB\\Database\\Kern\\Schema',
				'table'  => 'Acme\\Vendor\\BerlinDB\\Database\\Kern\\Table',
				'row'    => 'Acme\\Vendor\\BerlinDB\\Database\\Kern\\Row',
				'query'  => 'SugarCommerce\\Data\\Query',
			],
			$config->get( 'bases' )
		);
		$this->assertSame( Config::default_rules(), $config->get( 'rules' ) );
		$this->assertSame( '/x/src/a.php', $config->path( '/src/a.php' ) );
	}

	/**
	 * A different prefix for functions and for hooks.
	 *
	 * @return void
	 */
	public function test_prefixes_can_differ(): void {
		$config = new Config(
			'/x',
			[
				'namespace'       => 'Acme\\Database',
				'prefix'          => 'acme',
				'function_prefix' => 'acme_shop',
				'hook_prefix'     => 'acme_hooks',
			]
		);

		$this->assertSame( 'acme_shop_register_component', $config->get( 'register_function' ) );
		$this->assertSame( 'acme_hooks_register_components', $config->get( 'register_action' ) );
	}

	/**
	 * Composer, then the schema's "$config", then --config; lists replace.
	 *
	 * @return void
	 */
	public function test_sources_layer(): void {
		$dir = $this->plugin(
			[
				'$config' => [
					'prefix'     => 'from_schema',
					'components' => [ 'summary' => 'From the schema.' ],
					'rules'      => [ [ 'kind' => 'text' ] ],
				],
				'orders'  => [
					'singular' => 'order',
					'columns'  => [],
				],
			],
			[
				'default_number' => 'ACME_NUMBER',
				'components'     => [ 'class' => 'Bundled' ],
				'rules'          => [
					[ 'kind' => 'key' ],
					[ 'kind' => 'id' ],
				],
			]
		);

		self::put( "{$dir}/over.json", (string) json_encode( [ 'default_number' => '25' ] ) );

		$config = Config::load( $dir, "{$dir}/over.json" );

		$this->assertSame( 'from_schema', $config->get( 'prefix' ) );
		$this->assertSame( '25', $config->get( 'default_number' ) );
		$this->assertSame(
			[
				'class'   => 'Bundled',
				'summary' => 'From the schema.',
			],
			$config->get( 'components' )
		);
		$this->assertSame( [ [ 'kind' => 'text' ] ], $config->get( 'rules' ), 'A list replaces a list.' );
	}

	/**
	 * A schema somewhere else is found through composer.json.
	 *
	 * @return void
	 */
	public function test_the_schema_path_is_configurable(): void {
		$dir = $this->folder();

		self::put( "{$dir}/db/tables.json", (string) json_encode( [ 'notes' => [ 'singular' => 'note', 'columns' => [] ] ] ) );
		self::put(
			"{$dir}/composer.json",
			(string) json_encode(
				[
					'extra' => [
						'schema' => [
							'schema'    => 'db/tables.json',
							'namespace' => 'Acme\\Database',
							'prefix'    => 'acme',
						],
					],
				]
			)
		);

		( new Generator( Config::load( $dir ) ) )->generate();

		$this->assertFileExists( "{$dir}/db/tables.lock.json" );
		$this->assertFileExists( "{$dir}/functions/notes.php", 'No functions_dir puts the file at the top.' );
		$this->assertStringContainsString( 'Generated from db/tables.json', (string) file_get_contents( "{$dir}/src/Database/Schemas/Notes.php" ) );
	}

	/**
	 * Base classes, header and @since come from the settings.
	 *
	 * @return void
	 */
	public function test_bases_and_header_reach_the_files(): void {
		$dir = $this->plugin(
			[
				'notes' => [
					'singular' => 'note',
					'alias'    => 'n',
					'columns'  => [],
				],
			],
			[
				'bases'  => [
					'query' => 'Shop\\Database\\Query',
					'row'   => 'Shop\\Database\\Row',
				],
				'header' => [
					'copyright' => '',
					'license'   => '',
					'since'     => '2.4.0',
				],
			]
		);

		( new Generator( Config::load( $dir ) ) )->generate();

		$query = (string) file_get_contents( "{$dir}/src/Database/Queries/Note.php" );
		$row   = (string) file_get_contents( "{$dir}/src/Database/Rows/Note.php" );
		$table = (string) file_get_contents( "{$dir}/src/Database/Tables/Notes.php" );

		$this->assertStringContainsString( "use Shop\\Database\\Query;\n", $query );
		$this->assertStringContainsString( 'class Note extends Query {', $query );
		$this->assertStringContainsString( "use Shop\\Database\\Row;\n", $row );
		$this->assertStringContainsString( "use BerlinDB\\Database\\Kern\\Table;\n", $table );
		$this->assertStringContainsString( '@since 2.4.0', $query );
		$this->assertStringNotContainsString( '1.0.0', $query . $row . $table );
		$this->assertStringNotContainsString( '@copyright', $query );
		$this->assertStringNotContainsString( '@license', $query );
		$this->assertStringContainsString( "protected \$version = 1;\n", $table, 'The default initial version.' );
		$this->assertStringContainsString( 'vendor/bin/schema generate rather than', (string) file_get_contents( "{$dir}/src/Database/Schemas/Notes.php" ) );
	}

	/**
	 * A malformed composer.json says so.
	 *
	 * @return void
	 */
	public function test_unreadable_json_is_reported(): void {
		$dir = $this->folder();

		self::put( "{$dir}/composer.json", '{ nope' );

		$this->expectException( GeneratorException::class );
		$this->expectExceptionMessage( 'is not a JSON object' );

		Config::load( $dir );
	}
}
