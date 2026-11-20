<?php
/**
 * The public CRUD functions for each table.
 *
 * @package     SugarCommerce\Schema
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema\Render;

use SugarCommerce\Schema\Config;
use SugarCommerce\Schema\Support\Value;

/**
 * Writes one functions file per table.
 *
 * Globally prefixed rather than namespaced. An add-on author calling
 * `{prefix}_get_order()` needs no `use function` line and no fully qualified
 * call -- and it is the shape most WordPress plugin APIs already have.
 *
 * Every read fires a filter and every write fires a before/after action
 * pair. That is a rule the generator enforces rather than something to
 * remember: six hooks a table, before any business logic.
 *
 * @since 1.0.0
 */
final class Functions {

	/**
	 * Constructor.
	 *
	 * @param Config $config The settings.
	 *
	 * @since 1.0.0
	 */
	public function __construct( private readonly Config $config ) {
	}

	/**
	 * Where a table's functions file goes.
	 *
	 * Which folder is data, beside the table: "functions_dir" in the schema.
	 * The folders are by subject -- orders/ holds orders, their items and
	 * their adjustments -- with the hand-written files about the same thing
	 * next to the generated ones. A table that names none goes at the top.
	 *
	 * @param string               $plural The table's name.
	 * @param array<string, mixed> $table  Its definition.
	 *
	 * @return string Relative to the root.
	 * @since 1.0.0
	 */
	public function path( string $plural, array $table ): string {
		$folder = trim( (string) ( $table['functions_dir'] ?? '' ), '/' );
		$root   = $this->config->get( 'paths' )['functions'];

		return '' === $folder ? "{$root}/{$plural}.php" : "{$root}/{$folder}/{$plural}.php";
	}

	/**
	 * A table's functions file.
	 *
	 * @param string               $plural The table's name.
	 * @param array<string, mixed> $table  Its definition.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function build( string $plural, array $table ): string {
		$sing  = (string) $table['singular'];
		$label = str_replace( '_', ' ', $sing );
		$vars  = [
			'{labels}'    => str_replace( '_', ' ', $plural ),
			'{ns}'        => $this->config->get( 'namespace' ),
			'{Sing}'      => Classes::studly( $sing ),
			'{a}'         => self::article( $sing ),
			'{label}'     => $label,
			'{fp}'        => (string) $this->config->get( 'function_prefix' ),
			'{hp}'        => (string) $this->config->get( 'hook_prefix' ),
			'{sing}'      => $sing,
			'{plural}'    => $plural,
			'{number}'    => (string) $this->config->get( 'default_number' ),
			'{meta_type}' => $this->config->get( 'prefix' ) . '_' . $sing,
		];

		$body = Header::file( $this->config, 'The public API for ' . $vars['{labels}'] . '.', (string) $this->config->get( 'package' ) )
			. strtr( Template::get( 'functions' ), $vars );

		if ( Value::truthy( $table['meta'] ?? false ) ) {
			$body .= strtr( Template::get( 'meta-functions' ), $vars );
		}

		return Header::since( $this->config, $body );
	}

	/**
	 * Every top-level function a functions file declares.
	 *
	 * @param string $text The file.
	 *
	 * @return array<int, string>
	 * @since 1.0.0
	 */
	public static function defined_in( string $text ): array {
		preg_match_all( '/^function\s+([a-z0-9_]+)/m', $text, $matches );

		return array_values( array_unique( $matches[1] ) );
	}

	/**
	 * How many filters and actions a file fires.
	 *
	 * @param string $text The file.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function hooks_in( string $text ): int {
		return substr_count( $text, 'apply_filters(' ) + substr_count( $text, 'do_action(' );
	}

	/**
	 * "a" or "an".
	 *
	 * @param string $word The word that follows.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private static function article( string $word ): string {
		return in_array( $word[0] ?? '', [ 'a', 'e', 'i', 'o', 'u' ], true ) ? 'an' : 'a';
	}
}
