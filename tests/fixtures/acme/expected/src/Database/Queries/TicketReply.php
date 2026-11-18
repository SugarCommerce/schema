<?php
/**
 * Querying ticket_replies.
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
 * Reads ticket_replies.
 *
 * @since 1.0.0
 */
class TicketReply extends Query {

	/**
	 * Table name, without any prefix.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $table_name = 'ticket_replies';

	/**
	 * Table prefix.
	 *
	 * Must match the table's. It also drives get_meta_type(), which decides
	 * the acme_ticket_reply_id column the meta table is keyed on.
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
	protected $table_alias = 'tr';

	/**
	 * Class describing the columns.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $table_schema = '\\Acme\\Database\\Schemas\\TicketReplies';

	/**
	 * Singular item name.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $item_name = 'ticket_reply';

	/**
	 * Plural item name.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $item_name_plural = 'ticket_replies';

	/**
	 * What a result row becomes.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $item_shape = '\\Acme\\Database\\Rows\\TicketReply';

	/**
	 * Cache group.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $cache_group = 'acme_ticket_replies';

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
		'token'   => 'acme_make_token',
		'user_id' => 'get_current_user_id',
	);
}
