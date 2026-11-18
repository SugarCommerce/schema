<?php
/**
 * Shared helpers for the suite.
 *
 * @package SugarCommerce\Schema
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema\Tests;

use SugarCommerce\Schema\Config;

/**
 * Temporary plugin folders, cleaned up after each test.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase {

	/**
	 * Folders made by this test.
	 *
	 * @var array<int, string>
	 */
	private array $temporary = [];

	/**
	 * Remove the folders.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( $this->temporary as $dir ) {
			self::remove( $dir );
		}

		$this->temporary = [];

		parent::tearDown();
	}

	/**
	 * A new empty folder.
	 *
	 * @return string
	 */
	protected function folder(): string {
		$dir = sys_get_temp_dir() . '/sugarcommerce-schema-test-' . bin2hex( random_bytes( 6 ) );

		mkdir( $dir, 0777, true );

		$this->temporary[] = $dir;

		return $dir;
	}

	/**
	 * A copy of the Acme fixture's input, ready to generate into.
	 *
	 * @return string The folder.
	 */
	protected function acme(): string {
		$dir = $this->folder();

		self::copy( self::fixture( 'acme/input' ), $dir );

		return $dir;
	}

	/**
	 * A plugin folder with a schema and settings.
	 *
	 * @param array<string, mixed> $schema   The schema file's contents.
	 * @param array<string, mixed> $settings extra.schema.
	 *
	 * @return string The folder.
	 */
	protected function plugin( array $schema, array $settings = [] ): string {
		$dir = $this->folder();

		self::put( "{$dir}/bin/schema.json", json_encode( $schema, JSON_PRETTY_PRINT ) );
		self::put(
			"{$dir}/composer.json",
			json_encode(
				[
					'extra' => [
						'schema' => $settings + [
							'namespace' => 'Acme\\Database',
							'prefix'    => 'acme',
						],
					],
				],
				JSON_PRETTY_PRINT
			)
		);

		return $dir;
	}

	/**
	 * Settings for a folder.
	 *
	 * @param string               $root   The folder.
	 * @param array<string, mixed> $values Settings.
	 *
	 * @return Config
	 */
	protected static function config( string $root, array $values = [] ): Config {
		return new Config(
			$root,
			$values + [
				'namespace' => 'Acme\\Database',
				'prefix'    => 'acme',
			]
		);
	}

	/**
	 * A fixture's path.
	 *
	 * @param string $name Relative to tests/fixtures.
	 *
	 * @return string
	 */
	protected static function fixture( string $name ): string {
		return __DIR__ . '/fixtures/' . $name;
	}

	/**
	 * Write a file, making its folder.
	 *
	 * @param string $file The file.
	 * @param string $text Its contents.
	 *
	 * @return void
	 */
	protected static function put( string $file, string $text ): void {
		if ( ! is_dir( dirname( $file ) ) ) {
			mkdir( dirname( $file ), 0777, true );
		}

		file_put_contents( $file, $text );
	}

	/**
	 * Every file under a folder, relative to it.
	 *
	 * @param string $dir The folder.
	 *
	 * @return array<int, string>
	 */
	protected static function files( string $dir ): array {
		$out = [];

		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			$out[] = substr( $file->getPathname(), strlen( $dir ) + 1 );
		}

		sort( $out );

		return $out;
	}

	/**
	 * Copy a folder.
	 *
	 * @param string $from From.
	 * @param string $to   To.
	 *
	 * @return void
	 */
	protected static function copy( string $from, string $to ): void {
		foreach ( self::files( $from ) as $relative ) {
			self::put( "{$to}/{$relative}", (string) file_get_contents( "{$from}/{$relative}" ) );
		}
	}

	/**
	 * Remove a folder and everything in it.
	 *
	 * @param string $dir The folder.
	 *
	 * @return void
	 */
	private static function remove( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}

		rmdir( $dir );
	}
}
