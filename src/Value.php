<?php
/**
 * Two rules about values the generated files have always followed.
 *
 * @package     SugarCommerce\Schema
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema;

/**
 * Truth and text, as the first generator decided them.
 *
 * The generator began as a script in another language, and the files it
 * wrote are in plugins now. Two of its rules show in the output -- which
 * values count as "set", and how a value is printed into a template -- so
 * they are kept exactly, or regenerating would change files nobody meant
 * to change.
 *
 * @since 1.0.0
 */
final class Value {

	/**
	 * Whether a value counts as set.
	 *
	 * Nothing, false, nought, an empty string and an empty list do not.
	 * Unlike PHP's own rule, the string "0" does.
	 *
	 * @param mixed $value The value.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function truthy( mixed $value ): bool {
		return ! ( null === $value || false === $value || 0 === $value || 0.0 === $value || '' === $value || [] === $value );
	}

	/**
	 * A value as it is printed into a template.
	 *
	 * @param mixed $value The value.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function text( mixed $value ): string {
		return match ( true ) {
			true === $value  => 'True',
			false === $value => 'False',
			null === $value  => 'None',
			is_float( $value ) => Json::encode( $value ),
			is_scalar( $value ) => (string) $value,
			default => Json::encode( $value ),
		};
	}
}
