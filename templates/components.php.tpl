use {ns}\Rows;
use {ns}\Schemas;
use {ns}\Tables;

/**
 * {summary}
 *
 * Registered on the same action an add-on would use, deliberately: if the
 * core components took a private path, the public one would be the part
 * nobody tested. Anything that breaks for an add-on breaks here first.
 *
 * Generated from {schema}.
 *
 * @since 1.0.0
 */
final class {class} {

	/**
	 * Hook registration.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function init(): void {
		add_action( '{action}', array( self::class, 'register' ) );
	}

	/**
	 * Register every component.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function register(): void {
{body}
	}
}
