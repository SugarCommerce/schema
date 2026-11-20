<?php
/**
 * A question somebody asked, and everything known about it.
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
 * A question somebody asked, and everything known about it.
 *
 * Generated from bin/schema.json -- edit that and re-run
 * bin/generate-components.py rather than changing this by hand.
 *
 * @since 1.0.0
 */
class Tickets extends Schema {

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
			'name'       => 'title',
			'type'       => 'varchar',
			'length'     => '200',
			'searchable' => true,
			'sortable'   => true,
			'default'    => '',
			'validate'   => array( self::class, 'shape_title' ),
		),
		array(
			'name'       => 'status',
			'type'       => 'varchar',
			'length'     => '20',
			'sortable'   => true,
			'transition' => true,
			'default'    => 'open',
			'validate'   => array( self::class, 'shape_status' ),
		),
		array(
			'name'       => 'urgent',
			'type'       => 'tinyint',
			'length'     => '1',
			'unsigned'   => true,
			'transition' => true,
			'default'    => '0',
			'validate'   => 'acme_sanitize_flag',
		),
		array(
			'name'     => 'customer_id',
			'type'     => 'bigint',
			'length'   => '20',
			'unsigned' => true,
			'default'  => '0',
			'validate' => 'acme_sanitize_id',
		),
		array(
			'name'     => 'reply_count',
			'type'     => 'int',
			'length'   => '11',
			'unsigned' => true,
			'sortable' => true,
			'default'  => '0',
			'validate' => 'acme_sanitize_count',
		),
		array(
			'name'     => 'amount_paid',
			'type'     => 'bigint',
			'length'   => '20',
			'default'  => '0',
			'validate' => 'acme_sanitize_amount',
		),
		array(
			'name'     => 'refund_value',
			'type'     => 'bigint',
			'length'   => '20',
			'default'  => '0',
			'validate' => 'acme_sanitize_amount',
		),
		array(
			'name'     => 'currency',
			'type'     => 'char',
			'length'   => '3',
			'default'  => '',
			'validate' => 'acme_sanitize_currency_code',
		),
		array(
			'name'     => 'country',
			'type'     => 'char',
			'length'   => '2',
			'default'  => '',
			'validate' => 'acme_sanitize_country_code',
		),
		array(
			'name'       => 'email',
			'type'       => 'varchar',
			'length'     => '100',
			'searchable' => true,
			'default'    => '',
			'validate'   => 'acme_sanitize_email',
		),
		array(
			'name'     => 'ip',
			'type'     => 'varchar',
			'length'   => '45',
			'default'  => '',
			'validate' => 'acme_sanitize_ip',
		),
		array(
			'name'     => 'stripe_charge_id',
			'type'     => 'varchar',
			'length'   => '100',
			'default'  => '',
			'validate' => array( self::class, 'shape_stripe_charge_id' ),
		),
		array(
			'name'     => 'body',
			'type'     => 'longtext',
			'validate' => 'acme_sanitize_textarea',
		),
		array(
			'name'     => 'payload',
			'type'     => 'longtext',
			'validate' => 'acme_sanitize_json',
		),
		array(
			'name'     => 'tax_rate',
			'type'     => 'decimal',
			'length'   => '10',
			'scale'    => '4',
			'default'  => '0',
			'validate' => 'acme_sanitize_rate',
		),
		array(
			'name'    => 'weight',
			'type'    => 'decimal',
			'length'  => '10',
			'scale'   => '2',
			'default' => '0',
		),
		array(
			'name'     => 'label_color',
			'type'     => 'varchar',
			'length'   => '7',
			'default'  => '',
			'validate' => 'acme_sanitize_color',
		),
		array(
			'name'     => 'icon',
			'type'     => 'varchar',
			'length'   => '60',
			'default'  => '',
			'validate' => 'acme_sanitize_icon',
		),
		array(
			'name'     => 'path',
			'type'     => 'varchar',
			'length'   => '255',
			'default'  => '',
			'validate' => array( self::class, 'shape_path' ),
		),
		array(
			'name'     => 'sku',
			'type'     => 'varchar',
			'length'   => '40',
			'default'  => '',
			'validate' => 'acme_clean_sku',
		),
		array(
			'name' => 'notes',
			'type' => 'text',
		),
		array(
			'name'     => 'card_last4',
			'type'     => 'char',
			'length'   => '4',
			'default'  => '',
			'validate' => array( self::class, 'shape_card_last4' ),
		),
		array(
			'name'     => 'channel_type',
			'type'     => 'varchar',
			'length'   => '20',
			'default'  => 'email',
			'validate' => array( self::class, 'shape_channel_type' ),
		),
		array(
			'name'     => 'stage',
			'type'     => 'varchar',
			'length'   => '20',
			'default'  => 'new',
			'validate' => array( self::class, 'shape_stage' ),
		),
		array(
			'name'       => 'date_closed',
			'type'       => 'datetime',
			'sortable'   => true,
			'allow_null' => true,
			'compare'    => true,
			'validate'   => 'acme_sanitize_datetime',
		),
		array(
			'name'    => 'uuid',
			'type'    => 'varchar',
			'length'  => '100',
			'uuid'    => true,
			'default' => '',
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
			'name'    => 'customer_id_title',
			'columns' => array( 'customer_id', 'title' ),
			'unique'  => true,
		),
		array(
			'name'    => 'status_date_modified',
			'columns' => array( 'status', 'date_modified' ),
		),
		array(
			'name'    => 'status',
			'columns' => array( 'status' ),
		),
		array(
			'name'    => 'customer_id',
			'columns' => array( 'customer_id' ),
		),
		array(
			'name'    => 'date_created',
			'columns' => array( 'date_created' ),
		),

	);

	/**
	 * The `title` column's shape: acme_sanitize_text(), told how much the column holds.
	 *
	 * @param mixed $value What is about to be written.
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	public static function shape_title( $value ) {
		return acme_sanitize_text( $value, 200 );
	}

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
	 * The `stripe_charge_id` column's shape: acme_sanitize_stripe_id(), told how much the column holds.
	 *
	 * @param mixed $value What is about to be written.
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	public static function shape_stripe_charge_id( $value ) {
		return acme_sanitize_stripe_id( $value, 100 );
	}

	/**
	 * The `path` column's shape: acme_sanitize_path(), told how much the column holds.
	 *
	 * @param mixed $value What is about to be written.
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	public static function shape_path( $value ) {
		return acme_sanitize_path( $value, 255 );
	}

	/**
	 * The `card_last4` column's shape: acme_sanitize_digits(), told how much the column holds.
	 *
	 * @param mixed $value What is about to be written.
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	public static function shape_card_last4( $value ) {
		return acme_sanitize_digits( $value, 4 );
	}

	/**
	 * The `channel_type` column's shape: acme_sanitize_key(), told how much the column holds.
	 *
	 * @param mixed $value What is about to be written.
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	public static function shape_channel_type( $value ) {
		return acme_sanitize_key( $value, 20 );
	}

	/**
	 * The `stage` column's shape: acme_sanitize_key(), told how much the column holds.
	 *
	 * @param mixed $value What is about to be written.
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	public static function shape_stage( $value ) {
		return acme_sanitize_key( $value, 20 );
	}
}
