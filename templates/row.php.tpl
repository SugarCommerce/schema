
/**
 * One {sing}.
 *
 * Properties are declared rather than left dynamic: PHP 8.2 deprecates
 * dynamic properties, and an undeclared column would be a notice on every
 * read rather than anything obvious.
 *
 * @since 1.0.0
 */
class {single} extends {base} {
{concern}
{props}
	/**
	 * Constructor.
	 *
	 * @param mixed $item Row from the database.
	 *
	 * @since 1.0.0
	 */
	public function __construct( $item ) {
		parent::__construct( $item );

		$this->id = (int) $this->id;
	}
}
