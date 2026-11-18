<?php
/**
 * The docblock every generated file opens with.
 *
 * @package     SugarCommerce\Schema
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema;

/**
 * File headers, from the plugin's settings.
 *
 * @since 1.0.0
 */
final class Header {

	/**
	 * `<?php` and the file docblock.
	 *
	 * The copyright and licence lines are left out when they are set to
	 * nothing.
	 *
	 * @param Config $config  The settings.
	 * @param string $desc    What the file is.
	 * @param string $package Its @package.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function file( Config $config, string $desc, string $package ): string {
		$header = $config->get( 'header' );
		$lines  = [ " * @package     {$package}" ];

		if ( '' !== (string) $header['copyright'] ) {
			$lines[] = ' * @copyright   ' . $header['copyright'];
		}

		if ( '' !== (string) $header['license'] ) {
			$lines[] = ' * @license     ' . $header['license'];
		}

		$lines[] = ' * @since       ' . $header['since'];

		return "<?php\n/**\n * {$desc}\n *\n" . implode( "\n", $lines ) . "\n */\n";
	}

	/**
	 * The configured version in every `@since` of a template.
	 *
	 * @param Config $config The settings.
	 * @param string $text   The file.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function since( Config $config, string $text ): string {
		$since = (string) $config->get( 'header' )['since'];

		return '1.0.0' === $since ? $text : str_replace( '@since 1.0.0', '@since ' . $since, $text );
	}
}
