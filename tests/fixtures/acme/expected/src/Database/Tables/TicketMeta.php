<?php
/**
 * Arbitrary metadata for a ticket.
 *
 * @package     Acme\Database\Tables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

namespace Acme\Database\Tables;

use Acme\Vendor\BerlinDB\Database\Kern\Table;

defined( 'ABSPATH' ) || exit;

/**
 * Arbitrary metadata for a ticket.
 *
 * @since 1.0.0
 */
final class TicketMeta extends Table {

	/**
	 * Table name, without any prefix.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $name = 'ticketmeta';

	/**
	 * Table prefix, applied between $wpdb->prefix and the name.
	 *
	 * Without this BerlinDB defaults to an empty string and the table lands
	 * as wp_products rather than wp_acme_products -- generic enough to
	 * collide with anything. It also drives the Query's meta type.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $prefix = 'acme';

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
	protected $schema = '\\Acme\\Database\\Schemas\\TicketMeta';

	/**
	 * Schema version.
	 *
	 * Written by the generator, and raised by it whenever this table's
	 * columns or indexes change in bin/schema.json. BerlinDB compares it
	 * with the stored wpdb_{table}_version option; when this is higher it
	 * adds the columns and indexes an existing table is missing, so an
	 * upgrade needs no routine of its own.
	 *
	 * @var mixed
	 * @since 1.0.0
	 */
	protected $version = 202611150;

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
