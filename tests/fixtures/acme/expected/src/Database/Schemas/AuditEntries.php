<?php
/**
 * Who changed what, and when.
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
 * Who changed what, and when.
 *
 * Generated from bin/schema.json -- edit that and re-run
 * bin/generate-components.py rather than changing this by hand.
 *
 * @since 1.0.0
 */
class AuditEntries extends Schema {

	/**
	 * Columns.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public $columns = array(

		array(
			'name'      => 'id',
			'type'      => 'bigint',
			'length'    => '20',
			'unsigned'  => true,
			'sortable'  => true,
			'compare'   => true,
			'cache_key' => true,
			'extra'     => 'auto_increment',
		),
		array(
			'name'     => 'object_type',
			'type'     => 'varchar',
			'length'   => '20',
			'default'  => '',
			'validate' => array( self::class, 'shape_object_type' ),
		),
		array(
			'name'     => 'object_id',
			'type'     => 'bigint',
			'length'   => '20',
			'unsigned' => true,
			'default'  => '0',
			'validate' => 'acme_sanitize_id',
		),
		array(
			'name'     => 'total',
			'type'     => 'int',
			'length'   => '11',
			'default'  => '0',
			'validate' => 'acme_sanitize_amount',
		),
		array(
			'name'     => 'date_seen',
			'type'     => 'date',
			'default'  => '',
			'validate' => 'acme_sanitize_text',
		),
		array(
			'name'       => 'date_created',
			'type'       => 'datetime',
			'sortable'   => true,
			'date_query' => true,
			'created'    => true,
			'default'    => '',
		),
		array(
			'name'       => 'date_modified',
			'type'       => 'datetime',
			'sortable'   => true,
			'date_query' => true,
			'modified'   => true,
			'default'    => '',
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
			'columns' => array( 'id' ),
			'type'    => 'primary',
		),
		array(
			'name'    => 'date_created',
			'columns' => array( 'date_created' ),
		),

	);

	/**
	 * The `object_type` column's shape: acme_sanitize_key(), told how much the column holds.
	 *
	 * @param mixed $value What is about to be written.
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	public static function shape_object_type( $value ) {
		return acme_sanitize_key( $value, 20 );
	}
}
