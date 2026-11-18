<?php
/**
 * Why the generator stopped.
 *
 * @package     SugarCommerce\Schema
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema;

/**
 * A reason to stop before anything is written.
 *
 * The message is written for the person at the terminal: what is wrong and
 * what to do about it.
 *
 * @since 1.0.0
 */
final class GeneratorException extends \RuntimeException {
}
