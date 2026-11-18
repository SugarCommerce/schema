<?php
/**
 * The public API for tickets.
 *
 * @package     Acme
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

use Acme\Database\Queries\Ticket;
use Acme\Database\Rows\Ticket as TicketRow;

defined( 'ABSPATH' ) || exit;

/**
 * Get a ticket by id.
 *
 * @param int $id The ticket id.
 *
 * @return TicketRow|false The ticket, or false when there is not one.
 * @since 1.0.0
 */
function acme_get_ticket( int $id = 0 ) {
	$item = $id > 0
		? ( new Ticket() )->get_item( $id )
		: false;

	/**
	 * Filter a ticket as it is read.
	 *
	 * @param TicketRow|false $item The ticket.
	 * @param int             $id   The id asked for.
	 *
	 * @since 1.0.0
	 */
	$item = apply_filters( 'acme_get_ticket', $item, $id );

	return $item;
}

/**
 * Get a ticket by any unique column.
 *
 * @param string $field Column name.
 * @param mixed  $value Value to match.
 *
 * @return TicketRow|false
 * @since 1.0.0
 */
function acme_get_ticket_by( string $field = '', $value = '' ) {
	$item = ( '' !== $field && '' !== $value && null !== $value )
		? ( new Ticket() )->get_item_by( $field, $value )
		: false;

	/**
	 * Filter a ticket looked up by column.
	 *
	 * @param TicketRow|false $item  The ticket.
	 * @param string          $field Column name.
	 * @param mixed           $value Value matched.
	 *
	 * @since 1.0.0
	 */
	$item = apply_filters( 'acme_get_ticket_by', $item, $field, $value );

	return $item;
}

/**
 * Get one field from a ticket, without hydrating the whole row.
 *
 * @param int    $id    The ticket id.
 * @param string $field Column name.
 *
 * @return mixed|false
 * @since 1.0.0
 */
function acme_get_ticket_field( int $id = 0, string $field = '' ) {
	$item = acme_get_ticket( $id );

	return ( $item && isset( $item->{$field} ) )
		? $item->{$field}
		: false;
}

/**
 * Query tickets.
 *
 * @param array $args Query arguments, as understood by BerlinDB.
 *
 * @return array<int, TicketRow>
 * @since 1.0.0
 */
function acme_get_tickets( array $args = array() ): array {
	/**
	 * Filter the arguments before tickets are queried.
	 *
	 * @param array $args Query arguments.
	 *
	 * @since 1.0.0
	 */
	$args = apply_filters( 'acme_get_tickets_args', $args );

	// 'number' is left alone when already set: a caller asking for all of
	// them has to say so, but a caller that said nothing gets a bounded
	// query rather than the whole table.
	$defaults = array( 'number' => ACME_DEFAULT_QUERY_NUMBER );
	$args     = wp_parse_args( $args, $defaults );

	$items = ( new Ticket() )->query( $args );

	/**
	 * Filter the tickets returned.
	 *
	 * @param array $items The tickets.
	 * @param array $args  Query arguments.
	 *
	 * @since 1.0.0
	 */
	$items = (array) apply_filters( 'acme_get_tickets', $items, $args );

	return $items;
}

/**
 * Count tickets.
 *
 * @param array $args Query arguments.
 *
 * @return int
 * @since 1.0.0
 */
function acme_count_tickets( array $args = array() ): int {
	$args  = array_merge(
		$args,
		array(
			'count'  => true,
			'number' => 0,
		)
	);
	$count = (int) ( new Ticket() )->query( $args );

	/**
	 * Filter the ticket count.
	 *
	 * @param int   $count The count.
	 * @param array $args  Query arguments.
	 *
	 * @since 1.0.0
	 */
	$count = (int) apply_filters( 'acme_count_tickets', $count, $args );

	return $count;
}

/**
 * Add a ticket.
 *
 * @param array $data Column values.
 *
 * @return int|false The new id, or false when it could not be written.
 * @since 1.0.0
 */
