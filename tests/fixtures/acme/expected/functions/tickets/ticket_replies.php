<?php
/**
 * The public API for ticket replies.
 *
 * @package     Acme
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

use Acme\Database\Queries\TicketReply;
use Acme\Database\Rows\TicketReply as TicketReplyRow;

defined( 'ABSPATH' ) || exit;

/**
 * Get a ticket reply by id.
 *
 * @param int $id The ticket reply id.
 *
 * @return TicketReplyRow|false The ticket reply, or false when there is not one.
 * @since 1.0.0
 */
function acme_get_ticket_reply( int $id = 0 ) {
	$item = $id > 0
		? ( new TicketReply() )->get_item( $id )
		: false;

	/**
	 * Filter a ticket reply as it is read.
	 *
	 * @param TicketReplyRow|false $item The ticket reply.
	 * @param int             $id   The id asked for.
	 *
	 * @since 1.0.0
	 */
	$item = apply_filters( 'acme_get_ticket_reply', $item, $id );

	return $item;
}

/**
 * Get a ticket reply by any unique column.
 *
 * @param string $field Column name.
 * @param mixed  $value Value to match.
 *
 * @return TicketReplyRow|false
 * @since 1.0.0
 */
function acme_get_ticket_reply_by( string $field = '', $value = '' ) {
	$item = ( '' !== $field && '' !== $value && null !== $value )
		? ( new TicketReply() )->get_item_by( $field, $value )
		: false;

	/**
	 * Filter a ticket reply looked up by column.
	 *
	 * @param TicketReplyRow|false $item  The ticket reply.
	 * @param string          $field Column name.
	 * @param mixed           $value Value matched.
	 *
	 * @since 1.0.0
	 */
	$item = apply_filters( 'acme_get_ticket_reply_by', $item, $field, $value );

	return $item;
}

/**
 * Get one field from a ticket reply, without hydrating the whole row.
 *
 * @param int    $id    The ticket reply id.
 * @param string $field Column name.
 *
 * @return mixed|false
 * @since 1.0.0
 */
function acme_get_ticket_reply_field( int $id = 0, string $field = '' ) {
	$item = acme_get_ticket_reply( $id );

	return ( $item && isset( $item->{$field} ) )
		? $item->{$field}
		: false;
}

/**
 * Query ticket replies.
 *
 * @param array $args Query arguments, as understood by BerlinDB.
 *
 * @return array<int, TicketReplyRow>
 * @since 1.0.0
 */
function acme_get_ticket_replies( array $args = array() ): array {
	/**
	 * Filter the arguments before ticket replies are queried.
	 *
	 * @param array $args Query arguments.
	 *
	 * @since 1.0.0
	 */
	$args = apply_filters( 'acme_get_ticket_replies_args', $args );

	// 'number' is left alone when already set: a caller asking for all of
	// them has to say so, but a caller that said nothing gets a bounded
	// query rather than the whole table.
	$defaults = array( 'number' => ACME_DEFAULT_QUERY_NUMBER );
	$args     = wp_parse_args( $args, $defaults );

	$items = ( new TicketReply() )->query( $args );

	/**
	 * Filter the ticket replies returned.
	 *
	 * @param array $items The ticket replies.
	 * @param array $args  Query arguments.
	 *
	 * @since 1.0.0
	 */
	$items = (array) apply_filters( 'acme_get_ticket_replies', $items, $args );

	return $items;
}

/**
 * Count ticket replies.
 *
 * @param array $args Query arguments.
 *
 * @return int
 * @since 1.0.0
 */
function acme_count_ticket_replies( array $args = array() ): int {
	$args  = array_merge(
		$args,
		array(
			'count'  => true,
			'number' => 0,
		)
	);
	$count = (int) ( new TicketReply() )->query( $args );

	/**
	 * Filter the ticket reply count.
	 *
	 * @param int   $count The count.
	 * @param array $args  Query arguments.
	 *
	 * @since 1.0.0
	 */
	$count = (int) apply_filters( 'acme_count_ticket_replies', $count, $args );

	return $count;
}

/**
 * Add a ticket reply.
 *
 * @param array $data Column values.
 *
 * @return int|false The new id, or false when it could not be written.
 * @since 1.0.0
 */
function acme_add_ticket_reply( array $data = array() ) {
	/**
	 * Filter the data before a ticket reply is created.
	 *
	 * @param array $data Column values.
	 *
	 * @since 1.0.0
	 */
	$data = apply_filters( 'acme_add_ticket_reply_data', $data );

	if ( empty( $data ) ) {
		return false;
	}

	/**
	 * Fires before a ticket reply is created.
	 *
	 * @param array $data Column values.
	 *
	 * @since 1.0.0
	 */
	do_action( 'acme_before_add_ticket_reply', $data );

	$id = ( new TicketReply() )->add_item( $data );

	if ( empty( $id ) ) {
		return false;
	}

	/**
	 * Fires once a ticket reply has been created.
	 *
	 * @param int   $id   The new id.
	 * @param array $data Column values.
	 *
	 * @since 1.0.0
	 */
	do_action( 'acme_add_ticket_reply', $id, $data );

	return (int) $id;
}

/**
 * Update a ticket reply.
 *
 * @param int   $id   The ticket reply id.
 * @param array $data Column values to change.
 *
 * @return bool
 * @since 1.0.0
 */
function acme_update_ticket_reply( int $id = 0, array $data = array() ): bool {
	if ( $id <= 0 ) {
		return false;
	}

	/**
	 * Filter the data before a ticket reply is updated.
	 *
	 * @param array $data Column values.
	 * @param int   $id   The ticket reply id.
	 *
	 * @since 1.0.0
	 */
	$data = apply_filters( 'acme_update_ticket_reply_data', $data, $id );

	if ( empty( $data ) ) {
		return false;
	}

	/**
	 * Fires before a ticket reply is updated.
	 *
	 * @param int   $id   The ticket reply id.
	 * @param array $data Column values.
	 *
	 * @since 1.0.0
	 */
	do_action( 'acme_before_update_ticket_reply', $id, $data );

	$updated = (bool) ( new TicketReply() )->update_item( $id, $data );

	if ( $updated ) {
		/**
		 * Fires once a ticket reply has been updated.
		 *
		 * @param int   $id   The ticket reply id.
		 * @param array $data Column values.
		 *
		 * @since 1.0.0
		 */
		do_action( 'acme_update_ticket_reply', $id, $data );
	}

	return $updated;
}

/**
 * Delete a ticket reply.
 *
 * @param int $id The ticket reply id.
 *
 * @return bool
 * @since 1.0.0
 */
function acme_delete_ticket_reply( int $id = 0 ): bool {
	if ( $id <= 0 ) {
		return false;
	}

	/**
	 * Fires before a ticket reply is deleted, while it can still be read.
	 *
	 * @param int $id The ticket reply id.
	 *
	 * @since 1.0.0
	 */
	do_action( 'acme_before_delete_ticket_reply', $id );

	$deleted = (bool) ( new TicketReply() )->delete_item( $id );

	if ( $deleted ) {
		/**
		 * Fires once a ticket reply has been deleted.
		 *
		 * @param int $id The id it had.
		 *
		 * @since 1.0.0
		 */
		do_action( 'acme_delete_ticket_reply', $id );
	}

	return $deleted;
}
