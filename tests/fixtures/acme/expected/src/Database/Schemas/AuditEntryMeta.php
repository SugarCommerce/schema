<?php
/**
 * Columns for the audit_entrymeta table.
 *
 * @package     Acme\Database\Schemas
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

namespace Acme\Database\Schemas;

use Acme\Vendor\BerlinDB\Database\Kern\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Columns for the audit_entrymeta table.
 *
 * Generated from bin/schema.json -- edit that and re-run
 * bin/generate-components.py rather than changing this by hand.
 *
 * @since 1.0.0
 */
class AuditEntryMeta extends Schema {

	/**
	 * Columns.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public $columns = array(

		array(
			'name'      => 'meta_id',
			'type'      => 'bigint',
			'length'    => '20',
			'unsigned'  => true,
			'sortable'  => true,
			'cache_key' => true,
			'extra'     => 'auto_increment',
		),
		array(
			'name'     => 'acme_audit_entry_id',
			'type'     => 'bigint',
			'length'   => '20',
			'unsigned' => true,
			'default'  => '0',
		),
		array(
			'name'       => 'meta_key',
			'type'       => 'varchar',
			'length'     => '191',
			'searchable' => true,
			'allow_null' => true,
		),
		array(
			'name'       => 'meta_value',
			'type'       => 'longtext',
			'searchable' => true,
			'allow_null' => true,
		),

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

		array(
			'name'    => 'primary',
			'columns' => array( 'meta_id' ),
			'type'    => 'primary',
		),
		array(
			'name'    => 'acme_audit_entry_id',
			'columns' => array( 'acme_audit_entry_id' ),
		),
		array(
			'name'    => 'meta_key',
			'columns' => array( 'meta_key' ),
		),

	);
}
