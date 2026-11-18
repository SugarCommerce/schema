<?php
/**
 * Querying tickets.
 *
 * @package     Acme\Database\Queries
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

namespace Acme\Database\Queries;

use Acme\Database\Query;

defined( 'ABSPATH' ) || exit;

/**
 * Reads tickets.
 *
 * @since 1.0.0
 */
class Ticket extends Query {

	/**
	 * Table name, without any prefix.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $table_name = 'tickets';

	/**
	 * Table prefix.
	 *
	 * Must match the table's. It also drives get_meta_type(), which decides
	 * the acme_ticket_id column the meta table is keyed on.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $prefix = 'acme';

	/**
	 * Table alias, used in JOINs.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $table_alias = 't';

	/**
	 * Class describing the columns.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $table_schema = '\\Acme\\Database\\Schemas\\Tickets';

	/**
	 * Singular item name.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $item_name = 'ticket';

	/**
	 * Plural item name.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $item_name_plural = 'tickets';

	/**
	 * What a result row becomes.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $item_shape = '\\Acme\\Database\\Rows\\Ticket';

	/**
	 * Cache group.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $cache_group = 'acme_tickets';

	/**
	 * Columns a new row is given a value for when the caller names none.
	 *
	 * Column => the function that says what. From "default_function" in
	 * bin/schema.json.
	 *
	 * @var array<string, callable-string>
	 * @since 1.0.0
	 */
	protected $insert_defaults = array(
		'currency' => 'acme_get_default_currency',
	);
}
