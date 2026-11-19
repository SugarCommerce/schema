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
 * Count tickets, one figure for each value of a column.
 *
 * One query -- `COUNT(*)` grouped by the column -- over the rows
 * acme_get_tickets() would match with the same arguments.
 *
 * @param array  $args   Query arguments.
 * @param string $column The column to count by.
 *
 * @return array<string, int> How many, by the column's value. Empty when
 *                            nothing matched or there is no such column.
 * @since 1.0.0
 */
function acme_get_ticket_counts( array $args = array(), string $column = 'status' ): array {
	$rows = ( new Ticket() )->query(
		array_merge(
			array_diff_key( $args, array_flip( array( 'count', 'fields', 'offset', 'orderby', 'order' ) ) ),
			array(
				'number'    => 0,
				'groupby'   => $column,
				'aggregate' => array( 'count' => array( 'count', '*' ) ),
			)
		)
	);

	$counts = array();

	// Not grouped -- a column the table does not have -- is one figure and
	// not a row per value, so nothing here.
	foreach ( (array) $rows as $row ) {
		if ( is_array( $row ) && array_key_exists( $column, $row ) ) {
			$counts[ (string) $row[ $column ] ] = (int) ( $row['count'] ?? 0 );
		}
	}

	/**
	 * Filter the ticket counts.
	 *
	 * @param array  $counts How many, by the column's value.
	 * @param array  $args   Query arguments.
	 * @param string $column The column counted by.
	 *
	 * @since 1.0.0
	 */
	$counts = (array) apply_filters( 'acme_get_ticket_counts', $counts, $args, $column );

	return $counts;
}

/**
 * One figure over the tickets a query matches: a sum, an average, the
 * smallest or largest value, or how many values a column has.
 *
 * One query, over the rows acme_get_tickets() would match with the same
 * arguments. With `groupby` among them, one figure for each value of that
 * column: money is summed by currency.
 *
 * @param string $function `sum`, `avg`, `min`, `max` or `count`.
 * @param string $column   The column (`*` to count rows); `sum` and `avg`
 *                         need a numeric one.
 * @param array  $args     Query arguments, and `groupby` for one figure a
 *                         value.
 *
 * @return int|float|string|array<string, int|float|string> Nought when
 *                                                          nothing matched,
 *                                                          or the function
 *                                                          or column is not
 *                                                          one there is.
 * @since 1.0.0
 */
function acme_get_ticket_stats( string $function, string $column, array $args = array() ) {
	$function = strtolower( $function );
	$groupby  = (string) ( $args['groupby'] ?? '' );
	$query    = new Ticket();
	$value    = '' === $groupby ? 0 : array();

	// Refused here and not left to the query: an aggregate it cannot build
	// is dropped, and what is left is an ordinary query for every row.
	$known = in_array( $function, array( 'sum', 'avg', 'min', 'max', 'count' ), true );

	if ( $known && ! ( 'count' === $function && '*' === $column ) ) {
		$found = $query->get_column_by( array( 'name' => $column ) );
		$known = $found && ( ! in_array( $function, array( 'sum', 'avg' ), true ) || $found->is_numeric() );
	}

	if ( $known ) {
		$result = $query->query(
			array_merge(
				array_diff_key( $args, array_flip( array( 'count', 'fields', 'offset', 'orderby', 'order' ) ) ),
				array(
					'number'    => 0,
					'aggregate' => array( 'value' => array( $function, $column ) ),
				)
			)
		);

		$figure = static function ( $raw ) {
			return is_numeric( $raw ) ? $raw + 0 : ( null === $raw ? 0 : (string) $raw );
		};

		if ( '' === $groupby ) {
			$value = is_array( $result ) && array_key_exists( 'value', $result ) ? $figure( $result['value'] ) : 0;
		} else {
			foreach ( (array) $result as $row ) {
				if ( is_array( $row ) && array_key_exists( $groupby, $row ) ) {
					$value[ (string) $row[ $groupby ] ] = $figure( $row['value'] ?? null );
				}
			}
		}
	}

	/**
	 * Filter a figure over tickets.
	 *
	 * @param int|float|string|array $value    The figure, or figures by `groupby`.
	 * @param string                 $function `sum`, `avg`, `min`, `max` or `count`.
	 * @param string                 $column   The column.
	 * @param array                  $args     Query arguments.
	 *
	 * @since 1.0.0
	 */
	$value = apply_filters( 'acme_get_ticket_stats', $value, $function, $column, $args );

	return $value;
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

/**
 * Delete a meta key from every ticket.
 *
 * Runs WordPress's delete_metadata() in delete-all mode, so the delete hooks
 * fire and every affected ticket's meta cache is cleared.
 *
 * @param string $key Meta key.
 *
 * @return bool False when no ticket had the key.
 * @since 1.0.0
 */
function acme_delete_ticket_meta_by_key( string $key ): bool {
	return delete_metadata( 'acme_ticket', 0, $key, '', true );
}
