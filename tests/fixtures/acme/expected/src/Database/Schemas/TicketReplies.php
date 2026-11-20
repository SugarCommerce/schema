<?php
/**
 * One answer on a ticket.
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
 * One answer on a ticket.
 *
 * Generated from bin/schema.json -- edit that and re-run
 * bin/generate-components.py rather than changing this by hand.
 *
 * @since 1.0.0
 */
class TicketReplies extends Schema {

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
			'name'     => 'ticket_id',
			'type'     => 'bigint',
			'length'   => '20',
			'unsigned' => true,
			'default'  => '0',
			'validate' => 'acme_sanitize_id',
		),
		array(
			'name'     => 'content',
			'type'     => 'text',
			'validate' => 'acme_sanitize_html',
		),
		array(
			'name'     => 'status',
			'type'     => 'varchar',
			'length'   => '20',
			'default'  => 'sent',
			'validate' => array( self::class, 'shape_status' ),
		),
		array(
			'name'     => 'token',
			'type'     => 'char',
			'length'   => '64',
			'default'  => '',
			'validate' => array( self::class, 'shape_token' ),
		),
		array(
			'name'     => 'user_id',
			'type'     => 'bigint',
			'length'   => '20',
			'unsigned' => true,
			'default'  => '0',
			'validate' => 'acme_sanitize_id',
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
			'name'    => 'token',
			'columns' => array( 'token' ),
			'unique'  => true,
		),
		array(
			'name'    => 'ticket_id',
			'columns' => array( 'ticket_id' ),
		),
		array(
			'name'    => 'date_created',
			'columns' => array( 'date_created' ),
		),

	);

	/**
	 * The `status` column's shape: acme_sanitize_key(), told how much the column holds.
	 *
	 * @param mixed $value What is about to be written.
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	public static function shape_status( $value ) {
		return acme_sanitize_key( $value, 20 );
	}

	/**
	 * The `token` column's shape: acme_sanitize_text(), told how much the column holds.
	 *
	 * @param mixed $value What is about to be written.
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	public static function shape_token( $value ) {
		return acme_sanitize_text( $value, 64 );
	}
}