function acme_add_ticket( array $data = array() ) {
	/**
	 * Filter the data before a ticket is created.
	 *
	 * @param array $data Column values.
	 *
	 * @since 1.0.0
	 */
	$data = apply_filters( 'acme_add_ticket_data', $data );

	if ( empty( $data ) ) {
		return false;
	}

	/**
	 * Fires before a ticket is created.
	 *
	 * @param array $data Column values.
	 *
	 * @since 1.0.0
	 */
	do_action( 'acme_before_add_ticket', $data );

	$id = ( new Ticket() )->add_item( $data );

	if ( empty( $id ) ) {
		return false;
	}

	/**
	 * Fires once a ticket has been created.
	 *
	 * @param int   $id   The new id.
	 * @param array $data Column values.
	 *
	 * @since 1.0.0
	 */
	do_action( 'acme_add_ticket', $id, $data );

	return (int) $id;
}

/**
 * Update a ticket.
 *
 * @param int   $id   The ticket id.
 * @param array $data Column values to change.
 *
 * @return bool
 * @since 1.0.0
 */
function acme_update_ticket( int $id = 0, array $data = array() ): bool {
	if ( $id <= 0 ) {
		return false;
	}

	/**
	 * Filter the data before a ticket is updated.
	 *
	 * @param array $data Column values.
	 * @param int   $id   The ticket id.
	 *
	 * @since 1.0.0
	 */
	$data = apply_filters( 'acme_update_ticket_data', $data, $id );

	if ( empty( $data ) ) {
		return false;
	}

	/**
	 * Fires before a ticket is updated.
	 *
	 * @param int   $id   The ticket id.
	 * @param array $data Column values.
	 *
	 * @since 1.0.0
	 */
	do_action( 'acme_before_update_ticket', $id, $data );

	$updated = (bool) ( new Ticket() )->update_item( $id, $data );

	if ( $updated ) {
		/**
		 * Fires once a ticket has been updated.
		 *
		 * @param int   $id   The ticket id.
		 * @param array $data Column values.
		 *
		 * @since 1.0.0
		 */
		do_action( 'acme_update_ticket', $id, $data );
	}

	return $updated;
}

/**
 * Delete a ticket.
 *
 * @param int $id The ticket id.
 *
 * @return bool
 * @since 1.0.0
 */
function acme_delete_ticket( int $id = 0 ): bool {
	if ( $id <= 0 ) {
		return false;
	}

	/**
	 * Fires before a ticket is deleted, while it can still be read.
	 *
	 * @param int $id The ticket id.
	 *
	 * @since 1.0.0
	 */
	do_action( 'acme_before_delete_ticket', $id );

	$deleted = (bool) ( new Ticket() )->delete_item( $id );

	if ( $deleted ) {
		/**
		 * Fires once a ticket has been deleted.
		 *
		 * @param int $id The id it had.
		 *
		 * @since 1.0.0
		 */
		do_action( 'acme_delete_ticket', $id );
	}

	return $deleted;
}

/**
 * Add a meta value to a ticket.
 *
 * @param int    $id     The ticket id.
 * @param string $key    Meta key.
 * @param mixed  $value  Meta value.
 * @param bool   $unique Whether to fail if the key already exists.
 *
 * @return int|false
 * @since 1.0.0
 */
function acme_add_ticket_meta( int $id, string $key, $value, bool $unique = false ) {
	return add_metadata( 'acme_ticket', $id, $key, $value, $unique );
}

/**
 * Read meta from a ticket.
 *
 * @param int    $id     The ticket id.
 * @param string $key    Meta key. Empty returns everything.
 * @param bool   $single Whether to return a single value.
 *
 * @return mixed
 * @since 1.0.0
 */
function acme_get_ticket_meta( int $id, string $key = '', bool $single = false ) {
	return get_metadata( 'acme_ticket', $id, $key, $single );
}

/**
 * Update meta on a ticket.
 *
 * @param int    $id    The ticket id.
 * @param string $key   Meta key.
 * @param mixed  $value Meta value.
 * @param mixed  $prev  Only update rows matching this previous value.
 *
 * @return int|bool
 * @since 1.0.0
 */
function acme_update_ticket_meta( int $id, string $key, $value, $prev = '' ) {
	return update_metadata( 'acme_ticket', $id, $key, $value, $prev );
}

/**
 * Delete meta from a ticket.
 *
 * @param int    $id    The ticket id.
 * @param string $key   Meta key.
 * @param mixed  $value Only delete rows matching this value.
 *
 * @return bool
 * @since 1.0.0
 */
function acme_delete_ticket_meta( int $id, string $key, $value = '' ): bool {
	return delete_metadata( 'acme_ticket', $id, $key, $value );
}
