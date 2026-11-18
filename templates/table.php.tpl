
/**
 * {desc}
 *
 * @since 1.0.0
 */
final class {name} extends {base} {

	/**
	 * Table name, without any prefix.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $name = '{unprefixed}';

	/**
	 * Table prefix, applied between $wpdb->prefix and the name.
	 *
	 * Without this BerlinDB defaults to an empty string and the table lands
	 * as wp_products rather than wp_{prefix}_products -- generic enough to
	 * collide with anything. It also drives the Query's meta type.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $prefix = '{prefix}';

	/**
	 * The class describing this table's columns and indexes.
	 *
	 * A class name, not a SQL string. BerlinDB 3.0 builds CREATE TABLE from
	 * the Schema object, and its own set_schema() is private -- a subclass
	 * declaring one writes a method nothing calls, and the table is simply
	 * never created, with no error anywhere.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $schema = '{schema}';

	/**
	 * Schema version.
	 *
	 * Written by the generator, and raised by it whenever this table's
	 * columns or indexes change in {schema_file}. BerlinDB compares it
	 * with the stored wpdb_{table}_version option; when this is higher it
	 * adds the columns and indexes an existing table is missing, so an
	 * upgrade needs no routine of its own.
	 *
	 * @var mixed
	 * @since 1.0.0
	 */
	protected $version = {version};

	/**
	 * Upgrades, keyed by the version that introduces them.
	 *
	 * Empty, and meant to stay so: adding columns and indexes is automatic.
	 * A routine belongs here only for what that cannot do -- moving data
	 * from one column to another.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $upgrades = array();
}
