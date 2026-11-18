
/**
 * Reads {plural}.
 *
 * @since 1.0.0
 */
class {single} extends {base} {

	/**
	 * Table name, without any prefix.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $table_name = '{plural}';

	/**
	 * Table prefix.
	 *
	 * Must match the table's. It also drives get_meta_type(), which decides
	 * the {prefix}_{sing}_id column the meta table is keyed on.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $prefix = '{prefix}';

	/**
	 * Table alias, used in JOINs.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $table_alias = '{alias}';

	/**
	 * Class describing the columns.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $table_schema = '{schema}';

	/**
	 * Singular item name.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $item_name = '{sing}';

	/**
	 * Plural item name.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $item_name_plural = '{plural}';

	/**
	 * What a result row becomes.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $item_shape = '{shape}';

	/**
	 * Cache group.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $cache_group = '{prefix}_{plural}';
{defaults}}
