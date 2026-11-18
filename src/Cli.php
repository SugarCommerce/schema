<?php
/**
 * The `schema` command.
 *
 * @package     SugarCommerce\Schema
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema;

/**
 * Command-line entry point.
 *
 *     vendor/bin/schema generate [--only=orders,prices]
 *     vendor/bin/schema check
 *     vendor/bin/schema reconcile [--apply [--drop [--force]]] [--wp=path]
 *
 * Every command takes `--root=path` (the plugin; default: the current
 * folder) and `--config=file.json` (settings over the rest).
 *
 * phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text for a terminal, never a web page.
 *
 * @since 1.0.0
 */
final class Cli {

	/**
	 * Usage.
	 *
	 * @since 1.0.0
	 */
	private const USAGE = <<<'TXT'
Usage: schema <command> [options]

  generate [--only=table,...]   Write the classes, functions and lock file
  check                         Exit 1 when a generated file is out of date
  reconcile [--apply] [--drop] [--force] [--wp=path]
                                Compare live tables with the schema (needs WordPress)

Options for every command:
  --root=path                   The plugin folder (default: the current folder)
  --config=file.json            Settings over composer.json's extra.schema

TXT;

	/**
	 * Run a command.
	 *
	 * @param array<int, string> $argv   The arguments, the script's name first.
	 * @param resource|null      $errors Where a reason to stop is written; STDERR by default.
	 *
	 * @return int The exit code.
	 * @since 1.0.0
	 */
	public static function main( array $argv, $errors = null ): int {
		$command = $argv[1] ?? 'help';
		$options = self::options( array_slice( $argv, 2 ) );
		$root    = (string) ( $options['root'] ?? getcwd() );

		try {
			$config = Config::load( $root, isset( $options['config'] ) ? (string) $options['config'] : null );

			return match ( $command ) {
				'generate'  => self::generate( $config, $options ),
				'check'     => self::check( $config ),
				'reconcile' => self::reconcile( $config, $options ),
				default     => self::help( $command ),
			};
		} catch ( GeneratorException $e ) {
			self::error( $e->getMessage(), $errors );

			return 1;
		}
	}

	/**
	 * `generate`.
	 *
	 * @param Config               $config  The settings.
	 * @param array<string, mixed> $options The options.
	 *
	 * @return int
	 * @throws GeneratorException When writing would lose code.
	 * @since 1.0.0
	 */
	private static function generate( Config $config, array $options ): int {
		$only = isset( $options['only'] ) ? array_values( array_filter( array_map( 'trim', explode( ',', (string) $options['only'] ) ) ) ) : null;
		$done = ( new Generator( $config ) )->generate( $only );

		foreach ( $done['removed'] as $path ) {
			echo "  removed {$path}\n";
		}

		printf(
			"  %d files | %d tables (%d base + %d meta) | %d components\n",
			$done['files'],
			$done['tables'],
			$done['components'],
			$done['meta'],
			$done['components']
		);
		printf( "  %d files | %d extension points\n", $done['functions'], $done['hooks'] );

		return 0;
	}

	/**
	 * `check`.
	 *
	 * @param Config $config The settings.
	 *
	 * @return int
	 * @throws GeneratorException When a column names an unknown kind.
	 * @since 1.0.0
	 */
	private static function check( Config $config ): int {
		$drift = ( new Generator( $config ) )->check();

		if ( [] === $drift ) {
			echo "  Every generated file matches the schema.\n";

			return 0;
		}

		foreach ( $drift as $path => $why ) {
			printf( "  %-8s %s\n", $why, $path );
		}

		echo "\n  Run vendor/bin/schema generate, and commit what it writes.\n";

		return 1;
	}

	/**
	 * `reconcile`.
	 *
	 * WordPress is loaded by the `schema` script before this runs, at file
	 * scope -- wp-config.php sets globals, and included from inside a method
	 * they would be that method's locals instead.
	 *
	 * @param Config               $config  The settings.
	 * @param array<string, mixed> $options The options.
	 *
	 * @return int
	 * @throws GeneratorException When WordPress is not loaded.
	 * @since 1.0.0
	 */
	private static function reconcile( Config $config, array $options ): int {
		if ( ! isset( $GLOBALS['wpdb'] ) || ! is_object( $GLOBALS['wpdb'] ) ) {
			throw new GeneratorException( 'reconcile needs WordPress loaded. Run it as vendor/bin/schema reconcile.' );
		}

		return ( new Reconciler( $config, $GLOBALS['wpdb'] ) )->run(
			! empty( $options['apply'] ),
			! empty( $options['drop'] ),
			! empty( $options['force'] )
		);
	}

	/**
	 * The wp-load.php a `reconcile` run needs, from the arguments.
	 *
	 * @param array<int, string> $argv   The arguments, the script's name first.
	 * @param resource|null      $errors Where a reason to stop is written; STDERR by default.
	 *
	 * @return string|null The file, or null (having said why) when there is none.
	 * @since 1.0.0
	 */
	public static function wordpress( array $argv, $errors = null ): ?string {
		$options = self::options( array_slice( $argv, 2 ) );

		try {
			return Reconciler::find_wordpress( (string) ( $options['root'] ?? getcwd() ), isset( $options['wp'] ) ? (string) $options['wp'] : null );
		} catch ( GeneratorException $e ) {
			self::error( $e->getMessage(), $errors );

			return null;
		}
	}

	/**
	 * Say why the command stopped.
	 *
	 * @param string        $message The reason.
	 * @param resource|null $errors  Where to write it; STDERR by default.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function error( string $message, $errors = null ): void {
		fwrite( $errors ?? STDERR, "\n  " . $message . "\n\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- The terminal.
	}

	/**
	 * Usage, for `help` or anything unknown.
	 *
	 * @param string $command What was asked for.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	private static function help( string $command ): int {
		echo self::USAGE;

		return in_array( $command, [ 'help', '--help', '-h' ], true ) ? 0 : 1;
	}

	/**
	 * `--key=value` and `--flag`, as an array.
	 *
	 * @param array<int, string> $args The arguments after the command.
	 *
	 * @return array<string, string|bool>
	 * @since 1.0.0
	 */
	private static function options( array $args ): array {
		$out = [];

		foreach ( $args as $arg ) {
			if ( ! str_starts_with( $arg, '--' ) ) {
				continue;
			}

			$parts = explode( '=', substr( $arg, 2 ), 2 );

			$out[ $parts[0] ] = $parts[1] ?? true;
		}

		return $out;
	}
}
