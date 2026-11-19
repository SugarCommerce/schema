<?php
/**
 * The command.
 *
 * @package SugarCommerce\Schema
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema\Tests;

use SugarCommerce\Schema\Cli;

/**
 * `schema generate`, `check` and the rest, through the entry point.
 */
final class CliTest extends TestCase {

	/**
	 * Run the command, capturing what it prints.
	 *
	 * @param string ...$args Arguments after the script name.
	 *
	 * @return array{0: int, 1: string}
	 */
	private static function cli( string ...$args ): array {
		$errors = fopen( 'php://memory', 'w+' );

		ob_start();
		$code = Cli::main( array_merge( [ 'schema' ], $args ), $errors );
		$out  = (string) ob_get_clean();

		rewind( $errors );

		return [ $code, $out . stream_get_contents( $errors ) ];
	}

	/**
	 * Generate, then check.
	 *
	 * @return void
	 */
	public function test_generate_then_check(): void {
		$dir = $this->acme();

		[ $code, $out ] = self::cli( 'check', "--root={$dir}" );

		$this->assertSame( 1, $code );
		$this->assertStringContainsString( 'missing  src/Database/Schemas/Tickets.php', $out );

		[ $code, $out ] = self::cli( 'generate', "--root={$dir}" );

		$this->assertSame( 0, $code );
		$this->assertSame( "  17 files | 5 tables (3 base + 2 meta) | 3 components\n  3 files | 45 extension points\n", $out );

		[ $code, $out ] = self::cli( 'check', "--root={$dir}" );

		$this->assertSame( 0, $code );
		$this->assertStringContainsString( 'Every generated file matches the schema.', $out );
	}

	/**
	 * --only and --config reach the generator.
	 *
	 * @return void
	 */
	public function test_options(): void {
		$dir = $this->acme();

		self::put( "{$dir}/over.json", (string) json_encode( [ 'components' => [ 'class' => 'Bundled' ] ] ) );

		[ $code ] = self::cli( 'generate', "--root={$dir}", '--only=audit_entries', "--config={$dir}/over.json" );

		$this->assertSame( 0, $code );
		$this->assertFileExists( "{$dir}/src/Database/Components/Bundled.php" );
		$this->assertFileExists( "{$dir}/src/Database/Schemas/AuditEntries.php" );
		$this->assertFileDoesNotExist( "{$dir}/src/Database/Schemas/Tickets.php" );
	}

	/**
	 * A stop is exit 1, and an unknown command prints the usage.
	 *
	 * @return void
	 */
	public function test_failures(): void {
		[ $code, $out ] = self::cli( 'generate', '--root=' . $this->folder() );

		$this->assertSame( 1, $code, 'No settings at all.' );
		$this->assertStringContainsString( 'No "namespace" is set.', $out );

		[ $code, $out ] = self::cli( 'frobnicate', '--root=' . $this->acme() );

		$this->assertSame( 1, $code );
		$this->assertStringContainsString( 'Usage: schema <command>', $out );

		[ $code ] = self::cli( 'help', '--root=' . $this->acme() );

		$this->assertSame( 0, $code );
	}

	/**
	 * reconcile without WordPress says so rather than failing somewhere else.
	 *
	 * @return void
	 */
	public function test_reconcile_needs_wordpress(): void {
		[ $code, $out ] = self::cli( 'reconcile', '--root=' . $this->acme() );

		$this->assertSame( 1, $code );
		$this->assertStringContainsString( 'reconcile needs WordPress loaded.', $out );

		$errors = fopen( 'php://memory', 'w+' );

		$this->assertNull( Cli::wordpress( [ 'schema', 'reconcile', '--root=' . $this->folder(), '--wp=' . $this->folder() ], $errors ) );

		rewind( $errors );

		$this->assertStringContainsString( 'There is no wp-load.php in', (string) stream_get_contents( $errors ) );
	}
}
