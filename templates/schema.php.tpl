
/**
 * {desc}
 *
 * Generated from {schema} -- edit that and re-run
 * {command} rather than changing this by hand.
 *
 * @since 1.0.0
 */
class {name} extends {base} {

	/**
	 * Columns.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public $columns = array(

{columns}

	);

	/**
	 * Indexes.
	 *
	 * BerlinDB 3.0 builds CREATE TABLE from these, and does not derive
	 * PRIMARY from a column's flag -- it has to be declared here or the
	 * table is created without a primary key.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public $indexes = array(

{indexes}

	);
{adapters}}
