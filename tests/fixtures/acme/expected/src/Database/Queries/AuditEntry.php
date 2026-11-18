<?php
/**
 * Querying audit_entries.
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
 * Reads audit_entries.
 *
 * @since 1.0.0
 */
class AuditEntry extends Query {

	/**
	 * Table name, without any prefix.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $table_name = 'audit_entries';

	/**
	 * Table prefix.
	 *
	 * Must match the table's. It also drives get_meta_type(), which decides
	 * the acme_audit_entry_id column the meta table is keyed on.
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
	protected $table_alias = 'ae';

	/**
	 * Class describing the columns.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $table_schema = '\\Acme\\Database\\Schemas\\AuditEntries';

	/**
	 * Singular item name.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $item_name = 'audit_entry';

	/**
	 * Plural item name.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $item_name_plural = 'audit_entries';

	/**
	 * What a result row becomes.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $item_shape = '\\Acme\\Database\\Rows\\AuditEntry';

	/**
	 * Cache group.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $cache_group = 'acme_audit_entries';
}
