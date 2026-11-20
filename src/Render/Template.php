<?php
/**
 * The text of each generated file, with gaps.
 *
 * @package     SugarCommerce\Schema
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema\Render;

/**
 * Templates, from templates/*.php.tpl.
 *
 * Kept as files rather than strings in the classes so that what a generated
 * file says can be read, and changed, as the file it becomes. `{name}` is a
 * gap the generator fills; anything else is written as it stands, so every
 * byte here is a byte in somebody's plugin.
 *
 * @since 1.0.0
 */
final class Template {

	/**
	 * Templates read so far.
	 *
	 * @var array<string, string>
	 * @since 1.0.0
	 */
	private static array $cache = [];

	/**
	 * One template's text.
	 *
	 * @param string $name Its name, without `.php.tpl`.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function get( string $name ): string {
		if ( ! isset( self::$cache[ $name ] ) ) {
			$text = file_get_contents( dirname( __DIR__, 2 ) . "/templates/{$name}.php.tpl" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A file of this package, read by a command-line tool.

			if ( false === $text ) {
				throw new \LogicException( "There is no template {$name}." );
			}

			self::$cache[ $name ] = $text;
		}

		return self::$cache[ $name ];
	}
}
